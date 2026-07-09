<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                if (! Schema::hasColumn('companies', 'migrated_at')) {
                    $table->timestamp('migrated_at')->nullable()->index();
                }
                if (! Schema::hasColumn('companies', 'migration_batch_id')) {
                    $table->string('migration_batch_id', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('companies', 'odoo_company_id')) {
                    $table->unsignedBigInteger('odoo_company_id')->nullable()->index();
                }
                if (! Schema::hasColumn('companies', 'odoo_migration_notes')) {
                    $table->text('odoo_migration_notes')->nullable();
                }
                if (! Schema::hasColumn('companies', 'odoo_raw_json')) {
                    $table->json('odoo_raw_json')->nullable();
                }
                if (! Schema::hasColumn('companies', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->index();
                }
                if (! Schema::hasColumn('companies', 'source_model')) {
                    $table->string('source_model', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('companies', 'source_system')) {
                    $table->string('source_system', 50)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('currencies')) {
            Schema::table('currencies', function (Blueprint $table) {
                if (! Schema::hasColumn('currencies', 'migrated_at')) {
                    $table->timestamp('migrated_at')->nullable()->index();
                }
                if (! Schema::hasColumn('currencies', 'migration_batch_id')) {
                    $table->string('migration_batch_id', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('currencies', 'odoo_currency_id')) {
                    $table->unsignedBigInteger('odoo_currency_id')->nullable()->index();
                }
                if (! Schema::hasColumn('currencies', 'odoo_migration_notes')) {
                    $table->text('odoo_migration_notes')->nullable();
                }
                if (! Schema::hasColumn('currencies', 'odoo_raw_json')) {
                    $table->json('odoo_raw_json')->nullable();
                }
                if (! Schema::hasColumn('currencies', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->index();
                }
                if (! Schema::hasColumn('currencies', 'source_model')) {
                    $table->string('source_model', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('currencies', 'source_system')) {
                    $table->string('source_system', 50)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('inventory_units')) {
            Schema::table('inventory_units', function (Blueprint $table) {
                if (! Schema::hasColumn('inventory_units', 'migrated_at')) {
                    $table->timestamp('migrated_at')->nullable()->index();
                }
                if (! Schema::hasColumn('inventory_units', 'migration_batch_id')) {
                    $table->string('migration_batch_id', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('inventory_units', 'odoo_category_id')) {
                    $table->unsignedBigInteger('odoo_category_id')->nullable()->index();
                }
                if (! Schema::hasColumn('inventory_units', 'odoo_migration_notes')) {
                    $table->text('odoo_migration_notes')->nullable();
                }
                if (! Schema::hasColumn('inventory_units', 'odoo_raw_json')) {
                    $table->json('odoo_raw_json')->nullable();
                }
                if (! Schema::hasColumn('inventory_units', 'odoo_uom_id')) {
                    $table->unsignedBigInteger('odoo_uom_id')->nullable()->index();
                }
                if (! Schema::hasColumn('inventory_units', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->index();
                }
                if (! Schema::hasColumn('inventory_units', 'source_model')) {
                    $table->string('source_model', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('inventory_units', 'source_system')) {
                    $table->string('source_system', 50)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('product_categories')) {
            Schema::table('product_categories', function (Blueprint $table) {
                if (! Schema::hasColumn('product_categories', 'migrated_at')) {
                    $table->timestamp('migrated_at')->nullable()->index();
                }
                if (! Schema::hasColumn('product_categories', 'migration_batch_id')) {
                    $table->string('migration_batch_id', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('product_categories', 'odoo_category_id')) {
                    $table->unsignedBigInteger('odoo_category_id')->nullable()->index();
                }
                if (! Schema::hasColumn('product_categories', 'odoo_migration_notes')) {
                    $table->text('odoo_migration_notes')->nullable();
                }
                if (! Schema::hasColumn('product_categories', 'odoo_parent_id')) {
                    $table->unsignedBigInteger('odoo_parent_id')->nullable()->index();
                }
                if (! Schema::hasColumn('product_categories', 'odoo_raw_json')) {
                    $table->json('odoo_raw_json')->nullable();
                }
                if (! Schema::hasColumn('product_categories', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->index();
                }
                if (! Schema::hasColumn('product_categories', 'source_model')) {
                    $table->string('source_model', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('product_categories', 'source_system')) {
                    $table->string('source_system', 50)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('tax_rates')) {
            Schema::table('tax_rates', function (Blueprint $table) {
                if (! Schema::hasColumn('tax_rates', 'migrated_at')) {
                    $table->timestamp('migrated_at')->nullable()->index();
                }
                if (! Schema::hasColumn('tax_rates', 'migration_batch_id')) {
                    $table->string('migration_batch_id', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('tax_rates', 'odoo_company_id')) {
                    $table->unsignedBigInteger('odoo_company_id')->nullable()->index();
                }
                if (! Schema::hasColumn('tax_rates', 'odoo_migration_notes')) {
                    $table->text('odoo_migration_notes')->nullable();
                }
                if (! Schema::hasColumn('tax_rates', 'odoo_raw_json')) {
                    $table->json('odoo_raw_json')->nullable();
                }
                if (! Schema::hasColumn('tax_rates', 'odoo_tax_id')) {
                    $table->unsignedBigInteger('odoo_tax_id')->nullable()->index();
                }
                if (! Schema::hasColumn('tax_rates', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->index();
                }
                if (! Schema::hasColumn('tax_rates', 'source_model')) {
                    $table->string('source_model', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('tax_rates', 'source_system')) {
                    $table->string('source_system', 50)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('accounting_accounts')) {
            Schema::table('accounting_accounts', function (Blueprint $table) {
                if (! Schema::hasColumn('accounting_accounts', 'migrated_at')) {
                    $table->timestamp('migrated_at')->nullable()->index();
                }
                if (! Schema::hasColumn('accounting_accounts', 'migration_batch_id')) {
                    $table->string('migration_batch_id', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('accounting_accounts', 'odoo_account_id')) {
                    $table->unsignedBigInteger('odoo_account_id')->nullable()->index();
                }
                if (! Schema::hasColumn('accounting_accounts', 'odoo_company_id')) {
                    $table->unsignedBigInteger('odoo_company_id')->nullable()->index();
                }
                if (! Schema::hasColumn('accounting_accounts', 'odoo_migration_notes')) {
                    $table->text('odoo_migration_notes')->nullable();
                }
                if (! Schema::hasColumn('accounting_accounts', 'odoo_raw_json')) {
                    $table->json('odoo_raw_json')->nullable();
                }
                if (! Schema::hasColumn('accounting_accounts', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->index();
                }
                if (! Schema::hasColumn('accounting_accounts', 'source_model')) {
                    $table->string('source_model', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('accounting_accounts', 'source_system')) {
                    $table->string('source_system', 50)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('accounting_journals')) {
            Schema::table('accounting_journals', function (Blueprint $table) {
                if (! Schema::hasColumn('accounting_journals', 'migrated_at')) {
                    $table->timestamp('migrated_at')->nullable()->index();
                }
                if (! Schema::hasColumn('accounting_journals', 'migration_batch_id')) {
                    $table->string('migration_batch_id', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('accounting_journals', 'odoo_company_id')) {
                    $table->unsignedBigInteger('odoo_company_id')->nullable()->index();
                }
                if (! Schema::hasColumn('accounting_journals', 'odoo_journal_id')) {
                    $table->unsignedBigInteger('odoo_journal_id')->nullable()->index();
                }
                if (! Schema::hasColumn('accounting_journals', 'odoo_migration_notes')) {
                    $table->text('odoo_migration_notes')->nullable();
                }
                if (! Schema::hasColumn('accounting_journals', 'odoo_raw_json')) {
                    $table->json('odoo_raw_json')->nullable();
                }
                if (! Schema::hasColumn('accounting_journals', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->index();
                }
                if (! Schema::hasColumn('accounting_journals', 'source_model')) {
                    $table->string('source_model', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('accounting_journals', 'source_system')) {
                    $table->string('source_system', 50)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('payment_forms')) {
            Schema::table('payment_forms', function (Blueprint $table) {
                if (! Schema::hasColumn('payment_forms', 'migrated_at')) {
                    $table->timestamp('migrated_at')->nullable()->index();
                }
                if (! Schema::hasColumn('payment_forms', 'migration_batch_id')) {
                    $table->string('migration_batch_id', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('payment_forms', 'odoo_migration_notes')) {
                    $table->text('odoo_migration_notes')->nullable();
                }
                if (! Schema::hasColumn('payment_forms', 'odoo_payment_form_id')) {
                    $table->unsignedBigInteger('odoo_payment_form_id')->nullable()->index();
                }
                if (! Schema::hasColumn('payment_forms', 'odoo_payment_method_id')) {
                    $table->unsignedBigInteger('odoo_payment_method_id')->nullable()->index();
                }
                if (! Schema::hasColumn('payment_forms', 'odoo_raw_json')) {
                    $table->json('odoo_raw_json')->nullable();
                }
                if (! Schema::hasColumn('payment_forms', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->index();
                }
                if (! Schema::hasColumn('payment_forms', 'source_model')) {
                    $table->string('source_model', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('payment_forms', 'source_system')) {
                    $table->string('source_system', 50)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('payment_terms')) {
            Schema::table('payment_terms', function (Blueprint $table) {
                if (! Schema::hasColumn('payment_terms', 'migrated_at')) {
                    $table->timestamp('migrated_at')->nullable()->index();
                }
                if (! Schema::hasColumn('payment_terms', 'migration_batch_id')) {
                    $table->string('migration_batch_id', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('payment_terms', 'odoo_migration_notes')) {
                    $table->text('odoo_migration_notes')->nullable();
                }
                if (! Schema::hasColumn('payment_terms', 'odoo_payment_term_id')) {
                    $table->unsignedBigInteger('odoo_payment_term_id')->nullable()->index();
                }
                if (! Schema::hasColumn('payment_terms', 'odoo_raw_json')) {
                    $table->json('odoo_raw_json')->nullable();
                }
                if (! Schema::hasColumn('payment_terms', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->index();
                }
                if (! Schema::hasColumn('payment_terms', 'source_model')) {
                    $table->string('source_model', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('payment_terms', 'source_system')) {
                    $table->string('source_system', 50)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('contacts')) {
            Schema::table('contacts', function (Blueprint $table) {
                if (! Schema::hasColumn('contacts', 'migrated_at')) {
                    $table->timestamp('migrated_at')->nullable()->index();
                }
                if (! Schema::hasColumn('contacts', 'migration_batch_id')) {
                    $table->string('migration_batch_id', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('contacts', 'odoo_company_id')) {
                    $table->unsignedBigInteger('odoo_company_id')->nullable()->index();
                }
                if (! Schema::hasColumn('contacts', 'odoo_migration_notes')) {
                    $table->text('odoo_migration_notes')->nullable();
                }
                if (! Schema::hasColumn('contacts', 'odoo_parent_id')) {
                    $table->unsignedBigInteger('odoo_parent_id')->nullable()->index();
                }
                if (! Schema::hasColumn('contacts', 'odoo_partner_id')) {
                    $table->unsignedBigInteger('odoo_partner_id')->nullable()->index();
                }
                if (! Schema::hasColumn('contacts', 'odoo_raw_json')) {
                    $table->json('odoo_raw_json')->nullable();
                }
                if (! Schema::hasColumn('contacts', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->index();
                }
                if (! Schema::hasColumn('contacts', 'source_model')) {
                    $table->string('source_model', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('contacts', 'source_system')) {
                    $table->string('source_system', 50)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('warehouses')) {
            Schema::table('warehouses', function (Blueprint $table) {
                if (! Schema::hasColumn('warehouses', 'migrated_at')) {
                    $table->timestamp('migrated_at')->nullable()->index();
                }
                if (! Schema::hasColumn('warehouses', 'migration_batch_id')) {
                    $table->string('migration_batch_id', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('warehouses', 'odoo_company_id')) {
                    $table->unsignedBigInteger('odoo_company_id')->nullable()->index();
                }
                if (! Schema::hasColumn('warehouses', 'odoo_lot_stock_id')) {
                    $table->unsignedBigInteger('odoo_lot_stock_id')->nullable()->index();
                }
                if (! Schema::hasColumn('warehouses', 'odoo_migration_notes')) {
                    $table->text('odoo_migration_notes')->nullable();
                }
                if (! Schema::hasColumn('warehouses', 'odoo_raw_json')) {
                    $table->json('odoo_raw_json')->nullable();
                }
                if (! Schema::hasColumn('warehouses', 'odoo_view_location_id')) {
                    $table->unsignedBigInteger('odoo_view_location_id')->nullable()->index();
                }
                if (! Schema::hasColumn('warehouses', 'odoo_warehouse_id')) {
                    $table->unsignedBigInteger('odoo_warehouse_id')->nullable()->index();
                }
                if (! Schema::hasColumn('warehouses', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->index();
                }
                if (! Schema::hasColumn('warehouses', 'source_model')) {
                    $table->string('source_model', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('warehouses', 'source_system')) {
                    $table->string('source_system', 50)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('stock_locations')) {
            Schema::table('stock_locations', function (Blueprint $table) {
                if (! Schema::hasColumn('stock_locations', 'migrated_at')) {
                    $table->timestamp('migrated_at')->nullable()->index();
                }
                if (! Schema::hasColumn('stock_locations', 'migration_batch_id')) {
                    $table->string('migration_batch_id', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('stock_locations', 'odoo_company_id')) {
                    $table->unsignedBigInteger('odoo_company_id')->nullable()->index();
                }
                if (! Schema::hasColumn('stock_locations', 'odoo_location_id')) {
                    $table->unsignedBigInteger('odoo_location_id')->nullable()->index();
                }
                if (! Schema::hasColumn('stock_locations', 'odoo_migration_notes')) {
                    $table->text('odoo_migration_notes')->nullable();
                }
                if (! Schema::hasColumn('stock_locations', 'odoo_parent_id')) {
                    $table->unsignedBigInteger('odoo_parent_id')->nullable()->index();
                }
                if (! Schema::hasColumn('stock_locations', 'odoo_raw_json')) {
                    $table->json('odoo_raw_json')->nullable();
                }
                if (! Schema::hasColumn('stock_locations', 'odoo_usage')) {
                    $table->string('odoo_usage', 50)->nullable()->index();
                }
                if (! Schema::hasColumn('stock_locations', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->index();
                }
                if (! Schema::hasColumn('stock_locations', 'source_model')) {
                    $table->string('source_model', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('stock_locations', 'source_system')) {
                    $table->string('source_system', 50)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('sales_price_lists')) {
            Schema::table('sales_price_lists', function (Blueprint $table) {
                if (! Schema::hasColumn('sales_price_lists', 'migrated_at')) {
                    $table->timestamp('migrated_at')->nullable()->index();
                }
                if (! Schema::hasColumn('sales_price_lists', 'migration_batch_id')) {
                    $table->string('migration_batch_id', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('sales_price_lists', 'odoo_migration_notes')) {
                    $table->text('odoo_migration_notes')->nullable();
                }
                if (! Schema::hasColumn('sales_price_lists', 'odoo_pricelist_id')) {
                    $table->unsignedBigInteger('odoo_pricelist_id')->nullable()->index();
                }
                if (! Schema::hasColumn('sales_price_lists', 'odoo_raw_json')) {
                    $table->json('odoo_raw_json')->nullable();
                }
                if (! Schema::hasColumn('sales_price_lists', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->index();
                }
                if (! Schema::hasColumn('sales_price_lists', 'source_model')) {
                    $table->string('source_model', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('sales_price_lists', 'source_system')) {
                    $table->string('source_system', 50)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('pos_points')) {
            Schema::table('pos_points', function (Blueprint $table) {
                if (! Schema::hasColumn('pos_points', 'migrated_at')) {
                    $table->timestamp('migrated_at')->nullable()->index();
                }
                if (! Schema::hasColumn('pos_points', 'migration_batch_id')) {
                    $table->string('migration_batch_id', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('pos_points', 'odoo_company_id')) {
                    $table->unsignedBigInteger('odoo_company_id')->nullable()->index();
                }
                if (! Schema::hasColumn('pos_points', 'odoo_migration_notes')) {
                    $table->text('odoo_migration_notes')->nullable();
                }
                if (! Schema::hasColumn('pos_points', 'odoo_pos_config_id')) {
                    $table->unsignedBigInteger('odoo_pos_config_id')->nullable()->index();
                }
                if (! Schema::hasColumn('pos_points', 'odoo_raw_json')) {
                    $table->json('odoo_raw_json')->nullable();
                }
                if (! Schema::hasColumn('pos_points', 'odoo_warehouse_id')) {
                    $table->unsignedBigInteger('odoo_warehouse_id')->nullable()->index();
                }
                if (! Schema::hasColumn('pos_points', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->index();
                }
                if (! Schema::hasColumn('pos_points', 'source_model')) {
                    $table->string('source_model', 120)->nullable()->index();
                }
                if (! Schema::hasColumn('pos_points', 'source_system')) {
                    $table->string('source_system', 50)->nullable()->index();
                }
            });
        }

    }

    public function down(): void
    {
        if (Schema::hasTable('pos_points')) {
            Schema::table('pos_points', function (Blueprint $table) {
                if (Schema::hasColumn('pos_points', 'source_system')) {
                    $table->dropColumn('source_system');
                }
                if (Schema::hasColumn('pos_points', 'source_model')) {
                    $table->dropColumn('source_model');
                }
                if (Schema::hasColumn('pos_points', 'source_id')) {
                    $table->dropColumn('source_id');
                }
                if (Schema::hasColumn('pos_points', 'odoo_warehouse_id')) {
                    $table->dropColumn('odoo_warehouse_id');
                }
                if (Schema::hasColumn('pos_points', 'odoo_raw_json')) {
                    $table->dropColumn('odoo_raw_json');
                }
                if (Schema::hasColumn('pos_points', 'odoo_pos_config_id')) {
                    $table->dropColumn('odoo_pos_config_id');
                }
                if (Schema::hasColumn('pos_points', 'odoo_migration_notes')) {
                    $table->dropColumn('odoo_migration_notes');
                }
                if (Schema::hasColumn('pos_points', 'odoo_company_id')) {
                    $table->dropColumn('odoo_company_id');
                }
                if (Schema::hasColumn('pos_points', 'migration_batch_id')) {
                    $table->dropColumn('migration_batch_id');
                }
                if (Schema::hasColumn('pos_points', 'migrated_at')) {
                    $table->dropColumn('migrated_at');
                }
            });
        }

        if (Schema::hasTable('sales_price_lists')) {
            Schema::table('sales_price_lists', function (Blueprint $table) {
                if (Schema::hasColumn('sales_price_lists', 'source_system')) {
                    $table->dropColumn('source_system');
                }
                if (Schema::hasColumn('sales_price_lists', 'source_model')) {
                    $table->dropColumn('source_model');
                }
                if (Schema::hasColumn('sales_price_lists', 'source_id')) {
                    $table->dropColumn('source_id');
                }
                if (Schema::hasColumn('sales_price_lists', 'odoo_raw_json')) {
                    $table->dropColumn('odoo_raw_json');
                }
                if (Schema::hasColumn('sales_price_lists', 'odoo_pricelist_id')) {
                    $table->dropColumn('odoo_pricelist_id');
                }
                if (Schema::hasColumn('sales_price_lists', 'odoo_migration_notes')) {
                    $table->dropColumn('odoo_migration_notes');
                }
                if (Schema::hasColumn('sales_price_lists', 'migration_batch_id')) {
                    $table->dropColumn('migration_batch_id');
                }
                if (Schema::hasColumn('sales_price_lists', 'migrated_at')) {
                    $table->dropColumn('migrated_at');
                }
            });
        }

        if (Schema::hasTable('stock_locations')) {
            Schema::table('stock_locations', function (Blueprint $table) {
                if (Schema::hasColumn('stock_locations', 'source_system')) {
                    $table->dropColumn('source_system');
                }
                if (Schema::hasColumn('stock_locations', 'source_model')) {
                    $table->dropColumn('source_model');
                }
                if (Schema::hasColumn('stock_locations', 'source_id')) {
                    $table->dropColumn('source_id');
                }
                if (Schema::hasColumn('stock_locations', 'odoo_usage')) {
                    $table->dropColumn('odoo_usage');
                }
                if (Schema::hasColumn('stock_locations', 'odoo_raw_json')) {
                    $table->dropColumn('odoo_raw_json');
                }
                if (Schema::hasColumn('stock_locations', 'odoo_parent_id')) {
                    $table->dropColumn('odoo_parent_id');
                }
                if (Schema::hasColumn('stock_locations', 'odoo_migration_notes')) {
                    $table->dropColumn('odoo_migration_notes');
                }
                if (Schema::hasColumn('stock_locations', 'odoo_location_id')) {
                    $table->dropColumn('odoo_location_id');
                }
                if (Schema::hasColumn('stock_locations', 'odoo_company_id')) {
                    $table->dropColumn('odoo_company_id');
                }
                if (Schema::hasColumn('stock_locations', 'migration_batch_id')) {
                    $table->dropColumn('migration_batch_id');
                }
                if (Schema::hasColumn('stock_locations', 'migrated_at')) {
                    $table->dropColumn('migrated_at');
                }
            });
        }

        if (Schema::hasTable('warehouses')) {
            Schema::table('warehouses', function (Blueprint $table) {
                if (Schema::hasColumn('warehouses', 'source_system')) {
                    $table->dropColumn('source_system');
                }
                if (Schema::hasColumn('warehouses', 'source_model')) {
                    $table->dropColumn('source_model');
                }
                if (Schema::hasColumn('warehouses', 'source_id')) {
                    $table->dropColumn('source_id');
                }
                if (Schema::hasColumn('warehouses', 'odoo_warehouse_id')) {
                    $table->dropColumn('odoo_warehouse_id');
                }
                if (Schema::hasColumn('warehouses', 'odoo_view_location_id')) {
                    $table->dropColumn('odoo_view_location_id');
                }
                if (Schema::hasColumn('warehouses', 'odoo_raw_json')) {
                    $table->dropColumn('odoo_raw_json');
                }
                if (Schema::hasColumn('warehouses', 'odoo_migration_notes')) {
                    $table->dropColumn('odoo_migration_notes');
                }
                if (Schema::hasColumn('warehouses', 'odoo_lot_stock_id')) {
                    $table->dropColumn('odoo_lot_stock_id');
                }
                if (Schema::hasColumn('warehouses', 'odoo_company_id')) {
                    $table->dropColumn('odoo_company_id');
                }
                if (Schema::hasColumn('warehouses', 'migration_batch_id')) {
                    $table->dropColumn('migration_batch_id');
                }
                if (Schema::hasColumn('warehouses', 'migrated_at')) {
                    $table->dropColumn('migrated_at');
                }
            });
        }

        if (Schema::hasTable('contacts')) {
            Schema::table('contacts', function (Blueprint $table) {
                if (Schema::hasColumn('contacts', 'source_system')) {
                    $table->dropColumn('source_system');
                }
                if (Schema::hasColumn('contacts', 'source_model')) {
                    $table->dropColumn('source_model');
                }
                if (Schema::hasColumn('contacts', 'source_id')) {
                    $table->dropColumn('source_id');
                }
                if (Schema::hasColumn('contacts', 'odoo_raw_json')) {
                    $table->dropColumn('odoo_raw_json');
                }
                if (Schema::hasColumn('contacts', 'odoo_partner_id')) {
                    $table->dropColumn('odoo_partner_id');
                }
                if (Schema::hasColumn('contacts', 'odoo_parent_id')) {
                    $table->dropColumn('odoo_parent_id');
                }
                if (Schema::hasColumn('contacts', 'odoo_migration_notes')) {
                    $table->dropColumn('odoo_migration_notes');
                }
                if (Schema::hasColumn('contacts', 'odoo_company_id')) {
                    $table->dropColumn('odoo_company_id');
                }
                if (Schema::hasColumn('contacts', 'migration_batch_id')) {
                    $table->dropColumn('migration_batch_id');
                }
                if (Schema::hasColumn('contacts', 'migrated_at')) {
                    $table->dropColumn('migrated_at');
                }
            });
        }

        if (Schema::hasTable('payment_terms')) {
            Schema::table('payment_terms', function (Blueprint $table) {
                if (Schema::hasColumn('payment_terms', 'source_system')) {
                    $table->dropColumn('source_system');
                }
                if (Schema::hasColumn('payment_terms', 'source_model')) {
                    $table->dropColumn('source_model');
                }
                if (Schema::hasColumn('payment_terms', 'source_id')) {
                    $table->dropColumn('source_id');
                }
                if (Schema::hasColumn('payment_terms', 'odoo_raw_json')) {
                    $table->dropColumn('odoo_raw_json');
                }
                if (Schema::hasColumn('payment_terms', 'odoo_payment_term_id')) {
                    $table->dropColumn('odoo_payment_term_id');
                }
                if (Schema::hasColumn('payment_terms', 'odoo_migration_notes')) {
                    $table->dropColumn('odoo_migration_notes');
                }
                if (Schema::hasColumn('payment_terms', 'migration_batch_id')) {
                    $table->dropColumn('migration_batch_id');
                }
                if (Schema::hasColumn('payment_terms', 'migrated_at')) {
                    $table->dropColumn('migrated_at');
                }
            });
        }

        if (Schema::hasTable('payment_forms')) {
            Schema::table('payment_forms', function (Blueprint $table) {
                if (Schema::hasColumn('payment_forms', 'source_system')) {
                    $table->dropColumn('source_system');
                }
                if (Schema::hasColumn('payment_forms', 'source_model')) {
                    $table->dropColumn('source_model');
                }
                if (Schema::hasColumn('payment_forms', 'source_id')) {
                    $table->dropColumn('source_id');
                }
                if (Schema::hasColumn('payment_forms', 'odoo_raw_json')) {
                    $table->dropColumn('odoo_raw_json');
                }
                if (Schema::hasColumn('payment_forms', 'odoo_payment_method_id')) {
                    $table->dropColumn('odoo_payment_method_id');
                }
                if (Schema::hasColumn('payment_forms', 'odoo_payment_form_id')) {
                    $table->dropColumn('odoo_payment_form_id');
                }
                if (Schema::hasColumn('payment_forms', 'odoo_migration_notes')) {
                    $table->dropColumn('odoo_migration_notes');
                }
                if (Schema::hasColumn('payment_forms', 'migration_batch_id')) {
                    $table->dropColumn('migration_batch_id');
                }
                if (Schema::hasColumn('payment_forms', 'migrated_at')) {
                    $table->dropColumn('migrated_at');
                }
            });
        }

        if (Schema::hasTable('accounting_journals')) {
            Schema::table('accounting_journals', function (Blueprint $table) {
                if (Schema::hasColumn('accounting_journals', 'source_system')) {
                    $table->dropColumn('source_system');
                }
                if (Schema::hasColumn('accounting_journals', 'source_model')) {
                    $table->dropColumn('source_model');
                }
                if (Schema::hasColumn('accounting_journals', 'source_id')) {
                    $table->dropColumn('source_id');
                }
                if (Schema::hasColumn('accounting_journals', 'odoo_raw_json')) {
                    $table->dropColumn('odoo_raw_json');
                }
                if (Schema::hasColumn('accounting_journals', 'odoo_migration_notes')) {
                    $table->dropColumn('odoo_migration_notes');
                }
                if (Schema::hasColumn('accounting_journals', 'odoo_journal_id')) {
                    $table->dropColumn('odoo_journal_id');
                }
                if (Schema::hasColumn('accounting_journals', 'odoo_company_id')) {
                    $table->dropColumn('odoo_company_id');
                }
                if (Schema::hasColumn('accounting_journals', 'migration_batch_id')) {
                    $table->dropColumn('migration_batch_id');
                }
                if (Schema::hasColumn('accounting_journals', 'migrated_at')) {
                    $table->dropColumn('migrated_at');
                }
            });
        }

        if (Schema::hasTable('accounting_accounts')) {
            Schema::table('accounting_accounts', function (Blueprint $table) {
                if (Schema::hasColumn('accounting_accounts', 'source_system')) {
                    $table->dropColumn('source_system');
                }
                if (Schema::hasColumn('accounting_accounts', 'source_model')) {
                    $table->dropColumn('source_model');
                }
                if (Schema::hasColumn('accounting_accounts', 'source_id')) {
                    $table->dropColumn('source_id');
                }
                if (Schema::hasColumn('accounting_accounts', 'odoo_raw_json')) {
                    $table->dropColumn('odoo_raw_json');
                }
                if (Schema::hasColumn('accounting_accounts', 'odoo_migration_notes')) {
                    $table->dropColumn('odoo_migration_notes');
                }
                if (Schema::hasColumn('accounting_accounts', 'odoo_company_id')) {
                    $table->dropColumn('odoo_company_id');
                }
                if (Schema::hasColumn('accounting_accounts', 'odoo_account_id')) {
                    $table->dropColumn('odoo_account_id');
                }
                if (Schema::hasColumn('accounting_accounts', 'migration_batch_id')) {
                    $table->dropColumn('migration_batch_id');
                }
                if (Schema::hasColumn('accounting_accounts', 'migrated_at')) {
                    $table->dropColumn('migrated_at');
                }
            });
        }

        if (Schema::hasTable('tax_rates')) {
            Schema::table('tax_rates', function (Blueprint $table) {
                if (Schema::hasColumn('tax_rates', 'source_system')) {
                    $table->dropColumn('source_system');
                }
                if (Schema::hasColumn('tax_rates', 'source_model')) {
                    $table->dropColumn('source_model');
                }
                if (Schema::hasColumn('tax_rates', 'source_id')) {
                    $table->dropColumn('source_id');
                }
                if (Schema::hasColumn('tax_rates', 'odoo_tax_id')) {
                    $table->dropColumn('odoo_tax_id');
                }
                if (Schema::hasColumn('tax_rates', 'odoo_raw_json')) {
                    $table->dropColumn('odoo_raw_json');
                }
                if (Schema::hasColumn('tax_rates', 'odoo_migration_notes')) {
                    $table->dropColumn('odoo_migration_notes');
                }
                if (Schema::hasColumn('tax_rates', 'odoo_company_id')) {
                    $table->dropColumn('odoo_company_id');
                }
                if (Schema::hasColumn('tax_rates', 'migration_batch_id')) {
                    $table->dropColumn('migration_batch_id');
                }
                if (Schema::hasColumn('tax_rates', 'migrated_at')) {
                    $table->dropColumn('migrated_at');
                }
            });
        }

        if (Schema::hasTable('product_categories')) {
            Schema::table('product_categories', function (Blueprint $table) {
                if (Schema::hasColumn('product_categories', 'source_system')) {
                    $table->dropColumn('source_system');
                }
                if (Schema::hasColumn('product_categories', 'source_model')) {
                    $table->dropColumn('source_model');
                }
                if (Schema::hasColumn('product_categories', 'source_id')) {
                    $table->dropColumn('source_id');
                }
                if (Schema::hasColumn('product_categories', 'odoo_raw_json')) {
                    $table->dropColumn('odoo_raw_json');
                }
                if (Schema::hasColumn('product_categories', 'odoo_parent_id')) {
                    $table->dropColumn('odoo_parent_id');
                }
                if (Schema::hasColumn('product_categories', 'odoo_migration_notes')) {
                    $table->dropColumn('odoo_migration_notes');
                }
                if (Schema::hasColumn('product_categories', 'odoo_category_id')) {
                    $table->dropColumn('odoo_category_id');
                }
                if (Schema::hasColumn('product_categories', 'migration_batch_id')) {
                    $table->dropColumn('migration_batch_id');
                }
                if (Schema::hasColumn('product_categories', 'migrated_at')) {
                    $table->dropColumn('migrated_at');
                }
            });
        }

        if (Schema::hasTable('inventory_units')) {
            Schema::table('inventory_units', function (Blueprint $table) {
                if (Schema::hasColumn('inventory_units', 'source_system')) {
                    $table->dropColumn('source_system');
                }
                if (Schema::hasColumn('inventory_units', 'source_model')) {
                    $table->dropColumn('source_model');
                }
                if (Schema::hasColumn('inventory_units', 'source_id')) {
                    $table->dropColumn('source_id');
                }
                if (Schema::hasColumn('inventory_units', 'odoo_uom_id')) {
                    $table->dropColumn('odoo_uom_id');
                }
                if (Schema::hasColumn('inventory_units', 'odoo_raw_json')) {
                    $table->dropColumn('odoo_raw_json');
                }
                if (Schema::hasColumn('inventory_units', 'odoo_migration_notes')) {
                    $table->dropColumn('odoo_migration_notes');
                }
                if (Schema::hasColumn('inventory_units', 'odoo_category_id')) {
                    $table->dropColumn('odoo_category_id');
                }
                if (Schema::hasColumn('inventory_units', 'migration_batch_id')) {
                    $table->dropColumn('migration_batch_id');
                }
                if (Schema::hasColumn('inventory_units', 'migrated_at')) {
                    $table->dropColumn('migrated_at');
                }
            });
        }

        if (Schema::hasTable('currencies')) {
            Schema::table('currencies', function (Blueprint $table) {
                if (Schema::hasColumn('currencies', 'source_system')) {
                    $table->dropColumn('source_system');
                }
                if (Schema::hasColumn('currencies', 'source_model')) {
                    $table->dropColumn('source_model');
                }
                if (Schema::hasColumn('currencies', 'source_id')) {
                    $table->dropColumn('source_id');
                }
                if (Schema::hasColumn('currencies', 'odoo_raw_json')) {
                    $table->dropColumn('odoo_raw_json');
                }
                if (Schema::hasColumn('currencies', 'odoo_migration_notes')) {
                    $table->dropColumn('odoo_migration_notes');
                }
                if (Schema::hasColumn('currencies', 'odoo_currency_id')) {
                    $table->dropColumn('odoo_currency_id');
                }
                if (Schema::hasColumn('currencies', 'migration_batch_id')) {
                    $table->dropColumn('migration_batch_id');
                }
                if (Schema::hasColumn('currencies', 'migrated_at')) {
                    $table->dropColumn('migrated_at');
                }
            });
        }

        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                if (Schema::hasColumn('companies', 'source_system')) {
                    $table->dropColumn('source_system');
                }
                if (Schema::hasColumn('companies', 'source_model')) {
                    $table->dropColumn('source_model');
                }
                if (Schema::hasColumn('companies', 'source_id')) {
                    $table->dropColumn('source_id');
                }
                if (Schema::hasColumn('companies', 'odoo_raw_json')) {
                    $table->dropColumn('odoo_raw_json');
                }
                if (Schema::hasColumn('companies', 'odoo_migration_notes')) {
                    $table->dropColumn('odoo_migration_notes');
                }
                if (Schema::hasColumn('companies', 'odoo_company_id')) {
                    $table->dropColumn('odoo_company_id');
                }
                if (Schema::hasColumn('companies', 'migration_batch_id')) {
                    $table->dropColumn('migration_batch_id');
                }
                if (Schema::hasColumn('companies', 'migrated_at')) {
                    $table->dropColumn('migrated_at');
                }
            });
        }

    }
};
