# V5.65.5b - Servicio base de timbrado CFDI nomina

Objetivo:
Crear la capa segura para intentar timbrado CFDI nomina reutilizando la configuracion PAC/CSD existente, pero bloqueada en DEV por el guard de V5.65.5a.

Alcance:
- Servicio PayrollCfdiStampService.
- Comando payroll:cfdi-stamp.
- El servicio siempre ejecuta PayrollCfdiStampingGuardService antes de cualquier intento.
- En DEV debe bloquear antes de tocar PAC/SAT.
- Registra auditoria con action=stamp y status=blocked/error.
- No timbra todavia en DEV.
- No envia datos al PAC/SAT en DEV.

Uso:
php artisan payroll:cfdi-stamp --company=5 --receipt=ID

Estado esperado en DEV:
RESULTADO: TIMBRADO CFDI NOMINA BLOQUEADO

Siguiente:
V5.65.5c debe conectar el servicio al PAC real reutilizando:
- App\Support\Billing\SwPacClient
- App\Support\Billing\InvoiceCfdiStampService
- Campos PAC/CSD en companies

Regla:
Timbrado real solo en PROD con:
- APP_ENV=production
- PAYROLL_CFDI_STAMPING_ENABLED=true
