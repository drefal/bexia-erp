<?php

namespace App\Providers\Filament;

use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SystemPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('system')
            ->path('system')
            ->brandName('Bexia ERP | Sistema')
            ->login()

            // ✅ Tenancy también en System (para que Spatie Teams tenga company_id)
            ->tenant(\App\Models\Company::class)

            // ✅ Spatie Teams: setear company_id según tenant (persistente para Livewire)
            ->tenantMiddleware([
                \App\Http\Middleware\SetSpatieCompanyFromTenant::class,
            ], isPersistent: true)

            // (Opcional) Si quieres dashboard en system panel:
            ->pages([
                Pages\Dashboard::class,
            ])

            // ✅ Resources / Pages del panel system
            ->discoverResources(
                in: app_path('Filament/System/Resources'),
                for: 'App\\Filament\\System\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/System/Pages'),
                for: 'App\\Filament\\System\\Pages'
            )

            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                \App\Http\Middleware\SetLocale::class,
            ])
            ->authMiddleware([

                \App\Http\Middleware\SetPermissionTeamFromUserCompany::class,
                Authenticate::class,
            ]);
    }
}
