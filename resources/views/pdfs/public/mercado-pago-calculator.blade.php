<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">

    <title>
        Simulación de pagos - {{ $company->name }}
    </title>

    <style>
        @page {
            margin: 30px 36px 32px;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #172033;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
        }

        .header-table td {
            vertical-align: middle;
        }

        .company-logo {
            max-width: 150px;
            max-height: 66px;
        }

        .title-cell {
            text-align: right;
        }

        .company-name {
            margin: 0 0 3px;
            font-size: 9px;
            color: #667085;
        }

        h1 {
            margin: 0;
            font-size: 22px;
            line-height: 1.15;
        }

        .subtitle {
            margin-top: 5px;
            font-size: 10px;
            color: #667085;
        }

        .summary {
            margin-bottom: 20px;
            border: 1px solid #dfe3e8;
            border-radius: 7px;
            background: #f8fafc;
            padding: 14px 16px;
        }

        .summary-label {
            font-size: 9px;
            color: #667085;
        }

        .amount {
            margin-top: 3px;
            font-size: 24px;
            font-weight: bold;
        }

        .date {
            margin-top: 7px;
            font-size: 9px;
            color: #667085;
        }

        table.results {
            width: 100%;
            border-collapse: collapse;
        }

        .results th {
            background: #172033;
            color: #ffffff;
            padding: 9px 8px;
            font-size: 9px;
            text-align: center;
        }

        .results td {
            padding: 10px 8px;
            border-bottom: 1px solid #e4e7ec;
            vertical-align: middle;
        }

        .results td.center {
            text-align: center;
        }

        .results td.money {
            text-align: right;
            font-size: 11px;
        }

        .monthly {
            font-weight: bold;
            font-size: 13px;
        }

        .notice {
            margin-top: 20px;
            padding: 11px 13px;
            background: #f8fafc;
            border-left: 3px solid #98a2b3;
            color: #667085;
            line-height: 1.5;
            font-size: 8.5px;
        }

        .footer-table {
            width: 100%;
            margin-top: 24px;
            border-collapse: collapse;
        }

        .footer-table td {
            vertical-align: middle;
        }

        .footer-text {
            color: #98a2b3;
            font-size: 8px;
        }

        .bexia-cell {
            text-align: right;
        }

        .bexia-logo {
            max-width: 145px;
            max-height: 48px;
        }
    </style>
</head>

<body>

<table class="header-table">
    <tr>
        <td style="width: 34%;">
            @if ($companyLogoDataUri)
                <img
                    class="company-logo"
                    src="{{ $companyLogoDataUri }}"
                    alt="{{ $company->name }}"
                >
            @endif
        </td>

        <td class="title-cell">
            <div class="company-name">
                {{ $company->name }}
            </div>

            <h1>Simulación de pagos</h1>

            <div class="subtitle">
                Mercado Pago
            </div>
        </td>
    </tr>
</table>

<div class="summary">
    <div class="summary-label">
        Monto de referencia
    </div>

    <div class="amount">
        ${{ number_format($amount, 2, '.', ',') }}
    </div>

    <div class="date">
        Generado:
        {{ $generatedAt->format('d/m/Y H:i') }}
    </div>
</div>

<table class="results">
    <thead>
        <tr>
            <th style="width: 17%;">Plazo</th>
            <th style="width: 18%;">Recargo</th>
            <th style="width: 32%;">
                Pago mensual aproximado
            </th>
            <th style="width: 33%;">
                Total a pagar
            </th>
        </tr>
    </thead>

    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td class="center">
                    <strong>
                        {{ $row['months'] }} meses
                    </strong>
                </td>

                <td class="center">
                    {{ number_format(
                        $row['rate'],
                        4,
                        '.',
                        ','
                    ) }}%
                </td>

                <td class="money monthly">
                    ${{ number_format(
                        $row['monthly'],
                        2,
                        '.',
                        ','
                    ) }}
                </td>

                <td class="money">
                    ${{ number_format(
                        $row['total'],
                        2,
                        '.',
                        ','
                    ) }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="notice">
    Esta simulación es únicamente informativa.
    Las mensualidades mostradas son aproximadas.
    El total a pagar es el importe de referencia.
    Los planes y condiciones pueden cambiar conforme
    a la configuración vigente en Bexia ERP.
</div>

<table class="footer-table">
    <tr>
        <td class="footer-text">
            Calculado con los planes públicos vigentes
            de {{ $company->name }}.
        </td>

        <td class="bexia-cell">
            @if ($bexiaLogoDataUri)
                <img
                    class="bexia-logo"
                    src="{{ $bexiaLogoDataUri }}"
                    alt="Bexia ERP"
                >
            @else
                Bexia ERP
            @endif
        </td>
    </tr>
</table>

</body>
</html>
