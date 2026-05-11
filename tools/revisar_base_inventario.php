<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function titleLine(string $title): void
{
    echo PHP_EOL;
    echo "======================================" . PHP_EOL;
    echo $title . PHP_EOL;
    echo "======================================" . PHP_EOL;
}

function showTable(string $table): void
{
    if (! Schema::hasTable($table)) {
        echo "{$table}: NO EXISTE" . PHP_EOL;
        return;
    }

    echo "{$table}: " . implode(', ', Schema::getColumnListing($table)) . PHP_EOL;
}

titleLine('1) Tablas base empresa / sucursal');

foreach ([
    'organizations',
    'company_groups',
    'companies',
    'branches',
    'company_user',
    'company_group_user',
] as $table) {
    showTable($table);
}

titleLine('2) Tablas contables disponibles');

foreach ([
    'accounting_accounts',
    'accounting_journals',
    'accounting_journal_mappings',
    'accounting_settings',
    'sat_account_groupings',
] as $table) {
    showTable($table);
}

titleLine('3) Posibles tablas de inventario existentes');

foreach ([
    'products',
    'product_categories',
    'product_units',
    'units',
    'unit_measurements',
    'warehouses',
    'warehouse_locations',
    'location_types',
    'inventory_locations',
    'inventory_movements',
    'stock_movements',
    'stocks',
    'stock_levels',
    'lots',
    'serial_numbers',
    'product_lots',
    'product_serials',
] as $table) {
    showTable($table);
}

titleLine('4) Empresas y sucursales');

if (Schema::hasTable('companies')) {
    DB::table('companies')
        ->select('id', 'name', 'company_group_id')
        ->orderBy('id')
        ->get()
        ->each(fn ($row) => dump((array) $row));
}

if (Schema::hasTable('branches')) {
    DB::table('branches')
        ->select('id', 'company_id', 'name', 'code', 'active')
        ->orderBy('company_id')
        ->orderBy('id')
        ->get()
        ->each(fn ($row) => dump((array) $row));
}

titleLine('5) Settings contables por empresa');

if (Schema::hasTable('accounting_settings')) {
    DB::table('accounting_settings')
        ->select(
            'company_id',
            'costing_method',
            'inventory_account_id',
            'cogs_account_id',
            'inventory_adjustment_account_id',
            'default_journal_id',
            'purchases_journal_id',
            'sales_journal_id',
            'pos_journal_id'
        )
        ->orderBy('company_id')
        ->get()
        ->each(fn ($row) => dump((array) $row));
}

titleLine('6) Mapeos de inventario');

if (Schema::hasTable('accounting_journal_mappings')) {
    DB::table('accounting_journal_mappings')
        ->join('accounting_journals', 'accounting_journals.id', '=', 'accounting_journal_mappings.journal_id')
        ->where('accounting_journal_mappings.module', 'inventory')
        ->select(
            'accounting_journal_mappings.company_id',
            'accounting_journal_mappings.operation_type',
            'accounting_journals.code as journal_code',
            'accounting_journals.name as journal_name'
        )
        ->orderBy('accounting_journal_mappings.company_id')
        ->orderBy('accounting_journal_mappings.operation_type')
        ->get()
        ->each(fn ($row) => dump((array) $row));
}
