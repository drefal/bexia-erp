# V5.65.7c - UI cancelar CFDI nomina

Objetivo:
Agregar accion visual para intentar cancelar CFDI nomina desde la UI.

Alcance:
- Boton Cancelar CFDI en listado de recibos CFDI nomina.
- Boton Cancelar CFDI en vista detalle.
- Modal con motivo SAT:
  - 01 Comprobante emitido con errores con relacion.
  - 02 Comprobante emitido con errores sin relacion.
  - 03 No se llevo a cabo la operacion.
  - 04 Operacion nominativa relacionada en factura global.
- Campo UUID relacionado opcional.
- Usa PayrollCfdiCancelService.
- El servicio ejecuta PayrollCfdiCancellationGuardService antes de tocar PAC.
- En DEV debe bloquear.
- En DEV no debe modificar recibos.
- En DEV no debe crear auditoria sending_to_pac ni success.
- En PROD podra cancelar cuando:
  - APP_ENV=production
  - PAYROLL_CFDI_CANCELLATION_ENABLED=true
  - PAC/CSD de la empresa esten configurados
  - Recibo tenga UUID real
  - Recibo no sea dev-demo

Regla operativa:
La cancelacion real de CFDI nomina solo se permite en PROD.
