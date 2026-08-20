<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Support\MercadoPagoCalculator;
use App\Support\PublicPageAnalytics;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicMercadoPagoCalculatorController extends Controller
{
    public function __invoke(
        Request $request,
        string $companySlug
    ): View {
        $company =
            Company::query()
                ->where(
                    'active',
                    true
                )
                ->whereRaw(
                    'LOWER(slug) = ?',
                    [
                        Str::lower(
                            $companySlug
                        ),
                    ]
                )
                ->firstOrFail();

        $calculator =
            app(
                MercadoPagoCalculator::class
            );

        $terms =
            $calculator->terms(
                $company
            );

        /*
         * BEXIA_MP_PUBLIC_SCOPE_GUARD_V5_83_2A3
         *
         * Una empresa sin planes Mercado Pago
         * no debe exponer la calculadora.
         */
        abort_if(
            $terms->isEmpty(),
            404,
            'No hay planes de pago publicados.'
        );

        /*
         * La colección siempre incluye
         * 1 pago sintético.
         *
         * Sólo consideramos publicada
         * la calculadora si existen
         * planes financiados reales.
         */
        $hasConfiguredPlans =
            $terms
                ->where(
                    'months',
                    '>',
                    1
                )
                ->isNotEmpty();

        if ($hasConfiguredPlans) {
            app(
                PublicPageAnalytics::class
            )->recordView(
                (int) $company->id,
                PublicPageAnalytics::
                    MERCADO_PAGO_CALCULATOR,
                $request
            );
        }

        $publicStats =
            $hasConfiguredPlans
                ? app(
                    PublicPageAnalytics::class
                )->summary(
                    (int) $company->id,
                    PublicPageAnalytics::
                        MERCADO_PAGO_CALCULATOR
                )
                : [
                    'today' => [
                        'views' => 0,
                        'unique' => 0,
                        'pdf' => 0,
                    ],
                    'last30' => [
                        'views' => 0,
                        'unique' => 0,
                        'pdf' => 0,
                    ],
                    'all' => [
                        'views' => 0,
                        'unique' => 0,
                        'pdf' => 0,
                    ],
                ];

        $initialAmount =
            0.0;

        if (
            $request->filled('monto')
            &&
            is_numeric(
                $request->query('monto')
            )
        ) {
            $initialAmount =
                max(
                    0,
                    min(
                        5000000,
                        (float)
                        $request->query(
                            'monto'
                        )
                    )
                );
        }

        return view(
            'public.mercado-pago-calculator',
            [
                'company' =>
                    $company,

                'terms' =>
                    $terms,

                'initialAmount' =>
                    $initialAmount,

                'initialMode' =>
                    $calculator
                        ->normalizeMode(
                            $request->query(
                                'modo'
                            )
                        ),

                'creditSwipe' =>
                    MercadoPagoCalculator::
                        CREDIT_SWIPE,

                'debitSwipe' =>
                    MercadoPagoCalculator::
                        DEBIT_SWIPE,

                'publicStats' =>
                    $publicStats,
            ]
        );
    }
}
