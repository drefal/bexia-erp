<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Movimientos de caja chica</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #222;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .header td {
            vertical-align: middle;
        }

        .logo {
            max-width: 150px;
            max-height: 65px;
        }

        .title {
            text-align: right;
        }

        h1 {
            margin: 0 0 4px 0;
            font-size: 17px;
        }

        .meta {
            color: #555;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .summary td {
            border: 1px solid #ddd;
            padding: 5px;
        }

        .data {
            width: 100%;
            border-collapse: collapse;
        }

        .data th,
        .data td {
            border: 1px solid #ddd;
            padding: 3px;
        }

        .data th {
            background: #f2f2f2;
        }

        .right {
            text-align: right;
        }
    </style>
</head>

<body>
    <table class="header">
        <tr>
            <td>
                @if (! empty($logoDataUri))
                    <img
                        src="{{ $logoDataUri }}"
                        class="logo"
                        alt="Logo"
                    >
                @endif
            </td>

            <td class="title">
                <h1>Movimientos de caja chica</h1>

                <div class="meta">
                    <strong>{{ $companyName }}</strong><br>

                    Periodo:
                    {{ $dateFrom ?: 'Inicio' }}
                    al
                    {{ $dateTo ?: 'Hoy' }}<br>

                    @if ($fundId)
                        Caja:
                        {{ $fundOptions[$fundId] ?? $fundId }}
                        <br>
                    @endif

                    @if ($movementType)
                        Tipo:
                        {{ $typeOptions[$movementType] ?? $movementType }}
                        <br>
                    @endif

                    @if ($fundingSourceId)
                        Origen:
                        {{ $fundingSourceOptions[$fundingSourceId] ?? $fundingSourceId }}
                        <br>
                    @endif

                    Generado:
                    {{ $generatedAt->format('d/m/Y H:i') }}
                </div>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td>
                <strong>Movimientos:</strong>
                {{ number_format($summary['movements'] ?? 0) }}
            </td>

            <td>
                <strong>Entradas:</strong>
                ${{ number_format($summary['entries'] ?? 0, 2) }}
            </td>

            <td>
                <strong>Salidas:</strong>
                ${{ number_format($summary['exits'] ?? 0, 2) }}
            </td>

            <td>
                <strong>Saldo inicial:</strong>
                ${{ number_format($summary['opening_balance'] ?? 0, 2) }}
            </td>

            <td>
                <strong>Saldo final:</strong>
                ${{ number_format($summary['closing_balance'] ?? 0, 2) }}
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Caja</th>
                <th>Responsable</th>
                <th>Tipo</th>
                <th>Origen fondos</th>
                <th>Cuenta origen</th>
                <th>Referencia</th>
                <th>Descripción</th>
                <th class="right">Entrada</th>
                <th class="right">Salida</th>
                <th class="right">Antes</th>
                <th class="right">Después</th>
                <th>Comprobación</th>
                <th>Tesorería</th>
                <th>Usuario</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->movement_date }}</td>
                    <td>
                        {{ $row->fund_number }}
                        ·
                        {{ $row->fund_name }}
                    </td>
                    <td>{{ $row->employee_name ?: '—' }}</td>
                    <td>{{ $row->type_label }}</td>
                    <td>
                        @if ($row->funding_source_name)
                            {{ $row->funding_source_code ? $row->funding_source_code . ' · ' : '' }}
                            {{ $row->funding_source_name }}
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $row->source_account_name ?: '—' }}</td>
                    <td>{{ $row->reference ?: '—' }}</td>
                    <td>{{ $row->description ?: '—' }}</td>

                    <td class="right">
                        @if ($row->entry_amount > 0)
                            ${{ number_format($row->entry_amount, 2) }}
                        @else
                            —
                        @endif
                    </td>

                    <td class="right">
                        @if ($row->exit_amount > 0)
                            ${{ number_format($row->exit_amount, 2) }}
                        @else
                            —
                        @endif
                    </td>

                    <td class="right">
                        ${{ number_format((float) $row->balance_before, 2) }}
                    </td>

                    <td class="right">
                        ${{ number_format((float) $row->balance_after, 2) }}
                    </td>

                    <td>
                        {{ $row->expense_report_number ?: '—' }}
                    </td>

                    <td>
                        {{ $row->treasury_movement_id ?: '—' }}
                    </td>

                    <td>
                        {{ $row->created_by_name ?: '—' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
