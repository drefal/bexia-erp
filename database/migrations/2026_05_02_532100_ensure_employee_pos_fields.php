<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'is_pos_cashier')) {
                $table->boolean('is_pos_cashier')->default(false)->index();
            }

            if (! Schema::hasColumn('employees', 'is_pos_seller')) {
                $table->boolean('is_pos_seller')->default(false)->index();
            }

            if (! Schema::hasColumn('employees', 'pos_active')) {
                $table->boolean('pos_active')->default(true)->index();
            }

            if (! Schema::hasColumn('employees', 'pos_pin_hash')) {
                $table->string('pos_pin_hash')->nullable();
            }
        });
    }

    public function down(): void
    {
        // No se eliminan columnas para no perder configuración.
    }
};
