<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseOrderFromXml extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Crear OC desde XML';

    protected static ?int $navigationSort = 40;

    protected static ?string $title = 'Crear orden de compra desde XML';

    protected static ?string $navigationGroup = 'Compras';
protected static ?string $slug = 'purchase-orders/from-xml';

    protected static string $view = 'filament.pages.create-purchase-order-from-xml';

    protected static function canUsePurchaseXmlPage(): bool
{
    return auth()->user()?->can('purchases.create') ?? false;
}

public array $warehouses = [];

    public array $receivingLocations = [];

    public function mount(): void
    {
        $companyId = $this->tenantId();

        $this->warehouses = $this->warehouseOptions($companyId);
        $this->receivingLocations = $this->receivingLocationOptions($companyId);
    }

    protected function tenantId(): int
    {
        $tenant = request()->route('tenant');

        if (is_numeric($tenant) && (int) $tenant > 0) {
            return (int) $tenant;
        }

        return (int) (auth()->user()?->company_id ?? 0);
    }

    protected function warehouseOptions(int $companyId): array
    {
        if (! Schema::hasTable('warehouses')) {
            return [];
        }

        $columns = Schema::getColumnListing('warehouses');

        $query = DB::table('warehouses');

        if ($companyId > 0 && in_array('company_id', $columns, true)) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->orderBy(in_array('name', $columns, true) ? 'name' : 'id')
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'label' => $this->buildLabel($row, ['code', 'name', 'label']),
            ])
            ->values()
            ->all();
    }

    protected function receivingLocationOptions(int $companyId): array
    {
        $candidateTables = [
            'warehouse_locations',
            'inventory_locations',
            'stock_locations',
            'locations',
        ];

        $options = [];

        foreach ($candidateTables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);

            $rows = DB::table($table)
                ->orderBy(in_array('warehouse_id', $columns, true) ? 'warehouse_id' : 'id')
                ->orderBy(in_array('name', $columns, true) ? 'name' : 'id')
                ->get();

            foreach ($rows as $row) {
                if (! $this->isUsableReceivingLocation($row, $columns, $companyId)) {
                    continue;
                }

                $warehouseId = in_array('warehouse_id', $columns, true)
                    ? (int) ($row->warehouse_id ?? 0)
                    : 0;

                $key = $table . ':' . (int) $row->id;

                $options[$key] = [
                    'id' => (int) $row->id,
                    'warehouse_id' => $warehouseId,
                    'label' => $this->buildLabel($row, ['code', 'name', 'label', 'full_name', 'description']),
                    'source_table' => $table,
                ];
            }
        }

        uasort($options, fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return array_values($options);
    }

    protected function isUsableReceivingLocation(object $row, array $columns, int $companyId): bool
    {
        if ($companyId > 0 && in_array('company_id', $columns, true)) {
            $rowCompanyId = (int) ($row->company_id ?? 0);

            if ($rowCompanyId > 0 && $rowCompanyId !== $companyId) {
                return false;
            }
        }

        foreach (['is_active', 'active'] as $activeColumn) {
            if (in_array($activeColumn, $columns, true)) {
                $value = $row->{$activeColumn} ?? null;

                if ($value === 0 || $value === '0' || $value === false) {
                    return false;
                }
            }
        }

        $code = strtoupper(trim((string) ($row->code ?? '')));
        $name = strtoupper(trim((string) ($row->name ?? '')));
        $label = strtoupper(trim((string) ($row->label ?? '')));
        $description = strtoupper(trim((string) ($row->description ?? '')));

        $text = trim($code . ' ' . $name . ' ' . $label . ' ' . $description);

        if ($text === '') {
            return false;
        }

        $excludedExact = [
            'STOCK',
            'RECEPCION',
            'RECEPCIÓN',
            'DESPACHO',
            'AJUSTES',
            'EXISTENCIAS',
            'TRANSITO',
            'TRÁNSITO',
            'PROVEEDORES',
            'CLIENTES',
            'PERDIDA',
            'PÉRDIDA',
            'PRODUCCION',
            'PRODUCCIÓN',
        ];

        if (in_array($code, $excludedExact, true) || in_array($name, $excludedExact, true)) {
            return false;
        }

        /*
         * Si es una ubicación estándar interna, normalmente el código y nombre son iguales:
         * STOCK - Stock, RECEPCION - Recepción, DESPACHO - Despacho, AJUSTES - Ajustes.
         */
        foreach ($excludedExact as $excluded) {
            if ($code === $excluded || $name === $excluded) {
                return false;
            }
        }

        /*
         * Aceptamos explícitamente ubicaciones tipo CDF / CEDIS / centro de distribución.
         */
        if (
            str_contains($code, 'CDF')
            || str_contains($name, 'CEDIS')
            || str_contains($description, 'CEDIS')
            || str_contains($text, 'CENTRO DE DISTRIB')
        ) {
            return true;
        }

        /*
         * También aceptamos ubicaciones personalizadas que no sean las internas estándar.
         */
        return true;
    }

    protected function buildLabel(object $row, array $columns): string
    {
        $parts = [];

        foreach ($columns as $column) {
            if (property_exists($row, $column) && trim((string) $row->{$column}) !== '') {
                $parts[] = trim((string) $row->{$column});
            }
        }

        $parts = array_values(array_unique($parts));

        return $parts ? implode(' - ', $parts) : ('ID ' . ($row->id ?? ''));
    }
public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('purchases.view')
            );
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('purchases.view')
            );
    }

}
