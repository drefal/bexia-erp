<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_categories')) {
            Schema::table('product_categories', function (Blueprint $table): void {
                if (! Schema::hasColumn('product_categories', 'costing_method')) {
                    $table->string('costing_method', 30)
                        ->default('inherit')
                        ->after('parent_id');
                }
            });
        }

        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table): void {
                if (! Schema::hasColumn('companies', 'default_costing_method')) {
                    $table->string('default_costing_method', 30)
                        ->default('average')
                        ->after('default_location_id');
                }

                if (! Schema::hasColumn('companies', 'costing_scope')) {
                    $table->string('costing_scope', 30)
                        ->default('company')
                        ->after('default_costing_method');
                }
            });
        }

        if (Schema::hasTable('stock_movement_lines')) {
            Schema::table('stock_movement_lines', function (Blueprint $table): void {
                if (! Schema::hasColumn('stock_movement_lines', 'total_cost')) {
                    $table->decimal('total_cost', 18, 6)
                        ->nullable()
                        ->after('unit_cost');
                }

                if (! Schema::hasColumn('stock_movement_lines', 'costing_method')) {
                    $table->string('costing_method', 30)
                        ->nullable()
                        ->after('total_cost');
                }

                if (! Schema::hasColumn('stock_movement_lines', 'cost_source')) {
                    $table->string('cost_source', 120)
                        ->nullable()
                        ->after('costing_method');
                }
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'costing_method')) {
            try {
                if (DB::connection()->getDriverName() === 'pgsql') {
                    DB::statement("alter table products alter column costing_method set default 'inherit'");
                }
            } catch (Throwable $e) {
                // No bloqueamos la migracion si el motor no permite ajustar default.
            }
        }

        if (Schema::hasTable('product_categories') && Schema::hasColumn('product_categories', 'costing_method')) {
            DB::table('product_categories')
                ->whereNull('costing_method')
                ->update(['costing_method' => 'inherit']);
        }

        if (Schema::hasTable('companies')) {
            if (Schema::hasColumn('companies', 'default_costing_method')) {
                DB::table('companies')
                    ->whereNull('default_costing_method')
                    ->update(['default_costing_method' => 'average']);
            }

            if (Schema::hasColumn('companies', 'costing_scope')) {
                DB::table('companies')
                    ->whereNull('costing_scope')
                    ->update(['costing_scope' => 'company']);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_movement_lines')) {
            Schema::table('stock_movement_lines', function (Blueprint $table): void {
                foreach (['cost_source', 'costing_method', 'total_cost'] as $column) {
                    if (Schema::hasColumn('stock_movement_lines', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table): void {
                foreach (['costing_scope', 'default_costing_method'] as $column) {
                    if (Schema::hasColumn('companies', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('product_categories')) {
            Schema::table('product_categories', function (Blueprint $table): void {
                if (Schema::hasColumn('product_categories', 'costing_method')) {
                    $table->dropColumn('costing_method');
                }
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'costing_method')) {
            try {
                if (DB::connection()->getDriverName() === 'pgsql') {
                    DB::statement("alter table products alter column costing_method set default 'average'");
                }
            } catch (Throwable $e) {
                // No bloquear rollback.
            }
        }
    }
};
