@if(! empty($saleOrderId))
    @livewire('sale-order-lines-inline', ['saleOrderId' => (int) $saleOrderId], key('sale-order-lines-inline-' . $saleOrderId))
@else
    <div class="text-sm text-gray-500">
        Guarda primero la cotización para editar productos.
    </div>
@endif
