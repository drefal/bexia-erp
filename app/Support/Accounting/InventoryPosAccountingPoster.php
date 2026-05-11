<?php

namespace App\Support\Accounting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class InventoryPosAccountingPoster
{
    public function postPosOrderCost(int $posOrderId, bool $dryRun = false, bool $force = false): array
    {
        $this->assertTable('pos_orders');
        $this->assertTable('pos_order_lines');

        $order = DB::table('pos_orders')->where('id', $posOrderId)->first();

        if (! $order) {
            throw new RuntimeException('No existe pos_order ID ' . $posOrderId);
        }

        $status = (string) ($order->status ?? '');

        if (! $force && ! in_array($status, ['paid', 'returned', 'partial_refunded', 'partially_refunded'], true)) {
            throw new RuntimeException('El ticket POS no está pagado/devuelto. Estado actual: ' . ($status ?: 'N/A') . '. Usa --force solo para pruebas controladas.');
        }

        $lines = DB::table('pos_order_lines')
            ->where('pos_order_id', $posOrderId)
            ->orderBy('id')
            ->get();

        $summary = $this->emptySummary('pos_order', $posOrderId, $dryRun);

        foreach ($lines as $line) {
            try {
                $row = $this->buildPosLinePayload($order, $line);

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
                $this->markLineError('pos_order_lines', (int) $line->id, $e->getMessage());

                $summary['errors'][] = [
                    'line_id' => $line->id,
                    'message' => $e->getMessage(),
                ];
            }
        }

        if (! $dryRun) {
            $this->refreshPosOrderAccountingStatus($posOrderId);
        }

        return $summary;
    }

    public function postPosRefundReturn(int $posOrderRefundId, bool $dryRun = false, bool $force = false): array
    {
        $this->assertTable('pos_order_refunds');
        $this->assertTable('pos_order_refund_lines');

        $refund = DB::table('pos_order_refunds')->where('id', $posOrderRefundId)->first();

        if (! $refund) {
            throw new RuntimeException('No existe pos_order_refund ID ' . $posOrderRefundId);
        }

        $status = (string) ($refund->status ?? '');

        if (! $force && ! in_array($status, ['done', 'refunded', 'completed'], true)) {
            throw new RuntimeException('La devolución POS no está finalizada. Estado actual: ' . ($status ?: 'N/A') . '. Usa --force solo para pruebas controladas.');
        }

        $lines = DB::table('pos_order_refund_lines')
            ->where('pos_order_refund_id', $posOrderRefundId)
            ->orderBy('id')
            ->get();

        $summary = $this->emptySummary('pos_order_refund', $posOrderRefundId, $dryRun);

        foreach ($lines as $line) {
            try {
                $row = $this->buildRefundLinePayload($refund, $line);

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
                $this->markLineError('pos_order_refund_lines', (int) $line->id, $e->getMessage());

                $summary['errors'][] = [
                    'line_id' => $line->id,
                    'message' => $e->getMessage(),
                ];
            }
        }

        if (! $dryRun) {
            $this->refreshRefundAccountingStatus($posOrderRefundId);
        }

        return $summary;
    }

    private function buildPosLinePayload(object $order, object $line): array
    {
        if ($this->lineAlreadyPosted($line)) {
            return $this->notProcessable('Línea POS ya contabilizada.');
        }

        $companyId = (int) ($order->company_id ?? 0);

        if ($companyId <= 0) {
            return $this->notProcessable('Ticket POS sin company_id válido.');
        }

        $productId = (int) ($line->product_id ?? 0);

        if ($productId <= 0) {
            return $this->notProcessable('Línea POS sin producto.');
        }

        $quantity = round((float) ($line->quantity ?? 0), 6);

        if ($quantity <= 0) {
            return $this->notProcessable('Cantidad POS en cero.');
        }

        $unitCost = $this->resolveProductUnitCost($companyId, $productId, null);
        $amount = round($quantity * $unitCost, 6);

        if ($unitCost <= 0 || $amount <= 0) {
            return $this->notProcessable('Costo de producto en cero. Producto ID ' . $productId);
        }

        $label = 'Costo POS ' . ($order->number ?? ('POS #' . $order->id)) . ' / Línea #' . $line->id;

        return [
            'processable' => true,
            'payload' => [
                'company_id' => $companyId,
                'operation_type' => 'sale_issue',
                'amount' => $amount,
                'source_type' => 'pos_order_lines',
                'source_id' => (int) $line->id,
                'source_line_id' => (int) $line->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'currency' => (string) ($order->currency_code ?? 'MXN'),
                'movement_date' => $this->firstDate([
                    $order->paid_at ?? null,
                    $order->ordered_at ?? null,
                    $line->created_at ?? null,
                ]),
                'label' => $label,
            ],
        ];
    }

    private function buildRefundLinePayload(object $refund, object $line): array
    {
        if ($this->lineAlreadyPosted($line)) {
            return $this->notProcessable('Línea de devolución POS ya contabilizada.');
        }

        $companyId = (int) ($refund->company_id ?? 0);

        if ($companyId <= 0 && isset($refund->pos_order_id)) {
            $order = DB::table('pos_orders')->where('id', $refund->pos_order_id)->first();
            $companyId = (int) ($order->company_id ?? 0);
        }

        if ($companyId <= 0) {
            return $this->notProcessable('Devolución POS sin company_id válido.');
        }

        $productId = (int) ($line->product_id ?? 0);

        if ($productId <= 0) {
            return $this->notProcessable('Línea de devolución sin producto.');
        }

        $quantity = round((float) ($line->quantity ?? 0), 6);

        if ($quantity <= 0) {
            return $this->notProcessable('Cantidad devuelta en cero.');
        }

        $originalLineId = (int) ($line->pos_order_line_id ?? 0);
        $unitCost = $this->resolveProductUnitCost($companyId, $productId, $originalLineId);
        $amount = round($quantity * $unitCost, 6);

        if ($unitCost <= 0 || $amount <= 0) {
            return $this->notProcessable('Costo de producto en cero para devolución. Producto ID ' . $productId);
        }

        $label = 'Devolución POS ' . ($refund->number ?? ('DEV #' . $refund->id)) . ' / Línea #' . $line->id;

        return [
            'processable' => true,
            'payload' => [
                'company_id' => $companyId,
                'operation_type' => 'customer_return',
                'amount' => $amount,
                'source_type' => 'pos_order_refund_lines',
                'source_id' => (int) $line->id,
                'source_line_id' => (int) $line->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'currency' => 'MXN',
                'movement_date' => $this->firstDate([
                    $refund->refunded_at ?? null,
                    $line->created_at ?? null,
                ]),
                'label' => $label,
            ],
        ];
    }

    private function resolveProductUnitCost(int $companyId, int $productId, ?int $originalPosLineId = null): float
    {
        if ($originalPosLineId) {
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

        if (Schema::hasTable('stock_balances')) {
            $query = DB::table('stock_balances')
                ->where('company_id', $companyId)
                ->where('product_id', $productId);

            if (Schema::hasColumn('stock_balances', 'average_cost_without_tax')) {
                $cost = $query->where('average_cost_without_tax', '>', 0)
                    ->orderByDesc('updated_at')
                    ->value('average_cost_without_tax');

                if ((float) $cost > 0) {
                    return round((float) $cost, 6);
                }
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

    private function refreshPosOrderAccountingStatus(int $posOrderId): void
    {
        $eligible = DB::table('pos_order_lines')
            ->where('pos_order_id', $posOrderId)
            ->whereNotNull('product_id')
            ->where('quantity', '>', 0);

        $total = (clone $eligible)->count();

        if ($total <= 0) {
            $this->updateStatus('pos_orders', $posOrderId, 'not_applicable');
            return;
        }

        $pending = (clone $eligible)
            ->where(function ($query) {
                $query->whereNull('accounting_entry_id')
                    ->orWhere('accounting_status', '!=', 'posted');
            })
            ->count();

        $this->updateStatus('pos_orders', $posOrderId, $pending === 0 ? 'posted' : 'partial');
    }

    private function refreshRefundAccountingStatus(int $refundId): void
    {
        $eligible = DB::table('pos_order_refund_lines')
            ->where('pos_order_refund_id', $refundId)
            ->whereNotNull('product_id')
            ->where('quantity', '>', 0);

        $total = (clone $eligible)->count();

        if ($total <= 0) {
            $this->updateStatus('pos_order_refunds', $refundId, 'not_applicable');
            return;
        }

        $pending = (clone $eligible)
            ->where(function ($query) {
                $query->whereNull('accounting_entry_id')
                    ->orWhere('accounting_status', '!=', 'posted');
            })
            ->count();

        $this->updateStatus('pos_order_refunds', $refundId, $pending === 0 ? 'posted' : 'partial');
    }

    private function updateStatus(string $table, int $id, string $status): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

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

    private function lineAlreadyPosted(object $line): bool
    {
        if (($line->accounting_entry_id ?? null)) {
            return true;
        }

        return in_array((string) ($line->accounting_status ?? ''), ['posted', 'done'], true);
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
