<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\SaleDelivery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SaleDeliveryPrintController extends Controller
{
    public function __invoke(SaleDelivery $saleDelivery): Response
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        if (
            ! (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin())
            && ! (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin())
            && method_exists($user, 'can')
            && ! $user->can('sales.view')
            && ! $user->can('inventory.view')
        ) {
            abort(403);
        }

        $order = DB::table('sales_orders')
            ->where('id', $saleDelivery->sales_order_id)
            ->first();

        $company = null;
        $customer = null;
        $warehouse = null;
        $sourceLocation = null;
        $destinationLocation = null;

        if ($order && Schema::hasTable('companies')) {
            $company = DB::table('companies')
                ->where('id', $order->company_id)
                ->first();
        }

        if ($order && Schema::hasTable('contacts') && ! empty($order->customer_contact_id)) {
            $customer = DB::table('contacts')
                ->where('id', $order->customer_contact_id)
                ->first();
        }

        if ($order && Schema::hasTable('warehouses') && ! empty($order->warehouse_id)) {
            $warehouse = DB::table('warehouses')
                ->where('id', $order->warehouse_id)
                ->first();
        }

        if (Schema::hasTable('stock_locations')) {
            if (! empty($saleDelivery->source_location_id)) {
                $sourceLocation = DB::table('stock_locations')
                    ->where('id', $saleDelivery->source_location_id)
                    ->first();
            }

            if (! empty($saleDelivery->destination_location_id)) {
                $destinationLocation = DB::table('stock_locations')
                    ->where('id', $saleDelivery->destination_location_id)
                    ->first();
            }
        }

        $lines = DB::table('sale_delivery_lines')
            ->where('sale_delivery_id', $saleDelivery->id)
            ->orderBy('id')
            ->get();

        $logoSrc = $this->companyLogoSrc($company);

        $pdf = Pdf::loadView('pdfs.sale-delivery', [
            'delivery' => $saleDelivery,
            'order' => $order,
            'company' => $company,
            'customer' => $customer,
            'warehouse' => $warehouse,
            'sourceLocation' => $sourceLocation,
            'destinationLocation' => $destinationLocation,
            'lines' => $lines,
            'logoSrc' => $logoSrc,
            'documentTitle' => 'Entrega de venta',
        ])->setPaper('letter', 'portrait');

        $filename = 'entrega-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($saleDelivery->number ?: $saleDelivery->id)) . '.pdf';

        return $pdf->stream($filename);
    }

    protected function companyLogoSrc(?object $company): ?string
    {
        /*
         * Primero intentamos usar columnas comunes de logo en companies.
         * Esto permite que el PDF use el logo del tenant/empresa.
         */
        if ($company) {
            foreach ([
                'logo_path',
                'logo',
                'logo_url',
                'logo_file',
                'image',
                'image_path',
                'brand_logo',
                'brand_logo_path',
            ] as $column) {
                if (! property_exists($company, $column)) {
                    continue;
                }

                $value = trim((string) ($company->{$column} ?? ''));

                if ($value === '') {
                    continue;
                }

                $src = $this->fileValueToDataUri($value);

                if ($src) {
                    return $src;
                }
            }
        }

        /*
         * Respaldo: logo genérico si la empresa todavía no tiene logo.
         */
        return $this->fallbackLogoSrc();
    }

    protected function fileValueToDataUri(string $value): ?string
    {
        if (str_starts_with($value, 'data:image/')) {
            return $value;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $contents = @file_get_contents($value);

            if ($contents !== false) {
                $mime = $this->mimeFromPath($value) ?: 'image/png';

                return 'data:' . $mime . ';base64,' . base64_encode($contents);
            }

            return $value;
        }

        $candidates = [
            $value,
            public_path($value),
            public_path(ltrim($value, '/')),
            storage_path('app/public/' . ltrim($value, '/')),
            storage_path('app/' . ltrim($value, '/')),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $mime = mime_content_type($candidate) ?: $this->mimeFromPath($candidate) ?: 'image/png';

                return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($candidate));
            }
        }

        try {
            if (Storage::disk('public')->exists($value)) {
                $contents = Storage::disk('public')->get($value);
                $mime = Storage::disk('public')->mimeType($value) ?: $this->mimeFromPath($value) ?: 'image/png';

                return 'data:' . $mime . ';base64,' . base64_encode($contents);
            }
        } catch (\Throwable $e) {
            //
        }

        return null;
    }

    protected function fallbackLogoSrc(): ?string
    {
        $paths = [
            public_path('images/logo.png'),
            public_path('img/logo.png'),
            public_path('logo.png'),
            public_path('favicon.png'),
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $mime = mime_content_type($path) ?: 'image/png';

                return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
            }
        }

        return null;
    }

    protected function mimeFromPath(string $path): ?string
    {
        return match (strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => null,
        };
    }
}
