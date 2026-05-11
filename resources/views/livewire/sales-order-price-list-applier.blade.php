<div x-data @sales-order-prices-applied.window="setTimeout(() => window.location.reload(), 700)" style="border:1px solid #dbe3ef;border-radius:14px;background:#ffffff;padding:12px 14px;margin-top:8px;">
    <div style="font-size:13px;font-weight:600;color:#0f172a;">
        Aplicar lista de precios
    </div>

    <div style="font-size:12px;color:#64748b;margin-top:2px;">
        Lista aplicada actualmente: {{ $this->appliedListName }}
    </div>

    <div style="display:grid;grid-template-columns:minmax(220px, 1fr) auto;gap:10px;align-items:end;margin-top:10px;">
        <div>
            <label style="font-size:12px;color:#475569;display:block;margin-bottom:4px;">Lista a aplicar</label>
            <select wire:model.live="selectedPriceListId" style="width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:8px 10px;font-size:13px;background:white;">
                <option value="">Selecciona...</option>
                @foreach($this->options as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <button
            type="button"
            wire:click="applyPriceList"
            @if(! $this->canApply) disabled @endif
            style="border:0;border-radius:10px;padding:9px 13px;font-size:13px;font-weight:600;{{ $this->canApply ? 'background:#f59e0b;color:white;cursor:pointer;' : 'background:#e2e8f0;color:#94a3b8;cursor:not-allowed;' }}"
        >
            Aplicar lista
        </button>
    </div>

    @if(! $this->canApply)
        <div style="font-size:11px;color:#64748b;margin-top:7px;">
            Cambia la lista para aplicar precios, o revisa el estado del documento. {{ $this->permissionMessage }}
        </div>
    @endif

    @if($message)
        <div style="font-size:12px;color:#166534;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:10px;padding:8px 10px;margin-top:10px;">
            {{ $message }} La pantalla se actualizará automáticamente.
        </div>
    @endif

    @if($errorMessage)
        <div style="font-size:12px;color:#991b1b;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:8px 10px;margin-top:10px;">
            {{ $errorMessage }}
        </div>
    @endif
</div>
