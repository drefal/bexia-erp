<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('repair_order_parts')) {
            Schema::create('repair_order_parts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('repair_order_id')->nullable()->index();
                $table->string('source_type')->default('manual')->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('product_name')->nullable();
                $table->text('description')->nullable();
                $table->decimal('quantity', 14, 4)->default(1);
                $table->decimal('unit_cost', 14, 2)->nullable();
                $table->decimal('unit_price', 14, 2)->nullable();
                $table->decimal('total_cost', 14, 2)->nullable();
                $table->decimal('total_price', 14, 2)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('repair_order_parts')) {
            Schema::table('repair_order_parts', function (Blueprint $table) {
                if (! Schema::hasColumn('repair_order_parts', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->index();
                }

                if (! Schema::hasColumn('repair_order_parts', 'source_type')) {
                    $table->string('source_type')->default('manual')->index();
                }

                if (! Schema::hasColumn('repair_order_parts', 'product_id')) {
                    $table->unsignedBigInteger('product_id')->nullable()->index();
                }

                if (! Schema::hasColumn('repair_order_parts', 'product_name')) {
                    $table->string('product_name')->nullable();
                }

                if (! Schema::hasColumn('repair_order_parts', 'description')) {
                    $table->text('description')->nullable();
                }

                if (! Schema::hasColumn('repair_order_parts', 'quantity')) {
                    $table->decimal('quantity', 14, 4)->default(1);
                }

                if (! Schema::hasColumn('repair_order_parts', 'unit_cost')) {
                    $table->decimal('unit_cost', 14, 2)->nullable();
                }

                if (! Schema::hasColumn('repair_order_parts', 'unit_price')) {
                    $table->decimal('unit_price', 14, 2)->nullable();
                }

                if (! Schema::hasColumn('repair_order_parts', 'total_cost')) {
                    $table->decimal('total_cost', 14, 2)->nullable();
                }

                if (! Schema::hasColumn('repair_order_parts', 'total_price')) {
                    $table->decimal('total_price', 14, 2)->nullable();
                }

                if (! Schema::hasColumn('repair_order_parts', 'notes')) {
                    $table->text('notes')->nullable();
                }
            });
        }

        if (Schema::hasTable('repair_orders')) {
            Schema::table('repair_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('repair_orders', 'internal_approval_flow_id')) {
                    $table->unsignedBigInteger('internal_approval_flow_id')->nullable()->index();
                }

                if (! Schema::hasColumn('repair_orders', 'internal_approval_document_type')) {
                    $table->string('internal_approval_document_type')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('repair_orders')) {
            foreach ([
                'internal_approval_document_type',
                'internal_approval_flow_id',
            ] as $column) {
                if (Schema::hasColumn('repair_orders', $column)) {
                    Schema::table('repair_orders', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};
