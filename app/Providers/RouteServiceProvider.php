<?php

namespace App\Providers;

use App\Services\TenantManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $tenant = app(TenantManager::class)->getTenant();

        if ($tenant) {
            $path = base_path('routes/tenants/'.$tenant->subdomain.'.php');
            if (file_exists($path)) {
                Route::middleware('web')->group($path);
            }
        }
    }
}

