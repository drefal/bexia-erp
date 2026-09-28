<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_movements', 'destination_warehouse_id')) {
                $table->foreignId('destination_warehouse_id')
                    ->nullable()
                    ->after('warehouse_id')
                    ->constrained('warehouses')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('stock_movements', 'transit_location_id')) {
                $table->foreignId('transit_location_id')
                    ->nullable()
                    ->after('destination_location_id')
                    ->constrained('stock_locations')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('stock_movements', 'dispatched_by')) {
                $table->unsignedBigInteger('dispatched_by')
                    ->nullable()
                    ->after('confirmed_at');
            }

            if (! Schema::hasColumn('stock_movements', 'dispatched_at')) {
                $table->timestamp('dispatched_at')
                    ->nullable()
                    ->after('dispatched_by');
            }

            if (! Schema::hasColumn('stock_movements', 'received_by')) {
                $table->unsignedBigInteger('received_by')
                    ->nullable()
                    ->after('dispatched_at');
            }

            if (! Schema::hasColumn('stock_movements', 'received_at')) {
                $table->timestamp('received_at')
                    ->nullable()
                    ->after('received_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            foreach ([
                'destination_warehouse_id',
                'transit_location_id',
            ] as $column) {
                if (Schema::hasColumn('stock_movements', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach ([
                'dispatched_by',
                'dispatched_at',
                'received_by',
                'received_at',
            ] as $column) {
                if (Schema::hasColumn('stock_movements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
