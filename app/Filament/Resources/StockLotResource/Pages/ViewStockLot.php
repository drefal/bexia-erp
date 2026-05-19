<?php

namespace App\Filament\Resources\StockLotResource\Pages;

use App\Filament\Resources\StockLotResource;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ViewStockLot extends Page
{
    protected static string $resource = StockLotResource::class;

    protected static string $view = 'filament.resources.stock-lot-resource.pages.view-stock-lot';

    public int $lotId = 0;

    public function mount(mixed $record): void
    {
        $this->lotId = $this->recordIdFromRouteValue($record);

        if (! $this->lot()) {
            abort(404, 'No se encontró el lote.');
        }
    }

    public function getTitle(): string
    {
        return 'Detalle de lote';
    }

    public function getHeading(): string
    {
        $lot = $this->lot();

        return 'Lote ' . ($lot->lot_number ?? $lot->number ?? ('#' . $this->lotId));
    }

    public function lot(): ?object
    {
        if (! Schema::hasTable('stock_lots')) {
            return null;
        }

        return DB::table('stock_lots')
            ->where('id', $this->lotId)
            ->first();
    }

    public function product(): ?object
    {
        $lot = $this->lot();

        if (! $lot || empty($lot->product_id) || ! Schema::hasTable('products')) {
            return null;
        }

        return DB::table('products')
            ->where('id', $lot->product_id)
            ->first();
    }

    public function receipt(): ?object
    {
        $lot = $this->lot();

        if (! $lot || ! Schema::hasTable('purchase_receipts')) {
            return null;
        }

        if (property_exists($lot, 'purchase_receipt_id') && ! empty($lot->purchase_receipt_id)) {
            return DB::table('purchase_receipts')
                ->where('id', $lot->purchase_receipt_id)
                ->first();
        }

        if (
            Schema::hasTable('purchase_receipt_lines') &&
            Schema::hasColumn('purchase_receipt_lines', 'purchase_receipt_id')
        ) {
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

            if ($line && ! empty($line->purchase_receipt_id)) {
                return DB::table('purchase_receipts')
                    ->where('id', $line->purchase_receipt_id)
                    ->first();
            }
        }

        return null;
    }

    public function stats(): array
    {
        $lot = $this->lot();

        if (! $lot) {
            return [
                'total' => 0.0,
                'sold' => 0.0,
                'remaining' => 0.0,
                'source' => 'Sin datos',
            ];
        }

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
            'source' => $this->statsSource($lot),
        ];
    }

    public function receiptUrl(): ?string
    {
        $receipt = $this->receipt();

        if (! $receipt) {
            return null;
        }

        return url('/admin/' . $this->tenantId($receipt) . '/purchase-receipts/' . $receipt->id . '/panel');
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

    protected function statsSource(object $lot): string
    {
        if (Schema::hasTable('purchase_receipt_lines')) {
            return 'Recepciones y existencia actual';
        }

        if (Schema::hasTable('stock_quants')) {
            return 'Existencias de inventario';
        }

        return 'Datos del lote';
    }

    protected function tenantId(?object $row = null): int
    {
        $tenant = request()->route('tenant');

        if (is_numeric($tenant)) {
            return (int) $tenant;
        }

        if (is_object($tenant) && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        if (is_object($tenant) && isset($tenant->id)) {
            return (int) $tenant->id;
        }

        if ($row && property_exists($row, 'company_id') && (int) $row->company_id > 0) {
            return (int) $row->company_id;
        }

        return (int) (auth()->user()?->company_id ?? 0);
    }

    protected function recordIdFromRouteValue(mixed $record): int
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

    public function printUrl(): string
    {
        $lot = $this->lot();

        return url('/admin/' . $this->tenantId($lot) . '/stock-lots/' . $this->lotId . '/pdf');
    }

    public function downloadPdfUrl(): string
    {
        return $this->printUrl() . '?download=1';
    }



}
