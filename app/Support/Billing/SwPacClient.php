<?php

namespace App\Support\Billing;

use App\Models\Company;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class SwPacClient
{
    public function testAuthentication(Company $company): array
    {
        $username = trim((string) ($company->billing_pac_username ?? ''));
        $passwordEncrypted = (string) ($company->billing_pac_password ?? '');
        $testEnv = (bool) ($company->billing_pac_test_env ?? true);

        if ($username === '' || $passwordEncrypted === '') {
            return $this->persistResult($company, false, 'Faltan usuario y/o contraseña del PAC.');
        }

        try {
            $password = Crypt::decryptString($passwordEncrypted);
        } catch (Throwable $e) {
            return $this->persistResult($company, false, 'La contraseña del PAC no se pudo desencriptar. Captúrala de nuevo.');
        }

        if (trim($password) === '') {
            return $this->persistResult($company, false, 'La contraseña del PAC está vacía.');
        }

        $loginUrl = $testEnv
            ? 'https://services.test.sw.com.mx/security/authenticate'
            : 'https://services.sw.com.mx/security/authenticate';

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'user' => $username,
                    'password' => $password,
                    'Cache-Control' => 'no-cache',
                ])
                ->send('POST', $loginUrl, [
                    'body' => '',
                ]);

            $json = $response->json();

            if (! $response->successful()) {
                $message = $this->safeMessage(
                    data_get($json, 'messageDetail')
                    ?: data_get($json, 'message')
                    ?: ('HTTP ' . $response->status())
                );

                return $this->persistResult($company, false, $message);
            }

            $token = data_get($json, 'data.token');

            if (! $token) {
                return $this->persistResult($company, false, 'SW respondió correctamente, pero no regresó data.token.');
            }

            return $this->persistResult($company, true, 'Conexión correcta con SW. Token recibido.');
        } catch (Throwable $e) {
            return $this->persistResult($company, false, $this->safeMessage($e->getMessage()));
        }
    }

    public function endpoints(bool $testEnv): array
    {
        return $testEnv
            ? [
                'login_url' => 'https://services.test.sw.com.mx/security/authenticate',
                'sign_url' => 'https://services.test.sw.com.mx/cfdi33/stamp/v3/b64',
                'cancel_url' => 'https://services.test.sw.com.mx/cfdi33/cancel/csd',
            ]
            : [
                'login_url' => 'https://services.sw.com.mx/security/authenticate',
                'sign_url' => 'https://services.sw.com.mx/cfdi33/stamp/v3/b64',
                'cancel_url' => 'https://services.sw.com.mx/cfdi33/cancel/csd',
            ];
    }

    private function persistResult(Company $company, bool $success, string $message): array
    {
        $status = $success ? 'success' : 'error';
        $message = $this->safeMessage($message);

        DB::table('companies')
            ->where('id', $company->id)
            ->update([
                'billing_pac_last_test_status' => $status,
                'billing_pac_last_test_message' => $message,
                'billing_pac_last_test_at' => now(),
                'updated_at' => now(),
            ]);

        return [
            'success' => $success,
            'status' => $status,
            'message' => $message,
        ];
    }

    private function safeMessage(string $message): string
    {
        $message = trim($message);

        $message = preg_replace('/(password|contraseña|passwd|secret|token)\s*[:=]\s*\S+/i', '$1=***', $message) ?: $message;

        return mb_substr($message, 0, 1000);
    }
}
