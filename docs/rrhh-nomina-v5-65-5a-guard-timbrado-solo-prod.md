# V5.65.5a - Guard timbrado CFDI nomina solo PROD

Objetivo:
Agregar un candado tecnico para impedir que CFDI nomina se timbre fuera de PROD.

Regla principal:
- DEV puede preparar recibos, generar XML borrador, ver/copiar/descargar XML.
- DEV no puede timbrar.
- PROD es el unico ambiente autorizado para timbrar.
- Aun en PROD, debe existir PAYROLL_CFDI_STAMPING_ENABLED=true.

Variables:
- APP_ENV=production
- PAYROLL_CFDI_STAMPING_ALLOWED_ENV=production
- PAYROLL_CFDI_STAMPING_ENABLED=true

Comando:
php artisan payroll:cfdi-stamping-guard --company=5

Comando por recibo:
php artisan payroll:cfdi-stamping-guard --company=5 --receipt=ID

Notas:
- Este paso no timbra.
- Este paso no llama al PAC.
- Este paso prepara el candado antes de integrar timbrado real.
- Como facturacion ya timbra en PROD, el siguiente paso debe reutilizar la configuracion PAC/CSD ya existente para facturas.
