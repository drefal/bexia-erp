<?php

namespace App\Support\Accounting;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class AccountPayablePaymentAccountingPoster
{
    public function postPayment(int $paymentId, ?int $userId = null): object
    {
        return DB::transaction(function () use ($paymentId, $userId): object {
            $payment = DB::table('account_payable_payments')
                ->where('id', $paymentId)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw new RuntimeException('No se encontró el pago CxP para contabilizar.');
            }

            if ((string) $payment->status !== 'posted') {
                throw new RuntimeException('Solo se pueden contabilizar pagos aplicados.');
            }

            if ($payment->accounting_entry_id) {
                $entry = DB::table('accounting_entries')->where('id', $payment->accounting_entry_id)->first();

                if ($entry) {
                    return $entry;
                }
            }

            $existingEntry = DB::table('accounting_entries')
                ->where('company_id', $payment->company_id)
                ->where('source_type', 'account_payable_payment')
                ->where('source_id', $payment->id)
                ->whereIn('status', ['draft', 'posted'])
                ->first();

            if ($existingEntry) {
                DB::table('account_payable_payments')
                    ->where('id', $payment->id)
                    ->update([
                        'accounting_entry_id' => $existingEntry->id,
                        'updated_at' => now(),
                    ]);

                DB::table('treasury_movements')
                    ->where('id', $payment->treasury_movement_id)
                    ->update([
                        'accounting_entry_id' => $existingEntry->id,
                        'updated_at' => now(),
                    ]);

                return $existingEntry;
            }

            $payable = DB::table('account_payables')
                ->where('id', $payment->account_payable_id)
                ->where('company_id', $payment->company_id)
                ->first();

            if (! $payable) {
                throw new RuntimeException('No se encontró la CxP relacionada al pago.');
            }

            $treasuryAccount = DB::table('treasury_accounts')
                ->where('id', $payment->treasury_account_id)
                ->where('company_id', $payment->company_id)
                ->first();

            if (! $treasuryAccount) {
                throw new RuntimeException('No se encontró la cuenta/caja de tesorería relacionada al pago.');
            }

            $supplierAccount = $this->resolveSupplierPayableAccount((int) $payment->company_id);
            $cashOrBankAccount = $this->resolveTreasuryAccountingAccount((int) $payment->company_id, $treasuryAccount);
            $journal = $this->resolveJournal((int) $payment->company_id, $treasuryAccount);

            $amount = round((float) $payment->amount, 6);

            if ($amount <= 0) {
                throw new RuntimeException('El importe del pago CxP no es válido para contabilizar.');
            }

            $currency = (string) ($payment->currency ?: 'MXN');
            $label = 'Pago CxP ' . ($payable->number ?? ('#' . $payable->id));
            $entryNumber = $this->buildEntryNumber($journal, 'CXP-PAG', (int) $payment->id);

            $entryId = DB::table('accounting_entries')->insertGetId([
                'company_id' => $payment->company_id,
                'journal_id' => $journal?->id,
                'entry_number' => $entryNumber,
                'entry_date' => $payment->payment_date ?: now()->toDateString(),
                'status' => 'posted',
                'source_type' => 'account_payable_payment',
                'source_id' => $payment->id,
                'source_label' => $label,
                'currency' => $currency,
                'total_debit' => $amount,
                'total_credit' => $amount,
                'posted_at' => now(),
                'cancelled_at' => null,
                'cancelled_by_entry_id' => null,
                'created_by_user_id' => $userId ?: ($payment->created_by_user_id ?? null),
                'posted_by_user_id' => $userId,
                'notes' => 'Póliza generada automáticamente desde pago de cuenta por pagar.',
                'metadata' => json_encode([
                    'created_by_patch' => 'v5.56.6b',
                    'account_payable_payment_id' => $payment->id,
                    'account_payable_id' => $payable->id,
                    'account_payable_number' => $payable->number,
                    'treasury_account_id' => $treasuryAccount->id,
                    'treasury_account_name' => $treasuryAccount->name,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->createLine(
                $entryId,
                (int) $payment->company_id,
                (int) $supplierAccount->id,
                1,
                'Cargo a proveedores - ' . $label,
                $amount,
                0,
                $currency,
                $payable->supplier_contact_id ?? null,
                (int) $payment->id
            );

            $this->createLine(
                $entryId,
                (int) $payment->company_id,
                (int) $cashOrBankAccount->id,
                2,
                'Abono a ' . $cashOrBankAccount->name . ' - ' . $label,
                0,
                $amount,
                $currency,
                $payable->supplier_contact_id ?? null,
                (int) $payment->id
            );

            $this->assertEntryBalances($entryId);

            DB::table('account_payable_payments')
                ->where('id', $payment->id)
                ->update([
                    'accounting_entry_id' => $entryId,
                    'updated_at' => now(),
                ]);

            DB::table('treasury_movements')
                ->where('id', $payment->treasury_movement_id)
                ->update([
                    'accounting_entry_id' => $entryId,
                    'updated_at' => now(),
                ]);

            return DB::table('accounting_entries')->where('id', $entryId)->first();
        });
    }

    public function cancelPayment(int $paymentId, ?int $userId = null): ?object
    {
        return DB::transaction(function () use ($paymentId, $userId): ?object {
            $payment = DB::table('account_payable_payments')
                ->where('id', $paymentId)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw new RuntimeException('No se encontró el pago CxP para reversar contablemente.');
            }

            if (! $payment->accounting_entry_id) {
                return null;
            }

            $originalEntry = DB::table('accounting_entries')
                ->where('id', $payment->accounting_entry_id)
                ->where('company_id', $payment->company_id)
                ->lockForUpdate()
                ->first();

            if (! $originalEntry) {
                throw new RuntimeException('No se encontró la póliza original del pago CxP.');
            }

            $existingReversal = DB::table('accounting_entries')
                ->where('company_id', $payment->company_id)
                ->where('source_type', 'account_payable_payment_cancellation')
                ->where('source_id', $payment->id)
                ->whereIn('status', ['draft', 'posted'])
                ->first();

            if ($existingReversal) {
                if ((string) $originalEntry->status !== 'cancelled') {
                    DB::table('accounting_entries')
                        ->where('id', $originalEntry->id)
                        ->update([
                            'status' => 'cancelled',
                            'cancelled_at' => now(),
                            'cancelled_by_entry_id' => $existingReversal->id,
                            'updated_at' => now(),
                        ]);
                }

                return $existingReversal;
            }

            if ((string) $originalEntry->status === 'cancelled' && $originalEntry->cancelled_by_entry_id) {
                return DB::table('accounting_entries')->where('id', $originalEntry->cancelled_by_entry_id)->first();
            }

            $payable = DB::table('account_payables')
                ->where('id', $payment->account_payable_id)
                ->where('company_id', $payment->company_id)
                ->first();

            if (! $payable) {
                throw new RuntimeException('No se encontró la CxP relacionada al pago.');
            }

            $treasuryAccount = DB::table('treasury_accounts')
                ->where('id', $payment->treasury_account_id)
                ->where('company_id', $payment->company_id)
                ->first();

            if (! $treasuryAccount) {
                throw new RuntimeException('No se encontró la cuenta/caja relacionada al pago.');
            }

            $supplierAccount = $this->resolveSupplierPayableAccount((int) $payment->company_id);
            $cashOrBankAccount = $this->resolveTreasuryAccountingAccount((int) $payment->company_id, $treasuryAccount);
            $journal = $this->resolveJournal((int) $payment->company_id, $treasuryAccount);

            $amount = round((float) $payment->amount, 6);

            if ($amount <= 0) {
                throw new RuntimeException('El importe del pago CxP no es válido para reversar.');
            }

            $currency = (string) ($payment->currency ?: 'MXN');
            $label = 'Cancelación pago CxP ' . ($payable->number ?? ('#' . $payable->id));
            $entryNumber = $this->buildEntryNumber($journal, 'CXP-CAN', (int) $payment->id);

            $reversalEntryId = DB::table('accounting_entries')->insertGetId([
                'company_id' => $payment->company_id,
                'journal_id' => $journal?->id,
                'entry_number' => $entryNumber,
                'entry_date' => now()->toDateString(),
                'status' => 'posted',
                'source_type' => 'account_payable_payment_cancellation',
                'source_id' => $payment->id,
                'source_label' => $label,
                'currency' => $currency,
                'total_debit' => $amount,
                'total_credit' => $amount,
                'posted_at' => now(),
                'cancelled_at' => null,
                'cancelled_by_entry_id' => null,
                'created_by_user_id' => $userId ?: ($payment->created_by_user_id ?? null),
                'posted_by_user_id' => $userId,
                'notes' => 'Póliza de reversa generada automáticamente por cancelación de pago CxP.',
                'metadata' => json_encode([
                    'created_by_patch' => 'v5.56.6b',
                    'reverses_accounting_entry_id' => $originalEntry->id,
                    'account_payable_payment_id' => $payment->id,
                    'account_payable_id' => $payable->id,
                    'account_payable_number' => $payable->number,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->createLine(
                $reversalEntryId,
                (int) $payment->company_id,
                (int) $cashOrBankAccount->id,
                1,
                'Cargo a ' . $cashOrBankAccount->name . ' - ' . $label,
                $amount,
                0,
                $currency,
                $payable->supplier_contact_id ?? null,
                (int) $payment->id,
                'account_payable_payment_cancellation'
            );

            $this->createLine(
                $reversalEntryId,
                (int) $payment->company_id,
                (int) $supplierAccount->id,
                2,
                'Abono a proveedores - ' . $label,
                0,
                $amount,
                $currency,
                $payable->supplier_contact_id ?? null,
                (int) $payment->id,
                'account_payable_payment_cancellation'
            );

            $this->assertEntryBalances($reversalEntryId);

            DB::table('accounting_entries')
                ->where('id', $originalEntry->id)
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by_entry_id' => $reversalEntryId,
                    'updated_at' => now(),
                ]);

            return DB::table('accounting_entries')->where('id', $reversalEntryId)->first();
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
        int $paymentId,
        string $sourceType = 'account_payable_payment'
    ): void {
        $account = DB::table('accounting_accounts')
            ->where('id', $accountId)
            ->where('company_id', $companyId)
            ->first();

        if (! $account) {
            throw new RuntimeException('No se encontró la cuenta contable para la línea.');
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
            'source_type' => $sourceType,
            'source_id' => $paymentId,
            'metadata' => json_encode([
                'account_code' => $account->code,
                'account_name' => $account->name,
                'created_by_patch' => 'v5.56.6b',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function assertEntryBalances(int $entryId): void
    {
        $debit = (float) DB::table('accounting_entry_lines')
            ->where('accounting_entry_id', $entryId)
            ->sum('debit');

        $credit = (float) DB::table('accounting_entry_lines')
            ->where('accounting_entry_id', $entryId)
            ->sum('credit');

        if (abs($debit - $credit) > 0.0001) {
            throw new RuntimeException('La póliza no cuadra: debe=' . $debit . ', haber=' . $credit . '.');
        }

        DB::table('accounting_entries')
            ->where('id', $entryId)
            ->update([
                'total_debit' => round($debit, 6),
                'total_credit' => round($credit, 6),
                'updated_at' => now(),
            ]);
    }

    protected function resolveSupplierPayableAccount(int $companyId): object
    {
        $settings = DB::table('accounting_settings')->where('company_id', $companyId)->first();

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

    protected function resolveTreasuryAccountingAccount(int $companyId, object $treasuryAccount): object
    {
        $accountId = $treasuryAccount->accounting_account_id ?? null;

        $account = $accountId
            ? DB::table('accounting_accounts')
                ->where('company_id', $companyId)
                ->where('id', $accountId)
                ->where('is_active', true)
                ->first()
            : null;

        if (! $account) {
            $settings = DB::table('accounting_settings')->where('company_id', $companyId)->first();

            $fallbackId = match ((string) ($treasuryAccount->type ?? '')) {
                'bank' => $settings->bank_account_id ?? null,
                default => $settings->cash_account_id ?? null,
            };

            $account = $fallbackId
                ? DB::table('accounting_accounts')
                    ->where('company_id', $companyId)
                    ->where('id', $fallbackId)
                    ->where('is_active', true)
                    ->first()
                : null;
        }

        if (! $account) {
            throw new RuntimeException('No se encontró cuenta contable para la caja/banco de tesorería.');
        }

        return $account;
    }

    protected function resolveJournal(int $companyId, object $treasuryAccount): ?object
    {
        $type = (string) ($treasuryAccount->type ?? '');

        $journalType = $type === 'bank' ? 'bank' : 'cash';

        $journal = DB::table('accounting_journals')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('type', $journalType)
            ->orderBy('id')
            ->first();

        if (! $journal) {
            $journal = DB::table('accounting_journals')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->where('type', 'general')
                ->orderBy('id')
                ->first();
        }

        return $journal;
    }

    protected function buildEntryNumber(?object $journal, string $operation, int $paymentId): string
    {
        $prefix = $journal?->code ?: 'GEN';

        return $prefix . '-' . $operation . '-' . str_pad((string) $paymentId, 8, '0', STR_PAD_LEFT);
    }
}
