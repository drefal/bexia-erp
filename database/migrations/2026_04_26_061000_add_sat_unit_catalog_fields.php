<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sat_unit_codes')) {
            return;
        }

        Schema::table('sat_unit_codes', function (Blueprint $table): void {
            if (! Schema::hasColumn('sat_unit_codes', 'description')) {
                $table->text('description')->nullable()->after('name');
            }

            if (! Schema::hasColumn('sat_unit_codes', 'note')) {
                $table->text('note')->nullable()->after('description');
            }

            if (! Schema::hasColumn('sat_unit_codes', 'valid_from')) {
                $table->date('valid_from')->nullable()->after('symbol');
            }

            if (! Schema::hasColumn('sat_unit_codes', 'valid_to')) {
                $table->date('valid_to')->nullable()->after('valid_from');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sat_unit_codes')) {
            return;
        }

        Schema::table('sat_unit_codes', function (Blueprint $table): void {
            foreach (['valid_to', 'valid_from', 'note', 'description'] as $column) {
                if (Schema::hasColumn('sat_unit_codes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
