@php
    $papelon = $summary['papelon_close'] ?? [];
    $sections = $papelon['sections'] ?? [];
    $methodTotals = $papelon['method_totals'] ?? [];
    $productsBySection = $papelon['products_by_section'] ?? [];
    $refunds = $papelon['refunds'] ?? [];
    $totals = $papelon['totals'] ?? [];

    $money = fn ($value) => '$' . number_format((float) $value, 2);
    $sessionNumber = (string) ($session->number ?? ('#' . ($session->id ?? '')));
    $openedAt = $session->opened_at ?? $session->created_at ?? null;
    $closedAt = $session->closed_at ?? null;
    $logoSrc = trim((string) ($companyLogoUrl ?? ''));
    $hasLogo = $logoSrc !== '';
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Corte de Caja Papelón</title>
<style>
*{box-sizing:border-box}
body{width:80mm;margin:0 auto;padding:8px;font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;color:#111}
.center{text-align:center}.right{text-align:right}.bold{font-weight:800}
.logo{max-width:48mm;max-height:22mm;margin:0 auto 4px;display:block}
.brand{font-size:15px;font-weight:900;margin:3px 0;text-transform:uppercase}
.subtitle{font-size:13px;font-weight:900;text-transform:uppercase;margin-bottom:5px}
.sep{border-top:1px dashed #333;margin:7px 0}
.section-title{font-weight:900;text-align:center;background:#eee;padding:4px 2px;border:1px solid #222;margin-top:7px}
table{width:100%;border-collapse:collapse}
td{padding:2px 0;vertical-align:top}
.total-row td{border-top:1px solid #222;font-weight:900;padding-top:4px}
.net-row td{border-top:1px solid #000;font-size:12px;font-weight:900;padding-top:4px}
.small{font-size:10px}
.cash-table td{border-bottom:1px dotted #aaa;padding:3px 0}
@media print{body{margin:0}}
</style>
</head>
<body>
<div class="center">
    @if($hasLogo)
        <img class="logo" src="{{ $logoSrc }}" alt="Logo">
    @else
        <div class="brand">PAPELÓN</div>
    @endif
    <div class="subtitle">CORTE DE CAJA</div>
    <div>Sesión: {{ $sessionNumber }}</div>
    <div>Apertura: {{ $openedAt ?: 'N/D' }}</div>
    <div>Cierre: {{ $closedAt ?: now()->format('Y-m-d H:i:s') }}</div>
</div>

<div class="sep"></div>

@foreach($sections as $section)
    <div class="section-title">{{ $section['name'] ?? 'SECCIÓN' }}</div>
    <table>
        @foreach(($section['methods'] ?? []) as $method)
            <tr>
                <td>{{ $method['method'] ?? 'Método' }}</td>
                <td class="right">{{ $money($method['total'] ?? 0) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>
                @if(($section['name'] ?? '') === 'IMPRESIÓN Y COPIAS')
                    TOTAL IMPRESIONES
                @else
                    Total {{ $section['name'] ?? '' }}
                @endif
            </td>
            <td class="right">{{ $money($section['total'] ?? 0) }}</td>
        </tr>
    </table>
@endforeach

<div class="sep"></div>

<div class="section-title">DEVOLUCIONES</div>
<table>
    <tr>
        <td>Devoluciones totales</td>
        <td class="right">{{ $money($refunds['total_refunds_total'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Devoluciones parciales</td>
        <td class="right">{{ $money($refunds['partial_refunds_total'] ?? 0) }}</td>
    </tr>
    @if((float)($refunds['other_refunds_total'] ?? 0) > 0)
        <tr>
            <td>Otras devoluciones</td>
            <td class="right">{{ $money($refunds['other_refunds_total'] ?? 0) }}</td>
        </tr>
    @endif
    <tr class="total-row">
        <td>Total devuelto</td>
        <td class="right">-{{ $money($refunds['refunded_total'] ?? 0) }}</td>
    </tr>
</table>

<div class="sep"></div>

<div class="section-title">RESUMEN FINAL</div>
<table>
    <tr>
        <td>Total vendido</td>
        <td class="right">{{ $money($totals['gross_total'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Total devuelto</td>
        <td class="right">-{{ $money($totals['refunded_total'] ?? $refunds['refunded_total'] ?? 0) }}</td>
    </tr>
    <tr class="net-row">
        <td>VENTA NETA</td>
        <td class="right">{{ $money($totals['net_total'] ?? 0) }}</td>
    </tr>
</table>

<div class="sep"></div>

<div class="section-title">TOTALES POR MÉTODO</div>
<table>
    @foreach($methodTotals as $method)
        <tr>
            <td>{{ $method['method'] ?? 'Método' }}</td>
            <td class="right">{{ $money($method['total'] ?? 0) }}</td>
        </tr>
    @endforeach
</table>

<div class="section-title">CUENTA EFECTIVO</div>
<table class="cash-table">
    @foreach([1000,500,200,100,50,20,10,5,2,1,0.5] as $denom)
        <tr>
            <td>${{ number_format((float)$denom, $denom < 1 ? 2 : 0) }}</td>
            <td class="center">x ______</td>
            <td class="right">= ______</td>
        </tr>
    @endforeach
</table>

<div class="sep"></div>

<table>
    <tr><td>Entregó</td><td class="right">________________</td></tr>
    <tr><td>Recibió</td><td class="right">________________</td></tr>
</table>

<div class="sep"></div>
<div class="center small">Formato Papelón · Bexia ERP</div>
</body>
</html>
