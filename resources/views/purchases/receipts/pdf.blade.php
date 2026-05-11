@php
    $money = fn ($v) => '$' . number_format((float) $v, 2);
    $qty = fn ($v) => number_format((float) $v, 6);

    $companyName = $companyInfo['name'] ?? 'Empresa';
    $companyRfc = $companyInfo['rfc'] ?? null;
    $logo = $companyInfo['logo'] ?? null;

    $receivedByName = $receivedBy->name ?? $receivedBy->email ?? '—';
@endphp

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $receipt->number ?? 'Recepción' }}</title>
    <style>
        @page {
            margin: 28px 30px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.35;
        }

        .print-toolbar {
            margin-bottom: 14px;
            text-align: right;
        }

        .print-button {
            display: inline-block;
            padding: 9px 14px;
            border-radius: 8px;
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
            font-weight: bold;
        }

        .document {
            border: 1px solid #d1d5db;
            border-radius: 10px;
            overflow: hidden;
        }

        .header {
            width: 100%;
            border-bottom: 1px solid #d1d5db;
            background: #f9fafb;
            padding: 18px 20px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .brand-cell {
            width: 62%;
            vertical-align: top;
        }

        .folio-cell {
            width: 38%;
            vertical-align: top;
            text-align: right;
        }

        .brand-wrap {
            display: table;
            width: 100%;
        }

        .logo-box {
            display: table-cell;
            width: 120px;
            vertical-align: middle;
        }

        .logo {
            max-width: 110px;
            max-height: 70px;
        }

        .brand-text {
            display: table-cell;
            vertical-align: middle;
            padding-left: 12px;
        }

        .company-name {
            font-size: 15px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 3px;
        }

        .company-meta {
            color: #4b5563;
            font-size: 10px;
        }

        .doc-title {
            font-size: 19px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 5px;
        }

        .folio {
            font-size: 16px;
            font-weight: bold;
            color: #1d4ed8;
        }

        .status {
            display: inline-block;
            margin-top: 7px;
            padding: 4px 9px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-size: 10px;
            font-weight: bold;
        }

        .section {
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            width: 33.333%;
            vertical-align: top;
            padding: 5px 10px 7px 0;
        }

        .label {
            color: #6b7280;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 3px;
        }

        .value {
            color: #111827;
            font-size: 11px;
            font-weight: bold;
        }

        .lines-table {
            width: 100%;
            border-collapse: collapse;
        }

        .lines-table th {
            background: #f3f4f6;
            border-bottom: 1px solid #d1d5db;
            color: #374151;
            font-size: 9px;
            font-weight: bold;
            padding: 8px 7px;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .lines-table td {
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 7px;
            vertical-align: top;
        }

        .right {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .product {
            font-weight: bold;
            color: #111827;
        }

        .muted {
            color: #6b7280;
        }

        .totals-wrap {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        .totals-spacer {
            width: 58%;
        }

        .totals-cell {
            width: 42%;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 5px 0;
        }

        .total-row td {
            border-top: 1px solid #d1d5db;
            padding-top: 8px;
            font-size: 13px;
            font-weight: bold;
        }

        .notes {
            min-height: 45px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 9px;
            color: #374151;
            background: #fafafa;
        }

        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 28px;
        }

        .signatures td {
            width: 50%;
            text-align: center;
            padding: 28px 30px 0;
        }

        .signature-line {
            border-top: 1px solid #6b7280;
            padding-top: 7px;
            font-size: 10px;
            color: #374151;
        }

        .footer {
            padding: 10px 20px;
            color: #6b7280;
            font-size: 9px;
            text-align: center;
            background: #f9fafb;
        }

        @media print {
            .print-toolbar {
                display: none;
            }

            body {
                background: #ffffff;
            }
        }

        /* Compactación V5.20.2 */
        @page {
            margin: 18px 20px;
        }

        body {
            font-size: 8.5px;
            line-height: 1.18;
        }

        .document {
            border-radius: 6px;
        }

        .header {
            padding: 10px 12px;
        }

        .logo-box {
            width: 82px;
        }

        .logo {
            max-width: 78px;
            max-height: 46px;
        }

        .brand-text {
            padding-left: 8px;
        }

        .company-name {
            font-size: 11px;
            margin-bottom: 1px;
        }

        .company-meta {
            font-size: 7.5px;
        }

        .doc-title {
            font-size: 14px;
            margin-bottom: 2px;
        }

        .folio {
            font-size: 11px;
        }

        .status {
            margin-top: 4px;
            padding: 2px 7px;
            font-size: 7.5px;
        }

        .section {
            padding: 9px 12px;
        }

        .section-title {
            font-size: 9px;
            margin-bottom: 6px;
        }

        .info-table td {
            padding: 2px 6px 4px 0;
        }

        .label {
            font-size: 7.2px;
            margin-bottom: 1px;
        }

        .value {
            font-size: 8.4px;
            line-height: 1.15;
            word-break: break-word;
        }

        .lines-table {
            table-layout: fixed;
        }

        .lines-table th {
            font-size: 7px;
            padding: 5px 4px;
            line-height: 1.1;
            white-space: normal;
        }

        .lines-table td {
            font-size: 7.8px;
            padding: 5px 4px;
            line-height: 1.15;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .product {
            font-size: 7.8px;
            line-height: 1.15;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .right {
            white-space: nowrap;
            font-size: 7.6px;
        }

        .totals-wrap {
            margin-top: 8px;
        }

        .totals-spacer {
            width: 62%;
        }

        .totals-cell {
            width: 38%;
        }

        .totals-table td {
            padding: 3px 0;
            font-size: 8px;
        }

        .total-row td {
            padding-top: 5px;
            font-size: 10px;
        }

        .notes {
            min-height: 30px;
            padding: 6px;
            font-size: 8px;
        }

        .signatures {
            margin-top: 18px;
        }

        .signatures td {
            padding: 22px 24px 0;
        }

        .signature-line {
            padding-top: 5px;
            font-size: 8px;
        }

        .footer {
            padding: 6px 12px;
            font-size: 7px;
        }


        /* Corrección encabezado derecho V5.20.3 */
        .header-table {
            table-layout: fixed;
        }

        .brand-cell {
            width: 60%;
        }

        .folio-cell {
            width: 40%;
            text-align: right;
            vertical-align: top;
            overflow: hidden;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .doc-title {
            font-size: 11px;
            line-height: 1.15;
            margin-bottom: 2px;
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .folio {
            font-size: 9.5px;
            line-height: 1.15;
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .status {
            font-size: 7px;
            padding: 2px 6px;
            margin-top: 3px;
            white-space: nowrap;
        }


        /* Encabezado sin corte V5.20.4 */
        .header {
            padding: 10px 14px;
        }

        .header-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .brand-cell {
            width: 66%;
            padding-right: 8px;
            vertical-align: top;
        }

        .folio-cell {
            width: 34%;
            vertical-align: top;
            text-align: right;
            padding-right: 10px;
        }

        .folio-box {
            display: inline-block;
            width: 145px;
            max-width: 145px;
            text-align: center;
            border: 1px solid #d1d5db;
            background: #ffffff;
            padding: 6px 7px;
            overflow: hidden;
        }

        .folio-box .doc-title {
            font-size: 8.5px;
            line-height: 1.1;
            margin: 0 0 3px 0;
            white-space: normal;
            word-break: normal;
            overflow-wrap: break-word;
        }

        .folio-box .folio {
            font-size: 8.2px;
            line-height: 1.1;
            margin: 0;
            color: #1d4ed8;
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .folio-box .status {
            display: inline-block;
            margin-top: 4px;
            font-size: 6.5px;
            line-height: 1;
            padding: 2px 5px;
            white-space: nowrap;
        }

    </style>
</head>
<body>
@if(! empty($pdfFallback))
    <div class="print-toolbar">
        <a href="javascript:window.print()" class="print-button">Imprimir / Guardar PDF</a>
    </div>
@endif

<div class="document">
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="brand-cell">
                    <div class="brand-wrap">
                        @if($logo)
                            <div class="logo-box">
                                <img class="logo" src="{{ $logo }}" alt="Logo">
                            </div>
                        @endif

                        <div class="brand-text">
                            <div class="company-name">{{ $companyName }}</div>
                            @if($companyRfc)
                                <div class="company-meta">RFC: {{ $companyRfc }}</div>
                            @endif
                            <div class="company-meta">Documento generado por Bexia ERP</div>
                        </div>
                    </div>
                </td>

                <td class="folio-cell">
                    <div class="folio-box">
                        <div class="doc-title">Recepción de compra</div>
                        <div class="folio">{{ $receipt->number ?? ('REC #' . $receipt->id) }}</div>
                        <div class="status">RECIBIDA</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Datos generales</div>

        <table class="info-table">
            <tr>
                <td>
                    <div class="label">Orden de compra</div>
                    <div class="value">{{ $order->number ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">Movimiento inventario</div>
                    <div class="value">{{ $movement->reference ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">Fecha recepción</div>
                    <div class="value">{{ $receipt->received_at ? \Carbon\Carbon::parse($receipt->received_at)->format('d/m/Y H:i') : '—' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Almacén</div>
                    <div class="value">{{ $warehouse->name ?? ($receipt->warehouse_id ? 'Almacén #' . $receipt->warehouse_id : '—') }}</div>
                </td>
                <td>
                    <div class="label">Ubicación</div>
                    <div class="value">{{ $location->name ?? ($receipt->location_id ? 'Ubicación #' . $receipt->location_id : '—') }}</div>
                </td>
                <td>
                    <div class="label">Recibió</div>
                    <div class="value">{{ $receivedByName }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Productos recibidos</div>

        <table class="lines-table">
            <thead>
                <tr>
                    <th style="width:30%;">Producto</th>
                    <th style="width:18%;">Variante</th>
                    <th style="width:10%;">Unidad</th>
                    <th style="width:12%;" class="right">Cantidad</th>
                    <th style="width:10%;" class="right">Costo</th>
                    <th style="width:10%;" class="right">IVA</th>
                    <th style="width:10%;" class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lines as $line)
                    <tr>
                        <td>
                            <div class="product">{{ $line->product_label ?? 'Producto' }}</div>
                        </td>
                        <td class="muted">{{ $line->variant_label ?? '—' }}</td>
                        <td class="muted">{{ $line->purchase_unit_label ?? '—' }}</td>
                        <td class="right">{{ $qty($line->received_quantity ?? 0) }}</td>
                        <td class="right">{{ $money($line->unit_cost_without_tax ?? 0) }}</td>
                        <td class="right">{{ $money($line->line_tax ?? 0) }}</td>
                        <td class="right"><strong>{{ $money($line->line_total_with_tax ?? 0) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals-wrap">
            <tr>
                <td class="totals-spacer"></td>
                <td class="totals-cell">
                    <table class="totals-table">
                        <tr>
                            <td>Subtotal</td>
                            <td class="right"><strong>{{ $money($receipt->total_without_tax ?? 0) }}</strong></td>
                        </tr>
                        <tr>
                            <td>IVA</td>
                            <td class="right"><strong>{{ $money($receipt->total_tax ?? 0) }}</strong></td>
                        </tr>
                        <tr class="total-row">
                            <td>Total</td>
                            <td class="right">{{ $money($receipt->total_with_tax ?? 0) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Notas</div>
        <div class="notes">{{ trim((string) ($receipt->notes ?? '')) !== '' ? $receipt->notes : 'Sin notas.' }}</div>

        <table class="signatures">
            <tr>
                <td>
                    <div class="signature-line">Recibió mercancía</div>
                </td>
                <td>
                    <div class="signature-line">Autorizó / Revisó</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        {{ $receipt->number ?? '' }} · {{ $companyName }} · Bexia ERP
    </div>
</div>
</body>
</html>
