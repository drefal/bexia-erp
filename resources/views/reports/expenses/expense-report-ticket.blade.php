@php
    $company = $record->company;

    $companyName =
        $company?->name
        ?: $company?->business_name
        ?: 'Empresa';

    $logoUrl = $company?->getLogoUrl();

    $types = [
        'petty_cash' => 'Caja chica',
        'reimbursement' => 'Reembolso',
    ];

    $statuses = [
        'draft' => 'Borrador',
        'submitted' => 'Enviada',
        'pending_approval' => 'Pendiente de aprobación',
        'approved' => 'Aprobada',
        'rejected' => 'Rechazada',
        'paid' => 'Pagada',
        'closed' => 'Cerrada',
        'cancelled' => 'Cancelada',
    ];

    $type = $types[$record->type]
        ?? $record->type;

    $status = $statuses[$record->status]
        ?? $record->status;

    $money = static fn ($amount) =>
        '$' . number_format((float) $amount, 2, '.', ',');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Vale de Caja - Comprobación {{ $voucherFolio ?: $record->number }}</title>
<style>
@page {
    size: 80mm auto;
    margin: 4mm;
}
* { box-sizing: border-box; }
body {
    width: 72mm;
    margin: 0 auto;
    padding: 0;
    background: white;
    color: #111;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10.5px;
    line-height: 1.35;
}
.ticket { padding: 2mm 0 5mm; }
.center { text-align: center; }
.logo {
    display: block;
    max-width: 42mm;
    max-height: 16mm;
    object-fit: contain;
    margin: 0 auto 2mm;
}
.company {
    font-size: 15px;
    font-weight: 900;
}
.origin {
    font-size: 10px;
    font-weight: 700;
    margin-top: 2mm;
    overflow-wrap: anywhere;
}
.location {
    font-size: 10px;
    margin-top: 1mm;
    overflow-wrap: anywhere;
}
.title {
    text-align: center;
    margin: 4mm 0 2mm;
    padding: 2.5mm 0;
    border-top: 2px solid black;
    border-bottom: 2px solid black;
    font-size: 14px;
    font-weight: 900;
}
.folio {
    text-align: center;
    font-weight: 800;
    font-size: 12px;
    margin-bottom: 3mm;
}
.row {
    display: flex;
    justify-content: space-between;
    gap: 2mm;
    margin: 1mm 0;
}
.row span:first-child { font-weight: 700; }
.row span:last-child { text-align: right; }
.section {
    font-weight: 900;
    margin-top: 4mm;
    padding-bottom: 1mm;
    border-bottom: 1px dashed #888;
}
.item {
    padding: 2mm 0;
    border-bottom: 1px dashed #bbb;
    overflow-wrap: anywhere;
}
.item-title { font-weight: 700; }
.muted { font-size: 9px; color: #444; }
.amount {
    text-align: right;
    font-weight: 800;
}
.total {
    font-size: 15px;
    font-weight: 900;
    padding: 3mm 0;
    border-top: 2px solid black;
    border-bottom: 2px solid black;
    margin-top: 3mm;
}
.notice {
    text-align: center;
    font-size: 9px;
    margin-top: 4mm;
}
.toolbar {
    padding: 12px;
    text-align: center;
}
.toolbar button {
    padding: 9px 18px;
    font-size: 14px;
    cursor: pointer;
}
@media print {
    .toolbar { display: none; }
}
</style>
</head>
<body>
<div class="toolbar">
    <button type="button" onclick="window.print()">
        Imprimir vale
    </button>
</div>

<div class="ticket">
    @if($logoUrl)
        <img class="logo"
             src="{{ $logoUrl }}"
             alt="Logotipo">
    @endif

    <div class="company center">
        {{ $companyName }}
    </div>

    @if($record->pettyCashFund)
        <div class="origin center">
            {{ $record->pettyCashFund->name }}
        </div>
        <div class="location center">
            Ubicación:
            {{ $record->pettyCashFund->location ?: 'No especificada' }}
        </div>
    @endif

    <div class="title">
        VALE DE CAJA - COMPROBACIÓN
    </div>

    <div class="folio">
        {{ $voucherFolio ?: ($record->number ?: '#'.$record->id) }}
    </div>

    <div class="row">
        <span>Fecha:</span>
        <span>{{ $record->report_date?->format('d/m/Y') ?? '—' }}</span>
    </div>
    <div class="row">
        <span>Responsable:</span>
        <span>{{ $record->employee?->name ?? '—' }}</span>
    </div>
    <div class="row">
        <span>Tipo:</span>
        <span>{{ $type }}</span>
    </div>
    <div class="row">
        <span>Estado:</span>
        <span>{{ $status }}</span>
    </div>

    @if($record->pettyCashFund)
        <div class="row">
            <span>Caja:</span>
            <span>{{ $record->pettyCashFund->name }}</span>
        </div>
    @endif

    @if(filled($record->purpose))
        <div class="section">Motivo</div>
        <div>{{ $record->purpose }}</div>
    @endif

    <div class="section">Detalle de gastos</div>

    @forelse($record->lines as $line)
        <div class="item">
            <div class="item-title">
                {{ $loop->iteration }}.
                {{ $line->description ?: 'Gasto' }}
            </div>

            <div class="muted">
                {{ $line->expense_date?->format('d/m/Y') ?? '—' }}
                @if(filled($line->supplier_name))
                    · {{ $line->supplier_name }}
                @endif
            </div>

            @if($line->spentByEmployee)
                <div class="muted">
                    Realizó: {{ $line->spentByEmployee->name }}
                </div>
            @endif

            <div class="amount">
                {{ $money($line->total_amount) }}
            </div>
        </div>
    @empty
        <div class="item">Sin gastos registrados</div>
    @endforelse

    <div class="row">
        <span>Subtotal:</span>
        <span>{{ $money($record->subtotal) }}</span>
    </div>
    <div class="row">
        <span>Impuestos:</span>
        <span>{{ $money($record->tax_amount) }}</span>
    </div>
    <div class="row total">
        <span>TOTAL:</span>
        <span>{{ $money($record->total_amount) }}</span>
    </div>

    <div class="notice">
        Comprobación de gastos registrados.
        Este documento no acredita entrega de dinero
        ni constituye recibo de reembolso.
    </div>
</div>
<script>
/* GASTOS10M3_AUTO_PRINT */
(function () {
    let printed = false;

    function printOnce() {
        if (printed) return;
        printed = true;

        window.focus();
        window.print();
    }

    window.addEventListener('load', function () {
        const images = Array.from(
            document.querySelectorAll('.ticket img')
        );

        const ready = images.map(function (img) {
            if (img.complete) {
                return Promise.resolve();
            }

            return new Promise(function (resolve) {
                img.addEventListener('load', resolve, { once: true });
                img.addEventListener('error', resolve, { once: true });
            });
        });

        Promise.all(ready).then(function () {
            setTimeout(printOnce, 350);
        });
    });
})();
</script>
</body>
</html>
