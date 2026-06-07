<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
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
        foreach (['is_super_admin', 'super_admin', 'is_global_admin', 'is_admin'] as $field) {
            if (isset($user->{$field}) && (bool) $user->{$field}) {
                return true;
            }
        }

        $email = strtolower((string) ($user->email ?? ''));

        if (in_array($email, [
            'drefal@gmail.com',
        ], true)) {
            return true;
        }

        if (method_exists($user, 'hasRole')) {
            foreach ([
                'super_admin',
                'superadmin',
                'Super Admin',
                'Administrador global',
                'admin_global',
                'admin grupo',
                'Admin Grupo',
            ] as $role) {
                try {
                    if ($user->hasRole($role)) {
                        return true;
                    }
                } catch (\Throwable) {
                    // Continuar con validación por relación roles.
                }
            }
        }

        try {
            if (method_exists($user, 'roles')) {
                $roles = $user->roles()
                    ->pluck('name')
                    ->map(fn ($role) => mb_strtolower(trim((string) $role)))
                    ->all();

                foreach ($roles as $role) {
                    if (in_array($role, [
                        'super_admin',
                        'superadmin',
                        'super admin',
                        'administrador global',
                        'admin_global',
                        'admin grupo',
                    ], true)) {
                        return true;
                    }
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
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

        $content = file_get_contents($path) ?: '';

        return mb_substr($content, -60000);
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
