<?php

namespace App\Support\Cxp;

use App\Support\Accounting\AccountPayableAccountingPoster;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class AccountPayableFromPurchaseReceiptService
{
    public function createFromReceipt(int $purchaseReceiptId, ?int $userId = null): ?int
    {
        if ($purchaseReceiptId <= 0) {
            return null;
        }

        $this->assertRequiredTables();

        return DB::transaction(function () use ($purchaseReceiptId, $userId): ?int {
            $receipt = DB::table('purchase_receipts')
                ->where('id', $purchaseReceiptId)
                ->lockForUpdate()
                ->first();

            if (! $receipt) {
                throw new RuntimeException('No se encontró la recepción de compra para crear CxP.');
            }

            if ((string) ($receipt->status ?? '') !== 'received') {
                return null;
            }

            $companyId = (int) ($receipt->company_id ?? 0);
            $total = round((float) ($receipt->total_with_tax ?? 0), 4);

            if ($companyId <= 0 || $total <= 0) {
                return null;
            }

            $existingId = $this->existingPayableId($companyId, $purchaseReceiptId);

            if ($existingId) {
                $this->tryPostPayable($existingId, $userId);

                return $existingId;
            }

            $order = null;

            if (! empty($receipt->purchase_order_id)) {
                $order = DB::table('purchase_orders')
                    ->where('id', $receipt->purchase_order_id)
                    ->first();
            }

            $settings = Schema::hasTable('account_payable_settings')
                ? DB::table('account_payable_settings')->where('company_id', $companyId)->first()
                : null;

            $dueDays = max(0, (int) ($settings->default_due_days ?? 30));

            $issueDate = $receipt->received_at
                ? Carbon::parse($receipt->received_at)->toDateString()
                : Carbon::parse($receipt->created_at ?? now())->toDateString();

            $dueDate = Carbon::parse($issueDate)->addDays($dueDays)->toDateString();

            $supplierName = $order?->supplier_name
                ?: $order?->xml_supplier_name
                ?: 'Proveedor sin nombre';

            $number = $this->nextPayableNumber($companyId, (string) $receipt->number);

            $payableId = (int) DB::table('account_payables')->insertGetId([
                'company_id' => $companyId,
                'number' => $number,
                'status' => 'open',
                'source_type' => 'purchase_receipt',
                'source_id' => $purchaseReceiptId,
                'purchase_order_id' => $receipt->purchase_order_id ?? null,
                'purchase_receipt_id' => $purchaseReceiptId,
                'supplier_contact_id' => $order?->supplier_contact_id,
                'supplier_name' => $supplierName,
                'supplier_reference' => $order?->xml_uuid ?: $receipt->number,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'currency' => $order?->currency ?: 'MXN',
                'subtotal' => round((float) ($receipt->total_without_tax ?? 0), 4),
                'tax_total' => round((float) ($receipt->total_tax ?? 0), 4),
                'total' => $total,
                'paid_total' => 0,
                'balance_total' => $total,
                'accounting_status' => 'pending',
                'accounting_entry_id' => null,
                'accounting_posted_at' => null,
                'accounting_error_message' => null,
                'notes' => 'CxP generada automáticamente desde recepción de compra ' . $receipt->number,
                'metadata' => json_encode([
                    'created_by' => 'AccountPayableFromPurchaseReceiptService',
                    'version' => 'v5.56.10',
                    'source' => 'purchase_receipts',
                    'receipt_number' => $receipt->number,
                    'purchase_order_number' => $order?->number,
                    'xml_supplier_rfc' => $order?->xml_supplier_rfc,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_by_user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->tryPostPayable($payableId, $userId);

            return $payableId;
        });
    }

    protected function tryPostPayable(int $payableId, ?int $userId = null): void
    {
        try {
            app(AccountPayableAccountingPoster::class)->postPayable($payableId, $userId);
        } catch (Throwable $e) {
            DB::table('account_payables')
                ->where('id', $payableId)
                ->update([
                    'accounting_status' => 'error',
                    'accounting_error_message' => $e->getMessage(),
                    'updated_at' => now(),
                ]);
        }
    }

    protected function existingPayableId(int $companyId, int $purchaseReceiptId): ?int
    {
        $existing = DB::table('account_payables')
            ->where('company_id', $companyId)
            ->where(function ($query) use ($purchaseReceiptId): void {
                $query->where(function ($sub) use ($purchaseReceiptId): void {
                    $sub->where('source_type', 'purchase_receipt')
                        ->where('source_id', $purchaseReceiptId);
                })
                    ->orWhere('purchase_receipt_id', $purchaseReceiptId);
            })
            ->value('id');

        return $existing ? (int) $existing : null;
    }

    protected function nextPayableNumber(int $companyId, string $receiptNumber): string
    {
        $cleanReceipt = trim(preg_replace('/[^A-Za-z0-9\-]+/', '-', $receiptNumber), '-');

        if ($cleanReceipt === '') {
            $cleanReceipt = now()->format('YmdHis');
        }

        $base = 'CXP-' . $cleanReceipt;
        $number = $base;
        $i = 2;

        while (DB::table('account_payables')
            ->where('company_id', $companyId)
            ->where('number', $number)
            ->exists()
        ) {
            $number = $base . '-' . $i;
            $i++;
        }

        return $number;
    }

    protected function assertRequiredTables(): void
    {
        foreach (['purchase_receipts', 'purchase_orders', 'account_payables'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('No existe la tabla requerida: ' . $table);
            }
        }
    }
}
