<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AccountingRc1Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Contabilidad';

    protected static ?string $navigationLabel = 'Resumen contable';

    protected static ?string $title = 'Resumen contable';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.accounting-rc1-dashboard';
    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    public static function tableLabel(?string $table): string
    {
        return [
            'purchase_orders' => 'Compras',
            'purchase_order_lines' => 'Líneas de compra',
            'sales_orders' => 'Ventas',
            'sales_order_lines' => 'Líneas de venta',
            'pos_orders' => 'Tickets POS',
            'pos_order_lines' => 'Líneas POS',
            'pos_order_refunds' => 'Devoluciones POS',
            'pos_order_refund_lines' => 'Líneas de devolución POS',
        ][$table] ?? ($table ?: 'Sin nombre');
    }

    public static function statusLabel(?string $status): string
    {
        return [
            'posted' => 'Contabilizado',
            'not_posted' => 'Sin contabilizar',
            'partial' => 'Parcialmente contabilizado',
            'error' => 'Con error',
            'draft' => 'Borrador',
            'cancelled' => 'Cancelado',
            'canceled' => 'Cancelado',
            'not_applicable' => 'No aplica',
            'done' => 'Terminado',
            'paid' => 'Pagado',
            'received' => 'Recibido',
            'delivered' => 'Entregado',
            'returned' => 'Devuelto',
            'partial_refunded' => 'Parcialmente devuelto',
            'partially_refunded' => 'Parcialmente devuelto',
        ][$status] ?? ($status ?: 'Sin estatus');
    }

    public static function sourceLabel(?string $source): string
    {
        return [
            'inventory.adjustment_in:manual_inventory' => 'Ajuste manual de inventario',
            'inventory.sale_issue:sales_order_lines' => 'Costo de venta',
            'inventory.purchase_receipt:purchase_order_lines' => 'Entrada por compra',
            'inventory.sale_issue:pos_order_lines' => 'Costo POS',
            'inventory.customer_return:pos_order_refund_lines' => 'Devolución POS',
            'accounting.reversal' => 'Reversa contable',
            'invoice' => 'Factura',
        ][$source] ?? ($source ?: 'Sin origen');
    }

    public static function operationLabel(?string $operation): string
    {
        return [
            'purchase_receipt' => 'Entrada por compra',
            'sale_issue' => 'Salida por venta',
            'adjustment_in' => 'Ajuste positivo',
            'adjustment_out' => 'Ajuste negativo',
            'customer_return' => 'Devolución de cliente',
            'supplier_return' => 'Devolución a proveedor',
            'reversal' => 'Reversa',
        ][$operation] ?? ($operation ?: 'Sin operación');
    }

    public function stats(): array
    {
        return [
            'entries_total' => $this->countTable('accounting_entries'),
            'entry_lines_total' => $this->countTable('accounting_entry_lines'),
            'valuation_layers_total' => $this->countTable('accounting_inventory_valuation_layers'),
            'posting_audits_total' => $this->countTable('accounting_posting_audits'),
            'unbalanced_entries' => $this->unbalancedEntriesCount(),
            'duplicate_sources' => $this->duplicateSourcesCount(),
            'inventory_entries_without_layer' => $this->inventoryEntriesWithoutLayerCount(),
            'pos_products_without_cost' => $this->posProductsWithoutCostCount(),
        ];
    }

    public function accountingStatusSummary(): array
    {
        $tables = [
            'purchase_orders',
            'purchase_order_lines',
            'sales_orders',
            'sales_order_lines',
            'pos_orders',
            'pos_order_lines',
            'pos_order_refunds',
            'pos_order_refund_lines',
        ];

        $rows = [];

        foreach ($tables as $table) {
            if (! $this->hasTable($table) || ! Schema::hasColumn($table, 'accounting_status')) {
                continue;
            }

            try {
                $counts = DB::table($table)
                    ->select('accounting_status', DB::raw('count(*) as total'))
                    ->groupBy('accounting_status')
                    ->orderBy('accounting_status')
                    ->get()
                    ->map(fn ($row) => [
                        'status' => (string) ($row->accounting_status ?? 'null'),
                        'status_label' => self::statusLabel((string) ($row->accounting_status ?? '')),
                        'total' => (int) $row->total,
                    ])
                    ->all();

                $rows[] = [
                    'table' => $table,
                    'table_label' => self::tableLabel($table),
                    'counts' => $counts,
                ];
            } catch (Throwable $e) {
                $rows[] = [
                    'table' => $table,
                    'table_label' => self::tableLabel($table),
                    'counts' => [
                        [
                            'status' => 'error',
                            'status_label' => 'Error al leer',
                            'total' => $e->getMessage(),
                        ],
                    ],
                ];
            }
        }

        return $rows;
    }

    public function totalsByAccount(): array
    {
        if (! $this->hasTable('accounting_entry_lines') || ! $this->hasTable('accounting_accounts')) {
            return [];
        }

        try {
            return DB::select("
                select
                    a.company_id,
                    a.code,
                    a.name,
                    count(l.id) as line_count,
                    coalesce(sum(l.debit), 0) as debit,
                    coalesce(sum(l.credit), 0) as credit,
                    coalesce(sum(l.debit), 0) - coalesce(sum(l.credit), 0) as balance
                from accounting_entry_lines l
                join accounting_accounts a on a.id = l.account_id
                group by a.company_id, a.code, a.name
                order by a.company_id, a.code
            ");
        } catch (Throwable $e) {
            return [];
        }
    }

    public function totalsBySourceType(): array
    {
        if (! $this->hasTable('accounting_entries')) {
            return [];
        }

        try {
            $rows = DB::select("
                select
                    source_type,
                    count(*) as entries,
                    coalesce(sum(total_debit), 0) as debit,
                    coalesce(sum(total_credit), 0) as credit
                from accounting_entries
                group by source_type
                order by source_type
            ");

            foreach ($rows as $row) {
                $row->source_type_label = self::sourceLabel($row->source_type ?? null);
            }

            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }

    public function valuationByProduct(): array
    {
        if (! $this->hasTable('accounting_inventory_valuation_layers')) {
            return [];
        }

        try {
            return DB::select("
                select
                    company_id,
                    product_id,
                    coalesce(sum(case when direction = 'in' then quantity else -quantity end), 0) as net_quantity,
                    coalesce(sum(case when direction = 'in' then total_cost else -total_cost end), 0) as net_value
                from accounting_inventory_valuation_layers
                group by company_id, product_id
                order by company_id, product_id
                limit 50
            ");
        } catch (Throwable $e) {
            return [];
        }
    }

    private function countTable(string $table): int
    {
        try {
            if (! $this->hasTable($table)) {
                return 0;
            }

            return (int) DB::table($table)->count();
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function unbalancedEntriesCount(): int
    {
        if (! $this->hasTable('accounting_entries') || ! $this->hasTable('accounting_entry_lines')) {
            return 0;
        }

        try {
            $rows = DB::select("
                select count(*) as total
                from (
                    select
                        e.id,
                        abs(coalesce(sum(l.debit), 0) - coalesce(sum(l.credit), 0)) as difference
                    from accounting_entries e
                    left join accounting_entry_lines l on l.accounting_entry_id = e.id
                    group by e.id
                ) x
                where difference > 0.0001
            ");

            return (int) ($rows[0]->total ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function duplicateSourcesCount(): int
    {
        if (! $this->hasTable('accounting_entries')) {
            return 0;
        }

        try {
            $rows = DB::select("
                select count(*) as total
                from (
                    select source_type, source_id, count(*) as total
                    from accounting_entries
                    where source_type is not null
                      and source_id is not null
                      and status in ('draft', 'posted')
                    group by source_type, source_id
                    having count(*) > 1
                ) x
            ");

            return (int) ($rows[0]->total ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function inventoryEntriesWithoutLayerCount(): int
    {
        if (! $this->hasTable('accounting_entries') || ! $this->hasTable('accounting_inventory_valuation_layers')) {
            return 0;
        }

        try {
            $rows = DB::select("
                select count(*) as total
                from accounting_entries e
                left join accounting_inventory_valuation_layers v on v.accounting_entry_id = e.id
                where e.source_type like 'inventory.%'
                  and v.id is null
            ");

            return (int) ($rows[0]->total ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function posProductsWithoutCostCount(): int
    {
        if (! $this->hasTable('pos_order_lines') || ! $this->hasTable('pos_orders')) {
            return 0;
        }

        try {
            $products = DB::table('pos_order_lines as l')
                ->join('pos_orders as o', 'o.id', '=', 'l.pos_order_id')
                ->whereIn('o.status', ['paid', 'returned', 'partial_refunded', 'partially_refunded'])
                ->whereNotNull('l.product_id')
                ->select('o.company_id', 'l.product_id')
                ->distinct()
                ->get();

            $missing = 0;

            foreach ($products as $product) {
                if ($this->resolveProductCost((int) $product->company_id, (int) $product->product_id) <= 0) {
                    $missing++;
                }
            }

            return $missing;
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function resolveProductCost(int $companyId, int $productId): float
    {
        try {
            if ($this->hasTable('stock_balances') && Schema::hasColumn('stock_balances', 'average_cost_without_tax')) {
                $cost = DB::table('stock_balances')
                    ->where('company_id', $companyId)
                    ->where('product_id', $productId)
                    ->where('average_cost_without_tax', '>', 0)
                    ->orderByDesc('updated_at')
                    ->value('average_cost_without_tax');

                if ((float) $cost > 0) {
                    return (float) $cost;
                }
            }

            if ($this->hasTable('products')) {
                $product = DB::table('products')->where('id', $productId)->first();

                if ($product) {
                    foreach (['average_cost_without_tax', 'standard_cost', 'purchase_price', 'last_purchase_cost', 'cost', 'cost_price'] as $field) {
                        if (property_exists($product, $field) && (float) $product->{$field} > 0) {
                            return (float) $product->{$field};
                        }
                    }
                }
            }

            if ($this->hasTable('accounting_inventory_valuation_layers')) {
                $cost = DB::table('accounting_inventory_valuation_layers')
                    ->where('company_id', $companyId)
                    ->where('product_id', $productId)
                    ->where('unit_cost', '>', 0)
                    ->orderByDesc('movement_date')
                    ->orderByDesc('id')
                    ->value('unit_cost');

                if ((float) $cost > 0) {
                    return (float) $cost;
                }
            }
        } catch (Throwable $e) {
            return 0;
        }

        return 0;
    }

    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable $e) {
            return false;
        }
    }
    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'pages.accountingrc1dashboard',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('accounting.view')
            );
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('accounting.view')
            );
    }

}
