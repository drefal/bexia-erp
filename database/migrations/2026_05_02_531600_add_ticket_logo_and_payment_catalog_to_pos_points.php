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
            if (! Schema::hasColumn('pos_points', 'ticket_logo_path')) {
                $table->string('ticket_logo_path')->nullable();
            }

            if (! Schema::hasColumn('pos_points', 'payment_method_ids')) {
                $table->json('payment_method_ids')->nullable();
            }
        });
    }

    public function down(): void
    {
        // No se eliminan columnas para evitar pérdida de configuración.
    }
};
