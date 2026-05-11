<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de venta</title>

    <style>
        @page { margin: 16mm 13mm 20mm 13mm; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0f172a;
            font-size: 10px;
            line-height: 1.35;
        }

        h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 900;
        }

        h2 {
            margin: 14px 0 6px;
            font-size: 12px;
            font-weight: 900;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }

        .header {
            display: table;
            width: 100%;
            margin-bottom: 14px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }

        .header-left,
        .header-right {
            display: table-cell;
            vertical-align: top;
        }

        .header-right {
            width: 34%;
            text-align: right;
            color: #475569;
            font-size: 9px;
        }

        .logo {
            max-width: 150px;
            max-height: 48px;
            margin-bottom: 6px;
        }

        .muted { color: #64748b; }

        .box {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 8px;
            padding: 8px 9px;
            margin: 8px 0;
        }

        .kpis {
            display: table;
            width: 100%;
            margin: 8px 0 12px;
        }

        .kpi-row { display: table-row; }

        .kpi {
            display: table-cell;
            width: 25%;
            border: 1px solid #e2e8f0;
            padding: 7px;
            vertical-align: top;
        }

        .kpi small {
            display: block;
            color: #64748b;
            font-size: 8px;
            margin-bottom: 3px;
        }

        .kpi strong {
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0 10px;
        }

        th {
            background: #f1f5f9;
            color: #334155;
            font-size: 8px;
            text-transform: uppercase;
            border: 1px solid #e2e8f0;
            padding: 4px;
            text-align: left;
        }

        td {
            border: 1px solid #e2e8f0;
            padding: 4px;
            font-size: 9px;
            vertical-align: top;
        }

        .right { text-align: right; }
        .total { font-weight: 900; color: #1d4ed8; }
        .danger { color: #b91c1c; font-weight: 900; }
        .success { color: #166534; font-weight: 900; }

        .signatures {
            display: table;
            width: 100%;
            margin-top: 34px;
        }

        .signature {
            display: table-cell;
            width: 50%;
            text-align: center;
            padding: 0 20px;
        }

        .signature-line {
            border-top: 1px solid #0f172a;
            padding-top: 5px;
            font-size: 9px;
        }
    </style>
</head>
<body>

<?php
    $summary = $summary ?? [];

    $company = $summary['company'] ?? [];
    $pos = $summary['pos'] ?? [];
    $session = $summary['session'] ?? [];
    $cashier = $summary['cashier'] ?? [];
    $totals = $summary['totals'] ?? [];

    $payments = $summary['payments_by_method'] ?? [];

    // V5.51.0S - Reporte cierre: total vendido bruto, devuelto y venta neta.
    try {
        $v5510sTotalVendidoBruto = 0.0;

        if (isset($payments)) {
            foreach ($payments as $row) {
                if (is_array($row)) {
                    $v5510sTotalVendidoBruto += (float) ($row['total'] ?? 0);
                } elseif (is_object($row)) {
                    $v5510sTotalVendidoBruto += (float) ($row->total ?? 0);
                }
            }
        }

        $v5510sSellersTotal = 0.0;

        if (isset($sellers)) {
            foreach ($sellers as $row) {
                if (is_array($row)) {
                    $v5510sSellersTotal += (float) ($row['total'] ?? 0);
                } elseif (is_object($row)) {
                    $v5510sSellersTotal += (float) ($row->total ?? 0);
                }
            }
        }

        if ($v5510sSellersTotal > $v5510sTotalVendidoBruto) {
            $v5510sTotalVendidoBruto = $v5510sSellersTotal;
        }

        if ($v5510sTotalVendidoBruto <= 0 && isset($totals) && is_array($totals)) {
            $v5510sTotalVendidoBruto = (float) (
                $totals['paid_total']
                ?? $totals['total_sold']
                ?? $totals['sales_total']
                ?? 0
            );
        }

        $v5510sTotalVendidoBruto = round((float) $v5510sTotalVendidoBruto, 2);

        $v5510sTotalDevuelto = 0.0;

        foreach (['v5507eRefunds', 'v5508cRefunds', 'v5510bRefunds', 'refunds'] as $collectionName) {
            if (isset($$collectionName) && is_iterable($$collectionName)) {
                foreach ($$collectionName as $refundRow) {
                    if (is_array($refundRow)) {
                        $v5510sTotalDevuelto += (float) ($refundRow['payment_total'] ?? $refundRow['total'] ?? 0);
                    } elseif (is_object($refundRow)) {
                        $v5510sTotalDevuelto += (float) ($refundRow->payment_total ?? $refundRow->total ?? 0);
                    }
                }
            }
        }

        foreach ([
            $v5507eRefundTotal ?? 0,
            $v5508cRefundTotal ?? 0,
            $v5510bRefundTotal ?? 0,
            $v5510cTotalDevuelto ?? 0,
            $v5510pTotalDevuelto ?? 0,
            $v5510qTotalDevuelto ?? 0,
        ] as $candidate) {
            if ((float) $candidate > $v5510sTotalDevuelto) {
                $v5510sTotalDevuelto = (float) $candidate;
            }
        }

        $v5510sSessionId = null;

        if (isset($session) && is_object($session) && isset($session->id)) {
            $v5510sSessionId = (int) $session->id;
        }

        if (! $v5510sSessionId && isset($posSession) && is_object($posSession) && isset($posSession->id)) {
            $v5510sSessionId = (int) $posSession->id;
        }

        if (! $v5510sSessionId && isset($record) && is_object($record) && isset($record->id)) {
            $v5510sSessionId = (int) $record->id;
        }

        if (! $v5510sSessionId && isset($sessionData) && is_array($sessionData)) {
            if (! empty($sessionData['id'])) {
                $v5510sSessionId = (int) $sessionData['id'];
            } elseif (! empty($sessionData['number']) && \Illuminate\Support\Facades\Schema::hasTable('pos_sessions')) {
                $v5510sSessionId = (int) \Illuminate\Support\Facades\DB::table('pos_sessions')
                    ->where('number', (string) $sessionData['number'])
                    ->value('id');
            }
        }

        if (
            $v5510sSessionId
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')
        ) {
            $v5510sRefundFromDb = \Illuminate\Support\Facades\DB::table('pos_order_refunds as r')
                ->join('pos_orders as o', 'o.id', '=', 'r.pos_order_id')
                ->where('r.status', 'done')
                ->where(function ($q) use ($v5510sSessionId) {
                    $q->where('r.pos_session_id', $v5510sSessionId)
                        ->orWhere('o.pos_session_id', $v5510sSessionId);
                })
                ->sum('r.payment_total');

            if ((float) $v5510sRefundFromDb > $v5510sTotalDevuelto) {
                $v5510sTotalDevuelto = (float) $v5510sRefundFromDb;
            }
        }

        $v5510sTotalDevuelto = round((float) $v5510sTotalDevuelto, 2);
        $v5510sVentaNeta = round($v5510sTotalVendidoBruto - $v5510sTotalDevuelto, 2);

        $v5507eGrossSalesTotal = $v5510sTotalVendidoBruto;
        $v5507eRefundTotal = $v5510sTotalDevuelto;
        $v5507eNetSalesTotal = $v5510sVentaNeta;

        $v5508cTotalVendido = $v5510sTotalVendidoBruto;
        $v5508cRefundTotal = $v5510sTotalDevuelto;
        $v5508cVentaNeta = $v5510sVentaNeta;

        $v5510cTotalVendidoVisible = $v5510sTotalVendidoBruto;
        $v5510cTotalDevuelto = $v5510sTotalDevuelto;
        $v5510cVentaNeta = $v5510sVentaNeta;

        $v5510qTotalVendidoBruto = $v5510sTotalVendidoBruto;
        $v5510qTotalDevuelto = $v5510sTotalDevuelto;
        $v5510qVentaNeta = $v5510sVentaNeta;
    } catch (\Throwable $e) {
        //
    }

    $cashMovements = $summary['cash_movements'] ?? [];
    $salesBySeller = $summary['sales_by_seller'] ?? [];
    $paidOrders = $summary['paid_orders'] ?? [];

    $openingCash = $summary['opening_cash'] ?? [];
    $openingCashCount = $openingCash['cash_count'] ?? [];
    $closingCashCount = $session['closing_cash_count'] ?? [];

    $difference = (float) ($session['closing_difference'] ?? 0);

    $money = function ($value) {
        return '$' . number_format((float) ($value ?? 0), 2);
    };

    $safe = function ($value) {
        return e((string) ($value ?? ''));
    };

    // V5.49.7F - Resolver lista de precios desde paid_orders + metadata.
    $v5497fResolvePriceListName = function ($order): string {
        $metadata = [];

        $metadataRaw = is_array($order)
            ? ($order['metadata'] ?? '')
            : ($order->metadata ?? '');

        if (trim((string) $metadataRaw) !== '') {
            $decoded = json_decode((string) $metadataRaw, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        $name = trim((string) (
            (is_array($order) ? ($order['price_list_name'] ?? null) : ($order->price_list_name ?? null))
            ?? ($metadata['price_list_name'] ?? null)
            ?? ($metadata['selected_price_list_name'] ?? null)
            ?? ''
        ));

        if ($name !== '') {
            return $name;
        }

        $id = is_array($order)
            ? ($order['price_list_id'] ?? ($metadata['price_list_id'] ?? ($metadata['selected_price_list_id'] ?? null)))
            : ($order->price_list_id ?? ($metadata['price_list_id'] ?? ($metadata['selected_price_list_id'] ?? null)));

        if (is_numeric($id) && (int) $id > 0) {
            return 'Lista #' . (int) $id;
        }

        return 'Sin lista';
    };

    $v5497fPaidOrdersByPriceList = collect($paidOrders ?? [])
        ->filter(function ($order) {
            return (string) (is_array($order) ? ($order['status'] ?? '') : ($order->status ?? '')) === 'paid';
        })
        ->values();

    $v5497fPriceListTotals = $v5497fPaidOrdersByPriceList
        ->groupBy(function ($order) use ($v5497fResolvePriceListName) {
            return $v5497fResolvePriceListName($order);
        })
        ->map(function ($rows, $name) {
            return [
                'name' => $name,
                'count' => $rows->count(),
                'total' => round((float) $rows->sum(function ($row) {
                    return (float) (is_array($row) ? ($row['total'] ?? 0) : ($row->total ?? 0));
                }), 2),
            ];
        })
        ->sortBy('name')
        ->values();

    // V5.50.7B - Devoluciones en cierre de sesión.
    $v5507bSessionId = null;

    foreach (['session', 'posSession', 'record'] as $v5507bSessionVar) {
        if (isset($$v5507bSessionVar) && is_object($$v5507bSessionVar) && isset($$v5507bSessionVar->id)) {
            $v5507bSessionId = (int) $$v5507bSessionVar->id;
            break;
        }
    }

    if (! $v5507bSessionId && isset($sessionId)) {
        $v5507bSessionId = (int) $sessionId;
    }

    if (! $v5507bSessionId && isset($pos_session_id)) {
        $v5507bSessionId = (int) $pos_session_id;
    }

    $v5507bRefunds = collect();
    $v5507bRefundTotal = 0.0;
    $v5507bGrossSalesTotal = 0.0;
    $v5507bNetSalesTotal = 0.0;

    try {
        if (
            $v5507bSessionId
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')
        ) {
            $v5507bRefunds = \Illuminate\Support\Facades\DB::table('pos_order_refunds as r')
                ->join('pos_orders as o', 'o.id', '=', 'r.pos_order_id')
                ->where('r.status', 'done')
                ->where(function ($q) use ($v5507bSessionId) {
                    $q->where('r.pos_session_id', $v5507bSessionId)
                        ->orWhere('o.pos_session_id', $v5507bSessionId);
                })
                ->orderBy('r.id')
                ->get([
                    'r.id',
                    'r.number',
                    'r.type',
                    'r.reason',
                    'r.total',
                    'r.payment_total',
                    'r.refunded_at',
                    'r.stock_movement_id',
                    'o.id as order_id',
                    'o.number as order_number',
                    'o.total as order_total',
                    'o.status as order_status',
                ])
                ->map(function ($refund) {
                    $payments = collect();

                    if (\Illuminate\Support\Facades\Schema::hasTable('pos_order_refund_payments')) {
                        $payments = \Illuminate\Support\Facades\DB::table('pos_order_refund_payments')
                            ->where('pos_order_refund_id', $refund->id)
                            ->orderBy('id')
                            ->get();
                    }

                    $refund->payment_labels = $payments->pluck('payment_label')
                        ->filter()
                        ->unique()
                        ->values()
                        ->implode(', ');

                    if ($refund->payment_labels === '') {
                        $refund->payment_labels = 'No especificado';
                    }

                    $refund->type_label = match ((string) ($refund->type ?? '')) {
                        'partial' => 'Parcial',
                        'total' => 'Total',
                        default => ucfirst((string) ($refund->type ?? 'Devolución')),
                    };

                    return $refund;
                });

            $v5507bRefundTotal = round((float) $v5507bRefunds->sum(function ($refund) {
                return (float) ($refund->payment_total ?? $refund->total ?? 0);
            }), 4);
        }

        if (isset($paidOrdersCollection) && $paidOrdersCollection instanceof \Illuminate\Support\Collection) {
            $v5507bGrossSalesTotal = round((float) $paidOrdersCollection->sum(function ($order) {
                return (float) ($order->total ?? 0);
            }), 4);
        } elseif (isset($paidOrders) && $paidOrders instanceof \Illuminate\Support\Collection) {
            $v5507bGrossSalesTotal = round((float) $paidOrders->sum(function ($order) {
                return (float) ($order->total ?? 0);
            }), 4);
        } elseif (isset($totalSales)) {
            $v5507bGrossSalesTotal = round((float) $totalSales, 4);
        } elseif (isset($salesTotal)) {
            $v5507bGrossSalesTotal = round((float) $salesTotal, 4);
        }

        $v5507bNetSalesTotal = round($v5507bGrossSalesTotal - $v5507bRefundTotal, 4);
    } catch (\Throwable $e) {
        $v5507bRefunds = collect();
        $v5507bRefundTotal = 0.0;
        $v5507bNetSalesTotal = $v5507bGrossSalesTotal;
    }


    // V5.50.7C - Devoluciones robustas en reporte/corte de sesión.
    $v5507cSessionId = null;
    $v5507cSessionNumber = null;

    foreach (['session', 'posSession', 'record'] as $v5507cSessionVar) {
        if (isset($$v5507cSessionVar) && is_object($$v5507cSessionVar)) {
            if (isset($$v5507cSessionVar->id)) {
                $v5507cSessionId = (int) $$v5507cSessionVar->id;
            }

            foreach (['number', 'name', 'reference', 'code'] as $v5507cField) {
                if (! $v5507cSessionNumber && isset($$v5507cSessionVar->{$v5507cField})) {
                    $v5507cSessionNumber = (string) $$v5507cSessionVar->{$v5507cField};
                }
            }

            if ($v5507cSessionId) {
                break;
            }
        }
    }

    foreach (['sessionId', 'session_id', 'pos_session_id'] as $v5507cIdVar) {
        if (! $v5507cSessionId && isset($$v5507cIdVar)) {
            $v5507cSessionId = (int) $$v5507cIdVar;
        }
    }

    foreach (['sessionNumber', 'session_number', 'sessionName', 'session_name'] as $v5507cNumberVar) {
        if (! $v5507cSessionNumber && isset($$v5507cNumberVar)) {
            $v5507cSessionNumber = (string) $$v5507cNumberVar;
        }
    }

    try {
        if (! $v5507cSessionId && $v5507cSessionNumber && \Illuminate\Support\Facades\Schema::hasTable('pos_sessions')) {
            $v5507cSessionColumns = \Illuminate\Support\Facades\Schema::getColumnListing('pos_sessions');

            $v5507cSessionQuery = \Illuminate\Support\Facades\DB::table('pos_sessions');

            $v5507cSessionQuery->where(function ($q) use ($v5507cSessionColumns, $v5507cSessionNumber) {
                foreach (['number', 'name', 'reference', 'code'] as $column) {
                    if (in_array($column, $v5507cSessionColumns, true)) {
                        $q->orWhere($column, $v5507cSessionNumber);
                    }
                }
            });

            $v5507cSessionId = (int) $v5507cSessionQuery->value('id');
        }
    } catch (\Throwable $e) {
        $v5507cSessionId = $v5507cSessionId ?: null;
    }

    $v5507cRefunds = collect();
    $v5507cRefundTotal = 0.0;
    $v5507cGrossSalesTotal = 0.0;
    $v5507cNetSalesTotal = 0.0;

    try {
        if (
            $v5507cSessionId
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')
        ) {
            $v5507cRefunds = \Illuminate\Support\Facades\DB::table('pos_order_refunds as r')
                ->join('pos_orders as o', 'o.id', '=', 'r.pos_order_id')
                ->where('r.status', 'done')
                ->where(function ($q) use ($v5507cSessionId) {
                    $q->where('r.pos_session_id', $v5507cSessionId)
                        ->orWhere('o.pos_session_id', $v5507cSessionId);
                })
                ->orderBy('r.id')
                ->get([
                    'r.id',
                    'r.number',
                    'r.type',
                    'r.reason',
                    'r.total',
                    'r.payment_total',
                    'r.refunded_at',
                    'r.stock_movement_id',
                    'o.id as order_id',
                    'o.number as order_number',
                    'o.total as order_total',
                    'o.status as order_status',
                ])
                ->map(function ($refund) {
                    $payments = collect();

                    if (\Illuminate\Support\Facades\Schema::hasTable('pos_order_refund_payments')) {
                        $payments = \Illuminate\Support\Facades\DB::table('pos_order_refund_payments')
                            ->where('pos_order_refund_id', $refund->id)
                            ->orderBy('id')
                            ->get();
                    }

                    $refund->payment_labels = $payments->pluck('payment_label')
                        ->filter()
                        ->unique()
                        ->values()
                        ->implode(', ');

                    if ($refund->payment_labels === '') {
                        $refund->payment_labels = 'No especificado';
                    }

                    $refund->type_label = match ((string) ($refund->type ?? '')) {
                        'partial' => 'Parcial',
                        'total' => 'Total',
                        default => ucfirst((string) ($refund->type ?? 'Devolución')),
                    };

                    return $refund;
                });

            $v5507cRefundTotal = round((float) $v5507cRefunds->sum(function ($refund) {
                return (float) ($refund->payment_total ?? $refund->total ?? 0);
            }), 4);

            $v5507cGrossSalesTotal = round((float) \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('pos_session_id', $v5507cSessionId)
                ->whereIn('status', ['paid', 'returned'])
                ->sum('total'), 4);
        }

        if ($v5507cGrossSalesTotal <= 0) {
            if (isset($paidOrdersCollection) && $paidOrdersCollection instanceof \Illuminate\Support\Collection) {
                $v5507cGrossSalesTotal = round((float) $paidOrdersCollection->sum(function ($order) {
                    return (float) ($order->total ?? 0);
                }), 4);
            } elseif (isset($paidOrders) && $paidOrders instanceof \Illuminate\Support\Collection) {
                $v5507cGrossSalesTotal = round((float) $paidOrders->sum(function ($order) {
                    return (float) ($order->total ?? 0);
                }), 4);
            } elseif (isset($totalSales)) {
                $v5507cGrossSalesTotal = round((float) $totalSales, 4);
            } elseif (isset($salesTotal)) {
                $v5507cGrossSalesTotal = round((float) $salesTotal, 4);
            }
        }

        $v5507cNetSalesTotal = round($v5507cGrossSalesTotal - $v5507cRefundTotal, 4);
    } catch (\Throwable $e) {
        $v5507cRefunds = collect();
        $v5507cRefundTotal = 0.0;
        $v5507cNetSalesTotal = $v5507cGrossSalesTotal;
    }

?>

<?php
    // V5.51.0Q - Totales cierre definitivos para reporte.
    try {
        $v5510qTotalVendidoBruto = 0.0;

        // Total vendido bruto = suma visible de métodos de pago.
        if (isset($payments)) {
            foreach ($payments as $v5510qPaymentRow) {
                if (is_array($v5510qPaymentRow)) {
                    $v5510qTotalVendidoBruto += (float) ($v5510qPaymentRow['total'] ?? 0);
                } elseif (is_object($v5510qPaymentRow)) {
                    $v5510qTotalVendidoBruto += (float) ($v5510qPaymentRow->total ?? 0);
                }
            }
        }

        // Fallback: resumen del cierre.
        if ($v5510qTotalVendidoBruto <= 0 && isset($totals) && is_array($totals)) {
            $v5510qTotalVendidoBruto = (float) (
                $totals['paid_total']
                ?? $totals['total_sold']
                ?? $totals['sales_total']
                ?? 0
            );
        }

        // Fallback adicional: total por vendedor, si existe y es mayor.
        if (isset($sellers)) {
            $v5510qTotalVendedores = 0.0;

            foreach ($sellers as $v5510qSellerRow) {
                if (is_array($v5510qSellerRow)) {
                    $v5510qTotalVendedores += (float) ($v5510qSellerRow['total'] ?? 0);
                } elseif (is_object($v5510qSellerRow)) {
                    $v5510qTotalVendedores += (float) ($v5510qSellerRow->total ?? 0);
                }
            }

            if ($v5510qTotalVendedores > $v5510qTotalVendidoBruto) {
                $v5510qTotalVendidoBruto = $v5510qTotalVendedores;
            }
        }

        $v5510qTotalVendidoBruto = round((float) $v5510qTotalVendidoBruto, 2);

        // Total devuelto = devoluciones ya calculadas por el bloque de devoluciones.
        $v5510qTotalDevuelto = round((float) (
            $v5507eRefundTotal
            ?? $v5508cRefundTotal
            ?? $v5510bRefundTotal
            ?? $v5510cTotalDevuelto
            ?? $v5510pTotalDevuelto
            ?? 0
        ), 2);

        // Si hay variable vieja con 0 pero el bloque visible ya tiene devoluciones, respetar la mayor.
        if (isset($v5508cRefundTotal) && (float) $v5508cRefundTotal > $v5510qTotalDevuelto) {
            $v5510qTotalDevuelto = round((float) $v5508cRefundTotal, 2);
        }

        if (isset($v5507eRefundTotal) && (float) $v5507eRefundTotal > $v5510qTotalDevuelto) {
            $v5510qTotalDevuelto = round((float) $v5507eRefundTotal, 2);
        }

        $v5510qVentaNeta = round($v5510qTotalVendidoBruto - $v5510qTotalDevuelto, 2);

        // Sobrescribir compatibilidad de variables viejas.
        $v5507eGrossSalesTotal = $v5510qTotalVendidoBruto;
        $v5507eRefundTotal = $v5510qTotalDevuelto;
        $v5507eNetSalesTotal = $v5510qVentaNeta;

        $v5508cTotalVendido = $v5510qTotalVendidoBruto;
        $v5508cRefundTotal = $v5510qTotalDevuelto;
        $v5508cVentaNeta = $v5510qVentaNeta;

        $v5510cTotalVendidoVisible = $v5510qTotalVendidoBruto;
        $v5510cTotalDevuelto = $v5510qTotalDevuelto;
        $v5510cVentaNeta = $v5510qVentaNeta;

        $v5510lTotalMetodosPago = $v5510qTotalVendidoBruto;
        $v5510lVentaTotalNeta = $v5510qVentaNeta;
        $v5510mTotalVendidoBruto = $v5510qTotalVendidoBruto;
        $v5510mVentaNeta = $v5510qVentaNeta;
        $v5510pTotalVendidoBruto = $v5510qTotalVendidoBruto;
        $v5510pTotalDevuelto = $v5510qTotalDevuelto;
        $v5510pVentaNeta = $v5510qVentaNeta;
    } catch (\Throwable $e) {
        //
    }
?>


<?php
    // V5.51.0P - Reporte venta: tarjetas total vendido / devuelto / neta.
    try {
        $v5510pSessionId = null;

        if (isset($session) && is_object($session) && isset($session->id)) {
            $v5510pSessionId = (int) $session->id;
        }

        if (! $v5510pSessionId && isset($posSession) && is_object($posSession) && isset($posSession->id)) {
            $v5510pSessionId = (int) $posSession->id;
        }

        if (! $v5510pSessionId && isset($record) && is_object($record) && isset($record->id)) {
            $v5510pSessionId = (int) $record->id;
        }

        if (! $v5510pSessionId && isset($sessionData) && is_array($sessionData)) {
            if (! empty($sessionData['id'])) {
                $v5510pSessionId = (int) $sessionData['id'];
            } elseif (! empty($sessionData['number']) && \Illuminate\Support\Facades\Schema::hasTable('pos_sessions')) {
                $v5510pSessionId = (int) \Illuminate\Support\Facades\DB::table('pos_sessions')
                    ->where('number', (string) $sessionData['number'])
                    ->value('id');
            }
        }

        if (! $v5510pSessionId && isset($summary) && is_array($summary)) {
            foreach (['session_id', 'pos_session_id'] as $v5510pKey) {
                if (! empty($summary[$v5510pKey])) {
                    $v5510pSessionId = (int) $summary[$v5510pKey];
                    break;
                }
            }

            if (! $v5510pSessionId && isset($summary['session']) && is_array($summary['session'])) {
                if (! empty($summary['session']['id'])) {
                    $v5510pSessionId = (int) $summary['session']['id'];
                } elseif (! empty($summary['session']['number']) && \Illuminate\Support\Facades\Schema::hasTable('pos_sessions')) {
                    $v5510pSessionId = (int) \Illuminate\Support\Facades\DB::table('pos_sessions')
                        ->where('number', (string) $summary['session']['number'])
                        ->value('id');
                }
            }
        }

        $v5510pTotalVendidoBruto = 0.0;

        if (isset($payments)) {
            foreach ($payments as $v5510pPaymentRow) {
                if (is_array($v5510pPaymentRow)) {
                    $v5510pTotalVendidoBruto += (float) ($v5510pPaymentRow['total'] ?? 0);
                } elseif (is_object($v5510pPaymentRow)) {
                    $v5510pTotalVendidoBruto += (float) ($v5510pPaymentRow->total ?? 0);
                }
            }
        }

        if ($v5510pTotalVendidoBruto <= 0 && isset($totals) && is_array($totals)) {
            $v5510pTotalVendidoBruto = (float) (
                $totals['paid_total']
                ?? $totals['total_sold']
                ?? $totals['sales_total']
                ?? 0
            );
        }

        if (
            $v5510pSessionId
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_payments')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')
        ) {
            $v5510pDbTotal = \Illuminate\Support\Facades\DB::table('pos_order_payments as p')
                ->join('pos_orders as o', 'o.id', '=', 'p.pos_order_id')
                ->where('o.pos_session_id', $v5510pSessionId)
                ->whereIn('o.status', ['paid', 'returned'])
                ->sum('p.amount');

            if ((float) $v5510pDbTotal > 0) {
                $v5510pTotalVendidoBruto = (float) $v5510pDbTotal;
            }
        }

        $v5510pTotalVendidoBruto = round((float) $v5510pTotalVendidoBruto, 2);

        $v5510pTotalDevuelto = round((float) (
            $v5507eRefundTotal
            ?? $v5508cRefundTotal
            ?? $v5510bRefundTotal
            ?? $v5510cTotalDevuelto
            ?? 0
        ), 2);

        if (
            $v5510pSessionId
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_refund_payments')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')
        ) {
            $v5510pDbRefund = \Illuminate\Support\Facades\DB::table('pos_order_refund_payments as rp')
                ->join('pos_order_refunds as r', 'r.id', '=', 'rp.pos_order_refund_id')
                ->join('pos_orders as o', 'o.id', '=', 'r.pos_order_id')
                ->where('r.status', 'done')
                ->where(function ($q) use ($v5510pSessionId) {
                    $q->where('r.pos_session_id', $v5510pSessionId)
                        ->orWhere('o.pos_session_id', $v5510pSessionId);
                })
                ->sum('rp.amount');

            if ((float) $v5510pDbRefund > 0) {
                $v5510pTotalDevuelto = round((float) $v5510pDbRefund, 2);
            }
        }

        $v5510pVentaNeta = round($v5510pTotalVendidoBruto - $v5510pTotalDevuelto, 2);

        $v5507eGrossSalesTotal = $v5510pTotalVendidoBruto;
        $v5507eRefundTotal = $v5510pTotalDevuelto;
        $v5507eNetSalesTotal = $v5510pVentaNeta;

        $v5508cTotalVendido = $v5510pTotalVendidoBruto;
        $v5508cRefundTotal = $v5510pTotalDevuelto;
        $v5508cVentaNeta = $v5510pVentaNeta;

        $v5510cTotalVendidoVisible = $v5510pTotalVendidoBruto;
        $v5510cTotalDevuelto = $v5510pTotalDevuelto;
        $v5510cVentaNeta = $v5510pVentaNeta;
    } catch (\Throwable $e) {
        $v5510pTotalVendidoBruto = (float) ($totals['paid_total'] ?? 0);
        $v5510pTotalDevuelto = (float) ($v5507eRefundTotal ?? $v5508cRefundTotal ?? 0);
        $v5510pVentaNeta = round($v5510pTotalVendidoBruto - $v5510pTotalDevuelto, 2);
    }
?>


<?php
// V5.50.7E - Devoluciones reales en cierre: compatible con $session array u objeto.
$v5507eSessionId = null;

if (isset($session)) {
    if (is_array($session)) {
        $v5507eSessionId = isset($session['id']) ? (int) $session['id'] : null;
    } elseif (is_object($session) && isset($session->id)) {
        $v5507eSessionId = (int) $session->id;
    }
}

if (! $v5507eSessionId && isset($summary) && is_array($summary)) {
    foreach (['session', 'pos_session'] as $key) {
        if (! empty($summary[$key])) {
            if (is_array($summary[$key]) && isset($summary[$key]['id'])) {
                $v5507eSessionId = (int) $summary[$key]['id'];
                break;
            }

            if (is_object($summary[$key]) && isset($summary[$key]->id)) {
                $v5507eSessionId = (int) $summary[$key]->id;
                break;
            }
        }
    }
}

foreach (['sessionId', 'session_id', 'pos_session_id'] as $varName) {
    if (! $v5507eSessionId && isset($$varName)) {
        $v5507eSessionId = (int) $$varName;
    }
}

$v5507eRefunds = collect();
$v5507eRefundTotal = 0.0;
$v5507eGrossSalesTotal = 0.0;
$v5507eNetSalesTotal = 0.0;

try {
    if (
        $v5507eSessionId
        && \Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')
        && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')
    ) {
        $v5507eRefunds = \Illuminate\Support\Facades\DB::table('pos_order_refunds as r')
            ->join('pos_orders as o', 'o.id', '=', 'r.pos_order_id')
            ->where('r.status', 'done')
            ->where(function ($q) use ($v5507eSessionId) {
                $q->where('r.pos_session_id', $v5507eSessionId)
                    ->orWhere('o.pos_session_id', $v5507eSessionId);
            })
            ->orderBy('r.id')
            ->get([
                'r.id',
                'r.number',
                'r.type',
                'r.reason',
                'r.total',
                'r.payment_total',
                'r.refunded_at',
                'o.id as order_id',
                'o.number as order_number',
                'o.status as order_status',
            ])
            ->map(function ($refund) {
                $payments = collect();

                if (\Illuminate\Support\Facades\Schema::hasTable('pos_order_refund_payments')) {
                    $payments = \Illuminate\Support\Facades\DB::table('pos_order_refund_payments')
                        ->where('pos_order_refund_id', $refund->id)
                        ->orderBy('id')
                        ->get();
                }

                $refund->payment_labels = $payments->pluck('payment_label')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');

                if ($refund->payment_labels === '') {
                    $refund->payment_labels = 'No especificado';
                }

                $refund->type_label = match ((string) ($refund->type ?? '')) {
                    'partial' => 'Parcial',
                    'total' => 'Total',
                    default => ucfirst((string) ($refund->type ?? 'Devolución')),
                };

                return $refund;
            });

        $v5507eRefundTotal = round((float) $v5507eRefunds->sum(function ($refund) {
            return (float) ($refund->payment_total ?? $refund->total ?? 0);
        }), 2);

        $v5507eGrossSalesTotal = round((float) \Illuminate\Support\Facades\DB::table('pos_orders')
            ->where('pos_session_id', $v5507eSessionId)
            ->whereIn('status', ['paid', 'returned'])
            ->sum('total'), 2);
    }

    if ($v5507eGrossSalesTotal <= 0) {
        if (isset($totals) && is_array($totals) && isset($totals['total_sold'])) {
            $v5507eGrossSalesTotal = round((float) $totals['total_sold'], 2);
        } elseif (isset($summary) && is_array($summary) && isset($summary['totals']['total_sold'])) {
            $v5507eGrossSalesTotal = round((float) $summary['totals']['total_sold'], 2);
        }
    }

    $v5507eNetSalesTotal = round($v5507eGrossSalesTotal - $v5507eRefundTotal, 2);
} catch (\Throwable $e) {
    $v5507eRefunds = collect();
    $v5507eRefundTotal = 0.0;
    $v5507eNetSalesTotal = $v5507eGrossSalesTotal;
}
?>


<div class="header">
    <div class="header-left">
        <?php if (! empty($company['logo_url'])): ?>
            <img class="logo" src="<?= $safe($company['logo_url']) ?>">
        <?php endif; ?>

        <h1>Reporte de venta / cierre de caja</h1>
        <div class="muted"><?= $safe($company['name'] ?? '') ?></div>
        <div class="muted">
            Caja / PDV: <strong><?= $safe($pos['name'] ?? 'PDV') ?></strong>
        </div>
    </div>

    <div class="header-right">
        <div><strong>Sesión:</strong> <?= $safe($session['number'] ?? '') ?></div>
        <div><strong>Estado:</strong> <?= $safe($session['status_label'] ?? $session['status'] ?? '') ?></div>
        <div><strong>Apertura:</strong> <?= $safe($session['opened_at'] ?? '') ?></div>
        <div><strong>Cierre:</strong> <?= $safe($session['closed_at'] ?? 'Aún abierta') ?></div>
        <div><strong>Generado:</strong> <?= $safe($summary['generated_at'] ?? now()->toDateTimeString()) ?></div>
    </div>
</div>

<div class="box">
    <strong>Cajero / caja de cobro:</strong> <?= $safe($cashier['name'] ?? 'Sin cajero') ?><br>
    <strong>Fondo de apertura:</strong> <?= $money($totals['opening_cash_amount'] ?? ($session['opening_amount'] ?? 0)) ?><br>
    <strong>Efectivo contado:</strong> <?= $money($session['closing_amount'] ?? 0) ?><br>
    <strong>Diferencia:</strong>
    <span class="<?= abs($difference) > 0.009 ? 'danger' : 'success' ?>">
        <?= $money($difference) ?>
    </span>
</div>

<h2>Efectivo de caja</h2>
<table>
    <tr>
        <th>Concepto</th>
<th class="right">Importe</th>
    </tr>
    <tr>
        <td>Fondo de apertura</td>
        <td class="right"><?= $money($totals['opening_cash_amount'] ?? ($session['opening_amount'] ?? 0)) ?></td>
    </tr>
    <tr>
        <td>Ventas cobradas en efectivo</td>
        <td class="right"><?= $money($totals['cash_payments_total'] ?? 0) ?></td>
    </tr>
    <tr>
        <td>Entradas de efectivo</td>
        <td class="right"><?= $money($totals['cash_in_total'] ?? 0) ?></td>
    </tr>
    <tr>
        <td>Retiros de efectivo</td>
        <td class="right">-<?= $money($totals['cash_out_total'] ?? 0) ?></td>
    </tr>
    <tr>
        <td><strong>Efectivo esperado</strong></td>
        <td class="right total"><?= $money($totals['expected_cash'] ?? 0) ?></td>
    </tr>
    <tr>
        <td><strong>Efectivo contado</strong></td>
        <td class="right total"><?= $money($session['closing_amount'] ?? 0) ?></td>
    </tr>
    <tr>
        <td><strong>Diferencia</strong></td>
        <td class="right <?= abs($difference) > 0.009 ? 'danger' : 'success' ?>">
            <?= $money($difference) ?>
        </td>
    </tr>
</table>

<h2>Movimientos de efectivo</h2>
<table>
    <tr>
        <th>Tipo</th>
        <th>Folio</th>
<th>Motivo</th>
        <th>Realiza</th>
        <th>Supervisor</th>
        <th class="right">Importe</th>
    </tr>

    <?php if (! empty($cashMovements)): ?>
        <?php foreach ($cashMovements as $movement): ?>
            <tr>
                <td><?= $safe($movement['type_label'] ?? $movement['type'] ?? '') ?></td>
                <td><?= $safe($movement['number'] ?? '') ?></td>
                <td><?= $safe($movement['reason'] ?? '') ?></td>
                <td><?= $safe($movement['performed_by_name'] ?? '') ?></td>
                <td><?= $safe($movement['supervisor_name'] ?? '') ?></td>
                <td class="right"><?= $money($movement['amount'] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="6">Sin movimientos de efectivo.</td>
        </tr>
    <?php endif; ?>
</table>



<?php if (! empty($session['closing_note'])): ?>
    <div class="box">
        <strong>Nota de cierre:</strong><br>
        <?= $safe($session['closing_note']) ?>
    </div>

<?php endif; ?>

<div class="kpis">
    <div class="kpi-row">
        <div class="kpi">

@php
    // V5.51.0T - Fix FINAL tarjetas reporte venta.
    try {
        /*
         * Total vendido bruto:
         * Usamos el mayor entre:
         * - suma de métodos de pago
         * - total por vendedor
         * - totals['paid_total']
         */
        $v5510tTotalVendidoBruto = 0.0;

        if (isset($payments) && is_iterable($payments)) {
            foreach ($payments as $row) {
                if (is_array($row)) {
                    $v5510tTotalVendidoBruto += (float) ($row['total'] ?? 0);
                } elseif (is_object($row)) {
                    $v5510tTotalVendidoBruto += (float) ($row->total ?? 0);
                }
            }
        }

        $v5510tTotalVendedores = 0.0;

        if (isset($sellers) && is_iterable($sellers)) {
            foreach ($sellers as $row) {
                if (is_array($row)) {
                    $v5510tTotalVendedores += (float) ($row['total'] ?? 0);
                } elseif (is_object($row)) {
                    $v5510tTotalVendedores += (float) ($row->total ?? 0);
                }
            }
        }

        foreach ([
            $v5510tTotalVendedores,
            $totals['paid_total'] ?? 0,
            $totals['total_sold'] ?? 0,
            $totals['sales_total'] ?? 0,
            $v5507eGrossSalesTotal ?? 0,
            $v5508cTotalVendido ?? 0,
            $v5510qTotalVendidoBruto ?? 0,
            $v5510sTotalVendidoBruto ?? 0,
        ] as $candidate) {
            if ((float) $candidate > $v5510tTotalVendidoBruto) {
                $v5510tTotalVendidoBruto = (float) $candidate;
            }
        }

        $v5510tTotalVendidoBruto = round((float) $v5510tTotalVendidoBruto, 2);

        /*
         * Buscar sesión.
         */
        $v5510tSessionId = null;

        if (isset($session) && is_object($session) && isset($session->id)) {
            $v5510tSessionId = (int) $session->id;
        }

        if (! $v5510tSessionId && isset($posSession) && is_object($posSession) && isset($posSession->id)) {
            $v5510tSessionId = (int) $posSession->id;
        }

        if (! $v5510tSessionId && isset($record) && is_object($record) && isset($record->id)) {
            $v5510tSessionId = (int) $record->id;
        }

        if (! $v5510tSessionId && isset($sessionData) && is_array($sessionData)) {
            if (! empty($sessionData['id'])) {
                $v5510tSessionId = (int) $sessionData['id'];
            } elseif (! empty($sessionData['number']) && \Illuminate\Support\Facades\Schema::hasTable('pos_sessions')) {
                $v5510tSessionId = (int) \Illuminate\Support\Facades\DB::table('pos_sessions')
                    ->where('number', (string) $sessionData['number'])
                    ->value('id');
            }
        }

        if (! $v5510tSessionId && isset($summary) && is_array($summary)) {
            foreach (['session_id', 'pos_session_id'] as $key) {
                if (! empty($summary[$key])) {
                    $v5510tSessionId = (int) $summary[$key];
                    break;
                }
            }

            if (! $v5510tSessionId && isset($summary['session']) && is_array($summary['session'])) {
                if (! empty($summary['session']['id'])) {
                    $v5510tSessionId = (int) $summary['session']['id'];
                } elseif (! empty($summary['session']['number']) && \Illuminate\Support\Facades\Schema::hasTable('pos_sessions')) {
                    $v5510tSessionId = (int) \Illuminate\Support\Facades\DB::table('pos_sessions')
                        ->where('number', (string) $summary['session']['number'])
                        ->value('id');
                }
            }
        }

        /*
         * Total devuelto:
         * 1) variables/colecciones ya calculadas
         * 2) BD por pos_order_refunds
         * 3) metadata de órdenes como fallback
         */
        $v5510tTotalDevuelto = 0.0;

        foreach (['v5507eRefunds', 'v5508cRefunds', 'v5510bRefunds', 'refunds'] as $collectionName) {
            if (isset($$collectionName) && is_iterable($$collectionName)) {
                foreach ($$collectionName as $refundRow) {
                    if (is_array($refundRow)) {
                        $v5510tTotalDevuelto += (float) ($refundRow['payment_total'] ?? $refundRow['total'] ?? 0);
                    } elseif (is_object($refundRow)) {
                        $v5510tTotalDevuelto += (float) ($refundRow->payment_total ?? $refundRow->total ?? 0);
                    }
                }
            }
        }

        foreach ([
            $v5507eRefundTotal ?? 0,
            $v5508cRefundTotal ?? 0,
            $v5510bRefundTotal ?? 0,
            $v5510cTotalDevuelto ?? 0,
            $v5510pTotalDevuelto ?? 0,
            $v5510qTotalDevuelto ?? 0,
            $v5510sTotalDevuelto ?? 0,
        ] as $candidate) {
            if ((float) $candidate > $v5510tTotalDevuelto) {
                $v5510tTotalDevuelto = (float) $candidate;
            }
        }

        if (
            $v5510tSessionId
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')
        ) {
            $v5510tRefundDb = \Illuminate\Support\Facades\DB::table('pos_order_refunds as r')
                ->join('pos_orders as o', 'o.id', '=', 'r.pos_order_id')
                ->where('r.status', 'done')
                ->where(function ($q) use ($v5510tSessionId) {
                    $q->where('r.pos_session_id', $v5510tSessionId)
                        ->orWhere('o.pos_session_id', $v5510tSessionId);
                })
                ->sum('r.payment_total');

            if ((float) $v5510tRefundDb > $v5510tTotalDevuelto) {
                $v5510tTotalDevuelto = (float) $v5510tRefundDb;
            }
        }

        if (
            $v5510tTotalDevuelto <= 0
            && $v5510tSessionId
            && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')
        ) {
            $ordersWithMetadata = \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('pos_session_id', $v5510tSessionId)
                ->whereNotNull('metadata')
                ->get(['metadata']);

            foreach ($ordersWithMetadata as $orderRow) {
                $metadata = json_decode((string) ($orderRow->metadata ?? ''), true);

                if (is_array($metadata)) {
                    $v5510tTotalDevuelto += (float) ($metadata['refund_total'] ?? 0);
                }
            }
        }

        $v5510tTotalDevuelto = round((float) $v5510tTotalDevuelto, 2);
        $v5510tVentaNeta = round($v5510tTotalVendidoBruto - $v5510tTotalDevuelto, 2);

        /*
         * Sobrescribir todas las variables viejas para que las tarjetas no usen valores previos.
         */
        $v5507eGrossSalesTotal = $v5510tTotalVendidoBruto;
        $v5507eRefundTotal = $v5510tTotalDevuelto;
        $v5507eNetSalesTotal = $v5510tVentaNeta;

        $v5508cTotalVendido = $v5510tTotalVendidoBruto;
        $v5508cRefundTotal = $v5510tTotalDevuelto;
        $v5508cVentaNeta = $v5510tVentaNeta;

        $v5510cTotalVendidoVisible = $v5510tTotalVendidoBruto;
        $v5510cTotalDevuelto = $v5510tTotalDevuelto;
        $v5510cVentaNeta = $v5510tVentaNeta;

        $v5510qTotalVendidoBruto = $v5510tTotalVendidoBruto;
        $v5510qTotalDevuelto = $v5510tTotalDevuelto;
        $v5510qVentaNeta = $v5510tVentaNeta;
    } catch (\Throwable $e) {
        //
    }
@endphp


            <small>Total vendido</small>
            <strong><?= $money($v5510tTotalVendidoBruto ?? 0) ?></strong>
        </div>
        <div class="kpi">
            <small>Tickets cobrados</small>
            <strong><?= number_format((float) ($totals['paid_tickets'] ?? 0)) ?></strong>
        </div>
        <div class="kpi">
            <small>Tickets pendientes</small>
            <strong><?= number_format((float) ($totals['pending_tickets_created_in_session'] ?? 0)) ?></strong>
        </div>
        <div class="kpi">
            <small>Reservas activas</small>
            <strong><?= number_format((float) ($totals['active_reservations'] ?? 0)) ?></strong>
        </div>
    </div>
</div>

<?php if (! empty($openingCashCount)): ?>
    <h2>Fondo de apertura por denominación</h2>
    <table>
        <tr>
            <th>Denominación</th>
            <th class="right">Cantidad</th>
            <th class="right">Total</th>
        </tr>

        <?php foreach ($openingCashCount as $row): ?>
            <?php if ((float) ($row['quantity'] ?? 0) > 0): ?>
                <tr>
                    <td><?= $safe($row['name'] ?? ('$' . number_format((float) ($row['value'] ?? 0), 2))) ?></td>
                    <td class="right"><?= number_format((float) ($row['quantity'] ?? 0)) ?></td>
                    <td class="right"><?= $money($row['total'] ?? 0) ?></td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php if (! empty($closingCashCount)): ?>
    <h2>Conteo de efectivo de cierre</h2>
    <table>
        <tr>
            <th>Denominación</th>
            <th class="right">Cantidad</th>
            <th class="right">Total</th>
        </tr>

        <?php foreach ($closingCashCount as $row): ?>
            <?php if ((float) ($row['quantity'] ?? 0) > 0): ?>
                <tr>
                    <td><?= $money($row['value'] ?? 0) ?></td>
                    <td class="right"><?= number_format((float) ($row['quantity'] ?? 0)) ?></td>
                    <td class="right"><?= $money($row['total'] ?? 0) ?></td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </table>
<?php endif; ?>


<?php if (isset($v5507eRefundTotal) && (float) $v5507eRefundTotal > 0): ?>
<div id="v5507h-refund-cards-row" style="display:table; table-layout:fixed; border-collapse:collapse; margin:0 0 12px 0; width:100%;">

<?php
    $v5507kTotalVendido = (float) ($v5507eGrossSalesTotal ?? 0);

    if (isset($totals) && is_array($totals)) {
        foreach (['total_sold', 'sold_total', 'sales_total', 'total_sales'] as $v5507kKey) {
            if (isset($totals[$v5507kKey])) {
                $v5507kTotalVendido = (float) $totals[$v5507kKey];
                break;
            }
        }
    }

    if (isset($summary) && is_array($summary) && isset($summary['totals']) && is_array($summary['totals'])) {
        foreach (['total_sold', 'sold_total', 'sales_total', 'total_sales'] as $v5507kKey) {
            if (isset($summary['totals'][$v5507kKey])) {
                $v5507kTotalVendido = (float) $summary['totals'][$v5507kKey];
                break;
            }
        }
    }

    $v5507kVentaNeta = round($v5507kTotalVendido - (float) ($v5507eRefundTotal ?? 0), 2);
?>

    <div id="v5507h-refunds-card" style="display:table-cell; width:33.33%; padding:10px 12px; border:1px solid #e5e7eb; vertical-align:top;">
        <small style="display:block; color:#64748b; font-size:10px;">Total devuelto</small>
        <strong style="color:#b91c1c;">-<?= $money($v5510tTotalDevuelto ?? 0) ?></strong>
    </div>

    <div id="v5507h-net-sales-card" style="display:table-cell; width:33.33%; padding:10px 12px; border:1px solid #e5e7eb; vertical-align:top;">
        <small style="display:block; color:#64748b; font-size:10px;">Venta neta</small>
        <strong><?= $money($v5510tVentaNeta ?? 0) ?></strong>
    </div>

    <div id="v5507h-refunded-tickets-card" style="display:table-cell; width:33.33%; padding:10px 12px; border:1px solid #e5e7eb; vertical-align:top;">
        <small style="display:block; color:#64748b; font-size:10px;">Tickets devueltos</small>
        <strong><?= number_format(isset($v5507eRefunds) ? $v5507eRefunds->count() : 0) ?></strong>
    </div>
</div>
<?php endif; ?>

<h2>Resumen por método de pago</h2>
<table>
    <tr>
        <th>Método</th>
        <th class="right">Operaciones</th>
        <th class="right">Total</th>
    </tr>

    <?php if (! empty($payments)): ?>
        <?php foreach ($payments as $row): ?>
            <tr>
                <td><?= $safe($row['method'] ?? 'Sin método') ?></td>
                <td class="right"><?= number_format((float) ($row['count'] ?? $row['payments_count'] ?? 0)) ?></td>
                <td class="right"><?= $money($row['total'] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="3">Sin pagos registrados.</td>
        </tr>
    <?php endif; ?>
</table>

<h2>Resumen por vendedor</h2>
<table>
    <tr>
        <th>Vendedor</th>
        <th class="right">Tickets</th>
        <th class="right">Total</th>
    </tr>

    <?php if (! empty($salesBySeller)): ?>
        <?php foreach ($salesBySeller as $seller): ?>
            <tr>
                <td><?= $safe($seller['seller_name'] ?? 'Sin vendedor') ?></td>
                <td class="right"><?= number_format((float) ($seller['tickets'] ?? $seller['count'] ?? 0)) ?></td>
                <td class="right"><?= $money($seller['total'] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="3">Sin ventas por vendedor.</td>
        </tr>
    <?php endif; ?>
</table>







<h2>Tickets cobrados</h2>
<table>
    <tr>
        <th>Ticket</th>
        <th>Cliente</th>
        <th>Métodos de pago</th>
        <th>Lista de precios</th>
        <th class="right">Total</th>
    </tr>

    <?php if (! empty($paidOrders)): ?>
        <?php foreach ($paidOrders as $order): ?>
            <tr>
                <td><?= $safe($order['number'] ?? ('#' . ($order['id'] ?? ''))) ?></td>
                <td><?= $safe($order['customer_name'] ?? 'Cliente mostrador') ?></td>
                <td>
                    <?php
                        $orderPayments = $order['payments'] ?? [];
                        $pieces = [];

                        if (is_array($orderPayments)) {
                            foreach ($orderPayments as $payment) {
                                $pieces[] = ($payment['method'] ?? $payment['payment_label'] ?? 'Pago') . ' ' . $money($payment['amount'] ?? 0);
                            }
                        }

                        echo $safe(! empty($pieces) ? implode(', ', $pieces) : '—');
                    ?>
                </td>
                <td><?= $safe($v5497fResolvePriceListName($order)) ?></td>
                <td class="right"><?= $money($order['total'] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="5">Sin tickets cobrados.</td>
        </tr>
    <?php endif; ?>
</table>
<!-- V5.49.7K_RESUMEN_LISTAS -->
<?php if (isset($v5497fPriceListTotals) && $v5497fPriceListTotals->count()): ?>

@if(isset($v5507bRefunds) && $v5507bRefunds->count())
    <h2 id="v5507b-refunds-section">Devoluciones de la sesión</h2>

    <table style="width:100%; border-collapse:collapse; margin-top:8px;">
        <thead>
            <tr>
                <th>Devolución</th>
                <th>Tipo</th>
                <th>Ticket original</th>
                <th>Método</th>
                <th>Motivo</th>
                <th style="text-align:right;">Total devuelto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($v5507bRefunds as $refund)
                <tr>
                    <td>
                        <strong>{{ $refund->number }}</strong><br>
                        <small>{{ $refund->refunded_at }}</small>
                    </td>
                    <td>{{ $refund->type_label }}</td>
                    <td>{{ $refund->order_number }}</td>
                    <td>{{ $refund->payment_labels }}</td>
                    <td>{{ $refund->reason }}</td>
                    <td style="text-align:right;">
                        -${{ number_format((float) ($refund->payment_total ?? $refund->total ?? 0), 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="5" style="text-align:right;">Total devoluciones</th>
                <th style="text-align:right;">-${{ number_format((float) ($v5507bRefundTotal ?? 0), 2) }}</th>
            </tr>
        </tfoot>
    </table>
@endif


@if(isset($v5507cRefunds) && $v5507cRefunds->count())
    <h2 id="v5507c-refunds-section">Devoluciones de la sesión</h2>

    <table style="width:100%; border-collapse:collapse; margin-top:8px;">
        <thead>
            <tr>
                <th>Devolución</th>
                <th>Tipo</th>
                <th>Ticket original</th>
                <th>Método</th>
                <th>Motivo</th>
                <th style="text-align:right;">Total devuelto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($v5507cRefunds as $refund)
                <tr>
                    <td>
                        <strong>{{ $refund->number }}</strong><br>
                        <small>{{ $refund->refunded_at }}</small>
                    </td>
                    <td>{{ $refund->type_label }}</td>
                    <td>{{ $refund->order_number }}</td>
                    <td>{{ $refund->payment_labels }}</td>
                    <td>{{ $refund->reason }}</td>
                    <td style="text-align:right;">
                        -${{ number_format((float) ($refund->payment_total ?? $refund->total ?? 0), 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="5" style="text-align:right;">Total devoluciones</th>
                <th style="text-align:right;">-${{ number_format((float) ($v5507cRefundTotal ?? 0), 2) }}</th>
            </tr>
        </tfoot>
    </table>
@endif


<?php if (isset($v5507eRefunds) && $v5507eRefunds->count()): ?>
<h2 id="v5507e-refunds-section">Devoluciones de la sesión</h2>

<table>
    <tr>
        <th>Devolución</th>
        <th>Tipo</th>
        <th>Ticket original</th>
        <th>Método</th>
        <th>Motivo</th>
        <th class="right">Total devuelto</th>
    </tr>

    <?php foreach ($v5507eRefunds as $refund): ?>
        <tr>
            <td>
                <strong><?= $safe($refund->number) ?></strong><br>
                <span class="muted"><?= $safe($refund->refunded_at) ?></span>
            </td>
            <td><?= $safe($refund->type_label) ?></td>
            <td><?= $safe($refund->order_number) ?></td>
            <td><?= $safe($refund->payment_labels) ?></td>
            <td><?= $safe($refund->reason) ?></td>
            <td class="right">-<?= $money($refund->payment_total ?? $refund->total ?? 0) ?></td>
        </tr>
    <?php endforeach; ?>

    <tr>
        <th colspan="5" class="right">Total devoluciones</th>
        <th class="right">-<?= $money($v5507eRefundTotal ?? 0) ?></th>
    </tr>
</table>
<?php endif; ?>

<h2>Ventas por lista de precios</h2>
    <table>
        <tr>
            <th>Lista</th>
            <th class="right">Ventas</th>
            <th class="right">Total</th>
        </tr>

        <?php foreach ($v5497fPriceListTotals as $row): ?>
            <tr>
                <td><?= $safe($row['name'] ?? 'Sin lista') ?></td>
                <td class="right"><?= number_format((float) ($row['count'] ?? 0)) ?></td>
                <td class="right"><?= $money($row['total'] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
<!-- /V5.49.7K_RESUMEN_LISTAS -->
<div class="signatures">
    <div class="signature">
        <div class="signature-line">Firma cajero</div>
    </div>

    <div class="signature">
        <div class="signature-line">Firma supervisor</div>
    </div>
</div>





</body>
</html>
