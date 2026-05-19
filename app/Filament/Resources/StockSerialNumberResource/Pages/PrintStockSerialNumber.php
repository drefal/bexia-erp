<?php

namespace App\Filament\Resources\StockSerialNumberResource\Pages;

use App\Filament\Resources\StockSerialNumberResource;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PrintStockSerialNumber extends Page
{
    protected static string $resource = StockSerialNumberResource::class;

    protected static string $view = 'filament.resources.stock-serial-number-resource.pages.print-stock-serial-number';

    protected static bool $shouldRegisterNavigation = false;

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
        return 'Impresión de número de serie';
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

    public function company(): ?object
    {
        $tenantId = $this->tenantId($this->serial());

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
