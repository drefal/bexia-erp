# V5.65.7a - Cancelacion CFDI nomina con guard

Objetivo:
Preparar la estructura segura de cancelacion CFDI nomina.

Alcance:
- Config PAYROLL_CFDI_CANCELLATION_ENABLED.
- Config PAYROLL_CFDI_CANCELLATION_ALLOWED_ENV.
- Guard PayrollCfdiCancellationGuardService.
- Servicio PayrollCfdiCancelService.
- Comando payroll:cfdi-cancel.
- Auditoria action=cancel con status=blocked/error.
- En DEV debe bloquear.
- No llama PAC/SAT en DEV.
- No modifica recibos cuando queda bloqueado.

Comando:
php artisan payroll:cfdi-cancel --company=5 --receipt=ID --reason=02

Motivos SAT:
- 01 Comprobante emitido con errores con relacion.
- 02 Comprobante emitido con errores sin relacion.
- 03 No se llevo a cabo la operacion.
- 04 Operacion nominativa relacionada en factura global.

Regla:
Cancelacion real solo en PROD con:
- APP_ENV=production
- PAYROLL_CFDI_CANCELLATION_ENABLED=true
- PAC/CSD configurado por empresa
- Recibo status=stamped
- UUID real
- No aplica a timbrado demo dev-demo

Pendiente:
- V5.65.7b conectar cancelacion real al PAC SW.
- V5.65.7c UI para cancelar CFDI nomina.
