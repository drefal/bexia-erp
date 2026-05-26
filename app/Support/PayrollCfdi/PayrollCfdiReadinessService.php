<?php

namespace App\Support\PayrollCfdi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollCfdiReadinessService
{
    public function validateCompany(int $companyId, ?int $payrollRunId = null): array
    {
        $errors = [];
        $warnings = [];

        $company = DB::table('companies')->where('id', $companyId)->first();

        if (! $company) {
            return [
                'success' => false,
                'errors' => ["No existe la empresa {$companyId}."],
                'warnings' => [],
                'summary' => [],
            ];
        }

        $this->requireField($errors, $company, 'tax_id', 'La empresa no tiene RFC/tax_id.');
        $this->requireField($errors, $company, 'business_name', 'La empresa no tiene razón social/business_name.');
        $this->requireField($errors, $company, 'tax_regime', 'La empresa no tiene régimen fiscal/tax_regime.');
        $this->requireField($errors, $company, 'fiscal_postal_code', 'La empresa no tiene código postal fiscal.');

        $this->warnField($warnings, $company, 'billing_pac_provider', 'La empresa no tiene PAC configurado.');
        $this->warnField($warnings, $company, 'billing_pac_username', 'La empresa no tiene usuario PAC configurado.');
        $this->warnField($warnings, $company, 'billing_pac_password', 'La empresa no tiene contraseña PAC configurada.');
        $this->warnField($warnings, $company, 'billing_csd_certificate_path', 'La empresa no tiene certificado CSD cargado.');
        $this->warnField($warnings, $company, 'billing_csd_key_path', 'La empresa no tiene llave CSD cargada.');
        $this->warnField($warnings, $company, 'billing_csd_password', 'La empresa no tiene contraseña CSD configurada.');

        $employeeResult = $this->validateEmployees($companyId);
        $conceptResult = $this->validatePayrollConcepts($companyId);
        $runResult = $payrollRunId ? $this->validatePayrollRun($companyId, $payrollRunId) : [
            'errors' => [],
            'warnings' => ['No se validó una corrida de nómina específica porque no se envió --payroll-run.'],
            'summary' => [],
        ];

        $errors = array_merge($errors, $employeeResult['errors'], $conceptResult['errors'], $runResult['errors']);
        $warnings = array_merge($warnings, $employeeResult['warnings'], $conceptResult['warnings'], $runResult['warnings']);

        return [
            'success' => count($errors) === 0,
            'errors' => array_values($errors),
            'warnings' => array_values($warnings),
            'summary' => array_merge([
                'company_id' => $companyId,
                'company_name' => $company->name ?? null,
                'payroll_run_id' => $payrollRunId,
            ], $employeeResult['summary'], $conceptResult['summary'], $runResult['summary']),
        ];
    }

    private function validateEmployees(int $companyId): array
    {
        $errors = [];
        $warnings = [];

        if (! Schema::hasTable('employees')) {
            return [
                'errors' => ['No existe la tabla employees.'],
                'warnings' => [],
                'summary' => [],
            ];
        }

        $employees = DB::table('employees')
            ->where('company_id', $companyId)
            ->where(function ($query): void {
                if (Schema::hasColumn('employees', 'active')) {
                    $query->where('active', true);
                }
            })
            ->orderBy('id')
            ->get();

        foreach ($employees as $employee) {
            $label = trim((string) ($employee->employee_number ?? '')) !== ''
                ? "{$employee->employee_number} - {$employee->name}"
                : "{$employee->id} - {$employee->name}";

            $this->requireField($errors, $employee, 'rfc', "Empleado {$label}: falta RFC.");
            $this->requireField($errors, $employee, 'curp', "Empleado {$label}: falta CURP.");

            $nss = $this->value($employee, 'social_security_number') ?: $this->value($employee, 'ssn');
            if ($nss === '') {
                $errors[] = "Empleado {$label}: falta NSS/social_security_number.";
            }

            $fiscalName = $this->value($employee, 'fiscal_name') ?: $this->value($employee, 'name');
            if ($fiscalName === '') {
                $errors[] = "Empleado {$label}: falta nombre fiscal.";
            }

            $this->requireField($errors, $employee, 'fiscal_postal_code', "Empleado {$label}: falta código postal fiscal.");
            $this->requireField($errors, $employee, 'sat_tax_regime_code', "Empleado {$label}: falta régimen fiscal SAT.");

            $contract = $this->currentContractForEmployee((int) $employee->id, $companyId);

            if (! $contract) {
                $errors[] = "Empleado {$label}: no tiene contrato actual.";
                continue;
            }

            $this->requireField($errors, $contract, 'contract_type', "Empleado {$label}: el contrato no tiene tipo interno.");
            $this->requireField($errors, $contract, 'sat_contract_type_code', "Empleado {$label}: el contrato no tiene TipoContrato SAT.");
            $this->requireField($errors, $contract, 'sat_workday_type_code', "Empleado {$label}: el contrato no tiene TipoJornada SAT.");
            $this->requireField($errors, $contract, 'sat_regime_type_code', "Empleado {$label}: el contrato no tiene TipoRegimen SAT.");
            $this->requireField($errors, $contract, 'payroll_periodicity_id', "Empleado {$label}: el contrato no tiene periodicidad de pago.");
            $this->requireField($errors, $contract, 'payroll_employer_registration_id', "Empleado {$label}: el contrato no tiene registro patronal.");

            if ((float) ($contract->base_salary ?? 0) <= 0) {
                $errors[] = "Empleado {$label}: el contrato no tiene sueldo base válido.";
            }

            if ((float) ($contract->daily_salary ?? 0) <= 0) {
                $errors[] = "Empleado {$label}: el contrato no tiene salario diario para CFDI.";
            }

            if ((float) ($contract->integrated_daily_salary ?? 0) <= 0) {
                $errors[] = "Empleado {$label}: el contrato no tiene salario diario integrado para CFDI.";
            }
        }

        if ($employees->count() === 0) {
            $warnings[] = "No hay empleados activos en la empresa {$companyId}.";
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => [
                'active_employees_count' => $employees->count(),
            ],
        ];
    }

    private function validatePayrollConcepts(int $companyId): array
    {
        $errors = [];
        $warnings = [];

        if (! Schema::hasTable('payroll_concepts')) {
            return [
                'errors' => ['No existe la tabla payroll_concepts.'],
                'warnings' => [],
                'summary' => [],
            ];
        }

        $concepts = DB::table('payroll_concepts')
            ->where('company_id', $companyId)
            ->where(function ($query): void {
                if (Schema::hasColumn('payroll_concepts', 'is_active')) {
                    $query->where('is_active', true);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($concepts as $concept) {
            $label = "{$concept->code} - {$concept->name}";

            $this->requireField($errors, $concept, 'sat_key', "Concepto {$label}: falta clave SAT de nómina.");

            if ($this->value($concept, 'type') === '') {
                $errors[] = "Concepto {$label}: falta tipo perception/deduction.";
            }

            if ($this->value($concept, 'category') === '') {
                $warnings[] = "Concepto {$label}: falta categoría interna.";
            }

            if (property_exists($concept, 'is_taxable') && $concept->is_taxable === null) {
                $warnings[] = "Concepto {$label}: falta marcar si es gravado/exento para CFDI.";
            }
        }

        if ($concepts->count() === 0) {
            $errors[] = "No hay conceptos de nómina activos para la empresa {$companyId}.";
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => [
                'active_payroll_concepts_count' => $concepts->count(),
            ],
        ];
    }

    private function validatePayrollRun(int $companyId, int $payrollRunId): array
    {
        $errors = [];
        $warnings = [];

        if (! Schema::hasTable('payroll_runs')) {
            return [
                'errors' => ['No existe la tabla payroll_runs.'],
                'warnings' => [],
                'summary' => [],
            ];
        }

        $run = DB::table('payroll_runs')
            ->where('company_id', $companyId)
            ->where('id', $payrollRunId)
            ->first();

        if (! $run) {
            return [
                'errors' => ["No existe la corrida de nómina {$payrollRunId} para empresa {$companyId}."],
                'warnings' => [],
                'summary' => [],
            ];
        }

        if (! (bool) ($run->is_locked ?? false)) {
            $errors[] = "La nómina {$payrollRunId} todavía no está bloqueada/cerrada.";
        }

        if (empty($run->closed_at)) {
            $errors[] = "La nómina {$payrollRunId} no tiene fecha de cierre.";
        }

        $linesCount = Schema::hasTable('payroll_run_lines')
            ? DB::table('payroll_run_lines')
                ->where('company_id', $companyId)
                ->where('payroll_run_id', $payrollRunId)
                ->count()
            : 0;

        if ($linesCount === 0) {
            $errors[] = "La nómina {$payrollRunId} no tiene líneas para timbrar.";
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => [
                'payroll_run_lines_count' => $linesCount,
                'payroll_run_status' => $run->status ?? null,
                'payroll_run_is_locked' => (bool) ($run->is_locked ?? false),
            ],
        ];
    }

    private function currentContractForEmployee(int $employeeId, int $companyId): ?object
    {
        if (! Schema::hasTable('employee_contracts')) {
            return null;
        }

        $query = DB::table('employee_contracts')
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId);

        if (Schema::hasColumn('employee_contracts', 'is_current')) {
            $current = (clone $query)
                ->where('is_current', true)
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->first();

            if ($current) {
                return $current;
            }
        }

        if (Schema::hasColumn('employee_contracts', 'status')) {
            $active = (clone $query)
                ->whereIn('status', ['active', 'vigente', 'current'])
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->first();

            if ($active) {
                return $active;
            }
        }

        return $query
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    private function requireField(array &$errors, object $row, string $field, string $message): void
    {
        if ($this->value($row, $field) === '') {
            $errors[] = $message;
        }
    }

    private function warnField(array &$warnings, object $row, string $field, string $message): void
    {
        if ($this->value($row, $field) === '') {
            $warnings[] = $message;
        }
    }

    private function value(object $row, string $field): string
    {
        if (! property_exists($row, $field)) {
            return '';
        }

        $value = $row->{$field};

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }
}
