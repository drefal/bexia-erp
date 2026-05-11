<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'sat_tax_object_code')) {
                $table->string('sat_tax_object_code', 10)
                    ->nullable()
                    ->after('sat_unit_code')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'sat_tax_object_code')) {
                $table->dropColumn('sat_tax_object_code');
            }
        });
    }
};
