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
            ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make('Inicio'),
                \Filament\Navigation\NavigationGroup::make('Contactos'),
                \Filament\Navigation\NavigationGroup::make('Recursos Humanos'),
                \Filament\Navigation\NavigationGroup::make('Productos'),
                \Filament\Navigation\NavigationGroup::make('Compras'),
                \Filament\Navigation\NavigationGroup::make('Cuentas por pagar'),
                \Filament\Navigation\NavigationGroup::make('Ventas'),
                \Filament\Navigation\NavigationGroup::make('Cuentas por cobrar'),
                \Filament\Navigation\NavigationGroup::make('Punto de Venta'),
                \Filament\Navigation\NavigationGroup::make('Inventario'),
                \Filament\Navigation\NavigationGroup::make('Salidas'),
                \Filament\Navigation\NavigationGroup::make('Tesorería'),
                \Filament\Navigation\NavigationGroup::make('Facturación'),
                \Filament\Navigation\NavigationGroup::make('Contabilidad'),
                \Filament\Navigation\NavigationGroup::make('Catálogos'),
                \Filament\Navigation\NavigationGroup::make('Configuración empresa'),
                \Filament\Navigation\NavigationGroup::make('Configuración Bexia'),
                \Filament\Navigation\NavigationGroup::make('Seguridad'),
            ])
            // BEXIA_MENU_ORDER_V5_59_1B_END
            // BEXIA_SALES_NAV_ITEMS_V5_59_1F_START
            ->navigationItems([
                \Filament\Navigation\NavigationItem::make('Cotizaciones')
                    ->group('Ventas')
                    ->sort(10)
                    ->icon('heroicon-o-document-text')
                    ->url(fn (): string => \App\Filament\Resources\SaleOrderResource::getUrl('quotes'))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.sale-orders.quotes'))
                    ->visible(fn (): bool => \App\Filament\Resources\SaleOrderResource::canViewAny()),

                \Filament\Navigation\NavigationItem::make('Órdenes de venta')
                    ->group('Ventas')
                    ->sort(20)
                    ->icon('heroicon-o-shopping-cart')
                    ->url(fn (): string => \App\Filament\Resources\SaleOrderResource::getUrl('orders'))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.sale-orders.orders'))
                    ->visible(fn (): bool => \App\Filament\Resources\SaleOrderResource::canViewAny()),
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
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                \App\Http\Middleware\SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
