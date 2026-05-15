<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $order->number ?? 'Ticket pendiente' }}</title>
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

        .pending-box {
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

        .qr-box {
            text-align: center;
            margin: 3mm 0 1mm 0;
            font-size: 10px;
            font-weight: 900;
        }

        .reprint-small {
            margin-top: 1mm;
            font-size: 10px;
            font-weight: 900;
            text-align: center;
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
    </style>
</head>
<body>
@php
    /* BEXIA_V5527D5_RECEIPT_SELLER_DISPLAY_MODE_VIEW */
    $v5496aPriceListName = trim((string) (
        $order->price_list_name
        ?? (json_decode((string) ($order->metadata ?? ''), true)['price_list_name'] ?? '')
        ?? ''
    ));
@endphp


    {{-- V5.44.2 discount print start --}}
    @php
        $v5442Metadata = [];
        $v5442Discount = null;
        $v5442DiscountAmount = 0.0;
        $v5442DiscountUser = '';
        $v5442DiscountType = '';
        $v5442DiscountValue = 0.0;

        if (! empty($order->metadata)) {
            $v5442Decoded = json_decode((string) $order->metadata, true);

            if (is_array($v5442Decoded)) {
                $v5442Metadata = $v5442Decoded;
            }
        }

        if (isset($v5442Metadata['discount']) && is_array($v5442Metadata['discount'])) {
            $v5442Discount = $v5442Metadata['discount'];
            $v5442DiscountAmount = (float) ($v5442Discount['amount'] ?? 0);
            $v5442DiscountUser = trim((string) ($v5442Discount['user_name'] ?? ''));
            $v5442DiscountType = (string) ($v5442Discount['type'] ?? '');
            $v5442DiscountValue = (float) ($v5442Discount['value'] ?? 0);
        }
    @endphp
    {{-- V5.44.2 discount print end --}}

    <div class="center">
        @if(!empty($logoUrl))
            <img class="ticket-logo" src="{{ $logoUrl }}" alt="Logo PDV">
        @endif

        <div class="subtitle">TICKET PENDIENTE DE COBRO</div>
        <div class="copy-label">NO ES COMPROBANTE DE PAGO</div>
    </div>

    <div class="pending-box">NO PAGADO</div>
<div class="warning-box">NO ENTREGAR MERCANCÍA SIN COBRO</div>

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
        @if(trim((string) ($sellerName ?? '')) !== '')
            <tr>
                <td>Vendedor:</td>
                <td class="right">{{ $sellerName }}</td>
            </tr>
        @endif
        <tr>
            <td>Estado:</td>
            <td class="right bold">Pendiente de cobro</td>
        </tr>
    </table>

    <div class="line"></div>

@if($v5496aPriceListName !== '')
    <div style="font-size:11px; margin-top:2px;">
        Lista de precios: {{ $v5496aPriceListName }}
    </div>
@endif


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
        {{-- V5.44.2 discount print start --}}
        @if($v5442DiscountAmount > 0)
            <tr>
                <td>Descuento:</td>
                <td class="right">-${{ number_format($v5442DiscountAmount, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2" style="font-size:10px; font-weight:900;">
                    Aplicado por: {{ $v5442DiscountUser !== '' ? $v5442DiscountUser : 'Usuario' }}
                    @if($v5442DiscountType === 'percent')
                        ({{ rtrim(rtrim(number_format($v5442DiscountValue, 2), '0'), '.') }}%)
                    @endif
                </td>
            </tr>
        @endif
        {{-- V5.44.2 discount print end --}}
        <tr>
            <td class="total">TOTAL ADEUDADO</td>
            <td class="right total">${{ number_format((float) ($order->total ?? 0), 2) }}</td>
        </tr>
    </table>

    <div class="qr-box">
        <img
            src="https://api.qrserver.com/v1/create-qr-code/?size=130x130&data={{ urlencode($order->number) }}"
            alt="QR ticket"
        >
        <div>{{ $order->number }}</div>

        @if(($reprintCount ?? 0) > 0)
            <div class="reprint-small">Reimpresión No. {{ $reprintCount }}</div>
        @endif
    </div>

    <div class="line"></div>

    <div class="footer">
        Presente este ticket en caja para realizar el pago.<br>
        Este ticket se reemplaza por el comprobante final al cobrar.
    </div>

    <div class="no-print">
        <button onclick="window.print()">Imprimir</button>
        <button onclick="window.close()">Cerrar</button>
    </div>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 400);
        });
    </script>
</body>
</html>
