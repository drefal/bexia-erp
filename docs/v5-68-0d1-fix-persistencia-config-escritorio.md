# V5.68.0d1 - Fix persistencia configuración Escritorio

## Problema

La pantalla de configuración de Escritorio funcionaba, pero `syncUserDefaults()` sobrescribía siempre las preferencias existentes.

Eso podía provocar que:

- El orden personalizado regresara a defaults.
- Un widget oculto pudiera volver a visible al recargar.
- La página pareciera guardar, pero luego restaurara el estado base.

## Cambio

`UserDashboardPreferenceService::syncUserDefaults()` ahora solo crea preferencias faltantes.

Si la preferencia ya existe, no modifica:

- is_visible
- sort_order
- settings

## Resultado esperado

- Ocultar/mostrar widget persiste.
- Cambiar orden persiste.
- Cargar la página no resetea la configuración.
- Restaurar defaults sigue funcionando porque borra y vuelve a sincronizar.
