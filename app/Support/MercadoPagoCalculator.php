<?php

namespace App\Support;

use App\Models\Company;
use App\Models\SalesPriceList;
use Illuminate\Support\Collection;

class MercadoPagoCalculator
{
    public const MODE_RECEIVE =
        'receive';

    public const MODE_CHARGE =
        'charge';

    /*
     * Tasas base de deslizamiento vigentes.
     *
     * El financiamiento de cada plazo
     * se almacena por separado.
     */
    public const CREDIT_SWIPE =
        2.1900;

    public const DEBIT_SWIPE =
        1.6900;

    public function terms(
        Company $company
    ): Collection {
        $today =
            now()->toDateString();

        $configured =
            SalesPriceList::query()
                ->where(
                    'company_id',
                    $company->id
                )
                ->where(
                    'is_active',
                    true
                )
                ->where(
                    'public_calculator',
                    true
                )
                ->where(
                    'payment_provider',
                    'mercado_pago'
                )
                ->where(
                    'calculation_type',
                    'formula'
                )
                ->where(
                    'formula_basis',
                    'price_list'
                )
                ->whereNotNull(
                    'installment_months'
                )
                ->whereNotNull(
                    'financing_percent'
                )
                ->where(
                    function (
                        $query
                    ) use ($today): void {
                        $query
                            ->whereNull(
                                'valid_from'
                            )
                            ->orWhereDate(
                                'valid_from',
                                '<=',
                                $today
                            );
                    }
                )
                ->where(
                    function (
                        $query
                    ) use ($today): void {
                        $query
                            ->whereNull(
                                'valid_to'
                            )
                            ->orWhereDate(
                                'valid_to',
                                '>=',
                                $today
                            );
                    }
                )
                ->orderByRaw(
                    'COALESCE(' .
                    'public_sort, ' .
                    'installment_months' .
                    ') ASC'
                )
                ->orderBy(
                    'installment_months'
                )
                ->get([
                    'id',
                    'code',
                    'name',
                    'installment_months',
                    'financing_percent',
                ])
                ->map(
                    fn (
                        SalesPriceList $plan
                    ): array => [
                        'id' =>
                            (int) $plan->id,

                        'code' =>
                            (string) $plan->code,

                        'months' =>
                            (int)
                            $plan->installment_months,

                        'financing' =>
                            round(
                                (float)
                                $plan->financing_percent,
                                4
                            ),
                    ]
                )
                ->values();

        /*
         * BEXIA_MP_SCOPE_GUARD_V5_83_2A3
         *
         * No publicar siquiera el pago único
         * si la empresa no tiene planes
         * Mercado Pago configurados.
         */
        if ($configured->isEmpty()) {
            return collect();
        }

        /*
         * 1 pago no necesita una lista adicional:
         * no tiene financiamiento.
         */
        return collect([
            [
                'id' => null,
                'code' => 'ONE_PAYMENT',
                'months' => 1,
                'financing' => 0.0000,
            ],
        ])
            ->concat($configured)
            ->values();
    }

    public function normalizeMode(
        ?string $mode
    ): string {
        return in_array(
            $mode,
            [
                self::MODE_RECEIVE,
                self::MODE_CHARGE,
            ],
            true
        )
            ? $mode
            : self::MODE_RECEIVE;
    }

    public function calculate(
        Collection $terms,
        float $amount,
        string $mode
    ): Collection {
        $mode =
            $this->normalizeMode(
                $mode
            );

        return $terms
            ->map(
                function (
                    array $term
                ) use (
                    $amount,
                    $mode
                ): array {
                    return [
                        'months' =>
                            $term['months'],

                        'financing' =>
                            $term['financing'],

                        'credit' =>
                            $this->cardResult(
                                $amount,
                                $term['months'],
                                $term['financing'],
                                self::CREDIT_SWIPE,
                                $mode
                            ),

                        'debit' =>
                            $this->cardResult(
                                $amount,
                                $term['months'],
                                $term['financing'],
                                self::DEBIT_SWIPE,
                                $mode
                            ),
                    ];
                }
            )
            ->values();
    }

    protected function cardResult(
        float $amount,
        int $months,
        float $financing,
        float $swipe,
        string $mode
    ): array {
        $totalRate =
            round(
                $financing + $swipe,
                4
            );

        $factor =
            1 - ($totalRate / 100);

        if ($factor <= 0) {
            throw new \RuntimeException(
                'La comisión total debe ser menor a 100%.'
            );
        }

        if (
            $mode ===
            self::MODE_RECEIVE
        ) {
            $received =
                $amount;

            $charged =
                $amount / $factor;
        } else {
            $charged =
                $amount;

            $received =
                $amount * $factor;
        }

        $chargedRounded =
            round(
                $charged,
                2
            );

        $receivedRounded =
            round(
                $received,
                2
            );

        $swipeAmount =
            round(
                $charged * ($swipe / 100),
                2
            );

        $financingAmount =
            round(
                $charged * ($financing / 100),
                2
            );

        $feeAmount =
            round(
                $chargedRounded - $receivedRounded,
                2
            );

        return [
            'swipe' =>
                round($swipe, 4),

            'financing' =>
                round(
                    $financing,
                    4
                ),

            'rate' =>
                $totalRate,

            'charged' =>
                $chargedRounded,

            'received' =>
                $receivedRounded,

            'payment' =>
                round(
                    $charged / $months,
                    2
                ),

            'swipe_amount' =>
                $swipeAmount,

            'financing_amount' =>
                $financingAmount,

            'fee_amount' =>
                $feeAmount,
        ];
    }
}
