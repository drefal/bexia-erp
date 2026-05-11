<x-filament-panels::page>
    @include('filament.sales-orders.delivery-field', [
        'saleOrderId' => $this->record->id,
    ])
</x-filament-panels::page>
