<?php

namespace App\Support\Sales;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class QuotePosTicketSyncService
{
    public function markPaidFromPosOrder(int $posOrderId): bool
    {
        $ok = $this->markPaidOnlyFromPosOrder($posOrderId);

        if (! $ok) {
            return false;
        }

        // V5.61.4e: si se llama desde otro punto, tambien intentar salida.
        // PosInventoryPoster debe ser idempotente.
        try {
            if (class_exists(\App\Support\PosInventoryPoster::class)) {
                app(\App\Support\PosInventoryPoster::class)
                    ->postPaidOrder($posOrderId);
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo postear inventario del ticket de cotizacion PDV.', [
                'pos_order_id' => $posOrderId,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    public function markPaidOnlyFromPosOrder(int $posOrderId): bool
    {
        if ($posOrderId <= 0) {
            return false;
        }

        if (! Schema::hasTable('pos_orders') || ! Schema::hasTable('sales_quote_pos_tickets')) {
            return false;
        }

        $posOrder = DB::table('pos_orders')->where('id', $posOrderId)->first();

        if (! $posOrder) {
            return false;
        }

        $bridge = DB::table('sales_quote_pos_tickets')
            ->where('pos_order_id', $posOrderId)
            ->orderByDesc('id')
            ->first();

        if (! $bridge) {
            return false;
        }

        if (! $this->posOrderIsPaid($posOrder)) {
            return false;
        }

        $paidAt = $posOrder->paid_at ?? now();

        DB::transaction(function () use ($bridge, $posOrder, $paidAt): void {
            $now = now();

            DB::table('sales_quote_pos_tickets')
                ->where('id', (int) $bridge->id)
                ->update([
                    'status' => 'paid',
                    'paid_at' => $paidAt,
                    'updated_at' => $now,
                ]);

            if (! Schema::hasTable('sales_orders')) {
                return;
            }

            $quoteUpdate = [
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('sales_orders', 'payment_status')) {
                $quoteUpdate['payment_status'] = 'paid';
            }

            if (Schema::hasColumn('sales_orders', 'quote_pos_payment_status')) {
                $quoteUpdate['quote_pos_payment_status'] = 'paid';
            }

            if (Schema::hasColumn('sales_orders', 'quote_pos_paid_at')) {
                $quoteUpdate['quote_pos_paid_at'] = $paidAt;
            }

            if (Schema::hasColumn('sales_orders', 'quote_pos_order_id')) {
                $quoteUpdate['quote_pos_order_id'] = (int) $posOrder->id;
            }

            if (Schema::hasColumn('sales_orders', 'quote_validation_message')) {
                $quoteUpdate['quote_validation_message'] = 'Cobrado en PDV mediante ticket ' . (string) ($posOrder->number ?? $posOrder->id) . '.';
            }

            DB::table('sales_orders')
                ->where('id', (int) $bridge->sales_order_id)
                ->update($quoteUpdate);
        });

        return true;
    }

    public function posOrderIsPaid(object $posOrder): bool
    {
        $status = Str::lower((string) ($posOrder->status ?? ''));

        if (in_array($status, ['paid', 'completed', 'done', 'closed'], true)) {
            return true;
        }

        if (! empty($posOrder->paid_at)) {
            return true;
        }

        $paymentStatus = Str::lower((string) ($posOrder->payment_status ?? ''));

        if (in_array($paymentStatus, ['paid', 'completed'], true)) {
            return true;
        }

        $total = $this->floatValue($posOrder->total ?? null);
        $paidTotal = $this->floatValue($posOrder->paid_total ?? null);

        if ($total > 0 && $paidTotal >= ($total - 0.01)) {
            return true;
        }

        return $this->paymentsCoverTotal((int) ($posOrder->id ?? 0), $total);
    }

    protected function paymentsCoverTotal(int $posOrderId, float $total): bool
    {
        if ($posOrderId <= 0 || $total <= 0) {
            return false;
        }

        foreach (['pos_order_payments', 'pos_payments'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);

            $orderColumn = collect(['pos_order_id', 'order_id'])
                ->first(fn (string $column): bool => in_array($column, $columns, true));

            $amountColumn = collect(['amount', 'payment_amount', 'paid_amount', 'total', 'value'])
                ->first(fn (string $column): bool => in_array($column, $columns, true));

            if (! $orderColumn || ! $amountColumn) {
                continue;
            }

            $paid = (float) DB::table($table)
                ->where($orderColumn, $posOrderId)
                ->sum($amountColumn);

            if ($paid >= ($total - 0.01)) {
                return true;
            }
        }

        return false;
    }

    protected function floatValue(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 4);
    }
}
