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
    </style>
</head>
<body>
    <div class="logo">
        @if($logoDataUri)
            <img src="{{ $logoDataUri }}" alt="Logo">
        @endif
    </div>

    <div class="title">Detalle de número de serie</div>
    <div class="subtitle">Documento interno de trazabilidad.</div>

    <div class="section">
        <div class="section-title">Información general</div>
        <table>
            <tr>
                <td><div class="label">Serie / VIN</div><div class="value">{{ $serial->serial_number ?? '—' }}</div></td>
                <td><div class="label">Producto</div><div class="value">{{ $productLabel }}</div></td>
                <td><div class="label">Lote</div><div class="value">{{ $lotNumber }}</div></td>
            </tr>
            <tr>
                <td><div class="label">Estado</div><div class="value">{{ $statusText }}</div></td>
                <td><div class="label">Recepción</div><div class="value">{{ $receipt->number ?? '—' }}</div></td>
                <td><div class="label">Fecha recepción</div><div class="value">{{ $receipt->received_at ?? '—' }}</div></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Trazabilidad / importación</div>
        <table>
            <tr>
                <td><div class="label">Motor</div><div class="value">{{ $serial->motor_number ?? '—' }}</div></td>
                <td><div class="label">Pedimento</div><div class="value">{{ $serial->customs_entry_number ?? '—' }}</div></td>
                <td><div class="label">Fecha pedimento</div><div class="value">{{ $serial->customs_entry_date ?? '—' }}</div></td>
            </tr>
            <tr>
                <td><div class="label">Aduana</div><div class="value">{{ $serial->customs_office ?? '—' }}</div></td>
                <td><div class="label">Modelo importado</div><div class="value">{{ $serial->imported_model ?? '—' }}</div></td>
                <td><div class="label">Color importado</div><div class="value">{{ $serial->imported_color ?? '—' }}</div></td>
            </tr>
            <tr>
                <td colspan="3" style="width:100%;">
                    <div class="label">Referencia documento/factura</div>
                    <div class="value">{{ $serial->import_document_reference ?? '—' }}</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
