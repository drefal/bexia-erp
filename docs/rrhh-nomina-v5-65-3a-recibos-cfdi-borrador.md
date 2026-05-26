# V5.65.3a - Recibos CFDI nomina en borrador

Objetivo:
Preparar recibos CFDI de nomina en estado borrador desde una corrida de nomina cerrada y bloqueada.

Este paso no timbra, no genera XML y no envia informacion al PAC/SAT.

Comando agregado:
php artisan payroll:cfdi-prepare-receipts --company=5 --payroll-run=ID

Opcional:
php artisan payroll:cfdi-prepare-receipts --company=5 --payroll-run=ID --force

Reglas:
- La corrida debe existir.
- La corrida debe estar cerrada y bloqueada.
- La validacion payroll:cfdi-readiness debe pasar.
- Si ya existe recibo para una linea y no se usa --force, se omite.
- Si el recibo ya esta timbrado o cancelado, no se modifica.

Salida:
- Crea o actualiza payroll_cfdi_receipts.
- Crea auditoria en payroll_cfdi_audits.
- Actualiza payroll_run_lines.payroll_cfdi_status.
- Actualiza payroll_runs.payroll_cfdi_status.
- Actualiza contadores de lineas listas y con error.

Pendiente siguiente:
V5.65.3b debe agregar vista/listado en Filament para ver los recibos CFDI nomina generados en borrador.
