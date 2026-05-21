<?php

namespace App\Support\Cxc;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AccountReceivableFromInvoiceService
{
    public function createFromInvoice(int $invoiceId, ?int $userId = null): ?int
    {
        if ($invoiceId <= 0) {
            return null;
        }

        $this->assertRequiredTables();

        return DB::transaction(function () use ($invoiceId, $userId): ?int {
            $invoice = DB::table('invoices')
                ->where('id', $invoiceId)
                ->lockForUpdate()
                ->first();

            if (! $invoice) {
                throw new RuntimeException('No se encontró la factura para crear CxC.');
            }

            $companyId = (int) ($invoice->company_id ?? 0);
            $total = round((float) ($invoice->total ?? 0), 4);
            $paid = round((float) ($invoice->paid_total ?? 0), 4);
            $balance = round((float) ($invoice->balance_total ?? max($total - $paid, 0)), 4);
            $metadata = $this->metadataArray($invoice->metadata ?? null);
            $saleOrderId = $this->saleOrderId($invoice, $metadata);

            if ($companyId <= 0 || $total <= 0) {
                return null;
            }

            $existingId = $this->existingReceivableId($companyId, $invoiceId, $saleOrderId);
            $existing = $existingId
                ? DB::table('account_receivables')->where('id', $existingId)->first()
                : null;

            $wasSalesOrderReceivable = $existing
                && (string) ($existing->source_type ?? '') === 'sales_order';

            if ($this->isInvoiceCancelled($invoice)) {
                if ($existingId) {
                    DB::table('account_receivables')
                        ->where('id', $existingId)
                        ->update([
                            'status' => 'cancelled',
                            'invoice_id' => $invoiceId,
                            'sale_order_id' => $saleOrderId ?: ($existing->sale_order_id ?? null),
                            'accounting_status' => 'cancelled',
                            'accounting_error_message' => null,
                            'updated_at' => now(),
                        ]);
                }

                return $existingId;
            }

            if ($balance <= 0.009) {
                if ($existingId) {
                    DB::table('account_receivables')
                        ->where('id', $existingId)
                        ->update([
                            'status' => 'paid',
                            'invoice_id' => $invoiceId,
                            'sale_order_id' => $saleOrderId ?: ($existing->sale_order_id ?? null),
                            'collected_total' => $paid,
                            'balance_total' => 0,
                            'updated_at' => now(),
                        ]);

                    if ($wasSalesOrderReceivable) {
                        app(\App\Support\Accounting\AccountReceivableInvoiceReclassificationPoster::class)
                            ->reclassify((int) $existingId, (int) $invoiceId, $userId);
                    }
                }

                return $existingId;
            }

            if (! $this->isInvoiceIssuedEnough($invoice)) {
                return null;
            }

            $status = $paid > 0 ? 'partial' : 'open';
            $issueDate = $this->dateOrToday($invoice->invoice_date ?? null);
            $dueDate = $this->dueDate($invoice, $issueDate);

            $payload = [
                'company_id' => $companyId,
                'number' => $this->nextReceivableNumber($companyId, (string) ($invoice->number ?? ('INV-' . $invoiceId))),
                'status' => $status,
                'source_type' => 'invoice',
                'source_id' => $invoiceId,
                'sale_order_id' => $saleOrderId ?: ($existing->sale_order_id ?? null),
                'invoice_id' => $invoiceId,
                'customer_contact_id' => $invoice->contact_id ?? ($existing->customer_contact_id ?? null),
                'customer_name' => $this->customerName($invoice),
                'customer_reference' => $this->customerReference($invoice),
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'currency' => $invoice->currency_code ?: 'MXN',
                'subtotal' => round((float) ($invoice->subtotal ?? 0), 4),
                'tax_total' => round((float) ($invoice->tax_total ?? 0), 4),
                'total' => $total,
                'collected_total' => $paid,
                'balance_total' => $balance,
                'accounting_status' => 'pending',
                'accounting_entry_id' => null,
                'accounting_posted_at' => null,
                'accounting_error_message' => null,
                'notes' => 'CxC generada/actualizada automáticamente desde factura ' . ($invoice->number ?? ('#' . $invoiceId)),
                'metadata' => json_encode([
                    'created_by' => 'AccountReceivableFromInvoiceService',
                    'version' => 'v5.57.3c',
                    'invoice_number' => $invoice->number ?? null,
                    'invoice_status' => $invoice->status ?? null,
                    'cfdi_status' => $invoice->cfdi_status ?? null,
                    'cfdi_uuid' => $invoice->cfdi_uuid ?? null,
                    'source_type' => $invoice->source_type ?? null,
                    'source_id' => $invoice->source_id ?? null,
                    'source_number' => $invoice->source_number ?? null,
                    'sale_order_id_detected' => $saleOrderId,
                    'dedupe_rule' => 'invoice_or_sale_order',
                    'reclassification_rule' => $wasSalesOrderReceivable ? 'sales_order_bridge_to_invoice' : 'invoice_direct',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_by_user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($existingId) {
                unset(
                    $payload['company_id'],
                    $payload['number'],
                    $payload['source_type'],
                    $payload['source_id'],
                    $payload['created_at']
                );

                if ($wasSalesOrderReceivable) {
                    unset(
                        $payload['accounting_status'],
                        $payload['accounting_entry_id'],
                        $payload['accounting_posted_at']
                    );
                }

                DB::table('account_receivables')
                    ->where('id', $existingId)
                    ->update($payload);

                if ($wasSalesOrderReceivable) {
                    app(\App\Support\Accounting\AccountReceivableInvoiceReclassificationPoster::class)
                        ->reclassify((int) $existingId, (int) $invoiceId, $userId);
                } else {
                    app(\App\Support\Accounting\AccountReceivableAccountingPoster::class)
                        ->postReceivable((int) $existingId, $userId);
                }

                return $existingId;
            }

            $newId = (int) DB::table('account_receivables')->insertGetId($payload);

            app(\App\Support\Accounting\AccountReceivableAccountingPoster::class)
                ->postReceivable($newId, $userId);

            return $newId;
        });
    }

    protected function isInvoiceCancelled(object $invoice): bool
    {
        return in_array((string) ($invoice->status ?? ''), ['cancelled', 'canceled', 'cancelado'], true)
            || in_array((string) ($invoice->cfdi_status ?? ''), ['cancelled', 'canceled', 'cancelado', 'cancelled_internal'], true);
    }

    protected function isInvoiceIssuedEnough(object $invoice): bool
    {
        return in_array((string) ($invoice->status ?? ''), ['issued'], true)
            || in_array((string) ($invoice->cfdi_status ?? ''), ['stamped'], true);
    }

    protected function existingReceivableId(int $companyId, int $invoiceId, ?int $saleOrderId = null): ?int
    {
        $query = DB::table('account_receivables')
            ->where('company_id', $companyId)
            ->where(function ($query) use ($invoiceId, $saleOrderId): void {
                $query->where(function ($sub) use ($invoiceId): void {
                    $sub->where('source_type', 'invoice')
                        ->where('source_id', $invoiceId);
                })
                    ->orWhere('invoice_id', $invoiceId);

                if ($saleOrderId && $saleOrderId > 0) {
                    $query->orWhere('sale_order_id', $saleOrderId)
                        ->orWhere(function ($sub) use ($saleOrderId): void {
                            $sub->where('source_type', 'sales_order')
                                ->where('source_id', $saleOrderId);
                        });
                }
            })
            ->orderByRaw("
                case
                    when invoice_id = ? then 0
                    when source_type = 'invoice' and source_id = ? then 1
                    when sale_order_id = ? then 2
                    else 3
                end
            ", [$invoiceId, $invoiceId, $saleOrderId ?: 0])
            ->orderBy('id');

        $existing = $query->value('id');

        return $existing ? (int) $existing : null;
    }

    protected function nextReceivableNumber(int $companyId, string $invoiceNumber): string
    {
        $cleanInvoice = trim(preg_replace('/[^A-Za-z0-9\-]+/', '-', $invoiceNumber), '-');

        if ($cleanInvoice === '') {
            $cleanInvoice = now()->format('YmdHis');
        }

        $base = 'CXC-' . $cleanInvoice;
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

    protected function dateOrToday($date): string
    {
        return $date
            ? Carbon::parse($date)->toDateString()
            : now()->toDateString();
    }

    protected function dueDate(object $invoice, string $issueDate): string
    {
        if (! empty($invoice->due_date)) {
            return Carbon::parse($invoice->due_date)->toDateString();
        }

        $terms = trim((string) ($invoice->payment_terms ?? ''));
        $days = 30;

        if (preg_match('/(\d+)/', $terms, $matches)) {
            $days = max(0, (int) $matches[1]);
        }

        if ((string) ($invoice->payment_method_code ?? '') === 'PUE') {
            $days = 0;
        }

        return Carbon::parse($issueDate)->addDays($days)->toDateString();
    }

    protected function customerName(object $invoice): string
    {
        foreach (['customer_name', 'customer_fiscal_name'] as $field) {
            $value = trim((string) ($invoice->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return 'Cliente sin nombre';
    }

    protected function customerReference(object $invoice): ?string
    {
        foreach (['cfdi_uuid', 'cfdi_number_display', 'source_number', 'number'] as $field) {
            $value = trim((string) ($invoice->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function metadataArray($metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && trim($metadata) !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function saleOrderId(object $invoice, array $metadata): ?int
    {
        if ((string) ($invoice->source_type ?? '') === 'sales_order' && ! empty($invoice->source_id)) {
            return (int) $invoice->source_id;
        }

        foreach (['sales_order_id', 'sale_order_id'] as $key) {
            if (! empty($metadata[$key])) {
                return (int) $metadata[$key];
            }
        }

        return null;
    }

    protected function assertRequiredTables(): void
    {
        foreach (['invoices', 'account_receivables'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('No existe la tabla requerida: ' . $table);
            }
        }
    }
}
