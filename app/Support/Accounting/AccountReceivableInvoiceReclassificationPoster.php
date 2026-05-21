<?php

namespace App\Support\Accounting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AccountReceivableInvoiceReclassificationPoster
{
    public function reclassify(int $accountReceivableId, int $invoiceId, ?int $userId = null): ?object
    {
        if ($accountReceivableId <= 0 || $invoiceId <= 0) {
            return null;
        }

        return DB::transaction(function () use ($accountReceivableId, $invoiceId, $userId): ?object {
            $receivable = DB::table('account_receivables')
                ->where('id', $accountReceivableId)
                ->lockForUpdate()
                ->first();

            if (! $receivable) {
                throw new RuntimeException('No se encontró la CxC para reclasificar.');
            }

            $invoice = DB::table('invoices')
                ->where('id', $invoiceId)
                ->where('company_id', $receivable->company_id)
                ->first();

            if (! $invoice) {
                throw new RuntimeException('No se encontró la factura para reclasificar la CxC.');
            }

            if ((string) ($receivable->source_type ?? '') !== 'sales_order') {
                return null;
            }

            $existing = DB::table('accounting_entries')
                ->where('company_id', $receivable->company_id)
                ->where('source_type', 'account_receivable_invoice_reclassification')
                ->where('source_id', $receivable->id)
                ->whereIn('status', ['draft', 'posted'])
                ->first();

            if ($existing) {
                $this->markReceivablePosted($receivable, (int) $existing->id);

                return $existing;
            }

            $companyId = (int) $receivable->company_id;
            $total = round((float) ($invoice->total ?? $receivable->total ?? 0), 6);
            $tax = round((float) ($invoice->tax_total ?? $receivable->tax_total ?? 0), 6);
            $subtotal = round((float) ($invoice->subtotal ?? $receivable->subtotal ?? 0), 6);

            if ($total <= 0) {
                throw new RuntimeException('El total de la factura debe ser mayor a cero para reclasificar CxC.');
            }

            if ($subtotal <= 0) {
                $subtotal = max(0, round($total - $tax, 6));
            }

            $currency = (string) ($invoice->currency_code ?: ($receivable->currency ?: 'MXN'));
            $journal = $this->resolveJournal($companyId);

            $unbilledReceivable = $this->resolveUnbilledReceivableAccount($companyId);
            $unbilledRevenue = $this->resolveUnbilledRevenueAccount($companyId);
            $customerAccount = $this->resolveCustomerReceivableAccount($companyId);
            $revenueAccount = $this->resolveSalesRevenueAccount($companyId);
            $taxAccount = $tax > 0 ? $this->resolveTaxPayableAccount($companyId) : null;

            $entryNumber = $this->buildEntryNumber($journal, 'CXC-REC', (int) $receivable->id);
            $label = 'Reclasificación CxC factura ' . ($invoice->number ?? ('#' . $invoiceId));
            $entryDate = $invoice->invoice_date ?: ($receivable->issue_date ?: now()->toDateString());

            $totalDebit = round($total + $total, 6);
            $totalCredit = round($total + $subtotal + $tax, 6);

            $entryId = DB::table('accounting_entries')->insertGetId([
                'company_id' => $companyId,
                'journal_id' => $journal?->id,
                'entry_number' => $entryNumber,
                'entry_date' => $entryDate,
                'status' => 'posted',
                'source_type' => 'account_receivable_invoice_reclassification',
                'source_id' => $receivable->id,
                'source_label' => $label,
                'currency' => $currency,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'posted_at' => now(),
                'cancelled_at' => null,
                'cancelled_by_entry_id' => null,
                'created_by_user_id' => $userId ?: ($receivable->created_by_user_id ?? null),
                'posted_by_user_id' => $userId,
                'notes' => 'Reclasificación automática: CxC puente de venta entregada a CxC por factura.',
                'metadata' => json_encode([
                    'created_by_patch' => 'v5.57.3c',
                    'account_receivable_id' => $receivable->id,
                    'account_receivable_number' => $receivable->number,
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoice->number ?? null,
                    'sale_order_id' => $receivable->sale_order_id,
                    'subtotal' => $subtotal,
                    'tax_total' => $tax,
                    'total' => $total,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $line = 1;

            $this->createLine(
                $entryId,
                $companyId,
                (int) $unbilledRevenue->id,
                $line++,
                'Cargo a ventas por facturar - ' . $label,
                $total,
                0,
                $currency,
                $receivable->customer_contact_id ?? null,
                (int) $receivable->id
            );

            $this->createLine(
                $entryId,
                $companyId,
                (int) $unbilledReceivable->id,
                $line++,
                'Abono a clientes por facturar - ' . $label,
                0,
                $total,
                $currency,
                $receivable->customer_contact_id ?? null,
                (int) $receivable->id
            );

            $this->createLine(
                $entryId,
                $companyId,
                (int) $customerAccount->id,
                $line++,
                'Cargo a clientes nacionales - ' . $label,
                $total,
                0,
                $currency,
                $receivable->customer_contact_id ?? null,
                (int) $receivable->id
            );

            if ($subtotal > 0) {
                $this->createLine(
                    $entryId,
                    $companyId,
                    (int) $revenueAccount->id,
                    $line++,
                    'Abono a ventas - ' . $label,
                    0,
                    $subtotal,
                    $currency,
                    $receivable->customer_contact_id ?? null,
                    (int) $receivable->id
                );
            }

            if ($tax > 0 && $taxAccount) {
                $this->createLine(
                    $entryId,
                    $companyId,
                    (int) $taxAccount->id,
                    $line,
                    'Abono a IVA trasladado - ' . $label,
                    0,
                    $tax,
                    $currency,
                    $receivable->customer_contact_id ?? null,
                    (int) $receivable->id
                );
            }

            $this->assertEntryBalances($entryId);
            $this->markReceivablePosted($receivable, $entryId);

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
        int $receivableId
    ): void {
        $account = DB::table('accounting_accounts')
            ->where('company_id', $companyId)
            ->where('id', $accountId)
            ->first();

        if (! $account) {
            throw new RuntimeException('No se encontró la cuenta contable para la reclasificación CxC.');
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
            'source_type' => 'account_receivable_invoice_reclassification',
            'source_id' => $receivableId,
            'metadata' => json_encode([
                'account_code' => $account->code,
                'account_name' => $account->name,
                'created_by_patch' => 'v5.57.3c',
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
            throw new RuntimeException('La reclasificación CxC no cuadra: debe=' . $debit . ', haber=' . $credit . '.');
        }

        DB::table('accounting_entries')
            ->where('id', $entryId)
            ->update([
                'total_debit' => $debit,
                'total_credit' => $credit,
                'updated_at' => now(),
            ]);
    }

    protected function markReceivablePosted(object $receivable, int $entryId): void
    {
        DB::table('account_receivables')
            ->where('id', $receivable->id)
            ->update([
                'accounting_status' => 'posted',
                'accounting_entry_id' => $entryId,
                'accounting_posted_at' => now(),
                'accounting_error_message' => null,
                'updated_at' => now(),
            ]);
    }

    protected function resolveCustomerReceivableAccount(int $companyId): object
    {
        return $this->resolveMappedOrAccount($companyId, 'sales', 'invoice', 'customer_receivable', [
            'account_usage' => ['receivable', 'customer_receivable', 'accounts_receivable'],
            'names' => ['%clientes nacionales%', '%clientes%', '%cuentas por cobrar%'],
            'codes' => ['105.%', '110.%'],
            'error' => 'No se encontró cuenta de clientes nacionales.',
        ]);
    }

    protected function resolveUnbilledReceivableAccount(int $companyId): object
    {
        return $this->resolveMappedOrAccount($companyId, 'sales', 'sales_order_delivery', 'ar_unbilled_receivable', [
            'account_usage' => ['unbilled_receivable', 'customer_unbilled_receivable', 'receivable_bridge'],
            'names' => ['%clientes por facturar%', '%cxc puente%', '%cuentas por cobrar por facturar%'],
            'codes' => ['110.%'],
            'error' => 'No se encontró cuenta puente de clientes por facturar.',
        ]);
    }

    protected function resolveUnbilledRevenueAccount(int $companyId): object
    {
        return $this->resolveMappedOrAccount($companyId, 'sales', 'sales_order_delivery', 'ar_unbilled_revenue', [
            'account_usage' => ['unbilled_revenue', 'sales_to_invoice', 'revenue_bridge'],
            'names' => ['%ventas por facturar%', '%ingresos por facturar%', '%puente ventas%'],
            'codes' => ['209.%'],
            'error' => 'No se encontró cuenta puente de ventas por facturar.',
        ]);
    }

    protected function resolveSalesRevenueAccount(int $companyId): object
    {
        return $this->resolveMappedOrAccount($companyId, 'sales', 'invoice', 'sales_revenue', [
            'account_usage' => ['sales_revenue', 'revenue', 'income'],
            'names' => ['%ventas gravadas%', '%ventas nacionales%', '%ventas%', '%ingresos%'],
            'codes' => ['401.%'],
            'error' => 'No se encontró cuenta de ventas.',
        ]);
    }

    protected function resolveTaxPayableAccount(int $companyId): object
    {
        return $this->resolveMappedOrAccount($companyId, 'sales', 'invoice', 'tax_payable', [
            'account_usage' => ['tax_payable', 'vat_payable', 'output_tax'],
            'names' => ['%iva traslad%', '%iva por pagar%', '%impuesto traslad%'],
            'codes' => ['207.%', '209.%', '210.%'],
            'error' => 'No se encontró cuenta de IVA trasladado.',
        ]);
    }

    protected function resolveMappedOrAccount(int $companyId, string $module, string $operationType, string $mappingKey, array $fallback): object
    {
        $mapped = DB::table('accounting_mappings as m')
            ->join('accounting_accounts as a', 'a.id', '=', 'm.account_id')
            ->where('m.company_id', $companyId)
            ->where('m.module', $module)
            ->where('m.operation_type', $operationType)
            ->where('m.mapping_key', $mappingKey)
            ->where('m.is_active', true)
            ->where('a.company_id', $companyId)
            ->where('a.is_active', true)
            ->orderBy('m.priority')
            ->first(['a.*']);

        if ($mapped) {
            return $mapped;
        }

        return $this->resolveAccount($companyId, $fallback);
    }

    protected function resolveAccount(int $companyId, array $config): object
    {
        $account = DB::table('accounting_accounts')
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
            ->orderBy('code')
            ->first();

        if (! $account) {
            throw new RuntimeException($config['error'] ?? 'No se encontró cuenta contable requerida.');
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
                $inner->whereIn('code', ['VTA', 'CXC', 'VEN', 'GEN'])
                    ->orWhere('name', 'ilike', '%venta%')
                    ->orWhere('name', 'ilike', '%cxc%')
                    ->orWhere('name', 'ilike', '%general%');
            })
            ->orderByRaw("case when code = 'VTA' then 0 when code = 'CXC' then 1 when code = 'GEN' then 2 else 3 end")
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
        $journalCode = $journal?->code ?: 'CXC';

        return $journalCode . '-' . $prefix . '-' . str_pad((string) $sourceId, 8, '0', STR_PAD_LEFT);
    }
}
