@if(! empty($saleOrderId))
    @livewire('sales-order-price-list-applier', ['saleOrderId' => (int) $saleOrderId], key('sales-order-price-list-applier-' . $saleOrderId))
@endif
