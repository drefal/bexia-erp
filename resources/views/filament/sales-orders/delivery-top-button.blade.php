@php
    $bexiaDeliveryOrder = null;
    $bexiaDeliveryUrl = null;

    if (isset($saleOrderId) && $saleOrderId) {
        $bexiaDeliveryOrder = \Illuminate\Support\Facades\DB::table('sales_orders')
            ->where('id', (int) $saleOrderId)
            ->first();
    }

    if (
        $bexiaDeliveryOrder
        && in_array((string) ($bexiaDeliveryOrder->status ?? ''), ['confirmed', 'partially_delivered'], true)
        && \Illuminate\Support\Facades\Route::has('sales-orders.deliveries.page')
    ) {
        try {
            $bexiaDeliveryUrl = route('sales-orders.deliveries.page', [
                'saleOrder' => $bexiaDeliveryOrder->id,
            ]);
        } catch (\Throwable $e) {
            $bexiaDeliveryUrl = url('/sales-orders/' . $bexiaDeliveryOrder->id . '/delivery');
        }
    }
@endphp

@if($bexiaDeliveryOrder && $bexiaDeliveryUrl)
    <div style="margin-bottom: 16px; border: 2px solid #f59e0b; background: #fffbeb; border-radius: 14px; padding: 16px;">
        <div style="display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
            <div>
                <div style="font-size: 15px; font-weight: 800; color: #78350f;">
                    Orden confirmada lista para entrega
                </div>
                <div style="font-size: 13px; color: #92400e; margin-top: 3px;">
                    Crea una entrega completa o parcial. En V5.29.3 todavía no se descuenta inventario.
                </div>
            </div>

            <a
                href="{{ $bexiaDeliveryUrl }}"
                style="display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; background: #d97706; color: white; padding: 10px 20px; font-size: 14px; font-weight: 800; text-decoration: none;"
            >
                Entrega
            </a>
        </div>
    </div>
@endif
