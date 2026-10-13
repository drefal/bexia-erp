{{-- BEXIA_V5836_POS_REFUND_FIX2B8P9D2 --}}
@php
    $detail = $refundLedger ?? [
        'rows' => [],
        'total' => 0,
        'cash' => 0,
        'external' => 0,
        'pending' => 0,
    ];
    $moneyRefund = fn ($v) => '$' . number_format((float) $v, 2);
@endphp

@if(! empty($detail['rows']))
    <h3>Detalle de movimientos de devolución</h3>
    <table style="width:100%;border-collapse:collapse;font-size:10px;">
        <thead>
            <tr>
                <th style="text-align:left;">Folio / Fecha / Ticket</th>
                <th style="text-align:left;">Reembolsos</th>
                <th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detail['rows'] as $refund)
                <tr>
                    <td style="vertical-align:top;">
                        <strong>{{ $refund['number'] }}</strong>
                        <div>{{ $refund['created_at'] }}</div>
                        <div>Venta: {{ $refund['ticket_number'] }}</div>
                        <div>{{ $refund['user'] }}</div>
                    </td>
                    <td style="vertical-align:top;">
                        @foreach($refund['payments'] as $payment)
                            <div>
                                {{ $payment['method'] }}:
                                {{ $moneyRefund($payment['amount']) }}
                                ({{ $payment['status'] === 'pending' ? 'Pendiente' : 'Reembolsado' }})
                            </div>
                            @if($payment['confirmed_at'] !== '')
                                <div>
                                    Confirmado: {{ $payment['confirmed_at'] }}
                                    @if($payment['confirmed_by'] !== '')
                                        / Usuario #{{ $payment['confirmed_by'] }}
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </td>
                    <td style="text-align:right;vertical-align:top;">
                        {{ $moneyRefund($refund['amount']) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width:100%;font-size:10px;">
        <tr>
            <td>Efectivo reembolsado</td>
            <td style="text-align:right;">{{ $moneyRefund($detail['cash']) }}</td>
        </tr>
        <tr>
            <td>Reembolsos externos confirmados</td>
            <td style="text-align:right;">{{ $moneyRefund($detail['external']) }}</td>
        </tr>
        <tr>
            <td>Reembolsos pendientes</td>
            <td style="text-align:right;">{{ $moneyRefund($detail['pending']) }}</td>
        </tr>
        <tr>
            <td><strong>Total de devoluciones</strong></td>
            <td style="text-align:right;">
                <strong>{{ $moneyRefund($detail['total']) }}</strong>
            </td>
        </tr>
    </table>
    <div style="font-size:9px;">
        Desglose informativo. Los importes ya están incluidos
        en los totales del corte y no se descuentan nuevamente.
    </div>
@endif
