<?php

namespace App\Support\Billing;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CsdValidator
{
    public function validate(Company $company): array
    {
        $certificatePath = (string) ($company->billing_csd_certificate_path ?? '');
        $keyPath = (string) ($company->billing_csd_key_path ?? '');
        $encryptedPassword = (string) ($company->billing_csd_password ?? '');

        if ($certificatePath === '' || $keyPath === '' || $encryptedPassword === '') {
            return $this->persistResult($company, false, 'Faltan certificado .cer, llave .key o contraseña CSD.');
        }

        $certificateFullPath = Storage::disk('local')->path($certificatePath);
        $keyFullPath = Storage::disk('local')->path($keyPath);

        if (! is_file($certificateFullPath)) {
            return $this->persistResult($company, false, 'No se encontró el archivo .cer del CSD.');
        }

        if (! is_file($keyFullPath)) {
            return $this->persistResult($company, false, 'No se encontró el archivo .key del CSD.');
        }

        try {
            $password = Crypt::decryptString($encryptedPassword);
        } catch (Throwable $e) {
            return $this->persistResult($company, false, 'La contraseña CSD no se pudo desencriptar. Captúrala nuevamente.');
        }

        if (trim($password) === '') {
            return $this->persistResult($company, false, 'La contraseña CSD está vacía.');
        }

        $cert = $this->readCertificate($certificateFullPath);

        if (! $cert['success']) {
            return $this->persistResult($company, false, $cert['message']);
        }

        $key = $this->validatePrivateKey($keyFullPath, $password);

        if (! $key['success']) {
            return $this->persistResult($company, false, $key['message'], $cert);
        }

        $now = now();

        if ($cert['valid_from'] && $now->lt($cert['valid_from'])) {
            return $this->persistResult($company, false, 'El certificado CSD todavía no está vigente.', $cert);
        }

        if ($cert['valid_to'] && $now->gt($cert['valid_to'])) {
            return $this->persistResult($company, false, 'El certificado CSD está vencido.', $cert);
        }

        $companyRfc = strtoupper(preg_replace('/[^A-Z0-9&Ñ]/iu', '', (string) ($company->tax_id ?? '')));
        $certRfc = strtoupper(preg_replace('/[^A-Z0-9&Ñ]/iu', '', (string) ($cert['rfc'] ?? '')));

        if ($companyRfc !== '' && $certRfc !== '' && $companyRfc !== $certRfc) {
            return $this->persistResult(
                $company,
                false,
                "El RFC del CSD ({$certRfc}) no coincide con el RFC de la empresa ({$companyRfc}).",
                $cert
            );
        }

        $message = 'CSD validado correctamente.';

        if ($certRfc === '') {
            $message .= ' No se pudo extraer RFC del certificado; verifica manualmente que corresponda a la empresa.';
        }

        return $this->persistResult($company, true, $message, $cert);
    }

    private function readCertificate(string $path): array
    {
        $commands = [
            'openssl x509 -inform DER -in ' . escapeshellarg($path) . ' -noout -serial -dates -subject -issuer',
            'openssl x509 -inform PEM -in ' . escapeshellarg($path) . ' -noout -serial -dates -subject -issuer',
        ];

        $output = null;
        $lastError = '';

        foreach ($commands as $command) {
            $result = $this->run($command);

            if ($result['code'] === 0) {
                $output = trim($result['stdout']);
                break;
            }

            $lastError = trim($result['stderr'] ?: $result['stdout']);
        }

        if (! $output) {
            return [
                'success' => false,
                'message' => 'No se pudo leer el certificado .cer. Verifica que sea un CSD válido. ' . mb_substr($lastError, 0, 300),
            ];
        }

        $serial = $this->match('/serial=([^\n\r]+)/i', $output);
        $notBefore = $this->match('/notBefore=([^\n\r]+)/i', $output);
        $notAfter = $this->match('/notAfter=([^\n\r]+)/i', $output);
        $subject = $this->match('/subject=([^\n\r]+)/i', $output);

        $validFrom = $notBefore ? $this->parseOpenSslDate($notBefore) : null;
        $validTo = $notAfter ? $this->parseOpenSslDate($notAfter) : null;

        $rfc = '';

        foreach ([
            '/x500UniqueIdentifier\s*=\s*([A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3})/iu',
            '/uniqueIdentifier\s*=\s*([A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3})/iu',
            '/\/UID=([A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3})/iu',
            '/([A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3})/iu',
        ] as $pattern) {
            if (preg_match($pattern, $subject, $m)) {
                $rfc = strtoupper($m[1]);
                break;
            }
        }

        return [
            'success' => true,
            'message' => 'Certificado leído correctamente.',
            'serial' => $serial,
            'rfc' => $rfc,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'subject' => $subject,
        ];
    }

    private function validatePrivateKey(string $path, string $password): array
    {
        $tmp = tempnam(storage_path('app'), 'csd_key_');

        if (! $tmp) {
            return [
                'success' => false,
                'message' => 'No se pudo crear archivo temporal para validar la llave privada.',
            ];
        }

        $commands = [
            'openssl pkcs8 -inform DER -in ' . escapeshellarg($path) . ' -passin ' . escapeshellarg('pass:' . $password) . ' -out ' . escapeshellarg($tmp) . ' -outform PEM',
            'openssl pkcs8 -inform PEM -in ' . escapeshellarg($path) . ' -passin ' . escapeshellarg('pass:' . $password) . ' -out ' . escapeshellarg($tmp) . ' -outform PEM',
            'openssl rsa -inform DER -in ' . escapeshellarg($path) . ' -passin ' . escapeshellarg('pass:' . $password) . ' -check -noout',
            'openssl rsa -inform PEM -in ' . escapeshellarg($path) . ' -passin ' . escapeshellarg('pass:' . $password) . ' -check -noout',
        ];

        $lastError = '';

        foreach ($commands as $command) {
            $result = $this->run($command);

            if ($result['code'] === 0) {
                @unlink($tmp);

                return [
                    'success' => true,
                    'message' => 'Llave privada validada correctamente.',
                ];
            }

            $lastError = trim($result['stderr'] ?: $result['stdout']);
        }

        @unlink($tmp);

        return [
            'success' => false,
            'message' => 'No se pudo abrir la llave privada con la contraseña CSD capturada. ' . mb_substr($lastError, 0, 300),
        ];
    }

    private function persistResult(Company $company, bool $success, string $message, array $cert = []): array
    {
        $status = $success ? 'success' : 'error';

        DB::table('companies')
            ->where('id', $company->id)
            ->update([
                'billing_csd_last_test_status' => $status,
                'billing_csd_last_test_message' => mb_substr($message, 0, 1000),
                'billing_csd_last_test_at' => now(),
                'billing_csd_serial_number' => $cert['serial'] ?? null,
                'billing_csd_rfc' => $cert['rfc'] ?? null,
                'billing_csd_valid_from' => $cert['valid_from'] ?? null,
                'billing_csd_valid_to' => $cert['valid_to'] ?? null,
                'updated_at' => now(),
            ]);

        return [
            'success' => $success,
            'status' => $status,
            'message' => $message,
            'certificate' => [
                'serial' => $cert['serial'] ?? null,
                'rfc' => $cert['rfc'] ?? null,
                'valid_from' => isset($cert['valid_from']) && $cert['valid_from'] ? $cert['valid_from']->toDateTimeString() : null,
                'valid_to' => isset($cert['valid_to']) && $cert['valid_to'] ? $cert['valid_to']->toDateTimeString() : null,
            ],
        ];
    }

    private function run(string $command): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            return [
                'code' => 1,
                'stdout' => '',
                'stderr' => 'No se pudo ejecutar openssl.',
            ];
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';

        fclose($pipes[1]);
        fclose($pipes[2]);

        $code = proc_close($process);

        return [
            'code' => $code,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    private function match(string $pattern, string $subject): ?string
    {
        if (preg_match($pattern, $subject, $m)) {
            return trim((string) ($m[1] ?? ''));
        }

        return null;
    }

    private function parseOpenSslDate(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (Throwable $e) {
            return null;
        }
    }
}
