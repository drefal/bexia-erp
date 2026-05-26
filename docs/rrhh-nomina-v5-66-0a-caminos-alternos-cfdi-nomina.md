# V5.66.0a - Caminos alternos CFDI nómina

## Objetivo

Definir los caminos alternos de CFDI nómina para empresas que no necesariamente timbran desde Bexia.

Este documento parte del estado posterior a V5.65.9:

- CFDI nómina existe en DEV y PROD.
- Se pueden preparar recibos CFDI.
- Se puede generar XML borrador.
- Se puede generar PDF.
- Timbrado real está protegido por guard.
- Cancelación real está protegida por guard.
- PROD validó bloqueo de timbrado sin envío PAC/SAT.

## Problema

No todas las empresas van a operar nómina fiscal de la misma forma.

Bexia debe soportar al menos cuatro escenarios:

1. Nómina fiscal completa en Bexia.
2. Nómina interna sin timbrado.
3. Nómina timbrada fuera de Bexia.
4. CFDI no requerido o no aplicable para ciertos casos.

## Escenario A - Timbrado completo en Bexia

Uso esperado:

- Bexia calcula/cierra nómina.
- Bexia prepara recibos.
- Bexia genera XML borrador.
- Bexia timbra con PAC.
- Bexia guarda UUID, XML timbrado y PDF.
- Bexia puede cancelar CFDI si se habilita cancelación.

Estados actuales aplicables:

- draft
- validated
- stamped
- stamp_error
- cancelled
- cancel_error

Este flujo ya quedó cubierto en V5.65.

## Escenario B - Recibo interno sin CFDI

Uso esperado:

- La empresa usa Bexia solo para control interno.
- No requiere timbrar nómina desde Bexia.
- Se genera PDF interno.
- El recibo no debe quedar como error fiscal.
- Debe quedar claro que no tiene validez CFDI.

Estado propuesto:

- internal_only

Campos/metadatos propuestos:

- metadata.internal_only = true
- metadata.internal_only_reason
- metadata.internal_only_marked_by
- metadata.internal_only_marked_at

Acciones UI propuestas:

- Marcar como recibo interno
- Revertir a pendiente CFDI
- Generar PDF interno

Regla:

- No debe intentar PAC.
- No debe exigir UUID.
- Debe mostrar marca visible en PDF: Recibo interno sin validez CFDI.

## Escenario C - Timbrado externo

Uso esperado:

- La empresa calcula o controla nómina en Bexia.
- El timbrado fiscal se hace en otro sistema.
- Bexia permite registrar UUID externo.
- Bexia permite adjuntar/cargar XML timbrado externo.
- Opcionalmente se genera PDF desde el XML o se conserva PDF externo.

Estado propuesto:

- external_stamped

Campos/metadatos propuestos:

- uuid
- xml_path
- pac_provider = external
- metadata.external_stamp = true
- metadata.external_uuid
- metadata.external_xml_original_name
- metadata.external_registered_by
- metadata.external_registered_at
- metadata.external_notes

Acciones UI propuestas:

- Registrar CFDI externo
- Cargar XML timbrado externo
- Descargar XML externo
- Generar/ver PDF
- Revertir registro externo, si tiene permiso especial

Regla:

- No debe llamar PAC.
- Debe validar que exista UUID.
- Debe validar que el XML tenga UUID cuando sea posible.
- Debe dejar auditoría.

## Escenario D - CFDI no requerido / no aplicable

Uso esperado:

- Casos donde la línea/recibo no debe timbrarse.
- Ajustes internos.
- Corridas históricas.
- Migraciones.
- Nóminas no fiscales.

Estado propuesto:

- cfdi_not_required

Campos/metadatos propuestos:

- metadata.cfdi_not_required = true
- metadata.cfdi_not_required_reason
- metadata.cfdi_not_required_by
- metadata.cfdi_not_required_at

Acciones UI propuestas:

- Marcar CFDI no requerido
- Revertir a pendiente CFDI

Regla:

- No debe intentar PAC.
- No debe tratarse como error.
- Debe aparecer separado de pendientes reales de timbrado.

## Estados propuestos finales

Estados actuales a conservar:

- draft
- validated
- stamped
- stamp_error
- cancelled
- cancel_error

Estados nuevos propuestos:

- internal_only
- external_stamped
- cfdi_not_required

Estados auxiliares posibles:

- external_xml_error
- internal_pdf_ready

## Auditoría propuesta

Usar payroll_cfdi_audits con acciones nuevas:

- mark_internal_only
- unmark_internal_only
- register_external_stamp
- remove_external_stamp
- mark_cfdi_not_required
- unmark_cfdi_not_required
- upload_external_xml
- generate_internal_pdf

Cada acción debe guardar:

- company_id
- payroll_cfdi_receipt_id
- payroll_run_id
- payroll_run_line_id
- employee_id
- user_id
- action
- status
- request_meta
- response_meta
- message

## Configuración por empresa

Se propone agregar configuración operativa por empresa en una fase posterior:

- payroll_cfdi_mode

Valores sugeridos:

- bexia_stamp
- internal_only
- external_stamp
- not_required

Esta configuración no debe activar timbrado real por sí sola. El timbrado real seguirá dependiendo de:

- APP_ENV=production
- PAYROLL_CFDI_STAMPING_ENABLED=true
- PAC/CSD completos
- guard aprobado

## UI propuesta

En PayrollCfdiReceiptResource:

### Para recibos validated

Acciones:

- Timbrar CFDI
- Generar PDF
- Descargar XML
- Marcar como recibo interno
- Registrar CFDI externo
- Marcar CFDI no requerido

### Para internal_only

Acciones:

- Ver PDF interno
- Revertir a pendiente CFDI

### Para external_stamped

Acciones:

- Ver XML externo
- Descargar XML externo
- Generar/ver PDF
- Revertir registro externo

### Para cfdi_not_required

Acciones:

- Revertir a pendiente CFDI

## Seguridad

Las acciones deben tener permisos separados:

- nomina.cfdi.marcar_interno
- nomina.cfdi.registrar_externo
- nomina.cfdi.marcar_no_requerido
- nomina.cfdi.revertir_alterno

## Orden de implementación recomendado

### V5.66.0b

Agregar estados alternos y helper de metadata.

### V5.66.0c

Agregar acciones backend:

- mark internal only
- mark not required
- register external stamp

### V5.66.0d

Agregar UI en PayrollCfdiReceiptResource.

### V5.66.0e

Agregar PDF interno con marca visible.

### V5.66.0f

Prueba integral DEV.

### V5.66.0g

Handoff y release.

## Pendientes para decidir

1. Si el XML externo se cargará desde UI en esta fase o solo se registrará ruta/UUID.
2. Si se permitirá adjuntar PDF externo.
3. Si se creará configuración global por empresa desde el inicio o después.
4. Si los estados alternos deben reflejarse también en payroll_runs.payroll_cfdi_status.
5. Si internal_only debe generar recibo PDF aunque no exista XML.
