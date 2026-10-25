<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $typeLabel }} {{ $movement->number }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 4mm;
        }

        body {
            width: 72mm;
            margin: 0 auto;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #111;
        }

        .copy {
            padding: 8px 0 14px;
            border-bottom: 1px dashed #000;
            margin-bottom: 12px;
        }

        .center {
            text-align: center;
        }

        .title {
            font-weight: 900;
            font-size: 14px;
            margin: 4px 0;
        }

        .line {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin: 3px 0;
        }

        .amount {
            text-align: center;
            font-size: 18px;
            font-weight: 900;
            margin: 10px 0;
        }

        .sign {
            margin-top: 28px;
            border-top: 1px solid #000;
            padding-top: 4px;
            text-align: center;
            font-size: 10px;
        }

        .small {
            font-size: 10px;
        }

        @media print {
            body {
                width: 72mm;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    @for($i = 1; $i <= 2; $i++)
        <div class="copy">
            <div class="center">
                <div><strong>{{ $companyName }}</strong></div>
                <div>{{ $posName }}</div>
                <div class="title">{{ $typeLabel }}</div>
                <div>{{ $movement->number }}</div>
                <div class="small">Copia {{ $i }} de 2</div>
            </div>

            <div class="amount">${{ number_format((float) $movement->amount, 2) }}</div>

            {{-- BEXIA_V5836_CASH_DENOMS_FIX1_TICKET --}}
            @php
                $v5836Meta = json_decode(
                    (string) ($movement->metadata ?? ''),
                    true
                );
                $v5836Meta = is_array($v5836Meta) ? $v5836Meta : [];
                $v5836Rows = $v5836Meta['cash_denominations'] ?? [];
                $v5836Rows = is_array($v5836Rows) ? $v5836Rows : [];
            @endphp

            @if((string) $movement->type === 'cash_out')
                <div style="margin-top:10px;font-weight:900">
                    DESGLOSE DEL RETIRO
                </div>

                @forelse($v5836Rows as $v5836Line)
                    @php
                        $v5836Value = (float) ($v5836Line['value'] ?? 0);
                        $v5836Qty = (int) ($v5836Line['quantity'] ?? 0);
                        $v5836Kind = (string) ($v5836Line['type'] ?? '');
                    @endphp
                    <div class="line">
                        <span>
                            {{ $v5836Kind === 'coin' ? 'Moneda' : 'Billete' }}
                            ${{ number_format($v5836Value, 2) }}
                            x {{ $v5836Qty }}
                        </span>
                        <strong>
                            ${{ number_format($v5836Value * $v5836Qty, 2) }}
                        </strong>
                    </div>
                @empty
                    <div class="small">
                        Retiro historico sin desglose de denominaciones.
                    </div>
                @endforelse
            @endif

            <div class="line">
                <span>Sesión:</span>
                <strong>{{ $session->number ?? ('#'.$session->id) }}</strong>
            </div>

            <div class="line">
                <span>Fecha:</span>
                <strong>{{ $movement->movement_at }}</strong>
            </div>

            <div class="line">
                <span>Realiza:</span>
                <strong>{{ $movement->performed_by_name }}</strong>
            </div>

            <div class="line">
                <span>Supervisor:</span>
                <strong>{{ $movement->supervisor_name }}</strong>
            </div>

            <div style="margin-top:8px;">
                <strong>Motivo:</strong><br>
                {{ $movement->reason }}
            </div>

            @if(! empty($movement->notes))
                <div style="margin-top:8px;">
                    <strong>Notas:</strong><br>
                    {{ $movement->notes }}
                </div>
            @endif

            <div class="sign">Firma cajero</div>
            <div class="sign">Firma supervisor</div>
        </div>
    @endfor

    <div class="center no-print">
        <button onclick="window.print()">Imprimir</button>
    </div>

    <script>
        window.addEventListener('load', function () {
            window.setTimeout(function () {
                window.print();
            }, 350);
        });
    </script>
</body>
</html>
