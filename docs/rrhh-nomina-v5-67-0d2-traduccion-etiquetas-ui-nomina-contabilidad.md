# V5.67.0d2 - Traducción de etiquetas UI nómina/contabilidad

## Objetivo

Evitar que en la UI se muestren códigos técnicos en inglés para nómina y contabilidad.

## Cambios

### PayrollRunResource

Se agregó traducción para estados CFDI de nómina:

- partial_stamped → Parcialmente timbrada
- partial_validated → Parcialmente validada
- partial_error → Con errores parciales
- cancelled → Cancelada
- stamping_error → Error de timbrado
- xml_generated → XML generado

### AccountingEntryResource

Se agregaron etiquetas para orígenes contables de nómina:

- payroll_run → Póliza de nómina
- payroll_run_reversal → Reversa de nómina
- payroll_accounting_setup → Configuración contable de nómina

### Líneas del asiento

Si las líneas muestran `source_type`, se intenta usar el mismo traductor de `AccountingEntryResource`.

## Alcance

- No cambia BD.
- No cambia lógica contable.
- No afecta CFDI.
- No envía PAC/SAT.
- Solo mejora presentación visual en lista/detalle.
