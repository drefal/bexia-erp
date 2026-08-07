<?php

namespace App\Livewire;

use App\Models\PurchaseOrder;
use App\Support\PurchaseOrderHistory;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class PurchaseOrderLinesInline extends Component
{
    public int $purchaseOrderId;

    public array $order = [];
    public array $lines = [];

    public bool $isDraft = false;

    public string $productSearch = '';
    public string $lineSearch = '';
    public array $productResults = [];

    public ?int $selectedProductId = null;
    public string $selectedProductLabel = '';

    public ?int $selectedVariantId = null;
    public array $variantOptions = [];

    public string $selectedUnitKey = 'base';
    public array $unitOptions = [];

    public array $taxOptions = [];

    public string $newQuantity = '1';
    public string $newPurchaseUnitFactor = '1';
    public string $newUnitCostWithoutTax = '0';
    public string $newTaxRate = '16.0000';

    public ?float $newSuggestedUxe = null;
    public string $newSuggestedUxeText = '';

    public ?int $editingLineId = null;
    public string $editingProductLabel = '';
    public string $editingVariantLabel = '';
    public string $editingUnitLabel = '';
    public string $editQuantity = '1';
    public string $editPurchaseUnitFactor = '1';
    public string $editUnitCostWithoutTax = '0';
    public string $editTaxRate = '16.0000';
    public string $editNotes = '';

    public ?float $editSuggestedUxe = null;
    public string $editSuggestedUxeText = '';

    public ?int $deleteLineId = null;
    public string $deleteLineLabel = '';

    public float $subtotal = 0;
    public float $taxTotal = 0;
    public float $total = 0;

    public function mount(int $purchaseOrderId): void
    {
        $this->purchaseOrderId = $purchaseOrderId;
        $this->loadData();
    }

    public function updatedProductSearch(): void
    {
        if ($this->editingLineId) {
            return;
        }

        $this->searchProducts();
    }

    public function updatedSelectedVariantId(): void
    {
        $this->refreshAddCostAndTax();
    }

    public function updatedSelectedUnitKey(): void
    {
        $this->refreshAddCostAndTax();
    }

    public function loadData(): void
    {
        $order = DB::table('purchase_orders')
            ->where('id', $this->purchaseOrderId)
            ->first();

        if (! $order) {
            $this->order = [];
            $this->lines = [];
            $this->isDraft = false;
            return;
        }

        $this->order = (array) $order;
        $this->taxOptions = $this->getTaxOptions();
        $this->isDraft = in_array((string) ($order->status ?? ''), ['draft', 'borrador'], true);

        $this->lines = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $this->purchaseOrderId)
            ->orderBy('id')
            ->get()
            ->map(fn ($line): array => [
                'id' => (int) $line->id,
                'product_label' => (string) ($line->product_label ?? ''),
                'variant_label' => (string) ($line->variant_label ?? ''),
                'purchase_unit_label' => (string) ($line->purchase_unit_label ?? ''),
                'purchase_unit_factor' => (string) ($line->purchase_unit_factor ?? '1'),
                'ordered_quantity' => (string) ($line->ordered_quantity ?? '0'),
                'base_quantity' => (string) ($line->base_quantity ?? '0'),
                'unit_cost_without_tax' => (string) ($line->unit_cost_without_tax ?? '0'),
                'tax_rate' => $this->normalizeTaxRate($line->tax_rate ?? 0),
                'unit_cost_with_tax' => (string) $this->resolvedUnitCostWithTax($line),
                'line_total_without_tax' => (string) ($line->line_total_without_tax ?? '0'),
                'line_tax' => (string) ($line->line_tax ?? '0'),
                'line_total_with_tax' => (string) $this->resolvedLineTotalWithTax($line),
                'notes' => (string) ($line->notes ?? ''),
            ])
            ->all();

        $this->refreshTotals();
    }

    public function searchProducts(): void
    {
        $search = trim($this->productSearch);

        if (mb_strlen($search) < 2 || ! Schema::hasTable('products')) {
            $this->productResults = [];
            return;
        }

        $columns = Schema::getColumnListing('products');

        $query = DB::table('products')->limit(20);

        if (in_array('company_id', $columns, true) && ! empty($this->order['company_id'])) {
            $query->where(function ($q): void {
                $q->where('company_id', $this->order['company_id'])->orWhereNull('company_id');
            });
        }

        if (in_array('is_active', $columns, true)) {
            $query->where('is_active', true);
        }

        if (in_array('can_be_purchased', $columns, true)) {
            $query->where('can_be_purchased', true);
        }

        if (in_array('is_variant', $columns, true)) {
            $query->where(function ($q): void {
                $q->where('is_variant', false)->orWhereNull('is_variant');
            });
        }

        if (in_array('parent_product_id', $columns, true)) {
            $query->whereNull('parent_product_id');
        }

        $searchable = array_values(array_intersect($columns, [
            'name',
            'nombre',
            'code',
            'codigo',
            'sku',
            'barcode',
            'bar_code',
            'internal_reference',
            'reference',
        ]));

        if ($searchable) {
            $query->where(function ($q) use ($searchable, $search): void {
                foreach ($searchable as $column) {
                    $q->orWhereRaw('CAST(' . $column . ' AS TEXT) ILIKE ?', ['%' . $search . '%']);
                }
            });
        }

        $orderColumn = in_array('internal_reference', $columns, true)
            ? 'internal_reference'
            : (in_array('sku', $columns, true) ? 'sku' : 'id');

        $this->productResults = $query
            ->orderBy($orderColumn)
            ->get()
            ->map(fn ($product): array => [
                'id' => (int) $product->id,
                'label' => $this->productLabel($product),
            ])
            ->all();
    }

    public function selectProduct(int $productId): void
    {
        $product = $this->findProduct($productId);

        if (! $product) {
            return;
        }

        $this->selectedProductId = $productId;
        $this->selectedProductLabel = $this->productLabel($product);
        $this->productSearch = $this->selectedProductLabel;
        $this->productResults = [];

        $this->selectedVariantId = null;
        $this->variantOptions = $this->getVariantOptions($productId);

        $this->unitOptions = $this->getUnitOptions($productId);
        $this->selectedUnitKey = array_key_first($this->unitOptions) ?: 'base';

        $this->newTaxRate = $this->resolveDefaultTaxRate($product);
        $this->refreshAddCostAndTax();
    }

    protected function refreshAddCostAndTax(): void
    {
        $product = $this->selectedProductId ? $this->findProduct((int) $this->selectedProductId) : null;
        $variant = $this->selectedVariantId ? $this->findVariant((int) $this->selectedVariantId) : null;

        $source = $variant ?: $product;

        if (! $source) {
            return;
        }

        $factor = max(
            (float) ($this->unitOptions[$this->selectedUnitKey]['factor'] ?? 1),
            1
        );

        $baseCost = $variant ? $this->productCost($variant) : $this->productCost($product);

        $this->newPurchaseUnitFactor = number_format($factor, 6, '.', '');
        $this->newUnitCostWithoutTax = number_format($baseCost * $factor, 4, '.', '');
        $this->newTaxRate = $this->resolveDefaultTaxRate($source);

        $this->refreshNewUxeSuggestion();
    }

    public function addProduct(): void
    {
        if (! $this->isDraft) {
            $this->notifyLocked();
            return;
        }

        if ($this->editingLineId) {
            $this->saveEditLine();
            return;
        }

        if (! $this->selectedProductId) {
            Notification::make()
                ->title('Selecciona un producto')
                ->warning()
                ->send();

            return;
        }

        $product = $this->findProduct($this->selectedProductId);

        if (! $product) {
            Notification::make()
                ->title('Producto no encontrado')
                ->danger()
                ->send();

            return;
        }

        $variant = $this->selectedVariantId ? $this->findVariant($this->selectedVariantId) : null;
        $unit = $this->unitOptions[$this->selectedUnitKey] ?? $this->baseUnitOption();

        $qty = max($this->toFloat($this->newQuantity), 0);
        $factor = max($this->toFloat($this->newPurchaseUnitFactor), 1);
        $baseQty = round($qty * $factor, 6);

        $unitCost = max($this->toFloat($this->newUnitCostWithoutTax), 0);
        $taxRate = max($this->toFloat($this->normalizeTaxRate($this->newTaxRate)), 0);

        $lineSubtotal = round($qty * $unitCost, 6);
        $lineTax = round($lineSubtotal * ($taxRate / 100), 6);
        $lineTotal = round($lineSubtotal + $lineTax, 6);
        $unitWithTax = round($unitCost * (1 + ($taxRate / 100)), 6);

        DB::table('purchase_order_lines')->insert([
            'purchase_order_id' => $this->purchaseOrderId,
            'company_id' => $this->order['company_id'] ?? null,
            'product_id' => $this->selectedProductId,
            'product_variant_id' => $this->selectedVariantId,
            'product_label' => $this->productLabel($product),
            'variant_label' => $variant ? $this->variantLabel($variant) : '—',
            'purchase_unit_type' => $unit['type'] ?? 'base',
            'purchase_unit_label' => $unit['label'] ?? 'Pieza',
            'purchase_unit_factor' => $factor,
            'sat_unit_key' => $unit['sat_unit_key'] ?? null,
            'sat_unit_name' => $unit['sat_unit_name'] ?? null,
            'ordered_quantity' => $qty,
            'base_quantity' => $baseQty,
            'received_quantity' => 0,
            'received_base_quantity' => 0,
            'unit_cost_without_tax' => $unitCost,
            'tax_rate' => $taxRate,
            'unit_cost_with_tax' => $unitWithTax,
            'line_total_without_tax' => $lineSubtotal,
            'line_tax' => $lineTax,
            'line_total_with_tax' => $lineTotal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->afterLinesChanged('Producto agregado a la orden.');

        $this->resetAddFields();

        Notification::make()
            ->title('Producto agregado')
            ->success()
            ->send();
    }

    public function editLine(int $lineId): void
    {
        if (! $this->isDraft) {
            $this->notifyLocked();
            return;
        }

        $line = DB::table('purchase_order_lines')
            ->where('id', $lineId)
            ->where('purchase_order_id', $this->purchaseOrderId)
            ->first();

        if (! $line) {
            Notification::make()
                ->title('Producto no encontrado')
                ->danger()
                ->send();

            return;
        }

        $this->editingLineId = (int) $line->id;
        $this->editingProductLabel = (string) ($line->product_label ?? '');
        $this->editingVariantLabel = (string) ($line->variant_label ?? '—');
        $this->editingUnitLabel = (string) ($line->purchase_unit_label ?? 'Pieza');
        $this->editQuantity = (string) ($line->ordered_quantity ?? '0');
        $this->editPurchaseUnitFactor = (string) ($line->purchase_unit_factor ?? '1');
        $this->editUnitCostWithoutTax = (string) ($line->unit_cost_without_tax ?? '0');
        $this->editTaxRate = $this->normalizeTaxRate($line->tax_rate ?? 0);
        $this->editNotes = (string) ($line->notes ?? '');

        $this->refreshEditUxeSuggestion($line);

        $this->productSearch = $this->editingProductLabel;
        $this->productResults = [];
    }

    public function cancelEditLine(): void
    {
        $this->editingLineId = null;
        $this->editingProductLabel = '';
        $this->editingVariantLabel = '';
        $this->editingUnitLabel = '';
        $this->editQuantity = '1';
        $this->editPurchaseUnitFactor = '1';
        $this->editUnitCostWithoutTax = '0';
        $this->editTaxRate = '16.0000';
        $this->editNotes = '';
        $this->editSuggestedUxe = null;
        $this->editSuggestedUxeText = '';

        $this->resetAddFields();
    }

    public function saveEditLine(): void
    {
        if (! $this->isDraft) {
            $this->notifyLocked();
            return;
        }

        if (! $this->editingLineId) {
            return;
        }

        $line = DB::table('purchase_order_lines')
            ->where('id', $this->editingLineId)
            ->where('purchase_order_id', $this->purchaseOrderId)
            ->first();

        if (! $line) {
            Notification::make()
                ->title('Producto no encontrado')
                ->danger()
                ->send();

            $this->cancelEditLine();
            return;
        }

        $qty = max($this->toFloat($this->editQuantity), 0);
        $factor = max($this->toFloat($this->editPurchaseUnitFactor), 1);
        $baseQty = round($qty * $factor, 6);

        $unitCost = max($this->toFloat($this->editUnitCostWithoutTax), 0);
        $taxRate = max($this->toFloat($this->normalizeTaxRate($this->editTaxRate)), 0);

        $lineSubtotal = round($qty * $unitCost, 6);
        $lineTax = round($lineSubtotal * ($taxRate / 100), 6);
        $lineTotal = round($lineSubtotal + $lineTax, 6);
        $unitWithTax = round($unitCost * (1 + ($taxRate / 100)), 6);

        DB::table('purchase_order_lines')
            ->where('id', $this->editingLineId)
            ->where('purchase_order_id', $this->purchaseOrderId)
            ->update([
                'purchase_unit_factor' => $factor,
                'ordered_quantity' => $qty,
                'base_quantity' => $baseQty,
                'unit_cost_without_tax' => $unitCost,
                'tax_rate' => $taxRate,
                'unit_cost_with_tax' => $unitWithTax,
                'line_total_without_tax' => $lineSubtotal,
                'line_tax' => $lineTax,
                'line_total_with_tax' => $lineTotal,
                'notes' => trim($this->editNotes) ?: null,
                'updated_at' => now(),
            ]);

        $this->afterLinesChanged('Producto editado en la orden.');

        $this->cancelEditLine();

        Notification::make()
            ->title('Producto actualizado')
            ->success()
            ->send();
    }

    public function confirmDeleteLine(int $lineId): void
    {
        if (! $this->isDraft) {
            $this->notifyLocked();
            return;
        }

        $line = DB::table('purchase_order_lines')
            ->where('id', $lineId)
            ->where('purchase_order_id', $this->purchaseOrderId)
            ->first();

        if (! $line) {
            return;
        }

        $this->deleteLineId = (int) $line->id;
        $this->deleteLineLabel = trim((string) ($line->product_label ?? 'Producto'));
    }

    public function cancelDeleteLine(): void
    {
        $this->deleteLineId = null;
        $this->deleteLineLabel = '';
    }

    public function deleteConfirmedLine(): void
    {
        if (! $this->isDraft) {
            $this->notifyLocked();
            return;
        }

        if (! $this->deleteLineId) {
            return;
        }

        DB::table('purchase_order_lines')
            ->where('id', $this->deleteLineId)
            ->where('purchase_order_id', $this->purchaseOrderId)
            ->delete();

        $this->afterLinesChanged('Producto eliminado de la orden.');

        $this->cancelDeleteLine();

        Notification::make()
            ->title('Producto eliminado')
            ->success()
            ->send();
    }

    protected function afterLinesChanged(string $note): void
    {
        $before = DB::table('purchase_orders')
            ->where('id', $this->purchaseOrderId)
            ->first();

        $this->recalculateOrderTotals();
        $this->refreshOrderDifference();

        $after = DB::table('purchase_orders')
            ->where('id', $this->purchaseOrderId)
            ->first();

        if (class_exists(PurchaseOrderHistory::class)) {
            PurchaseOrderHistory::log(
                $this->purchaseOrderId,
                'lines_changed',
                $before->status ?? null,
                $after->status ?? null,
                $note . ' No se envía a aprobación hasta presionar Confirmar orden.',
                [
                    'before_total' => (float) ($before->total_with_tax ?? 0),
                    'after_total' => (float) ($after->total_with_tax ?? 0),
                    'differs_from_request' => (bool) ($after->differs_from_request ?? false),
                ]
            );
        }

        $this->loadData();
    }

    protected function recalculateOrderTotals(): void
    {
        $totals = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $this->purchaseOrderId)
            ->selectRaw('
                COALESCE(SUM(line_total_without_tax), 0) as subtotal,
                COALESCE(SUM(line_tax), 0) as tax,
                COALESCE(SUM(line_total_with_tax), 0) as total
            ')
            ->first();

        DB::table('purchase_orders')
            ->where('id', $this->purchaseOrderId)
            ->update([
                'total_without_tax' => (float) ($totals->subtotal ?? 0),
                'total_tax' => (float) ($totals->tax ?? 0),
                'total_with_tax' => (float) ($totals->total ?? 0),
                'updated_at' => now(),
            ]);
    }

    protected function refreshOrderDifference(): void
    {
        if (! Schema::hasColumn('purchase_orders', 'current_hash')) {
            return;
        }

        $currentHash = $this->computeOrderHash();

        $order = DB::table('purchase_orders')
            ->where('id', $this->purchaseOrderId)
            ->first();

        $updates = [
            'current_hash' => $currentHash,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('purchase_orders', 'differs_from_request')) {
            $sourceHash = (string) ($order->source_snapshot_hash ?? '');

            $updates['differs_from_request'] = $sourceHash !== ''
                ? $currentHash !== $sourceHash
                : false;
        }

        DB::table('purchase_orders')
            ->where('id', $this->purchaseOrderId)
            ->update($updates);
    }

    protected function computeOrderHash(): string
    {
        $lines = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $this->purchaseOrderId)
            ->orderBy('id')
            ->get()
            ->map(fn ($line): array => [
                'product_id' => (int) ($line->product_id ?? 0),
                'product_variant_id' => (int) ($line->product_variant_id ?? 0),
                'unit' => (string) ($line->purchase_unit_label ?? ''),
                'factor' => number_format((float) ($line->purchase_unit_factor ?? 1), 6, '.', ''),
                'qty' => number_format((float) ($line->ordered_quantity ?? 0), 6, '.', ''),
                'base_qty' => number_format((float) ($line->base_quantity ?? 0), 6, '.', ''),
                'cost' => number_format((float) ($line->unit_cost_without_tax ?? 0), 6, '.', ''),
                'tax' => number_format((float) ($line->tax_rate ?? 0), 4, '.', ''),
                'total' => number_format((float) ($line->line_total_with_tax ?? 0), 6, '.', ''),
            ])
            ->values()
            ->all();

        return hash('sha256', json_encode($lines, JSON_UNESCAPED_UNICODE));
    }

    protected function resetAddFields(): void
    {
        $this->productSearch = '';
        $this->productResults = [];
        $this->selectedProductId = null;
        $this->selectedProductLabel = '';
        $this->selectedVariantId = null;
        $this->variantOptions = [];
        $this->selectedUnitKey = 'base';
        $this->unitOptions = [];
        $this->newQuantity = '1';
        $this->newPurchaseUnitFactor = '1';
        $this->newUnitCostWithoutTax = '0';
        $this->newTaxRate = '16.0000';
        $this->newSuggestedUxe = null;
        $this->newSuggestedUxeText = '';
    }

    public function applySuggestedUxe(): void
    {
        if ($this->newSuggestedUxe === null || $this->newSuggestedUxe < 1) {
            return;
        }

        $this->newPurchaseUnitFactor = number_format(
            $this->newSuggestedUxe,
            6,
            '.',
            ''
        );
    }

    public function applyEditSuggestedUxe(): void
    {
        if ($this->editSuggestedUxe === null || $this->editSuggestedUxe < 1) {
            return;
        }

        $this->editPurchaseUnitFactor = number_format(
            $this->editSuggestedUxe,
            6,
            '.',
            ''
        );
    }

    protected function refreshNewUxeSuggestion(): void
    {
        $this->newSuggestedUxe = null;
        $this->newSuggestedUxeText = '';

        if (! $this->selectedProductId) {
            return;
        }

        $unitLabel = (string) (
            $this->unitOptions[$this->selectedUnitKey]['label']
            ?? 'Pieza'
        );

        $history = $this->findHistoricalUxe(
            (int) $this->selectedProductId,
            $this->selectedVariantId
                ? (int) $this->selectedVariantId
                : null,
            $unitLabel
        );

        if (! $history) {
            $this->newSuggestedUxeText =
                'Sin UXE histórico para este producto/proveedor.';
            return;
        }

        $this->newSuggestedUxe = (float) $history['factor'];
        $this->newSuggestedUxeText = $this->uxeHistoryText($history);
    }

    protected function refreshEditUxeSuggestion(object $line): void
    {
        $this->editSuggestedUxe = null;
        $this->editSuggestedUxeText = '';

        $productId = (int) ($line->product_id ?? 0);

        if ($productId <= 0) {
            return;
        }

        $variantId = (int) ($line->product_variant_id ?? 0);

        $history = $this->findHistoricalUxe(
            $productId,
            $variantId > 0 ? $variantId : null,
            (string) ($line->purchase_unit_label ?? '')
        );

        if (! $history) {
            $this->editSuggestedUxeText =
                'Sin UXE histórico para este producto/proveedor.';
            return;
        }

        $this->editSuggestedUxe = (float) $history['factor'];
        $this->editSuggestedUxeText = $this->uxeHistoryText($history);
    }

    protected function findHistoricalUxe(
        int $productId,
        ?int $variantId,
        string $unitLabel = ''
    ): ?array {
        $companyId = (int) ($this->order['company_id'] ?? 0);

        if ($companyId <= 0 || $productId <= 0) {
            return null;
        }

        $supplierContactId = (int) (
            $this->order['supplier_contact_id']
            ?? 0
        );

        $supplierName = trim((string) (
            $this->order['supplier_name']
            ?? ''
        ));

        $query = DB::table('purchase_order_lines as pol')
            ->join(
                'purchase_orders as po',
                'po.id',
                '=',
                'pol.purchase_order_id'
            )
            ->where('po.company_id', $companyId)
            ->where('po.id', '<>', $this->purchaseOrderId)
            ->where('pol.product_id', $productId)
            ->where('pol.purchase_unit_factor', '>', 0)
            ->whereNotIn('po.status', ['cancelled', 'canceled']);

        if ($variantId) {
            $query->where('pol.product_variant_id', $variantId);
        } else {
            $query->whereNull('pol.product_variant_id');
        }

        if ($supplierContactId > 0) {
            $query->where(
                'po.supplier_contact_id',
                $supplierContactId
            );
        } elseif ($supplierName !== '') {
            $query->whereRaw(
                "LOWER(TRIM(COALESCE(po.supplier_name, ''))) = ?",
                [mb_strtolower($supplierName)]
            );
        } else {
            return null;
        }

        $columns = [
            'pol.purchase_unit_factor',
            'pol.purchase_unit_label',
            'po.number as purchase_order_number',
            'po.order_date as purchase_order_date',
        ];

        $row = null;

        if (trim($unitLabel) !== '') {
            $row = (clone $query)
                ->where('pol.purchase_unit_label', $unitLabel)
                ->orderByDesc('po.order_date')
                ->orderByDesc('po.id')
                ->orderByDesc('pol.id')
                ->first($columns);
        }

        if (! $row) {
            $row = $query
                ->orderByDesc('po.order_date')
                ->orderByDesc('po.id')
                ->orderByDesc('pol.id')
                ->first($columns);
        }

        if (! $row) {
            return null;
        }

        $factor = (float) ($row->purchase_unit_factor ?? 0);

        if ($factor < 1) {
            return null;
        }

        return [
            'factor' => $factor,
            'unit_label' => (string) (
                $row->purchase_unit_label
                ?? ''
            ),
            'order_number' => (string) (
                $row->purchase_order_number
                ?? ''
            ),
            'order_date' => (string) (
                $row->purchase_order_date
                ?? ''
            ),
        ];
    }

    protected function uxeHistoryText(array $history): string
    {
        $factor = rtrim(
            rtrim(
                number_format(
                    (float) ($history['factor'] ?? 1),
                    6,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        );

        $parts = ['UXE sugerido: ' . $factor];

        $number = trim((string) (
            $history['order_number']
            ?? ''
        ));

        if ($number !== '') {
            $parts[] = 'última compra ' . $number;
        }

        $date = trim((string) (
            $history['order_date']
            ?? ''
        ));

        if ($date !== '' && strtotime($date) !== false) {
            $parts[] = date('d/m/Y', strtotime($date));
        }

        $unit = trim((string) (
            $history['unit_label']
            ?? ''
        ));

        if ($unit !== '') {
            $parts[] = 'unidad ' . $unit;
        }

        return implode(' · ', $parts);
    }

    protected function resolvedUnitCostWithTax(object $line): float
    {
        $stored = (float) ($line->unit_cost_with_tax ?? 0);
        $withoutTax = (float) ($line->unit_cost_without_tax ?? 0);

        if ($stored > 0 || $withoutTax <= 0) {
            return $stored;
        }

        $taxRate = (float) ($line->tax_rate ?? 0);

        return round(
            $withoutTax * (1 + ($taxRate / 100)),
            6
        );
    }

    protected function resolvedLineTotalWithTax(object $line): float
    {
        $stored = (float) ($line->line_total_with_tax ?? 0);

        if ($stored > 0) {
            return $stored;
        }

        $quantity = (float) ($line->ordered_quantity ?? 0);

        return round(
            $quantity * $this->resolvedUnitCostWithTax($line),
            6
        );
    }

    protected function refreshTotals(): void
    {
        $this->subtotal = collect($this->lines)
            ->sum(fn ($line): float => $this->toFloat($line['line_total_without_tax'] ?? 0));

        $this->taxTotal = collect($this->lines)
            ->sum(fn ($line): float => $this->toFloat($line['line_tax'] ?? 0));

        $this->total = collect($this->lines)
            ->sum(fn ($line): float => $this->toFloat($line['line_total_with_tax'] ?? 0));
    }

    protected function findProduct(int $id): ?object
    {
        return Schema::hasTable('products')
            ? DB::table('products')->where('id', $id)->first()
            : null;
    }

    protected function findVariant(int $id): ?object
    {
        return $this->findProduct($id);
    }

    protected function getVariantOptions(int $productId): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        $columns = Schema::getColumnListing('products');

        if (! in_array('parent_product_id', $columns, true)) {
            return [];
        }

        $query = DB::table('products')
            ->where('parent_product_id', $productId);

        if (in_array('company_id', $columns, true) && ! empty($this->order['company_id'])) {
            $query->where(function ($q): void {
                $q->where('company_id', $this->order['company_id'])->orWhereNull('company_id');
            });
        }

        if (in_array('is_variant', $columns, true)) {
            $query->where('is_variant', true);
        }

        if (in_array('is_active', $columns, true)) {
            $query->where('is_active', true);
        }

        if (in_array('can_be_purchased', $columns, true)) {
            $query->where('can_be_purchased', true);
        }

        $orderColumn = in_array('variant_value', $columns, true)
            ? 'variant_value'
            : (in_array('variant_name', $columns, true) ? 'variant_name' : 'name');

        return $query
            ->orderBy($orderColumn)
            ->orderBy('id')
            ->get()
            ->map(fn ($variant): array => [
                'id' => (int) $variant->id,
                'label' => $this->variantLabel($variant),
            ])
            ->values()
            ->all();
    }

    protected function getUnitOptions(int $productId): array
    {
        $options = [
            'base' => $this->baseUnitOption(),
        ];

        if (Schema::hasTable('product_purchase_units')) {
            $columns = Schema::getColumnListing('product_purchase_units');

            $query = DB::table('product_purchase_units')
                ->where('product_id', $productId);

            if (in_array('is_active', $columns, true)) {
                $query->where('is_active', true);
            }

            $rows = $query
                ->orderByDesc(in_array('is_default', $columns, true) ? 'is_default' : 'id')
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                $factor = (float) ($row->factor ?? 1);
                $name = trim((string) ($row->name ?? 'Unidad'));

                $options['purchase_unit_' . $row->id] = [
                    'type' => 'purchase_unit',
                    'id' => (int) $row->id,
                    'label' => $name . ' (' . rtrim(rtrim(number_format($factor, 4, '.', ''), '0'), '.') . ' base)',
                    'factor' => $factor,
                    'sat_unit_key' => $row->sat_unit_key ?? null,
                    'sat_unit_name' => $row->sat_unit_name ?? null,
                ];
            }
        }

        return $options;
    }

    protected function baseUnitOption(): array
    {
        return [
            'type' => 'base',
            'id' => null,
            'label' => 'Pieza',
            'factor' => 1,
            'sat_unit_key' => 'H87',
            'sat_unit_name' => 'Pieza',
        ];
    }

    protected function getTaxOptions(): array
    {
        $options = [];

        foreach ([
            'taxes',
            'tax_rates',
            'company_taxes',
            'sat_taxes',
            'sat_tax_rates',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);

            $rateColumn = $this->firstExistingColumn($columns, [
                'rate',
                'tax_rate',
                'percentage',
                'percent',
                'value',
            ]);

            if (! $rateColumn) {
                continue;
            }

            $query = DB::table($table);

            if (in_array('company_id', $columns, true) && ! empty($this->order['company_id'])) {
                $query->where(function ($q): void {
                    $q->where('company_id', $this->order['company_id'])->orWhereNull('company_id');
                });
            }

            $activeColumn = $this->firstExistingColumn($columns, [
                'is_active',
                'active',
                'enabled',
            ]);

            if ($activeColumn) {
                $query->where($activeColumn, true);
            }

            foreach ($query->orderBy($rateColumn)->limit(50)->get() as $row) {
                $rate = $this->normalizeTaxRate($row->{$rateColumn} ?? 0);

                $name = $this->firstValue($row, [
                    'name',
                    'label',
                    'description',
                    'descripcion',
                    'tax_name',
                    'code',
                    'sat_code',
                ]) ?: (((float) $rate > 0) ? 'IVA ' . rtrim(rtrim($rate, '0'), '.') . '%' : 'Exento / 0%');

                $options[$rate] = $name . ' (' . rtrim(rtrim($rate, '0'), '.') . '%)';
            }
        }

        if (empty($options)) {
            $options = [
                '16.0000' => 'IVA 16% (16.00%)',
                '8.0000' => 'IVA 8% (8.00%)',
                '0.0000' => 'IVA 0% / Exento (0.00%)',
            ];
        }

        if (! array_key_exists('16.0000', $options)) {
            $options = ['16.0000' => 'IVA 16% (16.00%)'] + $options;
        }

        return $options;
    }

    protected function resolveDefaultTaxRate(object $product): string
    {
        $productRate = $this->normalizeTaxRate($this->productTaxRate($product));

        if (array_key_exists($productRate, $this->taxOptions)) {
            return $productRate;
        }

        return array_key_exists('16.0000', $this->taxOptions)
            ? '16.0000'
            : (array_key_first($this->taxOptions) ?: '16.0000');
    }

    protected function productLabel(object $product): string
    {
        $code = $this->firstValue($product, ['internal_reference', 'code', 'codigo', 'sku', 'reference']);
        $name = $this->firstValue($product, ['name', 'nombre', 'description', 'descripcion']);

        if ($code && $name && $code !== $name) {
            return $code . ' - ' . $name;
        }

        return $name ?: ($code ?: ('Producto #' . $product->id));
    }

    protected function variantLabel(object $variant): string
    {
        $value = property_exists($variant, 'variant_value')
            ? trim((string) ($variant->variant_value ?? ''))
            : '';

        if ($value !== '') {
            return $value;
        }

        $name = property_exists($variant, 'variant_name')
            ? trim((string) ($variant->variant_name ?? ''))
            : '';

        if ($name !== '') {
            return $name;
        }

        $signature = property_exists($variant, 'variant_signature')
            ? trim((string) ($variant->variant_signature ?? ''))
            : '';

        if ($signature !== '') {
            return str_contains($signature, ':')
                ? trim(substr($signature, strrpos($signature, ':') + 1))
                : $signature;
        }

        return 'Variante #' . ($variant->id ?? '');
    }

    protected function productCost(?object $product): float
    {
        if (! $product) {
            return 0;
        }

        foreach ([
            'purchase_price',
            'purchase_cost',
            'purchase_cost_without_tax',
            'purchase_unit_cost',
            'standard_cost',
            'average_cost_without_tax',
            'average_cost',
            'last_purchase_cost',
            'last_cost',
            'cost_without_tax',
            'cost',
            'base_cost',
        ] as $column) {
            if (property_exists($product, $column) && is_numeric($product->{$column}) && (float) $product->{$column} > 0) {
                return (float) $product->{$column};
            }
        }

        return $this->lookupProductCost((int) ($product->id ?? 0));
    }


    protected function lookupProductCost(int $productId): float
    {
        if ($productId <= 0) {
            return 0;
        }

        foreach ([
            'product_costs',
            'product_prices',
            'product_price_costs',
            'product_supplier_prices',
            'product_company_costs',
            'product_cost_histories',
            'inventory_product_costs',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);

            $productColumn = $this->firstExistingColumn($columns, [
                'product_id',
                'product_variant_id',
                'variant_id',
            ]);

            if (! $productColumn) {
                continue;
            }

            $costColumn = $this->firstExistingColumn($columns, [
                'purchase_price',
                'purchase_cost',
                'purchase_cost_without_tax',
                'unit_cost_without_tax',
                'average_cost_without_tax',
                'standard_cost',
                'last_purchase_cost',
                'cost_without_tax',
                'cost',
                'price',
            ]);

            if (! $costColumn) {
                continue;
            }

            $query = DB::table($table)
                ->where($productColumn, $productId)
                ->whereNotNull($costColumn);

            if (in_array('company_id', $columns, true) && ! empty($this->order['company_id'])) {
                $query->where(function ($q): void {
                    $q->where('company_id', $this->order['company_id'])->orWhereNull('company_id');
                });
            }

            $activeColumn = $this->firstExistingColumn($columns, ['is_active', 'active', 'enabled']);

            if ($activeColumn) {
                $query->where($activeColumn, true);
            }

            foreach (['is_default', 'current', 'updated_at', 'created_at', 'id'] as $sortColumn) {
                if (in_array($sortColumn, $columns, true)) {
                    $query->orderByDesc($sortColumn);
                }
            }

            $value = $query->value($costColumn);

            if (is_numeric($value) && (float) $value > 0) {
                return (float) $value;
            }
        }

        return 0;
    }


    protected function productTaxRate(object $product): float
    {
        foreach ([
            'purchase_tax_rate',
            'sale_tax_rate',
            'tax_rate',
            'tax_percent',
            'iva_rate',
            'iva_percent',
        ] as $column) {
            if (property_exists($product, $column) && is_numeric($product->{$column})) {
                return (float) $product->{$column};
            }
        }

        return 16;
    }

    protected function firstValue(object $row, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (property_exists($row, $column) && trim((string) $row->{$column}) !== '') {
                return trim((string) $row->{$column});
            }
        }

        return null;
    }

    protected function firstExistingColumn(array $columns, array $candidates): ?string
    {
        $lower = array_map('strtolower', $columns);

        foreach ($candidates as $candidate) {
            $index = array_search(strtolower($candidate), $lower, true);

            if ($index !== false) {
                return $columns[$index];
            }
        }

        return null;
    }

    protected function normalizeTaxRate(mixed $rate): string
    {
        return number_format((float) $rate, 4, '.', '');
    }

    protected function toFloat(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace([',', '$', ' '], ['', '', ''], $value);
        }

        return (float) $value;
    }

    protected function notifyLocked(): void
    {
        Notification::make()
            ->title('La orden no se puede editar')
            ->body('Solo las órdenes en borrador permiten cambios de productos.')
            ->danger()
            ->send();
    }

    public function render()
    {
        return view('livewire.purchase-order-lines-inline');
    }
}
