<?php

namespace App\Support\Accounting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AccountingReportsService
{
    public function dashboard(?int $companyId = null): array
    {
        return [
            'summary' => $this->summary($companyId),
            'trial_balance' => $this->trialBalance($companyId),
            'ledger' => $this->ledger($companyId),
            'source_totals' => $this->sourceTotals($companyId),
            'inventory_valuation' => $this->inventoryValuation($companyId),
            'alerts' => $this->alerts($companyId),
        ];
    }

    public function summary(?int $companyId = null): array
    {
        return [
            'entries' => $this->countTable('accounting_entries', $companyId),
            'lines' => $this->countTable('accounting_entry_lines', $companyId),
            'valuation_layers' => $this->countTable('accounting_inventory_valuation_layers', $companyId),
            'audits' => $this->countTable('accounting_posting_audits', $companyId),
        ];
    }

    public function trialBalance(?int $companyId = null): array
    {
        if (! $this->hasTable('accounting_entry_lines') || ! $this->hasTable('accounting_accounts')) {
            return [];
        }

        try {
            $query = DB::table('accounting_entry_lines as l')
                ->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')
                ->selectRaw("
                    a.company_id,
                    a.code,
                    a.name,
                    count(l.id) as line_count,
                    coalesce(sum(l.debit), 0) as debit,
                    coalesce(sum(l.credit), 0) as credit,
                    coalesce(sum(l.debit), 0) - coalesce(sum(l.credit), 0) as balance
                ")
                ->groupBy('a.company_id', 'a.code', 'a.name')
                ->orderBy('a.company_id')
                ->orderBy('a.code');

            if ($companyId && Schema::hasColumn('accounting_entry_lines', 'company_id')) {
                $query->where('l.company_id', $companyId);
            } elseif ($companyId) {
                $query->where('a.company_id', $companyId);
            }

            return $query->get()->map(fn ($row) => (array) $row)->all();
        } catch (Throwable $e) {
            return [
                [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    public function ledger(?int $companyId = null): array
    {
        if (! $this->hasTable('accounting_entry_lines') || ! $this->hasTable('accounting_entries') || ! $this->hasTable('accounting_accounts')) {
            return [];
        }

        try {
            $query = DB::table('accounting_entry_lines as l')
                ->join('accounting_entries as e', 'e.id', '=', 'l.accounting_entry_id')
                ->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')
                ->selectRaw("
                    e.id as entry_id,
                    e.entry_number,
                    e.entry_date,
                    e.source_type,
                    e.source_id,
                    a.code as account_code,
                    a.name as account_name,
                    l.label,
                    l.debit,
                    l.credit,
                    l.created_at
                ")
                ->orderByDesc('e.entry_date')
                ->orderByDesc('e.id')
                ->orderBy('l.line_number')
                ->limit(200);

            if ($companyId && Schema::hasColumn('accounting_entry_lines', 'company_id')) {
                $query->where('l.company_id', $companyId);
            } elseif ($companyId && Schema::hasColumn('accounting_entries', 'company_id')) {
                $query->where('e.company_id', $companyId);
            }

            return $query->get()->map(fn ($row) => (array) $row)->all();
        } catch (Throwable $e) {
            return [
                [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    public function sourceTotals(?int $companyId = null): array
    {
        if (! $this->hasTable('accounting_entries')) {
            return [];
        }

        try {
            $query = DB::table('accounting_entries')
                ->selectRaw("
                    source_type,
                    count(*) as entries,
                    coalesce(sum(total_debit), 0) as debit,
                    coalesce(sum(total_credit), 0) as credit
                ")
                ->groupBy('source_type')
                ->orderBy('source_type');

            if ($companyId && Schema::hasColumn('accounting_entries', 'company_id')) {
                $query->where('company_id', $companyId);
            }

            return $query->get()
                ->map(function ($row) {
                    $item = (array) $row;
                    $item['source_label'] = $this->sourceLabel($item['source_type'] ?? null);

                    return $item;
                })
                ->all();
        } catch (Throwable $e) {
            return [
                [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    public function inventoryValuation(?int $companyId = null): array
    {
        if (! $this->hasTable('accounting_inventory_valuation_layers')) {
            return [];
        }

        try {
            $query = DB::table('accounting_inventory_valuation_layers as v')
                ->leftJoin('products as p', 'p.id', '=', 'v.product_id')
                ->selectRaw("
                    v.company_id,
                    v.product_id,
                    max(coalesce(p.name, p.product_name, p.description, '')) as product_name,
                    max(coalesce(p.reference, p.default_code, p.code, p.sku, '')) as product_reference,
                    coalesce(sum(case when v.direction = 'in' then v.quantity else -v.quantity end), 0) as net_quantity,
                    coalesce(sum(case when v.direction = 'in' then v.total_cost else -v.total_cost end), 0) as net_value,
                    count(v.id) as layers
                ")
                ->groupBy('v.company_id', 'v.product_id')
                ->orderBy('v.company_id')
                ->orderBy('v.product_id')
                ->limit(200);

            if ($companyId && Schema::hasColumn('accounting_inventory_valuation_layers', 'company_id')) {
                $query->where('v.company_id', $companyId);
            }

            return $query->get()->map(fn ($row) => (array) $row)->all();
        } catch (Throwable $e) {
            return [
                [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    public function alerts(?int $companyId = null): array
    {
        return [
            'unbalanced_entries' => $this->unbalancedEntriesCount($companyId),
            'entries_without_lines' => $this->entriesWithoutLinesCount($companyId),
            'inventory_entries_without_layer' => $this->inventoryEntriesWithoutLayerCount($companyId),
            'duplicate_sources' => $this->duplicateSourcesCount($companyId),
            'pos_products_without_cost' => $this->posProductsWithoutCostCount($companyId),
        ];
    }

    public function sourceLabel(?string $source): string
    {
        return [
            'accounting.reversal' => 'Reversa contable',
            'inventory.adjustment_in:manual_inventory' => 'Ajuste manual de inventario',
            'inventory.purchase_receipt:purchase_order_lines' => 'Entrada por compra',
            'inventory.sale_issue:sales_order_lines' => 'Costo de venta',
            'inventory.sale_issue:pos_order_lines' => 'Costo POS',
            'inventory.customer_return:pos_order_refund_lines' => 'Devolución POS',
            'invoice' => 'Factura interna',
        ][$source] ?? ($source ?: 'Sin origen');
    }

    private function countTable(string $table, ?int $companyId = null): int
    {
        try {
            if (! $this->hasTable($table)) {
                return 0;
            }

            $query = DB::table($table);

            if ($companyId && Schema::hasColumn($table, 'company_id')) {
                $query->where('company_id', $companyId);
            }

            return (int) $query->count();
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function unbalancedEntriesCount(?int $companyId = null): int
    {
        if (! $this->hasTable('accounting_entries') || ! $this->hasTable('accounting_entry_lines')) {
            return 0;
        }

        try {
            $companyWhere = '';

            if ($companyId && Schema::hasColumn('accounting_entries', 'company_id')) {
                $companyWhere = 'where e.company_id = ' . (int) $companyId;
            }

            $rows = DB::select("
                select count(*) as total
                from (
                    select
                        e.id,
                        abs(coalesce(sum(l.debit), 0) - coalesce(sum(l.credit), 0)) as difference
                    from accounting_entries e
                    left join accounting_entry_lines l on l.accounting_entry_id = e.id
                    {$companyWhere}
                    group by e.id
                ) x
                where difference > 0.0001
            ");

            return (int) ($rows[0]->total ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function entriesWithoutLinesCount(?int $companyId = null): int
    {
        if (! $this->hasTable('accounting_entries') || ! $this->hasTable('accounting_entry_lines')) {
            return 0;
        }

        try {
            $query = DB::table('accounting_entries as e')
                ->leftJoin('accounting_entry_lines as l', 'l.accounting_entry_id', '=', 'e.id')
                ->whereNull('l.id');

            if ($companyId && Schema::hasColumn('accounting_entries', 'company_id')) {
                $query->where('e.company_id', $companyId);
            }

            return (int) $query->count();
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function inventoryEntriesWithoutLayerCount(?int $companyId = null): int
    {
        if (! $this->hasTable('accounting_entries') || ! $this->hasTable('accounting_inventory_valuation_layers')) {
            return 0;
        }

        try {
            $query = DB::table('accounting_entries as e')
                ->leftJoin('accounting_inventory_valuation_layers as v', 'v.accounting_entry_id', '=', 'e.id')
                ->where('e.source_type', 'like', 'inventory.%')
                ->whereNull('v.id');

            if ($companyId && Schema::hasColumn('accounting_entries', 'company_id')) {
                $query->where('e.company_id', $companyId);
            }

            return (int) $query->count();
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function duplicateSourcesCount(?int $companyId = null): int
    {
        if (! $this->hasTable('accounting_entries')) {
            return 0;
        }

        try {
            $query = DB::table('accounting_entries')
                ->selectRaw('source_type, source_id, count(*) as total')
                ->whereNotNull('source_type')
                ->whereNotNull('source_id')
                ->whereIn('status', ['draft', 'posted'])
                ->groupBy('source_type', 'source_id')
                ->havingRaw('count(*) > 1');

            if ($companyId && Schema::hasColumn('accounting_entries', 'company_id')) {
                $query->where('company_id', $companyId);
            }

            return (int) DB::query()->fromSub($query, 'x')->count();
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function posProductsWithoutCostCount(?int $companyId = null): int
    {
        if (! $this->hasTable('pos_order_lines') || ! $this->hasTable('pos_orders')) {
            return 0;
        }

        try {
            $query = DB::table('pos_order_lines as l')
                ->join('pos_orders as o', 'o.id', '=', 'l.pos_order_id')
                ->whereIn('o.status', ['paid', 'returned', 'partial_refunded', 'partially_refunded'])
                ->whereNotNull('l.product_id')
                ->select('o.company_id', 'l.product_id')
                ->distinct();

            if ($companyId && Schema::hasColumn('pos_orders', 'company_id')) {
                $query->where('o.company_id', $companyId);
            }

            $products = $query->get();
            $missing = 0;

            foreach ($products as $row) {
                if ($this->resolveProductCost((int) $row->company_id, (int) $row->product_id) <= 0) {
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
                    foreach ([
                        'average_cost_without_tax',
                        'standard_cost',
                        'purchase_price',
                        'last_purchase_cost',
                        'cost',
                        'cost_price',
                    ] as $field) {
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
}
