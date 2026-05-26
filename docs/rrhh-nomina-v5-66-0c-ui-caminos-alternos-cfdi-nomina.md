# V5.66.0c - UI caminos alternos CFDI nómina

## Objetivo

Agregar acciones visibles en Filament para manejar caminos alternos de CFDI nómina sin enviar nada al PAC/SAT.

## Archivos modificados

- app/Filament/Resources/PayrollCfdiReceiptResource.php
- app/Filament/Resources/PayrollCfdiReceiptResource/Pages/ViewPayrollCfdiReceipt.php

## Acciones agregadas

En listado y vista del recibo CFDI nómina:

- Recibo interno
- CFDI no requerido
- Timbrado externo
- Revertir alterno

## Estados alternos visibles

- internal_only: Recibo interno
- external_stamped: Timbrado externo
- cfdi_not_required: CFDI no requerido

## Reglas

- Las acciones alternas solo aparecen en recibos draft, validated, stamp_error o error.
- Revertir alterno solo aparece en internal_only, cfdi_not_required o external_stamped.
- Timbrado externo no envía PAC/SAT; solo registra UUID externo y auditoría.
- Si se informa ruta XML externa, debe existir en storage local.
- No se permiten caminos alternos sobre recibos stamped o cancelled desde el backend.

## Próximo paso

V5.66.0c1 debe validar desde comando/UI que:
- Los botones aparecen.
- Las acciones cambian estado y revierten.
- No hay envío PAC/SAT.
