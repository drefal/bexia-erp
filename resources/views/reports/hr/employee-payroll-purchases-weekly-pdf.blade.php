<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111827; }
        h1 { margin: 0 0 4px 0; font-size: 16px; }
        .meta { margin-bottom: 12px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; white-space: nowrap; }
        .totals { margin-top: 12px; width: 42%; margin-left: auto; }
        .totals td:first-child { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Deducciones por compras vía nómina</h1>
    <div class="meta">
        Semana del {{ $from->format('d/m/Y') }} al {{ $to->format('d/m/Y') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Empleado</th>
                <th>Compra</th>
                <th>Productos</th>
                <th>Pago</th>
                <th>Fecha</th>
                <th class="num">A deducir</th>
                <th class="num">Aplicado</th>
                <th>Estado</th>
                <th class="num">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['employee'] }}</td>
                    <td>{{ $row['purchase_number'] }}</td>
                    <td>{{ $row['products'] }}</td>
                    <td>{{ $row['installment'] }}</td>
                    <td>{{ \Carbon\Carbon::parse($row['due_date'])->format('d/m/Y') }}</td>
                    <td class="num">$ {{ number_format($row['scheduled_amount'], 2) }}</td>
                    <td class="num">$ {{ number_format($row['applied_amount'], 2) }}</td>
                    <td>{{ $row['status'] === 'applied' ? 'Aplicado' : 'Pendiente' }}</td>
                    <td class="num">$ {{ number_format($row['outstanding_amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">No hay cuotas programadas para esta semana.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Total programado</td><td class="num">$ {{ number_format($scheduled_total, 2) }}</td></tr>
        <tr><td>Total pendiente</td><td class="num">$ {{ number_format($pending_total, 2) }}</td></tr>
        <tr><td>Total aplicado</td><td class="num">$ {{ number_format($applied_total, 2) }}</td></tr>
    </table>
</body>
</html>
