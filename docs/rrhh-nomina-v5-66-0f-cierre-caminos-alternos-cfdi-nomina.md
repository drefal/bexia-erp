# V5.66.0f - Cierre técnico caminos alternos CFDI nómina

## Alcance cerrado

Se implementaron caminos alternos para recibos CFDI nómina:

1. Recibo interno sin CFDI.
2. CFDI no requerido.
3. Timbrado externo registrado manualmente.
4. Reversión de estados alternos.
5. PDF con marca visible por estado alterno.
6. Auditoría de todas las acciones alternas.

## Commits incluidos

- V5.66.0a diseño caminos alternos CFDI nómina.
- V5.66.0b backend caminos alternos CFDI nómina.
- V5.66.0c UI caminos alternos CFDI nómina.
- V5.66.0d PDF interno caminos alternos CFDI nómina.

## Archivos principales

- app/Support/PayrollCfdi/PayrollCfdiAlternateFlowService.php
- app/Console/Commands/PayrollCfdiAlternateFlowCommand.php
- app/Support/PayrollCfdi/PayrollCfdiReceiptPdfService.php
- app/Filament/Resources/PayrollCfdiReceiptResource.php
- app/Filament/Resources/PayrollCfdiReceiptResource/Pages/ViewPayrollCfdiReceipt.php
- resources/views/payroll-cfdi/receipt-pdf.blade.php

## Comando agregado

    php artisan payroll:cfdi-alternate

Acciones:

- summary
- internal-only
- not-required
- external-stamp
- revert

## Estados alternos

- internal_only
- cfdi_not_required
- external_stamped

## UI agregada

En Recibos CFDI nómina:

- Recibo interno
- CFDI no requerido
- Timbrado externo
- Revertir alterno

## PDF

El PDF muestra marca visible:

- RECIBO INTERNO - NO CFDI
- CFDI NO REQUERIDO
- TIMBRADO EXTERNO

## Validación DEV

Recibo usado:

- company_id=5
- folio=RUN36-LINE70
- receipt_id=2

Resultado final esperado:

- status=validated
- uuid=null
- pac_provider=sw
- sin envío PAC/SAT

## Seguridad

- No se activó timbrado real.
- No se activó cancelación real.
- No se envió nada al PAC/SAT.
- Timbrado externo solo registra UUID externo en Bexia.
- Reversión de external_stamped requiere force en backend.

## Pendientes futuros

1. Permisos separados para acciones alternas:
   - nomina.cfdi.marcar_interno
   - nomina.cfdi.registrar_externo
   - nomina.cfdi.marcar_no_requerido
   - nomina.cfdi.revertir_alterno

2. Carga real de XML externo desde UI.

3. Carga de PDF externo, si se requiere.

4. Configuración por empresa:
   - bexia_stamp
   - internal_only
   - external_stamp
   - not_required

5. Integración con contabilidad de nómina.
