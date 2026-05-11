@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $money = fn ($v) => '$' . number_format((float) $v, 2);
    $qty = fn ($v) => number_format((float) $v, 6);

    $companyName = $companyInfo['name'] ?? 'Empresa';
    $companyRfc = $companyInfo['rfc'] ?? null;
    $logo = $companyInfo['logo'] ?? null;

    $statusLabel = match ((string) ($movement->status ?? '')) {
        'draft' => 'BORRADOR',
        'done' => 'HECHO',
        'cancelled' => 'CANCELADO',
        default => strtoupper((string) ($movement->status ?? '')),
    };

    $productLabel = function ($productId, $variantId, $notes = null) {
        $notes = trim((string) $notes);

        if ($notes !== '') {
            return $notes;
        }

        if (! $productId || ! Schema::hasTable('products')) {
            return 'Producto';
        }

        $product = DB::table('products')->where('id', $productId)->first();

        if (! $product) {
            return 'Producto #' . $productId;
        }

        $ref = '';

        foreach (['internal_reference', 'sku', 'barcode', 'code'] as $column) {
            if (property_exists($product, $column) && trim((string) $product->{$column}) !== '') {
                $ref = trim((string) $product->{$column});
                break;
            }
        }

        $name = property_exists($product, 'name') ? trim((string) $product->name) : '';

        return trim(($ref ? $ref . ' - ' : '') . ($name ?: ('Producto #' . $productId)));
    };

    $variantLabel = function ($variantId) {
        if (! $variantId || ! Schema::hasTable('products')) {
            return '—';
        }

        $variant = DB::table('products')->where('id', $variantId)->first();

        if (! $variant) {
            return 'Variante #' . $variantId;
        }

        $value = property_exists($variant, 'variant_value') ? trim((string) $variant->variant_value) : '';
        $name = property_exists($variant, 'name') ? trim((string) $variant->name) : '';

        return $value ?: ($name ?: ('Variante #' . $variantId));
    };

    $totalCost = 0;

    foreach ($lines as $line) {
        $totalCost += ((float) ($line->done_quantity ?? 0)) * ((float) ($line->unit_cost ?? 0));
    }
@endphp

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $movement->reference ?? 'Movimiento de almacén' }}</title>
    <style>
        @page {
            margin: 18px 20px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8.5px;
            line-height: 1.18;
        }

        .print-toolbar {
            margin-bottom: 12px;
            text-align: right;
        }

        .print-button {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 8px;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            font-weight: bold;
        }

        .document {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            overflow: hidden;
        }

        .header {
            padding: 10px 14px;
            border-bottom: 1px solid #d1d5db;
            background: #f9fafb;
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

        .brand-wrap {
            display: table;
            width: 100%;
        }

        .logo-box {
            display: table-cell;
            width: 82px;
            vertical-align: middle;
        }

        .logo {
            max-width: 78px;
            max-height: 46px;
        }

        .brand-text {
            display: table-cell;
            vertical-align: middle;
            padding-left: 8px;
        }

        .company-name {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 1px;
        }

        .company-meta {
            color: #4b5563;
            font-size: 7.5px;
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

        .doc-title {
            font-size: 8.5px;
            line-height: 1.1;
            margin: 0 0 3px 0;
            font-weight: bold;
            overflow-wrap: break-word;
        }

        .folio {
            font-size: 8.2px;
            line-height: 1.1;
            color: #1d4ed8;
            font-weight: bold;
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 5px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1e40af;
            font-size: 6.5px;
            font-weight: bold;
            white-space: nowrap;
        }

        .section {
            padding: 9px 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .section-title {
            font-size: 9px;
            font-weight: bold;
            margin-bottom: 6px;
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
            padding: 2px 6px 4px 0;
        }

        .label {
            color: #6b7280;
            font-size: 7.2px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 1px;
        }

        .value {
            color: #111827;
            font-size: 8.4px;
            font-weight: bold;
            line-height: 1.15;
            overflow-wrap: anywhere;
        }

        .lines-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .lines-table th {
            background: #f3f4f6;
            border-bottom: 1px solid #d1d5db;
            color: #374151;
            font-size: 7px;
            font-weight: bold;
            padding: 5px 4px;
            line-height: 1.1;
            text-align: left;
            text-transform: uppercase;
        }

        .lines-table td {
            border-bottom: 1px solid #e5e7eb;
            padding: 5px 4px;
            font-size: 7.8px;
            line-height: 1.15;
            vertical-align: top;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .right {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .product {
            font-weight: bold;
        }

        .muted {
            color: #6b7280;
        }

        .totals-wrap {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .totals-spacer {
            width: 62%;
        }

        .totals-cell {
            width: 38%;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 3px 0;
            font-size: 8px;
        }

        .total-row td {
            border-top: 1px solid #d1d5db;
            padding-top: 5px;
            font-size: 10px;
            font-weight: bold;
        }

        .notes {
            min-height: 30px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 6px;
            color: #374151;
            background: #fafafa;
            font-size: 8px;
        }

        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }

        .signatures td {
            width: 50%;
            text-align: center;
            padding: 22px 24px 0;
        }

        .signature-line {
            border-top: 1px solid #6b7280;
            padding-top: 5px;
            font-size: 8px;
        }

        .footer {
            padding: 6px 12px;
            color: #6b7280;
            font-size: 7px;
            text-align: center;
            background: #f9fafb;
        }

        @media print {
            .print-toolbar {
                display: none;
            }
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
                        <div class="doc-title">Movimiento de almacén</div>
                        <div class="folio">{{ $movement->reference ?? ('MOV #' . $movement->id) }}</div>
                        <div class="status">{{ $statusLabel }}</div>
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
                    <div class="label">Referencia</div>
                    <div class="value">{{ $movement->reference ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">Origen</div>
                    <div class="value">{{ $originLabel }}</div>
                </td>
                <td>
                    <div class="label">Fecha</div>
                    <div class="value">{{ $movement->movement_at ? \Carbon\Carbon::parse($movement->movement_at)->format('d/m/Y H:i') : '—' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Tipo</div>
                    <div class="value">{{ $operationType->name ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">Almacén</div>
                    <div class="value">{{ $warehouse->name ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">Estado</div>
                    <div class="value">{{ $statusLabel }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Desde</div>
                    <div class="value">{{ $sourceLocation->name ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">A</div>
                    <div class="value">{{ $destinationLocation->name ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">Confirmó</div>
                    <div class="value">{{ $confirmedBy->name ?? $confirmedBy->email ?? '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Productos del movimiento</div>

        <table class="lines-table">
            <thead>
                <tr>
                    <th style="width:36%;">Producto</th>
                    <th style="width:18%;">Variante</th>
                    <th style="width:14%;" class="right">Solicitada</th>
                    <th style="width:14%;" class="right">Hecha</th>
                    <th style="width:9%;" class="right">Costo</th>
                    <th style="width:9%;" class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lines as $line)
                    @php
                        $done = (float) ($line->done_quantity ?? 0);
                        $unitCost = (float) ($line->unit_cost ?? 0);
                    @endphp
                    <tr>
                        <td>
                            <div class="product">{{ $productLabel($line->product_id ?? null, $line->product_variant_id ?? null, $line->notes ?? null) }}</div>
                        </td>
                        <td class="muted">{{ $variantLabel($line->product_variant_id ?? null) }}</td>
                        <td class="right">{{ $qty($line->requested_quantity ?? 0) }}</td>
                        <td class="right">{{ $qty($done) }}</td>
                        <td class="right">{{ $money($unitCost) }}</td>
                        <td class="right"><strong>{{ $money($done * $unitCost) }}</strong></td>
                    </tr>
                @endforeach

                @if($lines->isEmpty())
                    <tr>
                        <td colspan="6">Este movimiento no tiene líneas registradas.</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <table class="totals-wrap">
            <tr>
                <td class="totals-spacer"></td>
                <td class="totals-cell">
                    <table class="totals-table">
                        <tr class="total-row">
                            <td>Total costo</td>
                            <td class="right">{{ $money($totalCost) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Notas</div>
        <div class="notes">{{ trim((string) ($movement->notes ?? '')) !== '' ? $movement->notes : 'Sin notas.' }}</div>

        <table class="signatures">
            <tr>
                <td>
                    <div class="signature-line">Entregó / Generó</div>
                </td>
                <td>
                    <div class="signature-line">Recibió / Revisó</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        {{ $movement->reference ?? '' }} · {{ $companyName }} · Bexia ERP
    </div>
</div>
</body>
</html>
