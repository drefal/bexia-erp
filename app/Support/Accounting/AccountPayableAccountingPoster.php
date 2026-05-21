<?php

namespace App\Support\Accounting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AccountPayableAccountingPoster
{
    public function postPayable(int $accountPayableId, ?int $userId = null): ?object
    {
        if ($accountPayableId <= 0) {
            return null;
        }

        return DB::transaction(function () use ($accountPayableId, $userId): ?object {
            $payable = DB::table('account_payables')
                ->where('id', $accountPayableId)
                ->lockForUpdate()
                ->first();

            if (! $payable) {
                throw new RuntimeException('No se encontró la CxP para contabilizar.');
            }

            if ((string) ($payable->status ?? '') === 'cancelled') {
                throw new RuntimeException('No se puede contabilizar una CxP cancelada.');
            }

            if (! empty($payable->accounting_entry_id)) {
                $entry = DB::table('accounting_entries')
                    ->where('id', $payable->accounting_entry_id)
                    ->where('company_id', $payable->company_id)
                    ->first();

                if ($entry) {
                    return $entry;
                }
            }

            $existingEntry = DB::table('accounting_entries')
                ->where('company_id', $payable->company_id)
                ->where('source_type', 'account_payable_purchase_receipt')
                ->where('source_id', $payable->id)
                ->whereIn('status', ['draft', 'posted'])
                ->first();

            if ($existingEntry) {
                $this->markPayablePosted($payable, (int) $existingEntry->id);

                return $existingEntry;
            }

            $companyId = (int) $payable->company_id;
            $subtotal = round((float) ($payable->subtotal ?? 0), 6);
            $tax = round((float) ($payable->tax_total ?? 0), 6);
            $total = round((float) ($payable->total ?? 0), 6);

            if ($total <= 0) {
                throw new RuntimeException('El total de la CxP debe ser mayor a cero para contabilizar.');
            }

            if ($subtotal <= 0) {
                $subtotal = max(0, round($total - $tax, 6));
            }

            $currency = (string) ($payable->currency ?: 'MXN');
            $journal = $this->resolveJournal($companyId);
            $clearingAccount = $this->resolvePurchaseClearingAccount($companyId);
            $taxAccount = $tax > 0 ? $this->resolveTaxCreditAccount($companyId) : null;
            $supplierAccount = $this->resolveSupplierPayableAccount($companyId);

            $entryNumber = $this->buildEntryNumber($journal, 'CXP-REC', (int) $payable->id);
            $label = 'CxP recepción ' . ($payable->number ?? ('#' . $payable->id));
            $entryDate = $payable->issue_date ?: now()->toDateString();

            $entryId = DB::table('accounting_entries')->insertGetId([
                'company_id' => $companyId,
                'journal_id' => $journal?->id,
                'entry_number' => $entryNumber,
                'entry_date' => $entryDate,
                'status' => 'posted',
                'source_type' => 'account_payable_purchase_receipt',
                'source_id' => $payable->id,
                'source_label' => $label,
                'currency' => $currency,
                'total_debit' => $total,
                'total_credit' => $total,
                'posted_at' => now(),
                'cancelled_at' => null,
                'cancelled_by_entry_id' => null,
                'created_by_user_id' => $userId ?: ($payable->created_by_user_id ?? null),
                'posted_by_user_id' => $userId,
                'notes' => 'Póliza automática de CxP desde recepción de compra. Cierra cuenta puente contra proveedor.',
                'metadata' => json_encode([
                    'created_by_patch' => 'v5.56.10',
                    'account_payable_id' => $payable->id,
                    'account_payable_number' => $payable->number,
                    'purchase_receipt_id' => $payable->purchase_receipt_id,
                    'purchase_order_id' => $payable->purchase_order_id,
                    'subtotal' => $subtotal,
                    'tax_total' => $tax,
                    'total' => $total,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $lineNumber = 1;

            if ($subtotal > 0) {
                $this->createLine(
                    $entryId,
                    $companyId,
                    (int) $clearingAccount->id,
                    $lineNumber++,
                    'Cargo a compras por liquidar - ' . $label,
                    $subtotal,
                    0,
                    $currency,
                    $payable->supplier_contact_id ?? null,
                    (int) $payable->id
                );
            }

            if ($tax > 0 && $taxAccount) {
                $this->createLine(
                    $entryId,
                    $companyId,
                    (int) $taxAccount->id,
                    $lineNumber++,
                    'Cargo a IVA acreditable - ' . $label,
                    $tax,
                    0,
                    $currency,
                    $payable->supplier_contact_id ?? null,
                    (int) $payable->id
                );
            }

            $this->createLine(
                $entryId,
                $companyId,
                (int) $supplierAccount->id,
                $lineNumber,
                'Abono a proveedores - ' . $label,
                0,
                $total,
                $currency,
                $payable->supplier_contact_id ?? null,
                (int) $payable->id
            );

            $this->assertEntryBalances($entryId);
            $this->markPayablePosted($payable, $entryId);

            return DB::table('accounting_entries')->where('id', $entryId)->first();
        });
    }

    protected function createLine(
        int $entryId,
        int $companyId,
        int $accountId,
        int $lineNumber,
        string $label,
        float $debit,
        float $credit,
        string $currency,
        ?int $partnerContactId,
        int $payableId
    ): void {
        $account = DB::table('accounting_accounts')
            ->where('company_id', $companyId)
            ->where('id', $accountId)
            ->first();

        if (! $account) {
            throw new RuntimeException('No se encontró la cuenta contable para la línea CxP.');
        }

        DB::table('accounting_entry_lines')->insert([
            'company_id' => $companyId,
            'accounting_entry_id' => $entryId,
            'account_id' => $accountId,
            'line_number' => $lineNumber,
            'label' => $label,
            'partner_contact_id' => $partnerContactId,
            'debit' => round($debit, 6),
            'credit' => round($credit, 6),
            'currency' => $currency,
            'source_type' => 'account_payable_purchase_receipt',
            'source_id' => $payableId,
            'metadata' => json_encode([
                'account_code' => $account->code,
                'account_name' => $account->name,
                'created_by_patch' => 'v5.56.10',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function assertEntryBalances(int $entryId): void
    {
        $debit = round((float) DB::table('accounting_entry_lines')
            ->where('accounting_entry_id', $entryId)
            ->sum('debit'), 6);

        $credit = round((float) DB::table('accounting_entry_lines')
            ->where('accounting_entry_id', $entryId)
            ->sum('credit'), 6);

        if (abs($debit - $credit) > 0.0001) {
            throw new RuntimeException('La póliza CxP no cuadra: debe=' . $debit . ', haber=' . $credit . '.');
        }

        DB::table('accounting_entries')
            ->where('id', $entryId)
            ->update([
                'total_debit' => $debit,
                'total_credit' => $credit,
                'updated_at' => now(),
            ]);
    }

    protected function markPayablePosted(object $payable, int $entryId): void
    {
        DB::table('account_payables')
            ->where('id', $payable->id)
            ->update([
                'accounting_status' => 'posted',
                'accounting_entry_id' => $entryId,
                'accounting_posted_at' => now(),
                'accounting_error_message' => null,
                'updated_at' => now(),
            ]);
    }

    protected function resolvePurchaseClearingAccount(int $companyId): object
    {
        $mapped = DB::table('accounting_mappings as m')
            ->join('accounting_accounts as a', 'a.id', '=', 'm.account_id')
            ->where('m.company_id', $companyId)
            ->where('m.module', 'inventory')
            ->where('m.operation_type', 'purchase_receipt')
            ->where('m.mapping_key', 'inventory_purchase_clearing')
            ->where('m.is_active', true)
            ->where('a.company_id', $companyId)
            ->where('a.is_active', true)
            ->orderBy('m.priority')
            ->first(['a.*']);

        if ($mapped) {
            return $mapped;
        }

        $account = DB::table('accounting_accounts')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereIn('account_usage', ['inventory_purchase_clearing', 'purchase_clearing', 'clearing'])
                    ->orWhere('name', 'ilike', '%liquidar%')
                    ->orWhere('name', 'ilike', '%puente%')
                    ->orWhere('name', 'ilike', '%compras por pagar%')
                    ->orWhere('name', 'ilike', '%compras por liquidar%');
            })
            ->orderBy('code')
            ->first();

        if (! $account) {
            throw new RuntimeException('No se encontró cuenta puente de compras por liquidar. Configura el mapeo inventory_purchase_clearing.');
        }

        return $account;
    }

    protected function resolveTaxCreditAccount(int $companyId): object
    {
        $account = DB::table('accounting_accounts')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereIn('account_usage', ['tax_credit', 'vat_credit', 'input_tax'])
                    ->orWhere('name', 'ilike', '%iva acredit%')
                    ->orWhere('name', 'ilike', '%impuesto acredit%')
                    ->orWhere('name', 'ilike', '%iva por acreditar%')
                    ->orWhere('code', 'like', '118.%')
                    ->orWhere('code', 'like', '119.%');
            })
            ->orderByRaw("case when name ilike '%iva acredit%' then 0 else 1 end")
            ->orderBy('code')
            ->first();

        if (! $account) {
            throw new RuntimeException('No se encontró cuenta de IVA acreditable para la empresa.');
        }

        return $account;
    }

    protected function resolveSupplierPayableAccount(int $companyId): object
    {
        $settings = Schema::hasTable('accounting_settings')
            ? DB::table('accounting_settings')->where('company_id', $companyId)->first()
            : null;

        $accountId = $settings->supplier_payable_account_id ?? null;

        $account = $accountId
            ? DB::table('accounting_accounts')
                ->where('company_id', $companyId)
                ->where('id', $accountId)
                ->where('is_active', true)
                ->first()
            : null;

        if (! $account) {
            $account = DB::table('accounting_accounts')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->where('account_usage', 'payable')
                        ->orWhere('name', 'ilike', '%proveedor%')
                        ->orWhere('code', 'like', '201.%');
                })
                ->orderBy('code')
                ->first();
        }

        if (! $account) {
            throw new RuntimeException('No se encontró cuenta contable de proveedores para la empresa.');
        }

        return $account;
    }

    protected function resolveJournal(int $companyId): ?object
    {
        $query = DB::table('accounting_journals')->where('company_id', $companyId);

        if (Schema::hasColumn('accounting_journals', 'is_active')) {
            $query->where('is_active', true);
        }

        $journal = $query
            ->where(function ($inner): void {
                $inner->whereIn('code', ['COM', 'CXP', 'COMP', 'GEN'])
                    ->orWhere('name', 'ilike', '%compra%')
                    ->orWhere('name', 'ilike', '%cxp%')
                    ->orWhere('name', 'ilike', '%general%');
            })
            ->orderByRaw("case when code = 'COM' then 0 when code = 'CXP' then 1 when code = 'GEN' then 2 else 3 end")
            ->first();

        if ($journal) {
            return $journal;
        }

        $fallback = DB::table('accounting_journals')->where('company_id', $companyId);

        if (Schema::hasColumn('accounting_journals', 'is_active')) {
            $fallback->where('is_active', true);
        }

        return $fallback->orderBy('id')->first();
    }

    protected function buildEntryNumber(?object $journal, string $prefix, int $sourceId): string
    {
        $journalCode = $journal?->code ?: 'CXP';

        return $journalCode . '-' . $prefix . '-' . str_pad((string) $sourceId, 8, '0', STR_PAD_LEFT);
    }
}
