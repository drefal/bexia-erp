<?php

namespace App\Livewire;

use App\Models\StockMovement;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class StockTransferLinesInline extends Component
{
    public ?int $movementId = null;

    public ?int $companyId = null;

    public ?int $warehouseId = null;

    public ?int $sourceLocationId = null;

    public string $search = '';

    public array $lines = [];

    public function mount(
        ?int $movementId = null,
        ?int $companyId = null,
        ?int $warehouseId = null,
        ?int $sourceLocationId = null
    ): void {
        $this->movementId = $movementId;
        $this->companyId = $this->resolveCurrentCompanyId();
        $this->warehouseId = $warehouseId;
        $this->sourceLocationId = $sourceLocationId;

        $this->loadLines();
    }

    public function updatedWarehouseId(): void
    {
        $this->refreshStock();
    }

    public function updatedSourceLocationId(): void
    {
        $this->refreshStock();
    }

    public function setOrigin(?int $warehouseId, ?int $sourceLocationId): void
    {
        $this->warehouseId = $warehouseId;
        $this->sourceLocationId = $sourceLocationId;

        $this->refreshStock();
    }

    public function addProduct(int $productId, ?int $variantId = null): void
    {
        if (!$this->companyId || !$this->warehouseId || !$this->sourceLocationId) {
            $this->addError(
                'origin',
                'Selecciona primero almacén y ubicación de origen.'
            );

            return;
        }

        $product = DB::table('products')
            ->where('id', $productId)
            ->first();

        if (!$product) {
            return;
        }

        foreach ($this->lines as $line) {
            if (
                (int) ($line['product_id'] ?? 0) === $productId
                && (int) ($line['product_variant_id'] ?? 0) === (int) $variantId
            ) {
                return;
            }
        }

        $stock = $this->stockFor($productId, $variantId);

        $this->lines[] = [
            'id' => null,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'sku' => $this->productReference($product),
            'name' => (string) ($product->name ?? ('Producto '.$productId)),
            'quantity' => 1,
            'stock_quantity' => $stock['quantity'],
            'reserved_quantity' => $stock['reserved'],
            'available_quantity' => $stock['available'],
        ];
    }

    public function removeLine(int $index): void
    {
        if (!array_key_exists($index, $this->lines)) {
            return;
        }

        unset($this->lines[$index]);

        $this->lines = array_values($this->lines);
    }

    public function save(): void
    {
        if (!$this->movementId) {
            $this->addError(
                'movement',
                'Guarda primero el traslado como borrador.'
            );

            return;
        }

        $movement = StockMovement::query()
            ->whereKey($this->movementId)
            ->where('company_id', $this->companyId)
            ->firstOrFail();

        if ($movement->status !== 'draft') {
            $this->addError(
                'movement',
                'Solo se pueden modificar productos en un traslado en borrador.'
            );

            return;
        }

        $this->warehouseId = (int) $movement->warehouse_id;
        $this->sourceLocationId = (int) $movement->source_location_id;

        $this->refreshStock();

        foreach ($this->lines as $index => $line) {
            $quantity = (float) ($line['quantity'] ?? 0);
            $available = (float) ($line['available_quantity'] ?? 0);

            if ($quantity <= 0) {
                $this->addError(
                    "lines.$index.quantity",
                    'La cantidad debe ser mayor a cero.'
                );

                return;
            }

            if ($quantity > $available) {
                $this->addError(
                    "lines.$index.quantity",
                    'La cantidad excede la existencia disponible del origen.'
                );

                return;
            }
        }

        DB::transaction(function () use ($movement): void {
            DB::table('stock_movement_lines')
                ->where('stock_movement_id', $movement->id)
                ->delete();

            foreach ($this->lines as $line) {
                $quantity = (float) $line['quantity'];

                $payload = [
                    'stock_movement_id' => $movement->id,
                    'product_id' => (int) $line['product_id'],
                    'product_variant_id' => !empty($line['product_variant_id'])
                        ? (int) $line['product_variant_id']
                        : null,
                    'requested_quantity' => $quantity,
                    'done_quantity' => $quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $columns = Schema::getColumnListing('stock_movement_lines');

                $payload = array_intersect_key(
                    $payload,
                    array_flip($columns)
                );

                DB::table('stock_movement_lines')->insert($payload);
            }
        });

        $this->loadLines();

        session()->flash(
            'stock_transfer_lines_saved',
            'Productos del traslado guardados.'
        );
    }

    public function getProductsProperty()
    {
        if (
            ! $this->companyId
            || ! $this->warehouseId
            || ! $this->sourceLocationId
            || mb_strlen(trim($this->search)) < 2
        ) {
            return collect();
        }

        $search = trim($this->search);

        /*
         * IMPORTANTE:
         * En traslados NO partimos del catalogo completo.
         * Partimos de stock_quants del origen.
         *
         * Esto garantiza:
         * - empresa correcta
         * - almacen correcto
         * - ubicacion correcta
         * - producto con existencia real
         * - evita SKU duplicado de otra empresa
         */
        return DB::table('stock_quants as q')
            ->join('products as p', 'p.id', '=', 'q.product_id')
            ->where('q.company_id', $this->companyId)
            ->where('q.warehouse_id', $this->warehouseId)
            ->where('q.location_id', $this->sourceLocationId)
            ->where(function (Builder $query) use ($search): void {
                $query->where('p.name', 'ilike', '%'.$search.'%');

                foreach (
                    ['internal_reference', 'sku', 'barcode']
                    as $column
                ) {
                    if (Schema::hasColumn('products', $column)) {
                        $query->orWhere(
                            'p.'.$column,
                            'ilike',
                            '%'.$search.'%'
                        );
                    }
                }
            })
            ->whereRaw(
                '(q.quantity - COALESCE(q.reserved_quantity, 0)) > 0'
            )
            ->select([
                'p.id',
                'p.name',
                'p.internal_reference',
                'p.sku',
                'p.barcode',
            ])
            ->groupBy([
                'p.id',
                'p.name',
                'p.internal_reference',
                'p.sku',
                'p.barcode',
            ])
            ->orderBy('p.name')
            ->limit(20)
            ->get()
            ->map(function ($product): array {
                $stock = $this->stockFor(
                    (int) $product->id,
                    null
                );

                return [
                    'id' => (int) $product->id,
                    'sku' => $this->productReference($product),
                    'name' => (string) ($product->name ?? ''),
                    'quantity' => $stock['quantity'],
                    'reserved' => $stock['reserved'],
                    'available' => $stock['available'],
                ];
            })
            ->filter(
                fn (array $product): bool =>
                    (float) $product['available'] > 0
            )
            ->values();
    }

    public function render(): View
    {
        return view('livewire.stock-transfer-lines-inline');
    }

    protected function loadLines(): void
    {
        $this->lines = [];

        if (!$this->movementId) {
            return;
        }

        $companyId = $this->resolveCurrentCompanyId();

        if (! $companyId) {
            return;
        }

        $movement = StockMovement::query()
            ->whereKey($this->movementId)
            ->where('company_id', $companyId)
            ->first();

        if (! $movement) {
            return;
        }

        $this->companyId = $companyId;
        $this->warehouseId = (int) $movement->warehouse_id;
        $this->sourceLocationId = (int) $movement->source_location_id;

        $rows = DB::table('stock_movement_lines as sml')
            ->join('products as p', 'p.id', '=', 'sml.product_id')
            ->where('sml.stock_movement_id', $movement->id)
            ->orderBy('sml.id')
            ->select([
                'sml.*',
                'p.name as product_name',
            ])
            ->get();

        foreach ($rows as $row) {
            $variantId = property_exists($row, 'product_variant_id')
                ? $row->product_variant_id
                : null;

            $stock = $this->stockFor(
                (int) $row->product_id,
                $variantId ? (int) $variantId : null
            );

            $this->lines[] = [
                'id' => (int) $row->id,
                'product_id' => (int) $row->product_id,
                'product_variant_id' => $variantId
                    ? (int) $variantId
                    : null,
                'sku' => $this->productReferenceById((int) $row->product_id),
                'name' => (string) $row->product_name,
                'quantity' => (float) (
                    $row->requested_quantity
                    ?? $row->done_quantity
                    ?? 0
                ),
                'stock_quantity' => $stock['quantity'],
                'reserved_quantity' => $stock['reserved'],
                'available_quantity' => $stock['available'],
            ];
        }
    }

    protected function refreshStock(): void
    {
        foreach ($this->lines as $index => $line) {
            $stock = $this->stockFor(
                (int) $line['product_id'],
                !empty($line['product_variant_id'])
                    ? (int) $line['product_variant_id']
                    : null
            );

            $this->lines[$index]['stock_quantity'] = $stock['quantity'];
            $this->lines[$index]['reserved_quantity'] = $stock['reserved'];
            $this->lines[$index]['available_quantity'] = $stock['available'];
        }
    }

    protected function sourceLocationTracksStock(): bool
    {
        if (
            ! $this->sourceLocationId
            || ! $this->companyId
            || ! Schema::hasColumn('stock_locations', 'tracks_stock')
        ) {
            return false;
        }

        return (bool) DB::table('stock_locations')
            ->where('id', $this->sourceLocationId)
            ->where('company_id', $this->companyId)
            ->value('tracks_stock');
    }

    protected function stockFor(int $productId, ?int $variantId): array
    {
        if (!$this->companyId || !$this->warehouseId || !$this->sourceLocationId) {
            return [
                'quantity' => 0,
                'reserved' => 0,
                'available' => 0,
            ];
        }

        $query = DB::table('stock_quants')
            ->where('company_id', $this->companyId)
            ->where('warehouse_id', $this->warehouseId)
            ->where('location_id', $this->sourceLocationId)
            ->where('product_id', $productId);

        if (Schema::hasColumn('stock_quants', 'product_variant_id')) {
            if ($variantId) {
                $query->where('product_variant_id', $variantId);
            } else {
                $query->whereNull('product_variant_id');
            }
        }

        $quantityColumn = Schema::hasColumn('stock_quants', 'quantity')
            ? 'quantity'
            : null;

        $reservedColumn = Schema::hasColumn('stock_quants', 'reserved_quantity')
            ? 'reserved_quantity'
            : (
                Schema::hasColumn('stock_quants', 'reserved_qty')
                    ? 'reserved_qty'
                    : null
            );

        $quantity = $quantityColumn
            ? (float) (clone $query)->sum($quantityColumn)
            : 0.0;

        $reserved = $reservedColumn
            ? (float) (clone $query)->sum($reservedColumn)
            : 0.0;

        return [
            'quantity' => $quantity,
            'reserved' => $reserved,
            'available' => max(0, $quantity - $reserved),
        ];
    }

    protected function resolveCurrentCompanyId(): ?int
    {
        try {
            $tenant = Filament::getTenant();

            if ($tenant && isset($tenant->id)) {
                return (int) $tenant->id;
            }
        } catch (\Throwable) {
        }

        foreach ([
            'current_company_id',
            'active_company_id',
            'company_id',
            'tenant_company_id',
            'filament.tenant.id',
        ] as $key) {
            $value = session($key);

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        $user = auth()->user();

        foreach (['company_id', 'current_company_id'] as $field) {
            if ($user && isset($user->{$field}) && is_numeric($user->{$field})) {
                return (int) $user->{$field};
            }
        }

        return null;
    }

    protected function productReference(object $product): string
    {
        foreach (['internal_reference', 'sku', 'barcode'] as $field) {
            if (
                property_exists($product, $field)
                && filled($product->{$field})
            ) {
                return (string) $product->{$field};
            }
        }

        return '';
    }

    protected function productReferenceById(int $productId): string
    {
        $product = DB::table('products')
            ->where('id', $productId)
            ->first();

        return $product ? $this->productReference($product) : '';
    }
}
