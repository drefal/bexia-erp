<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 22px 26px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #111827; font-size: 11px; }
        .logo { width: 100%; height: 82px; text-align: center; border-bottom: 1px solid #d1d5db; margin-bottom: 16px; padding-bottom: 10px; }
        .logo img { max-width: 100%; max-height: 82px; }
        .title { font-size: 20px; font-weight: bold; margin-bottom: 2px; }
        .subtitle { color: #6b7280; margin-bottom: 14px; }
        .section { border: 1px solid #d1d5db; border-radius: 8px; margin-bottom: 14px; }
        .section-title { background: #f3f4f6; padding: 8px 10px; font-weight: bold; border-bottom: 1px solid #d1d5db; }
        table { width: 100%; border-collapse: collapse; }
        td { width: 33.33%; vertical-align: top; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; padding: 8px 10px; }
        .label { font-size: 9px; color: #6b7280; text-transform: uppercase; font-weight: bold; margin-bottom: 4px; }
        .value { font-size: 11px; font-weight: normal; word-break: break-word; }
        .stat { font-size: 16px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="logo">
        @if($logoDataUri)
            <img src="{{ $logoDataUri }}" alt="Logo">
        @endif
    </div>

    <div class="title">Detalle de lote</div>
    <div class="subtitle">Documento interno de trazabilidad.</div>

    <div class="section">
        <div class="section-title">Resumen del lote</div>
        <table>
            <tr>
                <td><div class="label">Lote</div><div class="value">{{ $lotNumber }}</div></td>
                <td><div class="label">Producto</div><div class="value">{{ $productLabel }}</div></td>
                <td><div class="label">Recepción</div><div class="value">{{ $receipt->number ?? '—' }}</div></td>
            </tr>
            <tr>
                <td><div class="label">Productos en lote</div><div class="value stat">{{ number_format((float) ($stats['total'] ?? 0), 6) }}</div></td>
                <td><div class="label">Vendidos / salidos</div><div class="value stat">{{ number_format((float) ($stats['sold'] ?? 0), 6) }}</div></td>
                <td><div class="label">Disponibles / quedan</div><div class="value stat">{{ number_format((float) ($stats['remaining'] ?? 0), 6) }}</div></td>
            </tr>
            <tr>
                <td><div class="label">Estado</div><div class="value">{{ $statusText }}</div></td>
                <td><div class="label">Caducidad</div><div class="value">{{ $lot->expiration_date ?? '—' }}</div></td>
                <td><div class="label">Notas</div><div class="value">{{ $lot->notes ?? '—' }}</div></td>
            </tr>
        </table>
    </div>
</body>
</html>
