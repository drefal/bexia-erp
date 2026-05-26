# V5.65.6a4 - Textos SAT y reorden PDF recibo nomina

Objetivo:
Conservar el diseño visual aprobado y ajustar dos datos:
- Tipo de contrato debe mostrarse como texto, no como codigo.
- Regimen Fiscal Receptor debe ir en Datos del empleado, debajo/cerca de CURP, y como texto.

Cambios:
- Agrega catalogo local para tipos de contrato SAT.
- Agrega catalogo local para regimen fiscal receptor / tipo regimen nomina.
- Mueve Regimen Fiscal Receptor del bloque de periodo al bloque Datos del empleado.
- Mantiene percepciones, deducciones, resumen y marca DEMO sin cambios relevantes.

No cambia:
- Timbrado
- UUID
- XML
- Totales
- Ruta PDF
- Controlador
- Servicio PDF
