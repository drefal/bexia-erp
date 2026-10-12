<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeContract;
use App\Models\EmployeeIncident;
use App\Models\EmployeePayrollDeductionApplication;
use App\Models\EmployeePayrollDeduction;
use App\Models\EmployeePayrollPerceptionApplication;
use App\Models\EmployeePayrollPerception;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\PayrollPolicy;
use App\Models\PayrollRunLineConcept;
use App\Models\PayrollConcept;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\PayrollRunCloseService;
use App\Support\PayrollTax\PayrollFiscalRunCalculationService;

class PayrollRunCalculator
{
    public static function calculate(PayrollRun $run, ?int $userId = null): PayrollRun
    {
        PayrollRunCloseService::ensureCanRecalculate($run);

        if (in_array((string) $run->status, ['pending_approval', 'approved', 'closed', 'cancelled'], true)) {
            throw new \RuntimeException('Solo se puede recalcular una pre-nómina en borrador o calculada.');
        }

        /*
         * BEXIA_V5836_PAYROLL_PENDING_ATTENDANCE_GUARD
         *
         * No permitir cálculo/recalculo mientras existan incidencias
         * automáticas de asistencia que afectan nómina y siguen sin
         * resolver.
         *
         * approved = justificada, no descuenta.
         * rejected = no justificada, sí participa en el cálculo.
         * draft/pending = debe resolverse antes de calcular.
         *
         * Esto evita que un recálculo sustituya silenciosamente la
         * asistencia física por ceros mientras la incidencia está
         * pendiente.
         */
        PayrollAttendanceIncidentGate::assertResolved(
            $run,
            'calcular la prenómina'
        );

        /*
         * Antes de borrar o recalcular líneas validamos que todos los
         * empleados incluidos tengan datos mínimos suficientes.
         */
        PayrollRunReadinessService::assertReady($run);

        return DB::transaction(function () use ($run, $userId): PayrollRun {
            static::reverseEmployeeDeductionApplications($run);
            static::reverseEmployeePerceptionApplications($run);

            if (class_exists(PayrollRunLineConcept::class) && Schema::hasTable('payroll_run_line_concepts')) {
                PayrollRunLineConcept::query()
                    ->where('payroll_run_id', $run->id)
                    ->delete();
            }

            $run->lines()->delete();

            $policy = static::activePolicy((int) $run->company_id);

            $employees = static::employeesForRun($run);

            /*
             * Los resultados fiscales se calculan junto con cada línea,
             * pero sólo se persisten después de capturar la versión final.
             */
            $fiscalRows = [];

            $summary = [
                'employees' => 0,
                'attendance_records' => 0,
                'worked_minutes' => 0,
                'late_minutes' => 0,
                'early_leave_minutes' => 0,
                'overtime_minutes' => 0,
                'absence_days' => 0,
                'rest_day_worked_days' => 0,
                'approved_incidents_count' => 0,
            ];

            foreach ($employees as $employee) {
                $line = static::calculateEmployee($run, $employee, $policy);
                $line->save();
                static::createLineConcepts($line);

                /*
                 * La capa operativa queda intacta.
                 *
                 * Sólo fiscal_preview / fiscal_cfdi agregan ISR
                 * y modifican deductions/net antes de totalizar.
                 */
                if ($run->calculatesFiscalTaxes()) {
                    $fiscalRows[] =
                        PayrollFiscalRunCalculationService
                            ::applyToLine(
                                $run,
                                $employee,
                                $line
                            );

                    $line->refresh();
                }

                $summary['employees']++;
                $summary['attendance_records'] += (int) $line->attendance_records;
                $summary['worked_minutes'] += (int) $line->worked_minutes;
                $summary['late_minutes'] += (int) $line->late_minutes;
                $summary['early_leave_minutes'] += (int) $line->early_leave_minutes;
                $summary['overtime_minutes'] += (int) $line->overtime_minutes;
                $summary['absence_days'] += (float) $line->absence_days;
                $summary['rest_day_worked_days'] += (float) $line->rest_day_worked_days;
                $summary['approved_incidents_count'] += (int) $line->approved_incidents_count;
            }

            $perceptionsTotal = (float) $run->lines()->sum('incident_perceptions');

            if (class_exists(PayrollRunLineConcept::class) && Schema::hasTable('payroll_run_line_concepts')) {
                $perceptionsTotal = (float) PayrollRunLineConcept::query()
                    ->where('payroll_run_id', $run->id)
                    ->where('type', 'perception')
                    ->whereNotIn('code', ['SUELDO_BASE', 'HORAS_EXTRA'])
                    ->sum('amount');
            }

            $totals = [
                'employees_count' => $employees->count(),
                'base_total' => (float) $run->lines()->sum('base_amount'),
                'overtime_total' => (float) $run->lines()->sum('overtime_amount'),
                'perceptions_total' => $perceptionsTotal,
                'deductions_total' => (float) $run->lines()->sum('deductions_amount'),
                'gross_total' => (float) $run->lines()->sum('gross_amount'),
                'net_total' => (float) $run->lines()->sum('net_amount'),
            ];

            $run->forceFill([
                ...$totals,
                'summary' => $summary,
                'status' => 'calculated',
                'calculated_by_user_id' => $userId ?: auth()->id(),
                'updated_by_user_id' => $userId ?: auth()->id(),
                'calculated_at' => now(),
            ])->save();

            /*
             * Cada cálculo exitoso queda congelado como una versión
             * auditable antes de confirmar la transacción.
             */
            $version = PayrollRunVersionService::capture(
                $run,
                $userId ?: auth()->id(),
                'calculation'
            );

            /*
             * El resultado fiscal se liga exactamente a la versión
             * que acaba de congelar estos totales y conceptos.
             */
            if ($run->calculatesFiscalTaxes()) {
                PayrollFiscalRunCalculationService
                    ::persistForVersion(
                        $run->fresh(),
                        $version,
                        $fiscalRows
                    );
            }

            return $run->fresh(['lines.employee']);
        });
    }

    protected static function employeesForRun(PayrollRun $run)
    {
        return Employee::query()
            ->where('company_id', $run->company_id)
            ->where('payroll_group_id', $run->payroll_group_id)
            ->where(function ($query) use ($run): void {
                $query->where('active', true)
                    ->orWhere(function ($query) use ($run): void {
                        $query->whereNotNull('termination_date')
                            ->whereDate('termination_date', '>=', $run->period_start);
                    });
            })
            ->where(function ($query) use ($run): void {
                $query->whereNull('hire_date')
                    ->orWhereDate('hire_date', '<=', $run->period_end);
            })
            ->orderBy('name')
            ->get();
    }

    public static function calculateEmployee(PayrollRun $run, Employee $employee, array $policy = []): PayrollRunLine
    {
        $policy = $policy ?: static::activePolicy((int) $run->company_id);

        $periodStart = CarbonImmutable::parse($run->period_start)->startOfDay();
        $periodEnd = CarbonImmutable::parse($run->period_end)->startOfDay();
        $periodDays = $periodStart->diffInDays($periodEnd) + 1;

        $contract = static::currentContract(
            $employee,
            $periodStart,
            $periodEnd
        );

        $salary = static::salaryData(
            $employee,
            $contract
        );

        /*
         * BEXIA_V5836_EMPLOYMENT_PAYABLE_DAYS
         *
         * Días base pagables del periodo:
         * únicamente los días dentro de la relación laboral
         * efectiva del empleado/contrato.
         *
         * Aún NO se descuentan aquí faltas, permisos sin goce
         * ni incapacidades. Esos conceptos se resolverán
         * explícitamente en una fase posterior.
         */
        $employmentStart = $periodStart;
        $employmentEnd = $periodEnd;

        if (! empty($employee->hire_date)) {
            $hireDate = CarbonImmutable::parse(
                $employee->hire_date
            )->startOfDay();

            if ($hireDate->greaterThan($employmentStart)) {
                $employmentStart = $hireDate;
            }
        }

        if (! empty($contract?->start_date)) {
            $contractStart = CarbonImmutable::parse(
                $contract->start_date
            )->startOfDay();

            if ($contractStart->greaterThan($employmentStart)) {
                $employmentStart = $contractStart;
            }
        }

        if (! empty($employee->termination_date)) {
            $terminationDate = CarbonImmutable::parse(
                $employee->termination_date
            )->startOfDay();

            if ($terminationDate->lessThan($employmentEnd)) {
                $employmentEnd = $terminationDate;
            }
        }

        if (! empty($contract?->end_date)) {
            $contractEnd = CarbonImmutable::parse(
                $contract->end_date
            )->startOfDay();

            if ($contractEnd->lessThan($employmentEnd)) {
                $employmentEnd = $contractEnd;
            }
        }

        $employmentPayableDays =
            $employmentEnd->lessThan($employmentStart)
                ? 0.0
                : (
                    $employmentStart
                        ->diffInDays($employmentEnd)
                    + 1
                );

        $attendances = EmployeeAttendance::query()
            ->where('company_id', $run->company_id)
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', '>=', $periodStart->toDateString())
            ->whereDate('attendance_date', '<=', $periodEnd->toDateString())
            ->get();

        /*
         * Asistencia física:
         * conserva exactamente lo ocurrido en reloj/captura.
         */
        $attendanceSummary = [
            'attendance_records' => $attendances->count(),
            'worked_minutes' => (int) $attendances->sum(fn ($row) => (int) $row->worked_minutes),
            'worked_hours' => round(((int) $attendances->sum(fn ($row) => (int) $row->worked_minutes)) / 60, 2),
            'late_minutes' => (int) $attendances->sum(fn ($row) => (int) $row->late_minutes),
            'late_count' => $attendances
                ->filter(fn ($row): bool => (int) ($row->late_minutes ?? 0) > 0)
                ->count(),
            'early_leave_minutes' => (int) $attendances->sum(fn ($row) => (int) $row->early_leave_minutes),
            'overtime_minutes' => (int) $attendances->sum(fn ($row) => (int) $row->overtime_minutes),
            'overtime_hours' => round(((int) $attendances->sum(fn ($row) => (int) $row->overtime_minutes)) / 60, 2),
            'absence_days' => (float) $attendances->where('status', 'absence')->count(),
            'rest_day_worked_days' => (float) $attendances->where('status', 'rest_day_worked')->count(),
        ];

        /*
         * Asistencia efectiva para nómina:
         *
         * approved = incidencia justificada -> no cuenta;
         * rejected = no justificada -> sí cuenta;
         * pending/draft = no cuenta todavía.
         *
         * El resumen físico permanece intacto para auditoría.
         */
        $attendanceResolution =
            static::attendanceIncidentResolution(
                $run,
                $employee,
                $attendances
            );

        $payrollAttendanceSummary = $attendanceSummary;

        if ($attendanceResolution['managed']) {
            $payrollAttendanceSummary['late_minutes'] =
                $attendanceResolution['late_minutes'];

            $payrollAttendanceSummary['late_count'] =
                $attendanceResolution['late_count'];

            $payrollAttendanceSummary['early_leave_minutes'] =
                $attendanceResolution['early_leave_minutes'];

            $payrollAttendanceSummary['absence_days'] =
                $attendanceResolution['absence_days'];
        }

        $attendancePolicy = static::applyAttendancePolicy(
            $payrollAttendanceSummary,
            $salary,
            $policy
        );

        $incidents = static::approvedPayrollIncidents(
            $run,
            $employee
        );

        $incidentAmounts = static::incidentAmounts(
            $incidents,
            $salary
        );

        /*
         * BEXIA_V5836_INCAPACITY_PERIOD_NORMALIZATION
         *
         * Valida las incapacidades aprobadas y calcula únicamente
         * los días que intersectan el periodo actual de nómina.
         *
         * Aún NO modifica payable_days ni importes.
         */
        $normalizedIncapacities =
            static::normalizedIncapacityIncidents(
                $incidents
            );

        $periodIncapacities =
            static::incapacityPeriodsForRun(
                $normalizedIncapacities,
                $periodStart,
                $periodEnd
            );

        $incapacityDaysInPeriod = array_sum(
            array_map(
                static fn (array $row): int =>
                    (int) ($row['days_in_period'] ?? 0),
                $periodIncapacities
            )
        );

        /*
         * BEXIA_V5836_INCAPACITY_ECONOMIC_ALLOCATION_TRACE
         *
         * Sólo calcula y congela la asignación que corresponde
         * al periodo. Todavía NO la suma a gross/deductions/net.
         */
        $incapacityEconomicAllocations =
            static::incapacityEconomicAllocations(
                $periodIncapacities
            );

        /*
         * BEXIA_V5836_MANUAL_UNPAID_DAYS
         *
         * Sólo incidencias MANUALES aprobadas, no ligadas a
         * asistencia, pueden reducir aquí los días pagados.
         *
         * Casos aceptados:
         * - FALTA
         * - PERMISO_SIN_GOCE
         *
         * Deben venir expresadas explícitamente en días.
         *
         * NO incluye:
         * - retardos convertidos por política a falta equivalente;
         * - vacaciones;
         * - permiso con goce;
         * - incapacidad;
         * - descuentos genéricos.
         */
        $manualUnpaidDays =
            static::manualUnpaidIncidentDays(
                $incidents
            );

        /*
         * BEXIA_V5836_ATTENDANCE_UNPAID_DAYS
         *
         * attendanceResolution['absence_days'] sólo contiene
         * incidencias FALTA de asistencia resueltas como rejected
         * (no justificadas).
         *
         * NO contiene:
         * - retardos;
         * - salida temprana;
         * - late_count_absence;
         * - late_accumulated_absence.
         *
         * Por tanto sí representa una ausencia real no pagada
         * distinta de las faltas equivalentes por política.
         */
        $attendanceUnpaidDays = max(
            0.0,
            (float) (
                $attendanceResolution['absence_days']
                ?? 0
            )
        );

        $totalUnpaidDays = max(
            0.0,
            (float) $manualUnpaidDays['days']
                + $attendanceUnpaidDays
        );

        $unpaidDaysApplied = min(
            (float) $employmentPayableDays,
            $totalUnpaidDays
        );

        $payableDays = max(
            0.0,
            (float) $employmentPayableDays
                - $unpaidDaysApplied
        );

        $policyDeductions = static::attendancePolicyDeductions($attendancePolicy, $salary, $policy);
        $employeeDeductions = static::employeePayrollDeductions($run, $employee);
        $employeePerceptions = static::employeePayrollPerceptions($run, $employee);

        /*
         * BEXIA_V5836_BASE_AMOUNT_EMPLOYMENT_DAYS
         *
         * El sueldo base debe corresponder a los días dentro de
         * la relación laboral, no siempre a la longitud completa
         * de la corrida.
         *
         * Para empleados por hora, baseAmount() continúa usando
         * worked_hours.
         */
        $baseAmount = static::baseAmount(
            $salary,
            $employmentPayableDays,
            $attendanceSummary
        );

        $overtimeMultiplier = (float) ($policy['overtime_multiplier'] ?? 2);
        $overtimeAmount = round($salary['hourly_rate'] * $overtimeMultiplier * $attendanceSummary['overtime_hours'], 2);

        /*
         * BEXIA_V5836_INCAPACITY_ECONOMIC_TOTALS
         *
         * Los importes específicos de incapacidad se incorporan
         * una sola vez aquí.
         *
         * No vienen de incidentAmounts(), porque INACTIVIDAD
         * fiscal no debe duplicarse con la incidencia genérica.
         */
        $grossAmount = round(
            $baseAmount
                + $overtimeAmount
                + $incidentAmounts['perceptions']
                + $employeePerceptions['amount']
                + (
                    $incapacityEconomicAllocations[
                        'perceptions'
                    ] ?? 0
                ),
            2
        );

        $deductionsAmount = round(
            $incidentAmounts['deductions']
                + $policyDeductions['amount']
                + $employeeDeductions['amount']
                + (
                    $incapacityEconomicAllocations[
                        'deductions'
                    ] ?? 0
                ),
            2
        );

        $netAmount = round(
            max(
                0,
                $grossAmount
                    - $deductionsAmount
            ),
            2
        );

        return new PayrollRunLine([
            'company_id' => $run->company_id,
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'employee_contract_id' => $contract?->id,
            'status' => 'calculated',

            'base_salary' => $salary['base_salary'],
            'salary_type' => $salary['salary_type'],
            'daily_salary' => $salary['daily_salary'],
            'hourly_rate' => $salary['hourly_rate'],

            'period_days' => $periodDays,
            'payable_days' => $payableDays,

            ...$attendanceSummary,

            'approved_incidents_count' => $incidents->count(),
            'approved_incident_deduction_days' => $incidentAmounts['deduction_days'],
            'approved_incident_deduction_minutes' => $incidentAmounts['deduction_minutes'],

            'base_amount' => $baseAmount,
            'overtime_amount' => $overtimeAmount,
            /*
             * BEXIA_V5836_INCAPACITY_LINE_TOTALS
             */
            'incident_perceptions' => round(
                $incidentAmounts['perceptions']
                    + (
                        $incapacityEconomicAllocations[
                            'perceptions'
                        ] ?? 0
                    ),
                2
            ),

            'incident_deductions' => round(
                $incidentAmounts['deductions']
                    + (
                        $incapacityEconomicAllocations[
                            'deductions'
                        ] ?? 0
                    ),
                2
            ),
            'gross_amount' => $grossAmount,
            'deductions_amount' => $deductionsAmount,
            'net_amount' => $netAmount,

            'details' => [
                'contract_id' => $contract?->id,
                'contract_number' => $contract?->contract_number,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),

                'employment_period_start' =>
                    $employmentStart->toDateString(),

                'employment_period_end' =>
                    $employmentEnd->toDateString(),

                'employment_payable_days' =>
                    $employmentPayableDays,

                'incapacities_in_period' =>
                    $periodIncapacities,

                'incapacity_days_in_period' =>
                    $incapacityDaysInPeriod,

                'incapacity_economic_allocations' =>
                    $incapacityEconomicAllocations,

                'manual_unpaid_incident_days' =>
                    $manualUnpaidDays['days'],

                'manual_unpaid_incident_ids' =>
                    $manualUnpaidDays['incident_ids'],

                'attendance_unpaid_days' =>
                    $attendanceUnpaidDays,

                'total_unpaid_days' =>
                    $totalUnpaidDays,

                'unpaid_days_applied' =>
                    $unpaidDaysApplied,

                'payable_days_after_unpaid_incidents' =>
                    $payableDays,

                'attendance_ids' => $attendances->pluck('id')->values()->all(),
                'approved_incident_ids' => $incidents->pluck('id')->values()->all(),
                'attendance_incident_resolution' => $attendanceResolution,
                'physical_attendance' => $attendanceSummary,
                'payroll_policy' => [
                    'id' => $policy['id'] ?? null,
                    'name' => $policy['name'] ?? 'Defaults internos',
                    'overtime_multiplier' => $overtimeMultiplier ?? (float) ($policy['overtime_multiplier'] ?? 2),
                    'late_tolerance_minutes' => (int) ($policy['late_tolerance_minutes'] ?? 0),
                    'late_discount_mode' => $policy['late_discount_mode'] ?? 'none',
                    'late_minutes_to_absence' => (int) ($policy['late_minutes_to_absence'] ?? 0),
                    'early_leave_discount_mode' => $policy['early_leave_discount_mode'] ?? 'none',
                    'absence_discount_mode' => $policy['absence_discount_mode'] ?? 'incident_only',
                    'policy_deductions' => $policyDeductions ?? ['amount' => 0, 'items' => []],
                    'raw_attendance' => $attendancePolicy['raw'] ?? [],
                    'effective_attendance' => $attendancePolicy['effective'] ?? [],
                ],
                'employee_payroll_deductions' => $employeeDeductions ?? ['amount' => 0, 'items' => []],
                'employee_payroll_perceptions' => $employeePerceptions ?? ['amount' => 0, 'items' => []],
                'formula' => [
                    'base_amount' =>
                        'salary basis by salary_type and employment payable days',
                    'overtime_amount' => 'hourly_rate * ' . ($overtimeMultiplier ?? (float) ($policy['overtime_multiplier'] ?? 2)) . ' * overtime_hours',
                    'net_amount' => 'gross_amount - deductions_amount',
                ],
            ],
            'notes' => $contract ? null : 'Empleado sin contrato vigente. Se usó salario cero o hourly_cost si existe.',
        ]);
    }

    protected static function currentContract(Employee $employee, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): ?EmployeeContract
    {
        return EmployeeContract::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where(function ($query) use ($periodEnd): void {
                $query->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', $periodEnd->toDateString());
            })
            ->where(function ($query) use ($periodStart): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $periodStart->toDateString());
            })
            ->orderByDesc('is_current')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    protected static function salaryData(Employee $employee, ?EmployeeContract $contract): array
    {
        $baseSalary = (float) ($contract?->base_salary ?? 0);
        $salaryType = strtolower((string) ($contract?->salary_type ?: 'monthly'));
        $hoursPerWeek = (float) ($contract?->hours_per_week ?? 40);

        if ($baseSalary <= 0 && (float) ($employee->hourly_cost ?? 0) > 0) {
            $salaryType = 'hourly';
            $baseSalary = (float) $employee->hourly_cost;
        }

        $dailySalary = match ($salaryType) {
            'daily', 'diario' => $baseSalary,
            'weekly', 'semanal' => round($baseSalary / 7, 2),
            'biweekly', 'quincenal', 'fortnightly', 'catorcenal' => round($baseSalary / 15, 2),
            'monthly', 'mensual' => round($baseSalary / 30, 2),
            'hourly', 'hora', 'por_hora' => round($baseSalary * max(1, $hoursPerWeek / 5), 2),
            default => round($baseSalary / 30, 2),
        };

        $hourlyRate = match ($salaryType) {
            'hourly', 'hora', 'por_hora' => $baseSalary,
            default => round($dailySalary / 8, 4),
        };

        return [
            'base_salary' => round($baseSalary, 2),
            'salary_type' => $salaryType ?: null,
            'daily_salary' => round($dailySalary, 2),
            'hourly_rate' => round($hourlyRate, 4),
        ];
    }

    protected static function baseAmount(array $salary, float $periodDays, array $attendanceSummary): float
    {
        $salaryType = strtolower((string) ($salary['salary_type'] ?? ''));

        if (in_array($salaryType, ['hourly', 'hora', 'por_hora'], true)) {
            return round(
                $salary['hourly_rate'] * $attendanceSummary['worked_hours'],
                2
            );
        }

        /*
         * Para una semana completa usamos directamente el sueldo semanal
         * contractual. Evita perder centavos al convertir primero a salario
         * diario redondeado: 2500 / 7 = 357.14 -> 2499.98.
         *
         * Periodos parciales conservan el cálculo proporcional diario.
         */
        if (
            in_array($salaryType, ['weekly', 'semanal'], true)
            && abs($periodDays - 7.0) < 0.001
        ) {
            return round((float) $salary['base_salary'], 2);
        }

        return round($salary['daily_salary'] * $periodDays, 2);
    }


    protected static function activePolicy(int $companyId): array
    {
        if (! class_exists(PayrollPolicy::class)) {
            return static::defaultPolicy();
        }

        try {
            $policy = PayrollPolicy::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->where('status', 'active')
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable) {
            $policy = null;
        }

        if (! $policy) {
            return static::defaultPolicy();
        }

        return [
            'id' => $policy->id,
            'name' => $policy->name,
            'overtime_multiplier' => (float) $policy->overtime_multiplier,
            'rest_day_overtime_multiplier' => (float) $policy->rest_day_overtime_multiplier,
            'holiday_overtime_multiplier' => (float) $policy->holiday_overtime_multiplier,
            'late_tolerance_minutes' => (int) $policy->late_tolerance_minutes,
            'late_discount_mode' => (string) $policy->late_discount_mode,
            'late_minutes_to_absence' => (int) $policy->late_minutes_to_absence,
            'late_count_to_absence' => max(
                0,
                (int) (
                    is_array($policy->settings)
                        ? ($policy->settings['late_count_to_absence'] ?? 0)
                        : 0
                )
            ),
            'early_leave_discount_mode' => (string) $policy->early_leave_discount_mode,
            'absence_discount_mode' => (string) $policy->absence_discount_mode,
            'rest_day_worked_mode' => (string) $policy->rest_day_worked_mode,
            'holiday_worked_mode' => (string) $policy->holiday_worked_mode,
        ];
    }

    protected static function defaultPolicy(): array
    {
        return [
            'id' => null,
            'name' => 'Defaults internos',
            'overtime_multiplier' => 2.0,
            'rest_day_overtime_multiplier' => 2.0,
            'holiday_overtime_multiplier' => 2.0,
            'late_tolerance_minutes' => 0,
            'late_discount_mode' => 'none',
            'late_minutes_to_absence' => 0,
            'late_count_to_absence' => 0,
            'early_leave_discount_mode' => 'none',
            'absence_discount_mode' => 'incident_only',
            'rest_day_worked_mode' => 'informational',
            'holiday_worked_mode' => 'informational',
        ];
    }

    protected static function applyAttendancePolicy(array $attendanceSummary, array $salary, array $policy): array
    {
        $raw = [
            'late_minutes' => (int) ($attendanceSummary['late_minutes'] ?? 0),
            'late_count' => (int) ($attendanceSummary['late_count'] ?? 0),
            'early_leave_minutes' => (int) ($attendanceSummary['early_leave_minutes'] ?? 0),
            'absence_days' => (float) ($attendanceSummary['absence_days'] ?? 0),
            'overtime_minutes' => (int) ($attendanceSummary['overtime_minutes'] ?? 0),
            'overtime_hours' => (float) ($attendanceSummary['overtime_hours'] ?? 0),
        ];

        $lateTolerance = max(0, (int) ($policy['late_tolerance_minutes'] ?? 0));
        $attendanceSummary['late_minutes'] = max(0, $raw['late_minutes'] - $lateTolerance);

        return [
            'attendance' => $attendanceSummary,
            'raw' => $raw,
            'effective' => [
                'late_minutes' => (int) $attendanceSummary['late_minutes'],
                'late_count' => (int) ($attendanceSummary['late_count'] ?? 0),
                'early_leave_minutes' => (int) ($attendanceSummary['early_leave_minutes'] ?? 0),
                'absence_days' => (float) ($attendanceSummary['absence_days'] ?? 0),
                'overtime_minutes' => (int) ($attendanceSummary['overtime_minutes'] ?? 0),
                'overtime_hours' => (float) ($attendanceSummary['overtime_hours'] ?? 0),
            ],
        ];
    }

    protected static function attendancePolicyDeductions(array $attendancePolicy, array $salary, array $policy): array
    {
        $effective = $attendancePolicy['effective'] ?? [];
        $lateMinutes = (int) ($effective['late_minutes'] ?? 0);
        $earlyLeaveMinutes = (int) ($effective['early_leave_minutes'] ?? 0);
        $absenceDays = (float) ($effective['absence_days'] ?? 0);

        $amount = 0.0;
        $items = [];

        $lateMode = (string) ($policy['late_discount_mode'] ?? 'none');
        $lateMinutesToAbsence = (int) ($policy['late_minutes_to_absence'] ?? 0);

        if ($lateMode === 'minutes' && $lateMinutes > 0) {
            $lateAmount = round(($salary['hourly_rate'] / 60) * $lateMinutes, 2);
            $amount += $lateAmount;
            $items[] = [
                'type' => 'late_minutes',
                'quantity' => $lateMinutes,
                'amount' => $lateAmount,
            ];
        }

        if ($lateMode === 'accumulate_to_absence' && $lateMinutesToAbsence > 0 && $lateMinutes >= $lateMinutesToAbsence) {
            $days = floor($lateMinutes / $lateMinutesToAbsence);
            $lateAbsenceAmount = round($salary['daily_salary'] * $days, 2);
            $amount += $lateAbsenceAmount;
            $items[] = [
                'type' => 'late_accumulated_absence',
                'quantity' => $days,
                'amount' => $lateAbsenceAmount,
            ];
        }

        if ($lateMode === 'count_to_absence') {
            $lateCount = max(
                0,
                (int) ($effective['late_count'] ?? 0)
            );

            $lateCountToAbsence = max(
                0,
                (int) ($policy['late_count_to_absence'] ?? 0)
            );

            if (
                $lateCountToAbsence > 0
                && $lateCount >= $lateCountToAbsence
            ) {
                $days = floor(
                    $lateCount / $lateCountToAbsence
                );

                $lateAbsenceAmount = round(
                    $salary['daily_salary'] * $days,
                    2
                );

                $amount += $lateAbsenceAmount;

                $items[] = [
                    'type' => 'late_count_absence',
                    'quantity' => $days,
                    'late_count' => $lateCount,
                    'late_count_to_absence' =>
                        $lateCountToAbsence,
                    'amount' => $lateAbsenceAmount,
                ];
            }
        }

        if (($policy['early_leave_discount_mode'] ?? 'none') === 'minutes' && $earlyLeaveMinutes > 0) {
            $earlyAmount = round(($salary['hourly_rate'] / 60) * $earlyLeaveMinutes, 2);
            $amount += $earlyAmount;
            $items[] = [
                'type' => 'early_leave_minutes',
                'quantity' => $earlyLeaveMinutes,
                'amount' => $earlyAmount,
            ];
        }

        if (($policy['absence_discount_mode'] ?? 'incident_only') === 'attendance_days' && $absenceDays > 0) {
            $absenceAmount = round($salary['daily_salary'] * $absenceDays, 2);
            $amount += $absenceAmount;
            $items[] = [
                'type' => 'attendance_absence_days',
                'quantity' => $absenceDays,
                'amount' => $absenceAmount,
            ];
        }

        return [
            'amount' => round($amount, 2),
            'items' => $items,
        ];
    }




    protected static function reverseEmployeePerceptionApplications(PayrollRun $run): void
    {
        if (
            ! class_exists(EmployeePayrollPerceptionApplication::class)
            || ! class_exists(EmployeePayrollPerception::class)
            || ! Schema::hasTable('employee_payroll_perception_applications')
            || ! Schema::hasTable('employee_payroll_perceptions')
        ) {
            return;
        }

        $applications = EmployeePayrollPerceptionApplication::query()
            ->where('payroll_run_id', $run->id)
            ->get();

        foreach ($applications as $application) {
            $perception = EmployeePayrollPerception::query()
                ->whereKey($application->employee_payroll_perception_id)
                ->lockForUpdate()
                ->first();

            if (! $perception) {
                continue;
            }

            $perception->remaining_amount = round((float) $perception->remaining_amount + (float) $application->amount, 2);
            $perception->applied_periods = max(0, (int) $perception->applied_periods - 1);

            if ((string) $perception->status === 'paid' && (float) $perception->remaining_amount > 0) {
                $perception->status = 'active';
            }

            $perception->save();
        }

        EmployeePayrollPerceptionApplication::query()
            ->where('payroll_run_id', $run->id)
            ->delete();
    }

    protected static function employeePayrollPerceptions(PayrollRun $run, Employee $employee): array
    {
        if (
            ! class_exists(EmployeePayrollPerception::class)
            || ! Schema::hasTable('employee_payroll_perceptions')
        ) {
            return [
                'amount' => 0.0,
                'items' => [],
            ];
        }

        $periodStart = CarbonImmutable::parse($run->period_start)->toDateString();
        $periodEnd = CarbonImmutable::parse($run->period_end)->toDateString();
        $paymentDate = $run->payment_date
            ? CarbonImmutable::parse($run->payment_date)->toDateString()
            : $periodEnd;

        $perceptions = EmployeePayrollPerception::query()
            ->with('concept')
            ->where('company_id', $run->company_id)
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->where('remaining_amount', '>', 0)
            ->where('period_amount', '>', 0)
            ->where(function ($query) use ($paymentDate) {
                $query->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', $paymentDate);
            })
            ->where(function ($query) use ($periodStart) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $periodStart);
            })
            ->where(function ($query) {
                $query->whereNull('max_periods')
                    ->orWhereColumn('applied_periods', '<', 'max_periods');
            })
            ->orderBy('id')
            ->get();

        $items = [];
        $amount = 0.0;

        foreach ($perceptions as $perception) {
            $balanceBefore = (float) $perception->remaining_amount;
            $periodAmount = (float) $perception->period_amount;
            $applyAmount = round(min($balanceBefore, $periodAmount), 2);

            if ($applyAmount <= 0) {
                continue;
            }

            $code = $perception->code ?: EmployeePayrollPerception::defaultCodeForType((string) $perception->type);
            $concept = $perception->concept;

            $items[] = [
                'id' => $perception->id,
                'type' => $perception->type,
                'code' => $concept?->code ?: $code,
                'name' => $concept?->name ?: $perception->name,
                'payroll_concept_id' => $concept?->id,
                'amount' => $applyAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => round($balanceBefore - $applyAmount, 2),
                'period_amount' => $periodAmount,
                'sort_order' => $concept?->sort_order ?: match ((string) $perception->type) {
                    'bonus' => 40,
                    'commission' => 50,
                    'gratification' => 60,
                    'transport_allowance' => 70,
                    'meal_allowance' => 80,
                    default => 90,
                },
            ];

            $amount += $applyAmount;
        }

        return [
            'amount' => round($amount, 2),
            'items' => $items,
        ];
    }

    protected static function applyEmployeePayrollPerception(PayrollRunLine $line, PayrollRunLineConcept $concept, array $row): void
    {
        if (
            ! class_exists(EmployeePayrollPerception::class)
            || ! class_exists(EmployeePayrollPerceptionApplication::class)
            || ! Schema::hasTable('employee_payroll_perceptions')
            || ! Schema::hasTable('employee_payroll_perception_applications')
        ) {
            return;
        }

        $metadata = $row['metadata'] ?? [];
        $item = $metadata['employee_payroll_perception'] ?? null;

        if (! is_array($item) || empty($item['id'])) {
            return;
        }

        $perception = EmployeePayrollPerception::query()
            ->whereKey($item['id'])
            ->lockForUpdate()
            ->first();

        if (! $perception || (string) $perception->status !== 'active') {
            return;
        }

        $amount = round(min((float) $concept->amount, (float) $perception->remaining_amount), 2);

        if ($amount <= 0) {
            return;
        }

        $balanceBefore = (float) $perception->remaining_amount;
        $balanceAfter = round(max(0, $balanceBefore - $amount), 2);

        $perception->remaining_amount = $balanceAfter;
        $perception->applied_periods = (int) $perception->applied_periods + 1;
        $perception->status = $balanceAfter <= 0 ? 'paid' : 'active';
        $perception->save();

        EmployeePayrollPerceptionApplication::create([
            'company_id' => $line->company_id,
            'employee_payroll_perception_id' => $perception->id,
            'payroll_run_id' => $line->payroll_run_id,
            'payroll_run_line_id' => $line->id,
            'payroll_run_line_concept_id' => $concept->id,
            'employee_id' => $line->employee_id,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'applied_at' => now(),
            'metadata' => [
                'code' => $concept->code,
                'name' => $concept->name,
                'type' => $perception->type,
            ],
        ]);
    }

    protected static function reverseEmployeeDeductionApplications(PayrollRun $run): void
    {
        if (
            ! class_exists(EmployeePayrollDeductionApplication::class)
            || ! class_exists(EmployeePayrollDeduction::class)
            || ! Schema::hasTable('employee_payroll_deduction_applications')
            || ! Schema::hasTable('employee_payroll_deductions')
        ) {
            return;
        }

        $applications = EmployeePayrollDeductionApplication::query()
            ->where('payroll_run_id', $run->id)
            ->get();

        foreach ($applications as $application) {
            $applicationMetadata = is_array($application->metadata)
                ? $application->metadata
                : [];

            $purchaseInstallmentIds = array_values(array_filter(
                array_map(
                    'intval',
                    (array) ($applicationMetadata['purchase_installment_ids'] ?? [])
                ),
                fn (int $id): bool => $id > 0
            ));

            if (
                $purchaseInstallmentIds
                && Schema::hasTable('employee_payroll_purchase_installments')
            ) {
                DB::table('employee_payroll_purchase_installments')
                    ->whereIn('id', $purchaseInstallmentIds)
                    ->where('employee_payroll_deduction_application_id', $application->id)
                    ->update([
                        'status' => 'pending',
                        'applied_amount' => 0,
                        'employee_payroll_deduction_application_id' => null,
                        'payroll_run_id' => null,
                        'applied_at' => null,
                        'updated_at' => now(),
                    ]);
            }

            $deduction = EmployeePayrollDeduction::query()
                ->whereKey($application->employee_payroll_deduction_id)
                ->lockForUpdate()
                ->first();

            if (! $deduction) {
                continue;
            }

            $deduction->outstanding_amount = round((float) $deduction->outstanding_amount + (float) $application->amount, 2);
            $deduction->applied_periods = max(0, (int) $deduction->applied_periods - 1);

            if ((string) $deduction->status === 'paid' && (float) $deduction->outstanding_amount > 0) {
                $deduction->status = 'active';
            }

            $deduction->save();

            if (
                (int) ($deduction->employee_payroll_purchase_id ?? 0) > 0
                && Schema::hasTable('employee_payroll_purchases')
                && (float) $deduction->outstanding_amount > 0
            ) {
                DB::table('employee_payroll_purchases')
                    ->where('id', (int) $deduction->employee_payroll_purchase_id)
                    ->where('status', 'paid')
                    ->update([
                        'status' => 'confirmed',
                        'updated_at' => now(),
                    ]);
            }
        }

        EmployeePayrollDeductionApplication::query()
            ->where('payroll_run_id', $run->id)
            ->delete();
    }

    protected static function employeePayrollDeductions(PayrollRun $run, Employee $employee): array
    {
        if (
            ! class_exists(EmployeePayrollDeduction::class)
            || ! Schema::hasTable('employee_payroll_deductions')
        ) {
            return [
                'amount' => 0.0,
                'items' => [],
            ];
        }

        $periodStart = CarbonImmutable::parse($run->period_start)->toDateString();
        $periodEnd = CarbonImmutable::parse($run->period_end)->toDateString();
        $paymentDate = $run->payment_date
            ? CarbonImmutable::parse($run->payment_date)->toDateString()
            : $periodEnd;

        $deductions = EmployeePayrollDeduction::query()
            ->with('concept')
            ->where('company_id', $run->company_id)
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->where('outstanding_amount', '>', 0)
            ->where('period_amount', '>', 0)
            ->where(function ($query) use ($paymentDate) {
                $query->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', $paymentDate);
            })
            ->where(function ($query) use ($periodStart) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $periodStart);
            })
            ->where(function ($query) {
                $query->whereNull('max_periods')
                    ->orWhereColumn('applied_periods', '<', 'max_periods');
            })
            ->orderBy('id')
            ->get();

        $items = [];
        $amount = 0.0;

        foreach ($deductions as $deduction) {
            $balanceBefore = (float) $deduction->outstanding_amount;
            $periodAmount = (float) $deduction->period_amount;
            $purchaseId = (int) ($deduction->employee_payroll_purchase_id ?? 0);
            $purchaseInstallmentIds = [];

            if (
                $purchaseId > 0
                && Schema::hasTable('employee_payroll_purchase_installments')
            ) {
                $dueInstallments = DB::table('employee_payroll_purchase_installments')
                    ->where('employee_payroll_purchase_id', $purchaseId)
                    ->where('employee_payroll_deduction_id', $deduction->id)
                    ->where('status', 'pending')
                    ->whereDate('due_date', '<=', $paymentDate)
                    ->orderBy('due_date')
                    ->orderBy('installment_number')
                    ->get(['id', 'scheduled_amount']);

                if ($dueInstallments->isEmpty()) {
                    continue;
                }

                $purchaseInstallmentIds = $dueInstallments
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                $periodAmount = round(
                    (float) $dueInstallments->sum(
                        fn ($row) => (float) $row->scheduled_amount
                    ),
                    2
                );
            }

            $applyAmount = round(min($balanceBefore, $periodAmount), 2);

            if ($applyAmount <= 0) {
                continue;
            }

            $code = $deduction->code ?: EmployeePayrollDeduction::defaultCodeForType((string) $deduction->type);
            $concept = $deduction->concept;

            $items[] = [
                'id' => $deduction->id,
                'type' => $deduction->type,
                'code' => $concept?->code ?: $code,
                'name' => $concept?->name ?: $deduction->name,
                'payroll_concept_id' => $concept?->id,
                'amount' => $applyAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => round($balanceBefore - $applyAmount, 2),
                'period_amount' => $periodAmount,
                'employee_payroll_purchase_id' => $purchaseId > 0 ? $purchaseId : null,
                'purchase_installment_ids' => $purchaseInstallmentIds,
                'sort_order' => $concept?->sort_order ?: match ((string) $deduction->type) {
                    'loan' => 210,
                    'advance' => 220,
                    'product_purchase' => 240,
                    default => 230,
                },
            ];

            $amount += $applyAmount;
        }

        return [
            'amount' => round($amount, 2),
            'items' => $items,
        ];
    }

    protected static function applyEmployeePayrollDeduction(PayrollRunLine $line, PayrollRunLineConcept $concept, array $row): void
    {
        if (
            ! class_exists(EmployeePayrollDeduction::class)
            || ! class_exists(EmployeePayrollDeductionApplication::class)
            || ! Schema::hasTable('employee_payroll_deductions')
            || ! Schema::hasTable('employee_payroll_deduction_applications')
        ) {
            return;
        }

        $metadata = $row['metadata'] ?? [];
        $item = $metadata['employee_payroll_deduction'] ?? null;

        if (! is_array($item) || empty($item['id'])) {
            return;
        }

        $deduction = EmployeePayrollDeduction::query()
            ->whereKey($item['id'])
            ->lockForUpdate()
            ->first();

        if (! $deduction || (string) $deduction->status !== 'active') {
            return;
        }

        $amount = round(min((float) $concept->amount, (float) $deduction->outstanding_amount), 2);

        if ($amount <= 0) {
            return;
        }

        $balanceBefore = (float) $deduction->outstanding_amount;
        $balanceAfter = round(max(0, $balanceBefore - $amount), 2);

        $deduction->outstanding_amount = $balanceAfter;
        $deduction->applied_periods = (int) $deduction->applied_periods + 1;
        $deduction->status = $balanceAfter <= 0 ? 'paid' : 'active';
        $deduction->save();

        $application = EmployeePayrollDeductionApplication::create([
            'company_id' => $line->company_id,
            'employee_payroll_deduction_id' => $deduction->id,
            'payroll_run_id' => $line->payroll_run_id,
            'payroll_run_line_id' => $line->id,
            'payroll_run_line_concept_id' => $concept->id,
            'employee_id' => $line->employee_id,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'applied_at' => now(),
            'metadata' => [
                'code' => $concept->code,
                'name' => $concept->name,
                'type' => $deduction->type,
                'employee_payroll_purchase_id' => $item['employee_payroll_purchase_id'] ?? null,
                'purchase_installment_ids' => $item['purchase_installment_ids'] ?? [],
            ],
        ]);

        $purchaseInstallmentIds = array_values(array_filter(
            array_map('intval', (array) ($item['purchase_installment_ids'] ?? [])),
            fn (int $id): bool => $id > 0
        ));

        if (
            $purchaseInstallmentIds
            && Schema::hasTable('employee_payroll_purchase_installments')
        ) {
            $remainingToApply = $amount;

            $installments = DB::table('employee_payroll_purchase_installments')
                ->whereIn('id', $purchaseInstallmentIds)
                ->where('status', 'pending')
                ->orderBy('due_date')
                ->orderBy('installment_number')
                ->get();

            foreach ($installments as $installment) {
                if ($remainingToApply <= 0) {
                    break;
                }

                $scheduled = (float) $installment->scheduled_amount;
                $applied = round(min($scheduled, $remainingToApply), 2);
                $isFullyApplied = abs($applied - $scheduled) < 0.005;

                DB::table('employee_payroll_purchase_installments')
                    ->where('id', (int) $installment->id)
                    ->update([
                        'status' => $isFullyApplied ? 'applied' : 'pending',
                        'applied_amount' => $applied,
                        'employee_payroll_deduction_application_id' => $application->id,
                        'payroll_run_id' => $line->payroll_run_id,
                        'applied_at' => now(),
                        'updated_at' => now(),
                    ]);

                $remainingToApply = round($remainingToApply - $applied, 2);
            }
        }

        $purchaseId = (int) ($item['employee_payroll_purchase_id'] ?? 0);

        if (
            $purchaseId > 0
            && Schema::hasTable('employee_payroll_purchases')
            && $balanceAfter <= 0
        ) {
            DB::table('employee_payroll_purchases')
                ->where('id', $purchaseId)
                ->update([
                    'status' => 'paid',
                    'updated_at' => now(),
                ]);
        }
    }

    protected static function createLineConcepts(PayrollRunLine $line): void
    {
        if (! class_exists(PayrollRunLineConcept::class) || ! Schema::hasTable('payroll_run_line_concepts')) {
            return;
        }

        PayrollRunLineConcept::query()
            ->where('payroll_run_line_id', $line->id)
            ->delete();

        $details = $line->details ?: [];
        $policy = $details['payroll_policy'] ?? [];
        $policyDeductions = $policy['policy_deductions']['items'] ?? [];
        $employeeDeductions = $details['employee_payroll_deductions']['items'] ?? [];
        $employeePerceptions = $details['employee_payroll_perceptions']['items'] ?? [];

        $rows = [];

        if ((float) $line->base_amount !== 0.0) {
            $rows[] = static::conceptPayload(
                line: $line,
                code: 'SUELDO_BASE',
                name: 'Sueldo base',
                type: 'perception',
                category: 'base_salary',
                source: 'system',
                unit: 'days',
                quantity: (float) $line->period_days,
                rate: (float) $line->daily_salary,
                amount: (float) $line->base_amount,
                metadata: [
                    'salary_type' => $line->salary_type,
                    'base_salary' => (float) $line->base_salary,
                ],
                sortOrder: 10,
            );
        }

        if ((float) $line->overtime_amount !== 0.0) {
            $overtimeMultiplier = (float) ($policy['overtime_multiplier'] ?? 2);
            $rows[] = static::conceptPayload(
                line: $line,
                code: 'HORAS_EXTRA',
                name: 'Horas extra',
                type: 'perception',
                category: 'overtime',
                source: 'system',
                unit: 'hours',
                quantity: (float) $line->overtime_hours,
                rate: round((float) $line->hourly_rate * $overtimeMultiplier, 4),
                amount: (float) $line->overtime_amount,
                metadata: [
                    'overtime_minutes' => (int) $line->overtime_minutes,
                    'hourly_rate' => (float) $line->hourly_rate,
                    'multiplier' => $overtimeMultiplier,
                ],
                sortOrder: 20,
            );
        }

        /*
         * BEXIA_V5836_INCAPACITY_LINE_CONCEPTS
         *
         * Separar la incapacidad de las incidencias genéricas
         * para conservar su TipoPercepcion / TipoDeduccion SAT.
         */
        $incapacityEconomic =
            $details[
                'incapacity_economic_allocations'
            ] ?? [
                'perceptions' => 0,
                'deductions' => 0,
                'items' => [],
            ];

        $incapacityPerceptionAmount = round(
            (float) (
                $incapacityEconomic[
                    'perceptions'
                ] ?? 0
            ),
            2
        );

        $incapacityDeductionAmount = round(
            (float) (
                $incapacityEconomic[
                    'deductions'
                ] ?? 0
            ),
            2
        );

        $genericIncidentPerceptions = round(
            max(
                0,
                (float) $line->incident_perceptions
                    - $incapacityPerceptionAmount
            ),
            2
        );

        $genericIncidentDeductions = round(
            max(
                0,
                (float) $line->incident_deductions
                    - $incapacityDeductionAmount
            ),
            2
        );

        if ($genericIncidentPerceptions > 0) {
            $rows[] = static::conceptPayload(
                line: $line,
                code: 'INCIDENCIAS_PERCEPCION',
                name: 'Percepciones por incidencias',
                type: 'perception',
                category: 'incident',
                source: 'incident',
                unit: 'amount',
                quantity: (float) $line->approved_incidents_count,
                rate: 0,
                amount: $genericIncidentPerceptions,
                metadata: [
                    'approved_incident_ids' =>
                        $details[
                            'approved_incident_ids'
                        ] ?? [],
                ],
                sortOrder: 30,
            );
        }

        foreach (
            $incapacityEconomic['items'] ?? []
            as $incapacityItem
        ) {
            $effect =
                (string) (
                    $incapacityItem[
                        'payroll_effect'
                    ] ?? 'none'
                );

            $amount = round(
                (float) (
                    $incapacityItem[
                        'period_monetary_amount'
                    ] ?? 0
                ),
                2
            );

            if (
                $effect === 'perception'
                && $amount > 0
            ) {
                $rows[] = static::conceptPayload(
                    line: $line,
                    code: 'INCAPACIDAD_PERCEPCION',
                    name: 'Subsidio por incapacidad',
                    type: 'perception',
                    category: 'incapacity',
                    source: 'incident',
                    unit: 'days',
                    quantity: (float) (
                        $incapacityItem[
                            'days_in_period'
                        ] ?? 0
                    ),
                    rate: (
                        (float) (
                            $incapacityItem[
                                'days_in_period'
                            ] ?? 0
                        ) > 0
                    )
                        ? round(
                            $amount
                            / (float) $incapacityItem[
                                'days_in_period'
                            ],
                            4
                        )
                        : 0,
                    amount: $amount,
                    metadata: [
                        /*
                         * BEXIA_V5836_INCAPACITY_CFDI_KEY
                         *
                         * Clave interna != Clave CFDI Nómina.
                         */
                        'cfdi_key' =>
                            'INCAP_PER',

                        'incapacity' =>
                            $incapacityItem,
                    ],
                    sortOrder: 35,
                );

                /*
                 * BEXIA_V5836_INCAPACITY_CONCEPT_EXPLICIT_TAX_SPLIT
                 *
                 * Sobrescribir el gravado/exento del payload
                 * genérico con el reparto explícito del periodo.
                 */
                $lastIndex = array_key_last($rows);

                $rows[$lastIndex][
                    'taxable_amount'
                ] = round(
                    (float) (
                        $incapacityItem[
                            'period_taxable_amount'
                        ] ?? 0
                    ),
                    2
                );

                $rows[$lastIndex][
                    'exempt_amount'
                ] = round(
                    (float) (
                        $incapacityItem[
                            'period_exempt_amount'
                        ] ?? 0
                    ),
                    2
                );
            }

            if (
                $effect === 'deduction'
                && $amount > 0
            ) {
                $rows[] = static::conceptPayload(
                    line: $line,
                    code: 'INCAPACIDAD_DEDUCCION',
                    name: 'Descuento por incapacidad',
                    type: 'deduction',
                    category: 'incapacity',
                    source: 'incident',
                    unit: 'days',
                    quantity: (float) (
                        $incapacityItem[
                            'days_in_period'
                        ] ?? 0
                    ),
                    rate: (
                        (float) (
                            $incapacityItem[
                                'days_in_period'
                            ] ?? 0
                        ) > 0
                    )
                        ? round(
                            $amount
                            / (float) $incapacityItem[
                                'days_in_period'
                            ],
                            4
                        )
                        : 0,
                    amount: $amount,
                    metadata: [
                        'cfdi_key' =>
                            'INCAP_DED',

                        'incapacity' =>
                            $incapacityItem,
                    ],
                    sortOrder: 105,
                );
            }
        }

        if ($genericIncidentDeductions > 0) {
            $rows[] = static::conceptPayload(
                line: $line,
                code: 'INCIDENCIAS_DEDUCCION',
                name: 'Deducciones por incidencias',
                type: 'deduction',
                category: 'incident',
                source: 'incident',
                unit: 'amount',
                quantity: (float) $line->approved_incidents_count,
                rate: 0,
                amount: $genericIncidentDeductions,
                metadata: [
                    'approved_incident_ids' =>
                        $details[
                            'approved_incident_ids'
                        ] ?? [],
                    'deduction_days' =>
                        (float) $line
                            ->approved_incident_deduction_days,
                    'deduction_minutes' =>
                        (int) $line
                            ->approved_incident_deduction_minutes,
                ],
                sortOrder: 110,
            );
        }

        foreach ($policyDeductions as $item) {
            $type = (string) ($item['type'] ?? 'policy');
            $quantity = (float) ($item['quantity'] ?? 0);
            $amount = (float) ($item['amount'] ?? 0);

            if ($amount === 0.0) {
                continue;
            }

            [$code, $name, $unit, $sortOrder] = match ($type) {
                'late_minutes' => ['POLITICA_RETARDO', 'Descuento por retardo', 'minutes', 120],
                'early_leave_minutes' => ['POLITICA_SALIDA_TEMPRANA', 'Descuento por salida temprana', 'minutes', 130],
                'attendance_absence_days', 'late_accumulated_absence', 'late_count_absence' => ['POLITICA_FALTA', 'Descuento por falta', 'days', 140],
                default => ['POLITICA_RETARDO', 'Descuento por política de asistencia', 'units', 150],
            };

            $rows[] = static::conceptPayload(
                line: $line,
                code: $code,
                name: $name,
                type: 'deduction',
                category: 'attendance',
                source: 'policy',
                unit: $unit,
                quantity: $quantity,
                rate: $quantity > 0 ? round($amount / $quantity, 4) : 0,
                amount: $amount,
                metadata: [
                    'policy_item' => $item,
                    'policy' => [
                        'id' => $policy['id'] ?? null,
                        'name' => $policy['name'] ?? null,
                    ],
                ],
                sortOrder: $sortOrder,
            );
        }

        foreach ($employeePerceptions as $item) {
            $amount = (float) ($item['amount'] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            $rows[] = static::conceptPayload(
                line: $line,
                code: (string) ($item['code'] ?? 'BONO_PRODUCTIVIDAD'),
                name: (string) ($item['name'] ?? 'Percepción de empleado'),
                type: 'perception',
                category: 'manual',
                source: 'manual',
                unit: 'amount',
                quantity: 1,
                rate: $amount,
                amount: $amount,
                metadata: [
                    'employee_payroll_perception' => $item,
                ],
                sortOrder: (int) ($item['sort_order'] ?? 40),
            );
        }

        foreach ($employeeDeductions as $item) {
            $amount = (float) ($item['amount'] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            $rows[] = static::conceptPayload(
                line: $line,
                code: (string) ($item['code'] ?? 'DESCUENTO_RECURRENTE'),
                name: (string) ($item['name'] ?? 'Descuento de empleado'),
                type: 'deduction',
                category: 'manual',
                source: 'manual',
                unit: 'amount',
                quantity: 1,
                rate: $amount,
                amount: $amount,
                metadata: [
                    'employee_payroll_deduction' => $item,
                ],
                sortOrder: (int) ($item['sort_order'] ?? 210),
            );
        }

        foreach ($rows as $row) {
            $concept = PayrollRunLineConcept::create($row);
            static::applyEmployeePayrollDeduction($line, $concept, $row);
            static::applyEmployeePayrollPerception($line, $concept, $row);
        }
    }

    protected static function conceptPayload(
        PayrollRunLine $line,
        string $code,
        string $name,
        string $type,
        string $category,
        string $source,
        string $unit,
        float $quantity,
        float $rate,
        float $amount,
        array $metadata,
        int $sortOrder,
    ): array {
        $concept = static::ensurePayrollConcept(
            companyId: (int) $line->company_id,
            code: $code,
            name: $name,
            type: $type,
            category: $category,
            source: $source,
            unit: $unit,
            sortOrder: $sortOrder,
        );

        /*
         * BEXIA_V5836_INCAPACITY_CONCEPT_SAT_KEYS
         *
         * Estos dos conceptos son determinísticos por definición.
         * No dejar que nazcan con sat_key NULL.
         */
        if (
            $concept
            && $code === 'INCAPACIDAD_PERCEPCION'
        ) {
            /*
             * BEXIA_V5836_INCAPACITY_NO_IMPLICIT_TAXABILITY
             *
             * SAT 014 queda identificado, pero gravado/exento
             * se congela por incidencia y no se infiere aquí.
             */
            $concept->forceFill([
                'sat_key' => '014',
                'is_taxable' => null,
                'reduces_taxable_base' => false,
            ])->save();
        }

        if (
            $concept
            && $code === 'INCAPACIDAD_DEDUCCION'
        ) {
            $concept->forceFill([
                'sat_key' => '006',
                'reduces_taxable_base' => false,
            ])->save();
        }

        /*
         * BEXIA_V5836_CATALOG_CFDI_KEY_TO_METADATA
         *
         * La Clave CFDI configurada en el catálogo se congela
         * dentro de metadata del concepto de línea.
         *
         * Si metadata ya trae una clave explícita (por ejemplo
         * incapacidad), se conserva. Después el snapshot de versión
         * congela exactamente este metadata.
         */
        if (
            ! isset($metadata['cfdi_key'])
            && $concept !== null
            && filled($concept->cfdi_key)
        ) {
            $metadata['cfdi_key'] =
                (string) $concept->cfdi_key;
        }

        return [
            'company_id' => $line->company_id,
            'payroll_run_id' => $line->payroll_run_id,
            'payroll_run_line_id' => $line->id,
            'employee_id' => $line->employee_id,
            'payroll_concept_id' => $concept?->id,
            'code' => $code,
            'name' => $concept?->name ?: $name,
            'type' => $concept?->type ?: $type,
            'category' => $concept?->category ?: $category,
            'source' => $concept?->source ?: $source,
            'unit' => $concept?->unit ?: $unit,
            'sat_key' => $concept?->sat_key,
            'quantity' => round($quantity, 4),
            'rate' => round($rate, 4),
            'amount' => round($amount, 2),
            'taxable_amount' => static::conceptTaxableAmount(
                $concept,
                $type,
                $amount
            ),
            'exempt_amount' => static::conceptExemptAmount(
                $concept,
                $type,
                $amount
            ),
            'reduces_taxable_base' =>
                $concept !== null
                    ? (bool) $concept->reduces_taxable_base
                    : false,
            'metadata' => $metadata,
            'sort_order' => $concept?->sort_order ?: $sortOrder,
        ];
    }

    protected static function conceptTaxableAmount(
        ?PayrollConcept $concept,
        string $type,
        float $amount
    ): ?float {
        /*
         * Gravado/exento aplica a percepciones.
         * Las deducciones usan TipoDeduccion SAT y no esta división.
         */
        if ($type !== 'perception' || ! $concept) {
            return null;
        }

        if ($concept->is_taxable === null) {
            return null;
        }

        if ((bool) $concept->is_taxable) {
            return round($amount, 2);
        }

        return 0.0;
    }

    protected static function conceptExemptAmount(
        ?PayrollConcept $concept,
        string $type,
        float $amount
    ): ?float {
        if ($type !== 'perception' || ! $concept) {
            return null;
        }

        if ($concept->is_taxable === null) {
            return null;
        }

        if ((bool) $concept->is_taxable) {
            return 0.0;
        }

        return round($amount, 2);
    }

    protected static function ensurePayrollConcept(
        int $companyId,
        string $code,
        string $name,
        string $type,
        string $category,
        string $source,
        string $unit,
        int $sortOrder,
    ): ?PayrollConcept {
        if (! class_exists(PayrollConcept::class) || ! Schema::hasTable('payroll_concepts')) {
            return null;
        }

        return PayrollConcept::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'code' => $code,
            ],
            [
                'name' => $name,
                'type' => $type,
                'category' => $category,
                'source' => $source,
                'unit' => $unit,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ],
        );
    }

    /**
     * Resume únicamente las incidencias de asistencia ya resueltas
     * como NO JUSTIFICADAS (status=rejected).
     *
     * Las aprobadas están justificadas.
     * Las pending/draft todavía no producen descuento.
     */
    protected static function attendanceIncidentResolution(
        PayrollRun $run,
        Employee $employee,
        $attendances
    ): array {
        $result = [
            'managed' => false,
            'late_minutes' => 0,
            'late_count' => 0,
            'early_leave_minutes' => 0,
            'absence_days' => 0.0,
            'approved_justified_incident_ids' => [],
            'rejected_unjustified_incident_ids' => [],
            'pending_incident_ids' => [],
        ];

        if (
            ! Schema::hasColumn(
                'employee_incidents',
                'employee_attendance_id'
            )
            || $attendances->isEmpty()
        ) {
            return $result;
        }

        $attendanceIds = $attendances
            ->pluck('id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if (empty($attendanceIds)) {
            return $result;
        }

        $incidents = EmployeeIncident::query()
            ->with('incidentType')
            ->where('company_id', $run->company_id)
            ->where('employee_id', $employee->id)
            ->whereIn('employee_attendance_id', $attendanceIds)
            ->whereIn(
                'status',
                ['draft', 'pending', 'approved', 'rejected']
            )
            ->get();

        /*
         * En cuanto existen incidencias automáticas asociadas,
         * éstas son la fuente de verdad para el efecto de asistencia
         * en nómina.
         */
        $managedCodes = [
            'RETARDO',
            'FALTA',
            'SALIDA_TEMPRANA',
        ];

        $managed = $incidents->filter(
            function ($incident) use ($managedCodes): bool {
                $code = strtoupper(
                    (string) ($incident->incidentType?->code ?? '')
                );

                return in_array($code, $managedCodes, true);
            }
        );

        if ($managed->isEmpty()) {
            return $result;
        }

        $result['managed'] = true;

        foreach ($managed as $incident) {
            $status = (string) $incident->status;
            $code = strtoupper(
                (string) ($incident->incidentType?->code ?? '')
            );

            if ($status === 'approved') {
                $result['approved_justified_incident_ids'][] =
                    (int) $incident->id;

                continue;
            }

            if (in_array($status, ['draft', 'pending'], true)) {
                $result['pending_incident_ids'][] =
                    (int) $incident->id;

                continue;
            }

            if ($status !== 'rejected') {
                continue;
            }

            $result['rejected_unjustified_incident_ids'][] =
                (int) $incident->id;

            $quantity = max(
                0,
                (float) ($incident->quantity ?? 0)
            );

            if ($code === 'RETARDO') {
                $result['late_count']++;
                $result['late_minutes'] +=
                    (int) round($quantity);

                continue;
            }

            if ($code === 'SALIDA_TEMPRANA') {
                $result['early_leave_minutes'] +=
                    (int) round($quantity);

                continue;
            }

            if ($code === 'FALTA') {
                $result['absence_days'] +=
                    $quantity > 0 ? $quantity : 1.0;
            }
        }

        $result['approved_justified_incident_ids'] =
            array_values(array_unique(
                $result['approved_justified_incident_ids']
            ));

        $result['rejected_unjustified_incident_ids'] =
            array_values(array_unique(
                $result['rejected_unjustified_incident_ids']
            ));

        $result['pending_incident_ids'] =
            array_values(array_unique(
                $result['pending_incident_ids']
            ));

        return $result;
    }

    protected static function approvedPayrollIncidents(PayrollRun $run, Employee $employee)
    {
        $query = EmployeeIncident::query()
            ->with('incidentType')
            ->where('company_id', $run->company_id)
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('affects_payroll', true);

        /*
         * Una incidencia ligada a asistencia usa la política de asistencia.
         *
         * approved significa JUSTIFICADA y por ello no debe convertirse
         * aquí en una deducción individual.
         */
        if (
            Schema::hasColumn(
                'employee_incidents',
                'employee_attendance_id'
            )
        ) {
            $query->whereNull('employee_attendance_id');
        }

        return $query
            ->whereDate('start_date', '<=', $run->period_end)
            ->where(function ($query) use ($run): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $run->period_start);
            })
            ->get();
    }

    /**
     * Días no pagados explícitos provenientes exclusivamente
     * de incidencias manuales aprobadas.
     *
     * approvedPayrollIncidents() ya excluye incidencias
     * ligadas a employee_attendance_id.
     */
    /**
     * BEXIA_V5836_INCAPACITY_FISCAL_GUARD
     *
     * Normaliza y valida incapacidades aprobadas antes de
     * permitir que la capa fiscal las utilice.
     *
     * No calcula importes ni modifica payable_days.
     */
    protected static function normalizedIncapacityIncidents(
        $incidents
    ): array {
        $result = [];

        foreach ($incidents as $incident) {
            $type = $incident->incidentType;

            $code = strtoupper(
                trim(
                    (string) (
                        $type?->code
                        ?? ''
                    )
                )
            );

            if ($code !== 'INCAPACIDAD') {
                continue;
            }

            $unit = strtolower(
                trim(
                    (string) (
                        $incident->quantity_unit
                        ?? ''
                    )
                )
            );

            if ($unit !== 'days') {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($incident->id ?? 'N/D')
                    .' debe estar expresada en días.'
                );
            }

            if ($incident->quantity === null) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($incident->id ?? 'N/D')
                    .' no tiene DiasIncapacidad.'
                );
            }

            $days = (float) $incident->quantity;

            if (
                $days < 1
                || abs($days - round($days)) > 0.000001
            ) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($incident->id ?? 'N/D')
                    .' debe tener un número entero '
                    .'de días mayor o igual a 1.'
                );
            }

            $satType = trim(
                (string) (
                    $incident->sat_incapacity_type_code
                    ?? ''
                )
            );

            if (
                ! in_array(
                    $satType,
                    ['01', '02', '03', '04'],
                    true
                )
            ) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($incident->id ?? 'N/D')
                    .' no tiene TipoIncapacidad SAT válido.'
                );
            }

            /*
             * BEXIA_V5836_INCAPACITY_EFFECT_GUARD
             */
            $payrollEffect = trim(
                (string) (
                    $incident->incapacity_payroll_effect
                    ?? ''
                )
            );

            if (
                ! in_array(
                    $payrollEffect,
                    [
                        'none',
                        'perception',
                        'deduction',
                    ],
                    true
                )
            ) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($incident->id ?? 'N/D')
                    .' no tiene tratamiento de nómina válido.'
                );
            }

            /*
             * BEXIA_V5836_INCAPACITY_EXPLICIT_SEMANTICS_GUARD
             */
            $salaryTreatment = trim(
                (string) (
                    $incident->incapacity_salary_treatment
                    ?? ''
                )
            );

            $taxableAmountRaw =
                $incident->incapacity_taxable_amount;

            $exemptAmountRaw =
                $incident->incapacity_exempt_amount;

            $amount =
                $incident->incapacity_monetary_amount;

            if (
                in_array(
                    $payrollEffect,
                    [
                        'perception',
                        'deduction',
                    ],
                    true
                )
                && ! in_array(
                    $salaryTreatment,
                    [
                        'keep_salary',
                        'replace_salary_days',
                    ],
                    true
                )
            ) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($incident->id ?? 'N/D')
                    .' requiere tratamiento explícito '
                    .'del sueldo ordinario.'
                );
            }

            if (
                $payrollEffect === 'none'
                && $salaryTreatment !== ''
            ) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($incident->id ?? 'N/D')
                    .' no tiene efecto económico y no debe '
                    .'definir tratamiento salarial.'
                );
            }

            if (
                in_array(
                    $payrollEffect,
                    [
                        'perception',
                        'deduction',
                    ],
                    true
                )
                && (
                    $amount === null
                    || (float) $amount <= 0
                )
            ) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($incident->id ?? 'N/D')
                    .' requiere ImporteMonetario mayor a cero.'
                );
            }

            if (
                $payrollEffect === 'none'
                && $amount !== null
                && abs((float) $amount) > 0.000001
            ) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($incident->id ?? 'N/D')
                    .' tiene tratamiento sin importe pero '
                    .'contiene ImporteMonetario.'
                );
            }

            $taxableAmount =
                $taxableAmountRaw !== null
                    ? round(
                        (float) $taxableAmountRaw,
                        2
                    )
                    : null;

            $exemptAmount =
                $exemptAmountRaw !== null
                    ? round(
                        (float) $exemptAmountRaw,
                        2
                    )
                    : null;

            if (
                $taxableAmount !== null
                && $taxableAmount < 0
            ) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($incident->id ?? 'N/D')
                    .' tiene importe gravado inválido.'
                );
            }

            if (
                $exemptAmount !== null
                && $exemptAmount < 0
            ) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($incident->id ?? 'N/D')
                    .' tiene importe exento inválido.'
                );
            }

            if ($payrollEffect === 'perception') {
                if (
                    $taxableAmount === null
                    || $exemptAmount === null
                ) {
                    throw new \RuntimeException(
                        'La incapacidad '
                        .($incident->id ?? 'N/D')
                        .' requiere importe gravado y exento '
                        .'explícitos.'
                    );
                }

                if (
                    abs(
                        round(
                            $taxableAmount
                                + $exemptAmount,
                            2
                        )
                        - round(
                            (float) $amount,
                            2
                        )
                    ) > 0.009
                ) {
                    throw new \RuntimeException(
                        'La incapacidad '
                        .($incident->id ?? 'N/D')
                        .' tiene gravado + exento distinto '
                        .'del ImporteMonetario.'
                    );
                }
            }

            if (
                in_array(
                    $payrollEffect,
                    [
                        'none',
                        'deduction',
                    ],
                    true
                )
                && (
                    $taxableAmount !== null
                    || $exemptAmount !== null
                )
            ) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($incident->id ?? 'N/D')
                    .' no debe tener desglose gravado/exento '
                    .'para este tratamiento.'
                );
            }

            if (
                $amount !== null
                && (float) $amount < 0
            ) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($incident->id ?? 'N/D')
                    .' tiene ImporteMonetario inválido.'
                );
            }

            $result[] = [
                'incident_id' =>
                    ! empty($incident->id)
                        ? (int) $incident->id
                        : null,

                'start_date' =>
                    $incident->start_date
                        ? $incident->start_date->toDateString()
                        : null,

                'end_date' =>
                    $incident->end_date
                        ? $incident->end_date->toDateString()
                        : (
                            $incident->start_date
                                ? $incident->start_date->toDateString()
                                : null
                        ),

                'days' => (int) round($days),

                'sat_type_code' => $satType,

                'payroll_effect' =>
                    $payrollEffect,

                'salary_treatment' =>
                    $salaryTreatment !== ''
                        ? $salaryTreatment
                        : null,

                'taxable_amount' =>
                    $taxableAmount,

                'exempt_amount' =>
                    $exemptAmount,

                'monetary_amount' =>
                    $amount !== null
                        ? round((float) $amount, 2)
                        : null,
            ];
        }

        return $result;
    }


    /**
     * BEXIA_V5836_INCAPACITY_PERIOD_OVERLAP
     *
     * Calcula la porción de cada incapacidad que cae dentro
     * del periodo de nómina.
     *
     * Falla cerrado si quantity no coincide con las fechas
     * completas capturadas para la incapacidad.
     */
    protected static function incapacityPeriodsForRun(
        array $incapacities,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd
    ): array {
        $result = [];

        foreach ($incapacities as $row) {
            $startRaw = $row['start_date'] ?? null;
            $endRaw = $row['end_date'] ?? $startRaw;

            if (blank($startRaw)) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($row['incident_id'] ?? 'N/D')
                    .' no tiene fecha de inicio.'
                );
            }

            $start = CarbonImmutable::parse(
                $startRaw
            )->startOfDay();

            $end = CarbonImmutable::parse(
                $endRaw
            )->startOfDay();

            if ($end->lessThan($start)) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($row['incident_id'] ?? 'N/D')
                    .' tiene fecha final anterior '
                    .'a la fecha inicial.'
                );
            }

            $capturedDays = (int) (
                $row['days']
                ?? 0
            );

            /*
             * BEXIA_V5836_INCAPACITY_DATE_SPAN_INT
             *
             * Carbon puede devolver un tipo numérico distinto de int.
             * Normalizamos antes de comparar estrictamente.
             */
            $dateSpanDays =
                (int) round(
                    $start->diffInDays($end)
                ) + 1;

            /*
             * No adivinar qué dato es correcto.
             * Si días y fechas no coinciden, RRHH debe corregir
             * la incidencia antes de usarla fiscalmente.
             */
            if ($capturedDays !== $dateSpanDays) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($row['incident_id'] ?? 'N/D')
                    .' tiene '
                    .$capturedDays
                    .' días capturados pero sus fechas '
                    .'abarcan '
                    .$dateSpanDays
                    .' días.'
                );
            }

            $overlapStart =
                $start->greaterThan($periodStart)
                    ? $start
                    : $periodStart;

            $overlapEnd =
                $end->lessThan($periodEnd)
                    ? $end
                    : $periodEnd;

            if ($overlapEnd->lessThan($overlapStart)) {
                continue;
            }

            $daysInPeriod =
                $overlapStart->diffInDays(
                    $overlapEnd
                ) + 1;

            $result[] = [
                ...$row,

                'original_start_date' =>
                    $start->toDateString(),

                'original_end_date' =>
                    $end->toDateString(),

                'period_start_date' =>
                    $overlapStart->toDateString(),

                'period_end_date' =>
                    $overlapEnd->toDateString(),

                'days_in_period' =>
                    (int) $daysInPeriod,
            ];
        }

        return $result;
    }


    /**
     * BEXIA_V5836_INCAPACITY_PERIOD_AMOUNT_ALLOCATION
     *
     * incapacity_monetary_amount representa el importe TOTAL
     * de la incapacidad completa.
     *
     * Cuando la incapacidad cruza periodos, se distribuye
     * proporcionalmente por días usando acumulados redondeados.
     *
     * De esta forma la suma de todos los periodos reproduce
     * exactamente el importe total capturado, incluyendo centavos.
     *
     * No modifica todavía los totales económicos de la nómina.
     */
    protected static function incapacityEconomicAllocations(
        array $periodIncapacities
    ): array {
        $items = [];
        $perceptions = 0.0;
        $deductions = 0.0;

        foreach ($periodIncapacities as $row) {
            $effect = (string) (
                $row['payroll_effect']
                ?? 'none'
            );

            $row['period_monetary_amount'] = 0.0;
            $row['sat_payroll_key'] = null;

            if ($effect === 'none') {
                $items[] = $row;
                continue;
            }

            if (
                ! in_array(
                    $effect,
                    [
                        'perception',
                        'deduction',
                    ],
                    true
                )
            ) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($row['incident_id'] ?? 'N/D')
                    .' tiene tratamiento económico inválido.'
                );
            }

            $totalDays = (int) (
                $row['days']
                ?? 0
            );

            $totalAmount = round(
                (float) (
                    $row['monetary_amount']
                    ?? 0
                ),
                2
            );

            if ($totalDays < 1) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($row['incident_id'] ?? 'N/D')
                    .' no tiene días totales válidos.'
                );
            }

            if ($totalAmount <= 0) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($row['incident_id'] ?? 'N/D')
                    .' no tiene importe total válido.'
                );
            }

            $originalStart =
                CarbonImmutable::parse(
                    (string) $row[
                        'original_start_date'
                    ]
                )->startOfDay();

            $periodStart =
                CarbonImmutable::parse(
                    (string) $row[
                        'period_start_date'
                    ]
                )->startOfDay();

            $periodEnd =
                CarbonImmutable::parse(
                    (string) $row[
                        'period_end_date'
                    ]
                )->startOfDay();

            /*
             * Número de días completos anteriores al tramo
             * que cae en esta corrida.
             */
            $daysBefore =
                (int) round(
                    $originalStart->diffInDays(
                        $periodStart
                    )
                );

            /*
             * Número acumulado de días desde el inicio de la
             * incapacidad hasta el final del tramo actual,
             * incluyendo ambos extremos.
             */
            $daysThrough =
                (int) round(
                    $originalStart->diffInDays(
                        $periodEnd
                    )
                ) + 1;

            if (
                $daysBefore < 0
                || $daysThrough < 1
                || $daysThrough > $totalDays
                || $daysBefore >= $daysThrough
            ) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($row['incident_id'] ?? 'N/D')
                    .' tiene un tramo monetario inválido.'
                );
            }

            /*
             * Asignación acumulativa:
             *
             * round(total * dias_hasta / dias_total)
             * -
             * round(total * dias_antes / dias_total)
             *
             * Evita perder/duplicar centavos cuando el incidente
             * cruza varios periodos.
             */
            $cumulativeBefore = round(
                $totalAmount
                    * $daysBefore
                    / $totalDays,
                2
            );

            $cumulativeThrough = round(
                $totalAmount
                    * $daysThrough
                    / $totalDays,
                2
            );

            $periodAmount = round(
                $cumulativeThrough
                    - $cumulativeBefore,
                2
            );

            if ($periodAmount < 0) {
                throw new \RuntimeException(
                    'La incapacidad '
                    .($row['incident_id'] ?? 'N/D')
                    .' produjo un importe de periodo negativo.'
                );
            }

            $row['period_monetary_amount'] =
                $periodAmount;

            /*
             * BEXIA_V5836_INCAPACITY_PERIOD_TAX_ALLOCATION
             *
             * El desglose fiscal se distribuye con la misma
             * lógica acumulativa usada para el importe total.
             */
            $row['period_taxable_amount'] = null;
            $row['period_exempt_amount'] = null;

            if ($effect === 'perception') {
                $totalTaxable = round(
                    (float) (
                        $row['taxable_amount']
                        ?? 0
                    ),
                    2
                );

                $totalExempt = round(
                    (float) (
                        $row['exempt_amount']
                        ?? 0
                    ),
                    2
                );

                /*
                 * BEXIA_V5836_INCAPACITY_COUPLED_CENT_ALLOCATION
                 *
                 * No distribuir gravado y exento de forma
                 * independiente por días.
                 *
                 * Primero distribuimos el importe monetario.
                 * Después distribuimos los centavos gravados
                 * dentro de los centavos monetarios acumulados.
                 * El exento es siempre la diferencia.
                 *
                 * Garantiza:
                 *
                 * gravado_periodo + exento_periodo
                 * = monetario_periodo
                 *
                 * y conserva exactamente los totales al finalizar
                 * toda la incapacidad.
                 */
                $totalAmountCents = (int) round(
                    $totalAmount * 100
                );

                $totalTaxableCents = (int) round(
                    $totalTaxable * 100
                );

                $totalExemptCents = (int) round(
                    $totalExempt * 100
                );

                if (
                    $totalTaxableCents < 0
                    || $totalExemptCents < 0
                    || (
                        $totalTaxableCents
                        + $totalExemptCents
                    ) !== $totalAmountCents
                ) {
                    throw new \RuntimeException(
                        'La incapacidad '
                        .($row['incident_id'] ?? 'N/D')
                        .' tiene un desglose fiscal total '
                        .'inconsistente.'
                    );
                }

                $monetaryBeforeCents = (int) round(
                    $cumulativeBefore * 100
                );

                $monetaryThroughCents = (int) round(
                    $cumulativeThrough * 100
                );

                if ($totalAmountCents <= 0) {
                    throw new \RuntimeException(
                        'La incapacidad '
                        .($row['incident_id'] ?? 'N/D')
                        .' no tiene importe monetario válido '
                        .'para distribuir su desglose fiscal.'
                    );
                }

                /*
                 * El gravado acumulado avanza únicamente conforme
                 * avanzan los centavos monetarios acumulados.
                 */
                $taxableBeforeCents = (int) round(
                    $totalTaxableCents
                        * $monetaryBeforeCents
                        / $totalAmountCents
                );

                $taxableThroughCents = (int) round(
                    $totalTaxableCents
                        * $monetaryThroughCents
                        / $totalAmountCents
                );

                /*
                 * Defensa explícita del último tramo:
                 * al llegar al total monetario debemos recuperar
                 * exactamente el gravado total.
                 */
                if (
                    $monetaryThroughCents
                    === $totalAmountCents
                ) {
                    $taxableThroughCents =
                        $totalTaxableCents;
                }

                $periodAmountCents =
                    $monetaryThroughCents
                    - $monetaryBeforeCents;

                $periodTaxableCents =
                    $taxableThroughCents
                    - $taxableBeforeCents;

                if (
                    $periodTaxableCents < 0
                    || $periodTaxableCents
                        > $periodAmountCents
                ) {
                    throw new \RuntimeException(
                        'La incapacidad '
                        .($row['incident_id'] ?? 'N/D')
                        .' produjo un reparto gravado inválido.'
                    );
                }

                $periodExemptCents =
                    $periodAmountCents
                    - $periodTaxableCents;

                $periodTaxable =
                    $periodTaxableCents / 100;

                $periodExempt =
                    $periodExemptCents / 100;

                if (
                    abs(
                        round(
                            $periodTaxable
                                + $periodExempt,
                            2
                        )
                        - $periodAmount
                    ) > 0.009
                ) {
                    throw new \RuntimeException(
                        'La incapacidad '
                        .($row['incident_id'] ?? 'N/D')
                        .' produjo un desglose fiscal de periodo '
                        .'distinto del importe monetario.'
                    );
                }

                $row['period_taxable_amount'] =
                    $periodTaxable;

                $row['period_exempt_amount'] =
                    $periodExempt;

                $row['sat_payroll_key'] = '014';

                $perceptions = round(
                    $perceptions
                        + $periodAmount,
                    2
                );
            }

            if ($effect === 'deduction') {
                $row['sat_payroll_key'] = '006';

                $deductions = round(
                    $deductions
                        + $periodAmount,
                    2
                );
            }

            $items[] = $row;
        }

        return [
            'perceptions' => $perceptions,
            'deductions' => $deductions,
            'items' => $items,
        ];
    }


    protected static function manualUnpaidIncidentDays(
        $incidents
    ): array {
        $days = 0.0;
        $incidentIds = [];

        foreach ($incidents as $incident) {
            $type = $incident->incidentType;

            $code = strtoupper(
                trim(
                    (string) (
                        $type?->code
                        ?? ''
                    )
                )
            );

            if (
                ! in_array(
                    $code,
                    [
                        'FALTA',
                        'PERMISO_SIN_GOCE',
                    ],
                    true
                )
            ) {
                continue;
            }

            $unit = strtolower(
                trim(
                    (string) (
                        $incident->quantity_unit
                        ?? ''
                    )
                )
            );

            if ($unit !== 'days') {
                continue;
            }

            $quantity =
                $incident->quantity !== null
                    ? max(
                        0.0,
                        (float) $incident->quantity
                    )
                    : 1.0;

            if ($quantity <= 0) {
                continue;
            }

            $days += $quantity;

            if (! empty($incident->id)) {
                $incidentIds[] =
                    (int) $incident->id;
            }
        }

        return [
            'days' => round($days, 2),
            'incident_ids' =>
                array_values(
                    array_unique($incidentIds)
                ),
        ];
    }


    protected static function incidentAmounts($incidents, array $salary): array
    {
        $perceptions = 0.0;
        $deductions = 0.0;
        $deductionDays = 0.0;
        $deductionMinutes = 0;

        foreach ($incidents as $incident) {
            $type = $incident->incidentType;
            $effect = (string) ($type?->effect ?? 'informational');
            $code = strtoupper((string) ($type?->code ?? ''));
            $quantity = (float) ($incident->quantity ?? 1);
            $unit = (string) ($incident->quantity_unit ?? 'units');

            $amount = $incident->payroll_amount !== null
                ? (float) $incident->payroll_amount
                : static::estimatedIncidentAmount($code, $quantity, $unit, $salary);

            if ($effect === 'perception') {
                $perceptions += $amount;
                continue;
            }

            if ($effect === 'deduction') {
                $deductions += $amount;

                if ($unit === 'days') {
                    $deductionDays += $quantity;
                }

                if ($unit === 'minutes') {
                    $deductionMinutes += (int) round($quantity);
                }
            }
        }

        return [
            'perceptions' => round($perceptions, 2),
            'deductions' => round($deductions, 2),
            'deduction_days' => round($deductionDays, 2),
            'deduction_minutes' => $deductionMinutes,
        ];
    }

    protected static function estimatedIncidentAmount(string $code, float $quantity, string $unit, array $salary): float
    {
        if ($code === 'FALTA' || $unit === 'days') {
            return round($salary['daily_salary'] * max(1, $quantity), 2);
        }

        if ($code === 'RETARDO' || $unit === 'minutes') {
            return round(($salary['hourly_rate'] / 60) * max(1, $quantity), 2);
        }

        return round(max(0, $quantity), 2);
    }
}
