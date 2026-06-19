<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['service_cases', 'repair_orders'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'sale_reference')) {
                    $table->string('sale_reference')->nullable()->index();
                }

                if (! Schema::hasColumn($tableName, 'invoice_reference')) {
                    $table->string('invoice_reference')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['service_cases', 'repair_orders'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach (['invoice_reference', 'sale_reference'] as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    Schema::table($tableName, function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};
