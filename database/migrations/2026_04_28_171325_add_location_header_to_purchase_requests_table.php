<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_requests')) {
            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_requests', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->index()->after('supplier_name');
            }

            if (! Schema::hasColumn('purchase_requests', 'location_id')) {
                $table->unsignedBigInteger('location_id')->nullable()->index()->after('warehouse_id');
            }

            if (! Schema::hasColumn('purchase_requests', 'warehouse_label')) {
                $table->string('warehouse_label')->nullable()->after('location_id');
            }

            if (! Schema::hasColumn('purchase_requests', 'location_label')) {
                $table->string('location_label')->nullable()->after('warehouse_label');
            }
        });

        if (Schema::hasTable('purchase_request_lines')) {
            $requests = DB::table('purchase_requests')
                ->whereNull('location_id')
                ->get(['id']);

            foreach ($requests as $request) {
                $line = DB::table('purchase_request_lines')
                    ->where('purchase_request_id', $request->id)
                    ->orderBy('id')
                    ->first();

                if (! $line) {
                    continue;
                }

                DB::table('purchase_requests')
                    ->where('id', $request->id)
                    ->update([
                        'warehouse_id' => $line->warehouse_id ?? null,
                        'location_id' => $line->location_id ?? null,
                        'warehouse_label' => $line->warehouse_label ?? null,
                        'location_label' => $line->location_label ?? null,
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_requests')) {
            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table): void {
            foreach (['location_label', 'warehouse_label', 'location_id', 'warehouse_id'] as $column) {
                if (Schema::hasColumn('purchase_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
