<?php

namespace App\Support\Accounting;

use App\Models\AccountingEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class InventoryDocumentAccountingPoster
{
    public function postPurchaseOrderReceipt(int $purchaseOrderId, bool $dryRun = false, bool $force = false): array
    {
        $this->assertTable('purchase_orders');
        $this->assertTable('purchase_order_lines');

        $order = DB::table('purchase_orders')->where('id', $purchaseOrderId)->first();

        if (! $order) {
            throw new RuntimeException('No existe purchase_order ID ' . $purchaseOrderId);
        }

        if (! $force && ! in_array((string) $order->status, ['received', 'done', 'completed'], true)) {
            throw new RuntimeException('La OC no está recibida. Estado actual: ' . ($order->status ?? 'N/A') . '. Usa --force solo para pruebas controladas.');
        }

        $lines = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $purchaseOrderId)
            ->orderBy('id')
            ->get();

        $summary = $this->emptySummary('purchase_order', $purchaseOrderId, $dryRun);

        foreach ($lines as $line) {
            try {
                $row = $this->buildPurchaseLinePayload($order, $line, $force);

                if (! $row['processable']) {
                    $summary['skipped'][] = [
                        'line_id' => $line->id,
                        'reason' => $row['reason'],
                    ];
                    continue;
                }

                if ($dryRun) {
                    $summary['dry_run'][] = $row['payload'];
                    continue;
                }

                $entry = app(InventoryAccountingPoster::class)->post($row['payload'], null);

                $summary['posted'][] = [
                    'line_id' => $line->id,
                    'entry_id' => $entry->id,
                    'entry_number' => $entry->entry_number,
                    'amount' => $entry->total_debit,
                ];
            } catch (Throwable $e) {
                $this->markLineError('purchase_order_lines', (int) $line->id, $e->getMessage());

                $summary['errors'][] = [
                    'line_id' => $line->id,
                    'message' => $e->getMessage(),
                ];
            }
        }

        if (! $dryRun) {
            $this->refreshPurchaseOrderAccountingStatus($purchaseOrderId);
        }

        return $summary;
    }

    public function postSalesOrderCost(int $salesOrderId, bool $dryRun = false, bool $force = false): array
    {
        $this->assertTable('sales_orders');
        $this->assertTable('sales_order_lines');

        $order = DB::table('sales_orders')->where('id', $salesOrderId)->first();

        if (! $order) {
            throw new RuntimeException('No existe sales_order ID ' . $salesOrderId);
        }

        if (! $force && ! in_array((string) $order->status, ['delivered', 'done', 'completed'], true)) {
            throw new RuntimeException('La venta no está entregada. Estado actual: ' . ($order->status ?? 'N/A') . '. Usa --force solo para pruebas controladas.');
        }

        $lines = DB::table('sales_order_lines')
            ->where('sales_order_id', $salesOrderId)
            ->orderBy('id')
            ->get();

        $summary = $this->emptySummary('sales_order', $salesOrderId, $dryRun);

        foreach ($lines as $line) {
            try {
                $row = $this->buildSalesLinePayload($order, $line, $force);

                if (! $row['processable']) {
                    $summary['skipped'][] = [
                        'line_id' => $line->id,
                        'reason' => $row['reason'],
                    ];
                    continue;
                }

                if ($dryRun) {
                    $summary['dry_run'][] = $row['payload'];
                    continue;
                }

                $entry = app(InventoryAccountingPoster::class)->post($row['payload'], null);

                $summary['posted'][] = [
                    'line_id' => $line->id,
                    'entry_id' => $entry->id,
                    'entry_number' => $entry->entry_number,
                    'amount' => $entry->total_debit,
                ];
            } catch (Throwable $e) {
                $this->markLineError('sales_order_lines', (int) $line->id, $e->getMessage());

                $summary['errors'][] = [
                    'line_id' => $line->id,
                    'message' => $e->getMessage(),
                ];
            }
        }

        if (! $dryRun) {
            $this->refreshSalesOrderAccountingStatus($salesOrderId);
        }

        return $summary;
    }

    private function buildPurchaseLinePayload(object $order, object $line, bool $force): array
    {
        if ($this->lineAlreadyPosted($line) && ! $force) {
            return $this->notProcessable('Línea ya contabilizada.');
        }

        $companyId = (int) ($line->company_id ?: $order->company_id ?: 0);

        if ($companyId <= 0) {
            return $this->notProcessable('Sin company_id válido.');
        }

        $productId = (int) ($line->product_variant_id ?: $line->product_id ?: 0);

        if ($productId <= 0) {
            return $this->notProcessable('Sin producto asignado.');
        }

        $quantity = $this->firstPositive([
            $line->received_base_quantity ?? 0,
            $line->received_quantity ?? 0,
        ]);

        if ($quantity <= 0 && $force) {
            $quantity = $this->firstPositive([
                $line->base_quantity ?? 0,
                $line->ordered_quantity ?? 0,
            ]);
        }

        if ($quantity <= 0) {
            return $this->notProcessable('Cantidad recibida en cero.');
        }

        $unitCost = (float) ($line->unit_cost_without_tax ?? 0);
        $amount = round($quantity * $unitCost, 6);

        if ($amount <= 0) {
            $lineTotal = (float) ($line->line_total_without_tax ?? 0);

            if ($lineTotal > 0) {
                $amount = round($lineTotal, 6);
                $unitCost = round($amount / $quantity, 6);
            }
        }

        if ($amount <= 0 || $unitCost <= 0) {
            return $this->notProcessable('Costo de compra en cero.');
        }

        $label = 'Entrada compra ' . ($order->number ?? ('OC #' . $order->id)) . ' / Línea #' . $line->id;

        return [
            'processable' => true,
            'payload' => [
                'company_id' => $companyId,
                'operation_type' => 'purchase_receipt',
                'amount' => $amount,
                'source_type' => 'purchase_order_lines',
                'source_id' => (int) $line->id,
                'source_line_id' => (int) $line->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'currency' => (string) ($order->currency ?? 'MXN'),
                'movement_date' => $this->firstDate([
                    $line->last_received_at ?? null,
                    $order->confirmed_at ?? null,
                    $order->order_date ?? null,
                    $line->updated_at ?? null,
                ]),
                'label' => $label,
            ],
        ];
    }

    private function buildSalesLinePayload(object $order, object $line, bool $force): array
    {
        if ($this->lineAlreadyPosted($line) && ! $force) {
            return $this->notProcessable('Línea ya contabilizada.');
        }

        $companyId = (int) ($line->company_id ?: $order->company_id ?: 0);

        if ($companyId <= 0) {
            return $this->notProcessable('Sin company_id válido.');
        }

        $productId = (int) ($line->product_variant_id ?: $line->product_id ?: 0);

        if ($productId <= 0) {
            return $this->notProcessable('Sin producto asignado.');
        }

        $quantity = $this->firstPositive([
            $line->delivered_quantity ?? 0,
        ]);

        if ($quantity <= 0 && $force) {
            $quantity = $this->firstPositive([
                $line->quantity ?? 0,
            ]);
        }

        if ($quantity <= 0) {
            return $this->notProcessable('Cantidad entregada en cero.');
        }

        $unitCost = $this->resolveSalesUnitCost($line, $productId);
        $amount = round($quantity * $unitCost, 6);

        if ($amount <= 0 || $unitCost <= 0) {
            return $this->notProcessable('Costo estimado en cero.');
        }

        $label = 'Costo venta ' . ($order->number ?? ('Venta #' . $order->id)) . ' / Línea #' . $line->id;

        return [
            'processable' => true,
            'payload' => [
                'company_id' => $companyId,
                'operation_type' => 'sale_issue',
                'amount' => $amount,
                'source_type' => 'sales_order_lines',
                'source_id' => (int) $line->id,
                'source_line_id' => (int) $line->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'currency' => (string) ($order->currency ?? 'MXN'),
                'movement_date' => $this->firstDate([
                    $order->confirmed_at ?? null,
                    $order->order_date ?? null,
                    $line->updated_at ?? null,
                ]),
                'label' => $label,
            ],
        ];
    }

    private function resolveSalesUnitCost(object $line, int $productId): float
    {
        $estimated = (float) ($line->estimated_unit_cost_without_tax ?? 0);

        if ($estimated > 0) {
            return round($estimated, 6);
        }

        if (! Schema::hasTable('products')) {
            return 0.0;
        }

        $product = DB::table('products')->where('id', $productId)->first();

        if (! $product) {
            return 0.0;
        }

        foreach ([
            'average_cost_without_tax',
            'standard_cost',
            'purchase_price',
            'last_purchase_cost',
        ] as $field) {
            if (property_exists($product, $field) && (float) $product->{$field} > 0) {
                return round((float) $product->{$field}, 6);
            }
        }

        return 0.0;
    }

    private function lineAlreadyPosted(object $line): bool
    {
        if (($line->accounting_entry_id ?? null)) {
            return true;
        }

        return in_array((string) ($line->accounting_status ?? ''), ['posted', 'done'], true);
    }

    private function markLineError(string $table, int $id, string $message): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $updates = [];

        if (Schema::hasColumn($table, 'accounting_status')) {
            $updates['accounting_status'] = 'error';
        }

        if (Schema::hasColumn($table, 'accounting_error_message')) {
            $updates['accounting_error_message'] = mb_substr($message, 0, 5000);
        }

        if ($updates) {
            DB::table($table)->where('id', $id)->update($updates);
        }
    }

    private function refreshPurchaseOrderAccountingStatus(int $purchaseOrderId): void
    {
        if (! Schema::hasTable('purchase_orders') || ! Schema::hasTable('purchase_order_lines')) {
            return;
        }

        $eligible = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $purchaseOrderId)
            ->whereNotNull('product_id')
            ->where(function ($query) {
                $query->where('received_base_quantity', '>', 0)
                    ->orWhere('received_quantity', '>', 0);
            });

        $total = (clone $eligible)->count();

        if ($total <= 0) {
            return;
        }

        $pending = (clone $eligible)
            ->where(function ($query) {
                $query->whereNull('accounting_entry_id')
                    ->where('accounting_status', '!=', 'posted');
            })
            ->count();

        $this->updateParentStatus('purchase_orders', $purchaseOrderId, $pending === 0 ? 'posted' : 'partial');
    }

    private function refreshSalesOrderAccountingStatus(int $salesOrderId): void
    {
        if (! Schema::hasTable('sales_orders') || ! Schema::hasTable('sales_order_lines')) {
            return;
        }

        $eligible = DB::table('sales_order_lines')
            ->where('sales_order_id', $salesOrderId)
            ->whereNotNull('product_id')
            ->where('delivered_quantity', '>', 0);

        $total = (clone $eligible)->count();

        if ($total <= 0) {
            return;
        }

        $pending = (clone $eligible)
            ->where(function ($query) {
                $query->whereNull('accounting_entry_id')
                    ->where('accounting_status', '!=', 'posted');
            })
            ->count();

        $this->updateParentStatus('sales_orders', $salesOrderId, $pending === 0 ? 'posted' : 'partial');
    }

    private function updateParentStatus(string $table, int $id, string $status): void
    {
        $updates = [];

        if (Schema::hasColumn($table, 'accounting_status')) {
            $updates['accounting_status'] = $status;
        }

        if ($status === 'posted' && Schema::hasColumn($table, 'accounting_posted_at')) {
            $updates['accounting_posted_at'] = now();
        }

        if (Schema::hasColumn($table, 'accounting_error_message')) {
            $updates['accounting_error_message'] = null;
        }

        if ($updates) {
            DB::table($table)->where('id', $id)->update($updates);
        }
    }

    private function firstPositive(array $values): float
    {
        foreach ($values as $value) {
            $number = (float) $value;

            if ($number > 0) {
                return round($number, 6);
            }
        }

        return 0.0;
    }

    private function firstDate(array $values): ?string
    {
        foreach ($values as $value) {
            if ($value) {
                return (string) $value;
            }
        }

        return null;
    }

    private function assertTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            throw new RuntimeException('No existe la tabla ' . $table);
        }
    }

    private function notProcessable(string $reason): array
    {
        return [
            'processable' => false,
            'reason' => $reason,
        ];
    }

    private function emptySummary(string $sourceType, int $sourceId, bool $dryRun): array
    {
        return [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'dry_run_mode' => $dryRun,
            'dry_run' => [],
            'posted' => [],
            'skipped' => [],
            'errors' => [],
        ];
    }
}
