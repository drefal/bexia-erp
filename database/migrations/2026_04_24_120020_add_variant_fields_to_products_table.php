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
            if (! Schema::hasColumn('products', 'product_template_id')) {
                $table->foreignId('product_template_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('product_templates')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('products', 'variant_name')) {
                $table->string('variant_name')->nullable()->after('name');
            }

            if (! Schema::hasColumn('products', 'variant_signature')) {
                $table->string('variant_signature')->nullable()->after('variant_name');
            }

            if (! Schema::hasColumn('products', 'is_variant')) {
                $table->boolean('is_variant')->default(false)->after('variant_signature');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            try {
                $table->index(['company_id', 'product_template_id'], 'products_company_template_idx');
            } catch (Throwable $e) {
                //
            }

            try {
                $table->unique(['product_template_id', 'variant_signature'], 'products_template_variant_signature_unique');
            } catch (Throwable $e) {
                //
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach ([
                'is_variant',
                'variant_signature',
                'variant_name',
                'product_template_id',
            ] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
