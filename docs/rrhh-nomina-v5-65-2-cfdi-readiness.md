# V5.65.2 - CFDI nomina readiness

## Objetivo

Preparar la base funcional de CFDI de nomina sin timbrado real.

Este bloque no envia informacion al PAC/SAT. Solo agrega estructura, validaciones, campos de captura y datos demo para validar el flujo en DEV.

## Alcance implementado

### Estructura

Se agrego migracion para:

- Campos fiscales de empleado:
  - fiscal_name
  - fiscal_postal_code
  - sat_tax_regime_code
  - social_security_number

- Campos SAT de contrato:
  - sat_contract_type_code
  - sat_workday_type_code
  - sat_regime_type_code
  - sat_risk_position_code
  - daily_salary
  - integrated_daily_salary
  - is_unionized

- Campos fiscales de conceptos:
  - is_taxable
  - taxable_amount_default
  - exempt_amount_default

- Campos de estatus CFDI en nomina y lineas.

- Tablas nuevas:
  - payroll_cfdi_receipts
  - payroll_cfdi_audits

### Servicio y comando

Se agrego:

    php artisan payroll:cfdi-readiness --company=5

El comando valida preparacion fiscal de nomina sin timbrar.

### Formularios Filament

Se agregaron campos en:

- Empleados:
  - Pestana Fiscal / CFDI nomina

- Contratos:
  - Seccion CFDI nomina SAT

- Conceptos de nomina:
  - Clave SAT nomina
  - Gravado para CFDI
  - Importe gravado default
  - Importe exento default

### DEV / BexiaDemo

Para pruebas en DEV se cargaron datos demo solo en:

- BexiaDemo company_id=5

No se uso Papeleria Papelon.

## Resultado validado

El validador termino con:

    RESULTADO: LISTO PARA PREPARAR CFDI NOMINA
    VALIDATION_EXIT=0

## Pendiente siguiente

V5.65.3 debe preparar recibos CFDI de nomina en borrador por corrida cerrada, todavia sin timbrado real.

Flujo esperado siguiente:

1. Seleccionar corrida de nomina cerrada.
2. Validar payroll:cfdi-readiness --company=5 --payroll-run=<ID>.
3. Crear recibos en payroll_cfdi_receipts.
4. Generar snapshots fiscales.
5. Preparar vista/listado de recibos CFDI nomina.
6. Mas adelante: generacion XML y timbrado PAC/SAT.
