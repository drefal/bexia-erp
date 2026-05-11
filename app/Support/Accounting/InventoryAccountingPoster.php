<?php

namespace App\Support\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingEntry;
use App\Models\AccountingEntryLine;
use App\Models\AccountingInventoryValuationLayer;
use App\Models\AccountingJournal;
use App\Models\AccountingMapping;
use App\Models\AccountingPostingAudit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class InventoryAccountingPoster
{
    private array $operationDefinitions = [
        'purchase_receipt' => [
            'label' => 'Entrada por compra',
            'direction' => 'in',
            'debit' => 'inventory_asset',
            'credit' => 'inventory_purchase_clearing',
        ],
        'sale_issue' => [
            'label' => 'Salida por venta / costo',
            'direction' => 'out',
            'debit' => 'cost_of_goods_sold',
            'credit' => 'inventory_asset',
        ],
        'adjustment_in' => [
            'label' => 'Ajuste positivo de inventario',
            'direction' => 'in',
            'debit' => 'inventory_asset',
            'credit' => 'inventory_adjustment_gain',
        ],
        'adjustment_out' => [
            'label' => 'Ajuste negativo de inventario',
            'direction' => 'out',
            'debit' => 'inventory_adjustment_loss',
            'credit' => 'inventory_asset',
        ],
        'customer_return' => [
            'label' => 'Devolución de cliente a inventario',
            'direction' => 'in',
            'debit' => 'inventory_asset',
            'credit' => 'cost_of_goods_sold',
        ],
        'supplier_return' => [
            'label' => 'Devolución a proveedor desde inventario',
            'direction' => 'out',
            'debit' => 'inventory_purchase_clearing',
            'credit' => 'inventory_asset',
        ],
    ];

    public function availableOperations(): array
    {
        return array_keys($this->operationDefinitions);
    }

    public function diagnoseMappings(int $companyId): array
    {
        $result = [];

        foreach ($this->operationDefinitions as $operation => $definition) {
            foreach (['debit', 'credit'] as $side) {
                $key = $definition[$side];

                try {
                    $account = $this->resolveAccount($companyId, $operation, $key);

                    $result[$operation][$side] = [
                        'mapping_key' => $key,
                        'ok' => true,
                        'account_id' => $account->id,
                        'account_code' => $account->code ?? null,
                        'account_name' => $account->name ?? null,
                    ];
                } catch (Throwable $e) {
                    $result[$operation][$side] = [
                        'mapping_key' => $key,
                        'ok' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return $result;
    }

    public function post(array $payload, ?int $userId = null): AccountingEntry
    {
        $companyId = (int) ($payload['company_id'] ?? 0);
        $operation = (string) ($payload['operation_type'] ?? '');
        $amount = round((float) ($payload['amount'] ?? 0), 6);

        if ($companyId <= 0) {
            throw new RuntimeException('company_id es requerido para contabilizar inventario.');
        }

        if (! isset($this->operationDefinitions[$operation])) {
            throw new RuntimeException('Operación de inventario no soportada: ' . $operation);
        }

        if ($amount <= 0) {
            throw new RuntimeException('El importe de costo debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($payload, $userId, $companyId, $operation, $amount): AccountingEntry {
            $sourceType = (string) ($payload['source_type'] ?? 'manual_inventory');
            $sourceId = isset($payload['source_id']) && $payload['source_id'] !== '' ? (int) $payload['source_id'] : null;
            $sourceLineId = isset($payload['source_line_id']) && $payload['source_line_id'] !== '' ? (int) $payload['source_line_id'] : null;

            if ($sourceId) {
                $existing = AccountingEntry::query()
                    ->where('company_id', $companyId)
                    ->where('source_type', $this->entrySourceType($operation, $sourceType))
                    ->where('source_id', $sourceId)
                    ->whereIn('status', ['draft', 'posted'])
                    ->first();

                if ($existing) {
                    throw new RuntimeException('Este movimiento de inventario ya fue contabilizado en el asiento ' . $existing->entry_number . '.');
                }
            }

            $definition = $this->operationDefinitions[$operation];

            $journal = $this->resolveJournal($companyId);
            $debitAccount = $this->resolveAccount($companyId, $operation, $definition['debit']);
            $creditAccount = $this->resolveAccount($companyId, $operation, $definition['credit']);

            $movementDate = $this->resolveDate($payload['movement_date'] ?? null);
            $currency = (string) ($payload['currency'] ?? 'MXN');
            $quantity = round((float) ($payload['quantity'] ?? 0), 6);
            $unitCost = round((float) ($payload['unit_cost'] ?? ($quantity > 0 ? $amount / $quantity : 0)), 6);
            $label = (string) ($payload['label'] ?? $definition['label']);
            $entryNumber = $this->buildEntryNumber($journal, $operation, $sourceId);

            $entry = AccountingEntry::query()->create([
                'company_id' => $companyId,
                'journal_id' => $journal?->id,
                'entry_number' => $entryNumber,
                'entry_date' => $movementDate->toDateString(),
                'status' => 'posted',
                'source_type' => $this->entrySourceType($operation, $sourceType),
                'source_id' => $sourceId,
                'source_label' => $label,
                'currency' => $currency,
                'total_debit' => $amount,
                'total_credit' => $amount,
                'posted_at' => now(),
                'created_by_user_id' => $userId,
                'posted_by_user_id' => $userId,
                'notes' => 'Asiento automático de inventario perpetuo RC1.',
                'metadata' => [
                    'operation_type' => $operation,
                    'source_type_original' => $sourceType,
                    'source_id' => $sourceId,
                    'source_line_id' => $sourceLineId,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                ],
            ]);

            $this->createLine($entry, $debitAccount, 1, 'Debe - ' . $label, $amount, 0, $currency, $operation, $sourceId);
            $this->createLine($entry, $creditAccount, 2, 'Haber - ' . $label, 0, $amount, $currency, $operation, $sourceId);

            $this->createValuationLayer(
                $entry,
                $payload,
                $definition,
                $operation,
                $movementDate,
                $quantity,
                $unitCost,
                $amount,
                $currency,
                $label,
                $sourceType,
                $sourceId,
                $sourceLineId
            );

            $this->assertEntryBalances($entry);
            $this->markSourceAsPosted($sourceType, $sourceId, $entry->id);

            $this->audit(
                $companyId,
                $sourceType,
                $sourceId,
                $entry->id,
                'post_inventory_' . $operation,
                'success',
                'Movimiento de inventario contabilizado.',
                $payload,
                [
                    'entry_id' => $entry->id,
                    'entry_number' => $entry->entry_number,
                ],
                $userId
            );

            return $entry;
        });
    }

    private function resolveJournal(int $companyId): ?AccountingJournal
    {
        $query = AccountingJournal::query()->where('company_id', $companyId);

        if (Schema::hasColumn('accounting_journals', 'is_active')) {
            $query->where('is_active', true);
        }

        $query->where(function ($inner) {
            $inner->whereIn('code', ['INV', 'ALM', 'INVPERP']);

            if (Schema::hasColumn('accounting_journals', 'type')) {
                $inner->orWhereIn('type', ['inventory', 'warehouse', 'stock']);
            }
        });

        $journal = $query
            ->orderByRaw("case when code = 'INV' then 0 when code = 'ALM' then 1 else 2 end")
            ->first();

        if ($journal) {
            return $journal;
        }

        $fallback = AccountingJournal::query()->where('company_id', $companyId);

        if (Schema::hasColumn('accounting_journals', 'is_active')) {
            $fallback->where('is_active', true);
        }

        return $fallback->orderBy('id')->first();
    }

    private function resolveAccount(int $companyId, string $operation, string $mappingKey): AccountingAccount
    {
        $mapped = AccountingMapping::query()
            ->where('company_id', $companyId)
            ->where('module', 'inventory')
            ->where('operation_type', $operation)
            ->where('mapping_key', $mappingKey)
            ->where('is_active', true)
            ->orderBy('priority')
            ->first();

        if ($mapped) {
            $account = AccountingAccount::query()
                ->where('company_id', $companyId)
                ->find($mapped->account_id);

            if ($account) {
                return $account;
            }
        }

        $globalMapped = AccountingMapping::query()
            ->where('company_id', $companyId)
            ->where('module', 'inventory')
            ->where('operation_type', '*')
            ->where('mapping_key', $mappingKey)
            ->where('is_active', true)
            ->orderBy('priority')
            ->first();

        if ($globalMapped) {
            $account = AccountingAccount::query()
                ->where('company_id', $companyId)
                ->find($globalMapped->account_id);

            if ($account) {
                return $account;
            }
        }

        $settingsAccount = $this->resolveAccountFromSettings($companyId, $mappingKey);

        if ($settingsAccount) {
            return $settingsAccount;
        }

        $usageAccount = $this->resolveAccountFromUsage($companyId, $mappingKey);

        if ($usageAccount) {
            return $usageAccount;
        }

        $codeAccount = $this->resolveAccountFromCodes($companyId, $mappingKey);

        if ($codeAccount) {
            return $codeAccount;
        }

        throw new RuntimeException(
            'Falta mapeo contable de inventario: operation=' . $operation
            . ', mapping_key=' . $mappingKey
            . ', company_id=' . $companyId . '.'
        );
    }

    private function resolveAccountFromSettings(int $companyId, string $mappingKey): ?AccountingAccount
    {
        if (! Schema::hasTable('accounting_settings')) {
            return null;
        }

        $fieldCandidates = [
            'inventory_asset' => ['inventory_asset_account_id', 'inventory_account_id', 'stock_account_id'],
            'cost_of_goods_sold' => ['cost_of_goods_sold_account_id', 'cogs_account_id', 'cost_account_id'],
            'inventory_purchase_clearing' => ['inventory_purchase_clearing_account_id', 'purchase_clearing_account_id', 'accounts_payable_account_id', 'supplier_payable_account_id'],
            'inventory_adjustment_gain' => ['inventory_adjustment_gain_account_id', 'inventory_gain_account_id', 'other_income_account_id'],
            'inventory_adjustment_loss' => ['inventory_adjustment_loss_account_id', 'inventory_loss_account_id', 'expense_account_id'],
        ];

        $settings = DB::table('accounting_settings')
            ->where('company_id', $companyId)
            ->first();

        if (! $settings) {
            return null;
        }

        foreach ($fieldCandidates[$mappingKey] ?? [] as $field) {
            if (! Schema::hasColumn('accounting_settings', $field)) {
                continue;
            }

            $accountId = $settings->{$field} ?? null;

            if ($accountId) {
                $account = AccountingAccount::query()
                    ->where('company_id', $companyId)
                    ->find($accountId);

                if ($account) {
                    return $account;
                }
            }
        }

        return null;
    }

    private function resolveAccountFromUsage(int $companyId, string $mappingKey): ?AccountingAccount
    {
        if (! Schema::hasColumn('accounting_accounts', 'account_usage')) {
            return null;
        }

        $usageCandidates = [
            'inventory_asset' => ['inventory_asset', 'stock_asset', 'inventory'],
            'cost_of_goods_sold' => ['cost_of_goods_sold', 'cogs', 'cost_sales'],
            'inventory_purchase_clearing' => ['purchase_clearing', 'accounts_payable', 'supplier_payable'],
            'inventory_adjustment_gain' => ['inventory_adjustment_gain', 'other_income'],
            'inventory_adjustment_loss' => ['inventory_adjustment_loss', 'inventory_loss', 'expense'],
        ];

        $query = AccountingAccount::query()
            ->where('company_id', $companyId)
            ->whereIn('account_usage', $usageCandidates[$mappingKey] ?? []);

        if (Schema::hasColumn('accounting_accounts', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->orderBy('code')->first();
    }

    private function resolveAccountFromCodes(int $companyId, string $mappingKey): ?AccountingAccount
    {
        $codeCandidates = [
            'inventory_asset' => ['115.01', '115', '120.01', '118.01'],
            'cost_of_goods_sold' => ['501.01', '501', '500.01', '510.01'],
            'inventory_purchase_clearing' => ['201.01', '201', '210.01', '211.01'],
            'inventory_adjustment_gain' => ['402.99', '499.01', '701.01'],
            'inventory_adjustment_loss' => ['502.99', '599.01', '702.01'],
        ];

        $query = AccountingAccount::query()
            ->where('company_id', $companyId)
            ->whereIn('code', $codeCandidates[$mappingKey] ?? []);

        if (Schema::hasColumn('accounting_accounts', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->orderBy('code')->first();
    }

    private function createLine(
        AccountingEntry $entry,
        AccountingAccount $account,
        int $lineNumber,
        string $label,
        float $debit,
        float $credit,
        string $currency,
        string $operation,
        ?int $sourceId
    ): AccountingEntryLine {
        return AccountingEntryLine::query()->create([
            'company_id' => $entry->company_id,
            'accounting_entry_id' => $entry->id,
            'account_id' => $account->id,
            'line_number' => $lineNumber,
            'label' => $label,
            'debit' => round($debit, 6),
            'credit' => round($credit, 6),
            'currency' => $currency,
            'source_type' => 'inventory.' . $operation,
            'source_id' => $sourceId,
            'metadata' => [
                'account_code' => $account->code ?? null,
                'account_name' => $account->name ?? null,
                'operation_type' => $operation,
            ],
        ]);
    }

    private function createValuationLayer(
        AccountingEntry $entry,
        array $payload,
        array $definition,
        string $operation,
        Carbon $movementDate,
        float $quantity,
        float $unitCost,
        float $amount,
        string $currency,
        string $label,
        string $sourceType,
        ?int $sourceId,
        ?int $sourceLineId
    ): void {
        if (! Schema::hasTable('accounting_inventory_valuation_layers')) {
            return;
        }

        AccountingInventoryValuationLayer::query()->create([
            'company_id' => $entry->company_id,
            'product_id' => isset($payload['product_id']) && $payload['product_id'] !== '' ? (int) $payload['product_id'] : null,
            'accounting_entry_id' => $entry->id,
            'operation_type' => $operation,
            'direction' => $definition['direction'],
            'movement_date' => $movementDate->toDateString(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_line_id' => $sourceLineId,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $amount,
            'remaining_quantity' => $definition['direction'] === 'in' ? $quantity : 0,
            'currency' => $currency,
            'label' => $label,
            'metadata' => [
                'payload' => $payload,
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
            throw new RuntimeException('El asiento de inventario no cuadra: debe=' . $debit . ', haber=' . $credit . '.');
        }

        $entry->forceFill([
            'total_debit' => round($debit, 6),
            'total_credit' => round($credit, 6),
        ])->save();
    }

    private function markSourceAsPosted(string $sourceType, ?int $sourceId, int $entryId): void
    {
        if (! $sourceId || ! Schema::hasTable($sourceType)) {
            return;
        }

        $updates = [];

        if (Schema::hasColumn($sourceType, 'accounting_status')) {
            $updates['accounting_status'] = 'posted';
        }

        if (Schema::hasColumn($sourceType, 'accounting_entry_id')) {
            $updates['accounting_entry_id'] = $entryId;
        }

        if (Schema::hasColumn($sourceType, 'accounting_posted_at')) {
            $updates['accounting_posted_at'] = now();
        }

        if (Schema::hasColumn($sourceType, 'accounting_error_message')) {
            $updates['accounting_error_message'] = null;
        }

        if ($updates) {
            DB::table($sourceType)
                ->where('id', $sourceId)
                ->update($updates);
        }
    }

    private function audit(
        int $companyId,
        string $sourceType,
        ?int $sourceId,
        ?int $entryId,
        string $event,
        string $status,
        string $message,
        array $requestMeta,
        array $responseMeta,
        ?int $userId
    ): void {
        if (! Schema::hasTable('accounting_posting_audits')) {
            return;
        }

        AccountingPostingAudit::query()->create([
            'company_id' => $companyId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'accounting_entry_id' => $entryId,
            'event' => $event,
            'status' => $status,
            'message' => $message,
            'request_meta' => $requestMeta,
            'response_meta' => $responseMeta,
            'created_by_user_id' => $userId,
        ]);
    }

    private function resolveDate(mixed $value): Carbon
    {
        if ($value) {
            return Carbon::parse($value);
        }

        return now();
    }

    private function buildEntryNumber(?AccountingJournal $journal, string $operation, ?int $sourceId): string
    {
        $prefix = $journal?->code ?: 'INV';
        $operationCode = strtoupper(substr(str_replace('_', '', $operation), 0, 8));
        $idPart = $sourceId ? str_pad((string) $sourceId, 8, '0', STR_PAD_LEFT) : now()->format('YmdHis');

        return $prefix . '-' . $operationCode . '-' . $idPart;
    }

    private function entrySourceType(string $operation, string $sourceType): string
    {
        return 'inventory.' . $operation . ':' . $sourceType;
    }
}
