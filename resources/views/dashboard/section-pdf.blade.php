<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - Bexia ERP</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0f172a;
            font-size: 11px;
            margin: 18px;
        }
        .header {
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 14px;
            border: 1px solid #dbeafe;
        }
        .purple { background: #faf5ff; border-color: #e9d5ff; }
        .blue { background: #eff6ff; border-color: #bfdbfe; }
        .green { background: #f0fdf4; border-color: #bbf7d0; }
        h1 { margin: 0; font-size: 22px; }
        h2 { margin: 16px 0 8px; font-size: 15px; }
        p { margin: 4px 0; }
        .grid { width: 100%; border-collapse: separate; border-spacing: 8px; }
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
            background: white;
            vertical-align: top;
        }
        .label { color: #64748b; font-size: 10px; }
        .value { font-size: 20px; font-weight: bold; margin-top: 6px; }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.data th, table.data td {
            border-bottom: 1px solid #e2e8f0;
            padding: 6px;
            text-align: left;
        }
        table.data th {
            background: #f8fafc;
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
        }
        .right { text-align: right !important; }
        .bar-bg { height: 10px; background: #e5e7eb; border-radius: 999px; overflow: hidden; }
        .bar { height: 10px; border-radius: 999px; }
    </style>
</head>
<body>
    <div class="header {{ $theme }}">
        <h1>{{ $title }}</h1>
        <p>
            {{ $data['company_name'] ?? '' }}
            @if (! empty($data['updated_at']))
                · Generado {{ now()->format('d/m/Y H:i:s') }}
                · Última lectura {{ $data['updated_at'] }}
            @endif
        </p>
    </div>

    @if ($section === 'rrhh' || $section === 'contabilidad')
        <table class="grid">
            @foreach (collect($data['cards'])->chunk(3) as $chunk)
                <tr>
                    @foreach ($chunk as $card)
                        <td class="card" width="33.33%">
                            <div class="label">{{ $card['label'] }}</div>
                            <div class="value">{{ $card['value'] }}</div>
                            <p>{{ $card['description'] }}</p>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endif

    @if ($section === 'tesoreria')
        <table class="grid">
            <tr>
                <td class="card" width="25%">
                    <div class="label">Efectivo actual</div>
                    <div class="value">$ {{ number_format($data['total_cash'], 2) }}</div>
                    <p>Saldo en cajas operativas</p>
                </td>
                <td class="card" width="25%">
                    <div class="label">En tránsito</div>
                    <div class="value">$ {{ number_format($data['transit_total'], 2) }}</div>
                    <p>Pendiente sin aplicar</p>
                </td>
                <td class="card" width="25%">
                    <div class="label">Entradas hoy</div>
                    <div class="value">$ {{ number_format($data['today_in'], 2) }}</div>
                    <p>Movimientos de entrada</p>
                </td>
                <td class="card" width="25%">
                    <div class="label">Salidas hoy</div>
                    <div class="value">$ {{ number_format($data['today_out'], 2) }}</div>
                    <p>Movimientos de salida</p>
                </td>
            </tr>
        </table>

        <h2>Cajas operativas</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Caja</th>
                    <th>Tipo</th>
                    <th class="right">Saldo</th>
                    <th>Visual</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['columns'] as $column)
                    <tr>
                        <td>{{ $column['name'] }}</td>
                        <td>{{ $column['scope_label'] }}</td>
                        <td class="right">{{ $column['money'] }}</td>
                        <td>
                            <div class="bar-bg">
                                <div class="bar" style="width: {{ $column['percent'] }}%; background: {{ $column['color'] }};"></div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Cajas en tránsito</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Origen</th>
                    <th>Destino</th>
                    <th class="right">Monto</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data['transit'] as $row)
                    <tr>
                        <td>{{ $row->number ?? ('#' . $row->id) }}</td>
                        <td>{{ $row->source_name ?? '-' }}</td>
                        <td>{{ $row->destination_name ?? '-' }}</td>
                        <td class="right">$ {{ number_format((float) $row->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No hay efectivo en tránsito.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Últimos movimientos</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Caja / cuenta</th>
                    <th>Tipo</th>
                    <th>Referencia</th>
                    <th>Fecha</th>
                    <th class="right">Monto</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data['movements'] as $row)
                    <tr>
                        <td>{{ $row->account_name ?? '-' }}</td>
                        <td>{{ str_replace('_', ' ', $row->type ?? '-') }}</td>
                        <td>{{ $row->reference ?? '-' }}</td>
                        <td>{{ $row->created_at ? \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i') : '-' }}</td>
                        <td class="right">$ {{ number_format((float) $row->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No hay movimientos recientes.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
</body>
</html>
