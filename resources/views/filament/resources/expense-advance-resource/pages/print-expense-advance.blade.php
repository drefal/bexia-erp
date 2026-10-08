@php
    /** @var \App\Models\ExpenseAdvance $record */

    $record->loadMissing([
        'company',
        'employee',
        'pettyCashFund.employee',
        'authorizedByEmployee',
        'approvedBy',
    ]);

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

    $giver =
        $fund?->employee?->name
        ?? '____________________________';

    $receiver =
        $record->employee?->name
        ?? '____________________________';

    /*
     * El nombre impreso como AUTORIZÓ es un empleado,
     * no el usuario técnico que aprobó en Bexia.
     */
    $authorizer =
        $record->authorizedByEmployee?->name
        ?? '____________________________';

    $amount = number_format(
        (float) $record->amount,
        2,
        '.',
        ','
    );

    $requestDate =
        $record->request_date
            ?->format('d/m/Y')
        ?? '—';

    $dueDate =
        $record->due_date
            ?->format('d/m/Y')
        ?? '—';

    $deliveredAt =
        $record->delivered_at
            ?->format('d/m/Y H:i')
        ?? '—';
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">

    <title>{{ $record->number }}</title>

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
            font-size: 14px;
            font-weight: 900;
            letter-spacing: .4px;
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
            flex: 0 0 30mm;
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

        .employee-box {
            margin-top: 1mm;
            padding: 2mm 0;
            font-size: 11px;
            font-weight: 800;
        }

        .purpose-box {
            margin-top: 1mm;
            padding: 2mm;
            border: 1px solid #999;
            border-radius: 2px;
            white-space: pre-wrap;
            min-height: 10mm;
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

        .deadline-box {
            margin-top: 3mm;
            padding: 2.2mm;
            border: 1.5px solid #111;
        }

        .deadline-title {
            text-align: center;
            font-size: 9px;
            font-weight: 900;
            margin-bottom: 1mm;
        }

        .deadline-date {
            text-align: center;
            font-size: 14px;
            font-weight: 900;
            margin-top: 1mm;
        }

        .signatures {
            margin-top: 5mm;
        }

        .signature {
            margin-top: 8mm;
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
            VALE DE ANTICIPO
        </div>

        <div class="folio">
            {{ $record->number }}
        </div>

        <div class="section">
            <div class="section-title">
                Datos del movimiento
            </div>

            <div class="line">
                <div class="label">
                    Fecha solicitud
                </div>

                <div class="value">
                    {{ $requestDate }}
                </div>
            </div>

            <div class="line">
                <div class="label">
                    Fecha entrega
                </div>

                <div class="value">
                    {{ $deliveredAt }}
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
                    Nombre caja
                </div>

                <div class="value">
                    {{ $fund?->name ?? '—' }}
                </div>
            </div>
        </div>

        <div class="divider"></div>

        <div class="section">
            <div class="section-title">
                Recibe
            </div>

            <div class="employee-box">
                {{ $receiver }}
            </div>
        </div>

        <div class="section">
            <div class="section-title">
                Motivo / propósito
            </div>

            <div class="purpose-box">
                {{ $record->purpose }}
            </div>
        </div>

        @if(filled($record->notes))
            <div class="section">
                <div class="section-title">
                    Notas
                </div>

                <div class="purpose-box">
                    {{ $record->notes }}
                </div>
            </div>
        @endif

        <div class="amount-box">
            <div class="amount-label">
                IMPORTE ENTREGADO
            </div>

            <div class="amount">
                ${{ $amount }}
            </div>

            <div class="currency">
                {{ $record->currency_code ?: 'MXN' }}
            </div>
        </div>

        <div class="deadline-box">
            <div class="deadline-title">
                PLAZO PARA COMPROBAR
            </div>

            <div class="center">
                {{ $record->due_days }} días
            </div>

            <div class="deadline-date">
                {{ $dueDate }}
            </div>
        </div>

        <div class="signatures">

            <div class="signature">
                <div class="signature-name">
                    {{ $giver }}
                </div>

                <div class="signature-line">
                    Entregó / Firma
                </div>
            </div>

            <div class="signature">
                <div class="signature-name">
                    {{ $receiver }}
                </div>

                <div class="signature-line">
                    Recibió / Firma
                </div>
            </div>

            <div class="signature">
                <div class="signature-name">
                    {{ $authorizer }}
                </div>

                <div class="signature-line">
                    Autorizó / Firma
                </div>
            </div>

        </div>

        <div class="footer">
            Anticipo pendiente de comprobación.
            <br>
            Conserva este vale como respaldo de la entrega.
        </div>

    </main>

    <script>
        window.addEventListener(
            'load',
            function () {
                window.setTimeout(
                    function () {
                        window.print();
                    },
                    250
                );
            }
        );
    </script>
</body>
</html>
