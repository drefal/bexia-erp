<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $order->number ?? 'Ticket pagado' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        @page { size: 80mm auto; margin: 4mm; }
        * { box-sizing: border-box; }

        body {
            width: 72mm;
            margin: 0 auto;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #000;
            background: #fff;
        }

        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: 900; }

        .ticket-logo {
            display: block;
            max-width: 46mm;
            max-height: 18mm;
            object-fit: contain;
            margin: 0 auto 2mm auto;
        }

        .subtitle {
            font-size: 13px;
            font-weight: 900;
            margin-bottom: 1mm;
        }

        .copy-label {
            font-size: 10px;
            font-weight: 900;
            margin-top: 1mm;
        }

        .paid-box {
            border: 2px solid #000;
            padding: 2.5mm 1.5mm;
            margin: 2mm 0;
            text-align: center;
            font-weight: 900;
            font-size: 14px;
        }

        .refund-box {
            border: 2px solid #000;
            padding: 2.5mm 1.5mm;
            margin: 2mm 0;
            text-align: center;
            font-weight: 900;
            font-size: 14px;
        }

        .warning-box {
            border: 1px dashed #000;
            padding: 2mm 1.5mm;
            margin: 2mm 0;
            text-align: center;
            font-weight: 900;
            font-size: 11px;
        }

        .line {
            border-top: 1px dashed #000;
            margin: 2mm 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            vertical-align: top;
            padding: 1mm 0;
        }

        .item-name {
            font-weight: 700;
            padding-top: 1.5mm;
        }

        .total {
            font-size: 16px;
            font-weight: 900;
        }

        .small {
            font-size: 10px;
        }

        .qr-box {
            text-align: center;
            margin: 3mm 0 1mm 0;
            font-size: 10px;
            font-weight: 900;
        }

        .qr-box img {
            width: 26mm;
            height: 26mm;
            display: block;
            margin: 0 auto 1mm auto;
        }

        .footer {
            margin-top: 3mm;
            font-size: 10px;
            text-align: center;
        }

        .no-print {
            margin-top: 12px;
            text-align: center;
        }

        .no-print button {
            padding: 8px 12px;
            font-weight: 700;
            margin: 0 3px;
        }

        @media print {
            .no-print { display: none; }
        }
    
        /* V5.50.8C - Botones grises igual al ticket pendiente */
        .no-print button,
        button {
            background: #f3f4f6 !important;
            color: #111827 !important;
            border: 1px solid #9ca3af !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            padding: 8px 12px !important;
            font-weight: 700 !important;
        }


        /* V5.51.0A - Bloque devolución ticket 80mm */
        .refund-status-box {
            border: 2px solid #000;
            padding: 2mm 1.5mm;
            margin: 2mm 0;
            text-align: center;
            font-weight: 900;
            font-size: 12px;
        }

        .refund-detail-box {
            border: 1px dashed #000;
            padding: 1.5mm;
            margin: 2mm 0;
            font-size: 10px;
        }

</style>
</head>
<body>
@php
    // V5.50.8B - Ticket 80mm pagado/devuelto limpio.
    $ticketMetadata = is_array($metadata ?? null) ? $metadata : [];

    if (empty($ticketMetadata) && isset($order) && ! empty($order->metadata)) {
        $decoded = json_decode((string) $order->metadata, true);
        $ticketMetadata = is_array($decoded) ? $decoded : [];
    }

    $v5496nPriceListName = trim((string) (
        ($priceListName ?? null)
        ?? ($order->price_list_name ?? null)
        ?? ($ticketMetadata['price_list_name'] ?? null)
        ?? ($ticketMetadata['selected_price_list_name'] ?? null)
        ?? ''
    ));

    $discount = $ticketMetadata['discount'] ?? null;
    $discountAmount = is_array($discount) ? (float) ($discount['amount'] ?? 0) : 0;
    $discountUser = is_array($discount) ? trim((string) ($discount['user_name'] ?? '')) : '';
    $discountType = is_array($discount) ? (string) ($discount['type'] ?? '') : '';
    $discountValue = is_array($discount) ? (float) ($discount['value'] ?? 0) : 0;
    $orderNote = trim((string) ($ticketMetadata['order_note'] ?? ''));

    $ticketStatus = (string) ($order->status ?? '');
    $isReturned = $ticketStatus === 'returned';

    $refund = null;
    $refundTypeLabel = null;
    $refundPayments = 'No especificado';

    try {
        if (
            isset($order)
            && isset($order->id)
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')
        ) {
            $refund = \Illuminate\Support\Facades\DB::table('pos_order_refunds')
                ->where('pos_order_id', (int) $order->id)
                ->where('status', 'done')
                ->orderByDesc('id')
                ->first();

            if ($refund) {
                $refundTypeLabel = match ((string) ($refund->type ?? '')) {
                    'partial' => 'Parcial',
                    'total' => 'Total',
                    default => ucfirst((string) ($refund->type ?? 'Devolución')),
                };

                if (\Illuminate\Support\Facades\Schema::hasTable('pos_order_refund_payments')) {
                    $refundPayments = \Illuminate\Support\Facades\DB::table('pos_order_refund_payments')
                        ->where('pos_order_refund_id', $refund->id)
                        ->pluck('payment_label')
                        ->filter()
                        ->unique()
                        ->values()
                        ->implode(', ');

                    if ($refundPayments === '') {
                        $refundPayments = 'No especificado';
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        $refund = null;
        $refundTypeLabel = null;
        $refundPayments = 'No especificado';
    }

    // V5.51.0A - Estado de devolución en ticket 80mm.
    $v5510aRefund = null;
    $v5510aRefundType = null;
    $v5510aRefundTypeLabel = null;
    $v5510aRefundTotal = 0.0;
    $v5510aRefundPayments = 'No especificado';
    $v5510aHasRefund = false;
    $v5510aIsPartialRefund = false;
    $v5510aIsTotalRefund = false;

    try {
        if (
            isset($order)
            && isset($order->id)
            && \Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')
        ) {
            $v5510aRefund = \Illuminate\Support\Facades\DB::table('pos_order_refunds')
                ->where('pos_order_id', (int) $order->id)
                ->where('status', 'done')
                ->orderByDesc('id')
                ->first();

            if ($v5510aRefund) {
                $v5510aHasRefund = true;
                $v5510aRefundType = (string) ($v5510aRefund->type ?? '');
                $v5510aIsPartialRefund = $v5510aRefundType === 'partial';
                $v5510aIsTotalRefund = $v5510aRefundType === 'total';
                $v5510aRefundTypeLabel = $v5510aIsPartialRefund ? 'Parcial' : ($v5510aIsTotalRefund ? 'Total' : 'Devolución');
                $v5510aRefundTotal = (float) ($v5510aRefund->payment_total ?? $v5510aRefund->total ?? 0);

                if (\Illuminate\Support\Facades\Schema::hasTable('pos_order_refund_payments')) {
                    $v5510aRefundPayments = \Illuminate\Support\Facades\DB::table('pos_order_refund_payments')
                        ->where('pos_order_refund_id', $v5510aRefund->id)
                        ->pluck('payment_label')
                        ->filter()
                        ->unique()
                        ->values()
                        ->implode(', ');

                    if ($v5510aRefundPayments === '') {
                        $v5510aRefundPayments = 'No especificado';
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        $v5510aRefund = null;
        $v5510aHasRefund = false;
        $v5510aIsPartialRefund = false;
        $v5510aIsTotalRefund = false;
        $v5510aRefundTotal = 0.0;
    }

@endphp

    <div class="center">
        @if(!empty($logoUrl))
            <img class="ticket-logo" src="{{ $logoUrl }}" alt="Logo PDV">
        @endif

        <div class="subtitle">TICKET DE VENTA</div>
        <div class="copy-label">
            {{ ($v5510aHasRefund ?? false) ? (($v5510aIsPartialRefund ?? false) ? 'COMPROBANTE CON DEVOLUCIÓN' : 'COMPROBANTE DEVUELTO') : 'COMPROBANTE DE PAGO' }}
        </div>
    </div>

    @if($v5510aIsTotalRefund ?? false)
        <div class="refund-box">DEVUELTO</div>
        <div class="warning-box">ESTE TICKET FUE DEVUELTO</div>
    @elseif($v5510aIsPartialRefund ?? false)
        <div class="paid-box">PAGADO</div>
        <div class="refund-status-box">DEVOLUCIÓN PARCIAL REGISTRADA</div>
    @else
        <div class="paid-box">PAGADO</div>
    @endif

    <div class="line"></div>

    <table>
        <tr>
            <td>Folio:</td>
            <td class="right bold">{{ $order->number }}</td>
        </tr>
        <tr>
            <td>Fecha:</td>
            <td class="right">{{ $printedAt->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td>Cliente:</td>
            <td class="right">{{ $customerName ?? 'Público en General' }}</td>
        </tr>
        @if($v5496nPriceListName !== '')
            <tr>
                <td>Lista:</td>
                <td class="right">{{ $v5496nPriceListName }}</td>
            </tr>
        @endif
        <tr>
            <td>Vendedor:</td>
            <td class="right">{{ $sellerName }}</td>
        </tr>
        <tr>
            <td>Estado:</td>
            <td class="right bold">{{ $isReturned ? 'Devuelto' : 'Pagado' }}</td>
        </tr>
        @if(!empty($order->paid_at))
            <tr>
                <td>Pagado:</td>
                <td class="right">{{ \Carbon\Carbon::parse($order->paid_at)->format('d/m/Y H:i') }}</td>
            </tr>
        @endif
    </table>

    @if($orderNote !== '')
        <div class="line"></div>
        <div class="small"><strong>Nota:</strong> {{ $orderNote }}</div>
    @endif

    <div class="line"></div>

    <table>
        <thead>
            <tr>
                <td><strong>Cant.</strong></td>
                <td><strong>Precio</strong></td>
                <td class="right"><strong>Importe</strong></td>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $line)
                @php
                    $qty = (float) ($line->quantity ?? 0);
                    $price = (float) ($line->unit_price ?? 0);
                    $lineTotal = (float) ($line->total ?? ($qty * $price));
                @endphp

                <tr>
                    <td colspan="3" class="item-name">
                        {{ $line->product_name ?? 'Producto' }}
                    </td>
                </tr>
                <tr>
                    <td>{{ number_format($qty, 2) }}</td>
                    <td>${{ number_format($price, 2) }}</td>
                    <td class="right">${{ number_format($lineTotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="line"></div>

    <table>
        @if($discountAmount > 0)
            <tr>
                <td>Descuento:</td>
                <td class="right">-${{ number_format($discountAmount, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2" class="small">
                    Aplicado por: {{ $discountUser !== '' ? $discountUser : 'Usuario' }}
                    @if($discountType === 'percent')
                        ({{ rtrim(rtrim(number_format($discountValue, 2), '0'), '.') }}%)
                    @endif
                </td>
            </tr>
        @endif
        <tr>
            <td class="total">TOTAL PAGADO</td>
            <td class="right total">${{ number_format((float) ($order->total ?? 0), 2) }}</td>
        </tr>
    </table>

    <div class="line"></div>

    <div class="bold">Formas de pago</div>
    <table>
        @foreach($payments as $payment)
            <tr>
                <td>{{ $payment->payment_label ?? 'Pago' }}</td>
                <td class="right">${{ number_format((float) ($payment->amount ?? 0), 2) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="line"></div>

    
    @if($v5510aHasRefund ?? false)
        <div class="line"></div>

        <div class="refund-status-box">
            DEVOLUCIÓN {{ strtoupper($v5510aRefundTypeLabel ?? '') }}
        </div>

        <div class="refund-detail-box">
            <table>
                <tr>
                    <td>Folio devolución:</td>
                    <td class="right bold">{{ $v5510aRefund->number ?? '—' }}</td>
                </tr>
                <tr>
                    <td>Tipo:</td>
                    <td class="right">{{ $v5510aRefundTypeLabel ?? '—' }}</td>
                </tr>
                <tr>
                    <td>Método:</td>
                    <td class="right">{{ $v5510aRefundPayments ?? 'No especificado' }}</td>
                </tr>
                <tr>
                    <td>Total devuelto:</td>
                    <td class="right bold">-${{ number_format((float) ($v5510aRefundTotal ?? 0), 2) }}</td>
                </tr>
                @if(!empty($v5510aRefund->reason))
                    <tr>
                        <td>Motivo:</td>
                        <td class="right">{{ $v5510aRefund->reason }}</td>
                    </tr>
                @endif
            </table>
        </div>
    @endif

<div class="qr-box">
        <img
            src="https://api.qrserver.com/v1/create-qr-code/?size=130x130&data={{ urlencode($invoiceUrl) }}"
            alt="QR facturación"
        >
        <div>Escanea para facturar</div>
        <div class="small">{{ $order->number }}</div>
    </div>

    <div class="footer">
        @if($isReturned)
            Este ticket tiene una devolución registrada.<br>
            Conserva este comprobante para aclaraciones.
        @else
            Facturación próximamente en Bexia.<br>
            Conserva este ticket para solicitar tu factura.
        @endif
    </div>

    <div class="no-print">
        <button onclick="window.print()">Imprimir</button>
        <button onclick="window.close()">Cerrar</button>
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
