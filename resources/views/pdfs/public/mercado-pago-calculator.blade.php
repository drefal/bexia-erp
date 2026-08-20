<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">

    <title>
        Simulación de pagos -
        {{ $company->name }}
    </title>

    <style>
        @page {
            margin: 13px 16px;
        }

        body {
            margin: 0;
            font-family:
                DejaVu Sans,
                sans-serif;
            font-size: 6.3px;
            color: #172033;
        }

        .pdf-page {
            width: 100%;
        }

        .page-break {
            page-break-after: always;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        .header td {
            vertical-align: middle;
        }

        .company-logo {
            max-width: 95px;
            max-height: 38px;
        }

        .title {
            text-align: right;
        }

        .title-company {
            color: #667085;
            font-size: 5.7px;
        }

        .title h1 {
            margin: 1px 0 0;
            font-size: 14px;
            line-height: 1.05;
        }

        .subtitle {
            margin-top: 1px;
            color: #667085;
            font-size: 6px;
        }

        .summary {
            margin-bottom: 4px;
            padding: 5px 7px;
            border: 1px solid #dfe3e8;
            background: #f8fafc;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table td {
            vertical-align: middle;
        }

        .summary-label {
            color: #667085;
            font-size: 5.7px;
        }

        .summary-amount {
            margin-top: 1px;
            font-size: 13px;
            font-weight: bold;
        }

        .summary-meta {
            text-align: right;
            color: #667085;
            font-size: 5.4px;
            line-height: 1.35;
        }

        /*
         * BEXIA_MP_PDF_SIX_CARDS_V5_83_2A5
         *
         * 2 columnas x 3 filas = 6 planes por hoja.
         */
        .cards-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 4px 4px;
            table-layout: fixed;
        }

        .grid-cell {
            width: 50%;
            vertical-align: top;
        }

        /*
         * BEXIA_MP_PDF_FULL_LAST_ROW_V5_83_2A6
         *
         * Si en una fila queda un solo plazo,
         * ocupa las dos columnas.
         */
        .grid-cell-full {
            width: 100%;
            vertical-align: top;
        }

        .term-card {
            width: 100%;
            border: 1px solid #dfe3e8;
            border-collapse: collapse;
            background: #fff;
            page-break-inside: avoid;
        }

        .term-title {
            padding: 4px 6px;
            background: #f9fafb;
            border-bottom: 1px solid #eaecf0;
            font-weight: bold;
            font-size: 7.8px;
        }

        .card-columns {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .card-columns > tbody > tr > td {
            width: 50%;
            vertical-align: top;
            padding: 5px 6px;
        }

        .card-columns > tbody > tr > td + td {
            border-left: 1px solid #eaecf0;
        }

        .card-type {
            color: #475467;
            font-size: 5.4px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .main-payment {
            margin-top: 2px;
            font-size: 11.5px;
            line-height: 1.05;
            font-weight: bold;
        }

        .payment-note {
            margin-top: 1px;
            color: #667085;
            font-size: 5px;
        }

        .block {
            margin-top: 4px;
            padding-top: 3px;
            border-top: 1px solid #eaecf0;
        }

        .line,
        .fee-line {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1px;
        }

        .line td,
        .fee-line td {
            padding: 1px 0;
            vertical-align: top;
            line-height: 1.25;
        }

        .line td:first-child,
        .fee-line td:first-child {
            width: 44%;
            color: #667085;
        }

        .line td:last-child,
        .fee-line td:last-child {
            width: 56%;
            text-align: right;
        }

        .line td:last-child {
            font-weight: bold;
        }

        .fees {
            margin-top: 3px;
            padding-top: 3px;
            border-top: 1px dashed #dfe3e8;
        }

        .fee-value {
            white-space: nowrap;
        }

        .fee-percent,
        .fee-money {
            display: block;
            line-height: 1.2;
        }

        .fee-money {
            margin-top: 1px;
        }

        .fee-total td {
            padding-top: 2px;
            color: #172033;
            font-weight: bold;
        }

        .footer-note {
            margin-top: 3px;
            padding: 3px 5px;
            border-left: 2px solid #98a2b3;
            background: #f8fafc;
            color: #667085;
            font-size: 4.9px;
            line-height: 1.3;
        }

        .footer {
            width: 100%;
            margin-top: 3px;
            border-collapse: collapse;
        }

        .footer td {
            vertical-align: middle;
        }

        .footer-text {
            color: #98a2b3;
            font-size: 4.8px;
        }

        .bexia {
            text-align: right;
        }

        .bexia-logo {
            max-width: 75px;
            max-height: 23px;
        }
    </style>
</head>

<body>

@php
    /*
     * Cada grupo representa una hoja.
     */
    $pages = collect($rows)->chunk(6);

    $selectedLabels = collect($rows)
        ->pluck('months')
        ->map(
            fn ($months) =>
                (int) $months === 1
                    ? '1 pago'
                    : $months . ' meses'
        )
        ->implode(', ');
@endphp

@foreach ($pages as $pageRows)
    <div
        class="pdf-page {{
            ! $loop->last
                ? 'page-break'
                : ''
        }}"
    >
        <table class="header">
            <tr>
                <td>
                    @if ($companyLogoDataUri)
                        <img
                            class="company-logo"
                            src="{{ $companyLogoDataUri }}"
                            alt="{{ $company->name }}"
                        >
                    @endif
                </td>

                <td class="title">
                    <div class="title-company">
                        {{ $company->name }}
                    </div>

                    <h1>
                        Simulación de pagos
                    </h1>

                    <div class="subtitle">
                        Mercado Pago
                    </div>
                </td>
            </tr>
        </table>

        <div class="summary">
            <table class="summary-table">
                <tr>
                    <td>
                        <div class="summary-label">
                            {{
                                $mode === 'receive'
                                    ? 'Monto que se desea recibir'
                                    : 'Monto que se cobrará'
                            }}
                        </div>

                        <div class="summary-amount">
                            ${{
                                number_format(
                                    $amount,
                                    2,
                                    '.',
                                    ','
                                )
                            }}
                        </div>
                    </td>

                    <td class="summary-meta">
                        {{
                            $mode === 'receive'
                                ? 'Cálculo desde monto neto recibido'
                                : 'Cálculo desde monto cobrado'
                        }}

                        <br>

                        Planes:
                        {{ $selectedLabels }}

                        <br>

                        {{
                            $generatedAt->format(
                                'd/m/Y H:i'
                            )
                        }}
                    </td>
                </tr>
            </table>
        </div>

        <table class="cards-grid">
            @foreach (
                collect($pageRows)->chunk(2)
                as $rowChunk
            )
                <tr>
                    @foreach ($rowChunk as $row)
                        <td
                            class="{{
                                $rowChunk->count() === 1
                                    ? 'grid-cell-full'
                                    : 'grid-cell'
                            }}"
                            {{
                                $rowChunk->count() === 1
                                    ? 'colspan=2'
                                    : ''
                            }}
                        >
                            <table class="term-card">
                                <tr>
                                    <td
                                        class="term-title"
                                        colspan="2"
                                    >
                                        {{
                                            $row['months'] === 1
                                                ? '1 pago'
                                                : $row['months'] .
                                                    ' meses'
                                        }}
                                    </td>
                                </tr>

                                <tr>
                                    <td
                                        colspan="2"
                                        style="padding:0;"
                                    >
                                        <table class="card-columns">
                                            <tr>
                                                @foreach ([
                                                    'credit' =>
                                                        'Crédito',
                                                    'debit' =>
                                                        'Débito',
                                                ] as $key => $label)
                                                    @php
                                                        $data =
                                                            $row[$key];

                                                        $paymentNote =
                                                            $row[
                                                                'months'
                                                            ] === 1
                                                                ? 'pago único'
                                                                : 'mensualidad aproximada';
                                                    @endphp

                                                    <td>
                                                        <div class="card-type">
                                                            {{ $label }}
                                                        </div>

                                                        <div class="main-payment">
                                                            ${{
                                                                number_format(
                                                                    $data[
                                                                        'payment'
                                                                    ],
                                                                    2,
                                                                    '.',
                                                                    ','
                                                                )
                                                            }}
                                                        </div>

                                                        <div class="payment-note">
                                                            {{
                                                                $paymentNote
                                                            }}
                                                        </div>

                                                        <div class="block">
                                                            <table class="line">
                                                                <tr>
                                                                    <td>
                                                                        Cobrar
                                                                    </td>

                                                                    <td>
                                                                        ${{
                                                                            number_format(
                                                                                $data[
                                                                                    'charged'
                                                                                ],
                                                                                2,
                                                                                '.',
                                                                                ','
                                                                            )
                                                                        }}
                                                                    </td>
                                                                </tr>

                                                                <tr>
                                                                    <td>
                                                                        Recibir
                                                                    </td>

                                                                    <td>
                                                                        ${{
                                                                            number_format(
                                                                                $data[
                                                                                    'received'
                                                                                ],
                                                                                2,
                                                                                '.',
                                                                                ','
                                                                            )
                                                                        }}
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>

                                                        <div class="fees">
                                                            <table class="fee-line">
                                                                <tr>
                                                                    <td>
                                                                        Desliz.
                                                                    </td>

                                                                    <td class="fee-value">
                                                                        <span class="fee-percent">
                                                                            {{
                                                                                number_format(
                                                                                    $data[
                                                                                        'swipe'
                                                                                    ],
                                                                                    4,
                                                                                    '.',
                                                                                    ','
                                                                                )
                                                                            }}%
                                                                        </span>

                                                                        <span class="fee-money">
                                                                            ${{
                                                                                number_format(
                                                                                    $data[
                                                                                        'swipe_amount'
                                                                                    ],
                                                                                    2,
                                                                                    '.',
                                                                                    ','
                                                                                )
                                                                            }}
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            </table>

                                                            <table class="fee-line">
                                                                <tr>
                                                                    <td>
                                                                        Financ.
                                                                    </td>

                                                                    <td class="fee-value">
                                                                        <span class="fee-percent">
                                                                            {{
                                                                                number_format(
                                                                                    $data[
                                                                                        'financing'
                                                                                    ],
                                                                                    4,
                                                                                    '.',
                                                                                    ','
                                                                                )
                                                                            }}%
                                                                        </span>

                                                                        <span class="fee-money">
                                                                            ${{
                                                                                number_format(
                                                                                    $data[
                                                                                        'financing_amount'
                                                                                    ],
                                                                                    2,
                                                                                    '.',
                                                                                    ','
                                                                                )
                                                                            }}
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            </table>

                                                            <table class="fee-line fee-total">
                                                                <tr>
                                                                    <td>
                                                                        Total
                                                                    </td>

                                                                    <td class="fee-value">
                                                                        <span class="fee-percent">
                                                                            {{
                                                                                number_format(
                                                                                    $data[
                                                                                        'rate'
                                                                                    ],
                                                                                    4,
                                                                                    '.',
                                                                                    ','
                                                                                )
                                                                            }}%
                                                                        </span>

                                                                        <span class="fee-money">
                                                                            ${{
                                                                                number_format(
                                                                                    $data[
                                                                                        'fee_amount'
                                                                                    ],
                                                                                    2,
                                                                                    '.',
                                                                                    ','
                                                                                )
                                                                            }}
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                    </td>
                                                @endforeach
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    @endforeach

                </tr>
            @endforeach
        </table>

        <div class="footer-note">
            Crédito utiliza 2.1900% de deslizamiento
            y débito 1.6900%.
            El financiamiento adicional depende
            del plazo seleccionado.
        </div>

        <table class="footer">
            <tr>
                <td class="footer-text">
                    Simulación informativa ·
                    Página {{ $loop->iteration }}
                    de {{ $pages->count() }}
                </td>

                <td class="bexia">
                    @if ($bexiaLogoDataUri)
                        <img
                            class="bexia-logo"
                            src="{{ $bexiaLogoDataUri }}"
                            alt="Bexia ERP"
                        >
                    @endif
                </td>
            </tr>
        </table>
    </div>
@endforeach

</body>
</html>
