<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeContract;
use App\Models\EmployeeIncident;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
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
                $line = static::calculateEmployee($run, $employee);
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

    public static function calculateEmployee(PayrollRun $run, Employee $employee): PayrollRunLine
    {
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

        $incidents = static::approvedPayrollIncidents($run, $employee);
        $incidentAmounts = static::incidentAmounts($incidents, $salary);

        $baseAmount = static::baseAmount($salary, $periodDays, $attendanceSummary);
        $overtimeAmount = round($salary['hourly_rate'] * 2 * $attendanceSummary['overtime_hours'], 2);

        $grossAmount = round($baseAmount + $overtimeAmount + $incidentAmounts['perceptions'], 2);
        $deductionsAmount = round($incidentAmounts['deductions'], 2);
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
                'formula' => [
                    'base_amount' => 'salary basis by salary_type',
                    'overtime_amount' => 'hourly_rate * 2 * overtime_hours',
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
