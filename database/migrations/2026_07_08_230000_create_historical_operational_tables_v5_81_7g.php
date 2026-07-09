<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function addHistoricalBase(Blueprint $table, string $prefix): void
    {
        $table->id();

        $table->string('source_system', 32)->default('odoo')->index($prefix . '_srcsys_idx');
        $table->string('source_model', 128)->nullable()->index($prefix . '_srcmodel_idx');
        $table->string('source_file', 255)->nullable();

        $table->string('period_label', 64)->index($prefix . '_period_idx');
        $table->string('month_key', 16)->nullable()->index($prefix . '_month_idx');

        $table->unsignedBigInteger('odoo_id');
        $table->unsignedBigInteger('load_run_id')->nullable()->index($prefix . '_run_idx');

        $table->unsignedBigInteger('odoo_company_id')->nullable()->index($prefix . '_ocomp_idx');
        $table->unsignedBigInteger('company_id')->nullable()->index($prefix . '_comp_idx');

        $table->unsignedBigInteger('odoo_partner_id')->nullable()->index($prefix . '_opartner_idx');
        $table->unsignedBigInteger('contact_id')->nullable()->index($prefix . '_contact_idx');

        $table->unsignedBigInteger('odoo_product_id')->nullable()->index($prefix . '_oproduct_idx');
        $table->unsignedBigInteger('product_id')->nullable()->index($prefix . '_product_idx');

        $table->unsignedBigInteger('odoo_location_id')->nullable()->index($prefix . '_oloc_idx');
        $table->unsignedBigInteger('inventory_location_id')->nullable()->index($prefix . '_loc_idx');

        $table->unsignedBigInteger('odoo_location_dest_id')->nullable()->index($prefix . '_odloc_idx');
        $table->unsignedBigInteger('destination_inventory_location_id')->nullable()->index($prefix . '_dloc_idx');

        $table->unsignedBigInteger('odoo_account_id')->nullable()->index($prefix . '_oacc_idx');
        $table->unsignedBigInteger('account_dimension_id')->nullable()->index($prefix . '_accdim_idx');

        $table->unsignedBigInteger('odoo_journal_id')->nullable()->index($prefix . '_ojrn_idx');
        $table->unsignedBigInteger('journal_dimension_id')->nullable()->index($prefix . '_jrndim_idx');

        $table->unsignedBigInteger('odoo_parent_id')->nullable()->index($prefix . '_oparent_idx');
        $table->unsignedBigInteger('historical_parent_id')->nullable()->index($prefix . '_hparent_idx');

        $table->string('document_type', 64)->nullable()->index($prefix . '_doctype_idx');
        $table->string('number', 255)->nullable()->index($prefix . '_number_idx');
        $table->string('name', 1024)->nullable();
        $table->string('reference', 1024)->nullable();
        $table->string('origin', 1024)->nullable();
        $table->string('state', 128)->nullable()->index($prefix . '_state_idx');

        $table->timestamp('record_date')->nullable()->index($prefix . '_record_date_idx');
        $table->timestamp('posted_date')->nullable()->index($prefix . '_posted_date_idx');
        $table->timestamp('source_create_date')->nullable();
        $table->timestamp('source_write_date')->nullable();

        $table->decimal('quantity', 20, 6)->default(0);
        $table->decimal('quantity_done', 20, 6)->default(0);
        $table->decimal('unit_price', 20, 6)->default(0);
        $table->decimal('unit_cost', 20, 6)->default(0);

        $table->decimal('amount_untaxed', 20, 2)->default(0);
        $table->decimal('amount_tax', 20, 2)->default(0);
        $table->decimal('amount_total', 20, 2)->default(0);
        $table->decimal('amount_residual', 20, 2)->default(0);

        $table->decimal('debit', 20, 2)->default(0);
        $table->decimal('credit', 20, 2)->default(0);
        $table->decimal('balance', 20, 2)->default(0);

        $table->decimal('value', 20, 2)->default(0);
        $table->decimal('remaining_qty', 20, 6)->default(0);
        $table->decimal('remaining_value', 20, 2)->default(0);

        $table->boolean('is_reconciled')->nullable();

        $table->jsonb('raw_json')->nullable();
        $table->jsonb('mapping_json')->nullable();
        $table->jsonb('metadata')->nullable();

        $table->timestamp('migrated_at')->nullable();
        $table->timestamps();

        $table->unique(['period_label', 'odoo_id'], $prefix . '_period_odoo_uidx');
    }

    private function createHistoricalTable(string $tableName, string $prefix): void
    {
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) use ($prefix) {
                $this->addHistoricalBase($table, $prefix);
            });
        }
    }

    public function up(): void
    {
        $this->createHistoricalTable('historical_sale_orders', 'hso');
        $this->createHistoricalTable('historical_sale_order_lines', 'hsol');

        $this->createHistoricalTable('historical_pos_orders', 'hposo');
        $this->createHistoricalTable('historical_pos_order_lines', 'hposl');
        $this->createHistoricalTable('historical_pos_payments', 'hposp');

        $this->createHistoricalTable('historical_purchase_orders', 'hpo');
        $this->createHistoricalTable('historical_purchase_order_lines', 'hpol');

        $this->createHistoricalTable('historical_stock_pickings', 'hsp');
        $this->createHistoricalTable('historical_stock_moves', 'hsm');
        $this->createHistoricalTable('historical_stock_move_lines', 'hsml');
        $this->createHistoricalTable('historical_stock_valuation_layers', 'hsvl');

        $this->createHistoricalTable('historical_account_moves', 'ham');
        $this->createHistoricalTable('historical_account_move_lines', 'haml');
        $this->createHistoricalTable('historical_account_payments', 'hap');

        if (! Schema::hasTable('historical_data_load_runs')) {
            Schema::create('historical_data_load_runs', function (Blueprint $table) {
                $table->id();
                $table->string('version', 64)->nullable()->index('hdl_version_idx');
                $table->string('range_label', 128)->nullable()->index('hdl_range_idx');
                $table->string('load_group', 128)->nullable()->index('hdl_group_idx');
                $table->string('status', 64)->default('pending')->index('hdl_status_idx');
                $table->unsignedBigInteger('rows_expected')->default(0);
                $table->unsignedBigInteger('rows_loaded')->default(0);
                $table->unsignedBigInteger('rows_failed')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->jsonb('raw_json')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('historical_data_load_summaries')) {
            Schema::create('historical_data_load_summaries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('load_run_id')->nullable()->index('hdls_run_idx');
                $table->string('period_label', 64)->nullable()->index('hdls_period_idx');
                $table->string('source_model', 128)->nullable()->index('hdls_model_idx');
                $table->string('target_table', 128)->nullable()->index('hdls_target_idx');
                $table->unsignedBigInteger('expected_rows')->default(0);
                $table->unsignedBigInteger('actual_rows')->default(0);
                $table->bigInteger('diff_rows')->default(0);
                $table->jsonb('raw_json')->nullable();
                $table->timestamps();

                $table->unique(['period_label', 'source_model', 'target_table'], 'hdls_period_model_target_uidx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_data_load_summaries');
        Schema::dropIfExists('historical_data_load_runs');

        Schema::dropIfExists('historical_account_payments');
        Schema::dropIfExists('historical_account_move_lines');
        Schema::dropIfExists('historical_account_moves');

        Schema::dropIfExists('historical_stock_valuation_layers');
        Schema::dropIfExists('historical_stock_move_lines');
        Schema::dropIfExists('historical_stock_moves');
        Schema::dropIfExists('historical_stock_pickings');

        Schema::dropIfExists('historical_purchase_order_lines');
        Schema::dropIfExists('historical_purchase_orders');

        Schema::dropIfExists('historical_pos_payments');
        Schema::dropIfExists('historical_pos_order_lines');
        Schema::dropIfExists('historical_pos_orders');

        Schema::dropIfExists('historical_sale_order_lines');
        Schema::dropIfExists('historical_sale_orders');
    }
};
