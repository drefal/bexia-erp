# V5.67.0c - UI contabilidad de nómina

## Objetivo

Conectar el backend de contabilidad de nómina a Filament.

## Archivos modificados

- app/Support/Accounting/PayrollAccountingPoster.php
- app/Filament/Resources/PayrollRunResource.php
- app/Filament/Resources/PayrollRunResource/Pages/EditPayrollRun.php

## Cambios

### Backend

Se ajustó el folio contable para permitir re-post después de una reversa.

Ejemplo:

- GEN-NOM-00000036
- GEN-NOM-00000036-02

### Listado de pre-nómina

Se agregó columna:

- Contabilidad

Estados:

- Pendiente
- Contabilizada
- Borrador contable
- Reversada

### Acciones agregadas

En listado y edición de pre-nómina:

- Config. contable
- Resumen contable
- Generar póliza
- Ver póliza
- Revertir póliza

## Reglas

- Solo muestra acciones para usuarios con permisos de nómina/contabilidad/admin.
- Solo contabiliza nóminas cerradas, aprobadas o pagadas.
- No afecta CFDI.
- No envía PAC/SAT.
- La reversa genera asiento inverso y cancela la póliza original.

## Prueba DEV

Se usó:

- company_id=5
- payroll_run_id=36

Resultado esperado:

- Dry-run balanceado.
- Post correcto.
- Reversa correcta.
- Re-post posterior a reversa correcto.
- Sin póliza activa al cierre de la prueba automática.
