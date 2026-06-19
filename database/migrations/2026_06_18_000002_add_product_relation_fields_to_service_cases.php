<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_cases')) {
            return;
        }

        Schema::table('service_cases', function (Blueprint $table) {
            if (! Schema::hasColumn('service_cases', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->index();
            }

            if (! Schema::hasColumn('service_cases', 'product_name')) {
                $table->string('product_name')->nullable();
            }

            if (! Schema::hasColumn('service_cases', 'serial_number')) {
                $table->string('serial_number')->nullable()->index();
            }

            if (! Schema::hasColumn('service_cases', 'lot_number')) {
                $table->string('lot_number')->nullable()->index();
            }

            if (! Schema::hasColumn('service_cases', 'sale_id')) {
                $table->unsignedBigInteger('sale_id')->nullable()->index();
            }

            if (! Schema::hasColumn('service_cases', 'invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_cases')) {
            return;
        }

        foreach ([
            'invoice_id',
            'sale_id',
            'lot_number',
            'serial_number',
            'product_name',
            'product_id',
        ] as $column) {
            if (Schema::hasColumn('service_cases', $column)) {
                Schema::table('service_cases', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
