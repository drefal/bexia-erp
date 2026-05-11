@if(! empty($purchaseOrderId))
    @livewire('purchase-order-lines-inline', ['purchaseOrderId' => (int) $purchaseOrderId], key('purchase-order-lines-inline-' . $purchaseOrderId))
@else
    <div class="text-sm text-gray-500">
        Guarda primero la orden para editar productos.
    </div>
@endif
