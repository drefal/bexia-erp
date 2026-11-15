<div class="page-copy">
    <table class="header">
        <tr>
            <td style="width:52%;">
                @if ($logo)
                    <img class="logo" src="{{ $logo }}" alt="Papelón">
                @else
                    <div class="brand">Papelón</div>
                    <div class="muted">Papelería y Globos</div>
                @endif
            </td>
            <td style="width:48%;">
                <div class="copy-title">{{ $copyTitle }}</div>
                <div class="folio">{{ $order->number }}</div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td style="width:50%;">
                <div class="info-label">Cliente</div>
                <div>
                    Nombre: {{ $order->customer_name_snapshot }}
                </div>
                <div>
                    WhatsApp: {{ $order->customer_phone_snapshot }}
                </div>
            </td>
            <td style="width:50%;">
                <div class="info-label">Entrega</div>
                <div>Fecha: {{ $deliveryLocal }}</div>
                <div class="muted">Capturado: {{ $createdLocal }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">
        Productos
        <span style="float:right;font-size:8pt;">
            Piezas:
            {{ number_format(collect($lines)->sum('quantity'), 0) }}
        </span>
    </div>

    @foreach ($lines as $index => $line)
        <table class="line">
            <tr>
                <td class="photo-cell">
                    @if ($line['image'])
                        <img class="photo" src="{{ $line['image'] }}">
                    @else
                        <div class="muted">Sin imagen</div>
                    @endif
                </td>
                <td>
                    <strong>
                        {{ $index + 1 }}.
                        {{ $line['name'] }}
                    </strong>
                    @if ($line['size'])
                        <div class="muted">
                            Tamaño: {{ $line['size'] }}
                            @if ($line['inflation'])
                                · {{ $line['inflation'] }}
                            @endif
                        </div>
                    @endif
                    @if ($line['design'])
                        <div class="muted">
                            Diseño: {{ $line['design'] }}
                        </div>
                    @endif
                    @if ($line['personalization'])
                        <div class="muted">
                            Texto: {{ $line['personalization'] }}
                        </div>
                    @endif
                </td>
                <td class="money" style="width:85pt;">
                    {{ number_format($line['quantity'], 0) }}
                    × ${{ number_format($line['unit_price'], 2) }}
                    <div>
                        <strong>
                            ${{ number_format($line['subtotal'], 2) }}
                        </strong>
                    </div>
                </td>
            </tr>
        </table>
    @endforeach

    @if ($order->notes)
        <div class="note">
            <strong>Observaciones:</strong>
            {{ $order->notes }}
        </div>
    @endif

    <table class="summary">
        <tr>
            <td><strong>Total</strong></td>
            <td class="money">
                <strong>${{ number_format($order->total, 2) }}</strong>
            </td>
        </tr>
        @if (! $order->pos_order_id)
            <tr>
                <td>Anticipo registrado</td>
                <td class="money">
                    ${{ number_format($order->deposit_amount, 2) }}
                </td>
            </tr>
            <tr>
                <td><strong>Saldo</strong></td>
                <td class="money">
                    <strong>
                        ${{ number_format($order->balance_amount, 2) }}
                    </strong>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="muted">
                    Pendiente de enviar al PDV. Sin pagos en caja registrados.
                </td>
            </tr>
        @else
            <tr>
                <td colspan="2" class="muted">
                    Ticket PDV: {{ $order->pos_order_id }}.
                    Consultar los cobros y el saldo actualizado en caja.
                </td>
            </tr>
        @endif
    </table>

    <div class="disclaimer">
        Las imágenes son una referencia del diseño solicitado.
        Cada arreglo se prepara de forma personalizada,
        por lo que puede presentar pequeñas variaciones
        en colores, acomodo, proporciones o detalles.
    </div>

    <div class="signature">
        Firma recibe: ______________________________
    </div>

    <div class="muted" style="margin-top:12pt;">
        Impreso: {{ $printedLocal }}
    </div>
</div>
