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

    public function setOrigin(
        ?int $warehouseId,
        ?int $sourceLocationId
    ): void {
        $this->warehouseId = $warehouseId;
        $this->sourceLocationId = $sourceLocationId;

        $this->refreshStock();
    }

    public function addProduct(
        int $productId,
        ?int $variantId = null
    ): void {
        if (
            !$this->companyId
            || !$this->warehouseId
            || !$this->sourceLocationId
        ) {
            $this->addError(
                'origin',
                'Selecciona primero almacén y ubicación de origen.'
            );

            return;
        }

        $product = DB::table('products')
            ->where('id', $productId)
            ->where('company_id', $this->companyId)
            ->first();

        if (!$product) {
            return;
        }

        $trackingMode = $this->trackingModeForProduct(
            $productId,
            $variantId
        );

        /*
         * Producto normal:
         * una sola línea por producto/variante.
         *
         * Producto con serie/lote:
         * permite varias líneas, porque cada línea puede representar
         * una serie o lote distinto.
         */
        if ($trackingMode === 'none') {
            foreach ($this->lines as $line) {
                if (
                    (int) ($line['product_id'] ?? 0) === $productId
                    && (int) ($line['product_variant_id'] ?? 0)
                        === (int) $variantId
                    && ($line['tracking_mode'] ?? 'none') === 'none'
                ) {
                    return;
                }
            }
        } else {
            /*
             * Evita crear varias líneas vacías del mismo producto.
             * Una vez elegida la serie/lote se puede pulsar Agregar
             * nuevamente para otra unidad/lote.
             */
            foreach ($this->lines as $line) {
                if (
                    (int) ($line['product_id'] ?? 0) !== $productId
                    || (int) ($line['product_variant_id'] ?? 0)
                        !== (int) $variantId
                ) {
                    continue;
                }

                if (
                    $trackingMode === 'serial'
                    && empty($line['stock_serial_number_id'])
                ) {
                    return;
                }

                if (
                    $trackingMode === 'lot'
                    && empty($line['lot_id'])
                ) {
                    return;
                }
            }
        }

        $stock = $this->stockFor(
            $productId,
            $variantId,
            null
        );

        $this->lines[] = [
            'id' => null,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'sku' => $this->productReference($product),
            'name' => (string) (
                $product->name
                ?? ('Producto '.$productId)
            ),
            'tracking_mode' => $trackingMode,
            'lot_id' => null,
            'lot_number' => null,
            'stock_serial_number_id' => null,
            'serial_number' => null,
            'quantity' => 1,
            'stock_quantity' => $stock['quantity'],
            'reserved_quantity' => $stock['reserved'],
            'available_quantity' => $stock['available'],
        ];
    }

    public function updatedLines($value, $key): void
    {
        $parts = explode('.', (string) $key);

        if (count($parts) < 2) {
            return;
        }

        $index = (int) $parts[0];
        $field = (string) $parts[1];

        if (!isset($this->lines[$index])) {
            return;
        }

        if ($field === 'stock_serial_number_id') {
            $this->applySerialSelection($index);

            return;
        }

        if ($field === 'lot_id') {
            $this->applyLotSelection($index);

            return;
        }

        if (
            $field === 'quantity'
            && ($this->lines[$index]['tracking_mode'] ?? null)
                === 'serial'
        ) {
            $this->lines[$index]['quantity'] = 1;
        }
    }

    public function serialOptionsForLine(int $index): array
    {
        if (
            !isset($this->lines[$index])
            || !$this->companyId
            || !$this->warehouseId
            || !$this->sourceLocationId
        ) {
            return [];
        }

        $line = $this->lines[$index];
        $productId = (int) ($line['product_id'] ?? 0);

        if (!$productId) {
            return [];
        }

        $variantId = !empty($line['product_variant_id'])
            ? (int) $line['product_variant_id']
            : null;

        $currentSerialId = !empty(
            $line['stock_serial_number_id']
        )
            ? (int) $line['stock_serial_number_id']
            : null;

        $selectedElsewhere = [];

        foreach ($this->lines as $otherIndex => $otherLine) {
            if ($otherIndex === $index) {
                continue;
            }

            if (!empty($otherLine['stock_serial_number_id'])) {
                $selectedElsewhere[] = (int)
                    $otherLine['stock_serial_number_id'];
            }
        }

        $query = DB::table('stock_serial_numbers as s')
            ->leftJoin(
                'stock_lots as lot',
                'lot.id',
                '=',
                's.lot_id'
            )
            ->where('s.company_id', $this->companyId)
            ->where('s.product_id', $productId)
            ->where('s.current_warehouse_id', $this->warehouseId)
            ->where('s.current_location_id', $this->sourceLocationId)
            ->where(function ($q) use ($currentSerialId): void {
                $q->where('s.status', 'available');

                if ($currentSerialId) {
                    $q->orWhere('s.id', $currentSerialId);
                }
            });

        if (
            Schema::hasColumn(
                'stock_serial_numbers',
                'product_variant_id'
            )
        ) {
            if ($variantId) {
                $query->where(
                    's.product_variant_id',
                    $variantId
                );
            } else {
                $query->whereNull(
                    's.product_variant_id'
                );
            }
        }

        if ($selectedElsewhere !== []) {
            $query->whereNotIn(
                's.id',
                array_values(array_unique($selectedElsewhere))
            );
        }

        return $query
            ->orderBy('s.serial_number')
            ->get([
                's.id',
                's.serial_number',
                's.lot_id',
                'lot.lot_number',
            ])
            ->mapWithKeys(function ($serial): array {
                $label = (string) $serial->serial_number;

                if (!empty($serial->lot_number)) {
                    $label .= ' · Lote '
                        .$serial->lot_number;
                }

                return [
                    (int) $serial->id => $label,
                ];
            })
            ->all();
    }

    public function lotOptionsForLine(int $index): array
    {
        if (
            !isset($this->lines[$index])
            || !$this->companyId
            || !$this->warehouseId
            || !$this->sourceLocationId
        ) {
            return [];
        }

        $line = $this->lines[$index];
        $productId = (int) ($line['product_id'] ?? 0);

        if (!$productId) {
            return [];
        }

        $variantId = !empty($line['product_variant_id'])
            ? (int) $line['product_variant_id']
            : null;

        $currentLotId = !empty($line['lot_id'])
            ? (int) $line['lot_id']
            : null;

        $selectedElsewhere = [];

        foreach ($this->lines as $otherIndex => $otherLine) {
            if ($otherIndex === $index) {
                continue;
            }

            if (
                (int) ($otherLine['product_id'] ?? 0)
                    === $productId
                && (int) (
                    $otherLine['product_variant_id']
                    ?? 0
                ) === (int) $variantId
                && !empty($otherLine['lot_id'])
                && ($otherLine['tracking_mode'] ?? null)
                    === 'lot'
            ) {
                $selectedElsewhere[] = (int)
                    $otherLine['lot_id'];
            }
        }

        $query = DB::table('stock_quants as q')
            ->join(
                'stock_lots as lot',
                'lot.id',
                '=',
                'q.lot_id'
            )
            ->where('q.company_id', $this->companyId)
            ->where('q.warehouse_id', $this->warehouseId)
            ->where('q.location_id', $this->sourceLocationId)
            ->where('q.product_id', $productId)
            ->whereNotNull('q.lot_id')
            ->whereRaw(
                '(q.quantity - COALESCE(q.reserved_quantity, 0)) > 0'
            );

        if (
            Schema::hasColumn(
                'stock_quants',
                'product_variant_id'
            )
        ) {
            if ($variantId) {
                $query->where(
                    'q.product_variant_id',
                    $variantId
                );
            } else {
                $query->whereNull(
                    'q.product_variant_id'
                );
            }
        }

        if ($selectedElsewhere !== []) {
            $query->whereNotIn(
                'q.lot_id',
                array_values(array_unique($selectedElsewhere))
            );
        }

        return $query
            ->select([
                'q.lot_id',
                'lot.lot_number',
                DB::raw(
                    'SUM(q.quantity) as quantity'
                ),
                DB::raw(
                    'SUM(COALESCE(q.reserved_quantity, 0)) as reserved'
                ),
                DB::raw(
                    'SUM(q.quantity - COALESCE(q.reserved_quantity, 0)) as available'
                ),
            ])
            ->groupBy([
                'q.lot_id',
                'lot.lot_number',
            ])
            ->orderBy('lot.lot_number')
            ->get()
            ->mapWithKeys(function ($lot) use ($currentLotId): array {
                $label = (string) $lot->lot_number
                    .' · Disp. '
                    .number_format(
                        (float) $lot->available,
                        4
                    );

                return [
                    (int) $lot->lot_id => $label,
                ];
            })
            ->all();
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
        $this->resetErrorBag();

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

        $this->warehouseId = (int)
            $movement->warehouse_id;

        $this->sourceLocationId = (int)
            $movement->source_location_id;

        $this->refreshStock();

        $usedSerialIds = [];
        $usedLotKeys = [];

        foreach ($this->lines as $index => &$line) {
            $productId = (int)
                ($line['product_id'] ?? 0);

            $variantId = !empty(
                $line['product_variant_id']
            )
                ? (int) $line['product_variant_id']
                : null;

            $trackingMode = $this->trackingModeForProduct(
                $productId,
                $variantId
            );

            $line['tracking_mode'] = $trackingMode;

            if ($trackingMode === 'serial') {
                $serialId = !empty(
                    $line['stock_serial_number_id']
                )
                    ? (int) $line['stock_serial_number_id']
                    : 0;

                if (!$serialId) {
                    $this->addError(
                        "lines.$index.stock_serial_number_id",
                        'Selecciona el número de serie que se trasladará.'
                    );

                    return;
                }

                if (in_array($serialId, $usedSerialIds, true)) {
                    $this->addError(
                        "lines.$index.stock_serial_number_id",
                        'El número de serie ya está incluido en este traslado.'
                    );

                    return;
                }

                $serial = $this->validSerialForSource(
                    $serialId,
                    $productId,
                    $variantId
                );

                if (!$serial) {
                    $this->addError(
                        "lines.$index.stock_serial_number_id",
                        'La serie ya no está disponible en el almacén y ubicación de origen.'
                    );

                    return;
                }

                $usedSerialIds[] = $serialId;

                $line['stock_serial_number_id'] =
                    $serialId;

                $line['serial_number'] =
                    (string) $serial->serial_number;

                $line['lot_id'] = !empty($serial->lot_id)
                    ? (int) $serial->lot_id
                    : null;

                $line['lot_number'] = $line['lot_id']
                    ? $this->lotNumber(
                        (int) $line['lot_id']
                    )
                    : null;

                $line['quantity'] = 1;

                $stock = $this->stockFor(
                    $productId,
                    $variantId,
                    $line['lot_id']
                );

                if ((float) $stock['available'] < 1) {
                    $this->addError(
                        "lines.$index.stock_serial_number_id",
                        'La serie no tiene existencia disponible en el origen.'
                    );

                    return;
                }

                continue;
            }

            if ($trackingMode === 'lot') {
                $lotId = !empty($line['lot_id'])
                    ? (int) $line['lot_id']
                    : 0;

                if (!$lotId) {
                    $this->addError(
                        "lines.$index.lot_id",
                        'Selecciona el lote que se trasladará.'
                    );

                    return;
                }

                $lotKey = $productId
                    .'|'
                    .($variantId ?? 0)
                    .'|'
                    .$lotId;

                if (isset($usedLotKeys[$lotKey])) {
                    $this->addError(
                        "lines.$index.lot_id",
                        'El lote ya está incluido en otra línea de este traslado.'
                    );

                    return;
                }

                $usedLotKeys[$lotKey] = true;

                $stock = $this->stockFor(
                    $productId,
                    $variantId,
                    $lotId
                );

                $line['stock_quantity'] =
                    $stock['quantity'];

                $line['reserved_quantity'] =
                    $stock['reserved'];

                $line['available_quantity'] =
                    $stock['available'];

                $quantity = (float)
                    ($line['quantity'] ?? 0);

                if ($quantity <= 0) {
                    $this->addError(
                        "lines.$index.quantity",
                        'La cantidad debe ser mayor a cero.'
                    );

                    return;
                }

                if (
                    $quantity
                    > (float) $stock['available']
                ) {
                    $this->addError(
                        "lines.$index.quantity",
                        'La cantidad excede la existencia disponible de ese lote.'
                    );

                    return;
                }

                $line['lot_number'] =
                    $this->lotNumber($lotId);

                $line['stock_serial_number_id'] =
                    null;

                $line['serial_number'] = null;

                continue;
            }

            $quantity = (float)
                ($line['quantity'] ?? 0);

            $available = (float)
                ($line['available_quantity'] ?? 0);

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

            $line['lot_id'] = null;
            $line['lot_number'] = null;
            $line['stock_serial_number_id'] = null;
            $line['serial_number'] = null;
        }

        unset($line);

        DB::transaction(
            function () use ($movement): void {
                DB::table('stock_movement_lines')
                    ->where(
                        'stock_movement_id',
                        $movement->id
                    )
                    ->delete();

                $columns = Schema::getColumnListing(
                    'stock_movement_lines'
                );

                foreach ($this->lines as $line) {
                    $quantity = (
                        ($line['tracking_mode'] ?? 'none')
                            === 'serial'
                    )
                        ? 1.0
                        : (float) $line['quantity'];

                    $serialId = !empty(
                        $line['stock_serial_number_id']
                    )
                        ? (int) $line[
                            'stock_serial_number_id'
                        ]
                        : null;

                    $lotId = !empty($line['lot_id'])
                        ? (int) $line['lot_id']
                        : null;

                    $trackingMetadata = null;

                    if ($serialId) {
                        $trackingMetadata = json_encode(
                            [
                                'source' =>
                                    'manual_internal_transfer',
                                'serial_number' =>
                                    $line['serial_number']
                                    ?? null,
                                'lot_id' => $lotId,
                                'lot_number' =>
                                    $line['lot_number']
                                    ?? null,
                            ],
                            JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                        );
                    }

                    $payload = [
                        'stock_movement_id' =>
                            $movement->id,
                        'product_id' =>
                            (int) $line['product_id'],
                        'product_variant_id' =>
                            !empty(
                                $line['product_variant_id']
                            )
                                ? (int) $line[
                                    'product_variant_id'
                                ]
                                : null,
                        'lot_id' => $lotId,
                        'stock_serial_number_id' =>
                            $serialId,
                        'serial_tracking_metadata' =>
                            $trackingMetadata,
                        'requested_quantity' =>
                            $quantity,
                        'done_quantity' =>
                            $quantity,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $payload = array_intersect_key(
                        $payload,
                        array_flip($columns)
                    );

                    DB::table('stock_movement_lines')
                        ->insert($payload);
                }
            }
        );

        $this->loadLines();

        session()->flash(
            'stock_transfer_lines_saved',
            'Productos del traslado guardados.'
        );
    }

    public function getProductsProperty()
    {
        if (
            !$this->companyId
            || !$this->warehouseId
            || !$this->sourceLocationId
            || mb_strlen(trim($this->search)) < 2
        ) {
            return collect();
        }

        $search = trim($this->search);

        return DB::table('stock_quants as q')
            ->join(
                'products as p',
                'p.id',
                '=',
                'q.product_id'
            )
            ->where(
                'q.company_id',
                $this->companyId
            )
            ->where(
                'q.warehouse_id',
                $this->warehouseId
            )
            ->where(
                'q.location_id',
                $this->sourceLocationId
            )
            ->where(
                function (
                    Builder $query
                ) use ($search): void {
                    $query->where(
                        'p.name',
                        'ilike',
                        '%'.$search.'%'
                    );

                    foreach (
                        [
                            'internal_reference',
                            'sku',
                            'barcode',
                        ] as $column
                    ) {
                        if (
                            Schema::hasColumn(
                                'products',
                                $column
                            )
                        ) {
                            $query->orWhere(
                                'p.'.$column,
                                'ilike',
                                '%'.$search.'%'
                            );
                        }
                    }
                }
            )
            ->whereRaw(
                '(q.quantity - COALESCE(q.reserved_quantity, 0)) > 0'
            )
            ->select([
                'p.id',
                'p.name',
                'p.internal_reference',
                'p.sku',
                'p.barcode',
                'p.tracking',
            ])
            ->groupBy([
                'p.id',
                'p.name',
                'p.internal_reference',
                'p.sku',
                'p.barcode',
                'p.tracking',
            ])
            ->orderBy('p.name')
            ->limit(20)
            ->get()
            ->map(function ($product): array {
                $stock = $this->stockFor(
                    (int) $product->id,
                    null,
                    null
                );

                $trackingMode =
                    $this->trackingModeForProduct(
                        (int) $product->id,
                        null
                    );

                return [
                    'id' => (int) $product->id,
                    'sku' =>
                        $this->productReference(
                            $product
                        ),
                    'name' =>
                        (string) (
                            $product->name ?? ''
                        ),
                    'tracking_mode' =>
                        $trackingMode,
                    'quantity' =>
                        $stock['quantity'],
                    'reserved' =>
                        $stock['reserved'],
                    'available' =>
                        $stock['available'],
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
        return view(
            'livewire.stock-transfer-lines-inline'
        );
    }

    protected function loadLines(): void
    {
        $this->lines = [];

        if (!$this->movementId) {
            return;
        }

        $companyId =
            $this->resolveCurrentCompanyId();

        if (!$companyId) {
            return;
        }

        $movement = StockMovement::query()
            ->whereKey($this->movementId)
            ->where('company_id', $companyId)
            ->first();

        if (!$movement) {
            return;
        }

        $this->companyId = $companyId;
        $this->warehouseId =
            (int) $movement->warehouse_id;
        $this->sourceLocationId =
            (int) $movement->source_location_id;

        $rows = DB::table(
            'stock_movement_lines as sml'
        )
            ->join(
                'products as p',
                'p.id',
                '=',
                'sml.product_id'
            )
            ->leftJoin(
                'stock_lots as lot',
                'lot.id',
                '=',
                'sml.lot_id'
            )
            ->leftJoin(
                'stock_serial_numbers as sn',
                'sn.id',
                '=',
                'sml.stock_serial_number_id'
            )
            ->where(
                'sml.stock_movement_id',
                $movement->id
            )
            ->orderBy('sml.id')
            ->select([
                'sml.*',
                'p.name as product_name',
                'p.tracking as product_tracking',
                'lot.lot_number',
                'sn.serial_number',
            ])
            ->get();

        foreach ($rows as $row) {
            $variantId =
                property_exists(
                    $row,
                    'product_variant_id'
                )
                    ? $row->product_variant_id
                    : null;

            $serialId = !empty(
                $row->stock_serial_number_id
            )
                ? (int)
                    $row->stock_serial_number_id
                : null;

            $lotId = !empty($row->lot_id)
                ? (int) $row->lot_id
                : null;

            if ($serialId) {
                $trackingMode = 'serial';
            } elseif ($lotId) {
                $trackingMode = 'lot';
            } else {
                $trackingMode =
                    $this->trackingModeForProduct(
                        (int) $row->product_id,
                        $variantId
                            ? (int) $variantId
                            : null
                    );
            }

            $stock = $this->stockFor(
                (int) $row->product_id,
                $variantId
                    ? (int) $variantId
                    : null,
                $lotId
            );

            $this->lines[] = [
                'id' => (int) $row->id,
                'product_id' =>
                    (int) $row->product_id,
                'product_variant_id' =>
                    $variantId
                        ? (int) $variantId
                        : null,
                'sku' =>
                    $this->productReferenceById(
                        (int) $row->product_id
                    ),
                'name' =>
                    (string) $row->product_name,
                'tracking_mode' =>
                    $trackingMode,
                'lot_id' => $lotId,
                'lot_number' =>
                    $row->lot_number ?? null,
                'stock_serial_number_id' =>
                    $serialId,
                'serial_number' =>
                    $row->serial_number ?? null,
                'quantity' => (float) (
                    $row->requested_quantity
                    ?? $row->done_quantity
                    ?? 0
                ),
                'stock_quantity' =>
                    $stock['quantity'],
                'reserved_quantity' =>
                    $stock['reserved'],
                'available_quantity' =>
                    $stock['available'],
            ];
        }
    }

    protected function refreshStock(): void
    {
        foreach ($this->lines as $index => $line) {
            $productId =
                (int) $line['product_id'];

            $variantId = !empty(
                $line['product_variant_id']
            )
                ? (int)
                    $line['product_variant_id']
                : null;

            $trackingMode =
                $this->trackingModeForProduct(
                    $productId,
                    $variantId
                );

            $this->lines[$index][
                'tracking_mode'
            ] = $trackingMode;

            if (
                $trackingMode === 'serial'
                && !empty(
                    $line['stock_serial_number_id']
                )
            ) {
                $serial = $this->validSerialForSource(
                    (int) $line[
                        'stock_serial_number_id'
                    ],
                    $productId,
                    $variantId
                );

                if ($serial) {
                    $this->lines[$index][
                        'lot_id'
                    ] = !empty($serial->lot_id)
                        ? (int) $serial->lot_id
                        : null;

                    $this->lines[$index][
                        'serial_number'
                    ] = $serial->serial_number;

                    $this->lines[$index][
                        'quantity'
                    ] = 1;
                }
            }

            $lotId = !empty(
                $this->lines[$index]['lot_id']
            )
                ? (int)
                    $this->lines[$index]['lot_id']
                : null;

            $stock = $this->stockFor(
                $productId,
                $variantId,
                $lotId
            );

            $this->lines[$index][
                'stock_quantity'
            ] = $stock['quantity'];

            $this->lines[$index][
                'reserved_quantity'
            ] = $stock['reserved'];

            $this->lines[$index][
                'available_quantity'
            ] = $stock['available'];
        }
    }

    protected function applySerialSelection(
        int $index
    ): void {
        if (!isset($this->lines[$index])) {
            return;
        }

        $serialId = !empty(
            $this->lines[$index][
                'stock_serial_number_id'
            ]
        )
            ? (int) $this->lines[$index][
                'stock_serial_number_id'
            ]
            : null;

        if (!$serialId) {
            $this->lines[$index][
                'serial_number'
            ] = null;

            $this->lines[$index]['lot_id'] =
                null;

            $this->lines[$index][
                'lot_number'
            ] = null;

            return;
        }

        $productId =
            (int) $this->lines[$index][
                'product_id'
            ];

        $variantId = !empty(
            $this->lines[$index][
                'product_variant_id'
            ]
        )
            ? (int) $this->lines[$index][
                'product_variant_id'
            ]
            : null;

        $serial = $this->validSerialForSource(
            $serialId,
            $productId,
            $variantId
        );

        if (!$serial) {
            $this->lines[$index][
                'stock_serial_number_id'
            ] = null;

            $this->addError(
                "lines.$index.stock_serial_number_id",
                'La serie no está disponible en el origen.'
            );

            return;
        }

        $this->lines[$index][
            'serial_number'
        ] = (string) $serial->serial_number;

        $this->lines[$index]['lot_id'] =
            !empty($serial->lot_id)
                ? (int) $serial->lot_id
                : null;

        $this->lines[$index][
            'lot_number'
        ] = !empty($serial->lot_id)
            ? $this->lotNumber(
                (int) $serial->lot_id
            )
            : null;

        $this->lines[$index]['quantity'] = 1;

        $stock = $this->stockFor(
            $productId,
            $variantId,
            $this->lines[$index]['lot_id']
        );

        $this->lines[$index][
            'stock_quantity'
        ] = $stock['quantity'];

        $this->lines[$index][
            'reserved_quantity'
        ] = $stock['reserved'];

        $this->lines[$index][
            'available_quantity'
        ] = $stock['available'];
    }

    protected function applyLotSelection(
        int $index
    ): void {
        if (!isset($this->lines[$index])) {
            return;
        }

        $lotId = !empty(
            $this->lines[$index]['lot_id']
        )
            ? (int)
                $this->lines[$index]['lot_id']
            : null;

        $this->lines[$index]['lot_number'] =
            $lotId
                ? $this->lotNumber($lotId)
                : null;

        $productId =
            (int) $this->lines[$index][
                'product_id'
            ];

        $variantId = !empty(
            $this->lines[$index][
                'product_variant_id'
            ]
        )
            ? (int) $this->lines[$index][
                'product_variant_id'
            ]
            : null;

        $stock = $this->stockFor(
            $productId,
            $variantId,
            $lotId
        );

        $this->lines[$index][
            'stock_quantity'
        ] = $stock['quantity'];

        $this->lines[$index][
            'reserved_quantity'
        ] = $stock['reserved'];

        $this->lines[$index][
            'available_quantity'
        ] = $stock['available'];
    }

    protected function trackingModeForProduct(
        int $productId,
        ?int $variantId
    ): string {
        $tracking = (string) (
            DB::table('products')
                ->where('id', $productId)
                ->where(
                    'company_id',
                    $this->companyId
                )
                ->value('tracking')
            ?? 'none'
        );

        $tracking = strtolower(
            trim($tracking)
        );

        if ($tracking === 'serial') {
            return 'serial';
        }

        if ($tracking === 'lot') {
            return 'lot';
        }

        /*
         * Compatibilidad con inventario migrado:
         * existen productos con tracking=none pero quants
         * físicamente separados por lot_id.
         *
         * En ese caso el traslado debe conservar el lote,
         * aunque el catálogo todavía diga tracking=none.
         */
        if (
            $this->companyId
            && $this->warehouseId
            && $this->sourceLocationId
            && Schema::hasColumn(
                'stock_quants',
                'lot_id'
            )
        ) {
            $query = DB::table('stock_quants')
                ->where(
                    'company_id',
                    $this->companyId
                )
                ->where(
                    'warehouse_id',
                    $this->warehouseId
                )
                ->where(
                    'location_id',
                    $this->sourceLocationId
                )
                ->where(
                    'product_id',
                    $productId
                )
                ->whereNotNull('lot_id')
                ->whereRaw(
                    '(quantity - COALESCE(reserved_quantity,0)) > 0'
                );

            if (
                Schema::hasColumn(
                    'stock_quants',
                    'product_variant_id'
                )
            ) {
                if ($variantId) {
                    $query->where(
                        'product_variant_id',
                        $variantId
                    );
                } else {
                    $query->whereNull(
                        'product_variant_id'
                    );
                }
            }

            if ($query->exists()) {
                return 'lot';
            }
        }

        return 'none';
    }

    protected function validSerialForSource(
        int $serialId,
        int $productId,
        ?int $variantId
    ): ?object {
        if (
            !$this->companyId
            || !$this->warehouseId
            || !$this->sourceLocationId
        ) {
            return null;
        }

        $query = DB::table(
            'stock_serial_numbers'
        )
            ->where('id', $serialId)
            ->where(
                'company_id',
                $this->companyId
            )
            ->where('product_id', $productId)
            ->where(
                'current_warehouse_id',
                $this->warehouseId
            )
            ->where(
                'current_location_id',
                $this->sourceLocationId
            )
            ->where('status', 'available');

        if (
            Schema::hasColumn(
                'stock_serial_numbers',
                'product_variant_id'
            )
        ) {
            if ($variantId) {
                $query->where(
                    'product_variant_id',
                    $variantId
                );
            } else {
                $query->whereNull(
                    'product_variant_id'
                );
            }
        }

        return $query->first();
    }

    protected function lotNumber(
        int $lotId
    ): ?string {
        $number = DB::table('stock_lots')
            ->where('id', $lotId)
            ->where(
                'company_id',
                $this->companyId
            )
            ->value('lot_number');

        return filled($number)
            ? (string) $number
            : null;
    }

    protected function sourceLocationTracksStock(): bool
    {
        if (
            !$this->sourceLocationId
            || !$this->companyId
            || !Schema::hasColumn(
                'stock_locations',
                'tracks_stock'
            )
        ) {
            return false;
        }

        return (bool) DB::table(
            'stock_locations'
        )
            ->where(
                'id',
                $this->sourceLocationId
            )
            ->where(
                'company_id',
                $this->companyId
            )
            ->value('tracks_stock');
    }

    protected function stockFor(
        int $productId,
        ?int $variantId,
        ?int $lotId = null
    ): array {
        if (
            !$this->companyId
            || !$this->warehouseId
            || !$this->sourceLocationId
        ) {
            return [
                'quantity' => 0,
                'reserved' => 0,
                'available' => 0,
            ];
        }

        $query = DB::table('stock_quants')
            ->where(
                'company_id',
                $this->companyId
            )
            ->where(
                'warehouse_id',
                $this->warehouseId
            )
            ->where(
                'location_id',
                $this->sourceLocationId
            )
            ->where(
                'product_id',
                $productId
            );

        if (
            Schema::hasColumn(
                'stock_quants',
                'product_variant_id'
            )
        ) {
            if ($variantId) {
                $query->where(
                    'product_variant_id',
                    $variantId
                );
            } else {
                $query->whereNull(
                    'product_variant_id'
                );
            }
        }

        if (
            $lotId
            && Schema::hasColumn(
                'stock_quants',
                'lot_id'
            )
        ) {
            $query->where('lot_id', $lotId);
        }

        $quantityColumn =
            Schema::hasColumn(
                'stock_quants',
                'quantity'
            )
                ? 'quantity'
                : null;

        $reservedColumn =
            Schema::hasColumn(
                'stock_quants',
                'reserved_quantity'
            )
                ? 'reserved_quantity'
                : (
                    Schema::hasColumn(
                        'stock_quants',
                        'reserved_qty'
                    )
                        ? 'reserved_qty'
                        : null
                );

        $quantity = $quantityColumn
            ? (float) (
                clone $query
            )->sum($quantityColumn)
            : 0.0;

        $reserved = $reservedColumn
            ? (float) (
                clone $query
            )->sum($reservedColumn)
            : 0.0;

        return [
            'quantity' => $quantity,
            'reserved' => $reserved,
            'available' =>
                max(0, $quantity - $reserved),
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

        foreach (
            [
                'company_id',
                'current_company_id',
            ] as $field
        ) {
            if (
                $user
                && isset($user->{$field})
                && is_numeric($user->{$field})
            ) {
                return (int) $user->{$field};
            }
        }

        return null;
    }

    protected function productReference(
        object $product
    ): string {
        foreach (
            [
                'internal_reference',
                'sku',
                'barcode',
            ] as $field
        ) {
            if (
                property_exists(
                    $product,
                    $field
                )
                && filled($product->{$field})
            ) {
                return (string)
                    $product->{$field};
            }
        }

        return '';
    }

    protected function productReferenceById(
        int $productId
    ): string {
        $product = DB::table('products')
            ->where('id', $productId)
            ->first();

        return $product
            ? $this->productReference($product)
            : '';
    }
}
