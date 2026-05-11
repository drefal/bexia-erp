@php
    $logs = collect();

    if (! empty($purchaseOrderId) && \Illuminate\Support\Facades\Schema::hasTable('purchase_order_status_logs')) {
        $query = \Illuminate\Support\Facades\DB::table('purchase_order_status_logs')
            ->where('purchase_order_id', $purchaseOrderId)
            ->orderByDesc('created_at')
            ->limit(50);

        if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
            $query->leftJoin('users', 'users.id', '=', 'purchase_order_status_logs.user_id')
                ->select(
                    'purchase_order_status_logs.*',
                    'users.name as user_name'
                );
        }

        $logs = $query->get();
    }
@endphp

<div style="overflow-x:auto;">
    <table style="width:100%; border-collapse:collapse; font-size:12px;">
        <thead>
            <tr style="background:#f8fafc;">
                <th style="text-align:left; padding:10px 8px; border-bottom:1px solid #d9e2ef;">Fecha</th>
                <th style="text-align:left; padding:10px 8px; border-bottom:1px solid #d9e2ef;">Usuario</th>
                <th style="text-align:left; padding:10px 8px; border-bottom:1px solid #d9e2ef;">Evento</th>
                <th style="text-align:left; padding:10px 8px; border-bottom:1px solid #d9e2ef;">Anterior</th>
                <th style="text-align:left; padding:10px 8px; border-bottom:1px solid #d9e2ef;">Nuevo</th>
                <th style="text-align:left; padding:10px 8px; border-bottom:1px solid #d9e2ef;">Detalle</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                @php
                    $metadata = $log->metadata ? json_decode($log->metadata, true) : [];
                @endphp

                <tr>
                    <td style="padding:9px 8px; border-bottom:1px solid #eef2f7; white-space:nowrap;">
                        {{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i') }}
                    </td>

                    <td style="padding:9px 8px; border-bottom:1px solid #eef2f7;">
                        {{ $log->user_name ?? 'Sistema' }}
                    </td>

                    <td style="padding:9px 8px; border-bottom:1px solid #eef2f7;">
                        {{ \App\Support\PurchaseOrderHistory::eventLabel($log->event) }}
                    </td>

                    <td style="padding:9px 8px; border-bottom:1px solid #eef2f7;">
                        {{ \App\Support\PurchaseOrderHistory::statusLabel($log->from_status) }}
                    </td>

                    <td style="padding:9px 8px; border-bottom:1px solid #eef2f7;">
                        {{ \App\Support\PurchaseOrderHistory::statusLabel($log->to_status) }}
                    </td>

                    <td style="padding:9px 8px; border-bottom:1px solid #eef2f7;">
                        {{ $log->notes ?: '—' }}

                        @if(! empty($metadata['before_total']) || ! empty($metadata['after_total']))
                            <div style="font-size:11px; color:#64748b; margin-top:3px;">
                                Total anterior:
                                $ {{ number_format((float) ($metadata['before_total'] ?? 0), 2) }}
                                →
                                Total nuevo:
                                $ {{ number_format((float) ($metadata['after_total'] ?? 0), 2) }}
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="padding:16px; text-align:center; color:#64748b;">
                        No hay movimientos registrados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
