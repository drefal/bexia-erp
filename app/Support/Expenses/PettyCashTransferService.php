<?php

namespace App\Support\Expenses;

use App\Models\FundingSource;
use App\Models\PettyCashFund;
use App\Models\PettyCashFundMovement;
use App\Models\TreasuryAccount;
use App\Models\TreasuryCashTransferRequest;
use App\Support\Treasury\CashTransferService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PettyCashTransferService
{
    public const ACTION_INITIAL_FUNDING = 'initial_funding';
    public const ACTION_REPLENISHMENT = 'replenishment';
    public const ACTION_RETURN = 'return';

    public const ACTION_FUND_TRANSFER = 'fund_transfer';

    public function requestInitialFunding(
        PettyCashFund $fund,
        float $amount,
        ?int $userId = null,
        ?string $notes = null,
        ?int $fundingSourceId = null,
        ?int $sourceTreasuryAccountId = null
    ): TreasuryCashTransferRequest {
        return $this->requestInbound(
            $fund,
            $amount,
            self::ACTION_INITIAL_FUNDING,
            $userId,
            $notes,
            $fundingSourceId,
            $sourceTreasuryAccountId
        );
    }

    public function requestReplenishment(
        PettyCashFund $fund,
        float $amount,
        ?int $userId = null,
        ?string $notes = null,
        ?int $fundingSourceId = null,
        ?int $sourceTreasuryAccountId = null
    ): TreasuryCashTransferRequest {
        return $this->requestInbound(
            $fund,
            $amount,
            self::ACTION_REPLENISHMENT,
            $userId,
            $notes,
            $fundingSourceId,
            $sourceTreasuryAccountId
        );
    }

    public function requestReturn(
        PettyCashFund $fund,
        float $amount,
        ?int $userId = null,
        ?string $notes = null
    ): TreasuryCashTransferRequest {
        $amount = round($amount, 6);

        if ($amount <= 0) {
            throw new RuntimeException(
                'El importe a devolver debe ser mayor a cero.'
            );
        }

        $fund = PettyCashFund::query()
            ->with(['treasuryAccount', 'fundingTreasuryAccount'])
            ->findOrFail($fund->id);

        $this->assertFundCanOperate($fund);

        if (! $fund->funding_treasury_account_id) {
            throw new RuntimeException(
                'La caja chica no tiene configurada una caja origen/destino de fondeo.'
            );
        }

        $realBalance = round(
            (float) ($fund->treasuryAccount?->current_balance ?? 0),
            6
        );

        if ($amount > $realBalance + 0.000001) {
            throw new RuntimeException(
                'No se puede devolver más efectivo que el saldo real de la caja chica.'
            );
        }

        return app(CashTransferService::class)->createRequest([
            'company_id' => $fund->company_id,
            'branch_id' => $fund->employee?->branch_id,
            'source_treasury_account_id' => $fund->treasury_account_id,
            'destination_treasury_account_id' => $fund->funding_treasury_account_id,
            'type' => 'transfer',
            'amount' => $amount,
            'currency_code' => $fund->currency_code ?: 'MXN',
            'reason' => 'Devolución de efectivo de caja chica '
                . ($fund->number ?: ('#' . $fund->id)),
            'notes' => $notes,
            'requested_by_user_id' => $userId,
            'metadata' => $this->metadata(
                $fund,
                self::ACTION_RETURN
            ),
        ]);
    }

    public function requestFundTransfer(
        PettyCashFund $sourceFund,
        int $destinationFundId,
        float $amount,
        ?int $userId = null,
        ?string $reason = null,
        ?string $notes = null
    ): TreasuryCashTransferRequest {
        $amount = round($amount, 6);
        $reason = trim((string) $reason);

        if ($amount <= 0) {
            throw new RuntimeException(
                'El importe a transferir debe ser mayor a cero.'
            );
        }

        if ($reason === '') {
            throw new RuntimeException(
                'El motivo de la transferencia es obligatorio.'
            );
        }

        $sourceFund = PettyCashFund::query()
            ->with(['employee', 'treasuryAccount'])
            ->findOrFail($sourceFund->id);

        $this->assertFundCanOperate($sourceFund);

        if ((int) $sourceFund->id === $destinationFundId) {
            throw new RuntimeException(
                'La caja chica origen y destino no pueden ser la misma.'
            );
        }

        $destinationFund = PettyCashFund::query()
            ->with(['employee', 'treasuryAccount'])
            ->whereKey($destinationFundId)
            ->first();

        if (! $destinationFund) {
            throw new RuntimeException(
                'No se encontró la caja chica destino.'
            );
        }

        $this->assertFundCanOperate($destinationFund);

        if (
            (int) $sourceFund->company_id
            !== (int) $destinationFund->company_id
        ) {
            throw new RuntimeException(
                'Las cajas chicas deben pertenecer a la misma empresa.'
            );
        }

        if (
            strtoupper((string) $sourceFund->currency_code)
            !== strtoupper((string) $destinationFund->currency_code)
        ) {
            throw new RuntimeException(
                'Las cajas chicas deben manejar la misma moneda.'
            );
        }

        if (
            (int) $sourceFund->treasury_account_id
            === (int) $destinationFund->treasury_account_id
        ) {
            throw new RuntimeException(
                'Las cajas chicas no pueden compartir la misma cuenta de Tesorería.'
            );
        }

        $sourceBalance = round(
            (float) ($sourceFund->treasuryAccount?->current_balance ?? 0),
            6
        );

        if ($amount > $sourceBalance + 0.000001) {
            throw new RuntimeException(
                'La caja chica origen no tiene saldo suficiente. '
                . 'Saldo disponible: $'
                . number_format($sourceBalance, 2)
            );
        }

        $destinationBalance = round(
            (float) ($destinationFund->treasuryAccount?->current_balance ?? 0),
            6
        );

        $destinationAuthorized = round(
            (float) $destinationFund->authorized_amount,
            6
        );

        if (
            ($destinationBalance + $amount)
            > ($destinationAuthorized + 0.000001)
        ) {
            throw new RuntimeException(
                'La transferencia excedería el monto autorizado de la caja destino. '
                . 'Disponible para recibir: $'
                . number_format(
                    max(
                        0,
                        $destinationAuthorized - $destinationBalance
                    ),
                    2
                )
            );
        }

        return app(CashTransferService::class)->createRequest([
            'company_id' => $sourceFund->company_id,
            'branch_id' => $sourceFund->employee?->branch_id,
            'source_treasury_account_id' =>
                $sourceFund->treasury_account_id,
            'destination_treasury_account_id' =>
                $destinationFund->treasury_account_id,
            'type' => 'transfer',
            'amount' => $amount,
            'currency_code' =>
                $sourceFund->currency_code ?: 'MXN',
            'reason' =>
                'Transferencia de caja chica '
                . ($sourceFund->number ?: ('#' . $sourceFund->id))
                . ' a '
                . ($destinationFund->number ?: ('#' . $destinationFund->id))
                . ': '
                . $reason,
            'notes' => $notes,
            'requested_by_user_id' => $userId,
            'metadata' => [
                'module' => 'expenses',
                'source' => 'petty_cash',
                'petty_cash_action' => self::ACTION_FUND_TRANSFER,

                /*
                 * Conservamos petty_cash_fund_id apuntando al origen
                 * para compatibilidad con el selector actual de flujo.
                 */
                'petty_cash_fund_id' => $sourceFund->id,
                'petty_cash_number' => $sourceFund->number,

                'source_petty_cash_fund_id' => $sourceFund->id,
                'source_petty_cash_number' => $sourceFund->number,

                'destination_petty_cash_fund_id' =>
                    $destinationFund->id,
                'destination_petty_cash_number' =>
                    $destinationFund->number,

                'transfer_reason' => $reason,
            ],
        ]);
    }

    protected function requestInbound(
        PettyCashFund $fund,
        float $amount,
        string $action,
        ?int $userId,
        ?string $notes,
        ?int $fundingSourceId = null,
        ?int $sourceTreasuryAccountId = null
    ): TreasuryCashTransferRequest {
        $amount = round($amount, 6);

        if ($amount <= 0) {
            throw new RuntimeException(
                'El importe debe ser mayor a cero.'
            );
        }

        $fund = PettyCashFund::query()
            ->with(['employee', 'treasuryAccount', 'fundingTreasuryAccount'])
            ->findOrFail($fund->id);

        $this->assertFundCanOperate($fund);

        if (! $fundingSourceId) {
            throw new RuntimeException(
                'Selecciona un origen de fondos.'
            );
        }

        $fundingSource = FundingSource::query()
            ->whereKey($fundingSourceId)
            ->where('company_id', $fund->company_id)
            ->where('is_active', true)
            ->first();

        if (! $fundingSource) {
            throw new RuntimeException(
                'El origen de fondos seleccionado no es válido para esta empresa.'
            );
        }

        $sourceTreasuryAccountId =
            $sourceTreasuryAccountId
            ?: (
                $fund->funding_treasury_account_id
                    ? (int) $fund->funding_treasury_account_id
                    : null
            );

        if (! $sourceTreasuryAccountId) {
            throw new RuntimeException(
                'Selecciona una cuenta origen de Tesorería.'
            );
        }

        if (
            (int) $sourceTreasuryAccountId
            === (int) $fund->treasury_account_id
        ) {
            throw new RuntimeException(
                'La cuenta origen no puede ser la misma cuenta de la caja chica.'
            );
        }

        $sourceAccount = TreasuryAccount::query()
            ->whereKey($sourceTreasuryAccountId)
            ->where('company_id', $fund->company_id)
            ->where('is_active', true)
            ->first();

        if (! $sourceAccount) {
            throw new RuntimeException(
                'La cuenta origen seleccionada no es válida o no pertenece a esta empresa.'
            );
        }

        $realBalance = round(
            (float) ($fund->treasuryAccount?->current_balance ?? 0),
            6
        );

        $authorized = round(
            (float) $fund->authorized_amount,
            6
        );

        if (($realBalance + $amount) > ($authorized + 0.000001)) {
            throw new RuntimeException(
                'El fondeo excedería el monto autorizado de la caja chica. '
                . 'Disponible para fondear: $'
                . number_format(max(0, $authorized - $realBalance), 2)
            );
        }

        $label = $action === self::ACTION_INITIAL_FUNDING
            ? 'Fondeo inicial'
            : 'Reposición';

        return app(CashTransferService::class)->createRequest([
            'company_id' => $fund->company_id,
            'branch_id' => $fund->employee?->branch_id,
            'source_treasury_account_id' => $sourceTreasuryAccountId,
            'destination_treasury_account_id' => $fund->treasury_account_id,
            'type' => 'transfer',
            'amount' => $amount,
            'currency_code' => $fund->currency_code ?: 'MXN',
            'reason' => $label . ' de caja chica '
                . ($fund->number ?: ('#' . $fund->id)),
            'notes' => $notes,
            'requested_by_user_id' => $userId,
            'metadata' => array_merge(
                $this->metadata($fund, $action),
                [
                    'funding_source_id' => $fundingSourceId,
                    'source_treasury_account_id' =>
                        $sourceTreasuryAccountId,
                ]
            ),
        ]);
    }

    public function syncPostedTransfer(
        object $request,
        array $result,
        ?int $userId = null
    ): void {
        $metadata = $this->metadataArray($request->metadata ?? null);

        if (($metadata['module'] ?? null) !== 'expenses') {
            return;
        }

        if (($metadata['source'] ?? null) !== 'petty_cash') {
            return;
        }

        $fundId = (int) ($metadata['petty_cash_fund_id'] ?? 0);
        $action = (string) ($metadata['petty_cash_action'] ?? '');

        if ($action === self::ACTION_FUND_TRANSFER) {
            $this->syncFundToFundTransfer(
                $request,
                $result,
                $metadata,
                $userId
            );

            return;
        }

        $fundingSourceId = ! empty($metadata['funding_source_id'])
            ? (int) $metadata['funding_source_id']
            : null;

        if ($fundId <= 0) {
            return;
        }

        if (! in_array($action, [
            self::ACTION_INITIAL_FUNDING,
            self::ACTION_REPLENISHMENT,
            self::ACTION_RETURN,
        ], true)) {
            return;
        }

        $fund = PettyCashFund::query()
            ->whereKey($fundId)
            ->lockForUpdate()
            ->first();

        if (! $fund) {
            throw new RuntimeException(
                'No se encontró el fondo de caja chica ligado al traspaso.'
            );
        }

        if ((int) $fund->company_id !== (int) $request->company_id) {
            throw new RuntimeException(
                'La caja chica no pertenece a la empresa del traspaso.'
            );
        }

        $movementId = $action === self::ACTION_RETURN
            ? ($result['outflow_movement_id'] ?? null)
            : ($result['inflow_movement_id'] ?? null);

        if (! $movementId) {
            throw new RuntimeException(
                'No se encontró el movimiento de Tesorería correspondiente a la caja chica.'
            );
        }

        if (
            PettyCashFundMovement::query()
                ->where('petty_cash_fund_id', $fund->id)
                ->where('treasury_movement_id', $movementId)
                ->exists()
        ) {
            return;
        }

        $account = TreasuryAccount::query()
            ->whereKey($fund->treasury_account_id)
            ->lockForUpdate()
            ->firstOrFail();

        $before = round((float) $fund->operational_balance, 6);
        $after = round((float) $account->current_balance, 6);
        $amount = round((float) $request->amount, 6);

        PettyCashFundMovement::query()->create([
            'company_id' => $fund->company_id,
            'petty_cash_fund_id' => $fund->id,
            'expense_report_id' => null,
            'type' => $action,
            'funding_source_id' => $fundingSourceId,
            'movement_date' => now()->toDateString(),
            'amount' => $amount,
            'currency_code' => $request->currency_code ?: $fund->currency_code,
            'balance_before' => $before,
            'balance_after' => $after,
            'reference' => $request->number,
            'description' => $request->reason,
            'treasury_movement_id' => $movementId,
            'created_by_user_id' => $userId,
            'metadata' => [
                'treasury_cash_transfer_request_id' => $request->id,
                'outflow_movement_id' => $result['outflow_movement_id'] ?? null,
                'inflow_movement_id' => $result['inflow_movement_id'] ?? null,
            ],
        ]);

        $fund->forceFill([
            'operational_balance' => $after,
        ])->save();
    }

    protected function syncFundToFundTransfer(
        object $request,
        array $result,
        array $metadata,
        ?int $userId = null
    ): void {
        $sourceFundId = (int) (
            $metadata['source_petty_cash_fund_id']
            ?? $metadata['petty_cash_fund_id']
            ?? 0
        );

        $destinationFundId = (int) (
            $metadata['destination_petty_cash_fund_id']
            ?? 0
        );

        if ($sourceFundId <= 0 || $destinationFundId <= 0) {
            throw new RuntimeException(
                'La transferencia entre cajas chicas no tiene origen y destino válidos.'
            );
        }

        if ($sourceFundId === $destinationFundId) {
            throw new RuntimeException(
                'La caja chica origen y destino no pueden ser la misma.'
            );
        }

        /*
         * Bloqueamos siempre por ID ascendente para evitar deadlocks si
         * existen transferencias simultáneas en sentidos opuestos.
         */
        $funds = PettyCashFund::query()
            ->whereIn('id', [$sourceFundId, $destinationFundId])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $sourceFund = $funds->get($sourceFundId);
        $destinationFund = $funds->get($destinationFundId);

        if (! $sourceFund || ! $destinationFund) {
            throw new RuntimeException(
                'No se encontraron ambas cajas chicas del traspaso.'
            );
        }

        if (
            (int) $sourceFund->company_id
            !== (int) $request->company_id
            || (int) $destinationFund->company_id
            !== (int) $request->company_id
        ) {
            throw new RuntimeException(
                'Las cajas chicas no pertenecen a la empresa del traspaso.'
            );
        }

        $outflowMovementId =
            $result['outflow_movement_id'] ?? null;

        $inflowMovementId =
            $result['inflow_movement_id'] ?? null;

        if (! $outflowMovementId || ! $inflowMovementId) {
            throw new RuntimeException(
                'No se encontraron ambos movimientos de Tesorería del traspaso.'
            );
        }

        $sourceAccount = TreasuryAccount::query()
            ->whereKey($sourceFund->treasury_account_id)
            ->firstOrFail();

        $destinationAccount = TreasuryAccount::query()
            ->whereKey($destinationFund->treasury_account_id)
            ->firstOrFail();

        $amount = round((float) $request->amount, 6);

        $sourceBefore = round(
            (float) $sourceFund->operational_balance,
            6
        );

        $sourceAfter = round(
            (float) $sourceAccount->current_balance,
            6
        );

        $destinationBefore = round(
            (float) $destinationFund->operational_balance,
            6
        );

        $destinationAfter = round(
            (float) $destinationAccount->current_balance,
            6
        );

        $baseMetadata = [
            'treasury_cash_transfer_request_id' => $request->id,
            'outflow_movement_id' => $outflowMovementId,
            'inflow_movement_id' => $inflowMovementId,
            'source_petty_cash_fund_id' => $sourceFund->id,
            'destination_petty_cash_fund_id' =>
                $destinationFund->id,
        ];

        $sourceExists = PettyCashFundMovement::query()
            ->where(
                'petty_cash_fund_id',
                $sourceFund->id
            )
            ->where(
                'treasury_movement_id',
                $outflowMovementId
            )
            ->exists();

        if (! $sourceExists) {
            PettyCashFundMovement::query()->create([
                'company_id' => $sourceFund->company_id,
                'petty_cash_fund_id' => $sourceFund->id,
                'expense_report_id' => null,
                'type' => 'transfer_out',
                'funding_source_id' => null,
                'movement_date' => now()->toDateString(),
                'amount' => $amount,
                'currency_code' =>
                    $request->currency_code
                    ?: $sourceFund->currency_code,
                'balance_before' => $sourceBefore,
                'balance_after' => $sourceAfter,
                'reference' => $request->number,
                'description' =>
                    'Transferencia enviada a '
                    . (
                        $destinationFund->number
                        ?: ('#' . $destinationFund->id)
                    ),
                'treasury_movement_id' =>
                    $outflowMovementId,
                'created_by_user_id' => $userId,
                'metadata' => array_merge(
                    $baseMetadata,
                    [
                        'direction' => 'out',
                        'counterpart_petty_cash_fund_id' =>
                            $destinationFund->id,
                    ]
                ),
            ]);
        }

        $destinationExists = PettyCashFundMovement::query()
            ->where(
                'petty_cash_fund_id',
                $destinationFund->id
            )
            ->where(
                'treasury_movement_id',
                $inflowMovementId
            )
            ->exists();

        if (! $destinationExists) {
            PettyCashFundMovement::query()->create([
                'company_id' => $destinationFund->company_id,
                'petty_cash_fund_id' =>
                    $destinationFund->id,
                'expense_report_id' => null,
                'type' => 'transfer_in',
                'funding_source_id' => null,
                'movement_date' => now()->toDateString(),
                'amount' => $amount,
                'currency_code' =>
                    $request->currency_code
                    ?: $destinationFund->currency_code,
                'balance_before' => $destinationBefore,
                'balance_after' => $destinationAfter,
                'reference' => $request->number,
                'description' =>
                    'Transferencia recibida de '
                    . (
                        $sourceFund->number
                        ?: ('#' . $sourceFund->id)
                    ),
                'treasury_movement_id' =>
                    $inflowMovementId,
                'created_by_user_id' => $userId,
                'metadata' => array_merge(
                    $baseMetadata,
                    [
                        'direction' => 'in',
                        'counterpart_petty_cash_fund_id' =>
                            $sourceFund->id,
                    ]
                ),
            ]);
        }

        $sourceFund->forceFill([
            'operational_balance' => $sourceAfter,
        ])->save();

        $destinationFund->forceFill([
            'operational_balance' => $destinationAfter,
        ])->save();
    }

    protected function assertFundCanOperate(PettyCashFund $fund): void
    {
        if (! $fund->is_active) {
            throw new RuntimeException(
                'La caja chica está inactiva.'
            );
        }

        if ((string) $fund->status !== 'active') {
            throw new RuntimeException(
                'La caja chica no se encuentra en estado Activa.'
            );
        }

        if (! $fund->treasuryAccount) {
            throw new RuntimeException(
                'La caja chica no tiene cuenta de Tesorería.'
            );
        }

        if (! $fund->treasuryAccount->is_active) {
            throw new RuntimeException(
                'La cuenta de Tesorería de la caja chica está inactiva.'
            );
        }
    }

    protected function metadata(
        PettyCashFund $fund,
        string $action
    ): array {
        return [
            'module' => 'expenses',
            'source' => 'petty_cash',
            'petty_cash_fund_id' => $fund->id,
            'petty_cash_number' => $fund->number,
            'petty_cash_action' => $action,
        ];
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
}
