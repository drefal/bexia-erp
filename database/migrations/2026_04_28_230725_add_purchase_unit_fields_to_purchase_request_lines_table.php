<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_request_lines', 'purchase_unit_type')) {
                $table->string('purchase_unit_type', 30)->default('piece');
            }

            if (! Schema::hasColumn('purchase_request_lines', 'purchase_unit_label')) {
                $table->string('purchase_unit_label', 80)->default('Pieza');
            }

            if (! Schema::hasColumn('purchase_request_lines', 'purchase_unit_factor')) {
                $table->decimal('purchase_unit_factor', 18, 6)->default(1);
            }

            if (! Schema::hasColumn('purchase_request_lines', 'base_quantity')) {
                $table->decimal('base_quantity', 18, 6)->default(0);
            }
        });

        DB::table('purchase_request_lines')
            ->whereNull('purchase_unit_type')
            ->update(['purchase_unit_type' => 'piece']);

        DB::table('purchase_request_lines')
            ->whereNull('purchase_unit_label')
            ->update(['purchase_unit_label' => 'Pieza']);

        DB::table('purchase_request_lines')
            ->where(function ($query) {
                $query->whereNull('purchase_unit_factor')
                    ->orWhere('purchase_unit_factor', '<=', 0);
            })
            ->update(['purchase_unit_factor' => 1]);

        DB::table('purchase_request_lines')
            ->where(function ($query) {
                $query->whereNull('base_quantity')
                    ->orWhere('base_quantity', '=', 0);
            })
            ->update([
                'base_quantity' => DB::raw('COALESCE(requested_quantity, 0)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('purchase_request_lines', function (Blueprint $table) {
            foreach (['purchase_unit_type', 'purchase_unit_label', 'purchase_unit_factor', 'base_quantity'] as $column) {
                if (Schema::hasColumn('purchase_request_lines', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
