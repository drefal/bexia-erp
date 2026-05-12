<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura {{ $invoice->cfdi_series ?? '' }} {{ $invoice->cfdi_folio ?? '' }}</title>
    <style>
        /* BEXIA_V5523Q3_PDF_VISUAL_TUNE */
        @page { margin: 16px 18px 14px 18px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111;
            font-size: 9px;
            line-height: 1.15;
        }

        .clearfix:after {
            content: "";
            display: block;
            clear: both;
        }

        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .muted { color: #666; }
        .upper { text-transform: uppercase; }
        .mono { font-family: DejaVu Sans Mono, monospace; word-break: break-all; }
        .tiny { font-size: 7px; }

        .preliminar {
            position: fixed;
            top: 340px;
            left: 55px;
            right: 55px;
            text-align: center;
            font-size: 44px;
            color: #eeeeee;
            transform: rotate(-24deg);
            z-index: -1;
            font-weight: bold;
        }

        .brand-header {
            border-bottom: 2px solid #1f2937;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .logo-col {
            float: left;
            width: 28%;
            text-align: center;
            min-height: 72px;
        }

        .logo-col img {
            max-width: 135px;
            max-height: 58px;
        }

        .company-col {
            float: left;
            width: 48%;
            text-align: center;
        }

        .branch-col {
            float: right;
            width: 22%;
            font-size: 9px;
            padding-top: 28px;
        }

        .company-title {
            font-size: 15.8px;
            font-weight: 800;
            letter-spacing: .35px;
            margin-bottom: 2px;
        }

        .company-sub {
            font-size: 8.5px;
            font-weight: 600;
        }

        .company-address {
            margin-top: 5px;
            font-size: 8.5px;
            line-height: 1.1;
            white-space: pre-line;
            text-align: left;
            display: inline-block;
            min-width: 210px;
        }

        .branch-title {
            font-size: 9.5px;
            font-weight: 800;
        }

        .header-row {
            margin-bottom: 8px;
        }

        .client-block {
            float: left;
            width: 48%;
            font-size: 8.7px;
        }

        .meta-block {
            float: right;
            width: 48%;
        }

        .client-title {
            color: #666;
            font-size: 9px;
            margin-bottom: 2px;
        }

        .client-name {
            font-size: 9.5px;
            font-weight: 800;
        }

        .address {
            white-space: pre-line;
            line-height: 1.18;
        }

        .kv-line {
            margin-top: 4px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
        }

        .meta-table td {
            border: 1px solid #444;
            padding: 2px 4px;
        }

        .meta-table td:first-child {
            width: 34%;
            font-weight: 700;
        }

        .meta-table td:last-child {
            text-align: right;
        }

        table.lines {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 7.4px;
        }

        .lines th {
            border: 1px solid #c7c7c7;
            border-bottom: 2px solid #111;
            background: #fff;
            text-align: center;
            padding: 2px 3px;
            font-weight: 800;
            line-height: 1.05;
        }

        .lines td {
            border: 1px solid #d6d6d6;
            padding: 2px 3px;
            vertical-align: top;
            text-align: center;
            line-height: 1.1;
        }

        .lines .desc {
            text-align: center;
            text-transform: uppercase;
        }

        .totals-wrap {
            width: 48%;
            margin-left: 52%;
            margin-top: 8px;
        }

        .totals {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        .totals td {
            border: 1px solid #d6d6d6;
            padding: 5px 6px;
        }

        .totals td:first-child {
            width: 58%;
            text-align: right;
            font-weight: 800;
        }

        .totals td:last-child {
            text-align: right;
        }

        .amount-words {
            text-align: center;
            margin-top: 8px;
            margin-bottom: 4px;
        }

        .amount-words .words {
            font-size: 8.6px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .amount-words .legend {
            margin-top: 3px;
            font-size: 8px;
            color: #555;
        }

        .fiscal-wrap {
            margin-top: 5px;
            page-break-inside: avoid;
        }

        .qr-col {
            float: left;
            width: 27%;
        }

        .qr-col img {
            width: 158px;
            height: 158px;
            border: 0;
        }

        .qr-placeholder {
            width: 158px;
            height: 158px;
            border: 1px solid #ccc;
            text-align: center;
            font-size: 8.5px;
            color: #666;
            padding-top: 68px;
            box-sizing: border-box;
        }

        .fiscal-col {
            float: right;
            width: 71%;
        }

        .band {
            background: #5f5f5f;
            color: #fff;
            font-weight: 800;
            padding: 2.5px 5px;
            margin-top: 2px;
            border-radius: 2px;
            font-size: 7.5px;
        }

        .seal {
            font-size: 5.75px;
            line-height: 1.06;
            word-break: break-all;
            margin-bottom: 1px;
        }

        .info-extra {
            font-size: 5.85px;
            line-height: 1.08;
            word-break: break-word;
        }
    
        /* BEXIA_V5523Q6_HEADER_LOGO_PRO_START */
        /*
         * Ajuste visual Q6:
         * - Logo más grande.
         * - Cabecera más balanceada.
         * - Emisor más legible.
         * - Sucursal compacta.
         */
        .brand-header {
            padding-bottom: 10px !important;
            margin-bottom: 8px !important;
            min-height: 92px !important;
            border-bottom: 2px solid #1f2937 !important;
        }

        .logo-col {
            float: left !important;
            width: 36% !important;
            text-align: left !important;
            min-height: 92px !important;
            padding-top: 0 !important;
        }

        .logo-col img {
            max-width: 285px !important;
            max-height: 112px !important;
            width: auto !important;
            height: auto !important;
            display: block !important;
            margin: 0 !important;
        }

        .company-col {
            float: left !important;
            width: 40% !important;
            text-align: center !important;
            padding-top: 8px !important;
        }

        .branch-col {
            float: right !important;
            width: 22% !important;
            padding-top: 23px !important;
            font-size: 8.5px !important;
            text-align: left !important;
        }

        .company-title {
            font-size: 17.4px !important;
            font-weight: 800 !important;
            letter-spacing: .35px !important;
            margin-bottom: 3px !important;
            line-height: 1.08 !important;
        }

        .company-sub {
            font-size: 8.4px !important;
            font-weight: 700 !important;
            line-height: 1.1 !important;
        }

        .company-address {
            margin-top: 3px !important;
            font-size: 7.8px !important;
            line-height: 1.05 !important;
            white-space: pre-line !important;
            text-align: center !important;
            display: block !important;
            min-width: 0 !important;
        }

        .branch-title {
            font-size: 9px !important;
            font-weight: 800 !important;
            margin-bottom: 2px !important;
        }

        .header-row {
            margin-top: 4px !important;
            margin-bottom: 8px !important;
        }

        .client-block {
            width: 47% !important;
        }

        .meta-block {
            width: 49.5% !important;
        }

        .meta-table {
            font-size: 8.2px !important;
        }

        .meta-table td {
            padding: 2.5px 4px !important;
        }

        table.lines {
            margin-top: 6px !important;
        }

        .amount-words {
            margin-top: 8px !important;
        }

        .qr-col img {
            width: 165px !important;
            height: 165px !important;
        }

        .qr-placeholder {
            width: 165px !important;
            height: 165px !important;
            padding-top: 70px !important;
        }
        /* BEXIA_V5523Q6_HEADER_LOGO_PRO_END */

    
        /* BEXIA_V5523Q7B_MINIMAL_SEAL_OVERFLOW_START */
        /*
         * Fix mínimo:
         * mantiene la tipografía actual y solo evita que sellos/cadena se salgan a la derecha.
         */
        .seal,
        .info-extra {
            max-width: 100% !important;
            box-sizing: border-box !important;
            overflow: hidden !important;
            white-space: normal !important;
            word-break: normal !important;
            overflow-wrap: break-word !important;
        }
        /* BEXIA_V5523Q7B_MINIMAL_SEAL_OVERFLOW_END */

    </style>
</head>
<body>
@if (! $isStamped)
    <div class="preliminar">NO TIMBRADO</div>
@endif

<div class="brand-header clearfix">
    <div class="logo-col">
        @if ($logoDataUri)
            <img src="{{ $logoDataUri }}" alt="Logo">
        @else
            <div style="font-size:18px;font-weight:800;margin-top:20px;">BEXIA</div>
        @endif
    </div>

    <div class="company-col">
        <div class="company-title">{{ $company->business_name ?: ($company->name ?? '') }}</div>
        <div class="company-sub">
            R.F.C.: {{ $company->tax_id ?? '' }}
            &nbsp; • &nbsp;
            Régimen Fiscal: {{ $company->tax_regime ?? '' }}
        </div>
        <div class="company-address">{{ $companyAddress }}</div>
    </div>

    <div class="branch-col">
        <div class="branch-title">SUCURSAL</div>
        <div>{{ $branchLabel ?: 'Matriz' }}</div>
    </div>
</div>

<div class="header-row clearfix">
    <div class="client-block">
        <div class="client-title">CLIENTE</div>
        <div class="client-name upper">{{ $invoice->customer_fiscal_name ?: ($invoice->customer_name ?? '') }}</div>
        <div class="upper address">{{ $customerAddress }}</div>
        <div class="kv-line">RFC: <span class="bold">{{ $invoice->customer_rfc ?? '' }}</span></div>
        <div class="kv-line">
            Régimen Fiscal (Receptor):
            <span class="bold">{{ $invoice->customer_tax_regime_code ?? '' }}</span>
        </div>
        <div class="kv-line">
            Dirección de entrega:
            <span class="bold">{{ $invoice->customer_fiscal_name ?: ($invoice->customer_name ?? '') }}</span>
        </div>
    </div>

    <div class="meta-block">
        <table class="meta-table">
            <tr>
                <td>Folio</td>
                <td>{{ trim(($invoice->cfdi_series ?? '').' '.str_pad((string) ($invoice->cfdi_folio ?? ''), 5, '0', STR_PAD_LEFT)) }}</td>
            </tr>
            <tr>
                <td>Tipo de comprobante</td>
                <td>{{ $cfdiTypeLabel }}</td>
            </tr>
            <tr>
                <td>Fecha de factura</td>
                <td>{{ optional($invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date) : $generatedAt)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td>Origen</td>
                <td>{{ $invoice->source_number ?? $invoice->source_type ?? '' }}</td>
            </tr>
            <tr>
                <td>Uso CFDI</td>
                <td>{{ $cfdiUseLabel }}</td>
            </tr>
            <tr>
                <td>Método / Forma</td>
                <td>{{ $paymentLabel }}</td>
            </tr>
            <tr>
                <td>Folio Fiscal UUID</td>
                <td>{{ $invoice->cfdi_uuid ?: ($xmlInfo['uuid'] ?? '') }}</td>
            </tr>
        </table>
    </div>
</div>

<table class="lines">
    <thead>
        <tr>
            <th style="width:7%;">Cantidad</th>
            <th style="width:9%;">Unidad de<br>Medida</th>
            <th style="width:10%;">Clave SAT</th>
            <th style="width:12%;">Referencia interna</th>
            <th style="width:12%;">Código de barras</th>
            <th>Descripción</th>
            <th style="width:10%;">Precio Unitario</th>
            <th style="width:12%;">Impuestos</th>
            <th style="width:10%;">Importe</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lines as $line)
            <tr>
                <td>{{ rtrim(rtrim(number_format((float) $line['quantity'], 4, '.', ''), '0'), '.') }}</td>
                <td>
                    {{ $line['unit'] }}
                    @if ($line['unit_name'] && $line['unit_name'] !== $line['unit'])
                        - {{ $line['unit_name'] }}
                    @endif
                </td>
                <td>{{ $line['sat_code'] }}</td>
                <td>{{ $line['internal_ref'] }}</td>
                <td>{{ $line['barcode'] }}</td>
                <td class="desc">{{ $line['description'] }}</td>
                <td class="right">$ {{ number_format((float) $line['unit_price'], 2) }}</td>
                <td class="right">$ {{ number_format((float) $line['tax'], 2) }}</td>
                <td class="right">$ {{ number_format((float) $line['subtotal'], 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="totals-wrap">
    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td>$ {{ number_format((float) $subtotal, 2) }}</td>
        </tr>
        <tr>
            <td>Impuestos</td>
            <td>$ {{ number_format((float) $taxTotal, 2) }}</td>
        </tr>
        <tr>
            <td>Total</td>
            <td>$ {{ number_format((float) $total, 2) }}</td>
        </tr>
    </table>
</div>

<div class="amount-words">
    <div class="words">{{ $amountWords }}</div>
    <div class="legend">Este documento es una representación impresa de un CFDI.</div>
</div>

@php
    // BEXIA_V5523Q7B_SOFT_WRAP_FISCAL_TEXT
    // Solo agrega espacios visuales para que Dompdf pueda envolver sellos largos.
    // No modifica XML, sello, UUID ni timbrado.
    $bexiaSoftWrapFiscalText = function ($value, int $chunk = 150): string {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return trim(chunk_split($value, $chunk, ' '));
    };
@endphp

<div class="fiscal-wrap clearfix">
    <div class="qr-col">
        @if ($qrDataUri)
            <img src="{{ $qrDataUri }}" alt="QR CFDI">
        @else
            <div class="qr-placeholder">
                @if ($isStamped)
                    QR CFDI pendiente
                @else
                    QR disponible al timbrar
                @endif
            </div>
        @endif
    </div>

    <div class="fiscal-col">
        <div class="band">Sello digital del emisor</div>
        <div class="seal mono">{{ $bexiaSoftWrapFiscalText($xmlInfo['sello_cfdi'] ?? '', 150) }}</div>

        <div class="band">Sello digital del SAT</div>
        <div class="seal mono">{{ $bexiaSoftWrapFiscalText($xmlInfo['sello_sat'] ?? '', 150) }}</div>

        <div class="band">Cadena original del complemento del certificado digital del SAT</div>
        <div class="seal mono">{{ $bexiaSoftWrapFiscalText($xmlInfo['cadena_sat'] ?? '', 165) }}</div>

        <div class="band">Información Extra</div>
        <div class="info-extra mono">
            Certificado del emisor: {{ $xmlInfo['issuer_certificate'] ?? '' }}
            | Certificado SAT: {{ $xmlInfo['sat_certificate'] ?? '' }}
            | Lugar de expedición: {{ $xmlInfo['expedition_place'] ?? ($company->fiscal_postal_code ?? $company->postal_code ?? '') }}
            | Régimen Fiscal: {{ $xmlInfo['issuer_regime'] ?? ($company->tax_regime ?? '') }}
            | Fecha de Emisión: {{ $xmlInfo['emission_date'] ?? '' }}
            | Fecha de Certificación: {{ $xmlInfo['stamp_date'] ?? '' }}
            | Folio fiscal (UUID): {{ $invoice->cfdi_uuid ?: ($xmlInfo['uuid'] ?? '') }}
        </div>
    </div>
</div>
</body>
</html>
