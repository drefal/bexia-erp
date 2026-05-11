<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function bxNow(): string
{
    return now()->toDateTimeString();
}

function bxTitle(string $title): void
{
    echo PHP_EOL;
    echo "======================================" . PHP_EOL;
    echo $title . PHP_EOL;
    echo "======================================" . PHP_EOL;
}

function bxUpsert(string $table, array $keys, array $values): void
{
    $query = DB::table($table);

    foreach ($keys as $column => $value) {
        $query->where($column, $value);
    }

    if ($query->exists()) {
        DB::table($table)->where($keys)->update(array_merge($values, [
            'updated_at' => bxNow(),
        ]));

        return;
    }

    DB::table($table)->insert(array_merge($keys, $values, [
        'created_at' => bxNow(),
        'updated_at' => bxNow(),
    ]));
}

foreach ([
    'companies',
    'branches',
    'accounting_settings',
    'inventory_units',
    'product_categories',
    'inventory_location_types',
    'warehouses',
    'warehouse_locations',
] as $table) {
    if (! Schema::hasTable($table)) {
        throw new RuntimeException("Falta tabla requerida: {$table}");
    }
}

$units = [
    ['code' => 'PZA', 'name' => 'Pieza', 'type' => 'unit'],
    ['code' => 'CAJA', 'name' => 'Caja', 'type' => 'unit'],
    ['code' => 'KG', 'name' => 'Kilogramo', 'type' => 'weight'],
    ['code' => 'LT', 'name' => 'Litro', 'type' => 'volume'],
    ['code' => 'M', 'name' => 'Metro', 'type' => 'length'],
];

$locationTypes = [
    ['code' => 'INTERNAL', 'name' => 'Interna', 'behavior' => 'internal'],
    ['code' => 'SUPPLIER', 'name' => 'Proveedor', 'behavior' => 'supplier'],
    ['code' => 'CUSTOMER', 'name' => 'Cliente', 'behavior' => 'customer'],
    ['code' => 'TRANSIT', 'name' => 'Tránsito', 'behavior' => 'transit'],
    ['code' => 'ADJUSTMENT', 'name' => 'Ajuste de inventario', 'behavior' => 'adjustment'],
    ['code' => 'SCRAP', 'name' => 'Merma / desecho', 'behavior' => 'scrap'],
];

bxTitle('1) Empresas');

$companies = DB::table('companies')
    ->select('id', 'name')
    ->orderBy('id')
    ->get();

$companies->each(fn ($company) => print "Empresa {$company->id}: {$company->name}" . PHP_EOL);

bxTitle('2) Sembrando base');

DB::transaction(function () use ($companies, $units, $locationTypes): void {
    foreach ($companies as $company) {
        echo PHP_EOL . "Empresa {$company->id}: {$company->name}" . PHP_EOL;

        foreach ($units as $unit) {
            bxUpsert('inventory_units', [
                'company_id' => $company->id,
                'code' => $unit['code'],
            ], [
                'name' => $unit['name'],
                'type' => $unit['type'],
                'is_active' => true,
                'is_system' => true,
            ]);

            echo "Unidad {$unit['code']} {$unit['name']}" . PHP_EOL;
        }

        $settings = DB::table('accounting_settings')
            ->where('company_id', $company->id)
            ->first();

        bxUpsert('product_categories', [
            'company_id' => $company->id,
            'code' => 'GENERAL',
        ], [
            'parent_id' => null,
            'name' => 'General',
            'inventory_account_id' => $settings?->inventory_account_id,
            'cogs_account_id' => $settings?->cogs_account_id,
            'sales_income_account_id' => $settings?->sales_income_account_id,
            'is_active' => true,
            'description' => 'Categoría base de productos.',
        ]);

        echo "Categoría GENERAL" . PHP_EOL;

        foreach ($locationTypes as $type) {
            bxUpsert('inventory_location_types', [
                'company_id' => $company->id,
                'code' => $type['code'],
            ], [
                'name' => $type['name'],
                'behavior' => $type['behavior'],
                'is_system' => true,
                'is_active' => true,
            ]);

            echo "Tipo ubicación {$type['code']} {$type['name']}" . PHP_EOL;
        }

        $internalTypeId = DB::table('inventory_location_types')
            ->where('company_id', $company->id)
            ->where('code', 'INTERNAL')
            ->value('id');

        $adjustmentTypeId = DB::table('inventory_location_types')
            ->where('company_id', $company->id)
            ->where('code', 'ADJUSTMENT')
            ->value('id');

        $branches = DB::table('branches')
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->get();

        if ($branches->isEmpty()) {
            bxUpsert('warehouses', [
                'company_id' => $company->id,
                'code' => 'GEN',
            ], [
                'branch_id' => null,
                'name' => 'Almacén General',
                'is_active' => true,
                'notes' => 'Almacén base creado automáticamente.',
            ]);

            echo "Almacén GEN Almacén General" . PHP_EOL;
        } else {
            foreach ($branches as $branch) {
                $branchCode = $branch->code ?: ('SUC' . $branch->id);
                $warehouseCode = 'ALM-' . $branchCode;

                bxUpsert('warehouses', [
                    'company_id' => $company->id,
                    'code' => $warehouseCode,
                ], [
                    'branch_id' => $branch->id,
                    'name' => 'Almacén ' . $branch->name,
                    'is_active' => true,
                    'notes' => 'Almacén base creado automáticamente para sucursal.',
                ]);

                echo "Almacén {$warehouseCode} Almacén {$branch->name}" . PHP_EOL;
            }
        }

        $warehouses = DB::table('warehouses')
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->get();

        foreach ($warehouses as $warehouse) {
            foreach ([
                ['code' => 'STOCK', 'name' => 'Stock', 'type_id' => $internalTypeId],
                ['code' => 'RECEPCION', 'name' => 'Recepción', 'type_id' => $internalTypeId],
                ['code' => 'DESPACHO', 'name' => 'Despacho', 'type_id' => $internalTypeId],
                ['code' => 'AJUSTES', 'name' => 'Ajustes', 'type_id' => $adjustmentTypeId],
            ] as $location) {
                bxUpsert('warehouse_locations', [
                    'warehouse_id' => $warehouse->id,
                    'code' => $location['code'],
                ], [
                    'company_id' => $company->id,
                    'parent_id' => null,
                    'inventory_location_type_id' => $location['type_id'],
                    'name' => $location['name'],
                    'barcode' => null,
                    'is_active' => true,
                    'notes' => 'Ubicación base creada automáticamente.',
                ]);

                echo "Ubicación {$warehouse->code}/{$location['code']} {$location['name']}" . PHP_EOL;
            }
        }
    }
});

bxTitle('3) Resumen');

foreach ($companies as $company) {
    $unitsCount = DB::table('inventory_units')->where('company_id', $company->id)->count();
    $categoriesCount = DB::table('product_categories')->where('company_id', $company->id)->count();
    $typesCount = DB::table('inventory_location_types')->where('company_id', $company->id)->count();
    $warehousesCount = DB::table('warehouses')->where('company_id', $company->id)->count();
    $locationsCount = DB::table('warehouse_locations')->where('company_id', $company->id)->count();

    echo "Empresa {$company->id}: unidades={$unitsCount}, categorias={$categoriesCount}, tipos_ubicacion={$typesCount}, almacenes={$warehousesCount}, ubicaciones={$locationsCount}" . PHP_EOL;
}
