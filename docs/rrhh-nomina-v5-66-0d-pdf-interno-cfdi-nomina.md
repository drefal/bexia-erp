# V5.66.0d - PDF interno y estados alternos CFDI nómina

## Objetivo

Permitir que los recibos CFDI nómina en caminos alternos generen PDF con marca visible.

## Archivos modificados

- app/Support/PayrollCfdi/PayrollCfdiReceiptPdfService.php
- resources/views/payroll-cfdi/receipt-pdf.blade.php
- app/Filament/Resources/PayrollCfdiReceiptResource.php
- app/Filament/Resources/PayrollCfdiReceiptResource/Pages/ViewPayrollCfdiReceipt.php

## Marcas visibles

- internal_only: RECIBO INTERNO - NO CFDI
- cfdi_not_required: CFDI NO REQUERIDO
- external_stamped: TIMBRADO EXTERNO
- dev-demo: DEMO - NO FISCAL

## Cambios UI

La acción Generar PDF ahora aparece para:

- validated
- stamped
- internal_only
- external_stamped
- cfdi_not_required

## Reglas

- No se llama PAC/SAT.
- El PDF de recibo interno muestra explícitamente que no tiene validez CFDI.
- El PDF de CFDI no requerido indica que quedó fuera del flujo fiscal.
- El PDF de timbrado externo indica que el UUID fue registrado manualmente.
