<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_orders', 'created_from_xml')) {
                    $table->boolean('created_from_xml')->default(false)->index();
                }

                if (! Schema::hasColumn('purchase_orders', 'xml_uuid')) {
                    $table->string('xml_uuid', 80)->nullable()->index();
                }

                if (! Schema::hasColumn('purchase_orders', 'xml_supplier_rfc')) {
                    $table->string('xml_supplier_rfc', 30)->nullable()->index();
                }

                if (! Schema::hasColumn('purchase_orders', 'xml_supplier_name')) {
                    $table->string('xml_supplier_name', 255)->nullable();
                }

                if (! Schema::hasColumn('purchase_orders', 'xml_receiver_rfc')) {
                    $table->string('xml_receiver_rfc', 30)->nullable();
                }

                if (! Schema::hasColumn('purchase_orders', 'xml_issued_at')) {
                    $table->timestamp('xml_issued_at')->nullable();
                }

                if (! Schema::hasColumn('purchase_orders', 'xml_currency')) {
                    $table->string('xml_currency', 10)->nullable();
                }

                if (! Schema::hasColumn('purchase_orders', 'xml_subtotal')) {
                    $table->decimal('xml_subtotal', 18, 6)->nullable();
                }

                if (! Schema::hasColumn('purchase_orders', 'xml_total')) {
                    $table->decimal('xml_total', 18, 6)->nullable();
                }

                if (! Schema::hasColumn('purchase_orders', 'xml_path')) {
                    $table->string('xml_path', 600)->nullable();
                }

                if (! Schema::hasColumn('purchase_orders', 'xml_import_status')) {
                    $table->string('xml_import_status', 80)->nullable()->index();
                }

                if (! Schema::hasColumn('purchase_orders', 'xml_mapping_pending_count')) {
                    $table->unsignedInteger('xml_mapping_pending_count')->default(0);
                }
            });
        }

        if (Schema::hasTable('purchase_order_lines')) {
            Schema::table('purchase_order_lines', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_order_lines', 'xml_line_index')) {
                    $table->unsignedInteger('xml_line_index')->nullable();
                }

                if (! Schema::hasColumn('purchase_order_lines', 'xml_no_identificacion')) {
                    $table->string('xml_no_identificacion', 120)->nullable()->index();
                }

                if (! Schema::hasColumn('purchase_order_lines', 'xml_description')) {
                    $table->text('xml_description')->nullable();
                }

                if (! Schema::hasColumn('purchase_order_lines', 'xml_unit_key')) {
                    $table->string('xml_unit_key', 30)->nullable();
                }

                if (! Schema::hasColumn('purchase_order_lines', 'xml_unit_name')) {
                    $table->string('xml_unit_name', 120)->nullable();
                }

                if (! Schema::hasColumn('purchase_order_lines', 'xml_requires_mapping')) {
                    $table->boolean('xml_requires_mapping')->default(false)->index();
                }

                if (! Schema::hasColumn('purchase_order_lines', 'xml_mapping_status')) {
                    $table->string('xml_mapping_status', 50)->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        // No eliminamos columnas para no perder información importada de XML.
    }
};
