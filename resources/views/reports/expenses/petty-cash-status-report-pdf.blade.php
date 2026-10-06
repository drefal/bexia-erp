<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Estado de cajas chicas</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #222;
        }

        .header-table {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
        }

        .logo-cell {
            width: 180px;
        }

        .logo {
            max-width: 160px;
            max-height: 70px;
        }

        .title-cell {
            text-align: right;
        }

        h1 {
            margin: 0 0 4px 0;
            font-size: 18px;
        }

        .meta {
            color: #555;
        }

        .summary {
            width: 100%;
            margin-bottom: 14px;
            border-collapse: collapse;
        }

        .summary td {
            border: 1px solid #ddd;
            padding: 6px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data th,
        table.data td {
            border: 1px solid #ddd;
            padding: 4px;
        }

        table.data th {
            background: #f2f2f2;
            font-weight: bold;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .warn {
            font-weight: bold;
        }
    </style>
</head>

<body>

    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if (! empty($logoDataUri))
                    <img
                        src="{{ $logoDataUri }}"
                        class="logo"
                        alt="Logo empresa"
                    >
                @endif
            </td>

            <td class="title-cell">
                <h1>Estado de cajas chicas</h1>

                <div class="meta">
                    <strong>
                        {{ $companyName ?: '—' }}
                    </strong><br>

                    Generado:
                    {{ $generatedAt->format('d/m/Y H:i') }}
                </div>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td>
                <strong>Cajas:</strong>
                {{ number_format($summary['funds'] ?? 0) }}
            </td>

            <td>
                <strong>Autorizado:</strong>
                ${{ number_format($summary['authorized'] ?? 0, 2) }}
            </td>

            <td>
                <strong>Saldo operativo:</strong>
                ${{ number_format($summary['operational'] ?? 0, 2) }}
            </td>

            <td>
                <strong>Saldo Tesorería:</strong>
                ${{ number_format($summary['treasury'] ?? 0, 2) }}
            </td>
        </tr>

        <tr>
            <td>
                <strong>Fondeo inicial:</strong>
                ${{ number_format($summary['initial_funding'] ?? 0, 2) }}
            </td>

            <td>
                <strong>Reposiciones:</strong>
                ${{ number_format($summary['replenishments'] ?? 0, 2) }}
            </td>

            <td>
                <strong>Gastos:</strong>
                ${{ number_format($summary['expenses'] ?? 0, 2) }}
            </td>

            <td>
                <strong>Diferencias:</strong>
                {{ number_format($summary['differences'] ?? 0) }}
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Folio</th>
                <th>Caja</th>
                <th>Responsable</th>
                <th class="right">Autorizado</th>
                <th class="right">Saldo operativo</th>
                <th class="right">Saldo Tesorería</th>
                <th class="right">Diferencia</th>
                <th class="right">Fondeo</th>
                <th class="right">Reposición</th>
                <th class="right">Gasto</th>
                <th class="right">Devolución</th>
                <th>Último mov.</th>
                <th>Estado</th>
                <th>Control</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->number }}</td>
                    <td>{{ $row->name }}</td>
                    <td>{{ $row->employee_name }}</td>

                    <td class="right">
                        ${{ number_format((float) $row->authorized_amount, 2) }}
                    </td>

                    <td class="right">
                        ${{ number_format((float) $row->operational_balance, 2) }}
                    </td>

                    <td class="right">
                        ${{ number_format((float) ($row->treasury_balance ?? 0), 2) }}
                    </td>

                    <td class="right">
                        ${{ number_format((float) $row->difference, 2) }}
                    </td>

                    <td class="right">
                        ${{ number_format((float) $row->initial_funding_total, 2) }}
                    </td>

                    <td class="right">
                        ${{ number_format((float) $row->replenishment_total, 2) }}
                    </td>

                    <td class="right">
                        ${{ number_format((float) $row->expense_total, 2) }}
                    </td>

                    <td class="right">
                        ${{ number_format((float) $row->return_total, 2) }}
                    </td>

                    <td>
                        {{ $row->last_movement_date ?: '—' }}
                    </td>

                    <td>
                        {{ $row->status }}
                    </td>

                    <td class="center {{ $row->has_difference ? 'warn' : '' }}">
                        {{ $row->has_difference ? 'REVISAR' : 'OK' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
