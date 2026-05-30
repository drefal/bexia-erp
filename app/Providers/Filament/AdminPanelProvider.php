<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->maxContentWidth(\Filament\Support\Enums\MaxWidth::Full)
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Bexia ERP')
            ->brandLogo(asset('logo.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('favicon.png'))
            ->homeUrl(function (): string {
                $tenant = filament()->getTenant();

                if ($tenant) {
                    return url('/admin/' . $tenant->getKey());
                }

                return url('/admin');
            })
            ->login()
            ->authGuard('web')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->sidebarCollapsibleOnDesktop()
            // BEXIA_MENU_ORDER_V5_59_1B_START
            ->navigationGroups(\App\Support\Navigation\BexiaMenuRuntime::navigationGroups([
                'inicio' => 'Inicio',
                'contactos' => 'Contactos',
                'recursos_humanos' => 'Recursos Humanos',
                'productos' => 'Productos',
                'compras' => 'Compras',
                'cuentas_por_pagar' => 'Cuentas por pagar',
                'ventas' => 'Ventas',
                'cuentas_por_cobrar' => 'Cuentas por cobrar',
                'punto_de_venta' => 'Punto de Venta',
                'inventario' => 'Inventario',
                'salidas' => 'Salidas',
                'tesorer_a' => 'Tesorería',
                'facturaci_n' => 'Facturación',
                'contabilidad' => 'Contabilidad',
                'cat_logos' => 'Catálogos',
                'configuraci_n_empresa' => 'Configuración empresa',
                'configuraci_n_bexia' => 'Configuración Bexia',
                'seguridad' => 'Seguridad',
            ]))
            // BEXIA_MENU_ORDER_V5_59_1B_END
            // BEXIA_SALES_NAV_ITEMS_V5_59_1F_START
            ->navigationItems([
                \Filament\Navigation\NavigationItem::make(\App\Support\Navigation\BexiaMenuRuntime::itemLabel('sales.quotes', 'Cotizaciones'))
                    ->group(\App\Support\Navigation\BexiaMenuRuntime::itemGroupLabel('sales.quotes', 'ventas', 'Ventas'))
                    ->sort(\App\Support\Navigation\BexiaMenuRuntime::itemSort('sales.quotes', 10))
                    ->icon('heroicon-o-document-text')
                    ->url(fn (): string => \App\Filament\Resources\SaleOrderResource::getUrl('quotes'))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.sale-orders.quotes'))
                    ->visible(fn (): bool => \App\Support\Navigation\BexiaMenuRuntime::itemVisible('sales.quotes', true)
                        && \App\Filament\Resources\SaleOrderResource::canViewAny()),

                \Filament\Navigation\NavigationItem::make(\App\Support\Navigation\BexiaMenuRuntime::itemLabel('sales.orders', 'Órdenes de venta'))
                    ->group(\App\Support\Navigation\BexiaMenuRuntime::itemGroupLabel('sales.orders', 'ventas', 'Ventas'))
                    ->sort(\App\Support\Navigation\BexiaMenuRuntime::itemSort('sales.orders', 20))
                    ->icon('heroicon-o-shopping-cart')
                    ->url(fn (): string => \App\Filament\Resources\SaleOrderResource::getUrl('orders'))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.sale-orders.orders'))
                    ->visible(fn (): bool => \App\Support\Navigation\BexiaMenuRuntime::itemVisible('sales.orders', true)
                        && \App\Filament\Resources\SaleOrderResource::canViewAny()),
            ])
            // BEXIA_SALES_NAV_ITEMS_V5_59_1F_END
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(<<<'HTML'
<script>
(function () {
    const forceLight = function () {
        try {
            localStorage.setItem('theme', 'light');
        } catch (e) {}

        document.documentElement.classList.remove('dark');
        document.documentElement.style.colorScheme = 'light';

        document.addEventListener('livewire:navigated', function () {
            try {
                localStorage.setItem('theme', 'light');
            } catch (e) {}

            document.documentElement.classList.remove('dark');
            document.documentElement.style.colorScheme = 'light';
        });
    };

    forceLight();
})();
</script>
HTML),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_AFTER,
                fn (): string => view('filament.components.topbar-user-name')->render(),
            )
            ->tenant(
                \App\Models\Company::class,
                ownershipRelationship: 'companies'
            )
            ->tenantMiddleware([
            \App\Http\Middleware\SetSpatieCompanyFromTenant::class,
            \App\Http\Middleware\SetPermissionTeamFromUserCompany::class,
            ], isPersistent: true)
            ->discoverPages(
                in: app_path('Filament/Pages'),
                for: 'App\\Filament\\Pages'
            )
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\\Filament\\Resources'
            )
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
             // BEXIA_TOPBAR_APPROVAL_LINKS_V5_13_5

             // BEXIA_TOPBAR_APPROVAL_LINKS_V5_13_7

             // BEXIA_TOPBAR_APPROVAL_LINKS_V5_13_8

            ->renderHook(
                'panels::user-menu.before',
                fn (): string => view('filament.topbar.approval-links')->render()
            ) // BEXIA_TOPBAR_APPROVAL_LINKS_V5_13_8

->middleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    \App\Http\Middleware\KeepPathOnTenantSwitch::class,
    ShareErrorsFromSession::class,
    VerifyCsrfToken::class,
    SubstituteBindings::class,
    \App\Http\Middleware\SetLocale::class,
    \App\Http\Middleware\SetPermissionTeamFromUserCompany::class,
])
->authMiddleware([
    Authenticate::class,
    \App\Http\Middleware\SetPermissionTeamFromUserCompany::class,
]);
    }
}
