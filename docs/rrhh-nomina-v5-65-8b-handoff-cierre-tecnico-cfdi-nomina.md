# V5.65.8b - Handoff / cierre tecnico CFDI nomina

## Estado general

CFDI nomina quedo cerrado tecnicamente en DEV hasta el flujo seguro previo a produccion.

La prueba integral V5.65.8a confirmo:

- Rama: bexia-erp/develop.
- Git limpio al inicio y al final.
- Sintaxis PHP correcta.
- Caches Laravel / Filament limpiadas.
- Comandos Artisan CFDI nomina disponibles.
- Ruta PDF disponible.
- UI validada con acciones XML, timbrado, PDF y cancelacion.
- XML listos: 2.
- PDF listo: 1.
- Timbrado real bloqueado en DEV.
- Cancelacion real bloqueada en DEV.
- Sin envio PAC/SAT en DEV.
- stamp_sending_to_pac = 0.
- stamp_success = 0.
- cancel_sending_to_pac = 0.
- cancel_success = 0.

## Commits principales cerrados

- aa478cd V5.65.7c UI cancelar CFDI nomina
- 08ff6d6 V5.65.7b conectar cancelacion CFDI nomina a PAC SW
- 69f0f60 V5.65.7a guard cancelacion CFDI nomina
- 4d49a7e V5.65.6a PDF recibo CFDI nomina
- bc15c39 V5.65.5e timbrado demo CFDI nomina para PDF
- 7737908 V5.65.5d UI timbrar CFDI nomina con guard
- 22c7265 V5.65.5c conectar timbrado CFDI nomina a PAC SW
- 57a2cff V5.65.5b servicio timbrado CFDI nomina bloqueado
- df712f2 V5.65.5a guard timbrado CFDI nomina solo PROD
- e4afb43 V5.65.4c ver y descargar XML CFDI nomina
- e57e6ce V5.65.4b UI generar XML CFDI nomina
- 975bf76 V5.65.4a XML borrador CFDI nomina
- d41e7be V5.65.3c acciones CFDI desde nomina
- bbb3eb2 V5.65.3b UI recibos CFDI nomina
- 0c65cf5 V5.65.3a preparar recibos CFDI nomina borrador
- 1d3cc0a V5.65.2 preparar CFDI nomina readiness
- e575979 V5.64.29 cierre final RRHH y nomina

## Que quedo listo en DEV

### 1. Readiness fiscal CFDI nomina

Archivos:

- app/Support/PayrollCfdi/PayrollCfdiReadinessService.php
- app/Console/Commands/ValidatePayrollCfdiReadinessCommand.php

Comando:

    php artisan payroll:cfdi-readiness --company=5

Sirve para validar preparacion fiscal de empresa, empleados, contratos y conceptos antes de preparar CFDI nomina.

### 2. Recibos CFDI nomina

Archivos:

- app/Models/PayrollCfdiReceipt.php
- app/Support/PayrollCfdi/PayrollCfdiReceiptPreparationService.php
- app/Console/Commands/PreparePayrollCfdiReceiptsCommand.php
- app/Filament/Resources/PayrollCfdiReceiptResource.php

Comando:

    php artisan payroll:cfdi-prepare-receipts --company=5 --payroll-run=ID

Genera recibos CFDI nomina desde una corrida cerrada.

### 3. XML borrador

Archivo:

- app/Support/PayrollCfdi/PayrollCfdiXmlDraftService.php

Comando:

    php artisan payroll:cfdi-prepare-xml-drafts --company=5 --payroll-run=ID

Genera XML tecnico en borrador. No timbra y no envia al PAC/SAT.

### 4. Ver, copiar y descargar XML

Disponible desde la UI de recibos CFDI nomina.

### 5. Timbrado real protegido

Archivos:

- config/payroll_cfdi.php
- app/Support/PayrollCfdi/PayrollCfdiStampingGuardService.php
- app/Support/PayrollCfdi/PayrollCfdiStampService.php
- app/Console/Commands/StampPayrollCfdiCommand.php

Comando:

    php artisan payroll:cfdi-stamp --company=5 --receipt=ID

Regla:

- DEV bloquea siempre.
- PROD avanza solo si APP_ENV=production y PAYROLL_CFDI_STAMPING_ENABLED=true.
- Requiere PAC, CSD, XML listo y recibo validado.

Conexion PAC:

    App\Support\Billing\SwPacClient::stampSignedXml(Company $company, string $xml)

### 6. UI Timbrar CFDI

Boton disponible en:

- listado de recibos CFDI nomina
- vista detalle del recibo

En DEV muestra bloqueo. En PROD sera la accion real.

### 7. Timbrado demo DEV

Archivos:

- app/Support/PayrollCfdi/PayrollCfdiDevDemoStampService.php
- app/Console/Commands/DevDemoStampPayrollCfdiCommand.php

Comandos:

    php artisan payroll:cfdi-demo-stamp --company=5 --receipt=ID
    php artisan payroll:cfdi-demo-stamp --company=5 --receipt=ID --restore

Uso:

- solo DEV
- no PAC
- no SAT
- UUID demo
- XML demo
- permite revisar PDF con caso timbrado

### 8. PDF recibo nomina

Archivos:

- app/Support/PayrollCfdi/PayrollCfdiReceiptPdfService.php
- app/Console/Commands/GeneratePayrollCfdiReceiptPdfCommand.php
- app/Http/Controllers/PayrollCfdiReceiptPdfController.php
- resources/views/payroll-cfdi/receipt-pdf.blade.php

Comando:

    php artisan payroll:cfdi-generate-pdf --company=5 --receipt=ID --force

Ruta:

    GET /payroll-cfdi-receipts/{receipt}/pdf
    name: payroll-cfdi-receipts.pdf

UI:

- Generar PDF
- Ver PDF

Si el recibo es demo, muestra:

    TIMBRADO DEMO DEV - NO FISCAL - NO PAC/SAT

### 9. Cancelacion CFDI nomina protegida

Archivos:

- app/Support/PayrollCfdi/PayrollCfdiCancellationGuardService.php
- app/Support/PayrollCfdi/PayrollCfdiCancelService.php
- app/Console/Commands/CancelPayrollCfdiCommand.php

Comando:

    php artisan payroll:cfdi-cancel --company=5 --receipt=ID --reason=02

Regla:

- DEV bloquea siempre.
- PROD avanza solo si APP_ENV=production y PAYROLL_CFDI_CANCELLATION_ENABLED=true.
- Requiere PAC configurado, recibo timbrado, UUID real y no dev-demo.

Conexion PAC:

    App\Support\Billing\SwPacClient::cancelCfdi(Company $company, string $uuid, string $reasonCode, ?string $replacementUuid = null)

### 10. UI Cancelar CFDI

Boton disponible en:

- listado de recibos CFDI nomina
- vista detalle del recibo

Modal:

- motivo SAT de cancelacion
- UUID relacionado opcional

Motivos SAT:

- 01 Comprobante emitido con errores con relacion
- 02 Comprobante emitido con errores sin relacion
- 03 No se llevo a cabo la operacion
- 04 Operacion nominativa relacionada en factura global

## Que se puede usar en DEV

- preparar CFDI nomina
- generar recibos
- generar XML borrador
- ver/copiar/descargar XML
- generar timbrado demo
- generar PDF
- revisar UI
- validar bloqueo de timbrado real
- validar bloqueo de cancelacion real

## Que solo se debe probar en PROD

- timbrado real
- UUID real
- XML timbrado real
- cancelacion real
- acuse real de cancelacion
- validacion completa con CSD y PAC reales

## Release DEV a MAIN

Precondiciones:

- V5.65.8a prueba integral OK.
- V5.65.8b handoff commiteado.
- Git limpio en develop.
- Ultimo commit en develop debe incluir V5.65.8b.

Pasos sugeridos:

    cd /opt/bexia/dev || exit 1
    git fetch origin
    git checkout bexia-erp/develop
    git pull origin bexia-erp/develop
    git status --short

    git checkout bexia-erp/main
    git pull origin bexia-erp/main
    git merge --no-ff bexia-erp/develop -m "V5.65.9a release CFDI nomina DEV to MAIN"
    git push origin bexia-erp/main

Tag recomendado:

    git tag -a v5.65.9a-cfdi-nomina-release -m "V5.65.9a release CFDI nomina"
    git push origin v5.65.9a-cfdi-nomina-release

Volver a develop:

    git checkout bexia-erp/develop
    git status --short

## Checklist PROD antes de timbrado real

Validar codigo desplegado en /opt/bexia/app.

Validar variables:

- APP_ENV=production
- PAYROLL_CFDI_STAMPING_ENABLED=true
- PAYROLL_CFDI_STAMPING_ALLOWED_ENV=production
- PAYROLL_CFDI_CANCELLATION_ENABLED=false al inicio
- PAYROLL_CFDI_CANCELLATION_ALLOWED_ENV=production

Validar empresa:

- RFC
- razon social
- regimen fiscal
- codigo postal fiscal
- PAC provider
- usuario PAC
- password/token PAC
- certificado CSD
- llave CSD
- password CSD

Validar empleados y contratos:

- RFC empleado
- CURP
- NSS si aplica
- contrato activo
- tipo contrato SAT
- regimen nomina SAT
- periodicidad
- salario diario
- salario integrado

Validar nomina:

- corrida cerrada
- lineas correctas
- percepciones y deducciones revisadas
- neto revisado

## Primera prueba controlada PROD

Recomendacion:

1. Usar una nomina pequena.
2. Timbrar primero por comando, no por UI.
3. Timbrar un solo recibo.
4. Revisar auditorias y XML.
5. Generar PDF.
6. Usar UI despues de confirmar el comando.

Flujo:

    php artisan payroll:cfdi-readiness --company=ID
    php artisan payroll:cfdi-prepare-receipts --company=ID --payroll-run=ID
    php artisan payroll:cfdi-prepare-xml-drafts --company=ID --payroll-run=ID
    php artisan payroll:cfdi-stamp --company=ID --receipt=ID
    php artisan payroll:cfdi-generate-pdf --company=ID --receipt=ID --force

Esperado:

- status stamped
- uuid real
- auditoria stamp success
- XML timbrado
- PDF sin marca DEMO

Cancelacion:

No cancelar el primer CFDI salvo que sea indispensable.

Si se requiere prueba de cancelacion:

1. Activar PAYROLL_CFDI_CANCELLATION_ENABLED=true.
2. Limpiar cache.
3. Ejecutar php artisan payroll:cfdi-cancel --company=ID --receipt=ID --reason=02.
4. Validar status cancelled.
5. Validar auditoria cancel success.
6. Volver a false si no se dejara activo.

## Pendientes posteriores

### Contabilidad de nomina

- cuentas contables por concepto
- poliza contable de nomina
- asiento de sueldos, impuestos, deducciones y provisiones
- conexion con contabilidad general
- reversas o ajustes si cambia/cancela una nomina

### Dashboard RRHH

- empleados activos
- nominas abiertas y cerradas
- CFDI pendientes de timbrar
- CFDI timbrados
- CFDI con error
- costo de nomina por periodo
- incidencias y asistencia
- alertas de contratos/documentacion

### Caminos alternos

- nomina interna sin timbrado
- empresa que timbra fuera de Bexia
- importacion de UUID externos
- recibo PDF interno sin CFDI
- conciliacion con XML externos
