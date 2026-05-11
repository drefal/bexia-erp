@if(! empty($recordId))
    @livewire('purchase-request-lines-inline', ['purchaseRequestId' => $recordId], key('purchase-request-lines-inline-' . $recordId))
@else
    <div style="border:1px solid #e5e7eb;border-radius:14px;background:#f8fafc;padding:16px;color:#64748b;">
        Guarda primero el encabezado de la solicitud. Despues podras agregar productos.
    </div>
@endif
