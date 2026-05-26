# V5.65.5c - Conectar timbrado CFDI nomina con PAC SW

Objetivo:
Conectar el servicio de timbrado CFDI nomina con la capa PAC existente de facturacion, manteniendo bloqueo total en DEV.

Alcance:
- PayrollCfdiStampService ahora inyecta App\Support\Billing\SwPacClient.
- Si el guard permite, carga XML y llama SwPacClient::stampSignedXml.
- Si el PAC responde success:
  - guarda XML timbrado en storage/app/payroll-cfdi/stamped
  - actualiza UUID
  - cambia recibo a stamped
  - actualiza payroll_runs
  - registra auditoria stamp success
- Si PAC responde error:
  - cambia recibo a error
  - guarda mensaje PAC
  - registra auditoria stamp error
- En DEV no debe llamar al PAC porque PayrollCfdiStampingGuardService bloquea antes.

Regla operativa:
Timbrado real solo en PROD con:
- APP_ENV=production
- PAYROLL_CFDI_STAMPING_ENABLED=true
- Configuracion PAC/CSD valida en companies

DEV:
- debe seguir mostrando RESULTADO: TIMBRADO CFDI NOMINA BLOQUEADO
- uuid_count debe ser 0
- no debe enviar datos al PAC/SAT

Pendiente:
- V5.65.5d: UI boton Timbrar visible/usable solo cuando guard permita.
- Prueba real de timbrado unicamente en PROD.
