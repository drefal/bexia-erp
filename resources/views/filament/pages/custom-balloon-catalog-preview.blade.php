<x-filament-panels::page>
    @if ($editingOrderId)
        <div class="mb-5 rounded-lg border border-blue-300 p-4">
            <strong>Editar pedido existente</strong>
            <p class="mt-1 text-sm">
                Estás modificando un pedido guardado.
                Se conservará el mismo folio al guardar.
            </p>
        </div>
    @endif
    <style>
        .glb-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.75fr) minmax(280px, 1fr);
            gap: 20px;
            align-items: start;
        }
        .glb-panel {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 20px;
        }
        .glb-card-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }
        .glb-product {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            text-align: left;
            background: white;
            cursor: pointer;
            width: 100%;
        }
        .glb-product.active,
        .glb-choice.active {
            border: 2px solid #2563eb;
            background: #eff6ff;
        }
        .glb-image {
            height: 118px;
            width: 100%;
            object-fit: contain;
            background: #f8fafc;
        }
        .glb-placeholder {
            height: 118px;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f1f5f9;
            color: #64748b;
            font-size: 32px;
        }
        .glb-choice {
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px;
            text-align: left;
            background: white;
            cursor: pointer;
            width: 100%;
        }
        .glb-input {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            padding: 10px 12px;
            background: white;
            color: #111827;
        }
        .glb-heading {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .glb-muted {
            font-size: 12px;
            color: #64748b;
        }
        .glb-button {
            background: #2563eb;
            color: white;
            border-radius: 10px;
            padding: 12px 16px;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
        }
        .glb-button:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }
        .glb-model {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            background: white;
        }
        .glb-model.active { outline: 2px solid #2563eb; }
        .glb-model img {
            width: 100%;
            height: 115px;
            object-fit: cover;
        }
        .glb-model-name {
            font-size: 11px;
            padding: 8px;
            min-height: 42px;
        }
        .glb-cart {
            position: sticky;
            top: 20px;
        }
        @media (max-width: 1050px) {
            .glb-layout { grid-template-columns: 1fr; }
            .glb-cart { position: static; }
        }
        @media (max-width: 650px) {
            .glb-card-grid { grid-template-columns: repeat(2, 1fr); }
            .glb-panel { padding: 14px; }
        }
        .dark .glb-panel,
        .dark .glb-product,
        .dark .glb-choice,
        .dark .glb-model {
            background: #111827;
            border-color: #374151;
            color: #f9fafb;
        }
        .dark .glb-input {
            background: #111827;
            border-color: #4b5563;
            color: #f9fafb;
        }
        .dark .glb-product.active,
        .dark .glb-choice.active {
            background: #1e3a5f;
        }

        /* GLB4-F2: selector y galeria compactos */
        .glb-size-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }
        .glb-size-grid .glb-choice {
            text-align: center;
            padding: 11px 8px;
        }
        .glb-model-scroller {
            max-height: 440px;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 3px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
        }
        .glb-model-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            padding: 7px;
        }
        .glb-model {
            width: 100%;
            text-align: left;
        }
        .glb-model img {
            display: block;
            height: 130px;
            object-fit: contain;
            background: #f8fafc;
        }
        .glb-model .glb-placeholder {
            height: 130px;
        }
        .glb-model-name {
            font-size: 12px;
            min-height: 0;
            line-height: 1.35;
        }
        .glb-required-note {
            border-radius: 9px;
            background: #eff6ff;
            color: #1d4ed8;
            padding: 11px 13px;
            font-size: 13px;
            font-weight: 600;
        }
        .dark .glb-model-scroller {
            border-color: #374151;
        }
        .dark .glb-required-note {
            background: #1e3a5f;
            color: #bfdbfe;
        }
        @media (max-width: 1250px) {
            .glb-model-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        @media (max-width: 650px) {
            .glb-model-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }


        /* GLB4-F2 UI2: selector visual y modal */
        .glb-model-card {
            position: relative;
        }
        .glb-select-indicator {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 28px;
            height: 28px;
            border-radius: 9999px;
            border: 2px solid #cbd5e1;
            background: rgba(255,255,255,.95);
            color: #2563eb;
            font-size: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 4px rgba(0,0,0,.12);
            z-index: 2;
        }
        .glb-select-indicator.is-selected {
            border-color: #2563eb;
            background: #dbeafe;
        }
        .glb-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 60;
            background: rgba(15, 23, 42, .62);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }
        .glb-modal {
            width: min(940px, 96vw);
            max-height: 94vh;
            overflow: auto;
            border-radius: 18px;
            background: #ffffff;
            padding: 18px;
            box-shadow: 0 28px 70px rgba(15, 23, 42, .28);
        }
        .glb-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 12px;
        }
        .glb-modal-title {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }
        .glb-modal-close {
            border: 1px solid #cbd5e1;
            background: #fff;
            border-radius: 10px;
            padding: 8px 12px;
            font-weight: 700;
        }
        .glb-preview-stage {
            width: 100%;
            border: 1px solid #dbe3ee;
            border-radius: 16px;
            background: #f8fafc;
            padding: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 480px;
        }
        .glb-preview-image {
            display: block;
            max-width: 100%;
            max-height: 72vh;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        .glb-preview-placeholder {
            min-height: 240px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .glb-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 14px;
        }
        .glb-btn-primary,
        .glb-btn-secondary {
            border-radius: 10px;
            padding: 10px 16px;
            font-weight: 600;
            border: 1px solid transparent;
        }
        .glb-btn-primary {
            background: #2563eb;
            color: #fff;
        }
        .glb-btn-secondary {
            background: #fff;
            color: #0f172a;
            border-color: #cbd5e1;
        }
        .dark .glb-modal {
            background: #111827;
        }
        .dark .glb-modal-title,
        .dark .glb-btn-secondary,
        .dark .glb-modal-close {
            color: #e5e7eb;
        }
        .dark .glb-modal-close,
        .dark .glb-btn-secondary {
            background: #1f2937;
            border-color: #374151;
        }
        .dark .glb-preview-stage {
            background: #0f172a;
            border-color: #374151;
        }
        .dark .glb-select-indicator {
            background: rgba(17,24,39,.95);
            border-color: #475569;
            color: #93c5fd;
        }
        .dark .glb-select-indicator.is-selected {
            background: #1e3a8a;
            border-color: #60a5fa;
            color: #eff6ff;
        }


        /* GLB4-F2 UI3 */
        .glb-model.is-selected {
            outline: 3px solid #2563eb;
            outline-offset: -3px;
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, .15);
        }
        .glb-model-card:has(.glb-model.is-selected) {
            border-radius: 12px;
        }
        .glb-preview-counter {
            display: inline-block;
            margin-top: 5px;
            padding: 4px 11px;
            border-radius: 9999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 13px;
            font-weight: 700;
        }
        .dark .glb-model.is-selected {
            outline-color: #60a5fa;
            border-color: #60a5fa;
        }
        .dark .glb-preview-counter {
            background: #1e3a8a;
            color: #dbeafe;
        }

    </style>

    <div class="glb-layout">
        <div class="space-y-5">
            <div class="glb-panel">
                <h2 class="glb-heading">
                    1. Seleccionar producto
                </h2>

                <label
                    for="glb-product-selector"
                    class="block text-sm font-semibold mb-2"
                >
                    Producto del catalogo Globos Personalizados
                </label>

                <select
                    id="glb-product-selector"
                    class="glb-input"
                    wire:change="selectProduct($event.target.value)"
                >
                    <option
                        value=""
                        disabled
                        @selected($productId === null)
                    >
                        Selecciona un producto...
                    </option>

                    @foreach ($this->products as $product)
                        <option
                            value="{{ $product->id }}"
                            @selected($productId === (int) $product->id)
                        >
                            {{ $product->name }}
                            @if ($product->has_variants)
                                — Elegir variante
                            @else
                                — ${{ number_format(
                                    (float) $product->sale_price *
                                    (1 + ((float) $product->sale_tax_rate / 100)),
                                    2
                                ) }}
                            @endif
                        </option>
                    @endforeach
                </select>

                @error('productId')
                    <p class="mt-2 text-sm text-red-600">
                        {{ $message }}
                    </p>
                @enderror

                @if ($this->products->isEmpty())
                    <p class="glb-muted mt-4">
                        No hay productos disponibles de Globos Personalizados.
                    </p>
                @endif
            </div>

            @if ($this->selectedProduct)
                <div class="glb-panel">
                    <h2 class="glb-heading">
                        2. Configurar producto
                    </h2>

                    <p class="mb-3 font-semibold">
                        {{ $this->selectedProduct->name }}
                    </p>

                    @if (count($this->balloonMatrix) > 0)
                        <div class="space-y-4">
                            <div>
                                <p class="glb-muted mb-2">
                                    Tamaño
                                </p>
                                <div class="glb-size-grid">
                                    @foreach (
                                        array_values(array_unique(
                                            array_column(
                                                $this->balloonMatrix,
                                                'size'
                                            )
                                        )) as $sizeIndex => $size
                                    )
                                        <button
                                            type="button"
                                            wire:click="selectBalloonSize({{ $sizeIndex }})"
                                            class="glb-choice {{ $selectedBalloonSize === $size ? 'active' : '' }}"
                                        >
                                            <strong>{{ $size }}</strong>
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            @if ($selectedBalloonSize !== null)
                                <div>
                                    <p class="glb-muted mb-2">
                                        Inflado
                                    </p>
                                    <div class="grid grid-cols-2 gap-2">
                                        @foreach ($this->balloonMatrix as $combination)
                                            @if ($combination['size'] === $selectedBalloonSize)
                                                <button
                                                    type="button"
                                                    wire:click="selectBalloonInflation({{ $combination['variant_id'] }})"
                                                    class="glb-choice {{ $variantId === (int) $combination['variant_id'] ? 'active' : '' }}"
                                                >
                                                    <strong>
                                                        {{ $combination['inflation'] }}
                                                    </strong>
                                                    <div class="glb-muted mt-1">
                                                        {{ $combination['reference'] }}
                                                    </div>
                                                </button>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @elseif ($this->variants->isNotEmpty())
                        <p class="glb-muted mb-2">
                            Selecciona una variante
                        </p>

                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($this->variants as $variant)
                                <button
                                    type="button"
                                    wire:click="selectVariant({{ $variant->id }})"
                                    class="glb-choice {{ $variantId === (int) $variant->id ? 'active' : '' }}"
                                >
                                    <div class="text-sm font-medium">
                                        {{ $variant->variant_group ?: 'Variante' }}:
                                        {{ $variant->variant_value ?: $variant->name }}
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @if ($this->requiresDesign)
                        <div class="glb-required-note mt-4">
                            Este producto requiere seleccionar un diseño.
                        </div>
                        <div class="mt-5">
                            <h3 class="glb-heading">
                                3. Elegir diseño
                            </h3>

                            <p class="glb-muted mb-3">
                                Biblioteca existente de Globos.
                                Compatibilidad todavía no configurada.
                            </p>

                            <input
                                type="search"
                                wire:model.live.debounce.300ms="modelSearch"
                                class="glb-input mb-3"
                                placeholder="Buscar diseño por nombre..."
                                aria-label="Buscar modelo"
                            />

                            <div class="glb-model-scroller">
                                <div class="glb-model-grid">
                                @foreach ($this->filteredModels as $model)
                                    <div class="glb-model-card">
                                        <button
                                            type="button"
                                            wire:click="openModelPreview({{ $model->id }})"
                                            class="glb-model {{ $modelId === $model->id ? 'is-selected' : '' }}"
                                        >
                                            @if ($model->image_path)
                                                <img
                                                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($model->image_path) }}"
                                                    alt="{{ $model->name }}"
                                                />
                                            @else
                                                <div class="glb-placeholder">
                                                    Sin imagen
                                                </div>
                                            @endif

                                            <div class="glb-model-name">
                                                {{ $model->name }}
                                            </div>
                                        </button>

                                        <button
                                            type="button"
                                            wire:click.stop="selectModel({{ $model->id }})"
                                            class="glb-select-indicator {{ $modelId === $model->id ? 'is-selected' : '' }}"
                                            title="{{ $modelId === $model->id ? 'Diseño seleccionado' : 'Seleccionar diseño' }}"
                                            aria-label="{{ $modelId === $model->id ? 'Diseño seleccionado' : 'Seleccionar diseño' }}"
                                        >
                                            @if ($modelId === $model->id)
                                                ✓
                                            @endif
                                        </button>
                                    </div>
                                @endforeach
                                </div>
                            </div>
                            @error('modelId')
                                <p class="mt-2 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                            @if ($this->filteredModels->isEmpty())
                                <p class="glb-muted mt-2">
                                    No se encontraron modelos con ese nombre.
                                </p>
                            @endif
                        </div>
                        @if ($this->previewModel)
                            <div
                                class="glb-modal-backdrop"
                                wire:key="glb-model-preview"
                                x-data="{}"
                                x-on:keydown.window="
                                    if ($event.key === 'ArrowLeft') {
                                        $event.preventDefault();
                                        $wire.previewPreviousModel();
                                    } else if ($event.key === 'ArrowRight') {
                                        $event.preventDefault();
                                        $wire.previewNextModel();
                                    } else if ($event.key === 'Escape') {
                                        $event.preventDefault();
                                        $wire.closeModelPreview();
                                    }
                                "
                            >
                                <div class="glb-modal">
                                    <div class="glb-modal-header">
                                        <div>
                                            <div class="glb-modal-title">
                                                {{ $this->previewModel->name }}
                                            </div>
                                            <div class="glb-preview-counter">
                                                {{ $this->previewPosition['position'] }}
                                                de
                                                {{ $this->previewPosition['total'] }}
                                            </div>
                                            <p class="glb-muted text-sm">
                                                Vista previa del diseño. Puedes navegar y seleccionar desde aquí.
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            wire:click="closeModelPreview"
                                            class="glb-modal-close"
                                        >
                                            ✕
                                        </button>
                                    </div>

                                    <button
                                        type="button"
                                        wire:click="confirmPreviewModel"
                                        class="glb-preview-stage"
                                        title="Seleccionar este diseño"
                                    >
                                        @if ($this->previewModel->image_path)
                                            <img
                                                src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($this->previewModel->image_path) }}"
                                                alt="{{ $this->previewModel->name }}"
                                                class="glb-preview-image"
                                            />
                                        @else
                                            <div class="glb-placeholder glb-preview-placeholder">
                                                Sin imagen
                                            </div>
                                        @endif
                                    </button>

                                    <div class="glb-modal-actions">
                                        <button
                                            type="button"
                                            wire:click="previewPreviousModel"
                                            class="glb-btn-secondary"
                                        >
                                            ← Anterior
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="confirmPreviewModel"
                                            class="glb-btn-primary"
                                        >
                                            {{ $modelId === $this->previewModel->id ? 'Diseño seleccionado' : 'Seleccionar este diseño' }}
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="previewNextModel"
                                            class="glb-btn-secondary"
                                        >
                                            Siguiente →
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif



                        <div class="mt-4">
                            <label class="block text-sm font-semibold mb-2">
                                Texto personalizado
                            </label>

                            <textarea
                                wire:model="personalizationText"
                                rows="3"
                                class="glb-input"
                                placeholder="Ej. Feliz cumpleaños, Ana"
                            ></textarea>
                        </div>
                    @endif

                    <div class="mt-5 flex items-end justify-between gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-2">
                                Cantidad
                            </label>
                            <input
                                type="number"
                                min="1"
                                max="99"
                                wire:model="quantity"
                                class="glb-input"
                                style="width:100px"
                            />
                        </div>

                        <div class="text-right">
                            <p class="glb-muted">Precio unitario con IVA</p>
                            <div class="text-2xl font-bold">
                                @if ($this->price)
                                    ${{ number_format($this->price['sale_price_with_tax'], 2) }}
                                @else
                                    Selecciona variante
                                @endif
                            </div>
                        </div>
                    </div>

                    @error('quantity')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="mt-4">
                        <button
                            type="button"
                            wire:click="addToCart"
                            class="glb-button"
                            @disabled(!$this->price || $savedOrderId !== null)
                        >
                            + Agregar al pedido
                        </button>
                    </div>

                    @if ($notice)
                        <p class="mt-3 text-sm text-green-600">
                            {{ $notice }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="glb-panel glb-cart">
            <h2 class="glb-heading">
                Resumen del pedido
            </h2>

            <p class="glb-muted mb-4">
                Configura los productos y registra los
                datos del cliente antes de guardar.
            </p>

            @forelse ($cart as $index => $line)
                <div class="py-3 border-b dark:border-gray-700">
                    <div class="flex justify-between gap-2">
                        <div class="text-sm font-semibold">
                            {{ $line['name'] }}
                        </div>
                        <button
                            type="button"
                            wire:click="removeFromCart({{ $index }})"
                            @disabled($savedOrderId !== null)
                            class="text-red-600 text-xs"
                        >
                            Quitar
                        </button>
                    </div>

                    @if ($line['variant'])
                        <p class="glb-muted">
                            {{ $line['variant'] }}
                        </p>
                    @endif

                    @if ($line['design'])
                        <p class="glb-muted">
                            Diseño: {{ $line['design'] }}
                        </p>
                    @endif

                    @if ($line['text'])
                        <p class="glb-muted">
                            {{ $line['text'] }}
                        </p>
                    @endif

                    <div class="flex justify-between mt-2 text-sm">
                        <span>
                            {{ $line['quantity'] }} ×
                            ${{ number_format($line['unit_price'], 2) }}
                        </span>
                        <strong>
                            ${{ number_format($line['subtotal'], 2) }}
                        </strong>
                    </div>
                </div>
            @empty
                <div class="py-10 text-center">
                    <div class="text-4xl mb-2">🛒</div>
                    <p class="glb-muted">
                        Agrega productos para comenzar.
                    </p>
                </div>
            @endforelse

            <div class="flex justify-between mt-5 items-center">
                <strong>Total estimado</strong>
                <span class="text-2xl font-bold">
                    ${{ number_format($this->total, 2) }}
                </span>
            </div>

            <div class="mt-5 border-t pt-5 dark:border-gray-700">
                <h3 class="font-bold text-base mb-3">
                    Datos del pedido
                </h3>

                @if ($savedOrderId)
                    <div class="rounded-lg border border-green-500 p-4 mb-4">
                        <p class="font-bold text-green-700 dark:text-green-400">
                            Pedido guardado correctamente
                        </p>
                        <p class="mt-1 font-semibold">
                            Folio: {{ $savedOrderNumber }}
                        </p>
                        <p class="glb-muted mt-2">
                            Pendiente de envío al PDV.
                            Los cobros se realizarán únicamente en caja.
                        </p>
                    </div>
                @endif

                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">
                        Cliente de Contactos *
                    </label>

                    <select
                        wire:model.live="customerContactId"
                        class="glb-input w-full"
                        @disabled($savedOrderId !== null)
                    >
                        <option value="">Selecciona un cliente</option>
                        @foreach ($this->balloonCustomers as $contact)
                            <option value="{{ $contact->id }}">
                                {{ $contact->name }}
                                @if ($contact->mobile ?: $contact->phone)
                                    — {{ $contact->mobile ?: $contact->phone }}
                                @else
                                    — Sin teléfono
                                @endif
                            </option>
                        @endforeach
                    </select>

                    <p class="glb-muted mt-1">
                        El cliente debe existir en Contactos
                        de Papelón y tener teléfono o celular válido.
                    </p>

                    @error('customerContactId')
                        <p class="mt-1 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror

                    @error('customer_contact_id')
                        <p class="mt-1 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>


                @if ($this->selectedCustomerMissingPhone && ! $savedOrderId)
                    <div class="mb-4 rounded-lg border border-amber-300 p-3">
                        <label class="block text-sm font-semibold mb-2">
                            Teléfono del cliente *
                        </label>

                        <p class="glb-muted mb-2">
                            Este contacto no tiene teléfono registrado.
                            Captúralo aquí y se actualizará en Contactos
                            cuando guardes el pedido.
                        </p>

                        <input
                            type="tel"
                            wire:model="newCustomerPhone"
                            class="glb-input w-full"
                            maxlength="30"
                            autocomplete="tel"
                            placeholder="Ej. 5512345678"
                        />

                        @error('newCustomerPhone')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                        @error('customer_phone')
                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                @endif

                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">
                        Fecha y hora solicitadas de entrega *
                    </label>

                    <input
                        type="datetime-local"
                        wire:model="requestedDeliveryAt"
                        class="glb-input w-full"
                        @disabled($savedOrderId !== null)
                    />

                    @error('requestedDeliveryAt')
                        <p class="mt-1 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror

                    @error('promised_at')
                        <p class="mt-1 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">
                        Observaciones del pedido
                    </label>

                    <textarea
                        wire:model="orderNotes"
                        class="glb-input w-full"
                        rows="3"
                        maxlength="2000"
                        placeholder="Instrucciones generales del pedido"
                        @disabled($savedOrderId !== null)
                    ></textarea>

                    @error('orderNotes')
                        <p class="mt-1 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                @error('cart')
                    <p class="mb-3 text-sm text-red-600">
                        {{ $message }}
                    </p>
                @enderror

                @error('order')
                    <p class="mb-3 text-sm text-red-600">
                        {{ $message }}
                    </p>
                @enderror

                @if (
                    ! $savedOrderId
                    && (
                        (
                            $editingOrderId !== null
                            && \App\Support\CustomBalloonsAccess::can(
                                'custom_balloons.update'
                            )
                        )
                        || (
                            $editingOrderId === null
                            && \App\Support\CustomBalloonsAccess::can(
                                'custom_balloons.create'
                            )
                        )
                    )
                )
                    <button
                        type="button"
                        wire:click="saveBalloonOrder"
                        wire:loading.attr="disabled"
                        wire:target="saveBalloonOrder"
                        class="glb-button mt-2"
                        @disabled(count($cart) === 0)
                    >
                        {{ $editingOrderId
                            ? 'Guardar cambios'
                            : 'Guardar pedido' }}
                    </button>
                @endif
            </div>

            <p class="glb-muted mt-3">
                No aplica todavía listas de precios,
                depósitos, descuentos ni reglas de inventario.
            </p>
        </div>
    </div>
</x-filament-panels::page>
