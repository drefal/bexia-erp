<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class ServerMonitor extends Page
{
    protected static ?string $navigationGroup = 'Administración';

    protected static ?string $navigationLabel = 'Monitor del servidor';

    protected static ?string $title = 'Monitor del servidor';

    protected static ?string $slug = 'monitor-servidor';

    protected static ?int $navigationSort = 991;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static string $view = 'filament.pages.server-monitor';

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((bool) ($user->is_system_admin ?? false)) {
            return true;
        }

        return method_exists($user, 'can') && $user->can('server_monitor.view');
    }

    public function getViewData(): array
    {
        $runtime = $this->runtime();

        return [
            'runtime' => $runtime,
            'disk' => $this->command('df -h /'),
            'memory' => $this->command('free -h'),
            'uptime' => $this->command('uptime'),
            'docker' => $this->dockerPs($runtime),
            'monitorMailLog' => $this->tail('/var/log/bexia-monitor/mail-alert.log', 80),
            'systemOpsState' => $this->readFile(storage_path('app/private/system-ops/state/last-operation.json')),
            'prodBackupsState' => $this->readFile(storage_path('app/private/system-ops/state/prod-backups.json')),
        ];
    }

    protected function runtime(): array
    {
        $appUrl = (string) (config('app.url') ?: env('APP_URL', ''));
        $isDev = Str::contains($appUrl, 'dev.bexiaerp.com');

        if ($isDev) {
            return [
                'name' => 'DEV',
                'app_url' => $appUrl,
                'app_dir' => '/opt/bexia/dev',
                'compose_file' => 'docker-compose.dev.yml',
                'compose_project' => 'bexia_dev',
            ];
        }

        return [
            'name' => 'PROD',
            'app_url' => $appUrl,
            'app_dir' => '/opt/bexia/app',
            'compose_file' => 'docker-compose.yml',
            'compose_project' => null,
        ];
    }

    protected function dockerPs(array $runtime): string
    {
        $dir = escapeshellarg((string) $runtime['app_dir']);
        $composeFile = escapeshellarg((string) $runtime['compose_file']);
        $project = $runtime['compose_project'];

        if ($project) {
            $projectArg = '-p ' . escapeshellarg((string) $project) . ' ';
        } else {
            $projectArg = '';
        }

        return $this->command("cd {$dir} && docker compose {$projectArg}-f {$composeFile} ps");
    }

    protected function command(string $command): string
    {
        try {
            $result = Process::timeout(20)->run($command);

            return trim(($result->output() ?: '') . ($result->errorOutput() ? "\n" . $result->errorOutput() : '')) ?: 'Sin salida.';
        } catch (\Throwable $e) {
            return 'ERROR: ' . $e->getMessage();
        }
    }

    protected function tail(string $path, int $lines = 80): string
    {
        if (! File::exists($path)) {
            return 'No existe: ' . $path;
        }

        return $this->command('tail -n ' . (int) $lines . ' ' . escapeshellarg($path));
    }

    protected function readFile(string $path): string
    {
        if (! File::exists($path)) {
            return 'No existe: ' . $path;
        }

        try {
            return trim(File::get($path)) ?: 'Archivo vacío.';
        } catch (\Throwable $e) {
            return 'ERROR: ' . $e->getMessage();
        }
    }
}
