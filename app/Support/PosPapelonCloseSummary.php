<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PosPapelonCloseSummary
{
    public static function build(int $sessionId): array
    {
        return (new self())->handle($sessionId);
    }

    protected function handle(int $sessionId): array
    {
        foreach (['pos_sessions', 'pos_orders', 'pos_order_payments', 'pos_order_lines', 'products', 'product_categories'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->empty("No existe tabla {$table}.");
            }
        }

        $session = DB::table('pos_sessions')->where('id', $sessionId)->first();

        if (! $session) {
            return $this->empty('Sesión no encontrada.');
        }

        $openedAt = $session->opened_at ?? $session->created_at ?? null;
        $closedAt = $session->closed_at ?? null;
        $companyId = (int) ($session->company_id ?? 0);
        $posPointId = (int) ($session->pos_point_id ?? 0);

        $paymentsQuery = DB::table('pos_order_payments as p')
            ->join('pos_orders as o', 'o.id', '=', 'p.pos_order_id')
            ->where('o.pos_point_id', $posPointId);

        if ($companyId > 0 && Schema::hasColumn('pos_orders', 'company_id')) {
            $paymentsQuery->where('o.company_id', $companyId);
        }

        $paymentsQuery = $this->applyOperationalOrderScope($paymentsQuery, 'o');

        if (Schema::hasColumn('pos_order_payments', 'status')) {
            $paymentsQuery->where(function ($q) {
                $q->whereNull('p.status')
                    ->orWhereIn('p.status', ['paid', 'done', 'posted']);
            });
        }

        if (Schema::hasColumn('pos_order_payments', 'pos_session_id')) {
            $paymentsQuery->where('p.pos_session_id', $session->id);
        } else {
            if ($openedAt) {
                $paymentsQuery->where('p.created_at', '>=', $openedAt);
            }

            if (($session->status ?? null) === 'closed' && $closedAt) {
                $paymentsQuery->where('p.created_at', '<=', $closedAt);
            }
        }

        $payments = $paymentsQuery->get([
            'p.id',
            'p.pos_order_id',
            'p.payment_label',
            'p.amount',
            'p.created_at',
            'o.number as order_number',
            'o.total as order_total',
        ]);

        $orderIds = $payments->pluck('pos_order_id')->filter()->unique()->values();
        $refunds = $this->refunds($session, $orderIds);

        if ($orderIds->isEmpty()) {
            return $this->result([], [], [], $refunds, 0, 0, 0, 0);
        }

        $lines = DB::table('pos_order_lines as l')
            ->join('pos_orders as o', 'o.id', '=', 'l.pos_order_id')
            ->leftJoin('products as p', 'p.id', '=', 'l.product_id')
            ->whereIn('l.pos_order_id', $orderIds)
            ->orderBy('o.number')
            ->orderBy('l.id')
            ->get([
                'l.id',
                'l.pos_order_id',
                'o.number as order_number',
                'o.total as order_total',
                'l.product_id',
                'l.product_name',
                'l.quantity',
                'l.unit_price',
                'l.subtotal',
                'l.tax_total',
                'l.total',
                'p.product_category_id',
            ]);

        $categories = DB::table('product_categories')
            ->get(['id', 'name', 'parent_id'])
            ->keyBy('id');

        $paymentsByOrder = $payments->groupBy('pos_order_id');

        $grossByOrder = [];
        $grossByLine = [];

        foreach ($lines as $line) {
            $amount = $this->lineAmount($line);
            $grossByLine[(int) $line->id] = $amount;
            $grossByOrder[(int) $line->pos_order_id] = ($grossByOrder[(int) $line->pos_order_id] ?? 0) + $amount;
        }

        $sectionMethods = [];
        $methodTotals = [];
        $sectionProducts = [];
        $sectionTotals = [];

        foreach ($lines as $line) {
            $lineGross = round((float) ($grossByLine[(int) $line->id] ?? 0), 4);

            if ($lineGross <= 0) {
                continue;
            }

            $orderPayments = $paymentsByOrder->get($line->pos_order_id, collect());
            $orderPaymentTotal = round((float) $orderPayments->sum('amount'), 4);
            $orderLineGross = round((float) ($grossByOrder[(int) $line->pos_order_id] ?? 0), 4);

            $lineNet = $lineGross;

            if ($orderPaymentTotal > 0 && $orderLineGross > 0) {
                $lineNet = round($lineGross * ($orderPaymentTotal / $orderLineGross), 4);
            }

            $path = $this->categoryPath(
                $line->product_category_id ? (int) $line->product_category_id : null,
                $categories
            );

            $section = $this->pathContains($path, 'Impresión y Copias')
                ? 'IMPRESIÓN Y COPIAS'
                : 'PAPELÓN';

            if ($orderPayments->isNotEmpty()) {
                foreach ($orderPayments as $payment) {
                    $method = $this->paymentMethod($payment->payment_label ?? null);
                    $ratio = $orderPaymentTotal > 0 ? ((float) $payment->amount / $orderPaymentTotal) : 1.0;
                    $amount = round($lineNet * $ratio, 4);

                    $sectionMethods[$section][$method] = ($sectionMethods[$section][$method] ?? 0) + $amount;
                    $methodTotals[$method] = ($methodTotals[$method] ?? 0) + $amount;
                    $sectionTotals[$section] = ($sectionTotals[$section] ?? 0) + $amount;
                }
            } else {
                $method = 'Sin método';

                $sectionMethods[$section][$method] = ($sectionMethods[$section][$method] ?? 0) + $lineNet;
                $methodTotals[$method] = ($methodTotals[$method] ?? 0) + $lineNet;
                $sectionTotals[$section] = ($sectionTotals[$section] ?? 0) + $lineNet;
            }

            $productName = html_entity_decode(
                trim((string) ($line->product_name ?: ('Producto #' . ($line->product_id ?? '')))),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );

            $productName = $productName !== '' ? $productName : 'Producto';
            $pathLabel = $path ? implode(' > ', array_column($path, 'name')) : '[SIN RUTA]';

            if (! isset($sectionProducts[$section][$productName])) {
                $sectionProducts[$section][$productName] = [
                    'name' => $productName,
                    'qty' => 0,
                    'total' => 0,
                    'path' => $pathLabel,
                ];
            }

            $sectionProducts[$section][$productName]['qty'] += (float) ($line->quantity ?? 0);
            $sectionProducts[$section][$productName]['total'] += $lineNet;
        }

        $grossTotal = round((float) array_sum($sectionTotals), 2);
        $paymentsTotal = round((float) $payments->sum('amount'), 2);

        if ($grossTotal <= 0 && $paymentsTotal > 0) {
            $grossTotal = $paymentsTotal;
        }

        return $this->result(
            $sectionMethods,
            $methodTotals,
            $sectionProducts,
            $refunds,
            $grossTotal,
            $paymentsTotal,
            $orderIds->count(),
            $lines->count()
        );
    }


    /**
     * BEXIA_V582_P3_XLSM_A33A2_PAPELON_OPERATIONAL_SCOPE
     *
     * Evita que el formato especial de cierre Papelon vuelva a sumar
     * movimientos migrados de solo consulta.
     */
    protected function applyOperationalOrderScope($query, string $alias = 'o')
    {
        $prefix = $alias !== '' ? ($alias . '.') : '';

        if (Schema::hasColumn('pos_orders', 'is_legacy')) {
            $query->where(function ($q) use ($prefix) {
                $q->whereNull($prefix . 'is_legacy')
                    ->orWhere($prefix . 'is_legacy', false);
            });
        }

        if (Schema::hasColumn('pos_orders', 'migration_batch_id')) {
            $query->whereNull($prefix . 'migration_batch_id');
        }

        if (Schema::hasColumn('pos_orders', 'source_system')) {
            $query->where(function ($q) use ($prefix) {
                $q->whereNull($prefix . 'source_system')
                    ->orWhereRaw(
                        "UPPER(TRIM(COALESCE({$prefix}source_system, ''))) <> ?",
                        ['PAPELON_XLSM']
                    );
            });
        }

        return $query;
    }

    protected function lineAmount(object $line): float
    {
        $total = isset($line->total) && is_numeric($line->total) ? (float) $line->total : 0.0;

        if ($total > 0) {
            return round($total, 4);
        }

        $subtotal = isset($line->subtotal) && is_numeric($line->subtotal) ? (float) $line->subtotal : 0.0;
        $tax = isset($line->tax_total) && is_numeric($line->tax_total) ? (float) $line->tax_total : 0.0;

        if (($subtotal + $tax) > 0) {
            return round($subtotal + $tax, 4);
        }

        $qty = isset($line->quantity) && is_numeric($line->quantity) ? (float) $line->quantity : 0.0;
        $price = isset($line->unit_price) && is_numeric($line->unit_price) ? (float) $line->unit_price : 0.0;

        if ($qty > 0 && $price >= 0) {
            return round($qty * $price, 4);
        }

        return 0.0;
    }

    protected function refunds(object $session, $orderIds): array
    {
        $summary = [
            'refunded_total' => 0.0,
            'total_refunds_total' => 0.0,
            'partial_refunds_total' => 0.0,
            'other_refunds_total' => 0.0,
            'total_refunds_count' => 0,
            'partial_refunds_count' => 0,
            'other_refunds_count' => 0,
            'refunds_count' => 0,
        ];

        if (! Schema::hasTable('pos_order_refunds')) {
            return $summary;
        }

        $columns = Schema::getColumnListing('pos_order_refunds');

        $amountColumn = null;

        foreach (['payment_total', 'refund_total', 'total', 'amount', 'subtotal'] as $column) {
            if (in_array($column, $columns, true)) {
                $amountColumn = $column;
                break;
            }
        }

        if (! $amountColumn) {
            return $summary;
        }

        $query = DB::table('pos_order_refunds as r');

        if (in_array('pos_order_id', $columns, true) && Schema::hasTable('pos_orders')) {
            $query->leftJoin('pos_orders as o', 'o.id', '=', 'r.pos_order_id');
        }

        if (in_array('pos_session_id', $columns, true)) {
            $query->where('r.pos_session_id', (int) $session->id);
        } elseif ($orderIds && $orderIds->isNotEmpty() && in_array('pos_order_id', $columns, true)) {
            $query->whereIn('r.pos_order_id', $orderIds);
        } elseif (in_array('created_at', $columns, true)) {
            if (! empty($session->opened_at)) {
                $query->where('r.created_at', '>=', $session->opened_at);
            }

            if (($session->status ?? null) === 'closed' && ! empty($session->closed_at)) {
                $query->where('r.created_at', '<=', $session->closed_at);
            }
        }

        if (in_array('company_id', $columns, true) && ! empty($session->company_id)) {
            $query->where('r.company_id', (int) $session->company_id);
        } elseif (Schema::hasColumn('pos_orders', 'company_id') && ! empty($session->company_id)) {
            $query->where('o.company_id', (int) $session->company_id);
        }

        if (in_array('pos_point_id', $columns, true) && ! empty($session->pos_point_id)) {
            $query->where('r.pos_point_id', (int) $session->pos_point_id);
        } elseif (Schema::hasColumn('pos_orders', 'pos_point_id') && ! empty($session->pos_point_id)) {
            $query->where('o.pos_point_id', (int) $session->pos_point_id);
        }

        if (in_array('status', $columns, true)) {
            $query->whereRaw("LOWER(COALESCE(r.status, '')) NOT IN ('cancelled', 'canceled', 'void')");
        }

        $select = [
            DB::raw("r.{$amountColumn} as refund_amount"),
        ];

        if (in_array('type', $columns, true)) {
            $select[] = DB::raw('r.type as refund_type');
        } else {
            $select[] = DB::raw('NULL as refund_type');
        }

        foreach ($query->get($select) as $row) {
            $amount = round(abs((float) ($row->refund_amount ?? 0)), 2);

            if ($amount <= 0) {
                continue;
            }

            $type = $this->norm($row->refund_type ?? '');

            $summary['refunded_total'] += $amount;
            $summary['refunds_count']++;

            if (str_contains($type, 'total')) {
                $summary['total_refunds_total'] += $amount;
                $summary['total_refunds_count']++;
            } elseif (str_contains($type, 'partial') || str_contains($type, 'parcial')) {
                $summary['partial_refunds_total'] += $amount;
                $summary['partial_refunds_count']++;
            } else {
                $summary['other_refunds_total'] += $amount;
                $summary['other_refunds_count']++;
            }
        }

        foreach (['refunded_total', 'total_refunds_total', 'partial_refunds_total', 'other_refunds_total'] as $key) {
            $summary[$key] = round((float) $summary[$key], 2);
        }

        return $summary;
    }

    protected function result(array $sectionMethods, array $methodTotals, array $sectionProducts, array $refunds, float $grossTotal, float $paymentsTotal, int $ordersCount, int $linesCount): array
    {
        $sections = [];

        foreach (['PAPELÓN', 'IMPRESIÓN Y COPIAS'] as $name) {
            $sections[] = [
                'name' => $name,
                'methods' => $this->sortedMethods($sectionMethods[$name] ?? [], true),
                'total' => round((float) ($sectionMethods[$name] ?? [] ? array_sum($sectionMethods[$name]) : 0), 2),
            ];
        }

        foreach ($sectionMethods as $name => $methods) {
            if (in_array($name, ['PAPELÓN', 'IMPRESIÓN Y COPIAS'], true)) {
                continue;
            }

            $sections[] = [
                'name' => $name,
                'methods' => $this->sortedMethods($methods, true),
                'total' => round((float) array_sum($methods), 2),
            ];
        }

        $productsBySection = [];

        foreach ($sectionProducts as $section => $products) {
            uasort($products, fn ($a, $b) => ($b['total'] <=> $a['total']));

            $productsBySection[$section] = array_map(function ($row) {
                return [
                    'name' => (string) ($row['name'] ?? 'Producto'),
                    'qty' => round((float) ($row['qty'] ?? 0), 4),
                    'total' => round((float) ($row['total'] ?? 0), 2),
                    'path' => (string) ($row['path'] ?? ''),
                ];
            }, array_values($products));
        }

        $refundedTotal = round((float) ($refunds['refunded_total'] ?? 0), 2);
        $netTotal = round($grossTotal - $refundedTotal, 2);

        return [
            'format' => 'papelon',
            'ok' => true,
            'message' => null,
            'sections' => $sections,
            'method_totals' => $this->sortedMethods($methodTotals, true),
            'products_by_section' => $productsBySection,
            'refunds' => $refunds,
            'totals' => [
                'gross_total' => round($grossTotal, 2),
                'payments_total' => round($paymentsTotal, 2),
                'refunded_total' => $refundedTotal,
                'net_total' => $netTotal,
                'orders_count' => $ordersCount,
                'lines_count' => $linesCount,
            ],
        ];
    }

    protected function paymentMethod(?string $label): string
    {
        $key = $this->norm($label);

        if (str_contains($key, 'efectivo') || str_contains($key, 'cash')) {
            return 'Efectivo';
        }

        if (str_contains($key, 'tarjeta') || str_contains($key, 'debito') || str_contains($key, 'credito') || str_contains($key, 'card')) {
            return 'Tarjeta';
        }

        if (str_contains($key, 'transfer') || str_contains($key, 'spei')) {
            return 'Transferencia';
        }

        $label = trim((string) $label);

        return $label !== '' ? $label : 'Sin método';
    }

    protected function sortedMethods(array $methods, bool $standardRows): array
    {
        if ($standardRows) {
            foreach (['Efectivo', 'Tarjeta', 'Transferencia'] as $method) {
                $methods[$method] = $methods[$method] ?? 0.0;
            }
        }

        $order = [
            'Efectivo' => 10,
            'Tarjeta' => 20,
            'Transferencia' => 30,
            'Sin método' => 90,
        ];

        uksort($methods, function ($a, $b) use ($order) {
            $oa = $order[$a] ?? 50;
            $ob = $order[$b] ?? 50;

            return $oa === $ob ? strcmp((string) $a, (string) $b) : ($oa <=> $ob);
        });

        $rows = [];

        foreach ($methods as $method => $total) {
            $rows[] = [
                'method' => (string) $method,
                'total' => round((float) $total, 2),
            ];
        }

        return $rows;
    }

    protected function categoryPath(?int $categoryId, $categories): array
    {
        $path = [];
        $seen = [];

        while ($categoryId && isset($categories[$categoryId])) {
            if (isset($seen[$categoryId])) {
                break;
            }

            $seen[$categoryId] = true;
            $cat = $categories[$categoryId];

            array_unshift($path, [
                'id' => (int) $cat->id,
                'name' => (string) ($cat->name ?? ''),
                'parent_id' => ! empty($cat->parent_id) ? (int) $cat->parent_id : null,
            ]);

            $categoryId = ! empty($cat->parent_id) ? (int) $cat->parent_id : null;
        }

        return $path;
    }

    protected function pathContains(array $path, string $needle): bool
    {
        $needle = $this->norm($needle);

        foreach ($path as $category) {
            if ($this->norm($category['name'] ?? '') === $needle) {
                return true;
            }
        }

        return false;
    }

    protected function norm(?string $value): string
    {
        $value = trim((string) $value);
        $value = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '', $value);

        return $value ?: '';
    }

    protected function empty(string $message): array
    {
        return [
            'format' => 'papelon',
            'ok' => false,
            'message' => $message,
            'sections' => [],
            'method_totals' => $this->sortedMethods([], true),
            'products_by_section' => [],
            'refunds' => [
                'refunded_total' => 0,
                'total_refunds_total' => 0,
                'partial_refunds_total' => 0,
                'other_refunds_total' => 0,
                'total_refunds_count' => 0,
                'partial_refunds_count' => 0,
                'other_refunds_count' => 0,
                'refunds_count' => 0,
            ],
            'totals' => [
                'gross_total' => 0,
                'payments_total' => 0,
                'refunded_total' => 0,
                'net_total' => 0,
                'orders_count' => 0,
                'lines_count' => 0,
            ],
        ];
    }
}
