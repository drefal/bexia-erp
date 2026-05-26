# V5.65.5e - Timbrado demo DEV CFDI nomina

Objetivo:
Permitir marcar algunos recibos CFDI nomina como "timbrados demo" en DEV para revisar PDF, UUID visual y XML con nodo demo.

Reglas:
- Solo funciona fuera de production.
- No llama PAC.
- No llama SAT.
- No consume timbres.
- No tiene valor fiscal.
- Inserta UUID demo.
- Cambia status a stamped solo en DEV.
- Guarda XML demo en storage/app/payroll-cfdi/demo-stamped.
- Registra auditoria action=dev_demo_stamp.

Uso:
php artisan payroll:cfdi-demo-stamp --company=5 --receipt=ID

Uso automatico primer recibo disponible:
php artisan payroll:cfdi-demo-stamp --company=5

Revertir:
php artisan payroll:cfdi-demo-stamp --company=5 --receipt=ID --restore

Motivo:
Revisar PDF de recibo de nomina con un caso timbrado sin usar PAC/SAT.

Pendiente:
- V5.65.6a: generar PDF recibo nomina con marca DEMO cuando metadata.dev_demo_stamp=true.
