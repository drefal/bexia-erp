# V5.65.3c - Acciones CFDI desde nomina

Objetivo:
Agregar acciones en el listado de nominas para preparar y consultar recibos CFDI de nomina.

Alcance:
- Se agregan columnas de seguimiento CFDI en PayrollRunResource:
  - payroll_cfdi_status
  - payroll_cfdi_ready_lines_count
  - payroll_cfdi_error_lines_count

- Se agrega accion Preparar CFDI:
  - Solo visible en nominas cerradas y bloqueadas.
  - Crea recibos CFDI de nomina en borrador.
  - No timbra.
  - No genera XML.
  - No envia datos a PAC/SAT.
  - Usa PayrollCfdiReceiptPreparationService.

- Se agrega accion Ver CFDI:
  - Abre el recurso de recibos CFDI nomina.

Pendiente:
- V5.65.4: preparar XML CFDI nomina en borrador.
- V5.65.5: timbrado sandbox/PAC.
