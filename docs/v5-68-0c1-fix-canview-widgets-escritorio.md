# V5.68.0c1 - Fix canView widgets Escritorio

## Problema

V5.68.0c creó los widgets configurables y los conectó al catálogo, pero la validación de `canView()` en consola regresó `false` para todos los widgets.

El catálogo sí resolvía los 7 widgets visibles cuando se pasaba `company_id=5`, pero `canView()` no recibía company_id directamente y dependía de `DashboardWidgetRegistry::currentCompanyId()`.

En contexto de consola/tinker no siempre existe tenant de Filament ni session company_id, por lo que `currentCompanyId()` podía regresar 0.

## Cambio

Se mejoró `DashboardWidgetRegistry::currentCompanyId()` con fallback en este orden:

1. Tenant actual de Filament.
2. `session('company_id')`.
3. Spatie permission team id.
4. Tabla `dashboard_widget_user_settings` del usuario autenticado.
5. Pivotes posibles de usuario/empresa:
   - company_user
   - company_user_access
   - company_users

## Resultado esperado

- Los widgets configurables conservan seguridad por empresa.
- En Filament con tenant siguen usando el tenant.
- En consola/pruebas pueden resolver company_id desde preferencias.
- `canView()` ya debe respetar ocultar/restaurar por preferencia.
