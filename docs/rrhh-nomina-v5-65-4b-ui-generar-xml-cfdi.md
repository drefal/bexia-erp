# V5.65.4b - UI generar XML CFDI nomina

Objetivo:
Agregar acciones visuales para generar XML CFDI de nomina en borrador desde la UI.

Alcance:
- Accion Generar XML en PayrollRunResource.
- Accion Generar XML en PayrollCfdiReceiptResource.
- Columna XML en recibos CFDI nomina.
- Usa PayrollCfdiXmlDraftService.
- No timbra.
- No genera UUID.
- No envia datos al PAC/SAT.

Reglas:
- La accion desde nomina usa la corrida seleccionada.
- La accion desde recibos usa la corrida del recibo.
- Si el XML ya existe y no se usa force, el servicio omite el recibo.
- Recibos timbrados o cancelados no deben modificarse.

Pendiente:
- V5.65.4c: vista/descarga segura de XML desde la UI.
- V5.65.5: timbrado sandbox/PAC.
