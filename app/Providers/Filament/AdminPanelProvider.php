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
            ->homeUrl(fn (): string => \App\Support\Security\SafeAdminUrl::current())
            ->login()
            ->authGuard('web')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->sidebarCollapsibleOnDesktop()
            // BEXIA_MENU_ORDER_V5_59_1B_START
            ->navigationGroups(\App\Support\Navigation\BexiaMenuRuntime::navigationGroups([
                'inicio' => 'Inicio',
                'contactos' => 'Contactos',
                'recursos_humanos' => 'RRHH',
                'nomina' => 'Nómina',
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
<style>
/* BEXIA_SIDEBAR_DARK_V5_79_8B_START */
:root {
    --bexia-sidebar-bg: #0b1220;
    --bexia-sidebar-bg-2: #111827;
    --bexia-sidebar-border: #263244;
    --bexia-sidebar-text: #e5e7eb;
    --bexia-sidebar-muted: #94a3b8;
    --bexia-sidebar-active: #2563eb;
    --bexia-sidebar-active-2: #334155;
}

/* Sidebar negro suave premium, conservando cabecera clara para que el logo BexiaERP se lea bien */
.fi-sidebar {
    background: linear-gradient(180deg, var(--bexia-sidebar-bg) 0%, var(--bexia-sidebar-bg-2) 100%) !important;
    border-right: 1px solid var(--bexia-sidebar-border) !important;
}

.fi-sidebar-header {
    background: #ffffff !important;
    border-bottom: 1px solid #e5e7eb !important;
}

.fi-sidebar-nav,
.fi-sidebar-nav-groups {
    background: transparent !important;
}

/* Grupos y textos */
.fi-sidebar .fi-sidebar-group-label,
.fi-sidebar .fi-sidebar-group-button,
.fi-sidebar .fi-sidebar-item-label,
.fi-sidebar .fi-sidebar-item-button,
.fi-sidebar .fi-sidebar-item-button span {
    color: var(--bexia-sidebar-text) !important;
}

.fi-sidebar .fi-sidebar-group-label {
    color: #cbd5e1 !important;
    font-weight: 700 !important;
    letter-spacing: .01em !important;
}

.fi-sidebar .fi-sidebar-item-icon,
.fi-sidebar .fi-sidebar-group-collapse-button,
.fi-sidebar svg {
    color: var(--bexia-sidebar-muted) !important;
}

/* Items */
.fi-sidebar .fi-sidebar-item-button {
    border-radius: 14px !important;
    margin-inline: 0.25rem !important;
    transition: background-color .15s ease, color .15s ease, box-shadow .15s ease !important;
}

.fi-sidebar .fi-sidebar-item-button:hover {
    background: rgba(255, 255, 255, 0.09) !important;
}

.fi-sidebar .fi-sidebar-item-button:hover,
.fi-sidebar .fi-sidebar-item-button:hover *,
.fi-sidebar .fi-sidebar-item-button:hover svg {
    color: #ffffff !important;
}

/* Activo */
.fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-button,
.fi-sidebar .fi-sidebar-item-button.fi-active,
.fi-sidebar .fi-sidebar-item-button[aria-current="page"],
.fi-sidebar .fi-sidebar-item[aria-current="page"] > .fi-sidebar-item-button {
    background: linear-gradient(90deg, #1f2937 0%, #111827 100%) !important;
    background-color: #1f2937 !important;
    border: 1px solid rgba(148, 163, 184, 0.18) !important;
    color: #f8fafc !important;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.025), 0 8px 18px rgba(0, 0, 0, 0.16) !important;
}

.fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-button *,
.fi-sidebar .fi-sidebar-item-button.fi-active *,
.fi-sidebar .fi-sidebar-item-button[aria-current="page"] *,
.fi-sidebar .fi-sidebar-item[aria-current="page"] > .fi-sidebar-item-button * {
    color: #f8fafc !important;
}

.fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-button svg,
.fi-sidebar .fi-sidebar-item-button.fi-active svg,
.fi-sidebar .fi-sidebar-item-button[aria-current="page"] svg,
.fi-sidebar .fi-sidebar-item[aria-current="page"] > .fi-sidebar-item-button svg {
    color: #bfdbfe !important;
}

/* Scrollbar */
.fi-sidebar nav {
    scrollbar-color: #475569 var(--bexia-sidebar-bg) !important;
}

.fi-sidebar ::-webkit-scrollbar-thumb {
    background: #475569 !important;
    border-radius: 999px !important;
}

.fi-sidebar ::-webkit-scrollbar-track {
    background: var(--bexia-sidebar-bg) !important;
}
/* BEXIA_SIDEBAR_DARK_V5_79_8B_END */
</style>
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
                \App\Filament\Pages\SystemOperationLogs::class,
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
                 . view('filament.topbar.company-switcher')->render()
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
