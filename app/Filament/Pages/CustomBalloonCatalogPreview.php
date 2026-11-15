<?php

namespace App\Filament\Pages;

use App\Models\CustomBalloonModel;
use App\Support\CustomBalloons\CatalogProductResolver;
use App\Support\CustomBalloons\BalloonVariantMatrix;
use App\Support\CustomBalloonsAccess;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomBalloonCatalogPreview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-swatch';
    protected static ?string $navigationLabel = 'Nuevo pedido de Globos';
    protected static ?string $navigationGroup = 'Globos personalizados';
    protected static ?string $title = 'Nuevo pedido de Globos Personalizados';
    protected static ?string $slug = 'globos-nuevo-pedido';
    protected static string $view =
        'filament.pages.custom-balloon-catalog-preview';

    public string $search = '';
    public ?int $productId = null;
    public ?int $variantId = null;
    public ?string $selectedBalloonSize = null;
    public ?int $modelId = null;
    public string $modelSearch = '';
    public ?int $previewModelId = null;
    public bool $personalize = false;
    public string $personalizationText = '';
    public int $quantity = 1;
    public array $cart = [];
    public string $notice = '';

    public ?int $customerContactId = null;
    public string $newCustomerPhone = '';
    public string $requestedDeliveryAt = '';
    public string $orderNotes = '';
    public ?int $savedOrderId = null;
    public string $savedOrderNumber = '';

    public ?int $editingOrderId = null;
    public string $editingRevision = '';


    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return CustomBalloonsAccess::canViewCatalog();
    }


    public function mount(): void
    {
        $value = request()->query('edit');

        if ($value === null) {
            return;
        }

        if (
            ! is_scalar($value)
            || ! ctype_digit((string) $value)
            || (int) $value <= 0
        ) {
            abort(404);
        }

        abort_unless(
            CustomBalloonsAccess::can('custom_balloons.update'),
            403
        );

        $order = DB::table('custom_balloon_orders')
            ->where('company_id', 3)
            ->where('id', (int) $value)
            ->first();

        if (! $order) {
            abort(404);
        }

        \App\Support\CustomBalloons\BalloonOrderRules::assertEditable(
            (int) $order->id,
            3
        );

        if (
            ! $order->pos_order_id
            && (string) $order->status !== 'captured'
        ) {
            abort(403);
        }

        $this->editingOrderId = (int) $order->id;
        $this->editingRevision = (string) $order->updated_at;
        $this->customerContactId = (int) $order->customer_contact_id;

        $delivery = \Carbon\Carbon::parse(
            $order->promised_at,
            'UTC'
        )->timezone('America/Mexico_City');

        $this->requestedDeliveryAt =
            $delivery->format('Y-m-d')
            . 'T'
            . $delivery->format('H:i');

        $this->orderNotes = (string) ($order->notes ?? '');

        $lines = DB::table('custom_balloon_order_lines')
            ->where('company_id', 3)
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->get();

        $this->cart = [];

        foreach ($lines as $line) {
            $metadata = json_decode(
                (string) $line->metadata,
                true
            );

            if (! is_array($metadata)) {
                $metadata = [];
            }

            $this->cart[] = [
                'key' => count($this->cart) + 1,
                'product_id' => (int) ($metadata['product_id'] ?? 0),
                'variant_id' => (int) ($metadata['variant_id'] ?? 0),
                'model_id' => (int) ($line->model_id ?? 0),
                'name' => (string) ($metadata['product_name'] ?? 'Producto'),
                'variant' => trim(
                    (string) ($metadata['size'] ?? $line->size_name_snapshot ?? '')
                    . ' '
                    . (string) ($metadata['inflation'] ?? '')
                ),
                'design' => $line->model_id
                    ? (string) $line->model_name_snapshot
                    : null,
                'text' => (string) ($line->personalization_text ?? ''),
                'quantity' => (int) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'subtotal' => (float) $line->subtotal,
            ];
        }
    }

    public function updatedSearch(): void
    {
        $this->productId = null;
        $this->variantId = null;
        $this->selectedBalloonSize = null;
        $this->modelId = null;
        $this->previewModelId = null;
        $this->modelSearch = '';
        $this->personalizationText = '';
        $this->personalize = false;
        $this->notice = '';
    }

    public function getProductsProperty()
    {
        if (
            ! CustomBalloonsAccess::isPapelon()
        ) {
            return collect();
        }

        $term = '%' . mb_strtolower(trim($this->search)) . '%';

        return DB::table('products')
            ->where('company_id', 3)
            ->where('is_active', true)
            ->where('can_be_sold', true)
            ->where('is_variant', false)
            ->where('available_in_custom_balloons', true)
            ->whereIn('product_category_id', function ($query) {
                $query->select('id')
                    ->from('product_categories')
                    ->where('company_id', 3)
                    ->where('code', 'GLB-PERS')
                    ->where('is_active', true);
            })
            ->where(function ($query) use ($term) {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw(
                        "LOWER(COALESCE(internal_reference,'')) LIKE ?",
                        [$term]
                    )
                    ->orWhereRaw(
                        "LOWER(COALESCE(sku,'')) LIKE ?",
                        [$term]
                    );
            })
            ->select(
                'id',
                'name',
                'image_path',
                'sale_price',
                'sale_tax_rate',
                'product_type',
                'has_variants'
            )
            ->orderBy('name')
            ->limit(100)
            ->get();
    }

    public function getSelectedProductProperty()
    {
        if (! $this->productId || ! CustomBalloonsAccess::isPapelon()) {
            return null;
        }

        return DB::table('products')
            ->where('id', $this->productId)
            ->where('company_id', 3)
            ->where('is_variant', false)
            ->where('available_in_custom_balloons', true)
            ->whereIn('product_category_id', function ($query) {
                $query->select('id')
                    ->from('product_categories')
                    ->where('company_id', 3)
                    ->where('code', 'GLB-PERS')
                    ->where('is_active', true);
            })
            ->where('is_active', true)
            ->where('can_be_sold', true)
            ->first();
    }

    public function getBalloonMatrixProperty(): array
    {
        if (! $this->selectedProduct) {
            return [];
        }

        return BalloonVariantMatrix::forProduct(
            (int) $this->productId,
            3
        );
    }

    public function getVariantsProperty()
    {
        if (! $this->selectedProduct) {
            return collect();
        }

        return DB::table('products')
            ->where('company_id', 3)
            ->where('parent_product_id', $this->productId)
            ->where('is_variant', true)
            ->where('is_active', true)
            ->where('can_be_sold', true)
            ->select(
                'id',
                'name',
                'variant_group',
                'variant_value',
                'sale_price',
                'sale_tax_rate'
            )
            ->orderBy('variant_group')
            ->orderBy('variant_value')
            ->limit(80)
            ->get();
    }

    public function getRequiresDesignProperty(): bool
    {
        return (bool) (
            $this->selectedProduct?->requires_balloon_design
            ?? false
        );
    }

    public function getFilteredModelsProperty()
    {
        $search = mb_strtolower(trim($this->modelSearch));

        if ($search === '') {
            return $this->models;
        }

        return $this->models->filter(
            fn ($model): bool =>
                str_contains(
                    mb_strtolower((string) $model->name),
                    $search
                )
        );
    }

    public function getPreviewPositionProperty(): array
    {
        $ids = $this->filteredModelIds;
        $total = count($ids);

        $current = array_search(
            (int) ($this->previewModelId ?? 0),
            $ids,
            true
        );

        return [
            'position' => $current === false
                ? 0
                : $current + 1,
            'total' => $total,
        ];
    }

    public function getPreviewModelProperty()
    {
        if (! $this->previewModelId) {
            return null;
        }

        return $this->models->firstWhere(
            'id',
            $this->previewModelId
        );
    }

    public function getFilteredModelIdsProperty(): array
    {
        return $this->filteredModels
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function getModelsProperty()
    {
        if (! CustomBalloonsAccess::isPapelon()) {
            return collect();
        }

        return CustomBalloonModel::query()
            ->where('company_id', 3)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'image_path']);
    }

    public function getPriceProperty(): ?array
    {
        if (! $this->selectedProduct) {
            return null;
        }

        try {
            return CatalogProductResolver::resolve(
                (int) $this->productId,
                $this->variantId,
                3
            );
        } catch (ValidationException $e) {
            return null;
        }
    }

    public function selectProduct(int $id): void
    {
        if (! CustomBalloonsAccess::canViewCatalog()) {
            abort(403);
        }

        $exists = DB::table('products')
            ->where('id', $id)
            ->where('company_id', 3)
            ->where('is_variant', false)
            ->where('available_in_custom_balloons', true)
            ->whereIn('product_category_id', function ($query) {
                $query->select('id')
                    ->from('product_categories')
                    ->where('company_id', 3)
                    ->where('code', 'GLB-PERS')
                    ->where('is_active', true);
            })
            ->where('is_active', true)
            ->where('can_be_sold', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'productId' => 'Producto no disponible.',
            ]);
        }

        $this->productId = $id;
        $this->variantId = null;
        $this->selectedBalloonSize = null;
        $this->modelId = null;
        $this->previewModelId = null;
        $this->modelSearch = '';
        $this->personalizationText = '';
        $this->personalize = false;
        $this->quantity = 1;
        $this->notice = '';
    }

    public function selectBalloonSize(int $index): void
    {
        if (! CustomBalloonsAccess::canViewCatalog()) {
            abort(403);
        }

        $sizes = array_values(array_unique(
            array_column($this->balloonMatrix, 'size')
        ));

        if (! array_key_exists($index, $sizes)) {
            throw ValidationException::withMessages([
                'variantId' => 'Tamaño no disponible.',
            ]);
        }

        $this->selectedBalloonSize = $sizes[$index];
        $this->variantId = null;
        $this->notice = '';
    }

    public function selectBalloonInflation(int $variantId): void
    {
        if (! CustomBalloonsAccess::canViewCatalog()) {
            abort(403);
        }

        $selected = collect($this->balloonMatrix)->first(
            fn (array $item): bool =>
                (int) $item['variant_id'] === $variantId
                && $item['size'] === $this->selectedBalloonSize
        );

        if (! $selected) {
            throw ValidationException::withMessages([
                'variantId' => 'Combinación no disponible.',
            ]);
        }

        CatalogProductResolver::resolve(
            (int) $this->productId,
            $variantId,
            3
        );

        $this->variantId = $variantId;
        $this->notice = '';
    }

    public function selectVariant(int $id): void
    {
        if (! CustomBalloonsAccess::canViewCatalog()) {
            abort(403);
        }

        CatalogProductResolver::resolve(
            (int) $this->productId,
            $id,
            3
        );

        $this->variantId = $id;
        $this->notice = '';
    }

    public function openModelPreview(int $id): void
    {
        if (! CustomBalloonsAccess::canViewCatalog()) {
            abort(403);
        }

        if (! $this->requiresDesign) {
            throw ValidationException::withMessages([
                'modelId' => 'Este producto no requiere diseño.',
            ]);
        }

        $model = $this->filteredModels->firstWhere('id', $id)
            ?? $this->models->firstWhere('id', $id);

        if (! $model) {
            throw ValidationException::withMessages([
                'modelId' => 'El diseño seleccionado no existe.',
            ]);
        }

        $this->previewModelId = (int) $id;
    }

    public function closeModelPreview(): void
    {
        $this->previewModelId = null;
    }

    public function previewPreviousModel(): void
    {
        $ids = $this->filteredModelIds;

        if ($ids === []) {
            return;
        }

        $current = (int) ($this->previewModelId ?? $ids[0]);
        $position = array_search($current, $ids, true);

        if ($position === false) {
            $this->previewModelId = (int) $ids[0];
            return;
        }

        $previous = ($position - 1 + count($ids)) % count($ids);
        $this->previewModelId = (int) $ids[$previous];
    }

    public function previewNextModel(): void
    {
        $ids = $this->filteredModelIds;

        if ($ids === []) {
            return;
        }

        $current = (int) ($this->previewModelId ?? $ids[0]);
        $position = array_search($current, $ids, true);

        if ($position === false) {
            $this->previewModelId = (int) $ids[0];
            return;
        }

        $next = ($position + 1) % count($ids);
        $this->previewModelId = (int) $ids[$next];
    }

    public function confirmPreviewModel(): void
    {
        if (! $this->previewModelId) {
            throw ValidationException::withMessages([
                'modelId' => 'Selecciona un diseño antes de continuar.',
            ]);
        }

        $this->selectModel((int) $this->previewModelId);
    }

    public function selectModel(int $id): void
    {
        if (! CustomBalloonsAccess::canViewCatalog()) {
            abort(403);
        }

        if (! $this->requiresDesign) {
            throw ValidationException::withMessages([
                'modelId' => 'Este producto no requiere diseño.',
            ]);
        }

        $exists = CustomBalloonModel::query()
            ->where('company_id', 3)
            ->where('is_active', true)
            ->whereKey($id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'modelId' => 'Diseño no disponible.',
            ]);
        }

        $this->modelId = $id;
        $this->previewModelId = null;
    }

    public function addToCart(): void
    {
        if ($this->savedOrderId !== null) {
            throw ValidationException::withMessages([
                'cart' => 'El pedido ya fue guardado.',
            ]);
        }

        if (! CustomBalloonsAccess::canViewCatalog()) {
            abort(403);
        }

        $this->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'personalizationText' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $this->selectedProduct) {
            throw ValidationException::withMessages([
                'productId' => 'El producto no está habilitado para Globos Personalizados.',
            ]);
        }

        $matrix = $this->balloonMatrix;

        if ($matrix) {
            $valid = collect($matrix)->contains(
                fn (array $item): bool =>
                    (int) $item['variant_id']
                        === (int) $this->variantId
                    && $item['size']
                        === $this->selectedBalloonSize
            );

            if (! $valid) {
                throw ValidationException::withMessages([
                    'variantId' =>
                        'Selecciona Tamaño e Inflado válidos.',
                ]);
            }
        }

        if (
            $this->requiresDesign
            && ! $this->modelId
        ) {
            throw ValidationException::withMessages([
                'modelId' =>
                    'Selecciona un diseño antes de agregar el producto.',
            ]);
        }

        $product = CatalogProductResolver::resolve(
            (int) $this->productId,
            $this->variantId,
            3
        );

        $designName = null;

        if ($this->requiresDesign && $this->modelId) {
            $design = CustomBalloonModel::query()
                ->where('company_id', 3)
                ->where('is_active', true)
                ->find($this->modelId);

            if (! $design) {
                throw ValidationException::withMessages([
                    'modelId' => 'Diseño no disponible.',
                ]);
            }

            $designName = $design->name;
        }

        $this->cart[] = [
            'key' => count($this->cart) + 1,
            'product_id' => $product['product_id'],
            'variant_id' => $product['product_variant_id'],
            'name' => $product['product_name'],
            'variant' => $product['variant_name'],
            'design' => $designName,
            'model_id' => $this->requiresDesign
                ? $this->modelId
                : null,
            'text' => $this->requiresDesign
                ? trim($this->personalizationText)
                : '',
            'quantity' => $this->quantity,
            'unit_price' => $product['sale_price_with_tax'],
            'subtotal' => round(
                $product['sale_price_with_tax'] * $this->quantity,
                2
            ),
        ];

        $this->notice = 'Producto agregado al borrador visual.';
        $this->quantity = 1;
        $this->personalizationText = '';
    }

    public function removeFromCart(int $index): void
    {
        if ($this->savedOrderId !== null) {
            throw ValidationException::withMessages([
                'cart' => 'El pedido ya fue guardado.',
            ]);
        }

        if (! CustomBalloonsAccess::canViewCatalog()) {
            abort(403);
        }

        if (array_key_exists($index, $this->cart)) {
            unset($this->cart[$index]);
            $this->cart = array_values($this->cart);
        }
    }


    public function updatedCustomerContactId(): void
    {
        $this->newCustomerPhone = '';
    }

    public function getSelectedBalloonCustomerProperty()
    {
        if (
            ! CustomBalloonsAccess::isPapelon()
            || ! $this->customerContactId
        ) {
            return null;
        }

        return DB::table('contacts')
            ->where('id', (int) $this->customerContactId)
            ->where('company_id', 3)
            ->where('is_customer', true)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first(['id', 'name', 'phone', 'mobile']);
    }

    public function getSelectedCustomerMissingPhoneProperty(): bool
    {
        $customer = $this->selectedBalloonCustomer;

        if (! $customer) {
            return false;
        }

        return trim((string) ($customer->phone ?? '')) === ''
            && trim((string) ($customer->mobile ?? '')) === '';
    }

    public function getBalloonCustomersProperty()
    {
        if (! CustomBalloonsAccess::isPapelon()) {
            return collect();
        }

        return DB::table('contacts')
            ->where('company_id', 3)
            ->where('is_customer', true)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->select('id', 'name', 'phone', 'mobile')
            ->orderBy('name')
            ->limit(200)
            ->get();
    }

    public function saveBalloonOrder(): void
    {
        if ($this->editingOrderId !== null) {
            $this->saveExistingBalloonOrder();
            return;
        }

        if (! CustomBalloonsAccess::can('custom_balloons.create')) {
            abort(403);
        }

        if ($this->savedOrderId !== null) {
            throw ValidationException::withMessages([
                'order' => 'Este pedido ya se guardó. No lo envíes dos veces.',
            ]);
        }

        $this->validate([
            'customerContactId' => [
                'required', 'integer', 'min:1',
            ],
            'requestedDeliveryAt' => [
                'required', 'string',
            ],
            'orderNotes' => [
                'nullable', 'string', 'max:2000',
            ],
            'newCustomerPhone' => [
                'nullable', 'string', 'max:30',
            ],
        ]);

        if ($this->cart === []) {
            throw ValidationException::withMessages([
                'cart' => 'Agrega al menos un producto al pedido.',
            ]);
        }

        $items = array_map(
            static function (array $line): array {
                return [
                    'product_id' => $line['product_id'] ?? null,
                    'variant_id' => $line['variant_id'] ?? null,
                    'model_id' => $line['model_id'] ?? null,
                    'quantity' => $line['quantity'] ?? null,
                    'personalization_text' => $line['text'] ?? '',
                ];
            },
            $this->cart
        );

        $delivery = str_replace(
            'T',
            ' ',
            trim($this->requestedDeliveryAt)
        );

        $orderId = app(
            \App\Support\CustomBalloons\BalloonOrderDraftService::class
        )->createDraft(
            (int) $this->customerContactId,
            $delivery,
            $items,
            trim($this->orderNotes) ?: null,
            trim($this->newCustomerPhone) ?: null
        );

        $saved = DB::table('custom_balloon_orders')
            ->where('company_id', 3)
            ->where('id', $orderId)
            ->first(['id', 'number']);

        if (! $saved) {
            throw new \RuntimeException(
                'No se encontró el pedido recién guardado.'
            );
        }

        $this->savedOrderId = (int) $saved->id;
        $this->savedOrderNumber = (string) $saved->number;
        $this->notice = 'Pedido guardado: '
            . $this->savedOrderNumber;
    }


    private function saveExistingBalloonOrder(): void
    {
        abort_unless(
            CustomBalloonsAccess::can('custom_balloons.update'),
            403
        );

        $this->validate([
            'customerContactId' => [
                'required', 'integer', 'min:1',
            ],
            'requestedDeliveryAt' => ['required', 'string'],
            'orderNotes' => ['nullable', 'string', 'max:2000'],
            'newCustomerPhone' => ['nullable', 'string', 'max:30'],
            'cart' => ['required', 'array', 'min:1', 'max:100'],
        ]);

        $items = array_map(
            static fn (array $line): array => [
                'product_id' => (int) ($line['product_id'] ?? 0),
                'variant_id' => (int) ($line['variant_id'] ?? 0),
                'model_id' => (int) ($line['model_id'] ?? 0),
                'quantity' => (int) ($line['quantity'] ?? 0),
                'personalization_text' =>
                    (string) ($line['text'] ?? ''),
            ],
            $this->cart
        );

        $orderId = (int) $this->editingOrderId;

        app(
            \App\Support\CustomBalloons\BalloonOrderEditService::class
        )->update(
            $orderId,
            $this->editingRevision,
            (int) $this->customerContactId,
            str_replace('T', ' ', trim($this->requestedDeliveryAt)),
            $items,
            trim($this->orderNotes) ?: null,
            trim($this->newCustomerPhone) ?: null
        );

        $this->redirect(
            \App\Filament\Resources\CustomBalloonOrderResource::getUrl(
                'view',
                ['record' => $orderId]
            ),
            navigate: true
        );
    }

    public function getTotalProperty(): float
    {
        return round(
            array_sum(array_column($this->cart, 'subtotal')),
            2
        );
    }
}
