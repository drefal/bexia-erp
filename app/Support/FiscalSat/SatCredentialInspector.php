<?php

namespace App\Support\FiscalSat;

use App\Models\SatCompanyCredential;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SatCredentialInspector
{
    public function inspectAndUpdate(SatCompanyCredential $credential): array
    {
        $result = $this->inspect($credential);

        $credential->forceFill([
            'certificate_serial' => $result['certificate_serial'] ?? null,
            'certificate_valid_from' => $result['certificate_valid_from'] ?? null,
            'certificate_valid_to' => $result['certificate_valid_to'] ?? null,
            'credential_status' => ($result['ok'] ?? false) ? 'verified' : 'error',
            'is_enabled' => (bool) ($result['ok'] ?? false),
            'last_verified_at' => now(),
            'last_error_message' => ($result['ok'] ?? false) ? null : ($result['message'] ?? 'No se pudo validar la e.firma.'),
            'metadata' => array_merge($credential->metadata ?? [], [
                'last_inspection' => [
                    'ok' => (bool) ($result['ok'] ?? false),
                    'certificate_ok' => (bool) ($result['certificate_ok'] ?? false),
                    'private_key_ok' => (bool) ($result['private_key_ok'] ?? false),
                    'checked_at' => now()->toDateTimeString(),
                ],
            ]),
        ])->save();

        return $result;
    }

    public function inspect(SatCompanyCredential $credential): array
    {
        try {
            if (! $credential->cer_file_path || ! $credential->key_file_path || ! $credential->password_encrypted) {
                throw new RuntimeException('Falta cargar .cer, .key o contraseña de e.firma.');
            }

            $cerContent = $this->readLocalFile($credential->cer_file_path, 'archivo .cer');
            $keyContent = $this->readLocalFile($credential->key_file_path, 'archivo .key');

            $certificatePem = $this->certificateToPem($cerContent);
            $certificate = openssl_x509_read($certificatePem);

            if ($certificate === false) {
                throw new RuntimeException('No se pudo leer el certificado .cer.');
            }

            $parsed = openssl_x509_parse($certificate);

            if (! is_array($parsed)) {
                throw new RuntimeException('No se pudo interpretar la información del certificado.');
            }

            $password = Crypt::decryptString((string) $credential->password_encrypted);
            $privateKeyPem = $this->privateKeyToPem($keyContent);
            $privateKey = @openssl_pkey_get_private($privateKeyPem, $password);

            if ($privateKey === false) {
                throw new RuntimeException('No se pudo abrir la llave privada .key con la contraseña capturada.');
            }

            $validFrom = isset($parsed['validFrom_time_t'])
                ? Carbon::createFromTimestamp((int) $parsed['validFrom_time_t'])
                : null;

            $validTo = isset($parsed['validTo_time_t'])
                ? Carbon::createFromTimestamp((int) $parsed['validTo_time_t'])
                : null;

            $serial = $parsed['serialNumberHex'] ?? $parsed['serialNumber'] ?? null;

            return [
                'ok' => true,
                'certificate_ok' => true,
                'private_key_ok' => true,
                'certificate_serial' => $serial ? (string) $serial : null,
                'certificate_valid_from' => $validFrom,
                'certificate_valid_to' => $validTo,
                'subject' => $parsed['subject'] ?? [],
                'issuer' => $parsed['issuer'] ?? [],
                'message' => 'e.firma validada correctamente.',
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'certificate_ok' => false,
                'private_key_ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function readLocalFile(string $path, string $label): string
    {
        if (! Storage::disk('local')->exists($path)) {
            throw new RuntimeException('No se encontró el ' . $label . ' en storage privado.');
        }

        $content = Storage::disk('local')->get($path);

        if (! is_string($content) || $content === '') {
            throw new RuntimeException('El ' . $label . ' está vacío.');
        }

        return $content;
    }

    private function certificateToPem(string $content): string
    {
        if (str_contains($content, 'BEGIN CERTIFICATE')) {
            return $content;
        }

        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(base64_encode($content), 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }

    private function privateKeyToPem(string $content): string
    {
        if (str_contains($content, 'BEGIN')) {
            return $content;
        }

        return "-----BEGIN ENCRYPTED PRIVATE KEY-----\n"
            . chunk_split(base64_encode($content), 64, "\n")
            . "-----END ENCRYPTED PRIVATE KEY-----\n";
    }
}
