<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Si vas a usar tu middleware global de “team” (Spatie), descomenta la siguiente línea:
// use App\Http\Middleware\SetCompanyForPermissions;

use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\SystemPanelProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Si quieres que Spatie conozca el team en TODAS las peticiones:
        // $middleware->prepend(SetCompanyForPermissions::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withProviders([
        // ✅ Registra ambos paneles de Filament
        AdminPanelProvider::class,
        SystemPanelProvider::class,
    ])
    ->create();
