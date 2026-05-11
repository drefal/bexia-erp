<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function bxTitle(string $title): void
{
    echo PHP_EOL;
    echo "======================================" . PHP_EOL;
    echo $title . PHP_EOL;
    echo "======================================" . PHP_EOL;
}

function bxRequireTable(string $table): void
{
    if (! Schema::hasTable($table)) {
        throw new RuntimeException("Falta tabla requerida: {$table}");
    }
}

function bxNow(): string
{
    return now()->toDateTimeString();
}

function bxUpsert(string $table, array $keys, array $values): void
{
    $query = DB::table($table);

    foreach ($keys as $column => $value) {
        $query->where($column, $value);
    }

    $exists = $query->exists();

    if ($exists) {
        DB::table($table)
            ->where($keys)
            ->update(array_merge($values, [
                'updated_at' => bxNow(),
            ]));

        return;
    }

    DB::table($table)->insert(array_merge($keys, $values, [
        'created_at' => bxNow(),
        'updated_at' => bxNow(),
    ]));
}

foreach ([
    'companies',
    'accounting_accounts',
    'accounting_journals',
    'accounting_settings',
    'sat_account_groupings',
    'accounting_journal_mappings',
] as $table) {
    bxRequireTable($table);
}

$satGroupings = [
    ['code' => '100', 'level' => null, 'name' => 'Activo', 'account_type' => 'asset'],
    ['code' => '101', 'level' => 1, 'name' => 'Caja', 'account_type' => 'asset'],
    ['code' => '101.01', 'level' => 2, 'name' => 'Caja y efectivo', 'account_type' => 'asset'],
    ['code' => '102', 'level' => 1, 'name' => 'Bancos', 'account_type' => 'asset'],
    ['code' => '102.01', 'level' => 2, 'name' => 'Bancos nacionales', 'account_type' => 'asset'],
    ['code' => '105', 'level' => 1, 'name' => 'Clientes', 'account_type' => 'asset'],
    ['code' => '105.01', 'level' => 2, 'name' => 'Clientes nacionales', 'account_type' => 'asset'],
    ['code' => '115', 'level' => 1, 'name' => 'Inventario', 'account_type' => 'asset'],
    ['code' => '115.01', 'level' => 2, 'name' => 'Inventario', 'account_type' => 'asset'],
    ['code' => '118.01', 'level' => 2, 'name' => 'IVA acreditable pagado', 'account_type' => 'asset'],
    ['code' => '119.01', 'level' => 2, 'name' => 'IVA pendiente de pago', 'account_type' => 'asset'],

    ['code' => '200', 'level' => null, 'name' => 'Pasivo', 'account_type' => 'liability'],
    ['code' => '201', 'level' => 1, 'name' => 'Proveedores', 'account_type' => 'liability'],
    ['code' => '201.01', 'level' => 2, 'name' => 'Proveedores nacionales', 'account_type' => 'liability'],
    ['code' => '207.01', 'level' => 2, 'name' => 'IVA trasladado', 'account_type' => 'liability'],
    ['code' => '208.01', 'level' => 2, 'name' => 'IVA trasladado cobrado', 'account_type' => 'liability'],
    ['code' => '209.01', 'level' => 2, 'name' => 'IVA trasladado no cobrado', 'account_type' => 'liability'],
    ['code' => '213.01', 'level' => 2, 'name' => 'IVA por pagar', 'account_type' => 'liability'],

    ['code' => '300', 'level' => null, 'name' => 'Capital contable', 'account_type' => 'equity'],

    ['code' => '400', 'level' => null, 'name' => 'Ingresos', 'account_type' => 'income'],
    ['code' => '401', 'level' => 1, 'name' => 'Ingresos', 'account_type' => 'income'],
    ['code' => '401.01', 'level' => 2, 'name' => 'Ventas y/o servicios gravados a la tasa general', 'account_type' => 'income'],
    ['code' => '401.04', 'level' => 2, 'name' => 'Ventas y/o servicios gravados al 0%', 'account_type' => 'income'],
    ['code' => '401.07', 'level' => 2, 'name' => 'Ventas y/o servicios exentos', 'account_type' => 'income'],
    ['code' => '402.01', 'level' => 2, 'name' => 'Devoluciones, descuentos o bonificaciones sobre ventas', 'account_type' => 'income'],

    ['code' => '500', 'level' => null, 'name' => 'Costos', 'account_type' => 'cost'],
    ['code' => '501.01', 'level' => 2, 'name' => 'Costo de venta', 'account_type' => 'cost'],
    ['code' => '501.08', 'level' => 2, 'name' => 'Otros conceptos de costo', 'account_type' => 'cost'],
    ['code' => '502.01', 'level' => 2, 'name' => 'Compras nacionales', 'account_type' => 'cost'],
    ['code' => '503.01', 'level' => 2, 'name' => 'Devoluciones, descuentos o bonificaciones sobre compras', 'account_type' => 'cost'],

    ['code' => '600', 'level' => null, 'name' => 'Gastos', 'account_type' => 'expense'],
    ['code' => '601.84', 'level' => 2, 'name' => 'Otros gastos generales', 'account_type' => 'expense'],
    ['code' => '701.10', 'level' => 2, 'name' => 'Comisiones bancarias', 'account_type' => 'expense'],
];

$accounts = [
    ['code' => '101.01', 'name' => 'Caja y efectivo', 'type' => 'asset', 'normal_balance' => 'debit', 'usage' => 'cash'],
    ['code' => '102.01', 'name' => 'Bancos nacionales', 'type' => 'asset', 'normal_balance' => 'debit', 'usage' => 'bank'],
    ['code' => '105.01', 'name' => 'Clientes nacionales', 'type' => 'asset', 'normal_balance' => 'debit', 'usage' => 'receivable'],
    ['code' => '115.01', 'name' => 'Inventario', 'type' => 'asset', 'normal_balance' => 'debit', 'usage' => 'inventory'],
    ['code' => '118.01', 'name' => 'IVA acreditable pagado', 'type' => 'asset', 'normal_balance' => 'debit', 'usage' => 'vat_creditable'],
    ['code' => '119.01', 'name' => 'IVA pendiente de pago', 'type' => 'asset', 'normal_balance' => 'debit', 'usage' => 'vat_creditable_pending'],
    ['code' => '201.01', 'name' => 'Proveedores nacionales', 'type' => 'liability', 'normal_balance' => 'credit', 'usage' => 'payable'],
    ['code' => '207.01', 'name' => 'IVA trasladado', 'type' => 'liability', 'normal_balance' => 'credit', 'usage' => 'vat_payable'],
    ['code' => '208.01', 'name' => 'IVA trasladado cobrado', 'type' => 'liability', 'normal_balance' => 'credit', 'usage' => 'vat_collected'],
    ['code' => '209.01', 'name' => 'IVA trasladado no cobrado', 'type' => 'liability', 'normal_balance' => 'credit', 'usage' => 'vat_not_collected'],
    ['code' => '401.01', 'name' => 'Ventas gravadas a la tasa general', 'type' => 'income', 'normal_balance' => 'credit', 'usage' => 'sales_income'],
    ['code' => '401.04', 'name' => 'Ventas gravadas al 0%', 'type' => 'income', 'normal_balance' => 'credit', 'usage' => 'sales_zero_rate'],
    ['code' => '401.07', 'name' => 'Ventas exentas', 'type' => 'income', 'normal_balance' => 'credit', 'usage' => 'sales_exempt'],
    ['code' => '402.01', 'name' => 'Devoluciones y descuentos sobre ventas', 'type' => 'income', 'normal_balance' => 'debit', 'usage' => 'sales_returns'],
    ['code' => '501.01', 'name' => 'Costo de venta', 'type' => 'cost', 'normal_balance' => 'debit', 'usage' => 'cogs'],
    ['code' => '501.08', 'name' => 'Ajustes de inventario / otros costos', 'type' => 'cost', 'normal_balance' => 'debit', 'usage' => 'inventory_adjustment'],
    ['code' => '502.01', 'name' => 'Compras nacionales', 'type' => 'cost', 'normal_balance' => 'debit', 'usage' => 'purchases'],
    ['code' => '503.01', 'name' => 'Devoluciones y descuentos sobre compras', 'type' => 'cost', 'normal_balance' => 'credit', 'usage' => 'purchase_returns'],
    ['code' => '601.84', 'name' => 'Otros gastos generales', 'type' => 'expense', 'normal_balance' => 'debit', 'usage' => 'general_expense'],
    ['code' => '701.10', 'name' => 'Comisiones bancarias', 'type' => 'expense', 'normal_balance' => 'debit', 'usage' => 'bank_fees'],
];

$journalTemplates = [
    ['code' => 'GEN', 'name' => 'Diario General', 'type' => 'general', 'default_account_code' => null],
    ['code' => 'CJA', 'name' => 'Diario de Caja', 'type' => 'cash', 'default_account_code' => '101.01'],
    ['code' => 'BNK', 'name' => 'Diario de Bancos', 'type' => 'bank', 'default_account_code' => '102.01'],
    ['code' => 'VEN', 'name' => 'Diario de Ventas', 'type' => 'sales', 'default_account_code' => '105.01'],
    ['code' => 'COM', 'name' => 'Diario de Compras', 'type' => 'purchases', 'default_account_code' => '201.01'],
    ['code' => 'POS', 'name' => 'Diario Punto de Venta', 'type' => 'pos', 'default_account_code' => '101.01'],
    ['code' => 'INV', 'name' => 'Diario de Inventario', 'type' => 'inventory', 'default_account_code' => '115.01'],
    ['code' => 'FAC', 'name' => 'Diario de Facturación / CFDI', 'type' => 'invoicing', 'default_account_code' => '105.01'],
];

$journalMappings = [
    ['module' => 'accounting', 'operation_type' => 'manual_entry', 'journal_code' => 'GEN', 'notes' => 'Pólizas manuales generales.'],
    ['module' => 'cash', 'operation_type' => 'cash_receipt', 'journal_code' => 'CJA', 'notes' => 'Entradas de efectivo.'],
    ['module' => 'cash', 'operation_type' => 'cash_payment', 'journal_code' => 'CJA', 'notes' => 'Salidas de efectivo.'],
    ['module' => 'bank', 'operation_type' => 'bank_payment', 'journal_code' => 'BNK', 'notes' => 'Pagos bancarios.'],
    ['module' => 'bank', 'operation_type' => 'bank_receipt', 'journal_code' => 'BNK', 'notes' => 'Cobros bancarios.'],
    ['module' => 'sales', 'operation_type' => 'invoice', 'journal_code' => 'VEN', 'notes' => 'Factura o venta a cliente.'],
    ['module' => 'sales', 'operation_type' => 'credit_note', 'journal_code' => 'VEN', 'notes' => 'Nota de crédito de venta.'],
    ['module' => 'purchases', 'operation_type' => 'bill', 'journal_code' => 'COM', 'notes' => 'Factura de proveedor.'],
    ['module' => 'purchases', 'operation_type' => 'vendor_credit', 'journal_code' => 'COM', 'notes' => 'Nota de crédito de proveedor.'],
    ['module' => 'pos', 'operation_type' => 'sale', 'journal_code' => 'POS', 'notes' => 'Venta de punto de venta.'],
    ['module' => 'pos', 'operation_type' => 'refund', 'journal_code' => 'POS', 'notes' => 'Devolución de punto de venta.'],
    ['module' => 'inventory', 'operation_type' => 'adjustment', 'journal_code' => 'INV', 'notes' => 'Ajuste de inventario.'],
    ['module' => 'inventory', 'operation_type' => 'transfer', 'journal_code' => 'INV', 'notes' => 'Traspaso entre almacenes.'],
    ['module' => 'inventory', 'operation_type' => 'receipt', 'journal_code' => 'INV', 'notes' => 'Entrada de inventario.'],
    ['module' => 'inventory', 'operation_type' => 'delivery', 'journal_code' => 'INV', 'notes' => 'Salida de inventario.'],
    ['module' => 'invoicing', 'operation_type' => 'cfdi_issued', 'journal_code' => 'FAC', 'notes' => 'CFDI emitido.'],
    ['module' => 'invoicing', 'operation_type' => 'cfdi_cancelled', 'journal_code' => 'FAC', 'notes' => 'CFDI cancelado.'],
];

bxTitle('1) Sembrando codigo agrupador SAT base');

foreach ($satGroupings as $grouping) {
    bxUpsert('sat_account_groupings', [
        'code' => $grouping['code'],
    ], [
        'level' => $grouping['level'],
        'name' => $grouping['name'],
        'account_type' => $grouping['account_type'],
        'is_active' => true,
    ]);

    echo "SAT {$grouping['code']} {$grouping['name']}" . PHP_EOL;
}

bxTitle('2) Empresas detectadas');

$companies = DB::table('companies')
    ->select('id', 'name')
    ->orderBy('id')
    ->get();

$companies->each(fn ($company) => print "Empresa {$company->id}: {$company->name}" . PHP_EOL);

bxTitle('3) Sembrando cuentas, diarios, settings y mapeos');

DB::transaction(function () use ($companies, $accounts, $journalTemplates, $journalMappings): void {
    foreach ($companies as $company) {
        echo PHP_EOL . "Empresa {$company->id}: {$company->name}" . PHP_EOL;

        foreach ($accounts as $account) {
            bxUpsert('accounting_accounts', [
                'company_id' => $company->id,
                'code' => $account['code'],
            ], [
                'parent_id' => null,
                'name' => $account['name'],
                'type' => $account['type'],
                'normal_balance' => $account['normal_balance'],
                'sat_grouping_code' => $account['code'],
                'account_usage' => $account['usage'],
                'allow_manual_entries' => true,
                'is_active' => true,
                'is_system' => true,
                'description' => 'Cuenta base alineada al Código agrupador SAT.',
            ]);

            echo "Cuenta {$account['code']} {$account['name']}" . PHP_EOL;
        }

        $accountIds = DB::table('accounting_accounts')
            ->where('company_id', $company->id)
            ->pluck('id', 'code');

        foreach ($journalTemplates as $journal) {
            $defaultAccountId = $journal['default_account_code']
                ? ($accountIds[$journal['default_account_code']] ?? null)
                : null;

            bxUpsert('accounting_journals', [
                'company_id' => $company->id,
                'code' => $journal['code'],
            ], [
                'name' => $journal['name'],
                'type' => $journal['type'],
                'default_account_id' => $defaultAccountId,
                'is_active' => true,
                'description' => 'Diario base generado para operación contable.',
            ]);

            echo "Diario {$journal['code']} {$journal['name']}" . PHP_EOL;
        }

        $journalIds = DB::table('accounting_journals')
            ->where('company_id', $company->id)
            ->pluck('id', 'code');

        foreach ($journalMappings as $mapping) {
            $journalId = $journalIds[$mapping['journal_code']] ?? null;

            if (! $journalId) {
                echo "WARN: no existe diario {$mapping['journal_code']} para {$mapping['module']}.{$mapping['operation_type']}" . PHP_EOL;
                continue;
            }

            bxUpsert('accounting_journal_mappings', [
                'company_id' => $company->id,
                'module' => $mapping['module'],
                'operation_type' => $mapping['operation_type'],
            ], [
                'journal_id' => $journalId,
                'is_active' => true,
                'options' => null,
                'notes' => $mapping['notes'],
            ]);

            echo "Mapeo {$mapping['module']}.{$mapping['operation_type']} -> {$mapping['journal_code']}" . PHP_EOL;
        }

        $settings = [
            'inventory_account_id' => $accountIds['115.01'] ?? null,
            'cogs_account_id' => $accountIds['501.01'] ?? null,
            'sales_income_account_id' => $accountIds['401.01'] ?? null,
            'customer_receivable_account_id' => $accountIds['105.01'] ?? null,
            'supplier_payable_account_id' => $accountIds['201.01'] ?? null,
            'vat_creditable_account_id' => $accountIds['118.01'] ?? null,
            'vat_payable_account_id' => $accountIds['207.01'] ?? null,
            'cash_account_id' => $accountIds['101.01'] ?? null,
            'bank_account_id' => $accountIds['102.01'] ?? null,
            'inventory_adjustment_account_id' => $accountIds['501.08'] ?? null,
            'default_journal_id' => $journalIds['GEN'] ?? null,
            'purchases_journal_id' => $journalIds['COM'] ?? null,
            'sales_journal_id' => $journalIds['VEN'] ?? null,
            'pos_journal_id' => $journalIds['POS'] ?? null,
            'costing_method' => 'average',
        ];

        bxUpsert('accounting_settings', [
            'company_id' => $company->id,
        ], $settings);

        echo "Configuración contable actualizada" . PHP_EOL;
    }
});

bxTitle('4) Resumen');

foreach ($companies as $company) {
    $accountsCount = DB::table('accounting_accounts')->where('company_id', $company->id)->count();
    $journalsCount = DB::table('accounting_journals')->where('company_id', $company->id)->count();
    $mappingsCount = DB::table('accounting_journal_mappings')->where('company_id', $company->id)->count();
    $settingsExists = DB::table('accounting_settings')->where('company_id', $company->id)->exists();

    echo "Empresa {$company->id}: cuentas={$accountsCount}, diarios={$journalsCount}, mapeos={$mappingsCount}, settings=" . ($settingsExists ? 'SI' : 'NO') . PHP_EOL;
}
