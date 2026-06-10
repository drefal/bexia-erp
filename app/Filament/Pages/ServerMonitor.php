<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;

class ServerMonitor extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static string $view = 'filament.pages.server-monitor';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $navigationLabel = 'Monitor del servidor';

    protected static ?string $title = 'Monitor del servidor';

    protected static ?string $slug = 'monitor-servidor';

    protected static ?int $navigationSort = 990;

    public string $serverMonitorAlertEmails = '';

    public ?string $serverMonitorAlertFeedback = null;

    public function mount(): void
    {
        $this->loadServerMonitorAlertEmailConfig();
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    protected function serverMonitorDirectory(): string
    {
        return storage_path('app/bexia-monitor');
    }

    protected function serverMonitorAlertEmailsFile(): string
    {
        return $this->serverMonitorDirectory() . '/alert-emails.txt';
    }

    protected function serverMonitorForceTestFlagFile(): string
    {
        return $this->serverMonitorDirectory() . '/force-test-email.flag';
    }

    public function loadServerMonitorAlertEmailConfig(): void
    {
        $path = $this->serverMonitorAlertEmailsFile();

        if (File::exists($path)) {
            $this->serverMonitorAlertEmails = trim((string) File::get($path));

            return;
        }

        $this->serverMonitorAlertEmails = '';
    }

    public function saveServerMonitorAlertEmails(): void
    {
        [$emails, $invalid] = $this->parseAlertEmails();

        if ($invalid !== []) {
            $this->serverMonitorAlertFeedback = 'Correos inválidos: ' . implode(', ', $invalid);

            Notification::make()
                ->title('Hay correos inválidos')
                ->body($this->serverMonitorAlertFeedback)
                ->danger()
                ->send();

            return;
        }

        File::ensureDirectoryExists($this->serverMonitorDirectory());

        $content = implode(', ', $emails);
        File::put($this->serverMonitorAlertEmailsFile(), $content . PHP_EOL);

        @chmod($this->serverMonitorAlertEmailsFile(), 0664);

        $this->serverMonitorAlertEmails = $content;
        $this->serverMonitorAlertFeedback = 'Correos guardados correctamente. El monitor usará esta lista en el siguiente ciclo.';

        Notification::make()
            ->title('Correos de alerta guardados')
            ->body($this->serverMonitorAlertFeedback)
            ->success()
            ->send();
    }

    public function requestServerMonitorEmailTest(): void
    {
        [$emails, $invalid] = $this->parseAlertEmails();

        if ($invalid !== []) {
            $this->serverMonitorAlertFeedback = 'Correos inválidos: ' . implode(', ', $invalid);

            Notification::make()
                ->title('No se solicitó la prueba')
                ->body($this->serverMonitorAlertFeedback)
                ->danger()
                ->send();

            return;
        }

        File::ensureDirectoryExists($this->serverMonitorDirectory());

        File::put($this->serverMonitorAlertEmailsFile(), implode(', ', $emails) . PHP_EOL);
        File::put($this->serverMonitorForceTestFlagFile(), now()->toIso8601String());

        @chmod($this->serverMonitorAlertEmailsFile(), 0664);
        @chmod($this->serverMonitorForceTestFlagFile(), 0664);

        $this->serverMonitorAlertEmails = implode(', ', $emails);
        $this->serverMonitorAlertFeedback = 'Prueba solicitada. El cron del monitor la ejecutará en el siguiente ciclo, máximo 15 minutos.';

        Notification::make()
            ->title('Prueba de correo solicitada')
            ->body($this->serverMonitorAlertFeedback)
            ->success()
            ->send();
    }

    protected function parseAlertEmails(): array
    {
        $emails = collect(preg_split('/[,;\n]+/', (string) $this->serverMonitorAlertEmails))
            ->map(fn ($email) => trim((string) $email))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $invalid = collect($emails)
            ->filter(fn ($email) => ! filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();

        return [$emails, $invalid];
    }

    public function serverMonitorStatusText(): string
    {
        return $this->tailFile(
            storage_path('app/bexia-monitor/server-health.txt'),
            120,
            'Sin lectura todavía. El monitor generará este estado en el próximo ciclo.'
        );
    }

    public function serverMonitorAppLogText(): string
    {
        return $this->tailFile(
            storage_path('logs/bexia-server-monitor.log'),
            60,
            'Sin eventos del monitor todavía.'
        );
    }

    public function serverMonitorAlertText(): string
    {
        return $this->tailFile(
            storage_path('app/bexia-monitor/latest-alert.txt'),
            20,
            'Sin alerta registrada.'
        );
    }

    protected function tailFile(string $path, int $lines, string $fallback): string
    {
        if (! File::exists($path)) {
            return $fallback;
        }

        $size = @filesize($path);

        if ($size === false || $size <= 0) {
            return $fallback;
        }

        $handle = @fopen($path, 'rb');

        if (! $handle) {
            return 'No se pudo leer el archivo.';
        }

        $readBytes = min($size, 30000);

        if ($size > $readBytes) {
            fseek($handle, -$readBytes, SEEK_END);
        }

        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        $rows = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];

        return trim(implode(PHP_EOL, array_slice($rows, -$lines))) ?: $fallback;
    }
}
