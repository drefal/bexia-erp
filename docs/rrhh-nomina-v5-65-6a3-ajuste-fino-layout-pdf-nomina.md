# V5.65.6a3 - Ajuste fino PDF recibo nomina

Objetivo:
Corregir dos detalles visuales del PDF:
- En columna Clave mostrar clave SAT del concepto, no el codigo interno.
- Separar Periodo del bloque principal de empleado para evitar que se vea encimado.

Cambios:
- Percepciones/Deducciones usan sat_key como primera opcion en Clave.
- Concepto usa name.
- Datos de empleado y periodo quedan en bloques separados.
- Periodo aparece como linea completa: fecha inicio al fecha fin.
- XML usa texto mas pequeno para evitar saturar el bloque CFDI.

No cambia:
- Timbrado
- UUID
- XML
- Totales
- Logica de PDF
