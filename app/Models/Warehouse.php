<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class Warehouse extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(function (Warehouse $warehouse): void {
            try {
                static::ensureInventoryDefaults($warehouse);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    public function locations(): HasMany
    {
        return $this->hasMany(StockLocation::class);
    }

    public static function ensureInventoryDefaults(Warehouse $warehouse): void
    {
        if (! Schema::hasTable('stock_locations')) {
            return;
        }

        static::ensureLocationTypes();

        $companyId = $warehouse->company_id ? (int) $warehouse->company_id : null;
        $warehouseId = (int) $warehouse->id;

        $internalTypeId = static::locationTypeId('INTERNAL');
        $supplierTypeId = static::locationTypeId('SUPPLIER');
        $customerTypeId = static::locationTypeId('CUSTOMER');
        $transitTypeId = static::locationTypeId('TRANSIT');
        $inventoryTypeId = static::locationTypeId('INVENTORY');
        $lossTypeId = static::locationTypeId('LOSS');
        $productionTypeId = static::locationTypeId('PRODUCTION');

        // Ubicación física principal:
        // Si el almacén ya tiene una ubicación interna real, se usa esa.
        // Si no tiene ninguna, se crea EXISTENCIAS.
        $stockLocationId = static::preferredInternalLocationId($companyId, $warehouseId);

        if (! $stockLocationId) {
            $stockLocationId = static::ensureLocation(
                companyId: $companyId,
                warehouseId: $warehouseId,
                code: 'EXISTENCIAS',
                name: 'Existencias',
                typeId: $internalTypeId,
                parentId: null,
                allowNegative: false
            );
        }

        // Ubicaciones virtuales de la empresa.
        $supplierLocationId = static::ensureLocation($companyId, null, 'PROVEEDORES', 'Proveedores', $supplierTypeId, null, false);
        $customerLocationId = static::ensureLocation($companyId, null, 'CLIENTES', 'Clientes', $customerTypeId, null, false);
        $inventoryLocationId = static::ensureLocation($companyId, null, 'AJUSTES', 'Ajustes de inventario', $inventoryTypeId, null, true);
        static::ensureLocation($companyId, null, 'PERDIDA', 'Pérdida / merma', $lossTypeId, null, true);
        $productionLocationId = static::ensureLocation($companyId, null, 'PRODUCCION', 'Producción', $productionTypeId, null, false);
        $transitLocationId = static::ensureLocation($companyId, null, 'TRANSITO', 'Tránsito', $transitTypeId, null, false);

        static::ensureOperationType(
            companyId: $companyId,
            warehouseId: $warehouseId,
            code: 'RECEPCION',
            name: 'Recepción',
            kind: 'receipt',
            prefix: 'IN',
            sequence: 10,
            sourceLocationId: $supplierLocationId,
            destinationLocationId: $stockLocationId
        );

        static::ensureOperationType(
            companyId: $companyId,
            warehouseId: $warehouseId,
            code: 'ENTREGA',
            name: 'Entrega',
            kind: 'delivery',
            prefix: 'OUT',
            sequence: 20,
            sourceLocationId: $stockLocationId,
            destinationLocationId: $customerLocationId
        );

        static::ensureOperationType(
            companyId: $companyId,
            warehouseId: $warehouseId,
            code: 'TRASLADO_INTERNO',
            name: 'Traslado interno',
            kind: 'internal_transfer',
            prefix: 'INT',
            sequence: 30,
            sourceLocationId: $stockLocationId,
            destinationLocationId: $transitLocationId
        );

        static::ensureOperationType(
            companyId: $companyId,
            warehouseId: $warehouseId,
            code: 'AJUSTE_INVENTARIO',
            name: 'Ajuste de inventario',
            kind: 'inventory_adjustment',
            prefix: 'AJU',
            sequence: 40,
            sourceLocationId: $inventoryLocationId,
            destinationLocationId: $stockLocationId
        );

        static::ensureOperationType(
            companyId: $companyId,
            warehouseId: $warehouseId,
            code: 'FABRICACION',
            name: 'Fabricación',
            kind: 'manufacturing',
            prefix: 'FAB',
            sequence: 50,
            sourceLocationId: $productionLocationId,
            destinationLocationId: $stockLocationId
        );
    }

    protected static function preferredInternalLocationId(?int $companyId, int $warehouseId): ?int
    {
        if (! Schema::hasTable('stock_locations')) {
            return null;
        }

        $query = DB::table('stock_locations')
            ->leftJoin('stock_location_types', 'stock_location_types.id', '=', 'stock_locations.stock_location_type_id')
            ->where('stock_locations.warehouse_id', $warehouseId)
            ->where('stock_locations.is_active', true)
            ->where(function ($query): void {
                $query
                    ->where('stock_location_types.is_internal', true)
                    ->orWhereNull('stock_location_types.id');
            });

        $companyId
            ? $query->where('stock_locations.company_id', $companyId)
            : $query->whereNull('stock_locations.company_id');

        // Priorizar ubicaciones reales creadas por el usuario sobre las genéricas.
        $nonGeneric = (clone $query)
            ->whereNotIn('stock_locations.code', ['STOCK', 'RECEPCION', 'DESPACHO', 'EXISTENCIAS'])
            ->orderBy('stock_locations.id')
            ->value('stock_locations.id');

        if ($nonGeneric) {
            return (int) $nonGeneric;
        }

        $any = $query
            ->orderBy('stock_locations.id')
            ->value('stock_locations.id');

        return $any ? (int) $any : null;
    }

    protected static function ensureLocationTypes(): void
    {
        if (! Schema::hasTable('stock_location_types')) {
            return;
        }

        $types = [
            ['code' => 'INTERNAL', 'name' => 'Interna', 'is_internal' => true],
            ['code' => 'SUPPLIER', 'name' => 'Proveedor', 'is_internal' => false],
            ['code' => 'CUSTOMER', 'name' => 'Cliente', 'is_internal' => false],
            ['code' => 'TRANSIT', 'name' => 'Tránsito', 'is_internal' => false],
            ['code' => 'INVENTORY', 'name' => 'Inventario / Ajuste', 'is_internal' => false],
            ['code' => 'LOSS', 'name' => 'Pérdida', 'is_internal' => false],
            ['code' => 'PRODUCTION', 'name' => 'Producción', 'is_internal' => false],
        ];

        foreach ($types as $type) {
            DB::table('stock_location_types')->updateOrInsert(
                ['company_id' => null, 'code' => $type['code']],
                [
                    'name' => $type['name'],
                    'is_internal' => $type['is_internal'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    protected static function locationTypeId(string $code): ?int
    {
        if (! Schema::hasTable('stock_location_types')) {
            return null;
        }

        return DB::table('stock_location_types')
            ->whereNull('company_id')
            ->where('code', $code)
            ->value('id');
    }

    protected static function ensureLocation(
        ?int $companyId,
        ?int $warehouseId,
        string $code,
        string $name,
        ?int $typeId,
        ?int $parentId = null,
        bool $allowNegative = false
    ): int {
        $code = static::cleanCode($code);

        $query = DB::table('stock_locations')->where('code', $code);

        $companyId ? $query->where('company_id', $companyId) : $query->whereNull('company_id');
        $warehouseId ? $query->where('warehouse_id', $warehouseId) : $query->whereNull('warehouse_id');

        $existingId = $query->value('id');

        $values = [
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'parent_id' => $parentId,
            'stock_location_type_id' => $typeId,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('stock_locations', 'allow_negative_stock')) {
            $values['allow_negative_stock'] = $allowNegative;
        }

        if ($existingId) {
            DB::table('stock_locations')->where('id', $existingId)->update($values);

            return (int) $existingId;
        }

        $values['created_at'] = now();

        return (int) DB::table('stock_locations')->insertGetId($values);
    }

    protected static function ensureOperationType(
        ?int $companyId,
        int $warehouseId,
        string $code,
        string $name,
        string $kind,
        string $prefix,
        int $sequence,
        ?int $sourceLocationId,
        ?int $destinationLocationId
    ): void {
        if (! Schema::hasTable('stock_operation_types')) {
            return;
        }

        DB::table('stock_operation_types')->updateOrInsert(
            [
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'code' => $code,
            ],
            [
                'name' => $name,
                'operation_kind' => $kind,
                'source_location_id' => $sourceLocationId,
                'destination_location_id' => $destinationLocationId,
                'reference_prefix' => $prefix,
                'next_number' => 1,
                'sequence' => $sequence,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    protected static function cleanCode(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 'SIN-CODIGO';
        }

        $value = Str::upper(Str::ascii($value));
        $value = preg_replace('/[^A-Z0-9]+/', '-', $value);
        $value = trim((string) $value, '-');

        return $value !== '' ? $value : 'SIN-CODIGO';
    }
}
