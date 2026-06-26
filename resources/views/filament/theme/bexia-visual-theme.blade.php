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
/* BEXIA_TABLES_LISTS_PREMIUM_V5_79_11B_START */
:root {
    --bexia-table-bg: #ffffff;
    --bexia-table-soft: #f8fafc;
    --bexia-table-soft-2: #f1f5f9;
    --bexia-table-border: #e2e8f0;
    --bexia-table-border-soft: #edf2f7;
    --bexia-table-text: #0f172a;
    --bexia-table-muted: #64748b;
    --bexia-table-hover: #f8fafc;
    --bexia-table-shadow: 0 8px 22px rgba(15, 23, 42, 0.045);
}

/* Contenedor general de tablas Filament */
.fi-ta {
    background: var(--bexia-table-bg) !important;
    border: 1px solid var(--bexia-table-border) !important;
    border-radius: 20px !important;
    box-shadow: var(--bexia-table-shadow) !important;
    overflow: hidden !important;
}

/* Header superior de tabla: buscador, filtros, acciones */
.fi-ta-header,
.fi-ta-header-toolbar,
.fi-ta-toolbar,
.fi-ta-filters,
.fi-ta-selection-indicator {
    background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%) !important;
    border-color: var(--bexia-table-border-soft) !important;
}

.fi-ta-header {
    padding-block: .9rem !important;
}

/* Inputs de búsqueda dentro de tablas */
.fi-ta .fi-input-wrp,
.fi-ta .fi-select-input,
.fi-ta input:not([type="checkbox"]):not([type="radio"]),
.fi-ta select {
    border-radius: 14px !important;
    border-color: #dbe3ee !important;
    background: #ffffff !important;
}

.fi-ta .fi-input-wrp:focus-within {
    border-color: #94a3b8 !important;
    box-shadow: 0 0 0 4px rgba(148, 163, 184, 0.16) !important;
}

/* Tabla */
.fi-ta-table {
    border-collapse: separate !important;
    border-spacing: 0 !important;
    background: #ffffff !important;
}

/* Encabezados */
.fi-ta-table thead,
.fi-ta-table thead tr,
.fi-ta-table th,
.fi-ta-header-cell {
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%) !important;
    color: #475569 !important;
    border-color: var(--bexia-table-border) !important;
}

.fi-ta-table th,
.fi-ta-header-cell {
    font-size: .72rem !important;
    font-weight: 800 !important;
    letter-spacing: .04em !important;
    text-transform: uppercase !important;
    white-space: nowrap !important;
}

/* Celdas */
.fi-ta-table td,
.fi-ta-cell {
    color: var(--bexia-table-text) !important;
    border-color: var(--bexia-table-border-soft) !important;
    vertical-align: middle !important;
}

.fi-ta-table td,
.fi-ta-table th {
    padding-top: .72rem !important;
    padding-bottom: .72rem !important;
}

/* Filas */
.fi-ta-table tbody tr {
    background: #ffffff !important;
    transition:
        background-color .14s ease,
        box-shadow .14s ease !important;
}

.fi-ta-table tbody tr:hover {
    background: var(--bexia-table-hover) !important;
}

.fi-ta-table tbody tr:hover td {
    color: #0f172a !important;
}

/* Texto secundario y truncado */
.fi-ta-text,
.fi-ta-cell .text-gray-500,
.fi-ta-cell .text-gray-600,
.fi-ta-cell .text-sm {
    color: var(--bexia-table-muted) !important;
}

.fi-ta-text strong,
.fi-ta-cell strong,
.fi-ta-cell .font-medium,
.fi-ta-cell .font-semibold {
    color: #0f172a !important;
}

/* Badges dentro de tablas */
.fi-ta .fi-badge {
    border-radius: 999px !important;
    font-weight: 800 !important;
    letter-spacing: -0.01em !important;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.04) !important;
}

/* Icon buttons y acciones de fila */
.fi-ta .fi-icon-btn,
.fi-ta .fi-ac-icon-btn,
.fi-ta .fi-dropdown-trigger button {
    border-radius: 12px !important;
    color: #64748b !important;
}

.fi-ta .fi-icon-btn:hover,
.fi-ta .fi-ac-icon-btn:hover,
.fi-ta .fi-dropdown-trigger button:hover {
    background: #eef2f7 !important;
    color: #0f172a !important;
}

/* Botones de acción en header/listados */
.fi-ta .fi-btn {
    border-radius: 14px !important;
    min-height: 2.45rem !important;
}

/* Filtros desplegados */
.fi-ta .fi-dropdown-panel,
.fi-ta-filters .fi-section,
.fi-ta-filters .fi-fieldset {
    border-radius: 18px !important;
    border-color: var(--bexia-table-border) !important;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.11) !important;
}

/* Paginación */
.fi-pagination,
.fi-ta-pagination {
    background: #ffffff !important;
    border-top: 1px solid var(--bexia-table-border-soft) !important;
}

.fi-pagination .fi-btn,
.fi-ta-pagination .fi-btn,
.fi-pagination button,
.fi-ta-pagination button {
    border-radius: 12px !important;
}

/* Estado vacío */
.fi-ta-empty-state {
    background:
        radial-gradient(circle at top, rgba(148, 163, 184, 0.10), transparent 18rem),
        #ffffff !important;
}

.fi-ta-empty-state-heading {
    color: #0f172a !important;
    font-weight: 800 !important;
}

.fi-ta-empty-state-description {
    color: #64748b !important;
}

/* Checkboxes de tabla: conservar fix previo */
.fi-ta-table th[data-column="select"],
.fi-ta-table td[data-column="select"] {
    width: 44px !important;
    min-width: 44px !important;
    max-width: 44px !important;
    text-align: center !important;
}

.fi-ta-table th[data-column="select"] input[type="checkbox"],
.fi-ta-table td[data-column="select"] input[type="checkbox"] {
    width: 16px !important;
    height: 16px !important;
    min-width: 16px !important;
    min-height: 16px !important;
    max-width: 16px !important;
    max-height: 16px !important;
    margin: 0 auto !important;
    padding: 0 !important;
    display: block !important;
    flex: none !important;
}

/* Tablas HTML custom dentro de páginas Filament, sin tocar PDFs/exports */
.fi-page table:not(.fi-ta-table):not(.flatpickr-calendar table) {
    border-radius: 16px !important;
}

.fi-page table:not(.fi-ta-table):not(.flatpickr-calendar table) thead {
    background: #f8fafc !important;
}

.fi-page table:not(.fi-ta-table):not(.flatpickr-calendar table) th {
    color: #475569 !important;
    font-weight: 800 !important;
}

.fi-page table:not(.fi-ta-table):not(.flatpickr-calendar table) tbody tr:hover {
    background: #f8fafc !important;
}

/* Móvil: no forzar transformaciones ni anchos raros */
@media (max-width: 768px) {
    .fi-ta {
        border-radius: 18px !important;
    }

    .fi-ta-table th,
    .fi-ta-table td {
        padding-top: .65rem !important;
        padding-bottom: .65rem !important;
    }
}
/* BEXIA_TABLES_LISTS_PREMIUM_V5_79_11B_END */
/* BEXIA_FORMS_MODALS_PREMIUM_V5_79_12B_START */
:root {
    --bexia-form-bg: #ffffff;
    --bexia-form-soft: #f8fafc;
    --bexia-form-soft-2: #f1f5f9;
    --bexia-form-border: #dbe3ee;
    --bexia-form-border-soft: #edf2f7;
    --bexia-form-text: #0f172a;
    --bexia-form-muted: #64748b;
    --bexia-form-primary: #2563eb;
    --bexia-form-primary-dark: #1d4ed8;
    --bexia-form-danger: #dc2626;
    --bexia-form-success: #16a34a;
    --bexia-form-warning: #d97706;
    --bexia-form-radius: 16px;
    --bexia-form-shadow: 0 8px 22px rgba(15, 23, 42, 0.045);
    --bexia-modal-shadow: 0 28px 70px rgba(15, 23, 42, 0.20);
}

/* Contenedores de formularios */
.fi-fo,
.fi-form,
.fi-form > div {
    gap: 1rem !important;
}

/* Sections dentro de forms */
.fi-fo .fi-section,
.fi-form .fi-section {
    border-radius: 20px !important;
    border: 1px solid var(--bexia-form-border-soft) !important;
    background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%) !important;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.035) !important;
}

.fi-fo .fi-section-header,
.fi-form .fi-section-header,
.fi-fo .fi-section header,
.fi-form .fi-section header {
    border-color: var(--bexia-form-border-soft) !important;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%) !important;
}

.fi-fo .fi-section h2,
.fi-fo .fi-section h3,
.fi-form .fi-section h2,
.fi-form .fi-section h3 {
    color: #0f172a !important;
    font-weight: 850 !important;
    letter-spacing: -0.025em !important;
}

.fi-fo .fi-section p,
.fi-form .fi-section p,
.fi-fo .fi-section-description,
.fi-form .fi-section-description {
    color: var(--bexia-form-muted) !important;
}

/* Labels y ayudas */
.fi-fo-field-wrp-label,
.fi-fo-field-wrp-label label,
.fi-fo-field-wrp-label span,
.fi-fieldset legend,
.fi-label {
    color: #334155 !important;
    font-weight: 750 !important;
    letter-spacing: -0.01em !important;
}

.fi-fo-field-wrp-hint,
.fi-fo-field-wrp-helper-text,
.fi-fo-field-wrp-error-message,
.fi-help-text {
    font-size: .78rem !important;
}

.fi-fo-field-wrp-helper-text,
.fi-help-text {
    color: #64748b !important;
}

.fi-fo-field-wrp-error-message {
    color: #dc2626 !important;
    font-weight: 700 !important;
}

/* Wrappers e inputs */
.fi-input-wrp,
.fi-select-input,
.fi-textarea,
.fi-fo input:not([type="checkbox"]):not([type="radio"]),
.fi-fo select,
.fi-fo textarea,
.fi-modal-window input:not([type="checkbox"]):not([type="radio"]),
.fi-modal-window select,
.fi-modal-window textarea {
    border-radius: var(--bexia-form-radius) !important;
    border-color: var(--bexia-form-border) !important;
    background: #ffffff !important;
    color: #0f172a !important;
    min-height: 2.75rem !important;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.85),
        0 1px 2px rgba(15, 23, 42, 0.035) !important;
}

.fi-input-wrp:hover,
.fi-select-input:hover,
.fi-textarea:hover {
    border-color: #cbd5e1 !important;
}

.fi-input-wrp:focus-within,
.fi-select-input:focus,
.fi-textarea:focus,
.fi-fo input:not([type="checkbox"]):not([type="radio"]):focus,
.fi-fo select:focus,
.fi-fo textarea:focus,
.fi-modal-window input:not([type="checkbox"]):not([type="radio"]):focus,
.fi-modal-window select:focus,
.fi-modal-window textarea:focus {
    border-color: rgba(37, 99, 235, 0.85) !important;
    box-shadow:
        0 0 0 4px rgba(37, 99, 235, 0.11),
        0 8px 20px rgba(15, 23, 42, 0.055) !important;
}

.fi-input,
.fi-select-input,
.fi-textarea {
    color: #0f172a !important;
}

.fi-input::placeholder,
.fi-textarea::placeholder,
.fi-fo input::placeholder,
.fi-fo textarea::placeholder {
    color: #94a3b8 !important;
}

/* Selects buscables / Choices */
.fi-fo .choices,
.fi-modal-window .choices {
    border-radius: var(--bexia-form-radius) !important;
}

.fi-fo .choices__inner,
.fi-modal-window .choices__inner {
    border-radius: var(--bexia-form-radius) !important;
    border-color: var(--bexia-form-border) !important;
    background: #ffffff !important;
    min-height: 2.75rem !important;
}

.fi-fo .choices__list--dropdown,
.fi-modal-window .choices__list--dropdown {
    border-radius: 16px !important;
    border-color: var(--bexia-form-border) !important;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12) !important;
    overflow: hidden !important;
}

/* Textareas */
.fi-textarea,
textarea.fi-textarea,
.fi-fo textarea,
.fi-modal-window textarea {
    min-height: 6.5rem !important;
    line-height: 1.45 !important;
}

/* Toggles */
.fi-fo-toggle,
.fi-toggle {
    align-items: center !important;
}

.fi-fo-toggle button,
.fi-toggle button,
button[role="switch"] {
    border-radius: 999px !important;
    border: 1px solid #cbd5e1 !important;
    background: #e2e8f0 !important;
    box-shadow:
        inset 0 1px 2px rgba(15, 23, 42, 0.10),
        0 1px 2px rgba(15, 23, 42, 0.04) !important;
}

.fi-fo-toggle button[aria-checked="true"],
.fi-toggle button[aria-checked="true"],
button[role="switch"][aria-checked="true"] {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
    border-color: #1d4ed8 !important;
    box-shadow:
        0 8px 18px rgba(37, 99, 235, 0.26),
        inset 0 1px 1px rgba(255, 255, 255, 0.18) !important;
}

.fi-fo-toggle button > span,
.fi-toggle button > span,
button[role="switch"] > span {
    background: #ffffff !important;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.18) !important;
}

/* Checkboxes/radios normales: no deformar tablas */
.fi-fo input[type="checkbox"]:not([role="switch"]),
.fi-modal-window input[type="checkbox"]:not([role="switch"]),
.fi-fo input[type="radio"],
.fi-modal-window input[type="radio"] {
    border-color: #94a3b8 !important;
}

.fi-fo input[type="checkbox"]:checked,
.fi-modal-window input[type="checkbox"]:checked,
.fi-fo input[type="radio"]:checked,
.fi-modal-window input[type="radio"]:checked {
    background-color: #2563eb !important;
    border-color: #2563eb !important;
}

/* Tabs en formularios */
.fi-tabs {
    border-radius: 18px !important;
    background: #f8fafc !important;
    border: 1px solid var(--bexia-form-border-soft) !important;
    padding: .25rem !important;
}

.fi-tabs-item {
    border-radius: 14px !important;
    font-weight: 750 !important;
    color: #64748b !important;
}

.fi-tabs-item:hover {
    background: #eef2f7 !important;
    color: #0f172a !important;
}

.fi-tabs-item.fi-active,
.fi-tabs-item[aria-selected="true"] {
    background: #ffffff !important;
    color: #0f172a !important;
    box-shadow:
        0 6px 14px rgba(15, 23, 42, 0.07),
        inset 0 0 0 1px rgba(148, 163, 184, 0.18) !important;
}

/* Fieldsets, repeaters y file uploads */
.fi-fieldset,
.fi-fo-repeater,
.fi-fo-repeater-item,
.fi-fo-file-upload,
.fi-fo-builder,
.fi-fo-wizard {
    border-radius: 18px !important;
    border-color: var(--bexia-form-border-soft) !important;
    background: #ffffff !important;
}

.fi-fo-repeater-item,
.fi-fo-file-upload {
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.035) !important;
}

/* Placeholders/infolist dentro de forms */
.fi-fo-placeholder,
.fi-in-entry,
.fi-in-text-entry {
    color: #334155 !important;
}

.fi-fo-placeholder .text-gray-500,
.fi-in-entry .text-gray-500 {
    color: #64748b !important;
}

/* Modales */
.fi-modal-window {
    border-radius: 24px !important;
    border: 1px solid rgba(226, 232, 240, 0.95) !important;
    background: #ffffff !important;
    box-shadow: var(--bexia-modal-shadow) !important;
    overflow: hidden !important;
}

.fi-modal-header {
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%) !important;
    border-bottom: 1px solid var(--bexia-form-border-soft) !important;
}

.fi-modal-heading {
    color: #0f172a !important;
    font-weight: 850 !important;
    letter-spacing: -0.025em !important;
}

.fi-modal-description {
    color: #64748b !important;
    line-height: 1.45 !important;
}

.fi-modal-content {
    background: #ffffff !important;
}

.fi-modal-footer {
    background: #fbfdff !important;
    border-top: 1px solid var(--bexia-form-border-soft) !important;
}

/* Botones de formularios y modales */
.fi-form-actions .fi-btn,
.fi-modal-footer .fi-btn,
.fi-ac-actions .fi-btn,
.fi-modal-window .fi-btn {
    border-radius: 15px !important;
    min-height: 2.65rem !important;
    font-weight: 800 !important;
    letter-spacing: -0.01em !important;
}

.fi-form-actions .fi-btn.fi-color-primary,
.fi-modal-footer .fi-btn.fi-color-primary,
.fi-modal-window .fi-btn.fi-color-primary,
.fi-btn.fi-color-primary {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
    color: #ffffff !important;
    box-shadow:
        0 12px 26px rgba(37, 99, 235, 0.24),
        0 4px 10px rgba(37, 99, 235, 0.12) !important;
}

.fi-form-actions .fi-btn.fi-color-primary *,
.fi-modal-footer .fi-btn.fi-color-primary *,
.fi-modal-window .fi-btn.fi-color-primary *,
.fi-btn.fi-color-primary * {
    color: #ffffff !important;
}

.fi-form-actions .fi-btn.fi-color-gray,
.fi-modal-footer .fi-btn.fi-color-gray,
.fi-modal-window .fi-btn.fi-color-gray,
.fi-btn.fi-color-gray {
    background: #ffffff !important;
    color: #334155 !important;
    border: 1px solid #cbd5e1 !important;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.055) !important;
}

.fi-form-actions .fi-btn.fi-color-gray:hover,
.fi-modal-footer .fi-btn.fi-color-gray:hover,
.fi-modal-window .fi-btn.fi-color-gray:hover,
.fi-btn.fi-color-gray:hover {
    background: #f8fafc !important;
    color: #0f172a !important;
    border-color: #94a3b8 !important;
}

/* Estados peligrosos se conservan rojos */
.fi-btn.fi-color-danger,
.fi-modal-footer .fi-btn.fi-color-danger,
.fi-modal-window .fi-btn.fi-color-danger {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 12px 26px rgba(220, 38, 38, 0.22) !important;
}

.fi-btn.fi-color-success,
.fi-modal-footer .fi-btn.fi-color-success,
.fi-modal-window .fi-btn.fi-color-success {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 12px 26px rgba(22, 163, 74, 0.20) !important;
}

.fi-btn.fi-color-warning,
.fi-modal-footer .fi-btn.fi-color-warning,
.fi-modal-window .fi-btn.fi-color-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 12px 26px rgba(245, 158, 11, 0.20) !important;
}

/* Date/time picker y dropdowns de campos */
.fi-dropdown-panel,
.fi-select-panel,
.fi-fo-date-time-picker-panel {
    border-radius: 18px !important;
    border-color: var(--bexia-form-border) !important;
    box-shadow: 0 18px 44px rgba(15, 23, 42, 0.13) !important;
}

/* No tocar login premium */
.fi-simple-main .fi-input-wrp,
.fi-simple-main .fi-form-actions,
.fi-simple-main .fi-btn {
    /* mantiene reglas previas del login */
}

/* Responsive */
@media (max-width: 768px) {
    .fi-modal-window {
        border-radius: 20px !important;
    }

    .fi-fo .fi-section,
    .fi-form .fi-section {
        border-radius: 18px !important;
    }

    .fi-tabs {
        overflow-x: auto !important;
    }
}

/* BEXIA_FORM_FIELD_CONTRAST_V5_79_12B2_START */
:root {
    --bexia-field-border-strong: #cbd5e1;
    --bexia-field-border-strong-hover: #94a3b8;
    --bexia-field-border-strong-focus: #64748b;
    --bexia-field-shadow-strong: 0 1px 2px rgba(15, 23, 42, 0.055);
    --bexia-field-focus-ring-strong: 0 0 0 3px rgba(100, 116, 139, 0.13);
}

/* Refuerzo visible de contorno en formularios y modales */
.fi-fo .fi-input-wrp,
.fi-form .fi-input-wrp,
.fi-modal-window .fi-input-wrp,
.fi-fo .fi-select-input,
.fi-form .fi-select-input,
.fi-modal-window .fi-select-input,
.fi-fo .fi-textarea,
.fi-form .fi-textarea,
.fi-modal-window .fi-textarea,
.fi-fo input:not([type="checkbox"]):not([type="radio"]),
.fi-form input:not([type="checkbox"]):not([type="radio"]),
.fi-modal-window input:not([type="checkbox"]):not([type="radio"]),
.fi-fo select,
.fi-form select,
.fi-modal-window select,
.fi-fo textarea,
.fi-form textarea,
.fi-modal-window textarea {
    border: 1px solid var(--bexia-field-border-strong) !important;
    background: #ffffff !important;
    box-shadow: var(--bexia-field-shadow-strong) !important;
}

.fi-fo .fi-input-wrp:hover,
.fi-form .fi-input-wrp:hover,
.fi-modal-window .fi-input-wrp:hover,
.fi-fo .fi-select-input:hover,
.fi-form .fi-select-input:hover,
.fi-modal-window .fi-select-input:hover,
.fi-fo .fi-textarea:hover,
.fi-form .fi-textarea:hover,
.fi-modal-window .fi-textarea:hover {
    border-color: var(--bexia-field-border-strong-hover) !important;
}

.fi-fo .fi-input-wrp:focus-within,
.fi-form .fi-input-wrp:focus-within,
.fi-modal-window .fi-input-wrp:focus-within,
.fi-fo .fi-select-input:focus,
.fi-form .fi-select-input:focus,
.fi-modal-window .fi-select-input:focus,
.fi-fo .fi-textarea:focus,
.fi-form .fi-textarea:focus,
.fi-modal-window .fi-textarea:focus,
.fi-fo input:not([type="checkbox"]):not([type="radio"]):focus,
.fi-form input:not([type="checkbox"]):not([type="radio"]):focus,
.fi-modal-window input:not([type="checkbox"]):not([type="radio"]):focus,
.fi-fo select:focus,
.fi-form select:focus,
.fi-modal-window select:focus,
.fi-fo textarea:focus,
.fi-form textarea:focus,
.fi-modal-window textarea:focus {
    border-color: var(--bexia-field-border-strong-focus) !important;
    box-shadow:
        var(--bexia-field-focus-ring-strong),
        var(--bexia-field-shadow-strong) !important;
}

/* Selects buscables tipo Choices/TomSelect */
.fi-fo .choices__inner,
.fi-form .choices__inner,
.fi-modal-window .choices__inner,
.fi-fo .ts-control,
.fi-form .ts-control,
.fi-modal-window .ts-control {
    border: 1px solid var(--bexia-field-border-strong) !important;
    background: #ffffff !important;
    box-shadow: var(--bexia-field-shadow-strong) !important;
}

.fi-fo .choices.is-focused .choices__inner,
.fi-form .choices.is-focused .choices__inner,
.fi-modal-window .choices.is-focused .choices__inner,
.fi-fo .ts-wrapper.focus .ts-control,
.fi-form .ts-wrapper.focus .ts-control,
.fi-modal-window .ts-wrapper.focus .ts-control {
    border-color: var(--bexia-field-border-strong-focus) !important;
    box-shadow:
        var(--bexia-field-focus-ring-strong),
        var(--bexia-field-shadow-strong) !important;
}

/* Mantener disabled más claro pero todavía visible */
.fi-fo .fi-input-wrp:has(input:disabled),
.fi-form .fi-input-wrp:has(input:disabled),
.fi-modal-window .fi-input-wrp:has(input:disabled),
.fi-fo input:disabled,
.fi-form input:disabled,
.fi-modal-window input:disabled,
.fi-fo textarea:disabled,
.fi-form textarea:disabled,
.fi-modal-window textarea:disabled {
    background: #f8fafc !important;
    border-color: #d6dee9 !important;
    color: #64748b !important;
}
/* BEXIA_FORM_FIELD_CONTRAST_V5_79_12B2_END */

/* BEXIA_SELECT_DOUBLE_BORDER_FIX_V5_79_12B3_START */

/* 1) Quitar el borde/sombra del contenedor externo solo en selects mejorados */
.fi-fo .fi-input-wrp:has(.choices__inner),
.fi-form .fi-input-wrp:has(.choices__inner),
.fi-modal-window .fi-input-wrp:has(.choices__inner),
.fi-fo .fi-input-wrp:has(.ts-control),
.fi-form .fi-input-wrp:has(.ts-control),
.fi-modal-window .fi-input-wrp:has(.ts-control),
.fi-fo .fi-input-wrp:has(.fi-select-input),
.fi-form .fi-input-wrp:has(.fi-select-input),
.fi-modal-window .fi-input-wrp:has(.fi-select-input) {
    border: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    padding: 0 !important;
}

/* 2) Dejar un solo contorno limpio y redondeado en el control interno */
.fi-fo .choices__inner,
.fi-form .choices__inner,
.fi-modal-window .choices__inner,
.fi-fo .ts-control,
.fi-form .ts-control,
.fi-modal-window .ts-control,
.fi-fo .fi-select-input,
.fi-form .fi-select-input,
.fi-modal-window .fi-select-input {
    border: 1px solid var(--bexia-field-border-strong) !important;
    border-radius: 0.75rem !important;
    background: #ffffff !important;
    box-shadow: var(--bexia-field-shadow-strong) !important;
    min-height: 2.75rem !important;
}

/* 3) Hover/focus del control interno */
.fi-fo .choices__inner:hover,
.fi-form .choices__inner:hover,
.fi-modal-window .choices__inner:hover,
.fi-fo .ts-control:hover,
.fi-form .ts-control:hover,
.fi-modal-window .ts-control:hover,
.fi-fo .fi-select-input:hover,
.fi-form .fi-select-input:hover,
.fi-modal-window .fi-select-input:hover {
    border-color: var(--bexia-field-border-strong-hover) !important;
}

.fi-fo .choices.is-focused .choices__inner,
.fi-form .choices.is-focused .choices__inner,
.fi-modal-window .choices.is-focused .choices__inner,
.fi-fo .ts-wrapper.focus .ts-control,
.fi-form .ts-wrapper.focus .ts-control,
.fi-modal-window .ts-wrapper.focus .ts-control,
.fi-fo .fi-select-input:focus,
.fi-form .fi-select-input:focus,
.fi-modal-window .fi-select-input:focus,
.fi-fo .fi-input-wrp:has(.choices__inner):focus-within .choices__inner,
.fi-form .fi-input-wrp:has(.choices__inner):focus-within .choices__inner,
.fi-modal-window .fi-input-wrp:has(.choices__inner):focus-within .choices__inner,
.fi-fo .fi-input-wrp:has(.ts-control):focus-within .ts-control,
.fi-form .fi-input-wrp:has(.ts-control):focus-within .ts-control,
.fi-modal-window .fi-input-wrp:has(.ts-control):focus-within .ts-control {
    border-color: var(--bexia-field-border-strong-focus) !important;
    box-shadow:
        var(--bexia-field-focus-ring-strong),
        var(--bexia-field-shadow-strong) !important;
}

/* 4) Afinar padding interno para que no se vea apretado */
.fi-fo .choices__inner,
.fi-form .choices__inner,
.fi-modal-window .choices__inner {
    padding-top: 0.22rem !important;
    padding-bottom: 0.22rem !important;
}

.fi-fo .choices[data-type*="select-one"] .choices__inner,
.fi-form .choices[data-type*="select-one"] .choices__inner,
.fi-modal-window .choices[data-type*="select-one"] .choices__inner {
    padding-bottom: 0.22rem !important;
}

/* BEXIA_SELECT_DOUBLE_BORDER_FIX_V5_79_12B3_END */

/* BEXIA_SELECT_VERTICAL_ALIGN_FIX_V5_79_12B5_START */

/* Centrado vertical de selects/dropdowns Filament */
.fi-fo .fi-select-input,
.fi-form .fi-select-input,
.fi-modal-window .fi-select-input {
    min-height: 2.75rem !important;
    height: 2.75rem !important;
    line-height: 1.25rem !important;
    padding-top: 0.68rem !important;
    padding-bottom: 0.68rem !important;
    display: flex !important;
    align-items: center !important;
}

/* Choices.js: texto seleccionado centrado */
.fi-fo .choices__inner,
.fi-form .choices__inner,
.fi-modal-window .choices__inner {
    min-height: 2.75rem !important;
    height: 2.75rem !important;
    display: flex !important;
    align-items: center !important;
    padding: 0 0.75rem !important;
}

.fi-fo .choices__list--single,
.fi-form .choices__list--single,
.fi-modal-window .choices__list--single {
    display: flex !important;
    align-items: center !important;
    padding: 0 !important;
    min-height: 2.65rem !important;
    line-height: 1.25rem !important;
}

.fi-fo .choices__item--selectable,
.fi-form .choices__item--selectable,
.fi-modal-window .choices__item--selectable {
    display: flex !important;
    align-items: center !important;
    min-height: 1.5rem !important;
    line-height: 1.25rem !important;
}

/* TomSelect: texto seleccionado centrado */
.fi-fo .ts-control,
.fi-form .ts-control,
.fi-modal-window .ts-control {
    min-height: 2.75rem !important;
    height: 2.75rem !important;
    display: flex !important;
    align-items: center !important;
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
    line-height: 1.25rem !important;
}

.fi-fo .ts-control > input,
.fi-form .ts-control > input,
.fi-modal-window .ts-control > input,
.fi-fo .ts-control .item,
.fi-form .ts-control .item,
.fi-modal-window .ts-control .item {
    line-height: 1.25rem !important;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}

/* Select con botón limpiar X / iconos */
.fi-fo .fi-input-wrp:has(.fi-select-input),
.fi-form .fi-input-wrp:has(.fi-select-input),
.fi-modal-window .fi-input-wrp:has(.fi-select-input) {
    min-height: 2.75rem !important;
    display: flex !important;
    align-items: center !important;
}

.fi-fo .fi-input-wrp:has(.fi-select-input) .fi-input-wrp-prefix,
.fi-form .fi-input-wrp:has(.fi-select-input) .fi-input-wrp-prefix,
.fi-modal-window .fi-input-wrp:has(.fi-select-input) .fi-input-wrp-prefix,
.fi-fo .fi-input-wrp:has(.fi-select-input) .fi-input-wrp-suffix,
.fi-form .fi-input-wrp:has(.fi-select-input) .fi-input-wrp-suffix,
.fi-modal-window .fi-input-wrp:has(.fi-select-input) .fi-input-wrp-suffix {
    display: flex !important;
    align-items: center !important;
    min-height: 2.75rem !important;
}

/* BEXIA_SELECT_VERTICAL_ALIGN_FIX_V5_79_12B5_END */
/* BEXIA_FORMS_MODALS_PREMIUM_V5_79_12B_END */

/* BEXIA_RESPONSIVE_MOBILE_TABLET_V5_79_13B_START */

/* Ajuste general para tablet */
@media (max-width: 1024px) {
    .fi-main,
    .fi-main-ctn,
    .fi-page {
        min-width: 0 !important;
        overflow-x: hidden !important;
    }

    .fi-page {
        padding-inline: 1rem !important;
    }

    .fi-topbar {
        min-width: 0 !important;
    }

    .fi-topbar nav {
        min-width: 0 !important;
        gap: .5rem !important;
    }

    .fi-header,
    .fi-page-header,
    .fi-header-heading {
        min-width: 0 !important;
    }

    .fi-header-heading,
    .fi-header-heading h1,
    .fi-header-heading h2 {
        overflow-wrap: anywhere !important;
        word-break: normal !important;
    }

    .fi-header-actions,
    .fi-ac,
    .fi-ac-actions {
        flex-wrap: wrap !important;
        gap: .5rem !important;
    }

    .fi-modal-window {
        width: calc(100vw - 2rem) !important;
        max-width: calc(100vw - 2rem) !important;
    }
}

/* Ajuste principal para móvil */
@media (max-width: 768px) {
    html,
    body {
        overflow-x: hidden !important;
    }

    .fi-main,
    .fi-main-ctn {
        min-width: 0 !important;
        width: 100% !important;
        max-width: 100vw !important;
        overflow-x: hidden !important;
    }

    .fi-main {
        padding-top: .25rem !important;
    }

    .fi-page {
        width: 100% !important;
        max-width: 100vw !important;
        min-width: 0 !important;
        padding: .75rem !important;
        gap: .75rem !important;
    }

    .fi-header {
        gap: .75rem !important;
    }

    .fi-header,
    .fi-header > div,
    .fi-page-header,
    .fi-page-header > div {
        min-width: 0 !important;
        width: 100% !important;
    }

    .fi-header-heading h1,
    .fi-header-heading h2,
    .fi-header-heading {
        font-size: 1.2rem !important;
        line-height: 1.3 !important;
        letter-spacing: -0.025em !important;
        overflow-wrap: anywhere !important;
    }

    .fi-breadcrumbs,
    .fi-breadcrumbs ol {
        max-width: 100% !important;
        overflow-x: auto !important;
        white-space: nowrap !important;
        scrollbar-width: thin !important;
    }

    /* Topbar móvil */
    .fi-topbar {
        min-height: 3.5rem !important;
        padding-inline: .5rem !important;
        border-bottom: 1px solid rgba(226, 232, 240, 0.9) !important;
    }

    .fi-topbar nav {
        min-width: 0 !important;
        width: 100% !important;
        gap: .35rem !important;
    }

    .fi-topbar .fi-icon-btn,
    .fi-topbar button {
        min-width: 2.35rem !important;
        min-height: 2.35rem !important;
        border-radius: .85rem !important;
    }

    .fi-topbar .fi-dropdown-trigger,
    .fi-topbar .fi-user-menu-trigger {
        min-width: 0 !important;
    }

    .bexia-topbar-company-switcher,
    .bexia-approval-topbar,
    .bexia-approval-topbar__inner {
        max-width: 100% !important;
        min-width: 0 !important;
        overflow-x: auto !important;
        scrollbar-width: thin !important;
    }

    .bexia-approval-topbar__link {
        white-space: nowrap !important;
        flex-shrink: 0 !important;
    }

    /* Cards/widgets en móvil */
    .fi-widget,
    .fi-section,
    .fi-card,
    .fi-in-card,
    .fi-simple-page-section,
    .bexia-dashboard-section,
    .bexia-dashboard-section-widget {
        border-radius: 16px !important;
        max-width: 100% !important;
        min-width: 0 !important;
    }

    .fi-section-content,
    .fi-card-content {
        min-width: 0 !important;
    }

    /* Tablas: scroll horizontal seguro */
    .fi-ta {
        max-width: 100% !important;
        overflow: hidden !important;
        border-radius: 16px !important;
    }

    .fi-ta-header,
    .fi-ta-header-toolbar,
    .fi-ta-toolbar,
    .fi-ta-filters,
    .fi-ta-selection-indicator {
        flex-wrap: wrap !important;
        gap: .5rem !important;
        padding: .75rem !important;
    }

    .fi-ta-content,
    .fi-ta-table-wrap,
    .fi-ta-table-container,
    .fi-ta .overflow-x-auto {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
        scrollbar-width: thin !important;
    }

    .fi-ta-table {
        min-width: 720px !important;
    }

    .fi-ta-table th,
    .fi-ta-table td {
        padding: .65rem .75rem !important;
        font-size: .82rem !important;
        white-space: nowrap !important;
    }

    .fi-ta-table th[data-column="select"],
    .fi-ta-table td[data-column="select"] {
        min-width: 42px !important;
        max-width: 42px !important;
    }

    .fi-ta-pagination {
        flex-wrap: wrap !important;
        gap: .5rem !important;
        padding: .75rem !important;
    }

    .fi-ta-pagination > div {
        min-width: 0 !important;
    }

    /* Formularios */
    .fi-fo,
    .fi-form,
    .fi-form > div {
        gap: .8rem !important;
        min-width: 0 !important;
    }

    .fi-fo .fi-section,
    .fi-form .fi-section {
        border-radius: 16px !important;
    }

    .fi-fo .grid,
    .fi-form .grid,
    .fi-section .grid {
        min-width: 0 !important;
    }

    .fi-input-wrp,
    .fi-select-input,
    .fi-textarea,
    .fi-fo input:not([type="checkbox"]):not([type="radio"]),
    .fi-fo select,
    .fi-fo textarea {
        min-width: 0 !important;
        width: 100% !important;
    }

    /* Tabs */
    .fi-tabs {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        display: flex !important;
        flex-wrap: nowrap !important;
        scrollbar-width: thin !important;
    }

    .fi-tabs-item {
        flex: 0 0 auto !important;
        white-space: nowrap !important;
    }

    /* Modales */
    .fi-modal,
    .fi-modal-window {
        max-width: calc(100vw - 1rem) !important;
    }

    .fi-modal-window {
        width: calc(100vw - 1rem) !important;
        border-radius: 18px !important;
        margin-inline: .5rem !important;
        max-height: calc(100vh - 1rem) !important;
    }

    .fi-modal-header,
    .fi-modal-content,
    .fi-modal-footer {
        padding-inline: 1rem !important;
    }

    .fi-modal-heading {
        font-size: 1.05rem !important;
        line-height: 1.3 !important;
    }

    .fi-modal-footer,
    .fi-modal-footer .fi-ac,
    .fi-modal-footer .fi-ac-actions {
        flex-wrap: wrap !important;
        gap: .5rem !important;
    }

    .fi-modal-footer .fi-btn,
    .fi-form-actions .fi-btn {
        width: 100% !important;
        justify-content: center !important;
    }

    /* Botones/actions */
    .fi-btn {
        min-height: 2.55rem !important;
        border-radius: .85rem !important;
    }

    .fi-ac,
    .fi-ac-actions,
    .fi-form-actions {
        flex-wrap: wrap !important;
        gap: .5rem !important;
    }

    /* Mapas geocerca */
    .bexia-geofence-map {
        max-width: 100% !important;
        overflow-x: hidden !important;
    }

    .bexia-geofence-map .leaflet-container {
        height: 360px !important;
        min-height: 360px !important;
        max-height: 65vh !important;
        border-radius: 14px !important;
    }

    .bexia-geofence-map .leaflet-control-zoom a {
        width: 34px !important;
        height: 34px !important;
        line-height: 34px !important;
    }

    /* Páginas custom con tablas manuales */
    .fi-page table:not(.fi-ta-table):not(.flatpickr-calendar table) {
        min-width: 720px !important;
    }

    .fi-page .overflow-x-auto,
    .fi-page [style*="overflow-x"] {
        -webkit-overflow-scrolling: touch !important;
        scrollbar-width: thin !important;
    }

    /* Evitar que textos largos rompan layouts */
    code,
    pre,
    .fi-in-text-entry,
    .fi-fo-placeholder,
    .fi-ta-text {
        overflow-wrap: anywhere !important;
        word-break: normal !important;
    }
}

/* Móvil pequeño */
@media (max-width: 480px) {
    .fi-page {
        padding: .6rem !important;
    }

    .fi-header-heading h1,
    .fi-header-heading h2,
    .fi-header-heading {
        font-size: 1.08rem !important;
    }

    .fi-section,
    .fi-card,
    .fi-ta,
    .fi-modal-window {
        border-radius: 14px !important;
    }

    .fi-ta-table {
        min-width: 680px !important;
    }

    .fi-ta-table th,
    .fi-ta-table td {
        padding: .58rem .65rem !important;
        font-size: .78rem !important;
    }

    .fi-modal-window {
        width: calc(100vw - .5rem) !important;
        max-width: calc(100vw - .5rem) !important;
        margin-inline: .25rem !important;
    }

    .fi-modal-header,
    .fi-modal-content,
    .fi-modal-footer {
        padding-inline: .85rem !important;
    }

    .bexia-geofence-map .leaflet-container {
        height: 320px !important;
        min-height: 320px !important;
    }

    .fi-form-actions,
    .fi-modal-footer .fi-ac-actions {
        width: 100% !important;
    }
}

/* Landscape de teléfono */
@media (max-width: 900px) and (orientation: landscape) {
    .fi-modal-window {
        max-height: calc(100vh - .75rem) !important;
    }

    .bexia-geofence-map .leaflet-container {
        height: 300px !important;
        min-height: 300px !important;
    }
}

/* BEXIA_RESPONSIVE_MOBILE_TABLET_V5_79_13B_END */

/* BEXIA_PRODUCT_RESOURCE_RESPONSIVE_V5_79_18C3_START */
/*
 * ProductResource responsive refinements.
 * Alcance: formulario de productos, tabs, repeaters y tablas HTML internas.
 * No cambia datos ni permisos; solo evita desbordes horizontales en pantallas chicas.
 */
.bexia-product-resource-tabs {
    max-width: 100% !important;
}

.bexia-product-resource-tabs .fi-tabs,
.bexia-product-resource-tabs [role="tablist"] {
    max-width: 100% !important;
    overflow-x: auto !important;
    overflow-y: hidden !important;
    -webkit-overflow-scrolling: touch !important;
    scrollbar-width: thin !important;
}

.bexia-product-resource-tabs [role="tab"] {
    flex: 0 0 auto !important;
    white-space: nowrap !important;
}

.bexia-product-resource-repeater,
.bexia-product-resource-repeater .fi-fo-repeater,
.bexia-product-resource-repeater .fi-fo-repeater-item,
.bexia-product-resource-repeater .fi-fo-component-ctn {
    max-width: 100% !important;
    overflow-x: hidden !important;
}

.bexia-product-resource-repeater .fi-fo-field-wrp,
.bexia-product-resource-repeater .fi-input-wrp,
.bexia-product-resource-repeater .fi-select-input,
.bexia-product-resource-repeater input:not([type="checkbox"]):not([type="radio"]),
.bexia-product-resource-repeater select,
.bexia-product-resource-repeater textarea {
    min-width: 0 !important;
    max-width: 100% !important;
}

.bexia-product-resource-table-wrap {
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: auto !important;
    overflow-y: hidden !important;
    -webkit-overflow-scrolling: touch !important;
    border-radius: 12px !important;
}

.bexia-product-resource-table {
    width: 100% !important;
    min-width: 680px !important;
}

.bexia-product-resource-table th,
.bexia-product-resource-table td {
    white-space: nowrap !important;
}

@media (max-width: 1024px) {
    .bexia-product-resource-tabs .fi-section,
    .bexia-product-resource-tabs .fi-fo-component-ctn,
    .bexia-product-resource-tabs .fi-grid,
    .bexia-product-resource-tabs .grid {
        min-width: 0 !important;
        max-width: 100% !important;
    }
}

@media (max-width: 768px) {
    .bexia-product-resource-tabs .fi-section,
    .bexia-product-resource-tabs .fi-fo-repeater-item {
        padding-left: 0.85rem !important;
        padding-right: 0.85rem !important;
    }

    .bexia-product-resource-table {
        min-width: 620px !important;
        font-size: 11px !important;
    }

    .bexia-product-resource-table th,
    .bexia-product-resource-table td {
        padding: 5px !important;
    }
}

@media (max-width: 640px) {
    .bexia-product-resource-tabs [role="tab"] {
        font-size: 0.78rem !important;
        padding-left: 0.7rem !important;
        padding-right: 0.7rem !important;
    }

    .bexia-product-resource-repeater .fi-fo-repeater-item {
        border-radius: 14px !important;
    }
}
/* BEXIA_PRODUCT_RESOURCE_RESPONSIVE_V5_79_18C3_END */


/* BEXIA_SALE_ORDER_RESOURCE_RESPONSIVE_V5_79_19C_START */
/*
 * SaleOrderResource responsive refinements.
 * Alcance: formulario de venta, tabs de detalle, previews de margen,
 * modales de cotización a PDV y seguimiento PDV.
 * No cambia datos, totales, estados ni permisos; solo evita desbordes visuales.
 */
.bexia-sale-order-resource-tabs {
    max-width: 100% !important;
}

.bexia-sale-order-resource-tabs .fi-tabs,
.bexia-sale-order-resource-tabs [role="tablist"] {
    max-width: 100% !important;
    overflow-x: auto !important;
    overflow-y: hidden !important;
    -webkit-overflow-scrolling: touch !important;
    scrollbar-width: thin !important;
}

.bexia-sale-order-resource-tabs [role="tab"] {
    flex: 0 0 auto !important;
    white-space: nowrap !important;
}

.bexia-sale-order-resource-tabs .fi-section,
.bexia-sale-order-resource-tabs .fi-fo-component-ctn,
.bexia-sale-order-resource-tabs .fi-grid,
.bexia-sale-order-resource-tabs .grid {
    min-width: 0 !important;
    max-width: 100% !important;
}

.bexia-sale-order-modal-block,
.bexia-sale-order-quote-summary,
.bexia-sale-order-pos-validation,
.bexia-sale-order-pending-pos-ticket,
.bexia-sale-order-pos-tracking-empty,
.bexia-sale-order-pos-tracking-card,
.bexia-sale-order-stock-lines {
    max-width: 100% !important;
    min-width: 0 !important;
    overflow-wrap: anywhere !important;
    word-break: normal !important;
}

.bexia-sale-order-pos-validation ul {
    max-width: 100% !important;
    padding-left: 1.15rem !important;
}

.bexia-sale-order-pos-validation li,
.bexia-sale-order-stock-lines {
    line-height: 1.45 !important;
}

.bexia-sale-order-pending-pos-actions {
    max-width: 100% !important;
}

.bexia-sale-order-pending-pos-actions a {
    max-width: 100% !important;
    text-align: center !important;
    white-space: normal !important;
}

.bexia-sale-order-pos-tracking-header {
    max-width: 100% !important;
}

.bexia-sale-order-pos-tracking-grid {
    max-width: 100% !important;
    min-width: 0 !important;
    grid-template-columns: minmax(130px, 180px) minmax(0, 1fr) !important;
}

.bexia-sale-order-pos-tracking-grid > div {
    min-width: 0 !important;
    overflow-wrap: anywhere !important;
}

.bexia-sale-order-margin-preview {
    max-width: 100% !important;
    white-space: normal !important;
}

@media (max-width: 768px) {
    .bexia-sale-order-resource-tabs [role="tab"] {
        font-size: 0.8rem !important;
        padding-left: 0.75rem !important;
        padding-right: 0.75rem !important;
    }

    .bexia-sale-order-resource-tabs .fi-section {
        padding-left: 0.85rem !important;
        padding-right: 0.85rem !important;
    }

    .bexia-sale-order-modal-block {
        overflow-x: hidden !important;
    }

    .bexia-sale-order-pos-tracking-grid {
        grid-template-columns: 1fr !important;
        gap: 4px 0 !important;
    }

    .bexia-sale-order-pos-tracking-grid > div:nth-child(odd) {
        font-size: 0.74rem !important;
        margin-top: 0.35rem !important;
    }

    .bexia-sale-order-pos-tracking-grid > div:nth-child(even) {
        font-size: 0.86rem !important;
    }

    .bexia-sale-order-margin-preview {
        display: block !important;
        width: 100% !important;
    }
}

@media (max-width: 640px) {
    .bexia-sale-order-quote-summary,
    .bexia-sale-order-pos-validation,
    .bexia-sale-order-pending-pos-ticket,
    .bexia-sale-order-pos-tracking-empty,
    .bexia-sale-order-pos-tracking-card {
        font-size: 0.86rem !important;
    }

    .bexia-sale-order-pos-tracking-card {
        padding: 0.85rem !important;
    }
}
/* BEXIA_SALE_ORDER_RESOURCE_RESPONSIVE_V5_79_19C_END */


/* BEXIA_EMPLOYEE_RESOURCE_RESPONSIVE_V5_79_20C_START */
/*
 * EmployeeResource responsive refinements.
 * Alcance: formulario de empleado, tabs, secciones RRHH/PDV,
 * placeholder de insignias y liga/modal QR de asistencia.
 * No cambia tokens, asistencia, permisos, datos ni acciones.
 */
.bexia-employee-operational-section,
.bexia-employee-resource-tabs,
.bexia-employee-attendance-qr-section,
.bexia-employee-attendance-pos-section,
.bexia-employee-badges-placeholder,
.bexia-employee-attendance-qr-url-field {
    max-width: 100% !important;
    min-width: 0 !important;
}

.bexia-employee-resource-tabs .fi-tabs,
.bexia-employee-resource-tabs [role="tablist"] {
    max-width: 100% !important;
    overflow-x: auto !important;
    overflow-y: hidden !important;
    -webkit-overflow-scrolling: touch !important;
    scrollbar-width: thin !important;
}

.bexia-employee-resource-tabs [role="tab"] {
    flex: 0 0 auto !important;
    white-space: nowrap !important;
}

.bexia-employee-resource-tabs .fi-section,
.bexia-employee-resource-tabs .fi-fo-component-ctn,
.bexia-employee-resource-tabs .fi-grid,
.bexia-employee-resource-tabs .grid,
.bexia-employee-operational-section .fi-grid,
.bexia-employee-attendance-qr-section .fi-grid,
.bexia-employee-attendance-pos-section .fi-grid {
    min-width: 0 !important;
    max-width: 100% !important;
}

.bexia-employee-attendance-qr-url,
.bexia-employee-attendance-qr-empty,
.bexia-employee-qr-modal,
.bexia-employee-qr-modal-empty,
.bexia-employee-qr-modal-link,
.bexia-employee-qr-modal-fallback,
.bexia-employee-badges-placeholder {
    max-width: 100% !important;
    min-width: 0 !important;
    overflow-wrap: anywhere !important;
    word-break: normal !important;
}

.bexia-employee-qr-code-line {
    display: block !important;
    max-width: 100% !important;
    overflow-x: auto !important;
    white-space: pre-wrap !important;
    word-break: break-all !important;
    -webkit-overflow-scrolling: touch !important;
}

.bexia-employee-qr-link {
    display: inline-flex !important;
    max-width: 100% !important;
    overflow-wrap: anywhere !important;
}

.bexia-employee-qr-modal-image-wrap {
    max-width: 100% !important;
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
}

.bexia-employee-qr-modal-image-card {
    max-width: 100% !important;
    width: max-content !important;
    margin-left: auto !important;
    margin-right: auto !important;
}

.bexia-employee-qr-modal-img {
    max-width: min(16rem, 100%) !important;
    height: auto !important;
}

@media (max-width: 768px) {
    .bexia-employee-resource-tabs [role="tab"] {
        font-size: 0.8rem !important;
        padding-left: 0.75rem !important;
        padding-right: 0.75rem !important;
    }

    .bexia-employee-resource-tabs .fi-section,
    .bexia-employee-operational-section,
    .bexia-employee-attendance-qr-section,
    .bexia-employee-attendance-pos-section {
        padding-left: 0.85rem !important;
        padding-right: 0.85rem !important;
    }

    .bexia-employee-qr-modal-image-card {
        width: 100% !important;
        padding: 0.75rem !important;
    }

    .bexia-employee-qr-modal-img {
        width: 100% !important;
        max-width: 14rem !important;
        margin-left: auto !important;
        margin-right: auto !important;
    }
}

@media (max-width: 640px) {
    .bexia-employee-attendance-qr-url,
    .bexia-employee-qr-modal,
    .bexia-employee-qr-modal-fallback,
    .bexia-employee-qr-modal-empty {
        font-size: 0.86rem !important;
    }

    .bexia-employee-qr-code-line {
        font-size: 0.72rem !important;
        padding: 0.65rem !important;
    }
}
/* BEXIA_EMPLOYEE_RESOURCE_RESPONSIVE_V5_79_20C_END */


/* BEXIA_PURCHASE_REQUEST_RESOURCE_RESPONSIVE_V5_79_21C_START */
/*
 * PurchaseRequestResource responsive refinements.
 * Alcance: secciones de solicitud de compra, productos,
 * historial de aprobación y tablas HtmlString de historial/totales.
 * No cambia lógica de compras, aprobación, totales, permisos ni datos.
 */
.bexia-purchase-request-main-section,
.bexia-purchase-request-products-section,
.bexia-purchase-request-history-section,
.bexia-purchase-request-status-history-field {
    max-width: 100% !important;
    min-width: 0 !important;
}

.bexia-purchase-request-main-section .fi-grid,
.bexia-purchase-request-products-section .fi-grid,
.bexia-purchase-request-history-section .fi-grid,
.bexia-purchase-request-main-section .fi-fo-component-ctn,
.bexia-purchase-request-products-section .fi-fo-component-ctn,
.bexia-purchase-request-history-section .fi-fo-component-ctn {
    max-width: 100% !important;
    min-width: 0 !important;
}

.bexia-purchase-request-history-empty,
.bexia-purchase-request-history-unavailable,
.bexia-purchase-request-totals-empty {
    max-width: 100% !important;
    color: #6b7280 !important;
    overflow-wrap: anywhere !important;
}

.bexia-purchase-request-status-history-wrap {
    max-width: 100% !important;
    overflow-x: auto !important;
    overflow-y: hidden !important;
    -webkit-overflow-scrolling: touch !important;
    scrollbar-width: thin !important;
    border-radius: 0.85rem !important;
}

.bexia-purchase-request-status-history-table {
    width: 100% !important;
    min-width: 720px !important;
    border-collapse: collapse !important;
    font-size: 0.8125rem !important;
}

.bexia-purchase-request-status-history-header-row {
    background: #f8fafc !important;
}

.dark .bexia-purchase-request-status-history-header-row {
    background: rgba(15, 23, 42, 0.72) !important;
}

.bexia-purchase-request-status-history-th {
    text-align: left !important;
    padding: 0.55rem 0.65rem !important;
    border-bottom: 1px solid #e5e7eb !important;
    white-space: nowrap !important;
    color: #374151 !important;
    font-weight: 700 !important;
}

.dark .bexia-purchase-request-status-history-th {
    border-bottom-color: rgba(71, 85, 105, 0.8) !important;
    color: #e5e7eb !important;
}

.bexia-purchase-request-status-history-td {
    padding: 0.55rem 0.65rem !important;
    border-bottom: 1px solid #f1f5f9 !important;
    vertical-align: top !important;
    color: #374151 !important;
    overflow-wrap: anywhere !important;
}

.dark .bexia-purchase-request-status-history-td {
    border-bottom-color: rgba(51, 65, 85, 0.75) !important;
    color: #d1d5db !important;
}

.bexia-purchase-request-status-history-td--strong {
    font-weight: 700 !important;
}

.bexia-purchase-request-totals-wrap {
    display: flex !important;
    justify-content: flex-end !important;
    max-width: 100% !important;
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
}

.bexia-purchase-request-totals-table {
    min-width: 320px !important;
    max-width: 100% !important;
    font-size: 0.875rem !important;
}

.bexia-purchase-request-totals-label,
.bexia-purchase-request-totals-value,
.bexia-purchase-request-totals-total-label,
.bexia-purchase-request-totals-total-value {
    padding: 0.3rem 0.5rem !important;
    text-align: right !important;
    white-space: nowrap !important;
}

.bexia-purchase-request-totals-label {
    color: #374151 !important;
}

.dark .bexia-purchase-request-totals-label {
    color: #d1d5db !important;
}

.bexia-purchase-request-totals-value {
    font-weight: 600 !important;
}

.bexia-purchase-request-totals-total-label,
.bexia-purchase-request-totals-total-value {
    font-size: 1rem !important;
    font-weight: 800 !important;
}

@media (max-width: 768px) {
    .bexia-purchase-request-main-section,
    .bexia-purchase-request-products-section,
    .bexia-purchase-request-history-section {
        padding-left: 0.85rem !important;
        padding-right: 0.85rem !important;
    }

    .bexia-purchase-request-status-history-table {
        min-width: 640px !important;
        font-size: 0.76rem !important;
    }

    .bexia-purchase-request-status-history-th,
    .bexia-purchase-request-status-history-td {
        padding: 0.5rem 0.55rem !important;
    }

    .bexia-purchase-request-totals-wrap {
        justify-content: flex-start !important;
    }

    .bexia-purchase-request-totals-table {
        min-width: 280px !important;
        width: 100% !important;
    }
}

@media (max-width: 640px) {
    .bexia-purchase-request-status-history-table {
        min-width: 580px !important;
    }

    .bexia-purchase-request-totals-label,
    .bexia-purchase-request-totals-value,
    .bexia-purchase-request-totals-total-label,
    .bexia-purchase-request-totals-total-value {
        white-space: normal !important;
        overflow-wrap: anywhere !important;
    }
}
/* BEXIA_PURCHASE_REQUEST_RESOURCE_RESPONSIVE_V5_79_21C_END */


/* BEXIA_CONTACT_RESOURCE_RESPONSIVE_V5_79_22C_START */
/*
 * ContactResource responsive refinements.
 * Alcance: tabs, secciones de contacto/facturación, Constancia SAT,
 * RFC/CURP uppercase por clase y botones del bloque CSF.
 * No cambia lógica de contactos, permisos, SAT, importación ni archivado.
 */
.bexia-contact-resource-tabs,
.bexia-contact-main-section,
.bexia-contact-details-section,
.bexia-contact-address-section,
.bexia-contact-fiscal-section,
.bexia-contact-csf-section,
.bexia-contact-payment-section,
.bexia-contact-sales-section,
.bexia-contact-purchases-section,
.bexia-contact-csf-file-info-field {
    max-width: 100% !important;
    min-width: 0 !important;
}

.bexia-contact-resource-tabs .fi-tabs {
    max-width: 100% !important;
    overflow-x: auto !important;
    overflow-y: hidden !important;
    -webkit-overflow-scrolling: touch !important;
    scrollbar-width: thin !important;
}

.bexia-contact-resource-tabs .fi-tabs-item {
    white-space: nowrap !important;
}

.bexia-contact-main-section .fi-grid,
.bexia-contact-details-section .fi-grid,
.bexia-contact-address-section .fi-grid,
.bexia-contact-fiscal-section .fi-grid,
.bexia-contact-csf-section .fi-grid,
.bexia-contact-payment-section .fi-grid,
.bexia-contact-sales-section .fi-grid,
.bexia-contact-purchases-section .fi-grid,
.bexia-contact-main-section .fi-fo-component-ctn,
.bexia-contact-details-section .fi-fo-component-ctn,
.bexia-contact-address-section .fi-fo-component-ctn,
.bexia-contact-fiscal-section .fi-fo-component-ctn,
.bexia-contact-csf-section .fi-fo-component-ctn,
.bexia-contact-payment-section .fi-fo-component-ctn,
.bexia-contact-sales-section .fi-fo-component-ctn,
.bexia-contact-purchases-section .fi-fo-component-ctn {
    max-width: 100% !important;
    min-width: 0 !important;
}

.bexia-contact-uppercase-input {
    text-transform: uppercase !important;
}

.bexia-contact-csf-file-info-field,
.bexia-contact-csf-preview,
.bexia-contact-csf-actions {
    max-width: 100% !important;
    min-width: 0 !important;
    overflow-wrap: anywhere !important;
}

.bexia-contact-csf-empty,
.bexia-contact-csf-note,
.bexia-contact-csf-date {
    color: #6b7280 !important;
    font-size: 0.875rem !important;
    line-height: 1.35 !important;
    overflow-wrap: anywhere !important;
}

.dark .bexia-contact-csf-empty,
.dark .bexia-contact-csf-note,
.dark .bexia-contact-csf-date {
    color: #9ca3af !important;
}

.bexia-contact-csf-preview {
    display: grid !important;
    gap: 0.25rem !important;
}

.bexia-contact-csf-actions {
    display: grid !important;
    gap: 0.5rem !important;
}

.bexia-contact-csf-filename {
    color: #111827 !important;
    font-weight: 700 !important;
    overflow-wrap: anywhere !important;
}

.dark .bexia-contact-csf-filename {
    color: #f9fafb !important;
}

.bexia-contact-csf-buttons {
    display: flex !important;
    align-items: center !important;
    flex-wrap: wrap !important;
    gap: 0.5rem !important;
    margin-top: 0.5rem !important;
    max-width: 100% !important;
}

.bexia-contact-csf-button {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 2.25rem !important;
    border-radius: 0.65rem !important;
    padding: 0.5rem 0.75rem !important;
    font-size: 0.875rem !important;
    font-weight: 700 !important;
    line-height: 1.1 !important;
    text-decoration: none !important;
    transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease !important;
}

.bexia-contact-csf-button--view {
    border: 1px solid #d1d5db !important;
    background: #ffffff !important;
    color: #374151 !important;
}

.bexia-contact-csf-button--view:hover {
    background: #f9fafb !important;
}

.dark .bexia-contact-csf-button--view {
    border-color: #475569 !important;
    background: rgba(15, 23, 42, 0.72) !important;
    color: #e5e7eb !important;
}

.dark .bexia-contact-csf-button--view:hover {
    background: rgba(30, 41, 59, 0.9) !important;
}

.bexia-contact-csf-button--download {
    border: 1px solid var(--primary-600, #2563eb) !important;
    background: var(--primary-600, #2563eb) !important;
    color: #ffffff !important;
}

.bexia-contact-csf-button--download:hover {
    background: var(--primary-500, #3b82f6) !important;
}

@media (max-width: 768px) {
    .bexia-contact-main-section,
    .bexia-contact-details-section,
    .bexia-contact-address-section,
    .bexia-contact-fiscal-section,
    .bexia-contact-csf-section,
    .bexia-contact-payment-section,
    .bexia-contact-sales-section,
    .bexia-contact-purchases-section {
        padding-left: 0.85rem !important;
        padding-right: 0.85rem !important;
    }

    .bexia-contact-csf-buttons {
        align-items: stretch !important;
    }

    .bexia-contact-csf-button {
        flex: 1 1 12rem !important;
        width: 100% !important;
    }
}

@media (max-width: 640px) {
    .bexia-contact-resource-tabs .fi-tabs {
        margin-left: -0.25rem !important;
        margin-right: -0.25rem !important;
        padding-left: 0.25rem !important;
        padding-right: 0.25rem !important;
    }

    .bexia-contact-csf-empty,
    .bexia-contact-csf-note,
    .bexia-contact-csf-date,
    .bexia-contact-csf-filename {
        font-size: 0.8125rem !important;
    }
}
/* BEXIA_CONTACT_RESOURCE_RESPONSIVE_V5_79_22C_END */


/* BEXIA_POS_POINT_RESOURCE_RESPONSIVE_V5_79_23C_START */
/*
 * PosPointResource responsive refinements.
 * Alcance: tabs de configuración PDV, secciones densas,
 * selects/toggles/file upload y placeholder de cajeros.
 * No cambia lógica de PDV, sesiones, pagos, inventario ni permisos.
 */
.bexia-pos-point-resource-tabs,
.bexia-pos-point-tab-general,
.bexia-pos-point-tab-interface,
.bexia-pos-point-tab-stock,
.bexia-pos-point-tab-prices,
.bexia-pos-point-tab-ticket-billing,
.bexia-pos-point-tab-payments,
.bexia-pos-point-tab-inventory,
.bexia-pos-point-tab-technical,
.bexia-pos-point-main-section,
.bexia-pos-point-partial-payment-section,
.bexia-pos-point-interface-section,
.bexia-pos-point-stock-section,
.bexia-pos-point-orders-section,
.bexia-pos-point-price-lists-section,
.bexia-pos-point-close-section,
.bexia-pos-point-receipt-privacy-section,
.bexia-pos-point-ticket-section,
.bexia-pos-point-invoice-qr-section,
.bexia-pos-point-payments-section,
.bexia-pos-point-inventory-section,
.bexia-pos-point-technical-section,
.bexia-pos-point-cashiers-help-field {
    max-width: 100% !important;
    min-width: 0 !important;
}

.bexia-pos-point-resource-tabs .fi-tabs {
    max-width: 100% !important;
    overflow-x: auto !important;
    overflow-y: hidden !important;
    -webkit-overflow-scrolling: touch !important;
    scrollbar-width: thin !important;
}

.bexia-pos-point-resource-tabs .fi-tabs-item {
    white-space: nowrap !important;
    flex-shrink: 0 !important;
}

.bexia-pos-point-main-section .fi-grid,
.bexia-pos-point-partial-payment-section .fi-grid,
.bexia-pos-point-interface-section .fi-grid,
.bexia-pos-point-stock-section .fi-grid,
.bexia-pos-point-orders-section .fi-grid,
.bexia-pos-point-price-lists-section .fi-grid,
.bexia-pos-point-close-section .fi-grid,
.bexia-pos-point-receipt-privacy-section .fi-grid,
.bexia-pos-point-ticket-section .fi-grid,
.bexia-pos-point-invoice-qr-section .fi-grid,
.bexia-pos-point-payments-section .fi-grid,
.bexia-pos-point-inventory-section .fi-grid,
.bexia-pos-point-technical-section .fi-grid,
.bexia-pos-point-main-section .fi-fo-component-ctn,
.bexia-pos-point-partial-payment-section .fi-fo-component-ctn,
.bexia-pos-point-interface-section .fi-fo-component-ctn,
.bexia-pos-point-stock-section .fi-fo-component-ctn,
.bexia-pos-point-orders-section .fi-fo-component-ctn,
.bexia-pos-point-price-lists-section .fi-fo-component-ctn,
.bexia-pos-point-close-section .fi-fo-component-ctn,
.bexia-pos-point-receipt-privacy-section .fi-fo-component-ctn,
.bexia-pos-point-ticket-section .fi-fo-component-ctn,
.bexia-pos-point-invoice-qr-section .fi-fo-component-ctn,
.bexia-pos-point-payments-section .fi-fo-component-ctn,
.bexia-pos-point-inventory-section .fi-fo-component-ctn,
.bexia-pos-point-technical-section .fi-fo-component-ctn {
    max-width: 100% !important;
    min-width: 0 !important;
}

.bexia-pos-point-resource-tabs .fi-input-wrp,
.bexia-pos-point-resource-tabs .fi-select-input,
.bexia-pos-point-resource-tabs .fi-fo-select,
.bexia-pos-point-resource-tabs .fi-fo-textarea,
.bexia-pos-point-resource-tabs textarea,
.bexia-pos-point-resource-tabs input,
.bexia-pos-point-resource-tabs .choices,
.bexia-pos-point-resource-tabs .choices__inner,
.bexia-pos-point-resource-tabs .fi-fo-file-upload {
    max-width: 100% !important;
    min-width: 0 !important;
}

.bexia-pos-point-resource-tabs .fi-fo-field-wrp-helper-text,
.bexia-pos-point-resource-tabs .fi-fo-field-wrp-hint,
.bexia-pos-point-resource-tabs .fi-section-description,
.bexia-pos-point-cashiers-help-field {
    overflow-wrap: anywhere !important;
    word-break: normal !important;
}

.bexia-pos-point-cashiers-help-field {
    color: #6b7280 !important;
    line-height: 1.4 !important;
}

.dark .bexia-pos-point-cashiers-help-field {
    color: #9ca3af !important;
}

@media (max-width: 768px) {
    .bexia-pos-point-main-section,
    .bexia-pos-point-partial-payment-section,
    .bexia-pos-point-interface-section,
    .bexia-pos-point-stock-section,
    .bexia-pos-point-orders-section,
    .bexia-pos-point-price-lists-section,
    .bexia-pos-point-close-section,
    .bexia-pos-point-receipt-privacy-section,
    .bexia-pos-point-ticket-section,
    .bexia-pos-point-invoice-qr-section,
    .bexia-pos-point-payments-section,
    .bexia-pos-point-inventory-section,
    .bexia-pos-point-technical-section {
        padding-left: 0.85rem !important;
        padding-right: 0.85rem !important;
    }

    .bexia-pos-point-resource-tabs .fi-tabs {
        margin-left: -0.25rem !important;
        margin-right: -0.25rem !important;
        padding-left: 0.25rem !important;
        padding-right: 0.25rem !important;
    }

    .bexia-pos-point-resource-tabs .fi-tabs-item {
        min-height: 2.35rem !important;
    }
}

@media (max-width: 640px) {
    .bexia-pos-point-resource-tabs .fi-section-description,
    .bexia-pos-point-resource-tabs .fi-fo-field-wrp-helper-text,
    .bexia-pos-point-cashiers-help-field {
        font-size: 0.8125rem !important;
    }

    .bexia-pos-point-resource-tabs textarea {
        min-height: 7rem !important;
    }
}
/* BEXIA_POS_POINT_RESOURCE_RESPONSIVE_V5_79_23C_END */


/* BEXIA_REPAIR_ORDER_RESOURCE_RESPONSIVE_V5_79_24C_START */
/*
 * RepairOrderResource responsive refinements.
 * Alcance: secciones densas de reparacion, header de estado,
 * repeater de refacciones/materiales, folio destacado y cargas de archivos.
 * No cambia flujo, permisos, calculos, firmas, PDF, eventos ni estados.
 */
.bexia-repair-order-economic-section,
.bexia-repair-order-delivery-section,
.bexia-repair-order-time-section,
.bexia-repair-order-general-section,
.bexia-repair-order-product-section,
.bexia-repair-order-diagnosis-section,
.bexia-repair-order-costs-section,
.bexia-repair-order-status-header-field,
.bexia-repair-order-parts-repeater,
.bexia-repair-order-resolution-locked-notice,
.bexia-repair-order-attachments-upload,
.bexia-repair-order-folio-input {
    max-width: 100% !important;
    min-width: 0 !important;
}

.bexia-repair-order-economic-section .fi-grid,
.bexia-repair-order-delivery-section .fi-grid,
.bexia-repair-order-time-section .fi-grid,
.bexia-repair-order-general-section .fi-grid,
.bexia-repair-order-product-section .fi-grid,
.bexia-repair-order-diagnosis-section .fi-grid,
.bexia-repair-order-costs-section .fi-grid,
.bexia-repair-order-economic-section .fi-fo-component-ctn,
.bexia-repair-order-delivery-section .fi-fo-component-ctn,
.bexia-repair-order-time-section .fi-fo-component-ctn,
.bexia-repair-order-general-section .fi-fo-component-ctn,
.bexia-repair-order-product-section .fi-fo-component-ctn,
.bexia-repair-order-diagnosis-section .fi-fo-component-ctn,
.bexia-repair-order-costs-section .fi-fo-component-ctn {
    max-width: 100% !important;
    min-width: 0 !important;
}

.bexia-repair-order-status-header {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 0.75rem !important;
    margin-bottom: 0.875rem !important;
    max-width: 100% !important;
    min-width: 0 !important;
}

.bexia-repair-order-status-card {
    border-radius: 0.875rem !important;
    padding: 0.75rem 0.875rem !important;
    max-width: 100% !important;
    min-width: 0 !important;
    overflow-wrap: anywhere !important;
}

.bexia-repair-order-status-label {
    font-size: 0.75rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.04em !important;
}

.bexia-repair-order-status-value {
    font-size: 1.125rem !important;
    font-weight: 800 !important;
    margin-top: 0.125rem !important;
    line-height: 1.2 !important;
    overflow-wrap: anywhere !important;
}

.bexia-repair-order-status-card--folio,
.bexia-repair-order-folio-input .fi-input-wrp {
    background: #fef3c7 !important;
    border: 1px solid #f59e0b !important;
    color: #78350f !important;
    border-radius: 0.75rem !important;
}

.bexia-repair-order-status-card--quote-draft {
    background: #fef3c7 !important;
    border: 1px solid #f59e0b !important;
    color: #78350f !important;
}

.bexia-repair-order-status-card--pending-approval {
    background: #ffedd5 !important;
    border: 1px solid #fb923c !important;
    color: #7c2d12 !important;
}

.bexia-repair-order-status-card--quote-approved {
    background: #dbeafe !important;
    border: 1px solid #93c5fd !important;
    color: #1e3a8a !important;
}

.bexia-repair-order-status-card--in-repair {
    background: #e0e7ff !important;
    border: 1px solid #818cf8 !important;
    color: #312e81 !important;
}

.bexia-repair-order-status-card--repaired {
    background: #dcfce7 !important;
    border: 1px solid #22c55e !important;
    color: #14532d !important;
}

.bexia-repair-order-status-card--supervisor-review {
    background: #fae8ff !important;
    border: 1px solid #d946ef !important;
    color: #701a75 !important;
}

.bexia-repair-order-status-card--ready-for-delivery {
    background: #ccfbf1 !important;
    border: 1px solid #14b8a6 !important;
    color: #134e4a !important;
}

.bexia-repair-order-status-card--delivered {
    background: #f3f4f6 !important;
    border: 1px solid #9ca3af !important;
    color: #111827 !important;
}

.bexia-repair-order-status-card--cancelled {
    background: #fee2e2 !important;
    border: 1px solid #ef4444 !important;
    color: #7f1d1d !important;
}

.bexia-repair-order-status-card--default {
    background: #f8fafc !important;
    border: 1px solid #cbd5e1 !important;
    color: #0f172a !important;
}

.dark .bexia-repair-order-status-card--folio,
.dark .bexia-repair-order-folio-input .fi-input-wrp,
.dark .bexia-repair-order-status-card--quote-draft {
    background: rgba(120, 53, 15, 0.35) !important;
    border-color: #f59e0b !important;
    color: #fde68a !important;
}

.dark .bexia-repair-order-status-card--pending-approval {
    background: rgba(124, 45, 18, 0.35) !important;
    border-color: #fb923c !important;
    color: #fed7aa !important;
}

.dark .bexia-repair-order-status-card--quote-approved {
    background: rgba(30, 58, 138, 0.35) !important;
    border-color: #93c5fd !important;
    color: #bfdbfe !important;
}

.dark .bexia-repair-order-status-card--in-repair {
    background: rgba(49, 46, 129, 0.35) !important;
    border-color: #818cf8 !important;
    color: #c7d2fe !important;
}

.dark .bexia-repair-order-status-card--repaired {
    background: rgba(20, 83, 45, 0.35) !important;
    border-color: #22c55e !important;
    color: #bbf7d0 !important;
}

.dark .bexia-repair-order-status-card--supervisor-review {
    background: rgba(112, 26, 117, 0.35) !important;
    border-color: #d946ef !important;
    color: #f5d0fe !important;
}

.dark .bexia-repair-order-status-card--ready-for-delivery {
    background: rgba(19, 78, 74, 0.35) !important;
    border-color: #14b8a6 !important;
    color: #99f6e4 !important;
}

.dark .bexia-repair-order-status-card--delivered,
.dark .bexia-repair-order-status-card--default {
    background: rgba(31, 41, 55, 0.85) !important;
    border-color: #6b7280 !important;
    color: #f9fafb !important;
}

.dark .bexia-repair-order-status-card--cancelled {
    background: rgba(127, 29, 29, 0.35) !important;
    border-color: #ef4444 !important;
    color: #fecaca !important;
}

.bexia-repair-order-parts-repeater .fi-fo-repeater-item,
.bexia-repair-order-parts-repeater .fi-fo-repeater-item-content,
.bexia-repair-order-parts-repeater .fi-grid,
.bexia-repair-order-parts-repeater .fi-fo-component-ctn,
.bexia-repair-order-parts-repeater .fi-input-wrp,
.bexia-repair-order-parts-repeater .fi-select-input,
.bexia-repair-order-parts-repeater .choices,
.bexia-repair-order-parts-repeater .choices__inner {
    max-width: 100% !important;
    min-width: 0 !important;
}

.bexia-repair-order-diagnosis-section textarea,
.bexia-repair-order-product-section input,
.bexia-repair-order-general-section input,
.bexia-repair-order-costs-section input,
.bexia-repair-order-economic-section input,
.bexia-repair-order-parts-repeater input {
    max-width: 100% !important;
    min-width: 0 !important;
}

.bexia-repair-order-resolution-locked-notice,
.bexia-repair-order-attachments-upload,
.bexia-repair-order-economic-section .fi-fo-field-wrp-helper-text,
.bexia-repair-order-general-section .fi-fo-field-wrp-helper-text,
.bexia-repair-order-product-section .fi-fo-field-wrp-helper-text,
.bexia-repair-order-diagnosis-section .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere !important;
    word-break: normal !important;
}

@media (max-width: 768px) {
    .bexia-repair-order-status-header {
        grid-template-columns: 1fr !important;
    }

    .bexia-repair-order-status-value {
        font-size: 1rem !important;
    }

    .bexia-repair-order-economic-section,
    .bexia-repair-order-delivery-section,
    .bexia-repair-order-time-section,
    .bexia-repair-order-general-section,
    .bexia-repair-order-product-section,
    .bexia-repair-order-diagnosis-section,
    .bexia-repair-order-costs-section {
        padding-left: 0.85rem !important;
        padding-right: 0.85rem !important;
    }

    .bexia-repair-order-parts-repeater .fi-fo-repeater-item {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
    }
}

@media (max-width: 640px) {
    .bexia-repair-order-diagnosis-section textarea {
        min-height: 7rem !important;
    }

    .bexia-repair-order-status-label,
    .bexia-repair-order-resolution-locked-notice,
    .bexia-repair-order-economic-section .fi-fo-field-wrp-helper-text,
    .bexia-repair-order-product-section .fi-fo-field-wrp-helper-text,
    .bexia-repair-order-diagnosis-section .fi-fo-field-wrp-helper-text {
        font-size: 0.8125rem !important;
    }
}
/* BEXIA_REPAIR_ORDER_RESOURCE_RESPONSIVE_V5_79_24C_END */


/* BEXIA_INVOICE_RESOURCE_RESPONSIVE_V5_79_25C_START
 * Alcance: InvoiceResource principal.
 * Objetivo: mejorar lectura responsive de factura, CFDI/PAC, autofacturacion,
 * informacion fiscal del cliente y campos largos sin tocar logica SAT/PAC.
 */
.bexia-invoice-portal-section,
.bexia-invoice-header-section,
.bexia-invoice-global-cfdi-section,
.bexia-invoice-cfdi-pac-section,
.bexia-invoice-customer-fiscal-section {
    overflow: hidden !important;
}

.bexia-invoice-portal-section .fi-section-content,
.bexia-invoice-header-section .fi-section-content,
.bexia-invoice-global-cfdi-section .fi-section-content,
.bexia-invoice-cfdi-pac-section .fi-section-content,
.bexia-invoice-customer-fiscal-section .fi-section-content {
    min-width: 0 !important;
}

.bexia-invoice-header-section .fi-fo-field-wrp,
.bexia-invoice-global-cfdi-section .fi-fo-field-wrp,
.bexia-invoice-cfdi-pac-section .fi-fo-field-wrp,
.bexia-invoice-customer-fiscal-section .fi-fo-field-wrp,
.bexia-invoice-portal-section .fi-fo-field-wrp {
    min-width: 0 !important;
}

.bexia-invoice-contact-select .fi-select-input,
.bexia-invoice-cfdi-use-select .fi-select-input,
.bexia-invoice-source-type-select .fi-select-input,
.bexia-invoice-payment-form-select .fi-select-input,
.bexia-invoice-payment-method-select .fi-select-input,
.bexia-invoice-payment-terms-select .fi-select-input,
.bexia-invoice-global-periodicity-select .fi-select-input,
.bexia-invoice-global-month-select .fi-select-input,
.bexia-invoice-status-select .fi-select-input,
.bexia-invoice-currency-input .fi-input,
.bexia-invoice-source-number-input .fi-input,
.bexia-invoice-global-year-input .fi-input,
.bexia-invoice-cancel-reason-field textarea {
    width: 100% !important;
    min-width: 0 !important;
}

.bexia-invoice-folio-display-field,
.bexia-invoice-tax-regime-display-field,
.bexia-invoice-cfdi-status-field,
.bexia-invoice-cfdi-uuid-field,
.bexia-invoice-cfdi-folio-field,
.bexia-invoice-pac-provider-field,
.bexia-invoice-pac-environment-field,
.bexia-invoice-cfdi-xml-field,
.bexia-invoice-cfdi-cancel-status-field,
.bexia-invoice-cfdi-cancel-message-field,
.bexia-invoice-cfdi-cancel-ack-field,
.bexia-invoice-pac-error-field,
.bexia-invoice-customer-warning-live-field,
.bexia-invoice-customer-warning-saved-field {
    min-width: 0 !important;
    overflow-wrap: anywhere !important;
    word-break: break-word !important;
}

.bexia-invoice-cfdi-uuid-field .fi-fo-placeholder,
.bexia-invoice-cfdi-folio-field .fi-fo-placeholder,
.bexia-invoice-cfdi-xml-field .fi-fo-placeholder,
.bexia-invoice-cfdi-cancel-message-field .fi-fo-placeholder,
.bexia-invoice-cfdi-cancel-ack-field .fi-fo-placeholder,
.bexia-invoice-pac-error-field .fi-fo-placeholder,
.bexia-invoice-customer-warning-live-field .fi-fo-placeholder,
.bexia-invoice-customer-warning-saved-field .fi-fo-placeholder {
    white-space: normal !important;
    overflow-wrap: anywhere !important;
    word-break: break-word !important;
}

.bexia-invoice-customer-fiscal-grid {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 0.625rem !important;
    font-size: 0.8125rem !important;
    line-height: 1.45 !important;
}

.bexia-invoice-fiscal-item {
    min-width: 0 !important;
    border-radius: 0.75rem !important;
    border: 1px solid rgba(148, 163, 184, 0.24) !important;
    background: rgba(248, 250, 252, 0.82) !important;
    padding: 0.625rem 0.75rem !important;
    overflow-wrap: anywhere !important;
    word-break: break-word !important;
}

.dark .bexia-invoice-fiscal-item {
    border-color: rgba(51, 65, 85, 0.9) !important;
    background: rgba(15, 23, 42, 0.74) !important;
}

.bexia-invoice-fiscal-item strong {
    display: inline-block !important;
    margin-bottom: 0.125rem !important;
    font-size: 0.72rem !important;
    letter-spacing: 0.04em !important;
    text-transform: uppercase !important;
    color: rgb(71, 85, 105) !important;
}

.dark .bexia-invoice-fiscal-item strong {
    color: rgb(203, 213, 225) !important;
}

.bexia-invoice-fiscal-item-wide {
    grid-column: span 2 / span 2 !important;
}

.bexia-invoice-fiscal-item-full {
    grid-column: 1 / -1 !important;
}

.bexia-invoice-portal-summary-card {
    min-width: 0 !important;
    overflow: hidden !important;
    overflow-wrap: anywhere !important;
    word-break: break-word !important;
}

.bexia-invoice-portal-summary-title {
    line-height: 1.3 !important;
}

.bexia-invoice-portal-summary-row {
    min-width: 0 !important;
}

.bexia-invoice-portal-summary-label,
.bexia-invoice-portal-summary-value {
    min-width: 0 !important;
    overflow-wrap: anywhere !important;
    word-break: break-word !important;
}

.bexia-invoice-portal-protected-alert {
    line-height: 1.45 !important;
    overflow-wrap: anywhere !important;
}

@media (max-width: 1024px) {
    .bexia-invoice-header-section .fi-grid,
    .bexia-invoice-global-cfdi-section .fi-grid,
    .bexia-invoice-cfdi-pac-section .fi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .bexia-invoice-customer-fiscal-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 768px) {
    .bexia-invoice-header-section .fi-grid,
    .bexia-invoice-global-cfdi-section .fi-grid,
    .bexia-invoice-cfdi-pac-section .fi-grid,
    .bexia-invoice-customer-fiscal-section .fi-grid,
    .bexia-invoice-portal-section .fi-grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-invoice-header-section .fi-fo-field-wrp,
    .bexia-invoice-global-cfdi-section .fi-fo-field-wrp,
    .bexia-invoice-cfdi-pac-section .fi-fo-field-wrp,
    .bexia-invoice-customer-fiscal-section .fi-fo-field-wrp,
    .bexia-invoice-portal-section .fi-fo-field-wrp {
        grid-column: 1 / -1 !important;
        width: 100% !important;
    }

    .bexia-invoice-customer-fiscal-grid {
        grid-template-columns: minmax(0, 1fr) !important;
        font-size: 0.78rem !important;
    }

    .bexia-invoice-fiscal-item,
    .bexia-invoice-fiscal-item-wide,
    .bexia-invoice-fiscal-item-full {
        grid-column: 1 / -1 !important;
    }

    .bexia-invoice-portal-summary-row {
        grid-template-columns: minmax(0, 1fr) !important;
    }
}

@media (max-width: 640px) {
    .bexia-invoice-header-section,
    .bexia-invoice-global-cfdi-section,
    .bexia-invoice-cfdi-pac-section,
    .bexia-invoice-customer-fiscal-section,
    .bexia-invoice-portal-section {
        border-radius: 1rem !important;
    }

    .bexia-invoice-fiscal-item {
        padding: 0.55rem 0.65rem !important;
    }

    .bexia-invoice-portal-summary-card {
        padding: 0.75rem !important;
    }
}
/* BEXIA_INVOICE_RESOURCE_RESPONSIVE_V5_79_25C_END */


/* BEXIA_STOCK_ADJUSTMENT_RESOURCE_RESPONSIVE_V5_79_26C_START
   Ajustes inventario: layout responsive + limpieza inline styles.
   Scope: StockAdjustmentResource.
*/
.bexia-stock-adjustment-header-section,
.bexia-stock-adjustment-lines-section {
    border-radius: 18px;
    overflow: hidden;
}

.bexia-stock-adjustment-header-section .fi-section-content,
.bexia-stock-adjustment-lines-section .fi-section-content {
    padding: clamp(1rem, 2vw, 1.5rem);
}

.bexia-stock-adjustment-header-section .fi-grid,
.bexia-stock-adjustment-lines-section .fi-grid {
    gap: 1rem;
}

.bexia-stock-adjustment-header-section .fi-fo-field-wrp,
.bexia-stock-adjustment-lines-section .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-stock-adjustment-reference-input .fi-input,
.bexia-stock-adjustment-warehouse-select .fi-select-input,
.bexia-stock-adjustment-location-select .fi-select-input,
.bexia-stock-adjustment-status-select .fi-select-input,
.bexia-stock-adjustment-reason-textarea textarea,
.bexia-stock-adjustment-notes-textarea textarea {
    min-height: 44px;
    border-radius: 12px;
}

.bexia-stock-adjustment-reason-textarea textarea,
.bexia-stock-adjustment-notes-textarea textarea {
    min-height: 88px;
}

.bexia-stock-adjustment-lines-capture-notice-field .fi-fo-placeholder {
    width: 100%;
}

.bexia-stock-adjustment-notice {
    padding: 12px;
    border-radius: 12px;
    line-height: 1.45;
    font-size: 0.92rem;
}

.bexia-stock-adjustment-notice-warning {
    border: 1px solid #fde68a;
    background: #fffbeb;
    color: #92400e;
}

.dark .bexia-stock-adjustment-notice-warning {
    border-color: rgba(253, 230, 138, 0.55);
    background: rgba(146, 64, 14, 0.22);
    color: #fde68a;
}

.bexia-stock-adjustment-notice-info {
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    color: #1e40af;
}

.dark .bexia-stock-adjustment-notice-info {
    border-color: rgba(191, 219, 254, 0.45);
    background: rgba(30, 64, 175, 0.22);
    color: #bfdbfe;
}

.bexia-stock-adjustment-notice-link {
    font-weight: 700;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.bexia-stock-adjustment-invalid-summary {
    font-size: 14px;
    line-height: 1.45;
}

.bexia-stock-adjustment-invalid-list {
    padding-left: 18px;
    margin-top: 10px;
}

.bexia-stock-adjustment-invalid-line-li {
    margin-bottom: 10px;
}

.bexia-stock-adjustment-invalid-more {
    margin-top: 10px;
}

.bexia-stock-adjustment-invalid-help-title,
.bexia-stock-adjustment-invalid-danger {
    margin-top: 12px;
}

.bexia-stock-adjustment-invalid-help {
    margin-top: 14px;
}

.bexia-stock-adjustment-invalid-steps {
    padding-left: 18px;
    margin-top: 6px;
}

.bexia-stock-adjustment-invalid-card {
    border: 1px solid #fecaca;
    background: #fef2f2;
    border-radius: 10px;
    padding: 12px;
    margin-top: 10px;
}

.dark .bexia-stock-adjustment-invalid-card {
    border-color: rgba(254, 202, 202, 0.45);
    background: rgba(127, 29, 29, 0.22);
}

.bexia-stock-adjustment-invalid-danger {
    color: #b91c1c;
}

.dark .bexia-stock-adjustment-invalid-danger {
    color: #fecaca;
}

@media (max-width: 1024px) {
    .bexia-stock-adjustment-header-section .fi-grid,
    .bexia-stock-adjustment-lines-section .fi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 768px) {
    .bexia-stock-adjustment-header-section .fi-grid,
    .bexia-stock-adjustment-lines-section .fi-grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-stock-adjustment-header-section .fi-fo-field-wrp,
    .bexia-stock-adjustment-lines-section .fi-fo-field-wrp {
        grid-column: 1 / -1 !important;
    }

    .bexia-stock-adjustment-notice {
        font-size: 0.88rem;
    }

    .bexia-stock-adjustment-invalid-card {
        padding: 10px;
    }
}

@media (max-width: 520px) {
    .bexia-stock-adjustment-header-section,
    .bexia-stock-adjustment-lines-section {
        border-radius: 14px;
    }

    .bexia-stock-adjustment-header-section .fi-section-content,
    .bexia-stock-adjustment-lines-section .fi-section-content {
        padding: 0.85rem;
    }

    .bexia-stock-adjustment-invalid-list,
    .bexia-stock-adjustment-invalid-steps {
        padding-left: 16px;
    }
}
/* BEXIA_STOCK_ADJUSTMENT_RESOURCE_RESPONSIVE_V5_79_26C_END */

</style>
