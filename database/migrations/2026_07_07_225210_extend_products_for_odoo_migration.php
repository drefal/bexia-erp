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

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'odoo_product_id')) {
                $table->bigInteger('odoo_product_id')->nullable()->unique();
            }
            if (! Schema::hasColumn('products', 'odoo_template_id')) {
                $table->bigInteger('odoo_template_id')->nullable()->index();
            }
            if (! Schema::hasColumn('products', 'odoo_category_id')) {
                $table->bigInteger('odoo_category_id')->nullable()->index();
            }
            if (! Schema::hasColumn('products', 'odoo_category_name')) {
                $table->string('odoo_category_name')->nullable();
            }
            if (! Schema::hasColumn('products', 'odoo_tracking')) {
                $table->string('odoo_tracking')->nullable()->index();
            }
            if (! Schema::hasColumn('products', 'odoo_migration_notes')) {
                $table->text('odoo_migration_notes')->nullable();
            }
            if (! Schema::hasColumn('products', 'odoo_raw_json')) {
                $table->json('odoo_raw_json')->nullable();
            }
        });
    }

    public function down(): void
    {
        // No destructive rollback for migration staging.
    }
};
