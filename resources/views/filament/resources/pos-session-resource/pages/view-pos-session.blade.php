<x-filament-panels::page>
    <div style="display:grid; gap:18px;">
        <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between;">
            <div>
                <div style="font-size:13px; color:#64748b; font-weight:700;">Sesión</div>
                <div style="font-size:22px; font-weight:950; color:#111827;">
                    {{ $record->number ?? ('#' . $record->id) }}
                </div>
            </div>

            {{-- BEXIA_V5836_PDV1F2_DUPLICATES_REMOVED
                 Los botones oficiales son las acciones superiores de Filament:
                 Descargar reporte / Imprimir ticket cierre.
            --}}
        </div>

        <div style="border:1px solid #e5e7eb; border-radius:16px; background:#fff; overflow:hidden;">
            <div style="padding:14px 16px; border-bottom:1px solid #e5e7eb; background:#f8fafc;">
                <div style="font-weight:950; color:#111827;">Formato de reporte de cierre</div>
                <div style="font-size:13px; color:#64748b; margin-top:3px;">
                    Vista previa del reporte de sesión. También puedes abrirlo en otra pestaña.
                </div>
            </div>

            @if($canDownloadReport ?? false)
                <iframe
                    src="{{ $reportUrl }}"
                    style="width:100%; min-height:720px; border:0; background:#fff;"
                    loading="lazy"
                ></iframe>
            @else
                <div style="padding:22px; color:#64748b; text-align:center;">
                    No tienes permiso para ver el reporte de cierre.
                </div>
            @endif
        </div>


        {{-- BEXIA_V5836_POS53C1_SESSION_TICKETS --}}
        @if($canViewSessionTickets ?? false)
        <div style="border:1px solid #e5e7eb;border-radius:16px;background:#fff;overflow:hidden;">
            <div style="padding:14px 16px;border-bottom:1px solid #e5e7eb;background:#f8fafc;">
                <div style="font-weight:950;color:#111827;">Tickets vinculados a esta sesión</div>
                <div style="font-size:13px;color:#64748b;margin-top:3px;">
                    Incluye tickets creados aquí y cobros realizados aquí sobre tickets anteriores.
                    El importe recibido corresponde solamente a esta sesión.
                </div>
            </div>

            @if($sessionTickets->isEmpty())
                <div style="padding:20px;text-align:center;color:#64748b;">
                    No se encontraron tickets vinculados a esta sesión.
                </div>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="text-align:left;padding:11px;">Folio</th>
                                <th style="text-align:left;padding:11px;">Relación</th>
                                <th style="text-align:left;padding:11px;">Estado</th>
                                <th style="text-align:right;padding:11px;">Total ticket</th>
                                {{-- BEXIA_V5836_POS53C1A_PAYMENT_COUNT --}}
                                <th style="text-align:center;padding:11px;">Pagos del ticket</th>
                                <th style="text-align:right;padding:11px;">Cobrado en sesión</th>
                                <th style="text-align:center;padding:11px;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sessionTickets as $ticket)
                            <tr>
                                <td style="padding:11px;border-top:1px solid #e5e7eb;font-weight:800;">
                                    {{ $ticket->number }}
                                </td>
                                <td style="padding:11px;border-top:1px solid #e5e7eb;">
                                    {{ (int) $ticket->pos_session_id === (int) $record->id ? 'Creado aquí' : 'Cobrado aquí' }}
                                </td>
                                <td style="padding:11px;border-top:1px solid #e5e7eb;">
                                    {{ match ((string) $ticket->status) {
                                        'paid' => 'Pagado',
                                        'pending_payment' => 'Pendiente',
                                        'cancelled', 'canceled' => 'Cancelado',
                                        default => (string) $ticket->status,
                                    } }}
                                </td>
                                <td style="padding:11px;border-top:1px solid #e5e7eb;text-align:right;white-space:nowrap;">
                                    ${{ number_format((float) $ticket->total, 2) }}
                                </td>
                                <td style="padding:11px;border-top:1px solid #e5e7eb;text-align:center;">
                                    {{-- BEXIA_V5836_POS53C1C_SESSION_PAYMENT_ONLY --}}
                                    <span style="color:#334155;font-weight:700;white-space:nowrap;">
                                        {{ (int) $ticket->all_payment_count }} {{ (int) $ticket->all_payment_count === 1 ? 'pago' : 'pagos' }}
                                    </span>
                                </td>
                                <td style="padding:11px;border-top:1px solid #e5e7eb;text-align:right;white-space:nowrap;">
                                    ${{ number_format((float) $ticket->session_paid, 2) }}
                                </td>
                                <td style="padding:11px;border-top:1px solid #e5e7eb;text-align:center;">
                                    <a href="{{ \App\Filament\Resources\PosTicketResource::getUrl('view', ['record' => $ticket->id]) }}"
                                       style="color:#2563eb;font-weight:800;text-decoration:underline;">
                                        Ver ticket
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @endif

        @if($canViewPriceListChanges ?? false)
        <div style="border:1px solid #e5e7eb; border-radius:16px; background:#fff; overflow:hidden;">
            <div style="padding:14px 16px; border-bottom:1px solid #e5e7eb; background:#f8fafc;">
                <div style="font-weight:950; color:#111827;">Cambios de listas de precios</div>
                <div style="font-size:13px; color:#64748b; margin-top:3px;">
                    Movimientos manuales o automáticos por cliente registrados dentro de esta sesión.
                </div>
            </div>

            @if($priceListChanges->isEmpty())
                <div style="padding:20px; color:#64748b; text-align:center;">
                    No hay cambios de lista registrados para esta sesión.
                </div>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="text-align:left; padding:10px; border-bottom:1px solid #e5e7eb;">Fecha/hora</th>
                                <th style="text-align:left; padding:10px; border-bottom:1px solid #e5e7eb;">Usuario</th>
                                <th style="text-align:left; padding:10px; border-bottom:1px solid #e5e7eb;">Cliente</th>
                                <th style="text-align:left; padding:10px; border-bottom:1px solid #e5e7eb;">Lista anterior</th>
                                <th style="text-align:left; padding:10px; border-bottom:1px solid #e5e7eb;">Lista nueva</th>
                                <th style="text-align:left; padding:10px; border-bottom:1px solid #e5e7eb;">Origen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($priceListChanges as $change)
                                @php
                                    $source = (string) ($change->source ?? '');
                                    $sourceLabel = match (true) {
                                        $source === 'manual' => 'Manual',
                                        str_contains($source, 'customer') || str_contains($source, 'cliente') || str_contains($source, 'select-customer') => 'Cliente',
                                        default => $source !== '' ? $source : 'No especificado',
                                    };
                                @endphp

                                <tr>
                                    <td style="padding:10px; border-bottom:1px solid #f1f5f9; white-space:nowrap;">
                                        {{ $change->changed_at ? \Illuminate\Support\Carbon::parse($change->changed_at)->format('Y-m-d H:i:s') : '—' }}
                                    </td>
                                    <td style="padding:10px; border-bottom:1px solid #f1f5f9;">
                                        {{ $change->user_name ?: ('Usuario #' . ($change->user_id ?? '—')) }}
                                    </td>
                                    <td style="padding:10px; border-bottom:1px solid #f1f5f9;">
                                        {{ $change->customer_name ?: (($change->customer_id ?? null) ? ('Cliente #' . $change->customer_id) : '—') }}
                                    </td>
                                    <td style="padding:10px; border-bottom:1px solid #f1f5f9;">
                                        {{ $change->previous_price_list_name ?: (($change->previous_price_list_id ?? null) ? ('Lista #' . $change->previous_price_list_id) : '—') }}
                                    </td>
                                    <td style="padding:10px; border-bottom:1px solid #f1f5f9; font-weight:900;">
                                        {{ $change->new_price_list_name ?: (($change->new_price_list_id ?? null) ? ('Lista #' . $change->new_price_list_id) : '—') }}
                                    </td>
                                    <td style="padding:10px; border-bottom:1px solid #f1f5f9;">
                                        {{ $sourceLabel }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @endif
    </div>
</x-filament-panels::page>
