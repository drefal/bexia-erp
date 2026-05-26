# V5.65.4c - Ver y descargar XML CFDI nomina

Objetivo:
Permitir consultar y descargar el XML CFDI de nomina generado en borrador desde la UI.

Alcance:
- Accion Descargar XML en PayrollCfdiReceiptResource.
- Seccion XML borrador en la vista detalle del recibo.
- Muestra ruta y contenido XML.
- Permite copiar el XML desde la vista.
- Descarga el archivo XML desde storage local.
- No timbra.
- No genera UUID.
- No envia datos al PAC/SAT.

Regla operativa:
El timbrado real se reserva para PROD. En DEV solo se permite preparar, validar, generar XML borrador y revisar/descargar XML.

Pendiente:
- V5.65.5: preparar capa de timbrado con bloqueo por ambiente para que solo PROD pueda timbrar.
