# V5.67.0e - Cierre técnico contabilidad de nómina

## Alcance cerrado

Se implementó la contabilidad de nómina usando la base contable existente.

## Commits incluidos

- V5.67.0a diseño contabilidad nómina.
- V5.67.0b backend contabilidad nómina.
- V5.67.0c UI contabilidad nómina.
- V5.67.0c1 fix de reversa contable por póliza.
- V5.67.0d2 traducción de etiquetas UI nómina/contabilidad.

## Archivos principales

- app/Support/Accounting/PayrollAccountingPoster.php
- app/Console/Commands/PayrollAccountingCommand.php
- app/Filament/Resources/PayrollRunResource.php
- app/Filament/Resources/PayrollRunResource/Pages/EditPayrollRun.php
- app/Filament/Resources/AccountingEntryResource.php
- app/Filament/Resources/AccountingEntryResource/RelationManagers/LinesRelationManager.php

## Comando agregado

    php artisan payroll:accounting

Acciones:

    setup-defaults
    dry-run
    post
    reverse
    summary

## Cuentas/mapeos creados por defecto

- 601.90 Sueldos y salarios
- 210.90 Sueldos por pagar
- 210.91 Retenciones de nómina por pagar
- 210.92 Deducciones de nómina por pagar

## Flujo funcional

Desde una nómina cerrada/aprobada/pagada:

1. Preparar configuración contable.
2. Ver resumen contable.
3. Generar póliza.
4. Ver póliza.
5. Revertir póliza.

## Validación DEV

Nómina usada:

- company_id=5
- payroll_run_id=36
- net_total=10000.00

Resultado:

- Dry-run balanceado.
- Debe = Haber.
- Póliza genera asiento contable.
- Reversa genera asiento inverso.
- Cada póliza nueva tiene su propia reversa ligada por metadata.
- Al cierre no queda póliza activa de RUN36.

## UI

En pre-nóminas se agregó:

- Columna Contabilidad.
- Config. contable.
- Resumen contable.
- Generar póliza.
- Ver póliza.
- Revertir póliza.

En asientos contables se agregaron etiquetas en español para:

- Póliza de nómina.
- Reversa de nómina.
- Configuración contable de nómina.
- Contabilizado.
- Cancelado.
- Parcialmente timbrada.

## Seguridad

- No cambia tablas.
- No afecta CFDI.
- No envía PAC/SAT.
- No activa timbrado ni cancelación.
- La póliza no depende de que el CFDI esté timbrado.

## Pendientes futuros

1. Configuración UI editable de mapeos contables por concepto de nómina.
2. Póliza por empleado, si se requiere.
3. Conexión con pago de nómina y tesorería.
4. Reporte PDF/Excel de resumen contable de nómina.
5. Permisos específicos:
   - nomina.contabilidad.ver
   - nomina.contabilidad.generar
   - nomina.contabilidad.revertir
   - nomina.contabilidad.configurar
