@php
    /** @var \App\Models\ExpenseAdvance $record */
    /** @var \App\Models\TreasuryMovement $movement */

    $company = $record->company;
    $fund = $record->pettyCashFund;

    $companyName = trim(
        (string) (
            $company?->name
            ?? $company?->business_name
            ?? 'Empresa'
        )
    );

    $logoUrl = null;

    if ($company) {
        if (method_exists($company, 'getLogoUrl')) {
            $logoUrl = $company->getLogoUrl();
        }

        if (
            ! $logoUrl
            && filled($company->logo_path ?? null)
        ) {
            $logoUrl =
                \Illuminate\Support\Facades\Storage::disk('public')
                    ->url($company->logo_path);
        }
    }

    $employee =
        $record->employee?->name
        ?? '____________________________';

    $receivedBy =
        $returnedBy?->name
        ?? '____________________________';

    $advanceAmount = number_format(
        (float) $record->amount,
        2,
        '.',
        ','
    );

    $reconciledAmount = number_format(
        (float) $record->reconciled_amount,
        2,
        '.',
        ','
    );

    $returnedAmount = number_format(
        (float) $movement->amount,
        2,
        '.',
        ','
    );

    $returnedAt =
        $record->returned_at
            ?->format('d/m/Y H:i')
        ?? optional($movement->posted_at)
            ?->format('d/m/Y H:i')
        ?? '—';
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">

    <title>
        Devolución {{ $record->number }}
    </title>

    <style>
        @page {
            size: 80mm auto;
            margin: 4mm;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            background: #fff;
        }

        body {
            width: 72mm;
            margin: 0 auto;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10.5px;
            line-height: 1.3;
        }

        .ticket {
            width: 72mm;
            padding: 1mm 0 4mm;
        }

        .center {
            text-align: center;
        }

        .logo {
            display: block;
            max-width: 42mm;
            max-height: 16mm;
            width: auto;
            height: auto;
            object-fit: contain;
            margin: 0 auto 1.5mm;
        }

        .brand {
            font-size: 15px;
            font-weight: 900;
            line-height: 1.05;
            text-align: center;
        }

        .document-title {
            margin-top: 4mm;
            padding: 2.5mm 0;
            border-top: 1.5px solid #111;
            border-bottom: 1.5px solid #111;
            text-align: center;
            font-size: 13px;
            font-weight: 900;
            letter-spacing: .3px;
        }

        .folio {
            margin-top: 2mm;
            text-align: center;
            font-size: 12px;
            font-weight: 900;
        }

        .section {
            margin-top: 3mm;
        }

        .section-title {
            margin-bottom: 1mm;
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: #333;
        }

        .line {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 3mm;
            padding: .6mm 0;
        }

        .line .label {
            font-weight: 700;
            flex: 0 0 31mm;
        }

        .line .value {
            flex: 1;
            text-align: right;
            font-weight: 500;
        }

        .divider {
            margin-top: 2.5mm;
            border-top: 1px dashed #888;
        }

        .amount-box {
            margin-top: 4mm;
            padding: 3mm 1mm;
            border-top: 2px solid #111;
            border-bottom: 2px solid #111;
            text-align: center;
        }

        .amount-label {
            font-size: 9px;
            font-weight: 900;
            letter-spacing: .5px;
        }

        .amount {
            margin-top: 1mm;
            font-size: 24px;
            line-height: 1;
            font-weight: 900;
        }

        .currency {
            margin-top: 1mm;
            font-size: 10px;
            font-weight: 700;
        }

        .zero-box {
            margin-top: 3mm;
            padding: 2mm;
            border: 1.5px solid #111;
            text-align: center;
            font-size: 11px;
            font-weight: 900;
        }

        .signatures {
            margin-top: 5mm;
        }

        .signature {
            margin-top: 9mm;
            text-align: center;
        }

        .signature-name {
            min-height: 4mm;
            margin-bottom: 5mm;
            font-size: 10px;
            font-weight: 700;
        }

        .signature-line {
            border-top: 1px solid #111;
            padding-top: 1mm;
            font-size: 9px;
            font-weight: 800;
        }

        .footer {
            margin-top: 5mm;
            padding-top: 2mm;
            border-top: 1px dashed #777;
            text-align: center;
            font-size: 8px;
            line-height: 1.25;
        }

        @media print {
            html,
            body,
            .ticket {
                width: 72mm;
            }
        }
    </style>
</head>

<body>
    <main class="ticket">

        @if($logoUrl)
            <img
                src="{{ $logoUrl }}"
                alt="Logo"
                class="logo"
            >
        @endif

        <div class="brand">
            {{ $companyName }}
        </div>

        <div class="document-title">
            RECIBO DE DEVOLUCIÓN
            <br>
            DE ANTICIPO
        </div>

        <div class="folio">
            {{ $record->number }}
        </div>

        <div class="section">
            <div class="section-title">
                Datos de la devolución
            </div>

            <div class="line">
                <div class="label">
                    Fecha
                </div>

                <div class="value">
                    {{ $returnedAt }}
                </div>
            </div>

            <div class="line">
                <div class="label">
                    Caja chica
                </div>

                <div class="value">
                    {{ $fund?->number ?? '—' }}
                </div>
            </div>

            <div class="line">
                <div class="label">
                    Empleado
                </div>

                <div class="value">
                    {{ $employee }}
                </div>
            </div>

            <div class="line">
                <div class="label">
                    Movimiento
                </div>

                <div class="value">
                    #{{ $movement->id }}
                </div>
            </div>
        </div>

        <div class="divider"></div>

        <div class="section">
            <div class="section-title">
                Conciliación
            </div>

            <div class="line">
                <div class="label">
                    Anticipo entregado
                </div>

                <div class="value">
                    ${{ $advanceAmount }}
                </div>
            </div>

            <div class="line">
                <div class="label">
                    Gastos comprobados
                </div>

                <div class="value">
                    ${{ $reconciledAmount }}
                </div>
            </div>
        </div>

        <div class="amount-box">
            <div class="amount-label">
                EFECTIVO DEVUELTO
            </div>

            <div class="amount">
                ${{ $returnedAmount }}
            </div>

            <div class="currency">
                {{ $record->currency_code ?: 'MXN' }}
            </div>
        </div>

        <div class="zero-box">
            SALDO PENDIENTE: $0.00
        </div>

        <div class="signatures">
            <div class="signature">
                <div class="signature-name">
                    {{ $employee }}
                </div>

                <div class="signature-line">
                    ENTREGÓ EFECTIVO
                </div>
            </div>

            <div class="signature">
                <div class="signature-name">
                    {{ $receivedBy }}
                </div>

                <div class="signature-line">
                    RECIBIÓ DEVOLUCIÓN
                </div>
            </div>
        </div>

        <div class="footer">
            Este documento acredita la devolución
            del sobrante correspondiente al anticipo
            {{ $record->number }}.
            <br>
            Movimiento de Tesorería:
            #{{ $movement->id }}
        </div>
    </main>

    <script>
        window.addEventListener('load', () => {
            setTimeout(() => {
                window.print();
            }, 250);
        });
    </script>
</body>
</html>
