@php
    $company = $advance->company;

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
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">

    <title>
        Vale reembolso {{ $advance->number }}
    </title>

    <style>
        @page {
            size: 80mm auto;
            margin: 4mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            width: 72mm;
            margin: 0 auto;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #000;
            background: #fff;
        }

        .center {
            text-align: center;
        }

        .company-logo {
            display: block;
            max-width: 46mm;
            max-height: 18mm;
            width: auto;
            height: auto;
            object-fit: contain;
            margin: 0 auto 5px auto;
        }

        .title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .subtitle {
            font-size: 12px;
            font-weight: 700;
        }

        .line {
            border-top: 1px dashed #000;
            margin: 7px 0;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin: 3px 0;
        }

        .label {
            font-weight: 700;
        }

        .value {
            text-align: right;
        }

        .amount {
            font-size: 17px;
            font-weight: 700;
        }

        .signature {
            margin-top: 24px;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-top: 28px;
            padding-top: 4px;
        }

        .small {
            font-size: 9px;
        }

        @media print {
            body {
                width: 72mm;
            }
        }
    </style>
</head>
<body>

<div class="center">
    @if($logoUrl)
        <img
            src="{{ $logoUrl }}"
            alt="Logo {{ $advance->company?->name ?? 'empresa' }}"
            class="company-logo"
        >
    @endif

    <div class="title">
        {{ $advance->company?->name ?? 'Bexia ERP' }}
    </div>

    <div class="subtitle">
        VALE DE REEMBOLSO
    </div>

    <div>
        {{ $advance->number }}
    </div>
</div>

<div class="line"></div>

<div class="row">
    <span class="label">Fecha:</span>

    <span class="value">
        {{
            $advance->reimbursed_at
                ?->format('d/m/Y H:i')
            ?? $treasuryMovement->posted_at
                ?->format('d/m/Y H:i')
            ?? now()->format('d/m/Y H:i')
        }}
    </span>
</div>

<div class="row">
    <span class="label">Empleado:</span>

    <span class="value">
        {{ $advance->employee?->name ?? '—' }}
    </span>
</div>

<div class="row">
    <span class="label">Caja chica:</span>

    <span class="value">
        {{
            $advance->pettyCashFund?->number
            ?? '—'
        }}
    </span>
</div>

<div class="line"></div>

<div class="row">
    <span class="label">Anticipo:</span>

    <span class="value">
        ${{ number_format((float) $advance->amount, 2) }}
        {{ $advance->currency_code ?: 'MXN' }}
    </span>
</div>

<div class="row">
    <span class="label">Comprobado:</span>

    <span class="value">
        ${{ number_format((float) $advance->reconciled_amount, 2) }}
        {{ $advance->currency_code ?: 'MXN' }}
    </span>
</div>

<div class="line"></div>

<div class="center">
    <div>REEMBOLSO ENTREGADO</div>

    <div class="amount">
        ${{ number_format((float) $treasuryMovement->amount, 2) }}
        {{ $advance->currency_code ?: 'MXN' }}
    </div>
</div>

<div class="line"></div>

<div class="row">
    <span class="label">Pendiente:</span>

    <span class="value">
        $0.00
        {{ $advance->currency_code ?: 'MXN' }}
    </span>
</div>

@if($transferRequest)
    <div class="row">
        <span class="label">Solicitud:</span>

        <span class="value">
            {{ $transferRequest->number }}
        </span>
    </div>
@endif

<div class="row">
    <span class="label">Mov. Tesorería:</span>

    <span class="value">
        #{{ $treasuryMovement->id }}
    </span>
</div>

<div class="line"></div>

<div class="signature">
    <div class="signature-line">
        Entrega
        <br>
        {{
            $advance->pettyCashFund
                ?->employee
                ?->name
            ?? 'Responsable de caja'
        }}
    </div>
</div>

<div class="signature">
    <div class="signature-line">
        Recibe
        <br>
        {{ $advance->employee?->name ?? 'Empleado' }}
    </div>
</div>

<div class="signature">
    <div class="signature-line">
        Autoriza
        <br>
        {{
            $advance->authorizedByEmployee?->name
            ?? '—'
        }}
    </div>
</div>

<div class="line"></div>

<div class="center small">
    Reembolso correspondiente a gastos
    comprobados por encima del anticipo.
</div>

<script>
    window.addEventListener('load', function () {
        window.print();
    });
</script>

</body>
</html>
