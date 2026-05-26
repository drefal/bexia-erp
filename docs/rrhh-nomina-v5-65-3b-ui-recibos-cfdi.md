# V5.65.3b - UI recibos CFDI nomina

Objetivo:
Agregar una pantalla Filament para consultar recibos CFDI de nomina preparados en borrador.

Alcance:
- Modelo App\Models\PayrollCfdiReceipt.
- Recurso Filament PayrollCfdiReceiptResource.
- Listado con estado, nomina, empleado, folio, UUID y fechas.
- Vista detalle con snapshots fiscales y totales.
- Sin timbrado real.
- Sin generacion XML.
- Sin comunicacion con PAC/SAT.

Pendiente:
- V5.65.3c: acciones desde la nomina para preparar/ver recibos.
- V5.65.4: preparar XML CFDI nomina en borrador.
- V5.65.5: timbrado sandbox/PAC.
