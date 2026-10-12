<?php

namespace App\Support;

use App\Models\PayrollRun;
use App\Models\PayrollRunVersion;
use Illuminate\Support\Facades\DB;

class PayrollRunVersionService
{
    public static function capture(
        PayrollRun $run,
        ?int $userId = null,
        string $reason = 'calculation'
    ): PayrollRunVersion {
        $run->refresh();

        $run->load([
            'lines.employee',
            'lines.contract',
            'lines.concepts',
        ]);

        $lastVersion = PayrollRunVersion::query()
            ->where('payroll_run_id', $run->id)
            ->orderByDesc('version_number')
            ->lockForUpdate()
            ->value('version_number');

        $versionNumber = ((int) $lastVersion) + 1;

        $version = PayrollRunVersion::create([
            'company_id' => $run->company_id,
            'payroll_run_id' => $run->id,
            'version_number' => $versionNumber,
            'payroll_group_id' => $run->payroll_group_id,
            'calculation_mode_snapshot' =>
                $run->calculation_mode_snapshot,
            'run_type' => $run->run_type,
            'snapshot_reason' => $reason,

            'name' => $run->name,
            'period_type' => $run->period_type,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'payment_date' => $run->payment_date,

            'status' => $run->status,
            'currency' => $run->currency,

            'employees_count' => $run->employees_count,
            'base_total' => $run->base_total,
            'overtime_total' => $run->overtime_total,
            'perceptions_total' => $run->perceptions_total,
            'gross_total' => $run->gross_total,
            'deductions_total' => $run->deductions_total,
            'net_total' => $run->net_total,

            'summary' => $run->summary,

            /*
             * BEXIA_V5836_VERSION_ISSUER_SNAPSHOT
             */
            'issuer_snapshot' =>
                static::issuerFiscalSnapshot(
                    $run
                ),

            'calculated_by_user_id' => $run->calculated_by_user_id,
            'calculated_at' => $run->calculated_at,

            'snapshot_by_user_id' => $userId ?: auth()->id(),
            'snapshot_at' => now(),
        ]);

        foreach ($run->lines as $line) {
            $version->lines()->create([
                'company_id' => $line->company_id,
                'original_payroll_run_line_id' => $line->id,

                'employee_id' => $line->employee_id,
                'employee_contract_id' => $line->employee_contract_id,

                'employee_name_snapshot' =>
                    $line->employee?->name
                    ?: ('Empleado #' . $line->employee_id),

                'contract_number_snapshot' =>
                    $line->contract?->contract_number,

                /*
                 * BEXIA_V5836_VERSION_LINE_FISCAL_SNAPSHOTS
                 */
                'employee_snapshot' =>
                    static::employeeFiscalSnapshot(
                        $line
                    ),

                'contract_snapshot' =>
                    static::contractFiscalSnapshot(
                        $run,
                        $line
                    ),

                'status' => $line->status,

                'base_salary' => $line->base_salary,
                'salary_type' => $line->salary_type,
                'daily_salary' => $line->daily_salary,
                'hourly_rate' => $line->hourly_rate,

                'period_days' => $line->period_days,
                'payable_days' => $line->payable_days,

                'attendance_records' => $line->attendance_records,
                'worked_minutes' => $line->worked_minutes,
                'worked_hours' => $line->worked_hours,

                'late_minutes' => $line->late_minutes,
                'early_leave_minutes' => $line->early_leave_minutes,
                'overtime_minutes' => $line->overtime_minutes,
                'overtime_hours' => $line->overtime_hours,

                'absence_days' => $line->absence_days,
                'rest_day_worked_days' => $line->rest_day_worked_days,

                'approved_incidents_count' =>
                    $line->approved_incidents_count,

                'approved_incident_deduction_days' =>
                    $line->approved_incident_deduction_days,

                'approved_incident_deduction_minutes' =>
                    $line->approved_incident_deduction_minutes,

                'base_amount' => $line->base_amount,
                'overtime_amount' => $line->overtime_amount,
                'incident_perceptions' => $line->incident_perceptions,
                'incident_deductions' => $line->incident_deductions,

                'gross_amount' => $line->gross_amount,
                'deductions_amount' => $line->deductions_amount,
                'net_amount' => $line->net_amount,

                'details' => $line->details,

                'concepts_snapshot' => $line->concepts
                    ->map(fn ($concept) => [
                        'id' => $concept->id,
                        'type' => $concept->type,
                        'code' => $concept->code,
                        'name' => $concept->name,
                        'category' => $concept->category,
                        'source' => $concept->source,
                        'unit' => $concept->unit,
                        'sat_key' => $concept->sat_key,
                        'quantity' => $concept->quantity,
                        'rate' => $concept->rate,
                        'amount' => $concept->amount,
                        'taxable_amount' => $concept->taxable_amount ?? null,
                        'exempt_amount' => $concept->exempt_amount ?? null,
                        'reduces_taxable_base' =>
                            $concept->reduces_taxable_base,
                        'metadata' => $concept->metadata,
                        'details' => $concept->details ?? null,
                    ])
                    ->values()
                    ->all(),

                'notes' => $line->notes,
            ]);
        }

        return $version->fresh(['lines']);
    }

    /*
     * BEXIA_V5836_VERSION_FISCAL_SNAPSHOT_HELPERS
     *
     * Estos snapshots se capturan al mismo tiempo que la versión
     * económica de la nómina.
     *
     * A partir de aquí el CFDI debe poder usar únicamente estos
     * valores congelados, sin consultar datos maestros vivos.
     */
    protected static function issuerFiscalSnapshot(
        PayrollRun $run
    ): array {
        $company = DB::table('companies')
            ->where('id', $run->company_id)
            ->first();

        if (! $company) {
            return [];
        }

        return [
            'company_id' =>
                (int) $run->company_id,

            'name' =>
                $company->name
                ?? null,

            'business_name' =>
                $company->business_name
                ?? null,

            'tax_id' =>
                $company->tax_id
                ?? null,

            'tax_regime' =>
                $company->tax_regime
                ?? null,

            'fiscal_postal_code' =>
                $company->fiscal_postal_code
                ?? null,
        ];
    }

    protected static function employeeFiscalSnapshot(
        object $line
    ): array {
        $employee = $line->employee;

        if (! $employee) {
            return [];
        }

        return [
            'employee_id' =>
                (int) $employee->id,

            'name' =>
                $employee->name
                ?? null,

            'fiscal_name' =>
                $employee->fiscal_name
                ?? $employee->name
                ?? null,

            'rfc' =>
                $employee->rfc
                ?? null,

            'curp' =>
                $employee->curp
                ?? null,

            'social_security_number' =>
                $employee->social_security_number
                ?? $employee->ssn
                ?? null,

            'fiscal_postal_code' =>
                $employee->fiscal_postal_code
                ?? null,

            'sat_tax_regime_code' =>
                $employee->sat_tax_regime_code
                ?? null,

            'hire_date' =>
                ! empty($employee->hire_date)
                    ? (string) $employee->hire_date
                    : null,

            'termination_date' =>
                ! empty($employee->termination_date)
                    ? (string) $employee->termination_date
                    : null,
        ];
    }

    protected static function contractFiscalSnapshot(
        PayrollRun $run,
        object $line
    ): array {
        $contract = $line->contract;

        if (! $contract) {
            return [];
        }

        $snapshot = $contract->getAttributes();

        $departmentName = null;
        $positionName = null;
        $periodicitySatCode = null;
        $periodicityName = null;
        $employerRegistrationNumber = null;
        $employerRegistrationName = null;

        if (
            ! empty($contract->hr_department_id)
            && DB::getSchemaBuilder()
                ->hasTable('hr_departments')
        ) {
            $department = DB::table('hr_departments')
                ->where(
                    'company_id',
                    $run->company_id
                )
                ->where(
                    'id',
                    $contract->hr_department_id
                )
                ->first();

            $departmentName =
                $department->name
                ?? null;
        }

        if (
            ! empty($contract->hr_job_position_id)
            && DB::getSchemaBuilder()
                ->hasTable('hr_job_positions')
        ) {
            $position = DB::table('hr_job_positions')
                ->where(
                    'company_id',
                    $run->company_id
                )
                ->where(
                    'id',
                    $contract->hr_job_position_id
                )
                ->first();

            $positionName =
                $position->name
                ?? null;
        }

        if (
            ! empty($contract->payroll_periodicity_id)
            && DB::getSchemaBuilder()
                ->hasTable('payroll_periodicities')
        ) {
            $periodicity = DB::table(
                'payroll_periodicities'
            )
                ->where(
                    'company_id',
                    $run->company_id
                )
                ->where(
                    'id',
                    $contract->payroll_periodicity_id
                )
                ->first();

            $periodicitySatCode =
                $periodicity->sat_code
                ?? null;

            $periodicityName =
                $periodicity->name
                ?? null;
        }

        if (
            ! empty(
                $contract
                    ->payroll_employer_registration_id
            )
            && DB::getSchemaBuilder()
                ->hasTable(
                    'payroll_employer_registrations'
                )
        ) {
            $registration = DB::table(
                'payroll_employer_registrations'
            )
                ->where(
                    'company_id',
                    $run->company_id
                )
                ->where(
                    'id',
                    $contract
                        ->payroll_employer_registration_id
                )
                ->first();

            $employerRegistrationNumber =
                $registration->registration_number
                ?? null;

            $employerRegistrationName =
                $registration->name
                ?? null;
        }

        $snapshot['department'] =
            filled($departmentName)
                ? trim((string) $departmentName)
                : null;

        $snapshot['position'] =
            filled($positionName)
                ? trim((string) $positionName)
                : null;

        $snapshot[
            'payroll_periodicity_sat_code'
        ] =
            filled($periodicitySatCode)
                ? trim(
                    (string) $periodicitySatCode
                )
                : null;

        $snapshot[
            'payroll_periodicity_name'
        ] =
            filled($periodicityName)
                ? trim(
                    (string) $periodicityName
                )
                : null;

        $snapshot[
            'employer_registration_number'
        ] =
            filled($employerRegistrationNumber)
                ? trim(
                    (string)
                    $employerRegistrationNumber
                )
                : null;

        $snapshot[
            'employer_registration_name'
        ] =
            filled($employerRegistrationName)
                ? trim(
                    (string)
                    $employerRegistrationName
                )
                : null;

        return $snapshot;
    }


    public static function captureBaseline(
        PayrollRun $run,
        ?int $userId = null
    ): PayrollRunVersion {
        return DB::transaction(
            fn () => static::capture(
                $run,
                $userId,
                'baseline'
            )
        );
    }
}
