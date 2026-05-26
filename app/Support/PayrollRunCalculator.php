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

class PayrollRunCalculator
{
    public static function calculate(PayrollRun $run, ?int $userId = null): PayrollRun
    {
        PayrollRunCloseService::ensureCanRecalculate($run);

        if (in_array((string) $run->status, ['pending_approval', 'approved', 'closed', 'cancelled'], true)) {
            throw new \RuntimeException('Solo se puede recalcular una pre-nómina en borrador o calculada.');
        }

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

            return $run->fresh(['lines.employee']);
        });
    }

    protected static function employeesForRun(PayrollRun $run)
    {
        return Employee::query()
            ->where('company_id', $run->company_id)
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

        $contract = static::currentContract($employee, $periodStart, $periodEnd);
        $salary = static::salaryData($employee, $contract);

        $attendances = EmployeeAttendance::query()
            ->where('company_id', $run->company_id)
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', '>=', $periodStart->toDateString())
            ->whereDate('attendance_date', '<=', $periodEnd->toDateString())
            ->get();

        $attendanceSummary = [
            'attendance_records' => $attendances->count(),
            'worked_minutes' => (int) $attendances->sum(fn ($row) => (int) $row->worked_minutes),
            'worked_hours' => round(((int) $attendances->sum(fn ($row) => (int) $row->worked_minutes)) / 60, 2),
            'late_minutes' => (int) $attendances->sum(fn ($row) => (int) $row->late_minutes),
            'early_leave_minutes' => (int) $attendances->sum(fn ($row) => (int) $row->early_leave_minutes),
            'overtime_minutes' => (int) $attendances->sum(fn ($row) => (int) $row->overtime_minutes),
            'overtime_hours' => round(((int) $attendances->sum(fn ($row) => (int) $row->overtime_minutes)) / 60, 2),
            'absence_days' => (float) $attendances->where('status', 'absence')->count(),
            'rest_day_worked_days' => (float) $attendances->where('status', 'rest_day_worked')->count(),
        ];

        $attendancePolicy = static::applyAttendancePolicy($attendanceSummary, $salary, $policy);
        $attendanceSummary = $attendancePolicy['attendance'];

        $incidents = static::approvedPayrollIncidents($run, $employee);
        $incidentAmounts = static::incidentAmounts($incidents, $salary);
        $policyDeductions = static::attendancePolicyDeductions($attendancePolicy, $salary, $policy);
        $employeeDeductions = static::employeePayrollDeductions($run, $employee);
        $employeePerceptions = static::employeePayrollPerceptions($run, $employee);

        $baseAmount = static::baseAmount($salary, $periodDays, $attendanceSummary);

        $overtimeMultiplier = (float) ($policy['overtime_multiplier'] ?? 2);
        $overtimeAmount = round($salary['hourly_rate'] * $overtimeMultiplier * $attendanceSummary['overtime_hours'], 2);

        $grossAmount = round($baseAmount + $overtimeAmount + $incidentAmounts['perceptions'] + $employeePerceptions['amount'], 2);
        $deductionsAmount = round($incidentAmounts['deductions'] + $policyDeductions['amount'] + $employeeDeductions['amount'], 2);
        $netAmount = round(max(0, $grossAmount - $deductionsAmount), 2);

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
            'payable_days' => $periodDays,

            ...$attendanceSummary,

            'approved_incidents_count' => $incidents->count(),
            'approved_incident_deduction_days' => $incidentAmounts['deduction_days'],
            'approved_incident_deduction_minutes' => $incidentAmounts['deduction_minutes'],

            'base_amount' => $baseAmount,
            'overtime_amount' => $overtimeAmount,
            'incident_perceptions' => $incidentAmounts['perceptions'],
            'incident_deductions' => $incidentAmounts['deductions'],
            'gross_amount' => $grossAmount,
            'deductions_amount' => $deductionsAmount,
            'net_amount' => $netAmount,

            'details' => [
                'contract_id' => $contract?->id,
                'contract_number' => $contract?->contract_number,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'attendance_ids' => $attendances->pluck('id')->values()->all(),
                'approved_incident_ids' => $incidents->pluck('id')->values()->all(),
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
                    'base_amount' => 'salary basis by salary_type',
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
        if (in_array($salary['salary_type'], ['hourly', 'hora', 'por_hora'], true)) {
            return round($salary['hourly_rate'] * $attendanceSummary['worked_hours'], 2);
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
                'sort_order' => $concept?->sort_order ?: match ((string) $deduction->type) {
                    'loan' => 210,
                    'advance' => 220,
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

        EmployeePayrollDeductionApplication::create([
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
            ],
        ]);
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

        if ((float) $line->incident_perceptions !== 0.0) {
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
                amount: (float) $line->incident_perceptions,
                metadata: [
                    'approved_incident_ids' => $details['approved_incident_ids'] ?? [],
                ],
                sortOrder: 30,
            );
        }

        if ((float) $line->incident_deductions !== 0.0) {
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
                amount: (float) $line->incident_deductions,
                metadata: [
                    'approved_incident_ids' => $details['approved_incident_ids'] ?? [],
                    'deduction_days' => (float) $line->approved_incident_deduction_days,
                    'deduction_minutes' => (int) $line->approved_incident_deduction_minutes,
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
                'attendance_absence_days', 'late_accumulated_absence' => ['POLITICA_FALTA', 'Descuento por falta', 'days', 140],
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
            'quantity' => round($quantity, 4),
            'rate' => round($rate, 4),
            'amount' => round($amount, 2),
            'metadata' => $metadata,
            'sort_order' => $concept?->sort_order ?: $sortOrder,
        ];
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

    protected static function approvedPayrollIncidents(PayrollRun $run, Employee $employee)
    {
        return EmployeeIncident::query()
            ->with('incidentType')
            ->where('company_id', $run->company_id)
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('affects_payroll', true)
            ->whereDate('start_date', '<=', $run->period_end)
            ->where(function ($query) use ($run): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $run->period_start);
            })
            ->get();
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
