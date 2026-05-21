<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 4px;
        }

        h2 {
            font-size: 13px;
            margin: 16px 0 6px;
        }

        .muted {
            color: #6b7280;
        }

        .header {
            width: 100%;
            margin-bottom: 12px;
        }

        .header td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }

        .logo {
            text-align: right;
        }

        .logo img {
            max-height: 54px;
            max-width: 170px;
        }

        .box {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
        }

        .box td {
            border: 1px solid #d1d5db;
            padding: 6px;
            vertical-align: top;
        }

        table.lines {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .lines th {
            background: #f3f4f6;
            font-weight: bold;
        }

        .lines th,
        .lines td {
            border: 1px solid #d1d5db;
            padding: 5px;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .total-row td {
            font-weight: bold;
            background: #f9fafb;
        }

        .footer {
            position: fixed;
            bottom: 10px;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>Asiento contable</h1>
                <div class="muted">
                    {{ $company->name ?? 'Empresa' }}
                </div>
                <div class="muted">
                    Generado: {{ now()->format('d/m/Y H:i') }}
                </div>
            </td>
            <td class="logo">
                @if(! empty($logoSrc))
                    <img src="{{ $logoSrc }}" alt="Logo">
                @endif
            </td>
        </tr>
    </table>

    <table class="box">
        <tr>
            <td>
                <b>Número</b><br>
                {{ $entry->entry_number }}
            </td>
            <td>
                <b>Fecha</b><br>
                {{ $entry->entry_date ? \Carbon\Carbon::parse($entry->entry_date)->format('d/m/Y') : '-' }}
            </td>
            <td>
                <b>Estatus</b><br>
                {{ $statusLabel }}
            </td>
        </tr>
        <tr>
            <td>
                <b>Diario</b><br>
                {{ trim(($entry->journal_code ? $entry->journal_code . ' - ' : '') . ($entry->journal_name ?: 'Sin diario')) }}
            </td>
            <td>
                <b>Origen</b><br>
                {{ $sourceLabel }}
            </td>
            <td>
                <b>ID origen</b><br>
                {{ $entry->source_id ?: '-' }}
            </td>
        </tr>
        <tr>
            <td>
                <b>Moneda</b><br>
                {{ $entry->currency ?: 'MXN' }}
            </td>
            <td>
                <b>Contabilizado</b><br>
                {{ $entry->posted_at ? \Carbon\Carbon::parse($entry->posted_at)->format('d/m/Y H:i') : '-' }}
            </td>
            <td>
                <b>Total</b><br>
                {{ '$ ' . number_format((float) $entry->total_debit, 2) . ' ' . ($entry->currency ?: 'MXN') }}
            </td>
        </tr>
    </table>

    @if(! empty($entry->source_label) || ! empty($entry->notes))
        <table class="box">
            @if(! empty($entry->source_label))
                <tr>
                    <td>
                        <b>Descripción</b><br>
                        {{ $entry->source_label }}
                    </td>
                </tr>
            @endif

            @if(! empty($entry->notes))
                <tr>
                    <td>
                        <b>Notas</b><br>
                        {{ $entry->notes }}
                    </td>
                </tr>
            @endif
        </table>
    @endif

    <h2>Líneas contables</h2>

    <table class="lines">
        <thead>
            <tr>
                <th class="center" style="width: 6%;">#</th>
                <th style="width: 16%;">Cuenta</th>
                <th>Nombre</th>
                <th>Concepto</th>
                <th class="right" style="width: 14%;">Debe</th>
                <th class="right" style="width: 14%;">Haber</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $line)
                <tr>
                    <td class="center">{{ $line->line_number }}</td>
                    <td>{{ $line->account_code }}</td>
                    <td>{{ $line->account_name }}</td>
                    <td>{{ $line->label }}</td>
                    <td class="right">
                        {{ ((float) $line->debit) > 0 ? '$ ' . number_format((float) $line->debit, 2) : '-' }}
                    </td>
                    <td class="right">
                        {{ ((float) $line->credit) > 0 ? '$ ' . number_format((float) $line->credit, 2) : '-' }}
                    </td>
                </tr>
            @endforeach

            <tr class="total-row">
                <td colspan="4" class="right">Totales</td>
                <td class="right">{{ '$ ' . number_format((float) $totals['debit'], 2) }}</td>
                <td class="right">{{ '$ ' . number_format((float) $totals['credit'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Bexia ERP · Asiento {{ $entry->entry_number }}
    </div>
</body>
</html>
