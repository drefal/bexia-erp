<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ticket cierre {{ $summary['session']['number'] ?? '' }}</title>

    <style>
        @page {
            size: 80mm auto;
            margin: 4mm;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 72mm;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
            font-size: 10.5px;
            line-height: 1.25;
        }

        body {
            margin: 0 auto;
        }

        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: 900; }
        .muted { color: #4b5563; }
        .title {
            font-size: 14px;
            font-weight: 900;
            margin: 5px 0 2px;
            letter-spacing: .02em;
        }

        .line {
            border-top: 1px dashed #111827;
            margin: 7px 0;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin: 2px 0;
        }

        .row span:first-child {
            max-width: 42mm;
        }

        .total {
            font-size: 12px;
            font-weight: 900;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
        }

        th,
        td {
            font-size: 9.5px;
            padding: 2px 0;
            vertical-align: top;
        }

        th {
            border-bottom: 1px solid #111827;
            text-align: left;
            font-weight: 900;
        }

        td.right,
        th.right {
            text-align: right;
        }

        .note {
            border: 1px dashed #9ca3af;
            padding: 5px;
            margin-top: 5px;
            font-size: 9.5px;
        }

        .sign {
            margin-top: 24px;
            border-top: 1px solid #111827;
            padding-top: 4px;
            text-align: center;
            font-size: 9.5px;
        }

        .no-print {
            margin: 12px 0;
            text-align: center;
        }

        .no-print button {
            padding: 8px 12px;
            border: 1px solid #2563eb;
            background: #2563eb;
            color: #fff;
            border-radius: 8px;
            font-weight: 900;
            cursor: pointer;
        }

        @media print {
            .no-print { display: none; }
        }
    
        /* V5.50.8C - Botón gris igual al ticket pendiente */
        .no-print button,
        .ticket-actions button,
        button {
            background: #f3f4f6 !important;
            color: #111827 !important;
            border: 1px solid #9ca3af !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            padding: 8px 12px !important;
            font-weight: 700 !important;
        }


    /* V5.51.0I - Forzar negritas en venta neta 80mm */
    .v5510i-net-bold,
    .v5510i-net-bold *,
    .v5510i-net-bold td,
    .v5510i-net-bold strong,
    .v5510i-net-bold span {
        font-weight: 900 !important;
    }

</style>
</head>
<body>

@php
    // V5.50.8C - Devoluciones en ticket cierre 80mm.
    $v5508cSessionId = null;

    if (isset($session)) {
        if (is_object($session) && isset($session->id)) {
            $v5508cSessionId = (int) $session->id;
        } elseif (is_array($session) && isset($session['id'])) {
            $v5508cSessionId = (int) $session['id'];
        }
    }

    if (! $v5508cSessionId && isset($summary) && is_array($summary)) {
        foreach (['session_id', 'pos_session_id', 'id'] as $key) {
            if (! empty($summary[$key])) {
                $v5508cSessionId = (int) $summary[$key];
                break;
            }
        }

        foreach (['session', 'pos_session'] as $key) {
            if (! $v5508cSessionId && ! empty($summary[$key])) {
                if (is_array($summary[$key]) && isset($summary[$key]['id'])) {
                    $v5508cSessionId = (int) $summary[$key]['id'];
                    break;
                }

                if (is_object($summary[$key]) && isset($summary[$key]->id)) {
                    $v5508cSessionId = (int) $summary[$key]->id;
                    break;
                }
            }
        }
    }

    $v5508cRefunds = collect();
    $v5508cRefundTotal = 0.0;
    $v5508cTicketsDevueltos = 0;
    $v5508cTotalVendido = 0.0;
    $v5508cVentaNeta = 0.0;

    try {
        if (
            $v5508cSessionId
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')
        ) {
            $v5508cRefunds = \Illuminate\Support\Facades\DB::table('pos_order_refunds as r')
                ->join('pos_orders as o', 'o.id', '=', 'r.pos_order_id')
                ->where('r.status', 'done')
                ->where(function ($q) use ($v5508cSessionId) {
                    $q->where('r.pos_session_id', $v5508cSessionId)
                        ->orWhere('o.pos_session_id', $v5508cSessionId);
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
                    'o.number as order_number',
                ])
                ->map(function ($refund) {
                    $refund->type_label = match ((string) ($refund->type ?? '')) {
                        'partial' => 'Parcial',
                        'total' => 'Total',
                        default => ucfirst((string) ($refund->type ?? 'Devolución')),
                    };

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

                    return $refund;
                });

            $v5508cRefundTotal = round((float) $v5508cRefunds->sum(function ($refund) {
                return (float) ($refund->payment_total ?? $refund->total ?? 0);
            }), 2);

            $v5508cTicketsDevueltos = $v5508cRefunds->count();

            $v5508cTotalVendido = round((float) \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('pos_session_id', $v5508cSessionId)
                ->whereIn('status', ['paid', 'returned'])
                ->sum('total'), 2);
        }

        if ($v5508cTotalVendido <= 0 && isset($summary) && is_array($summary)) {
            foreach (['total_sold', 'total_vendido', 'sales_total', 'total_sales'] as $key) {
                if (isset($summary[$key])) {
                    $v5508cTotalVendido = round((float) $summary[$key], 2);
                    break;
                }
            }

            if ($v5508cTotalVendido <= 0 && isset($summary['totals']) && is_array($summary['totals'])) {
                foreach (['total_sold', 'total_vendido', 'sales_total', 'total_sales'] as $key) {
                    if (isset($summary['totals'][$key])) {
                        $v5508cTotalVendido = round((float) $summary['totals'][$key], 2);
                        break;
                    }
                }
            }
        }

        $v5508cVentaNeta = round($v5508cTotalVendido - $v5508cRefundTotal, 2);
    } catch (\Throwable $e) {
        $v5508cRefunds = collect();
        $v5508cRefundTotal = 0.0;
        $v5508cTicketsDevueltos = 0;
        $v5508cVentaNeta = $v5508cTotalVendido;
    }
@endphp

@php
    // V5.50.8D - Corrección venta neta cierre 80mm.
    // Usa el mismo total vendido que muestra el ticket, no el cálculo parcial anterior.
    try {
        $v5508dTotalVendido = null;

        if (isset($summary) && is_array($summary)) {
            foreach ([
                'total_sold',
                'total_vendido',
                'sales_total',
                'total_sales',
                'sold_total',
                'total',
            ] as $key) {
                if ($v5508dTotalVendido === null && isset($summary[$key]) && is_numeric($summary[$key])) {
                    $v5508dTotalVendido = (float) $summary[$key];
                }
            }

            if ($v5508dTotalVendido === null && isset($summary['totals']) && is_array($summary['totals'])) {
                foreach ([
                    'total_sold',
                    'total_vendido',
                    'sales_total',
                    'total_sales',
                    'sold_total',
                    'total',
                ] as $key) {
                    if ($v5508dTotalVendido === null && isset($summary['totals'][$key]) && is_numeric($summary['totals'][$key])) {
                        $v5508dTotalVendido = (float) $summary['totals'][$key];
                    }
                }
            }
        }

        // Fallback: si no encontramos el total en summary, usa el total ya calculado.
        if ($v5508dTotalVendido !== null) {
            $v5508cTotalVendido = round($v5508dTotalVendido, 2);
        }

        $v5508cVentaNeta = round((float) ($v5508cTotalVendido ?? 0) - (float) ($v5508cRefundTotal ?? 0), 2);
    } catch (\Throwable $e) {
        //
    }
@endphp

@php
    // V5.50.8E - Venta neta correcta cierre 80mm.
    // Usa el total cobrado real de la sesión y resta devoluciones.
    try {
        $v5508eSessionId = $v5508cSessionId ?? null;

        if (! $v5508eSessionId && isset($session)) {
            if (is_object($session) && isset($session->id)) {
                $v5508eSessionId = (int) $session->id;
            } elseif (is_array($session) && isset($session['id'])) {
                $v5508eSessionId = (int) $session['id'];
            }
        }

        $v5508eTotalVendido = null;

        if (
            $v5508eSessionId
            && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_payments')
        ) {
            $v5508eTotalVendido = round((float) \Illuminate\Support\Facades\DB::table('pos_order_payments as p')
                ->join('pos_orders as o', 'o.id', '=', 'p.pos_order_id')
                ->where('o.pos_session_id', $v5508eSessionId)
                ->whereIn('o.status', ['paid', 'returned'])
                ->sum('p.amount'), 2);
        }

        if ($v5508eTotalVendido === null || $v5508eTotalVendido <= 0) {
            if (isset($summary) && is_array($summary)) {
                foreach ([
                    'total_sold',
                    'total_vendido',
                    'sales_total',
                    'total_sales',
                    'sold_total',
                    'total',
                ] as $key) {
                    if (isset($summary[$key]) && is_numeric($summary[$key])) {
                        $v5508eTotalVendido = round((float) $summary[$key], 2);
                        break;
                    }
                }

                if (($v5508eTotalVendido === null || $v5508eTotalVendido <= 0) && isset($summary['totals']) && is_array($summary['totals'])) {
                    foreach ([
                        'total_sold',
                        'total_vendido',
                        'sales_total',
                        'total_sales',
                        'sold_total',
                        'total',
                    ] as $key) {
                        if (isset($summary['totals'][$key]) && is_numeric($summary['totals'][$key])) {
                            $v5508eTotalVendido = round((float) $summary['totals'][$key], 2);
                            break;
                        }
                    }
                }
            }
        }

        if ($v5508eTotalVendido !== null && $v5508eTotalVendido > 0) {
            $v5508cTotalVendido = $v5508eTotalVendido;
            $v5508cVentaNeta = round($v5508cTotalVendido - (float) ($v5508cRefundTotal ?? 0), 2);
        }
    } catch (\Throwable $e) {
        //
    }
@endphp






@php
    $totals = $summary['totals'] ?? [];
    $sessionData = $summary['session'] ?? [];
    $payments = $summary['payments_by_method'] ?? [];
    $movements = $summary['cash_movements'] ?? [];
    $sellers = $summary['sales_by_seller'] ?? [];
    $openingCash = $summary['opening_cash'] ?? [];
    $openingCashCount = $openingCash['cash_count'] ?? [];
    $closingCashCount = $sessionData['closing_cash_count'] ?? ($closePayload['cash_count'] ?? []);
    $difference = (float) ($sessionData['closing_difference'] ?? ($closePayload['closing_difference'] ?? 0));
    $closingNote = (string) ($sessionData['closing_note'] ?? ($closePayload['closing_note'] ?? ''));
@endphp

<?php
    // V5.51.0Q - Totales cierre definitivos para ticket.
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


@php
    // V5.51.0L - Venta total neta desde Métodos de pago.
    try {
        $v5510lTotalMetodosPago = 0.0;

        if (isset($payments)) {
            foreach ($payments as $v5510lPaymentRow) {
                if (is_array($v5510lPaymentRow)) {
                    $v5510lTotalMetodosPago += (float) ($v5510lPaymentRow['total'] ?? 0);
                } elseif (is_object($v5510lPaymentRow)) {
                    $v5510lTotalMetodosPago += (float) ($v5510lPaymentRow->total ?? 0);
                }
            }
        }

        if ($v5510lTotalMetodosPago <= 0 && isset($totals) && is_array($totals)) {
            $v5510lTotalMetodosPago = (float) ($totals['paid_total'] ?? 0);
        }

        $v5510lTotalDevuelto = round((float) (
            $v5510eRefundTotal
            ?? $v5508cRefundTotal
            ?? $v5510kRefundTotal
            ?? 0
        ), 2);

        $v5510lVentaTotalNeta = round($v5510lTotalMetodosPago - $v5510lTotalDevuelto, 2);

        // Forzar todas las variables viejas que todavía se usan abajo.
        $v5508cTotalVendido = $v5510lVentaTotalNeta;
        $v5508cVentaNeta = $v5510lVentaTotalNeta;
        $v5510eTotalNet = $v5510lVentaTotalNeta;
        $v5510kVentaTotalNeta = $v5510lVentaTotalNeta;
        $v5510cVentaNeta = $v5510lVentaTotalNeta;
        $v5507eNetSalesTotal = $v5510lVentaTotalNeta;
    } catch (\Throwable $e) {
        //
    }
@endphp



@php
    // V5.51.0E - Resumen neto por métodos para ticket cierre 80mm.
    try {
        $v5510eSessionId = null;

        if (isset($session) && is_object($session) && isset($session->id)) {
            $v5510eSessionId = (int) $session->id;
        }

        if (! $v5510eSessionId && isset($sessionData) && is_array($sessionData) && ! empty($sessionData['id'])) {
            $v5510eSessionId = (int) $sessionData['id'];
        }

        if (! $v5510eSessionId && isset($summary) && is_array($summary)) {
            foreach (['session_id', 'pos_session_id'] as $v5510eKey) {
                if (! empty($summary[$v5510eKey])) {
                    $v5510eSessionId = (int) $summary[$v5510eKey];
                    break;
                }
            }

            if (! $v5510eSessionId && isset($summary['session']) && is_array($summary['session']) && ! empty($summary['session']['id'])) {
                $v5510eSessionId = (int) $summary['session']['id'];
            }
        }

        $v5510eOpeningCash = round((float) ($totals['opening_cash_amount'] ?? ($sessionData['opening_amount'] ?? 0)), 2);
        $v5510eCashIn = round((float) ($totals['cash_in_total'] ?? 0), 2);
        $v5510eCashOut = round((float) ($totals['cash_out_total'] ?? 0), 2);

        $v5510eCashGross = round((float) ($totals['cash_payments_total'] ?? 0), 2);
        $v5510eGrossTotal = round((float) ($totals['paid_total'] ?? 0), 2);
        $v5510eRefundCash = 0.0;
        $v5510eRefundTotal = 0.0;

        if (
            $v5510eSessionId
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_payments')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')
        ) {
            $v5510eCashGrossDb = \Illuminate\Support\Facades\DB::table('pos_order_payments as p')
                ->join('pos_orders as o', 'o.id', '=', 'p.pos_order_id')
                ->where('o.pos_session_id', $v5510eSessionId)
                ->whereIn('o.status', ['paid', 'returned'])
                ->where(function ($q) {
                    $q->whereRaw('LOWER(COALESCE(p.payment_label, "")) like ?', ['%efectivo%'])
                        ->orWhereRaw('LOWER(COALESCE(p.payment_label, "")) like ?', ['%cash%']);
                })
                ->sum('p.amount');

            if ((float) $v5510eCashGrossDb > 0) {
                $v5510eCashGross = round((float) $v5510eCashGrossDb, 2);
            }

            $v5510eGrossTotalDb = \Illuminate\Support\Facades\DB::table('pos_order_payments as p')
                ->join('pos_orders as o', 'o.id', '=', 'p.pos_order_id')
                ->where('o.pos_session_id', $v5510eSessionId)
                ->whereIn('o.status', ['paid', 'returned'])
                ->sum('p.amount');

            if ((float) $v5510eGrossTotalDb > 0) {
                $v5510eGrossTotal = round((float) $v5510eGrossTotalDb, 2);
            }
        }

        if (
            $v5510eSessionId
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_refund_payments')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')
        ) {
            $v5510eRefundCash = round((float) \Illuminate\Support\Facades\DB::table('pos_order_refund_payments as rp')
                ->join('pos_order_refunds as r', 'r.id', '=', 'rp.pos_order_refund_id')
                ->join('pos_orders as o', 'o.id', '=', 'r.pos_order_id')
                ->where('r.status', 'done')
                ->where(function ($q) use ($v5510eSessionId) {
                    $q->where('r.pos_session_id', $v5510eSessionId)
                        ->orWhere('o.pos_session_id', $v5510eSessionId);
                })
                ->where(function ($q) {
                    $q->whereRaw('LOWER(COALESCE(rp.payment_label, "")) like ?', ['%efectivo%'])
                        ->orWhereRaw('LOWER(COALESCE(rp.payment_label, "")) like ?', ['%cash%']);
                })
                ->sum('rp.amount'), 2);

            $v5510eRefundTotal = round((float) \Illuminate\Support\Facades\DB::table('pos_order_refund_payments as rp')
                ->join('pos_order_refunds as r', 'r.id', '=', 'rp.pos_order_refund_id')
                ->join('pos_orders as o', 'o.id', '=', 'r.pos_order_id')
                ->where('r.status', 'done')
                ->where(function ($q) use ($v5510eSessionId) {
                    $q->where('r.pos_session_id', $v5510eSessionId)
                        ->orWhere('o.pos_session_id', $v5510eSessionId);
                })
                ->sum('rp.amount'), 2);
        }

        if (
            $v5510eRefundTotal <= 0
            && $v5510eSessionId
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')
        ) {
            $v5510eRefundTotal = round((float) \Illuminate\Support\Facades\DB::table('pos_order_refunds as r')
                ->join('pos_orders as o', 'o.id', '=', 'r.pos_order_id')
                ->where('r.status', 'done')
                ->where(function ($q) use ($v5510eSessionId) {
                    $q->where('r.pos_session_id', $v5510eSessionId)
                        ->orWhere('o.pos_session_id', $v5510eSessionId);
                })
                ->sum('r.payment_total'), 2);

            $v5510eRefundCash = $v5510eRefundTotal;
        }

        $v5510eCashNet = round($v5510eCashGross - $v5510eRefundCash, 2);
        $v5510eOtherMethodsNet = round(($v5510eGrossTotal - $v5510eCashGross) - ($v5510eRefundTotal - $v5510eRefundCash), 2);
        $v5510eTotalNet = round($v5510eCashNet + $v5510eOtherMethodsNet, 2);
        $v5510eExpectedCash = round($v5510eOpeningCash + $v5510eCashNet + $v5510eCashIn - $v5510eCashOut, 2);
    } catch (\Throwable $e) {
        $v5510eCashGross = (float) ($totals['cash_payments_total'] ?? 0);
        $v5510eRefundCash = 0.0;
        $v5510eRefundTotal = 0.0;
        $v5510eCashNet = $v5510eCashGross;
        $v5510eOtherMethodsNet = 0.0;
        $v5510eTotalNet = (float) ($totals['paid_total'] ?? 0);
        $v5510eExpectedCash = (float) ($totals['expected_cash'] ?? 0);
    }
@endphp



<div class="center">
    @if(! empty($summary['company']['logo_url']))
        <img src="{{ $summary['company']['logo_url'] }}" style="max-height:38px;max-width:130px;margin-bottom:3px;">
    @endif

    <div class="title">CIERRE DE CAJA</div>
    <div class="bold">{{ $summary['company']['name'] ?? 'Bexia ERP' }}</div>
    <div>{{ $summary['pos']['name'] ?? 'PDV' }}</div>
    <div class="muted">{{ $sessionData['number'] ?? '' }}</div>
</div>

<div class="line"></div>

<div class="row"><span>Cajero</span><strong>{{ $summary['cashier']['name'] ?? 'Sin cajero' }}</strong></div>
<div class="row"><span>Apertura</span><strong>{{ $sessionData['opened_at'] ?? '' }}</strong></div>
<div class="row"><span>Cierre</span><strong>{{ $sessionData['closed_at'] ?? 'Aún abierta' }}</strong></div>
<div class="row"><span>Estado</span><strong>{{ $sessionData['status_label'] ?? $sessionData['status'] ?? '' }}</strong></div>
<div class="row"><span>Generado</span><strong>{{ $summary['generated_at'] ?? now()->toDateTimeString() }}</strong></div>

<div class="line"></div>

<div class="row"><span>Fondo apertura</span><strong>${{ number_format($totals['opening_cash_amount'] ?? ($sessionData['opening_amount'] ?? 0), 2) }}</strong></div>
<div class="row"><span>Ventas efectivo</span><strong>${{ number_format($v5510eCashGross ?? ($totals['cash_payments_total'] ?? 0), 2) }}</strong></div>
@if(($v5510eRefundCash ?? 0) > 0)
    <div class="row" id="v5510e-refund-cash-row"><span>Devoluciones efectivo</span><strong>-${{ number_format($v5510eRefundCash ?? 0, 2) }}</strong></div>
@endif
<div class="row"><span>Entradas efectivo</span><strong>${{ number_format($totals['cash_in_total'] ?? 0, 2) }}</strong></div>
<div class="row"><span>Retiros efectivo</span><strong>-${{ number_format($totals['cash_out_total'] ?? 0, 2) }}</strong></div>
<div class="row total"><span>Efectivo esperado</span><strong>${{ number_format($v5510eExpectedCash ?? ($totals['expected_cash'] ?? 0), 2) }}</strong></div>
<div class="row"><span>Efectivo contado</span><strong>${{ number_format($sessionData['closing_amount'] ?? 0, 2) }}</strong></div>
<div class="row"><span>Diferencia</span><strong>${{ number_format($difference, 2) }}</strong></div>

@if($closingNote !== '')
    <div class="note">
        <strong>Nota de cierre:</strong><br>
        {{ $closingNote }}
    </div>
@endif

<div class="line"></div>

<div class="bold">Métodos de pago</div>
<table>
    <thead>
        <tr>
            <th>Método</th>
            <th class="right">Ops.</th>
            <th class="right">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($payments as $row)
            <tr>
                <td>{{ $row['method'] ?? 'Sin método' }}</td>
                <td class="right">{{ number_format($row['count'] ?? $row['payments_count'] ?? 0) }}</td>
                <td class="right">${{ number_format($row['total'] ?? 0, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="3">Sin pagos</td></tr>
        @endforelse
    </tbody>
</table>


@if(($v5510eRefundTotal ?? 0) > 0)
    <div class="line"></div>
    <div class="bold">Resumen neto</div>
    <table id="v5510e-net-methods-summary">
        <tbody>
            <tr id="v5510h-venta-neta-efectivo-bold" class="v5510i-net-bold">
                <td style="font-weight:900 !important;"><strong>Venta neta efectivo</strong></td>
                <td class="right"><strong>${{ number_format($v5510eCashNet ?? 0, 2) }}</strong></td>
            </tr>
            <tr>
                <td>Otros métodos neto</td>
                <td class="right">${{ number_format($v5510eOtherMethodsNet ?? 0, 2) }}</td>
            </tr>
            <tr class="total" style="font-weight:900 !important;">
                <td style="font-weight:900 !important;"><strong>Venta total neta</strong></td>
                <td class="right" style="font-weight:900 !important;"><strong>${{ number_format($v5510qVentaNeta ?? 0, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
@endif

@if(! empty($movements))
    <div class="line"></div>
    <div class="bold">Movimientos efectivo</div>
    <table>
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Motivo</th>
                <th class="right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($movements as $m)
                <tr>
                    <td>{{ $m['type_label'] ?? $m['type'] ?? '' }}</td>
                    <td>{{ $m['reason'] ?? '' }}</td>
                    <td class="right">${{ number_format($m['amount'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if(! empty($closingCashCount))
    <div class="line"></div>
    <div class="bold">Conteo cierre</div>
    <table>
        <thead>
            <tr>
                <th>Denom.</th>
                <th class="right">Cant.</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($closingCashCount as $row)
                @if(($row['quantity'] ?? 0) > 0)
                    <tr>
                        <td>${{ number_format($row['value'] ?? 0, 2) }}</td>
                        <td class="right">{{ $row['quantity'] ?? 0 }}</td>
                        <td class="right">${{ number_format($row['total'] ?? 0, 2) }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
@endif

<div class="line"></div>


@php
    // V5.51.0K - Venta total neta definitiva cierre 80mm.
    try {
        $v5510kSessionId = null;

        if (isset($session) && is_object($session) && isset($session->id)) {
            $v5510kSessionId = (int) $session->id;
        }

        if (! $v5510kSessionId && isset($sessionData) && is_array($sessionData) && ! empty($sessionData['id'])) {
            $v5510kSessionId = (int) $sessionData['id'];
        }

        if (! $v5510kSessionId) {
            $routeSession = request()->route('session');

            if (is_object($routeSession) && isset($routeSession->id)) {
                $v5510kSessionId = (int) $routeSession->id;
            } elseif (is_numeric($routeSession)) {
                $v5510kSessionId = (int) $routeSession;
            }
        }

        $v5510kGrossPaidTotal = round((float) ($totals['paid_total'] ?? 0), 2);
        $v5510kRefundTotal = round((float) ($v5510eRefundTotal ?? $v5508cRefundTotal ?? 0), 2);

        if (
            $v5510kSessionId
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_payments')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')
        ) {
            $v5510kGrossPaidTotalDb = \Illuminate\Support\Facades\DB::table('pos_order_payments as p')
                ->join('pos_orders as o', 'o.id', '=', 'p.pos_order_id')
                ->where('o.pos_session_id', $v5510kSessionId)
                ->whereIn('o.status', ['paid', 'returned'])
                ->sum('p.amount');

            if ((float) $v5510kGrossPaidTotalDb > 0) {
                $v5510kGrossPaidTotal = round((float) $v5510kGrossPaidTotalDb, 2);
            }
        }

        if (
            $v5510kSessionId
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_refund_payments')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')
        ) {
            $v5510kRefundTotalDb = \Illuminate\Support\Facades\DB::table('pos_order_refund_payments as rp')
                ->join('pos_order_refunds as r', 'r.id', '=', 'rp.pos_order_refund_id')
                ->join('pos_orders as o', 'o.id', '=', 'r.pos_order_id')
                ->where('r.status', 'done')
                ->where(function ($q) use ($v5510kSessionId) {
                    $q->where('r.pos_session_id', $v5510kSessionId)
                        ->orWhere('o.pos_session_id', $v5510kSessionId);
                })
                ->sum('rp.amount');

            if ((float) $v5510kRefundTotalDb > 0) {
                $v5510kRefundTotal = round((float) $v5510kRefundTotalDb, 2);
            }
        }

        $v5510kVentaTotalNeta = round($v5510kGrossPaidTotal - $v5510kRefundTotal, 2);

        // Sobrescribir variables viejas que todavía se imprimen en el ticket.
        $v5508cVentaNeta = $v5510kVentaTotalNeta;
        $v5510eTotalNet = $v5510kVentaTotalNeta;
        $v5510cVentaNeta = $v5510kVentaTotalNeta;
        $v5507eNetSalesTotal = $v5510kVentaTotalNeta;
    } catch (\Throwable $e) {
        //
    }
@endphp



@php
    // V5.51.0M - Total vendido bruto y venta neta separados.
    try {
        $v5510mTotalVendidoBruto = 0.0;

        if (isset($payments)) {
            foreach ($payments as $v5510mPaymentRow) {
                if (is_array($v5510mPaymentRow)) {
                    $v5510mTotalVendidoBruto += (float) ($v5510mPaymentRow['total'] ?? 0);
                } elseif (is_object($v5510mPaymentRow)) {
                    $v5510mTotalVendidoBruto += (float) ($v5510mPaymentRow->total ?? 0);
                }
            }
        }

        if ($v5510mTotalVendidoBruto <= 0 && isset($totals) && is_array($totals)) {
            $v5510mTotalVendidoBruto = (float) ($totals['paid_total'] ?? 0);
        }

        $v5510mTotalVendidoBruto = round($v5510mTotalVendidoBruto, 2);

        $v5510mTotalDevuelto = round((float) (
            $v5510eRefundTotal
            ?? $v5510lTotalDevuelto
            ?? $v5510kRefundTotal
            ?? $v5508cRefundTotal
            ?? 0
        ), 2);

        $v5510mVentaNeta = round($v5510mTotalVendidoBruto - $v5510mTotalDevuelto, 2);

        // Mantener compatibilidad con variables previas, pero ya separadas.
        $v5510lTotalMetodosPago = $v5510mTotalVendidoBruto;
        $v5510lVentaTotalNeta = $v5510mVentaNeta;
        $v5510kVentaTotalNeta = $v5510mVentaNeta;
        $v5510eTotalNet = $v5510mVentaNeta;
        $v5508cVentaNeta = $v5510mVentaNeta;
    } catch (\Throwable $e) {
        $v5510mTotalVendidoBruto = (float) ($totals['paid_total'] ?? 0);
        $v5510mTotalDevuelto = (float) ($v5510eRefundTotal ?? $v5508cRefundTotal ?? 0);
        $v5510mVentaNeta = round($v5510mTotalVendidoBruto - $v5510mTotalDevuelto, 2);
    }
@endphp


<div class="row total"><span>Total vendido</span><strong>${{ number_format($v5510qTotalVendidoBruto ?? 0, 2) }}</strong></div>
<div class="row"><span>Tickets cobrados</span><strong>{{ number_format($totals['paid_tickets'] ?? 0) }}</strong></div>
<div class="row"><span>Pendientes sesión</span><strong>{{ number_format($totals['pending_tickets_created_in_session'] ?? 0) }}</strong></div>
<div class="row"><span>Reservas activas</span><strong>{{ number_format($totals['active_reservations'] ?? 0) }}</strong></div>

@if(! empty($sellers))
    
@php
    // V5.50.8G - Totales reales del cierre 80mm desde pos_orders.
    try {
        $v5508gSessionId = $v5508cSessionId ?? null;

        if (! $v5508gSessionId && isset($session)) {
            if (is_object($session) && isset($session->id)) {
                $v5508gSessionId = (int) $session->id;
            } elseif (is_array($session) && isset($session['id'])) {
                $v5508gSessionId = (int) $session['id'];
            }
        }

        $v5508gTotalVendido = 0.0;
        $v5508gTicketsCobrados = 0;
        $v5508gTicketsPendientes = 0;
        $v5508gReservasActivas = 0;

        if ($v5508gSessionId && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')) {
            $v5508gTotalVendido = round((float) \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('pos_session_id', $v5508gSessionId)
                ->whereIn('status', ['paid', 'returned'])
                ->sum('total'), 2);

            $v5508gTicketsCobrados = (int) \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('pos_session_id', $v5508gSessionId)
                ->whereIn('status', ['paid', 'returned'])
                ->count();

            $v5508gTicketsPendientes = (int) \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('pos_session_id', $v5508gSessionId)
                ->where('status', 'pending_payment')
                ->count();

            $v5508gReservasActivas = (int) \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('pos_session_id', $v5508gSessionId)
                ->where('status', 'reserved')
                ->count();

            $v5508cTotalVendido = $v5508gTotalVendido;
            $v5508cVentaNeta = round($v5508gTotalVendido - (float) ($v5508cRefundTotal ?? 0), 2);
        }
    } catch (\Throwable $e) {
        //
    }
@endphp

@if(isset($v5508cRefundTotal) && (float) $v5508cRefundTotal > 0)
    <table id="v5508e-close-refund-summary">
        <tr>
            <td>Total devuelto</td>
            <td class="right">-${{ number_format((float) $v5508cRefundTotal, 2) }}</td>
        </tr>
        <tr>
            <td>Tickets devueltos</td>
            <td class="right">{{ number_format((int) $v5508cTicketsDevueltos) }}</td>
        </tr>
        <tr class="total" style="font-weight:900 !important;">
                <td style="font-weight:900 !important;"><strong>Venta neta</strong></td>
                <td class="right" style="font-weight:900 !important;"><strong>${{ number_format($v5510qVentaNeta ?? 0, 2) }}</strong></td>
            </tr>
    </table>
@endif






<div id="v5508e-vendors-separator" style="border-top:1px solid #000; margin:2mm 0 1.5mm 0;"></div>

<div class="bold">Vendedores</div>
    <table>
        <thead>
            <tr>
                <th>Vendedor</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sellers as $seller)
                <tr>
                    <td>{{ $seller['seller_name'] ?? 'Sin vendedor' }}</td>
                    <td class="right">${{ number_format($seller['total'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="sign">Firma cajero</div>
<div class="sign">Firma supervisor</div>

<div class="center muted" style="margin-top:10px;">
    Fin del corte
</div>

<div class="no-print">
    <button onclick="window.print()">Imprimir ticket</button>
</div>

<script>
    window.addEventListener('load', function () {
        setTimeout(function () {
            window.print();
        }, 350);
    });
</script>
</body>
</html>
