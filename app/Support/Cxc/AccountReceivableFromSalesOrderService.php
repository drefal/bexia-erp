<?php

namespace App\Support\Cxc;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AccountReceivableFromSalesOrderService
{
    public function createFromSalesOrder(int $salesOrderId, ?int $userId = null): ?int
    {
        if ($salesOrderId <= 0) {
            return null;
        }

        $this->assertRequiredTables();

        return DB::transaction(function () use ($salesOrderId, $userId): ?int {
            $order = DB::table('sales_orders')
                ->where('id', $salesOrderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new RuntimeException('No se encontró la venta para crear CxC.');
            }

            $companyId = (int) ($order->company_id ?? 0);
            $total = round((float) ($order->total_with_tax ?? 0), 4);

            if ($companyId <= 0 || $total <= 0) {
                return null;
            }

            $existingId = $this->existingReceivableId($companyId, $salesOrderId);

            if (! $this->shouldCreateReceivable($order)) {
                return $existingId;
            }

            if ($existingId) {
                return $existingId;
            }

            $issueDate = $order->order_date
                ? Carbon::parse($order->order_date)->toDateString()
                : now()->toDateString();

            $dueDate = $this->dueDate($order, $issueDate);

            $accountingStatus = 'pending';
            $accountingEntryId = null;
            $accountingPostedAt = null;

            $receivableId = (int) DB::table('account_receivables')->insertGetId([
                'company_id' => $companyId,
                'number' => $this->nextReceivableNumber($companyId, (string) ($order->number ?? ('SO-' . $salesOrderId))),
                'status' => 'open',
                'source_type' => 'sales_order',
                'source_id' => $salesOrderId,
                'sale_order_id' => $salesOrderId,
                'invoice_id' => null,
                'customer_contact_id' => $order->customer_contact_id ?? null,
                'customer_name' => $this->customerName($order),
                'customer_reference' => $order->number ?? null,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'currency' => $order->currency ?: 'MXN',
                'subtotal' => round((float) ($order->total_without_tax ?? 0), 4),
                'tax_total' => round((float) ($order->total_tax ?? 0), 4),
                'total' => $total,
                'collected_total' => 0,
                'balance_total' => $total,
                'accounting_status' => $accountingStatus,
                'accounting_entry_id' => $accountingEntryId,
                'accounting_posted_at' => $accountingPostedAt,
                'accounting_error_message' => null,
                'notes' => 'CxC generada automáticamente desde venta entregada ' . ($order->number ?? ('#' . $salesOrderId)),
                'metadata' => json_encode([
                    'created_by' => 'AccountReceivableFromSalesOrderService',
                    'version' => 'v5.57.2b',
                    'sales_order_number' => $order->number ?? null,
                    'sales_order_status' => $order->status ?? null,
                    'invoice_status' => $order->invoice_status ?? null,
                    'payment_status' => $order->payment_status ?? null,
                    'delivered_total_quantity' => $order->delivered_total_quantity ?? null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_by_user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                app(\App\Support\Accounting\AccountReceivableAccountingPoster::class)
                    ->postReceivable($receivableId, $userId);
            } catch (\Throwable $e) {
                DB::table('account_receivables')
                    ->where('id', $receivableId)
                    ->update([
                        'accounting_status' => 'error',
                        'accounting_error_message' => $e->getMessage(),
                        'updated_at' => now(),
                    ]);
            }

            return $receivableId;
        });
    }

    protected function shouldCreateReceivable(object $order): bool
    {
        $status = (string) ($order->status ?? '');
        $invoiceStatus = (string) ($order->invoice_status ?? '');
        $paymentStatus = (string) ($order->payment_status ?? '');

        if (! in_array($status, ['delivered'], true)) {
            return false;
        }

        if (in_array($invoiceStatus, ['invoiced', 'fully_invoiced'], true)) {
            return false;
        }

        if (! in_array($paymentStatus, ['unpaid', '', 'pending'], true)) {
            return false;
        }

        if ((float) ($order->total_with_tax ?? 0) <= 0) {
            return false;
        }

        return true;
    }

    protected function existingReceivableId(int $companyId, int $salesOrderId): ?int
    {
        $existing = DB::table('account_receivables')
            ->where('company_id', $companyId)
            ->where(function ($query) use ($salesOrderId): void {
                $query->where(function ($sub) use ($salesOrderId): void {
                    $sub->where('source_type', 'sales_order')
                        ->where('source_id', $salesOrderId);
                })
                    ->orWhere('sale_order_id', $salesOrderId);
            })
            ->value('id');

        return $existing ? (int) $existing : null;
    }

    protected function nextReceivableNumber(int $companyId, string $salesOrderNumber): string
    {
        $clean = trim(preg_replace('/[^A-Za-z0-9\-]+/', '-', $salesOrderNumber), '-');

        if ($clean === '') {
            $clean = now()->format('YmdHis');
        }

        $base = 'CXC-' . $clean;
        $number = $base;
        $i = 2;

        while (DB::table('account_receivables')
            ->where('company_id', $companyId)
            ->where('number', $number)
            ->exists()
        ) {
            $number = $base . '-' . $i;
            $i++;
        }

        return $number;
    }

    protected function dueDate(object $order, string $issueDate): string
    {
        $terms = trim((string) ($order->payment_terms ?? ''));
        $days = 30;

        if (preg_match('/(\d+)/', $terms, $matches)) {
            $days = max(0, (int) $matches[1]);
        }

        return Carbon::parse($issueDate)->addDays($days)->toDateString();
    }

    protected function customerName(object $order): string
    {
        $name = trim((string) ($order->customer_name ?? ''));

        return $name !== '' ? $name : 'Cliente sin nombre';
    }

    protected function assertRequiredTables(): void
    {
        foreach (['sales_orders', 'account_receivables'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('No existe la tabla requerida: ' . $table);
            }
        }
    }
}
