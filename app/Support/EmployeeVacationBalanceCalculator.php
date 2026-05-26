<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeVacationBalance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeVacationBalanceCalculator
{
    public const POLICY_MX_LFT_2023 = 'MX_LFT_2023';
    public const VACATION_INCIDENT_CODE = 'VACACIONES';

    public static function vacationDaysForYears(int $yearsOfService): float
    {
        $yearsOfService = max(0, (int) $yearsOfService);

        if ($yearsOfService <= 0) {
            return 0.0;
        }

        if ($yearsOfService <= 5) {
            return (float) (10 + ($yearsOfService * 2));
        }

        return (float) (20 + (ceil(($yearsOfService - 5) / 5) * 2));
    }

    public static function currentPeriod(Employee $employee, ?CarbonImmutable $asOf = null): ?array
    {
        if (blank($employee->hire_date)) {
            return null;
        }

        $asOf = $asOf ?: CarbonImmutable::today();
        $hireDate = CarbonImmutable::parse($employee->hire_date)->startOfDay();

        if ($hireDate->greaterThan($asOf)) {
            return null;
        }

        $years = max(0, (int) floor($hireDate->diffInYears($asOf)));

        $periodStart = $hireDate->addYears($years);
        $periodEnd = $periodStart->addYear()->subDay();

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'years_of_service' => $years,
            'entitled_days' => self::vacationDaysForYears($years),
        ];
    }

    public static function calculateTakenDays(Employee $employee, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): float
    {
        if (! Schema::hasTable('employee_incidents') || ! Schema::hasTable('hr_incident_types')) {
            return 0.0;
        }

        $incidents = DB::table('employee_incidents as incidents')
            ->join('hr_incident_types as types', 'types.id', '=', 'incidents.hr_incident_type_id')
            ->where('incidents.company_id', $employee->company_id)
            ->where('incidents.employee_id', $employee->id)
            ->where('incidents.status', 'approved')
            ->where('types.code', self::VACATION_INCIDENT_CODE)
            ->whereDate('incidents.start_date', '<=', $periodEnd->toDateString())
            ->where(function ($query) use ($periodStart): void {
                $query
                    ->whereNull('incidents.end_date')
                    ->orWhereDate('incidents.end_date', '>=', $periodStart->toDateString());
            })
            ->select([
                'incidents.start_date',
                'incidents.end_date',
                'incidents.quantity',
                'incidents.quantity_unit',
            ])
            ->get();

        return round((float) $incidents->sum(function ($incident): float {
            if ($incident->quantity !== null && $incident->quantity_unit === 'days') {
                return (float) $incident->quantity;
            }

            $start = CarbonImmutable::parse($incident->start_date);
            $end = $incident->end_date
                ? CarbonImmutable::parse($incident->end_date)
                : $start;

            return (float) ($start->diffInDays($end) + 1);
        }), 2);
    }

    public static function generateCurrentBalance(Employee $employee, ?int $userId = null): EmployeeVacationBalance
    {
        $period = static::currentPeriod($employee);

        if (! $period) {
            throw new \RuntimeException('El empleado no tiene fecha de ingreso válida para calcular vacaciones.');
        }

        $periodStart = $period['period_start'];
        $periodEnd = $period['period_end'];

        $takenDays = static::calculateTakenDays($employee, $periodStart, $periodEnd);

        $existing = EmployeeVacationBalance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereDate('period_end', $periodEnd->toDateString())
            ->first();

        $carriedOverDays = (float) ($existing?->carried_over_days ?? 0);
        $adjustedDays = (float) ($existing?->adjusted_days ?? 0);
        $expiredDays = (float) ($existing?->expired_days ?? 0);
        $entitledDays = (float) $period['entitled_days'];

        $pendingDays = max(0, round($entitledDays + $carriedOverDays + $adjustedDays - $takenDays - $expiredDays, 2));

        $payload = [
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'years_of_service' => (int) $period['years_of_service'],
            'entitled_days' => $entitledDays,
            'carried_over_days' => $carriedOverDays,
            'adjusted_days' => $adjustedDays,
            'taken_days' => $takenDays,
            'pending_days' => $pendingDays,
            'expired_days' => $expiredDays,
            'policy_code' => self::POLICY_MX_LFT_2023,
            'status' => 'open',
            'notes' => $existing?->notes,
            'updated_by_user_id' => $userId ?: auth()->id(),
        ];

        if (! $existing) {
            $payload['created_by_user_id'] = $userId ?: auth()->id();
        }

        return EmployeeVacationBalance::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ],
            $payload
        );
    }
}
