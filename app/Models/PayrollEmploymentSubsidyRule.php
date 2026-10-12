<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollEmploymentSubsidyRule extends Model
{
    protected $fillable = [
        'code',
        'name',
        'fiscal_year',
        'valid_from',
        'valid_to',
        'monthly_income_limit',
        'uma_monthly',
        'subsidy_percentage',
        'month_days_factor',
        'source_reference',
        'is_active',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'monthly_income_limit' => 'decimal:6',
        'uma_monthly' => 'decimal:6',
        'subsidy_percentage' => 'decimal:6',
        'month_days_factor' => 'decimal:6',
        'is_active' => 'boolean',
    ];

    /*
     * BEXIA_V5836_SUBSIDY_RULE_OVERLAP_GUARD
     *
     * Sólo puede existir una regla activa de subsidio aplicable
     * a una fecha determinada.
     */
    protected static function booted(): void
    {
        static::saving(function (self $rule): void {
            if (! $rule->is_active) {
                return;
            }

            if (
                ! $rule->fiscal_year
                || ! $rule->valid_from
            ) {
                return;
            }

            $from = \Carbon\CarbonImmutable::parse(
                $rule->valid_from
            )->toDateString();

            $to = $rule->valid_to
                ? \Carbon\CarbonImmutable::parse(
                    $rule->valid_to
                )->toDateString()
                : null;

            if ($to !== null && $to < $from) {
                throw new \RuntimeException(
                    'La vigencia final del subsidio no puede ser '
                    . 'anterior a la inicial.'
                );
            }

            $query = self::query()
                ->where(
                    'fiscal_year',
                    (int) $rule->fiscal_year
                )
                ->where('is_active', true)
                ->when(
                    $rule->exists,
                    fn ($q) =>
                        $q->whereKeyNot($rule->getKey())
                )
                ->whereDate(
                    'valid_from',
                    '<=',
                    $to ?? '9999-12-31'
                )
                ->where(function ($q) use ($from): void {
                    $q
                        ->whereNull('valid_to')
                        ->orWhereDate(
                            'valid_to',
                            '>=',
                            $from
                        );
                });

            if ($query->exists()) {
                throw new \RuntimeException(
                    'Ya existe una regla activa de subsidio '
                    . 'cuya vigencia se traslapa.'
                );
            }
        });
    }


    /*
     * BEXIA_V5836_SUBSIDY_HISTORICAL_USE_GUARD
     *
     * Una regla de subsidio utilizada por un resultado fiscal
     * queda congelada históricamente.
     */
    public function isHistoricallyUsed(): bool
    {
        if (
            ! \Illuminate\Support\Facades\Schema::hasTable(
                'payroll_fiscal_run_results'
            )
        ) {
            return false;
        }

        if (
            ! \Illuminate\Support\Facades\Schema::hasColumn(
                'payroll_fiscal_run_results',
                'subsidy_rule_code'
            )
        ) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table(
            'payroll_fiscal_run_results'
        )
            ->where(
                'subsidy_rule_code',
                (string) $this->code
            )
            ->exists();
    }


    public function monthlyMaximum(): float
    {
        return round(
            (float) $this->uma_monthly
            * ((float) $this->subsidy_percentage / 100),
            2
        );
    }
}
