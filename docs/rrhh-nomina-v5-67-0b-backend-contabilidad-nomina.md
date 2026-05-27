# V5.67.0b - Backend contabilidad de nómina

## Objetivo

Crear el backend para generar pólizas contables desde nóminas cerradas usando las tablas contables existentes.

## Archivos creados

- app/Support/Accounting/PayrollAccountingPoster.php
- app/Console/Commands/PayrollAccountingCommand.php

## Comando agregado

    php artisan payroll:accounting

Acciones:

    php artisan payroll:accounting setup-defaults --company=5
    php artisan payroll:accounting dry-run --company=5 --run=36
    php artisan payroll:accounting post --company=5 --run=36
    php artisan payroll:accounting reverse --company=5 --run=36 --reason="Motivo"
    php artisan payroll:accounting summary --company=5 --run=36

## Tablas reutilizadas

- accounting_entries
- accounting_entry_lines
- accounting_accounts
- accounting_journals
- accounting_mappings
- accounting_journal_mappings
- accounting_posting_audits
- payroll_runs
- payroll_run_lines
- payroll_run_line_concepts

## Cuentas/mapeos por defecto

Se crean si no existen:

- payroll_expense: Sueldos y salarios
- payroll_payable: Sueldos por pagar
- payroll_tax_withholding_payable: Retenciones de nómina por pagar
- payroll_deduction_payable: Deducciones de nómina por pagar

## Póliza base

Debe:

- Sueldos y salarios por total bruto.

Haber:

- Sueldos por pagar por neto.
- Retenciones de nómina por pagar, si existen deducciones fiscales.
- Deducciones de nómina por pagar, si existen otras deducciones.

## Reglas

- Solo contabiliza nóminas cerradas/aprobadas/pagadas.
- Evita duplicado si ya existe póliza activa para payroll_run.
- La reversa genera un asiento inverso y marca la póliza original como cancelada.
- No depende del CFDI timbrado.
- No envía PAC/SAT.
