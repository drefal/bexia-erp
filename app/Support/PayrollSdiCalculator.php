<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\PayrollPolicy;
use Carbon\CarbonImmutable;

class PayrollSdiCalculator
{
    public const DEFAULT_AGUINALDO_DAYS = 15.0;

    public const DEFAULT_VACATION_PREMIUM_PERCENT = 25.0;

    public static function calculate(
        Employee $employee,
        float $dailySalary,
        ?int $companyId = null,
        ?CarbonImmutable $asOf = null
    ): array {
        $companyId = $companyId ?: (int) $employee->company_id;
        $asOf = $asOf ?: CarbonImmutable::today();

        $policy = PayrollPolicy::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        $settings = is_array($policy?->settings)
            ? $policy->settings
            : [];

        $aguinaldoDays = (float) ($settings['aguinaldo_days'] ?? self::DEFAULT_AGUINALDO_DAYS);

        $vacationPremiumPercent = (float) (
            $settings['vacation_premium_percent']
            ?? self::DEFAULT_VACATION_PREMIUM_PERCENT
        );

        $autoSdi = array_key_exists('automatic_sdi', $settings)
            ? (bool) $settings['automatic_sdi']
            : true;

        if (blank($employee->hire_date)) {
            return [
                'automatic_sdi' => $autoSdi,
                'can_calculate' => false,
                'reason' => 'missing_hire_date',
                'daily_salary' => round($dailySalary, 4),
                'years_of_service' => null,
                'service_year' => null,
                'vacation_days' => null,
                'aguinaldo_days' => $aguinaldoDays,
                'vacation_premium_percent' => $vacationPremiumPercent,
                'integration_factor' => null,
                'integrated_daily_salary' => null,
                'policy_id' => $policy?->id,
            ];
        }

        $hireDate = CarbonImmutable::parse($employee->hire_date)->startOfDay();

        if ($hireDate->greaterThan($asOf)) {
            return [
                'automatic_sdi' => $autoSdi,
                'can_calculate' => false,
                'reason' => 'hire_date_in_future',
                'daily_salary' => round($dailySalary, 4),
                'years_of_service' => null,
                'service_year' => null,
                'vacation_days' => null,
                'aguinaldo_days' => $aguinaldoDays,
                'vacation_premium_percent' => $vacationPremiumPercent,
                'integration_factor' => null,
                'integrated_daily_salary' => null,
                'policy_id' => $policy?->id,
            ];
        }

        $yearsOfService = max(
            0,
            (int) floor($hireDate->diffInYears($asOf))
        );

        /*
         * Para factor de integración usamos las vacaciones que corresponden
         * al año de servicio en curso:
         *
         * recién ingresado / primer año -> 12 días
         * segundo año -> 14
         * tercero -> 16
         * etc.
         */
        $serviceYear = max(1, $yearsOfService + 1);

        $vacationDays = EmployeeVacationBalanceCalculator::vacationDaysForYears(
            $serviceYear
        );

        $vacationPremiumRate = $vacationPremiumPercent / 100;

        $integrationFactor =
            1
            + ($aguinaldoDays / 365)
            + (($vacationDays * $vacationPremiumRate) / 365);

        $integratedDailySalary = round(
            max(0, $dailySalary) * $integrationFactor,
            4
        );

        return [
            'automatic_sdi' => $autoSdi,
            'can_calculate' => true,
            'reason' => null,
            'daily_salary' => round($dailySalary, 4),
            'years_of_service' => $yearsOfService,
            'service_year' => $serviceYear,
            'vacation_days' => $vacationDays,
            'aguinaldo_days' => $aguinaldoDays,
            'vacation_premium_percent' => $vacationPremiumPercent,
            'integration_factor' => round($integrationFactor, 6),
            'integrated_daily_salary' => $integratedDailySalary,
            'policy_id' => $policy?->id,
        ];
    }
}
