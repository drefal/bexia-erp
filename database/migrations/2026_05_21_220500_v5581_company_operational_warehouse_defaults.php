<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'default_warehouse_id')) {
                $table->unsignedBigInteger('default_warehouse_id')->nullable()->index();
            }

            if (! Schema::hasColumn('companies', 'default_location_id')) {
                $table->unsignedBigInteger('default_location_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'default_location_id')) {
                $table->dropColumn('default_location_id');
            }

            if (Schema::hasColumn('companies', 'default_warehouse_id')) {
                $table->dropColumn('default_warehouse_id');
            }
        });
    }
};
