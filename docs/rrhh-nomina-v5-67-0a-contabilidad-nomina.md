# V5.67.0a - Contabilidad de nómina

## Objetivo

Diseñar la integración contable de nómina para generar pólizas/asientos desde una nómina cerrada.

Este bloque parte de:

- V5.65 CFDI nómina base cerrado.
- V5.66 caminos alternos CFDI nómina cerrado.
- Timbrado real y cancelación real siguen protegidos por guards.
- Las empresas pueden manejar recibo interno, CFDI no requerido o timbrado externo.

## Problema

La nómina cerrada genera obligaciones contables:

1. Gasto por sueldos y percepciones.
2. Pasivos por sueldos por pagar.
3. Pasivos por impuestos retenidos.
4. Pasivos por deducciones y descuentos.
5. Posibles movimientos de tesorería al pagar la nómina.
6. Relación con CFDI, aunque la póliza no debe depender obligatoriamente del timbrado.

## Flujo recomendado

### Fase 1 - Póliza contable desde nómina cerrada

Entrada:

- payroll_runs
- payroll_run_lines
- payroll_line_concepts, si existe
- payroll_concepts
- employees
- employee_contracts
- payroll_cfdi_receipts, solo como referencia fiscal

Salida esperada:

- Una póliza/asiento contable por corrida de nómina.
- Líneas contables por tipo de concepto.
- Auditoría para evitar duplicados.
- Referencia a payroll_run_id.

### Fase 2 - Pago de nómina

Entrada:

- Nómina cerrada y contabilizada.
- Cuenta bancaria/tesorería.

Salida esperada:

- Movimiento de tesorería.
- Cancelación del pasivo de sueldos por pagar.
- Referencia al asiento contable o póliza.

### Fase 3 - Configuración por empresa

Configurar cuentas contables por empresa:

- Gasto sueldos.
- Gasto horas extra.
- Gasto bonos/comisiones.
- Pasivo sueldos por pagar.
- ISR retenido por pagar.
- IMSS/seguridad social por pagar.
- Préstamos/descuentos empleados.
- Otras deducciones.
- Banco/caja para pago.

## Asiento contable base propuesto

Ejemplo nómina neta:

Debe:

- Gasto de sueldos y salarios.
- Gasto de prestaciones/percepciones adicionales.
- Gasto de cargas sociales si se implementan después.

Haber:

- Sueldos por pagar, por importe neto.
- ISR retenido por pagar.
- IMSS/seguridad social por pagar.
- Otras deducciones por pagar.
- Préstamos empleados por cobrar, si corresponde como recuperación.

## Reglas de negocio

1. Solo contabilizar nóminas cerradas/aprobadas.
2. No duplicar póliza si ya existe.
3. Permitir reversa/cancelación contable con permiso especial.
4. El timbrado CFDI no debe bloquear la póliza si la empresa usa recibo interno o timbrado externo.
5. Debe quedar referencia a:
   - company_id
   - payroll_run_id
   - payroll_run_line_id cuando aplique
   - payroll_concept_id cuando aplique
   - employee_id cuando aplique
6. Debe tener auditoría.
7. El asiento debe cuadrar: debe = haber.

## Estados propuestos para payroll_runs

Si existe columna de estado contable o metadata:

- accounting_status = pending
- accounting_status = posted
- accounting_status = reversed
- accounting_status = error

Si no existe, se propone agregar en fase V5.67.0b:

- payroll_accounting_entries
- payroll_accounting_entry_lines
- payroll_accounting_mappings

O conectar con el módulo contable existente si ya hay tablas de pólizas/asientos.

## Comando propuesto

    php artisan payroll:accounting-post --company=5 --payroll-run=36

Opciones futuras:

    --dry-run
    --force
    --reverse
    --date=YYYY-MM-DD

## UI propuesta

En nómina cerrada:

- Generar póliza contable.
- Ver póliza contable.
- Revertir póliza contable, si no tiene pagos relacionados.
- Descargar resumen contable.

En configuración:

- Mapeo contable de conceptos de nómina.
- Cuenta por defecto para percepciones.
- Cuenta por defecto para deducciones.
- Cuenta por defecto para neto por pagar.

## Pruebas mínimas

1. Dry-run de asiento desde RUN36.
2. Validar que debe = haber.
3. Generar póliza real en DEV.
4. Evitar duplicado.
5. Revertir póliza.
6. Validar que no afecta CFDI.
7. Validar que no envía PAC/SAT.

## Pendientes a decidir con diagnóstico

1. Si ya existe tabla de asientos contables aprovechable.
2. Si se debe crear una tabla puente propia para nómina.
3. Si los conceptos actuales tienen cuenta contable asignada o se agregará mapeo.
4. Si la póliza será una por corrida o una por empleado.
5. Si pagos de nómina se conectarán de inmediato a tesorería o quedarán para V5.67.x posterior.
