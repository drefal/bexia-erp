# V5.68.0c - Widgets configurables en Escritorio

## Objetivo

Conectar el catálogo de widgets configurables al Escritorio/Dashboard de Filament.

## Cambios

### Widgets existentes actualizados

- ApprovalSummaryWidget ahora respeta la preferencia `approvals_summary`.
- PendingApprovalsWidget ahora respeta la preferencia `approvals_pending`.

### Widgets nuevos

- BexiaNoticesSummaryWidget
- BexiaHrEmployeesSummaryWidget
- BexiaPayrollRunsSummaryWidget
- BexiaPayrollCfdiSummaryWidget
- BexiaPayrollAccountingSummaryWidget

## Widgets disponibles

- approvals_summary
- approvals_pending
- notices
- hr_employees_summary
- payroll_runs_summary
- payroll_cfdi_summary
- payroll_accounting_summary

## Seguridad

Cada widget revisa:

1. Que el usuario tenga sesión.
2. Que el widget esté visible en preferencias.
3. Que DashboardWidgetRegistry lo permita por permisos.
4. Que los datos estén filtrados por tenant/company_id.

## Alcance

- No agrega todavía pantalla de configuración.
- Ya permite ocultar/mostrar widgets desde la tabla dashboard_widget_user_settings.
- La configuración visual no otorga permisos operativos.
