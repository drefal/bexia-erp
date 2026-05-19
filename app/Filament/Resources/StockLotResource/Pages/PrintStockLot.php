<?php

namespace App\Filament\Resources\StockLotResource\Pages;

use App\Filament\Resources\StockLotResource;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PrintStockLot extends Page
{
    protected static string $resource = StockLotResource::class;

    protected static string $view = 'filament.resources.stock-lot-resource.pages.print-stock-lot';

    protected static bool $shouldRegisterNavigation = false;

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
        return 'Impresión de lote';
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

        return null;
    }

    public function stats(): array
    {
        $lot = $this->lot();

        if (! $lot) {
            return ['total' => 0.0, 'sold' => 0.0, 'remaining' => 0.0];
        }

        $remaining = 0.0;

        foreach (['available_quantity', 'current_quantity', 'remaining_quantity', 'quantity'] as $field) {
            if (property_exists($lot, $field) && is_numeric($lot->{$field})) {
                $remaining = max((float) $lot->{$field}, 0);
                break;
            }
        }

        $total = 0.0;

        foreach (['received_quantity', 'initial_quantity', 'total_quantity', 'quantity'] as $field) {
            if (property_exists($lot, $field) && is_numeric($lot->{$field})) {
                $total = max((float) $lot->{$field}, 0);
                break;
            }
        }

        if ($total <= 0 && $remaining > 0) {
            $total = $remaining;
        }

        return [
            'total' => $total,
            'sold' => max($total - $remaining, 0),
            'remaining' => $remaining,
        ];
    }

    public function company(): ?object
    {
        $tenantId = $this->tenantId($this->lot());

        if ($tenantId <= 0 || ! Schema::hasTable('companies')) {
            return null;
        }

        return DB::table('companies')
            ->where('id', $tenantId)
            ->first();
    }

    public function companyLogoUrl(): ?string
    {
        $company = $this->company();

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

            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, 'data:image')) {
                return $value;
            }

            if (str_starts_with($value, '/')) {
                return $value;
            }

            return Storage::url($value);
        }

        return null;
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
}
