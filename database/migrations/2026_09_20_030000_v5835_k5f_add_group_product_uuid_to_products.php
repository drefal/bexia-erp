<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        if (! Schema::hasColumn('products', 'group_product_uuid')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->uuid('group_product_uuid')->nullable();
            });
        }

        DB::statement(
            'CREATE INDEX IF NOT EXISTS products_group_product_uuid_idx
             ON products (group_product_uuid)'
        );

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS products_company_group_product_uuid_unique
             ON products (company_id, group_product_uuid)
             WHERE group_product_uuid IS NOT NULL'
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        DB::statement(
            'DROP INDEX IF EXISTS products_company_group_product_uuid_unique'
        );

        DB::statement(
            'DROP INDEX IF EXISTS products_group_product_uuid_idx'
        );

        if (Schema::hasColumn('products', 'group_product_uuid')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('group_product_uuid');
            });
        }
    }
};
