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

    /*
     * BEXIA_V5836G5H4C_SEPARATE_SALES_ADVANCES
     */
    $isClosed =
        (string) ($session->status ?? '') === 'closed'
        && ! empty($closedAt);

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
.no-print{
    display:flex;
    justify-content:center;
    gap:6px;
    margin-top:10px;
}
.no-print button{
    border:1px solid #222;
    border-radius:4px;
    background:#fff;
    color:#111;
    padding:6px 10px;
    font:inherit;
    font-weight:700;
    cursor:pointer;
}
/*
 * BEXIA_V5836G5H7C4B_COMPACT_PRINT
 *
 * Compactar exclusivamente al imprimir.
 * Pantalla conserva sus medidas normales.
 */
@media print{
    @page{
        margin:4mm;
    }

    body{
        margin:0!important;
        padding:3px!important;
        font-size:9.5px!important;
        line-height:1.05!important;
    }

    .logo{
        max-height:16mm!important;
        margin-bottom:2px!important;
    }

    .brand{
        font-size:13px!important;
        margin:1px 0!important;
    }

    .subtitle{
        font-size:11px!important;
        margin-bottom:2px!important;
    }

    .sep{
        margin:3px 0!important;
    }

    .section-title{
        padding:2px 1px!important;
        margin-top:3px!important;
    }

    td{
        padding:1px 0!important;
        line-height:1.05!important;
    }

    .total-row td,
    .net-row td{
        padding-top:2px!important;
    }

    .cash-table td{
        padding:1px 0!important;
        line-height:1!important;
    }

    .no-print{
        display:none!important;
    }
}
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
    @if($isClosed)
        <div>Cierre: {{ $closedAt }}</div>
    @else
        <div>Generado: {{ now()->format('Y-m-d H:i:s') }}</div>
    @endif
</div>

<div class="sep"></div>

<div class="section-title">
    COBROS POR SECCIÓN Y MÉTODO
</div>

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
                    TOTAL COBRADO IMPRESIONES
                @else
                    Total cobrado {{ $section['name'] ?? '' }}
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
    {{-- BEXIA_V5836G5H7C4B_ADVANCE_REFUND_VISUAL --}}
    @if((float)($refunds['advance_refunds_total'] ?? 0) > 0)
        <tr>
            <td>Anticipos devueltos</td>
            <td class="right">{{ $money($refunds['advance_refunds_total'] ?? 0) }}</td>
        </tr>
    @endif
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

{{-- BEXIA_V5836G5H7C3_VISUAL_ADVANCE_REFUNDS --}}
<table>
    <tr>
        <td>Ventas liquidadas</td>
        <td class="right">{{ $money($totals['gross_total'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Anticipos cobrados</td>
        <td class="right">{{ $money($totals['advance_payments_total'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Anticipos devueltos</td>
        <td class="right">-{{ $money($totals['advance_refunds_total'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Anticipos pendientes</td>
        <td class="right">{{ $money($totals['outstanding_advance_total'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Cobrado en sesión</td>
        <td class="right">{{ $money($totals['collected_total'] ?? $totals['payments_total'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Flujo neto cobrado</td>
        <td class="right">{{ $money($totals['net_cashflow_total'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Devoluciones de venta</td>
        <td class="right">-{{ $money($totals['sale_refunds_total'] ?? 0) }}</td>
    </tr>
    <tr class="net-row">
        <td>VENTA NETA LIQUIDADA</td>
        <td class="right">{{ $money($totals['net_settled_sales_total'] ?? $totals['net_total'] ?? 0) }}</td>
    </tr>
</table>

<div class="sep"></div>

<div class="section-title">TOTALES COBRADOS POR MÉTODO</div>
<table>
    @foreach($methodTotals as $method)
        <tr>
            <td>{{ $method['method'] ?? 'Método' }}</td>
            <td class="right">{{ $money($method['total'] ?? 0) }}</td>
        </tr>
    @endforeach
</table>

{{-- BEXIA_V5836_PAPC6_PAPELON_CLOSE_DETAIL --}}
@php
    $papc6Session = $summary['session'] ?? [];
    $papc6Totals = $summary['totals'] ?? [];
    $papc6Rows = $papc6Session['closing_cash_count']
        ?? ($closePayload['cash_count'] ?? []);

    if (is_string($papc6Rows)) {
        $papc6Rows = json_decode($papc6Rows, true);
    }

    $papc6HasCount = is_array($papc6Rows) && count($papc6Rows) > 0;
    $papc6Lines = [];

    foreach (is_array($papc6Rows) ? $papc6Rows : [] as $row) {
        if (!is_array($row)) continue;

        $value = round((float) ($row['value'] ?? 0), 2);
        $quantity = (int) ($row['quantity'] ?? 0);
        if ($value <= 0 || $quantity < 0) continue;

        $name = trim((string) ($row['name'] ?? ''));
        $type = strtolower(trim((string) ($row['type'] ?? '')));

        if ($type === '' && $name !== '') {
            $lower = mb_strtolower($name);
            $type = str_contains($lower, 'billete')
                ? 'bill'
                : (str_contains($lower, 'moneda') ? 'coin' : '');
        }

        if ($name === '') {
            $label = number_format($value, $value < 1 ? 2 : 0);
            $name = ($type === 'coin' ? 'Moneda de '
                : ($type === 'bill' ? 'Billete de ' : '$')) . $label;
        }

        $papc6Lines[] = [
            'value' => $value,
            'quantity' => $quantity,
            'name' => $name,
            'type' => $type,
            'total' => round($value * $quantity, 2),
        ];
    }

    usort($papc6Lines, function ($a, $b) {
        $byValue = $b['value'] <=> $a['value'];
        if ($byValue !== 0) return $byValue;
        if ($a['type'] === $b['type']) return 0;
        if ($a['type'] === 'bill') return -1;
        if ($b['type'] === 'bill') return 1;
        return 0;
    });

    $papc6CashCounted = round(
        array_sum(array_column($papc6Lines, 'total')), 2
    );

    $papc6CardCredit = 0.0;
    $papc6CardDebit = 0.0;
    $papc6CardOther = 0.0;
    $papc6Transfer = 0.0;
    $papc6Other = 0.0;
    $papc6Sales = 0.0;

    foreach ($methodTotals as $method) {
        $name = mb_strtolower(
            \Illuminate\Support\Str::ascii(
                (string) ($method['method'] ?? '')
            )
        );
        $amount = (float) ($method['total'] ?? 0);
        $papc6Sales += $amount;

        if (str_contains($name, 'efectivo') || str_contains($name, 'cash')) {
            continue;
        }
        if (str_contains($name, 'transfer') || str_contains($name, 'spei')) {
            $papc6Transfer += $amount;
        } elseif (str_contains($name, 'tarjeta') || str_contains($name, 'card')) {
            if (str_contains($name, 'credito')) {
                $papc6CardCredit += $amount;
            } elseif (str_contains($name, 'debito')) {
                $papc6CardDebit += $amount;
            } else {
                $papc6CardOther += $amount;
            }
        } else {
            $papc6Other += $amount;
        }
    }

    $papc6Sales = round($papc6Sales, 2);
    $papc6CardTotal = round(
        $papc6CardCredit + $papc6CardDebit + $papc6CardOther, 2
    );
    $papc6Total = round(
        $papc6CashCounted + $papc6CardTotal
        + $papc6Transfer + $papc6Other, 2
    );
    $papc6Difference = round($papc6Total - $papc6Sales, 2);
    $papc6Note = trim((string) (
        $papc6Session['closing_note']
        ?? ($closePayload['closing_note'] ?? '')
    ));
@endphp

<div class="section-title">CUENTA EFECTIVO</div>
<table class="cash-table">
    @forelse($papc6Lines as $line)
        <tr>
            <td>{{ $line['name'] }}</td>
            <td class="center">x {{ $line['quantity'] }}</td>
            <td class="right">= {{ $money($line['total']) }}</td>
        </tr>
    @empty
        <tr><td colspan="3">Sin desglose de denominaciones</td></tr>
    @endforelse
    <tr class="total-row">
        <td colspan="2">TOTAL EFECTIVO CONTADO</td>
        <td class="right">
            {{ $papc6HasCount ? $money($papc6CashCounted) : 'Sin conteo' }}
        </td>
    </tr>
</table>

<div class="section-title">CONCILIACIÓN DEL CIERRE</div>
<table>
    <tr><td>Total cobrado registrado</td><td class="right">{{ $money($papc6Sales) }}</td></tr>
    <tr><td>Efectivo contado</td><td class="right">{{ $papc6HasCount ? $money($papc6CashCounted) : 'Sin conteo' }}</td></tr>
    <tr><td>Tarjeta de crédito</td><td class="right">{{ $money($papc6CardCredit) }}</td></tr>
    <tr><td>Tarjeta de débito</td><td class="right">{{ $money($papc6CardDebit) }}</td></tr>
    @if(abs($papc6CardOther) > 0.009)
        <tr><td>Otras tarjetas</td><td class="right">{{ $money($papc6CardOther) }}</td></tr>
    @endif
    <tr><td>Transferencias</td><td class="right">{{ $money($papc6Transfer) }}</td></tr>
    @if(abs($papc6Other) > 0.009)
        <tr><td>Otros métodos</td><td class="right">{{ $money($papc6Other) }}</td></tr>
    @endif
    <tr class="net-row">
        <td>TOTAL CONTADO Y REGISTRADO</td>
        <td class="right">{{ $papc6HasCount ? $money($papc6Total) : 'Sin conteo' }}</td>
    </tr>
    <tr>
        <td>DIFERENCIA VS COBRADO</td>
        <td class="right bold">
            {{ $papc6HasCount ? $money($papc6Difference) : 'Sin conteo' }}
        </td>
    </tr>
    <tr><td>Observaciones</td><td class="right">________________</td></tr>
</table>

@if($papc6Note !== '')
    <div class="sep"></div>
    <div class="small">
        <strong>Nota de cierre:</strong>
        {{ $papc6Note }}
    </div>
@endif

<div class="sep"></div>

<table>
    <tr><td>Entregó</td><td class="right">________________</td></tr>
    <tr><td>Recibió</td><td class="right">________________</td></tr>
</table>

<div class="sep"></div>
<div class="center small">Formato Papelón · Bexia ERP</div>

<div class="no-print">
    <button type="button" onclick="window.print()">Imprimir</button>
    <button type="button" onclick="window.close()">Cerrar</button>
</div>

<script id="BEXIA_V5829G2_PAPELON_CLOSE_AUTO_PRINT">
    /*
     * BEXIA_V5829G2_PAPELON_CLOSE_AUTO_PRINT
     *
     * Replica el comportamiento de los tickets cobrados:
     * al terminar de cargar el ticket, abre el diálogo del navegador.
     */
    window.addEventListener('load', function () {
        window.setTimeout(function () {
            window.focus();
            window.print();
        }, 350);
    }, { once: true });
</script>
</body>
</html>
