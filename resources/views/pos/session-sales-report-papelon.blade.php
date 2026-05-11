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
body{font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;color:#111827;margin:24px}
h1,h2,h3{margin:0}
h1{font-size:24px;text-transform:uppercase;letter-spacing:.5px}
h2{font-size:15px;margin-top:18px;padding:7px 9px;background:#111827;color:white}
h3{font-size:13px;margin:12px 0 5px}
.muted{color:#6b7280}
.header-table{width:100%;border-collapse:collapse;border-bottom:2px solid #111827;margin-bottom:16px}
.header-table td{border:none;padding:0 0 12px 0;vertical-align:middle}
.logo{max-width:180px;max-height:80px}
.header-right{text-align:right}
table{width:100%;border-collapse:collapse;margin-top:7px}
th{background:#f3f4f6;border:1px solid #d1d5db;padding:6px;text-align:left}
td{border:1px solid #e5e7eb;padding:5px 6px;vertical-align:top}
.right{text-align:right}.center{text-align:center}.bold{font-weight:800}
.total-row td{font-weight:900;background:#f9fafb}
.net-row td{font-weight:900;background:#111827;color:white;font-size:13px}
.note{margin-top:12px;font-size:10px;color:#6b7280}
</style>
</head>
<body>
<table class="header-table">
    <tr>
        <td style="width:42%;">
            @if($hasLogo)
                <img class="logo" src="{{ $logoSrc }}" alt="Logo">
            @else
                <h1>PAPELÓN</h1>
            @endif
        </td>
        <td class="header-right">
            <h1>Corte de Caja</h1>
            <div class="muted">Reporte especial por sección de cierre</div>
            <div><strong>Sesión:</strong> {{ $sessionNumber }}</div>
            <div><strong>Apertura:</strong> {{ $openedAt ?: 'N/D' }}</div>
            <div><strong>Cierre:</strong> {{ $closedAt ?: now()->format('Y-m-d H:i:s') }}</div>
        </td>
    </tr>
</table>

<h2>Resumen por sección y método de pago</h2>

@foreach($sections as $section)
    <h3>{{ $section['name'] ?? 'SECCIÓN' }}</h3>
    <table>
        <thead>
            <tr>
                <th>Método</th>
                <th class="right">Importe</th>
            </tr>
        </thead>
        <tbody>
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
        </tbody>
    </table>
@endforeach

<h2>Devoluciones</h2>
<table>
    <tbody>
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
    </tbody>
</table>

<h2>Resumen final</h2>
<table>
    <tbody>
        <tr>
            <td>Total vendido</td>
            <td class="right">{{ $money($totals['gross_total'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Total devuelto</td>
            <td class="right">-{{ $money($totals['refunded_total'] ?? $refunds['refunded_total'] ?? 0) }}</td>
        </tr>
        <tr class="net-row">
            <td>Venta neta</td>
            <td class="right">{{ $money($totals['net_total'] ?? 0) }}</td>
        </tr>
    </tbody>
</table>

<h2>Totales por método</h2>
<table>
    <tbody>
        @foreach($methodTotals as $method)
            <tr>
                <td>{{ $method['method'] ?? 'Método' }}</td>
                <td class="right">{{ $money($method['total'] ?? 0) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<h2>Detalle de productos por sección</h2>

@foreach($productsBySection as $sectionName => $products)
    @continue($sectionName === 'IMPRESIÓN Y COPIAS')
    <h3>{{ $sectionName }}</h3>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Ruta categoría</th>
                <th class="right">Cantidad</th>
                <th class="right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $product)
                <tr>
                    <td>{{ $product['name'] ?? 'Producto' }}</td>
                    <td>{{ $product['path'] ?? '' }}</td>
                    <td class="right">{{ number_format((float)($product['qty'] ?? 0), 2) }}</td>
                    <td class="right">{{ $money($product['total'] ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endforeach

<h2>Cuenta de efectivo</h2>
<table>
    <thead>
        <tr>
            <th>Denominación</th>
            <th class="center">Cantidad</th>
            <th class="right">Importe</th>
        </tr>
    </thead>
    <tbody>
        @foreach([1000,500,200,100,50,20,10,5,2,1,0.5] as $denom)
            <tr>
                <td>${{ number_format((float)$denom, $denom < 1 ? 2 : 0) }}</td>
                <td class="center">____________</td>
                <td class="right">____________</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="note">
    Este formato separa visualmente Impresión y Copias, aunque la categoría sigue perteneciendo a PAPELÓN.
</div>
</body>
</html>
