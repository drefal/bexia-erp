<?php

namespace App\Support\Billing;

use App\Models\Company;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SwPacClient
{
    public function testAuthentication(Company $company): array
    {
        $auth = $this->authenticate($company, persist: true);

        if (! $auth['success']) {
            return $auth;
        }

        return $this->persistResult($company, true, 'Conexión correcta con SW. Token recibido.');
    }

    public function authenticate(Company $company, bool $persist = false): array
    {
        $username = trim((string) ($company->billing_pac_username ?? ''));
        $passwordEncrypted = (string) ($company->billing_pac_password ?? '');
        $testEnv = (bool) ($company->billing_pac_test_env ?? true);

        if ($username === '' || $passwordEncrypted === '') {
            return $persist
                ? $this->persistResult($company, false, 'Faltan usuario y/o contraseña del PAC.')
                : $this->fail('Faltan usuario y/o contraseña del PAC.');
        }

        try {
            $password = Crypt::decryptString($passwordEncrypted);
        } catch (Throwable $e) {
            return $persist
                ? $this->persistResult($company, false, 'La contraseña del PAC no se pudo desencriptar. Captúrala de nuevo.')
                : $this->fail('La contraseña del PAC no se pudo desencriptar. Captúrala de nuevo.');
        }

        if (trim($password) === '') {
            return $persist
                ? $this->persistResult($company, false, 'La contraseña del PAC está vacía.')
                : $this->fail('La contraseña del PAC está vacía.');
        }

        $loginUrl = $this->endpoints($testEnv)['login_url'];

        try {
            $response = Http::timeout(25)
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
                $message = $this->responseMessage($response, $json);

                return $persist
                    ? $this->persistResult($company, false, $message)
                    : $this->fail($message, ['http_status' => $response->status(), 'response' => $this->safeResponse($json)]);
            }

            $token = data_get($json, 'data.token');

            if (! $token) {
                $message = 'SW respondió correctamente, pero no regresó data.token.';

                return $persist
                    ? $this->persistResult($company, false, $message)
                    : $this->fail($message, ['http_status' => $response->status(), 'response' => $this->safeResponse($json)]);
            }

            return [
                'success' => true,
                'status' => 'success',
                'message' => 'Token SW recibido.',
                'token' => $token,
                'environment' => $testEnv ? 'test' : 'production',
                'base_url' => $this->endpoints($testEnv)['base_url'],
            ];
        } catch (Throwable $e) {
            $message = $this->safeMessage($e->getMessage());

            return $persist
                ? $this->persistResult($company, false, $message)
                : $this->fail($message);
        }
    }

    public function stampSignedXml(Company $company, string $xml): array
    {
        $testEnv = (bool) ($company->billing_pac_test_env ?? true);

        $guard = $this->guardEnvironment($testEnv);

        if (! $guard['success']) {
            return $guard;
        }

        if (trim($xml) === '') {
            return $this->fail('No hay XML CFDI para timbrar.');
        }

        if (! str_contains($xml, '<cfdi:Comprobante') && ! str_contains($xml, '<Comprobante')) {
            return $this->fail('El contenido no parece ser un XML CFDI válido.');
        }

        $auth = $this->authenticate($company);

        if (! $auth['success']) {
            return $auth;
        }

        $endpoint = $this->endpoints($testEnv)['stamp_multipart_url'];

        try {
            $response = Http::timeout(60)
                ->withToken((string) $auth['token'])
                ->attach('xml', $xml, 'cfdi.xml', [
                    'Content-Type' => 'text/xml',
                ])
                ->post($endpoint);

            $json = $response->json();

            if (! $response->successful()) {
                return $this->fail($this->responseMessage($response, $json), [
                    'http_status' => $response->status(),
                    'endpoint' => $endpoint,
                    'response' => $this->safeResponse($json),
                    'request_id' => $this->requestId($response, $json),
                ]);
            }

            $stampedXml = $this->extractStampedXml($json, (string) $response->body());
            $uuid = $this->extractUuid($json, $stampedXml);

            if ($stampedXml === '') {
                return $this->fail('SW respondió correctamente, pero no se encontró XML timbrado en la respuesta.', [
                    'http_status' => $response->status(),
                    'endpoint' => $endpoint,
                    'response' => $this->safeResponse($json),
                    'request_id' => $this->requestId($response, $json),
                ]);
            }

            if ($uuid === '') {
                return $this->fail('SW respondió XML timbrado, pero no se pudo leer UUID del TimbreFiscalDigital.', [
                    'http_status' => $response->status(),
                    'endpoint' => $endpoint,
                    'response' => $this->safeResponse($json),
                    'request_id' => $this->requestId($response, $json),
                ]);
            }

            return [
                'success' => true,
                'status' => 'success',
                'message' => 'CFDI timbrado correctamente por SW.',
                'uuid' => $uuid,
                'xml' => $stampedXml,
                'http_status' => $response->status(),
                'endpoint' => $endpoint,
                'environment' => $testEnv ? 'test' : 'production',
                'request_id' => $this->requestId($response, $json),
                'response_meta' => $this->safeResponse($json),
            ];
        } catch (Throwable $e) {
            return $this->fail($this->safeMessage($e->getMessage()), [
                'endpoint' => $endpoint,
                'environment' => $testEnv ? 'test' : 'production',
            ]);
        }
    }


    public function cancelCfdi(Company $company, string $uuid, string $reasonCode, ?string $replacementUuid = null): array
    {
        $testEnv = (bool) ($company->billing_pac_test_env ?? true);

        $guard = $this->guardEnvironment($testEnv);

        if (! $guard['success']) {
            return $guard;
        }

        $uuid = strtoupper(trim($uuid));
        $reasonCode = str_pad(trim($reasonCode), 2, '0', STR_PAD_LEFT);
        $replacementUuid = $replacementUuid !== null ? strtoupper(trim($replacementUuid)) : null;

        if (! $this->isUuid($uuid)) {
            return $this->fail('UUID CFDI inválido para cancelación.');
        }

        if (! in_array($reasonCode, ['01', '02', '03', '04'], true)) {
            return $this->fail('Motivo SAT de cancelación inválido.');
        }

        if ($reasonCode === '01' && ! $this->isUuid((string) $replacementUuid)) {
            return $this->fail('El motivo 01 requiere folioSustitucion UUID válido.');
        }

        if ($reasonCode !== '01') {
            $replacementUuid = null;
        }

        $auth = $this->authenticate($company);

        if (! $auth['success']) {
            return $auth;
        }

        $csd = $this->csdCancellationPayload($company);

        if (! $csd['success']) {
            return $csd;
        }

        $rfc = strtoupper(trim((string) ($company->tax_id ?? $company->rfc ?? $company->billing_csd_rfc ?? '')));

        if ($rfc === '') {
            return $this->fail('La empresa no tiene RFC para cancelar CFDI.');
        }

        $endpoint = $this->endpoints($testEnv)['cancel_url'];

        $payload = [
            'rfc' => $rfc,
            'b64Cer' => $csd['b64Cer'],
            'b64Key' => $csd['b64Key'],
            'password' => $csd['password'],
            'uuid' => $uuid,
            'motivo' => $reasonCode,
        ];

        if ($replacementUuid) {
            $payload['folioSustitucion'] = $replacementUuid;
        }

        try {
            $response = Http::timeout(60)
                ->withToken((string) $auth['token'])
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);

            $json = $response->json();

            if (! $response->successful()) {
                return $this->fail($this->responseMessage($response, $json), [
                    'http_status' => $response->status(),
                    'endpoint' => $endpoint,
                    'environment' => $testEnv ? 'test' : 'production',
                    'response' => $this->safeResponse($json),
                    'request_id' => $this->requestId($response, $json),
                ]);
            }

            $swStatus = strtolower((string) data_get($json, 'status', ''));
            $cancelCode = $this->extractCancelCode($json, $uuid);

            if ($swStatus !== 'success') {
                return $this->fail($this->responseMessage($response, $json), [
                    'http_status' => $response->status(),
                    'endpoint' => $endpoint,
                    'environment' => $testEnv ? 'test' : 'production',
                    'cancel_code' => $cancelCode,
                    'response' => $this->safeResponse($json),
                    'request_id' => $this->requestId($response, $json),
                ]);
            }

            $finalStatus = in_array((string) $cancelCode, ['201', '202'], true)
                ? 'cancelled'
                : 'cancel_requested';

            $message = $finalStatus === 'cancelled'
                ? 'CFDI cancelado correctamente con SW.'
                : 'Solicitud de cancelación enviada a SW. Revisa estatus de cancelación.';

            return [
                'success' => true,
                'status' => $finalStatus,
                'message' => $message,
                'uuid' => $uuid,
                'reason_code' => $reasonCode,
                'replacement_uuid' => $replacementUuid,
                'cancel_code' => $cancelCode,
                'http_status' => $response->status(),
                'endpoint' => $endpoint,
                'environment' => $testEnv ? 'test' : 'production',
                'request_id' => $this->requestId($response, $json),
                'response_meta' => $this->safeResponse($json),
            ];
        } catch (Throwable $e) {
            return $this->fail($this->safeMessage($e->getMessage()), [
                'endpoint' => $endpoint,
                'environment' => $testEnv ? 'test' : 'production',
            ]);
        }
    }

    private function csdCancellationPayload(Company $company): array
    {
        $cerPath = trim((string) ($company->billing_csd_certificate_path ?? ''));
        $keyPath = trim((string) ($company->billing_csd_key_path ?? ''));

        if ($cerPath === '' || $keyPath === '') {
            return $this->fail('Faltan archivos CSD .cer y/o .key para cancelar.');
        }

        $cerFullPath = Storage::disk('local')->path($cerPath);
        $keyFullPath = Storage::disk('local')->path($keyPath);

        if (! is_file($cerFullPath)) {
            return $this->fail('No existe el archivo .cer del CSD.');
        }

        if (! is_file($keyFullPath)) {
            return $this->fail('No existe el archivo .key del CSD.');
        }

        $password = $this->csdPassword($company);

        if ($password === '') {
            return $this->fail('Falta contraseña del CSD para cancelar.');
        }

        $cerBinary = file_get_contents($cerFullPath);
        $keyBinary = file_get_contents($keyFullPath);

        if ($cerBinary === false || $cerBinary === '') {
            return $this->fail('No se pudo leer el archivo .cer del CSD.');
        }

        if ($keyBinary === false || $keyBinary === '') {
            return $this->fail('No se pudo leer el archivo .key del CSD.');
        }

        return [
            'success' => true,
            'status' => 'success',
            'message' => 'CSD listo para cancelación.',
            'b64Cer' => base64_encode($cerBinary),
            'b64Key' => base64_encode($keyBinary),
            'password' => $password,
        ];
    }

    private function csdPassword(Company $company): string
    {
        foreach ([
            'billing_csd_key_password',
            'billing_csd_password',
            'csd_key_password',
            'csd_password',
        ] as $field) {
            $value = (string) ($company->{$field} ?? '');

            if (trim($value) === '') {
                continue;
            }

            try {
                return trim(Crypt::decryptString($value));
            } catch (Throwable $e) {
                return trim($value);
            }
        }

        return '';
    }

    private function extractCancelCode(mixed $json, string $uuid): ?string
    {
        $uuid = strtoupper(trim($uuid));

        foreach ([
            'data.uuid.'.$uuid,
            'data.uuid.'.strtolower($uuid),
            'data.uuid',
            'uuid.'.$uuid,
            'uuid.'.strtolower($uuid),
            'uuid',
            'data.codigo',
            'data.code',
            'codigo',
            'code',
        ] as $path) {
            $value = data_get($json, $path);

            if (is_array($value)) {
                $value = reset($value);
            }

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/i', trim($value));
    }


    public function endpoints(bool $testEnv): array
    {
        $base = $testEnv
            ? 'https://services.test.sw.com.mx'
            : 'https://services.sw.com.mx';

        return [
            'base_url' => $base,
            'login_url' => $base . '/security/authenticate',
            'stamp_multipart_url' => $base . '/cfdi33/stamp/v4',
            'stamp_multipart_url_v3' => $base . '/cfdi33/stamp/v3',
            'stamp_json_b64_url' => $base . '/cfdi33/stamp/json/v4/b64',
            'issue_json_b64_url' => $base . '/cfdi33/issue/json/v4/b64',
            'cancel_url' => $base . '/cfdi33/cancel/csd',
        ];
    }

    private function guardEnvironment(bool $testEnv): array
    {
        $appUrl = strtolower((string) config('app.url'));
        $appEnv = strtolower((string) config('app.env'));
        $isDevRuntime = str_contains($appUrl, 'dev.bexiaerp.com')
            || str_contains($appUrl, 'staging')
            || str_contains($appEnv, 'local')
            || str_contains($appEnv, 'dev')
            || str_contains($appEnv, 'testing');

        if ($isDevRuntime && ! $testEnv) {
            return $this->fail(
                'Bloqueado por seguridad: DEV no puede timbrar contra SW producción. Activa ambiente de pruebas en la empresa o prueba el timbrado desde producción después del merge.'
            );
        }

        return [
            'success' => true,
            'status' => 'ok',
            'message' => 'Ambiente permitido.',
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

    private function fail(string $message, array $meta = []): array
    {
        return [
            'success' => false,
            'status' => 'error',
            'message' => $this->safeMessage($message),
            'meta' => $meta,
        ];
    }

    private function responseMessage(Response $response, mixed $json): string
    {
        return $this->safeMessage(
            (string) (
                data_get($json, 'messageDetail')
                ?: data_get($json, 'message')
                ?: data_get($json, 'messageDetail.0')
                ?: data_get($json, 'error')
                ?: ('HTTP ' . $response->status())
            )
        );
    }

    private function requestId(Response $response, mixed $json): ?string
    {
        return data_get($json, 'data.requestId')
            ?: data_get($json, 'requestId')
            ?: data_get($json, 'request_id')
            ?: $response->header('x-request-id')
            ?: $response->header('x-correlation-id');
    }

    private function extractStampedXml(mixed $json, string $rawBody): string
    {
        foreach ([
            'data.cfdi',
            'data.xml',
            'data.content',
            'data',
            'xml',
            'cfdi',
        ] as $path) {
            $value = data_get($json, $path);

            if (is_string($value) && trim($value) !== '') {
                $xml = $this->normalizeXmlPayload($value);

                if ($xml !== '') {
                    return $xml;
                }
            }
        }

        return $this->normalizeXmlPayload($rawBody);
    }

    private function normalizeXmlPayload(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (str_contains($value, '<cfdi:Comprobante') || str_contains($value, '<Comprobante')) {
            return $value;
        }

        $decoded = base64_decode($value, true);

        if (is_string($decoded)) {
            $decoded = trim($decoded);

            if (str_contains($decoded, '<cfdi:Comprobante') || str_contains($decoded, '<Comprobante')) {
                return $decoded;
            }
        }

        return '';
    }

    private function extractUuid(mixed $json, string $xml): string
    {
        foreach ([
            'data.uuid',
            'data.UUID',
            'data.tfd.uuid',
            'data.tfd.UUID',
            'uuid',
            'UUID',
        ] as $path) {
            $value = data_get($json, $path);

            if (is_string($value) && trim($value) !== '') {
                return strtoupper(trim($value));
            }
        }

        if ($xml !== '') {
            $dom = new \DOMDocument();

            libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($xml);
            libxml_clear_errors();

            if ($loaded) {
                $xpath = new \DOMXPath($dom);
                $xpath->registerNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

                $node = $xpath->query('//tfd:TimbreFiscalDigital')->item(0);

                if ($node instanceof \DOMElement) {
                    return strtoupper((string) $node->getAttribute('UUID'));
                }
            }
        }

        return '';
    }

    private function safeResponse(mixed $json): array|string|null
    {
        if (! is_array($json)) {
            return is_string($json) ? $this->safeMessage($json) : null;
        }

        $encoded = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($encoded)) {
            return null;
        }

        $encoded = preg_replace('/("token"\s*:\s*")[^"]+(")/i', '$1***$2', $encoded) ?: $encoded;
        $encoded = preg_replace('/("password"\s*:\s*")[^"]+(")/i', '$1***$2', $encoded) ?: $encoded;
        $encoded = preg_replace('/("sello"\s*:\s*")[^"]+(")/i', '$1***$2', $encoded) ?: $encoded;
        $encoded = preg_replace('/("certificado"\s*:\s*")[^"]+(")/i', '$1***$2', $encoded) ?: $encoded;

        $decoded = json_decode($encoded, true);

        return is_array($decoded) ? $decoded : mb_substr($encoded, 0, 2000);
    }

    private function safeMessage(string $message): string
    {
        $message = trim($message);

        $message = preg_replace('/(password|contraseña|passwd|secret|token)\s*[:=]\s*\S+/i', '$1=***', $message) ?: $message;

        return mb_substr($message, 0, 1000);
    }
}
