<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ajuste {{ $record->reference }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: 10mm 9mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5px;
            line-height: 1.15;
            color: #111827;
        }

        .header {
            width: 100%;
            border-bottom: 1.5px solid #1f2937;
            padding-bottom: 5px;
            margin-bottom: 8px;
        }

        .logo {
            width: 105px;
            max-height: 42px;
            object-fit: contain;
        }

        .company {
            font-size: 13px;
            font-weight: bold;
        }

        .muted {
            color: #6b7280;
            font-size: 8px;
        }

        .title {
            font-size: 14px;
            font-weight: bold;
            margin: 4px 0 2px;
        }

        .badge {
            display: inline-block;
            padding: 1px 5px;
            border: 1px solid #94a3b8;
            border-radius: 8px;
            font-size: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta {
            margin-bottom: 5px;
        }

        .meta td {
            padding: 2px 4px;
            vertical-align: top;
            line-height: 1.15;
        }

        .meta .label {
            font-weight: bold;
            width: 78px;
            color: #374151;
        }

        .lines {
            width: 100%;
            table-layout: fixed;
            font-size: 7.6px;
            line-height: 1.08;
            margin-top: 4px;
        }

        .lines thead {
            display: table-header-group;
        }

        .lines tfoot {
            display: table-row-group;
        }

        .lines tr {
            page-break-inside: avoid;
        }

        .lines th {
            background: #f3f4f6;
            border: 0.6px solid #cbd5e1;
            padding: 2px 3px;
            text-align: left;
            font-weight: bold;
            line-height: 1.05;
        }

        .lines td {
            border: 0.6px solid #cbd5e1;
            padding: 2px 3px;
            vertical-align: top;
            line-height: 1.08;
        }

        .right {
            text-align: right;
            white-space: nowrap;
        }

        .notes {
            margin-top: 6px;
            border: 0.6px solid #cbd5e1;
            padding: 5px;
            min-height: 20px;
            font-size: 8px;
            line-height: 1.15;
        }

        .signatures {
            margin-top: 28px;
            width: 100%;
            page-break-inside: avoid;
        }

        .signatures td {
            text-align: center;
            width: 33%;
            padding-top: 22px;
            font-size: 8px;
        }

        .line {
            border-top: 0.8px solid #111827;
            padding-top: 4px;
        }
    </style>
</head>
<body>
@php
    // V5623G_LOT_PDF_HELPERS
    $record->loadMissing('lines');

    $pdfRecordLines = $record->lines->values();
    $showLotColumn = $pdfRecordLines->contains(fn ($line): bool => ! empty($line->lot_id));
    $lotLabels = [];

    if ($showLotColumn && \Illuminate\Support\Facades\Schema::hasTable('stock_lots')) {
        $lotIds = $pdfRecordLines
            ->pluck('lot_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (! empty($lotIds)) {
            $lotLabels = \Illuminate\Support\Facades\DB::table('stock_lots')
                ->whereIn('id', $lotIds)
                ->pluck('lot_number', 'id')
                ->all();
        }
    }
@endphp
<table class="header">
    <tr>
        <td style="width: 35%;">
            @if($logoDataUri)
                <img class="logo" src="{{ $logoDataUri }}">
            @else
                <div class="company">{{ $company->name ?? config('app.name') }}</div>
            @endif
        </td>
        <td style="text-align: right;">
            <div class="title">Ajuste de inventario</div>
            <div><strong>{{ $record->reference }}</strong></div>
            <div class="muted">Generado: {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td class="label">Empresa</td>
        <td>{{ $company->name ?? '—' }}</td>
        <td class="label">Estado</td>
        <td><span class="badge">{{ match($record->status) { 'draft' => 'Borrador', 'done' => 'Hecho', 'cancelled' => 'Cancelado', default => $record->status } }}</span></td>
    </tr>
    <tr>
        <td class="label">Fecha y hora</td>
        <td>{{ $record->adjustment_at ? \Carbon\Carbon::parse($record->adjustment_at)->format('d/m/Y H:i') : '—' }}</td>
        <td class="label">Almacén</td>
        <td>{{ $warehouseLabel }}</td>
    </tr>
    <tr>
        <td class="label">Ubicación</td>
        <td>{{ $locationLabel }}</td>
        <td class="label">Motivo</td>
        <td>{{ $record->reason ?: '—' }}</td>
    </tr>
</table>

<br>

<table class="lines">
    <thead>
        <tr>
            <th style="width: 27%;">Producto</th>
            <th style="width: 18%;">Variante</th>
            {{-- V5623G_LOT_HEADER --}}
            @if($showLotColumn)
                <th style="width: 16%;">Lote</th>
            @endif
            <th class="right">Actual</th>
            <th class="right">Contada</th>
            <th class="right">Diferencia</th>
            <th class="right">Costo prom.</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lines as $lineIndex => $line)
            <tr>
                <td>{{ $line['product'] }}</td>
                <td>{{ $line['variant'] }}</td>
                {{-- V5623G_LOT_CELL --}}
                @if($showLotColumn)
                    @php
                        $pdfSourceLine = $pdfRecordLines->get($lineIndex);
                        $pdfLotId = $pdfSourceLine && ! empty($pdfSourceLine->lot_id) ? (int) $pdfSourceLine->lot_id : null;
                    @endphp
                    <td>
                        {{ $pdfLotId ? ($lotLabels[$pdfLotId] ?? ('Lote #' . $pdfLotId)) : '—' }}
                    </td>
                @endif
                <td class="right">{{ number_format($line['current_quantity'], 2) }}</td>
                <td class="right">{{ number_format($line['counted_quantity'], 2) }}</td>
                <td class="right">{{ number_format($line['difference_quantity'], 2) }}</td>
                <td class="right">{{ $line['unit_cost'] === null ? '—' : '$ ' . number_format($line['unit_cost'], 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $showLotColumn ? 7 : 6 }}">Sin líneas.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="notes">
    <strong>Notas:</strong><br>
    {{ $record->notes ?: '—' }}
</div>

<table class="signatures">
    <tr>
        <td><div class="line">Elaboró</div></td>
        <td><div class="line">Revisó</div></td>
        <td><div class="line">Autorizó</div></td>
    </tr>
</table>
</body>
</html>
