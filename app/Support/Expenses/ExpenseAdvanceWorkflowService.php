<?php

namespace App\Support\Expenses;

use App\Models\ExpenseAdvance;
use App\Models\PettyCashFund;
use App\Models\TreasuryAccount;
use App\Support\Treasury\CashTransferService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExpenseAdvanceWorkflowService
{
    public function submit(
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
                    !== ExpenseAdvance::STATUS_DRAFT
                ) {
                    throw new RuntimeException(
                        'Solo un anticipo en borrador '
                        . 'puede enviarse a aprobación.'
                    );
                }

                if (! $locked->number) {
                    $locked = app(
                        ExpenseAdvanceService::class
                    )->assignNumber($locked);
                }

                $fund = PettyCashFund::query()
                    ->whereKey(
                        $locked->petty_cash_fund_id
                    )
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $fund
                    || ! $fund->is_active
                    || (string) $fund->status !== 'active'
                ) {
                    throw new RuntimeException(
                        'La caja chica no está activa.'
                    );
                }

                if (
                    (int) $fund->company_id
                    !== (int) $locked->company_id
                ) {
                    throw new RuntimeException(
                        'La caja chica no pertenece '
                        . 'a la empresa del anticipo.'
                    );
                }

                $account = TreasuryAccount::query()
                    ->whereKey(
                        $fund->treasury_account_id
                    )
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $account
                    || ! $account->is_active
                ) {
                    throw new RuntimeException(
                        'La cuenta de Tesorería de '
                        . 'la caja chica no está activa.'
                    );
                }

                $amount = round(
                    (float) $locked->amount,
                    6
                );

                /*
                 * Caja chica es estricta:
                 * nunca permitimos anticipos superiores
                 * al efectivo realmente disponible.
                 */
                $actualBalance = round(
                    (float) $account->current_balance,
                    6
                );

                if (
                    $actualBalance + 0.000001
                    < $amount
                ) {
                    throw new RuntimeException(
                        'La caja chica no tiene saldo '
                        . 'suficiente para este anticipo.'
                    );
                }

                app(
                    ExpenseAdvanceService::class
                )->assertCanCreate(
                    (int) $locked->company_id,
                    (int) $locked->employee_id,
                    (int) $locked->id
                );

                $request = app(
                    CashTransferService::class
                )->createRequest([
                    'company_id' =>
                        $locked->company_id,

                    /*
                     * ANTICIPO:
                     * existe solamente cuenta origen.
                     * No creamos una cuenta ficticia
                     * para el empleado.
                     */
                    'source_treasury_account_id' =>
                        $fund->treasury_account_id,

                    'destination_treasury_account_id' =>
                        null,

                    'type' => 'expense_advance',

                    'amount' => $amount,

                    'currency_code' =>
                        $locked->currency_code
                        ?: $fund->currency_code
                        ?: 'MXN',

                    'reason' =>
                        'Anticipo '
                        . $locked->number
                        . ': '
                        . $locked->purpose,

                    'notes' => $locked->notes,

                    'requested_by_user_id' =>
                        $userId,

                    'metadata' => [
                        'module' => 'expenses',
                        'source' => 'petty_cash',
                        'petty_cash_action' =>
                            PettyCashTransferService::ACTION_ADVANCE,

                        /*
                         * Conservamos esta llave porque
                         * el selector actual de workflow
                         * de caja chica la utiliza.
                         */
                        'petty_cash_fund_id' =>
                            $fund->id,
                        'petty_cash_number' =>
                            $fund->number,

                        'expense_advance_id' =>
                            $locked->id,
                        'expense_advance_number' =>
                            $locked->number,

                        'employee_id' =>
                            $locked->employee_id,

                        'authorized_by_employee_id' =>
                            $locked->authorized_by_employee_id,

                        'due_days' =>
                            $locked->due_days,
                        'due_date' =>
                            $locked->due_date
                                ?->toDateString(),
                    ],
                ]);

                $locked->forceFill([
                    'status' =>
                        ExpenseAdvance::STATUS_PENDING_APPROVAL,

                    'treasury_cash_transfer_request_id' =>
                        $request->id,

                    'approval_request_id' =>
                        $request->approval_request_id,

                    'approval_status' =>
                        $request->approval_status
                        ?: 'pending',

                    'submitted_by_user_id' =>
                        $userId,

                    'submitted_at' =>
                        now(),
                ])->save();

                return $locked->refresh();
            }
        );
    }
}
