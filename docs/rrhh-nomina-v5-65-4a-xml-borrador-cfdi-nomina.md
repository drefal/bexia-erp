# V5.65.4a - XML CFDI nomina en borrador

Objetivo:
Generar XML CFDI de nomina en borrador local desde recibos preparados.

Alcance:
- Servicio PayrollCfdiXmlDraftService.
- Comando payroll:cfdi-prepare-xml-drafts.
- Guarda XML local en storage/app/payroll-cfdi/drafts.
- Actualiza xml_path en payroll_cfdi_receipts.
- Cambia recibos a status validated.
- Registra auditoria prepare_xml_draft.
- No timbra.
- No genera sello.
- No genera cadena original.
- No envia datos a PAC/SAT.

Uso:
php artisan payroll:cfdi-prepare-xml-drafts --company=5 --payroll-run=ID

Uso regenerando XML:
php artisan payroll:cfdi-prepare-xml-drafts --company=5 --payroll-run=ID --force

Pendiente:
- V5.65.4b: boton o accion UI para generar XML desde nomina o recibos.
- V5.65.4c: vista/descarga segura de XML.
- V5.65.5: timbrado sandbox/PAC.
