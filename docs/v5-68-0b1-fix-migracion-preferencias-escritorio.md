# V5.68.0b1 - Fix migración preferencias Escritorio

## Problema

En V5.68.0b la migración falló en PostgreSQL porque se intentó usar:

    coalesce(created_at, now())

dentro de un INSERT/UPSERT para la tabla permissions.

PostgreSQL no permite referenciar `created_at` en ese contexto de inserción.

## Cambio

Se corrigió la migración para crear permisos con lógica explícita:

- Si el permiso existe, solo actualiza updated_at.
- Si no existe, inserta name, guard_name, created_at y updated_at.

También se corrigió UserDashboardPreferenceService para evitar DB::raw/coalesce dentro de updateOrInsert.

## Resultado esperado

- La tabla dashboard_widget_user_settings se crea correctamente.
- Los permisos dashboard.ver y dashboard.configurar se crean correctamente.
- Las preferencias default por usuario se sincronizan correctamente.
- El catálogo y métricas de Dashboard siguen funcionando.
