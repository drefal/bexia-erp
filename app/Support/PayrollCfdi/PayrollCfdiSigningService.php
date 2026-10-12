<?php

namespace App\Support\PayrollCfdi;

use CfdiUtils\CadenaOrigen\DOMBuilder;
use CfdiUtils\XmlResolver\XmlResolver;
use DOMDocument;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use PhpCfdi\Credentials\Credential;
use RuntimeException;
use Throwable;

class PayrollCfdiSigningService
{
    /**
     * Firma localmente un XML CFDI Nómina antes de enviarlo al PAC.
     *
     * NO llama al PAC.
     * NO modifica BD.
     */
    public function sign(object $company, string $xml): array
    {
        if (trim($xml) === '') {
            return $this->failure(
                'No hay XML CFDI Nómina para firmar.'
            );
        }

        if (
            ! str_contains($xml, '<cfdi:Comprobante')
            && ! str_contains($xml, '<Comprobante')
        ) {
            return $this->failure(
                'El contenido no parece ser un CFDI válido.'
            );
        }

        /*
         * BEXIA_V5836_PAYROLL_CFDI_SIGN_REQUIRE_VALID_CSD
         *
         * No basta con que existan rutas:
         * el CSD debe haber pasado previamente por CsdValidator.
         */
        if (
            (string) (
                $company->billing_csd_last_test_status
                ?? ''
            ) !== 'success'
        ) {
            return $this->failure(
                'El CSD de la empresa no está validado correctamente.'
            );
        }

        $certificatePath = trim(
            (string) (
                $company->billing_csd_certificate_path
                ?? ''
            )
        );

        $keyPath = trim(
            (string) (
                $company->billing_csd_key_path
                ?? ''
            )
        );

        $encryptedPassword = (string) (
            $company->billing_csd_password
            ?? ''
        );

        if (
            $certificatePath === ''
            || $keyPath === ''
            || $encryptedPassword === ''
        ) {
            return $this->failure(
                'La empresa no tiene CSD completo.'
            );
        }

        $certificateFullPath = Storage::disk('local')
            ->path($certificatePath);

        $keyFullPath = Storage::disk('local')
            ->path($keyPath);

        if (! is_file($certificateFullPath)) {
            return $this->failure(
                'No se encontró el archivo .cer del CSD.'
            );
        }

        if (! is_file($keyFullPath)) {
            return $this->failure(
                'No se encontró el archivo .key del CSD.'
            );
        }

        try {
            $password = Crypt::decryptString(
                $encryptedPassword
            );
        } catch (Throwable) {
            return $this->failure(
                'La contraseña CSD no se pudo desencriptar.'
            );
        }

        try {
            $credential = Credential::openFiles(
                $certificateFullPath,
                $keyFullPath,
                $password
            );
        } catch (Throwable $e) {
            return $this->failure(
                'No se pudo abrir el CSD para firmar: '
                . $e->getMessage()
            );
        }

        $certificateContent = @file_get_contents(
            $certificateFullPath
        );

        if (
            ! is_string($certificateContent)
            || $certificateContent === ''
        ) {
            return $this->failure(
                'No se pudo leer el certificado CSD.'
            );
        }

        try {
            $serial = (string) $credential
                ->certificate()
                ->serialNumber()
                ->bytes();
        } catch (Throwable) {
            $serial = trim(
                (string) (
                    $company->billing_csd_serial_number
                    ?? ''
                )
            );
        }

        if ($serial === '') {
            return $this->failure(
                'No se pudo obtener el número de certificado CSD.'
            );
        }

        $certificateBase64 = preg_replace(
            '/\s+/',
            '',
            base64_encode($certificateContent)
        ) ?: '';

        if ($certificateBase64 === '') {
            return $this->failure(
                'No se pudo preparar el certificado CSD.'
            );
        }

        $doc = new DOMDocument(
            '1.0',
            'UTF-8'
        );

        if (! @$doc->loadXML($xml)) {
            return $this->failure(
                'No se pudo cargar el XML CFDI Nómina.'
            );
        }

        $root = $doc->documentElement;

        if (! $root) {
            return $this->failure(
                'No se encontró cfdi:Comprobante.'
            );
        }

        /*
         * Estos atributos deben formar parte de la cadena original.
         * Sello queda vacío hasta después de firmar.
         */
        $root->setAttribute(
            'Sello',
            ''
        );

        $root->setAttribute(
            'NoCertificado',
            $serial
        );

        $root->setAttribute(
            'Certificado',
            $certificateBase64
        );

        $unsignedXml = $doc->saveXML();

        if (
            ! is_string($unsignedXml)
            || trim($unsignedXml) === ''
        ) {
            return $this->failure(
                'No se pudo generar XML previo a firma.'
            );
        }

        try {
            $resolver = new XmlResolver();

            $location = $resolver
                ->resolveCadenaOrigenLocation(
                    '4.0'
                );

            $builder = new DOMBuilder();

            $cadenaOriginal = trim(
                (string) $builder->build(
                    $unsignedXml,
                    $location
                )
            );
        } catch (Throwable $e) {
            return $this->failure(
                'No se pudo generar cadena original CFDI: '
                . $e->getMessage()
            );
        }

        if ($cadenaOriginal === '') {
            return $this->failure(
                'La cadena original CFDI quedó vacía.'
            );
        }

        try {
            $rawSignature = $credential->sign(
                $cadenaOriginal
            );

            if (
                ! $credential->verify(
                    $cadenaOriginal,
                    $rawSignature
                )
            ) {
                return $this->failure(
                    'El sello CFDI generado no pudo verificarse.'
                );
            }
        } catch (Throwable $e) {
            return $this->failure(
                'No se pudo firmar el CFDI Nómina: '
                . $e->getMessage()
            );
        }

        $sello = base64_encode(
            $rawSignature
        );

        if ($sello === '') {
            return $this->failure(
                'El sello CFDI quedó vacío.'
            );
        }

        $root->setAttribute(
            'Sello',
            $sello
        );

        $signedXml = $doc->saveXML();

        if (
            ! is_string($signedXml)
            || trim($signedXml) === ''
        ) {
            return $this->failure(
                'No se pudo generar el XML CFDI firmado.'
            );
        }

        /*
         * Guardia final:
         * nunca entregar al caller XML sin los tres campos.
         */
        $check = new DOMDocument();

        if (! @$check->loadXML($signedXml)) {
            return $this->failure(
                'El XML firmado no pudo volver a cargarse.'
            );
        }

        $signedRoot = $check->documentElement;

        foreach (
            [
                'Sello',
                'NoCertificado',
                'Certificado',
            ] as $attribute
        ) {
            if (
                ! $signedRoot
                || trim(
                    $signedRoot->getAttribute(
                        $attribute
                    )
                ) === ''
            ) {
                return $this->failure(
                    "El XML firmado no contiene {$attribute}."
                );
            }
        }

        return [
            'success' => true,
            'message' => 'CFDI Nómina firmado localmente.',
            'xml' => $signedXml,
            'cadena_original' => $cadenaOriginal,
            'certificate_serial' => $serial,
            'sello_preview' => substr(
                $sello,
                0,
                24
            ) . '...',
        ];
    }

    public function assertSigned(string $xml): void
    {
        $doc = new DOMDocument();

        if (! @$doc->loadXML($xml)) {
            throw new RuntimeException(
                'El XML previo al PAC no es válido.'
            );
        }

        $root = $doc->documentElement;

        foreach (
            [
                'Sello',
                'NoCertificado',
                'Certificado',
            ] as $attribute
        ) {
            if (
                ! $root
                || trim(
                    $root->getAttribute(
                        $attribute
                    )
                ) === ''
            ) {
                throw new RuntimeException(
                    "CFDI Nómina bloqueado: falta {$attribute} antes del PAC."
                );
            }
        }
    }

    private function failure(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'xml' => null,
            'cadena_original' => null,
            'certificate_serial' => null,
        ];
    }
}
