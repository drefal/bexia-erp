<x-filament-panels::page>
    <div style="display:grid; gap:18px;">
        <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between;">
            <div>
                <div style="font-size:13px; color:#64748b; font-weight:700;">Sesión</div>
                <div style="font-size:22px; font-weight:950; color:#111827;">
                    {{ $record->number ?? ('#' . $record->id) }}
                </div>
            </div>

            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                @if($canDownloadReport ?? false)
                    <a href="{{ $reportUrl }}" target="_blank"
                       style="display:inline-flex; align-items:center; padding:9px 12px; border-radius:10px; background:#f8fafc; border:1px solid #cbd5e1; font-weight:800; font-size:13px;">
                        Abrir reporte en pestaña
                    </a>
                @endif

                @if($canPrintCloseTicket ?? false)
                    <a href="{{ $closeTicketUrl }}" target="_blank"
                       style="display:inline-flex; align-items:center; padding:9px 12px; border-radius:10px; background:#ecfdf5; border:1px solid #bbf7d0; font-weight:800; font-size:13px;">
                        Imprimir ticket cierre
                    </a>
                @endif
            </div>
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
