<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_categories')) {
            return;
        }

        Schema::table('product_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('product_categories', 'full_path')) {
                $table->string('full_path')->nullable()->after('name');
            }

            if (! Schema::hasColumn('product_categories', 'level')) {
                $table->unsignedInteger('level')->default(0)->after('full_path');
            }

            if (! Schema::hasColumn('product_categories', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('level');
            }
        });

        /*
         * Si por algún intento anterior se agregaron columnas legacy,
         * las quitamos porque el mapeo ahora será por code = CAT-{id}.
         */
        Schema::table('product_categories', function (Blueprint $table) {
            foreach ([
                'legacy_parent_id',
                'legacy_category_id',
                'legacy_source',
            ] as $column) {
                if (Schema::hasColumn('product_categories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('product_categories', function (Blueprint $table) {
            try {
                $table->index(['company_id', 'parent_id'], 'product_categories_company_parent_v2_idx');
            } catch (Throwable $e) {
                //
            }

            try {
                $table->index(['company_id', 'code'], 'product_categories_company_code_v2_idx');
            } catch (Throwable $e) {
                //
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_categories')) {
            return;
        }

        Schema::table('product_categories', function (Blueprint $table) {
            foreach ([
                'sort_order',
                'level',
                'full_path',
            ] as $column) {
                if (Schema::hasColumn('product_categories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
