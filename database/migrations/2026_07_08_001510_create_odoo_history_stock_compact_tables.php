<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('odoo_history_stock_moves')) {
            Schema::create('odoo_history_stock_moves', function (Blueprint $table) {
                $table->id();
                $table->string('period_label')->index();
                $table->string('month_key', 7)->index();
                $table->string('source_file')->index();
                $table->bigInteger('odoo_id')->index();
                $table->bigInteger('odoo_company_id')->nullable()->index();
                $table->bigInteger('odoo_product_id')->nullable()->index();
                $table->bigInteger('odoo_picking_id')->nullable()->index();
                $table->bigInteger('odoo_location_id')->nullable()->index();
                $table->bigInteger('odoo_location_dest_id')->nullable()->index();
                $table->text('name')->nullable();
                $table->text('reference')->nullable();
                $table->text('origin')->nullable();
                $table->string('state')->nullable()->index();
                $table->timestamp('record_date')->nullable()->index();
                $table->decimal('product_uom_qty', 24, 6)->default(0);
                $table->decimal('price_unit', 24, 6)->default(0);
                $table->timestamp('create_date')->nullable();
                $table->timestamp('write_date')->nullable();
                $table->timestamps();

                $table->unique(['period_label', 'odoo_id'], 'odoo_hist_stock_moves_unique');
            });
        }

        if (! Schema::hasTable('odoo_history_stock_move_lines')) {
            Schema::create('odoo_history_stock_move_lines', function (Blueprint $table) {
                $table->id();
                $table->string('period_label')->index();
                $table->string('month_key', 7)->index();
                $table->string('source_file')->index();
                $table->bigInteger('odoo_id')->index();
                $table->bigInteger('odoo_company_id')->nullable()->index();
                $table->bigInteger('odoo_product_id')->nullable()->index();
                $table->bigInteger('odoo_move_id')->nullable()->index();
                $table->bigInteger('odoo_picking_id')->nullable()->index();
                $table->bigInteger('odoo_lot_id')->nullable()->index();
                $table->bigInteger('odoo_location_id')->nullable()->index();
                $table->bigInteger('odoo_location_dest_id')->nullable()->index();
                $table->text('reference')->nullable();
                $table->text('lot_name')->nullable();
                $table->string('state')->nullable()->index();
                $table->timestamp('record_date')->nullable()->index();
                $table->decimal('qty_done', 24, 6)->default(0);
                $table->timestamp('create_date')->nullable();
                $table->timestamp('write_date')->nullable();
                $table->timestamps();

                $table->unique(['period_label', 'odoo_id'], 'odoo_hist_stock_move_lines_unique');
            });
        }

        if (! Schema::hasTable('odoo_history_stock_valuation_layers')) {
            Schema::create('odoo_history_stock_valuation_layers', function (Blueprint $table) {
                $table->id();
                $table->string('period_label')->index();
                $table->string('month_key', 7)->index();
                $table->string('source_file')->index();
                $table->bigInteger('odoo_id')->index();
                $table->bigInteger('odoo_company_id')->nullable()->index();
                $table->bigInteger('odoo_product_id')->nullable()->index();
                $table->bigInteger('odoo_stock_move_id')->nullable()->index();
                $table->bigInteger('odoo_account_move_id')->nullable()->index();
                $table->text('description')->nullable();
                $table->timestamp('record_date')->nullable()->index();
                $table->decimal('quantity', 24, 6)->default(0);
                $table->decimal('unit_cost', 24, 6)->default(0);
                $table->decimal('value', 24, 6)->default(0);
                $table->decimal('remaining_qty', 24, 6)->default(0);
                $table->decimal('remaining_value', 24, 6)->default(0);
                $table->timestamp('write_date')->nullable();
                $table->timestamps();

                $table->unique(['period_label', 'odoo_id'], 'odoo_hist_stock_val_layers_unique');
            });
        }

        if (! Schema::hasTable('odoo_history_compact_import_runs')) {
            Schema::create('odoo_history_compact_import_runs', function (Blueprint $table) {
                $table->id();
                $table->string('version')->index();
                $table->string('period_label')->index();
                $table->string('bucket')->index();
                $table->string('status')->index();
                $table->unsignedInteger('files_processed')->default(0);
                $table->unsignedInteger('rows_inserted')->default(0);
                $table->json('raw_json')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_history_compact_import_runs');
        Schema::dropIfExists('odoo_history_stock_valuation_layers');
        Schema::dropIfExists('odoo_history_stock_move_lines');
        Schema::dropIfExists('odoo_history_stock_moves');
    }
};
