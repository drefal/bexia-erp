<x-filament-panels::page>
    @php
        // BEXIA_V5526B2_GLOBAL_INVOICE_UI_INLINE_FILTERS
        // BEXIA_V5526B3_LIVE_TICKET_SELECTION
        $tickets = $this->tickets();
        $counters = $this->counters();

        $inputStyle = 'width:100%;height:42px;border:1px solid #d1d5db;border-radius:10px;padding:8px 10px;background:white;';
        $primaryBtn = 'display:inline-flex;align-items:center;justify-content:center;border-radius:10px;padding:10px 14px;background:#2563eb;color:white;font-weight:700;font-size:13px;border:0;cursor:pointer;';
        $grayBtn = 'display:inline-flex;align-items:center;justify-content:center;border-radius:10px;padding:10px 14px;background:#e5e7eb;color:#111827;font-weight:700;font-size:13px;border:0;cursor:pointer;';
        $successBtn = 'display:inline-flex;align-items:center;justify-content:center;border-radius:10px;padding:10px 14px;background:#16a34a;color:white;font-weight:700;font-size:13px;border:0;cursor:pointer;';
        $disabledBtn = 'display:inline-flex;align-items:center;justify-content:center;border-radius:10px;padding:10px 14px;background:#d1d5db;color:#6b7280;font-weight:700;font-size:13px;border:0;cursor:not-allowed;';
    @endphp

    <div style="display:flex;flex-direction:column;gap:24px;">
        <div style="background:white;border:1px solid #e5e7eb;border-radius:14px;padding:16px;">
            <div style="display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:14px;align-items:end;">
                <div style="grid-column:span 2;">
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:6px;">Desde</label>
                    <input type="date" wire:model.defer="date_from" style="{{ $inputStyle }}" />
                </div>

                <div style="grid-column:span 2;">
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:6px;">Hasta</label>
                    <input type="date" wire:model.defer="date_to" style="{{ $inputStyle }}" />
                </div>

                <div style="grid-column:span 2;">
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:6px;">Periodicidad</label>
                    <select wire:model.defer="periodicity" style="{{ $inputStyle }}">
                        <option value="01">01 - Diario</option>
                        <option value="02">02 - Semanal</option>
                        <option value="03">03 - Quincenal</option>
                        <option value="04">04 - Mensual</option>
                    </select>
                </div>

                <div style="grid-column:span 2;">
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:6px;">Mes</label>
                    <select wire:model.defer="month" style="{{ $inputStyle }}">
                        @foreach (range(1, 12) as $m)
                            <option value="{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}">
                                {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div style="grid-column:span 2;">
                    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:6px;">Año</label>
                    <input type="number" wire:model.defer="year" style="{{ $inputStyle }}" />
                </div>

                <div style="grid-column:span 2;">
                    <button type="button" wire:click="$refresh" style="{{ $primaryBtn }}width:100%;height:42px;">
                        Buscar
                    </button>
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;">
            <div style="background:white;border:1px solid #e5e7eb;border-radius:14px;padding:16px;">
                <div style="font-size:12px;color:#6b7280;">Tickets elegibles</div>
                <div style="font-size:24px;font-weight:800;">{{ number_format($counters['count']) }}</div>
            </div>

            <div style="background:white;border:1px solid #e5e7eb;border-radius:14px;padding:16px;">
                <div style="font-size:12px;color:#6b7280;">Seleccionados</div>
                <div style="font-size:24px;font-weight:800;">{{ number_format($counters['selected_count']) }}</div>
            </div>

            <div style="background:white;border:1px solid #e5e7eb;border-radius:14px;padding:16px;">
                <div style="font-size:12px;color:#6b7280;">IVA</div>
                <div style="font-size:24px;font-weight:800;">${{ number_format((float) $counters['tax_total'], 2) }}</div>
            </div>

            <div style="background:white;border:1px solid #e5e7eb;border-radius:14px;padding:16px;">
                <div style="font-size:12px;color:#6b7280;">Total filtrado</div>
                <div style="font-size:24px;font-weight:800;">${{ number_format((float) $counters['total'], 2) }}</div>
            </div>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:10px;">
            <button
                type="button"
                wire:click="selectAllFiltered"
                style="{{ count($tickets) > 0 ? $primaryBtn : $disabledBtn }}"
                @if (count($tickets) === 0) disabled @endif
            >
                Seleccionar todos filtrados
            </button>

            <button type="button" wire:click="clearSelection" style="{{ $grayBtn }}">
                Limpiar selección
            </button>

            <button
                type="button"
                wire:click="createDraft"
                style="{{ count($this->selectedTicketIds) > 0 ? $successBtn : $disabledBtn }}"
                @if (count($this->selectedTicketIds) === 0) disabled @endif
            >
                Crear factura global en borrador
            </button>

            @if ($lastInvoiceUrl)
                <a href="{{ $lastInvoiceUrl }}" style="{{ $primaryBtn }}text-decoration:none;">
                    Ver última factura global
                </a>
            @endif
        </div>

        <div style="overflow:hidden;background:white;border:1px solid #e5e7eb;border-radius:14px;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead style="background:#f9fafb;">
                    <tr>
                        <th style="padding:12px;text-align:left;width:60px;">Sel.</th>
                        <th style="padding:12px;text-align:left;">Ticket</th>
                        <th style="padding:12px;text-align:left;">Fecha</th>
                        <th style="padding:12px;text-align:right;">Subtotal</th>
                        <th style="padding:12px;text-align:right;">IVA</th>
                        <th style="padding:12px;text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr style="border-top:1px solid #f3f4f6;">
                            <td style="padding:12px;">
                                <input type="checkbox" wire:model.live="selectedTicketIds" value="{{ $ticket['id'] }}" />
                            </td>
                            <td style="padding:12px;font-weight:700;">{{ $ticket['number'] }}</td>
                            <td style="padding:12px;">{{ $ticket['created_at'] }}</td>
                            <td style="padding:12px;text-align:right;">${{ number_format((float) $ticket['subtotal'], 2) }}</td>
                            <td style="padding:12px;text-align:right;">${{ number_format((float) $ticket['tax_total'], 2) }}</td>
                            <td style="padding:12px;text-align:right;font-weight:800;">${{ number_format((float) $ticket['total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:28px;text-align:center;color:#6b7280;">
                                No hay tickets elegibles con los filtros actuales.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="border:1px solid #93c5fd;background:#eff6ff;color:#1e40af;border-radius:12px;padding:14px;font-size:13px;">
            Esta pantalla crea una factura global en borrador. Todavía falta validar el XML global antes de timbrar.
        </div>
    </div>
</x-filament-panels::page>
