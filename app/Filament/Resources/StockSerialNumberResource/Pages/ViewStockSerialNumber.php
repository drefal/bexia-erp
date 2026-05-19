<?php

namespace App\Filament\Resources\StockSerialNumberResource\Pages;

use App\Filament\Resources\StockSerialNumberResource;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ViewStockSerialNumber extends Page
{
    protected static string $resource = StockSerialNumberResource::class;

    protected static string $view = 'filament.resources.stock-serial-number-resource.pages.view-stock-serial-number';

    public int $serialId = 0;

    public function mount(mixed $record): void
    {
        $this->serialId = $this->recordIdFromRouteValue($record);

        if (! $this->serial()) {
            abort(404, 'No se encontró el número de serie.');
        }
    }

    public function getTitle(): string
    {
        return 'Detalle de número de serie';
    }

    public function getHeading(): string
    {
        $serial = $this->serial();

        return 'Número de serie ' . ($serial->serial_number ?? ('#' . $this->serialId));
    }

    public function serial(): ?object
    {
        if (! Schema::hasTable('stock_serial_numbers')) {
            return null;
        }

        return DB::table('stock_serial_numbers')
            ->where('id', $this->serialId)
            ->first();
    }

    public function product(): ?object
    {
        $serial = $this->serial();

        if (! $serial || empty($serial->product_id) || ! Schema::hasTable('products')) {
            return null;
        }

        return DB::table('products')
            ->where('id', $serial->product_id)
            ->first();
    }

    public function variant(): ?object
    {
        $serial = $this->serial();

        if (! $serial || empty($serial->product_variant_id) || ! Schema::hasTable('product_variants')) {
            return null;
        }

        return DB::table('product_variants')
            ->where('id', $serial->product_variant_id)
            ->first();
    }

    public function lot(): ?object
    {
        $serial = $this->serial();

        if (! $serial || ! Schema::hasTable('stock_lots')) {
            return null;
        }

        if (property_exists($serial, 'lot_id') && ! empty($serial->lot_id)) {
            return DB::table('stock_lots')
                ->where('id', $serial->lot_id)
                ->first();
        }

        if (property_exists($serial, 'lot_number') && ! empty($serial->lot_number) && Schema::hasColumn('stock_lots', 'lot_number')) {
            return DB::table('stock_lots')
                ->where('lot_number', $serial->lot_number)
                ->first();
        }

        return null;
    }

    public function receipt(): ?object
    {
        $serial = $this->serial();

        if (! $serial || empty($serial->purchase_receipt_id) || ! Schema::hasTable('purchase_receipts')) {
            return null;
        }

        return DB::table('purchase_receipts')
            ->where('id', $serial->purchase_receipt_id)
            ->first();
    }

    public function receiptLine(): ?object
    {
        $serial = $this->serial();

        if (! $serial || ! Schema::hasTable('purchase_receipt_lines')) {
            return null;
        }

        if (property_exists($serial, 'purchase_receipt_line_id') && ! empty($serial->purchase_receipt_line_id)) {
            return DB::table('purchase_receipt_lines')
                ->where('id', $serial->purchase_receipt_line_id)
                ->first();
        }

        if (! empty($serial->purchase_receipt_id) && ! empty($serial->serial_number)) {
            return DB::table('purchase_receipt_lines')
                ->where('purchase_receipt_id', $serial->purchase_receipt_id)
                ->where(function ($query) use ($serial): void {
                    $query
                        ->where('serial_numbers', 'like', '%' . $serial->serial_number . '%')
                        ->orWhere('serial_import_rows', 'like', '%' . $serial->serial_number . '%');
                })
                ->first();
        }

        return null;
    }

    public function movement(): ?object
    {
        $receipt = $this->receipt();

        if (! $receipt || empty($receipt->stock_movement_id) || ! Schema::hasTable('stock_movements')) {
            return null;
        }

        return DB::table('stock_movements')
            ->where('id', $receipt->stock_movement_id)
            ->first();
    }

    public function warehouse(): ?object
    {
        $serial = $this->serial();

        if (! $serial || ! Schema::hasTable('warehouses')) {
            return null;
        }

        $warehouseId = $serial->current_warehouse_id
            ?? $serial->warehouse_id
            ?? null;

        if (! $warehouseId) {
            return null;
        }

        return DB::table('warehouses')
            ->where('id', $warehouseId)
            ->first();
    }

    public function location(): ?object
    {
        $serial = $this->serial();

        if (! $serial || ! Schema::hasTable('stock_locations')) {
            return null;
        }

        $locationId = $serial->current_location_id
            ?? $serial->location_id
            ?? null;

        if (! $locationId) {
            return null;
        }

        return DB::table('stock_locations')
            ->where('id', $locationId)
            ->first();
    }

    public function receiptUrl(): ?string
    {
        $receipt = $this->receipt();

        if (! $receipt) {
            return null;
        }

        return url('/admin/' . $this->tenantId($receipt) . '/purchase-receipts/' . $receipt->id . '/panel');
    }

    public function lotUrl(): ?string
    {
        $lot = $this->lot();
        $serial = $this->serial();

        if (! $lot) {
            return null;
        }

        return url('/admin/' . $this->tenantId($serial) . '/stock-lots/' . $lot->id . '/view');
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
        $serial = $this->serial();

        return url('/admin/' . $this->tenantId($serial) . '/stock-serial-numbers/' . $this->serialId . '/pdf');
    }

    public function downloadPdfUrl(): string
    {
        return $this->printUrl() . '?download=1';
    }



}
