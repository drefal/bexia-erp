<x-filament-panels::page>
    @php
        $serial = $this->serial();
        $product = $this->product();
        $variant = $this->variant();
        $lot = $this->lot();
        $receipt = $this->receipt();
        $receiptLine = $this->receiptLine();
        $movement = $this->movement();
        $warehouse = $this->warehouse();
        $location = $this->location();

        $value = fn ($v) => filled($v ?? null) ? $v : '—';

        $statusLabels = [
            'available' => 'Disponible',
            'in_stock' => 'Disponible',
            'active' => 'Activo',
            'received' => 'Recibido',
            'reserved' => 'Reservado',
            'sold' => 'Vendido',
            'used' => 'Usado',
            'consumed' => 'Consumido',
            'inactive' => 'Inactivo',
            'blocked' => 'Bloqueado',
            'quarantine' => 'En cuarentena',
            'quarantined' => 'En cuarentena',
            'damaged' => 'Dañado',
            'expired' => 'Caducado',
            'done' => 'Hecho',
            'draft' => 'Borrador',
            'pending' => 'Pendiente',
        ];

        $statusText = function ($status) use ($statusLabels, $value): string {
            $key = mb_strtolower(trim((string) ($status ?? '')));

            if ($key === '') {
                return '—';
            }

            return $statusLabels[$key] ?? ucfirst(str_replace('_', ' ', $key));
        };



        $productLabel = trim(implode(' - ', array_filter([
            $product->internal_reference ?? $product->sku ?? null,
            $product->name ?? null,
        ]))) ?: '—';

        $lotNumber = $lot->lot_number
            ?? $lot->number
            ?? $serial->lot_number
            ?? '—';
    @endphp

    <style>
        .bexia-detail-actions{display:flex;justify-content:flex-end;gap:10px;margin-bottom:18px}
        .bexia-btn{display:inline-flex;align-items:center;justify-content:center;min-height:40px;border-radius:12px;padding:0 16px;font-size:14px;font-weight:800;text-decoration:none;border:1px solid #cbd5e1;background:#fff;color:#0f172a}
        .bexia-btn-primary{border-color:#2563eb;background:#2563eb;color:#fff}
        .bexia-card{border:1px solid #dbe3ef;background:#fff;border-radius:18px;box-shadow:0 12px 26px rgba(15,23,42,.06);overflow:hidden;margin-bottom:18px}
        .bexia-card-header{padding:20px 24px;border-bottom:1px solid #e5e7eb;background:#f8fafc}
        .bexia-title{font-size:22px;font-weight:850;color:#020617}
        .bexia-subtitle{margin-top:5px;font-size:14px;color:#64748b}
        .bexia-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:20px 24px}
        .bexia-field{border:1px solid #dbe3ef;border-radius:14px;background:#fbfdff;padding:13px 15px;min-height:76px}
        .bexia-label{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
        .bexia-value{margin-top:8px;font-size:15px;font-weight:600;color:#020617;word-break:break-word}
        .bexia-table{width:100%;border-collapse:collapse;font-size:14px}
        .bexia-table th{background:#f8fafc;border-bottom:1px solid #dbe3ef;padding:12px;text-align:left;font-weight:800;color:#334155}
        .bexia-table td{border-bottom:1px solid #edf2f7;padding:12px;vertical-align:top}
        @media(max-width:1100px){.bexia-grid{grid-template-columns:1fr}}
    </style>

    <div class="bexia-detail-actions">
<a href="{{ $this->printUrl() }}" target="_blank" rel="noopener" class="bexia-btn">
            Imprimir
        </a>

        @if($this->receiptUrl())
            <a href="{{ $this->receiptUrl() }}" class="bexia-btn">Ver recepción</a>
        @endif

        @if($this->lotUrl())
            <a href="{{ $this->lotUrl() }}" class="bexia-btn">Ver lote</a>
        @endif
    </div>

    <div class="bexia-card">
        <div class="bexia-card-header">
            <div class="bexia-title">{{ $value($serial->serial_number ?? null) }}</div>
            <div class="bexia-subtitle">Detalle del número de serie. Los datos de compra e importación se muestran en las secciones inferiores.</div>
        </div>

        <div class="bexia-grid">
            <div class="bexia-field">
                <div class="bexia-label">Producto</div>
                <div class="bexia-value">{{ $productLabel }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Variante</div>
                <div class="bexia-value">{{ $value($variant->name ?? $receiptLine->variant_label ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Estado</div>
                <div class="bexia-value">{{ $statusText($serial->status ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Lote</div>
                <div class="bexia-value">{{ $lotNumber }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Recepción</div>
                <div class="bexia-value">{{ $value($receipt->number ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Movimiento</div>
                <div class="bexia-value">{{ $value($movement->reference ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Almacén</div>
                <div class="bexia-value">{{ $value($warehouse->name ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Ubicación</div>
                <div class="bexia-value">{{ $value($location->name ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Cantidad</div>
                <div class="bexia-value">1</div>
            </div>
        </div>
    </div>

    <div class="bexia-card">
        <div class="bexia-card-header">
            <div class="bexia-title">Trazabilidad / Importación</div>
            <div class="bexia-subtitle">Datos capturados en la recepción para este número de serie.</div>
        </div>

        <div class="bexia-grid">
            <div class="bexia-field">
                <div class="bexia-label">Número de motor</div>
                <div class="bexia-value">{{ $value($serial->motor_number ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Número de pedimento</div>
                <div class="bexia-value">{{ $value($serial->customs_entry_number ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Fecha pedimento</div>
                <div class="bexia-value">{{ $value($serial->customs_entry_date ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Aduana</div>
                <div class="bexia-value">{{ $value($serial->customs_office ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Modelo importado</div>
                <div class="bexia-value">{{ $value($serial->imported_model ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Color importado</div>
                <div class="bexia-value">{{ $value($serial->imported_color ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Referencia documento/factura</div>
                <div class="bexia-value">{{ $value($serial->import_document_reference ?? null) }}</div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
