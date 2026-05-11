<?php

namespace App\Http\Controllers\Sales;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesOrderPrintController extends Controller
{
    public function __invoke(Request $request, int|string $saleOrder)
    {
        if (! app()->bound('dompdf.wrapper')) {
            abort(500, 'No hay motor PDF instalado (barryvdh/laravel-dompdf).');
        }

        if (! Schema::hasTable('sales_orders')) {
            abort(404);
        }

        $order = DB::table('sales_orders')
            ->where('id', (int) $saleOrder)
            ->first();

        if (! $order) {
            abort(404);
        }

        $user = $request->user();

        $canPrint = $user && (
            (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin())
            || (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin())
            || $user->can('sales.print_pdf')
            || $user->can('sales.view')
        );

        if (! $canPrint) {
            abort(403);
        }

        $lines = Schema::hasTable('sales_order_lines')
            ? DB::table('sales_order_lines')
                ->where('sales_order_id', $order->id)
                ->orderBy('id')
                ->get()
            : collect();

        $company = Schema::hasTable('companies')
            ? DB::table('companies')->where('id', $order->company_id ?? 0)->first()
            : null;

        $customer = null;

        if (Schema::hasTable('contacts') && ! empty($order->customer_contact_id)) {
            $customer = DB::table('contacts')
                ->where('id', $order->customer_contact_id)
                ->first();
        }

        $priceList = null;

        if (Schema::hasTable('sales_price_lists') && ! empty($order->price_list_id)) {
            $priceList = DB::table('sales_price_lists')
                ->where('id', $order->price_list_id)
                ->first();
        }

        $warehouse = null;

        if (Schema::hasTable('warehouses') && ! empty($order->warehouse_id)) {
            $warehouse = DB::table('warehouses')
                ->where('id', $order->warehouse_id)
                ->first();
        }

        $location = null;

        if (Schema::hasTable('stock_locations') && ! empty($order->location_id)) {
            $location = DB::table('stock_locations')
                ->where('id', $order->location_id)
                ->first();
        }

        $logoSrc = $this->companyLogoDataUri($company);

        $isQuote = in_array((string) ($order->status ?? ''), ['draft', 'borrador'], true);
        $documentTitle = $isQuote ? 'Cotización' : 'Orden de venta';
        $number = trim((string) ($order->number ?? ''));
        $filename = ($isQuote ? 'Cotizacion' : 'Orden-Venta') . '-' . ($number !== '' ? $number : $order->id) . '.pdf';

        $pdf = app('dompdf.wrapper')
            ->loadView('pdfs.sales-order', [
                'order' => $order,
                'lines' => $lines,
                'company' => $company,
                'customer' => $customer,
                'priceList' => $priceList,
                'warehouse' => $warehouse,
                'location' => $location,
                'logoSrc' => $logoSrc,
                'documentTitle' => $documentTitle,
                'isQuote' => $isQuote,
            ])
            ->setPaper('letter', 'portrait');

        return $pdf->stream($filename);
    }

    protected function companyLogoDataUri(?object $company): ?string
    {
        if (! $company) {
            return null;
        }

        foreach (['logo_path', 'logo', 'image_path'] as $column) {
            if (! property_exists($company, $column)) {
                continue;
            }

            $value = trim((string) ($company->{$column} ?? ''));

            if ($value === '') {
                continue;
            }

            if (str_starts_with($value, 'data:image/')) {
                return $value;
            }

            $clean = ltrim(str_replace('\\', '/', $value), '/');

            $candidates = [
                public_path($clean),
                public_path('storage/' . $clean),
                storage_path('app/public/' . $clean),
                storage_path('app/' . $clean),
            ];

            foreach ($candidates as $path) {
                if (! is_file($path)) {
                    continue;
                }

                $mime = mime_content_type($path) ?: 'image/png';
                $data = base64_encode(file_get_contents($path));

                return "data:{$mime};base64,{$data}";
            }
        }

        return null;
    }
}
