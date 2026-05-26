# V5.65.6a - PDF recibo CFDI nomina

Objetivo:
Generar PDF del recibo de nomina desde un recibo CFDI nomina.

Alcance:
- Servicio PayrollCfdiReceiptPdfService.
- Comando payroll:cfdi-generate-pdf.
- Controlador PayrollCfdiReceiptPdfController.
- Vista Blade resources/views/payroll-cfdi/receipt-pdf.blade.php.
- Ruta protegida por auth:
  /payroll-cfdi-receipts/{receipt}/pdf
- Botones en UI:
  - Generar PDF
  - Ver PDF
- Guarda PDF en storage/app/payroll-cfdi/pdfs.
- Actualiza pdf_path en payroll_cfdi_receipts.
- Registra auditoria action=generate_pdf.

Regla DEV:
Si metadata.dev_demo_stamp=true o pac_provider=dev-demo, el PDF muestra marca visible:
TIMBRADO DEMO DEV - NO FISCAL - NO PAC/SAT.

Pendiente:
- Revisar visualmente el PDF en navegador.
- Ajustar formato, logo, QR o leyendas legales si hace falta.
- V5.65.6b: commit posterior a revision visual.
