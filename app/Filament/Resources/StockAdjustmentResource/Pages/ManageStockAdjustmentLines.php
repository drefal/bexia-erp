<?php

namespace App\Filament\Resources\StockAdjustmentResource\Pages;

use App\Filament\Resources\StockAdjustmentResource;
use App\Models\StockAdjustment;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ManageStockAdjustmentLines extends Page
{
    protected static string $resource = StockAdjustmentResource::class;

    protected static string $view = 'filament.resources.stock-adjustment-resource.pages.manage-stock-adjustment-lines';

    public StockAdjustment $record;

    public array $countedInputs = [];

    public array $notesInputs = [];

    public string $quickProductSearch = '';

    public ?int $quickProductId = null;

    public ?string $quickProductLabel = null;

    public ?int $quickVariantId = null;

    public ?int $quickLotId = null;

    public $quickCountedQuantity = '0';

    public string $quickNotes = '';

    public function mount($record): void
    {
        // BEXIA_V5728B_AJUSTES_INVENTARIO_LINES_FINAL
        $recordId = $this->resolveStockAdjustmentRecordId($record);

        $this->record = StockAdjustment::query()->findOrFail($recordId);
        $this->syncLineInputs();
    }

    protected function resolveStockAdjustmentRecordId($record): int
    {
        if ($record instanceof StockAdjustment) {
            return (int) $record->getKey();
        }

        if ($record instanceof Model) {
            return (int) $record->getKey();
        }

        if (is_array($record)) {
            return (int) ($record['id'] ?? $record['record'] ?? 0);
        }

        if (is_object($record) && method_exists($record, 'getKey')) {
            return (int) $record->getKey();
        }

        if (is_object($record) && isset($record->id)) {
            return (int) $record->id;
        }

        if (is_string($record)) {
            $trimmed = trim($record);

            if ($trimmed !== '' && str_starts_with($trimmed, '{')) {
                $decoded = json_decode($trimmed, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return (int) ($decoded['id'] ?? $decoded['record'] ?? 0);
                }
            }

            if (is_numeric($trimmed)) {
                return (int) $trimmed;
            }
        }

        if (is_numeric($record)) {
            return (int) $record;
        }

        throw new \InvalidArgumentException('No se pudo resolver el ID del ajuste.');
    }

    public function getTitle(): string
    {
        return 'Líneas del ajuste #' . $this->record->getKey();
    }

    public function getSubheading(): ?string
    {
        return 'Captura rápida por tabla, sin Repeater y sin modal.';
    }

    public function editHeaderUrl(): string
    {
        return StockAdjustmentResource::getUrl('edit', ['record' => $this->record]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('editHeader')
                ->label('Editar encabezado')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->url(fn (): string => $this->editHeaderUrl()),

            Actions\Action::make('pdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn (): string => route('inventory.stock-adjustments.pdf', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('refreshLines')
                ->label('Recargar')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    $this->record->refresh();
                    $this->syncLineInputs();

                    Notification::make()
                        ->title('Líneas recargadas')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('recalculateVisibleLines')
                ->label('Recalcular actuales')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->visible(fn (): bool => (string) $this->record->status === 'draft')
                ->action(fn (): null => $this->recalculateVisibleLines()),

            Actions\Action::make('saveVisibleLines')
                ->label('Guardar cantidades')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => (string) $this->record->status === 'draft')
                ->action(fn (): null => $this->saveVisibleLines()),

            Actions\Action::make('confirmAdjustment')
                ->label('Confirmar ajuste')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmar ajuste de inventario')
                ->modalDescription('Al confirmar, se actualizarán las existencias y el ajuste quedará bloqueado.')
                ->modalSubmitActionLabel('Confirmar ajuste')
                ->visible(fn (): bool => (string) $this->record->status === 'draft')
                ->action(function (): void {
                    StockAdjustmentResource::confirmAdjustment($this->record);

                    Notification::make()
                        ->title('Ajuste confirmado')
                        ->body('Las existencias fueron actualizadas.')
                        ->success()
                        ->send();

                    $this->redirect(StockAdjustmentResource::getUrl('index'));
                }),
        ];
    }

    public function getLines(): Collection
    {
        return DB::table('stock_adjustment_lines as l')
            ->leftJoin('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('products as v', 'v.id', '=', 'l.product_variant_id')
            ->leftJoin('stock_lots as lot', 'lot.id', '=', 'l.lot_id')
            ->where('l.stock_adjustment_id', $this->record->getKey())
            ->orderBy('l.id')
            ->limit(500)
            ->get([
                'l.id',
                'l.product_id',
                'l.product_variant_id',
                'l.lot_id',
                'l.current_quantity',
                'l.counted_quantity',
                'l.difference_quantity',
                'l.unit_cost',
                'l.notes',
                'p.name as product_name',
                'p.sku as product_sku',
                'p.barcode as product_barcode',
                'p.internal_reference as product_internal_reference',
                'v.name as variant_name',
                'v.variant_name as variant_variant_name',
                'v.sku as variant_sku',
                'v.barcode as variant_barcode',
                'v.internal_reference as variant_internal_reference',
                'lot.lot_number as lot_number',
                'lot.expiration_date as lot_expiration_date',
            ]);
    }

    public function syncLineInputs(): void
    {
        $this->countedInputs = [];
        $this->notesInputs = [];

        foreach ($this->getLines() as $line) {
            $this->countedInputs[(int) $line->id] = $this->normalizeNumber($line->counted_quantity ?? 0);
            $this->notesInputs[(int) $line->id] = (string) ($line->notes ?? '');
        }
    }

    public function quickProductOptions(): array
    {
        $search = trim($this->quickProductSearch);

        if ($this->quickProductId || mb_strlen($search) < 2) {
            return [];
        }

        return $this->productSearchOptions($search, 12);
    }

    public function quickVariantOptions(): array
    {
        return $this->quickProductId ? $this->variantOptions((int) $this->quickProductId) : [];
    }

    public function quickLotOptions(): array
    {
        if (! $this->quickProductId) {
            return [];
        }

        return $this->lotOptions((int) $this->quickProductId, $this->quickVariantId);
    }

    public function quickProductRequiresLot(): bool
    {
        if (! $this->quickProductId) {
            return false;
        }

        return $this->productRequiresLot((int) $this->quickProductId, $this->quickVariantId);
    }

    public function quickProductRequiresSerial(): bool
    {
        if (! $this->quickProductId) {
            return false;
        }

        return $this->productRequiresSerial((int) $this->quickProductId, $this->quickVariantId);
    }

    public function selectQuickProduct(int $productId): void
    {
        $this->quickProductId = $productId;
        $this->quickProductLabel = $this->productOptionLabel($productId);
        $this->quickProductSearch = $this->quickProductLabel ?: '';
        $this->quickVariantId = null;
        $this->quickLotId = null;
    }

    public function updatedQuickVariantId(): void
    {
        $this->quickLotId = null;
    }

    public function clearQuickProduct(): void
    {
        $this->quickProductId = null;
        $this->quickProductLabel = null;
        $this->quickVariantId = null;
        $this->quickLotId = null;
        $this->quickProductSearch = '';
    }

    public function addQuickLineInline(): void
    {
        if ((string) $this->record->status !== 'draft') {
            Notification::make()
                ->title('No se puede agregar')
                ->body('El ajuste ya no está en borrador.')
                ->danger()
                ->send();

            return;
        }

        $productId = (int) ($this->quickProductId ?: 0);
        $variantId = $this->quickVariantId ? (int) $this->quickVariantId : null;
        $lotId = $this->quickLotId ? (int) $this->quickLotId : null;
        $counted = (float) ($this->quickCountedQuantity ?: 0);

        if ($productId <= 0) {
            Notification::make()
                ->title('Selecciona un producto')
                ->danger()
                ->send();

            return;
        }

        $variantOptions = $this->variantOptions($productId);

        if (count($variantOptions) > 0 && ! $variantId) {
            Notification::make()
                ->title('Selecciona una variante')
                ->body('Este producto tiene variantes.')
                ->danger()
                ->send();

            return;
        }

        if ($this->productRequiresSerial($productId, $variantId)) {
            Notification::make()
                ->title('Producto con serie')
                ->body('El ajuste por número de serie se hará en una pantalla especial para mantener trazabilidad individual.')
                ->danger()
                ->send();

            return;
        }

        if ($this->productRequiresLot($productId, $variantId) && ! $lotId) {
            Notification::make()
                ->title('Selecciona lote')
                ->body('Este producto maneja lote.')
                ->danger()
                ->send();

            return;
        }

        if ($counted < 0 && ! $this->locationAllowsNegativeStock()) {
            Notification::make()
                ->title('Cantidad inválida')
                ->body('La ubicación no permite existencia negativa.')
                ->danger()
                ->send();

            return;
        }

        $current = $this->currentQuantity($productId, $variantId, $lotId);
        $difference = round($counted - $current, 6);
        $unitCost = $this->averageCost($productId, $variantId, $lotId);

        $existing = DB::table('stock_adjustment_lines')
            ->where('stock_adjustment_id', $this->record->getKey())
            ->where('product_id', $productId)
            ->when($variantId, fn ($q) => $q->where('product_variant_id', $variantId), fn ($q) => $q->whereNull('product_variant_id'))
            ->when($lotId, fn ($q) => $q->where('lot_id', $lotId), fn ($q) => $q->whereNull('lot_id'))
            ->first();

        $payload = [
            'current_quantity' => $current,
            'counted_quantity' => $counted,
            'difference_quantity' => $difference,
            'unit_cost' => $unitCost,
            'notes' => trim($this->quickNotes) !== '' ? trim($this->quickNotes) : null,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('stock_adjustment_lines')
                ->where('id', $existing->id)
                ->where('stock_adjustment_id', $this->record->getKey())
                ->update($payload);

            $message = 'La línea existente fue actualizada.';
        } else {
            $payload = array_merge($payload, [
                'stock_adjustment_id' => $this->record->getKey(),
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'lot_id' => $lotId,
                'created_at' => now(),
            ]);

            DB::table('stock_adjustment_lines')->insert($payload);
            $message = 'El producto se agregó al ajuste.';
        }

        $this->touchRecord();

        $this->quickProductId = null;
        $this->quickProductLabel = null;
        $this->quickProductSearch = '';
        $this->quickVariantId = null;
        $this->quickLotId = null;
        $this->quickCountedQuantity = '0';
        $this->quickNotes = '';

        $this->syncLineInputs();

        Notification::make()
            ->title('Línea lista')
            ->body($message)
            ->success()
            ->send();
    }

    public function saveLine(int $lineId): void
    {
        if ((string) $this->record->status !== 'draft') {
            Notification::make()
                ->title('No se puede editar')
                ->body('El ajuste ya no está en borrador.')
                ->danger()
                ->send();

            return;
        }

        $line = DB::table('stock_adjustment_lines')
            ->where('stock_adjustment_id', $this->record->getKey())
            ->where('id', $lineId)
            ->first();

        if (! $line) {
            Notification::make()
                ->title('Línea no encontrada')
                ->danger()
                ->send();

            return;
        }

        $counted = (float) ($this->countedInputs[$lineId] ?? 0);

        if ($counted < 0 && ! $this->locationAllowsNegativeStock()) {
            Notification::make()
                ->title('Cantidad inválida')
                ->body('La ubicación no permite existencia negativa.')
                ->danger()
                ->send();

            return;
        }

        $current = (float) ($line->current_quantity ?? 0);
        $difference = round($counted - $current, 6);

        DB::table('stock_adjustment_lines')
            ->where('id', $lineId)
            ->where('stock_adjustment_id', $this->record->getKey())
            ->update([
                'counted_quantity' => $counted,
                'difference_quantity' => $difference,
                'notes' => $this->notesInputs[$lineId] ?? null,
                'updated_at' => now(),
            ]);

        $this->touchRecord();
        $this->syncLineInputs();

        Notification::make()
            ->title('Línea guardada')
            ->success()
            ->send();
    }

    public function saveVisibleLines(): null
    {
        if ((string) $this->record->status !== 'draft') {
            Notification::make()
                ->title('No se puede guardar')
                ->body('El ajuste ya no está en borrador.')
                ->danger()
                ->send();

            return null;
        }

        $lineIds = collect(array_keys($this->countedInputs))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        if (count($lineIds) === 0) {
            Notification::make()
                ->title('No hay líneas visibles para guardar')
                ->warning()
                ->send();

            return null;
        }

        $lines = DB::table('stock_adjustment_lines')
            ->where('stock_adjustment_id', $this->record->getKey())
            ->whereIn('id', $lineIds)
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($lines): void {
            foreach ($lines as $lineId => $line) {
                $lineId = (int) $lineId;
                $counted = (float) ($this->countedInputs[$lineId] ?? $line->counted_quantity ?? 0);

                if ($counted < 0 && ! $this->locationAllowsNegativeStock()) {
                    throw new Halt();
                }

                $current = (float) ($line->current_quantity ?? 0);
                $difference = round($counted - $current, 6);

                DB::table('stock_adjustment_lines')
                    ->where('id', $lineId)
                    ->where('stock_adjustment_id', $this->record->getKey())
                    ->update([
                        'counted_quantity' => $counted,
                        'difference_quantity' => $difference,
                        'notes' => $this->notesInputs[$lineId] ?? null,
                        'updated_at' => now(),
                    ]);
            }

            $this->touchRecord();
        });

        $this->syncLineInputs();

        Notification::make()
            ->title('Cantidades guardadas')
            ->body('Se actualizaron las líneas visibles.')
            ->success()
            ->send();

        return null;
    }

    public function recalculateVisibleLines(): null
    {
        if ((string) $this->record->status !== 'draft') {
            Notification::make()
                ->title('No se puede recalcular')
                ->body('El ajuste ya no está en borrador.')
                ->danger()
                ->send();

            return null;
        }

        $lines = DB::table('stock_adjustment_lines')
            ->where('stock_adjustment_id', $this->record->getKey())
            ->orderBy('id')
            ->limit(500)
            ->get();

        foreach ($lines as $line) {
            $current = $this->currentQuantity(
                (int) $line->product_id,
                $line->product_variant_id ? (int) $line->product_variant_id : null,
                $line->lot_id ? (int) $line->lot_id : null,
            );

            $counted = (float) ($line->counted_quantity ?? 0);
            $difference = round($counted - $current, 6);
            $unitCost = $this->averageCost(
                (int) $line->product_id,
                $line->product_variant_id ? (int) $line->product_variant_id : null,
                $line->lot_id ? (int) $line->lot_id : null,
            );

            DB::table('stock_adjustment_lines')
                ->where('id', $line->id)
                ->where('stock_adjustment_id', $this->record->getKey())
                ->update([
                    'current_quantity' => $current,
                    'difference_quantity' => $difference,
                    'unit_cost' => $unitCost,
                    'updated_at' => now(),
                ]);
        }

        $this->touchRecord();
        $this->syncLineInputs();

        Notification::make()
            ->title('Existencias recalculadas')
            ->body('Se actualizaron cantidades actuales y diferencias.')
            ->success()
            ->send();

        return null;
    }

    public function deleteLine(int $lineId): void
    {
        if ((string) $this->record->status !== 'draft') {
            Notification::make()
                ->title('No se puede quitar')
                ->body('El ajuste ya no está en borrador.')
                ->danger()
                ->send();

            return;
        }

        DB::table('stock_adjustment_lines')
            ->where('stock_adjustment_id', $this->record->getKey())
            ->where('id', $lineId)
            ->delete();

        $this->touchRecord();
        $this->syncLineInputs();

        Notification::make()
            ->title('Línea quitada')
            ->success()
            ->send();
    }

    protected function productSearchOptions(string $search, int $limit = 50): array
    {
        $companyId = (int) $this->record->company_id;
        $search = trim($search);

        $query = DB::table('products')
            ->select(['id', 'name', 'sku', 'barcode', 'internal_reference'])
            ->limit($limit);

        if (Schema::hasColumn('products', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if (Schema::hasColumn('products', 'is_variant')) {
            $query->where(function ($q): void {
                $q->whereNull('is_variant')->orWhere('is_variant', false);
            });
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where(function ($q): void {
                $q->whereNull('is_active')->orWhere('is_active', true);
            });
        }

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

            $query->where(function ($q) use ($like): void {
                $q->where('name', 'ilike', $like);

                foreach (['sku', 'barcode', 'internal_reference'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $q->orWhere($column, 'ilike', $like);
                    }
                }
            });
        }

        return $query
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($product) => [
                (int) $product->id => $this->productLabelFromObject($product),
            ])
            ->all();
    }

    protected function variantOptions(int $productId): array
    {
        if ($productId <= 0 || ! Schema::hasColumn('products', 'parent_product_id')) {
            return [];
        }

        $query = DB::table('products')
            ->select(['id', 'name', 'sku', 'barcode', 'internal_reference', 'variant_name'])
            ->where('parent_product_id', $productId)
            ->limit(200);

        if (Schema::hasColumn('products', 'company_id')) {
            $query->where('company_id', (int) $this->record->company_id);
        }

        if (Schema::hasColumn('products', 'is_variant')) {
            $query->where(function ($q): void {
                $q->whereNull('is_variant')->orWhere('is_variant', true);
            });
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where(function ($q): void {
                $q->whereNull('is_active')->orWhere('is_active', true);
            });
        }

        return $query
            ->orderBy('variant_name')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($variant) => [
                (int) $variant->id => $this->variantLabelFromObject($variant),
            ])
            ->all();
    }

    protected function lotOptions(int $productId, ?int $variantId = null): array
    {
        if ($productId <= 0 || ! Schema::hasTable('stock_lots')) {
            return [];
        }

        $query = DB::table('stock_lots as l')
            ->where('l.product_id', $productId);

        if (Schema::hasColumn('stock_lots', 'company_id')) {
            $query->where('l.company_id', (int) $this->record->company_id);
        }

        if ($variantId && Schema::hasColumn('stock_lots', 'product_variant_id')) {
            $query->where(function ($q) use ($variantId): void {
                $q->where('l.product_variant_id', $variantId)
                    ->orWhereNull('l.product_variant_id');
            });
        }

        if (Schema::hasColumn('stock_lots', 'is_active')) {
            $query->where(function ($q): void {
                $q->whereNull('l.is_active')->orWhere('l.is_active', true);
            });
        }

        if (Schema::hasTable('stock_quants') && Schema::hasColumn('stock_quants', 'lot_id')) {
            $query->leftJoin('stock_quants as q', function ($join) use ($productId, $variantId): void {
                $join->on('q.lot_id', '=', 'l.id')
                    ->where('q.company_id', '=', (int) $this->record->company_id)
                    ->where('q.warehouse_id', '=', (int) $this->record->warehouse_id)
                    ->where('q.location_id', '=', (int) $this->record->location_id)
                    ->where('q.product_id', '=', $productId);

                $variantId
                    ? $join->where('q.product_variant_id', '=', $variantId)
                    : $join->whereNull('q.product_variant_id');
            });

            $query->selectRaw('l.id, l.lot_number, l.expiration_date, COALESCE(SUM(q.quantity - COALESCE(q.reserved_quantity, 0)), 0) as available_quantity')
                ->groupBy('l.id', 'l.lot_number', 'l.expiration_date');
        } else {
            $query->select('l.id', 'l.lot_number', 'l.expiration_date');
        }

        return $query
            ->orderBy('l.lot_number')
            ->limit(100)
            ->get()
            ->mapWithKeys(function ($lot): array {
                $label = trim((string) ($lot->lot_number ?? ''));

                if ($label === '') {
                    $label = 'Lote #' . $lot->id;
                }

                if (! empty($lot->expiration_date)) {
                    $label .= ' · vence ' . $lot->expiration_date;
                }

                if (property_exists($lot, 'available_quantity')) {
                    $label .= ' · disp. ' . number_format((float) $lot->available_quantity, 2);
                }

                return [(int) $lot->id => $label];
            })
            ->all();
    }

    protected function productOptionLabel(int $productId): ?string
    {
        $product = DB::table('products')
            ->select(['id', 'name', 'sku', 'barcode', 'internal_reference'])
            ->where('id', $productId)
            ->first();

        return $product ? $this->productLabelFromObject($product) : null;
    }

    protected function productLabelFromObject(object $product): string
    {
        $bits = array_filter([
            $product->internal_reference ?? null,
            $product->sku ?? null,
            $product->barcode ?? null,
        ]);

        $prefix = $bits ? '[' . implode(' · ', $bits) . '] ' : '';

        return $prefix . (string) ($product->name ?? ('Producto #' . $product->id));
    }

    protected function variantLabelFromObject(object $variant): string
    {
        $name = trim((string) ($variant->variant_name ?? ''));

        if ($name === '') {
            $name = trim((string) ($variant->name ?? ''));
        }

        if ($name === '') {
            $name = 'Variante #' . $variant->id;
        }

        $bits = array_filter([
            $variant->internal_reference ?? null,
            $variant->sku ?? null,
            $variant->barcode ?? null,
        ]);

        return ($bits ? '[' . implode(' · ', $bits) . '] ' : '') . $name;
    }

    protected function productRequiresLot(int $productId, ?int $variantId = null): bool
    {
        return $this->productTrackingMatches($productId, $variantId, ['lot', 'lote']);
    }

    protected function productRequiresSerial(int $productId, ?int $variantId = null): bool
    {
        return $this->productTrackingMatches($productId, $variantId, ['serial', 'serie']);
    }

    protected function productTrackingMatches(int $productId, ?int $variantId, array $needles): bool
    {
        $ids = array_values(array_filter(array_unique([
            $variantId ?: 0,
            $productId ?: 0,
        ])));

        if (empty($ids) || ! Schema::hasTable('products')) {
            return false;
        }

        $rows = DB::table('products')
            ->whereIn('id', $ids)
            ->get();

        foreach ($rows as $row) {
            foreach (['tracking', 'advanced_tracking_mode', 'tracking_type', 'inventory_tracking', 'lot_serial_tracking'] as $column) {
                if (! Schema::hasColumn('products', $column)) {
                    continue;
                }

                $value = strtolower(trim((string) ($row->{$column} ?? '')));

                foreach ($needles as $needle) {
                    if ($value !== '' && str_contains($value, $needle)) {
                        return true;
                    }
                }
            }

            if (Schema::hasColumn('products', 'advanced_tracking_fields')) {
                $fields = $row->advanced_tracking_fields ?? null;

                if ($fields !== null && $fields !== '') {
                    $flat = strtolower(is_string($fields) ? $fields : json_encode($fields));

                    foreach ($needles as $needle) {
                        if (str_contains($flat, $needle)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    protected function currentQuantity(int $productId, ?int $variantId = null, ?int $lotId = null): float
    {
        if (! Schema::hasTable('stock_quants')) {
            return 0.0;
        }

        $query = DB::table('stock_quants')
            ->where('company_id', (int) $this->record->company_id)
            ->where('warehouse_id', (int) $this->record->warehouse_id)
            ->where('location_id', (int) $this->record->location_id)
            ->where('product_id', $productId);

        $variantId
            ? $query->where('product_variant_id', $variantId)
            : $query->whereNull('product_variant_id');

        if (Schema::hasColumn('stock_quants', 'lot_id')) {
            $lotId
                ? $query->where('lot_id', $lotId)
                : $query->whereNull('lot_id');
        }

        return (float) $query->sum('quantity');
    }

    protected function averageCost(int $productId, ?int $variantId = null, ?int $lotId = null): ?float
    {
        if (Schema::hasTable('stock_quants') && Schema::hasColumn('stock_quants', 'average_cost')) {
            $query = DB::table('stock_quants')
                ->where('company_id', (int) $this->record->company_id)
                ->where('warehouse_id', (int) $this->record->warehouse_id)
                ->where('location_id', (int) $this->record->location_id)
                ->where('product_id', $productId);

            $variantId
                ? $query->where('product_variant_id', $variantId)
                : $query->whereNull('product_variant_id');

            if (Schema::hasColumn('stock_quants', 'lot_id')) {
                $lotId
                    ? $query->where('lot_id', $lotId)
                    : $query->whereNull('lot_id');
            }

            $value = $query->value('average_cost');

            if ($value !== null) {
                return (float) $value;
            }
        }

        foreach (array_filter([$variantId, $productId]) as $id) {
            $product = DB::table('products')->where('id', $id)->first();

            if (! $product) {
                continue;
            }

            foreach (['standard_cost', 'purchase_price', 'last_purchase_cost'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $value = $product->{$column} ?? null;

                    if ($value !== null && (float) $value > 0) {
                        return (float) $value;
                    }
                }
            }
        }

        return null;
    }

    protected function locationAllowsNegativeStock(): bool
    {
        if (! Schema::hasTable('stock_locations') || ! Schema::hasColumn('stock_locations', 'allow_negative_stock')) {
            return false;
        }

        return (bool) DB::table('stock_locations')
            ->where('id', (int) $this->record->location_id)
            ->value('allow_negative_stock');
    }

    protected function touchRecord(): void
    {
        if (Schema::hasColumn('stock_adjustments', 'updated_at')) {
            DB::table('stock_adjustments')
                ->where('id', $this->record->getKey())
                ->update(['updated_at' => now()]);
        }

        $this->record->refresh();
    }

    public function normalizeNumber(mixed $value): string
    {
        $number = (float) ($value ?: 0);

        if (abs($number - round($number)) < 0.000001) {
            return (string) (int) round($number);
        }

        return rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');
    }

    public function money(mixed $value): string
    {
        return '$ ' . number_format((float) ($value ?: 0), 2);
    }

    public function quantity(mixed $value): string
    {
        return number_format((float) ($value ?: 0), 2);
    }
}
