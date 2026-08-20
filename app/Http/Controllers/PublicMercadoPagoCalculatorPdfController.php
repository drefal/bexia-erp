<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Support\MercadoPagoCalculator;
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
        if (
            ! app()->bound(
                'dompdf.wrapper'
            )
        ) {
            abort(
                500,
                'No hay motor PDF disponible.'
            );
        }

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

        $amount =
            $this->validatedAmount(
                $request
            );

        $calculator =
            app(
                MercadoPagoCalculator::class
            );

        $mode =
            $calculator
                ->normalizeMode(
                    $request->query(
                        'modo'
                    )
                );

        $terms =
            $calculator->terms(
                $company
            );

        abort_if(
            $terms
                ->where(
                    'months',
                    '>',
                    1
                )
                ->isEmpty(),
            404,
            'No hay planes publicados.'
        );

        /*
         * BEXIA_MP_PDF_SELECTED_PLANS_V5_83_2A5
         *
         * planes=1,6,12
         *
         * Sin parametro o sin seleccion valida:
         * se imprimen todos.
         */
        $availableMonths =
            $terms
                ->pluck('months')
                ->map(
                    fn ($value): int =>
                        (int) $value
                )
                ->all();

        $selectedMonths =
            collect(
                explode(
                    ',',
                    (string)
                    $request->query(
                        'planes',
                        ''
                    )
                )
            )
                ->map(
                    fn ($value): int =>
                        (int)
                        trim(
                            (string) $value
                        )
                )
                ->filter(
                    fn (int $months): bool =>
                        in_array(
                            $months,
                            $availableMonths,
                            true
                        )
                )
                ->unique()
                ->values();

        if (
            $selectedMonths->isNotEmpty()
        ) {
            $terms =
                $terms
                    ->whereIn(
                        'months',
                        $selectedMonths->all()
                    )
                    ->values();
        }

        $rows =
            $calculator->calculate(
                $terms,
                $amount,
                $mode
            );

        $pdf =
            app('dompdf.wrapper')
                ->loadView(
                    'pdfs.public.mercado-pago-calculator',
                    [
                        'company' =>
                            $company,

                        'amount' =>
                            $amount,

                        'mode' =>
                            $mode,

                        'rows' =>
                            $rows,

                        'generatedAt' =>
                            now(),

                        'companyLogoDataUri' =>
                            $this
                                ->companyLogoDataUri(
                                    $company
                                ),

                        'bexiaLogoDataUri' =>
                            $this
                                ->fileDataUri(
                                    public_path(
                                        'logo.png'
                                    )
                                ),
                    ]
                )
                ->setPaper(
                    'letter',
                    'landscape'
                );

        $amountText =
            number_format(
                $amount,
                2,
                '-',
                ''
            );

        $fileName =
            'simulacion-mercado-pago-' .
            Str::slug(
                $company->slug
            ) .
            '-' .
            $mode .
            '-' .
            $amountText .
            '.pdf';

        app(
            PublicPageAnalytics::class
        )->recordPdfDownload(
            (int) $company->id,
            PublicPageAnalytics::
                MERCADO_PAGO_CALCULATOR,
            $request
        );

        return $pdf->download(
            $fileName
        );
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
            (float)
            $request->query('monto'),
            2
        );
    }

    protected function companyLogoDataUri(
        Company $company
    ): ?string {
        $path =
            trim(
                (string)
                ($company->logo_path ?? '')
            );

        if ($path === '') {
            return null;
        }

        $normalized =
            ltrim(
                $path,
                '/'
            );

        if (
            str_starts_with(
                $normalized,
                'storage/'
            )
        ) {
            $normalized =
                substr(
                    $normalized,
                    strlen(
                        'storage/'
                    )
                );
        }

        if (
            Storage::disk('public')
                ->exists(
                    $normalized
                )
        ) {
            return $this
                ->fileDataUri(
                    Storage::disk(
                        'public'
                    )->path(
                        $normalized
                    )
                );
        }

        $publicPath =
            public_path(
                $path
            );

        if (
            is_file(
                $publicPath
            )
        ) {
            return $this
                ->fileDataUri(
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

        $extension =
            strtolower(
                pathinfo(
                    $path,
                    PATHINFO_EXTENSION
                )
            );

        $mime = match ($extension) {
            'jpg',
            'jpeg' =>
                'image/jpeg',

            'gif' =>
                'image/gif',

            'webp' =>
                'image/webp',

            default =>
                'image/png',
        };

        return
            'data:' .
            $mime .
            ';base64,' .
            base64_encode(
                (string)
                file_get_contents(
                    $path
                )
            );
    }
}
