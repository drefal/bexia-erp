<x-filament-panels::page>
    @php
        $serial = $this->serial();
        $product = $this->product();
        $receipt = $this->receipt();
        $lot = $this->lot();
        $logoUrl = $this->companyLogoUrl();

        $value = fn ($v) => filled($v ?? null) ? $v : '—';

        $productLabel = trim(implode(' - ', array_filter([
            $product->internal_reference ?? $product->sku ?? null,
            $product->name ?? null,
        ]))) ?: '—';

        $lotNumber = $lot->lot_number ?? $lot->number ?? $serial->lot_number ?? '—';
    @endphp

    <style>
        @page { size: letter; margin: 14mm; }

        .fi-header,
        .fi-sidebar,
        .fi-topbar,
        .fi-breadcrumbs,
        .fi-page-header,
        nav {
            display: none !important;
        }

        body {
            background: #ffffff !important;
        }

        .bexia-print-page {
            max-width: 900px;
            margin: 0 auto;
            color: #111827;
            font-family: Arial, sans-serif;
        }

        .bexia-print-logo {
            width: 100%;
            min-height: 92px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 22px;
            padding-bottom: 14px;
        }

        .bexia-print-logo img {
            max-width: 100%;
            max-height: 92px;
            object-fit: contain;
        }

        .bexia-print-title {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .bexia-print-subtitle {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 18px;
        }

        .bexia-section {
            border: 1px solid #d1d5db;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 16px;
        }

        .bexia-section-title {
            background: #f9fafb;
            padding: 10px 12px;
            font-weight: 800;
            border-bottom: 1px solid #d1d5db;
        }

        .bexia-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
        }

        .bexia-field {
            padding: 10px 12px;
            border-right: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            min-height: 54px;
        }

        .bexia-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6b7280;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .bexia-value {
            font-size: 13px;
            font-weight: 500;
            word-break: break-word;
        }

        .bexia-actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 16px;
        }

        .bexia-print-btn {
            border: 1px solid #2563eb;
            background: #2563eb;
            color: white;
            border-radius: 10px;
            padding: 9px 14px;
            font-weight: 800;
            cursor: pointer;
        }

        @media print {
            .bexia-actions {
                display: none !important;
            }

            .bexia-print-page {
                max-width: none;
                margin: 0;
            }
        }
    </style>

    <div class="bexia-print-page">
        <div class="bexia-actions">
            <button type="button" class="bexia-print-btn" onclick="window.print()">Imprimir</button>
        </div>

        <div class="bexia-print-logo">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="Logo empresa">
            @endif
        </div>

        <div class="bexia-print-title">Detalle de número de serie</div>
        <div class="bexia-print-subtitle">Documento interno de trazabilidad.</div>

        <div class="bexia-section">
            <div class="bexia-section-title">Información general</div>

            <div class="bexia-grid">
                <div class="bexia-field">
                    <div class="bexia-label">Serie / VIN</div>
                    <div class="bexia-value">{{ $value($serial->serial_number ?? null) }}</div>
                </div>

                <div class="bexia-field">
                    <div class="bexia-label">Producto</div>
                    <div class="bexia-value">{{ $productLabel }}</div>
                </div>

                <div class="bexia-field">
                    <div class="bexia-label">Lote</div>
                    <div class="bexia-value">{{ $lotNumber }}</div>
                </div>

                <div class="bexia-field">
                    <div class="bexia-label">Estado</div>
                    <div class="bexia-value">{{ $value($serial->status ?? null) }}</div>
                </div>

                <div class="bexia-field">
                    <div class="bexia-label">Recepción</div>
                    <div class="bexia-value">{{ $value($receipt->number ?? null) }}</div>
                </div>

                <div class="bexia-field">
                    <div class="bexia-label">Fecha recepción</div>
                    <div class="bexia-value">{{ $value($receipt->received_at ?? null) }}</div>
                </div>
            </div>
        </div>

        <div class="bexia-section">
            <div class="bexia-section-title">Trazabilidad / importación</div>

            <div class="bexia-grid">
                <div class="bexia-field">
                    <div class="bexia-label">Motor</div>
                    <div class="bexia-value">{{ $value($serial->motor_number ?? null) }}</div>
                </div>

                <div class="bexia-field">
                    <div class="bexia-label">Pedimento</div>
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
    </div>
</x-filament-panels::page>
