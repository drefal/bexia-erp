# V5.66.0b - Backend caminos alternos CFDI nómina

## Objetivo

Agregar backend para operar caminos alternos de CFDI nómina sin tocar PAC/SAT.

## Archivos agregados

- app/Support/PayrollCfdi/PayrollCfdiAlternateFlowService.php
- app/Console/Commands/PayrollCfdiAlternateFlowCommand.php

## Comando agregado

php artisan payroll:cfdi-alternate

Acciones:

- summary
- internal-only
- not-required
- external-stamp
- revert

## Estados nuevos

- internal_only
- external_stamped
- cfdi_not_required

## Ejemplos

Resumen:

    php artisan payroll:cfdi-alternate summary --company=5 --receipt=2

Marcar recibo interno:

    php artisan payroll:cfdi-alternate internal-only --company=5 --receipt=2 --reason="Recibo interno sin CFDI"

Marcar CFDI no requerido:

    php artisan payroll:cfdi-alternate not-required --company=5 --receipt=2 --reason="CFDI no aplicable"

Registrar timbrado externo:

    php artisan payroll:cfdi-alternate external-stamp --company=5 --receipt=2 --uuid=11111111-1111-4111-8111-111111111111 --notes="Timbrado en sistema externo"

Revertir:

    php artisan payroll:cfdi-alternate revert --company=5 --receipt=2 --reason="Prueba revertida"

Revertir timbrado externo:

    php artisan payroll:cfdi-alternate revert --company=5 --receipt=2 --reason="Prueba revertida" --force

## Seguridad

- No llama PAC.
- No modifica configuración de timbrado.
- No permite alternos sobre recibos stamped o cancelled.
- Registra auditoría en payroll_cfdi_audits.
- external-stamp exige UUID con formato válido.
- Si se pasa xml-path, valida que exista en storage local.

## Próximo paso

V5.66.0c debe agregar UI en PayrollCfdiReceiptResource para usar este servicio desde Filament.
