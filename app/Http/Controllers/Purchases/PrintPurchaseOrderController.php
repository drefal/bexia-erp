<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PrintPurchaseOrderController extends Controller
{
    public function __invoke(PurchaseOrder $purchaseOrder)
    {
        abort_unless(auth()->check(), 403);

        $order = DB::table('purchase_orders')
            ->where('id', $purchaseOrder->getKey())
            ->first();

        abort_if(! $order, 404);

        $lines = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $purchaseOrder->getKey())
            ->orderBy('id')
            ->get();

        $company = null;

        if (Schema::hasTable('companies') && ! empty($order->company_id)) {
            $company = DB::table('companies')
                ->where('id', $order->company_id)
                ->first();
        }

        $logoSrc = $this->companyLogoSrc($company);

        return response()
            ->view('purchases.orders.print', [
                'order' => $order,
                'lines' => $lines,
                'company' => $company,
                'logoSrc' => $logoSrc,
                'generatedAt' => now(),
            ]);
    }

    protected function companyLogoSrc(?object $company): ?string
    {
        if (! $company) {
            return null;
        }

        foreach (['logo_path', 'logo', 'logo_url', 'image_path'] as $column) {
            if (! property_exists($company, $column)) {
                continue;
            }

            $value = trim((string) ($company->{$column} ?? ''));

            if ($value === '') {
                continue;
            }

            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                return $value;
            }

            $candidates = [
                public_path($value),
                public_path('storage/' . ltrim($value, '/')),
                storage_path('app/public/' . ltrim($value, '/')),
            ];

            foreach ($candidates as $path) {
                if (is_file($path)) {
                    $mime = mime_content_type($path) ?: 'image/png';
                    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
                }
            }
        }

        return null;
    }
}
