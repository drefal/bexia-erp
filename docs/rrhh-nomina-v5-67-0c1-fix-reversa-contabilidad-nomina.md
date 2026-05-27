# V5.67.0c1 - Fix reversa contabilidad de nómina

## Problema detectado

Después de V5.67.0c, al re-postear una nómina y volver a revertirla, el servicio podía reutilizar una reversa anterior de la misma nómina en lugar de crear una reversa nueva ligada a la póliza recién creada.

## Cambio

En PayrollAccountingPoster::reverse(), la reversa existente ahora solo se reutiliza si su metadata indica que reversa exactamente la póliza original actual:

- reverses_accounting_entry_id = original.id

Si existe una reversa previa de otra póliza de la misma nómina, ya no se reutiliza.

## Resultado esperado

Ejemplo:

- GEN-NOM-00000036
- GEN-NOM-REV-00000036
- GEN-NOM-00000036-02
- GEN-NOM-REV-00000036-02
- GEN-NOM-00000036-03
- GEN-NOM-REV-00000036-03

Cada póliza queda cancelada por su propia reversa.

## Alcance

- No cambia tablas.
- No afecta CFDI.
- No envía PAC/SAT.
- Solo mejora consistencia contable/auditoría.
