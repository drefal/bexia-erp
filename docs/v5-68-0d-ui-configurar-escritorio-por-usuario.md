# V5.68.0d - UI configurar Escritorio por usuario

## Objetivo

Agregar una pantalla en Filament para configurar qué widgets/dashboards ve cada usuario en el Escritorio.

## Archivos agregados

- app/Filament/Pages/DashboardWidgetSettings.php
- resources/views/filament/pages/dashboard-widget-settings.blade.php

## Ruta / navegación

Página:

- Configurar escritorio

Grupo:

- Configuración empresa

Slug:

- configurar-escritorio

## Funcionalidad

Permite:

- Seleccionar usuario.
- Ver widgets configurables.
- Activar/ocultar widget.
- Subir/bajar orden.
- Restaurar defaults.

## Widgets configurables

- approvals_summary
- approvals_pending
- notices
- hr_employees_summary
- payroll_runs_summary
- payroll_cfdi_summary
- payroll_accounting_summary

## Seguridad

La página solo es accesible para:

- Super admin.
- Usuarios con dashboard.configurar.
- Usuarios con company.update.
- Usuarios con company.settings.update.

La visibilidad de widgets no otorga permisos operativos.

Si un usuario no tiene permiso real para un widget, se marca como "Sin permiso" y el widget no debe mostrarse aunque esté visible.
