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


/* BEXIA_EXIT_CATALOG_RESOURCES_RESPONSIVE_V5_79_27C_START */
.bexia-exit-catalog-section .fi-section-content {
    border-radius: 16px;
}

.bexia-exit-catalog-section .bexia-exit-catalog-input {
    background-color: #ffffff;
}

.dark .bexia-exit-catalog-section .bexia-exit-catalog-input {
    background-color: rgba(15, 23, 42, 0.92);
}

.bexia-exit-catalog-field {
    min-width: 0;
}

.bexia-exit-project-col-name,
.bexia-exit-warehouse-col-name {
    min-width: 220px;
}

.bexia-exit-project-col-code,
.bexia-exit-warehouse-col-code {
    min-width: 140px;
}

@media (max-width: 1024px) {
    .bexia-exit-project-section .fi-grid,
    .bexia-exit-warehouse-section .fi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 768px) {
    .bexia-exit-project-section .fi-grid,
    .bexia-exit-warehouse-section .fi-grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-exit-project-section .fi-fo-field-wrp,
    .bexia-exit-warehouse-section .fi-fo-field-wrp {
        grid-column: 1 / -1 !important;
    }

    .bexia-exit-project-col-name,
    .bexia-exit-warehouse-col-name,
    .bexia-exit-project-col-code,
    .bexia-exit-warehouse-col-code {
        min-width: 0;
        width: auto;
    }
}

@media (max-width: 520px) {
    .bexia-exit-project-section,
    .bexia-exit-warehouse-section {
        border-radius: 14px;
    }

    .bexia-exit-project-section .fi-section-content,
    .bexia-exit-warehouse-section .fi-section-content {
        padding: 0.85rem;
    }
}
/* BEXIA_EXIT_CATALOG_RESOURCES_RESPONSIVE_V5_79_27C_END */


/* BEXIA_ROLE_RESOURCE_RESPONSIVE_V5_79_28B_START */
.bexia-role-field {
    min-width: 0;
}

.bexia-role-permission-guide-wrapper {
    border-radius: 16px;
}

.bexia-role-permission-guide {
    font-size: 13px;
    line-height: 1.65;
    color: #334155;
}

.dark .bexia-role-permission-guide {
    color: #cbd5e1;
}

.bexia-role-permission-guide div + div {
    margin-top: 2px;
}

.bexia-role-permission-list {
    min-width: 0;
}

.bexia-role-permission-list .fi-fo-checkbox-list {
    width: 100%;
}

.bexia-role-col-name {
    min-width: 190px;
}

.bexia-role-col-company {
    min-width: 190px;
}

.bexia-role-col-permissions {
    min-width: 110px;
}

.bexia-role-col-updated {
    min-width: 140px;
}

@media (max-width: 1024px) {
    .bexia-role-permission-list .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 768px) {
    .bexia-role-field,
    .bexia-role-permission-guide-wrapper,
    .bexia-role-permission-list {
        grid-column: 1 / -1 !important;
    }

    .bexia-role-permission-list .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-role-permission-guide {
        font-size: 0.88rem;
        line-height: 1.55;
    }

    .bexia-role-col-name,
    .bexia-role-col-company,
    .bexia-role-col-permissions,
    .bexia-role-col-updated {
        min-width: 0;
        width: auto;
    }
}

@media (max-width: 520px) {
    .bexia-role-permission-guide-wrapper {
        border-radius: 14px;
    }

    .bexia-role-permission-guide {
        font-size: 0.84rem;
    }
}
/* BEXIA_ROLE_RESOURCE_RESPONSIVE_V5_79_28B_END */


/* BEXIA_COMPANY_RESOURCE_RESPONSIVE_V5_79_29C_START */
.bexia-company-section {
    min-width: 0;
}

.bexia-company-section .fi-section-content {
    border-radius: 16px;
}

.bexia-company-section .fi-grid,
.bexia-company-section .grid {
    min-width: 0;
}

.bexia-company-general-section,
.bexia-company-attendance-section,
.bexia-company-pac-section,
.bexia-company-csd-section,
.bexia-company-branding-section,
.bexia-company-costing-section {
    overflow: hidden;
}

.bexia-company-col-logo {
    min-width: 88px;
}

.bexia-company-col-id {
    min-width: 70px;
}

.bexia-company-col-name {
    min-width: 220px;
}

.bexia-company-col-slug {
    min-width: 160px;
}

.bexia-company-col-tax-id {
    min-width: 135px;
}

.bexia-company-col-pac,
.bexia-company-col-pac-test,
.bexia-company-col-active {
    min-width: 115px;
}

.bexia-company-col-pac-status {
    min-width: 160px;
}

@media (max-width: 1024px) {
    .bexia-company-section .fi-grid,
    .bexia-company-section .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .bexia-company-branding-section .fi-grid,
    .bexia-company-branding-section .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 768px) {
    .bexia-company-section .fi-grid,
    .bexia-company-section .grid,
    .bexia-company-branding-section .fi-grid,
    .bexia-company-branding-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-company-section .fi-fo-field-wrp,
    .bexia-company-section .fi-fo-component-ctn,
    .bexia-company-section [data-field-wrapper] {
        grid-column: 1 / -1 !important;
        min-width: 0;
    }

    .bexia-company-col-logo,
    .bexia-company-col-id,
    .bexia-company-col-name,
    .bexia-company-col-slug,
    .bexia-company-col-tax-id,
    .bexia-company-col-pac,
    .bexia-company-col-pac-test,
    .bexia-company-col-pac-status,
    .bexia-company-col-active {
        min-width: 0;
        width: auto;
    }
}

@media (max-width: 520px) {
    .bexia-company-section {
        border-radius: 14px;
    }

    .bexia-company-section .fi-section-content {
        padding: 0.85rem;
    }
}
/* BEXIA_COMPANY_RESOURCE_RESPONSIVE_V5_79_29C_END */


/* BEXIA_EMPLOYEE_INCIDENT_RESOURCE_RESPONSIVE_V5_79_30C_START */
.bexia-employee-incident-section {
    min-width: 0;
}

.bexia-employee-incident-section .fi-section-content {
    border-radius: 16px;
}

.bexia-employee-incident-form-grid,
.bexia-employee-incident-section .fi-grid,
.bexia-employee-incident-section .grid {
    min-width: 0;
}

.bexia-employee-incident-field,
.bexia-employee-incident-vacation-wrapper {
    min-width: 0;
}

.bexia-employee-incident-vacation-summary {
    min-width: 0;
    overflow-wrap: anywhere;
    line-height: 1.55;
}

.bexia-employee-incident-col-employee {
    min-width: 210px;
}

.bexia-employee-incident-col-type {
    min-width: 160px;
}

.bexia-employee-incident-col-title {
    min-width: 230px;
}

.bexia-employee-incident-col-status {
    min-width: 130px;
}

.bexia-employee-incident-col-start-date,
.bexia-employee-incident-col-end-date,
.bexia-employee-incident-col-created {
    min-width: 130px;
}

.bexia-employee-incident-col-quantity {
    min-width: 170px;
}

.bexia-employee-incident-col-quantity-unit,
.bexia-employee-incident-col-approval,
.bexia-employee-incident-col-payroll,
.bexia-employee-incident-col-attachment {
    min-width: 120px;
}

@media (max-width: 1024px) {
    .bexia-employee-incident-form-grid,
    .bexia-employee-incident-section .fi-grid,
    .bexia-employee-incident-section .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 768px) {
    .bexia-employee-incident-form-grid,
    .bexia-employee-incident-section .fi-grid,
    .bexia-employee-incident-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-employee-incident-section .fi-fo-field-wrp,
    .bexia-employee-incident-section .fi-fo-component-ctn,
    .bexia-employee-incident-section [data-field-wrapper],
    .bexia-employee-incident-field,
    .bexia-employee-incident-vacation-wrapper {
        grid-column: 1 / -1 !important;
        min-width: 0;
    }

    .bexia-employee-incident-vacation-summary {
        font-size: 0.86rem;
        line-height: 1.5;
    }

    .bexia-employee-incident-col-employee,
    .bexia-employee-incident-col-type,
    .bexia-employee-incident-col-title,
    .bexia-employee-incident-col-status,
    .bexia-employee-incident-col-start-date,
    .bexia-employee-incident-col-end-date,
    .bexia-employee-incident-col-created,
    .bexia-employee-incident-col-quantity,
    .bexia-employee-incident-col-quantity-unit,
    .bexia-employee-incident-col-approval,
    .bexia-employee-incident-col-payroll,
    .bexia-employee-incident-col-attachment {
        min-width: 0;
        width: auto;
    }
}

@media (max-width: 520px) {
    .bexia-employee-incident-section {
        border-radius: 14px;
    }

    .bexia-employee-incident-section .fi-section-content {
        padding: 0.85rem;
    }

    .bexia-employee-incident-vacation-summary {
        padding: 0.75rem !important;
    }
}
/* BEXIA_EMPLOYEE_INCIDENT_RESOURCE_RESPONSIVE_V5_79_30C_END */


/* BEXIA_EMPLOYEE_ATTENDANCE_RESOURCE_RESPONSIVE_V5_79_31C_START */
.bexia-employee-attendance-section {
    min-width: 0;
}

.bexia-employee-attendance-section .fi-section-content {
    border-radius: 16px;
}

.bexia-employee-attendance-grid,
.bexia-employee-attendance-section .fi-grid,
.bexia-employee-attendance-section .grid {
    min-width: 0;
}

.bexia-employee-attendance-field,
.bexia-employee-attendance-placeholder {
    min-width: 0;
}

.bexia-employee-attendance-calculation-hint {
    min-width: 0;
    overflow-wrap: anywhere;
    line-height: 1.55;
}

.bexia-employee-attendance-clock-in-geo-wrapper,
.bexia-employee-attendance-clock-out-geo-wrapper,
.bexia-employee-attendance-reviewer-rule-wrapper {
    overflow-wrap: anywhere;
}

.bexia-employee-attendance-col-employee {
    min-width: 220px;
}

.bexia-employee-attendance-col-source,
.bexia-employee-attendance-col-mobile-review,
.bexia-employee-attendance-col-status {
    min-width: 130px;
}

.bexia-employee-attendance-col-clock-in-geo,
.bexia-employee-attendance-col-clock-out-geo,
.bexia-employee-attendance-col-clock-in-guard,
.bexia-employee-attendance-col-clock-out-guard {
    min-width: 145px;
}

.bexia-employee-attendance-col-clock-in-distance,
.bexia-employee-attendance-col-clock-out-distance,
.bexia-employee-attendance-col-worked-hours,
.bexia-employee-attendance-col-late,
.bexia-employee-attendance-col-early-leave,
.bexia-employee-attendance-col-overtime {
    min-width: 120px;
}

.bexia-employee-attendance-col-clock-in-device,
.bexia-employee-attendance-col-clock-out-device {
    min-width: 150px;
}

.bexia-employee-attendance-col-date,
.bexia-employee-attendance-col-schedule,
.bexia-employee-attendance-col-expected-start,
.bexia-employee-attendance-col-clock-in,
.bexia-employee-attendance-col-expected-end,
.bexia-employee-attendance-col-clock-out {
    min-width: 135px;
}

@media (max-width: 1024px) {
    .bexia-employee-attendance-record-grid,
    .bexia-employee-attendance-record-section .fi-grid,
    .bexia-employee-attendance-record-section .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .bexia-employee-attendance-mobile-review-grid,
    .bexia-employee-attendance-mobile-review-section .fi-grid,
    .bexia-employee-attendance-mobile-review-section .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 768px) {
    .bexia-employee-attendance-grid,
    .bexia-employee-attendance-section .fi-grid,
    .bexia-employee-attendance-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-employee-attendance-section .fi-fo-field-wrp,
    .bexia-employee-attendance-section .fi-fo-component-ctn,
    .bexia-employee-attendance-section [data-field-wrapper],
    .bexia-employee-attendance-field,
    .bexia-employee-attendance-placeholder {
        grid-column: 1 / -1 !important;
        min-width: 0;
    }

    .bexia-employee-attendance-calculation-hint {
        font-size: 0.86rem;
        line-height: 1.5;
    }

    .bexia-employee-attendance-col-source,
    .bexia-employee-attendance-col-mobile-review,
    .bexia-employee-attendance-col-clock-in-geo,
    .bexia-employee-attendance-col-clock-out-geo,
    .bexia-employee-attendance-col-clock-in-distance,
    .bexia-employee-attendance-col-clock-out-distance,
    .bexia-employee-attendance-col-clock-in-guard,
    .bexia-employee-attendance-col-clock-out-guard,
    .bexia-employee-attendance-col-clock-in-device,
    .bexia-employee-attendance-col-clock-out-device,
    .bexia-employee-attendance-col-date,
    .bexia-employee-attendance-col-employee,
    .bexia-employee-attendance-col-schedule,
    .bexia-employee-attendance-col-status,
    .bexia-employee-attendance-col-expected-start,
    .bexia-employee-attendance-col-clock-in,
    .bexia-employee-attendance-col-expected-end,
    .bexia-employee-attendance-col-clock-out,
    .bexia-employee-attendance-col-worked-hours,
    .bexia-employee-attendance-col-late,
    .bexia-employee-attendance-col-early-leave,
    .bexia-employee-attendance-col-overtime {
        min-width: 0;
        width: auto;
    }
}

@media (max-width: 520px) {
    .bexia-employee-attendance-section {
        border-radius: 14px;
    }

    .bexia-employee-attendance-section .fi-section-content {
        padding: 0.85rem;
    }

    .bexia-employee-attendance-calculation-hint {
        padding: 0.75rem !important;
    }
}
/* BEXIA_EMPLOYEE_ATTENDANCE_RESOURCE_RESPONSIVE_V5_79_31C_END */


/* BEXIA_TREASURY_CASH_TRANSFER_REQUEST_RESOURCE_RESPONSIVE_V5_79_32C_START */
.bexia-treasury-cash-transfer-section,
.bexia-treasury-cash-transfer-infolist-section {
    min-width: 0;
}

.bexia-treasury-cash-transfer-section .fi-section-content,
.bexia-treasury-cash-transfer-infolist-section .fi-section-content {
    border-radius: 16px;
}

.bexia-treasury-cash-transfer-section .fi-grid,
.bexia-treasury-cash-transfer-section .grid,
.bexia-treasury-cash-transfer-infolist-section .fi-in-grid,
.bexia-treasury-cash-transfer-infolist-section .grid {
    min-width: 0;
}

.bexia-treasury-cash-transfer-field {
    min-width: 0;
}

.bexia-treasury-cash-transfer-log-section,
.bexia-treasury-cash-transfer-log-item,
.bexia-treasury-cash-transfer-log-note,
.bexia-treasury-cash-transfer-log-empty {
    min-width: 0;
    overflow-wrap: anywhere;
}

.bexia-treasury-cash-transfer-col-number {
    min-width: 145px;
}

.bexia-treasury-cash-transfer-col-status {
    min-width: 135px;
}

.bexia-treasury-cash-transfer-col-type {
    min-width: 190px;
}

.bexia-treasury-cash-transfer-col-amount {
    min-width: 120px;
}

.bexia-treasury-cash-transfer-col-source,
.bexia-treasury-cash-transfer-col-destination {
    min-width: 210px;
}

.bexia-treasury-cash-transfer-col-created {
    min-width: 145px;
}

@media (max-width: 1024px) {
    .bexia-treasury-cash-transfer-flow-section .fi-grid,
    .bexia-treasury-cash-transfer-flow-section .grid,
    .bexia-treasury-cash-transfer-request-section .fi-in-grid,
    .bexia-treasury-cash-transfer-request-section .grid,
    .bexia-treasury-cash-transfer-approval-section .fi-in-grid,
    .bexia-treasury-cash-transfer-approval-section .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 768px) {
    .bexia-treasury-cash-transfer-section .fi-grid,
    .bexia-treasury-cash-transfer-section .grid,
    .bexia-treasury-cash-transfer-infolist-section .fi-in-grid,
    .bexia-treasury-cash-transfer-infolist-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-treasury-cash-transfer-section .fi-fo-field-wrp,
    .bexia-treasury-cash-transfer-section .fi-fo-component-ctn,
    .bexia-treasury-cash-transfer-section [data-field-wrapper],
    .bexia-treasury-cash-transfer-field {
        grid-column: 1 / -1 !important;
        min-width: 0;
    }

    .bexia-treasury-cash-transfer-log-item {
        line-height: 1.45;
    }

    .bexia-treasury-cash-transfer-col-number,
    .bexia-treasury-cash-transfer-col-status,
    .bexia-treasury-cash-transfer-col-type,
    .bexia-treasury-cash-transfer-col-amount,
    .bexia-treasury-cash-transfer-col-source,
    .bexia-treasury-cash-transfer-col-destination,
    .bexia-treasury-cash-transfer-col-created {
        min-width: 0;
        width: auto;
    }
}

@media (max-width: 520px) {
    .bexia-treasury-cash-transfer-section,
    .bexia-treasury-cash-transfer-infolist-section {
        border-radius: 14px;
    }

    .bexia-treasury-cash-transfer-section .fi-section-content,
    .bexia-treasury-cash-transfer-infolist-section .fi-section-content {
        padding: 0.85rem;
    }
}
/* BEXIA_TREASURY_CASH_TRANSFER_REQUEST_RESOURCE_RESPONSIVE_V5_79_32C_END */


/* BEXIA_EMPLOYEE_CONTRACT_RESOURCE_RESPONSIVE_V5_79_33C_START */
.bexia-employee-contract-section {
    min-width: 0;
}

.bexia-employee-contract-section .fi-section-content {
    border-radius: 16px;
}

.bexia-employee-contract-section .fi-grid,
.bexia-employee-contract-section .grid {
    min-width: 0;
}

.bexia-employee-contract-field {
    min-width: 0;
}

.bexia-employee-contract-file-upload-field,
.bexia-employee-contract-notes-field {
    width: 100%;
}

.bexia-employee-contract-col-employee {
    min-width: 220px;
}

.bexia-employee-contract-col-number,
.bexia-employee-contract-col-type,
.bexia-employee-contract-col-status {
    min-width: 145px;
}

.bexia-employee-contract-col-current,
.bexia-employee-contract-col-start-date,
.bexia-employee-contract-col-end-date,
.bexia-employee-contract-col-salary,
.bexia-employee-contract-col-file {
    min-width: 120px;
}

.bexia-employee-contract-col-job-position,
.bexia-employee-contract-col-department {
    min-width: 180px;
}

@media (max-width: 1024px) {
    .bexia-employee-contract-labor-section .fi-grid,
    .bexia-employee-contract-labor-section .grid,
    .bexia-employee-contract-work-section .fi-grid,
    .bexia-employee-contract-work-section .grid,
    .bexia-employee-contract-sat-section .fi-grid,
    .bexia-employee-contract-sat-section .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 768px) {
    .bexia-employee-contract-section .fi-grid,
    .bexia-employee-contract-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-employee-contract-section .fi-fo-field-wrp,
    .bexia-employee-contract-section .fi-fo-component-ctn,
    .bexia-employee-contract-section [data-field-wrapper],
    .bexia-employee-contract-field {
        grid-column: 1 / -1 !important;
        min-width: 0;
    }

    .bexia-employee-contract-section .fi-input-wrp,
    .bexia-employee-contract-section .fi-select-input,
    .bexia-employee-contract-section textarea {
        min-width: 0;
    }

    .bexia-employee-contract-col-employee,
    .bexia-employee-contract-col-number,
    .bexia-employee-contract-col-type,
    .bexia-employee-contract-col-status,
    .bexia-employee-contract-col-current,
    .bexia-employee-contract-col-start-date,
    .bexia-employee-contract-col-end-date,
    .bexia-employee-contract-col-job-position,
    .bexia-employee-contract-col-department,
    .bexia-employee-contract-col-salary,
    .bexia-employee-contract-col-file {
        min-width: 0;
        width: auto;
    }
}

@media (max-width: 520px) {
    .bexia-employee-contract-section {
        border-radius: 14px;
    }

    .bexia-employee-contract-section .fi-section-content {
        padding: 0.85rem;
    }

    .bexia-employee-contract-section .fi-section-header {
        padding-inline: 0.85rem;
    }
}
/* BEXIA_EMPLOYEE_CONTRACT_RESOURCE_RESPONSIVE_V5_79_33C_END */


/* BEXIA_PAYROLL_CFDI_RECEIPT_RESOURCE_RESPONSIVE_V5_79_34C_START */
.bexia-payroll-cfdi-receipt-infolist-section {
    min-width: 0;
}

.bexia-payroll-cfdi-receipt-infolist-section .fi-section-content {
    border-radius: 16px;
}

.bexia-payroll-cfdi-receipt-infolist-section .fi-in-grid,
.bexia-payroll-cfdi-receipt-infolist-section .grid {
    min-width: 0;
}

.bexia-payroll-cfdi-receipt-entry,
.bexia-payroll-cfdi-receipt-modal-field {
    min-width: 0;
}

.bexia-payroll-cfdi-receipt-uuid-entry,
.bexia-payroll-cfdi-receipt-xml-path-entry,
.bexia-payroll-cfdi-receipt-xml-content-entry,
.bexia-payroll-cfdi-receipt-issuer-snapshot-entry,
.bexia-payroll-cfdi-receipt-employee-snapshot-entry,
.bexia-payroll-cfdi-receipt-contract-snapshot-entry,
.bexia-payroll-cfdi-receipt-validation-errors-entry {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-payroll-cfdi-receipt-xml-content-entry pre,
.bexia-payroll-cfdi-receipt-snapshots-section pre {
    max-width: 100%;
    overflow-x: auto;
    white-space: pre-wrap;
}

.bexia-payroll-cfdi-receipt-col-status,
.bexia-payroll-cfdi-receipt-col-run-id,
.bexia-payroll-cfdi-receipt-col-series,
.bexia-payroll-cfdi-receipt-col-pdf,
.bexia-payroll-cfdi-receipt-col-xml {
    min-width: 120px;
}

.bexia-payroll-cfdi-receipt-col-run,
.bexia-payroll-cfdi-receipt-col-employee {
    min-width: 220px;
}

.bexia-payroll-cfdi-receipt-col-folio {
    min-width: 150px;
}

.bexia-payroll-cfdi-receipt-col-uuid {
    min-width: 260px;
    max-width: 360px;
    overflow-wrap: anywhere;
}

.bexia-payroll-cfdi-receipt-col-validated,
.bexia-payroll-cfdi-receipt-col-stamped,
.bexia-payroll-cfdi-receipt-col-created {
    min-width: 150px;
}

@media (max-width: 1024px) {
    .bexia-payroll-cfdi-receipt-main-section .fi-in-grid,
    .bexia-payroll-cfdi-receipt-main-section .grid,
    .bexia-payroll-cfdi-receipt-totals-section .fi-in-grid,
    .bexia-payroll-cfdi-receipt-totals-section .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 768px) {
    .bexia-payroll-cfdi-receipt-infolist-section .fi-in-grid,
    .bexia-payroll-cfdi-receipt-infolist-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-payroll-cfdi-receipt-entry,
    .bexia-payroll-cfdi-receipt-modal-field,
    .bexia-payroll-cfdi-receipt-infolist-section [data-entry-wrapper],
    .bexia-payroll-cfdi-receipt-infolist-section .fi-in-entry-wrp {
        grid-column: 1 / -1 !important;
        min-width: 0;
    }

    .bexia-payroll-cfdi-receipt-col-status,
    .bexia-payroll-cfdi-receipt-col-run-id,
    .bexia-payroll-cfdi-receipt-col-run,
    .bexia-payroll-cfdi-receipt-col-employee,
    .bexia-payroll-cfdi-receipt-col-folio,
    .bexia-payroll-cfdi-receipt-col-series,
    .bexia-payroll-cfdi-receipt-col-uuid,
    .bexia-payroll-cfdi-receipt-col-pdf,
    .bexia-payroll-cfdi-receipt-col-xml,
    .bexia-payroll-cfdi-receipt-col-validated,
    .bexia-payroll-cfdi-receipt-col-stamped,
    .bexia-payroll-cfdi-receipt-col-created {
        min-width: 0;
        max-width: none;
        width: auto;
    }
}

@media (max-width: 520px) {
    .bexia-payroll-cfdi-receipt-infolist-section {
        border-radius: 14px;
    }

    .bexia-payroll-cfdi-receipt-infolist-section .fi-section-content {
        padding: 0.85rem;
    }

    .bexia-payroll-cfdi-receipt-infolist-section .fi-section-header {
        padding-inline: 0.85rem;
    }
}
/* BEXIA_PAYROLL_CFDI_RECEIPT_RESOURCE_RESPONSIVE_V5_79_34C_END */


/* BEXIA_STOCK_MOVEMENT_RESOURCE_RESPONSIVE_V5_79_35C_START */
.bexia-stock-movement-section {
    min-width: 0;
}

.bexia-stock-movement-section .fi-section-content {
    border-radius: 16px;
}

.bexia-stock-movement-section .fi-grid,
.bexia-stock-movement-section .grid {
    min-width: 0;
}

.bexia-stock-movement-field {
    min-width: 0;
}

.bexia-stock-movement-notes-field,
.bexia-stock-movement-line-notes-field,
.bexia-stock-movement-lines-repeater {
    width: 100%;
}

.bexia-stock-movement-lines-repeater .fi-fo-repeater-item,
.bexia-stock-movement-lines-repeater .fi-fo-repeater-item-content,
.bexia-stock-movement-lines-repeater .fi-grid,
.bexia-stock-movement-lines-repeater .grid {
    min-width: 0;
}

.bexia-stock-movement-line-product-field,
.bexia-stock-movement-line-variant-field,
.bexia-stock-movement-line-source-stock-field {
    min-width: 0;
    overflow-wrap: anywhere;
}

.bexia-stock-movement-col-reference,
.bexia-stock-movement-col-origin-document {
    min-width: 180px;
    max-width: 260px;
    overflow-wrap: anywhere;
}

.bexia-stock-movement-col-movement-at,
.bexia-stock-movement-col-status,
.bexia-stock-movement-col-lines-count {
    min-width: 120px;
}

.bexia-stock-movement-col-operation-type {
    min-width: 170px;
}

.bexia-stock-movement-col-warehouse,
.bexia-stock-movement-col-source-location,
.bexia-stock-movement-col-destination-location {
    min-width: 190px;
}

@media (max-width: 1024px) {
    .bexia-stock-movement-transfer-section .fi-grid,
    .bexia-stock-movement-transfer-section .grid,
    .bexia-stock-movement-lines-repeater .fi-grid,
    .bexia-stock-movement-lines-repeater .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 768px) {
    .bexia-stock-movement-section .fi-grid,
    .bexia-stock-movement-section .grid,
    .bexia-stock-movement-lines-repeater .fi-grid,
    .bexia-stock-movement-lines-repeater .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-stock-movement-section .fi-fo-field-wrp,
    .bexia-stock-movement-section .fi-fo-component-ctn,
    .bexia-stock-movement-section [data-field-wrapper],
    .bexia-stock-movement-field,
    .bexia-stock-movement-lines-repeater {
        grid-column: 1 / -1 !important;
        min-width: 0;
    }

    .bexia-stock-movement-section .fi-input-wrp,
    .bexia-stock-movement-section .fi-select-input,
    .bexia-stock-movement-section textarea,
    .bexia-stock-movement-lines-repeater .fi-input-wrp,
    .bexia-stock-movement-lines-repeater .fi-select-input,
    .bexia-stock-movement-lines-repeater textarea {
        min-width: 0;
    }

    .bexia-stock-movement-col-reference,
    .bexia-stock-movement-col-movement-at,
    .bexia-stock-movement-col-operation-type,
    .bexia-stock-movement-col-warehouse,
    .bexia-stock-movement-col-source-location,
    .bexia-stock-movement-col-destination-location,
    .bexia-stock-movement-col-lines-count,
    .bexia-stock-movement-col-origin-document,
    .bexia-stock-movement-col-status {
        min-width: 0;
        max-width: none;
        width: auto;
    }
}

@media (max-width: 520px) {
    .bexia-stock-movement-section {
        border-radius: 14px;
    }

    .bexia-stock-movement-section .fi-section-content {
        padding: 0.85rem;
    }

    .bexia-stock-movement-section .fi-section-header {
        padding-inline: 0.85rem;
    }

    .bexia-stock-movement-lines-repeater .fi-fo-repeater-item {
        border-radius: 14px;
    }
}
/* BEXIA_STOCK_MOVEMENT_RESOURCE_RESPONSIVE_V5_79_35C_END */

/* BEXIA_POS_TICKET_RESOURCE_RESPONSIVE_V5_79_36C_START */
/*
 * PosTicketResource responsive refinements.
 * Alcance visual: columnas largas, importes, badges y fechas del listado de tickets PDV.
 * No toca logica POS, pagos, facturacion, inventario, devoluciones ni permisos.
 */
.bexia-pos-ticket-col-number,
.bexia-pos-ticket-col-status,
.bexia-pos-ticket-col-inventory-status,
.bexia-pos-ticket-col-fiscal-state,
.bexia-pos-ticket-col-billing-status,
.bexia-pos-ticket-col-customer,
.bexia-pos-ticket-col-session,
.bexia-pos-ticket-col-point,
.bexia-pos-ticket-col-total,
.bexia-pos-ticket-col-ordered-at,
.bexia-pos-ticket-col-paid-at,
.bexia-pos-ticket-col-payment-count {
    vertical-align: top;
}

.bexia-pos-ticket-col-primary {
    font-weight: 650;
}

.bexia-pos-ticket-col-number {
    min-width: 8.5rem;
    white-space: nowrap;
}

.bexia-pos-ticket-col-customer {
    min-width: 12rem;
    max-width: 18rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-pos-ticket-col-status,
.bexia-pos-ticket-col-inventory-status,
.bexia-pos-ticket-col-fiscal-state,
.bexia-pos-ticket-col-billing-status {
    min-width: 8.5rem;
    white-space: normal;
}

.bexia-pos-ticket-col-session,
.bexia-pos-ticket-col-point {
    min-width: 9rem;
    max-width: 13rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-pos-ticket-col-money {
    min-width: 7rem;
    white-space: nowrap;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.bexia-pos-ticket-col-date {
    min-width: 8rem;
    white-space: nowrap;
    font-size: 0.78rem;
    line-height: 1.25rem;
}

.bexia-pos-ticket-col-compact {
    min-width: 5rem;
    white-space: nowrap;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-pos-ticket-col-number {
        min-width: 7.5rem;
    }

    .bexia-pos-ticket-col-customer {
        min-width: 10rem;
        max-width: 14rem;
    }

    .bexia-pos-ticket-col-session,
    .bexia-pos-ticket-col-point {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-pos-ticket-col-status,
    .bexia-pos-ticket-col-inventory-status,
    .bexia-pos-ticket-col-fiscal-state,
    .bexia-pos-ticket-col-billing-status {
        min-width: 7.75rem;
    }
}

@media (max-width: 768px) {
    .bexia-pos-ticket-col-customer,
    .bexia-pos-ticket-col-session,
    .bexia-pos-ticket-col-point {
        max-width: 12rem;
        font-size: 0.78rem;
    }

    .bexia-pos-ticket-col-date {
        min-width: 7rem;
        white-space: normal;
        font-size: 0.75rem;
    }

    .bexia-pos-ticket-col-money {
        min-width: 6.5rem;
    }

    .bexia-pos-ticket-col-status,
    .bexia-pos-ticket-col-inventory-status,
    .bexia-pos-ticket-col-fiscal-state,
    .bexia-pos-ticket-col-billing-status {
        min-width: 7rem;
        font-size: 0.76rem;
    }
}

@media (max-width: 640px) {
    .bexia-pos-ticket-col-number,
    .bexia-pos-ticket-col-total {
        font-size: 0.8rem;
    }

    .bexia-pos-ticket-col-customer {
        min-width: 9rem;
        max-width: 11rem;
    }

    .bexia-pos-ticket-col-session,
    .bexia-pos-ticket-col-point,
    .bexia-pos-ticket-col-payment-count {
        font-size: 0.74rem;
    }
}
/* BEXIA_POS_TICKET_RESOURCE_RESPONSIVE_V5_79_36C_END */

/* BEXIA_STOCK_SERIAL_NUMBER_RESOURCE_RESPONSIVE_V5_79_37C_START */
/*
 * StockSerialNumberResource responsive refinements.
 * Alcance visual: formulario, columnas largas, serie, producto, lote, ubicacion, estado y fechas.
 * No toca logica de series, lotes, ubicaciones, PDF/QR, inventario, movimientos, permisos ni tenant scope.
 */
.bexia-stock-serial-number-section {
    border-radius: 1rem;
}

.bexia-stock-serial-number-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-stock-serial-number-section .fi-grid,
.bexia-stock-serial-number-section .grid {
    gap: 0.95rem;
}

.bexia-stock-serial-number-field .fi-input-wrp,
.bexia-stock-serial-number-field .fi-select-input,
.bexia-stock-serial-number-field .fi-fo-select,
.bexia-stock-serial-number-field input,
.bexia-stock-serial-number-field select,
.bexia-stock-serial-number-field .choices,
.bexia-stock-serial-number-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-stock-serial-number-field-serial input {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    letter-spacing: 0.01em;
}

.bexia-stock-serial-number-col-serial,
.bexia-stock-serial-number-col-product,
.bexia-stock-serial-number-col-lot,
.bexia-stock-serial-number-col-warehouse,
.bexia-stock-serial-number-col-location,
.bexia-stock-serial-number-col-status,
.bexia-stock-serial-number-col-source,
.bexia-stock-serial-number-col-created {
    vertical-align: top;
}

.bexia-stock-serial-number-col-primary {
    min-width: 10rem;
    white-space: normal;
    overflow-wrap: anywhere;
    font-weight: 650;
    font-variant-numeric: tabular-nums;
}

.bexia-stock-serial-number-col-product {
    min-width: 14rem;
    max-width: 22rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-stock-serial-number-col-lot {
    min-width: 8rem;
    max-width: 12rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-stock-serial-number-col-warehouse,
.bexia-stock-serial-number-col-location {
    min-width: 10rem;
    max-width: 15rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-stock-serial-number-col-badge {
    min-width: 7.5rem;
    white-space: normal;
}

.bexia-stock-serial-number-col-source {
    min-width: 8rem;
    max-width: 12rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-stock-serial-number-col-date {
    min-width: 8rem;
    white-space: nowrap;
    font-size: 0.78rem;
    line-height: 1.25rem;
}

@media (max-width: 1024px) {
    .bexia-stock-serial-number-section .fi-grid,
    .bexia-stock-serial-number-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-stock-serial-number-col-product {
        min-width: 12rem;
        max-width: 18rem;
    }

    .bexia-stock-serial-number-col-warehouse,
    .bexia-stock-serial-number-col-location {
        min-width: 8.5rem;
        max-width: 12rem;
    }
}

@media (max-width: 768px) {
    .bexia-stock-serial-number-section {
        border-radius: 0.85rem;
    }

    .bexia-stock-serial-number-section .fi-section-header,
    .bexia-stock-serial-number-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-stock-serial-number-col-serial {
        min-width: 8.5rem;
        font-size: 0.78rem;
    }

    .bexia-stock-serial-number-col-product,
    .bexia-stock-serial-number-col-warehouse,
    .bexia-stock-serial-number-col-location {
        max-width: 11.5rem;
        font-size: 0.78rem;
    }

    .bexia-stock-serial-number-col-lot,
    .bexia-stock-serial-number-col-source,
    .bexia-stock-serial-number-col-status {
        font-size: 0.76rem;
    }

    .bexia-stock-serial-number-col-date {
        min-width: 7rem;
        white-space: normal;
        font-size: 0.75rem;
    }
}

@media (max-width: 640px) {
    .bexia-stock-serial-number-field .fi-fo-field-wrp-label,
    .bexia-stock-serial-number-field label {
        font-size: 0.8rem;
    }

    .bexia-stock-serial-number-field-serial input {
        font-size: 0.8rem;
    }

    .bexia-stock-serial-number-col-product,
    .bexia-stock-serial-number-col-warehouse,
    .bexia-stock-serial-number-col-location {
        max-width: 10rem;
    }
}
/* BEXIA_STOCK_SERIAL_NUMBER_RESOURCE_RESPONSIVE_V5_79_37C_END */

/* BEXIA_PAYROLL_RUN_RESOURCE_RESPONSIVE_V5_79_38C_START */
/*
 * PayrollRunResource responsive refinements.
 * Visual scope only.
 */
.bexia-payroll-run-section {
    border-radius: 1rem;
}

.bexia-payroll-run-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-payroll-run-section .fi-grid,
.bexia-payroll-run-section .grid {
    gap: 0.95rem;
}

.bexia-payroll-run-field .fi-input-wrp,
.bexia-payroll-run-field .fi-select-input,
.bexia-payroll-run-field .fi-fo-select,
.bexia-payroll-run-field input,
.bexia-payroll-run-field select,
.bexia-payroll-run-field textarea,
.bexia-payroll-run-field .choices,
.bexia-payroll-run-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-payroll-run-field-notes textarea,
.bexia-payroll-run-field-reason textarea {
    min-height: 5.5rem;
}

.bexia-payroll-run-section-money .fi-input-wrp,
.bexia-payroll-run-section-money input {
    font-variant-numeric: tabular-nums;
}

.bexia-payroll-run-col-cfdi-state,
.bexia-payroll-run-col-cfdi-ready,
.bexia-payroll-run-col-cfdi-errors,
.bexia-payroll-run-col-name,
.bexia-payroll-run-col-cycle,
.bexia-payroll-run-col-date-from,
.bexia-payroll-run-col-date-to,
.bexia-payroll-run-col-state,
.bexia-payroll-run-col-employees,
.bexia-payroll-run-col-bruto-money,
.bexia-payroll-run-col-deductions-money,
.bexia-payroll-run-col-neto-money,
.bexia-payroll-run-col-accounting-state {
    vertical-align: top;
}

.bexia-payroll-run-col-primary {
    min-width: 12rem;
    max-width: 20rem;
    white-space: normal;
    overflow-wrap: anywhere;
    font-weight: 650;
}

.bexia-payroll-run-col-cfdi-state,
.bexia-payroll-run-col-accounting-state,
.bexia-payroll-run-col-state {
    min-width: 8.5rem;
    white-space: normal;
}

.bexia-payroll-run-col-cfdi-ready,
.bexia-payroll-run-col-cfdi-errors,
.bexia-payroll-run-col-employees {
    min-width: 6.5rem;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.bexia-payroll-run-col-cycle {
    min-width: 8rem;
    white-space: normal;
}

.bexia-payroll-run-col-date-from,
.bexia-payroll-run-col-date-to {
    min-width: 7.5rem;
    white-space: nowrap;
    font-size: 0.78rem;
    line-height: 1.25rem;
}

.bexia-payroll-run-col-money {
    min-width: 8rem;
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

@media (max-width: 1024px) {
    .bexia-payroll-run-section .fi-grid,
    .bexia-payroll-run-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-payroll-run-col-primary {
        min-width: 11rem;
        max-width: 16rem;
    }

    .bexia-payroll-run-col-cfdi-state,
    .bexia-payroll-run-col-accounting-state,
    .bexia-payroll-run-col-state {
        min-width: 7.5rem;
    }
}

@media (max-width: 768px) {
    .bexia-payroll-run-section {
        border-radius: 0.85rem;
    }

    .bexia-payroll-run-section .fi-section-header,
    .bexia-payroll-run-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-payroll-run-col-primary {
        min-width: 9.5rem;
        max-width: 12rem;
        font-size: 0.78rem;
    }

    .bexia-payroll-run-col-cfdi-state,
    .bexia-payroll-run-col-accounting-state,
    .bexia-payroll-run-col-state,
    .bexia-payroll-run-col-cycle {
        min-width: 7rem;
        font-size: 0.76rem;
    }

    .bexia-payroll-run-col-date-from,
    .bexia-payroll-run-col-date-to {
        min-width: 6.5rem;
        white-space: normal;
        font-size: 0.75rem;
    }

    .bexia-payroll-run-col-money,
    .bexia-payroll-run-col-cfdi-ready,
    .bexia-payroll-run-col-cfdi-errors,
    .bexia-payroll-run-col-employees {
        min-width: 6.5rem;
        font-size: 0.75rem;
    }
}

@media (max-width: 640px) {
    .bexia-payroll-run-field .fi-fo-field-wrp-label,
    .bexia-payroll-run-field label {
        font-size: 0.8rem;
    }

    .bexia-payroll-run-field input,
    .bexia-payroll-run-field select,
    .bexia-payroll-run-field textarea {
        font-size: 0.82rem;
    }

    .bexia-payroll-run-col-primary {
        max-width: 10.5rem;
    }
}
/* BEXIA_PAYROLL_RUN_RESOURCE_RESPONSIVE_V5_79_38C_END */

/* BEXIA_STOCK_REPLENISHMENT_RULE_RESOURCE_RESPONSIVE_V5_79_39C_START */
/*
 * StockReplenishmentRuleResource responsive refinements.
 * Visual scope only. No cambia logica de inventario, productos, almacenes, compras, permisos ni tenant scope.
 */
.bexia-stock-replenishment-rule-section {
    border-radius: 1rem;
}

.bexia-stock-replenishment-rule-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-stock-replenishment-rule-section .fi-grid,
.bexia-stock-replenishment-rule-section .grid {
    gap: 0.95rem;
}

.bexia-stock-replenishment-rule-field .fi-input-wrp,
.bexia-stock-replenishment-rule-field .fi-select-input,
.bexia-stock-replenishment-rule-field .fi-fo-select,
.bexia-stock-replenishment-rule-field input,
.bexia-stock-replenishment-rule-field select,
.bexia-stock-replenishment-rule-field textarea,
.bexia-stock-replenishment-rule-field .choices,
.bexia-stock-replenishment-rule-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-stock-replenishment-rule-field-notes textarea {
    min-height: 5.5rem;
}

.bexia-stock-replenishment-rule-field-onhand,
.bexia-stock-replenishment-rule-field-buy-info {
    overflow-wrap: anywhere;
}

.bexia-stock-replenishment-rule-col-warehouse,
.bexia-stock-replenishment-rule-col-location,
.bexia-stock-replenishment-rule-col-item,
.bexia-stock-replenishment-rule-col-variant,
.bexia-stock-replenishment-rule-col-floor,
.bexia-stock-replenishment-rule-col-ceiling,
.bexia-stock-replenishment-rule-col-onhand,
.bexia-stock-replenishment-rule-col-supplier,
.bexia-stock-replenishment-rule-col-lead-days,
.bexia-stock-replenishment-rule-col-priority,
.bexia-stock-replenishment-rule-col-enabled,
.bexia-stock-replenishment-rule-col-updated {
    vertical-align: top;
}

.bexia-stock-replenishment-rule-col-primary {
    min-width: 13rem;
    max-width: 22rem;
    white-space: normal;
    overflow-wrap: anywhere;
    font-weight: 650;
}

.bexia-stock-replenishment-rule-col-warehouse,
.bexia-stock-replenishment-rule-col-location,
.bexia-stock-replenishment-rule-col-supplier {
    min-width: 10rem;
    max-width: 16rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-stock-replenishment-rule-col-variant {
    min-width: 9rem;
    max-width: 14rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-stock-replenishment-rule-col-number,
.bexia-stock-replenishment-rule-col-lead-days {
    min-width: 7rem;
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.bexia-stock-replenishment-rule-col-priority,
.bexia-stock-replenishment-rule-col-enabled,
.bexia-stock-replenishment-rule-col-updated {
    min-width: 7rem;
    white-space: nowrap;
}

@media (max-width: 1024px) {
    .bexia-stock-replenishment-rule-section .fi-grid,
    .bexia-stock-replenishment-rule-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-stock-replenishment-rule-col-primary {
        min-width: 11rem;
        max-width: 17rem;
    }

    .bexia-stock-replenishment-rule-col-warehouse,
    .bexia-stock-replenishment-rule-col-location,
    .bexia-stock-replenishment-rule-col-supplier {
        min-width: 8.5rem;
        max-width: 13rem;
    }
}

@media (max-width: 768px) {
    .bexia-stock-replenishment-rule-section {
        border-radius: 0.85rem;
    }

    .bexia-stock-replenishment-rule-section .fi-section-header,
    .bexia-stock-replenishment-rule-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-stock-replenishment-rule-col-primary {
        min-width: 9.5rem;
        max-width: 12rem;
        font-size: 0.78rem;
    }

    .bexia-stock-replenishment-rule-col-warehouse,
    .bexia-stock-replenishment-rule-col-location,
    .bexia-stock-replenishment-rule-col-variant,
    .bexia-stock-replenishment-rule-col-supplier {
        min-width: 7.5rem;
        max-width: 10.5rem;
        font-size: 0.76rem;
    }

    .bexia-stock-replenishment-rule-col-number,
    .bexia-stock-replenishment-rule-col-lead-days,
    .bexia-stock-replenishment-rule-col-priority,
    .bexia-stock-replenishment-rule-col-enabled,
    .bexia-stock-replenishment-rule-col-updated {
        min-width: 6.5rem;
        font-size: 0.75rem;
    }
}

@media (max-width: 640px) {
    .bexia-stock-replenishment-rule-field .fi-fo-field-wrp-label,
    .bexia-stock-replenishment-rule-field label {
        font-size: 0.8rem;
    }

    .bexia-stock-replenishment-rule-field input,
    .bexia-stock-replenishment-rule-field select,
    .bexia-stock-replenishment-rule-field textarea {
        font-size: 0.82rem;
    }

    .bexia-stock-replenishment-rule-col-primary {
        max-width: 10.5rem;
    }
}
/* BEXIA_STOCK_REPLENISHMENT_RULE_RESOURCE_RESPONSIVE_V5_79_39C_END */

/* BEXIA_TREASURY_ACCOUNT_RESOURCE_RESPONSIVE_V5_79_40C_START */
/*
 * TreasuryAccountResource responsive refinements.
 * Visual scope only. No cambia logica de cuentas, saldos, movimientos, bancos, cajas, conciliacion, permisos ni tenant scope.
 */
.bexia-treasury-account-section {
    border-radius: 1rem;
}

.bexia-treasury-account-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-treasury-account-section .fi-grid,
.bexia-treasury-account-section .grid {
    gap: 0.95rem;
}

.bexia-treasury-account-field .fi-input-wrp,
.bexia-treasury-account-field .fi-select-input,
.bexia-treasury-account-field .fi-fo-select,
.bexia-treasury-account-field input,
.bexia-treasury-account-field select,
.bexia-treasury-account-field textarea,
.bexia-treasury-account-field .choices,
.bexia-treasury-account-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-treasury-account-field-name input,
.bexia-treasury-account-field-number input,
.bexia-treasury-account-field-clabe input {
    overflow-wrap: anywhere;
}

.bexia-treasury-account-field-notes textarea {
    min-height: 5.5rem;
}

.bexia-treasury-account-col-name,
.bexia-treasury-account-col-entity,
.bexia-treasury-account-col-scope,
.bexia-treasury-account-col-branch,
.bexia-treasury-account-col-warehouse,
.bexia-treasury-account-col-pos-point,
.bexia-treasury-account-col-current-amount,
.bexia-treasury-account-col-approval,
.bexia-treasury-account-col-concentrator,
.bexia-treasury-account-col-enabled {
    vertical-align: top;
}

.bexia-treasury-account-col-primary {
    min-width: 12rem;
    max-width: 22rem;
    white-space: normal;
    overflow-wrap: anywhere;
    font-weight: 650;
}

.bexia-treasury-account-col-entity,
.bexia-treasury-account-col-scope,
.bexia-treasury-account-col-branch,
.bexia-treasury-account-col-warehouse,
.bexia-treasury-account-col-pos-point {
    min-width: 9rem;
    max-width: 15rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-treasury-account-col-money {
    min-width: 8rem;
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.bexia-treasury-account-col-approval,
.bexia-treasury-account-col-concentrator,
.bexia-treasury-account-col-enabled {
    min-width: 7rem;
    white-space: nowrap;
}

@media (max-width: 1024px) {
    .bexia-treasury-account-section .fi-grid,
    .bexia-treasury-account-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-treasury-account-col-primary {
        min-width: 11rem;
        max-width: 17rem;
    }

    .bexia-treasury-account-col-entity,
    .bexia-treasury-account-col-scope,
    .bexia-treasury-account-col-branch,
    .bexia-treasury-account-col-warehouse,
    .bexia-treasury-account-col-pos-point {
        min-width: 8rem;
        max-width: 13rem;
    }
}

@media (max-width: 768px) {
    .bexia-treasury-account-section {
        border-radius: 0.85rem;
    }

    .bexia-treasury-account-section .fi-section-header,
    .bexia-treasury-account-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-treasury-account-col-primary {
        min-width: 9.5rem;
        max-width: 12rem;
        font-size: 0.78rem;
    }

    .bexia-treasury-account-col-entity,
    .bexia-treasury-account-col-scope,
    .bexia-treasury-account-col-branch,
    .bexia-treasury-account-col-warehouse,
    .bexia-treasury-account-col-pos-point {
        min-width: 7.5rem;
        max-width: 10.5rem;
        font-size: 0.76rem;
    }

    .bexia-treasury-account-col-money,
    .bexia-treasury-account-col-approval,
    .bexia-treasury-account-col-concentrator,
    .bexia-treasury-account-col-enabled {
        min-width: 6.5rem;
        font-size: 0.75rem;
    }
}

@media (max-width: 640px) {
    .bexia-treasury-account-field .fi-fo-field-wrp-label,
    .bexia-treasury-account-field label {
        font-size: 0.8rem;
    }

    .bexia-treasury-account-field input,
    .bexia-treasury-account-field select,
    .bexia-treasury-account-field textarea {
        font-size: 0.82rem;
    }

    .bexia-treasury-account-col-primary {
        max-width: 10.5rem;
    }
}
/* BEXIA_TREASURY_ACCOUNT_RESOURCE_RESPONSIVE_V5_79_40C_END */

/* BEXIA_ACCOUNT_RECEIVABLE_PAYMENT_RESOURCE_RESPONSIVE_V5_79_41C_START */
/*
 * AccountReceivablePaymentResource responsive refinements.
 * Visual scope only. No cambia logica de pagos, aplicacion a CxC, saldos, facturas, clientes, tesoreria, contabilidad, permisos ni tenant scope.
 */
.bexia-cxc-pay-section {
    border-radius: 1rem;
}

.bexia-cxc-pay-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-cxc-pay-section .fi-in-grid,
.bexia-cxc-pay-section .grid {
    gap: 0.9rem;
}

.bexia-cxc-pay-entry {
    min-width: 0;
    max-width: 100%;
}

.bexia-cxc-pay-entry .fi-in-entry-wrp,
.bexia-cxc-pay-entry .fi-in-text,
.bexia-cxc-pay-entry .fi-badge,
.bexia-cxc-pay-entry .fi-ta-text {
    min-width: 0;
    max-width: 100%;
    overflow-wrap: anywhere;
}

.bexia-cxc-pay-entry-money .fi-in-text,
.bexia-cxc-pay-col-money {
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
    text-align: right;
}

.bexia-cxc-pay-entry-notes .fi-in-text {
    white-space: pre-wrap;
    line-height: 1.45;
}

.bexia-cxc-pay-col-id,
.bexia-cxc-pay-col-doc,
.bexia-cxc-pay-col-client,
.bexia-cxc-pay-col-date,
.bexia-cxc-pay-col-money,
.bexia-cxc-pay-col-state,
.bexia-cxc-pay-col-ref {
    vertical-align: top;
}

.bexia-cxc-pay-col-primary {
    min-width: 8rem;
    max-width: 13rem;
    white-space: normal;
    overflow-wrap: anywhere;
    font-weight: 650;
}

.bexia-cxc-pay-col-client {
    min-width: 12rem;
    max-width: 20rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-cxc-pay-col-ref {
    min-width: 8rem;
    max-width: 13rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-cxc-pay-col-date,
.bexia-cxc-pay-col-state {
    min-width: 7rem;
    white-space: nowrap;
}

.bexia-cxc-pay-col-numeric {
    min-width: 6rem;
}

@media (max-width: 1024px) {
    .bexia-cxc-pay-section .fi-in-grid,
    .bexia-cxc-pay-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-cxc-pay-col-client {
        min-width: 10rem;
        max-width: 16rem;
    }

    .bexia-cxc-pay-col-primary,
    .bexia-cxc-pay-col-ref {
        max-width: 11rem;
    }
}

@media (max-width: 768px) {
    .bexia-cxc-pay-section {
        border-radius: 0.85rem;
    }

    .bexia-cxc-pay-section .fi-section-header,
    .bexia-cxc-pay-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-cxc-pay-entry .fi-in-entry-wrp-label,
    .bexia-cxc-pay-entry .fi-in-entry-wrp-label span {
        font-size: 0.78rem;
    }

    .bexia-cxc-pay-entry .fi-in-text,
    .bexia-cxc-pay-entry .fi-badge {
        font-size: 0.82rem;
    }

    .bexia-cxc-pay-col-id,
    .bexia-cxc-pay-col-doc,
    .bexia-cxc-pay-col-client,
    .bexia-cxc-pay-col-date,
    .bexia-cxc-pay-col-money,
    .bexia-cxc-pay-col-state,
    .bexia-cxc-pay-col-ref {
        font-size: 0.76rem;
    }

    .bexia-cxc-pay-col-client {
        min-width: 8.5rem;
        max-width: 12rem;
    }

    .bexia-cxc-pay-col-primary,
    .bexia-cxc-pay-col-ref {
        min-width: 7.5rem;
        max-width: 10rem;
    }
}

@media (max-width: 640px) {
    .bexia-cxc-pay-entry-notes {
        grid-column: 1 / -1;
    }

    .bexia-cxc-pay-col-client {
        max-width: 10.5rem;
    }

    .bexia-cxc-pay-col-money {
        min-width: 6.75rem;
    }
}
/* BEXIA_ACCOUNT_RECEIVABLE_PAYMENT_RESOURCE_RESPONSIVE_V5_79_41C_END */

/* BEXIA_SALES_PRICE_LIST_RESOURCE_RESPONSIVE_V5_79_42C_START */
/*
 * SalesPriceListResource responsive refinements.
 * Visual scope only. No cambia logica de precios, margenes, descuentos, productos, moneda, compania, permisos ni tenant scope.
 */
.bexia-spl-section {
    border-radius: 1rem;
}

.bexia-spl-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-spl-section .fi-grid,
.bexia-spl-section .grid {
    gap: 0.9rem;
}

.bexia-spl-field,
.bexia-spl-repeater {
    min-width: 0;
    max-width: 100%;
}

.bexia-spl-field .fi-input-wrp,
.bexia-spl-field .fi-select-input,
.bexia-spl-field .fi-fo-select,
.bexia-spl-field input,
.bexia-spl-field select,
.bexia-spl-field textarea,
.bexia-spl-field .choices,
.bexia-spl-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-spl-field-code input,
.bexia-spl-field-curr input,
.bexia-spl-field-adjust input,
.bexia-spl-field-min-qty input,
.bexia-spl-field-amount input {
    font-variant-numeric: tabular-nums;
}

.bexia-spl-field-notes textarea {
    min-height: 5rem;
    line-height: 1.45;
}

.bexia-spl-repeater .fi-fo-repeater,
.bexia-spl-repeater .fi-fo-repeater-item,
.bexia-spl-repeater .fi-fo-component-ctn {
    min-width: 0;
    max-width: 100%;
}

.bexia-spl-repeater .fi-fo-repeater-item {
    overflow-x: hidden;
}

.bexia-spl-col-code,
.bexia-spl-col-name,
.bexia-spl-col-mode,
.bexia-spl-col-base-ref,
.bexia-spl-col-basis,
.bexia-spl-col-adjust,
.bexia-spl-col-main-flag,
.bexia-spl-col-enabled,
.bexia-spl-col-lines,
.bexia-spl-col-updated {
    vertical-align: top;
}

.bexia-spl-col-primary {
    min-width: 12rem;
    max-width: 22rem;
    white-space: normal;
    overflow-wrap: anywhere;
    font-weight: 650;
}

.bexia-spl-col-key {
    min-width: 7rem;
    max-width: 10rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-spl-col-base-ref {
    min-width: 10rem;
    max-width: 16rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-spl-col-mode,
.bexia-spl-col-basis,
.bexia-spl-col-main-flag,
.bexia-spl-col-enabled {
    min-width: 6.5rem;
    white-space: nowrap;
}

.bexia-spl-col-numeric {
    min-width: 6rem;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
    text-align: right;
}

.bexia-spl-col-updated {
    min-width: 8rem;
    white-space: nowrap;
}

@media (max-width: 1024px) {
    .bexia-spl-section .fi-grid,
    .bexia-spl-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-spl-repeater .fi-fo-repeater-item .fi-grid,
    .bexia-spl-repeater .fi-fo-repeater-item .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-spl-col-primary {
        max-width: 18rem;
    }

    .bexia-spl-col-base-ref {
        max-width: 13rem;
    }
}

@media (max-width: 768px) {
    .bexia-spl-section {
        border-radius: 0.85rem;
    }

    .bexia-spl-section .fi-section-header,
    .bexia-spl-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-spl-field .fi-fo-field-wrp-label,
    .bexia-spl-field label {
        font-size: 0.78rem;
    }

    .bexia-spl-field input,
    .bexia-spl-field select,
    .bexia-spl-field textarea,
    .bexia-spl-field .fi-select-input {
        font-size: 0.82rem;
    }

    .bexia-spl-repeater .fi-fo-repeater-item {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }

    .bexia-spl-col-code,
    .bexia-spl-col-name,
    .bexia-spl-col-mode,
    .bexia-spl-col-base-ref,
    .bexia-spl-col-basis,
    .bexia-spl-col-adjust,
    .bexia-spl-col-main-flag,
    .bexia-spl-col-enabled,
    .bexia-spl-col-lines,
    .bexia-spl-col-updated {
        font-size: 0.76rem;
    }

    .bexia-spl-col-primary {
        min-width: 9.5rem;
        max-width: 13rem;
    }

    .bexia-spl-col-key,
    .bexia-spl-col-base-ref {
        min-width: 7.5rem;
        max-width: 10.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-spl-field-notes {
        grid-column: 1 / -1;
    }

    .bexia-spl-col-primary {
        max-width: 11.5rem;
    }

    .bexia-spl-col-mode,
    .bexia-spl-col-basis {
        min-width: 5.75rem;
    }

    .bexia-spl-col-numeric {
        min-width: 5.75rem;
    }
}
/* BEXIA_SALES_PRICE_LIST_RESOURCE_RESPONSIVE_V5_79_42C_END */

/* BEXIA_USR_RESOURCE_RESPONSIVE_V5_79_43C_START */
/*
 * Responsive refinements for the security directory.
 * Visual scope only.
 */
.bexia-usr-section {
    border-radius: 1rem;
}

.bexia-usr-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-usr-section .fi-grid,
.bexia-usr-section .grid {
    gap: 0.9rem;
}

.bexia-usr-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-usr-field .fi-input-wrp,
.bexia-usr-field .fi-select-input,
.bexia-usr-field .fi-fo-select,
.bexia-usr-field input,
.bexia-usr-field select,
.bexia-usr-field textarea,
.bexia-usr-field .choices,
.bexia-usr-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-usr-field-avatar .fi-fo-file-upload,
.bexia-usr-field-avatar .filepond--root {
    max-width: 100%;
}

.bexia-usr-field-mail input,
.bexia-usr-field-key input {
    overflow-wrap: anywhere;
}

.bexia-usr-field-orgs .choices__list--multiple,
.bexia-usr-field-rset .choices__list--multiple,
.bexia-usr-field-pset .choices__list--multiple {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.bexia-usr-field-orgs .choices__item,
.bexia-usr-field-rset .choices__item,
.bexia-usr-field-pset .choices__item {
    max-width: 100%;
    overflow-wrap: anywhere;
    white-space: normal;
}

.bexia-usr-col-avatar,
.bexia-usr-col-id,
.bexia-usr-col-name,
.bexia-usr-col-mail,
.bexia-usr-col-gacc,
.bexia-usr-col-gadm,
.bexia-usr-col-eacc,
.bexia-usr-col-created {
    vertical-align: top;
}

.bexia-usr-col-avatar {
    min-width: 4rem;
    white-space: nowrap;
}

.bexia-usr-col-numeric {
    min-width: 4rem;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.bexia-usr-col-primary {
    min-width: 12rem;
    max-width: 20rem;
    white-space: normal;
    overflow-wrap: anywhere;
    font-weight: 650;
}

.bexia-usr-col-mail {
    min-width: 12rem;
    max-width: 20rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-usr-col-gacc,
.bexia-usr-col-gadm,
.bexia-usr-col-eacc {
    min-width: 11rem;
    max-width: 22rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-usr-col-created {
    min-width: 8rem;
    white-space: nowrap;
}

@media (max-width: 1024px) {
    .bexia-usr-section .fi-grid,
    .bexia-usr-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-usr-col-primary,
    .bexia-usr-col-mail {
        max-width: 16rem;
    }

    .bexia-usr-col-gacc,
    .bexia-usr-col-gadm,
    .bexia-usr-col-eacc {
        max-width: 18rem;
    }
}

@media (max-width: 768px) {
    .bexia-usr-section {
        border-radius: 0.85rem;
    }

    .bexia-usr-section .fi-section-header,
    .bexia-usr-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-usr-field .fi-fo-field-wrp-label,
    .bexia-usr-field label {
        font-size: 0.78rem;
    }

    .bexia-usr-field input,
    .bexia-usr-field select,
    .bexia-usr-field textarea,
    .bexia-usr-field .fi-select-input {
        font-size: 0.82rem;
    }

    .bexia-usr-col-avatar,
    .bexia-usr-col-id,
    .bexia-usr-col-name,
    .bexia-usr-col-mail,
    .bexia-usr-col-gacc,
    .bexia-usr-col-gadm,
    .bexia-usr-col-eacc,
    .bexia-usr-col-created {
        font-size: 0.76rem;
    }

    .bexia-usr-col-primary,
    .bexia-usr-col-mail {
        min-width: 9.5rem;
        max-width: 13rem;
    }

    .bexia-usr-col-gacc,
    .bexia-usr-col-gadm,
    .bexia-usr-col-eacc {
        min-width: 10rem;
        max-width: 14rem;
    }
}

@media (max-width: 640px) {
    .bexia-usr-field-avatar {
        grid-column: 1 / -1;
    }

    .bexia-usr-col-primary,
    .bexia-usr-col-mail {
        max-width: 11.5rem;
    }

    .bexia-usr-col-gacc,
    .bexia-usr-col-gadm,
    .bexia-usr-col-eacc {
        max-width: 12rem;
    }
}
/* BEXIA_USR_RESOURCE_RESPONSIVE_V5_79_43C_END */

/* BEXIA_APPM_RESOURCE_RESPONSIVE_V5_79_44C_START */
/*
 * Responsive refinements for AP remittance directory.
 * Visual scope only.
 */
.bexia-appm-section {
    border-radius: 1rem;
}

.bexia-appm-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-appm-section .fi-in-grid,
.bexia-appm-section .grid {
    gap: 0.9rem;
}

.bexia-appm-entry {
    min-width: 0;
    max-width: 100%;
}

.bexia-appm-entry .fi-in-entry,
.bexia-appm-entry .fi-in-text,
.bexia-appm-entry .fi-badge {
    max-width: 100%;
}

.bexia-appm-entry-num,
.bexia-appm-entry-sup,
.bexia-appm-entry-ref,
.bexia-appm-entry-tm,
.bexia-appm-entry-ae {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-appm-entry-amt .fi-in-entry,
.bexia-appm-entry-amt .fi-in-text {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.bexia-appm-col-num,
.bexia-appm-col-sup,
.bexia-appm-col-dt,
.bexia-appm-col-amt,
.bexia-appm-col-st,
.bexia-appm-col-ref {
    vertical-align: top;
}

.bexia-appm-col-primary {
    min-width: 9rem;
    max-width: 14rem;
    white-space: normal;
    overflow-wrap: anywhere;
    font-weight: 650;
}

.bexia-appm-col-sup {
    min-width: 12rem;
    max-width: 20rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-appm-col-dt {
    min-width: 7rem;
    white-space: nowrap;
}

.bexia-appm-col-money {
    min-width: 8rem;
    white-space: nowrap;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.bexia-appm-col-st {
    min-width: 8rem;
    white-space: nowrap;
}

.bexia-appm-col-ref {
    min-width: 10rem;
    max-width: 18rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

@media (max-width: 1024px) {
    .bexia-appm-section .fi-in-grid,
    .bexia-appm-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-appm-col-primary {
        max-width: 12rem;
    }

    .bexia-appm-col-sup {
        max-width: 16rem;
    }

    .bexia-appm-col-ref {
        max-width: 14rem;
    }
}

@media (max-width: 768px) {
    .bexia-appm-section {
        border-radius: 0.85rem;
    }

    .bexia-appm-section .fi-section-header,
    .bexia-appm-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-appm-entry .fi-in-entry-label,
    .bexia-appm-entry .fi-in-entry-label span {
        font-size: 0.78rem;
    }

    .bexia-appm-entry .fi-in-entry,
    .bexia-appm-entry .fi-in-text {
        font-size: 0.82rem;
    }

    .bexia-appm-col-num,
    .bexia-appm-col-sup,
    .bexia-appm-col-dt,
    .bexia-appm-col-amt,
    .bexia-appm-col-st,
    .bexia-appm-col-ref {
        font-size: 0.76rem;
    }

    .bexia-appm-col-primary {
        min-width: 8rem;
        max-width: 10rem;
    }

    .bexia-appm-col-sup {
        min-width: 10rem;
        max-width: 12rem;
    }

    .bexia-appm-col-ref {
        min-width: 9rem;
        max-width: 11rem;
    }
}

@media (max-width: 640px) {
    .bexia-appm-col-primary,
    .bexia-appm-col-sup,
    .bexia-appm-col-ref {
        max-width: 10rem;
    }

    .bexia-appm-col-money {
        min-width: 7rem;
    }
}
/* BEXIA_APPM_RESOURCE_RESPONSIVE_V5_79_44C_END */

/* BEXIA_ARCV_RESOURCE_RESPONSIVE_V5_79_45C_START */
/*
 * Responsive refinements for AR directory.
 * Visual scope only.
 */
.bexia-arcv-section {
    border-radius: 1rem;
}

.bexia-arcv-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-arcv-section .fi-in-grid,
.bexia-arcv-section .grid {
    gap: 0.9rem;
}

.bexia-arcv-item {
    min-width: 0;
    max-width: 100%;
}

.bexia-arcv-item .fi-in-entry,
.bexia-arcv-item .fi-in-text,
.bexia-arcv-item .fi-badge {
    max-width: 100%;
}

.bexia-arcv-item-folio,
.bexia-arcv-item-cust,
.bexia-arcv-item-custref,
.bexia-arcv-item-sale,
.bexia-arcv-item-inv,
.bexia-arcv-item-pol,
.bexia-arcv-item-acctgerr {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-arcv-item-sub .fi-in-text,
.bexia-arcv-item-tax .fi-in-text,
.bexia-arcv-item-gross .fi-in-text,
.bexia-arcv-item-coll .fi-in-text,
.bexia-arcv-item-bal .fi-in-text {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.bexia-arcv-col-folio,
.bexia-arcv-col-cust,
.bexia-arcv-col-custref,
.bexia-arcv-col-state,
.bexia-arcv-col-issue,
.bexia-arcv-col-due,
.bexia-arcv-col-gross,
.bexia-arcv-col-coll,
.bexia-arcv-col-bal {
    vertical-align: top;
}

.bexia-arcv-col-primary {
    min-width: 8rem;
    max-width: 13rem;
    white-space: normal;
    overflow-wrap: anywhere;
    font-weight: 650;
}

.bexia-arcv-col-wide {
    min-width: 12rem;
    max-width: 20rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-arcv-col-wrap {
    min-width: 10rem;
    max-width: 18rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-arcv-col-state,
.bexia-arcv-col-issue,
.bexia-arcv-col-due {
    min-width: 7rem;
    white-space: nowrap;
}

.bexia-arcv-col-money {
    min-width: 8rem;
    white-space: nowrap;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

@media (max-width: 1024px) {
    .bexia-arcv-section .fi-in-grid,
    .bexia-arcv-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-arcv-col-primary {
        max-width: 11rem;
    }

    .bexia-arcv-col-wide {
        max-width: 15rem;
    }

    .bexia-arcv-col-wrap {
        max-width: 13rem;
    }
}

@media (max-width: 768px) {
    .bexia-arcv-section {
        border-radius: 0.85rem;
    }

    .bexia-arcv-section .fi-section-header,
    .bexia-arcv-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-arcv-item .fi-in-entry-label,
    .bexia-arcv-item .fi-in-entry-label span {
        font-size: 0.78rem;
    }

    .bexia-arcv-item .fi-in-entry,
    .bexia-arcv-item .fi-in-text,
    .bexia-arcv-item .fi-badge {
        font-size: 0.82rem;
    }

    .bexia-arcv-col-folio,
    .bexia-arcv-col-cust,
    .bexia-arcv-col-custref,
    .bexia-arcv-col-state,
    .bexia-arcv-col-issue,
    .bexia-arcv-col-due,
    .bexia-arcv-col-gross,
    .bexia-arcv-col-coll,
    .bexia-arcv-col-bal {
        font-size: 0.76rem;
    }

    .bexia-arcv-col-primary {
        min-width: 7.5rem;
        max-width: 10rem;
    }

    .bexia-arcv-col-wide {
        min-width: 10rem;
        max-width: 12rem;
    }

    .bexia-arcv-col-wrap {
        min-width: 9rem;
        max-width: 11rem;
    }
}

@media (max-width: 640px) {
    .bexia-arcv-col-primary,
    .bexia-arcv-col-wide,
    .bexia-arcv-col-wrap {
        max-width: 10rem;
    }

    .bexia-arcv-col-money {
        min-width: 7rem;
    }
}
/* BEXIA_ARCV_RESOURCE_RESPONSIVE_V5_79_45C_END */

/* BEXIA_APBL_RESOURCE_RESPONSIVE_V5_79_46C_START */
/*
 * AccountPayableResource responsive refinements.
 * Visual scope only. No cambia logica de saldos, proveedores, facturas,
 * pagos, tesoreria, contabilidad, estatus, permisos ni tenant scope.
 */
.bexia-apbl-section {
    border-radius: 1rem;
}

.bexia-apbl-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-apbl-section .fi-in-grid,
.bexia-apbl-section .grid {
    gap: 0.9rem;
}

.bexia-apbl-item {
    min-width: 0;
    max-width: 100%;
}

.bexia-apbl-item .fi-in-entry,
.bexia-apbl-item .fi-in-text,
.bexia-apbl-item .fi-badge {
    max-width: 100%;
}

.bexia-apbl-item-folio,
.bexia-apbl-item-supplier,
.bexia-apbl-item-po,
.bexia-apbl-item-receipt,
.bexia-apbl-item-supplier-ref,
.bexia-apbl-item-pol,
.bexia-apbl-item-acctgerr {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-apbl-item-sub .fi-in-text,
.bexia-apbl-item-tax .fi-in-text,
.bexia-apbl-item-gross .fi-in-text,
.bexia-apbl-item-paid .fi-in-text,
.bexia-apbl-item-bal .fi-in-text {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.bexia-apbl-col-folio,
.bexia-apbl-col-supplier,
.bexia-apbl-col-receipt,
.bexia-apbl-col-state,
.bexia-apbl-col-issue,
.bexia-apbl-col-due,
.bexia-apbl-col-gross,
.bexia-apbl-col-paid,
.bexia-apbl-col-bal {
    vertical-align: top;
}

.bexia-apbl-col-primary {
    min-width: 8rem;
    max-width: 13rem;
    white-space: normal;
    overflow-wrap: anywhere;
    font-weight: 650;
}

.bexia-apbl-col-wide {
    min-width: 12rem;
    max-width: 20rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-apbl-col-wrap {
    min-width: 10rem;
    max-width: 18rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-apbl-col-state,
.bexia-apbl-col-issue,
.bexia-apbl-col-due {
    min-width: 7rem;
    white-space: nowrap;
}

.bexia-apbl-col-money {
    min-width: 8rem;
    white-space: nowrap;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

@media (max-width: 1024px) {
    .bexia-apbl-section .fi-in-grid,
    .bexia-apbl-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-apbl-col-primary {
        max-width: 11rem;
    }

    .bexia-apbl-col-wide {
        max-width: 15rem;
    }

    .bexia-apbl-col-wrap {
        max-width: 13rem;
    }
}

@media (max-width: 768px) {
    .bexia-apbl-section {
        border-radius: 0.85rem;
    }

    .bexia-apbl-section .fi-section-header,
    .bexia-apbl-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-apbl-item .fi-in-entry-label,
    .bexia-apbl-item .fi-in-entry-label span {
        font-size: 0.78rem;
    }

    .bexia-apbl-item .fi-in-entry,
    .bexia-apbl-item .fi-in-text,
    .bexia-apbl-item .fi-badge {
        font-size: 0.82rem;
    }

    .bexia-apbl-col-folio,
    .bexia-apbl-col-supplier,
    .bexia-apbl-col-receipt,
    .bexia-apbl-col-state,
    .bexia-apbl-col-issue,
    .bexia-apbl-col-due,
    .bexia-apbl-col-gross,
    .bexia-apbl-col-paid,
    .bexia-apbl-col-bal {
        font-size: 0.76rem;
    }

    .bexia-apbl-col-primary {
        min-width: 7.5rem;
        max-width: 10rem;
    }

    .bexia-apbl-col-wide {
        min-width: 10rem;
        max-width: 12rem;
    }

    .bexia-apbl-col-wrap {
        min-width: 9rem;
        max-width: 11rem;
    }
}

@media (max-width: 640px) {
    .bexia-apbl-col-primary,
    .bexia-apbl-col-wide,
    .bexia-apbl-col-wrap {
        max-width: 10rem;
    }

    .bexia-apbl-col-money {
        min-width: 7rem;
    }
}
/* BEXIA_APBL_RESOURCE_RESPONSIVE_V5_79_46C_END */

/* BEXIA_TRMOV_RESOURCE_RESPONSIVE_V5_79_47C_START */
/*
 * TreasuryMovementResource responsive refinements.
 * Visual scope only. No cambia logica de movimientos, cuentas, montos,
 * conciliacion, contabilidad, estatus, permisos ni tenant scope.
 */
.bexia-trmov-section {
    border-radius: 1rem;
}

.bexia-trmov-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-trmov-section .fi-in-grid,
.bexia-trmov-section .grid {
    gap: 0.9rem;
}

.bexia-trmov-field,
.bexia-trmov-filter,
.bexia-trmov-entry {
    min-width: 0;
    max-width: 100%;
}

.bexia-trmov-field .fi-input-wrp,
.bexia-trmov-field .fi-select-input,
.bexia-trmov-field .fi-fo-select,
.bexia-trmov-field input,
.bexia-trmov-field select,
.bexia-trmov-field textarea,
.bexia-trmov-filter .fi-input-wrp,
.bexia-trmov-filter input {
    max-width: 100%;
}

.bexia-trmov-field-reference input,
.bexia-trmov-field-description textarea,
.bexia-trmov-entry-account,
.bexia-trmov-entry-reference,
.bexia-trmov-entry-description {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-trmov-field-amount input,
.bexia-trmov-entry-amount .fi-in-entry,
.bexia-trmov-entry-amount .fi-in-text {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.bexia-trmov-col-date,
.bexia-trmov-col-account,
.bexia-trmov-col-type,
.bexia-trmov-col-amount,
.bexia-trmov-col-payment,
.bexia-trmov-col-reference,
.bexia-trmov-col-status {
    vertical-align: top;
}

.bexia-trmov-col-primary {
    min-width: 12rem;
    max-width: 20rem;
    white-space: normal;
    overflow-wrap: anywhere;
    font-weight: 650;
}

.bexia-trmov-col-wrap {
    min-width: 11rem;
    max-width: 18rem;
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-trmov-col-date,
.bexia-trmov-col-type,
.bexia-trmov-col-payment,
.bexia-trmov-col-status {
    min-width: 8rem;
    white-space: nowrap;
}

.bexia-trmov-col-money {
    min-width: 8rem;
    white-space: nowrap;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

@media (max-width: 1024px) {
    .bexia-trmov-section .fi-in-grid,
    .bexia-trmov-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-trmov-col-primary {
        max-width: 15rem;
    }

    .bexia-trmov-col-wrap {
        max-width: 13rem;
    }
}

@media (max-width: 768px) {
    .bexia-trmov-section {
        border-radius: 0.85rem;
    }

    .bexia-trmov-section .fi-section-header,
    .bexia-trmov-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-trmov-field .fi-fo-field-wrp-label,
    .bexia-trmov-field label,
    .bexia-trmov-entry .fi-in-entry-label,
    .bexia-trmov-entry .fi-in-entry-label span {
        font-size: 0.78rem;
    }

    .bexia-trmov-field input,
    .bexia-trmov-field select,
    .bexia-trmov-field textarea,
    .bexia-trmov-entry .fi-in-entry,
    .bexia-trmov-entry .fi-in-text,
    .bexia-trmov-entry .fi-badge {
        font-size: 0.82rem;
    }

    .bexia-trmov-col-date,
    .bexia-trmov-col-account,
    .bexia-trmov-col-type,
    .bexia-trmov-col-amount,
    .bexia-trmov-col-payment,
    .bexia-trmov-col-reference,
    .bexia-trmov-col-status {
        font-size: 0.76rem;
    }

    .bexia-trmov-col-primary {
        min-width: 10rem;
        max-width: 12rem;
    }

    .bexia-trmov-col-wrap {
        min-width: 9rem;
        max-width: 11rem;
    }

    .bexia-trmov-col-money {
        min-width: 7.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-trmov-col-primary,
    .bexia-trmov-col-wrap {
        max-width: 10rem;
    }

    .bexia-trmov-col-date,
    .bexia-trmov-col-type,
    .bexia-trmov-col-payment,
    .bexia-trmov-col-status {
        min-width: 7rem;
    }
}
/* BEXIA_TRMOV_RESOURCE_RESPONSIVE_V5_79_47C_END */

/* BEXIA_SVC_RESOURCE_RESPONSIVE_V5_79_48C_START */
/*
 * ServiceCaseResource responsive refinements.
 * Visual scope only. No cambia logica de clientes, tecnicos, eventos,
 * reparaciones, estados, prioridades, permisos ni tenant scope.
 */
.bexia-svc-section {
    border-radius: 1rem;
}

.bexia-svc-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-svc-section .fi-grid,
.bexia-svc-section .grid {
    gap: 0.9rem;
}

.bexia-svc-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-svc-field .fi-input-wrp,
.bexia-svc-field .fi-select-input,
.bexia-svc-field .fi-fo-select,
.bexia-svc-field input,
.bexia-svc-field select,
.bexia-svc-field textarea,
.bexia-svc-field .choices,
.bexia-svc-field .choices__inner {
    max-width: 100%;
}

.bexia-svc-field-subject input,
.bexia-svc-field-description textarea,
.bexia-svc-field-product-name input,
.bexia-svc-field-sale-reference input,
.bexia-svc-field-invoice-reference input,
.bexia-svc-col-wrap {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-svc-field-folio input,
.bexia-svc-field-serial input,
.bexia-svc-field-lot input,
.bexia-svc-col-folio,
.bexia-svc-col-serial {
    font-variant-numeric: tabular-nums;
}

.bexia-svc-col-folio,
.bexia-svc-col-subject,
.bexia-svc-col-status,
.bexia-svc-col-priority,
.bexia-svc-col-case-type,
.bexia-svc-col-product,
.bexia-svc-col-serial,
.bexia-svc-col-invoice,
.bexia-svc-col-channel,
.bexia-svc-col-team,
.bexia-svc-col-technician,
.bexia-svc-col-company,
.bexia-svc-col-due-at,
.bexia-svc-col-created-at {
    vertical-align: top;
}

.bexia-svc-col-primary {
    min-width: 8rem;
    white-space: nowrap;
    font-weight: 650;
}

.bexia-svc-col-wrap {
    min-width: 12rem;
    max-width: 22rem;
    white-space: normal;
}

.bexia-svc-col-status,
.bexia-svc-col-priority,
.bexia-svc-col-case-type,
.bexia-svc-col-channel,
.bexia-svc-col-company {
    min-width: 7rem;
    white-space: nowrap;
}

.bexia-svc-col-due-at,
.bexia-svc-col-created-at {
    min-width: 8.5rem;
    white-space: nowrap;
}

.bexia-svc-col-technician,
.bexia-svc-col-team,
.bexia-svc-col-invoice {
    min-width: 9rem;
}

@media (max-width: 1024px) {
    .bexia-svc-section .fi-grid,
    .bexia-svc-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-svc-col-wrap {
        max-width: 16rem;
    }

    .bexia-svc-col-technician,
    .bexia-svc-col-team {
        max-width: 13rem;
        white-space: normal;
        overflow-wrap: anywhere;
    }
}

@media (max-width: 768px) {
    .bexia-svc-section {
        border-radius: 0.85rem;
    }

    .bexia-svc-section .fi-section-header,
    .bexia-svc-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-svc-field .fi-fo-field-wrp-label,
    .bexia-svc-field label {
        font-size: 0.78rem;
    }

    .bexia-svc-field input,
    .bexia-svc-field select,
    .bexia-svc-field textarea,
    .bexia-svc-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-svc-col-folio,
    .bexia-svc-col-subject,
    .bexia-svc-col-status,
    .bexia-svc-col-priority,
    .bexia-svc-col-case-type,
    .bexia-svc-col-product,
    .bexia-svc-col-serial,
    .bexia-svc-col-invoice,
    .bexia-svc-col-channel,
    .bexia-svc-col-team,
    .bexia-svc-col-technician,
    .bexia-svc-col-company,
    .bexia-svc-col-due-at,
    .bexia-svc-col-created-at {
        font-size: 0.76rem;
    }

    .bexia-svc-col-wrap {
        min-width: 10rem;
        max-width: 12rem;
    }

    .bexia-svc-col-status,
    .bexia-svc-col-priority,
    .bexia-svc-col-case-type,
    .bexia-svc-col-channel {
        min-width: 6.5rem;
    }

    .bexia-svc-col-due-at,
    .bexia-svc-col-created-at {
        min-width: 7.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-svc-col-wrap {
        max-width: 10rem;
    }

    .bexia-svc-col-primary {
        min-width: 7rem;
    }

    .bexia-svc-col-technician,
    .bexia-svc-col-team,
    .bexia-svc-col-invoice {
        min-width: 7.5rem;
    }
}
/* BEXIA_SVC_RESOURCE_RESPONSIVE_V5_79_48C_END */

/* BEXIA_AENT_RESOURCE_RESPONSIVE_V5_79_49C_START */
/*
 * AccountingEntryResource responsive refinements.
 * Visual scope only. No cambia logica de polizas, cargos, abonos,
 * cuentas, balance, posting, permisos ni tenant scope.
 */
.bexia-aent-section {
    border-radius: 1rem;
}

.bexia-aent-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-aent-grid {
    min-width: 0;
    max-width: 100%;
    gap: 0.9rem;
}

.bexia-aent-entry {
    min-width: 0;
    max-width: 100%;
}

.bexia-aent-entry .fi-in-entry-wrp,
.bexia-aent-entry .fi-in-text,
.bexia-aent-entry .fi-in-text-item {
    max-width: 100%;
}

.bexia-aent-entry-number,
.bexia-aent-entry-source-id,
.bexia-aent-col-id,
.bexia-aent-col-number,
.bexia-aent-col-source-id {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.bexia-aent-entry-money,
.bexia-aent-col-money {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    text-align: right;
}

.bexia-aent-entry-notes,
.bexia-aent-col-source-type {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-aent-col-id,
.bexia-aent-col-number,
.bexia-aent-col-date,
.bexia-aent-col-company,
.bexia-aent-col-status,
.bexia-aent-col-source-type,
.bexia-aent-col-source-id,
.bexia-aent-col-debit,
.bexia-aent-col-credit,
.bexia-aent-col-posted-at {
    vertical-align: top;
}

.bexia-aent-col-primary {
    min-width: 9rem;
    font-weight: 650;
}

.bexia-aent-col-id {
    min-width: 4.5rem;
}

.bexia-aent-col-date,
.bexia-aent-col-posted-at {
    min-width: 8.5rem;
    white-space: nowrap;
}

.bexia-aent-col-company,
.bexia-aent-col-status,
.bexia-aent-col-source-type,
.bexia-aent-col-source-id {
    min-width: 7rem;
}

.bexia-aent-col-debit,
.bexia-aent-col-credit {
    min-width: 8.5rem;
}

@media (max-width: 1024px) {
    .bexia-aent-grid,
    .bexia-aent-section .fi-grid,
    .bexia-aent-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-aent-col-source-type {
        max-width: 13rem;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .bexia-aent-col-debit,
    .bexia-aent-col-credit {
        min-width: 7.5rem;
    }
}

@media (max-width: 768px) {
    .bexia-aent-section {
        border-radius: 0.85rem;
    }

    .bexia-aent-section .fi-section-header,
    .bexia-aent-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-aent-entry .fi-in-entry-wrp-label,
    .bexia-aent-entry .fi-in-entry-wrp-label span {
        font-size: 0.78rem;
    }

    .bexia-aent-entry .fi-in-text,
    .bexia-aent-entry .fi-in-text-item {
        font-size: 0.82rem;
    }

    .bexia-aent-col-id,
    .bexia-aent-col-number,
    .bexia-aent-col-date,
    .bexia-aent-col-company,
    .bexia-aent-col-status,
    .bexia-aent-col-source-type,
    .bexia-aent-col-source-id,
    .bexia-aent-col-debit,
    .bexia-aent-col-credit,
    .bexia-aent-col-posted-at {
        font-size: 0.76rem;
    }

    .bexia-aent-col-primary {
        min-width: 8rem;
    }

    .bexia-aent-col-date,
    .bexia-aent-col-posted-at {
        min-width: 7.5rem;
    }

    .bexia-aent-col-company,
    .bexia-aent-col-status,
    .bexia-aent-col-source-type,
    .bexia-aent-col-source-id {
        min-width: 6.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-aent-col-id {
        min-width: 3.5rem;
    }

    .bexia-aent-col-primary {
        min-width: 7rem;
    }

    .bexia-aent-col-debit,
    .bexia-aent-col-credit {
        min-width: 7rem;
    }
}
/* BEXIA_AENT_RESOURCE_RESPONSIVE_V5_79_49C_END */

/* BEXIA_APWF_RESOURCE_RESPONSIVE_V5_79_50C_START */
/*
 * ApprovalWorkflowResource responsive refinements.
 * Visual scope only. No cambia logica de aprobaciones, pasos, reglas,
 * estados, roles, usuarios, permisos ni tenant scope.
 */
.bexia-apwf-section {
    border-radius: 1rem;
}

.bexia-apwf-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-apwf-field,
.bexia-apwf-step-field,
.bexia-apwf-repeater {
    min-width: 0;
    max-width: 100%;
}

.bexia-apwf-field .fi-input-wrp,
.bexia-apwf-field .fi-select-input,
.bexia-apwf-field .fi-fo-select,
.bexia-apwf-field input,
.bexia-apwf-field select,
.bexia-apwf-field textarea,
.bexia-apwf-step-field .fi-input-wrp,
.bexia-apwf-step-field .fi-select-input,
.bexia-apwf-step-field .fi-fo-select,
.bexia-apwf-step-field input,
.bexia-apwf-step-field select,
.bexia-apwf-step-field textarea {
    max-width: 100%;
}

.bexia-apwf-field-flow-name input,
.bexia-apwf-field-notes textarea,
.bexia-apwf-step-field-name input,
.bexia-apwf-step-field-notes textarea,
.bexia-apwf-col-name,
.bexia-apwf-col-document-type {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-apwf-field-priority input,
.bexia-apwf-field-amount-min input,
.bexia-apwf-field-amount-max input,
.bexia-apwf-step-field-order input,
.bexia-apwf-step-field-amount-min input,
.bexia-apwf-step-field-amount-max input,
.bexia-apwf-col-priority,
.bexia-apwf-col-money,
.bexia-apwf-col-steps {
    font-variant-numeric: tabular-nums;
}

.bexia-apwf-col-active,
.bexia-apwf-col-name,
.bexia-apwf-col-document-type,
.bexia-apwf-col-priority,
.bexia-apwf-col-amount-range,
.bexia-apwf-col-steps,
.bexia-apwf-col-updated-at {
    vertical-align: top;
}

.bexia-apwf-col-primary {
    min-width: 13rem;
    max-width: 24rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-apwf-col-active {
    min-width: 4.5rem;
    text-align: center;
}

.bexia-apwf-col-document-type {
    min-width: 10rem;
    max-width: 16rem;
    white-space: normal;
}

.bexia-apwf-col-priority,
.bexia-apwf-col-steps {
    min-width: 5.5rem;
    white-space: nowrap;
}

.bexia-apwf-col-amount-range {
    min-width: 12rem;
    white-space: nowrap;
}

.bexia-apwf-col-updated-at {
    min-width: 8.5rem;
    white-space: nowrap;
}

.bexia-apwf-repeater .fi-fo-repeater-item,
.bexia-apwf-section-steps .fi-fo-repeater-item {
    min-width: 0;
    overflow-x: hidden;
}

.bexia-apwf-repeater .fi-fo-repeater-item-content,
.bexia-apwf-section-steps .fi-fo-repeater-item-content {
    min-width: 0;
}

@media (max-width: 1024px) {
    .bexia-apwf-section .fi-grid,
    .bexia-apwf-section .grid,
    .bexia-apwf-repeater .fi-grid,
    .bexia-apwf-repeater .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-apwf-col-primary {
        max-width: 18rem;
    }

    .bexia-apwf-col-document-type {
        max-width: 13rem;
    }

    .bexia-apwf-col-amount-range {
        min-width: 10rem;
    }
}

@media (max-width: 768px) {
    .bexia-apwf-section {
        border-radius: 0.85rem;
    }

    .bexia-apwf-section .fi-section-header,
    .bexia-apwf-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-apwf-repeater .fi-fo-repeater-item-header {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }

    .bexia-apwf-field .fi-fo-field-wrp-label,
    .bexia-apwf-step-field .fi-fo-field-wrp-label,
    .bexia-apwf-field label,
    .bexia-apwf-step-field label {
        font-size: 0.78rem;
    }

    .bexia-apwf-field input,
    .bexia-apwf-field select,
    .bexia-apwf-field textarea,
    .bexia-apwf-step-field input,
    .bexia-apwf-step-field select,
    .bexia-apwf-step-field textarea,
    .bexia-apwf-field .choices__inner,
    .bexia-apwf-step-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-apwf-col-active,
    .bexia-apwf-col-name,
    .bexia-apwf-col-document-type,
    .bexia-apwf-col-priority,
    .bexia-apwf-col-amount-range,
    .bexia-apwf-col-steps,
    .bexia-apwf-col-updated-at {
        font-size: 0.76rem;
    }

    .bexia-apwf-col-primary {
        min-width: 10rem;
        max-width: 13rem;
    }

    .bexia-apwf-col-document-type {
        min-width: 8.5rem;
        max-width: 11rem;
    }

    .bexia-apwf-col-amount-range {
        min-width: 8.5rem;
    }

    .bexia-apwf-col-updated-at {
        min-width: 7.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-apwf-col-primary {
        min-width: 8.5rem;
        max-width: 10rem;
    }

    .bexia-apwf-col-document-type {
        min-width: 7.5rem;
        max-width: 9rem;
    }

    .bexia-apwf-col-priority,
    .bexia-apwf-col-steps {
        min-width: 4.5rem;
    }

    .bexia-apwf-col-amount-range {
        min-width: 7.5rem;
    }
}
/* BEXIA_APWF_RESOURCE_RESPONSIVE_V5_79_50C_END */

/* BEXIA_HRLOC_RESOURCE_RESPONSIVE_V5_79_51C_START */
/*
 * HrAttendanceLocationResource responsive refinements.
 * Visual scope only. No cambia logica de asistencia, ubicaciones,
 * geocercas, coordenadas, radio, sucursal, empresa, permisos ni tenant.
 */
.bexia-hrloc-section {
    border-radius: 1rem;
}

.bexia-hrloc-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-hrloc-field,
.bexia-hrloc-toggle,
.bexia-hrloc-placeholder {
    min-width: 0;
    max-width: 100%;
}

.bexia-hrloc-field .fi-input-wrp,
.bexia-hrloc-field .fi-select-input,
.bexia-hrloc-field .fi-fo-select,
.bexia-hrloc-field input,
.bexia-hrloc-field select,
.bexia-hrloc-field textarea {
    max-width: 100%;
}

.bexia-hrloc-field-name input,
.bexia-hrloc-field-code input,
.bexia-hrloc-field-address textarea,
.bexia-hrloc-field-poly textarea,
.bexia-hrloc-field-notes textarea,
.bexia-hrloc-placeholder,
.bexia-hrloc-col-name,
.bexia-hrloc-col-branch,
.bexia-hrloc-col-kind {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-hrloc-field-number input,
.bexia-hrloc-field-lat input,
.bexia-hrloc-field-lng input,
.bexia-hrloc-col-number {
    font-variant-numeric: tabular-nums;
}

.bexia-hrloc-col-name,
.bexia-hrloc-col-code,
.bexia-hrloc-col-branch,
.bexia-hrloc-col-kind,
.bexia-hrloc-col-lat,
.bexia-hrloc-col-lng,
.bexia-hrloc-col-radius,
.bexia-hrloc-col-mobile,
.bexia-hrloc-col-review,
.bexia-hrloc-col-active {
    vertical-align: top;
}

.bexia-hrloc-col-primary {
    min-width: 13rem;
    max-width: 24rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-hrloc-col-code {
    min-width: 7rem;
    white-space: nowrap;
}

.bexia-hrloc-col-branch {
    min-width: 10rem;
    max-width: 16rem;
    white-space: normal;
}

.bexia-hrloc-col-kind {
    min-width: 8rem;
    max-width: 12rem;
    white-space: normal;
}

.bexia-hrloc-col-lat,
.bexia-hrloc-col-lng {
    min-width: 7rem;
    white-space: nowrap;
}

.bexia-hrloc-col-radius {
    min-width: 6.5rem;
    white-space: nowrap;
}

.bexia-hrloc-col-mobile,
.bexia-hrloc-col-review,
.bexia-hrloc-col-active {
    min-width: 5.25rem;
    text-align: center;
}

.bexia-hrloc-field-poly textarea {
    min-height: 7rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-size: 0.82rem;
    line-height: 1.35;
}

.bexia-hrloc-field-people .choices__inner,
.bexia-hrloc-field-people .fi-select-input {
    min-height: 2.7rem;
}

.bexia-hrloc-placeholder-summary {
    border-radius: 0.85rem;
    overflow-x: hidden;
}

@media (max-width: 1024px) {
    .bexia-hrloc-section .fi-grid,
    .bexia-hrloc-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-hrloc-col-primary {
        max-width: 18rem;
    }

    .bexia-hrloc-col-branch {
        max-width: 13rem;
    }

    .bexia-hrloc-col-kind {
        max-width: 10rem;
    }
}

@media (max-width: 768px) {
    .bexia-hrloc-section {
        border-radius: 0.85rem;
    }

    .bexia-hrloc-section .fi-section-header,
    .bexia-hrloc-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-hrloc-field .fi-fo-field-wrp-label,
    .bexia-hrloc-toggle .fi-fo-field-wrp-label,
    .bexia-hrloc-field label,
    .bexia-hrloc-toggle label {
        font-size: 0.78rem;
    }

    .bexia-hrloc-field input,
    .bexia-hrloc-field select,
    .bexia-hrloc-field textarea,
    .bexia-hrloc-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-hrloc-col-name,
    .bexia-hrloc-col-code,
    .bexia-hrloc-col-branch,
    .bexia-hrloc-col-kind,
    .bexia-hrloc-col-lat,
    .bexia-hrloc-col-lng,
    .bexia-hrloc-col-radius,
    .bexia-hrloc-col-mobile,
    .bexia-hrloc-col-review,
    .bexia-hrloc-col-active {
        font-size: 0.76rem;
    }

    .bexia-hrloc-col-primary {
        min-width: 10rem;
        max-width: 13rem;
    }

    .bexia-hrloc-col-code,
    .bexia-hrloc-col-lat,
    .bexia-hrloc-col-lng,
    .bexia-hrloc-col-radius {
        min-width: 6.25rem;
    }

    .bexia-hrloc-col-branch {
        min-width: 8.5rem;
        max-width: 11rem;
    }

    .bexia-hrloc-col-kind {
        min-width: 7rem;
        max-width: 9rem;
    }

    .bexia-hrloc-col-mobile,
    .bexia-hrloc-col-review,
    .bexia-hrloc-col-active {
        min-width: 4.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-hrloc-col-primary {
        min-width: 8.5rem;
        max-width: 10rem;
    }

    .bexia-hrloc-col-branch {
        min-width: 7.5rem;
        max-width: 9rem;
    }

    .bexia-hrloc-col-kind {
        min-width: 6.5rem;
        max-width: 8rem;
    }

    .bexia-hrloc-col-code,
    .bexia-hrloc-col-lat,
    .bexia-hrloc-col-lng,
    .bexia-hrloc-col-radius {
        min-width: 5.75rem;
    }
}
/* BEXIA_HRLOC_RESOURCE_RESPONSIVE_V5_79_51C_END */

/* BEXIA_ETERM_RESOURCE_RESPONSIVE_V5_79_52C_START */
/*
 * EmployeeTerminationResource responsive refinements.
 * Visual scope only. No cambia logica de bajas, empleados, fechas,
 * motivos, finiquitos/liquidaciones, empresa, permisos ni tenant.
 */
.bexia-eterm-section {
    border-radius: 1rem;
}

.bexia-eterm-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-eterm-grid,
.bexia-eterm-field,
.bexia-eterm-toggle,
.bexia-eterm-placeholder {
    min-width: 0;
    max-width: 100%;
}

.bexia-eterm-field .fi-input-wrp,
.bexia-eterm-field .fi-select-input,
.bexia-eterm-field .fi-fo-select,
.bexia-eterm-field input,
.bexia-eterm-field select,
.bexia-eterm-field textarea {
    max-width: 100%;
}

.bexia-eterm-field-employee,
.bexia-eterm-field-contract,
.bexia-eterm-field-reason textarea,
.bexia-eterm-field-notes textarea,
.bexia-eterm-placeholder,
.bexia-eterm-col-employee,
.bexia-eterm-col-type,
.bexia-eterm-col-status {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-eterm-field-money input,
.bexia-eterm-col-money {
    font-variant-numeric: tabular-nums;
}

.bexia-eterm-field-currency input,
.bexia-eterm-col-number,
.bexia-eterm-col-date {
    white-space: nowrap;
}

.bexia-eterm-col-employee,
.bexia-eterm-col-number,
.bexia-eterm-col-type,
.bexia-eterm-col-status,
.bexia-eterm-col-date,
.bexia-eterm-col-rehire,
.bexia-eterm-col-money,
.bexia-eterm-col-file {
    vertical-align: top;
}

.bexia-eterm-col-primary {
    min-width: 13rem;
    max-width: 24rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-eterm-col-number {
    min-width: 8rem;
}

.bexia-eterm-col-type,
.bexia-eterm-col-status {
    min-width: 8.5rem;
    max-width: 12rem;
    white-space: normal;
}

.bexia-eterm-col-date {
    min-width: 7.25rem;
}

.bexia-eterm-col-money {
    min-width: 8rem;
    text-align: right;
    white-space: nowrap;
}

.bexia-eterm-col-rehire,
.bexia-eterm-col-file {
    min-width: 5.25rem;
    text-align: center;
}

.bexia-eterm-field-reason textarea,
.bexia-eterm-field-notes textarea {
    min-height: 5.75rem;
    line-height: 1.4;
}

.bexia-eterm-placeholder-warning {
    border-radius: 0.85rem;
    overflow-x: hidden;
}

.bexia-eterm-field-date .fi-input-wrp,
.bexia-eterm-field-money .fi-input-wrp,
.bexia-eterm-field-currency .fi-input-wrp {
    min-width: 0;
}

@media (max-width: 1024px) {
    .bexia-eterm-grid,
    .bexia-eterm-section .fi-grid,
    .bexia-eterm-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-eterm-col-primary {
        max-width: 18rem;
    }

    .bexia-eterm-col-type,
    .bexia-eterm-col-status {
        max-width: 10rem;
    }
}

@media (max-width: 768px) {
    .bexia-eterm-section {
        border-radius: 0.85rem;
    }

    .bexia-eterm-section .fi-section-header,
    .bexia-eterm-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-eterm-field .fi-fo-field-wrp-label,
    .bexia-eterm-toggle .fi-fo-field-wrp-label,
    .bexia-eterm-field label,
    .bexia-eterm-toggle label {
        font-size: 0.78rem;
    }

    .bexia-eterm-field input,
    .bexia-eterm-field select,
    .bexia-eterm-field textarea,
    .bexia-eterm-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-eterm-col-employee,
    .bexia-eterm-col-number,
    .bexia-eterm-col-type,
    .bexia-eterm-col-status,
    .bexia-eterm-col-date,
    .bexia-eterm-col-rehire,
    .bexia-eterm-col-money,
    .bexia-eterm-col-file {
        font-size: 0.76rem;
    }

    .bexia-eterm-col-primary {
        min-width: 10rem;
        max-width: 13rem;
    }

    .bexia-eterm-col-number,
    .bexia-eterm-col-type,
    .bexia-eterm-col-status,
    .bexia-eterm-col-money {
        min-width: 7rem;
    }

    .bexia-eterm-col-date {
        min-width: 6.5rem;
    }

    .bexia-eterm-col-rehire,
    .bexia-eterm-col-file {
        min-width: 4.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-eterm-col-primary {
        min-width: 8.5rem;
        max-width: 10.5rem;
    }

    .bexia-eterm-col-number,
    .bexia-eterm-col-type,
    .bexia-eterm-col-status,
    .bexia-eterm-col-money {
        min-width: 6.25rem;
    }

    .bexia-eterm-col-date {
        min-width: 5.85rem;
    }
}
/* BEXIA_ETERM_RESOURCE_RESPONSIVE_V5_79_52C_END */

/* BEXIA_SC_RESOURCE_RESPONSIVE_V5_79_53C_START */
/*
 * SatCompanyCredentialResource responsive refinements.
 * Visual scope only. No cambia logica SAT, certificados, llaves,
 * passwords, PAC, RFC, empresa, permisos ni tenant.
 */
.bexia-scred-section {
    border-radius: 1rem;
}

.bexia-scred-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-scred-section,
.bexia-scred-field,
.bexia-scred-toggle {
    min-width: 0;
    max-width: 100%;
}

.bexia-scred-field .fi-input-wrp,
.bexia-scred-field .fi-select-input,
.bexia-scred-field .fi-fo-select,
.bexia-scred-field .fi-fo-file-upload,
.bexia-scred-field input,
.bexia-scred-field select,
.bexia-scred-field textarea {
    max-width: 100%;
}

.bexia-scred-field-company,
.bexia-scred-field-legal-name,
.bexia-scred-field-error textarea,
.bexia-scred-field-notes textarea,
.bexia-scred-col-company,
.bexia-scred-col-legal-name,
.bexia-scred-col-status {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-scred-mono,
.bexia-scred-col-rfc,
.bexia-scred-field-rfc input,
.bexia-scred-field-serial input {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    letter-spacing: 0.01em;
}

.bexia-scred-field-rfc input,
.bexia-scred-field-serial input,
.bexia-scred-col-rfc,
.bexia-scred-col-date {
    white-space: nowrap;
}

.bexia-scred-col-company,
.bexia-scred-col-rfc,
.bexia-scred-col-legal-name,
.bexia-scred-col-status,
.bexia-scred-col-enabled,
.bexia-scred-col-file,
.bexia-scred-col-date {
    vertical-align: top;
}

.bexia-scred-col-primary {
    min-width: 12rem;
    max-width: 22rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-scred-col-rfc {
    min-width: 8rem;
}

.bexia-scred-col-legal-name {
    min-width: 14rem;
    max-width: 24rem;
    white-space: normal;
}

.bexia-scred-col-status {
    min-width: 8.5rem;
    max-width: 12rem;
    white-space: normal;
}

.bexia-scred-col-enabled,
.bexia-scred-col-file {
    min-width: 4.75rem;
    text-align: center;
}

.bexia-scred-col-date {
    min-width: 8.5rem;
}

.bexia-scred-field-file .fi-fo-file-upload,
.bexia-scred-field-file .filepond--root {
    min-width: 0;
    max-width: 100%;
}

.bexia-scred-field-error textarea,
.bexia-scred-field-notes textarea {
    min-height: 5.75rem;
    line-height: 1.4;
}

.bexia-scred-section-validation .fi-section-content {
    overflow-x: hidden;
}

.bexia-scred-field-datetime .fi-input-wrp,
.bexia-scred-field-status .fi-input-wrp,
.bexia-scred-field-type .fi-input-wrp {
    min-width: 0;
}

@media (max-width: 1024px) {
    .bexia-scred-section .fi-grid,
    .bexia-scred-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-scred-col-primary,
    .bexia-scred-col-legal-name {
        max-width: 18rem;
    }

    .bexia-scred-col-status {
        max-width: 10rem;
    }
}

@media (max-width: 768px) {
    .bexia-scred-section {
        border-radius: 0.85rem;
    }

    .bexia-scred-section .fi-section-header,
    .bexia-scred-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-scred-field .fi-fo-field-wrp-label,
    .bexia-scred-toggle .fi-fo-field-wrp-label,
    .bexia-scred-field label,
    .bexia-scred-toggle label {
        font-size: 0.78rem;
    }

    .bexia-scred-field input,
    .bexia-scred-field select,
    .bexia-scred-field textarea,
    .bexia-scred-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-scred-col-company,
    .bexia-scred-col-rfc,
    .bexia-scred-col-legal-name,
    .bexia-scred-col-status,
    .bexia-scred-col-enabled,
    .bexia-scred-col-file,
    .bexia-scred-col-date {
        font-size: 0.76rem;
    }

    .bexia-scred-col-primary {
        min-width: 9rem;
        max-width: 13rem;
    }

    .bexia-scred-col-legal-name {
        min-width: 10rem;
        max-width: 14rem;
    }

    .bexia-scred-col-rfc,
    .bexia-scred-col-status,
    .bexia-scred-col-date {
        min-width: 7rem;
    }

    .bexia-scred-col-enabled,
    .bexia-scred-col-file {
        min-width: 4.25rem;
    }
}

@media (max-width: 640px) {
    .bexia-scred-col-primary,
    .bexia-scred-col-legal-name {
        min-width: 8.5rem;
        max-width: 10.5rem;
    }

    .bexia-scred-col-rfc,
    .bexia-scred-col-status,
    .bexia-scred-col-date {
        min-width: 6.25rem;
    }
}
/* BEXIA_SC_RESOURCE_RESPONSIVE_V5_79_53C_END */

/* BEXIA_PAUDIT_RESOURCE_RESPONSIVE_V5_79_54C_START */
/*
 * PosAuditLogResource responsive refinements.
 * Visual scope only. No cambia logica PDV, auditoria, logs,
 * usuario, ticket, sesion, empresa, permisos ni tenant.
 */
.bexia-paudit-section {
    border-radius: 1rem;
}

.bexia-paudit-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-paudit-section,
.bexia-paudit-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-paudit-field .fi-input-wrp,
.bexia-paudit-field input,
.bexia-paudit-field select,
.bexia-paudit-field textarea {
    max-width: 100%;
}

.bexia-paudit-field-description,
.bexia-paudit-field-entity-type,
.bexia-paudit-field-user-agent,
.bexia-paudit-section-data,
.bexia-paudit-section-data .fi-fo-field-wrp,
.bexia-paudit-section-data pre,
.bexia-paudit-section-data code,
.bexia-paudit-col-description,
.bexia-paudit-col-user,
.bexia-paudit-col-action {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-paudit-mono,
.bexia-paudit-col-id,
.bexia-paudit-col-company,
.bexia-paudit-col-session,
.bexia-paudit-col-ticket,
.bexia-paudit-col-refund,
.bexia-paudit-col-ip {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    letter-spacing: 0.01em;
}

.bexia-paudit-field-id input,
.bexia-paudit-field-company input,
.bexia-paudit-field-user input,
.bexia-paudit-field-session input,
.bexia-paudit-field-ticket input,
.bexia-paudit-field-refund input,
.bexia-paudit-field-stock-movement input,
.bexia-paudit-field-entity-id input,
.bexia-paudit-field-ip input,
.bexia-paudit-col-id,
.bexia-paudit-col-company,
.bexia-paudit-col-session,
.bexia-paudit-col-ticket,
.bexia-paudit-col-refund,
.bexia-paudit-col-ip,
.bexia-paudit-col-date {
    white-space: nowrap;
}

.bexia-paudit-col-id,
.bexia-paudit-col-date,
.bexia-paudit-col-action,
.bexia-paudit-col-description,
.bexia-paudit-col-company,
.bexia-paudit-col-user,
.bexia-paudit-col-session,
.bexia-paudit-col-ticket,
.bexia-paudit-col-refund,
.bexia-paudit-col-ip {
    vertical-align: top;
}

.bexia-paudit-col-primary {
    min-width: 14rem;
    max-width: 28rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-paudit-col-id {
    min-width: 4.75rem;
}

.bexia-paudit-col-date {
    min-width: 8.75rem;
}

.bexia-paudit-col-action {
    min-width: 9rem;
    max-width: 13rem;
    white-space: normal;
}

.bexia-paudit-col-company,
.bexia-paudit-col-session,
.bexia-paudit-col-ticket,
.bexia-paudit-col-refund {
    min-width: 6rem;
}

.bexia-paudit-col-user {
    min-width: 9rem;
    max-width: 15rem;
    white-space: normal;
}

.bexia-paudit-col-ip {
    min-width: 8rem;
}

.bexia-paudit-section-data .fi-section-content {
    overflow-x: auto;
}

.bexia-paudit-section-data pre,
.bexia-paudit-section-data code {
    max-width: 100%;
    white-space: pre-wrap;
    line-height: 1.35;
}

.bexia-paudit-field-user-agent input {
    text-overflow: ellipsis;
}

.bexia-paudit-field-filter-date .fi-input-wrp,
.bexia-paudit-field-datetime .fi-input-wrp {
    min-width: 0;
}

@media (max-width: 1024px) {
    .bexia-paudit-section .fi-grid,
    .bexia-paudit-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-paudit-col-primary {
        max-width: 20rem;
    }

    .bexia-paudit-col-action {
        max-width: 11rem;
    }
}

@media (max-width: 768px) {
    .bexia-paudit-section {
        border-radius: 0.85rem;
    }

    .bexia-paudit-section .fi-section-header,
    .bexia-paudit-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-paudit-field .fi-fo-field-wrp-label,
    .bexia-paudit-field label {
        font-size: 0.78rem;
    }

    .bexia-paudit-field input,
    .bexia-paudit-field select,
    .bexia-paudit-field textarea {
        font-size: 0.82rem;
    }

    .bexia-paudit-col-id,
    .bexia-paudit-col-date,
    .bexia-paudit-col-action,
    .bexia-paudit-col-description,
    .bexia-paudit-col-company,
    .bexia-paudit-col-user,
    .bexia-paudit-col-session,
    .bexia-paudit-col-ticket,
    .bexia-paudit-col-refund,
    .bexia-paudit-col-ip {
        font-size: 0.76rem;
    }

    .bexia-paudit-col-primary {
        min-width: 10rem;
        max-width: 14rem;
    }

    .bexia-paudit-col-action,
    .bexia-paudit-col-user {
        min-width: 7.5rem;
        max-width: 10rem;
    }

    .bexia-paudit-col-date,
    .bexia-paudit-col-ip {
        min-width: 7rem;
    }

    .bexia-paudit-col-company,
    .bexia-paudit-col-session,
    .bexia-paudit-col-ticket,
    .bexia-paudit-col-refund {
        min-width: 5.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-paudit-col-primary {
        min-width: 8.5rem;
        max-width: 11rem;
    }

    .bexia-paudit-col-action,
    .bexia-paudit-col-user {
        min-width: 6.75rem;
        max-width: 8.5rem;
    }

    .bexia-paudit-col-date,
    .bexia-paudit-col-ip {
        min-width: 6.25rem;
    }

    .bexia-paudit-col-id,
    .bexia-paudit-col-company,
    .bexia-paudit-col-session,
    .bexia-paudit-col-ticket,
    .bexia-paudit-col-refund {
        min-width: 4.75rem;
    }
}
/* BEXIA_PAUDIT_RESOURCE_RESPONSIVE_V5_79_54C_END */

/* BEXIA_EPDED_RESOURCE_RESPONSIVE_V5_79_55C_START */
/*
 * EmployeePayrollDeductionResource responsive refinements.
 * Alcance visual solamente. No cambia logica de nomina, descuentos,
 * empleado, concepto, montos, periodos, permisos, empresa ni tenant.
 */
.bexia-epded-section {
    border-radius: 1rem;
}

.bexia-epded-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-epded-section,
.bexia-epded-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-epded-field .fi-input-wrp,
.bexia-epded-field .fi-select-input,
.bexia-epded-field .fi-fo-select,
.bexia-epded-field input,
.bexia-epded-field select,
.bexia-epded-field textarea,
.bexia-epded-field .choices,
.bexia-epded-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-epded-field-name,
.bexia-epded-field-notes,
.bexia-epded-col-employee,
.bexia-epded-col-name,
.bexia-epded-col-type,
.bexia-epded-col-status {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-epded-mono,
.bexia-epded-field-code input,
.bexia-epded-col-periods {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    letter-spacing: 0.01em;
}

.bexia-epded-field-money input,
.bexia-epded-field-periods input,
.bexia-epded-col-money,
.bexia-epded-col-periods {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.bexia-epded-col-employee,
.bexia-epded-col-type,
.bexia-epded-col-name,
.bexia-epded-col-money,
.bexia-epded-col-periods,
.bexia-epded-col-status {
    vertical-align: top;
}

.bexia-epded-col-primary {
    min-width: 12rem;
    max-width: 24rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-epded-col-type,
.bexia-epded-col-status {
    min-width: 7.5rem;
    max-width: 11rem;
    white-space: normal;
}

.bexia-epded-col-money {
    min-width: 7.25rem;
}

.bexia-epded-col-periods {
    min-width: 6rem;
}

.bexia-epded-field-notes textarea {
    min-height: 6.5rem;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-epded-section-amounts .fi-section-content {
    overflow-x: hidden;
}

.bexia-epded-section-notes .fi-section-content {
    overflow-x: hidden;
}

@media (max-width: 1024px) {
    .bexia-epded-section .fi-grid,
    .bexia-epded-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-epded-col-primary {
        max-width: 18rem;
    }

    .bexia-epded-col-type,
    .bexia-epded-col-status {
        max-width: 10rem;
    }
}

@media (max-width: 768px) {
    .bexia-epded-section {
        border-radius: 0.85rem;
    }

    .bexia-epded-section .fi-section-header,
    .bexia-epded-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-epded-field .fi-fo-field-wrp-label,
    .bexia-epded-field label {
        font-size: 0.78rem;
    }

    .bexia-epded-field input,
    .bexia-epded-field select,
    .bexia-epded-field textarea,
    .bexia-epded-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-epded-col-employee,
    .bexia-epded-col-type,
    .bexia-epded-col-name,
    .bexia-epded-col-money,
    .bexia-epded-col-periods,
    .bexia-epded-col-status {
        font-size: 0.76rem;
    }

    .bexia-epded-col-primary {
        min-width: 9.5rem;
        max-width: 13rem;
    }

    .bexia-epded-col-type,
    .bexia-epded-col-status {
        min-width: 6.75rem;
        max-width: 8.75rem;
    }

    .bexia-epded-col-money {
        min-width: 6.25rem;
    }

    .bexia-epded-col-periods {
        min-width: 5rem;
    }
}

@media (max-width: 640px) {
    .bexia-epded-col-primary {
        min-width: 8.25rem;
        max-width: 10.5rem;
    }

    .bexia-epded-col-type,
    .bexia-epded-col-status {
        min-width: 5.75rem;
        max-width: 7.25rem;
    }

    .bexia-epded-col-money {
        min-width: 5.75rem;
    }

    .bexia-epded-col-periods {
        min-width: 4.75rem;
    }
}
/* BEXIA_EPDED_RESOURCE_RESPONSIVE_V5_79_55C_END */

/* BEXIA_EPPER_RESOURCE_RESPONSIVE_V5_79_56C_START */
/*
 * EmployeePayrollPerceptionResource responsive refinements.
 * Alcance visual solamente. No cambia logica de nomina, percepciones,
 * empleado, concepto, montos, periodos, permisos, empresa ni tenant.
 */
.bexia-epper-section {
    border-radius: 1rem;
}

.bexia-epper-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-epper-section,
.bexia-epper-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-epper-field .fi-input-wrp,
.bexia-epper-field .fi-select-input,
.bexia-epper-field .fi-fo-select,
.bexia-epper-field input,
.bexia-epper-field select,
.bexia-epper-field textarea,
.bexia-epper-field .choices,
.bexia-epper-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-epper-field-name,
.bexia-epper-field-notes,
.bexia-epper-col-employee,
.bexia-epper-col-name,
.bexia-epper-col-type,
.bexia-epper-col-status {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-epper-mono,
.bexia-epper-field-code input,
.bexia-epper-col-periods {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    letter-spacing: 0.01em;
}

.bexia-epper-field-money input,
.bexia-epper-field-periods input,
.bexia-epper-col-money,
.bexia-epper-col-periods {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.bexia-epper-col-employee,
.bexia-epper-col-type,
.bexia-epper-col-name,
.bexia-epper-col-money,
.bexia-epper-col-periods,
.bexia-epper-col-status {
    vertical-align: top;
}

.bexia-epper-col-primary {
    min-width: 12rem;
    max-width: 24rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-epper-col-type,
.bexia-epper-col-status {
    min-width: 7.5rem;
    max-width: 11rem;
    white-space: normal;
}

.bexia-epper-col-money {
    min-width: 7.25rem;
}

.bexia-epper-col-periods {
    min-width: 6rem;
}

.bexia-epper-field-notes textarea {
    min-height: 6.5rem;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-epper-section-amounts .fi-section-content {
    overflow-x: hidden;
}

.bexia-epper-section-notes .fi-section-content {
    overflow-x: hidden;
}

@media (max-width: 1024px) {
    .bexia-epper-section .fi-grid,
    .bexia-epper-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-epper-col-primary {
        max-width: 18rem;
    }

    .bexia-epper-col-type,
    .bexia-epper-col-status {
        max-width: 10rem;
    }
}

@media (max-width: 768px) {
    .bexia-epper-section {
        border-radius: 0.85rem;
    }

    .bexia-epper-section .fi-section-header,
    .bexia-epper-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-epper-field .fi-fo-field-wrp-label,
    .bexia-epper-field label {
        font-size: 0.78rem;
    }

    .bexia-epper-field input,
    .bexia-epper-field select,
    .bexia-epper-field textarea,
    .bexia-epper-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-epper-col-employee,
    .bexia-epper-col-type,
    .bexia-epper-col-name,
    .bexia-epper-col-money,
    .bexia-epper-col-periods,
    .bexia-epper-col-status {
        font-size: 0.76rem;
    }

    .bexia-epper-col-primary {
        min-width: 9.5rem;
        max-width: 13rem;
    }

    .bexia-epper-col-type,
    .bexia-epper-col-status {
        min-width: 6.75rem;
        max-width: 8.75rem;
    }

    .bexia-epper-col-money {
        min-width: 6.25rem;
    }

    .bexia-epper-col-periods {
        min-width: 5rem;
    }
}

@media (max-width: 640px) {
    .bexia-epper-col-primary {
        min-width: 8.25rem;
        max-width: 10.5rem;
    }

    .bexia-epper-col-type,
    .bexia-epper-col-status {
        min-width: 5.75rem;
        max-width: 7.25rem;
    }

    .bexia-epper-col-money {
        min-width: 5.75rem;
    }

    .bexia-epper-col-periods {
        min-width: 4.75rem;
    }
}
/* BEXIA_EPPER_RESOURCE_RESPONSIVE_V5_79_56C_END */

/* BEXIA_SCFDOC_RESOURCE_RESPONSIVE_V5_79_57C_START */
/*
 * SatCfdiDocumentResource responsive refinements.
 * Alcance visual solamente. No cambia logica fiscal/SAT/CFDI,
 * XML, PDF, UUID, cancelacion, permisos, empresa ni tenant.
 */
.bexia-scfdoc-section {
    border-radius: 1rem;
}

.bexia-scfdoc-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-scfdoc-section,
.bexia-scfdoc-field,
.bexia-scfdoc-placeholder {
    min-width: 0;
    max-width: 100%;
}

.bexia-scfdoc-field .fi-input-wrp,
.bexia-scfdoc-field .fi-select-input,
.bexia-scfdoc-field .fi-fo-select,
.bexia-scfdoc-field input,
.bexia-scfdoc-field select,
.bexia-scfdoc-field textarea,
.bexia-scfdoc-field .choices,
.bexia-scfdoc-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-scfdoc-field-uuid input,
.bexia-scfdoc-field-name input,
.bexia-scfdoc-col-company,
.bexia-scfdoc-col-uuid {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-scfdoc-mono,
.bexia-scfdoc-field-rfc input,
.bexia-scfdoc-field-uuid input,
.bexia-scfdoc-col-rfc,
.bexia-scfdoc-col-uuid {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    letter-spacing: 0.01em;
}

.bexia-scfdoc-field-money input,
.bexia-scfdoc-col-money {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.bexia-scfdoc-col-company,
.bexia-scfdoc-col-direction,
.bexia-scfdoc-col-uuid,
.bexia-scfdoc-col-type,
.bexia-scfdoc-col-status,
.bexia-scfdoc-col-rfc,
.bexia-scfdoc-col-date,
.bexia-scfdoc-col-money {
    vertical-align: top;
}

.bexia-scfdoc-col-primary {
    min-width: 11rem;
    max-width: 22rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-scfdoc-col-uuid {
    min-width: 18rem;
    max-width: 24rem;
    white-space: normal;
}

.bexia-scfdoc-col-rfc {
    min-width: 8.75rem;
    white-space: nowrap;
}

.bexia-scfdoc-col-badge,
.bexia-scfdoc-col-direction,
.bexia-scfdoc-col-type,
.bexia-scfdoc-col-status {
    min-width: 6.75rem;
    max-width: 10rem;
    white-space: normal;
}

.bexia-scfdoc-col-date {
    min-width: 8rem;
    white-space: nowrap;
}

.bexia-scfdoc-col-money {
    min-width: 7.25rem;
}

.bexia-scfdoc-placeholder-warning {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-scfdoc-section-fiscal .fi-section-content,
.bexia-scfdoc-section-parties .fi-section-content,
.bexia-scfdoc-section-amounts .fi-section-content {
    overflow-x: hidden;
}

@media (max-width: 1024px) {
    .bexia-scfdoc-section .fi-grid,
    .bexia-scfdoc-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-scfdoc-col-primary {
        max-width: 17rem;
    }

    .bexia-scfdoc-col-uuid {
        min-width: 14rem;
        max-width: 18rem;
    }
}

@media (max-width: 768px) {
    .bexia-scfdoc-section {
        border-radius: 0.85rem;
    }

    .bexia-scfdoc-section .fi-section-header,
    .bexia-scfdoc-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-scfdoc-field .fi-fo-field-wrp-label,
    .bexia-scfdoc-field label {
        font-size: 0.78rem;
    }

    .bexia-scfdoc-field input,
    .bexia-scfdoc-field select,
    .bexia-scfdoc-field textarea,
    .bexia-scfdoc-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-scfdoc-col-company,
    .bexia-scfdoc-col-direction,
    .bexia-scfdoc-col-uuid,
    .bexia-scfdoc-col-type,
    .bexia-scfdoc-col-status,
    .bexia-scfdoc-col-rfc,
    .bexia-scfdoc-col-date,
    .bexia-scfdoc-col-money {
        font-size: 0.76rem;
    }

    .bexia-scfdoc-col-primary {
        min-width: 9rem;
        max-width: 12.5rem;
    }

    .bexia-scfdoc-col-uuid {
        min-width: 11.5rem;
        max-width: 14rem;
    }

    .bexia-scfdoc-col-rfc {
        min-width: 7.25rem;
    }

    .bexia-scfdoc-col-badge,
    .bexia-scfdoc-col-direction,
    .bexia-scfdoc-col-type,
    .bexia-scfdoc-col-status {
        min-width: 5.75rem;
        max-width: 7.75rem;
    }

    .bexia-scfdoc-col-date {
        min-width: 6.75rem;
    }

    .bexia-scfdoc-col-money {
        min-width: 6rem;
    }
}

@media (max-width: 640px) {
    .bexia-scfdoc-col-primary {
        min-width: 8rem;
        max-width: 10.25rem;
    }

    .bexia-scfdoc-col-uuid {
        min-width: 10rem;
        max-width: 12rem;
    }

    .bexia-scfdoc-col-badge,
    .bexia-scfdoc-col-direction,
    .bexia-scfdoc-col-type,
    .bexia-scfdoc-col-status {
        min-width: 5.25rem;
        max-width: 6.75rem;
    }

    .bexia-scfdoc-col-money {
        min-width: 5.5rem;
    }
}
/* BEXIA_SCFDOC_RESOURCE_RESPONSIVE_V5_79_57C_END */

/* BEXIA_BSER_RESOURCE_RESPONSIVE_V5_79_58C_START */
/*
 * BillingSeriesResource responsive refinements.
 * Alcance visual solamente. No cambia logica de series, folios,
 * facturacion, CFDI/PAC, permisos, empresa ni tenant.
 */
.bexia-bser-section {
    border-radius: 1rem;
}

.bexia-bser-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-bser-section,
.bexia-bser-field,
.bexia-bser-placeholder {
    min-width: 0;
    max-width: 100%;
}

.bexia-bser-field .fi-input-wrp,
.bexia-bser-field .fi-select-input,
.bexia-bser-field .fi-fo-select,
.bexia-bser-field input,
.bexia-bser-field select,
.bexia-bser-field textarea,
.bexia-bser-field .choices,
.bexia-bser-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-bser-field-name input,
.bexia-bser-field-notes textarea,
.bexia-bser-col-company,
.bexia-bser-col-name {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-bser-mono,
.bexia-bser-field-series input,
.bexia-bser-placeholder-preview,
.bexia-bser-col-series,
.bexia-bser-col-preview {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    letter-spacing: 0.01em;
}

.bexia-bser-number-field input,
.bexia-bser-col-number {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.bexia-bser-col-company,
.bexia-bser-col-document-type,
.bexia-bser-col-name,
.bexia-bser-col-series,
.bexia-bser-col-next-number,
.bexia-bser-col-preview,
.bexia-bser-col-default,
.bexia-bser-col-active,
.bexia-bser-col-last-use {
    vertical-align: top;
}

.bexia-bser-col-primary {
    min-width: 11rem;
    max-width: 20rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-bser-col-name {
    min-width: 10rem;
    max-width: 18rem;
    white-space: normal;
}

.bexia-bser-col-document-type,
.bexia-bser-col-badge {
    min-width: 7rem;
    max-width: 10rem;
    white-space: normal;
}

.bexia-bser-col-series {
    min-width: 6rem;
    white-space: nowrap;
}

.bexia-bser-col-next-number,
.bexia-bser-col-number {
    min-width: 7rem;
}

.bexia-bser-col-preview {
    min-width: 10rem;
    max-width: 16rem;
    white-space: normal;
}

.bexia-bser-col-icon {
    width: 5rem;
    min-width: 5rem;
    text-align: center;
}

.bexia-bser-col-date {
    min-width: 8.75rem;
    white-space: nowrap;
}

.bexia-bser-placeholder-preview {
    display: block;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-bser-section-context .fi-section-content,
.bexia-bser-section-series .fi-section-content {
    overflow-x: hidden;
}

@media (max-width: 1024px) {
    .bexia-bser-section .fi-grid,
    .bexia-bser-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-bser-col-primary {
        max-width: 16rem;
    }

    .bexia-bser-col-preview {
        min-width: 8.5rem;
        max-width: 13rem;
    }
}

@media (max-width: 768px) {
    .bexia-bser-section {
        border-radius: 0.85rem;
    }

    .bexia-bser-section .fi-section-header,
    .bexia-bser-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-bser-field .fi-fo-field-wrp-label,
    .bexia-bser-field label {
        font-size: 0.78rem;
    }

    .bexia-bser-field input,
    .bexia-bser-field select,
    .bexia-bser-field textarea,
    .bexia-bser-field .choices__inner,
    .bexia-bser-placeholder {
        font-size: 0.82rem;
    }

    .bexia-bser-col-company,
    .bexia-bser-col-document-type,
    .bexia-bser-col-name,
    .bexia-bser-col-series,
    .bexia-bser-col-next-number,
    .bexia-bser-col-preview,
    .bexia-bser-col-default,
    .bexia-bser-col-active,
    .bexia-bser-col-last-use {
        font-size: 0.76rem;
    }

    .bexia-bser-col-primary {
        min-width: 9rem;
        max-width: 12.5rem;
    }

    .bexia-bser-col-name {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-bser-col-document-type,
    .bexia-bser-col-badge {
        min-width: 5.75rem;
        max-width: 7.75rem;
    }

    .bexia-bser-col-series {
        min-width: 5rem;
    }

    .bexia-bser-col-next-number,
    .bexia-bser-col-number {
        min-width: 5.75rem;
    }

    .bexia-bser-col-preview {
        min-width: 7.5rem;
        max-width: 10rem;
    }

    .bexia-bser-col-icon {
        width: 4.25rem;
        min-width: 4.25rem;
    }

    .bexia-bser-col-date {
        min-width: 7rem;
    }
}

@media (max-width: 640px) {
    .bexia-bser-col-primary {
        min-width: 8rem;
        max-width: 10.25rem;
    }

    .bexia-bser-col-name {
        min-width: 7.25rem;
        max-width: 9.25rem;
    }

    .bexia-bser-col-preview {
        min-width: 6.75rem;
        max-width: 8.5rem;
    }

    .bexia-bser-col-icon {
        width: 3.75rem;
        min-width: 3.75rem;
    }
}
/* BEXIA_BSER_RESOURCE_RESPONSIVE_V5_79_58C_END */

/* BEXIA_PPOL_RESOURCE_RESPONSIVE_V5_79_59C_START */
/*
 * PayrollPolicyResource responsive refinements.
 * Alcance visual solamente. No cambia logica de politicas de nomina,
 * calculos, percepciones, deducciones, ISR/IMSS/subsidio, permisos,
 * empresa ni tenant.
 */
.bexia-ppol-section {
    border-radius: 1rem;
}

.bexia-ppol-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-ppol-section,
.bexia-ppol-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-ppol-field .fi-input-wrp,
.bexia-ppol-field .fi-select-input,
.bexia-ppol-field .fi-fo-select,
.bexia-ppol-field input,
.bexia-ppol-field select,
.bexia-ppol-field textarea,
.bexia-ppol-field .choices,
.bexia-ppol-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-ppol-field-name input,
.bexia-ppol-field-notes textarea,
.bexia-ppol-col-company,
.bexia-ppol-col-name,
.bexia-ppol-col-mode {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-ppol-number-field input,
.bexia-ppol-col-number {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.bexia-ppol-col-active,
.bexia-ppol-col-company,
.bexia-ppol-col-name,
.bexia-ppol-col-status,
.bexia-ppol-col-overtime,
.bexia-ppol-col-late-tolerance,
.bexia-ppol-col-late-mode,
.bexia-ppol-col-absence-mode {
    vertical-align: top;
}

.bexia-ppol-col-primary {
    min-width: 11rem;
    max-width: 18rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-ppol-col-name {
    min-width: 11rem;
    max-width: 18rem;
    white-space: normal;
}

.bexia-ppol-col-status,
.bexia-ppol-col-badge {
    min-width: 7rem;
    max-width: 10rem;
    white-space: normal;
}

.bexia-ppol-col-number {
    min-width: 7rem;
}

.bexia-ppol-col-mode {
    min-width: 9rem;
    max-width: 14rem;
    white-space: normal;
}

.bexia-ppol-col-icon {
    width: 5rem;
    min-width: 5rem;
    text-align: center;
}

.bexia-ppol-section-general .fi-section-content,
.bexia-ppol-section-overtime .fi-section-content,
.bexia-ppol-section-attendance .fi-section-content {
    overflow-x: hidden;
}

@media (max-width: 1024px) {
    .bexia-ppol-section .fi-grid,
    .bexia-ppol-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-ppol-col-primary,
    .bexia-ppol-col-name {
        max-width: 15rem;
    }

    .bexia-ppol-col-mode {
        min-width: 8rem;
        max-width: 12rem;
    }
}

@media (max-width: 768px) {
    .bexia-ppol-section {
        border-radius: 0.85rem;
    }

    .bexia-ppol-section .fi-section-header,
    .bexia-ppol-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-ppol-field .fi-fo-field-wrp-label,
    .bexia-ppol-field label {
        font-size: 0.78rem;
    }

    .bexia-ppol-field input,
    .bexia-ppol-field select,
    .bexia-ppol-field textarea,
    .bexia-ppol-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-ppol-col-active,
    .bexia-ppol-col-company,
    .bexia-ppol-col-name,
    .bexia-ppol-col-status,
    .bexia-ppol-col-overtime,
    .bexia-ppol-col-late-tolerance,
    .bexia-ppol-col-late-mode,
    .bexia-ppol-col-absence-mode {
        font-size: 0.76rem;
    }

    .bexia-ppol-col-primary,
    .bexia-ppol-col-name {
        min-width: 8.5rem;
        max-width: 11.5rem;
    }

    .bexia-ppol-col-status,
    .bexia-ppol-col-badge {
        min-width: 5.75rem;
        max-width: 7.75rem;
    }

    .bexia-ppol-col-number {
        min-width: 5.75rem;
    }

    .bexia-ppol-col-mode {
        min-width: 7rem;
        max-width: 9.75rem;
    }

    .bexia-ppol-col-icon {
        width: 4.25rem;
        min-width: 4.25rem;
    }
}

@media (max-width: 640px) {
    .bexia-ppol-col-primary,
    .bexia-ppol-col-name {
        min-width: 7.5rem;
        max-width: 9.5rem;
    }

    .bexia-ppol-col-mode {
        min-width: 6.5rem;
        max-width: 8.25rem;
    }

    .bexia-ppol-col-icon {
        width: 3.75rem;
        min-width: 3.75rem;
    }
}
/* BEXIA_PPOL_RESOURCE_RESPONSIVE_V5_79_59C_END */

/* BEXIA_TBAC_RESOURCE_RESPONSIVE_V5_79_60C_START */
/*
 * TreasuryBankAccountResource responsive refinements.
 * Alcance visual solamente. No cambia logica de tesoreria, bancos,
 * cuentas, saldos, movimientos, conciliacion, permisos, empresa ni tenant.
 */
.bexia-tbac-section {
    border-radius: 1rem;
}

.bexia-tbac-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-tbac-section,
.bexia-tbac-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-tbac-field .fi-input-wrp,
.bexia-tbac-field .fi-select-input,
.bexia-tbac-field .fi-fo-select,
.bexia-tbac-field input,
.bexia-tbac-field select,
.bexia-tbac-field textarea,
.bexia-tbac-field .choices,
.bexia-tbac-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-tbac-field-name input,
.bexia-tbac-field-bank,
.bexia-tbac-field-account-number input,
.bexia-tbac-field-clabe input,
.bexia-tbac-field-notes textarea,
.bexia-tbac-col-name,
.bexia-tbac-col-company,
.bexia-tbac-col-bank,
.bexia-tbac-col-account-number,
.bexia-tbac-col-clabe {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-tbac-code-field input,
.bexia-tbac-col-code {
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-tbac-money-field input,
.bexia-tbac-col-money {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.bexia-tbac-col-name,
.bexia-tbac-col-company,
.bexia-tbac-col-bank,
.bexia-tbac-col-account-number,
.bexia-tbac-col-clabe,
.bexia-tbac-col-current-balance,
.bexia-tbac-col-default-concentrator,
.bexia-tbac-col-active {
    vertical-align: top;
}

.bexia-tbac-col-primary {
    min-width: 11rem;
    max-width: 18rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-tbac-col-context {
    min-width: 9rem;
    max-width: 14rem;
    white-space: normal;
}

.bexia-tbac-col-code {
    min-width: 9rem;
    max-width: 13rem;
    white-space: normal;
}

.bexia-tbac-col-money {
    min-width: 8rem;
}

.bexia-tbac-col-icon {
    width: 5rem;
    min-width: 5rem;
    text-align: center;
}

.bexia-tbac-section-main .fi-section-content,
.bexia-tbac-section-balances .fi-section-content,
.bexia-tbac-section-notes .fi-section-content {
    overflow-x: hidden;
}

@media (max-width: 1024px) {
    .bexia-tbac-section .fi-grid,
    .bexia-tbac-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-tbac-col-primary {
        max-width: 15rem;
    }

    .bexia-tbac-col-context,
    .bexia-tbac-col-code {
        max-width: 12rem;
    }
}

@media (max-width: 768px) {
    .bexia-tbac-section {
        border-radius: 0.85rem;
    }

    .bexia-tbac-section .fi-section-header,
    .bexia-tbac-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-tbac-field .fi-fo-field-wrp-label,
    .bexia-tbac-field label {
        font-size: 0.78rem;
    }

    .bexia-tbac-field input,
    .bexia-tbac-field select,
    .bexia-tbac-field textarea,
    .bexia-tbac-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-tbac-col-name,
    .bexia-tbac-col-company,
    .bexia-tbac-col-bank,
    .bexia-tbac-col-account-number,
    .bexia-tbac-col-clabe,
    .bexia-tbac-col-current-balance,
    .bexia-tbac-col-default-concentrator,
    .bexia-tbac-col-active {
        font-size: 0.76rem;
    }

    .bexia-tbac-col-primary {
        min-width: 8.5rem;
        max-width: 11.5rem;
    }

    .bexia-tbac-col-context {
        min-width: 7rem;
        max-width: 9.75rem;
    }

    .bexia-tbac-col-code {
        min-width: 7.5rem;
        max-width: 10.25rem;
    }

    .bexia-tbac-col-money {
        min-width: 6.75rem;
    }

    .bexia-tbac-col-icon {
        width: 4.25rem;
        min-width: 4.25rem;
    }
}

@media (max-width: 640px) {
    .bexia-tbac-col-primary {
        min-width: 7.5rem;
        max-width: 9.5rem;
    }

    .bexia-tbac-col-context,
    .bexia-tbac-col-code {
        min-width: 6.5rem;
        max-width: 8.25rem;
    }

    .bexia-tbac-col-money {
        min-width: 6.25rem;
    }

    .bexia-tbac-col-icon {
        width: 3.75rem;
        min-width: 3.75rem;
    }
}
/* BEXIA_TBAC_RESOURCE_RESPONSIVE_V5_79_60C_END */

/* BEXIA_SLOT_RESOURCE_RESPONSIVE_V5_79_61C_START */
/*
 * StockLotResource responsive refinements.
 * Alcance visual solamente. No cambia logica de lotes, inventario,
 * productos, series, ubicaciones, cantidades, trazabilidad, QR/PDF/print,
 * permisos, empresa ni tenant.
 */
.bexia-slot-section {
    border-radius: 1rem;
}

.bexia-slot-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-slot-section,
.bexia-slot-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-slot-field .fi-input-wrp,
.bexia-slot-field .fi-select-input,
.bexia-slot-field .fi-fo-select,
.bexia-slot-field input,
.bexia-slot-field select,
.bexia-slot-field .choices,
.bexia-slot-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-slot-field-product,
.bexia-slot-field-variant,
.bexia-slot-field-supplier,
.bexia-slot-field-lot-number input,
.bexia-slot-col-lot-number,
.bexia-slot-col-product,
.bexia-slot-col-variant {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-slot-code-field input,
.bexia-slot-col-code {
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-slot-col-lot-number,
.bexia-slot-col-product,
.bexia-slot-col-variant,
.bexia-slot-col-expiration,
.bexia-slot-col-series,
.bexia-slot-col-status,
.bexia-slot-col-created {
    vertical-align: top;
}

.bexia-slot-col-primary {
    min-width: 10rem;
    max-width: 16rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-slot-col-context {
    min-width: 10rem;
    max-width: 16rem;
    white-space: normal;
}

.bexia-slot-col-date {
    min-width: 8rem;
    max-width: 10rem;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.bexia-slot-col-number {
    min-width: 6rem;
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.bexia-slot-col-badge {
    min-width: 7rem;
    max-width: 9rem;
    white-space: normal;
}

.bexia-slot-section-main .fi-section-content {
    overflow-x: hidden;
}

@media (max-width: 1024px) {
    .bexia-slot-section .fi-grid,
    .bexia-slot-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-slot-col-primary,
    .bexia-slot-col-context {
        max-width: 13rem;
    }

    .bexia-slot-col-date {
        min-width: 7.25rem;
    }
}

@media (max-width: 768px) {
    .bexia-slot-section {
        border-radius: 0.85rem;
    }

    .bexia-slot-section .fi-section-header,
    .bexia-slot-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-slot-field .fi-fo-field-wrp-label,
    .bexia-slot-field label {
        font-size: 0.78rem;
    }

    .bexia-slot-field input,
    .bexia-slot-field select,
    .bexia-slot-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-slot-col-lot-number,
    .bexia-slot-col-product,
    .bexia-slot-col-variant,
    .bexia-slot-col-expiration,
    .bexia-slot-col-series,
    .bexia-slot-col-status,
    .bexia-slot-col-created {
        font-size: 0.76rem;
    }

    .bexia-slot-col-primary,
    .bexia-slot-col-context {
        min-width: 8rem;
        max-width: 10.5rem;
    }

    .bexia-slot-col-date {
        min-width: 6.75rem;
        max-width: 8.5rem;
    }

    .bexia-slot-col-number {
        min-width: 5rem;
    }

    .bexia-slot-col-badge {
        min-width: 5.75rem;
        max-width: 7.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-slot-col-primary,
    .bexia-slot-col-context {
        min-width: 7rem;
        max-width: 9rem;
    }

    .bexia-slot-col-date {
        min-width: 6rem;
        max-width: 7.5rem;
    }

    .bexia-slot-col-number {
        min-width: 4.5rem;
    }
}
/* BEXIA_SLOT_RESOURCE_RESPONSIVE_V5_79_61C_END */

/* BEXIA_SOPT_RESOURCE_RESPONSIVE_V5_79_62C_START */
/*
 * StockOperationTypeResource responsive refinements.
 * Alcance visual solamente. No cambia logica de tipos de operacion,
 * inventario, movimientos, entradas/salidas, ajustes, transferencias,
 * almacenes, ubicaciones, permisos, empresa ni tenant.
 */
.bexia-sopt-section {
    border-radius: 1rem;
}

.bexia-sopt-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-sopt-section,
.bexia-sopt-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-sopt-field .fi-input-wrp,
.bexia-sopt-field .fi-select-input,
.bexia-sopt-field .fi-fo-select,
.bexia-sopt-field input,
.bexia-sopt-field select,
.bexia-sopt-field textarea,
.bexia-sopt-field .choices,
.bexia-sopt-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-sopt-field-warehouse,
.bexia-sopt-field-operation-kind,
.bexia-sopt-field-source-location,
.bexia-sopt-field-destination-location,
.bexia-sopt-field-name input,
.bexia-sopt-field-description textarea,
.bexia-sopt-col-code,
.bexia-sopt-col-name,
.bexia-sopt-col-warehouse,
.bexia-sopt-col-source-location,
.bexia-sopt-col-destination-location {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-sopt-code-field input,
.bexia-sopt-col-code-text {
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-sopt-number-field input,
.bexia-sopt-col-number {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.bexia-sopt-col-code,
.bexia-sopt-col-name,
.bexia-sopt-col-operation-kind,
.bexia-sopt-col-warehouse,
.bexia-sopt-col-source-location,
.bexia-sopt-col-destination-location,
.bexia-sopt-col-reference-prefix,
.bexia-sopt-col-next-number,
.bexia-sopt-col-sequence,
.bexia-sopt-col-active {
    vertical-align: top;
}

.bexia-sopt-col-primary {
    min-width: 7.5rem;
    max-width: 11rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-sopt-col-primary-text {
    min-width: 11rem;
    max-width: 18rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-sopt-col-context {
    min-width: 9rem;
    max-width: 14rem;
    white-space: normal;
}

.bexia-sopt-col-code-text {
    min-width: 7rem;
    max-width: 10rem;
    white-space: normal;
}

.bexia-sopt-col-badge {
    min-width: 7rem;
    max-width: 10rem;
    white-space: normal;
}

.bexia-sopt-col-number {
    min-width: 5.75rem;
}

.bexia-sopt-col-icon {
    width: 5rem;
    min-width: 5rem;
    text-align: center;
}

.bexia-sopt-section-main .fi-section-content {
    overflow-x: hidden;
}

@media (max-width: 1024px) {
    .bexia-sopt-section .fi-grid,
    .bexia-sopt-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-sopt-col-primary-text {
        max-width: 15rem;
    }

    .bexia-sopt-col-context {
        max-width: 12rem;
    }
}

@media (max-width: 768px) {
    .bexia-sopt-section {
        border-radius: 0.85rem;
    }

    .bexia-sopt-section .fi-section-header,
    .bexia-sopt-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-sopt-field .fi-fo-field-wrp-label,
    .bexia-sopt-field label {
        font-size: 0.78rem;
    }

    .bexia-sopt-field input,
    .bexia-sopt-field select,
    .bexia-sopt-field textarea,
    .bexia-sopt-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-sopt-col-code,
    .bexia-sopt-col-name,
    .bexia-sopt-col-operation-kind,
    .bexia-sopt-col-warehouse,
    .bexia-sopt-col-source-location,
    .bexia-sopt-col-destination-location,
    .bexia-sopt-col-reference-prefix,
    .bexia-sopt-col-next-number,
    .bexia-sopt-col-sequence,
    .bexia-sopt-col-active {
        font-size: 0.76rem;
    }

    .bexia-sopt-col-primary {
        min-width: 6.25rem;
        max-width: 8.5rem;
    }

    .bexia-sopt-col-primary-text {
        min-width: 8.5rem;
        max-width: 11.5rem;
    }

    .bexia-sopt-col-context {
        min-width: 7rem;
        max-width: 9.75rem;
    }

    .bexia-sopt-col-code-text {
        min-width: 6rem;
        max-width: 8rem;
    }

    .bexia-sopt-col-badge {
        min-width: 6rem;
        max-width: 8rem;
    }

    .bexia-sopt-col-number {
        min-width: 5rem;
    }

    .bexia-sopt-col-icon {
        width: 4.25rem;
        min-width: 4.25rem;
    }
}

@media (max-width: 640px) {
    .bexia-sopt-col-primary {
        min-width: 5.75rem;
        max-width: 7.25rem;
    }

    .bexia-sopt-col-primary-text {
        min-width: 7.25rem;
        max-width: 9.5rem;
    }

    .bexia-sopt-col-context,
    .bexia-sopt-col-code-text,
    .bexia-sopt-col-badge {
        min-width: 6rem;
        max-width: 7.75rem;
    }

    .bexia-sopt-col-number {
        min-width: 4.5rem;
    }

    .bexia-sopt-col-icon {
        width: 3.75rem;
        min-width: 3.75rem;
    }
}
/* BEXIA_SOPT_RESOURCE_RESPONSIVE_V5_79_62C_END */

/* BEXIA_PAYROLL_CONCEPT_RESOURCE_RESPONSIVE_V5_79_63C_START */
/*
 * PayrollConceptResource responsive refinements.
 * Alcance visual solamente. No cambia logica de conceptos de nomina,
 * percepciones/deducciones, SAT/CFDI, ISR/IMSS, formulas, montos,
 * cuentas contables, permisos, empresa ni tenant.
 */
.bexia-payroll-concept-section {
    border-radius: 1rem;
}

.bexia-payroll-concept-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-payroll-concept-section,
.bexia-payroll-concept-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-payroll-concept-field .fi-input-wrp,
.bexia-payroll-concept-field .fi-select-input,
.bexia-payroll-concept-field .fi-fo-select,
.bexia-payroll-concept-field input,
.bexia-payroll-concept-field select,
.bexia-payroll-concept-field textarea,
.bexia-payroll-concept-field .choices,
.bexia-payroll-concept-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-payroll-concept-field-company,
.bexia-payroll-concept-field-type,
.bexia-payroll-concept-field-category,
.bexia-payroll-concept-field-source,
.bexia-payroll-concept-field-unit,
.bexia-payroll-concept-field-sat-key,
.bexia-payroll-concept-field-name input,
.bexia-payroll-concept-field-notes textarea,
.bexia-payroll-concept-col-company,
.bexia-payroll-concept-col-code,
.bexia-payroll-concept-col-name,
.bexia-payroll-concept-col-source,
.bexia-payroll-concept-col-unit,
.bexia-payroll-concept-col-sat-key {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-payroll-concept-code-field input,
.bexia-payroll-concept-col-code-text {
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-payroll-concept-money-field input,
.bexia-payroll-concept-number-field input,
.bexia-payroll-concept-col-number {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.bexia-payroll-concept-col-active,
.bexia-payroll-concept-col-company,
.bexia-payroll-concept-col-code,
.bexia-payroll-concept-col-name,
.bexia-payroll-concept-col-type,
.bexia-payroll-concept-col-category,
.bexia-payroll-concept-col-source,
.bexia-payroll-concept-col-unit,
.bexia-payroll-concept-col-sat-key,
.bexia-payroll-concept-col-taxable,
.bexia-payroll-concept-col-sort-order {
    vertical-align: top;
}

.bexia-payroll-concept-col-primary {
    min-width: 7.5rem;
    max-width: 11rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-payroll-concept-col-primary-text {
    min-width: 11rem;
    max-width: 18rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-payroll-concept-col-context {
    min-width: 8rem;
    max-width: 13rem;
    white-space: normal;
}

.bexia-payroll-concept-col-code-text {
    min-width: 7rem;
    max-width: 10.5rem;
    white-space: normal;
}

.bexia-payroll-concept-col-badge {
    min-width: 7rem;
    max-width: 10rem;
    white-space: normal;
}

.bexia-payroll-concept-col-number {
    min-width: 5.75rem;
}

.bexia-payroll-concept-col-icon {
    width: 5rem;
    min-width: 5rem;
    text-align: center;
}

.bexia-payroll-concept-section-main .fi-section-content {
    overflow-x: hidden;
}

@media (max-width: 1024px) {
    .bexia-payroll-concept-section .fi-grid,
    .bexia-payroll-concept-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-payroll-concept-col-primary-text {
        max-width: 15rem;
    }

    .bexia-payroll-concept-col-context {
        max-width: 11.5rem;
    }
}

@media (max-width: 768px) {
    .bexia-payroll-concept-section {
        border-radius: 0.85rem;
    }

    .bexia-payroll-concept-section .fi-section-header,
    .bexia-payroll-concept-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-payroll-concept-field .fi-fo-field-wrp-label,
    .bexia-payroll-concept-field label {
        font-size: 0.78rem;
    }

    .bexia-payroll-concept-field input,
    .bexia-payroll-concept-field select,
    .bexia-payroll-concept-field textarea,
    .bexia-payroll-concept-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-payroll-concept-col-active,
    .bexia-payroll-concept-col-company,
    .bexia-payroll-concept-col-code,
    .bexia-payroll-concept-col-name,
    .bexia-payroll-concept-col-type,
    .bexia-payroll-concept-col-category,
    .bexia-payroll-concept-col-source,
    .bexia-payroll-concept-col-unit,
    .bexia-payroll-concept-col-sat-key,
    .bexia-payroll-concept-col-taxable,
    .bexia-payroll-concept-col-sort-order {
        font-size: 0.76rem;
    }

    .bexia-payroll-concept-col-primary {
        min-width: 6.25rem;
        max-width: 8.5rem;
    }

    .bexia-payroll-concept-col-primary-text {
        min-width: 8.5rem;
        max-width: 11.5rem;
    }

    .bexia-payroll-concept-col-context {
        min-width: 6.75rem;
        max-width: 9.5rem;
    }

    .bexia-payroll-concept-col-code-text {
        min-width: 6rem;
        max-width: 8rem;
    }

    .bexia-payroll-concept-col-badge {
        min-width: 6rem;
        max-width: 8rem;
    }

    .bexia-payroll-concept-col-number {
        min-width: 5rem;
    }

    .bexia-payroll-concept-col-icon {
        width: 4.25rem;
        min-width: 4.25rem;
    }
}

@media (max-width: 640px) {
    .bexia-payroll-concept-col-primary {
        min-width: 5.75rem;
        max-width: 7.25rem;
    }

    .bexia-payroll-concept-col-primary-text {
        min-width: 7.25rem;
        max-width: 9.5rem;
    }

    .bexia-payroll-concept-col-context,
    .bexia-payroll-concept-col-code-text,
    .bexia-payroll-concept-col-badge {
        min-width: 6rem;
        max-width: 7.75rem;
    }

    .bexia-payroll-concept-col-number {
        min-width: 4.5rem;
    }

    .bexia-payroll-concept-col-icon {
        width: 3.75rem;
        min-width: 3.75rem;
    }
}
/* BEXIA_PAYROLL_CONCEPT_RESOURCE_RESPONSIVE_V5_79_63C_END */

/* BEXIA_BRANCH_RESOURCE_RESPONSIVE_V5_79_64C_START */
/*
 * BranchResource responsive refinements.
 * Alcance visual solamente. No cambia logica de sucursales, empresas,
 * tenant, direcciones, almacenes/ubicaciones, POS, fiscal/SAT,
 * permisos ni relaciones.
 */
.bexia-branch-section {
    border-radius: 1rem;
}

.bexia-branch-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-branch-section,
.bexia-branch-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-branch-section .fi-grid,
.bexia-branch-section .grid,
.bexia-branch-section .fi-fo-component-ctn {
    min-width: 0;
}

.bexia-branch-field .fi-input-wrp,
.bexia-branch-field .fi-select-input,
.bexia-branch-field input,
.bexia-branch-field select,
.bexia-branch-field textarea,
.bexia-branch-field .choices,
.bexia-branch-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-branch-field-name input,
.bexia-branch-field-address-line1 input,
.bexia-branch-field-address-line2 input,
.bexia-branch-field-city input,
.bexia-branch-field-state input,
.bexia-branch-field-country input,
.bexia-branch-field-contact-name input,
.bexia-branch-field-contact-email input,
.bexia-branch-field-notes textarea,
.bexia-branch-col-name,
.bexia-branch-col-code,
.bexia-branch-col-city,
.bexia-branch-col-contact-name {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-branch-code-field input,
.bexia-branch-phone-field input,
.bexia-branch-email-field input,
.bexia-branch-col-code-text {
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-branch-col-id,
.bexia-branch-col-name,
.bexia-branch-col-code,
.bexia-branch-col-city,
.bexia-branch-col-contact-name,
.bexia-branch-col-active,
.bexia-branch-col-created-at {
    vertical-align: top;
}

.bexia-branch-col-number {
    min-width: 4.5rem;
    white-space: nowrap;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.bexia-branch-col-primary-text {
    min-width: 11rem;
    max-width: 18rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-branch-col-code-text {
    min-width: 7rem;
    max-width: 10rem;
    white-space: normal;
}

.bexia-branch-col-context {
    min-width: 8rem;
    max-width: 13rem;
    white-space: normal;
}

.bexia-branch-col-icon {
    width: 4.75rem;
    min-width: 4.75rem;
    text-align: center;
}

.bexia-branch-col-date {
    min-width: 8.5rem;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

@media (max-width: 1024px) {
    .bexia-branch-section .fi-grid,
    .bexia-branch-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-branch-col-primary-text {
        max-width: 15rem;
    }

    .bexia-branch-col-context {
        max-width: 11.5rem;
    }
}

@media (max-width: 768px) {
    .bexia-branch-section {
        border-radius: 0.85rem;
    }

    .bexia-branch-section .fi-section-header,
    .bexia-branch-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-branch-field .fi-fo-field-wrp-label,
    .bexia-branch-field label {
        font-size: 0.78rem;
    }

    .bexia-branch-field input,
    .bexia-branch-field select,
    .bexia-branch-field textarea,
    .bexia-branch-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-branch-col-id,
    .bexia-branch-col-name,
    .bexia-branch-col-code,
    .bexia-branch-col-city,
    .bexia-branch-col-contact-name,
    .bexia-branch-col-active,
    .bexia-branch-col-created-at {
        font-size: 0.76rem;
    }

    .bexia-branch-col-number {
        min-width: 3.75rem;
    }

    .bexia-branch-col-primary-text {
        min-width: 8.5rem;
        max-width: 11.5rem;
    }

    .bexia-branch-col-code-text {
        min-width: 5.75rem;
        max-width: 7.75rem;
    }

    .bexia-branch-col-context {
        min-width: 6.75rem;
        max-width: 9.5rem;
    }

    .bexia-branch-col-icon {
        width: 4rem;
        min-width: 4rem;
    }

    .bexia-branch-col-date {
        min-width: 7.25rem;
    }
}

@media (max-width: 640px) {
    .bexia-branch-col-primary-text {
        min-width: 7rem;
        max-width: 9.25rem;
    }

    .bexia-branch-col-code-text,
    .bexia-branch-col-context {
        min-width: 5.75rem;
        max-width: 7.5rem;
    }

    .bexia-branch-col-icon {
        width: 3.5rem;
        min-width: 3.5rem;
    }

    .bexia-branch-col-date {
        min-width: 6.75rem;
    }
}
/* BEXIA_BRANCH_RESOURCE_RESPONSIVE_V5_79_64C_END */

/* BEXIA_PURCHASE_ORDER_RESOURCE_RESPONSIVE_V5_79_65C_START */
/*
 * PurchaseOrderResource responsive refinements.
 * Alcance visual solamente. No cambia logica de ordenes de compra,
 * proveedores, recepciones, XML/SAT/CFDI, productos, inventario,
 * almacenes, costos, impuestos, totales, contabilidad, permisos,
 * empresa ni tenant.
 */
.bexia-po-section {
    border-radius: 1rem;
}

.bexia-po-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-po-section,
.bexia-po-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-po-section .fi-grid,
.bexia-po-section .grid,
.bexia-po-section .fi-fo-component-ctn {
    min-width: 0;
}

.bexia-po-field .fi-input-wrp,
.bexia-po-field .fi-select-input,
.bexia-po-field .fi-fo-select,
.bexia-po-field input,
.bexia-po-field select,
.bexia-po-field textarea,
.bexia-po-field .choices,
.bexia-po-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-po-field-number input,
.bexia-po-field-supplier input,
.bexia-po-field-status input,
.bexia-po-field-origin input,
.bexia-po-field-warehouse input,
.bexia-po-field-location input,
.bexia-po-field-notes textarea,
.bexia-po-col-number,
.bexia-po-col-supplier,
.bexia-po-col-origin {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-po-code-field input,
.bexia-po-date-field input,
.bexia-po-col-code,
.bexia-po-col-date,
.bexia-po-col-money {
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-po-col-number,
.bexia-po-col-status,
.bexia-po-col-differs,
.bexia-po-col-supplier,
.bexia-po-col-origin,
.bexia-po-col-total,
.bexia-po-col-order-date {
    vertical-align: top;
}

.bexia-po-col-primary {
    min-width: 8rem;
    max-width: 11rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-po-col-primary-text {
    min-width: 11rem;
    max-width: 18rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-po-col-context {
    min-width: 8rem;
    max-width: 13rem;
    white-space: normal;
}

.bexia-po-col-badge {
    min-width: 7.5rem;
    max-width: 10.5rem;
    white-space: normal;
}

.bexia-po-col-icon {
    width: 5.25rem;
    min-width: 5.25rem;
    text-align: center;
}

.bexia-po-col-money {
    min-width: 7.5rem;
    white-space: nowrap;
    text-align: right;
}

.bexia-po-col-date {
    min-width: 8.5rem;
    white-space: nowrap;
}

.bexia-po-section-products .fi-section-content,
.bexia-po-section-history .fi-section-content {
    overflow-x: auto;
}

@media (max-width: 1024px) {
    .bexia-po-section .fi-grid,
    .bexia-po-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-po-col-primary-text {
        max-width: 15rem;
    }

    .bexia-po-col-context {
        max-width: 11.5rem;
    }
}

@media (max-width: 768px) {
    .bexia-po-section {
        border-radius: 0.85rem;
    }

    .bexia-po-section .fi-section-header,
    .bexia-po-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-po-field .fi-fo-field-wrp-label,
    .bexia-po-field label {
        font-size: 0.78rem;
    }

    .bexia-po-field input,
    .bexia-po-field select,
    .bexia-po-field textarea,
    .bexia-po-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-po-col-number,
    .bexia-po-col-status,
    .bexia-po-col-differs,
    .bexia-po-col-supplier,
    .bexia-po-col-origin,
    .bexia-po-col-total,
    .bexia-po-col-order-date {
        font-size: 0.76rem;
    }

    .bexia-po-col-primary {
        min-width: 6.75rem;
        max-width: 8.75rem;
    }

    .bexia-po-col-primary-text {
        min-width: 8.5rem;
        max-width: 11.5rem;
    }

    .bexia-po-col-context {
        min-width: 6.75rem;
        max-width: 9.5rem;
    }

    .bexia-po-col-badge {
        min-width: 6.25rem;
        max-width: 8.25rem;
    }

    .bexia-po-col-icon {
        width: 4.25rem;
        min-width: 4.25rem;
    }

    .bexia-po-col-money {
        min-width: 6.25rem;
    }

    .bexia-po-col-date {
        min-width: 7.25rem;
    }
}

@media (max-width: 640px) {
    .bexia-po-col-primary {
        min-width: 6rem;
        max-width: 7.75rem;
    }

    .bexia-po-col-primary-text {
        min-width: 7.25rem;
        max-width: 9.5rem;
    }

    .bexia-po-col-context,
    .bexia-po-col-badge {
        min-width: 5.75rem;
        max-width: 7.75rem;
    }

    .bexia-po-col-icon {
        width: 3.75rem;
        min-width: 3.75rem;
    }

    .bexia-po-col-money {
        min-width: 5.75rem;
    }

    .bexia-po-col-date {
        min-width: 6.75rem;
    }
}
/* BEXIA_PURCHASE_ORDER_RESOURCE_RESPONSIVE_V5_79_65C_END */

/* BEXIA_EMPLOYEE_VACATION_BALANCE_RESOURCE_RESPONSIVE_V5_79_66C_START */
/*
 * EmployeeVacationBalanceResource responsive refinements.
 * Alcance visual solamente. No cambia logica de empleados, vacaciones,
 * saldos, dias devengados/tomados/disponibles, antiguedad, contratos,
 * incidencias, nomina, aprobaciones, permisos, empresa ni tenant.
 */
.bexia-evb-section {
    border-radius: 1rem;
}

.bexia-evb-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-evb-section,
.bexia-evb-grid,
.bexia-evb-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-evb-section .fi-grid,
.bexia-evb-section .grid,
.bexia-evb-grid,
.bexia-evb-section .fi-fo-component-ctn {
    min-width: 0;
}

.bexia-evb-field .fi-input-wrp,
.bexia-evb-field .fi-select-input,
.bexia-evb-field .fi-fo-select,
.bexia-evb-field input,
.bexia-evb-field select,
.bexia-evb-field textarea,
.bexia-evb-field .choices,
.bexia-evb-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-evb-field-employee,
.bexia-evb-field-status,
.bexia-evb-field-policy input,
.bexia-evb-field-notes textarea,
.bexia-evb-col-employee,
.bexia-evb-col-policy,
.bexia-evb-col-status {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-evb-number-field input,
.bexia-evb-days-field input,
.bexia-evb-date-field input,
.bexia-evb-col-number,
.bexia-evb-col-days,
.bexia-evb-col-date {
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-evb-col-employee,
.bexia-evb-col-period-start,
.bexia-evb-col-period-end,
.bexia-evb-col-years,
.bexia-evb-col-entitled,
.bexia-evb-col-taken,
.bexia-evb-col-pending,
.bexia-evb-col-status,
.bexia-evb-col-policy {
    vertical-align: top;
}

.bexia-evb-col-primary {
    min-width: 11rem;
    max-width: 18rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-evb-col-date {
    min-width: 7.5rem;
    white-space: nowrap;
}

.bexia-evb-col-number {
    min-width: 6rem;
    white-space: nowrap;
    text-align: right;
}

.bexia-evb-col-days {
    min-width: 6.25rem;
    white-space: nowrap;
    text-align: right;
}

.bexia-evb-col-important {
    font-weight: 700;
}

.bexia-evb-col-badge {
    min-width: 7rem;
    max-width: 10rem;
    white-space: normal;
}

.bexia-evb-col-code {
    min-width: 7rem;
    max-width: 10rem;
    white-space: normal;
    font-variant-numeric: tabular-nums;
}

@media (max-width: 1024px) {
    .bexia-evb-section .fi-grid,
    .bexia-evb-section .grid,
    .bexia-evb-grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-evb-col-primary {
        max-width: 15rem;
    }
}

@media (max-width: 768px) {
    .bexia-evb-section {
        border-radius: 0.85rem;
    }

    .bexia-evb-section .fi-section-header,
    .bexia-evb-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-evb-field .fi-fo-field-wrp-label,
    .bexia-evb-field label {
        font-size: 0.78rem;
    }

    .bexia-evb-field input,
    .bexia-evb-field select,
    .bexia-evb-field textarea,
    .bexia-evb-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-evb-col-employee,
    .bexia-evb-col-period-start,
    .bexia-evb-col-period-end,
    .bexia-evb-col-years,
    .bexia-evb-col-entitled,
    .bexia-evb-col-taken,
    .bexia-evb-col-pending,
    .bexia-evb-col-status,
    .bexia-evb-col-policy {
        font-size: 0.76rem;
    }

    .bexia-evb-col-primary {
        min-width: 8.5rem;
        max-width: 11.5rem;
    }

    .bexia-evb-col-date {
        min-width: 6.75rem;
    }

    .bexia-evb-col-number,
    .bexia-evb-col-days {
        min-width: 5.5rem;
    }

    .bexia-evb-col-badge,
    .bexia-evb-col-code {
        min-width: 5.75rem;
        max-width: 7.75rem;
    }
}

@media (max-width: 640px) {
    .bexia-evb-col-primary {
        min-width: 7.25rem;
        max-width: 9.5rem;
    }

    .bexia-evb-col-date {
        min-width: 6.25rem;
    }

    .bexia-evb-col-number,
    .bexia-evb-col-days {
        min-width: 5rem;
    }

    .bexia-evb-col-badge,
    .bexia-evb-col-code {
        min-width: 5.25rem;
        max-width: 7rem;
    }
}
/* BEXIA_EMPLOYEE_VACATION_BALANCE_RESOURCE_RESPONSIVE_V5_79_66C_END */

/* BEXIA_REPAIR_ORDER_APPROVAL_RESOURCE_RESPONSIVE_V5_79_67C_START */
/*
 * RepairOrderApprovalResource responsive refinements.
 * Alcance visual solamente. No cambia logica de ordenes de reparacion,
 * aprobaciones, autorizaciones/rechazos, estados, clientes, tecnicos,
 * costos, productos, inventario, permisos, empresa ni tenant.
 */
.bexia-roa-section {
    border-radius: 1rem;
}

.bexia-roa-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-roa-section,
.bexia-roa-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-roa-section .fi-grid,
.bexia-roa-section .grid,
.bexia-roa-section .fi-fo-component-ctn {
    min-width: 0;
}

.bexia-roa-field .fi-input-wrp,
.bexia-roa-field .fi-select-input,
.bexia-roa-field .fi-fo-select,
.bexia-roa-field input,
.bexia-roa-field select,
.bexia-roa-field textarea,
.bexia-roa-field .choices,
.bexia-roa-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-roa-field-approval-type,
.bexia-roa-field-status,
.bexia-roa-field-reason textarea,
.bexia-roa-field-comments textarea,
.bexia-roa-col-approval-type,
.bexia-roa-col-status,
.bexia-roa-col-reason {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-roa-id-field input,
.bexia-roa-money-field input,
.bexia-roa-date-field input,
.bexia-roa-col-code,
.bexia-roa-col-money,
.bexia-roa-col-date {
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-roa-col-id,
.bexia-roa-col-approval-type,
.bexia-roa-col-status,
.bexia-roa-col-repair-order,
.bexia-roa-col-service-case,
.bexia-roa-col-amount,
.bexia-roa-col-requested-by,
.bexia-roa-col-requested-at,
.bexia-roa-col-decided-by,
.bexia-roa-col-decided-at,
.bexia-roa-col-reason,
.bexia-roa-col-company {
    vertical-align: top;
}

.bexia-roa-col-compact {
    min-width: 5.75rem;
    max-width: 8.5rem;
    white-space: nowrap;
}

.bexia-roa-col-code {
    text-align: right;
}

.bexia-roa-col-money {
    min-width: 7rem;
    white-space: nowrap;
    text-align: right;
}

.bexia-roa-col-date {
    min-width: 8.5rem;
    white-space: nowrap;
}

.bexia-roa-col-person {
    min-width: 8rem;
    max-width: 11.5rem;
    white-space: normal;
}

.bexia-roa-col-badge {
    min-width: 7rem;
    max-width: 10rem;
    white-space: normal;
}

.bexia-roa-col-long-text {
    min-width: 12rem;
    max-width: 20rem;
    white-space: normal;
}

.bexia-roa-col-context {
    overflow-wrap: anywhere;
}

@media (max-width: 1024px) {
    .bexia-roa-section .fi-grid,
    .bexia-roa-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-roa-col-long-text {
        max-width: 16rem;
    }

    .bexia-roa-col-person {
        max-width: 10rem;
    }
}

@media (max-width: 768px) {
    .bexia-roa-section {
        border-radius: 0.85rem;
    }

    .bexia-roa-section .fi-section-header,
    .bexia-roa-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-roa-field .fi-fo-field-wrp-label,
    .bexia-roa-field label {
        font-size: 0.78rem;
    }

    .bexia-roa-field input,
    .bexia-roa-field select,
    .bexia-roa-field textarea,
    .bexia-roa-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-roa-col-id,
    .bexia-roa-col-approval-type,
    .bexia-roa-col-status,
    .bexia-roa-col-repair-order,
    .bexia-roa-col-service-case,
    .bexia-roa-col-amount,
    .bexia-roa-col-requested-by,
    .bexia-roa-col-requested-at,
    .bexia-roa-col-decided-by,
    .bexia-roa-col-decided-at,
    .bexia-roa-col-reason,
    .bexia-roa-col-company {
        font-size: 0.76rem;
    }

    .bexia-roa-col-compact {
        min-width: 5rem;
        max-width: 6.75rem;
    }

    .bexia-roa-col-money {
        min-width: 6rem;
    }

    .bexia-roa-col-date {
        min-width: 7rem;
    }

    .bexia-roa-col-person {
        min-width: 6.75rem;
        max-width: 8.75rem;
    }

    .bexia-roa-col-badge {
        min-width: 5.75rem;
        max-width: 7.75rem;
    }

    .bexia-roa-col-long-text {
        min-width: 8.75rem;
        max-width: 12rem;
    }
}

@media (max-width: 640px) {
    .bexia-roa-col-compact {
        min-width: 4.5rem;
        max-width: 6rem;
    }

    .bexia-roa-col-money {
        min-width: 5.5rem;
    }

    .bexia-roa-col-date {
        min-width: 6.5rem;
    }

    .bexia-roa-col-person,
    .bexia-roa-col-badge {
        min-width: 5.75rem;
        max-width: 7rem;
    }

    .bexia-roa-col-long-text {
        min-width: 7.5rem;
        max-width: 10rem;
    }
}
/* BEXIA_REPAIR_ORDER_APPROVAL_RESOURCE_RESPONSIVE_V5_79_67C_END */

/* BEXIA_BILLING_PAC_CONFIGURATION_RESOURCE_RESPONSIVE_V5_79_68C_START */
/*
 * BillingPacConfigurationResource responsive refinements.
 * Alcance visual solamente. No cambia logica de PAC, SAT, CFDI,
 * timbrado, cancelaciones, credenciales, tokens, passwords,
 * certificados, ambientes, endpoints, series, folios, empresa ni tenant.
 */
.bexia-bpac-section {
    border-radius: 1rem;
}

.bexia-bpac-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-bpac-section,
.bexia-bpac-field,
.bexia-bpac-placeholder {
    min-width: 0;
    max-width: 100%;
}

.bexia-bpac-section .fi-grid,
.bexia-bpac-section .grid,
.bexia-bpac-section .fi-fo-component-ctn {
    min-width: 0;
}

.bexia-bpac-field .fi-input-wrp,
.bexia-bpac-field .fi-select-input,
.bexia-bpac-field .fi-fo-select,
.bexia-bpac-field input,
.bexia-bpac-field select,
.bexia-bpac-field textarea,
.bexia-bpac-field .choices,
.bexia-bpac-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-bpac-placeholder .fi-fo-placeholder,
.bexia-bpac-placeholder,
.bexia-bpac-field-provider,
.bexia-bpac-field-username,
.bexia-bpac-field-password,
.bexia-bpac-field-trusted-exporter,
.bexia-bpac-col-company,
.bexia-bpac-col-provider,
.bexia-bpac-col-username,
.bexia-bpac-col-last-test-status {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-bpac-code-field input,
.bexia-bpac-secret-field input,
.bexia-bpac-col-code,
.bexia-bpac-col-date {
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-bpac-placeholder-rfc .fi-fo-placeholder,
.bexia-bpac-placeholder-endpoints .fi-fo-placeholder,
.bexia-bpac-field-username input,
.bexia-bpac-field-password input,
.bexia-bpac-field-trusted-exporter input,
.bexia-bpac-col-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}

.bexia-bpac-placeholder-endpoints .fi-fo-placeholder,
.bexia-bpac-endpoints-field .fi-fo-placeholder {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-bpac-col-id,
.bexia-bpac-col-company,
.bexia-bpac-col-provider,
.bexia-bpac-col-username,
.bexia-bpac-col-test-env,
.bexia-bpac-col-last-test-status,
.bexia-bpac-col-last-test-at {
    vertical-align: top;
}

.bexia-bpac-col-compact {
    min-width: 4.75rem;
    max-width: 6.5rem;
    white-space: nowrap;
}

.bexia-bpac-col-primary {
    min-width: 12rem;
    max-width: 20rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-bpac-col-code {
    min-width: 8rem;
    max-width: 13rem;
    white-space: normal;
}

.bexia-bpac-col-badge {
    min-width: 7rem;
    max-width: 10rem;
    white-space: normal;
}

.bexia-bpac-col-date {
    min-width: 8.5rem;
    white-space: nowrap;
}

.bexia-bpac-col-context {
    overflow-wrap: anywhere;
}

.bexia-bpac-action {
    max-width: 100%;
}

@media (max-width: 1024px) {
    .bexia-bpac-section .fi-grid,
    .bexia-bpac-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-bpac-col-primary {
        max-width: 16rem;
    }

    .bexia-bpac-col-code {
        max-width: 11rem;
    }
}

@media (max-width: 768px) {
    .bexia-bpac-section {
        border-radius: 0.85rem;
    }

    .bexia-bpac-section .fi-section-header,
    .bexia-bpac-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-bpac-field .fi-fo-field-wrp-label,
    .bexia-bpac-field label {
        font-size: 0.78rem;
    }

    .bexia-bpac-field input,
    .bexia-bpac-field select,
    .bexia-bpac-field textarea,
    .bexia-bpac-field .choices__inner,
    .bexia-bpac-placeholder .fi-fo-placeholder {
        font-size: 0.82rem;
    }

    .bexia-bpac-col-id,
    .bexia-bpac-col-company,
    .bexia-bpac-col-provider,
    .bexia-bpac-col-username,
    .bexia-bpac-col-test-env,
    .bexia-bpac-col-last-test-status,
    .bexia-bpac-col-last-test-at {
        font-size: 0.76rem;
    }

    .bexia-bpac-col-compact {
        min-width: 4.25rem;
        max-width: 5.75rem;
    }

    .bexia-bpac-col-primary {
        min-width: 8.75rem;
        max-width: 12rem;
    }

    .bexia-bpac-col-code {
        min-width: 6.75rem;
        max-width: 9.25rem;
    }

    .bexia-bpac-col-badge {
        min-width: 5.75rem;
        max-width: 7.75rem;
    }

    .bexia-bpac-col-date {
        min-width: 7rem;
    }
}

@media (max-width: 640px) {
    .bexia-bpac-col-compact {
        min-width: 3.75rem;
        max-width: 5.25rem;
    }

    .bexia-bpac-col-primary {
        min-width: 7.5rem;
        max-width: 10rem;
    }

    .bexia-bpac-col-code,
    .bexia-bpac-col-badge {
        min-width: 5.75rem;
        max-width: 7rem;
    }

    .bexia-bpac-col-date {
        min-width: 6.5rem;
    }
}
/* BEXIA_BILLING_PAC_CONFIGURATION_RESOURCE_RESPONSIVE_V5_79_68C_END */

/* BEXIA_STOCK_ADJUSTMENT_AUDIT_RESOURCE_RESPONSIVE_V5_79_69C_START */
/*
 * StockAdjustmentAuditResource responsive refinements.
 * Alcance visual solamente. No cambia logica de auditoria, ajustes,
 * inventario, stock, movimientos, productos, lotes/series, ubicaciones,
 * cantidades, costos, usuarios, permisos, empresa ni tenant.
 */
.bexia-saudit-section {
    border-radius: 1rem;
}

.bexia-saudit-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-saudit-section,
.bexia-saudit-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-saudit-section .fi-grid,
.bexia-saudit-section .grid,
.bexia-saudit-section .fi-fo-component-ctn {
    min-width: 0;
}

.bexia-saudit-field .fi-input-wrp,
.bexia-saudit-field input,
.bexia-saudit-field textarea {
    min-width: 0;
    max-width: 100%;
}

.bexia-saudit-field-description textarea,
.bexia-saudit-long-field textarea,
.bexia-saudit-col-description,
.bexia-saudit-col-user,
.bexia-saudit-col-event {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-saudit-code-field input,
.bexia-saudit-col-code,
.bexia-saudit-col-date {
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-saudit-code-field input,
.bexia-saudit-col-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}

.bexia-saudit-col-created-at,
.bexia-saudit-col-event,
.bexia-saudit-col-description,
.bexia-saudit-col-adjustment,
.bexia-saudit-col-line,
.bexia-saudit-col-user,
.bexia-saudit-col-ip {
    vertical-align: top;
}

.bexia-saudit-col-primary {
    min-width: 13rem;
    max-width: 24rem;
    white-space: normal;
    font-weight: 600;
}

.bexia-saudit-col-long-text {
    min-width: 12rem;
    max-width: 24rem;
    white-space: normal;
}

.bexia-saudit-col-context {
    overflow-wrap: anywhere;
}

.bexia-saudit-col-compact {
    min-width: 5.25rem;
    max-width: 7.5rem;
    white-space: nowrap;
}

.bexia-saudit-col-code {
    min-width: 5.5rem;
    max-width: 8.5rem;
    white-space: normal;
}

.bexia-saudit-col-date {
    min-width: 8.5rem;
    white-space: nowrap;
}

.bexia-saudit-col-badge {
    min-width: 7rem;
    max-width: 10rem;
    white-space: normal;
}

.bexia-saudit-col-person {
    min-width: 8rem;
    max-width: 12rem;
    white-space: normal;
}

.bexia-saudit-col-ip {
    min-width: 7rem;
    max-width: 9rem;
}

@media (max-width: 1024px) {
    .bexia-saudit-section .fi-grid,
    .bexia-saudit-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-saudit-col-primary,
    .bexia-saudit-col-long-text {
        max-width: 18rem;
    }

    .bexia-saudit-col-person {
        max-width: 10rem;
    }
}

@media (max-width: 768px) {
    .bexia-saudit-section {
        border-radius: 0.85rem;
    }

    .bexia-saudit-section .fi-section-header,
    .bexia-saudit-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-saudit-field .fi-fo-field-wrp-label,
    .bexia-saudit-field label {
        font-size: 0.78rem;
    }

    .bexia-saudit-field input,
    .bexia-saudit-field textarea {
        font-size: 0.82rem;
    }

    .bexia-saudit-col-created-at,
    .bexia-saudit-col-event,
    .bexia-saudit-col-description,
    .bexia-saudit-col-adjustment,
    .bexia-saudit-col-line,
    .bexia-saudit-col-user,
    .bexia-saudit-col-ip {
        font-size: 0.76rem;
    }

    .bexia-saudit-col-date {
        min-width: 7rem;
    }

    .bexia-saudit-col-badge {
        min-width: 5.75rem;
        max-width: 7.75rem;
    }

    .bexia-saudit-col-primary,
    .bexia-saudit-col-long-text {
        min-width: 9rem;
        max-width: 12.5rem;
    }

    .bexia-saudit-col-compact,
    .bexia-saudit-col-code {
        min-width: 4.75rem;
        max-width: 6.75rem;
    }

    .bexia-saudit-col-person {
        min-width: 6.5rem;
        max-width: 8.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-saudit-col-date {
        min-width: 6.5rem;
    }

    .bexia-saudit-col-badge,
    .bexia-saudit-col-compact,
    .bexia-saudit-col-code {
        min-width: 4.5rem;
        max-width: 6.25rem;
    }

    .bexia-saudit-col-primary,
    .bexia-saudit-col-long-text {
        min-width: 7.75rem;
        max-width: 10rem;
    }

    .bexia-saudit-col-person {
        min-width: 5.75rem;
        max-width: 7.25rem;
    }
}
/* BEXIA_STOCK_ADJUSTMENT_AUDIT_RESOURCE_RESPONSIVE_V5_79_69C_END */

/* BEXIA_PRODUCT_CATEGORY_RESOURCE_RESPONSIVE_V5_79_70C_START */
/*
 * ProductCategoryResource responsive refinements.
 * Alcance visual solamente. No cambia logica de categorias, productos,
 * jerarquia padre/hijo, ordenamiento, slug/codigo, disponibilidad,
 * inventario, SAT/UNSPSC, impuestos, permisos, empresa ni tenant.
 */
.bexia-pcat-section {
    border-radius: 1rem;
}

.bexia-pcat-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-pcat-section,
.bexia-pcat-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-pcat-section .fi-grid,
.bexia-pcat-section .grid,
.bexia-pcat-section .fi-fo-component-ctn {
    min-width: 0;
}

.bexia-pcat-field .fi-input-wrp,
.bexia-pcat-field .fi-select-input,
.bexia-pcat-field .fi-fo-select,
.bexia-pcat-field input,
.bexia-pcat-field select,
.bexia-pcat-field textarea,
.bexia-pcat-field .choices,
.bexia-pcat-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-pcat-field-name input,
.bexia-pcat-field-parent,
.bexia-pcat-field-tree,
.bexia-pcat-col-tree,
.bexia-pcat-col-parent,
.bexia-pcat-col-context,
.bexia-pcat-col-long-text {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-pcat-code-field input,
.bexia-pcat-number-field input,
.bexia-pcat-col-mono,
.bexia-pcat-col-number {
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-pcat-code-field input,
.bexia-pcat-col-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}

.bexia-pcat-col-costing,
.bexia-pcat-col-code,
.bexia-pcat-col-tree,
.bexia-pcat-col-parent,
.bexia-pcat-col-sort-order,
.bexia-pcat-col-active {
    vertical-align: top;
}

.bexia-pcat-col-primary {
    min-width: 13rem;
    max-width: 24rem;
    white-space: normal;
    font-weight: 650;
}

.bexia-pcat-col-long-text {
    min-width: 11rem;
    max-width: 22rem;
    white-space: normal;
}

.bexia-pcat-col-hierarchy {
    line-height: 1.35;
}

.bexia-pcat-col-compact {
    min-width: 4.75rem;
    max-width: 7.25rem;
    white-space: nowrap;
}

.bexia-pcat-col-code {
    min-width: 6rem;
    max-width: 9rem;
    white-space: normal;
}

.bexia-pcat-col-number {
    min-width: 4.5rem;
    max-width: 6.5rem;
    text-align: right;
}

.bexia-pcat-col-badge {
    min-width: 7rem;
    max-width: 10rem;
    white-space: normal;
}

.bexia-pcat-col-status {
    min-width: 4.75rem;
    max-width: 6.5rem;
}

.bexia-pcat-action {
    max-width: 100%;
}

@media (max-width: 1024px) {
    .bexia-pcat-section .fi-grid,
    .bexia-pcat-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-pcat-col-primary,
    .bexia-pcat-col-long-text {
        max-width: 18rem;
    }

    .bexia-pcat-col-badge {
        max-width: 8.5rem;
    }
}

@media (max-width: 768px) {
    .bexia-pcat-section {
        border-radius: 0.85rem;
    }

    .bexia-pcat-section .fi-section-header,
    .bexia-pcat-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-pcat-field .fi-fo-field-wrp-label,
    .bexia-pcat-field label {
        font-size: 0.78rem;
    }

    .bexia-pcat-field input,
    .bexia-pcat-field select,
    .bexia-pcat-field textarea,
    .bexia-pcat-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-pcat-col-costing,
    .bexia-pcat-col-code,
    .bexia-pcat-col-tree,
    .bexia-pcat-col-parent,
    .bexia-pcat-col-sort-order,
    .bexia-pcat-col-active {
        font-size: 0.76rem;
    }

    .bexia-pcat-col-primary,
    .bexia-pcat-col-long-text {
        min-width: 9rem;
        max-width: 12.5rem;
    }

    .bexia-pcat-col-compact,
    .bexia-pcat-col-code {
        min-width: 4.75rem;
        max-width: 6.75rem;
    }

    .bexia-pcat-col-badge {
        min-width: 5.75rem;
        max-width: 7.75rem;
    }

    .bexia-pcat-col-number,
    .bexia-pcat-col-status {
        min-width: 4rem;
        max-width: 5.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-pcat-col-primary,
    .bexia-pcat-col-long-text {
        min-width: 7.75rem;
        max-width: 10rem;
    }

    .bexia-pcat-col-badge,
    .bexia-pcat-col-compact,
    .bexia-pcat-col-code {
        min-width: 4.5rem;
        max-width: 6.25rem;
    }

    .bexia-pcat-col-number,
    .bexia-pcat-col-status {
        min-width: 3.75rem;
        max-width: 5rem;
    }
}
/* BEXIA_PRODUCT_CATEGORY_RESOURCE_RESPONSIVE_V5_79_70C_END */

/* BEXIA_SAT_DOWNLOAD_REQUEST_RESOURCE_RESPONSIVE_V5_79_71C_START */
/*
 * SatDownloadRequestResource responsive refinements.
 * Alcance visual solamente. No cambia logica SAT, CFDI, XML,
 * solicitudes de descarga, paquetes, estados, fechas,
 * certificados/credenciales, RFC/emisor/receptor,
 * jobs/procesamiento, permisos, empresa ni tenant.
 */
.bexia-sdr-section {
    border-radius: 1rem;
}

.bexia-sdr-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-sdr-section,
.bexia-sdr-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-sdr-section .fi-grid,
.bexia-sdr-section .grid,
.bexia-sdr-section .fi-fo-component-ctn {
    min-width: 0;
}

.bexia-sdr-field .fi-input-wrp,
.bexia-sdr-field .fi-select-input,
.bexia-sdr-field .fi-fo-select,
.bexia-sdr-field input,
.bexia-sdr-field select,
.bexia-sdr-field textarea,
.bexia-sdr-field .choices,
.bexia-sdr-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-sdr-long-field textarea,
.bexia-sdr-field-notes textarea,
.bexia-sdr-field-sat-message textarea,
.bexia-sdr-field-error-message textarea {
    min-height: 6rem;
    resize: vertical;
}

.bexia-sdr-error-field textarea {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-sdr-code-field input,
.bexia-sdr-uuid-field input,
.bexia-sdr-col-code,
.bexia-sdr-col-uuid {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-sdr-col-company,
.bexia-sdr-col-direction,
.bexia-sdr-col-request-kind,
.bexia-sdr-col-date-from,
.bexia-sdr-col-date-to,
.bexia-sdr-col-status,
.bexia-sdr-col-request-uuid,
.bexia-sdr-col-sat-message,
.bexia-sdr-col-created-at {
    vertical-align: top;
}

.bexia-sdr-col-long-text,
.bexia-sdr-col-message,
.bexia-sdr-col-context {
    overflow-wrap: anywhere;
    word-break: break-word;
    white-space: normal;
}

.bexia-sdr-col-company {
    min-width: 10rem;
    max-width: 16rem;
}

.bexia-sdr-col-badge {
    min-width: 6.5rem;
    max-width: 9rem;
    white-space: normal;
}

.bexia-sdr-col-date {
    min-width: 7.75rem;
    max-width: 9.75rem;
    white-space: normal;
    font-variant-numeric: tabular-nums;
}

.bexia-sdr-col-compact {
    min-width: 5rem;
    max-width: 8rem;
}

.bexia-sdr-col-request-uuid {
    min-width: 12rem;
    max-width: 20rem;
}

.bexia-sdr-col-sat-message {
    min-width: 13rem;
    max-width: 24rem;
    line-height: 1.35;
}

.bexia-sdr-col-status {
    font-weight: 650;
}

@media (max-width: 1024px) {
    .bexia-sdr-section .fi-grid,
    .bexia-sdr-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-sdr-col-company,
    .bexia-sdr-col-sat-message {
        max-width: 18rem;
    }

    .bexia-sdr-col-request-uuid {
        max-width: 16rem;
    }
}

@media (max-width: 768px) {
    .bexia-sdr-section {
        border-radius: 0.85rem;
    }

    .bexia-sdr-section .fi-section-header,
    .bexia-sdr-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-sdr-field .fi-fo-field-wrp-label,
    .bexia-sdr-field label {
        font-size: 0.78rem;
    }

    .bexia-sdr-field input,
    .bexia-sdr-field select,
    .bexia-sdr-field textarea,
    .bexia-sdr-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-sdr-col-company,
    .bexia-sdr-col-direction,
    .bexia-sdr-col-request-kind,
    .bexia-sdr-col-date-from,
    .bexia-sdr-col-date-to,
    .bexia-sdr-col-status,
    .bexia-sdr-col-request-uuid,
    .bexia-sdr-col-sat-message,
    .bexia-sdr-col-created-at {
        font-size: 0.76rem;
    }

    .bexia-sdr-col-company,
    .bexia-sdr-col-sat-message {
        min-width: 9rem;
        max-width: 12.5rem;
    }

    .bexia-sdr-col-request-uuid {
        min-width: 9.5rem;
        max-width: 12.5rem;
    }

    .bexia-sdr-col-badge,
    .bexia-sdr-col-compact {
        min-width: 4.75rem;
        max-width: 7rem;
    }

    .bexia-sdr-col-date {
        min-width: 6.25rem;
        max-width: 8rem;
    }
}

@media (max-width: 640px) {
    .bexia-sdr-col-company,
    .bexia-sdr-col-sat-message,
    .bexia-sdr-col-request-uuid {
        min-width: 7.75rem;
        max-width: 10rem;
    }

    .bexia-sdr-col-badge,
    .bexia-sdr-col-compact {
        min-width: 4.5rem;
        max-width: 6.25rem;
    }

    .bexia-sdr-col-date {
        min-width: 5.75rem;
        max-width: 7rem;
    }
}
/* BEXIA_SAT_DOWNLOAD_REQUEST_RESOURCE_RESPONSIVE_V5_79_71C_END */

/* BEXIA_STOCK_SERIAL_SPECIAL_MOVEMENT_RESOURCE_RESPONSIVE_V5_79_72C_START */
/*
 * StockSerialSpecialMovementResource responsive refinements.
 * Alcance visual solamente. No cambia logica de inventario, series/lotes,
 * productos/variantes, movimientos especiales, almacenes/ubicaciones,
 * cantidades, estados, referencias, usuarios, permisos, empresa ni tenant.
 */
.bexia-sssm-section {
    border-radius: 1rem;
}

.bexia-sssm-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-sssm-section,
.bexia-sssm-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-sssm-section .fi-grid,
.bexia-sssm-section .grid,
.bexia-sssm-section .fi-fo-component-ctn {
    min-width: 0;
}

.bexia-sssm-field .fi-input-wrp,
.bexia-sssm-field .fi-select-input,
.bexia-sssm-field .fi-fo-select,
.bexia-sssm-field input,
.bexia-sssm-field select,
.bexia-sssm-field textarea,
.bexia-sssm-field .choices,
.bexia-sssm-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-sssm-long-field textarea,
.bexia-sssm-field-reason textarea,
.bexia-sssm-field-notes textarea {
    min-height: 5.75rem;
    resize: vertical;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.bexia-sssm-code-field input,
.bexia-sssm-serial-field input,
.bexia-sssm-col-code,
.bexia-sssm-col-serial {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-sssm-col-created-at,
.bexia-sssm-col-movement-type,
.bexia-sssm-col-serial-before,
.bexia-sssm-col-serial-after,
.bexia-sssm-col-product,
.bexia-sssm-col-source,
.bexia-sssm-col-destination,
.bexia-sssm-col-reason,
.bexia-sssm-col-reference,
.bexia-sssm-col-user,
.bexia-sssm-col-status {
    vertical-align: top;
}

.bexia-sssm-col-long-text,
.bexia-sssm-col-message,
.bexia-sssm-col-context,
.bexia-sssm-col-product,
.bexia-sssm-col-location {
    overflow-wrap: anywhere;
    word-break: break-word;
    white-space: normal;
}

.bexia-sssm-col-primary {
    min-width: 12rem;
    max-width: 22rem;
    font-weight: 650;
}

.bexia-sssm-col-location {
    min-width: 10rem;
    max-width: 18rem;
}

.bexia-sssm-col-serial {
    min-width: 11rem;
    max-width: 17rem;
}

.bexia-sssm-col-message {
    min-width: 10rem;
    max-width: 18rem;
    line-height: 1.35;
}

.bexia-sssm-col-reference {
    min-width: 7rem;
    max-width: 10rem;
}

.bexia-sssm-col-user {
    min-width: 8rem;
    max-width: 12rem;
}

.bexia-sssm-col-badge {
    min-width: 6.5rem;
    max-width: 9rem;
    white-space: normal;
}

.bexia-sssm-col-date {
    min-width: 7.75rem;
    max-width: 9.75rem;
    white-space: normal;
    font-variant-numeric: tabular-nums;
}

.bexia-sssm-col-compact {
    min-width: 5rem;
    max-width: 8rem;
}

.bexia-sssm-col-status {
    font-weight: 650;
}

@media (max-width: 1024px) {
    .bexia-sssm-section .fi-grid,
    .bexia-sssm-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-sssm-col-primary,
    .bexia-sssm-col-location,
    .bexia-sssm-col-message {
        max-width: 16rem;
    }

    .bexia-sssm-col-serial {
        max-width: 15rem;
    }
}

@media (max-width: 768px) {
    .bexia-sssm-section {
        border-radius: 0.85rem;
    }

    .bexia-sssm-section .fi-section-header,
    .bexia-sssm-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-sssm-field .fi-fo-field-wrp-label,
    .bexia-sssm-field label {
        font-size: 0.78rem;
    }

    .bexia-sssm-field input,
    .bexia-sssm-field select,
    .bexia-sssm-field textarea,
    .bexia-sssm-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-sssm-col-created-at,
    .bexia-sssm-col-movement-type,
    .bexia-sssm-col-serial-before,
    .bexia-sssm-col-serial-after,
    .bexia-sssm-col-product,
    .bexia-sssm-col-source,
    .bexia-sssm-col-destination,
    .bexia-sssm-col-reason,
    .bexia-sssm-col-reference,
    .bexia-sssm-col-user,
    .bexia-sssm-col-status {
        font-size: 0.76rem;
    }

    .bexia-sssm-col-primary,
    .bexia-sssm-col-location,
    .bexia-sssm-col-message {
        min-width: 8.75rem;
        max-width: 12.5rem;
    }

    .bexia-sssm-col-serial {
        min-width: 9rem;
        max-width: 12rem;
    }

    .bexia-sssm-col-badge,
    .bexia-sssm-col-compact,
    .bexia-sssm-col-reference,
    .bexia-sssm-col-user {
        min-width: 4.75rem;
        max-width: 7rem;
    }

    .bexia-sssm-col-date {
        min-width: 6.25rem;
        max-width: 8rem;
    }
}

@media (max-width: 640px) {
    .bexia-sssm-col-primary,
    .bexia-sssm-col-location,
    .bexia-sssm-col-message,
    .bexia-sssm-col-serial {
        min-width: 7.5rem;
        max-width: 10rem;
    }

    .bexia-sssm-col-badge,
    .bexia-sssm-col-compact,
    .bexia-sssm-col-reference,
    .bexia-sssm-col-user {
        min-width: 4.5rem;
        max-width: 6.25rem;
    }

    .bexia-sssm-col-date {
        min-width: 5.75rem;
        max-width: 7rem;
    }
}
/* BEXIA_STOCK_SERIAL_SPECIAL_MOVEMENT_RESOURCE_RESPONSIVE_V5_79_72C_END */

/* BEXIA_POS_STAFF_ASSIGNMENT_RESOURCE_RESPONSIVE_V5_79_73C_START */
/*
 * PosStaffAssignmentResource responsive refinements.
 * Alcance visual solamente. No cambia logica POS, asignaciones de personal,
 * cajeros, puntos POS, turnos, usuarios/empleados, sucursales,
 * estados, fechas, permisos, empresa ni tenant.
 */
.bexia-psa-section {
    border-radius: 1rem;
}

.bexia-psa-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-psa-section,
.bexia-psa-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-psa-section .fi-grid,
.bexia-psa-section .grid,
.bexia-psa-section .fi-fo-component-ctn {
    min-width: 0;
}

.bexia-psa-field .fi-input-wrp,
.bexia-psa-field .fi-select-input,
.bexia-psa-field .fi-fo-select,
.bexia-psa-field input,
.bexia-psa-field select,
.bexia-psa-field .choices,
.bexia-psa-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-psa-primary-field .fi-input-wrp,
.bexia-psa-primary-field .fi-select-input,
.bexia-psa-primary-field .choices__inner {
    min-height: 2.45rem;
}

.bexia-psa-toggle-field {
    min-width: 0;
}

.bexia-psa-toggle-field .fi-fo-toggle,
.bexia-psa-toggle-field .fi-toggle,
.bexia-psa-toggle-field button {
    max-width: 100%;
}

.bexia-psa-numeric-field input {
    max-width: 9rem;
    font-variant-numeric: tabular-nums;
}

.bexia-psa-col-pos-point,
.bexia-psa-col-employee,
.bexia-psa-col-role,
.bexia-psa-col-ticket,
.bexia-psa-col-charge,
.bexia-psa-col-active {
    vertical-align: top;
}

.bexia-psa-col-long-text,
.bexia-psa-col-context,
.bexia-psa-col-primary {
    overflow-wrap: anywhere;
    word-break: break-word;
    white-space: normal;
}

.bexia-psa-col-primary {
    min-width: 12rem;
    max-width: 22rem;
    font-weight: 650;
}

.bexia-psa-col-role {
    min-width: 7.5rem;
    max-width: 11rem;
    white-space: normal;
}

.bexia-psa-col-badge {
    font-weight: 650;
}

.bexia-psa-col-icon,
.bexia-psa-col-permission,
.bexia-psa-col-status {
    min-width: 4.25rem;
    max-width: 6.75rem;
    text-align: center;
    white-space: normal;
}

.bexia-psa-col-ticket,
.bexia-psa-col-charge,
.bexia-psa-col-active {
    font-variant-numeric: tabular-nums;
}

@media (max-width: 1024px) {
    .bexia-psa-section .fi-grid,
    .bexia-psa-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-psa-col-primary {
        max-width: 16rem;
    }

    .bexia-psa-col-role {
        max-width: 9.5rem;
    }
}

@media (max-width: 768px) {
    .bexia-psa-section {
        border-radius: 0.85rem;
    }

    .bexia-psa-section .fi-section-header,
    .bexia-psa-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-psa-field .fi-fo-field-wrp-label,
    .bexia-psa-field label {
        font-size: 0.78rem;
    }

    .bexia-psa-field input,
    .bexia-psa-field select,
    .bexia-psa-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-psa-toggle-field {
        padding-top: 0.15rem;
        padding-bottom: 0.15rem;
    }

    .bexia-psa-col-pos-point,
    .bexia-psa-col-employee,
    .bexia-psa-col-role,
    .bexia-psa-col-ticket,
    .bexia-psa-col-charge,
    .bexia-psa-col-active {
        font-size: 0.76rem;
    }

    .bexia-psa-col-primary {
        min-width: 8.75rem;
        max-width: 12.5rem;
    }

    .bexia-psa-col-role {
        min-width: 6rem;
        max-width: 8rem;
    }

    .bexia-psa-col-icon,
    .bexia-psa-col-permission,
    .bexia-psa-col-status {
        min-width: 3.75rem;
        max-width: 5.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-psa-col-primary {
        min-width: 7.5rem;
        max-width: 10rem;
    }

    .bexia-psa-col-role {
        min-width: 5.5rem;
        max-width: 7rem;
    }

    .bexia-psa-col-icon,
    .bexia-psa-col-permission,
    .bexia-psa-col-status {
        min-width: 3.25rem;
        max-width: 4.75rem;
    }

    .bexia-psa-numeric-field input {
        max-width: 7rem;
    }
}
/* BEXIA_POS_STAFF_ASSIGNMENT_RESOURCE_RESPONSIVE_V5_79_73C_END */

/* BEXIA_PAYMENT_FORM_RESOURCE_RESPONSIVE_V5_79_74C_START */
/*
 * PaymentFormResource responsive refinements.
 * Alcance visual solamente. No cambia logica de formas de pago,
 * claves SAT, fiscal, catalogos, bancos, estados, permisos, empresa ni tenant.
 */
.bexia-pfr-section {
    border-radius: 1rem;
}

.bexia-pfr-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-pfr-section,
.bexia-pfr-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-pfr-section .fi-grid,
.bexia-pfr-section .grid,
.bexia-pfr-section .fi-fo-component-ctn {
    min-width: 0;
}

.bexia-pfr-field .fi-input-wrp,
.bexia-pfr-field .fi-select-input,
.bexia-pfr-field .fi-fo-select,
.bexia-pfr-field input,
.bexia-pfr-field select,
.bexia-pfr-field .choices,
.bexia-pfr-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-pfr-primary-field .fi-input-wrp,
.bexia-pfr-primary-field input {
    min-height: 2.45rem;
}

.bexia-pfr-code-field input,
.bexia-pfr-sat-field .fi-select-input,
.bexia-pfr-sat-field .choices__inner {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-pfr-numeric-field input {
    max-width: 8rem;
    font-variant-numeric: tabular-nums;
}

.bexia-pfr-toggle-field {
    min-width: 0;
}

.bexia-pfr-toggle-field .fi-fo-toggle,
.bexia-pfr-toggle-field .fi-toggle,
.bexia-pfr-toggle-field button {
    max-width: 100%;
}

.bexia-pfr-col-code,
.bexia-pfr-col-name,
.bexia-pfr-col-sat-form,
.bexia-pfr-col-sat-method,
.bexia-pfr-col-payment-term,
.bexia-pfr-col-cash,
.bexia-pfr-col-credit,
.bexia-pfr-col-reference,
.bexia-pfr-col-active,
.bexia-pfr-col-sort-order {
    vertical-align: top;
}

.bexia-pfr-col-long-text,
.bexia-pfr-col-related,
.bexia-pfr-col-context {
    overflow-wrap: anywhere;
    word-break: break-word;
    white-space: normal;
}

.bexia-pfr-col-primary {
    min-width: 12rem;
    max-width: 22rem;
    font-weight: 650;
}

.bexia-pfr-col-code,
.bexia-pfr-col-key,
.bexia-pfr-col-sat,
.bexia-pfr-col-number {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.bexia-pfr-col-short {
    min-width: 4.75rem;
    max-width: 7rem;
}

.bexia-pfr-col-payment-term {
    min-width: 10rem;
    max-width: 16rem;
}

.bexia-pfr-col-icon,
.bexia-pfr-col-flag,
.bexia-pfr-col-status,
.bexia-pfr-col-requirement,
.bexia-pfr-col-payment-kind {
    min-width: 4.25rem;
    max-width: 7rem;
    text-align: center;
    white-space: normal;
}

@media (max-width: 1024px) {
    .bexia-pfr-section .fi-grid,
    .bexia-pfr-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-pfr-col-primary {
        max-width: 16rem;
    }

    .bexia-pfr-col-payment-term {
        max-width: 12rem;
    }
}

@media (max-width: 768px) {
    .bexia-pfr-section {
        border-radius: 0.85rem;
    }

    .bexia-pfr-section .fi-section-header,
    .bexia-pfr-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-pfr-field .fi-fo-field-wrp-label,
    .bexia-pfr-field label {
        font-size: 0.78rem;
    }

    .bexia-pfr-field input,
    .bexia-pfr-field select,
    .bexia-pfr-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-pfr-toggle-field {
        padding-top: 0.15rem;
        padding-bottom: 0.15rem;
    }

    .bexia-pfr-col-code,
    .bexia-pfr-col-name,
    .bexia-pfr-col-sat-form,
    .bexia-pfr-col-sat-method,
    .bexia-pfr-col-payment-term,
    .bexia-pfr-col-cash,
    .bexia-pfr-col-credit,
    .bexia-pfr-col-reference,
    .bexia-pfr-col-active,
    .bexia-pfr-col-sort-order {
        font-size: 0.76rem;
    }

    .bexia-pfr-col-primary {
        min-width: 8.75rem;
        max-width: 12.5rem;
    }

    .bexia-pfr-col-short {
        min-width: 4.25rem;
        max-width: 5.75rem;
    }

    .bexia-pfr-col-payment-term {
        min-width: 7.5rem;
        max-width: 10rem;
    }

    .bexia-pfr-col-icon,
    .bexia-pfr-col-flag,
    .bexia-pfr-col-status,
    .bexia-pfr-col-requirement,
    .bexia-pfr-col-payment-kind {
        min-width: 3.75rem;
        max-width: 5.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-pfr-col-primary {
        min-width: 7.5rem;
        max-width: 10rem;
    }

    .bexia-pfr-col-short {
        min-width: 3.75rem;
        max-width: 5rem;
    }

    .bexia-pfr-col-payment-term {
        min-width: 6.75rem;
        max-width: 8.75rem;
    }

    .bexia-pfr-col-icon,
    .bexia-pfr-col-flag,
    .bexia-pfr-col-status,
    .bexia-pfr-col-requirement,
    .bexia-pfr-col-payment-kind {
        min-width: 3.25rem;
        max-width: 4.75rem;
    }

    .bexia-pfr-numeric-field input {
        max-width: 6.5rem;
    }
}
/* BEXIA_PAYMENT_FORM_RESOURCE_RESPONSIVE_V5_79_74C_END */

/* BEXIA_EMPLOYEE_DOCUMENT_RESOURCE_RESPONSIVE_V5_79_75C_START */
/*
 * EmployeeDocumentResource responsive refinements.
 * Alcance visual solamente. No cambia logica RRHH, documentos,
 * archivos, upload/download, storage, vencimientos, tipos de documento,
 * empleados, estados, permisos, empresa ni tenant.
 */
.bexia-edr-section {
    border-radius: 1rem;
}

.bexia-edr-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-edr-section,
.bexia-edr-grid,
.bexia-edr-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-edr-section .fi-grid,
.bexia-edr-section .grid,
.bexia-edr-section .fi-fo-component-ctn,
.bexia-edr-grid {
    min-width: 0;
}

.bexia-edr-field .fi-input-wrp,
.bexia-edr-field .fi-select-input,
.bexia-edr-field .fi-fo-select,
.bexia-edr-field textarea,
.bexia-edr-field input,
.bexia-edr-field select,
.bexia-edr-field .choices,
.bexia-edr-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-edr-primary-field .fi-input-wrp,
.bexia-edr-primary-field input {
    min-height: 2.45rem;
}

.bexia-edr-code-field input {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-edr-date-field .fi-input-wrp,
.bexia-edr-date-field input {
    font-variant-numeric: tabular-nums;
}

.bexia-edr-file-upload-field .fi-fo-file-upload,
.bexia-edr-file-upload-field .filepond--root,
.bexia-edr-file-upload-field [data-filepond-item-state],
.bexia-edr-file-upload-field .filepond--drop-label {
    min-width: 0;
    max-width: 100%;
}

.bexia-edr-file-upload-field .filepond--drop-label {
    padding-left: 0.75rem;
    padding-right: 0.75rem;
}

.bexia-edr-notes-field textarea {
    min-height: 5.5rem;
    resize: vertical;
}

.bexia-edr-col-employee,
.bexia-edr-col-document-type,
.bexia-edr-col-document-name,
.bexia-edr-col-document-number,
.bexia-edr-col-status,
.bexia-edr-col-issued-at,
.bexia-edr-col-expires-at,
.bexia-edr-col-file,
.bexia-edr-col-created-at {
    vertical-align: top;
}

.bexia-edr-col-long-text,
.bexia-edr-col-related,
.bexia-edr-col-context {
    overflow-wrap: anywhere;
    word-break: break-word;
    white-space: normal;
}

.bexia-edr-col-primary {
    min-width: 11rem;
    max-width: 22rem;
    font-weight: 650;
}

.bexia-edr-col-document-type {
    min-width: 10rem;
    max-width: 18rem;
}

.bexia-edr-col-key,
.bexia-edr-col-date {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.bexia-edr-col-key {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
}

.bexia-edr-col-short {
    min-width: 5.75rem;
    max-width: 8.5rem;
}

.bexia-edr-col-state,
.bexia-edr-col-badge {
    min-width: 5.75rem;
    max-width: 8rem;
    white-space: normal;
}

.bexia-edr-col-expiration {
    min-width: 6.75rem;
    max-width: 9rem;
}

.bexia-edr-col-file,
.bexia-edr-col-icon,
.bexia-edr-col-file-status {
    min-width: 4.5rem;
    max-width: 6.5rem;
    text-align: center;
    white-space: normal;
}

@media (max-width: 1024px) {
    .bexia-edr-section .fi-grid,
    .bexia-edr-section .grid,
    .bexia-edr-grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-edr-col-primary {
        max-width: 16rem;
    }

    .bexia-edr-col-document-type {
        max-width: 13rem;
    }
}

@media (max-width: 768px) {
    .bexia-edr-section {
        border-radius: 0.85rem;
    }

    .bexia-edr-section .fi-section-header,
    .bexia-edr-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-edr-field .fi-fo-field-wrp-label,
    .bexia-edr-field label {
        font-size: 0.78rem;
    }

    .bexia-edr-field input,
    .bexia-edr-field select,
    .bexia-edr-field textarea,
    .bexia-edr-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-edr-file-upload-field .filepond--drop-label {
        font-size: 0.78rem;
        min-height: 3rem;
    }

    .bexia-edr-col-employee,
    .bexia-edr-col-document-type,
    .bexia-edr-col-document-name,
    .bexia-edr-col-document-number,
    .bexia-edr-col-status,
    .bexia-edr-col-issued-at,
    .bexia-edr-col-expires-at,
    .bexia-edr-col-file,
    .bexia-edr-col-created-at {
        font-size: 0.76rem;
    }

    .bexia-edr-col-primary {
        min-width: 8.75rem;
        max-width: 12.5rem;
    }

    .bexia-edr-col-document-type {
        min-width: 7.75rem;
        max-width: 10.5rem;
    }

    .bexia-edr-col-short,
    .bexia-edr-col-state,
    .bexia-edr-col-badge,
    .bexia-edr-col-expiration {
        min-width: 4.75rem;
        max-width: 6.75rem;
    }

    .bexia-edr-col-file,
    .bexia-edr-col-icon,
    .bexia-edr-col-file-status {
        min-width: 3.75rem;
        max-width: 5rem;
    }
}

@media (max-width: 640px) {
    .bexia-edr-col-primary {
        min-width: 7.25rem;
        max-width: 10rem;
    }

    .bexia-edr-col-document-type {
        min-width: 6.75rem;
        max-width: 8.75rem;
    }

    .bexia-edr-col-short,
    .bexia-edr-col-state,
    .bexia-edr-col-badge,
    .bexia-edr-col-expiration {
        min-width: 4.25rem;
        max-width: 5.75rem;
    }

    .bexia-edr-col-file,
    .bexia-edr-col-icon,
    .bexia-edr-col-file-status {
        min-width: 3.25rem;
        max-width: 4.5rem;
    }
}
/* BEXIA_EMPLOYEE_DOCUMENT_RESOURCE_RESPONSIVE_V5_79_75C_END */

/* BEXIA_PURCHASE_RECEIPT_RESOURCE_RESPONSIVE_V5_79_76C_START */
/*
 * PurchaseReceiptResource responsive refinements.
 * Alcance visual solamente. No cambia logica de recepciones de compra,
 * ordenes de compra, inventario, productos, cantidades, costos,
 * proveedores, almacenes, PDF/panel, estados, permisos, empresa ni tenant.
 */
.bexia-prr-modal-field,
.bexia-prr-filter-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-prr-modal-field textarea,
.bexia-prr-long-field textarea,
.bexia-prr-filter-field .fi-select-input,
.bexia-prr-filter-field .choices,
.bexia-prr-filter-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-prr-col-number,
.bexia-prr-col-purchase-order,
.bexia-prr-col-supplier,
.bexia-prr-col-status,
.bexia-prr-col-warehouse,
.bexia-prr-col-location-detail,
.bexia-prr-col-stock-movement,
.bexia-prr-col-received-at,
.bexia-prr-col-total,
.bexia-prr-col-created-at {
    vertical-align: top;
}

.bexia-prr-col-long-text,
.bexia-prr-col-party,
.bexia-prr-col-related,
.bexia-prr-col-context {
    overflow-wrap: anywhere;
    word-break: break-word;
    white-space: normal;
}

.bexia-prr-col-primary {
    min-width: 8.5rem;
    max-width: 12rem;
    font-weight: 650;
}

.bexia-prr-col-reference,
.bexia-prr-col-movement {
    min-width: 7.25rem;
    max-width: 11rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-variant-numeric: tabular-nums;
}

.bexia-prr-col-supplier {
    min-width: 12rem;
    max-width: 20rem;
}

.bexia-prr-col-warehouse,
.bexia-prr-col-location-detail {
    min-width: 9rem;
    max-width: 14rem;
}

.bexia-prr-col-status,
.bexia-prr-col-badge,
.bexia-prr-col-state {
    min-width: 6rem;
    max-width: 9rem;
    white-space: normal;
}

.bexia-prr-col-date,
.bexia-prr-col-timeline {
    min-width: 8rem;
    max-width: 10rem;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.bexia-prr-col-money,
.bexia-prr-col-number-value {
    min-width: 7rem;
    max-width: 9rem;
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

@media (max-width: 1024px) {
    .bexia-prr-col-supplier {
        max-width: 16rem;
    }

    .bexia-prr-col-warehouse,
    .bexia-prr-col-location-detail {
        max-width: 12rem;
    }

    .bexia-prr-col-reference,
    .bexia-prr-col-movement {
        max-width: 9.5rem;
    }
}

@media (max-width: 768px) {
    .bexia-prr-col-number,
    .bexia-prr-col-purchase-order,
    .bexia-prr-col-supplier,
    .bexia-prr-col-status,
    .bexia-prr-col-warehouse,
    .bexia-prr-col-location-detail,
    .bexia-prr-col-stock-movement,
    .bexia-prr-col-received-at,
    .bexia-prr-col-total,
    .bexia-prr-col-created-at {
        font-size: 0.76rem;
    }

    .bexia-prr-col-primary {
        min-width: 7.25rem;
        max-width: 9.5rem;
    }

    .bexia-prr-col-reference,
    .bexia-prr-col-movement {
        min-width: 6.25rem;
        max-width: 8rem;
    }

    .bexia-prr-col-supplier {
        min-width: 8.75rem;
        max-width: 12rem;
    }

    .bexia-prr-col-warehouse,
    .bexia-prr-col-location-detail {
        min-width: 7.25rem;
        max-width: 10rem;
    }

    .bexia-prr-col-status,
    .bexia-prr-col-badge,
    .bexia-prr-col-state {
        min-width: 4.75rem;
        max-width: 7rem;
    }

    .bexia-prr-col-date,
    .bexia-prr-col-timeline {
        min-width: 6.5rem;
        max-width: 8rem;
    }

    .bexia-prr-col-money,
    .bexia-prr-col-number-value {
        min-width: 5.75rem;
        max-width: 7.25rem;
    }

    .bexia-prr-modal-field textarea {
        min-height: 7rem;
        font-size: 0.82rem;
    }
}

@media (max-width: 640px) {
    .bexia-prr-col-primary {
        min-width: 6.5rem;
        max-width: 8rem;
    }

    .bexia-prr-col-reference,
    .bexia-prr-col-movement {
        min-width: 5.5rem;
        max-width: 7rem;
    }

    .bexia-prr-col-supplier {
        min-width: 7.5rem;
        max-width: 10rem;
    }

    .bexia-prr-col-warehouse,
    .bexia-prr-col-location-detail {
        min-width: 6.5rem;
        max-width: 8.5rem;
    }

    .bexia-prr-col-date,
    .bexia-prr-col-timeline {
        min-width: 5.75rem;
        max-width: 7rem;
    }

    .bexia-prr-col-money,
    .bexia-prr-col-number-value {
        min-width: 5.25rem;
        max-width: 6.5rem;
    }
}
/* BEXIA_PURCHASE_RECEIPT_RESOURCE_RESPONSIVE_V5_79_76C_END */

/* BEXIA_STOCK_LOCATION_RESOURCE_RESPONSIVE_V5_79_77C_START */
/*
 * StockLocationResource responsive refinements.
 * Alcance visual solamente. No cambia logica de ubicaciones, almacenes,
 * inventario, jerarquia padre/hijo, rutas, tipos, stock, movimientos,
 * permisos, empresa ni tenant.
 */
.bexia-slr-section {
    border-radius: 1rem;
}

.bexia-slr-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-slr-section,
.bexia-slr-field,
.bexia-slr-filter {
    min-width: 0;
    max-width: 100%;
}

.bexia-slr-section .fi-grid,
.bexia-slr-section .grid,
.bexia-slr-section .fi-fo-component-ctn {
    min-width: 0;
}

.bexia-slr-field .fi-input-wrp,
.bexia-slr-field .fi-select-input,
.bexia-slr-field .fi-fo-select,
.bexia-slr-field input,
.bexia-slr-field select,
.bexia-slr-field textarea,
.bexia-slr-field .choices,
.bexia-slr-field .choices__inner,
.bexia-slr-filter .fi-select-input,
.bexia-slr-filter .choices,
.bexia-slr-filter .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-slr-primary-field .fi-input-wrp,
.bexia-slr-primary-field input {
    min-height: 2.45rem;
}

.bexia-slr-code-field input,
.bexia-slr-barcode-field input,
.bexia-slr-col-key {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-slr-long-field textarea,
.bexia-slr-description-field textarea {
    min-height: 6.5rem;
    resize: vertical;
}

.bexia-slr-toggle-field {
    min-width: 0;
}

.bexia-slr-toggle-field .fi-fo-toggle,
.bexia-slr-toggle-field .fi-toggle,
.bexia-slr-toggle-field button {
    max-width: 100%;
}

.bexia-slr-col-warehouse,
.bexia-slr-col-code,
.bexia-slr-col-name,
.bexia-slr-col-parent,
.bexia-slr-col-type,
.bexia-slr-col-barcode,
.bexia-slr-col-negative-stock,
.bexia-slr-col-active {
    vertical-align: top;
}

.bexia-slr-col-long-text,
.bexia-slr-col-related,
.bexia-slr-col-hierarchy,
.bexia-slr-col-location-context,
.bexia-slr-col-context {
    overflow-wrap: anywhere;
    word-break: break-word;
    white-space: normal;
}

.bexia-slr-col-primary {
    min-width: 12rem;
    max-width: 20rem;
    font-weight: 650;
}

.bexia-slr-col-warehouse,
.bexia-slr-col-parent,
.bexia-slr-col-type {
    min-width: 9.5rem;
    max-width: 15rem;
}

.bexia-slr-col-code,
.bexia-slr-col-barcode,
.bexia-slr-col-short {
    min-width: 6.5rem;
    max-width: 9rem;
    white-space: nowrap;
}

.bexia-slr-col-location-type {
    min-width: 8rem;
    max-width: 12rem;
}

.bexia-slr-col-icon,
.bexia-slr-col-flag,
.bexia-slr-col-status,
.bexia-slr-col-policy {
    min-width: 4.25rem;
    max-width: 7rem;
    text-align: center;
    white-space: normal;
}

@media (max-width: 1024px) {
    .bexia-slr-section .fi-grid,
    .bexia-slr-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-slr-col-primary {
        max-width: 16rem;
    }

    .bexia-slr-col-warehouse,
    .bexia-slr-col-parent,
    .bexia-slr-col-type {
        max-width: 12rem;
    }
}

@media (max-width: 768px) {
    .bexia-slr-section {
        border-radius: 0.85rem;
    }

    .bexia-slr-section .fi-section-header,
    .bexia-slr-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-slr-field .fi-fo-field-wrp-label,
    .bexia-slr-field label {
        font-size: 0.78rem;
    }

    .bexia-slr-field input,
    .bexia-slr-field select,
    .bexia-slr-field textarea,
    .bexia-slr-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-slr-toggle-field {
        padding-top: 0.15rem;
        padding-bottom: 0.15rem;
    }

    .bexia-slr-col-warehouse,
    .bexia-slr-col-code,
    .bexia-slr-col-name,
    .bexia-slr-col-parent,
    .bexia-slr-col-type,
    .bexia-slr-col-barcode,
    .bexia-slr-col-negative-stock,
    .bexia-slr-col-active {
        font-size: 0.76rem;
    }

    .bexia-slr-col-primary {
        min-width: 8.75rem;
        max-width: 12.5rem;
    }

    .bexia-slr-col-warehouse,
    .bexia-slr-col-parent,
    .bexia-slr-col-type {
        min-width: 7.5rem;
        max-width: 10rem;
    }

    .bexia-slr-col-code,
    .bexia-slr-col-barcode,
    .bexia-slr-col-short {
        min-width: 5.25rem;
        max-width: 7rem;
    }

    .bexia-slr-col-icon,
    .bexia-slr-col-flag,
    .bexia-slr-col-status,
    .bexia-slr-col-policy {
        min-width: 3.75rem;
        max-width: 5.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-slr-col-primary {
        min-width: 7.5rem;
        max-width: 10rem;
    }

    .bexia-slr-col-warehouse,
    .bexia-slr-col-parent,
    .bexia-slr-col-type {
        min-width: 6.75rem;
        max-width: 8.75rem;
    }

    .bexia-slr-col-code,
    .bexia-slr-col-barcode,
    .bexia-slr-col-short {
        min-width: 4.75rem;
        max-width: 6.25rem;
    }

    .bexia-slr-col-icon,
    .bexia-slr-col-flag,
    .bexia-slr-col-status,
    .bexia-slr-col-policy {
        min-width: 3.25rem;
        max-width: 4.75rem;
    }

    .bexia-slr-description-field textarea {
        min-height: 5.5rem;
    }
}
/* BEXIA_STOCK_LOCATION_RESOURCE_RESPONSIVE_V5_79_77C_END */

/* BEXIA_SERVICE_CASE_EVENT_RESOURCE_RESPONSIVE_V5_79_78C_START */
/*
 * ServiceCaseEventResource responsive refinements.
 * Alcance visual solamente. No cambia logica de eventos de casos de servicio,
 * bitacora, cliente/contacto, tecnico, estado, prioridad, SLA,
 * fechas, permisos, empresa ni tenant.
 */
.bexia-scer-section {
    border-radius: 1rem;
}

.bexia-scer-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-scer-section,
.bexia-scer-field,
.bexia-scer-filter {
    min-width: 0;
    max-width: 100%;
}

.bexia-scer-section .fi-grid,
.bexia-scer-section .grid,
.bexia-scer-section .fi-fo-component-ctn {
    min-width: 0;
}

.bexia-scer-field .fi-input-wrp,
.bexia-scer-field .fi-select-input,
.bexia-scer-field .fi-fo-select,
.bexia-scer-field input,
.bexia-scer-field select,
.bexia-scer-field textarea,
.bexia-scer-field .choices,
.bexia-scer-field .choices__inner,
.bexia-scer-filter .fi-select-input,
.bexia-scer-filter .choices,
.bexia-scer-filter .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-scer-primary-field .fi-input-wrp,
.bexia-scer-primary-field input {
    min-height: 2.45rem;
}

.bexia-scer-code-field input,
.bexia-scer-reference-field input,
.bexia-scer-company-field input,
.bexia-scer-col-code,
.bexia-scer-col-reference {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-scer-date-field input,
.bexia-scer-timeline-field input {
    font-variant-numeric: tabular-nums;
}

.bexia-scer-long-field textarea,
.bexia-scer-notes-field textarea,
.bexia-scer-metadata-field textarea {
    min-height: 6.5rem;
    resize: vertical;
}

.bexia-scer-technical-field textarea {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-size: 0.82rem;
}

.bexia-scer-col-performed-at,
.bexia-scer-col-event-type,
.bexia-scer-col-service-case,
.bexia-scer-col-repair-order,
.bexia-scer-col-from-status,
.bexia-scer-col-to-status,
.bexia-scer-col-performed-by,
.bexia-scer-col-notes,
.bexia-scer-col-company {
    vertical-align: top;
}

.bexia-scer-col-long-text,
.bexia-scer-col-related,
.bexia-scer-col-context,
.bexia-scer-col-bitacora {
    overflow-wrap: anywhere;
    word-break: break-word;
    white-space: normal;
}

.bexia-scer-col-primary,
.bexia-scer-col-event-type {
    min-width: 8rem;
    max-width: 12rem;
    font-weight: 650;
}

.bexia-scer-col-date,
.bexia-scer-col-timeline {
    min-width: 8rem;
    max-width: 10rem;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.bexia-scer-col-reference,
.bexia-scer-col-service-case,
.bexia-scer-col-repair-order,
.bexia-scer-col-company {
    min-width: 5.75rem;
    max-width: 8rem;
    white-space: nowrap;
    text-align: right;
}

.bexia-scer-col-status,
.bexia-scer-col-state,
.bexia-scer-col-from-status,
.bexia-scer-col-to-status {
    min-width: 7rem;
    max-width: 10rem;
    white-space: normal;
}

.bexia-scer-col-user,
.bexia-scer-col-performed-by {
    min-width: 7rem;
    max-width: 11rem;
}

.bexia-scer-col-notes {
    min-width: 12rem;
    max-width: 22rem;
}

.bexia-scer-filter-event-type,
.bexia-scer-filter-case,
.bexia-scer-filter-repair {
    min-width: 0;
}

@media (max-width: 1024px) {
    .bexia-scer-section .fi-grid,
    .bexia-scer-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-scer-col-notes {
        max-width: 18rem;
    }

    .bexia-scer-col-status,
    .bexia-scer-col-state,
    .bexia-scer-col-from-status,
    .bexia-scer-col-to-status {
        max-width: 9rem;
    }
}

@media (max-width: 768px) {
    .bexia-scer-section {
        border-radius: 0.85rem;
    }

    .bexia-scer-section .fi-section-header,
    .bexia-scer-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-scer-field .fi-fo-field-wrp-label,
    .bexia-scer-field label {
        font-size: 0.78rem;
    }

    .bexia-scer-field input,
    .bexia-scer-field select,
    .bexia-scer-field textarea,
    .bexia-scer-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-scer-col-performed-at,
    .bexia-scer-col-event-type,
    .bexia-scer-col-service-case,
    .bexia-scer-col-repair-order,
    .bexia-scer-col-from-status,
    .bexia-scer-col-to-status,
    .bexia-scer-col-performed-by,
    .bexia-scer-col-notes,
    .bexia-scer-col-company {
        font-size: 0.76rem;
    }

    .bexia-scer-col-primary,
    .bexia-scer-col-event-type {
        min-width: 6.5rem;
        max-width: 9rem;
    }

    .bexia-scer-col-date,
    .bexia-scer-col-timeline {
        min-width: 6.5rem;
        max-width: 8rem;
    }

    .bexia-scer-col-reference,
    .bexia-scer-col-service-case,
    .bexia-scer-col-repair-order,
    .bexia-scer-col-company {
        min-width: 4.75rem;
        max-width: 6.5rem;
    }

    .bexia-scer-col-status,
    .bexia-scer-col-state,
    .bexia-scer-col-from-status,
    .bexia-scer-col-to-status {
        min-width: 5.75rem;
        max-width: 7.5rem;
    }

    .bexia-scer-col-user,
    .bexia-scer-col-performed-by {
        min-width: 5.75rem;
        max-width: 8rem;
    }

    .bexia-scer-col-notes {
        min-width: 8.5rem;
        max-width: 13rem;
    }

    .bexia-scer-long-field textarea,
    .bexia-scer-notes-field textarea,
    .bexia-scer-metadata-field textarea {
        min-height: 5.75rem;
    }
}

@media (max-width: 640px) {
    .bexia-scer-col-primary,
    .bexia-scer-col-event-type {
        min-width: 5.75rem;
        max-width: 7.5rem;
    }

    .bexia-scer-col-date,
    .bexia-scer-col-timeline {
        min-width: 5.75rem;
        max-width: 7rem;
    }

    .bexia-scer-col-reference,
    .bexia-scer-col-service-case,
    .bexia-scer-col-repair-order,
    .bexia-scer-col-company {
        min-width: 4.25rem;
        max-width: 5.75rem;
    }

    .bexia-scer-col-status,
    .bexia-scer-col-state,
    .bexia-scer-col-from-status,
    .bexia-scer-col-to-status {
        min-width: 5rem;
        max-width: 6.5rem;
    }

    .bexia-scer-col-user,
    .bexia-scer-col-performed-by {
        min-width: 5rem;
        max-width: 6.75rem;
    }

    .bexia-scer-col-notes {
        min-width: 7.25rem;
        max-width: 10rem;
    }
}
/* BEXIA_SERVICE_CASE_EVENT_RESOURCE_RESPONSIVE_V5_79_78C_END */

/* BEXIA_POS_CASHIER_RESOURCE_RESPONSIVE_V5_79_79C_START */
/*
 * PosCashierResource responsive refinements.
 * Alcance visual solamente. No cambia logica de cajeros POS, usuarios,
 * empleados, cajas, puntos de venta, tickets, sesiones, turnos,
 * pagos, permisos, empresa ni tenant.
 */
.bexia-pcash-section {
    border-radius: 1rem;
}

.bexia-pcash-section .fi-section-content {
    overflow-x: hidden;
}

.bexia-pcash-section,
.bexia-pcash-field {
    min-width: 0;
    max-width: 100%;
}

.bexia-pcash-section .fi-grid,
.bexia-pcash-section .grid,
.bexia-pcash-section .fi-fo-component-ctn {
    min-width: 0;
}

.bexia-pcash-field .fi-input-wrp,
.bexia-pcash-field .fi-select-input,
.bexia-pcash-field .fi-fo-select,
.bexia-pcash-field input,
.bexia-pcash-field select,
.bexia-pcash-field .choices,
.bexia-pcash-field .choices__inner {
    min-width: 0;
    max-width: 100%;
}

.bexia-pcash-primary-field .fi-input-wrp,
.bexia-pcash-primary-field input {
    min-height: 2.45rem;
}

.bexia-pcash-code-field input,
.bexia-pcash-identifier-field input,
.bexia-pcash-pin-field input,
.bexia-pcash-col-code,
.bexia-pcash-col-identifier {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.01em;
}

.bexia-pcash-percent-field input,
.bexia-pcash-numeric-field input {
    font-variant-numeric: tabular-nums;
    text-align: right;
}

.bexia-pcash-toggle-field .fi-fo-field-wrp,
.bexia-pcash-permission-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-pcash-section-permissions .fi-grid,
.bexia-pcash-section-controls .fi-grid {
    align-items: start;
}

.bexia-pcash-col-name,
.bexia-pcash-col-code,
.bexia-pcash-col-pos-point,
.bexia-pcash-col-pin,
.bexia-pcash-col-active {
    vertical-align: top;
}

.bexia-pcash-col-primary,
.bexia-pcash-col-name,
.bexia-pcash-col-context {
    overflow-wrap: anywhere;
    word-break: break-word;
    white-space: normal;
}

.bexia-pcash-col-name,
.bexia-pcash-col-cashier {
    min-width: 10rem;
    max-width: 16rem;
    font-weight: 650;
}

.bexia-pcash-col-code,
.bexia-pcash-col-identifier,
.bexia-pcash-col-compact {
    min-width: 5.5rem;
    max-width: 8rem;
    white-space: nowrap;
}

.bexia-pcash-col-pos-point,
.bexia-pcash-col-reference,
.bexia-pcash-col-pos {
    min-width: 6rem;
    max-width: 9rem;
    white-space: nowrap;
    text-align: right;
}

.bexia-pcash-col-pin,
.bexia-pcash-col-security,
.bexia-pcash-col-active,
.bexia-pcash-col-status,
.bexia-pcash-col-boolean {
    min-width: 4.75rem;
    max-width: 6.5rem;
    text-align: center;
}

.bexia-pcash-secret-field .fi-input-wrp,
.bexia-pcash-sensitive-field .fi-input-wrp {
    max-width: 14rem;
}

@media (max-width: 1024px) {
    .bexia-pcash-section .fi-grid,
    .bexia-pcash-section .grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .bexia-pcash-section-permissions .fi-grid,
    .bexia-pcash-section-controls .fi-grid {
        gap: 0.75rem;
    }

    .bexia-pcash-col-name,
    .bexia-pcash-col-cashier {
        max-width: 14rem;
    }

    .bexia-pcash-col-pos-point,
    .bexia-pcash-col-reference,
    .bexia-pcash-col-pos {
        max-width: 8rem;
    }
}

@media (max-width: 768px) {
    .bexia-pcash-section {
        border-radius: 0.85rem;
    }

    .bexia-pcash-section .fi-section-header,
    .bexia-pcash-section .fi-section-content {
        padding-left: 0.85rem;
        padding-right: 0.85rem;
    }

    .bexia-pcash-field .fi-fo-field-wrp-label,
    .bexia-pcash-field label {
        font-size: 0.78rem;
    }

    .bexia-pcash-field input,
    .bexia-pcash-field select,
    .bexia-pcash-field .choices__inner {
        font-size: 0.82rem;
    }

    .bexia-pcash-col-name,
    .bexia-pcash-col-code,
    .bexia-pcash-col-pos-point,
    .bexia-pcash-col-pin,
    .bexia-pcash-col-active {
        font-size: 0.76rem;
    }

    .bexia-pcash-col-name,
    .bexia-pcash-col-cashier {
        min-width: 7.5rem;
        max-width: 10.5rem;
    }

    .bexia-pcash-col-code,
    .bexia-pcash-col-identifier,
    .bexia-pcash-col-compact {
        min-width: 4.75rem;
        max-width: 6.5rem;
    }

    .bexia-pcash-col-pos-point,
    .bexia-pcash-col-reference,
    .bexia-pcash-col-pos {
        min-width: 4.75rem;
        max-width: 6.5rem;
    }

    .bexia-pcash-col-pin,
    .bexia-pcash-col-security,
    .bexia-pcash-col-active,
    .bexia-pcash-col-status,
    .bexia-pcash-col-boolean {
        min-width: 4rem;
        max-width: 5rem;
    }
}

@media (max-width: 640px) {
    .bexia-pcash-section .fi-section-header,
    .bexia-pcash-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-pcash-secret-field .fi-input-wrp,
    .bexia-pcash-sensitive-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-pcash-col-name,
    .bexia-pcash-col-cashier {
        min-width: 6.25rem;
        max-width: 8rem;
    }

    .bexia-pcash-col-code,
    .bexia-pcash-col-identifier,
    .bexia-pcash-col-compact {
        min-width: 4rem;
        max-width: 5.25rem;
    }

    .bexia-pcash-col-pos-point,
    .bexia-pcash-col-reference,
    .bexia-pcash-col-pos {
        min-width: 4rem;
        max-width: 5.25rem;
    }

    .bexia-pcash-col-pin,
    .bexia-pcash-col-security,
    .bexia-pcash-col-active,
    .bexia-pcash-col-status,
    .bexia-pcash-col-boolean {
        min-width: 3.5rem;
        max-width: 4.5rem;
    }
}
/* BEXIA_POS_CASHIER_RESOURCE_RESPONSIVE_V5_79_79C_END */

/* BEXIA_TAX_RATE_RESOURCE_RESPONSIVE_V5_79_80C_START */
.bexia-tax-section {
    overflow: hidden;
}

.bexia-tax-section .fi-section-header,
.bexia-tax-section .fi-section-content {
    padding-left: 1rem;
    padding-right: 1rem;
}

.bexia-tax-field .fi-fo-field-wrp-label,
.bexia-tax-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-tax-field .fi-input-wrp,
.bexia-tax-field .fi-select-input,
.bexia-tax-field textarea {
    min-width: 0;
}

.bexia-tax-wide-field .fi-input-wrp,
.bexia-tax-description-field textarea {
    width: 100%;
}

.bexia-tax-compact-field .fi-input-wrp,
.bexia-tax-rate-field .fi-input-wrp,
.bexia-tax-sort-field .fi-input-wrp {
    max-width: 12rem;
}

.bexia-tax-toggle-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-tax-header,
.bexia-tax-cell {
    white-space: normal;
}

.bexia-tax-col-code,
.bexia-tax-col-factor,
.bexia-tax-col-rate,
.bexia-tax-col-sort {
    min-width: 5.5rem;
    max-width: 7.5rem;
}

.bexia-tax-col-name {
    min-width: 10rem;
    max-width: 14rem;
}

.bexia-tax-col-type,
.bexia-tax-col-badge {
    min-width: 6.5rem;
    max-width: 8.5rem;
}

.bexia-tax-col-bool {
    min-width: 5rem;
    max-width: 6rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-tax-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-tax-field {
        min-width: 0;
    }

    .bexia-tax-wide-field,
    .bexia-tax-description-field,
    .bexia-tax-full-field {
        grid-column: 1 / -1;
    }

    .bexia-tax-col-code,
    .bexia-tax-col-factor,
    .bexia-tax-col-rate,
    .bexia-tax-col-sort {
        min-width: 4.75rem;
        max-width: 6.25rem;
    }

    .bexia-tax-col-name {
        min-width: 8.5rem;
        max-width: 11rem;
    }

    .bexia-tax-col-type,
    .bexia-tax-col-badge {
        min-width: 5.5rem;
        max-width: 7rem;
    }
}

@media (max-width: 640px) {
    .bexia-tax-section .fi-section-header,
    .bexia-tax-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-tax-compact-field .fi-input-wrp,
    .bexia-tax-rate-field .fi-input-wrp,
    .bexia-tax-sort-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-tax-col-code,
    .bexia-tax-col-factor,
    .bexia-tax-col-rate,
    .bexia-tax-col-sort {
        min-width: 4rem;
        max-width: 5.25rem;
    }

    .bexia-tax-col-name {
        min-width: 7.25rem;
        max-width: 9rem;
    }

    .bexia-tax-col-type,
    .bexia-tax-col-badge {
        min-width: 4.75rem;
        max-width: 6rem;
    }

    .bexia-tax-col-bool {
        min-width: 4rem;
        max-width: 5rem;
    }
}
/* BEXIA_TAX_RATE_RESOURCE_RESPONSIVE_V5_79_80C_END */

/* BEXIA_PRODUCT_ATTRIBUTE_RESOURCE_RESPONSIVE_V5_79_81C_START */
.bexia-pattr-section {
    overflow: hidden;
}

.bexia-pattr-section .fi-section-header,
.bexia-pattr-section .fi-section-content {
    padding-left: 1rem;
    padding-right: 1rem;
}

.bexia-pattr-field .fi-fo-field-wrp-label,
.bexia-pattr-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-pattr-field .fi-input-wrp,
.bexia-pattr-field .fi-toggle,
.bexia-pattr-field input {
    min-width: 0;
}

.bexia-pattr-wide-field .fi-input-wrp {
    width: 100%;
}

.bexia-pattr-compact-field .fi-input-wrp,
.bexia-pattr-sort-field .fi-input-wrp {
    max-width: 12rem;
}

.bexia-pattr-toggle-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-pattr-header,
.bexia-pattr-cell {
    white-space: normal;
}

.bexia-pattr-col-code,
.bexia-pattr-col-sort {
    min-width: 5.25rem;
    max-width: 7rem;
}

.bexia-pattr-col-name {
    min-width: 10rem;
    max-width: 14rem;
}

.bexia-pattr-col-bool,
.bexia-pattr-col-variant,
.bexia-pattr-col-active,
.bexia-pattr-col-system {
    min-width: 5rem;
    max-width: 6.25rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-pattr-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-pattr-field {
        min-width: 0;
    }

    .bexia-pattr-wide-field {
        grid-column: 1 / -1;
    }

    .bexia-pattr-col-code,
    .bexia-pattr-col-sort {
        min-width: 4.5rem;
        max-width: 6rem;
    }

    .bexia-pattr-col-name {
        min-width: 8.5rem;
        max-width: 11rem;
    }

    .bexia-pattr-col-bool,
    .bexia-pattr-col-variant,
    .bexia-pattr-col-active,
    .bexia-pattr-col-system {
        min-width: 4.5rem;
        max-width: 5.75rem;
    }
}

@media (max-width: 640px) {
    .bexia-pattr-section .fi-section-header,
    .bexia-pattr-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-pattr-compact-field .fi-input-wrp,
    .bexia-pattr-sort-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-pattr-col-code,
    .bexia-pattr-col-sort {
        min-width: 4rem;
        max-width: 5.25rem;
    }

    .bexia-pattr-col-name {
        min-width: 7.25rem;
        max-width: 9rem;
    }

    .bexia-pattr-col-bool,
    .bexia-pattr-col-variant,
    .bexia-pattr-col-active,
    .bexia-pattr-col-system {
        min-width: 4rem;
        max-width: 5rem;
    }
}
/* BEXIA_PRODUCT_ATTRIBUTE_RESOURCE_RESPONSIVE_V5_79_81C_END */

/* BEXIA_HR_WORK_SCHEDULE_RESOURCE_RESPONSIVE_V5_79_82C_START */
.bexia-hrws-section {
    overflow: hidden;
}

.bexia-hrws-section .fi-section-header,
.bexia-hrws-section .fi-section-content {
    padding-left: 1rem;
    padding-right: 1rem;
}

.bexia-hrws-field .fi-fo-field-wrp-label,
.bexia-hrws-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-hrws-field .fi-input-wrp,
.bexia-hrws-field .fi-select-input,
.bexia-hrws-field input {
    min-width: 0;
}

.bexia-hrws-wide-field .fi-input-wrp,
.bexia-hrws-full-field .fi-fo-field-wrp {
    width: 100%;
}

.bexia-hrws-compact-field .fi-input-wrp,
.bexia-hrws-time-field .fi-input-wrp,
.bexia-hrws-hours-field .fi-input-wrp {
    max-width: 12rem;
}

.bexia-hrws-checklist-field .fi-fo-checkbox-list {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.35rem 0.75rem;
}

.bexia-hrws-header,
.bexia-hrws-cell {
    white-space: normal;
}

.bexia-hrws-col-name {
    min-width: 10rem;
    max-width: 14rem;
}

.bexia-hrws-col-code {
    min-width: 5.25rem;
    max-width: 7rem;
}

.bexia-hrws-col-type {
    min-width: 6rem;
    max-width: 8rem;
}

.bexia-hrws-col-time,
.bexia-hrws-col-hours {
    min-width: 5.75rem;
    max-width: 7.25rem;
}

.bexia-hrws-col-bool {
    min-width: 4.75rem;
    max-width: 5.75rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-hrws-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-hrws-field {
        min-width: 0;
    }

    .bexia-hrws-wide-field,
    .bexia-hrws-full-field {
        grid-column: 1 / -1;
    }

    .bexia-hrws-checklist-field .fi-fo-checkbox-list {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .bexia-hrws-col-name {
        min-width: 8.5rem;
        max-width: 11rem;
    }

    .bexia-hrws-col-code,
    .bexia-hrws-col-type {
        min-width: 4.75rem;
        max-width: 6.5rem;
    }

    .bexia-hrws-col-time,
    .bexia-hrws-col-hours {
        min-width: 5rem;
        max-width: 6.25rem;
    }

    .bexia-hrws-col-bool {
        min-width: 4.25rem;
        max-width: 5.25rem;
    }
}

@media (max-width: 640px) {
    .bexia-hrws-section .fi-section-header,
    .bexia-hrws-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-hrws-compact-field .fi-input-wrp,
    .bexia-hrws-time-field .fi-input-wrp,
    .bexia-hrws-hours-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-hrws-checklist-field .fi-fo-checkbox-list {
        grid-template-columns: 1fr;
    }

    .bexia-hrws-col-name {
        min-width: 7.25rem;
        max-width: 9rem;
    }

    .bexia-hrws-col-code,
    .bexia-hrws-col-type,
    .bexia-hrws-col-time,
    .bexia-hrws-col-hours {
        min-width: 4rem;
        max-width: 5.25rem;
    }

    .bexia-hrws-col-bool {
        min-width: 3.75rem;
        max-width: 4.75rem;
    }
}
/* BEXIA_HR_WORK_SCHEDULE_RESOURCE_RESPONSIVE_V5_79_82C_END */

/* BEXIA_SAT_PRODUCT_SERVICE_CODE_RESOURCE_RESPONSIVE_V5_79_83C_START */
.bexia-spsc-section {
    overflow: hidden;
}

.bexia-spsc-section .fi-section-header,
.bexia-spsc-section .fi-section-content {
    padding-left: 1rem;
    padding-right: 1rem;
}

.bexia-spsc-field .fi-fo-field-wrp-label,
.bexia-spsc-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-spsc-field .fi-input-wrp,
.bexia-spsc-field .fi-textarea,
.bexia-spsc-field input,
.bexia-spsc-field textarea {
    min-width: 0;
}

.bexia-spsc-wide-field .fi-fo-field-wrp,
.bexia-spsc-description-field .fi-input-wrp,
.bexia-spsc-textarea-field .fi-input-wrp {
    width: 100%;
}

.bexia-spsc-compact-field .fi-input-wrp,
.bexia-spsc-date-field .fi-input-wrp {
    max-width: 12rem;
}

.bexia-spsc-toggle-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-spsc-header,
.bexia-spsc-cell {
    white-space: normal;
}

.bexia-spsc-col-code {
    min-width: 5.25rem;
    max-width: 7rem;
}

.bexia-spsc-col-description {
    min-width: 14rem;
    max-width: 22rem;
}

.bexia-spsc-col-bool {
    min-width: 4.75rem;
    max-width: 5.75rem;
    text-align: center;
}

.bexia-spsc-col-date {
    min-width: 5.75rem;
    max-width: 7.25rem;
}

@media (max-width: 1024px) {
    .bexia-spsc-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-spsc-field {
        min-width: 0;
    }

    .bexia-spsc-wide-field,
    .bexia-spsc-description-field,
    .bexia-spsc-textarea-field {
        grid-column: 1 / -1;
    }

    .bexia-spsc-col-code {
        min-width: 4.75rem;
        max-width: 6.25rem;
    }

    .bexia-spsc-col-description {
        min-width: 10rem;
        max-width: 14rem;
    }

    .bexia-spsc-col-bool {
        min-width: 4.25rem;
        max-width: 5.25rem;
    }

    .bexia-spsc-col-date {
        min-width: 5rem;
        max-width: 6.25rem;
    }
}

@media (max-width: 640px) {
    .bexia-spsc-section .fi-section-header,
    .bexia-spsc-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-spsc-compact-field .fi-input-wrp,
    .bexia-spsc-date-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-spsc-col-code,
    .bexia-spsc-col-bool,
    .bexia-spsc-col-date {
        min-width: 4rem;
        max-width: 5.25rem;
    }

    .bexia-spsc-col-description {
        min-width: 8rem;
        max-width: 11rem;
    }
}
/* BEXIA_SAT_PRODUCT_SERVICE_CODE_RESOURCE_RESPONSIVE_V5_79_83C_END */

/* BEXIA_SAT_BILLING_CATALOG_ITEM_RESOURCE_RESPONSIVE_V5_79_84C_START */
.bexia-sbci-section {
    overflow: hidden;
}

.bexia-sbci-section .fi-section-header,
.bexia-sbci-section .fi-section-content {
    padding-left: 1rem;
    padding-right: 1rem;
}

.bexia-sbci-field .fi-fo-field-wrp-label,
.bexia-sbci-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-sbci-field .fi-input-wrp,
.bexia-sbci-field .fi-textarea,
.bexia-sbci-field input,
.bexia-sbci-field textarea {
    min-width: 0;
}

.bexia-sbci-wide-field .fi-fo-field-wrp,
.bexia-sbci-name-field .fi-input-wrp,
.bexia-sbci-description-field .fi-input-wrp,
.bexia-sbci-textarea-field .fi-input-wrp {
    width: 100%;
}

.bexia-sbci-compact-field .fi-input-wrp,
.bexia-sbci-medium-field .fi-input-wrp,
.bexia-sbci-date-field .fi-input-wrp {
    max-width: 12rem;
}

.bexia-sbci-toggle-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-sbci-header,
.bexia-sbci-cell {
    white-space: normal;
}

.bexia-sbci-col-catalog {
    min-width: 8rem;
    max-width: 12rem;
}

.bexia-sbci-col-code,
.bexia-sbci-col-sheet {
    min-width: 5.25rem;
    max-width: 7.5rem;
}

.bexia-sbci-col-name {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-sbci-col-description {
    min-width: 14rem;
    max-width: 22rem;
}

.bexia-sbci-col-date {
    min-width: 5.75rem;
    max-width: 7.25rem;
}

.bexia-sbci-col-bool {
    min-width: 4.75rem;
    max-width: 5.75rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-sbci-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-sbci-field {
        min-width: 0;
    }

    .bexia-sbci-wide-field,
    .bexia-sbci-description-field,
    .bexia-sbci-textarea-field {
        grid-column: 1 / -1;
    }

    .bexia-sbci-col-catalog,
    .bexia-sbci-col-name {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-sbci-col-code,
    .bexia-sbci-col-sheet,
    .bexia-sbci-col-date {
        min-width: 4.75rem;
        max-width: 6.25rem;
    }

    .bexia-sbci-col-description {
        min-width: 10rem;
        max-width: 14rem;
    }

    .bexia-sbci-col-bool {
        min-width: 4.25rem;
        max-width: 5.25rem;
    }
}

@media (max-width: 640px) {
    .bexia-sbci-section .fi-section-header,
    .bexia-sbci-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-sbci-compact-field .fi-input-wrp,
    .bexia-sbci-medium-field .fi-input-wrp,
    .bexia-sbci-date-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-sbci-col-catalog,
    .bexia-sbci-col-code,
    .bexia-sbci-col-sheet,
    .bexia-sbci-col-date,
    .bexia-sbci-col-bool {
        min-width: 4rem;
        max-width: 5.5rem;
    }

    .bexia-sbci-col-name,
    .bexia-sbci-col-description {
        min-width: 8rem;
        max-width: 11rem;
    }
}
/* BEXIA_SAT_BILLING_CATALOG_ITEM_RESOURCE_RESPONSIVE_V5_79_84C_END */

/* BEXIA_SAT_UNIT_CODE_RESOURCE_RESPONSIVE_V5_79_85C_START */
.bexia-suc-section {
    overflow: hidden;
}

.bexia-suc-section .fi-section-header,
.bexia-suc-section .fi-section-content {
    padding-left: 1rem;
    padding-right: 1rem;
}

.bexia-suc-field .fi-fo-field-wrp-label,
.bexia-suc-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-suc-field .fi-input-wrp,
.bexia-suc-field .fi-textarea,
.bexia-suc-field input,
.bexia-suc-field textarea {
    min-width: 0;
}

.bexia-suc-wide-field .fi-fo-field-wrp,
.bexia-suc-name-field .fi-input-wrp,
.bexia-suc-description-field .fi-input-wrp,
.bexia-suc-note-field .fi-input-wrp,
.bexia-suc-textarea-field .fi-input-wrp {
    width: 100%;
}

.bexia-suc-compact-field .fi-input-wrp,
.bexia-suc-date-field .fi-input-wrp {
    max-width: 12rem;
}

.bexia-suc-toggle-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-suc-header,
.bexia-suc-cell {
    white-space: normal;
}

.bexia-suc-col-code,
.bexia-suc-col-symbol {
    min-width: 5.25rem;
    max-width: 7rem;
}

.bexia-suc-col-name {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-suc-col-description {
    min-width: 14rem;
    max-width: 22rem;
}

.bexia-suc-col-date {
    min-width: 5.75rem;
    max-width: 7.25rem;
}

.bexia-suc-col-bool {
    min-width: 4.75rem;
    max-width: 5.75rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-suc-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-suc-field {
        min-width: 0;
    }

    .bexia-suc-wide-field,
    .bexia-suc-description-field,
    .bexia-suc-note-field,
    .bexia-suc-textarea-field {
        grid-column: 1 / -1;
    }

    .bexia-suc-col-code,
    .bexia-suc-col-symbol,
    .bexia-suc-col-date {
        min-width: 4.75rem;
        max-width: 6.25rem;
    }

    .bexia-suc-col-name {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-suc-col-description {
        min-width: 10rem;
        max-width: 14rem;
    }

    .bexia-suc-col-bool {
        min-width: 4.25rem;
        max-width: 5.25rem;
    }
}

@media (max-width: 640px) {
    .bexia-suc-section .fi-section-header,
    .bexia-suc-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-suc-compact-field .fi-input-wrp,
    .bexia-suc-date-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-suc-col-code,
    .bexia-suc-col-symbol,
    .bexia-suc-col-date,
    .bexia-suc-col-bool {
        min-width: 4rem;
        max-width: 5.25rem;
    }

    .bexia-suc-col-name,
    .bexia-suc-col-description {
        min-width: 8rem;
        max-width: 11rem;
    }
}
/* BEXIA_SAT_UNIT_CODE_RESOURCE_RESPONSIVE_V5_79_85C_END */

/* BEXIA_COMPANY_GROUP_RESOURCE_RESPONSIVE_V5_79_86C_START */
.bexia-cgr-form {
    min-width: 0;
}

.bexia-cgr-form .fi-fo-component-ctn {
    min-width: 0;
}

.bexia-cgr-field .fi-fo-field-wrp-label,
.bexia-cgr-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-cgr-field .fi-input-wrp,
.bexia-cgr-field .fi-select-input,
.bexia-cgr-field input {
    min-width: 0;
}

.bexia-cgr-wide-field .fi-fo-field-wrp,
.bexia-cgr-organization-field .fi-input-wrp,
.bexia-cgr-name-field .fi-input-wrp,
.bexia-cgr-slug-field .fi-input-wrp,
.bexia-cgr-admins-field .fi-input-wrp {
    width: 100%;
}

.bexia-cgr-number-field .fi-input-wrp {
    max-width: 10rem;
}

.bexia-cgr-toggle-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-cgr-header,
.bexia-cgr-cell {
    white-space: normal;
}

.bexia-cgr-col-organization,
.bexia-cgr-col-name {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-cgr-col-limit,
.bexia-cgr-col-number {
    min-width: 5.75rem;
    max-width: 7.5rem;
    text-align: right;
}

.bexia-cgr-col-bool,
.bexia-cgr-col-free-trial,
.bexia-cgr-col-active {
    min-width: 5rem;
    max-width: 7rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-cgr-form .fi-fo-component-ctn {
        gap: 0.75rem;
    }

    .bexia-cgr-wide-field,
    .bexia-cgr-organization-field,
    .bexia-cgr-name-field,
    .bexia-cgr-slug-field,
    .bexia-cgr-admins-field {
        grid-column: 1 / -1;
    }

    .bexia-cgr-col-organization,
    .bexia-cgr-col-name {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-cgr-col-limit,
    .bexia-cgr-col-number {
        min-width: 5rem;
        max-width: 6.5rem;
    }

    .bexia-cgr-col-bool,
    .bexia-cgr-col-free-trial,
    .bexia-cgr-col-active {
        min-width: 4.5rem;
        max-width: 6rem;
    }
}

@media (max-width: 640px) {
    .bexia-cgr-form .fi-fo-component-ctn {
        grid-template-columns: minmax(0, 1fr);
    }

    .bexia-cgr-number-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-cgr-col-organization,
    .bexia-cgr-col-name {
        min-width: 8rem;
        max-width: 10rem;
    }

    .bexia-cgr-col-limit,
    .bexia-cgr-col-number,
    .bexia-cgr-col-bool,
    .bexia-cgr-col-free-trial,
    .bexia-cgr-col-active {
        min-width: 4.25rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_COMPANY_GROUP_RESOURCE_RESPONSIVE_V5_79_86C_END */

/* BEXIA_CURRENCY_RESOURCE_RESPONSIVE_V5_79_87C_START */
.bexia-cur-form {
    min-width: 0;
}

.bexia-cur-form .fi-fo-component-ctn,
.bexia-cur-section .fi-section-content {
    min-width: 0;
}

.bexia-cur-field .fi-fo-field-wrp-label,
.bexia-cur-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-cur-field .fi-input-wrp,
.bexia-cur-field .fi-select-input,
.bexia-cur-field input {
    min-width: 0;
}

.bexia-cur-wide-field .fi-fo-field-wrp,
.bexia-cur-company-field .fi-input-wrp,
.bexia-cur-name-field .fi-input-wrp {
    width: 100%;
}

.bexia-cur-compact-field .fi-input-wrp,
.bexia-cur-symbol-field .fi-input-wrp {
    max-width: 9rem;
}

.bexia-cur-number-field .fi-input-wrp,
.bexia-cur-rate-field .fi-input-wrp,
.bexia-cur-sort-field .fi-input-wrp {
    max-width: 10rem;
}

.bexia-cur-toggle-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-cur-header,
.bexia-cur-cell {
    white-space: normal;
}

.bexia-cur-col-code,
.bexia-cur-col-symbol {
    min-width: 5rem;
    max-width: 7rem;
}

.bexia-cur-col-name {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-cur-col-rate,
.bexia-cur-col-number {
    min-width: 6.5rem;
    max-width: 8.5rem;
    text-align: right;
}

.bexia-cur-col-bool,
.bexia-cur-col-default,
.bexia-cur-col-active {
    min-width: 4.75rem;
    max-width: 6.25rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-cur-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-cur-wide-field,
    .bexia-cur-company-field,
    .bexia-cur-name-field {
        grid-column: 1 / -1;
    }

    .bexia-cur-col-code,
    .bexia-cur-col-symbol {
        min-width: 4.75rem;
        max-width: 6.25rem;
    }

    .bexia-cur-col-name {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-cur-col-rate,
    .bexia-cur-col-number {
        min-width: 5.75rem;
        max-width: 7rem;
    }

    .bexia-cur-col-bool,
    .bexia-cur-col-default,
    .bexia-cur-col-active {
        min-width: 4.25rem;
        max-width: 5.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-cur-section .fi-section-header,
    .bexia-cur-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-cur-compact-field .fi-input-wrp,
    .bexia-cur-number-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-cur-col-code,
    .bexia-cur-col-symbol,
    .bexia-cur-col-rate,
    .bexia-cur-col-number,
    .bexia-cur-col-bool,
    .bexia-cur-col-default,
    .bexia-cur-col-active {
        min-width: 4rem;
        max-width: 5.5rem;
    }

    .bexia-cur-col-name {
        min-width: 8rem;
        max-width: 10.5rem;
    }
}
/* BEXIA_CURRENCY_RESOURCE_RESPONSIVE_V5_79_87C_END */

/* BEXIA_HR_INCIDENT_TYPE_RESOURCE_RESPONSIVE_V5_79_88C_START */
.bexia-hit-form {
    min-width: 0;
}

.bexia-hit-form .fi-fo-component-ctn,
.bexia-hit-section .fi-section-content {
    min-width: 0;
}

.bexia-hit-field .fi-fo-field-wrp-label,
.bexia-hit-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-hit-field .fi-input-wrp,
.bexia-hit-field .fi-select-input,
.bexia-hit-field input {
    min-width: 0;
}

.bexia-hit-wide-field .fi-fo-field-wrp,
.bexia-hit-name-field .fi-input-wrp {
    width: 100%;
}

.bexia-hit-compact-field .fi-input-wrp,
.bexia-hit-code-field .fi-input-wrp {
    max-width: 9rem;
}

.bexia-hit-select-field .fi-input-wrp,
.bexia-hit-effect-field .fi-input-wrp {
    min-width: 0;
}

.bexia-hit-toggle-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-hit-header,
.bexia-hit-cell {
    white-space: normal;
}

.bexia-hit-col-name {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-hit-col-code {
    min-width: 5rem;
    max-width: 7rem;
}

.bexia-hit-col-effect,
.bexia-hit-col-badge {
    min-width: 7rem;
    max-width: 9rem;
}

.bexia-hit-col-bool,
.bexia-hit-col-approval,
.bexia-hit-col-payroll,
.bexia-hit-col-active {
    min-width: 5rem;
    max-width: 7rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-hit-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-hit-wide-field,
    .bexia-hit-name-field {
        grid-column: 1 / -1;
    }

    .bexia-hit-col-name {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-hit-col-code,
    .bexia-hit-col-effect,
    .bexia-hit-col-badge {
        min-width: 5.5rem;
        max-width: 7.5rem;
    }

    .bexia-hit-col-bool,
    .bexia-hit-col-approval,
    .bexia-hit-col-payroll,
    .bexia-hit-col-active {
        min-width: 4.5rem;
        max-width: 6rem;
    }
}

@media (max-width: 640px) {
    .bexia-hit-section .fi-section-header,
    .bexia-hit-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-hit-compact-field .fi-input-wrp,
    .bexia-hit-select-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-hit-col-name {
        min-width: 8rem;
        max-width: 10.5rem;
    }

    .bexia-hit-col-code,
    .bexia-hit-col-effect,
    .bexia-hit-col-badge,
    .bexia-hit-col-bool,
    .bexia-hit-col-approval,
    .bexia-hit-col-payroll,
    .bexia-hit-col-active {
        min-width: 4rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_HR_INCIDENT_TYPE_RESOURCE_RESPONSIVE_V5_79_88C_END */

/* BEXIA_HR_DEPARTMENT_RESOURCE_RESPONSIVE_V5_79_89C_START */
.bexia-hdp-form {
    min-width: 0;
}

.bexia-hdp-form .fi-fo-component-ctn,
.bexia-hdp-section .fi-section-content {
    min-width: 0;
}

.bexia-hdp-field .fi-fo-field-wrp-label,
.bexia-hdp-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-hdp-field .fi-input-wrp,
.bexia-hdp-field .fi-select-input,
.bexia-hdp-field textarea,
.bexia-hdp-field input {
    min-width: 0;
}

.bexia-hdp-wide-field .fi-fo-field-wrp,
.bexia-hdp-name-field .fi-input-wrp,
.bexia-hdp-description-field .fi-fo-field-wrp {
    width: 100%;
}

.bexia-hdp-compact-field .fi-input-wrp,
.bexia-hdp-code-field .fi-input-wrp {
    max-width: 9rem;
}

.bexia-hdp-select-field .fi-input-wrp,
.bexia-hdp-parent-field .fi-input-wrp,
.bexia-hdp-manager-field .fi-input-wrp {
    min-width: 0;
}

.bexia-hdp-toggle-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-hdp-header,
.bexia-hdp-cell {
    white-space: normal;
}

.bexia-hdp-col-name {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-hdp-col-code {
    min-width: 5rem;
    max-width: 7rem;
}

.bexia-hdp-col-parent,
.bexia-hdp-col-manager {
    min-width: 9rem;
    max-width: 13rem;
}

.bexia-hdp-col-date,
.bexia-hdp-col-updated {
    min-width: 7.5rem;
    max-width: 9.5rem;
}

.bexia-hdp-col-bool,
.bexia-hdp-col-active {
    min-width: 4.75rem;
    max-width: 6rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-hdp-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-hdp-wide-field,
    .bexia-hdp-name-field,
    .bexia-hdp-description-field {
        grid-column: 1 / -1;
    }

    .bexia-hdp-col-name {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-hdp-col-code,
    .bexia-hdp-col-parent,
    .bexia-hdp-col-manager {
        min-width: 5.5rem;
        max-width: 8rem;
    }

    .bexia-hdp-col-date,
    .bexia-hdp-col-updated {
        min-width: 6.5rem;
        max-width: 8rem;
    }

    .bexia-hdp-col-bool,
    .bexia-hdp-col-active {
        min-width: 4.25rem;
        max-width: 5.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-hdp-section .fi-section-header,
    .bexia-hdp-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-hdp-compact-field .fi-input-wrp,
    .bexia-hdp-select-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-hdp-col-name {
        min-width: 8rem;
        max-width: 10.5rem;
    }

    .bexia-hdp-col-code,
    .bexia-hdp-col-parent,
    .bexia-hdp-col-manager,
    .bexia-hdp-col-date,
    .bexia-hdp-col-updated,
    .bexia-hdp-col-bool,
    .bexia-hdp-col-active {
        min-width: 4rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_HR_DEPARTMENT_RESOURCE_RESPONSIVE_V5_79_89C_END */

/* BEXIA_HR_JOB_POSITION_RESOURCE_RESPONSIVE_V5_79_90C_START */
.bexia-hjp-form {
    min-width: 0;
}

.bexia-hjp-form .fi-fo-component-ctn,
.bexia-hjp-section .fi-section-content {
    min-width: 0;
}

.bexia-hjp-field .fi-fo-field-wrp-label,
.bexia-hjp-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-hjp-field .fi-input-wrp,
.bexia-hjp-field .fi-select-input,
.bexia-hjp-field textarea,
.bexia-hjp-field input {
    min-width: 0;
}

.bexia-hjp-wide-field .fi-fo-field-wrp,
.bexia-hjp-name-field .fi-input-wrp,
.bexia-hjp-description-field .fi-fo-field-wrp {
    width: 100%;
}

.bexia-hjp-compact-field .fi-input-wrp,
.bexia-hjp-code-field .fi-input-wrp,
.bexia-hjp-level-field .fi-input-wrp {
    max-width: 9rem;
}

.bexia-hjp-select-field .fi-input-wrp,
.bexia-hjp-department-field .fi-input-wrp {
    min-width: 0;
}

.bexia-hjp-toggle-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-hjp-header,
.bexia-hjp-cell {
    white-space: normal;
}

.bexia-hjp-col-name {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-hjp-col-code,
.bexia-hjp-col-level {
    min-width: 5rem;
    max-width: 7rem;
}

.bexia-hjp-col-department {
    min-width: 9rem;
    max-width: 13rem;
}

.bexia-hjp-col-date,
.bexia-hjp-col-updated {
    min-width: 7.5rem;
    max-width: 9.5rem;
}

.bexia-hjp-col-bool,
.bexia-hjp-col-active {
    min-width: 4.75rem;
    max-width: 6rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-hjp-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-hjp-wide-field,
    .bexia-hjp-name-field,
    .bexia-hjp-description-field {
        grid-column: 1 / -1;
    }

    .bexia-hjp-col-name {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-hjp-col-code,
    .bexia-hjp-col-level,
    .bexia-hjp-col-department {
        min-width: 5.5rem;
        max-width: 8rem;
    }

    .bexia-hjp-col-date,
    .bexia-hjp-col-updated {
        min-width: 6.5rem;
        max-width: 8rem;
    }

    .bexia-hjp-col-bool,
    .bexia-hjp-col-active {
        min-width: 4.25rem;
        max-width: 5.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-hjp-section .fi-section-header,
    .bexia-hjp-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-hjp-compact-field .fi-input-wrp,
    .bexia-hjp-select-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-hjp-col-name {
        min-width: 8rem;
        max-width: 10.5rem;
    }

    .bexia-hjp-col-code,
    .bexia-hjp-col-level,
    .bexia-hjp-col-department,
    .bexia-hjp-col-date,
    .bexia-hjp-col-updated,
    .bexia-hjp-col-bool,
    .bexia-hjp-col-active {
        min-width: 4rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_HR_JOB_POSITION_RESOURCE_RESPONSIVE_V5_79_90C_END */

/* BEXIA_AI_INSIGHT_USER_ACCESS_RESOURCE_RESPONSIVE_V5_79_91C_START */
.bexia-aiu-form,
.bexia-aiu-shell {
    min-width: 0;
}

.bexia-aiu-form .fi-fo-component-ctn,
.bexia-aiu-section .fi-section-content {
    min-width: 0;
}

.bexia-aiu-section .fi-section-header-description,
.bexia-aiu-field .fi-fo-field-wrp-label,
.bexia-aiu-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-aiu-field .fi-input-wrp,
.bexia-aiu-field .fi-select-input,
.bexia-aiu-field textarea,
.bexia-aiu-field input {
    min-width: 0;
}

.bexia-aiu-wide-field .fi-fo-field-wrp,
.bexia-aiu-user-field .fi-input-wrp,
.bexia-aiu-notes-field .fi-fo-field-wrp {
    width: 100%;
}

.bexia-aiu-select-field .fi-input-wrp,
.bexia-aiu-user-field .fi-input-wrp,
.bexia-aiu-access-level-field .fi-input-wrp {
    min-width: 0;
}

.bexia-aiu-toggle-field .fi-fo-field-wrp,
.bexia-aiu-enabled-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-aiu-header,
.bexia-aiu-cell {
    white-space: normal;
}

.bexia-aiu-col-id,
.bexia-aiu-col-compact {
    min-width: 4.5rem;
    max-width: 6rem;
}

.bexia-aiu-col-user {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-aiu-col-email {
    min-width: 13rem;
    max-width: 18rem;
}

.bexia-aiu-col-bool,
.bexia-aiu-col-enabled {
    min-width: 4.75rem;
    max-width: 6rem;
    text-align: center;
}

.bexia-aiu-col-badge,
.bexia-aiu-col-access-level {
    min-width: 7rem;
    max-width: 9.5rem;
}

.bexia-aiu-col-date,
.bexia-aiu-col-updated {
    min-width: 7.5rem;
    max-width: 9.5rem;
}

.bexia-aiu-col-relation,
.bexia-aiu-col-updated-by {
    min-width: 9rem;
    max-width: 13rem;
}

@media (max-width: 1024px) {
    .bexia-aiu-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-aiu-wide-field,
    .bexia-aiu-user-field,
    .bexia-aiu-notes-field {
        grid-column: 1 / -1;
    }

    .bexia-aiu-col-user,
    .bexia-aiu-col-email,
    .bexia-aiu-col-updated-by {
        min-width: 8.5rem;
        max-width: 12rem;
    }

    .bexia-aiu-col-badge,
    .bexia-aiu-col-access-level,
    .bexia-aiu-col-date,
    .bexia-aiu-col-updated {
        min-width: 6.5rem;
        max-width: 8rem;
    }

    .bexia-aiu-col-id,
    .bexia-aiu-col-compact,
    .bexia-aiu-col-bool,
    .bexia-aiu-col-enabled {
        min-width: 4.25rem;
        max-width: 5.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-aiu-section .fi-section-header,
    .bexia-aiu-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-aiu-select-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-aiu-col-user,
    .bexia-aiu-col-email,
    .bexia-aiu-col-updated-by {
        min-width: 8rem;
        max-width: 10.5rem;
    }

    .bexia-aiu-col-id,
    .bexia-aiu-col-compact,
    .bexia-aiu-col-badge,
    .bexia-aiu-col-access-level,
    .bexia-aiu-col-date,
    .bexia-aiu-col-updated,
    .bexia-aiu-col-bool,
    .bexia-aiu-col-enabled {
        min-width: 4rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_AI_INSIGHT_USER_ACCESS_RESOURCE_RESPONSIVE_V5_79_91C_END */

/* BEXIA_CASH_DENOMINATION_RESOURCE_RESPONSIVE_V5_79_92C_START */
.bexia-cdn-form,
.bexia-cdn-shell {
    min-width: 0;
}

.bexia-cdn-form .fi-fo-component-ctn,
.bexia-cdn-section .fi-section-content {
    min-width: 0;
}

.bexia-cdn-section .fi-section-header-description,
.bexia-cdn-field .fi-fo-field-wrp-label,
.bexia-cdn-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-cdn-field .fi-input-wrp,
.bexia-cdn-field .fi-select-input,
.bexia-cdn-field textarea,
.bexia-cdn-field input {
    min-width: 0;
}

.bexia-cdn-wide-field .fi-fo-field-wrp,
.bexia-cdn-name-field .fi-input-wrp,
.bexia-cdn-company-field .fi-input-wrp {
    width: 100%;
}

.bexia-cdn-select-field .fi-input-wrp,
.bexia-cdn-company-field .fi-input-wrp,
.bexia-cdn-currency-field .fi-input-wrp,
.bexia-cdn-type-field .fi-input-wrp {
    min-width: 0;
}

.bexia-cdn-compact-field .fi-input-wrp,
.bexia-cdn-value-field .fi-input-wrp,
.bexia-cdn-sort-order-field .fi-input-wrp {
    max-width: 9rem;
}

.bexia-cdn-toggle-field .fi-fo-field-wrp,
.bexia-cdn-active-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-cdn-header,
.bexia-cdn-cell {
    white-space: normal;
}

.bexia-cdn-col-name {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-cdn-col-value,
.bexia-cdn-col-amount {
    min-width: 6rem;
    max-width: 8rem;
}

.bexia-cdn-col-type,
.bexia-cdn-col-badge {
    min-width: 6.5rem;
    max-width: 8.5rem;
}

.bexia-cdn-col-currency {
    min-width: 9rem;
    max-width: 13rem;
}

.bexia-cdn-col-bool,
.bexia-cdn-col-active {
    min-width: 4.75rem;
    max-width: 6rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-cdn-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-cdn-wide-field,
    .bexia-cdn-name-field,
    .bexia-cdn-company-field {
        grid-column: 1 / -1;
    }

    .bexia-cdn-col-name,
    .bexia-cdn-col-currency {
        min-width: 8.5rem;
        max-width: 11rem;
    }

    .bexia-cdn-col-value,
    .bexia-cdn-col-amount,
    .bexia-cdn-col-type,
    .bexia-cdn-col-badge {
        min-width: 5.5rem;
        max-width: 7.5rem;
    }

    .bexia-cdn-col-bool,
    .bexia-cdn-col-active {
        min-width: 4.25rem;
        max-width: 5.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-cdn-section .fi-section-header,
    .bexia-cdn-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-cdn-select-field .fi-input-wrp,
    .bexia-cdn-compact-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-cdn-col-name,
    .bexia-cdn-col-currency {
        min-width: 8rem;
        max-width: 10.5rem;
    }

    .bexia-cdn-col-value,
    .bexia-cdn-col-amount,
    .bexia-cdn-col-type,
    .bexia-cdn-col-badge,
    .bexia-cdn-col-bool,
    .bexia-cdn-col-active {
        min-width: 4rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_CASH_DENOMINATION_RESOURCE_RESPONSIVE_V5_79_92C_END */

/* BEXIA_HR_DOCUMENT_TYPE_RESOURCE_RESPONSIVE_V5_79_93C_START */
.bexia-hdt-form,
.bexia-hdt-shell {
    min-width: 0;
}

.bexia-hdt-form .fi-fo-component-ctn,
.bexia-hdt-section .fi-section-content {
    min-width: 0;
}

.bexia-hdt-section .fi-section-header-description,
.bexia-hdt-field .fi-fo-field-wrp-label,
.bexia-hdt-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-hdt-field .fi-input-wrp,
.bexia-hdt-field .fi-select-input,
.bexia-hdt-field textarea,
.bexia-hdt-field input {
    min-width: 0;
}

.bexia-hdt-wide-field .fi-fo-field-wrp,
.bexia-hdt-name-field .fi-input-wrp {
    width: 100%;
}

.bexia-hdt-compact-field .fi-input-wrp,
.bexia-hdt-code-field .fi-input-wrp {
    max-width: 10rem;
}

.bexia-hdt-toggle-field .fi-fo-field-wrp,
.bexia-hdt-required-field .fi-fo-field-wrp,
.bexia-hdt-active-field .fi-fo-field-wrp,
.bexia-hdt-expiration-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-hdt-header,
.bexia-hdt-cell {
    white-space: normal;
}

.bexia-hdt-col-name {
    min-width: 9.5rem;
    max-width: 14rem;
}

.bexia-hdt-col-code,
.bexia-hdt-col-compact {
    min-width: 6rem;
    max-width: 8.5rem;
}

.bexia-hdt-col-bool,
.bexia-hdt-col-expiration,
.bexia-hdt-col-required,
.bexia-hdt-col-active {
    min-width: 5rem;
    max-width: 7rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-hdt-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-hdt-wide-field,
    .bexia-hdt-name-field {
        grid-column: 1 / -1;
    }

    .bexia-hdt-col-name {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-hdt-col-code,
    .bexia-hdt-col-compact {
        min-width: 5.5rem;
        max-width: 7.5rem;
    }

    .bexia-hdt-col-bool,
    .bexia-hdt-col-expiration,
    .bexia-hdt-col-required,
    .bexia-hdt-col-active {
        min-width: 4.5rem;
        max-width: 6rem;
    }
}

@media (max-width: 640px) {
    .bexia-hdt-section .fi-section-header,
    .bexia-hdt-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-hdt-compact-field .fi-input-wrp,
    .bexia-hdt-code-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-hdt-col-name {
        min-width: 8rem;
        max-width: 10.5rem;
    }

    .bexia-hdt-col-code,
    .bexia-hdt-col-compact,
    .bexia-hdt-col-bool,
    .bexia-hdt-col-expiration,
    .bexia-hdt-col-required,
    .bexia-hdt-col-active {
        min-width: 4rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_HR_DOCUMENT_TYPE_RESOURCE_RESPONSIVE_V5_79_93C_END */

/* BEXIA_PAYMENT_TERM_RESOURCE_RESPONSIVE_V5_79_94C_START */
.bexia-ptr-form,
.bexia-ptr-shell {
    min-width: 0;
}

.bexia-ptr-form .fi-fo-component-ctn,
.bexia-ptr-section .fi-section-content {
    min-width: 0;
}

.bexia-ptr-section .fi-section-header-description,
.bexia-ptr-field .fi-fo-field-wrp-label,
.bexia-ptr-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-ptr-field .fi-input-wrp,
.bexia-ptr-field .fi-select-input,
.bexia-ptr-field textarea,
.bexia-ptr-field input {
    min-width: 0;
}

.bexia-ptr-wide-field .fi-fo-field-wrp,
.bexia-ptr-name-field .fi-input-wrp,
.bexia-ptr-description-field .fi-fo-field-wrp {
    width: 100%;
}

.bexia-ptr-compact-field .fi-input-wrp,
.bexia-ptr-code-field .fi-input-wrp,
.bexia-ptr-days-field .fi-input-wrp {
    max-width: 10rem;
}

.bexia-ptr-textarea-field textarea {
    width: 100%;
}

.bexia-ptr-toggle-field .fi-fo-field-wrp,
.bexia-ptr-active-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-ptr-header,
.bexia-ptr-cell {
    white-space: normal;
}

.bexia-ptr-col-name {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-ptr-col-code,
.bexia-ptr-col-compact {
    min-width: 6.5rem;
    max-width: 9rem;
}

.bexia-ptr-col-days,
.bexia-ptr-col-amount {
    min-width: 5.5rem;
    max-width: 7.5rem;
}

.bexia-ptr-col-bool,
.bexia-ptr-col-active {
    min-width: 4.75rem;
    max-width: 6rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-ptr-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-ptr-wide-field,
    .bexia-ptr-name-field,
    .bexia-ptr-description-field {
        grid-column: 1 / -1;
    }

    .bexia-ptr-col-name {
        min-width: 8.5rem;
        max-width: 11.5rem;
    }

    .bexia-ptr-col-code,
    .bexia-ptr-col-compact,
    .bexia-ptr-col-days,
    .bexia-ptr-col-amount {
        min-width: 5.5rem;
        max-width: 7.5rem;
    }

    .bexia-ptr-col-bool,
    .bexia-ptr-col-active {
        min-width: 4.25rem;
        max-width: 5.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-ptr-section .fi-section-header,
    .bexia-ptr-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-ptr-compact-field .fi-input-wrp,
    .bexia-ptr-code-field .fi-input-wrp,
    .bexia-ptr-days-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-ptr-textarea-field textarea {
        min-width: 100%;
    }

    .bexia-ptr-col-name {
        min-width: 8rem;
        max-width: 10.5rem;
    }

    .bexia-ptr-col-code,
    .bexia-ptr-col-compact,
    .bexia-ptr-col-days,
    .bexia-ptr-col-amount,
    .bexia-ptr-col-bool,
    .bexia-ptr-col-active {
        min-width: 4rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_PAYMENT_TERM_RESOURCE_RESPONSIVE_V5_79_94C_END */

/* BEXIA_PAYROLL_EMPLOYER_REGISTRATION_RESOURCE_RESPONSIVE_V5_79_95C_START */
.bexia-perg-form,
.bexia-perg-shell {
    min-width: 0;
}

.bexia-perg-form .fi-fo-component-ctn,
.bexia-perg-section .fi-section-content {
    min-width: 0;
}

.bexia-perg-section .fi-section-header-description,
.bexia-perg-field .fi-fo-field-wrp-label,
.bexia-perg-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-perg-field .fi-input-wrp,
.bexia-perg-field .fi-select-input,
.bexia-perg-field textarea,
.bexia-perg-field input {
    min-width: 0;
}

.bexia-perg-wide-field .fi-fo-field-wrp,
.bexia-perg-name-field .fi-input-wrp,
.bexia-perg-registration-field .fi-input-wrp {
    width: 100%;
}

.bexia-perg-compact-field .fi-input-wrp,
.bexia-perg-risk-field .fi-input-wrp,
.bexia-perg-state-field .fi-input-wrp {
    max-width: 10rem;
}

.bexia-perg-toggle-field .fi-fo-field-wrp,
.bexia-perg-active-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-perg-header,
.bexia-perg-cell {
    white-space: normal;
}

.bexia-perg-col-name {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-perg-col-registration {
    min-width: 9rem;
    max-width: 12rem;
}

.bexia-perg-col-risk,
.bexia-perg-col-state,
.bexia-perg-col-compact {
    min-width: 6rem;
    max-width: 8.5rem;
}

.bexia-perg-col-bool,
.bexia-perg-col-active {
    min-width: 4.75rem;
    max-width: 6rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-perg-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-perg-wide-field,
    .bexia-perg-name-field,
    .bexia-perg-registration-field {
        grid-column: 1 / -1;
    }

    .bexia-perg-col-name,
    .bexia-perg-col-registration {
        min-width: 8.5rem;
        max-width: 11.5rem;
    }

    .bexia-perg-col-risk,
    .bexia-perg-col-state,
    .bexia-perg-col-compact {
        min-width: 5.5rem;
        max-width: 7.5rem;
    }

    .bexia-perg-col-bool,
    .bexia-perg-col-active {
        min-width: 4.25rem;
        max-width: 5.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-perg-section .fi-section-header,
    .bexia-perg-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-perg-compact-field .fi-input-wrp,
    .bexia-perg-risk-field .fi-input-wrp,
    .bexia-perg-state-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-perg-col-name,
    .bexia-perg-col-registration {
        min-width: 8rem;
        max-width: 10.5rem;
    }

    .bexia-perg-col-risk,
    .bexia-perg-col-state,
    .bexia-perg-col-compact,
    .bexia-perg-col-bool,
    .bexia-perg-col-active {
        min-width: 4rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_PAYROLL_EMPLOYER_REGISTRATION_RESOURCE_RESPONSIVE_V5_79_95C_END */

/* BEXIA_STOCK_LOCATION_TYPE_RESOURCE_RESPONSIVE_V5_79_96C_START */
.bexia-slt-form,
.bexia-slt-shell {
    min-width: 0;
}

.bexia-slt-form .fi-fo-component-ctn,
.bexia-slt-section .fi-section-content {
    min-width: 0;
}

.bexia-slt-section .fi-section-header-description,
.bexia-slt-field .fi-fo-field-wrp-label,
.bexia-slt-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-slt-field .fi-input-wrp,
.bexia-slt-field .fi-select-input,
.bexia-slt-field textarea,
.bexia-slt-field input {
    min-width: 0;
}

.bexia-slt-wide-field .fi-fo-field-wrp,
.bexia-slt-name-field .fi-input-wrp,
.bexia-slt-description-field .fi-fo-field-wrp {
    width: 100%;
}

.bexia-slt-compact-field .fi-input-wrp,
.bexia-slt-code-field .fi-input-wrp {
    max-width: 10rem;
}

.bexia-slt-textarea-field textarea {
    width: 100%;
}

.bexia-slt-toggle-field .fi-fo-field-wrp,
.bexia-slt-internal-field .fi-fo-field-wrp,
.bexia-slt-active-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-slt-header,
.bexia-slt-cell {
    white-space: normal;
}

.bexia-slt-col-name {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-slt-col-code,
.bexia-slt-col-compact {
    min-width: 6.5rem;
    max-width: 9rem;
}

.bexia-slt-col-bool,
.bexia-slt-col-internal,
.bexia-slt-col-active {
    min-width: 5.5rem;
    max-width: 7rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-slt-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-slt-wide-field,
    .bexia-slt-name-field,
    .bexia-slt-description-field {
        grid-column: 1 / -1;
    }

    .bexia-slt-col-name {
        min-width: 8.5rem;
        max-width: 11.5rem;
    }

    .bexia-slt-col-code,
    .bexia-slt-col-compact {
        min-width: 5.5rem;
        max-width: 7.5rem;
    }

    .bexia-slt-col-bool,
    .bexia-slt-col-internal,
    .bexia-slt-col-active {
        min-width: 4.75rem;
        max-width: 6rem;
    }
}

@media (max-width: 640px) {
    .bexia-slt-section .fi-section-header,
    .bexia-slt-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-slt-compact-field .fi-input-wrp,
    .bexia-slt-code-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-slt-textarea-field textarea {
        min-width: 100%;
    }

    .bexia-slt-col-name {
        min-width: 8rem;
        max-width: 10.5rem;
    }

    .bexia-slt-col-code,
    .bexia-slt-col-compact,
    .bexia-slt-col-bool,
    .bexia-slt-col-internal,
    .bexia-slt-col-active {
        min-width: 4rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_STOCK_LOCATION_TYPE_RESOURCE_RESPONSIVE_V5_79_96C_END */

/* BEXIA_ACCOUNTING_INVENTORY_VALUATION_LAYER_RESOURCE_RESPONSIVE_V5_79_97C_START */
.bexia-aivl-form,
.bexia-aivl-shell {
    min-width: 0;
}

.bexia-aivl-readonly-form {
    min-width: 0;
}

.bexia-aivl-header,
.bexia-aivl-cell {
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-aivl-cell {
    vertical-align: top;
}

.bexia-aivl-col-id,
.bexia-aivl-col-company,
.bexia-aivl-col-compact {
    min-width: 4.75rem;
    max-width: 6.5rem;
}

.bexia-aivl-col-product,
.bexia-aivl-col-reference,
.bexia-aivl-col-source-id,
.bexia-aivl-col-entry {
    min-width: 5.75rem;
    max-width: 8rem;
}

.bexia-aivl-col-operation,
.bexia-aivl-col-direction,
.bexia-aivl-col-source,
.bexia-aivl-col-badge {
    min-width: 8rem;
    max-width: 11.5rem;
}

.bexia-aivl-col-date {
    min-width: 6.5rem;
    max-width: 8.5rem;
}

.bexia-aivl-col-quantity,
.bexia-aivl-col-number,
.bexia-aivl-col-unit-cost,
.bexia-aivl-col-total-cost,
.bexia-aivl-col-money {
    min-width: 7rem;
    max-width: 9.5rem;
}

.bexia-aivl-col-number,
.bexia-aivl-col-money {
    text-align: right;
}

@media (max-width: 1024px) {
    .bexia-aivl-col-operation,
    .bexia-aivl-col-direction,
    .bexia-aivl-col-source,
    .bexia-aivl-col-badge {
        min-width: 7rem;
        max-width: 9.5rem;
    }

    .bexia-aivl-col-quantity,
    .bexia-aivl-col-number,
    .bexia-aivl-col-unit-cost,
    .bexia-aivl-col-total-cost,
    .bexia-aivl-col-money {
        min-width: 6.5rem;
        max-width: 8.5rem;
    }

    .bexia-aivl-col-product,
    .bexia-aivl-col-reference,
    .bexia-aivl-col-source-id,
    .bexia-aivl-col-entry {
        min-width: 5.25rem;
        max-width: 7rem;
    }
}

@media (max-width: 640px) {
    .bexia-aivl-header,
    .bexia-aivl-cell {
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .bexia-aivl-col-id,
    .bexia-aivl-col-company,
    .bexia-aivl-col-compact,
    .bexia-aivl-col-product,
    .bexia-aivl-col-reference,
    .bexia-aivl-col-source-id,
    .bexia-aivl-col-entry {
        min-width: 4.25rem;
        max-width: 6.25rem;
    }

    .bexia-aivl-col-operation,
    .bexia-aivl-col-direction,
    .bexia-aivl-col-source,
    .bexia-aivl-col-badge,
    .bexia-aivl-col-quantity,
    .bexia-aivl-col-number,
    .bexia-aivl-col-unit-cost,
    .bexia-aivl-col-total-cost,
    .bexia-aivl-col-money {
        min-width: 5.75rem;
        max-width: 7.25rem;
    }
}
/* BEXIA_ACCOUNTING_INVENTORY_VALUATION_LAYER_RESOURCE_RESPONSIVE_V5_79_97C_END */

/* BEXIA_SALE_DELIVERY_RESOURCE_RESPONSIVE_V5_79_98C_START */
.bexia-sdel-shell {
    min-width: 0;
}

.bexia-sdel-return-reason-field .fi-fo-field-wrp,
.bexia-sdel-return-reason-field textarea {
    min-width: 0;
    width: 100%;
}

.bexia-sdel-return-reason-field .fi-fo-field-wrp-label,
.bexia-sdel-return-reason-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-sdel-header,
.bexia-sdel-cell {
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-sdel-cell {
    vertical-align: top;
}

.bexia-sdel-col-delivery,
.bexia-sdel-col-order,
.bexia-sdel-col-customer,
.bexia-sdel-col-reference {
    min-width: 8.5rem;
    max-width: 12rem;
}

.bexia-sdel-col-status,
.bexia-sdel-col-type,
.bexia-sdel-col-badge {
    min-width: 6.75rem;
    max-width: 9rem;
}

.bexia-sdel-col-quantity,
.bexia-sdel-col-number,
.bexia-sdel-col-movement,
.bexia-sdel-col-stock {
    min-width: 6rem;
    max-width: 8rem;
}

.bexia-sdel-col-date,
.bexia-sdel-col-created,
.bexia-sdel-col-delivered {
    min-width: 7.25rem;
    max-width: 9.75rem;
}

.bexia-sdel-col-quantity,
.bexia-sdel-col-number {
    text-align: right;
}

@media (max-width: 1024px) {
    .bexia-sdel-col-delivery,
    .bexia-sdel-col-order,
    .bexia-sdel-col-customer,
    .bexia-sdel-col-reference {
        min-width: 7.25rem;
        max-width: 10rem;
    }

    .bexia-sdel-col-status,
    .bexia-sdel-col-type,
    .bexia-sdel-col-badge {
        min-width: 6rem;
        max-width: 8rem;
    }

    .bexia-sdel-col-date,
    .bexia-sdel-col-created,
    .bexia-sdel-col-delivered,
    .bexia-sdel-col-quantity,
    .bexia-sdel-col-number,
    .bexia-sdel-col-movement,
    .bexia-sdel-col-stock {
        min-width: 5.75rem;
        max-width: 7.75rem;
    }
}

@media (max-width: 640px) {
    .bexia-sdel-header,
    .bexia-sdel-cell {
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .bexia-sdel-col-delivery,
    .bexia-sdel-col-order,
    .bexia-sdel-col-customer,
    .bexia-sdel-col-reference,
    .bexia-sdel-col-status,
    .bexia-sdel-col-type,
    .bexia-sdel-col-badge {
        min-width: 5.75rem;
        max-width: 7.25rem;
    }

    .bexia-sdel-col-date,
    .bexia-sdel-col-created,
    .bexia-sdel-col-delivered,
    .bexia-sdel-col-quantity,
    .bexia-sdel-col-number,
    .bexia-sdel-col-movement,
    .bexia-sdel-col-stock {
        min-width: 5.25rem;
        max-width: 6.75rem;
    }
}
/* BEXIA_SALE_DELIVERY_RESOURCE_RESPONSIVE_V5_79_98C_END */

/* BEXIA_SERVICE_TECHNICIAN_RESOURCE_RESPONSIVE_V5_79_99C_START */
.bexia-stec-form,
.bexia-stec-shell {
    min-width: 0;
}

.bexia-stec-section .fi-section-content,
.bexia-stec-employee-section .fi-section-content {
    min-width: 0;
}

.bexia-stec-section .fi-section-header-description,
.bexia-stec-field .fi-fo-field-wrp-label,
.bexia-stec-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-stec-field .fi-input-wrp,
.bexia-stec-field .fi-toggle,
.bexia-stec-field input {
    min-width: 0;
}

.bexia-stec-wide-field .fi-fo-field-wrp,
.bexia-stec-employee-label-field .fi-fo-placeholder,
.bexia-stec-employee-label-field .fi-fo-field-wrp {
    width: 100%;
}

.bexia-stec-compact-field .fi-input-wrp,
.bexia-stec-company-field .fi-input-wrp {
    max-width: 10rem;
}

.bexia-stec-bool-field .fi-fo-field-wrp,
.bexia-stec-technician-toggle-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-stec-header,
.bexia-stec-cell {
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-stec-cell {
    vertical-align: top;
}

.bexia-stec-col-id,
.bexia-stec-col-company,
.bexia-stec-col-compact {
    min-width: 4.5rem;
    max-width: 6.5rem;
}

.bexia-stec-col-employee-number,
.bexia-stec-col-number {
    min-width: 6rem;
    max-width: 8rem;
}

.bexia-stec-col-name,
.bexia-stec-col-first-name,
.bexia-stec-col-last-name,
.bexia-stec-col-person {
    min-width: 8.5rem;
    max-width: 12rem;
}

.bexia-stec-col-email {
    min-width: 10rem;
    max-width: 14rem;
}

.bexia-stec-col-technician,
.bexia-stec-col-bool {
    min-width: 5.25rem;
    max-width: 7rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-stec-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-stec-wide-field,
    .bexia-stec-employee-label-field {
        grid-column: 1 / -1;
    }

    .bexia-stec-col-name,
    .bexia-stec-col-first-name,
    .bexia-stec-col-last-name,
    .bexia-stec-col-person,
    .bexia-stec-col-email {
        min-width: 7.5rem;
        max-width: 10rem;
    }

    .bexia-stec-col-employee-number,
    .bexia-stec-col-number {
        min-width: 5.5rem;
        max-width: 7.5rem;
    }

    .bexia-stec-col-id,
    .bexia-stec-col-company,
    .bexia-stec-col-compact,
    .bexia-stec-col-technician,
    .bexia-stec-col-bool {
        min-width: 4.75rem;
        max-width: 6.25rem;
    }
}

@media (max-width: 640px) {
    .bexia-stec-section .fi-section-header,
    .bexia-stec-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-stec-compact-field .fi-input-wrp,
    .bexia-stec-company-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-stec-header,
    .bexia-stec-cell {
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .bexia-stec-col-name,
    .bexia-stec-col-first-name,
    .bexia-stec-col-last-name,
    .bexia-stec-col-person,
    .bexia-stec-col-email,
    .bexia-stec-col-employee-number,
    .bexia-stec-col-number {
        min-width: 5.75rem;
        max-width: 7.25rem;
    }

    .bexia-stec-col-id,
    .bexia-stec-col-company,
    .bexia-stec-col-compact,
    .bexia-stec-col-technician,
    .bexia-stec-col-bool {
        min-width: 4.25rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_SERVICE_TECHNICIAN_RESOURCE_RESPONSIVE_V5_79_99C_END */

/* BEXIA_CUSTOMS_OFFICE_RESOURCE_RESPONSIVE_V5_79_100C_START */
.bexia-coff-form,
.bexia-coff-shell {
    min-width: 0;
}

.bexia-coff-section .fi-section-content,
.bexia-coff-customs-section .fi-section-content {
    min-width: 0;
}

.bexia-coff-section .fi-section-header-description,
.bexia-coff-field .fi-fo-field-wrp-label,
.bexia-coff-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-coff-field .fi-input-wrp,
.bexia-coff-field textarea,
.bexia-coff-field input {
    min-width: 0;
}

.bexia-coff-wide-field .fi-fo-field-wrp,
.bexia-coff-display-name-field .fi-input-wrp,
.bexia-coff-notes-field .fi-fo-field-wrp {
    width: 100%;
}

.bexia-coff-compact-field .fi-input-wrp,
.bexia-coff-code-field .fi-input-wrp {
    max-width: 10rem;
}

.bexia-coff-textarea-field textarea {
    width: 100%;
}

.bexia-coff-bool-field .fi-fo-field-wrp,
.bexia-coff-active-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-coff-header,
.bexia-coff-cell {
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-coff-cell {
    vertical-align: top;
}

.bexia-coff-col-code,
.bexia-coff-col-compact {
    min-width: 5.5rem;
    max-width: 7rem;
}

.bexia-coff-col-name,
.bexia-coff-col-main,
.bexia-coff-col-display-name,
.bexia-coff-col-wide {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-coff-col-active,
.bexia-coff-col-bool {
    min-width: 5.25rem;
    max-width: 7rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-coff-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-coff-wide-field,
    .bexia-coff-display-name-field,
    .bexia-coff-notes-field {
        grid-column: 1 / -1;
    }

    .bexia-coff-col-name,
    .bexia-coff-col-main,
    .bexia-coff-col-display-name,
    .bexia-coff-col-wide {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-coff-col-code,
    .bexia-coff-col-compact,
    .bexia-coff-col-active,
    .bexia-coff-col-bool {
        min-width: 4.75rem;
        max-width: 6rem;
    }
}

@media (max-width: 640px) {
    .bexia-coff-section .fi-section-header,
    .bexia-coff-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-coff-compact-field .fi-input-wrp,
    .bexia-coff-code-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-coff-textarea-field textarea {
        min-width: 100%;
    }

    .bexia-coff-header,
    .bexia-coff-cell {
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .bexia-coff-col-name,
    .bexia-coff-col-main,
    .bexia-coff-col-display-name,
    .bexia-coff-col-wide {
        min-width: 5.75rem;
        max-width: 7.25rem;
    }

    .bexia-coff-col-code,
    .bexia-coff-col-compact,
    .bexia-coff-col-active,
    .bexia-coff-col-bool {
        min-width: 4.25rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_CUSTOMS_OFFICE_RESOURCE_RESPONSIVE_V5_79_100C_END */

/* BEXIA_SAT_UNIT_RESOURCE_RESPONSIVE_V5_79_101C_START */
.bexia-satu-form,
.bexia-satu-shell {
    min-width: 0;
}

.bexia-satu-section .fi-section-content,
.bexia-satu-unit-section .fi-section-content {
    min-width: 0;
}

.bexia-satu-section .fi-section-header-description,
.bexia-satu-field .fi-fo-field-wrp-label,
.bexia-satu-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-satu-field .fi-input-wrp,
.bexia-satu-field textarea,
.bexia-satu-field input {
    min-width: 0;
}

.bexia-satu-wide-field .fi-fo-field-wrp,
.bexia-satu-description-field .fi-fo-field-wrp,
.bexia-satu-textarea-field .fi-fo-field-wrp {
    width: 100%;
}

.bexia-satu-compact-field .fi-input-wrp,
.bexia-satu-code-field .fi-input-wrp,
.bexia-satu-symbol-field .fi-input-wrp {
    max-width: 10rem;
}

.bexia-satu-textarea-field textarea {
    width: 100%;
}

.bexia-satu-bool-field .fi-fo-field-wrp,
.bexia-satu-active-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-satu-header,
.bexia-satu-cell {
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-satu-cell {
    vertical-align: top;
}

.bexia-satu-col-code,
.bexia-satu-col-symbol,
.bexia-satu-col-compact {
    min-width: 5.5rem;
    max-width: 7rem;
}

.bexia-satu-col-name,
.bexia-satu-col-main {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-satu-col-active,
.bexia-satu-col-bool {
    min-width: 5.25rem;
    max-width: 7rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-satu-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-satu-wide-field,
    .bexia-satu-description-field {
        grid-column: 1 / -1;
    }

    .bexia-satu-col-name,
    .bexia-satu-col-main {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-satu-col-code,
    .bexia-satu-col-symbol,
    .bexia-satu-col-compact,
    .bexia-satu-col-active,
    .bexia-satu-col-bool {
        min-width: 4.75rem;
        max-width: 6rem;
    }
}

@media (max-width: 640px) {
    .bexia-satu-section .fi-section-header,
    .bexia-satu-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-satu-compact-field .fi-input-wrp,
    .bexia-satu-code-field .fi-input-wrp,
    .bexia-satu-symbol-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-satu-textarea-field textarea {
        min-width: 100%;
    }

    .bexia-satu-header,
    .bexia-satu-cell {
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .bexia-satu-col-name,
    .bexia-satu-col-main {
        min-width: 5.75rem;
        max-width: 7.25rem;
    }

    .bexia-satu-col-code,
    .bexia-satu-col-symbol,
    .bexia-satu-col-compact,
    .bexia-satu-col-active,
    .bexia-satu-col-bool {
        min-width: 4.25rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_SAT_UNIT_RESOURCE_RESPONSIVE_V5_79_101C_END */

/* BEXIA_SAT_CFDI_USE_TAX_REGIME_RESOURCE_RESPONSIVE_V5_79_102C_START */
.bexia-cutr-form,
.bexia-cutr-shell {
    min-width: 0;
}

.bexia-cutr-section .fi-section-content,
.bexia-cutr-relation-section .fi-section-content {
    min-width: 0;
}

.bexia-cutr-section .fi-section-header-description,
.bexia-cutr-field .fi-fo-field-wrp-label,
.bexia-cutr-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-cutr-field .fi-input-wrp,
.bexia-cutr-field .fi-select-input,
.bexia-cutr-field textarea,
.bexia-cutr-field input {
    min-width: 0;
}

.bexia-cutr-wide-field .fi-fo-field-wrp,
.bexia-cutr-tax-regime-field .fi-input-wrp,
.bexia-cutr-cfdi-use-field .fi-input-wrp,
.bexia-cutr-notes-field .fi-fo-field-wrp {
    width: 100%;
}

.bexia-cutr-select-field .fi-input-wrp,
.bexia-cutr-select-field .fi-select-input {
    min-width: 0;
}

.bexia-cutr-textarea-field textarea {
    width: 100%;
}

.bexia-cutr-bool-field .fi-fo-field-wrp,
.bexia-cutr-active-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-cutr-header,
.bexia-cutr-cell {
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-cutr-cell {
    vertical-align: top;
}

.bexia-cutr-col-tax-regime,
.bexia-cutr-col-cfdi-use,
.bexia-cutr-col-regime,
.bexia-cutr-col-use,
.bexia-cutr-col-wide {
    min-width: 10rem;
    max-width: 16rem;
}

.bexia-cutr-col-active,
.bexia-cutr-col-bool {
    min-width: 5.25rem;
    max-width: 7rem;
    text-align: center;
}

.bexia-cutr-col-updated-at,
.bexia-cutr-col-date {
    min-width: 7rem;
    max-width: 9rem;
}

@media (max-width: 1024px) {
    .bexia-cutr-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-cutr-wide-field,
    .bexia-cutr-tax-regime-field,
    .bexia-cutr-cfdi-use-field,
    .bexia-cutr-notes-field {
        grid-column: 1 / -1;
    }

    .bexia-cutr-col-tax-regime,
    .bexia-cutr-col-cfdi-use,
    .bexia-cutr-col-regime,
    .bexia-cutr-col-use,
    .bexia-cutr-col-wide {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-cutr-col-active,
    .bexia-cutr-col-bool,
    .bexia-cutr-col-updated-at,
    .bexia-cutr-col-date {
        min-width: 4.75rem;
        max-width: 6.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-cutr-section .fi-section-header,
    .bexia-cutr-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-cutr-select-field .fi-input-wrp,
    .bexia-cutr-tax-regime-field .fi-input-wrp,
    .bexia-cutr-cfdi-use-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-cutr-textarea-field textarea {
        min-width: 100%;
    }

    .bexia-cutr-header,
    .bexia-cutr-cell {
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .bexia-cutr-col-tax-regime,
    .bexia-cutr-col-cfdi-use,
    .bexia-cutr-col-regime,
    .bexia-cutr-col-use,
    .bexia-cutr-col-wide {
        min-width: 5.75rem;
        max-width: 7.25rem;
    }

    .bexia-cutr-col-active,
    .bexia-cutr-col-bool,
    .bexia-cutr-col-updated-at,
    .bexia-cutr-col-date {
        min-width: 4.25rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_SAT_CFDI_USE_TAX_REGIME_RESOURCE_RESPONSIVE_V5_79_102C_END */

/* BEXIA_WAREHOUSE_RESOURCE_RESPONSIVE_V5_79_103C_START */
.bexia-whse-form,
.bexia-whse-shell {
    min-width: 0;
}

.bexia-whse-section .fi-section-content,
.bexia-whse-main-section .fi-section-content {
    min-width: 0;
}

.bexia-whse-section .fi-section-header-description,
.bexia-whse-field .fi-fo-field-wrp-label,
.bexia-whse-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-whse-field .fi-input-wrp,
.bexia-whse-field textarea,
.bexia-whse-field input {
    min-width: 0;
}

.bexia-whse-wide-field .fi-fo-field-wrp,
.bexia-whse-description-field .fi-fo-field-wrp,
.bexia-whse-textarea-field .fi-fo-field-wrp {
    width: 100%;
}

.bexia-whse-compact-field .fi-input-wrp,
.bexia-whse-code-field .fi-input-wrp {
    max-width: 10rem;
}

.bexia-whse-textarea-field textarea {
    width: 100%;
}

.bexia-whse-bool-field .fi-fo-field-wrp,
.bexia-whse-active-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-whse-header,
.bexia-whse-cell {
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-whse-cell {
    vertical-align: top;
}

.bexia-whse-col-code,
.bexia-whse-col-compact {
    min-width: 5.5rem;
    max-width: 7rem;
}

.bexia-whse-col-name,
.bexia-whse-col-main {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-whse-col-locations,
.bexia-whse-col-count {
    min-width: 6rem;
    max-width: 8rem;
    text-align: center;
}

.bexia-whse-col-active,
.bexia-whse-col-bool {
    min-width: 5.25rem;
    max-width: 7rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-whse-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-whse-wide-field,
    .bexia-whse-description-field {
        grid-column: 1 / -1;
    }

    .bexia-whse-col-name,
    .bexia-whse-col-main {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-whse-col-code,
    .bexia-whse-col-compact,
    .bexia-whse-col-locations,
    .bexia-whse-col-count,
    .bexia-whse-col-active,
    .bexia-whse-col-bool {
        min-width: 4.75rem;
        max-width: 6.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-whse-section .fi-section-header,
    .bexia-whse-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-whse-compact-field .fi-input-wrp,
    .bexia-whse-code-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-whse-textarea-field textarea {
        min-width: 100%;
    }

    .bexia-whse-header,
    .bexia-whse-cell {
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .bexia-whse-col-name,
    .bexia-whse-col-main {
        min-width: 5.75rem;
        max-width: 7.25rem;
    }

    .bexia-whse-col-code,
    .bexia-whse-col-compact,
    .bexia-whse-col-locations,
    .bexia-whse-col-count,
    .bexia-whse-col-active,
    .bexia-whse-col-bool {
        min-width: 4.25rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_WAREHOUSE_RESOURCE_RESPONSIVE_V5_79_103C_END */

/* BEXIA_BANK_RESOURCE_RESPONSIVE_V5_79_104C_START */
.bexia-bank-form,
.bexia-bank-shell {
    min-width: 0;
}

.bexia-bank-field .fi-fo-field-wrp-label,
.bexia-bank-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-bank-field .fi-input-wrp,
.bexia-bank-field textarea,
.bexia-bank-field input {
    min-width: 0;
}

.bexia-bank-wide-field .fi-fo-field-wrp,
.bexia-bank-legal-name-field .fi-fo-field-wrp,
.bexia-bank-notes-field .fi-fo-field-wrp,
.bexia-bank-textarea-field .fi-fo-field-wrp {
    width: 100%;
}

.bexia-bank-compact-field .fi-input-wrp,
.bexia-bank-code-field .fi-input-wrp {
    max-width: 10rem;
}

.bexia-bank-textarea-field textarea {
    width: 100%;
}

.bexia-bank-bool-field .fi-fo-field-wrp,
.bexia-bank-active-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-bank-header,
.bexia-bank-cell {
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-bank-cell {
    vertical-align: top;
}

.bexia-bank-col-code,
.bexia-bank-col-compact {
    min-width: 5.5rem;
    max-width: 7rem;
}

.bexia-bank-col-name,
.bexia-bank-col-main {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-bank-col-legal-name,
.bexia-bank-col-wide {
    min-width: 11rem;
    max-width: 17rem;
}

.bexia-bank-col-active,
.bexia-bank-col-bool {
    min-width: 5.25rem;
    max-width: 7rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-bank-wide-field,
    .bexia-bank-legal-name-field,
    .bexia-bank-notes-field {
        grid-column: 1 / -1;
    }

    .bexia-bank-col-name,
    .bexia-bank-col-main,
    .bexia-bank-col-legal-name,
    .bexia-bank-col-wide {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-bank-col-code,
    .bexia-bank-col-compact,
    .bexia-bank-col-active,
    .bexia-bank-col-bool {
        min-width: 4.75rem;
        max-width: 6.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-bank-compact-field .fi-input-wrp,
    .bexia-bank-code-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-bank-textarea-field textarea {
        min-width: 100%;
    }

    .bexia-bank-header,
    .bexia-bank-cell {
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .bexia-bank-col-name,
    .bexia-bank-col-main,
    .bexia-bank-col-legal-name,
    .bexia-bank-col-wide {
        min-width: 5.75rem;
        max-width: 7.25rem;
    }

    .bexia-bank-col-code,
    .bexia-bank-col-compact,
    .bexia-bank-col-active,
    .bexia-bank-col-bool {
        min-width: 4.25rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_BANK_RESOURCE_RESPONSIVE_V5_79_104C_END */

/* BEXIA_PAYROLL_PERIODICITY_RESOURCE_RESPONSIVE_V5_79_105C_START */
.bexia-pper-form,
.bexia-pper-shell {
    min-width: 0;
}

.bexia-pper-section .fi-section-content,
.bexia-pper-main-section .fi-section-content {
    min-width: 0;
}

.bexia-pper-section .fi-section-header-description,
.bexia-pper-field .fi-fo-field-wrp-label,
.bexia-pper-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-pper-field .fi-input-wrp,
.bexia-pper-field input {
    min-width: 0;
}

.bexia-pper-compact-field .fi-input-wrp,
.bexia-pper-code-field .fi-input-wrp,
.bexia-pper-number-field .fi-input-wrp {
    max-width: 10rem;
}

.bexia-pper-bool-field .fi-fo-field-wrp,
.bexia-pper-active-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-pper-header,
.bexia-pper-cell {
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-pper-cell {
    vertical-align: top;
}

.bexia-pper-col-name,
.bexia-pper-col-main {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-pper-col-sat-code,
.bexia-pper-col-code,
.bexia-pper-col-compact {
    min-width: 5.75rem;
    max-width: 7.5rem;
}

.bexia-pper-col-days,
.bexia-pper-col-number {
    min-width: 4.75rem;
    max-width: 6.25rem;
    text-align: right;
}

.bexia-pper-col-active,
.bexia-pper-col-bool {
    min-width: 5.25rem;
    max-width: 7rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-pper-section .fi-section-content {
        overflow-x: auto;
    }

    .bexia-pper-col-name,
    .bexia-pper-col-main {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-pper-col-sat-code,
    .bexia-pper-col-code,
    .bexia-pper-col-compact,
    .bexia-pper-col-days,
    .bexia-pper-col-number,
    .bexia-pper-col-active,
    .bexia-pper-col-bool {
        min-width: 4.75rem;
        max-width: 6.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-pper-section .fi-section-header,
    .bexia-pper-section .fi-section-content {
        padding-left: 0.7rem;
        padding-right: 0.7rem;
    }

    .bexia-pper-compact-field .fi-input-wrp,
    .bexia-pper-code-field .fi-input-wrp,
    .bexia-pper-number-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-pper-header,
    .bexia-pper-cell {
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .bexia-pper-col-name,
    .bexia-pper-col-main {
        min-width: 5.75rem;
        max-width: 7.25rem;
    }

    .bexia-pper-col-sat-code,
    .bexia-pper-col-code,
    .bexia-pper-col-compact,
    .bexia-pper-col-days,
    .bexia-pper-col-number,
    .bexia-pper-col-active,
    .bexia-pper-col-bool {
        min-width: 4.25rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_PAYROLL_PERIODICITY_RESOURCE_RESPONSIVE_V5_79_105C_END */

/* BEXIA_SAT_CFDI_CANCELLATION_REASON_RESOURCE_RESPONSIVE_V5_79_106C_START */
.bexia-sccr-form,
.bexia-sccr-shell {
    min-width: 0;
}

.bexia-sccr-field .fi-fo-field-wrp-label,
.bexia-sccr-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-sccr-field .fi-input-wrp,
.bexia-sccr-field textarea,
.bexia-sccr-field input {
    min-width: 0;
}

.bexia-sccr-wide-field .fi-fo-field-wrp,
.bexia-sccr-description-field .fi-fo-field-wrp,
.bexia-sccr-notes-field .fi-fo-field-wrp,
.bexia-sccr-textarea-field .fi-fo-field-wrp {
    width: 100%;
}

.bexia-sccr-compact-field .fi-input-wrp,
.bexia-sccr-code-field .fi-input-wrp {
    max-width: 10rem;
}

.bexia-sccr-textarea-field textarea {
    width: 100%;
}

.bexia-sccr-bool-field .fi-fo-field-wrp,
.bexia-sccr-replacement-uuid-field .fi-fo-field-wrp,
.bexia-sccr-active-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-sccr-header,
.bexia-sccr-cell {
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-sccr-cell {
    vertical-align: top;
}

.bexia-sccr-col-code,
.bexia-sccr-col-compact {
    min-width: 5.5rem;
    max-width: 7rem;
}

.bexia-sccr-col-name,
.bexia-sccr-col-main,
.bexia-sccr-col-wide {
    min-width: 10rem;
    max-width: 16rem;
}

.bexia-sccr-col-replacement-uuid,
.bexia-sccr-col-active,
.bexia-sccr-col-bool {
    min-width: 5.25rem;
    max-width: 7.5rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-sccr-wide-field,
    .bexia-sccr-description-field,
    .bexia-sccr-notes-field {
        grid-column: 1 / -1;
    }

    .bexia-sccr-col-name,
    .bexia-sccr-col-main,
    .bexia-sccr-col-wide {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-sccr-col-code,
    .bexia-sccr-col-compact,
    .bexia-sccr-col-replacement-uuid,
    .bexia-sccr-col-active,
    .bexia-sccr-col-bool {
        min-width: 4.75rem;
        max-width: 6.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-sccr-compact-field .fi-input-wrp,
    .bexia-sccr-code-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-sccr-textarea-field textarea {
        min-width: 100%;
    }

    .bexia-sccr-header,
    .bexia-sccr-cell {
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .bexia-sccr-col-name,
    .bexia-sccr-col-main,
    .bexia-sccr-col-wide {
        min-width: 5.75rem;
        max-width: 7.25rem;
    }

    .bexia-sccr-col-code,
    .bexia-sccr-col-compact,
    .bexia-sccr-col-replacement-uuid,
    .bexia-sccr-col-active,
    .bexia-sccr-col-bool {
        min-width: 4.25rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_SAT_CFDI_CANCELLATION_REASON_RESOURCE_RESPONSIVE_V5_79_106C_END */

/* BEXIA_ACCOUNTING_POSTING_AUDIT_RESOURCE_RESPONSIVE_V5_79_107C_START */
.bexia-apau-table,
.bexia-apau-shell {
    min-width: 0;
}

.bexia-apau-header,
.bexia-apau-cell {
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-apau-cell {
    vertical-align: top;
}

.bexia-apau-col-id,
.bexia-apau-col-company,
.bexia-apau-col-source-id,
.bexia-apau-col-accounting-entry,
.bexia-apau-col-number,
.bexia-apau-col-compact {
    min-width: 4.75rem;
    max-width: 6.75rem;
}

.bexia-apau-col-event,
.bexia-apau-col-source-type,
.bexia-apau-col-main {
    min-width: 9rem;
    max-width: 13rem;
}

.bexia-apau-col-status,
.bexia-apau-col-badge {
    min-width: 6.5rem;
    max-width: 9rem;
}

.bexia-apau-col-message,
.bexia-apau-col-wide {
    min-width: 13rem;
    max-width: 22rem;
}

.bexia-apau-col-created-at,
.bexia-apau-col-date {
    min-width: 8rem;
    max-width: 10rem;
}

@media (max-width: 1024px) {
    .bexia-apau-col-message,
    .bexia-apau-col-wide {
        min-width: 10rem;
        max-width: 15rem;
    }

    .bexia-apau-col-event,
    .bexia-apau-col-source-type,
    .bexia-apau-col-main {
        min-width: 7rem;
        max-width: 10rem;
    }

    .bexia-apau-col-id,
    .bexia-apau-col-company,
    .bexia-apau-col-source-id,
    .bexia-apau-col-accounting-entry,
    .bexia-apau-col-number,
    .bexia-apau-col-compact,
    .bexia-apau-col-status,
    .bexia-apau-col-badge,
    .bexia-apau-col-created-at,
    .bexia-apau-col-date {
        min-width: 4.5rem;
        max-width: 6.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-apau-header,
    .bexia-apau-cell {
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .bexia-apau-col-message,
    .bexia-apau-col-wide {
        min-width: 7rem;
        max-width: 10rem;
    }

    .bexia-apau-col-event,
    .bexia-apau-col-source-type,
    .bexia-apau-col-main {
        min-width: 5.75rem;
        max-width: 7.75rem;
    }

    .bexia-apau-col-id,
    .bexia-apau-col-company,
    .bexia-apau-col-source-id,
    .bexia-apau-col-accounting-entry,
    .bexia-apau-col-number,
    .bexia-apau-col-compact,
    .bexia-apau-col-status,
    .bexia-apau-col-badge,
    .bexia-apau-col-created-at,
    .bexia-apau-col-date {
        min-width: 4.25rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_ACCOUNTING_POSTING_AUDIT_RESOURCE_RESPONSIVE_V5_79_107C_END */

/* BEXIA_ROL_BORRADOR_RESOURCE_RESPONSIVE_V5_79_108C_START */
.bexia-rolb-field,
.bexia-rolb-table,
.bexia-rolb-shell {
    min-width: 0;
}

.bexia-rolb-field .fi-fo-field-wrp-label,
.bexia-rolb-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-rolb-field .fi-input-wrp,
.bexia-rolb-field input,
.bexia-rolb-field select {
    min-width: 0;
}

.bexia-rolb-name-field .fi-input-wrp,
.bexia-rolb-company-field .fi-input-wrp,
.bexia-rolb-compact-field .fi-input-wrp {
    max-width: 18rem;
}

.bexia-rolb-permissions-field .fi-fo-field-wrp,
.bexia-rolb-wide-field .fi-fo-field-wrp {
    min-width: 0;
    width: 100%;
}

.bexia-rolb-permissions-field .fi-checkbox-list,
.bexia-rolb-permissions-field .fi-fo-checkbox-list {
    min-width: 0;
}

.bexia-rolb-header,
.bexia-rolb-cell {
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-rolb-cell {
    vertical-align: top;
}

.bexia-rolb-col-name,
.bexia-rolb-col-main {
    min-width: 9rem;
    max-width: 14rem;
}

.bexia-rolb-col-company,
.bexia-rolb-col-compact {
    min-width: 7rem;
    max-width: 10rem;
}

.bexia-rolb-col-permissions-count,
.bexia-rolb-col-number {
    min-width: 5.25rem;
    max-width: 7rem;
    text-align: right;
}

.bexia-rolb-col-updated-at,
.bexia-rolb-col-date {
    min-width: 7rem;
    max-width: 9rem;
}

@media (max-width: 1024px) {
    .bexia-rolb-permissions-field,
    .bexia-rolb-wide-field {
        grid-column: 1 / -1;
    }

    .bexia-rolb-col-name,
    .bexia-rolb-col-main {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-rolb-col-company,
    .bexia-rolb-col-compact,
    .bexia-rolb-col-permissions-count,
    .bexia-rolb-col-number,
    .bexia-rolb-col-updated-at,
    .bexia-rolb-col-date {
        min-width: 5rem;
        max-width: 7.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-rolb-name-field .fi-input-wrp,
    .bexia-rolb-company-field .fi-input-wrp,
    .bexia-rolb-compact-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-rolb-permissions-field .fi-checkbox-list,
    .bexia-rolb-permissions-field .fi-fo-checkbox-list {
        column-gap: 0.75rem;
    }

    .bexia-rolb-header,
    .bexia-rolb-cell {
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .bexia-rolb-col-name,
    .bexia-rolb-col-main {
        min-width: 5.75rem;
        max-width: 7.25rem;
    }

    .bexia-rolb-col-company,
    .bexia-rolb-col-compact,
    .bexia-rolb-col-permissions-count,
    .bexia-rolb-col-number,
    .bexia-rolb-col-updated-at,
    .bexia-rolb-col-date {
        min-width: 4.25rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_ROL_BORRADOR_RESOURCE_RESPONSIVE_V5_79_108C_END */

/* BEXIA_STOCK_QUANT_RESOURCE_RESPONSIVE_V5_79_109C_START */
.bexia-stqu-table,
.bexia-stqu-shell {
    min-width: 0;
}

.bexia-stqu-header,
.bexia-stqu-cell {
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-stqu-cell {
    vertical-align: top;
}

.bexia-stqu-col-warehouse,
.bexia-stqu-col-location,
.bexia-stqu-col-context {
    min-width: 7rem;
    max-width: 10rem;
}

.bexia-stqu-col-product,
.bexia-stqu-col-main,
.bexia-stqu-col-wide {
    min-width: 13rem;
    max-width: 20rem;
}

.bexia-stqu-col-variant {
    min-width: 10rem;
    max-width: 15rem;
}

.bexia-stqu-col-quantity,
.bexia-stqu-col-reserved,
.bexia-stqu-col-available,
.bexia-stqu-col-average-cost,
.bexia-stqu-col-number,
.bexia-stqu-col-compact {
    min-width: 6rem;
    max-width: 8rem;
    text-align: right;
}

@media (max-width: 1024px) {
    .bexia-stqu-col-product,
    .bexia-stqu-col-main,
    .bexia-stqu-col-wide {
        min-width: 10rem;
        max-width: 14rem;
    }

    .bexia-stqu-col-variant {
        min-width: 8rem;
        max-width: 11rem;
    }

    .bexia-stqu-col-warehouse,
    .bexia-stqu-col-location,
    .bexia-stqu-col-context,
    .bexia-stqu-col-quantity,
    .bexia-stqu-col-reserved,
    .bexia-stqu-col-available,
    .bexia-stqu-col-average-cost,
    .bexia-stqu-col-number,
    .bexia-stqu-col-compact {
        min-width: 5rem;
        max-width: 7rem;
    }
}

@media (max-width: 640px) {
    .bexia-stqu-header,
    .bexia-stqu-cell {
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .bexia-stqu-col-product,
    .bexia-stqu-col-main,
    .bexia-stqu-col-wide {
        min-width: 7rem;
        max-width: 9rem;
    }

    .bexia-stqu-col-variant {
        min-width: 6rem;
        max-width: 8rem;
    }

    .bexia-stqu-col-warehouse,
    .bexia-stqu-col-location,
    .bexia-stqu-col-context,
    .bexia-stqu-col-quantity,
    .bexia-stqu-col-reserved,
    .bexia-stqu-col-available,
    .bexia-stqu-col-average-cost,
    .bexia-stqu-col-number,
    .bexia-stqu-col-compact {
        min-width: 4.25rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_STOCK_QUANT_RESOURCE_RESPONSIVE_V5_79_109C_END */

/* BEXIA_PRODUCT_PRICE_COST_AUDIT_RESOURCE_RESPONSIVE_V5_79_110C_START */
.bexia-ppca-table,
.bexia-ppca-shell {
    min-width: 0;
}

.bexia-ppca-header,
.bexia-ppca-cell {
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-ppca-cell {
    vertical-align: top;
}

.bexia-ppca-col-changed-at,
.bexia-ppca-col-date,
.bexia-ppca-col-compact {
    min-width: 7rem;
    max-width: 9rem;
}

.bexia-ppca-col-product,
.bexia-ppca-col-main,
.bexia-ppca-col-wide {
    min-width: 13rem;
    max-width: 21rem;
}

.bexia-ppca-col-field,
.bexia-ppca-col-user,
.bexia-ppca-col-context {
    min-width: 8rem;
    max-width: 12rem;
}

.bexia-ppca-col-old-value,
.bexia-ppca-col-new-value,
.bexia-ppca-col-value {
    min-width: 7rem;
    max-width: 10rem;
}

.bexia-ppca-col-source,
.bexia-ppca-col-badge {
    min-width: 6rem;
    max-width: 8rem;
}

.bexia-ppca-col-notes {
    min-width: 10rem;
    max-width: 18rem;
}

@media (max-width: 1024px) {
    .bexia-ppca-col-product,
    .bexia-ppca-col-main,
    .bexia-ppca-col-wide,
    .bexia-ppca-col-notes {
        min-width: 9rem;
        max-width: 14rem;
    }

    .bexia-ppca-col-field,
    .bexia-ppca-col-user,
    .bexia-ppca-col-context,
    .bexia-ppca-col-old-value,
    .bexia-ppca-col-new-value,
    .bexia-ppca-col-value,
    .bexia-ppca-col-changed-at,
    .bexia-ppca-col-date,
    .bexia-ppca-col-source,
    .bexia-ppca-col-badge,
    .bexia-ppca-col-compact {
        min-width: 5rem;
        max-width: 7rem;
    }
}

@media (max-width: 640px) {
    .bexia-ppca-header,
    .bexia-ppca-cell {
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .bexia-ppca-col-product,
    .bexia-ppca-col-main,
    .bexia-ppca-col-wide,
    .bexia-ppca-col-notes {
        min-width: 7rem;
        max-width: 9rem;
    }

    .bexia-ppca-col-field,
    .bexia-ppca-col-user,
    .bexia-ppca-col-context,
    .bexia-ppca-col-old-value,
    .bexia-ppca-col-new-value,
    .bexia-ppca-col-value,
    .bexia-ppca-col-changed-at,
    .bexia-ppca-col-date,
    .bexia-ppca-col-source,
    .bexia-ppca-col-badge,
    .bexia-ppca-col-compact {
        min-width: 4.25rem;
        max-width: 5.75rem;
    }
}
/* BEXIA_PRODUCT_PRICE_COST_AUDIT_RESOURCE_RESPONSIVE_V5_79_110C_END */

/* BEXIA_USER_ACCESS_RESOURCE_RESPONSIVE_V5_79_111C_START */
.bexia-uacc-field,
.bexia-uacc-table,
.bexia-uacc-shell {
    min-width: 0;
}

.bexia-uacc-field .fi-fo-field-wrp-label,
.bexia-uacc-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-uacc-field .fi-input-wrp,
.bexia-uacc-field input,
.bexia-uacc-field select {
    min-width: 0;
}

.bexia-uacc-name-field .fi-input-wrp,
.bexia-uacc-email-field .fi-input-wrp,
.bexia-uacc-compact-field .fi-input-wrp {
    max-width: 20rem;
}

.bexia-uacc-roles-field .fi-fo-field-wrp,
.bexia-uacc-wide-field .fi-fo-field-wrp {
    min-width: 0;
    width: 100%;
}

.bexia-uacc-roles-field .fi-checkbox-list,
.bexia-uacc-roles-field .fi-fo-checkbox-list {
    min-width: 0;
}

.bexia-uacc-header,
.bexia-uacc-cell {
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-uacc-cell {
    vertical-align: top;
}

.bexia-uacc-col-name,
.bexia-uacc-col-email,
.bexia-uacc-col-context {
    min-width: 8rem;
    max-width: 12rem;
}

.bexia-uacc-col-roles,
.bexia-uacc-col-main {
    min-width: 11rem;
    max-width: 18rem;
}

@media (max-width: 1024px) {
    .bexia-uacc-roles-field,
    .bexia-uacc-wide-field {
        grid-column: 1 / -1;
    }

    .bexia-uacc-col-roles,
    .bexia-uacc-col-main {
        min-width: 9rem;
        max-width: 13rem;
    }

    .bexia-uacc-col-name,
    .bexia-uacc-col-email,
    .bexia-uacc-col-context {
        min-width: 6.5rem;
        max-width: 9rem;
    }
}

@media (max-width: 640px) {
    .bexia-uacc-name-field .fi-input-wrp,
    .bexia-uacc-email-field .fi-input-wrp,
    .bexia-uacc-compact-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-uacc-roles-field .fi-checkbox-list,
    .bexia-uacc-roles-field .fi-fo-checkbox-list {
        column-gap: 0.75rem;
    }

    .bexia-uacc-header,
    .bexia-uacc-cell {
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .bexia-uacc-col-roles,
    .bexia-uacc-col-main {
        min-width: 7rem;
        max-width: 9rem;
    }

    .bexia-uacc-col-name,
    .bexia-uacc-col-email,
    .bexia-uacc-col-context {
        min-width: 5.25rem;
        max-width: 7.25rem;
    }
}
/* BEXIA_USER_ACCESS_RESOURCE_RESPONSIVE_V5_79_111C_END */

/* BEXIA_ORGANIZATION_RESOURCE_RESPONSIVE_V5_79_112C_START */
.bexia-orgn-field,
.bexia-orgn-table,
.bexia-orgn-shell {
    min-width: 0;
}

.bexia-orgn-field .fi-fo-field-wrp-label,
.bexia-orgn-field .fi-fo-field-wrp-helper-text {
    overflow-wrap: anywhere;
}

.bexia-orgn-field .fi-input-wrp,
.bexia-orgn-field input,
.bexia-orgn-field select {
    min-width: 0;
}

.bexia-orgn-name-field .fi-input-wrp,
.bexia-orgn-slug-field .fi-input-wrp,
.bexia-orgn-compact-field .fi-input-wrp {
    max-width: 18rem;
}

.bexia-orgn-active-field .fi-fo-field-wrp,
.bexia-orgn-boolean-field .fi-fo-field-wrp {
    min-width: 0;
}

.bexia-orgn-header,
.bexia-orgn-cell {
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-orgn-cell {
    vertical-align: top;
}

.bexia-orgn-col-name,
.bexia-orgn-col-slug,
.bexia-orgn-col-main {
    min-width: 8rem;
    max-width: 13rem;
}

.bexia-orgn-col-active,
.bexia-orgn-col-bool {
    min-width: 5rem;
    max-width: 7rem;
    text-align: center;
}

@media (max-width: 1024px) {
    .bexia-orgn-col-name,
    .bexia-orgn-col-slug,
    .bexia-orgn-col-main {
        min-width: 6.75rem;
        max-width: 9.5rem;
    }

    .bexia-orgn-col-active,
    .bexia-orgn-col-bool {
        min-width: 4.75rem;
        max-width: 6.25rem;
    }
}

@media (max-width: 640px) {
    .bexia-orgn-name-field .fi-input-wrp,
    .bexia-orgn-slug-field .fi-input-wrp,
    .bexia-orgn-compact-field .fi-input-wrp {
        max-width: 100%;
    }

    .bexia-orgn-header,
    .bexia-orgn-cell {
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .bexia-orgn-col-name,
    .bexia-orgn-col-slug,
    .bexia-orgn-col-main {
        min-width: 5.25rem;
        max-width: 7.25rem;
    }

    .bexia-orgn-col-active,
    .bexia-orgn-col-bool {
        min-width: 4.25rem;
        max-width: 5.5rem;
    }
}
/* BEXIA_ORGANIZATION_RESOURCE_RESPONSIVE_V5_79_112C_END */

/* BEXIA_POS_SESSION_RESOURCE_RESPONSIVE_V5_79_113C_START */
.bexia-pssn-table,
.bexia-pssn-shell {
    min-width: 0;
}

.bexia-pssn-header,
.bexia-pssn-cell {
    white-space: normal;
    overflow-wrap: anywhere;
}

.bexia-pssn-cell {
    vertical-align: top;
}

.bexia-pssn-col-number,
.bexia-pssn-col-main {
    min-width: 8rem;
    max-width: 12rem;
}

.bexia-pssn-col-status,
.bexia-pssn-col-badge {
    min-width: 6rem;
    max-width: 8rem;
}

.bexia-pssn-col-opened-at,
.bexia-pssn-col-closed-at,
.bexia-pssn-col-date {
    min-width: 7.75rem;
    max-width: 10rem;
}

.bexia-pssn-col-opening-amount,
.bexia-pssn-col-closing-amount,
.bexia-pssn-col-money {
    min-width: 7rem;
    max-width: 9rem;
    text-align: right;
}

@media (max-width: 1024px) {
    .bexia-pssn-col-number,
    .bexia-pssn-col-main {
        min-width: 6.75rem;
        max-width: 9.25rem;
    }

    .bexia-pssn-col-status,
    .bexia-pssn-col-badge {
        min-width: 5.25rem;
        max-width: 7rem;
    }

    .bexia-pssn-col-opened-at,
    .bexia-pssn-col-closed-at,
    .bexia-pssn-col-date {
        min-width: 6.25rem;
        max-width: 8rem;
    }

    .bexia-pssn-col-opening-amount,
    .bexia-pssn-col-closing-amount,
    .bexia-pssn-col-money {
        min-width: 5.75rem;
        max-width: 7.5rem;
    }
}

@media (max-width: 640px) {
    .bexia-pssn-header,
    .bexia-pssn-cell {
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .bexia-pssn-col-number,
    .bexia-pssn-col-main {
        min-width: 5.25rem;
        max-width: 7rem;
    }

    .bexia-pssn-col-status,
    .bexia-pssn-col-badge {
        min-width: 4.75rem;
        max-width: 6rem;
    }

    .bexia-pssn-col-opened-at,
    .bexia-pssn-col-closed-at,
    .bexia-pssn-col-date {
        min-width: 5.5rem;
        max-width: 7rem;
    }

    .bexia-pssn-col-opening-amount,
    .bexia-pssn-col-closing-amount,
    .bexia-pssn-col-money {
        min-width: 5rem;
        max-width: 6.5rem;
    }
}
/* BEXIA_POS_SESSION_RESOURCE_RESPONSIVE_V5_79_113C_END */

/* BEXIA_FILAMENT_NOTIFICATIONS_V5_83_P4A_START */

/*
 * Notificaciones globales Bexia ERP.
 * Base azul de alto contraste.
 * Los estados semánticos cambian automáticamente
 * a azul Bexia, ámbar o rojo.
 */

.fi-no-notification {
    background:
        linear-gradient(
            135deg,
            #2f6fed 0%,
            #1e5ae0 100%
        ) !important;

    border: 1px solid #93c5fd !important;
    border-radius: 16px !important;

    color: #ffffff !important;

    box-shadow:
        0 18px 42px rgba(15, 23, 42, 0.24),
        0 6px 16px rgba(37, 99, 235, 0.18)
        !important;

    opacity: 1 !important;
}

.fi-no-notification .fi-no-notification-title,
.fi-no-notification .fi-no-notification-body,
.fi-no-notification .fi-no-notification-actions,
.fi-no-notification .fi-icon-btn {
    color: #ffffff !important;
}

.fi-no-notification .fi-no-notification-title {
    font-weight: 700 !important;
}

.fi-no-notification svg {
    color: currentColor !important;
    stroke: currentColor !important;
}

/* ÉXITO: guardado / actualizado */
.fi-no-notification.fi-color-success,
.fi-no-notification:has(.fi-color-success) {
    background:
        linear-gradient(
            135deg,
            #2f6fed 0%,
            #1e5ae0 100%
        ) !important;

    border-color: #93c5fd !important;

    box-shadow:
        0 18px 42px rgba(15, 23, 42, 0.22),
        0 7px 20px rgba(47, 111, 237, 0.30)
        !important;
}

/* INFORMACIÓN */
.fi-no-notification.fi-color-info,
.fi-no-notification.fi-color-primary,
.fi-no-notification:has(.fi-color-info),
.fi-no-notification:has(.fi-color-primary) {
    background:
        linear-gradient(
            135deg,
            #2f6fed 0%,
            #1e5ae0 100%
        ) !important;

    border-color: #93c5fd !important;
}

/* ADVERTENCIA */
.fi-no-notification.fi-color-warning,
.fi-no-notification:has(.fi-color-warning) {
    background:
        linear-gradient(
            135deg,
            #fbbf24 0%,
            #f59e0b 100%
        ) !important;

    border-color: #d97706 !important;

    color: #422006 !important;

    box-shadow:
        0 18px 42px rgba(15, 23, 42, 0.20),
        0 7px 20px rgba(217, 119, 6, 0.24)
        !important;
}

.fi-no-notification.fi-color-warning
    .fi-no-notification-title,
.fi-no-notification.fi-color-warning
    .fi-no-notification-body,
.fi-no-notification.fi-color-warning
    .fi-no-notification-actions,
.fi-no-notification.fi-color-warning
    .fi-icon-btn,
.fi-no-notification:has(.fi-color-warning)
    .fi-no-notification-title,
.fi-no-notification:has(.fi-color-warning)
    .fi-no-notification-body,
.fi-no-notification:has(.fi-color-warning)
    .fi-no-notification-actions,
.fi-no-notification:has(.fi-color-warning)
    .fi-icon-btn {
    color: #422006 !important;
}

/* ERROR / PELIGRO */
.fi-no-notification.fi-color-danger,
.fi-no-notification:has(.fi-color-danger) {
    background:
        linear-gradient(
            135deg,
            #b91c1c 0%,
            #dc2626 100%
        ) !important;

    border-color: #f87171 !important;

    box-shadow:
        0 18px 42px rgba(15, 23, 42, 0.22),
        0 7px 20px rgba(220, 38, 38, 0.28)
        !important;
}

/* Botón cerrar claramente visible */
.fi-no-notification .fi-icon-btn {
    border-radius: 999px !important;
    opacity: .92 !important;
}

.fi-no-notification .fi-icon-btn:hover {
    background: rgba(255, 255, 255, 0.16) !important;
    opacity: 1 !important;
}

.fi-no-notification.fi-color-warning
    .fi-icon-btn:hover,
.fi-no-notification:has(.fi-color-warning)
    .fi-icon-btn:hover {
    background: rgba(66, 32, 6, 0.10) !important;
}

/* BEXIA_FILAMENT_NOTIFICATIONS_V5_83_P4A_END */

</style>
