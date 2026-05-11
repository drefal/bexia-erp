<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PurchaseReceiptViewController extends Controller
{
    public function show($tenant, $purchaseReceipt)
    {
        abort_unless(auth()->check(), 403);

        $data = $this->receiptData((int) $tenant, (int) $purchaseReceipt);

        return view('purchases.receipts.show', $data);
    }

    public function pdf($tenant, $purchaseReceipt)
    {
        abort_unless(auth()->check(), 403);

        $data = $this->receiptData((int) $tenant, (int) $purchaseReceipt);
        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) ($data['receipt']->number ?? 'recepcion')) . '.pdf';

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('purchases.receipts.pdf', $data)
                ->setPaper('letter', 'portrait');

            return $pdf->stream($filename);
        }

        return view('purchases.receipts.pdf', $data + [
            'pdfFallback' => true,
        ]);
    }

    protected function receiptData(int $tenantId, int $receiptId): array
    {
        if (! Schema::hasTable('purchase_receipts')) {
            abort(404, 'No existe la tabla de recepciones.');
        }

        $receipt = DB::table('purchase_receipts')
            ->where('id', $receiptId)
            ->first();

        abort_if(! $receipt, 404);

        if ($tenantId > 0 && (int) ($receipt->company_id ?? 0) > 0) {
            abort_if($tenantId !== (int) $receipt->company_id, 403);
        }

        $order = Schema::hasTable('purchase_orders') && $receipt->purchase_order_id
            ? DB::table('purchase_orders')->where('id', $receipt->purchase_order_id)->first()
            : null;

        $lines = Schema::hasTable('purchase_receipt_lines')
            ? DB::table('purchase_receipt_lines')
                ->where('purchase_receipt_id', $receipt->id)
                ->orderBy('id')
                ->get()
            : collect();

        $movement = null;

        if (Schema::hasTable('stock_movements') && ! empty($receipt->stock_movement_id)) {
            $movement = DB::table('stock_movements')
                ->where('id', $receipt->stock_movement_id)
                ->first();
        }

        $warehouse = null;

        if (Schema::hasTable('warehouses') && ! empty($receipt->warehouse_id)) {
            $warehouse = DB::table('warehouses')
                ->where('id', $receipt->warehouse_id)
                ->first();
        }

        $location = null;

        if (Schema::hasTable('stock_locations') && ! empty($receipt->location_id)) {
            $location = DB::table('stock_locations')
                ->where('id', $receipt->location_id)
                ->first();
        }

        $receivedBy = null;

        if (Schema::hasTable('users') && ! empty($receipt->received_by_user_id)) {
            $receivedBy = DB::table('users')
                ->where('id', $receipt->received_by_user_id)
                ->first();
        }

        return [
            'tenantId' => $tenantId,
            'receipt' => $receipt,
            'order' => $order,
            'lines' => $lines,
            'movement' => $movement,
            'warehouse' => $warehouse,
            'location' => $location,
            'receivedBy' => $receivedBy,
            'companyInfo' => $this->companyInfo((int) ($receipt->company_id ?? $tenantId)),
        ];
    }

    protected function companyInfo(int $companyId): array
    {
        $name = 'BexiaERP';
        $logo = null;
        $rfc = null;

        if ($companyId > 0 && Schema::hasTable('companies')) {
            $company = DB::table('companies')
                ->where('id', $companyId)
                ->first();

            if ($company) {
                foreach (['business_name', 'legal_name', 'name', 'razon_social'] as $column) {
                    if (property_exists($company, $column) && trim((string) $company->{$column}) !== '') {
                        $name = trim((string) $company->{$column});
                        break;
                    }
                }

                foreach (['rfc', 'tax_id'] as $column) {
                    if (property_exists($company, $column) && trim((string) $company->{$column}) !== '') {
                        $rfc = trim((string) $company->{$column});
                        break;
                    }
                }

                foreach (['logo_path', 'logo', 'logo_url', 'image', 'avatar'] as $column) {
                    if (property_exists($company, $column) && trim((string) $company->{$column}) !== '') {
                        $logo = $this->logoDataUri(trim((string) $company->{$column}));
                        break;
                    }
                }
            }
        }

        return [
            'name' => $name,
            'rfc' => $rfc,
            'logo' => $logo,
        ];
    }

    protected function logoDataUri(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'data:image/')) {
            return $value;
        }

        $candidates = [];

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $clean = ltrim($value, '/');

        $candidates[] = public_path($clean);
        $candidates[] = public_path('storage/' . preg_replace('#^storage/#', '', $clean));
        $candidates[] = storage_path('app/public/' . preg_replace('#^storage/#', '', $clean));
        $candidates[] = storage_path('app/' . $clean);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                $mime = mime_content_type($path) ?: 'image/png';

                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            }
        }

        return null;
    }
}
