<?php

namespace App\Http\Controllers\Inventory;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class TrackingPrintPdfController extends Controller
{
    public function serial(Request $request, mixed $tenant, mixed $record)
    {
        $serialId = $this->recordId($record);

        $serial = DB::table('stock_serial_numbers')
            ->where('id', $serialId)
            ->first();

        abort_if(! $serial, 404, 'No se encontró el número de serie.');

        $product = $this->product($serial->product_id ?? null);
        $lot = $this->lotForSerial($serial);
        $receipt = $this->receipt($serial->purchase_receipt_id ?? null);
        $company = $this->company($tenant, $serial);

        $data = [
            'serial' => $serial,
            'product' => $product,
            'lot' => $lot,
            'receipt' => $receipt,
            'company' => $company,
            'logoDataUri' => $this->companyLogoDataUri($company),
            'productLabel' => $this->productLabel($product),
            'lotNumber' => $lot->lot_number ?? $lot->number ?? $serial->lot_number ?? '—',
            'statusText' => $this->statusText($serial->status ?? null),
        ];

        $pdf = Pdf::loadView('inventory.pdf.stock-serial-number', $data)
            ->setPaper('letter', 'portrait')
            ->setOption([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);

        $filename = 'numero-serie-' . $this->safeFilename($serial->serial_number ?? $serialId) . '.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    public function lot(Request $request, mixed $tenant, mixed $record)
    {
        $lotId = $this->recordId($record);

        $lot = DB::table('stock_lots')
            ->where('id', $lotId)
            ->first();

        abort_if(! $lot, 404, 'No se encontró el lote.');

        $product = $this->product($lot->product_id ?? null);
        $receipt = $this->receiptForLot($lot);
        $company = $this->company($tenant, $lot);
        $stats = $this->lotStats($lot);

        $data = [
            'lot' => $lot,
            'product' => $product,
            'receipt' => $receipt,
            'company' => $company,
            'logoDataUri' => $this->companyLogoDataUri($company),
            'productLabel' => $this->productLabel($product),
            'lotNumber' => $lot->lot_number ?? $lot->number ?? '—',
            'statusText' => $this->statusText($lot->status ?? null),
            'stats' => $stats,
        ];

        $pdf = Pdf::loadView('inventory.pdf.stock-lot', $data)
            ->setPaper('letter', 'portrait')
            ->setOption([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);

        $filename = 'lote-' . $this->safeFilename($lot->lot_number ?? $lot->number ?? $lotId) . '.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    protected function product(mixed $productId): ?object
    {
        if (! $productId || ! Schema::hasTable('products')) {
            return null;
        }

        return DB::table('products')->where('id', $productId)->first();
    }

    protected function receipt(mixed $receiptId): ?object
    {
        if (! $receiptId || ! Schema::hasTable('purchase_receipts')) {
            return null;
        }

        return DB::table('purchase_receipts')->where('id', $receiptId)->first();
    }

    protected function lotForSerial(object $serial): ?object
    {
        if (! Schema::hasTable('stock_lots')) {
            return null;
        }

        if (property_exists($serial, 'lot_id') && ! empty($serial->lot_id)) {
            return DB::table('stock_lots')->where('id', $serial->lot_id)->first();
        }

        if (
            property_exists($serial, 'lot_number') &&
            ! empty($serial->lot_number) &&
            Schema::hasColumn('stock_lots', 'lot_number')
        ) {
            return DB::table('stock_lots')->where('lot_number', $serial->lot_number)->first();
        }

        return null;
    }

    protected function receiptForLot(object $lot): ?object
    {
        if (! Schema::hasTable('purchase_receipts')) {
            return null;
        }

        if (property_exists($lot, 'purchase_receipt_id') && ! empty($lot->purchase_receipt_id)) {
            return DB::table('purchase_receipts')->where('id', $lot->purchase_receipt_id)->first();
        }

        if (! Schema::hasTable('purchase_receipt_lines')) {
            return null;
        }

        $lineQuery = DB::table('purchase_receipt_lines');

        if (Schema::hasColumn('purchase_receipt_lines', 'lot_id')) {
            $lineQuery->where('lot_id', $lot->id);
        } elseif (Schema::hasColumn('purchase_receipt_lines', 'lot_number')) {
            $number = $lot->lot_number ?? $lot->number ?? null;

            if (! $number) {
                return null;
            }

            $lineQuery->where('lot_number', $number);
        } else {
            return null;
        }

        $line = $lineQuery->orderByDesc('id')->first(['purchase_receipt_id']);

        if (! $line || empty($line->purchase_receipt_id)) {
            return null;
        }

        return DB::table('purchase_receipts')->where('id', $line->purchase_receipt_id)->first();
    }


    protected function lotStats(object $lot): array
    {
        $total = $this->totalReceivedForLot($lot);
        $remaining = $this->remainingForLot($lot);

        if ($total <= 0 && $remaining > 0) {
            $total = $remaining;
        }

        $sold = max($total - $remaining, 0);

        return [
            'total' => $total,
            'sold' => $sold,
            'remaining' => $remaining,
        ];
    }

    protected function totalReceivedForLot(object $lot): float
    {
        $fromReceiptLines = $this->sumReceiptLinesForLot($lot);

        if ($fromReceiptLines > 0) {
            return $fromReceiptLines;
        }

        foreach (['received_quantity', 'initial_quantity', 'total_quantity', 'quantity'] as $field) {
            if (property_exists($lot, $field) && is_numeric($lot->{$field})) {
                return (float) $lot->{$field};
            }
        }

        $serialCount = $this->serialCountForLot($lot);

        if ($serialCount > 0) {
            return (float) $serialCount;
        }

        return 0.0;
    }

    protected function remainingForLot(object $lot): float
    {
        $quantQuantity = $this->sumQuantsForLot($lot);

        if ($quantQuantity !== null) {
            return max((float) $quantQuantity, 0);
        }

        foreach (['available_quantity', 'current_quantity', 'remaining_quantity', 'quantity'] as $field) {
            if (property_exists($lot, $field) && is_numeric($lot->{$field})) {
                return max((float) $lot->{$field}, 0);
            }
        }

        $availableSerials = $this->availableSerialCountForLot($lot);

        if ($availableSerials !== null) {
            return (float) $availableSerials;
        }

        return 0.0;
    }

    protected function sumReceiptLinesForLot(object $lot): float
    {
        if (! Schema::hasTable('purchase_receipt_lines')) {
            return 0.0;
        }

        $quantityColumn = null;

        foreach (['received_quantity', 'quantity'] as $column) {
            if (Schema::hasColumn('purchase_receipt_lines', $column)) {
                $quantityColumn = $column;
                break;
            }
        }

        if (! $quantityColumn) {
            return 0.0;
        }

        $query = DB::table('purchase_receipt_lines');

        if (Schema::hasColumn('purchase_receipt_lines', 'lot_id')) {
            $query->where('lot_id', $lot->id);
        } elseif (Schema::hasColumn('purchase_receipt_lines', 'lot_number')) {
            $number = $lot->lot_number ?? $lot->number ?? null;

            if (! $number) {
                return 0.0;
            }

            $query->where('lot_number', $number);
        } else {
            return 0.0;
        }

        return (float) $query->sum($quantityColumn);
    }

    protected function sumQuantsForLot(object $lot): ?float
    {
        if (! Schema::hasTable('stock_quants')) {
            return null;
        }

        $quantityColumn = null;

        foreach (['quantity', 'available_quantity', 'qty'] as $column) {
            if (Schema::hasColumn('stock_quants', $column)) {
                $quantityColumn = $column;
                break;
            }
        }

        if (! $quantityColumn) {
            return null;
        }

        $query = DB::table('stock_quants');

        if (Schema::hasColumn('stock_quants', 'lot_id')) {
            $query->where('lot_id', $lot->id);
        } elseif (Schema::hasColumn('stock_quants', 'lot_number')) {
            $number = $lot->lot_number ?? $lot->number ?? null;

            if (! $number) {
                return null;
            }

            $query->where('lot_number', $number);
        } else {
            return null;
        }

        return (float) $query->sum($quantityColumn);
    }

    protected function serialCountForLot(object $lot): int
    {
        if (! Schema::hasTable('stock_serial_numbers')) {
            return 0;
        }

        $query = DB::table('stock_serial_numbers');

        if (Schema::hasColumn('stock_serial_numbers', 'lot_id')) {
            $query->where('lot_id', $lot->id);
        } elseif (Schema::hasColumn('stock_serial_numbers', 'lot_number')) {
            $number = $lot->lot_number ?? $lot->number ?? null;

            if (! $number) {
                return 0;
            }

            $query->where('lot_number', $number);
        } else {
            return 0;
        }

        return (int) $query->count();
    }

    protected function availableSerialCountForLot(object $lot): ?int
    {
        if (! Schema::hasTable('stock_serial_numbers')) {
            return null;
        }

        $query = DB::table('stock_serial_numbers');

        if (Schema::hasColumn('stock_serial_numbers', 'lot_id')) {
            $query->where('lot_id', $lot->id);
        } elseif (Schema::hasColumn('stock_serial_numbers', 'lot_number')) {
            $number = $lot->lot_number ?? $lot->number ?? null;

            if (! $number) {
                return null;
            }

            $query->where('lot_number', $number);
        } else {
            return null;
        }

        if (Schema::hasColumn('stock_serial_numbers', 'status')) {
            $query->whereIn('status', ['available', 'in_stock', 'active']);
        }

        return (int) $query->count();
    }



    protected function company(mixed $tenant, ?object $row = null): ?object
    {
        if (! Schema::hasTable('companies')) {
            return null;
        }

        $companyId = 0;

        if (is_numeric($tenant)) {
            $companyId = (int) $tenant;
        } elseif (is_object($tenant) && isset($tenant->id)) {
            $companyId = (int) $tenant->id;
        } elseif ($row && property_exists($row, 'company_id')) {
            $companyId = (int) $row->company_id;
        }

        if ($companyId <= 0) {
            $companyId = (int) (auth()->user()?->company_id ?? 0);
        }

        if ($companyId <= 0) {
            return null;
        }

        return DB::table('companies')->where('id', $companyId)->first();
    }

    protected function companyLogoDataUri(?object $company): ?string
    {
        if (! $company) {
            return null;
        }

        foreach ([
            'logo_path',
            'logo',
            'logo_url',
            'brand_logo_path',
            'image',
            'image_path',
            'avatar',
            'avatar_url',
        ] as $field) {
            if (! property_exists($company, $field)) {
                continue;
            }

            $value = trim((string) ($company->{$field} ?? ''));

            if ($value === '') {
                continue;
            }

            if (str_starts_with($value, 'data:image')) {
                return $value;
            }

            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                return $value;
            }

            $candidates = [];

            if (str_starts_with($value, '/')) {
                $candidates[] = public_path(ltrim($value, '/'));
            } else {
                $candidates[] = Storage::disk('public')->path($value);
                $candidates[] = public_path($value);
                $candidates[] = public_path('storage/' . ltrim($value, '/'));
            }

            foreach ($candidates as $path) {
                if (! is_file($path)) {
                    continue;
                }

                $mime = mime_content_type($path) ?: 'image/png';

                return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
            }
        }

        return null;
    }

    protected function productLabel(?object $product): string
    {
        if (! $product) {
            return '—';
        }

        return trim(implode(' - ', array_filter([
            $product->internal_reference ?? $product->sku ?? null,
            $product->name ?? null,
        ]))) ?: '—';
    }

    protected function statusText(mixed $status): string
    {
        $key = mb_strtolower(trim((string) ($status ?? '')));

        if ($key === '') {
            return '—';
        }

        $labels = [
            'available' => 'Disponible',
            'in_stock' => 'Disponible',
            'active' => 'Activo',
            'received' => 'Recibido',
            'reserved' => 'Reservado',
            'sold' => 'Vendido',
            'used' => 'Usado',
            'consumed' => 'Consumido',
            'inactive' => 'Inactivo',
            'blocked' => 'Bloqueado',
            'quarantine' => 'En cuarentena',
            'quarantined' => 'En cuarentena',
            'damaged' => 'Dañado',
            'expired' => 'Caducado',
            'done' => 'Hecho',
            'draft' => 'Borrador',
            'pending' => 'Pendiente',
        ];

        return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    protected function recordId(mixed $record): int
    {
        if (is_object($record) && method_exists($record, 'getKey')) {
            return (int) $record->getKey();
        }

        if (is_object($record) && isset($record->id)) {
            return (int) $record->id;
        }

        if (is_array($record) && isset($record['id'])) {
            return (int) $record['id'];
        }

        if (is_numeric($record)) {
            return (int) $record;
        }

        $value = trim((string) $record);

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (str_starts_with($value, '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded) && isset($decoded['id']) && is_numeric($decoded['id'])) {
                return (int) $decoded['id'];
            }
        }

        return 0;
    }

    protected function safeFilename(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 'documento';
        }

        $value = preg_replace('/[^A-Za-z0-9_\-]+/', '-', $value) ?: 'documento';

        return trim($value, '-_') ?: 'documento';
    }
}
