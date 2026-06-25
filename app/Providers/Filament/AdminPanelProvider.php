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
/* BEXIA_TOPBAR_HEADER_PREMIUM_V5_79_9B_START */
:root {
    --bexia-topbar-bg: rgba(255, 255, 255, 0.96);
    --bexia-topbar-border: #e2e8f0;
    --bexia-topbar-shadow: 0 10px 24px rgba(15, 23, 42, 0.045);
    --bexia-topbar-text: #0f172a;
    --bexia-topbar-muted: #64748b;
    --bexia-topbar-soft: #f8fafc;
    --bexia-topbar-soft-2: #eef2f7;
    --bexia-topbar-accent: #1f2937;
}

/* Topbar general: más limpia y alineada con el sidebar premium */
.fi-topbar {
    background: var(--bexia-topbar-bg) !important;
    border-bottom: 1px solid var(--bexia-topbar-border) !important;
    box-shadow: var(--bexia-topbar-shadow) !important;
    backdrop-filter: blur(12px) !important;
    -webkit-backdrop-filter: blur(12px) !important;
}

.fi-topbar nav {
    min-height: 4.25rem !important;
}

/* Botones e iconos generales del topbar */
.fi-topbar .fi-icon-btn,
.fi-topbar button,
.fi-topbar a {
    transition: background-color .15s ease, color .15s ease, box-shadow .15s ease, transform .12s ease !important;
}

.fi-topbar .fi-icon-btn {
    border-radius: 14px !important;
    color: var(--bexia-topbar-muted) !important;
}

.fi-topbar .fi-icon-btn:hover,
.fi-topbar .fi-icon-btn:focus-visible {
    background: var(--bexia-topbar-soft-2) !important;
    color: var(--bexia-topbar-text) !important;
}

/* Header del sidebar: conserva logo blanco, pero más premium */
.fi-sidebar-header {
    min-height: 4.25rem !important;
    padding-inline: 1rem !important;
    background: #ffffff !important;
    border-bottom: 1px solid #e2e8f0 !important;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.035) !important;
}

.fi-sidebar-header .fi-logo,
.fi-sidebar-header img {
    max-height: 3rem !important;
}

/* Botón para colapsar sidebar */
.fi-sidebar-header .fi-icon-btn,
.fi-sidebar-header button {
    border-radius: 12px !important;
    color: #64748b !important;
}

.fi-sidebar-header .fi-icon-btn:hover,
.fi-sidebar-header button:hover {
    background: #f1f5f9 !important;
    color: #0f172a !important;
}

/* Topbar: enlaces de aprobaciones/avisos/enviados */
.bexia-approval-topbar {
    gap: .45rem !important;
    align-items: center !important;
}

/* Base limpia, sin borrar los tonos funcionales */
.bexia-approval-topbar__link {
    min-height: 2.35rem !important;
    border-radius: 999px !important;
    border-width: 1px !important;
    border-style: solid !important;
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.035) !important;
}

.bexia-approval-topbar__link:hover,
.bexia-approval-topbar__link:focus-visible {
    transform: translateY(-1px) !important;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.07) !important;
}

/* Tono amarillo/ambar: conservar avisos o pendientes */
.bexia-approval-topbar__link.is-amber {
    background: #fffbeb !important;
    border-color: #fcd34d !important;
    color: #92400e !important;
}

.bexia-approval-topbar__link.is-amber:hover,
.bexia-approval-topbar__link.is-amber:focus-visible {
    background: #fef3c7 !important;
    border-color: #f59e0b !important;
    color: #78350f !important;
}

.bexia-approval-topbar__link.is-amber *,
.bexia-approval-topbar__link.is-amber span {
    color: #92400e !important;
}

/* Tono azul: conservar enviados/informativos */
.bexia-approval-topbar__link.is-blue {
    background: #eff6ff !important;
    border-color: #bfdbfe !important;
    color: #1d4ed8 !important;
}

.bexia-approval-topbar__link.is-blue:hover,
.bexia-approval-topbar__link.is-blue:focus-visible {
    background: #dbeafe !important;
    border-color: #60a5fa !important;
    color: #1e40af !important;
}

.bexia-approval-topbar__link.is-blue *,
.bexia-approval-topbar__link.is-blue span {
    color: #1d4ed8 !important;
}

/* Activo con color por tono, no negro genérico */
.bexia-approval-topbar__link.is-active.is-amber {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
    border-color: #d97706 !important;
    color: #ffffff !important;
    box-shadow: 0 8px 18px rgba(217, 119, 6, 0.20) !important;
}

.bexia-approval-topbar__link.is-active.is-blue {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
    border-color: #1d4ed8 !important;
    color: #ffffff !important;
    box-shadow: 0 8px 18px rgba(37, 99, 235, 0.18) !important;
}

.bexia-approval-topbar__link.is-active:not(.is-amber):not(.is-blue) {
    background: linear-gradient(135deg, #111827 0%, #1f2937 100%) !important;
    border-color: #334155 !important;
    color: #ffffff !important;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.16) !important;
}

.bexia-approval-topbar__link.is-active *,
.bexia-approval-topbar__link.is-active span {
    color: #ffffff !important;
}

/* Badges por tono */
.bexia-approval-topbar__badge {
    border-radius: 999px !important;
    font-weight: 700 !important;
}

.bexia-approval-topbar__badge.is-amber {
    background: #f59e0b !important;
    color: #ffffff !important;
}

.bexia-approval-topbar__badge.is-blue {
    background: #2563eb !important;
    color: #ffffff !important;
}

.bexia-approval-topbar__link.is-active .bexia-approval-topbar__badge {
    background: rgba(255, 255, 255, 0.22) !important;
    color: #ffffff !important;
}

/* Selector de empresa */
.bexia-topbar-company-switcher {
    min-height: 2.65rem !important;
    border-radius: 999px !important;
    border: 1px solid #e2e8f0 !important;
    background: #ffffff !important;
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.04) !important;
}

.bexia-topbar-company-switcher:hover {
    background: #f8fafc !important;
    border-color: #cbd5e1 !important;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06) !important;
}

.bexia-topbar-company-switcher__logo-wrap,
.bexia-topbar-company-switcher__fallback {
    border-radius: 999px !important;
}

.bexia-topbar-company-switcher__select {
    color: #0f172a !important;
    font-weight: 700 !important;
}

.bexia-topbar-company-switcher__arrow {
    color: #64748b !important;
}

/* Nombre de usuario / menú usuario */
.fi-topbar .fi-dropdown-trigger,
.fi-topbar .fi-user-menu-trigger {
    border-radius: 999px !important;
}

.fi-topbar .fi-dropdown-panel {
    border: 1px solid #e2e8f0 !important;
    border-radius: 18px !important;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12) !important;
}
/* BEXIA_TOPBAR_HEADER_PREMIUM_V5_79_9B_END */
/* BEXIA_DASHBOARD_CARDS_PREMIUM_V5_79_10B_START */
:root {
    --bexia-dashboard-bg: #f8fafc;
    --bexia-card-bg: #ffffff;
    --bexia-card-bg-soft: #fbfdff;
    --bexia-card-border: #e2e8f0;
    --bexia-card-border-soft: #edf2f7;
    --bexia-card-text: #0f172a;
    --bexia-card-muted: #64748b;
    --bexia-card-shadow: 0 8px 22px rgba(15, 23, 42, 0.045);
    --bexia-card-shadow-hover: 0 16px 34px rgba(15, 23, 42, 0.075);
    --bexia-card-radius: 20px;
}

/* Fondo general del área de trabajo */
.fi-main,
.fi-main-ctn,
.fi-page {
    background:
        radial-gradient(circle at top right, rgba(148, 163, 184, 0.10), transparent 28rem),
        linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%) !important;
}

/* Separación general del contenido */
.fi-main {
    color: var(--bexia-card-text) !important;
}

/* Cards, widgets y secciones principales */
.fi-widget,
.fi-section,
.fi-card,
.fi-in-card,
.fi-simple-page-section {
    background: linear-gradient(180deg, var(--bexia-card-bg) 0%, var(--bexia-card-bg-soft) 100%) !important;
    border: 1px solid var(--bexia-card-border) !important;
    border-radius: var(--bexia-card-radius) !important;
    box-shadow: var(--bexia-card-shadow) !important;
    overflow: hidden !important;
    transition:
        box-shadow .16s ease,
        border-color .16s ease,
        transform .12s ease,
        background-color .16s ease !important;
}

.fi-widget:hover,
.fi-section:hover,
.fi-card:hover {
    border-color: #cbd5e1 !important;
    box-shadow: var(--bexia-card-shadow-hover) !important;
    transform: translateY(-1px) !important;
}

/* Encabezados dentro de widgets/secciones */
.fi-widget .fi-section-header,
.fi-section .fi-section-header,
.fi-widget header,
.fi-section header {
    border-bottom-color: var(--bexia-card-border-soft) !important;
}

.fi-widget h2,
.fi-widget h3,
.fi-section h2,
.fi-section h3,
.fi-header-heading {
    color: var(--bexia-card-text) !important;
    letter-spacing: -0.02em !important;
}

/* Texto secundario */
.fi-widget p,
.fi-section p,
.fi-widget .text-gray-500,
.fi-widget .text-gray-600,
.fi-section .text-gray-500,
.fi-section .text-gray-600 {
    color: var(--bexia-card-muted) !important;
}

/* StatsOverviewWidget: tarjetas de indicadores */
.fi-wi-stats-overview-stat,
.fi-wi-stats-overview .fi-wi-stats-overview-stat,
.fi-wi-stats-overview-stat-card {
    background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%) !important;
    border: 1px solid var(--bexia-card-border) !important;
    border-radius: 20px !important;
    box-shadow: var(--bexia-card-shadow) !important;
    overflow: hidden !important;
    transition:
        box-shadow .16s ease,
        border-color .16s ease,
        transform .12s ease !important;
}

.fi-wi-stats-overview-stat:hover,
.fi-wi-stats-overview .fi-wi-stats-overview-stat:hover,
.fi-wi-stats-overview-stat-card:hover {
    border-color: #cbd5e1 !important;
    box-shadow: var(--bexia-card-shadow-hover) !important;
    transform: translateY(-1px) !important;
}

.fi-wi-stats-overview-stat-label,
.fi-wi-stats-overview-stat .fi-wi-stats-overview-stat-label {
    color: #64748b !important;
    font-weight: 700 !important;
}

.fi-wi-stats-overview-stat-value,
.fi-wi-stats-overview-stat .fi-wi-stats-overview-stat-value {
    color: #0f172a !important;
    letter-spacing: -0.03em !important;
}

/* Tablas dentro de widgets: solo contenedor, no rediseñar tablas todavía */
.fi-widget .fi-ta,
.fi-section .fi-ta {
    border-radius: 18px !important;
    border-color: var(--bexia-card-border-soft) !important;
    box-shadow: none !important;
}

/* Bloques custom Bexia del escritorio */
[class*="bexia"][class*="dashboard"],
[class*="bexia"][class*="widget"],
[class*="bexia"][class*="section"] {
    box-sizing: border-box !important;
}

/* Headers/separadores custom del escritorio */
.bexia-dashboard-section,
.bexia-dashboard-section-header,
.bexia-dashboard-section-widget {
    border-radius: 20px !important;
}

/* Evitar que el hover afecte modales o dropdowns de forma agresiva */
.fi-modal-window,
.fi-dropdown-panel {
    transform: none !important;
}

/* Mantener responsive natural */
@media (max-width: 768px) {
    .fi-widget,
    .fi-section,
    .fi-card,
    .fi-in-card,
    .fi-wi-stats-overview-stat,
    .fi-wi-stats-overview-stat-card {
        border-radius: 18px !important;
    }

    .fi-widget:hover,
    .fi-section:hover,
    .fi-card:hover,
    .fi-wi-stats-overview-stat:hover,
    .fi-wi-stats-overview-stat-card:hover {
        transform: none !important;
    }
}
/* BEXIA_DASHBOARD_CARDS_PREMIUM_V5_79_10B_END */
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
