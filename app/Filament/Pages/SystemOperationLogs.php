<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemOperationLogs extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Administración';
    protected static ?string $navigationLabel = 'Logs operativos';
    protected static ?string $title = 'Logs operativos';
    protected static ?string $slug = 'logs-operativos';
    protected static ?int $navigationSort = 990;
    protected static string $view = 'filament.pages.system-operation-logs';

    public ?string $selectedLog = null;
    public ?string $restoreConfirmation = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return static::isSuperAdminUser($user);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected static function isSuperAdminUser($user): bool
    {
        $email = strtolower((string) ($user->email ?? ''));

        if (in_array($email, [
            'admin@bexiaerp.com',
            'a.zuniga@grupolinea7.com',
        ], true)) {
            return true;
        }

        foreach (['is_super_admin', 'super_admin', 'is_global_admin', 'is_admin'] as $field) {
            if (isset($user->{$field}) && (bool) $user->{$field}) {
                return true;
            }
        }

        if (method_exists($user, 'hasRole')) {
            foreach (['super_admin', 'superadmin', 'Super Admin', 'Administrador global', 'admin_global'] as $role) {
                try {
                    if ($user->hasRole($role)) {
                        return true;
                    }
                } catch (\Throwable) {
                    //
                }
            }
        }

        return false;
    }

    public function isDevEnvironment(): bool
    {
        return config('app.url') === 'https://dev.bexiaerp.com'
            || env('APP_URL') === 'https://dev.bexiaerp.com';
    }

    public function isProdEnvironment(): bool
    {
        return config('app.url') === 'https://app.bexiaerp.com'
            || env('APP_URL') === 'https://app.bexiaerp.com';
    }

    public function getEnvironmentLabelProperty(): string
    {
        if ($this->isDevEnvironment()) {
            return 'DEV';
        }

        if ($this->isProdEnvironment()) {
            return 'PROD';
        }

        return 'DESCONOCIDO';
    }

    public function refreshBackupIndex(): void
    {
        $requestId = now()->format('Ymd_His') . '_' . Str::lower(Str::random(6));

        $this->writeRequest([
            'id' => $requestId,
            'type' => 'refresh_prod_backups_index',
            'created_at' => now()->toIso8601String(),
            'created_by_user_id' => auth()->id(),
            'created_by_email' => auth()->user()?->email,
        ]);

        Notification::make()
            ->title('Solicitud enviada')
            ->body('El servidor actualizará la lista de respaldos en menos de un minuto.')
            ->success()
            ->send();
    }

    public function requestRestore(string $packagePath): void
    {
        abort_unless($this->isDevEnvironment(), 403);

        if (trim((string) $this->restoreConfirmation) !== 'CLONAR PROD A DEV') {
            Notification::make()
                ->title('Confirmación requerida')
                ->body('Escribe exactamente: CLONAR PROD A DEV')
                ->danger()
                ->send();

            return;
        }

        if (! str_starts_with($packagePath, '/root/bexia_incoming_backups/prod/daily/')) {
            Notification::make()
                ->title('Respaldo inválido')
                ->body('La ruta del respaldo no es permitida.')
                ->danger()
                ->send();

            return;
        }

        $requestId = now()->format('Ymd_His') . '_' . Str::lower(Str::random(6));

        $this->writeRequest([
            'id' => $requestId,
            'type' => 'restore_prod_backup_to_dev',
            'package_path' => $packagePath,
            'created_at' => now()->toIso8601String(),
            'created_by_user_id' => auth()->id(),
            'created_by_email' => auth()->user()?->email,
        ]);

        $this->restoreConfirmation = null;

        Notification::make()
            ->title('Restauración solicitada')
            ->body('El servidor ejecutará la restauración en menos de un minuto. Revisa el estado y los logs.')
            ->success()
            ->send();
    }

    protected function writeRequest(array $payload): void
    {
        $dir = storage_path('app/private/system-ops/requests');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $name = 'pending_' . ($payload['id'] ?? now()->format('Ymd_His')) . '.json';

        file_put_contents(
            $dir . DIRECTORY_SEPARATOR . $name,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function getProdBackupsProperty(): array
    {
        $path = storage_path('app/private/system-ops/state/prod-backups.json');

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode(file_get_contents($path) ?: '{}', true);

        return is_array($data['items'] ?? null) ? $data['items'] : [];
    }

    public function getLastOperationProperty(): array
    {
        $path = storage_path('app/private/system-ops/state/last-operation.json');

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode(file_get_contents($path) ?: '{}', true);

        return is_array($data) ? $data : [];
    }

    public function getLogs(): array
    {
        $dir = storage_path('app/private/system-ops/logs');

        if (! is_dir($dir)) {
            return [];
        }

        $files = collect(glob($dir . '/*') ?: [])
            ->filter(fn (string $path): bool => is_file($path))
            ->filter(fn (string $path): bool => in_array(pathinfo($path, PATHINFO_EXTENSION), ['txt', 'log'], true))
            ->map(function (string $path): array {
                return [
                    'name' => basename($path),
                    'size' => filesize($path) ?: 0,
                    'modified_at' => filemtime($path) ?: 0,
                ];
            })
            ->sortByDesc('modified_at')
            ->values()
            ->all();

        if (! $this->selectedLog && count($files) > 0) {
            $this->selectedLog = $files[0]['name'];
        }

        return $files;
    }

    public function selectLog(string $name): void
    {
        $this->selectedLog = basename($name);
    }

    public function getSelectedLogContentProperty(): string
    {
        if (! $this->selectedLog) {
            return 'Selecciona un log.';
        }

        $path = $this->safePath($this->selectedLog);

        if (! $path || ! is_file($path)) {
            return 'El log seleccionado no existe.';
        }

        return mb_substr(file_get_contents($path) ?: '', -60000);
    }

    public function downloadLog(string $name): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        $path = $this->safePath($name);

        abort_unless($path && is_file($path), 404);

        return response()->streamDownload(function () use ($path): void {
            readfile($path);
        }, basename($path), [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    protected function safePath(string $name): ?string
    {
        $base = storage_path('app/private/system-ops/logs');
        $name = basename($name);

        if (! preg_match('/^[A-Za-z0-9._-]+\.(txt|log)$/', $name)) {
            return null;
        }

        $path = $base . DIRECTORY_SEPARATOR . $name;
        $realBase = realpath($base);
        $realPath = realpath($path);

        if (! $realBase || ! $realPath || ! str_starts_with($realPath, $realBase)) {
            return null;
        }

        return $realPath;
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}
