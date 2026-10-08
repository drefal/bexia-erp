<?php

namespace App\Support\Expenses;

use App\Models\ExpenseReport;
use App\Models\PettyCashFund;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use App\Support\Treasury\TreasuryMovementPostingService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExpenseReportPostingService
{
    public const SOURCE_TYPE = 'expense_report';
    public const FUND_MOVEMENT_TYPE = 'expense';

    public function postApprovedPettyCash(
        ExpenseReport $report,
        ?int $userId = null
    ): ExpenseReport {
        return DB::transaction(function () use (
            $report,
            $userId
        ): ExpenseReport {
            $locked = ExpenseReport::query()
                ->whereKey($report->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Los reembolsos siguen otro flujo.
             */
            if (
                (string) $locked->type
                !== ExpenseReport::TYPE_PETTY_CASH
            ) {
                return $locked->refresh();
            }

            /*
             * COMPROBACION DE ANTICIPO
             *
             * El dinero ya salió de Caja Chica al momento
             * de entregar el anticipo. La aprobación de la
             * comprobación es exclusivamente documental:
             * NO crea otra salida de Tesorería y NO crea
             * otro movimiento expense.
             */
            if ($locked->expense_advance_id) {
                $metadata = is_array($locked->metadata)
                    ? $locked->metadata
                    : [];

                $metadata['advance_reconciliation'] = [
                    'expense_advance_id' =>
                        (int) $locked->expense_advance_id,
                    'financial_posting_required' =>
                        false,
                    'approved_amount' =>
                        round(
                            (float) $locked->total_amount,
                            6
                        ),
                    'approved_at' =>
                        now()->toDateTimeString(),
                ];

                $locked->forceFill([
                    'payment_treasury_account_id' =>
                        null,
                    'treasury_movement_id' =>
                        null,
                    'metadata' =>
                        $metadata,
                ])->save();

                return $locked->refresh();
            }

            if (
                (string) $locked->status !== 'approved'
                || (string) $locked->approval_status !== 'approved'
            ) {
                throw new RuntimeException(
                    'La comprobación debe estar aprobada antes de afectar Caja Chica.'
                );
            }

            if (! $locked->petty_cash_fund_id) {
                throw new RuntimeException(
                    'La comprobación no tiene una Caja Chica asociada.'
                );
            }

            $amount = round(
                (float) $locked->total_amount,
                6
            );

            if ($amount <= 0) {
                throw new RuntimeException(
                    'El importe de la comprobación debe ser mayor a cero.'
                );
            }

            $fund = PettyCashFund::query()
                ->whereKey($locked->petty_cash_fund_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (int) $fund->company_id
                !== (int) $locked->company_id
            ) {
                throw new RuntimeException(
                    'La Caja Chica pertenece a otra empresa.'
                );
            }

            if (
                ! $fund->is_active
                || (string) $fund->status !== 'active'
            ) {
                throw new RuntimeException(
                    'La Caja Chica no está activa.'
                );
            }

            if (! $fund->treasury_account_id) {
                throw new RuntimeException(
                    'La Caja Chica no tiene cuenta de Tesorería asociada.'
                );
            }

            $account = TreasuryAccount::query()
                ->whereKey($fund->treasury_account_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (int) $account->company_id
                !== (int) $locked->company_id
            ) {
                throw new RuntimeException(
                    'La cuenta de Tesorería pertenece a otra empresa.'
                );
            }

            /*
             * Tesorería es la fuente financiera autoritativa.
             * La caja operativa debe estar conciliada antes de postear.
             */
            $treasuryBefore = round(
                (float) $account->current_balance,
                6
            );

            $fundBefore = round(
                (float) $fund->operational_balance,
                6
            );

            if (
                abs($treasuryBefore - $fundBefore)
                > 0.02
            ) {
                throw new RuntimeException(
                    'El saldo operativo de Caja Chica no coincide con Tesorería. '
                    . 'Caja: $'
                    . number_format($fundBefore, 2)
                    . ' / Tesorería: $'
                    . number_format($treasuryBefore, 2)
                    . '.'
                );
            }

            /*
             * Idempotencia:
             * si ya existe una salida viva para este reporte,
             * la reutilizamos.
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
                ->where('type', 'outbound')
                ->whereIn(
                    'status',
                    ['draft', 'posted']
                )
                ->latest('id')
                ->first();

            if (! $movement) {
                if ($treasuryBefore + 0.000001 < $amount) {
                    throw new RuntimeException(
                        'Saldo insuficiente en Caja Chica. '
                        . 'Disponible: $'
                        . number_format($treasuryBefore, 2)
                        . ' / Gasto: $'
                        . number_format($amount, 2)
                        . '.'
                    );
                }

                $movement = TreasuryMovement::query()
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
                            'outbound',

                        'source_type' =>
                            self::SOURCE_TYPE,

                        'source_id' =>
                            $locked->id,

                        'movement_date' =>
                            $locked->report_date
                                ?: now()->toDateString(),

                        'amount' =>
                            $amount,

                        'currency_code' =>
                            $locked->currency_code
                                ?: $fund->currency_code
                                ?: 'MXN',

                        'reference' =>
                            $locked->number,

                        'description' =>
                            'Comprobación de Caja Chica '
                            . ($locked->number ?: '#'.$locked->id),

                        'status' =>
                            'draft',

                        'created_by_user_id' =>
                            $userId
                                ?: $locked->approved_by_user_id,

                        'metadata' => [
                            'expense_report_id' =>
                                $locked->id,

                            'petty_cash_fund_id' =>
                                $fund->id,

                            'approval_request_id' =>
                                $locked->approval_request_id,

                            'posting_source' =>
                                'expense_report_approved',
                        ],
                    ]);
            }

            /*
             * Si ya estaba posted, NO volvemos a postear.
             */
            if ((string) $movement->status === 'draft') {
                $movement = app(
                    TreasuryMovementPostingService::class
                )->post(
                    $movement,
                    $userId
                        ?: $locked->approved_by_user_id
                );
            }

            if ((string) $movement->status !== 'posted') {
                throw new RuntimeException(
                    'No fue posible confirmar la salida de Tesorería.'
                );
            }

            $account->refresh();

            $treasuryAfter = round(
                (float) $account->current_balance,
                6
            );

            /*
             * Ledger de Caja Chica.
             * Solo una línea "expense" por reporte.
             */
            $fundMovement = DB::table(
                'petty_cash_fund_movements'
            )
                ->where(
                    'expense_report_id',
                    $locked->id
                )
                ->where(
                    'type',
                    self::FUND_MOVEMENT_TYPE
                )
                ->first();

            if (! $fundMovement) {
                DB::table(
                    'petty_cash_fund_movements'
                )->insert([
                    'company_id' =>
                        $locked->company_id,

                    'petty_cash_fund_id' =>
                        $fund->id,

                    'expense_report_id' =>
                        $locked->id,

                    'type' =>
                        self::FUND_MOVEMENT_TYPE,

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
                        'Gasto aprobado de Caja Chica',

                    'treasury_movement_id' =>
                        $movement->id,

                    'created_by_user_id' =>
                        $userId
                            ?: $locked->approved_by_user_id,

                    'metadata' =>
                        json_encode(
                            [
                                'expense_report_id' =>
                                    $locked->id,

                                'approval_request_id' =>
                                    $locked->approval_request_id,

                                'treasury_movement_id' =>
                                    $movement->id,
                            ],
                            JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                        ),

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);
            }

            /*
             * Caja operativa = saldo real de Tesorería.
             */
            $fund->forceFill([
                'operational_balance' =>
                    $treasuryAfter,
            ])->save();

            $metadata = is_array($locked->metadata)
                ? $locked->metadata
                : [];

            /*
             * La auditoría original no debe cambiar en reintentos.
             * Tomamos balance_before / balance_after del ledger,
             * que representa el evento financiero real.
             */
            $ledgerRow = DB::table(
                'petty_cash_fund_movements'
            )
                ->where(
                    'expense_report_id',
                    $locked->id
                )
                ->where(
                    'type',
                    self::FUND_MOVEMENT_TYPE
                )
                ->first();

            $existingPosting = is_array(
                $metadata['petty_cash_posting'] ?? null
            )
                ? $metadata['petty_cash_posting']
                : [];

            $metadata['petty_cash_posting'] = [
                'posted' =>
                    true,

                'treasury_movement_id' =>
                    $movement->id,

                'petty_cash_fund_id' =>
                    $fund->id,

                'amount' =>
                    $ledgerRow?->amount
                        ?? $existingPosting['amount']
                        ?? $amount,

                'balance_before' =>
                    $ledgerRow?->balance_before
                        ?? $existingPosting['balance_before']
                        ?? $fundBefore,

                'balance_after' =>
                    $ledgerRow?->balance_after
                        ?? $existingPosting['balance_after']
                        ?? $treasuryAfter,

                'posted_at' =>
                    $existingPosting['posted_at']
                        ?? optional(
                            $movement->posted_at
                        )?->toDateTimeString()
                        ?? now()->toDateTimeString(),
            ];

            $locked->forceFill([
                'payment_treasury_account_id' =>
                    $account->id,

                'treasury_movement_id' =>
                    $movement->id,

                'metadata' =>
                    $metadata,
            ])->save();

            return $locked->refresh();
        });
    }
}
