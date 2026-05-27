# V5.68.0a - Escritorio con dashboards configurables

## Objetivo

Usar la página existente de Escritorio como centro operativo configurable por usuario.

El Escritorio debe poder mostrar:

- Aprobaciones pendientes.
- Avisos.
- Indicadores RRHH.
- Indicadores de nómina.
- Indicadores CFDI nómina.
- Indicadores contables de nómina.
- Alertas operativas.

## Requisito principal

Cada usuario debe poder tener configurado qué dashboards o widgets puede ver.

La configuración debe poder ser administrada por:

- Super admin.
- Admin grupo.
- Admin empresa.

## Diseño recomendado

### 1. Registro de widgets por código

Los widgets deben tener una clave estable:

- approvals_pending
- notices
- hr_employees_summary
- payroll_runs_summary
- payroll_cfdi_summary
- payroll_accounting_summary
- accounting_pending_summary

Cada widget tendrá:

- key
- nombre visible
- descripción
- módulo
- permiso requerido
- orden por defecto
- activo/inactivo

### 2. Configuración por usuario

Crear tabla propuesta:

dashboard_widget_user_settings

Campos sugeridos:

- id
- company_id
- user_id
- widget_key
- is_visible
- sort_order
- settings json
- created_by_user_id
- updated_by_user_id
- timestamps

Regla:

- Si no existe configuración para un usuario, usar defaults por rol/permisos.
- Si existe, respetar configuración individual.

### 3. Servicio central

Crear servicio:

App\Support\Dashboard\DashboardWidgetRegistry

Responsabilidades:

- Definir catálogo de widgets.
- Resolver widgets visibles por usuario/empresa.
- Consultar métricas.
- Evitar que el usuario vea datos si no tiene permiso.

Crear servicio:

App\Support\Dashboard\UserDashboardPreferenceService

Responsabilidades:

- Guardar configuración.
- Restaurar defaults.
- Ordenar widgets.
- Activar/desactivar widgets por usuario.

### 4. UI

En Escritorio:

- Mostrar widgets visibles del usuario.
- Respetar orden.
- Ocultar widgets sin permiso.
- Mostrar tarjetas compactas.

En configuración:

- Página "Configurar Escritorio".
- Seleccionar usuario.
- Activar/desactivar widgets.
- Definir orden.
- Restaurar defaults.

### 5. Widgets iniciales propuestos

#### Aprobaciones pendientes

- Total pendientes.
- Pendientes mías.
- Pendientes por vencer.

#### Avisos

- Avisos activos.
- Avisos recientes.

#### RRHH

- Empleados activos.
- Empleados inactivos.
- Contratos por vencer, si existe dato.

#### Nómina

- Nóminas cerradas.
- Nóminas por aprobar.
- Última nómina cerrada.
- Total neto última nómina.

#### CFDI nómina

- Recibos validados.
- Recibos timbrados.
- Recibos internos.
- CFDI no requerido.
- Timbrado externo.
- Errores CFDI.

#### Contabilidad de nómina

- Nóminas contabilizadas.
- Nóminas pendientes de póliza.
- Pólizas reversadas.
- Última póliza de nómina.

## Reglas de seguridad

1. Nunca mostrar widgets sin permiso.
2. Nunca mostrar datos de otra empresa/tenant.
3. Super admin puede ver/configurar todo.
4. Admin empresa solo configura usuarios de su empresa.
5. Usuario normal solo ve lo asignado.
6. La configuración visual no debe dar permisos de operación; solo de visibilidad.

## Plan técnico

### V5.68.0b

Crear migración/modelo/servicios:

- dashboard_widget_user_settings
- DashboardWidgetRegistry
- UserDashboardPreferenceService

### V5.68.0c

Integrar widgets iniciales en Escritorio.

### V5.68.0d

Crear UI de configuración por usuario.

### V5.68.0e

Validación visual, permisos y cierre.

## Pendientes de diagnóstico

- Confirmar archivo real de la página Escritorio.
- Confirmar tablas reales de aprobaciones y avisos.
- Confirmar permisos actuales para admins.
- Confirmar si Filament ya registra widgets nativos en Escritorio.
