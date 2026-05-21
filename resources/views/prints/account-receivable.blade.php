<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: letter; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.5px; color: #111827; background: #fff; }
        .printbar { margin: 0 auto 10px auto; max-width: 190mm; text-align: right; }
        .btn { display: inline-block; padding: 6px 10px; border: 1px solid #1f2937; border-radius: 5px; color: #111827; text-decoration: none; font-size: 11px; }
        .sheet { max-width: 190mm; margin: 0 auto; background: #fff; }
        .header { display: table; width: 100%; border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 10px; }
        .brand, .document { display: table-cell; vertical-align: top; }
        .brand { width: 58%; }
        .document { width: 42%; text-align: right; }
        .logo { max-height: 38px; max-width: 150px; margin-bottom: 4px; }
        .company-name { font-size: 14px; font-weight: 800; margin: 0; line-height: 1.1; }
        .company-subtitle { margin-top: 2px; color: #6b7280; font-size: 9px; }
        .doc-title { font-size: 17px; font-weight: 800; margin: 0; line-height: 1.1; }
        .doc-number { font-size: 12px; font-weight: 700; margin-top: 3px; }
        .muted { color: #6b7280; }
        .badge { display: inline-block; margin-top: 5px; padding: 3px 7px; border-radius: 999px; border: 1px solid #cbd5e1; background: #f8fafc; font-size: 8.5px; font-weight: 700; text-transform: uppercase; }
        .section { margin-top: 10px; page-break-inside: avoid; }
        .section-title { font-size: 11px; font-weight: 800; text-transform: uppercase; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; margin-bottom: 6px; }
        .panel { border: 1px solid #d1d5db; border-radius: 6px; padding: 7px 8px; }
        .grid { display: table; width: 100%; border-collapse: collapse; }
        .grid-row { display: table-row; }
        .grid-cell { display: table-cell; width: 33.333%; padding: 3px 10px 5px 0; vertical-align: top; }
        .label { color: #6b7280; font-size: 7.5px; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 1px; }
        .value { font-size: 9.5px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; }
        th { background: #f3f4f6; font-size: 7.5px; color: #374151; text-transform: uppercase; text-align: left; }
        .right { text-align: right; }
        .strong { font-weight: 800; }
        .footer { margin-top: 14px; padding-top: 5px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 8px; text-align: right; }
        @media print { .printbar { display: none; } .sheet { max-width: none; margin: 0; } }
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
                <div class="company-subtitle">Bexia ERP · Cuentas por cobrar</div>
            </div>

            <div class="document">
                <div class="doc-title">Cuenta por cobrar</div>
                <div class="doc-number">{{ $record->number }}</div>
                <div class="muted">Impreso: {{ $printedAt->format('Y-m-d H:i') }}</div>
                <div class="badge">{{ \App\Filament\Resources\AccountReceivableResource::statusLabel($record->status) }}</div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Información general</div>
            <div class="panel">
                <div class="grid">
                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="label">Cliente</div>
                            <div class="value">{{ $record->customer_name ?: 'Sin cliente' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Referencia cliente</div>
                            <div class="value">{{ $record->customer_reference ?: 'Sin referencia' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Moneda</div>
                            <div class="value">{{ $record->currency }}</div>
                        </div>
                    </div>
                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="label">Fecha emisión</div>
                            <div class="value">{{ $record->issue_date ? \Illuminate\Support\Carbon::parse($record->issue_date)->format('Y-m-d') : '-' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Vencimiento</div>
                            <div class="value">{{ $record->due_date ? \Illuminate\Support\Carbon::parse($record->due_date)->format('Y-m-d') : '-' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Origen</div>
                            <div class="value">{{ $record->source_type ?: '-' }} #{{ $record->source_id ?: '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Importes</div>
            <table>
                <tr>
                    <th>Subtotal</th>
                    <th>Impuestos</th>
                    <th>Total</th>
                    <th>Cobrado</th>
                    <th>Saldo</th>
                </tr>
                <tr>
                    <td class="right">${{ number_format((float) $record->subtotal, 2) }} {{ $record->currency }}</td>
                    <td class="right">${{ number_format((float) $record->tax_total, 2) }} {{ $record->currency }}</td>
                    <td class="right strong">${{ number_format((float) $record->total, 2) }} {{ $record->currency }}</td>
                    <td class="right">${{ number_format((float) $record->collected_total, 2) }} {{ $record->currency }}</td>
                    <td class="right strong">${{ number_format((float) $record->balance_total, 2) }} {{ $record->currency }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Cobros</div>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Forma</th>
                    <th>Caja/Banco</th>
                    <th>Referencia</th>
                    <th>Estado</th>
                    <th class="right">Importe</th>
                </tr>
                @forelse($payments as $payment)
                    <tr>
                        <td>{{ $payment->id }}</td>
                        <td>{{ $payment->payment_date ?: '-' }}</td>
                        <td>{{ trim(($payment->payment_form_code ? $payment->payment_form_code . ' - ' : '') . ($payment->payment_form_name ?: '')) ?: '-' }}</td>
                        <td>{{ $payment->treasury_account_name ?: '-' }}</td>
                        <td>{{ $payment->reference ?: '-' }}</td>
                        <td>{{ \App\Filament\Resources\AccountReceivablePaymentResource::statusLabel($payment->status) }}</td>
                        <td class="right">${{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">Sin cobros registrados.</td>
                    </tr>
                @endforelse
            </table>
        </div>

        @if($record->notes)
            <div class="section">
                <div class="section-title">Notas</div>
                <div class="panel">{{ $record->notes }}</div>
            </div>
        @endif

        <div class="footer">
            Documento generado desde Bexia ERP.
        </div>
    </div>
</body>
</html>
