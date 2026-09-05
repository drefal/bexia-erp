<?php

namespace App\Support\Attendance;

use App\Models\AttendanceTerminal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceTerminalPairingService
{
    public const PAIRING_TTL_SECONDS = 600;

    public function createPairingRequest(array $device = []): array
    {
        $requestId = (string) Str::uuid();
        $exchangeSecret = bin2hex(random_bytes(32));
        $exchangeSecretHash = hash('sha256', $exchangeSecret);
        $code = $this->uniquePairingCode();
        $expiresAt = now()->addSeconds(self::PAIRING_TTL_SECONDS);

        $pending = [
            'state' => 'pending',
            'request_id' => $requestId,
            'pairing_code' => $code,
            'exchange_secret_hash' => $exchangeSecretHash,
            'device' => $this->sanitizeDevice($device),
            'created_at' => now()->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        Cache::put($this->codeKey($code), $pending, $expiresAt);
        Cache::put($this->requestKey($requestId), $pending, $expiresAt);

        return [
            'request_id' => $requestId,
            'exchange_secret' => $exchangeSecret,
            'pairing_code' => $code,
            'expires_in' => self::PAIRING_TTL_SECONDS,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function completePairing(AttendanceTerminal $terminal, string $code): array
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $code)) {
            throw ValidationException::withMessages([
                'pairing_code' => 'Captura exactamente los 6 digitos que aparecen en la tablet.',
            ]);
        }

        if (! $terminal->active) {
            throw ValidationException::withMessages([
                'pairing_code' => 'La terminal esta inactiva. Actívala antes de vincularla.',
            ]);
        }

        if ($terminal->isBlocked()) {
            throw ValidationException::withMessages([
                'pairing_code' => 'La terminal esta bloqueada. Desbloqueala antes de vincularla.',
            ]);
        }

        $pending = Cache::get($this->codeKey($code));

        if (! is_array($pending) || ($pending['state'] ?? null) !== 'pending') {
            throw ValidationException::withMessages([
                'pairing_code' => 'El codigo no existe o ya vencio. Genera uno nuevo desde la tablet.',
            ]);
        }

        $requestId = (string) ($pending['request_id'] ?? '');
        $exchangeSecretHash = (string) ($pending['exchange_secret_hash'] ?? '');

        if (! Str::isUuid($requestId) || strlen($exchangeSecretHash) !== 64) {
            Cache::forget($this->codeKey($code));

            throw ValidationException::withMessages([
                'pairing_code' => 'La solicitud de vinculacion no es valida. Genera un codigo nuevo.',
            ]);
        }

        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);
        $device = is_array($pending['device'] ?? null) ? $pending['device'] : [];
        $expiresAt = now()->addSeconds(self::PAIRING_TTL_SECONDS);

        $terminal->forceFill([
            'pairing_code_hash' => hash('sha256', $code),
            'pairing_expires_at' => $expiresAt,
        ])->save();

        Cache::put($this->requestKey($requestId), [
            'state' => 'paired',
            'request_id' => $requestId,
            'terminal_id' => $terminal->getKey(),
            'terminal_uuid' => (string) $terminal->uuid,
            'pairing_code_hash' => hash('sha256', $code),
            'exchange_secret_hash' => $exchangeSecretHash,
            'encrypted_token' => Crypt::encryptString($plainToken),
            'device' => $device,
            'paired_at' => now()->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        Cache::forget($this->codeKey($code));

        return [
            'terminal_id' => $terminal->getKey(),
            'terminal_uuid' => (string) $terminal->uuid,
            'request_id' => $requestId,
        ];
    }

    public function pairingStatus(string $requestId, string $exchangeSecret, Request $request): array
    {
        if (! Str::isUuid($requestId) || strlen($exchangeSecret) !== 64) {
            return ['state' => 'expired'];
        }

        $payload = Cache::get($this->requestKey($requestId));

        if (! is_array($payload)) {
            return ['state' => 'expired'];
        }

        $expectedSecretHash = (string) ($payload['exchange_secret_hash'] ?? '');
        $providedSecretHash = hash('sha256', $exchangeSecret);

        if ($expectedSecretHash === '' || ! hash_equals($expectedSecretHash, $providedSecretHash)) {
            return ['state' => 'expired'];
        }

        if (($payload['state'] ?? null) === 'pending') {
            return [
                'state' => 'pending',
                'expires_at' => $payload['expires_at'] ?? null,
            ];
        }

        if (($payload['state'] ?? null) !== 'paired') {
            return ['state' => 'expired'];
        }

        $terminal = AttendanceTerminal::query()
            ->with(['company:id,name', 'branch:id,name'])
            ->find($payload['terminal_id'] ?? null);

        if (! $terminal || ! $terminal->active || $terminal->isBlocked()) {
            return [
                'state' => 'blocked',
                'message' => 'La terminal fue bloqueada o desactivada.',
            ];
        }

        try {
            $plainToken = Crypt::decryptString((string) ($payload['encrypted_token'] ?? ''));
        } catch (\Throwable) {
            return ['state' => 'expired'];
        }

        $device = is_array($payload['device'] ?? null) ? $payload['device'] : [];

        $terminal->forceFill([
            'token_hash' => hash('sha256', $plainToken),
            'pairing_code_hash' => null,
            'pairing_expires_at' => null,
            'device_name' => $device['device_name'] ?? $terminal->device_name,
            'device_model' => $device['device_model'] ?? $terminal->device_model,
            'platform' => $device['platform'] ?? $terminal->platform,
            'app_version' => $device['app_version'] ?? $terminal->app_version,
            'last_seen_at' => now(),
            'last_ip_address' => $request->ip(),
            'last_user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ])->save();

        return [
            'state' => 'paired',
            'terminal_uuid' => (string) $terminal->uuid,
            'terminal_token' => $plainToken,
            'terminal' => $this->terminalPayload($terminal),
        ];
    }

    public function authenticateTerminal(string $uuid, string $token, Request $request): ?AttendanceTerminal
    {
        if (! Str::isUuid($uuid) || strlen($token) < 32) {
            return null;
        }

        $terminal = AttendanceTerminal::query()
            ->with(['company:id,name', 'branch:id,name'])
            ->where('uuid', $uuid)
            ->first();

        if (! $terminal || ! $terminal->token_hash) {
            return null;
        }

        if (! hash_equals((string) $terminal->token_hash, hash('sha256', $token))) {
            return null;
        }

        $terminal->forceFill([
            'last_seen_at' => now(),
            'last_ip_address' => $request->ip(),
            'last_user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ])->save();

        return $terminal;
    }

    public function terminalPayload(AttendanceTerminal $terminal): array
    {
        return [
            'uuid' => (string) $terminal->uuid,
            'code' => (string) $terminal->code,
            'name' => (string) $terminal->name,
            'active' => (bool) $terminal->active,
            'blocked' => $terminal->isBlocked(),
            'company' => $terminal->company ? [
                'id' => $terminal->company->getKey(),
                'name' => (string) $terminal->company->name,
            ] : null,
            'branch' => $terminal->branch ? [
                'id' => $terminal->branch->getKey(),
                'name' => (string) $terminal->branch->name,
            ] : null,
            'last_seen_at' => $terminal->last_seen_at?->toIso8601String(),
        ];
    }

    protected function uniquePairingCode(): string
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            if (! Cache::has($this->codeKey($code))) {
                return $code;
            }
        }

        throw new \RuntimeException('No fue posible generar un codigo de vinculacion unico.');
    }

    protected function sanitizeDevice(array $device): array
    {
        $clean = [];

        foreach (['device_name', 'device_model', 'platform', 'app_version'] as $key) {
            $value = trim((string) ($device[$key] ?? ''));

            if ($value !== '') {
                $clean[$key] = substr($value, 0, 255);
            }
        }

        return $clean;
    }

    protected function codeKey(string $code): string
    {
        return 'attendance:kiosk:pairing:code:' . $code;
    }

    protected function requestKey(string $requestId): string
    {
        return 'attendance:kiosk:pairing:request:' . $requestId;
    }
}
