<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'pos_sales_areas')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->json('pos_sales_areas')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Reversion conservadora: no eliminar datos de areas.
        // Una eliminacion requiere migracion especifica auditada.
    }
};
