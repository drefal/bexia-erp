@php
    $changes = collect($changes ?? []);
@endphp

<div style="display:grid; gap:14px;">
    <div style="border:1px solid #e5e7eb; border-radius:14px; padding:12px 14px; background:#f8fafc;">
        <div style="font-weight:900; color:#111827;">Cambios de lista de precios</div>
        <div style="font-size:13px; color:#64748b; margin-top:4px;">
            Historial de cambios manuales o automáticos por cliente dentro de esta sesión PDV.
        </div>
    </div>

    @if($changes->isEmpty())
        <div style="border:1px dashed #cbd5e1; border-radius:14px; padding:18px; color:#64748b; text-align:center;">
            No hay cambios de lista registrados para esta sesión.
        </div>
    @else
        <div style="overflow-x:auto; border:1px solid #e5e7eb; border-radius:14px;">
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
                    @foreach($changes as $change)
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
                            <td style="padding:10px; border-bottom:1px solid #f1f5f9; font-weight:800;">
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
