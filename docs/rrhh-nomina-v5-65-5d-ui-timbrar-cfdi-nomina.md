# V5.65.5d - UI timbrar CFDI nomina

Objetivo:
Agregar accion visual para intentar timbrar CFDI nomina desde la UI.

Alcance:
- Boton Timbrar CFDI en listado de recibos CFDI nomina.
- Boton Timbrar CFDI en vista detalle del recibo.
- Usa PayrollCfdiStampService.
- El servicio ejecuta PayrollCfdiStampingGuardService antes de tocar PAC.
- En DEV debe bloquear.
- En DEV no debe generar UUID.
- En DEV no debe crear auditoria sending_to_pac ni success.
- En PROD podra timbrar cuando:
  - APP_ENV=production
  - PAYROLL_CFDI_STAMPING_ENABLED=true
  - PAC/CSD de la empresa esten configurados.

Regla operativa:
El timbrado real de nomina solo se permite en PROD.

Pendiente:
- Probar flujo real en PROD con una nomina controlada.
- Generar PDF de recibo nomina.
- Cancelacion CFDI nomina.
