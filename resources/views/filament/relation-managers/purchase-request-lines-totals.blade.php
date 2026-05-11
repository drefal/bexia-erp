@php
    $subtotal = (float) ($record?->total_without_tax ?? 0);
    $iva = (float) ($record?->total_tax ?? 0);
    $total = (float) ($record?->total_with_tax ?? 0);
@endphp

<div style="border-top:1px solid #e5e7eb;background:#f8fafc;padding:14px 16px;">
    <div style="display:flex;justify-content:flex-end;">
        <div style="min-width:320px;max-width:420px;width:100%;">
            <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;">
                <span style="color:#475569;">Importe sin impuestos:</span>
                <strong>$ {{ number_format($subtotal, 2) }}</strong>
            </div>

            <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;">
                <span style="color:#475569;">IVA:</span>
                <strong>$ {{ number_format($iva, 2) }}</strong>
            </div>

            <div style="display:flex;justify-content:space-between;margin-top:8px;padding-top:8px;border-top:1px solid #cbd5e1;font-size:15px;">
                <span style="font-weight:700;">Total:</span>
                <strong>$ {{ number_format($total, 2) }}</strong>
            </div>
        </div>
    </div>
</div>
