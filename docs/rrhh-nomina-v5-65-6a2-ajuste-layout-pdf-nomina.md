# V5.65.6a2 - Ajuste visual PDF recibo nomina

Objetivo:
Ajustar el layout del PDF del recibo de nomina para que se vea mas como un recibo clasico:
- encabezado compacto
- datos del empleado y periodo
- percepciones a la izquierda
- deducciones a la derecha
- resumen de totales al final

Alcance:
- Solo cambia la vista Blade:
  resources/views/payroll-cfdi/receipt-pdf.blade.php
- No cambia la logica de timbrado.
- No cambia la logica de generacion del PDF.
- Mantiene la marca DEMO / NO FISCAL cuando aplica.

Resultado esperado:
- PDF mas ordenado y legible
- percepciones y deducciones claramente separadas
- neto visible al final
