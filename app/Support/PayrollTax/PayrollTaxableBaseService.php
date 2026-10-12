<?php

namespace App\Support\PayrollTax;

use App\Models\PayrollRunLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollTaxableBaseService
{
    /**
     * Calcula la remuneración gravable efectivamente pagada.
     *
     * IMPORTANTE:
     * - NO resta todas las deducciones.
     * - Sólo resta conceptos de asistencia que representan
     *   reducción de sueldo por tiempo no pagado.
     * - Préstamos, compras, anticipos, ISR, etc. no disminuyen
     *   el ingreso gravable.
     */
    public static function calculateForLine(
        PayrollRunLine $line
    ): array {
        $concepts = DB::table(
            'payroll_run_line_concepts'
        )
            ->where(
                'payroll_run_line_id',
                $line->id
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return static::calculateFromConcepts(
            $concepts
        );
    }

    public static function calculateFromConcepts(
        Collection $concepts
    ): array {
        $perceptions = $concepts->where(
            'type',
            'perception'
        );

        /*
         * Si existe metadata fiscal explícita, usamos esa
         * clasificación.
         *
         * Para conceptos todavía no configurados fiscalmente,
         * no inventamos gravabilidad.
         */
        $explicitTaxable = round(
            (float) $perceptions->sum(
                fn ($row): float =>
                    $row->taxable_amount !== null
                        ? (float) $row->taxable_amount
                        : 0.0
            ),
            2
        );

        $explicitExempt = round(
            (float) $perceptions->sum(
                fn ($row): float =>
                    $row->exempt_amount !== null
                        ? (float) $row->exempt_amount
                        : 0.0
            ),
            2
        );

        $unclassifiedPerceptions = $perceptions
            ->filter(
                fn ($row): bool =>
                    $row->taxable_amount === null
                    && $row->exempt_amount === null
            )
            ->values();

        /*
         * La semántica fiscal vive en el catálogo y se congela
         * en payroll_run_line_concepts al calcular la corrida.
         *
         * No dependemos de códigos específicos.
         */
        $wageReductions = $concepts
            ->filter(
                fn ($row): bool =>
                    (string) $row->type === 'deduction'
                    && (bool) (
                        $row->reduces_taxable_base
                        ?? false
                    )
            );

        $wageReductionAmount = round(
            (float) $wageReductions->sum('amount'),
            2
        );

        $taxableAfterWageReduction = round(
            max(
                0,
                $explicitTaxable
                - $wageReductionAmount
            ),
            2
        );

        return [
            'taxable_before_wage_reduction' =>
                $explicitTaxable,

            'wage_reduction_amount' =>
                $wageReductionAmount,

            'taxable_income' =>
                $taxableAfterWageReduction,

            'exempt_income' =>
                $explicitExempt,

            'unclassified_perceptions_count' =>
                $unclassifiedPerceptions->count(),

            'unclassified_perception_codes' =>
                $unclassifiedPerceptions
                    ->pluck('code')
                    ->values()
                    ->all(),

            'wage_reduction_codes' =>
                $wageReductions
                    ->pluck('code')
                    ->values()
                    ->all(),

            'wage_reduction_concept_ids' =>
                $wageReductions
                    ->pluck('id')
                    ->map(
                        fn ($id): int => (int) $id
                    )
                    ->values()
                    ->all(),
        ];
    }
}
