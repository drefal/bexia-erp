<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Corte individual #{{ $shift->id }} - Bexia</title>
<style>
* { box-sizing: border-box; }
body {
    margin: 0;
    background: #eef2f7;
    color: #162238;
    font-family: Arial, sans-serif;
    font-size: 13px;
}
.actions {
    max-width: 850px;
    margin: 18px auto;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.actions a, .actions button {
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    padding: 10px 15px;
    background: white;
    color: #1e40af;
    cursor: pointer;
    text-decoration: none;
    font-weight: 600;
}
.paper {
    background: #fff;
    max-width: 850px;
    margin: 0 auto 24px;
    padding: 30px;
    box-shadow: 0 4px 30px #14213d18;
}
h1 { font-size: 19px; text-align: center; margin: 0; }
.subtitle { text-align: center; margin: 6px 0 20px; }
.meta, .totals { width: 100%; border-collapse: collapse; }
.meta td { padding: 5px 0; }
.meta td:last-child { text-align: right; font-weight: bold; }
table.detail { border-collapse: collapse; width: 100%; }
.detail th, .detail td {
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    padding: 8px 4px;
}
.detail td:last-child, .detail th:last-child { text-align: right; }
.section {
    margin-top: 22px;
    padding-bottom: 7px;
    border-bottom: 2px solid #1e3a8a;
    font-weight: 700;
}
.totals td { padding: 6px 0; }
.totals td:last-child { text-align: right; }
.grand {
    font-size: 19px;
    font-weight: 800;
    border-top: 2px solid #162238;
}
.notice {
    margin-top: 20px;
    padding: 12px;
    border: 1px solid #f4cf80;
    background: #fffbeb;
    font-size: 12px;
    line-height: 1.5;
}
@media print {
    body { background: white; }
    .actions { display: none !important; }
    .paper { box-shadow: none; margin: 0; padding: 0; }
}
@if($format === '80mm')
.paper { width: 80mm; max-width: 80mm; padding: 4mm; }
h1 { font-size: 15px; }
.detail th, .detail td { padding: 5px 2px; font-size: 10px; }
@media print {
    @page { size: 80mm auto; margin: 2mm; }
    body, .paper { width: 76mm; }
}
@else
@media print {
    @page { size: letter portrait; margin: 12mm; }
}
@endif

/* ACCESS9 FIX5F: MARCA OFICIAL */
.access9-official-header {
    text-align: center;
    margin: 0 0 14px;
    padding: 0 0 11px;
    border-bottom: 1px solid #d1d5db;
    break-inside: avoid;
    page-break-inside: avoid;
}

.access9-official-logo {
    display: block;
    width: auto;
    height: auto;
    max-width: 46mm;
    max-height: 18mm;
    object-fit: contain;
    margin: 0 auto 8px;
}

.access9-official-brand {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.6px;
    color: #475569;
    margin-bottom: 5px;
}

.access9-official-header h1 {
    font-size: 17px;
    font-weight: 800;
    line-height: 1.25;
    margin: 0;
}

@media print {
    .access9-official-header {
        border-bottom-color: #999;
    }
}

@media screen and (max-width: 400px) {
    .access9-official-logo {
        max-width: 46mm;
        max-height: 18mm;
    }

    .access9-official-header h1 {
        font-size: 14px;
    }
}

.access9-cash-note {
    margin-top: 8px;
    font-size: 11px;
    line-height: 1.45;
    color: #475569;
}
@media print {
    .access9-cash-note {
        color: #222;
    }
}

/* BEXIA_ACCESS9_FIX7H_COMPACT */
@media print {
    body {
        font-size: 10.5px;
        line-height: 1.22;
    }

    .paper {
        padding: 0 !important;
        margin: 0 !important;
        box-shadow: none !important;
    }

    .access9-official-header {
        margin-bottom: 7px;
        padding-bottom: 5px;
    }

    .access9-official-logo {
        max-height: 13mm;
        margin-bottom: 4px;
    }

    .access9-official-header h1 {
        font-size: 14px;
        line-height: 1.1;
    }

    .access9-official-brand {
        font-size: 8px;
        margin-bottom: 3px;
    }

    .subtitle {
        margin: 4px 0 9px;
    }

    .meta td {
        padding: 2px 0;
    }

    .section {
        margin-top: 11px;
        padding-bottom: 4px;
        border-bottom-width: 1px;
    }

    .detail th,
    .detail td {
        padding: 3px 2px;
        line-height: 1.2;
    }

    .totals td {
        padding: 3px 0;
    }

    .grand {
        font-size: 14px;
    }

    .access9-cash-note {
        margin-top: 6px;
        font-size: 9px;
        line-height: 1.25;
    }

    .notice {
        margin-top: 8px;
        padding: 7px;
        font-size: 9px;
        line-height: 1.3;
    }
}

@media print and (max-width: 90mm) {
    body {
        font-size: 9px;
    }

    .paper {
        width: 76mm !important;
        max-width: 76mm !important;
    }

    .access9-official-logo {
        max-height: 11mm;
    }

    .access9-official-header h1 {
        font-size: 12px;
    }

    .detail th,
    .detail td {
        font-size: 9px;
        padding: 2px 1px;
    }

    .section {
        margin-top: 9px;
    }

    .grand {
        font-size: 12px;
    }

    .notice {
        font-size: 8.5px;
        padding: 5px;
    }
}

</style>
</head>
<body>
<div class="actions">
    <a href="{{ route('pos.cashier-shifts.report', ['shift' => $shift->id, 'format' => '80mm']) }}">
        Ticket 80 mm
    </a>
    <a href="{{ route('pos.cashier-shifts.report', ['shift' => $shift->id, 'format' => 'carta']) }}">
        Carta / PDF
    </a>
    <button type="button" onclick="window.print()">Imprimir / Guardar PDF</button>
</div>


@php
    /* BEXIA_ACCESS9_FIX5F_LOGO */
    $access9LogoPoint = \Illuminate\Support\Facades\DB::table('pos_points')
        ->where('id', (int) $shift->pos_point_id)
        ->where('company_id', (int) $shift->company_id)
        ->first(['name', 'ticket_logo_path']);

    $access9LogoUrl = null;

    if ($access9LogoPoint) {
        $access9LogoPath = trim(
            (string) ($access9LogoPoint->ticket_logo_path ?? '')
        );

        $access9LogoPath = ltrim($access9LogoPath, '/');

        if (
            $access9LogoPath !== ''
            && \Illuminate\Support\Facades\Storage::disk('public')
                ->exists($access9LogoPath)
        ) {
            $access9LogoUrl =
                \Illuminate\Support\Facades\Storage::disk('public')
                    ->url($access9LogoPath);
        }
    }
@endphp

<main class="paper">
    <div class="access9-official-header">
    @if($access9LogoUrl)
        <img
            class="access9-official-logo"
            src="{{ $access9LogoUrl }}"
            alt="Logotipo del punto de venta"
        >
    @endif
    <div class="access9-official-brand">BEXIA ERP</div>
    <h1>CORTE INDIVIDUAL DE CAJERO</h1>
</div>
    <div class="subtitle">Papelón Florales Secundario</div>

    <table class="meta">
        <tr><td>Turno</td><td>#{{ $shift->id }}</td></tr>
        <tr><td>Cajero</td><td>{{ $employeeName }}</td></tr>
        <tr><td>Estado</td><td>{{ $shift->status === 'closed' ? 'Cerrado' : 'Abierto' }}</td></tr>
        <tr><td>Sesión física</td><td>{{ $shift->pos_session_id }}</td></tr>
        <tr><td>Inicio</td><td>{{ $shift->opened_at }}</td></tr>
        <tr><td>Fin</td><td>{{ $shift->closed_at ?? 'En curso' }}</td></tr>
    </table>

    <div class="section">Cobros por método</div>
    <table class="totals">
        @forelse($methods as $label => $cents)
        <tr>
            <td>{{ $label }}</td>
            <td>${{ number_format($cents / 100, 2) }}</td>
        </tr>
        @empty
        <tr><td colspan="2">Sin cobros registrados</td></tr>
        @endforelse
        <tr class="grand">
            <td>Total cobrado</td>
            <td>${{ number_format($totalCents / 100, 2) }}</td>
        </tr>
    </table>

    <div class="section">Detalle de pagos</div>
    <table class="detail">
        <thead>
            <tr>
                <th>Pago / Ticket</th>
                <th>Concepto de pago</th>
                <th>Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $payment)
            <tr>
                <td>
                    #{{ $payment['id'] }} /
                    {{ $payment['order_id'] }}
                    <div>{{ $payment['label'] }}</div>
                </td>
                <td>{{-- BEXIA_POS_ACCESS9_FIX4B_ES --}}
                    @switch(strtolower(trim((string) $payment['kind'])))
                        @case('settlement')
                            Cobro de ticket
                            @break
                        @case('advance')
                            Anticipo
                            @break
                        @case('refund')
                            Devolución
                            @break
                        @case('pending')
                            Pendiente
                            @break
                        @case('paid')
                            Pagado
                            @break
                        @case('partial')
                            Pago parcial
                            @break
                        @default
                            {{ $payment['kind'] }}
                    @endswitch</td>
                <td>${{ number_format($payment['cents'] / 100, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="3">Sin pagos en este turno</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- BEXIA_ACCESS9_FIX6H_MOVEMENTS --}}
    <div class="section">Movimientos de efectivo</div>

    <table class="detail">
        <thead>
            <tr>
                <th>Folio / Motivo</th>
                <th>Tipo</th>
                <th>Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse($access9CashMovements as $movement)
                <tr>
                    <td>
                        {{ $movement['number'] }}
                        @if($movement['is_advance_refund'])
                            <div>
                                Anticipo:
                                {{ $movement['refund_number'] }}
                            </div>
                        @endif
                        <div>{{ $movement['reason'] }}</div>
                    </td>
                    <td>
                        @if($movement['type'] === 'cash_in')
                            Entrada
                        @elseif($movement['is_advance_refund'])
                            Devolución
                        @else
                            Retiro
                        @endif
                    </td>
                    <td>
                        ${{ number_format(
                            $movement['cents'] / 100,
                            2
                        ) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">
                        Sin movimientos de efectivo
                        atribuidos a este turno
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Total entradas de efectivo</td>
            <td>
                ${{ number_format(
                    $access9CashInCents / 100,
                    2
                ) }}
            </td>
        </tr>
        {{-- BEXIA_ACCESS9_FIX7G_REFUND_BREAKDOWN --}}
        <tr>
            <td>Devoluciones de anticipos en efectivo</td>
            <td>
                ${{ number_format(
                    $access9RefundOutCents / 100,
                    2
                ) }}
            </td>
        </tr>
        <tr>
            <td>Retiros ordinarios</td>
            <td>
                ${{ number_format(
                    $access9OrdinaryOutCents / 100,
                    2
                ) }}
            </td>
        </tr>
        <tr class="grand">
            <td>Total salidas de efectivo</td>
            <td>
                ${{ number_format(
                    $access9CashOutCents / 100,
                    2
                ) }}
            </td>
        </tr>
    </table>

    <div class="access9-cash-note">
        Estos movimientos pertenecen a la caja compartida
        y se muestran por responsabilidad del cajero.
        No se suman ni se restan del total cobrado.
    </div>

    <div class="notice">
        <strong>REPORTE PRELIMINAR.</strong>
        Incluye únicamente cobros y movimientos asociados
        a este turno. Los cobros incluyen anticipos que
        pudieron devolverse posteriormente.
        Las devoluciones se muestran dentro de las salidas
        de efectivo, sin duplicarlas.
        No equivale al arqueo físico de la caja compartida.
    </div>
</main>
</body>
</html>
