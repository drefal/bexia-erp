<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollIsrTariff extends Model
{
    protected $fillable = [
        'code',
        'name',
        'fiscal_year',
        'period_type',
        'period_days',
        'valid_from',
        'valid_to',
        'source_reference',
        'is_active',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'period_days' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_active' => 'boolean',
    ];

    /*
     * BEXIA_V5836_ISR_TARIFF_OVERLAP_GUARD
     *
     * No permitir dos tarifas activas de la misma periodicidad
     * cuya vigencia se traslape.
     */
    protected static function booted(): void
    {
        static::saving(function (self $tariff): void {
            if (! $tariff->is_active) {
                return;
            }

            if (
                ! $tariff->fiscal_year
                || ! $tariff->period_type
                || ! $tariff->valid_from
            ) {
                return;
            }

            $from = \Carbon\CarbonImmutable::parse(
                $tariff->valid_from
            )->toDateString();

            $to = $tariff->valid_to
                ? \Carbon\CarbonImmutable::parse(
                    $tariff->valid_to
                )->toDateString()
                : null;

            if ($to !== null && $to < $from) {
                throw new \RuntimeException(
                    'La vigencia final ISR no puede ser '
                    . 'anterior a la inicial.'
                );
            }

            $query = self::query()
                ->where(
                    'fiscal_year',
                    (int) $tariff->fiscal_year
                )
                ->where(
                    'period_type',
                    (string) $tariff->period_type
                )
                ->where('is_active', true)
                ->when(
                    $tariff->exists,
                    fn ($q) =>
                        $q->whereKeyNot($tariff->getKey())
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
                    'Ya existe una tarifa ISR activa de la misma '
                    . 'periodicidad cuya vigencia se traslapa.'
                );
            }
        });
    }


    /*
     * BEXIA_V5836_ISR_HISTORICAL_USE_GUARD
     *
     * Una tarifa que ya fue usada por un resultado fiscal debe
     * considerarse histórica y no editarse.
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
                'isr_tariff_code'
            )
        ) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table(
            'payroll_fiscal_run_results'
        )
            ->where(
                'isr_tariff_code',
                (string) $this->code
            )
            ->exists();
    }


    public function brackets(): HasMany
    {
        return $this->hasMany(
            PayrollIsrTariffBracket::class
        )->orderBy('sort_order');
    }
}
