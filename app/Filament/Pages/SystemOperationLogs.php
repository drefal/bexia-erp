<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemOperationLogs extends Page
{

    public string $serverMonitorAlertEmails = '';

    public ?string $serverMonitorAlertFeedback = null;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Administración';
    protected static ?string $navigationLabel = 'Logs operativos';
    protected static ?string $title = 'Logs operativos';
    protected static ?string $slug = 'logs-operativos';
    protected static ?int $navigationSort = 990;
    protected static string $view = 'filament.pages.system-operation-logs';

    public ?string $selectedLog = null;
    public ?string $backupConfirmation = null;

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

    public function isProdEnvironment(): bool
    {
        return config('app.url') === 'https://app.bexiaerp.com'
            || env('APP_URL') === 'https://app.bexiaerp.com';
    }

    public function isDevEnvironment(): bool
    {
        return config('app.url') === 'https://dev.bexiaerp.com'
            || env('APP_URL') === 'https://dev.bexiaerp.com';
    }

    public function getEnvironmentLabelProperty(): string
    {
        if ($this->isProdEnvironment()) {
            return 'PROD';
        }

        if ($this->isDevEnvironment()) {
            return 'DEV';
        }

        return 'DESCONOCIDO';
    }

    public function requestManualBackup(): void
    {
        abort_unless($this->isProdEnvironment(), 403);

        if (trim((string) $this->backupConfirmation) !== 'GENERAR RESPALDO') {
            Notification::make()
                ->title('Confirmación requerida')
                ->body('Escribe exactamente: GENERAR RESPALDO')
                ->danger()
                ->send();

            return;
        }

        $requestId = now()->format('Ymd_His') . '_' . Str::lower(Str::random(6));

        $this->writeRequest([
            'id' => $requestId,
            'type' => 'manual_prod_backup_to_dev',
            'created_at' => now()->toIso8601String(),
            'created_by_user_id' => auth()->id(),
            'created_by_email' => auth()->user()?->email,
        ]);

        $this->backupConfirmation = null;

        Notification::make()
            ->title('Respaldo solicitado')
            ->body('El servidor generará el respaldo y lo enviará a DEV en menos de un minuto.')
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

    public function mount(): void
    {
        $this->loadServerMonitorAlertEmailConfig();
    }

    protected function serverMonitorAlertEmailsFile(): string
    {
        return storage_path('app/bexia-monitor/alert-emails.txt');
    }

    protected function serverMonitorForceTestFlagFile(): string
    {
        return storage_path('app/bexia-monitor/force-test-email.flag');
    }

    public function loadServerMonitorAlertEmailConfig(): void
    {
        $path = $this->serverMonitorAlertEmailsFile();

        if (\Illuminate\Support\Facades\File::exists($path)) {
            $this->serverMonitorAlertEmails = trim((string) \Illuminate\Support\Facades\File::get($path));

            return;
        }

        $envPath = '/etc/bexia-monitor-prod.env';

        if (\Illuminate\Support\Facades\File::exists($envPath)) {
            $env = (string) \Illuminate\Support\Facades\File::get($envPath);

            if (preg_match('/^ALERT_EMAILS=(.*)$/m', $env, $matches)) {
                $this->serverMonitorAlertEmails = trim(trim((string) $matches[1]), "\"' ");

                return;
            }
        }

        $this->serverMonitorAlertEmails = '';
    }

    public function saveServerMonitorAlertEmails(): void
    {
        $emails = collect(preg_split('/[,;\n]+/', (string) $this->serverMonitorAlertEmails))
            ->map(fn ($email) => trim((string) $email))
            ->filter()
            ->unique()
            ->values();

        $invalid = $emails
            ->filter(fn ($email) => ! filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values();

        if ($invalid->isNotEmpty()) {
            $this->serverMonitorAlertFeedback = 'Correos inválidos: ' . $invalid->implode(', ');

            \Filament\Notifications\Notification::make()
                ->title('Hay correos inválidos')
                ->body($this->serverMonitorAlertFeedback)
                ->danger()
                ->send();

            return;
        }

        $path = $this->serverMonitorAlertEmailsFile();

        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($path));
        \Illuminate\Support\Facades\File::put($path, $emails->implode(', ') . PHP_EOL);

        @chmod($path, 0664);

        $this->serverMonitorAlertEmails = $emails->implode(', ');
        $this->serverMonitorAlertFeedback = 'Correos guardados correctamente. El monitor usará esta lista en el siguiente ciclo.';

        \Filament\Notifications\Notification::make()
            ->title('Correos de alerta guardados')
            ->body($this->serverMonitorAlertFeedback)
            ->success()
            ->send();
    }

    public function requestServerMonitorEmailTest(): void
    {
        $this->saveServerMonitorAlertEmails();

        $flagPath = $this->serverMonitorForceTestFlagFile();

        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($flagPath));
        \Illuminate\Support\Facades\File::put($flagPath, now()->toIso8601String());

        @chmod($flagPath, 0664);

        $this->serverMonitorAlertFeedback = 'Prueba solicitada. El cron del monitor la ejecutará en el siguiente ciclo, máximo 15 minutos.';

        \Filament\Notifications\Notification::make()
            ->title('Prueba de correo solicitada')
            ->body($this->serverMonitorAlertFeedback)
            ->success()
            ->send();
    }

    public function serverMonitorStatusText(): string
    {
        $path = storage_path('app/bexia-monitor/server-health.txt');

        if (! \Illuminate\Support\Facades\File::exists($path)) {
            return 'Sin lectura todavía. El monitor generará este estado en el próximo ciclo.';
        }

        return trim((string) \Illuminate\Support\Facades\File::get($path));
    }

    public function serverMonitorMailLogText(): string
    {
        $path = '/var/log/bexia-monitor/mail-alert.log';

        if (! \Illuminate\Support\Facades\File::exists($path)) {
            return 'Sin log de correo todavía.';
        }

        $content = trim((string) \Illuminate\Support\Facades\File::get($path));
        $lines = array_slice(preg_split('/\r\n|\r|\n/', $content), -20);

        return trim(implode(PHP_EOL, $lines));
    }

}
