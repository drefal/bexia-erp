<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sat_unit_codes')) {
            Schema::create('sat_unit_codes', function (Blueprint $table) {
                $table->id();
                $table->string('type')->nullable();
                $table->string('code', 20)->unique();
                $table->string('name');
                $table->string('symbol', 30)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active']);
                $table->index(['type']);
            });

            return;
        }

        Schema::table('sat_unit_codes', function (Blueprint $table) {
            if (! Schema::hasColumn('sat_unit_codes', 'type')) {
                $table->string('type')->nullable()->after('id');
            }
        });

        Schema::table('sat_unit_codes', function (Blueprint $table) {
            try {
                $table->index(['type'], 'sat_unit_codes_type_idx');
            } catch (Throwable $e) {
                //
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sat_unit_codes')) {
            return;
        }

        Schema::table('sat_unit_codes', function (Blueprint $table) {
            if (Schema::hasColumn('sat_unit_codes', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
