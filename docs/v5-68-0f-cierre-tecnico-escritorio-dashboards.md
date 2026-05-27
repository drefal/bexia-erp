# V5.68.0f - Cierre técnico Escritorio dashboards configurables

## Alcance cerrado

Se implementó el Escritorio/Dashboard configurable por usuario.

## Commits incluidos

- V5.68.0a diseño Escritorio dashboards configurables.
- V5.68.0b backend preferencias Escritorio.
- V5.68.0b1 fix migración preferencias Escritorio.
- V5.68.0c widgets configurables Escritorio.
- V5.68.0c1 fix canView widgets Escritorio.
- V5.68.0d UI configurar Escritorio por usuario.
- V5.68.0d1 fix persistencia configuración Escritorio.
- V5.68.0d2 ajuste visual botones configurar Escritorio.

## Tabla nueva

dashboard_widget_user_settings

Uso:

- company_id
- user_id
- widget_key
- is_visible
- sort_order
- settings

## Permisos nuevos

- dashboard.ver
- dashboard.configurar

## Servicios agregados

- App\Support\Dashboard\DashboardWidgetRegistry
- App\Support\Dashboard\UserDashboardPreferenceService

## Modelo agregado

- App\Models\DashboardWidgetUserSetting

## Página agregada

- App\Filament\Pages\DashboardWidgetSettings

Ruta:

- /admin/{tenant}/configurar-escritorio

Navegación:

- Configuración empresa / Configurar escritorio

## Widgets configurables

- approvals_summary
- approvals_pending
- notices
- hr_employees_summary
- payroll_runs_summary
- payroll_cfdi_summary
- payroll_accounting_summary

## Widgets nuevos

- BexiaNoticesSummaryWidget
- BexiaHrEmployeesSummaryWidget
- BexiaPayrollRunsSummaryWidget
- BexiaPayrollCfdiSummaryWidget
- BexiaPayrollAccountingSummaryWidget

## Widgets existentes ajustados

- ApprovalSummaryWidget
- PendingApprovalsWidget

Ahora respetan preferencias de usuario.

## Validación DEV

Validado:

- Catálogo con 7 widgets.
- canView true para admin en los 7 widgets.
- Ocultar/restaurar widget funciona.
- Persistencia de orden funciona.
- Pantalla Configurar escritorio funciona visualmente.
- Botones visibles: Actualizar, Restaurar defaults, Subir, Bajar, Ocultar, Mostrar.
- Métricas disponibles:
  - aprobaciones
  - avisos
  - RRHH
  - nómina
  - CFDI nómina
  - contabilidad de nómina

## Seguridad

La visibilidad del widget no otorga permisos operativos.

Un widget se muestra solo si:

1. El usuario tiene sesión.
2. El widget está visible en preferencias.
3. El usuario tiene permisos suficientes o es super admin.
4. El dato corresponde al tenant/company_id actual.

## Pendientes futuros

1. Configuración por rol además de usuario.
2. Agrupar widgets por módulo con colapsables.
3. Agregar gráficas, no solo tarjetas.
4. Permitir tamaños por widget.
5. Agregar dashboard financiero/ventas/compras/inventario.
