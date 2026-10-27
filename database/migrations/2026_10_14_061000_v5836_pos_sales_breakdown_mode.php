<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pos_points', 'sales_breakdown_mode')) {
            Schema::table('pos_points', function (Blueprint $table) {
                $table->string('sales_breakdown_mode', 20)
                    ->default('default');
            });
        }
    }

    public function down(): void
    {
        // Reversion conservadora: no eliminar configuracion existente.
        // Una eliminacion requiere migracion especifica auditada.
    }
};
