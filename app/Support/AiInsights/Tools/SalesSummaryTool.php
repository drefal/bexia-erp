<?php

namespace App\Support\AiInsights\Tools;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesSummaryTool
{
    public const VALID_SALES_ORDER_STATUSES = [
        'confirmed',
        'partially_delivered',
        'delivered',
        'invoiced',
        'partially_invoiced',
        'closed',
    ];

    public const VALID_POS_ORDER_STATUSES = [
        'paid',
        'returned',
    ];

    public function run(array $allowedCompanyIds, Carbon $from, Carbon $to): array
    {
        $allowedCompanyIds = collect($allowedCompanyIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($allowedCompanyIds === []) {
            return [
                'ok' => false,
                'message' => 'No hay empresas permitidas para consultar.',
                'allowed_company_ids' => [],
            ];
        }

        $companyLabels = $this->companyLabels($allowedCompanyIds);

        $salesOrders = $this->salesOrdersSummary($allowedCompanyIds, $from, $to);
        $posOrders = $this->posOrdersSummary($allowedCompanyIds, $from, $to);
        $posRefunds = $this->posRefundsSummary($allowedCompanyIds, $from, $to);

        $companyIds = collect($allowedCompanyIds)
            ->merge(array_keys($salesOrders['by_company']))
            ->merge(array_keys($posOrders['by_company']))
            ->merge(array_keys($posRefunds['by_company']))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();

        $byCompany = [];

        foreach ($companyIds as $companyId) {
            $sales = $salesOrders['by_company'][$companyId] ?? ['documents' => 0, 'total' => 0.0];
            $pos = $posOrders['by_company'][$companyId] ?? ['documents' => 0, 'total' => 0.0];
            $refunds = $posRefunds['by_company'][$companyId] ?? ['documents' => 0, 'total' => 0.0];

            $posNet = round((float) $pos['total'] - (float) $refunds['total'], 4);
            $totalNet = round((float) $sales['total'] + $posNet, 4);

            $byCompany[] = [
                'company_id' => $companyId,
                'company_name' => $companyLabels[$companyId] ?? ('Empresa #' . $companyId),
                'sales_orders_documents' => (int) $sales['documents'],
                'sales_orders_total' => round((float) $sales['total'], 4),
                'pos_orders_documents' => (int) $pos['documents'],
                'pos_orders_gross_total' => round((float) $pos['total'], 4),
                'pos_refunds_documents' => (int) $refunds['documents'],
                'pos_refunds_total' => round((float) $refunds['total'], 4),
                'pos_net_total' => $posNet,
                'total_net' => $totalNet,
            ];
        }

        $totalSalesOrders = round((float) collect($byCompany)->sum('sales_orders_total'), 4);
        $totalPosGross = round((float) collect($byCompany)->sum('pos_orders_gross_total'), 4);
        $totalPosRefunds = round((float) collect($byCompany)->sum('pos_refunds_total'), 4);
        $totalPosNet = round($totalPosGross - $totalPosRefunds, 4);
        $totalNet = round($totalSalesOrders + $totalPosNet, 4);

        return [
            'ok' => true,
            'tool' => 'sales_summary',
            'currency' => 'MXN',
            'range' => [
                'from' => $from->copy()->toDateTimeString(),
                'to' => $to->copy()->toDateTimeString(),
            ],
            'allowed_company_ids' => $allowedCompanyIds,
            'summary' => [
                'sales_orders_documents' => (int) collect($byCompany)->sum('sales_orders_documents'),
                'sales_orders_total' => $totalSalesOrders,
                'pos_orders_documents' => (int) collect($byCompany)->sum('pos_orders_documents'),
                'pos_orders_gross_total' => $totalPosGross,
                'pos_refunds_documents' => (int) collect($byCompany)->sum('pos_refunds_documents'),
                'pos_refunds_total' => $totalPosRefunds,
                'pos_net_total' => $totalPosNet,
                'total_net' => $totalNet,
            ],
            'by_company' => $byCompany,
            'notes' => [
                'Se suman ventas de sales_orders con estados operativos válidos.',
                'Se suman tickets PDV cobrados de pos_orders con paid_at.',
                'Se restan devoluciones PDV hechas en el rango consultado.',
                'No se usan invoices para evitar duplicar ventas facturadas.',
            ],
        ];
    }

    private function salesOrdersSummary(array $companyIds, Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable('sales_orders')) {
            return ['by_company' => []];
        }

        $rows = DB::table('sales_orders')
            ->selectRaw('company_id, COUNT(*) as documents, COALESCE(SUM(total_with_tax), 0) as total')
            ->whereIn('company_id', $companyIds)
            ->whereBetween('order_date', [$from, $to])
            ->whereIn('status', self::VALID_SALES_ORDER_STATUSES)
            ->groupBy('company_id')
            ->get();

        return [
            'by_company' => $rows
                ->mapWithKeys(fn ($row) => [
                    (int) $row->company_id => [
                        'documents' => (int) $row->documents,
                        'total' => (float) $row->total,
                    ],
                ])
                ->all(),
        ];
    }

    private function posOrdersSummary(array $companyIds, Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable('pos_orders')) {
            return ['by_company' => []];
        }

        $rows = DB::table('pos_orders')
            ->selectRaw('company_id, COUNT(*) as documents, COALESCE(SUM(total), 0) as total')
            ->whereIn('company_id', $companyIds)
            ->whereBetween('paid_at', [$from, $to])
            ->whereNotNull('paid_at')
            ->whereIn('status', self::VALID_POS_ORDER_STATUSES)
            ->groupBy('company_id')
            ->get();

        return [
            'by_company' => $rows
                ->mapWithKeys(fn ($row) => [
                    (int) $row->company_id => [
                        'documents' => (int) $row->documents,
                        'total' => (float) $row->total,
                    ],
                ])
                ->all(),
        ];
    }

    private function posRefundsSummary(array $companyIds, Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable('pos_order_refunds')) {
            return ['by_company' => []];
        }

        $rows = DB::table('pos_order_refunds')
            ->selectRaw('company_id, COUNT(*) as documents, COALESCE(SUM(total), 0) as total')
            ->whereIn('company_id', $companyIds)
            ->whereBetween('refunded_at', [$from, $to])
            ->where('status', 'done')
            ->groupBy('company_id')
            ->get();

        return [
            'by_company' => $rows
                ->mapWithKeys(fn ($row) => [
                    (int) $row->company_id => [
                        'documents' => (int) $row->documents,
                        'total' => (float) $row->total,
                    ],
                ])
                ->all(),
        ];
    }

    private function companyLabels(array $companyIds): array
    {
        if (! Schema::hasTable('companies')) {
            return [];
        }

        return DB::table('companies')
            ->whereIn('id', $companyIds)
            ->orderBy('id')
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($row) => [
                (int) $row->id => (string) ($row->name ?: ('Empresa #' . $row->id)),
            ])
            ->all();
    }
}
