<?php

namespace App\Support\Accounting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AccountReceivablePaymentAccountingPoster
{
    public function postPayment(int $paymentId, ?int $userId = null): object
    {
        return DB::transaction(function () use ($paymentId, $userId): object {
            $payment = DB::table('account_receivable_payments')
                ->where('id', $paymentId)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw new RuntimeException('No se encontró el cobro CxC para contabilizar.');
            }

            if ((string) $payment->status !== 'posted') {
                throw new RuntimeException('Solo se pueden contabilizar cobros aplicados.');
            }

            if ($payment->accounting_entry_id) {
                $entry = DB::table('accounting_entries')->where('id', $payment->accounting_entry_id)->first();

                if ($entry) {
                    return $entry;
                }
            }

            $existingEntry = DB::table('accounting_entries')
                ->where('company_id', $payment->company_id)
                ->where('source_type', 'account_receivable_payment')
                ->where('source_id', $payment->id)
                ->whereIn('status', ['draft', 'posted'])
                ->first();

            if ($existingEntry) {
                DB::table('account_receivable_payments')
                    ->where('id', $payment->id)
                    ->update([
                        'accounting_entry_id' => $existingEntry->id,
                        'updated_at' => now(),
                    ]);

                if ($payment->treasury_movement_id) {
                    DB::table('treasury_movements')
                        ->where('id', $payment->treasury_movement_id)
                        ->update([
                            'accounting_entry_id' => $existingEntry->id,
                            'updated_at' => now(),
                        ]);
                }

                return $existingEntry;
            }

            $receivable = DB::table('account_receivables')
                ->where('id', $payment->account_receivable_id)
                ->where('company_id', $payment->company_id)
                ->first();

            if (! $receivable) {
                throw new RuntimeException('No se encontró la CxC relacionada al cobro.');
            }

            $treasuryAccount = DB::table('treasury_accounts')
                ->where('id', $payment->treasury_account_id)
                ->where('company_id', $payment->company_id)
                ->first();

            if (! $treasuryAccount) {
                throw new RuntimeException('No se encontró la cuenta/caja de tesorería relacionada al cobro.');
            }

            $customerAccount = $this->resolveCustomerReceivableAccount((int) $payment->company_id);
            $cashOrBankAccount = $this->resolveTreasuryAccountingAccount((int) $payment->company_id, $treasuryAccount);
            $journal = $this->resolveJournal((int) $payment->company_id, $treasuryAccount);

            $amount = round((float) $payment->amount, 6);

            if ($amount <= 0) {
                throw new RuntimeException('El importe del cobro CxC no es válido para contabilizar.');
            }

            $currency = (string) ($payment->currency ?: 'MXN');
            $label = 'Cobro CxC ' . ($receivable->number ?? ('#' . $receivable->id));
            $entryNumber = $this->buildEntryNumber($journal, 'CXC-COB', (int) $payment->id);

            $entryId = DB::table('accounting_entries')->insertGetId([
                'company_id' => $payment->company_id,
                'journal_id' => $journal?->id,
                'entry_number' => $entryNumber,
                'entry_date' => $payment->payment_date ?: now()->toDateString(),
                'status' => 'posted',
                'source_type' => $sourceType,
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
                'notes' => 'Póliza generada automáticamente desde cobro de cuenta por cobrar.',
                'metadata' => json_encode([
                    'created_by_patch' => 'v5.57.4',
                    'account_receivable_payment_id' => $payment->id,
                    'account_receivable_id' => $receivable->id,
                    'account_receivable_number' => $receivable->number,
                    'treasury_account_id' => $treasuryAccount->id,
                    'treasury_account_name' => $treasuryAccount->name,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->createLine(
                $entryId,
                (int) $payment->company_id,
                (int) $cashOrBankAccount->id,
                1,
                'Cargo a ' . $cashOrBankAccount->name . ' - ' . $label,
                $amount,
                0,
                $currency,
                $receivable->customer_contact_id ?? null,
                (int) $payment->id
            );

            $this->createLine(
                $entryId,
                (int) $payment->company_id,
                (int) $customerAccount->id,
                2,
                'Abono a clientes - ' . $label,
                0,
                $amount,
                $currency,
                $receivable->customer_contact_id ?? null,
                (int) $payment->id
            );

            $this->assertEntryBalances($entryId);

            DB::table('account_receivable_payments')
                ->where('id', $payment->id)
                ->update([
                    'accounting_entry_id' => $entryId,
                    'updated_at' => now(),
                ]);

            if ($payment->treasury_movement_id) {
                DB::table('treasury_movements')
                    ->where('id', $payment->treasury_movement_id)
                    ->update([
                        'accounting_entry_id' => $entryId,
                        'updated_at' => now(),
                    ]);
            }

            return DB::table('accounting_entries')->where('id', $entryId)->first();
        });
    }

    public function cancelPayment(int $paymentId, ?int $userId = null): ?object
    {
        return DB::transaction(function () use ($paymentId, $userId): ?object {
            $payment = DB::table('account_receivable_payments')
                ->where('id', $paymentId)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw new RuntimeException('No se encontró el cobro CxC para reversar contablemente.');
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
                throw new RuntimeException('No se encontró la póliza original del cobro CxC.');
            }

            $existingReversal = DB::table('accounting_entries')
                ->where('company_id', $payment->company_id)
                ->where('source_type', 'account_receivable_payment_cancellation')
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

            $receivable = DB::table('account_receivables')
                ->where('id', $payment->account_receivable_id)
                ->where('company_id', $payment->company_id)
                ->first();

            if (! $receivable) {
                throw new RuntimeException('No se encontró la CxC relacionada al cobro.');
            }

            $treasuryAccount = DB::table('treasury_accounts')
                ->where('id', $payment->treasury_account_id)
                ->where('company_id', $payment->company_id)
                ->first();

            if (! $treasuryAccount) {
                throw new RuntimeException('No se encontró la cuenta/caja relacionada al cobro.');
            }

            $customerAccount = $this->resolveCustomerReceivableAccount((int) $payment->company_id);
            $cashOrBankAccount = $this->resolveTreasuryAccountingAccount((int) $payment->company_id, $treasuryAccount);
            $journal = $this->resolveJournal((int) $payment->company_id, $treasuryAccount);

            $amount = round((float) $payment->amount, 6);

            if ($amount <= 0) {
                throw new RuntimeException('El importe del cobro CxC no es válido para reversar.');
            }

            $currency = (string) ($payment->currency ?: 'MXN');
            $label = 'Cancelación cobro CxC ' . ($receivable->number ?? ('#' . $receivable->id));
            $entryNumber = $this->buildEntryNumber($journal, 'CXC-CAN', (int) $payment->id);

            $reversalEntryId = DB::table('accounting_entries')->insertGetId([
                'company_id' => $payment->company_id,
                'journal_id' => $journal?->id,
                'entry_number' => $entryNumber,
                'entry_date' => now()->toDateString(),
                'status' => 'posted',
                'source_type' => 'account_receivable_payment_cancellation',
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
                'notes' => 'Póliza de reversa generada automáticamente por cancelación de cobro CxC.',
                'metadata' => json_encode([
                    'created_by_patch' => 'v5.57.5',
                    'reverses_accounting_entry_id' => $originalEntry->id,
                    'account_receivable_payment_id' => $payment->id,
                    'account_receivable_id' => $receivable->id,
                    'account_receivable_number' => $receivable->number,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->createLine(
                $reversalEntryId,
                (int) $payment->company_id,
                (int) $customerAccount->id,
                1,
                'Cargo a clientes - ' . $label,
                $amount,
                0,
                $currency,
                $receivable->customer_contact_id ?? null,
                (int) $payment->id,
                'account_receivable_payment_cancellation'
            );

            $this->createLine(
                $reversalEntryId,
                (int) $payment->company_id,
                (int) $cashOrBankAccount->id,
                2,
                'Abono a ' . $cashOrBankAccount->name . ' - ' . $label,
                0,
                $amount,
                $currency,
                $receivable->customer_contact_id ?? null,
                (int) $payment->id,
                'account_receivable_payment_cancellation'
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
        string $sourceType = 'account_receivable_payment'
    ): void {
        $account = DB::table('accounting_accounts')
            ->where('id', $accountId)
            ->where('company_id', $companyId)
            ->first();

        if (! $account) {
            throw new RuntimeException('No se encontró la cuenta contable para la línea del cobro CxC.');
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
                'created_by_patch' => 'v5.57.4',
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
            throw new RuntimeException('La póliza del cobro CxC no cuadra: debe=' . $debit . ', haber=' . $credit . '.');
        }

        DB::table('accounting_entries')
            ->where('id', $entryId)
            ->update([
                'total_debit' => $debit,
                'total_credit' => $credit,
                'updated_at' => now(),
            ]);
    }

    protected function resolveCustomerReceivableAccount(int $companyId): object
    {
        return $this->resolveAccount($companyId, [
            'account_usage' => ['receivable', 'customer_receivable', 'accounts_receivable'],
            'names' => ['%clientes%', '%cuentas por cobrar%'],
            'codes' => ['105.%', '110.%'],
            'error' => 'No se encontró cuenta contable de clientes / cuentas por cobrar.',
        ]);
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

        if (! $account && Schema::hasTable('accounting_settings')) {
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
            $account = DB::table('accounting_accounts')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->where(function ($query) use ($treasuryAccount): void {
                    if ((string) ($treasuryAccount->type ?? '') === 'bank') {
                        $query->where('name', 'ilike', '%banco%')
                            ->orWhere('account_usage', 'bank')
                            ->orWhere('code', 'like', '102.%');
                    } else {
                        $query->where('name', 'ilike', '%caja%')
                            ->orWhere('account_usage', 'cash')
                            ->orWhere('code', 'like', '101.%');
                    }
                })
                ->orderBy('code')
                ->first();
        }

        if (! $account) {
            throw new RuntimeException('No se encontró cuenta contable para la caja/banco de tesorería.');
        }

        return $account;
    }

    protected function resolveAccount(int $companyId, array $config): object
    {
        $query = DB::table('accounting_accounts')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($query) use ($config): void {
                foreach (($config['account_usage'] ?? []) as $usage) {
                    $query->orWhere('account_usage', $usage);
                }

                foreach (($config['names'] ?? []) as $name) {
                    $query->orWhere('name', 'ilike', $name);
                }

                foreach (($config['codes'] ?? []) as $code) {
                    $query->orWhere('code', 'like', $code);
                }
            })
            ->orderBy('code');

        $account = $query->first();

        if (! $account) {
            throw new RuntimeException($config['error'] ?? 'No se encontró cuenta contable requerida.');
        }

        return $account;
    }

    protected function resolveJournal(int $companyId, object $treasuryAccount): ?object
    {
        $type = (string) ($treasuryAccount->type ?? '');
        $journalType = $type === 'bank' ? 'bank' : 'cash';

        $query = DB::table('accounting_journals')->where('company_id', $companyId);

        if (Schema::hasColumn('accounting_journals', 'is_active')) {
            $query->where('is_active', true);
        }

        $journal = $query
            ->where('type', $journalType)
            ->orderBy('id')
            ->first();

        if ($journal) {
            return $journal;
        }

        $fallback = DB::table('accounting_journals')->where('company_id', $companyId);

        if (Schema::hasColumn('accounting_journals', 'is_active')) {
            $fallback->where('is_active', true);
        }

        return $fallback
            ->where(function ($inner): void {
                $inner->whereIn('code', ['CXC', 'BCO', 'CAJ', 'GEN'])
                    ->orWhere('name', 'ilike', '%cobro%')
                    ->orWhere('name', 'ilike', '%banco%')
                    ->orWhere('name', 'ilike', '%caja%')
                    ->orWhere('name', 'ilike', '%general%');
            })
            ->orderByRaw("case when code = 'CXC' then 0 when code = 'BCO' then 1 when code = 'CAJ' then 2 when code = 'GEN' then 3 else 4 end")
            ->first();
    }

    protected function buildEntryNumber(?object $journal, string $operation, int $paymentId): string
    {
        $prefix = $journal?->code ?: 'CXC';

        return $prefix . '-' . $operation . '-' . str_pad((string) $paymentId, 8, '0', STR_PAD_LEFT);
    }
}
