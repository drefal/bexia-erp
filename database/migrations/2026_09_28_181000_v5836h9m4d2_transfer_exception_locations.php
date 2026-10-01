<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('warehouses')
            || ! Schema::hasTable('stock_locations')
            || ! Schema::hasTable('stock_location_types')
        ) {
            return;
        }

        $internalTypeId = DB::table('stock_location_types')
            ->whereNull('company_id')
            ->where('code', 'INTERNAL')
            ->value('id');

        $transitTypeId = DB::table('stock_location_types')
            ->whereNull('company_id')
            ->where('code', 'TRANSIT')
            ->value('id');

        if (! $internalTypeId || ! $transitTypeId) {
            throw new RuntimeException(
                'Faltan tipos INTERNAL o TRANSIT en stock_location_types.'
            );
        }

        $warehouses = DB::table('warehouses')
            ->whereNotNull('company_id')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'company_id']);

        foreach ($warehouses as $warehouse) {
            $exists = DB::table('stock_locations')
                ->where('company_id', $warehouse->company_id)
                ->where('warehouse_id', $warehouse->id)
                ->whereRaw("upper(code)='CUARENTENA'")
                ->exists();

            if (! $exists) {
                DB::table('stock_locations')->insert([
                    'company_id' => $warehouse->company_id,
                    'warehouse_id' => $warehouse->id,
                    'parent_id' => null,
                    'stock_location_type_id' => $internalTypeId,
                    'code' => 'CUARENTENA',
                    'name' => 'Cuarentena / dañados',
                    'is_active' => true,
                    'allow_negative_stock' => false,
                    'tracks_stock' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $companyIds = DB::table('warehouses')
            ->whereNotNull('company_id')
            ->distinct()
            ->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $exists = DB::table('stock_locations')
                ->where('company_id', $companyId)
                ->whereNull('warehouse_id')
                ->whereRaw("upper(code)='FALTANTE_TRANSITO'")
                ->exists();

            if (! $exists) {
                DB::table('stock_locations')->insert([
                    'company_id' => $companyId,
                    'warehouse_id' => null,
                    'parent_id' => null,
                    'stock_location_type_id' => $transitTypeId,
                    'code' => 'FALTANTE_TRANSITO',
                    'name' => 'Faltante en investigación',
                    'is_active' => true,
                    'allow_negative_stock' => false,
                    'tracks_stock' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('stock_locations')
            ->whereRaw("upper(code)='CUARENTENA'")
            ->where('name', 'Cuarentena / dañados')
            ->delete();

        DB::table('stock_locations')
            ->whereRaw("upper(code)='FALTANTE_TRANSITO'")
            ->where('name', 'Faltante en investigación')
            ->delete();
    }
};
