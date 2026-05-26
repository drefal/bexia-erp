<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeContract;
use App\Models\EmployeeIncident;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\PayrollPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PayrollRunCalculator
{
    public static function calculate(PayrollRun $run, ?int $userId = null): PayrollRun
    {
        if (in_array((string) $run->status, ['approved', 'closed', 'cancelled'], true)) {
            throw new \RuntimeException('Solo se puede recalcular una pre-nómina en borrador o calculada.');
        }

        return DB::transaction(function () use ($run, $userId): PayrollRun {
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

            $totals = [
                'employees_count' => $employees->count(),
                'base_total' => (float) $run->lines()->sum('base_amount'),
                'overtime_total' => (float) $run->lines()->sum('overtime_amount'),
                'perceptions_total' => (float) $run->lines()->sum('incident_perceptions'),
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

        $baseAmount = static::baseAmount($salary, $periodDays, $attendanceSummary);

        $overtimeMultiplier = (float) ($policy['overtime_multiplier'] ?? 2);
        $overtimeAmount = round($salary['hourly_rate'] * $overtimeMultiplier * $attendanceSummary['overtime_hours'], 2);

        $grossAmount = round($baseAmount + $overtimeAmount + $incidentAmounts['perceptions'], 2);
        $deductionsAmount = round($incidentAmounts['deductions'] + $policyDeductions['amount'], 2);
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
