# V5.65.7b - Conectar cancelacion CFDI nomina a PAC SW

Objetivo:
Conectar la cancelacion CFDI nomina al cliente PAC SW existente, manteniendo el bloqueo en DEV.

Alcance:
- PayrollCfdiCancelService ahora inyecta App\Support\Billing\SwPacClient.
- Si el guard permite, intenta llamar un metodo compatible de cancelacion:
  - cancelPayrollCfdi
  - cancelPayrollUuid
  - cancelCfdi
  - cancelUuid
  - cancel
- En DEV el guard debe bloquear antes de enviar al PAC.
- En DEV no debe existir auditoria cancel success.
- En DEV no debe existir auditoria cancel sending_to_pac.
- En PROD, si el metodo existe y responde success:
  - status pasa a cancelled
  - metadata guarda cancel_reason, replacement_uuid, respuesta PAC
  - auditoria action=cancel status=success
  - opcionalmente guarda acuse/XML de cancelacion en storage/app/payroll-cfdi/cancellations

Regla:
Cancelacion real solo en PROD con:
- APP_ENV=production
- PAYROLL_CFDI_CANCELLATION_ENABLED=true
- UUID real
- PAC/CSD configurado
- Recibo status=stamped
- No dev-demo

Pendiente:
- Confirmar metodo exacto de SwPacClient en PROD.
- V5.65.7c: UI de cancelacion CFDI nomina.
