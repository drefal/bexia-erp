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

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
        }

        .summary-table th,
        .summary-table td {
            padding: 5px 6px;
            border-bottom: 1px solid #e5e7eb;
        }

        .summary-table th {
            background: #f3f4f6;
            font-size: 7.5px;
            color: #374151;
            text-transform: uppercase;
            text-align: left;
        }

        .right { text-align: right; }
        .strong { font-weight: 800; }

        .totals {
            width: 290px;
            margin-left: auto;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            overflow: hidden;
        }

        .totals table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals td {
            padding: 5px 7px;
            border-bottom: 1px solid #e5e7eb;
        }

        .totals tr:last-child td { border-bottom: 0; }
        .totals .total-row td {
            background: #f9fafb;
            font-size: 10px;
            font-weight: 800;
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
                <div class="doc-title">Cuenta por pagar</div>
                <div class="doc-number">{{ $record->number }}</div>
                <div class="muted">Impreso: {{ $printedAt->format('Y-m-d H:i') }}</div>
                <div class="badge">{{ \App\Filament\Resources\AccountPayableResource::statusLabel($record->status) }}</div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Información general</div>
            <div class="panel">
                <div class="grid">
                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="label">Proveedor</div>
                            <div class="value">{{ $record->supplier_name ?: 'Sin proveedor' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Referencia proveedor</div>
                            <div class="value">{{ $record->supplier_reference ?: 'Sin referencia' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Moneda</div>
                            <div class="value">{{ $record->currency }}</div>
                        </div>
                    </div>

                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="label">Fecha</div>
                            <div class="value">{{ optional($record->issue_date)->format('Y-m-d') ?: 'Sin fecha' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Vencimiento</div>
                            <div class="value">{{ optional($record->due_date)->format('Y-m-d') ?: 'Sin vencimiento' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Recepción</div>
                            <div class="value">{{ optional($record->purchaseReceipt)->number ?: 'Sin recepción' }}</div>
                        </div>
                    </div>

                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="label">Orden de compra</div>
                            <div class="value">{{ optional($record->purchaseOrder)->number ?: 'Sin orden' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Estado contable</div>
                            <div class="value">{{ \App\Filament\Resources\AccountPayableResource::accountingStatusLabel($record->accounting_status) }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Póliza</div>
                            <div class="value">{{ $record->accounting_entry_id ? '#' . $record->accounting_entry_id : 'Pendiente' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Importes</div>
            <div class="totals">
                <table>
                    <tr>
                        <td>Subtotal</td>
                        <td class="right">${{ number_format((float) $record->subtotal, 2) }} {{ $record->currency }}</td>
                    </tr>
                    <tr>
                        <td>Impuestos</td>
                        <td class="right">${{ number_format((float) $record->tax_total, 2) }} {{ $record->currency }}</td>
                    </tr>
                    <tr>
                        <td>Total</td>
                        <td class="right">${{ number_format((float) $record->total, 2) }} {{ $record->currency }}</td>
                    </tr>
                    <tr>
                        <td>Pagado</td>
                        <td class="right">${{ number_format((float) $record->paid_total, 2) }} {{ $record->currency }}</td>
                    </tr>
                    <tr class="total-row">
                        <td>Saldo</td>
                        <td class="right">${{ number_format((float) $record->balance_total, 2) }} {{ $record->currency }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Pagos registrados</div>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Cuenta / caja</th>
                        <th>Forma</th>
                        <th>Referencia</th>
                        <th>Estado</th>
                        <th class="right">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td>#{{ $payment->id }}</td>
                            <td>{{ $payment->payment_date }}</td>
                            <td>{{ $payment->treasury_account_name ?: 'Sin cuenta' }}</td>
                            <td>{{ trim(($payment->payment_form_code ? $payment->payment_form_code . ' - ' : '') . ($payment->payment_form_name ?: '')) }}</td>
                            <td>{{ $payment->reference ?: 'Sin referencia' }}</td>
                            <td>{{ \App\Filament\Resources\AccountPayablePaymentResource::statusLabel($payment->status) }}</td>
                            <td class="right">${{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">Sin pagos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="footer">
            Documento generado desde Bexia ERP.
        </div>
    </div>
</body>
</html>
