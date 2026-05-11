<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $patchTag = 'V5_52_4C_inventory_perpetual_rc1';

    public function up(): void
    {
        $this->ensurePatchMarkerTables();
        $this->createInventoryValuationLayersTable();

        $sourceTables = [
            'pos_orders',
            'pos_order_items',
            'sales_orders',
            'sales_order_lines',
            'purchase_orders',
            'purchase_order_lines',
            'stock_movements',
            'stock_moves',
            'inventory_movements',
            'inventory_adjustments',
            'product_movements',
        ];

        foreach ($sourceTables as $tableName) {
            $this->addAccountingColumnsIfTableExists($tableName);
        }
    }

    public function down(): void
    {
        $this->dropColumnsAddedByPatch();
        $this->dropTablesAddedByPatch();
        $this->deletePatchMarkers();
    }

    private function ensurePatchMarkerTables(): void
    {
        if (! Schema::hasTable('accounting_schema_patch_tables')) {
            Schema::create('accounting_schema_patch_tables', function (Blueprint $table) {
                $table->id();
                $table->string('patch_tag', 120);
                $table->string('table_name', 120);
                $table->timestamps();

                $table->unique(['patch_tag', 'table_name'], 'acct_schema_patch_tables_unique');
            });
        }

        if (! Schema::hasTable('accounting_schema_patch_columns')) {
            Schema::create('accounting_schema_patch_columns', function (Blueprint $table) {
                $table->id();
                $table->string('patch_tag', 120);
                $table->string('table_name', 120);
                $table->string('column_name', 120);
                $table->timestamps();

                $table->unique(['patch_tag', 'table_name', 'column_name'], 'acct_schema_patch_columns_unique');
            });
        }
    }

    private function markTableCreated(string $tableName): void
    {
        DB::table('accounting_schema_patch_tables')->updateOrInsert(
            ['patch_tag' => $this->patchTag, 'table_name' => $tableName],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }

    private function markColumnCreated(string $tableName, string $columnName): void
    {
        DB::table('accounting_schema_patch_columns')->updateOrInsert(
            ['patch_tag' => $this->patchTag, 'table_name' => $tableName, 'column_name' => $columnName],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }

    private function createInventoryValuationLayersTable(): void
    {
        if (Schema::hasTable('accounting_inventory_valuation_layers')) {
            return;
        }

        Schema::create('accounting_inventory_valuation_layers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('product_id')->nullable();

            $table->foreignId('accounting_entry_id')
                ->nullable()
                ->constrained('accounting_entries')
                ->nullOnDelete();

            $table->string('operation_type', 80);
            $table->string('direction', 10);
            $table->date('movement_date');

            $table->string('source_type', 120)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('source_line_id')->nullable();

            $table->decimal('quantity', 18, 6)->default(0);
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->decimal('total_cost', 18, 6)->default(0);
            $table->decimal('remaining_quantity', 18, 6)->default(0);

            $table->string('currency', 10)->default('MXN');
            $table->string('label')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'product_id', 'movement_date'], 'acct_inv_layers_company_product_date_idx');
            $table->index(['source_type', 'source_id', 'source_line_id'], 'acct_inv_layers_source_idx');
            $table->index(['company_id', 'operation_type'], 'acct_inv_layers_operation_idx');
            $table->index(['accounting_entry_id'], 'acct_inv_layers_entry_idx');
        });

        $this->markTableCreated('accounting_inventory_valuation_layers');
    }

    private function addAccountingColumnsIfTableExists(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (! Schema::hasColumn($tableName, 'accounting_status')) {
                $table->string('accounting_status', 30)->default('not_posted')->index();
                $this->markColumnCreated($tableName, 'accounting_status');
            }

            if (! Schema::hasColumn($tableName, 'accounting_entry_id')) {
                $table->foreignId('accounting_entry_id')
                    ->nullable()
                    ->constrained('accounting_entries')
                    ->nullOnDelete();

                $this->markColumnCreated($tableName, 'accounting_entry_id');
            }

            if (! Schema::hasColumn($tableName, 'accounting_posted_at')) {
                $table->timestamp('accounting_posted_at')->nullable();
                $this->markColumnCreated($tableName, 'accounting_posted_at');
            }

            if (! Schema::hasColumn($tableName, 'accounting_error_message')) {
                $table->text('accounting_error_message')->nullable();
                $this->markColumnCreated($tableName, 'accounting_error_message');
            }
        });
    }

    private function dropColumnsAddedByPatch(): void
    {
        if (! Schema::hasTable('accounting_schema_patch_columns')) {
            return;
        }

        $rows = DB::table('accounting_schema_patch_columns')
            ->where('patch_tag', $this->patchTag)
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('table_name');

        foreach ($rows as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $columnNames = $columns->pluck('column_name')->values()->all();

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columnNames) {
                if (
                    in_array('accounting_entry_id', $columnNames, true)
                    && Schema::hasColumn($tableName, 'accounting_entry_id')
                ) {
                    try {
                        $table->dropConstrainedForeignId('accounting_entry_id');
                    } catch (Throwable $e) {
                        try {
                            $table->dropColumn('accounting_entry_id');
                        } catch (Throwable $ignored) {
                        }
                    }
                }

                foreach ($columnNames as $columnName) {
                    if ($columnName === 'accounting_entry_id') {
                        continue;
                    }

                    if (Schema::hasColumn($tableName, $columnName)) {
                        try {
                            $table->dropColumn($columnName);
                        } catch (Throwable $e) {
                        }
                    }
                }
            });
        }
    }

    private function dropTablesAddedByPatch(): void
    {
        if (! Schema::hasTable('accounting_schema_patch_tables')) {
            return;
        }

        $tables = DB::table('accounting_schema_patch_tables')
            ->where('patch_tag', $this->patchTag)
            ->orderBy('id', 'desc')
            ->pluck('table_name')
            ->all();

        foreach ($tables as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }

    private function deletePatchMarkers(): void
    {
        if (Schema::hasTable('accounting_schema_patch_columns')) {
            DB::table('accounting_schema_patch_columns')
                ->where('patch_tag', $this->patchTag)
                ->delete();
        }

        if (Schema::hasTable('accounting_schema_patch_tables')) {
            DB::table('accounting_schema_patch_tables')
                ->where('patch_tag', $this->patchTag)
                ->delete();
        }
    }
};
