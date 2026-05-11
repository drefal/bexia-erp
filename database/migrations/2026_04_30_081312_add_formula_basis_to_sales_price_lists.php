<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_price_lists')) {
            return;
        }

        Schema::table('sales_price_lists', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_price_lists', 'formula_basis')) {
                $table->string('formula_basis')->default('price_list')->index();
            }
        });

        DB::table('sales_price_lists')
            ->whereNull('formula_basis')
            ->update(['formula_basis' => 'price_list']);

        DB::table('sales_price_lists')
            ->where('formula_basis', '')
            ->update(['formula_basis' => 'price_list']);
    }

    public function down(): void
    {
        // No eliminamos para no romper listas existentes.
    }
};
