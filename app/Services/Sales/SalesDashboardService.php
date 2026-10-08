<?php

namespace App\Services\Sales;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesDashboardService
{
    public function build(array $filters): array
    {
        $companyId = (int) ($filters['company_id'] ?? 0);
        $tenantCompanyId = (int) ($filters['tenant_company_id'] ?? 0);

        if ($tenantCompanyId > 0) {
            $tenantGroupId = DB::table('companies')
                ->where('id', $tenantCompanyId)
                ->value('company_group_id');

            $allowedCompanyIds = DB::table('companies')
                ->where('active', true)
                ->when(
                    $tenantGroupId,
                    fn ($query) => $query->where(
                        'company_group_id',
                        $tenantGroupId
                    ),
                    fn ($query) => $query->where('id', $tenantCompanyId)
                )
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (! in_array($companyId, $allowedCompanyIds, true)) {
                $companyId = $tenantCompanyId;
            }
        }

        $branchId = filled($filters['branch_id'] ?? null)
            ? (int) $filters['branch_id']
            : null;

        $channel = $filters['channel'] ?? 'all';
        $granularity = $filters['granularity'] ?? 'day';
        $comparison = $filters['comparison'] ?? 'previous_period';

        [$from, $until] = $this->normalizeRange(
            $filters['from'] ?? null,
            $filters['until'] ?? null
        );

        [$compareFrom, $compareUntil] = $this->comparisonRange(
            $from,
            $until,
            $comparison
        );

        $current = $this->metrics(
            $companyId,
            $branchId,
            $channel,
            $from,
            $until
        );

        $previous = $comparison === 'none'
            ? $this->emptyMetrics()
            : $this->metrics(
                $companyId,
                $branchId,
                $channel,
                $compareFrom,
                $compareUntil
            );

        $events = $this->salesEvents(
            $companyId,
            $branchId,
            $channel,
            $from,
            $until
        );

        $topProducts = $this->topProducts(
            $companyId,
            $branchId,
            $channel,
            $from,
            $until,
            'sales'
        );

        $topProductsByQuantity = $this->topProducts(
            $companyId,
            $branchId,
            $channel,
            $from,
            $until,
            'quantity'
        );

        $branches = $this->branchRanking(
            $companyId,
            $channel,
            $from,
            $until
        );

        $paymentMethods = $this->paymentMethods(
            $companyId,
            $branchId,
            $channel,
            $from,
            $until
        );

        $mainSeries = $this->mainSeries(
            $events,
            $from,
            $until,
            $granularity
        );

        $hourly = $this->hourlySeries($events);
        $weekday = $this->weekdaySeries($events);
        $heatmap = $this->heatmap($events);

        $historicalCostIncomplete =
            ($current['historical_sales'] ?? 0) > 0;

        $previousHistoricalCostIncomplete =
            ($previous['historical_sales'] ?? 0) > 0;

        $kpis = [
            $this->kpi(
                'Ventas netas',
                '$ ' . number_format($current['net_sales'], 2),
                $current['net_sales'],
                $previous['net_sales'],
                'green',
                'heroicon-o-banknotes',
                '%'
            ),
            $this->kpi(
                'Tickets / ventas',
                number_format($current['documents']),
                $current['documents'],
                $previous['documents'],
                'blue',
                'heroicon-o-receipt-percent',
                '%'
            ),
            $this->kpi(
                'Ticket promedio',
                '$ ' . number_format($current['average_ticket'], 2),
                $current['average_ticket'],
                $previous['average_ticket'],
                'violet',
                'heroicon-o-calculator',
                '%'
            ),
            $this->kpi(
                'Unidades vendidas',
                number_format($current['units'], 2),
                $current['units'],
                $previous['units'],
                'orange',
                'heroicon-o-cube',
                '%'
            ),
            $this->kpi(
                'Utilidad bruta',
                $historicalCostIncomplete
                    ? 'N/D'
                    : '$ ' . number_format($current['gross_profit'], 2),
                $historicalCostIncomplete
                    ? 0
                    : $current['gross_profit'],
                $previousHistoricalCostIncomplete
                    ? 0
                    : $previous['gross_profit'],
                'cyan',
                'heroicon-o-arrow-trending-up',
                '%',
                ! $historicalCostIncomplete,
                $historicalCostIncomplete
                    ? 'Costo histórico incompleto'
                    : null
            ),
            $this->kpi(
                'Margen bruto',
                $historicalCostIncomplete
                    ? 'N/D'
                    : number_format($current['margin_percent'], 1) . '%',
                $historicalCostIncomplete
                    ? 0
                    : $current['margin_percent'],
                $previousHistoricalCostIncomplete
                    ? 0
                    : $previous['margin_percent'],
                'amber',
                'heroicon-o-chart-pie',
                ' pts',
                ! $historicalCostIncomplete,
                $historicalCostIncomplete
                    ? 'Costo histórico incompleto'
                    : null
            ),
        ];

        return [
            'kpis' => $kpis,
            'main_series' => $mainSeries,
            'hourly_sales' => $hourly,
            'weekday_sales' => $weekday,
            'payment_methods' => $paymentMethods,
            'top_products' => $topProducts,
            'top_products_by_quantity' => $topProductsByQuantity,
            'branches' => $branches,
            'heatmap' => $heatmap,
            'insights' => $this->insights(
                $current,
                $hourly,
                $weekday,
                $topProducts,
                $channel
            ),
            'meta' => [
                'real_data' => true,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'channel' => $channel,
                'from' => $from->toDateString(),
                'until' => $until->toDateString(),
            ],
        ];
    }

    public function companyOptions(): array
    {
        return DB::table('companies')
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    public function branchOptions(?int $companyId): array
    {
        if (! $companyId) {
            return [];
        }

        return DB::table('branches')
            ->where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    protected function normalizeRange(?string $from, ?string $until): array
    {
        $start = $from
            ? Carbon::parse($from)->startOfDay()
            : now()->startOfMonth();

        $end = $until
            ? Carbon::parse($until)->endOfDay()
            : now()->endOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [
                $end->copy()->startOfDay(),
                $start->copy()->endOfDay(),
            ];
        }

        return [$start, $end];
    }

    protected function comparisonRange(
        Carbon $from,
        Carbon $until,
        string $comparison
    ): array {
        if ($comparison === 'previous_year') {
            return [
                $from->copy()->subYear(),
                $until->copy()->subYear(),
            ];
        }

        $days = $from->copy()->startOfDay()
            ->diffInDays($until->copy()->startOfDay()) + 1;

        return [
            $from->copy()->subDays($days),
            $until->copy()->subDays($days),
        ];
    }

    protected function emptyMetrics(): array
    {
        return [
            'net_sales' => 0.0,
            'ticket_sales' => 0.0,
            'historical_sales' => 0.0,
            'documents' => 0,
            'average_ticket' => 0.0,
            'units' => 0.0,
            'net_without_tax' => 0.0,
            'cost' => 0.0,
            'gross_profit' => 0.0,
            'margin_percent' => 0.0,
            'pos_sales' => 0.0,
            'sales_module_sales' => 0.0,
            'refunds' => 0.0,
        ];
    }

    protected function metrics(
        int $companyId,
        ?int $branchId,
        string $channel,
        Carbon $from,
        Carbon $until
    ): array {
        $m = $this->emptyMetrics();

        if (! $companyId) {
            return $m;
        }

        if (in_array($channel, ['all', 'pos'], true)) {
            $pos = $this->posMetrics(
                $companyId,
                $branchId,
                $from,
                $until
            );

            foreach ([
                'net_sales',
                'ticket_sales',
                'historical_sales',
                'documents',
                'units',
                'net_without_tax',
                'cost',
                'refunds',
            ] as $key) {
                $m[$key] += $pos[$key];
            }

            $m['pos_sales'] += $pos['net_sales'];
        }

        if (in_array($channel, ['all', 'sales'], true)) {
            $sales = $this->salesModuleMetrics(
                $companyId,
                $branchId,
                $from,
                $until
            );

            foreach ([
                'net_sales',
                'ticket_sales',
                'historical_sales',
                'documents',
                'units',
                'net_without_tax',
                'cost',
                'refunds',
            ] as $key) {
                $m[$key] += $sales[$key];
            }

            $m['sales_module_sales'] += $sales['net_sales'];
        }

        $m['average_ticket'] = $m['documents'] > 0
            ? $m['ticket_sales'] / $m['documents']
            : 0.0;

        $m['gross_profit'] = $m['net_without_tax'] - $m['cost'];

        $m['margin_percent'] = $m['net_without_tax'] > 0
            ? ($m['gross_profit'] / $m['net_without_tax']) * 100
            : 0.0;

        return $m;
    }

    protected function validPosOrders(
        int $companyId,
        ?int $branchId,
        Carbon $from,
        Carbon $until
    ) {
        $q = DB::table('pos_orders as po')
            ->leftJoin('pos_points as pp', 'pp.id', '=', 'po.pos_point_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'pp.warehouse_id')
            ->where('po.company_id', $companyId)
            ->whereNull('po.cancelled_at')
            ->whereNotNull('po.paid_at')
            ->whereIn('po.status', ['paid', 'returned'])
            ->whereBetween('po.paid_at', [$from, $until]);

        if ($branchId) {
            $q->where('w.branch_id', $branchId);
        }

        return $q;
    }

    protected function validSalesOrders(
        int $companyId,
        ?int $branchId,
        Carbon $from,
        Carbon $until
    ) {
        $q = DB::table('sales_orders as so')
            ->leftJoin('warehouses as w', 'w.id', '=', 'so.warehouse_id')
            ->where('so.company_id', $companyId)
            ->whereNotIn('so.status', [
                'draft',
                'cancelled',
                'canceled',
                'cancelled_test',
                'rejected',
            ])
            ->where(function ($query) {
                $query
                    ->whereNotNull('so.confirmed_at')
                    ->orWhereIn('so.status', [
                        'confirmed',
                        'delivered',
                        'completed',
                        'done',
                    ]);
            })
            ->whereBetween(
                DB::raw('COALESCE(so.order_date, so.confirmed_at, so.created_at)'),
                [$from, $until]
            )
            ->where(function ($query) {
                $query->whereNull('so.quote_pos_order_id')
                    ->orWhereNotExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('pos_orders as linked_po')
                            ->whereColumn(
                                'linked_po.id',
                                'so.quote_pos_order_id'
                            )
                            ->whereNull('linked_po.cancelled_at')
                            ->whereNotNull('linked_po.paid_at')
                            ->whereIn('linked_po.status', ['paid', 'returned']);
                    });
            });

        if ($branchId) {
            $q->where('w.branch_id', $branchId);
        }

        return $q;
    }

    protected function posMetrics(
        int $companyId,
        ?int $branchId,
        Carbon $from,
        Carbon $until
    ): array {
        $orders = $this->validPosOrders(
            $companyId,
            $branchId,
            $from,
            $until
        );

        $grossSales = (float) (clone $orders)->sum('po.total');

        /*
         * V1.3:
         * El subtotal fiscal/comercial debe salir del encabezado.
         *
         * Las lineas pueden conservar sus importes previos a un descuento
         * global. El encabezado ya contiene descuentos y ajustes reales.
         */
        $grossSubtotal = (float) (clone $orders)->sum('po.subtotal');

        /*
         * Los históricos importados como resumen diario sí cuentan
         * para Ventas netas, pero NO son tickets individuales.
         *
         * Para Ticket promedio utilizamos exclusivamente el importe
         * correspondiente a documentos reales.
         */
        $realOrders = (clone $orders)
            ->where(function ($q) {
                $q->whereNull('po.source_type')
                    ->orWhere('po.source_type', '!=', 'historical_daily_summary');
            });

        $documents = (int) (clone $realOrders)->count('po.id');

        $realGrossSales = (float) (clone $realOrders)->sum('po.total');

        $historicalSales = max(
            0.0,
            $grossSales - $realGrossSales
        );

        $orderIds = (clone $orders)->pluck('po.id');

        $lineData = [
            'units' => 0.0,
            'subtotal' => 0.0,
            'cost' => 0.0,
        ];

        if ($orderIds->isNotEmpty()) {
            /*
             * COSTO POS V1.3
             *
             * Prioridad:
             *
             * 1. stock_movement_lines:
             *    costo guardado exactamente al momento de la salida PDV.
             *
             * 2. accounting_inventory_valuation_layers:
             *    capa historica contable si existe.
             *
             * 3. costo promedio actual del producto.
             *
             * 4. ultimo costo de compra.
             *
             * 5. costo estandar.
             *
             * Para servicios el costo directo de inventario es cero.
             */
            $stockCost = DB::table('stock_movement_lines')
                ->selectRaw('source_line_id, SUM(total_cost) AS total_cost')
                ->whereNotNull('source_line_id')
                ->where(function ($query) {
                    $query
                        ->where('source_line_type', 'pos_order_line')
                        ->orWhere('source_type', 'pos_order');
                })
                ->groupBy('source_line_id');

            $valuation = DB::table('accounting_inventory_valuation_layers')
                ->selectRaw('source_line_id, SUM(total_cost) AS total_cost')
                ->where('company_id', $companyId)
                ->where('source_type', 'pos_order_lines')
                ->where('direction', 'out')
                ->groupBy('source_line_id');

            $row = DB::table('pos_order_lines as pol')
                ->leftJoin('products as p', 'p.id', '=', 'pol.product_id')
                ->leftJoinSub($stockCost, 'sc', function ($join) {
                    $join->on('sc.source_line_id', '=', 'pol.id');
                })
                ->leftJoinSub($valuation, 'vl', function ($join) {
                    $join->on('vl.source_line_id', '=', 'pol.id');
                })
                ->whereIn('pol.pos_order_id', $orderIds)
                ->selectRaw("
                    COALESCE(SUM(pol.quantity),0) AS units,
                    COALESCE(SUM(pol.subtotal),0) AS subtotal,

                    COALESCE(SUM(
                        CASE
                            WHEN LOWER(COALESCE(p.product_type, ''))
                                IN ('service', 'servicio')
                            THEN 0

                            ELSE COALESCE(
                                sc.total_cost,
                                vl.total_cost,

                                CASE
                                    WHEN COALESCE(
                                        p.average_cost_without_tax,
                                        0
                                    ) > 0
                                    THEN p.average_cost_without_tax
                                        * pol.quantity
                                END,

                                CASE
                                    WHEN COALESCE(
                                        p.last_purchase_cost,
                                        0
                                    ) > 0
                                    THEN p.last_purchase_cost
                                        * pol.quantity
                                END,

                                CASE
                                    WHEN COALESCE(
                                        p.standard_cost,
                                        0
                                    ) > 0
                                    THEN p.standard_cost
                                        * pol.quantity
                                END,

                                0
                            )
                        END
                    ),0) AS cost
                ")
                ->first();

            $lineData = [
                'units' => (float) ($row->units ?? 0),
                'subtotal' => (float) ($row->subtotal ?? 0),
                'cost' => (float) ($row->cost ?? 0),
            ];
        }

        $refunds = DB::table('pos_order_refunds as pr')
            ->leftJoin('pos_points as pp', 'pp.id', '=', 'pr.pos_point_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'pp.warehouse_id')
            ->where('pr.company_id', $companyId)
            ->where('pr.status', 'done')
            ->whereBetween('pr.refunded_at', [$from, $until]);

        if ($branchId) {
            $refunds->where('w.branch_id', $branchId);
        }

        $refundTotal = (float) (clone $refunds)->sum('pr.total');
        $refundIds = (clone $refunds)->pluck('pr.id');

        $refundUnits = 0.0;
        $refundSubtotal = 0.0;
        $refundCost = 0.0;

        if ($refundIds->isNotEmpty()) {
            $refundValuation = DB::table('accounting_inventory_valuation_layers')
                ->selectRaw('source_line_id, SUM(total_cost) AS total_cost')
                ->where('company_id', $companyId)
                ->where('source_type', 'pos_order_refund_lines')
                ->where('direction', 'in')
                ->groupBy('source_line_id');

            $row = DB::table('pos_order_refund_lines as prl')
                ->leftJoin('products as p', 'p.id', '=', 'prl.product_id')
                ->leftJoinSub($refundValuation, 'rvl', function ($join) {
                    $join->on('rvl.source_line_id', '=', 'prl.id');
                })
                ->whereIn('prl.pos_order_refund_id', $refundIds)
                ->selectRaw("
                    COALESCE(SUM(prl.quantity),0) AS units,
                    COALESCE(SUM(prl.subtotal),0) AS subtotal,
                    COALESCE(SUM(
                        COALESCE(
                            rvl.total_cost,
                            COALESCE(
                                p.average_cost_without_tax,
                                p.standard_cost,
                                p.last_purchase_cost,
                                0
                            ) * prl.quantity
                        )
                    ),0) AS cost
                ")
                ->first();

            $refundUnits = (float) ($row->units ?? 0);
            $refundSubtotal = (float) ($row->subtotal ?? 0);
            $refundCost = (float) ($row->cost ?? 0);
        }

        return [
            'net_sales' => $grossSales - $refundTotal,
            'ticket_sales' => max(
                0.0,
                $realGrossSales - $refundTotal
            ),
            'historical_sales' => $historicalSales,
            'documents' => $documents,
            'units' => $lineData['units'] - $refundUnits,

            /*
             * Usar subtotal del encabezado para respetar descuentos
             * globales y otros ajustes aplicados al ticket.
             */
            'net_without_tax' => $grossSubtotal - $refundSubtotal,

            'cost' => max(0, $lineData['cost'] - $refundCost),
            'refunds' => $refundTotal,
        ];
    }

    protected function salesModuleMetrics(
        int $companyId,
        ?int $branchId,
        Carbon $from,
        Carbon $until
    ): array {
        $orders = $this->validSalesOrders(
            $companyId,
            $branchId,
            $from,
            $until
        );

        $gross = (float) (clone $orders)->sum('so.total_with_tax');
        $documents = (int) (clone $orders)->count('so.id');
        $orderIds = (clone $orders)->pluck('so.id');

        $units = 0.0;
        $subtotal = 0.0;
        $cost = 0.0;

        if ($orderIds->isNotEmpty()) {
            $row = DB::table('sales_order_lines')
                ->whereIn('sales_order_id', $orderIds)
                ->where('company_id', $companyId)
                ->selectRaw("
                    COALESCE(SUM(quantity),0) AS units,
                    COALESCE(SUM(line_total_without_tax),0) AS subtotal,
                    COALESCE(SUM(
                        COALESCE(estimated_unit_cost_without_tax,0)
                        * quantity
                    ),0) AS cost
                ")
                ->first();

            $units = (float) ($row->units ?? 0);
            $subtotal = (float) ($row->subtotal ?? 0);
            $cost = (float) ($row->cost ?? 0);
        }

        $returns = DB::table('sale_returns as sr')
            ->leftJoin('warehouses as w', 'w.id', '=', 'sr.warehouse_id')
            ->where('sr.company_id', $companyId)
            ->whereIn('sr.status', ['done', 'completed', 'returned'])
            ->whereBetween('sr.returned_at', [$from, $until]);

        if ($branchId) {
            $returns->where('w.branch_id', $branchId);
        }

        $returnIds = (clone $returns)->pluck('sr.id');

        $returnTotal = 0.0;
        $returnUnits = 0.0;
        $returnSubtotal = 0.0;
        $returnCost = 0.0;

        if ($returnIds->isNotEmpty()) {
            $row = DB::table('sale_return_lines as srl')
                ->leftJoin(
                    'sales_order_lines as sol',
                    'sol.id',
                    '=',
                    'srl.sales_order_line_id'
                )
                ->whereIn('srl.sale_return_id', $returnIds)
                ->selectRaw("
                    COALESCE(SUM(srl.quantity),0) AS units,
                    COALESCE(SUM(
                        srl.quantity * COALESCE(sol.unit_price_with_tax,0)
                    ),0) AS total_with_tax,
                    COALESCE(SUM(
                        srl.quantity * COALESCE(sol.unit_price_without_tax,0)
                    ),0) AS subtotal,
                    COALESCE(SUM(
                        COALESCE(
                            srl.total_cost,
                            srl.quantity * COALESCE(srl.unit_cost,0)
                        )
                    ),0) AS cost
                ")
                ->first();

            $returnUnits = (float) ($row->units ?? 0);
            $returnTotal = (float) ($row->total_with_tax ?? 0);
            $returnSubtotal = (float) ($row->subtotal ?? 0);
            $returnCost = (float) ($row->cost ?? 0);
        }

        return [
            'net_sales' => $gross - $returnTotal,
            'ticket_sales' => $gross - $returnTotal,
            'historical_sales' => 0.0,
            'documents' => $documents,
            'units' => $units - $returnUnits,
            'net_without_tax' => $subtotal - $returnSubtotal,
            'cost' => max(0, $cost - $returnCost),
            'refunds' => $returnTotal,
        ];
    }

    protected function salesEvents(
        int $companyId,
        ?int $branchId,
        string $channel,
        Carbon $from,
        Carbon $until
    ): Collection {
        $events = collect();

        if (in_array($channel, ['all', 'pos'], true)) {
            $orders = $this->validPosOrders(
                $companyId,
                $branchId,
                $from,
                $until
            )
                ->select([
                    'po.paid_at as event_at',
                    'po.total as amount',
                ])
                ->get();

            foreach ($orders as $row) {
                if ($row->event_at) {
                    $events->push([
                        'at' => Carbon::parse($row->event_at),
                        'amount' => (float) $row->amount,
                    ]);
                }
            }

            $refunds = DB::table('pos_order_refunds as pr')
                ->leftJoin('pos_points as pp', 'pp.id', '=', 'pr.pos_point_id')
                ->leftJoin('warehouses as w', 'w.id', '=', 'pp.warehouse_id')
                ->where('pr.company_id', $companyId)
                ->where('pr.status', 'done')
                ->whereBetween('pr.refunded_at', [$from, $until]);

            if ($branchId) {
                $refunds->where('w.branch_id', $branchId);
            }

            foreach ($refunds->get([
                'pr.refunded_at as event_at',
                'pr.total',
            ]) as $row) {
                if ($row->event_at) {
                    $events->push([
                        'at' => Carbon::parse($row->event_at),
                        'amount' => -1 * (float) $row->total,
                    ]);
                }
            }
        }

        if (in_array($channel, ['all', 'sales'], true)) {
            $orders = $this->validSalesOrders(
                $companyId,
                $branchId,
                $from,
                $until
            )
                ->selectRaw("
                    COALESCE(so.order_date, so.confirmed_at, so.created_at)
                        AS event_at,
                    so.total_with_tax AS amount
                ")
                ->get();

            foreach ($orders as $row) {
                if ($row->event_at) {
                    $events->push([
                        'at' => Carbon::parse($row->event_at),
                        'amount' => (float) $row->amount,
                    ]);
                }
            }
        }

        return $events;
    }

    protected function mainSeries(
        Collection $events,
        Carbon $from,
        Carbon $until,
        string $granularity
    ): array {
        $groups = [];

        foreach ($events as $event) {
            $at = $event['at'];

            $key = match ($granularity) {
                'hour' => $at->format('Y-m-d H:00'),
                'week' => $at->copy()->startOfWeek()->format('Y-m-d'),
                'month' => $at->format('Y-m'),
                default => $at->format('Y-m-d'),
            };

            $groups[$key] = ($groups[$key] ?? 0) + $event['amount'];
        }

        ksort($groups);

        if ($granularity === 'day') {
            foreach (
                CarbonPeriod::create(
                    $from->copy()->startOfDay(),
                    $until->copy()->startOfDay()
                ) as $day
            ) {
                $key = $day->format('Y-m-d');
                $groups[$key] ??= 0;
            }

            ksort($groups);
        }

        return collect($groups)
            ->map(fn ($value, $label) => [
                'label' => $this->shortLabel($label, $granularity),
                'value' => round((float) $value, 2),
            ])
            ->values()
            ->all();
    }

    protected function shortLabel(string $key, string $granularity): string
    {
        return match ($granularity) {
            'hour' => Carbon::parse($key)->format('d/m H:i'),
            'week' => 'Sem ' . Carbon::parse($key)->format('d/m'),
            'month' => Carbon::createFromFormat('Y-m', $key)->translatedFormat('M y'),
            default => Carbon::parse($key)->format('d/m'),
        };
    }

    protected function hourlySeries(Collection $events): array
    {
        $hours = [];

        foreach (range(0, 23) as $hour) {
            $hours[$hour] = 0.0;
        }

        foreach ($events as $event) {
            $hour = (int) $event['at']->format('G');
            $hours[$hour] += $event['amount'];
        }

        return collect($hours)
            ->map(fn ($value, $hour) => [
                'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT),
                'value' => round(max(0, $value), 2),
            ])
            ->values()
            ->all();
    }

    protected function weekdaySeries(Collection $events): array
    {
        $labels = [
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mié',
            4 => 'Jue',
            5 => 'Vie',
            6 => 'Sáb',
            7 => 'Dom',
        ];

        $values = array_fill(1, 7, 0.0);

        foreach ($events as $event) {
            $values[$event['at']->isoWeekday()] += $event['amount'];
        }

        $out = [];

        foreach ($labels as $day => $label) {
            $out[] = [
                'label' => $label,
                'value' => round(max(0, $values[$day]), 2),
            ];
        }

        return $out;
    }

    protected function heatmap(Collection $events): array
    {
        $hours = ['08', '10', '12', '14', '16', '18', '20', '22'];

        $days = [
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mié',
            4 => 'Jue',
            5 => 'Vie',
            6 => 'Sáb',
            7 => 'Dom',
        ];

        $matrix = [];

        foreach ($days as $day => $label) {
            $matrix[$day] = array_fill_keys($hours, 0.0);
        }

        foreach ($events as $event) {
            $hour = (int) $event['at']->format('G');

            $bucket = collect($hours)
                ->sortBy(fn ($h) => abs(((int) $h) - $hour))
                ->first();

            if ($bucket !== null) {
                $matrix[$event['at']->isoWeekday()][$bucket]
                    += max(0, $event['amount']);
            }
        }

        $max = 0.0;

        foreach ($matrix as $row) {
            foreach ($row as $value) {
                $max = max($max, $value);
            }
        }

        $rows = [];

        foreach ($days as $day => $label) {
            $rows[] = [
                'day' => $label,
                'values' => collect($hours)
                    ->map(function ($hour) use ($matrix, $day, $max) {
                        if ($max <= 0) {
                            return 0;
                        }

                        return (int) round(
                            ($matrix[$day][$hour] / $max) * 9
                        );
                    })
                    ->all(),
            ];
        }

        return [
            'hours' => $hours,
            'rows' => $rows,
        ];
    }

    protected function paymentMethods(
        int $companyId,
        ?int $branchId,
        string $channel,
        Carbon $from,
        Carbon $until
    ): array {
        $rows = collect();

        if (in_array($channel, ['all', 'pos'], true)) {
            $q = DB::table('pos_order_payments as pop')
                ->join('pos_orders as po', 'po.id', '=', 'pop.pos_order_id')
                ->leftJoin('payment_forms as pf', 'pf.id', '=', 'pop.payment_form_id')
                ->leftJoin('pos_points as pp', 'pp.id', '=', 'po.pos_point_id')
                ->leftJoin('warehouses as w', 'w.id', '=', 'pp.warehouse_id')
                ->where('po.company_id', $companyId)
                ->whereNull('po.cancelled_at')
                ->whereNotNull('po.paid_at')
                ->whereIn('po.status', ['paid', 'returned'])
                ->where('pop.status', 'paid')
                ->whereBetween('po.paid_at', [$from, $until]);

            if ($branchId) {
                $q->where('w.branch_id', $branchId);
            }

            $rows = $q
                ->selectRaw("
                    COALESCE(pf.code, '') AS code,
                    COALESCE(pop.payment_label, pf.name, 'Sin especificar') AS label,
                    SUM(pop.amount) AS amount
                ")
                ->groupBy('pf.code', 'pop.payment_label', 'pf.name')
                ->get();
        }

        $buckets = [];

        foreach ($rows as $row) {
            $label = $this->paymentBucket(
                (string) $row->code,
                (string) $row->label
            );

            $buckets[$label] = ($buckets[$label] ?? 0)
                + (float) $row->amount;
        }

        if (in_array($channel, ['all', 'sales'], true)) {
            $sales = $this->validSalesOrders(
                $companyId,
                $branchId,
                $from,
                $until
            );

            $unpaid = (float) (clone $sales)
                ->where(function ($q) {
                    $q->whereNull('so.payment_status')
                        ->orWhere('so.payment_status', '!=', 'paid');
                })
                ->sum('so.total_with_tax');

            if ($unpaid > 0) {
                $buckets['Venta sin cobro registrado']
                    = ($buckets['Venta sin cobro registrado'] ?? 0)
                    + $unpaid;
            }
        }

        $total = array_sum($buckets);

        $colors = [
            'Efectivo' => '#22c55e',
            'Tarjeta de débito' => '#3b82f6',
            'Tarjeta de crédito' => '#8b5cf6',
            'Tarjeta sin especificar' => '#f59e0b',
            'Transferencia' => '#06b6d4',
            'Venta sin cobro registrado' => '#94a3b8',
            'Otros' => '#64748b',
        ];

        arsort($buckets);

        return collect($buckets)
            ->map(function ($amount, $label) use ($total, $colors) {
                return [
                    'label' => $label,
                    'value' => round($amount, 2),
                    'percentage' => $total > 0
                        ? round(($amount / $total) * 100, 2)
                        : 0,
                    'css' => $colors[$label] ?? '#64748b',
                ];
            })
            ->values()
            ->all();
    }

    protected function paymentBucket(string $code, string $label): string
    {
        $normalized = mb_strtolower($label);

        if ($code === '01' || str_contains($normalized, 'efectivo')) {
            return 'Efectivo';
        }

        if ($code === '28' || str_contains($normalized, 'débito') || str_contains($normalized, 'debito')) {
            return 'Tarjeta de débito';
        }

        if ($code === '04' || str_contains($normalized, 'crédito') || str_contains($normalized, 'credito')) {
            return 'Tarjeta de crédito';
        }

        if ($code === '03' || str_contains($normalized, 'transfer')) {
            return 'Transferencia';
        }

        if (str_contains($normalized, 'clip') || str_contains($normalized, 'tarjeta')) {
            return 'Tarjeta sin especificar';
        }

        return 'Otros';
    }

    protected function topProducts(
        int $companyId,
        ?int $branchId,
        string $channel,
        Carbon $from,
        Carbon $until,
        string $sortBy = 'sales'
    ): array {
        $totals = [];

        if (in_array($channel, ['all', 'pos'], true)) {
            $orders = $this->validPosOrders(
                $companyId,
                $branchId,
                $from,
                $until
            )->pluck('po.id');

            if ($orders->isNotEmpty()) {
                $rows = DB::table('pos_order_lines')
                    ->whereIn('pos_order_id', $orders)
                    ->selectRaw("
                        COALESCE(product_id,0) AS product_id,
                        COALESCE(product_variant_id,0) AS variant_id,
                        MAX(product_name) AS name,
                        MAX(product_reference) AS reference,
                        SUM(total) AS sales,
                        SUM(quantity) AS quantity
                    ")
                    ->groupBy('product_id', 'product_variant_id')
                    ->get();

                foreach ($rows as $row) {
                    $key = $row->product_id . ':' . $row->variant_id;

                    $totals[$key] ??= [
                        'name' => $row->name ?: 'Producto',
                        'reference' => $row->reference ?: '',
                        'sales' => 0.0,
                        'quantity' => 0.0,
                    ];

                    $totals[$key]['sales'] += (float) $row->sales;
                    $totals[$key]['quantity'] += (float) $row->quantity;
                }
            }

            $refunds = DB::table('pos_order_refunds as pr')
                ->leftJoin('pos_points as pp', 'pp.id', '=', 'pr.pos_point_id')
                ->leftJoin('warehouses as w', 'w.id', '=', 'pp.warehouse_id')
                ->where('pr.company_id', $companyId)
                ->where('pr.status', 'done')
                ->whereBetween('pr.refunded_at', [$from, $until]);

            if ($branchId) {
                $refunds->where('w.branch_id', $branchId);
            }

            $refundIds = $refunds->pluck('pr.id');

            if ($refundIds->isNotEmpty()) {
                $rows = DB::table('pos_order_refund_lines')
                    ->whereIn('pos_order_refund_id', $refundIds)
                    ->selectRaw("
                        COALESCE(product_id,0) AS product_id,
                        COALESCE(product_variant_id,0) AS variant_id,
                        MAX(product_name) AS name,
                        MAX(product_reference) AS reference,
                        SUM(total) AS sales,
                        SUM(quantity) AS quantity
                    ")
                    ->groupBy('product_id', 'product_variant_id')
                    ->get();

                foreach ($rows as $row) {
                    $key = $row->product_id . ':' . $row->variant_id;

                    $totals[$key] ??= [
                        'name' => $row->name ?: 'Producto',
                        'reference' => $row->reference ?: '',
                        'sales' => 0.0,
                        'quantity' => 0.0,
                    ];

                    $totals[$key]['sales'] -= (float) $row->sales;
                    $totals[$key]['quantity'] -= (float) $row->quantity;
                }
            }
        }

        if (in_array($channel, ['all', 'sales'], true)) {
            $orders = $this->validSalesOrders(
                $companyId,
                $branchId,
                $from,
                $until
            )->pluck('so.id');

            if ($orders->isNotEmpty()) {
                $rows = DB::table('sales_order_lines as sol')
                    ->leftJoin('products as p', 'p.id', '=', 'sol.product_id')
                    ->whereIn('sol.sales_order_id', $orders)
                    ->where('sol.company_id', $companyId)
                    ->selectRaw("
                        COALESCE(sol.product_id,0) AS product_id,
                        COALESCE(sol.product_variant_id,0) AS variant_id,
                        MAX(sol.product_label) AS name,
                        MAX(COALESCE(p.internal_reference,p.sku,'')) AS reference,
                        SUM(sol.line_total_with_tax) AS sales,
                        SUM(sol.quantity) AS quantity
                    ")
                    ->groupBy('sol.product_id', 'sol.product_variant_id')
                    ->get();

                foreach ($rows as $row) {
                    $key = $row->product_id . ':' . $row->variant_id;

                    $totals[$key] ??= [
                        'name' => $row->name ?: 'Producto',
                        'reference' => $row->reference ?: '',
                        'sales' => 0.0,
                        'quantity' => 0.0,
                    ];

                    $totals[$key]['sales'] += (float) $row->sales;
                    $totals[$key]['quantity'] += (float) $row->quantity;
                }
            }
        }

        $rows = collect($totals)
            ->filter(
                fn ($row) =>
                    $row['sales'] > 0
                    || $row['quantity'] > 0
            );

        $rows = $sortBy === 'quantity'
            ? $rows->sortByDesc('quantity')
            : $rows->sortByDesc('sales');

        return $rows
            ->take(10)
            ->values()
            ->all();
    }

    protected function branchRanking(
        int $companyId,
        string $channel,
        Carbon $from,
        Carbon $until
    ): array {
        $branches = DB::table('branches')
            ->where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $rows = [];

        foreach ($branches as $branch) {
            $m = $this->metrics(
                $companyId,
                (int) $branch->id,
                $channel,
                $from,
                $until
            );

            $rows[] = [
                'name' => $branch->name,
                'sales' => round($m['net_sales'], 2),
                'change' => 0.0,
            ];
        }

        return collect($rows)
            ->sortByDesc('sales')
            ->values()
            ->all();
    }

    protected function insights(
        array $metrics,
        array $hourly,
        array $weekday,
        array $products,
        string $channel
    ): array {
        $bestHour = collect($hourly)->sortByDesc('value')->first();
        $bestDay = collect($weekday)->sortByDesc('value')->first();
        $top = collect($products)->first();

        $channelLabel = match ($channel) {
            'pos' => 'PDV',
            'sales' => 'Ventas',
            default => 'PDV + Ventas',
        };

        return [
            [
                'tone' => 'green',
                'icon' => 'heroicon-o-arrow-trending-up',
                'title' => 'Canal analizado',
                'body' => $channelLabel . ' · ventas netas $'
                    . number_format($metrics['net_sales'], 2) . '.',
            ],
            [
                'tone' => 'blue',
                'icon' => 'heroicon-o-clock',
                'title' => 'Hora con mayor venta',
                'body' => $bestHour
                    ? $bestHour['label'] . ':00 · $'
                        . number_format($bestHour['value'], 2)
                    : 'Sin información.',
            ],
            [
                'tone' => 'orange',
                'icon' => 'heroicon-o-calendar-days',
                'title' => 'Día más fuerte',
                'body' => $bestDay
                    ? $bestDay['label'] . ' · $'
                        . number_format($bestDay['value'], 2)
                    : 'Sin información.',
            ],
            [
                'tone' => 'violet',
                'icon' => 'heroicon-o-cube',
                'title' => 'Producto líder',
                'body' => $top
                    ? $top['name'] . ' · $'
                        . number_format($top['sales'], 2)
                    : 'Sin ventas de productos.',
            ],
        ];
    }

    protected function kpi(
        string $label,
        string $display,
        float|int $current,
        float|int $previous,
        string $tone,
        string $icon,
        string $suffix,
        bool $showChange = true,
        ?string $note = null
    ): array {
        if ((float) $previous === 0.0) {
            $change = (float) $current === 0.0 ? 0.0 : 100.0;
        } else {
            $change = (
                ((float) $current - (float) $previous)
                / abs((float) $previous)
            ) * 100;
        }

        if ($suffix === ' pts') {
            $change = (float) $current - (float) $previous;
        }

        return [
            'label' => $label,
            'display' => $display,
            'change' => round($change, 1),
            'suffix' => $suffix,
            'tone' => $tone,
            'icon' => $icon,
            'show_change' => $showChange,
            'note' => $note,
        ];
    }
}
