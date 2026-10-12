<?php

namespace App\Support\PayrollTax;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PayrollFiscalPreviewService
{
    public static function previewEmployee(
        PayrollRun $run,
        Employee $employee
    ): array {
        if (! $run->calculatesFiscalTaxes()) {
            throw new \RuntimeException(
                'La corrida no está configurada '
                . 'para cálculo fiscal.'
            );
        }

        if ((string) $run->run_type !== 'ordinary') {
            throw new \RuntimeException(
                'En esta fase sólo se permite preview '
                . 'de nómina ordinaria.'
            );
        }

        if (! $run->payment_date) {
            throw new \RuntimeException(
                'La corrida no tiene fecha de pago.'
            );
        }

        $line = PayrollRunLine::query()
            ->where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $taxBase =
            PayrollTaxableBaseService::calculateForLine(
                $line
            );

        if (
            (int) $taxBase[
                'unclassified_perceptions_count'
            ] > 0
        ) {
            throw new \RuntimeException(
                'Existen percepciones sin clasificación fiscal: '
                . implode(
                    ', ',
                    $taxBase[
                        'unclassified_perception_codes'
                    ]
                )
            );
        }

        /*
         * FISCAL_WAGE_REDUCTION_SAT_GUARD
         *
         * Toda deducción que reduzca la base fiscal debe tener
         * clasificación SAT explícita.
         *
         * Evita disminuir ISR mediante conceptos internos que
         * después no puedan explicarse en el CFDI de nómina.
         */
        $wageReductionConceptIds =
            collect(
                $taxBase[
                    'wage_reduction_concept_ids'
                ] ?? []
            )
                ->map(
                    fn ($id): int => (int) $id
                )
                ->filter()
                ->values();

        if ($wageReductionConceptIds->isNotEmpty()) {
            $unmappedReductions =
                \App\Models\PayrollRunLineConcept::query()
                    ->whereIn(
                        'id',
                        $wageReductionConceptIds
                    )
                    ->where(function ($query): void {
                        $query
                            ->whereNull('sat_key')
                            ->orWhere(
                                'sat_key',
                                ''
                            );
                    })
                    ->pluck('code')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

            if ($unmappedReductions !== []) {
                throw new \RuntimeException(
                    'Existen reducciones de base gravable '
                    . 'sin clave SAT: '
                    . implode(
                        ', ',
                        $unmappedReductions
                    )
                );
            }
        }

        $taxable = round(
            (float) $taxBase['taxable_income'],
            2
        );

        $exempt = round(
            (float) $taxBase['exempt_income'],
            2
        );

        if ($taxable <= 0) {
            throw new \RuntimeException(
                'La línea no tiene percepciones '
                . 'gravadas configuradas.'
            );
        }

        $periodType = match (
            (string) $run->period_type
        ) {
            'semanal' => 'weekly',
            'quincenal' => 'biweekly',
            'mensual' => 'monthly',

            'catorcenal' => throw new \RuntimeException(
                'La periodicidad catorcenal todavía no '
                . 'está habilitada para cálculo fiscal.'
            ),

            default => throw new \RuntimeException(
                'Periodicidad fiscal no soportada: '
                . (string) $run->period_type
            ),
        };

        $days = match ($periodType) {
            'weekly' => 7,
            'biweekly' => 15,
            'monthly' => 30,
            'fourteen_day' => 14,
        };

        $paymentDate = CarbonImmutable::parse(
            $run->payment_date
        );

        $isr = PayrollIsrCalculator::calculate(
            $taxable,
            $periodType,
            $paymentDate
        );

        $prior =
            PayrollFiscalAccumulatorService
                ::priorMonthValues(
                    (int) $run->company_id,
                    (int) $employee->id,
                    $paymentDate,
                    (int) $run->id
                );

        $monthTaxableIncludingCurrent = round(
            (float) $prior['taxable_income']
            + $taxable,
            2
        );

        /*
         * IMPORTANTE:
         *
         * Este servicio calcula la proyección fiscal.
         * La capa superior decide si el resultado se guarda
         * como auditoría preview o como cálculo fiscal CFDI.
         *
         * La elegibilidad final del subsidio se valida
         * contra el ingreso mensual acumulado conocido.
         */
        $subsidy =
            PayrollEmploymentSubsidyCalculator::calculate(
                $isr['isr_before_subsidy'],
                $monthTaxableIncludingCurrent,
                $days,
                $paymentDate,
                (float) $prior['subsidy_applied']
            );

        return [
            'payroll_run_id' => (int) $run->id,
            'employee_id' => (int) $employee->id,

            'period_type' => $periodType,
            'period_days' => $days,

            'taxable_income' => $taxable,
            'exempt_income' => $exempt,

            'taxable_before_wage_reduction' =>
                round(
                    (float) $taxBase[
                        'taxable_before_wage_reduction'
                    ],
                    2
                ),

            'wage_reduction_amount' =>
                round(
                    (float) $taxBase[
                        'wage_reduction_amount'
                    ],
                    2
                ),

            'wage_reduction_codes' =>
                $taxBase['wage_reduction_codes'],

            'prior_month_taxable_income' =>
                round(
                    (float) $prior['taxable_income'],
                    2
                ),

            'month_taxable_including_current' =>
                $monthTaxableIncludingCurrent,

            'isr_before_subsidy' =>
                round(
                    (float) $isr['isr_before_subsidy'],
                    2
                ),

            /*
             * BEXIA_V5836_PRIOR_MONTH_SUBSIDY_CAUSED
             */
            'prior_month_subsidy_caused' =>
                round(
                    (float) (
                        $prior['subsidy_caused']
                        ?? 0
                    ),
                    2
                ),

            'prior_month_subsidy_applied' =>
                round(
                    (float) $prior['subsidy_applied'],
                    2
                ),

            'monthly_subsidy_remaining' =>
                round(
                    (float) $subsidy[
                        'monthly_subsidy_remaining'
                    ],
                    2
                ),

            /*
             * BEXIA_V5836_PREVIEW_SUBSIDY_CAUSED
             */
            'subsidy_caused' =>
                round(
                    (float) $subsidy['subsidy_caused'],
                    2
                ),

            'subsidy_applied' =>
                round(
                    (float) $subsidy['subsidy_applied'],
                    2
                ),

            'isr_withheld_preview' =>
                round(
                    (float) $subsidy['isr_after_subsidy'],
                    2
                ),

            'isr_tariff_code' =>
                $isr['tariff_code'],

            'subsidy_rule_code' =>
                $subsidy['rule_code'],

            'subsidy_eligible' =>
                (bool) $subsidy['eligible'],

            'persisted' => false,
        ];
    }
}
