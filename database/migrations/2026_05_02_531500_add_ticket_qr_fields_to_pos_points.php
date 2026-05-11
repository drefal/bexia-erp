<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_points')) {
            return;
        }

        Schema::table('pos_points', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_points', 'invoice_qr_url')) {
                $table->string('invoice_qr_url')->nullable();
            }

            if (! Schema::hasColumn('pos_points', 'show_order_reference_on_ticket')) {
                $table->boolean('show_order_reference_on_ticket')->default(true);
            }
        });
    }

    public function down(): void
    {
        // No se eliminan columnas para evitar pérdida de configuración.
    }
};
