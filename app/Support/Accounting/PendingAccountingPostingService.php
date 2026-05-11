<?php

namespace App\Support\Accounting;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class PendingAccountingPostingService
{
    public function pendingDocuments(?int $companyId = null): array
    {
        return [
            'purchase_orders' => $this->pendingPurchaseOrders($companyId),
            'sales_orders' => $this->pendingSalesOrders($companyId),
            'pos_orders' => $this->pendingPosOrders($companyId),
            'pos_order_refunds' => $this->pendingPosRefunds($companyId),
            'invoices' => $this->pendingInvoices($companyId),
        ];
    }

    public function counters(?int $companyId = null): array
    {
        $docs = $this->pendingDocuments($companyId);

        $countPending = function (array $rows): int {
            return count(array_filter($rows, fn ($row) => (bool) ($row['counts_as_pending'] ?? true)));
        };

        return [
            'purchase_orders' => $countPending($docs['purchase_orders']),
            'sales_orders' => $countPending($docs['sales_orders']),
            'pos_orders' => $countPending($docs['pos_orders']),
            'pos_order_refunds' => $countPending($docs['pos_order_refunds']),
            'invoices' => $countPending($docs['invoices']),
            'total' => array_sum([
                $countPending($docs['purchase_orders']),
                $countPending($docs['sales_orders']),
                $countPending($docs['pos_orders']),
                $countPending($docs['pos_order_refunds']),
                $countPending($docs['invoices']),
            ]),
        ];
    }

    public function post(string $type, int $id, bool $force = false): array
    {
        return match ($type) {
            'purchase_order' => $this->postPurchaseOrder($id, $force),
            'sales_order' => $this->postSalesOrder($id, $force),
            'pos_order' => $this->postPosOrder($id, $force),
            'pos_order_refund' => $this->postPosRefund($id, $force),
            'invoice' => $this->postInvoice($id, $force),
            default => throw new RuntimeException('Tipo de documento no soportado: ' . $type),
        };
    }

    public function statusLabel(?string $status): string
    {
        return [
            'posted' => 'Contabilizado',
            'not_posted' => 'Sin contabilizar',
            'not_applicable' => 'No requiere asiento',
            'partial' => 'Parcialmente contabilizado',
            'error' => 'Con error',
            'draft' => 'Borrador',
            'review' => 'En revisión',
            'received' => 'Recibido',
            'delivered' => 'Entregado',
            'paid' => 'Pagado',
            'returned' => 'Devuelto',
            'done' => 'Terminado',
            'pending_payment' => 'Pendiente de pago',
            'cancelled' => 'Cancelado',
            'canceled' => 'Cancelado',
            'cancelled_internal' => 'Cancelado internamente',
            'validation_error' => 'Con error fiscal',
            'stamped' => 'Timbrada',
            'issued' => 'Emitida',
            'valid' => 'Válida',
            'active' => 'Activa',
        ][$status] ?? ($status ?: 'Sin estatus');
    }

    private function pendingPurchaseOrders(?int $companyId): array
    {
        if (! $this->hasTable('purchase_orders')) {
            return [];
        }

        $query = DB::table('purchase_orders')
            ->where('status', 'received')
            ->where(function ($q) {
                $q->whereNull('accounting_status')
                    ->orWhere('accounting_status', '!=', 'posted');
            });

        $this->applyCompany($query, 'purchase_orders', $companyId);

        return $query
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($row) => $this->normalizeRow('purchase_order', 'Compra recibida', $row))
            ->all();
    }

    private function pendingSalesOrders(?int $companyId): array
    {
        if (! $this->hasTable('sales_orders')) {
            return [];
        }

        $query = DB::table('sales_orders')
            ->where('status', 'delivered')
            ->where(function ($q) {
                $q->whereNull('accounting_status')
                    ->orWhere('accounting_status', '!=', 'posted');
            });

        $this->applyCompany($query, 'sales_orders', $companyId);

        return $query
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($row) => $this->normalizeRow('sales_order', 'Venta entregada', $row))
            ->all();
    }

    private function pendingPosOrders(?int $companyId): array
    {
        if (! $this->hasTable('pos_orders')) {
            return [];
        }

        $query = DB::table('pos_orders')
            ->whereIn('status', ['paid', 'returned', 'partial_refunded', 'partially_refunded'])
            ->where(function ($q) {
                $q->whereNull('accounting_status')
                    ->orWhereNotIn('accounting_status', ['posted', 'not_applicable']);
            });

        $this->applyCompany($query, 'pos_orders', $companyId);

        return $query
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($row) => $this->normalizeRow('pos_order', 'Ticket POS', $row))
            ->all();
    }

    private function pendingPosRefunds(?int $companyId): array
    {
        if (! $this->hasTable('pos_order_refunds')) {
            return [];
        }

        $query = DB::table('pos_order_refunds')
            ->whereIn('status', ['done', 'refunded', 'completed'])
            ->where(function ($q) {
                $q->whereNull('accounting_status')
                    ->orWhereNotIn('accounting_status', ['posted', 'not_applicable']);
            });

        $this->applyCompany($query, 'pos_order_refunds', $companyId);

        return $query
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($row) => $this->normalizeRow('pos_order_refund', 'Devolución POS', $row))
            ->all();
    }

    private function pendingInvoices(?int $companyId): array
    {
        if (! $this->hasTable('invoices')) {
            return [];
        }

        $query = DB::table('invoices');

        if (Schema::hasColumn('invoices', 'status')) {
            $query->whereNotIn('status', ['cancelled', 'canceled']);
        }

        $query->where(function ($q) {
            $q->whereNull('accounting_status')
                ->orWhereNotIn('accounting_status', ['posted', 'cancelled', 'canceled']);
        });

        $this->applyCompany($query, 'invoices', $companyId);

        return $query
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($row) => $this->normalizeRow('invoice', 'Factura interna', $row))
            ->all();
    }

    private function normalizeRow(string $type, string $typeLabel, object $row): array
    {
        $number = $row->number ?? ('#' . $row->id);
        $date = $row->order_date
            ?? $row->invoice_date
            ?? $row->ordered_at
            ?? $row->refunded_at
            ?? $row->created_at
            ?? null;

        $total = $row->total_with_tax
            ?? $row->total
            ?? $row->total_without_tax
            ?? 0;

        $canPost = true;
        $blockReason = null;
        $blockLabel = 'No lista';
        $fiscalStatusLabel = null;
        $countsAsPending = true;

        if ($type === 'invoice') {
            $readiness = $this->invoicePostingReadiness($row);
            $canPost = $readiness['can_post'];
            $blockReason = $readiness['reason'];
            $blockLabel = $readiness['label'] ?? 'No lista';
            $fiscalStatusLabel = $readiness['fiscal_status_label'];
        }

        if ($type === 'pos_order') {
            $readiness = $this->posOrderPostingReadiness($row);
            $canPost = $readiness['can_post'];
            $blockReason = $readiness['reason'];
            $blockLabel = $readiness['label'] ?? 'No lista';
            $countsAsPending = $readiness['counts_as_pending'] ?? true;
        }

        if ($type === 'pos_order_refund') {
            $readiness = $this->posRefundPostingReadiness($row);
            $canPost = $readiness['can_post'];
            $blockReason = $readiness['reason'];
            $blockLabel = $readiness['label'] ?? 'No lista';
            $countsAsPending = $readiness['counts_as_pending'] ?? true;
        }

        return [
            'type' => $type,
            'type_label' => $typeLabel,
            'id' => (int) $row->id,
            'company_id' => (int) ($row->company_id ?? 0),
            'number' => (string) $number,
            'status' => (string) ($row->status ?? ''),
            'status_label' => $this->statusLabel((string) ($row->status ?? '')),
            'accounting_status' => (string) ($row->accounting_status ?? ''),
            'accounting_status_label' => $this->statusLabel((string) ($row->accounting_status ?? '')),
            'accounting_entry_id' => $row->accounting_entry_id ?? null,
            'date' => $date,
            'total' => (float) $total,
            'error' => $row->accounting_error_message ?? null,
            'can_post' => $canPost,
            'block_reason' => $blockReason,
            'block_label' => $blockLabel,
            'fiscal_status_label' => $fiscalStatusLabel,
            'counts_as_pending' => $countsAsPending,
        ];
    }

    private function invoicePostingReadiness(object $invoice): array
    {
        $status = (string) ($invoice->status ?? '');
        $cfdiStatus = (string) ($invoice->cfdi_status ?? '');
        $uuid = trim((string) ($invoice->cfdi_uuid ?? ''));
        $stampedAt = trim((string) ($invoice->cfdi_stamped_at ?? ''));
        $accountingStatus = (string) ($invoice->accounting_status ?? '');
        $accountingEntryId = $invoice->accounting_entry_id ?? null;

        $fiscalStatusLabel = $this->statusLabel($cfdiStatus ?: $status);

        if (in_array($status, ['cancelled', 'canceled'], true)) {
            return [
                'can_post' => false,
                'reason' => 'La factura está cancelada.',
                'label' => 'Cancelada',
                'fiscal_status_label' => $fiscalStatusLabel,
            ];
        }

        if (in_array($accountingStatus, ['posted'], true) || $accountingEntryId) {
            return [
                'can_post' => false,
                'reason' => 'La factura ya tiene asiento contable.',
                'label' => 'Ya contabilizada',
                'fiscal_status_label' => $fiscalStatusLabel,
            ];
        }

        if ($uuid === '' || $stampedAt === '') {
            return [
                'can_post' => false,
                'reason' => 'La factura todavía no está timbrada. Corrige los datos fiscales y timbra antes de contabilizar.',
                'label' => 'No lista',
                'fiscal_status_label' => $fiscalStatusLabel,
            ];
        }

        if (in_array($cfdiStatus, ['validation_error', 'error', 'cancelled', 'cancelled_internal'], true)) {
            return [
                'can_post' => false,
                'reason' => 'La factura tiene estatus fiscal no válido para contabilizar: ' . $this->statusLabel($cfdiStatus),
                'label' => 'No lista',
                'fiscal_status_label' => $fiscalStatusLabel,
            ];
        }

        return [
            'can_post' => true,
            'reason' => null,
            'label' => 'Lista',
            'fiscal_status_label' => $fiscalStatusLabel,
        ];
    }

    private function posOrderPostingReadiness(object $order): array
    {
        $status = (string) ($order->status ?? '');

        if (! in_array($status, ['paid', 'returned', 'partial_refunded', 'partially_refunded'], true)) {
            return [
                'can_post' => false,
                'reason' => 'El ticket POS no está en un estado listo para contabilizar.',
                'label' => 'No lista',
                'counts_as_pending' => true,
            ];
        }

        if (! Schema::hasTable('pos_order_lines')) {
            return [
                'can_post' => false,
                'reason' => 'No existe la tabla de líneas POS.',
                'label' => 'No lista',
                'counts_as_pending' => true,
            ];
        }

        $companyId = (int) ($order->company_id ?? 0);

        if ($companyId <= 0) {
            return [
                'can_post' => false,
                'reason' => 'El ticket POS no tiene empresa válida.',
                'label' => 'No lista',
                'counts_as_pending' => true,
            ];
        }

        $query = DB::table('pos_order_lines')
            ->where('pos_order_id', (int) $order->id)
            ->whereNotNull('product_id');

        if (Schema::hasColumn('pos_order_lines', 'quantity')) {
            $query->where('quantity', '>', 0);
        }

        if (Schema::hasColumn('pos_order_lines', 'accounting_status')) {
            $query->where(function ($q) {
                $q->whereNull('accounting_status')
                    ->orWhereNotIn('accounting_status', ['posted', 'not_applicable']);
            });
        }

        $lines = $query->get();

        if ($lines->isEmpty()) {
            return [
                'can_post' => false,
                'reason' => 'El ticket no tiene líneas pendientes de contabilizar.',
                'label' => 'Sin pendientes',
                'counts_as_pending' => false,
            ];
        }

        $stockableLines = 0;
        $missing = [];

        foreach ($lines as $line) {
            $productId = (int) ($line->product_id ?? 0);

            if ($productId <= 0) {
                continue;
            }

            if (! $this->productRequiresInventoryCost($companyId, $productId)) {
                continue;
            }

            $stockableLines++;

            $cost = $this->resolveProductUnitCostForPosting($companyId, $productId, null);

            if ($cost <= 0) {
                $missing[] = $this->productLabel($productId);
            }
        }

        if ($stockableLines <= 0) {
            return [
                'can_post' => false,
                'reason' => 'El ticket solo contiene servicios o productos sin control de inventario. No requiere asiento de costo de inventario.',
                'label' => 'No requiere asiento',
                'counts_as_pending' => false,
            ];
        }

        if (! empty($missing)) {
            return [
                'can_post' => false,
                'reason' => 'No se puede contabilizar porque hay productos físicos sin costo: ' . implode(', ', array_unique($missing)) . '.',
                'label' => 'Falta costo',
                'counts_as_pending' => true,
            ];
        }

        return [
            'can_post' => true,
            'reason' => null,
            'label' => 'Lista',
            'counts_as_pending' => true,
        ];
    }

    private function posRefundPostingReadiness(object $refund): array
    {
        $status = (string) ($refund->status ?? '');

        if (! in_array($status, ['done', 'refunded', 'completed'], true)) {
            return [
                'can_post' => false,
                'reason' => 'La devolución POS no está finalizada.',
                'label' => 'No lista',
                'counts_as_pending' => true,
            ];
        }

        if (! Schema::hasTable('pos_order_refund_lines')) {
            return [
                'can_post' => false,
                'reason' => 'No existe la tabla de líneas de devolución POS.',
                'label' => 'No lista',
                'counts_as_pending' => true,
            ];
        }

        $companyId = (int) ($refund->company_id ?? 0);

        if ($companyId <= 0 && isset($refund->pos_order_id) && Schema::hasTable('pos_orders')) {
            $companyId = (int) DB::table('pos_orders')
                ->where('id', $refund->pos_order_id)
                ->value('company_id');
        }

        if ($companyId <= 0) {
            return [
                'can_post' => false,
                'reason' => 'La devolución POS no tiene empresa válida.',
                'label' => 'No lista',
                'counts_as_pending' => true,
            ];
        }

        $query = DB::table('pos_order_refund_lines')
            ->where('pos_order_refund_id', (int) $refund->id)
            ->whereNotNull('product_id');

        if (Schema::hasColumn('pos_order_refund_lines', 'quantity')) {
            $query->where('quantity', '>', 0);
        }

        if (Schema::hasColumn('pos_order_refund_lines', 'accounting_status')) {
            $query->where(function ($q) {
                $q->whereNull('accounting_status')
                    ->orWhereNotIn('accounting_status', ['posted', 'not_applicable']);
            });
        }

        $lines = $query->get();

        if ($lines->isEmpty()) {
            return [
                'can_post' => false,
                'reason' => 'La devolución no tiene líneas pendientes de contabilizar.',
                'label' => 'Sin pendientes',
                'counts_as_pending' => false,
            ];
        }

        $stockableLines = 0;
        $missing = [];

        foreach ($lines as $line) {
            $productId = (int) ($line->product_id ?? 0);

            if ($productId <= 0) {
                continue;
            }

            if (! $this->productRequiresInventoryCost($companyId, $productId)) {
                continue;
            }

            $stockableLines++;

            $originalLineId = (int) ($line->pos_order_line_id ?? 0);
            $cost = $this->resolveProductUnitCostForPosting($companyId, $productId, $originalLineId > 0 ? $originalLineId : null);

            if ($cost <= 0) {
                $missing[] = $this->productLabel($productId);
            }
        }

        if ($stockableLines <= 0) {
            return [
                'can_post' => false,
                'reason' => 'La devolución solo contiene servicios o productos sin control de inventario. No requiere asiento de inventario.',
                'label' => 'No requiere asiento',
                'counts_as_pending' => false,
            ];
        }

        if (! empty($missing)) {
            return [
                'can_post' => false,
                'reason' => 'No se puede contabilizar la devolución porque hay productos físicos sin costo: ' . implode(', ', array_unique($missing)) . '.',
                'label' => 'Falta costo',
                'counts_as_pending' => true,
            ];
        }

        return [
            'can_post' => true,
            'reason' => null,
            'label' => 'Lista',
            'counts_as_pending' => true,
        ];
    }

    private function productRequiresInventoryCost(int $companyId, int $productId): bool
    {
        if (Schema::hasTable('accounting_product_inventory_classifications')) {
            $row = DB::table('accounting_product_inventory_classifications')
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->orderByDesc('id')
                ->first();

            if ($row) {
                return (bool) $row->requires_inventory_cost;
            }
        }

        if (! Schema::hasTable('products')) {
            return true;
        }

        $product = DB::table('products')->where('id', $productId)->first();

        if (! $product) {
            return true;
        }

        foreach (['is_stockable', 'stockable', 'track_inventory', 'manage_stock', 'inventory_tracking'] as $field) {
            if (property_exists($product, $field) && $product->{$field} !== null) {
                return (bool) $product->{$field};
            }
        }

        foreach (['is_service', 'is_non_stockable'] as $field) {
            if (property_exists($product, $field) && $product->{$field} !== null && (bool) $product->{$field}) {
                return false;
            }
        }

        foreach (['type', 'product_type', 'inventory_type'] as $field) {
            if (property_exists($product, $field)) {
                $value = strtolower(trim((string) $product->{$field}));

                if (in_array($value, ['service', 'servicio', 'non_stockable', 'no_stockable', 'consumable_service'], true)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function resolveProductUnitCostForPosting(int $companyId, int $productId, ?int $originalPosLineId = null): float
    {
        if ($originalPosLineId && Schema::hasTable('accounting_inventory_valuation_layers')) {
            $fromOriginalSale = DB::table('accounting_inventory_valuation_layers')
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->where('source_type', 'pos_order_lines')
                ->where('source_id', $originalPosLineId)
                ->where('operation_type', 'sale_issue')
                ->where('unit_cost', '>', 0)
                ->orderByDesc('id')
                ->value('unit_cost');

            if ((float) $fromOriginalSale > 0) {
                return round((float) $fromOriginalSale, 6);
            }
        }

        if (Schema::hasTable('accounting_product_cost_overrides')) {
            $overrideCost = DB::table('accounting_product_cost_overrides')
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->where('cost_without_tax', '>', 0)
                ->orderByDesc('applied_at')
                ->orderByDesc('id')
                ->value('cost_without_tax');

            if ((float) $overrideCost > 0) {
                return round((float) $overrideCost, 6);
            }
        }

        if (Schema::hasTable('stock_balances') && Schema::hasColumn('stock_balances', 'average_cost_without_tax')) {
            $cost = DB::table('stock_balances')
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->where('average_cost_without_tax', '>', 0)
                ->orderByDesc('updated_at')
                ->value('average_cost_without_tax');

            if ((float) $cost > 0) {
                return round((float) $cost, 6);
            }
        }

        if (Schema::hasTable('products')) {
            $product = DB::table('products')->where('id', $productId)->first();

            if ($product) {
                foreach ([
                    'average_cost_without_tax',
                    'standard_cost',
                    'purchase_price',
                    'last_purchase_cost',
                    'cost',
                    'cost_price',
                ] as $field) {
                    if (property_exists($product, $field) && (float) $product->{$field} > 0) {
                        return round((float) $product->{$field}, 6);
                    }
                }
            }
        }

        if (Schema::hasTable('accounting_inventory_valuation_layers')) {
            $cost = DB::table('accounting_inventory_valuation_layers')
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->whereIn('operation_type', ['purchase_receipt', 'adjustment_in', 'customer_return'])
                ->where('unit_cost', '>', 0)
                ->orderByDesc('movement_date')
                ->orderByDesc('id')
                ->value('unit_cost');

            if ((float) $cost > 0) {
                return round((float) $cost, 6);
            }
        }

        return 0.0;
    }

    private function productLabel(int $productId): string
    {
        if (! Schema::hasTable('products')) {
            return '#' . $productId;
        }

        $product = DB::table('products')->where('id', $productId)->first();

        if (! $product) {
            return '#' . $productId;
        }

        $name = null;

        foreach (['name', 'product_name', 'description'] as $field) {
            if (property_exists($product, $field) && trim((string) $product->{$field}) !== '') {
                $name = trim((string) $product->{$field});
                break;
            }
        }

        $code = null;

        foreach (['sku', 'reference', 'default_code', 'code', 'barcode'] as $field) {
            if (property_exists($product, $field) && trim((string) $product->{$field}) !== '') {
                $code = trim((string) $product->{$field});
                break;
            }
        }

        $label = '#' . $productId;

        if ($code) {
            $label .= ' ' . $code;
        }

        if ($name) {
            $label .= ' ' . $name;
        }

        return $label;
    }

    private function postPurchaseOrder(int $id, bool $force): array
    {
        $summary = app(InventoryDocumentAccountingPoster::class)
            ->postPurchaseOrderReceipt($id, false, $force);

        return $this->postResult('purchase_order', $id, $summary);
    }

    private function postSalesOrder(int $id, bool $force): array
    {
        $summary = app(InventoryDocumentAccountingPoster::class)
            ->postSalesOrderCost($id, false, $force);

        return $this->postResult('sales_order', $id, $summary);
    }

    private function postPosOrder(int $id, bool $force): array
    {
        $readiness = $this->posOrderPostingReadiness(DB::table('pos_orders')->where('id', $id)->first());

        if (! ($readiness['can_post'] ?? false) && ! $force) {
            throw new RuntimeException($readiness['reason'] ?? 'El ticket POS no está listo para contabilizar.');
        }

        $summary = app(InventoryPosAccountingPoster::class)
            ->postPosOrderCost($id, false, $force);

        return $this->postResult('pos_order', $id, $summary);
    }

    private function postPosRefund(int $id, bool $force): array
    {
        $readiness = $this->posRefundPostingReadiness(DB::table('pos_order_refunds')->where('id', $id)->first());

        if (! ($readiness['can_post'] ?? false) && ! $force) {
            throw new RuntimeException($readiness['reason'] ?? 'La devolución POS no está lista para contabilizar.');
        }

        $summary = app(InventoryPosAccountingPoster::class)
            ->postPosRefundReturn($id, false, $force);

        return $this->postResult('pos_order_refund', $id, $summary);
    }

    private function postInvoice(int $id, bool $force): array
    {
        $invoice = DB::table('invoices')->where('id', $id)->first();

        if (! $invoice) {
            throw new RuntimeException('No existe la factura ID ' . $id);
        }

        $readiness = $this->invoicePostingReadiness($invoice);

        if (! $readiness['can_post'] && ! $force) {
            throw new RuntimeException($readiness['reason'] ?? 'La factura no está lista para contabilizar.');
        }

        $command = 'bexia:accounting-post-invoice';

        if (! array_key_exists($command, Artisan::all())) {
            throw new RuntimeException('El comando de contabilización de facturas internas no está disponible.');
        }

        $exitCode = Artisan::call($command, [
            'invoice_id' => $id,
        ]);

        $output = Artisan::output();

        if ($exitCode !== 0 && ! $force) {
            throw new RuntimeException(trim($output) ?: 'No se pudo contabilizar la factura interna.');
        }

        return [
            'type' => 'invoice',
            'source_id' => $id,
            'posted_count' => $exitCode === 0 ? 1 : 0,
            'skipped_count' => 0,
            'error_count' => $exitCode === 0 ? 0 : 1,
            'raw' => [
                'exit_code' => $exitCode,
                'output' => $output,
            ],
        ];
    }

    private function postResult(string $type, int $id, array $summary): array
    {
        return [
            'type' => $type,
            'source_id' => $id,
            'posted_count' => count($summary['posted'] ?? []),
            'skipped_count' => count($summary['skipped'] ?? []),
            'error_count' => count($summary['errors'] ?? []),
            'raw' => $summary,
        ];
    }

    private function applyCompany($query, string $table, ?int $companyId): void
    {
        if ($companyId && Schema::hasColumn($table, 'company_id')) {
            $query->where('company_id', $companyId);
        }
    }

    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable $e) {
            return false;
        }
    }
}
