@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $events = collect();

    if (! empty($saleOrderId)) {
        if (Schema::hasTable('sales_order_events')) {
            $events = $events->merge(
                DB::table('sales_order_events')
                    ->leftJoin('users', 'users.id', '=', 'sales_order_events.user_id')
                    ->where('sales_order_events.sales_order_id', $saleOrderId)
                    ->selectRaw("
                        sales_order_events.created_at as created_at,
                        sales_order_events.title as title,
                        sales_order_events.description as description,
                        COALESCE(users.name, 'Sistema') as user_name
                    ")
                    ->orderByDesc('sales_order_events.created_at')
                    ->get()
            );
        }

        if (Schema::hasTable('approval_requests')) {
            $events = $events->merge(
                DB::table('approval_requests')
                    ->where('approvable_type', \App\Models\SaleOrder::class)
                    ->where('approvable_id', $saleOrderId)
                    ->whereIn('document_type', ['sales_quote', 'sales_:quote', 'sale_quote', 'sales_order', 'sales_margin_approval'])
                    ->selectRaw("
                        created_at,
                        CASE
                            WHEN status = 'approved' THEN 'Aprobación aprobada'
                            WHEN status = 'rejected' THEN 'Aprobación rechazada'
                            WHEN status = 'cancelled' THEN 'Aprobación cancelada'
                            ELSE 'Aprobación solicitada'
                        END as title,
                        COALESCE(last_decision_reason, notes, '') as description,
                        'Flujo de aprobación' as user_name
                    ")
                    ->orderByDesc('created_at')
                    ->get()
            );
        }

        $events = $events
            ->sortByDesc('created_at')
            ->values()
            ->take(30);
    }
@endphp

<div style="width:100%;border:1px solid #dbe3ef;border-radius:14px;background:#fff;overflow:hidden;margin-top:12px;">
    <div style="padding:10px 14px;border-bottom:1px solid #e5edf6;">
        <div style="font-size:13px;font-weight:600;color:#0f172a;">Historial</div>
        <div style="font-size:11px;color:#64748b;margin-top:1px;">
            Cambios, envíos, aprobaciones y rechazos de esta cotización/orden.
        </div>
    </div>

    @if($events->count() === 0)
        <div style="padding:12px 14px;color:#64748b;font-size:12px;">
            Aún no hay eventos registrados.
        </div>
    @else
        <div>
            @foreach($events as $event)
                <div style="padding:9px 14px;border-bottom:1px solid #f1f5f9;">
                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
                        <div style="font-size:12px;font-weight:500;color:#0f172a;">
                            {{ $event->title }}
                        </div>
                        <div style="font-size:11px;color:#64748b;white-space:nowrap;">
                            {{ \Carbon\Carbon::parse($event->created_at)->format('d/m/Y H:i') }}
                        </div>
                    </div>

                    @if(! empty($event->description))
                        <div style="font-size:12px;color:#334155;margin-top:3px;">
                            {{ $event->description }}
                        </div>
                    @endif

                    <div style="font-size:11px;color:#64748b;margin-top:3px;">
                        Usuario: {{ $event->user_name }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
