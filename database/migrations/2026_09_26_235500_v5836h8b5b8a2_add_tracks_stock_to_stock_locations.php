<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_locations', function (Blueprint $table): void {
            $table->boolean('tracks_stock')
                ->default(false)
                ->index();
        });

        $stockTypeIds = DB::table('stock_location_types')
            ->whereIn('code', ['INTERNAL', 'TRANSIT'])
            ->pluck('id');

        DB::table('stock_locations')
            ->whereIn('stock_location_type_id', $stockTypeIds)
            ->update(['tracks_stock' => true]);

        /*
         * Empresa 16:
         * ubicaciones de traspaso confirmadas operativamente.
         * Se indican por ID + código para evitar afectar homónimos.
         */
        $transferLocations = [
            426 => 'OV704',
            427 => 'OV369',
            428 => 'OV370',
            429 => 'OV794',
            430 => 'OV760',
            431 => 'OV830',
        ];

        foreach ($transferLocations as $id => $code) {
            DB::table('stock_locations')
                ->where('id', $id)
                ->where('company_id', 16)
                ->where('code', $code)
                ->update(['tracks_stock' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('stock_locations', function (Blueprint $table): void {
            $table->dropIndex(['tracks_stock']);
            $table->dropColumn('tracks_stock');
        });
    }
};
