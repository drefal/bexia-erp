# V5.68.0d2 - Ajuste visual botones Configurar Escritorio

## Problema

En la pantalla `Configurar escritorio`, algunos botones se veían como cápsulas blancas sin texto visible.

Afectaba principalmente:

- Restaurar defaults
- Ocultar / Mostrar

## Cambio

Se reemplazaron los botones `x-filament::button` dentro de la vista custom por botones HTML con clases Tailwind explícitas.

## Resultado esperado

Ahora deben verse claramente:

- Actualizar
- Restaurar defaults
- Subir
- Bajar
- Ocultar
- Mostrar

## Alcance

- No cambia BD.
- No cambia lógica de preferencias.
- No cambia permisos.
- Solo corrige presentación visual.
