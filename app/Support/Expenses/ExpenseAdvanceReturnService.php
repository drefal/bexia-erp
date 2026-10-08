<?php

namespace App\Support\Expenses;

use App\Models\ExpenseAdvance;
use App\Models\PettyCashFund;
use App\Models\PettyCashFundMovement;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use App\Support\Treasury\TreasuryMovementPostingService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExpenseAdvanceReturnService
{
    public const SOURCE_TYPE =
        'expense_advance_return';

    public const FUND_MOVEMENT_TYPE =
        'advance_return';

    public function register(
        ExpenseAdvance $advance,
        ?int $userId = null
    ): ExpenseAdvance {
        return DB::transaction(
            function () use (
                $advance,
                $userId
            ): ExpenseAdvance {
                $locked = ExpenseAdvance::query()
                    ->whereKey($advance->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    (string) $locked->status
                    !== ExpenseAdvance::STATUS_PENDING_RETURN
                ) {
                    throw new RuntimeException(
                        'El anticipo no está pendiente '
                        . 'de devolución.'
                    );
                }

                $amount = round(
                    (float) $locked->return_due_amount,
                    6
                );

                if ($amount <= 0) {
                    throw new RuntimeException(
                        'El anticipo no tiene efectivo '
                        . 'pendiente de devolver.'
                    );
                }

                $fund = PettyCashFund::query()
                    ->whereKey(
                        $locked->petty_cash_fund_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    (int) $fund->company_id
                    !== (int) $locked->company_id
                ) {
                    throw new RuntimeException(
                        'La caja chica pertenece '
                        . 'a otra empresa.'
                    );
                }

                if (
                    ! $fund->is_active
                    || (string) $fund->status
                        !== 'active'
                ) {
                    throw new RuntimeException(
                        'La caja chica no está activa.'
                    );
                }

                if (! $fund->treasury_account_id) {
                    throw new RuntimeException(
                        'La caja chica no tiene '
                        . 'cuenta de Tesorería.'
                    );
                }

                $account = TreasuryAccount::query()
                    ->whereKey(
                        $fund->treasury_account_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    (int) $account->company_id
                    !== (int) $locked->company_id
                ) {
                    throw new RuntimeException(
                        'La cuenta de Tesorería pertenece '
                        . 'a otra empresa.'
                    );
                }

                if (! $account->is_active) {
                    throw new RuntimeException(
                        'La cuenta de Tesorería '
                        . 'no está activa.'
                    );
                }

                $treasuryBefore = round(
                    (float) $account->current_balance,
                    6
                );

                $fundBefore = round(
                    (float) $fund->operational_balance,
                    6
                );

                if (
                    abs(
                        $treasuryBefore
                        - $fundBefore
                    ) > 0.02
                ) {
                    throw new RuntimeException(
                        'El saldo operativo de Caja Chica '
                        . 'no coincide con Tesorería. '
                        . 'Caja: $'
                        . number_format(
                            $fundBefore,
                            2
                        )
                        . ' / Tesorería: $'
                        . number_format(
                            $treasuryBefore,
                            2
                        )
                        . '.'
                    );
                }

                /*
                 * Idempotencia:
                 * una única devolución financiera
                 * por anticipo.
                 */
                $movement = TreasuryMovement::query()
                    ->where(
                        'source_type',
                        self::SOURCE_TYPE
                    )
                    ->where(
                        'source_id',
                        $locked->id
                    )
                    ->whereIn(
                        'status',
                        ['draft', 'posted']
                    )
                    ->latest('id')
                    ->first();

                if (! $movement) {
                    $movement =
                        TreasuryMovement::query()
                            ->create([
                                'company_id' =>
                                    $locked->company_id,

                                'treasury_account_id' =>
                                    $account->id,

                                'payment_form_id' =>
                                    null,

                                'accounting_entry_id' =>
                                    null,

                                'type' =>
                                    'inbound',

                                'source_type' =>
                                    self::SOURCE_TYPE,

                                'source_id' =>
                                    $locked->id,

                                'movement_date' =>
                                    now()->toDateString(),

                                'amount' =>
                                    $amount,

                                'currency_code' =>
                                    $locked->currency_code
                                    ?: $fund->currency_code
                                    ?: 'MXN',

                                'reference' =>
                                    $locked->number,

                                'description' =>
                                    'Devolución de sobrante '
                                    . 'del anticipo '
                                    . $locked->number,

                                'status' =>
                                    'draft',

                                'created_by_user_id' =>
                                    $userId,

                                'metadata' => [
                                    'expense_advance_id' =>
                                        $locked->id,

                                    'expense_advance_number' =>
                                        $locked->number,

                                    'employee_id' =>
                                        $locked->employee_id,

                                    'petty_cash_fund_id' =>
                                        $fund->id,

                                    'return_amount' =>
                                        $amount,

                                    'posting_source' =>
                                        'expense_advance_return',
                                ],
                            ]);
                }

                if (
                    round(
                        (float) $movement->amount,
                        6
                    ) !== $amount
                ) {
                    throw new RuntimeException(
                        'Ya existe un movimiento de '
                        . 'devolución con un importe distinto.'
                    );
                }

                if (
                    (string) $movement->status
                    === 'draft'
                ) {
                    $movement = app(
                        TreasuryMovementPostingService::class
                    )->post(
                        $movement,
                        $userId
                    );
                }

                if (
                    (string) $movement->status
                    !== 'posted'
                ) {
                    throw new RuntimeException(
                        'No fue posible confirmar '
                        . 'la entrada a Tesorería.'
                    );
                }

                $account->refresh();

                $treasuryAfter = round(
                    (float) $account->current_balance,
                    6
                );

                $expectedAfter = round(
                    $treasuryBefore + $amount,
                    6
                );

                if (
                    abs(
                        $treasuryAfter
                        - $expectedAfter
                    ) > 0.02
                ) {
                    throw new RuntimeException(
                        'El saldo resultante de Tesorería '
                        . 'no coincide con la devolución.'
                    );
                }

                $pettyMovement =
                    PettyCashFundMovement::query()
                        ->where(
                            'petty_cash_fund_id',
                            $fund->id
                        )
                        ->where(
                            'treasury_movement_id',
                            $movement->id
                        )
                        ->first();

                if (! $pettyMovement) {
                    $pettyMovement =
                        PettyCashFundMovement::query()
                            ->create([
                                'company_id' =>
                                    $locked->company_id,

                                'petty_cash_fund_id' =>
                                    $fund->id,

                                'expense_report_id' =>
                                    null,

                                'type' =>
                                    self::FUND_MOVEMENT_TYPE,

                                'funding_source_id' =>
                                    null,

                                'movement_date' =>
                                    now()->toDateString(),

                                'amount' =>
                                    $amount,

                                'currency_code' =>
                                    $locked->currency_code
                                    ?: $fund->currency_code
                                    ?: 'MXN',

                                'balance_before' =>
                                    $fundBefore,

                                'balance_after' =>
                                    $treasuryAfter,

                                'reference' =>
                                    $locked->number,

                                'description' =>
                                    'Devolución de sobrante '
                                    . 'del anticipo '
                                    . $locked->number,

                                'treasury_movement_id' =>
                                    $movement->id,

                                'created_by_user_id' =>
                                    $userId,

                                'metadata' => [
                                    'expense_advance_id' =>
                                        $locked->id,

                                    'expense_advance_number' =>
                                        $locked->number,

                                    'employee_id' =>
                                        $locked->employee_id,

                                    'return_amount' =>
                                        $amount,

                                    'treasury_movement_id' =>
                                        $movement->id,
                                ],
                            ]);
                }

                /*
                 * Caja operativa siempre refleja
                 * el saldo real de Tesorería.
                 */
                $fund->forceFill([
                    'operational_balance' =>
                        $treasuryAfter,
                ])->save();

                $metadata = is_array(
                    $locked->metadata
                )
                    ? $locked->metadata
                    : [];

                $metadata['return'] = [
                    'amount' =>
                        $amount,

                    'treasury_movement_id' =>
                        $movement->id,

                    'petty_cash_fund_movement_id' =>
                        $pettyMovement->id,

                    'returned_by_user_id' =>
                        $userId,

                    'returned_at' =>
                        now()->toDateTimeString(),

                    'balance_before' =>
                        $fundBefore,

                    'balance_after' =>
                        $treasuryAfter,
                ];

                $locked->forceFill([
                    'status' =>
                        ExpenseAdvance::STATUS_CLOSED,

                    'return_due_amount' =>
                        0,

                    'return_treasury_movement_id' =>
                        $movement->id,

                    'return_petty_cash_fund_movement_id' =>
                        $pettyMovement->id,

                    'returned_at' =>
                        now(),

                    'returned_by_user_id' =>
                        $userId,

                    'closed_at' =>
                        now(),

                    'metadata' =>
                        $metadata,
                ])->save();

                return $locked->refresh();
            }
        );
    }
}
