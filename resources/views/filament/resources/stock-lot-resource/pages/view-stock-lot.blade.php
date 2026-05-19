<x-filament-panels::page>
    @php
        $lot = $this->lot();
        $product = $this->product();
        $receipt = $this->receipt();
        $stats = $this->stats();

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


        $qty = fn ($v) => number_format((float) $v, 6);

        $productLabel = trim(implode(' - ', array_filter([
            $product->internal_reference ?? $product->sku ?? null,
            $product->name ?? null,
        ]))) ?: '—';

        $lotNumber = $lot->lot_number ?? $lot->number ?? '—';
    @endphp

    <style>
        .bexia-detail-actions{display:flex;justify-content:flex-end;gap:10px;margin-bottom:18px}
        .bexia-btn{display:inline-flex;align-items:center;justify-content:center;min-height:40px;border-radius:12px;padding:0 16px;font-size:14px;font-weight:800;text-decoration:none;border:1px solid #cbd5e1;background:#fff;color:#0f172a}
        .bexia-card{border:1px solid #dbe3ef;background:#fff;border-radius:18px;box-shadow:0 12px 26px rgba(15,23,42,.06);overflow:hidden;margin-bottom:18px}
        .bexia-card-header{padding:20px 24px;border-bottom:1px solid #e5e7eb;background:#f8fafc}
        .bexia-title{font-size:22px;font-weight:850;color:#020617}
        .bexia-subtitle{margin-top:5px;font-size:14px;color:#64748b}
        .bexia-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:20px 24px}
        .bexia-stat-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:20px 24px}
        .bexia-field,.bexia-stat{border:1px solid #dbe3ef;border-radius:14px;background:#fbfdff;padding:13px 15px;min-height:76px}
        .bexia-label{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
        .bexia-value{margin-top:8px;font-size:15px;font-weight:600;color:#020617;word-break:break-word}
        .bexia-stat-value{margin-top:8px;font-size:28px;font-weight:850;color:#020617;line-height:1}
        .bexia-stat-muted{margin-top:8px;font-size:12px;color:#64748b}
        .bexia-stat-total{background:#eff6ff;border-color:#bfdbfe}
        .bexia-stat-sold{background:#fff7ed;border-color:#fed7aa}
        .bexia-stat-remaining{background:#ecfdf5;border-color:#a7f3d0}
        @media(max-width:1100px){.bexia-grid,.bexia-stat-grid{grid-template-columns:1fr}}
    </style>

    <div class="bexia-detail-actions">
<a href="{{ $this->printUrl() }}" target="_blank" rel="noopener" class="bexia-btn">
            Imprimir
        </a>

        @if($this->receiptUrl())
            <a href="{{ $this->receiptUrl() }}" class="bexia-btn">Ver recepción</a>
        @endif
    </div>

    <div class="bexia-card">
        <div class="bexia-card-header">
            <div class="bexia-title">{{ $lotNumber }}</div>
            <div class="bexia-subtitle">Detalle y resumen de existencias del lote.</div>
        </div>

        <div class="bexia-stat-grid">
            <div class="bexia-stat bexia-stat-total">
                <div class="bexia-label">Productos en el lote</div>
                <div class="bexia-stat-value">{{ $qty($stats['total'] ?? 0) }}</div>
                <div class="bexia-stat-muted">Total recibido o registrado para este lote.</div>
            </div>

            <div class="bexia-stat bexia-stat-sold">
                <div class="bexia-label">Vendidos / salidos</div>
                <div class="bexia-stat-value">{{ $qty($stats['sold'] ?? 0) }}</div>
                <div class="bexia-stat-muted">Diferencia entre total del lote y disponible.</div>
            </div>

            <div class="bexia-stat bexia-stat-remaining">
                <div class="bexia-label">Disponibles / quedan</div>
                <div class="bexia-stat-value">{{ $qty($stats['remaining'] ?? 0) }}</div>
                <div class="bexia-stat-muted">{{ $stats['source'] ?? 'Existencia actual' }}</div>
            </div>
        </div>
    </div>

    <div class="bexia-card">
        <div class="bexia-card-header">
            <div class="bexia-title">Información del lote</div>
            <div class="bexia-subtitle">Datos generales del lote seleccionado.</div>
        </div>

        <div class="bexia-grid">
            <div class="bexia-field">
                <div class="bexia-label">Producto</div>
                <div class="bexia-value">{{ $productLabel }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Estado</div>
                <div class="bexia-value">{{ $statusText($lot->status ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Recepción</div>
                <div class="bexia-value">{{ $value($receipt->number ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Fecha caducidad</div>
                <div class="bexia-value">{{ $value($lot->expiration_date ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Creado</div>
                <div class="bexia-value">{{ $value($lot->created_at ?? null) }}</div>
            </div>

            <div class="bexia-field">
                <div class="bexia-label">Notas</div>
                <div class="bexia-value">{{ $value($lot->notes ?? null) }}</div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
