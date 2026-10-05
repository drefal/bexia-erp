<?php

namespace App\Support\Expenses;

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

    public function requestInitialFunding(
        PettyCashFund $fund,
        float $amount,
        ?int $userId = null,
        ?string $notes = null
    ): TreasuryCashTransferRequest {
        return $this->requestInbound(
            $fund,
            $amount,
            self::ACTION_INITIAL_FUNDING,
            $userId,
            $notes
        );
    }

    public function requestReplenishment(
        PettyCashFund $fund,
        float $amount,
        ?int $userId = null,
        ?string $notes = null
    ): TreasuryCashTransferRequest {
        return $this->requestInbound(
            $fund,
            $amount,
            self::ACTION_REPLENISHMENT,
            $userId,
            $notes
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

    protected function requestInbound(
        PettyCashFund $fund,
        float $amount,
        string $action,
        ?int $userId,
        ?string $notes
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

        if (! $fund->funding_treasury_account_id) {
            throw new RuntimeException(
                'Primero configura la caja / cuenta origen de fondeo.'
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
            'source_treasury_account_id' => $fund->funding_treasury_account_id,
            'destination_treasury_account_id' => $fund->treasury_account_id,
            'type' => 'transfer',
            'amount' => $amount,
            'currency_code' => $fund->currency_code ?: 'MXN',
            'reason' => $label . ' de caja chica '
                . ($fund->number ?: ('#' . $fund->id)),
            'notes' => $notes,
            'requested_by_user_id' => $userId,
            'metadata' => $this->metadata($fund, $action),
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
