# V5.68.0b - Backend preferencias de Escritorio

## Objetivo

Crear la base técnica para que el Escritorio/Dashboard pueda mostrar widgets configurables por usuario.

## Archivos agregados

- database/migrations/2026_05_27_568000_v5680b_create_dashboard_widget_user_settings.php
- app/Models/DashboardWidgetUserSetting.php
- app/Support/Dashboard/DashboardWidgetRegistry.php
- app/Support/Dashboard/UserDashboardPreferenceService.php

## Tabla creada

dashboard_widget_user_settings

Campos principales:

- company_id
- user_id
- widget_key
- is_visible
- sort_order
- settings
- created_by_user_id
- updated_by_user_id

## Permisos creados

- dashboard.ver
- dashboard.configurar

## Widgets registrados inicialmente

- approvals_summary
- approvals_pending
- notices
- hr_employees_summary
- payroll_runs_summary
- payroll_cfdi_summary
- payroll_accounting_summary

## Seguridad

La preferencia visual no sustituye permisos.

Regla:

- Si un widget está oculto por preferencia, no se muestra.
- Si está visible pero el usuario no tiene permiso, tampoco se muestra.
- El super admin puede ver todo.
- Los datos se consultan por company_id/tenant.

## Próximo paso

V5.68.0c:

- Conectar widgets reales al Dashboard/Escritorio.
- Hacer que ApprovalSummaryWidget y PendingApprovalsWidget respeten preferencias.
- Crear widgets resumen RRHH/Nómina/CFDI/Contabilidad.
