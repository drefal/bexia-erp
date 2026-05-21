<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: letter; margin: 12mm 12mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9.5px;
            line-height: 1.35;
            color: #111827;
            background: #ffffff;
        }

        .printbar {
            margin: 0 auto 10px auto;
            max-width: 190mm;
            text-align: right;
        }

        .btn {
            display: inline-block;
            padding: 6px 10px;
            border: 1px solid #1f2937;
            border-radius: 5px;
            color: #111827;
            text-decoration: none;
            font-size: 11px;
        }

        .sheet {
            max-width: 190mm;
            margin: 0 auto;
            background: #ffffff;
        }

        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #111827;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .brand, .document {
            display: table-cell;
            vertical-align: top;
        }

        .brand { width: 58%; }
        .document { width: 42%; text-align: right; }

        .logo {
            max-height: 38px;
            max-width: 150px;
            margin-bottom: 4px;
        }

        .company-name {
            font-size: 14px;
            font-weight: 800;
            margin: 0;
            line-height: 1.1;
        }

        .company-subtitle {
            margin-top: 2px;
            color: #6b7280;
            font-size: 9px;
        }

        .doc-title {
            font-size: 17px;
            font-weight: 800;
            margin: 0;
            line-height: 1.1;
        }

        .doc-number {
            font-size: 12px;
            font-weight: 700;
            margin-top: 3px;
        }

        .muted { color: #6b7280; }

        .badge {
            display: inline-block;
            margin-top: 5px;
            padding: 3px 7px;
            border-radius: 999px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .section {
            margin-top: 10px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #111827;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 3px;
            margin-bottom: 6px;
        }

        .panel {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 7px 8px;
        }

        .grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }

        .grid-row { display: table-row; }

        .grid-cell {
            display: table-cell;
            width: 33.333%;
            padding: 3px 10px 5px 0;
            vertical-align: top;
        }

        .label {
            color: #6b7280;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: .03em;
            margin-bottom: 1px;
        }

        .value {
            font-size: 9.5px;
            font-weight: 700;
        }

        .amount-box {
            border: 1px solid #111827;
            border-radius: 8px;
            padding: 10px 12px;
            width: 290px;
            margin-left: auto;
            text-align: right;
        }

        .amount-label {
            font-size: 8px;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: .03em;
        }

        .amount {
            font-size: 20px;
            font-weight: 800;
            line-height: 1.1;
        }

        .notes {
            white-space: pre-wrap;
        }

        .footer {
            margin-top: 14px;
            padding-top: 5px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 8px;
            text-align: right;
        }

        @media print {
            .printbar { display: none; }
            .sheet { max-width: none; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="printbar">
        <a href="#" onclick="window.print(); return false;" class="btn">Imprimir / Guardar PDF</a>
    </div>

    <div class="sheet">
        <div class="header">
            <div class="brand">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" class="logo" alt="Logo">
                @endif
                <div class="company-name">{{ $company->name ?? 'Empresa' }}</div>
                <div class="company-subtitle">Bexia ERP · Cuentas por pagar</div>
            </div>

            <div class="document">
                <div class="doc-title">Comprobante de pago</div>
                <div class="doc-number">Pago #{{ $record->id }}</div>
                <div class="muted">Impreso: {{ $printedAt->format('Y-m-d H:i') }}</div>
                <div class="badge">{{ \App\Filament\Resources\AccountPayablePaymentResource::statusLabel($record->status) }}</div>
            </div>
        </div>

        <div class="amount-box">
            <div class="amount-label">Importe pagado</div>
            <div class="amount">${{ number_format((float) $record->amount, 2) }} {{ $record->currency }}</div>
        </div>

        <div class="section">
            <div class="section-title">Información del pago</div>
            <div class="panel">
                <div class="grid">
                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="label">CxP</div>
                            <div class="value">{{ optional($record->accountPayable)->number ?: 'Sin CxP' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Proveedor</div>
                            <div class="value">{{ optional($record->accountPayable)->supplier_name ?: 'Sin proveedor' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Fecha pago</div>
                            <div class="value">{{ optional($record->payment_date)->format('Y-m-d') ?: $record->payment_date }}</div>
                        </div>
                    </div>

                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="label">Cuenta / Caja</div>
                            <div class="value">{{ optional($record->treasuryAccount)->name ?: 'Sin cuenta' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Forma de pago</div>
                            <div class="value">{{ trim((optional($record->paymentForm)->code ? optional($record->paymentForm)->code . ' - ' : '') . (optional($record->paymentForm)->name ?: 'Sin forma')) }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Referencia</div>
                            <div class="value">{{ $record->reference ?: 'Sin referencia' }}</div>
                        </div>
                    </div>

                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="label">Movimiento tesorería</div>
                            <div class="value">{{ $record->treasury_movement_id ? '#' . $record->treasury_movement_id : 'Pendiente' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Póliza contable</div>
                            <div class="value">{{ $record->accounting_entry_id ? '#' . $record->accounting_entry_id : 'Pendiente' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Aplicado</div>
                            <div class="value">{{ optional($record->posted_at)->format('Y-m-d H:i') ?: 'Pendiente' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($record->notes)
            <div class="section">
                <div class="section-title">Notas</div>
                <div class="panel notes">{{ $record->notes }}</div>
            </div>
        @endif

        <div class="footer">
            Documento generado desde Bexia ERP.
        </div>
    </div>
</body>
</html>
