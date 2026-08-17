<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\SalesPriceList;
use App\Support\PublicPageAnalytics;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicMercadoPagoCalculatorController extends Controller
{
    public function __invoke(Request $request, string $companySlug): View
    {
        $company = Company::query()
            ->where('active', true)
            ->whereRaw('LOWER(slug) = ?', [Str::lower($companySlug)])
            ->firstOrFail();

        $today = now()->toDateString();

        $plans = SalesPriceList::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->where('public_calculator', true)
            ->where('payment_provider', 'mercado_pago')
            ->where('calculation_type', 'formula')
            ->where('formula_basis', 'price_list')
            ->whereNotNull('installment_months')
            ->whereNotNull('adjustment_percent')
            ->where(function ($query) use ($today): void {
                $query
                    ->whereNull('valid_from')
                    ->orWhereDate('valid_from', '<=', $today);
            })
            ->where(function ($query) use ($today): void {
                $query
                    ->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $today);
            })
            ->orderByRaw(
                'COALESCE(public_sort, installment_months) ASC'
            )
            ->orderBy('installment_months')
            ->get([
                'installment_months',
                'adjustment_percent',
            ])
            ->map(fn (SalesPriceList $plan): array => [
                'months' => (int) $plan->installment_months,
                'rate' => (float) $plan->adjustment_percent,
            ])
            ->values();

        /*
         * BEXIA_PUBLIC_ANALYTICS_V5_83_1A
         * Solo cuenta calculadoras realmente configuradas.
         */
        if ($plans->isNotEmpty()) {
            app(PublicPageAnalytics::class)
                ->recordView(
                    (int) $company->id,
                    PublicPageAnalytics::MERCADO_PAGO_CALCULATOR,
                    $request
                );
        }

        /*
         * BEXIA_PUBLIC_COUNTER_V5_83_1B
         * Resumen visible en la pagina publica.
         */
        $publicStats = $plans->isNotEmpty()
            ? app(PublicPageAnalytics::class)->summary(
                (int) $company->id,
                PublicPageAnalytics::MERCADO_PAGO_CALCULATOR
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

        $initialAmount = 0.0;

        if ($request->filled('monto') && is_numeric($request->query('monto'))) {
            $initialAmount = max(
                0,
                min(5000000, (float) $request->query('monto'))
            );
        }

        return view('public.mercado-pago-calculator', [
            'company' => $company,
            'plans' => $plans,
            'initialAmount' => $initialAmount,
            'publicStats' => $publicStats,
        ]);
    }
}
