<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: letter; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #111827; background: #fff; }
        .printbar { margin: 0 auto 10px auto; max-width: 190mm; text-align: right; }
        .btn { display: inline-block; padding: 6px 10px; border: 1px solid #1f2937; border-radius: 5px; color: #111827; text-decoration: none; font-size: 11px; }
        .sheet { max-width: 190mm; margin: 0 auto; background: #fff; }
        .header { display: table; width: 100%; border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 12px; }
        .brand, .document { display: table-cell; vertical-align: top; }
        .brand { width: 58%; }
        .document { width: 42%; text-align: right; }
        .logo { max-height: 38px; max-width: 150px; margin-bottom: 4px; }
        .company-name { font-size: 14px; font-weight: 800; margin: 0; }
        .company-subtitle { margin-top: 2px; color: #6b7280; font-size: 9px; }
        .doc-title { font-size: 17px; font-weight: 800; margin: 0; }
        .doc-number { font-size: 12px; font-weight: 700; margin-top: 3px; }
        .muted { color: #6b7280; }
        .badge { display: inline-block; margin-top: 5px; padding: 3px 7px; border-radius: 999px; border: 1px solid #cbd5e1; background: #f8fafc; font-size: 8.5px; font-weight: 700; text-transform: uppercase; }
        .section { margin-top: 10px; page-break-inside: avoid; }
        .section-title { font-size: 11px; font-weight: 800; text-transform: uppercase; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; margin-bottom: 6px; }
        .panel { border: 1px solid #d1d5db; border-radius: 6px; padding: 7px 8px; }
        .grid { display: table; width: 100%; border-collapse: collapse; }
        .grid-row { display: table-row; }
        .grid-cell { display: table-cell; width: 33.333%; padding: 4px 10px 6px 0; vertical-align: top; }
        .label { color: #6b7280; font-size: 7.5px; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 1px; }
        .value { font-size: 10px; font-weight: 700; }
        .amount { font-size: 20px; font-weight: 900; text-align: right; margin-top: 8px; }
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
                <div class="company-subtitle">Bexia ERP · Cobros de clientes</div>
            </div>

            <div class="document">
                <div class="doc-title">Cobro de cliente</div>
                <div class="doc-number">#{{ $record->id }}</div>
                <div class="muted">Impreso: {{ $printedAt->format('Y-m-d H:i') }}</div>
                <div class="badge">{{ \App\Filament\Resources\AccountReceivablePaymentResource::statusLabel($record->status) }}</div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Resumen del cobro</div>
            <div class="panel">
                <div class="grid">
                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="label">CxC</div>
                            <div class="value">{{ $record->accountReceivable->number ?? '-' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Cliente</div>
                            <div class="value">{{ $record->accountReceivable->customer_name ?? '-' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Fecha</div>
                            <div class="value">{{ $record->payment_date ? \Illuminate\Support\Carbon::parse($record->payment_date)->format('Y-m-d') : '-' }}</div>
                        </div>
                    </div>

                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="label">Caja/Banco</div>
                            <div class="value">{{ $record->treasuryAccount->name ?? '-' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Forma de pago</div>
                            <div class="value">{{ $record->paymentForm->name ?? '-' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Referencia</div>
                            <div class="value">{{ $record->reference ?: '-' }}</div>
                        </div>
                    </div>

                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="label">Movimiento tesorería</div>
                            <div class="value">{{ $record->treasury_movement_id ?: '-' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Póliza</div>
                            <div class="value">{{ $record->accounting_entry_id ?: '-' }}</div>
                        </div>
                        <div class="grid-cell">
                            <div class="label">Aplicado</div>
                            <div class="value">{{ $record->posted_at ? \Illuminate\Support\Carbon::parse($record->posted_at)->format('Y-m-d H:i') : '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="amount">
                    ${{ number_format((float) $record->amount, 2) }} {{ $record->currency }}
                </div>
            </div>
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
