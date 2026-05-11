<?php

namespace App\Support\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingEntry;
use App\Models\AccountingEntryLine;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingAudit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class InvoiceAccountingPoster
{
    public function post(Model $invoice, ?int $userId = null): AccountingEntry
    {
        $companyId = (int) ($invoice->getAttribute('company_id') ?? 0);
        $invoiceId = (int) $invoice->getKey();

        if ($companyId <= 0) {
            throw new RuntimeException('La factura no tiene company_id válido.');
        }

        return DB::transaction(function () use ($invoice, $companyId, $invoiceId, $userId): AccountingEntry {
            try {
                $this->guardInvoiceCanBePosted($invoice);

                $existingEntry = AccountingEntry::query()
                    ->where('company_id', $companyId)
                    ->where('source_type', 'invoice')
                    ->where('source_id', $invoiceId)
                    ->whereIn('status', ['draft', 'posted'])
                    ->first();

                if ($existingEntry) {
                    throw new RuntimeException('Esta factura ya tiene un asiento contable relacionado: ' . $existingEntry->entry_number);
                }

                $journal = $this->resolveJournal($companyId);

                $receivableAccount = $this->resolveAccount(
                    $companyId,
                    'customer_receivable_account_id',
                    ['receivable'],
                    ['105.01']
                );

                $salesAccount = $this->resolveAccount(
                    $companyId,
                    'sales_income_account_id',
                    ['sales_income'],
                    ['401.01']
                );

                $vatAccount = $this->resolveAccount(
                    $companyId,
                    'vat_payable_account_id',
                    ['vat_payable'],
                    ['207.01', '209.01']
                );

                $amounts = $this->resolveInvoiceAmounts($invoice);
                $entryDate = $this->resolveEntryDate($invoice);
                $currency = (string) ($invoice->getAttribute('currency') ?: $invoice->getAttribute('currency_code') ?: 'MXN');
                $entryNumber = $this->buildEntryNumber($journal, $invoice);
                $sourceLabel = $this->buildSourceLabel($invoice);
                $partnerContactId = $this->resolvePartnerContactId($invoice);

                $entry = AccountingEntry::query()->create([
                    'company_id' => $companyId,
                    'journal_id' => $journal?->id,
                    'entry_number' => $entryNumber,
                    'entry_date' => $entryDate->toDateString(),
                    'status' => 'posted',
                    'source_type' => 'invoice',
                    'source_id' => $invoiceId,
                    'source_label' => $sourceLabel,
                    'currency' => $currency,
                    'total_debit' => $amounts['total'],
                    'total_credit' => $amounts['total'],
                    'posted_at' => now(),
                    'created_by_user_id' => $userId,
                    'posted_by_user_id' => $userId,
                    'notes' => 'Asiento generado automáticamente desde factura interna.',
                    'metadata' => [
                        'invoice_id' => $invoiceId,
                        'invoice_status' => $invoice->getAttribute('status'),
                        'cfdi_status' => $invoice->getAttribute('cfdi_status'),
                    ],
                ]);

                $lineNumber = 1;

                $this->createLine(
                    $entry,
                    $receivableAccount,
                    $lineNumber++,
                    'Cliente por cobrar - ' . $sourceLabel,
                    $amounts['total'],
                    0,
                    $currency,
                    $partnerContactId,
                    $invoiceId
                );

                $this->createLine(
                    $entry,
                    $salesAccount,
                    $lineNumber++,
                    'Ingreso por venta - ' . $sourceLabel,
                    0,
                    $amounts['subtotal'],
                    $currency,
                    $partnerContactId,
                    $invoiceId
                );

                if ($amounts['tax'] > 0) {
                    $this->createLine(
                        $entry,
                        $vatAccount,
                        $lineNumber++,
                        'IVA trasladado - ' . $sourceLabel,
                        0,
                        $amounts['tax'],
                        $currency,
                        $partnerContactId,
                        $invoiceId
                    );
                }

                $this->assertEntryBalances($entry);
                $this->markInvoiceAsPosted($invoice, $entry);

                $this->audit(
                    $companyId,
                    $invoiceId,
                    $entry->id,
                    'post_invoice',
                    'success',
                    'Factura contabilizada correctamente.',
                    [
                        'amounts' => $amounts,
                        'journal_id' => $journal?->id,
                    ],
                    [
                        'entry_id' => $entry->id,
                        'entry_number' => $entry->entry_number,
                    ],
                    $userId
                );

                return $entry;
            } catch (Throwable $e) {
                $this->markInvoiceAsError($invoice, $e->getMessage());

                $this->audit(
                    $companyId,
                    $invoiceId,
                    null,
                    'post_invoice',
                    'error',
                    $e->getMessage(),
                    [
                        'invoice_id' => $invoiceId,
                    ],
                    [
                        'exception' => get_class($e),
                    ],
                    $userId
                );

                throw $e;
            }
        });
    }

    private function guardInvoiceCanBePosted(Model $invoice): void
    {
        if (Schema::hasColumn('invoices', 'accounting_entry_id') && $invoice->getAttribute('accounting_entry_id')) {
            throw new RuntimeException('La factura ya tiene accounting_entry_id. No se permite contabilizar dos veces.');
        }

        if (Schema::hasColumn('invoices', 'accounting_status') && $invoice->getAttribute('accounting_status') === 'posted') {
            throw new RuntimeException('La factura ya está marcada como contabilizada.');
        }

        $status = (string) ($invoice->getAttribute('status') ?? '');

        if (in_array($status, ['cancelled', 'canceled'], true)) {
            throw new RuntimeException('No se puede contabilizar una factura cancelada.');
        }
    }

    private function resolveJournal(int $companyId): ?AccountingJournal
    {
        $settings = Schema::hasTable('accounting_settings')
            ? DB::table('accounting_settings')->where('company_id', $companyId)->first()
            : null;

        foreach (['sales_journal_id', 'default_journal_id'] as $field) {
            $journalId = $settings?->{$field} ?? null;

            if ($journalId) {
                $journal = AccountingJournal::query()
                    ->where('company_id', $companyId)
                    ->find($journalId);

                if ($journal) {
                    return $journal;
                }
            }
        }

        return AccountingJournal::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereIn('code', ['FAC', 'VEN'])
                    ->orWhereIn('type', ['invoicing', 'sales']);
            })
            ->orderByRaw("case when code = 'FAC' then 0 when code = 'VEN' then 1 else 2 end")
            ->first();
    }

    private function resolveAccount(int $companyId, string $settingsField, array $usages, array $codes): AccountingAccount
    {
        $settings = Schema::hasTable('accounting_settings')
            ? DB::table('accounting_settings')->where('company_id', $companyId)->first()
            : null;

        $accountId = $settings?->{$settingsField} ?? null;

        if ($accountId) {
            $account = AccountingAccount::query()
                ->where('company_id', $companyId)
                ->find($accountId);

            if ($account) {
                return $account;
            }
        }

        if (Schema::hasColumn('accounting_accounts', 'account_usage')) {
            $account = AccountingAccount::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereIn('account_usage', $usages)
                ->orderBy('code')
                ->first();

            if ($account) {
                return $account;
            }
        }

        $account = AccountingAccount::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('code', $codes)
            ->orderBy('code')
            ->first();

        if ($account) {
            return $account;
        }

        throw new RuntimeException('No se encontró cuenta contable para ' . $settingsField . ' en empresa ' . $companyId . '.');
    }

    private function resolveInvoiceAmounts(Model $invoice): array
    {
        $subtotal = $this->firstNumeric($invoice, ['subtotal', 'total_without_tax', 'subtotal_amount', 'amount_subtotal']);
        $tax = $this->firstNumeric($invoice, ['tax_total', 'total_tax', 'tax_amount', 'amount_tax']);
        $total = $this->firstNumeric($invoice, ['total', 'total_with_tax', 'grand_total', 'amount_total']);

        if (($subtotal <= 0 || $total <= 0) && Schema::hasTable('invoice_lines')) {
            $lineAmounts = $this->sumInvoiceLineAmounts((int) $invoice->getKey());

            if ($subtotal <= 0) {
                $subtotal = $lineAmounts['subtotal'];
            }

            if ($tax <= 0) {
                $tax = $lineAmounts['tax'];
            }

            if ($total <= 0) {
                $total = $lineAmounts['total'];
            }
        }

        if ($total <= 0 && ($subtotal + $tax) > 0) {
            $total = $subtotal + $tax;
        }

        if ($subtotal <= 0 && $total > 0) {
            $subtotal = max($total - $tax, 0);
        }

        if (abs(($subtotal + $tax) - $total) > 0.0001) {
            $subtotal = max($total - $tax, 0);
        }

        $subtotal = round($subtotal, 6);
        $tax = round($tax, 6);
        $total = round($total, 6);

        if ($total <= 0) {
            throw new RuntimeException('La factura no tiene total mayor a cero para contabilizar.');
        }

        if (abs(($subtotal + $tax) - $total) > 0.0001) {
            throw new RuntimeException('La factura no cuadra para contabilizar: subtotal + impuesto distinto al total.');
        }

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
        ];
    }

    private function sumInvoiceLineAmounts(int $invoiceId): array
    {
        $subtotal = 0.0;
        $tax = 0.0;
        $total = 0.0;

        $rows = DB::table('invoice_lines')
            ->where('invoice_id', $invoiceId)
            ->get();

        foreach ($rows as $row) {
            $rowSubtotal = $this->firstNumericFromObject($row, ['subtotal', 'total_without_tax', 'line_subtotal', 'amount_subtotal', 'base_amount']);
            $rowTax = $this->firstNumericFromObject($row, ['tax_total', 'total_tax', 'tax_amount', 'amount_tax']);
            $rowTotal = $this->firstNumericFromObject($row, ['total', 'total_with_tax', 'line_total', 'amount_total']);

            if ($rowTotal <= 0 && ($rowSubtotal + $rowTax) > 0) {
                $rowTotal = $rowSubtotal + $rowTax;
            }

            if ($rowSubtotal <= 0 && $rowTotal > 0) {
                $rowSubtotal = max($rowTotal - $rowTax, 0);
            }

            $subtotal += $rowSubtotal;
            $tax += $rowTax;
            $total += $rowTotal;
        }

        return [
            'subtotal' => round($subtotal, 6),
            'tax' => round($tax, 6),
            'total' => round($total, 6),
        ];
    }

    private function firstNumeric(Model $model, array $fields): float
    {
        foreach ($fields as $field) {
            $value = $model->getAttribute($field);

            if ($value !== null && $value !== '') {
                return (float) $value;
            }
        }

        return 0.0;
    }

    private function firstNumericFromObject(object $row, array $fields): float
    {
        foreach ($fields as $field) {
            if (property_exists($row, $field) && $row->{$field} !== null && $row->{$field} !== '') {
                return (float) $row->{$field};
            }
        }

        return 0.0;
    }

    private function resolveEntryDate(Model $invoice): Carbon
    {
        foreach (['invoice_date', 'issued_at', 'cfdi_stamped_at', 'created_at'] as $field) {
            $value = $invoice->getAttribute($field);

            if ($value) {
                return Carbon::parse($value);
            }
        }

        return now();
    }

    private function buildEntryNumber(?AccountingJournal $journal, Model $invoice): string
    {
        $prefix = $journal?->code ?: 'CON';

        return $prefix . '-INV-' . str_pad((string) $invoice->getKey(), 8, '0', STR_PAD_LEFT);
    }

    private function buildSourceLabel(Model $invoice): string
    {
        $parts = [];

        foreach (['cfdi_series', 'series', 'serie'] as $field) {
            $value = $invoice->getAttribute($field);

            if ($value) {
                $parts[] = $value;
                break;
            }
        }

        foreach (['cfdi_folio', 'folio', 'number'] as $field) {
            $value = $invoice->getAttribute($field);

            if ($value) {
                $parts[] = $value;
                break;
            }
        }

        return count($parts) > 0
            ? implode('-', $parts)
            : 'Factura #' . $invoice->getKey();
    }

    private function resolvePartnerContactId(Model $invoice): ?int
    {
        foreach (['customer_contact_id', 'contact_id', 'customer_id'] as $field) {
            $value = $invoice->getAttribute($field);

            if ($value) {
                return (int) $value;
            }
        }

        return null;
    }

    private function createLine(
        AccountingEntry $entry,
        AccountingAccount $account,
        int $lineNumber,
        string $label,
        float $debit,
        float $credit,
        string $currency,
        ?int $partnerContactId,
        int $invoiceId
    ): AccountingEntryLine {
        return AccountingEntryLine::query()->create([
            'company_id' => $entry->company_id,
            'accounting_entry_id' => $entry->id,
            'account_id' => $account->id,
            'line_number' => $lineNumber,
            'label' => $label,
            'partner_contact_id' => $partnerContactId,
            'debit' => round($debit, 6),
            'credit' => round($credit, 6),
            'currency' => $currency,
            'source_type' => 'invoice',
            'source_id' => $invoiceId,
            'metadata' => [
                'account_code' => $account->code,
                'account_name' => $account->name,
            ],
        ]);
    }

    private function assertEntryBalances(AccountingEntry $entry): void
    {
        $debit = (float) AccountingEntryLine::query()
            ->where('accounting_entry_id', $entry->id)
            ->sum('debit');

        $credit = (float) AccountingEntryLine::query()
            ->where('accounting_entry_id', $entry->id)
            ->sum('credit');

        if (abs($debit - $credit) > 0.0001) {
            throw new RuntimeException('El asiento no cuadra: debe=' . $debit . ', haber=' . $credit . '.');
        }

        $entry->forceFill([
            'total_debit' => round($debit, 6),
            'total_credit' => round($credit, 6),
        ])->save();
    }

    private function markInvoiceAsPosted(Model $invoice, AccountingEntry $entry): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        $updates = [];

        if (Schema::hasColumn('invoices', 'accounting_status')) {
            $updates['accounting_status'] = 'posted';
        }

        if (Schema::hasColumn('invoices', 'accounting_entry_id')) {
            $updates['accounting_entry_id'] = $entry->id;
        }

        if (Schema::hasColumn('invoices', 'accounting_posted_at')) {
            $updates['accounting_posted_at'] = now();
        }

        if (Schema::hasColumn('invoices', 'accounting_error_message')) {
            $updates['accounting_error_message'] = null;
        }

        if ($updates) {
            DB::table('invoices')
                ->where('id', $invoice->getKey())
                ->update($updates);

            $invoice->forceFill($updates);
        }
    }

    private function markInvoiceAsError(Model $invoice, string $message): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        $updates = [];

        if (Schema::hasColumn('invoices', 'accounting_status')) {
            $updates['accounting_status'] = 'error';
        }

        if (Schema::hasColumn('invoices', 'accounting_error_message')) {
            $updates['accounting_error_message'] = mb_substr($message, 0, 5000);
        }

        if ($updates) {
            DB::table('invoices')
                ->where('id', $invoice->getKey())
                ->update($updates);

            $invoice->forceFill($updates);
        }
    }

    private function audit(
        int $companyId,
        int $invoiceId,
        ?int $entryId,
        string $event,
        string $status,
        string $message,
        array $requestMeta,
        array $responseMeta,
        ?int $userId
    ): void {
        if (! class_exists(AccountingPostingAudit::class) || ! Schema::hasTable('accounting_posting_audits')) {
            return;
        }

        AccountingPostingAudit::query()->create([
            'company_id' => $companyId,
            'source_type' => 'invoice',
            'source_id' => $invoiceId,
            'accounting_entry_id' => $entryId,
            'event' => $event,
            'status' => $status,
            'message' => $message,
            'request_meta' => $requestMeta,
            'response_meta' => $responseMeta,
            'created_by_user_id' => $userId,
        ]);
    }
}
