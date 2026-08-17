<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\SalesPriceList;
use App\Support\PublicPageAnalytics;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicMercadoPagoCalculatorPdfController extends Controller
{
    public function __invoke(
        Request $request,
        string $companySlug
    ): Response {
        if (! app()->bound('dompdf.wrapper')) {
            abort(500, 'No hay motor PDF disponible.');
        }

        $company = Company::query()
            ->where('active', true)
            ->whereRaw(
                'LOWER(slug) = ?',
                [Str::lower($companySlug)]
            )
            ->firstOrFail();

        $amount = $this->validatedAmount($request);

        $today = now()->toDateString();

        $plans = SalesPriceList::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->where('public_calculator', true)
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
            ->whereNotNull('installment_months')
            ->whereNotNull('adjustment_percent')
            ->where(function ($query) use ($today): void {
                $query
                    ->whereNull('valid_from')
                    ->orWhereDate(
                        'valid_from',
                        '<=',
                        $today
                    );
            })
            ->where(function ($query) use ($today): void {
                $query
                    ->whereNull('valid_to')
                    ->orWhereDate(
                        'valid_to',
                        '>=',
                        $today
                    );
            })
            ->orderByRaw(
                'COALESCE(public_sort, installment_months) ASC'
            )
            ->orderBy('installment_months')
            ->get();

        abort_if(
            $plans->isEmpty(),
            404,
            'No hay planes de pago publicados.'
        );

        $rows = $plans
            ->map(function (
                SalesPriceList $plan
            ) use ($amount): array {
                $months = (int) $plan->installment_months;
                $rate = (float) $plan->adjustment_percent;

                $total = round(
                    $amount * (1 + ($rate / 100)),
                    2
                );

                $monthly = round(
                    $total / $months,
                    2
                );

                return [
                    'months' => $months,
                    'rate' => $rate,
                    'monthly' => $monthly,
                    'total' => $total,
                ];
            })
            ->values();

        $pdf = app('dompdf.wrapper')
            ->loadView(
                'pdfs.public.mercado-pago-calculator',
                [
                    'company' => $company,
                    'amount' => $amount,
                    'rows' => $rows,
                    'generatedAt' => now(),
                    'companyLogoDataUri' =>
                        $this->companyLogoDataUri($company),
                    'bexiaLogoDataUri' =>
                        $this->fileDataUri(
                            public_path('logo.png')
                        ),
                ]
            )
            ->setPaper('letter', 'portrait');

        $amountText = number_format(
            $amount,
            2,
            '-',
            ''
        );

        $fileName =
            'simulacion-mercado-pago-' .
            Str::slug($company->slug) .
            '-' .
            $amountText .
            '.pdf';

        /*
         * BEXIA_PUBLIC_PDF_ANALYTICS_V5_83_1A
         * Se registra solo despues de construir correctamente el PDF.
         */
        app(PublicPageAnalytics::class)
            ->recordPdfDownload(
                (int) $company->id,
                PublicPageAnalytics::MERCADO_PAGO_CALCULATOR,
                $request
            );

        return $pdf->download($fileName);
    }

    protected function validatedAmount(
        Request $request
    ): float {
        $request->validate([
            'monto' => [
                'required',
                'numeric',
                'min:1',
                'max:5000000',
            ],
        ]);

        return round(
            (float) $request->query('monto'),
            2
        );
    }

    protected function companyLogoDataUri(
        Company $company
    ): ?string {
        $path = trim(
            (string) ($company->logo_path ?? '')
        );

        if ($path === '') {
            return null;
        }

        $normalized = ltrim($path, '/');

        if (
            str_starts_with(
                $normalized,
                'storage/'
            )
        ) {
            $normalized = substr(
                $normalized,
                strlen('storage/')
            );
        }

        if (
            Storage::disk('public')->exists(
                $normalized
            )
        ) {
            return $this->fileDataUri(
                Storage::disk('public')->path(
                    $normalized
                )
            );
        }

        $publicPath = public_path($path);

        if (is_file($publicPath)) {
            return $this->fileDataUri(
                $publicPath
            );
        }

        return null;
    }

    protected function fileDataUri(
        string $path
    ): ?string {
        if (! is_file($path)) {
            return null;
        }

        $extension = strtolower(
            pathinfo(
                $path,
                PATHINFO_EXTENSION
            )
        );

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return
            'data:' .
            $mime .
            ';base64,' .
            base64_encode(
                (string) file_get_contents($path)
            );
    }
}
