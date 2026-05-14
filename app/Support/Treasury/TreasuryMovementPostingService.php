<?php

namespace App\Support\Treasury;

use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use App\Support\Billing\InvoicePaymentTreasuryService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TreasuryMovementPostingService
{
    public function post(TreasuryMovement $movement, ?int $userId = null): TreasuryMovement
    {
        return DB::transaction(function () use ($movement, $userId): TreasuryMovement {
            $lockedMovement = TreasuryMovement::query()
                ->whereKey($movement->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $lockedMovement->status !== 'draft') {
                throw new RuntimeException('Solo se pueden confirmar movimientos en borrador.');
            }

            if (! in_array((string) $lockedMovement->type, ['inbound', 'outbound'], true)) {
                throw new RuntimeException('Por ahora solo se pueden confirmar movimientos de entrada o salida.');
            }

            $amount = (float) $lockedMovement->amount;

            if ($amount <= 0) {
                throw new RuntimeException('El importe debe ser mayor a cero.');
            }

            $account = TreasuryAccount::query()
                ->whereKey($lockedMovement->treasury_account_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $account->company_id !== (int) $lockedMovement->company_id) {
                throw new RuntimeException('La cuenta/caja no pertenece a la misma empresa del movimiento.');
            }

            $currentBalance = (float) $account->current_balance;

            $newBalance = match ((string) $lockedMovement->type) {
                'inbound' => $currentBalance + $amount,
                'outbound' => $currentBalance - $amount,
            };

            $account->forceFill([
                'current_balance' => $newBalance,
            ])->save();

            $lockedMovement->forceFill([
                'status' => 'posted',
                'posted_at' => now(),
                'created_by_user_id' => $lockedMovement->created_by_user_id ?: $userId,
            ])->save();

            // BEXIA_V5525C_SYNC_INVOICE_PAYMENT_POSTED
            $this->syncInvoicePaymentPosted($lockedMovement->refresh());

            return $lockedMovement->refresh();
        });
    }

    public function cancel(TreasuryMovement $movement): TreasuryMovement
    {
        return DB::transaction(function () use ($movement): TreasuryMovement {
            $lockedMovement = TreasuryMovement::query()
                ->whereKey($movement->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $lockedMovement->status === 'cancelled') {
                return $lockedMovement;
            }

            if (! in_array((string) $lockedMovement->status, ['draft', 'posted'], true)) {
                throw new RuntimeException('El movimiento no tiene un estado cancelable.');
            }

            if ((string) $lockedMovement->status === 'posted') {
                $account = TreasuryAccount::query()
                    ->whereKey($lockedMovement->treasury_account_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $amount = (float) $lockedMovement->amount;
                $currentBalance = (float) $account->current_balance;

                $newBalance = match ((string) $lockedMovement->type) {
                    'inbound' => $currentBalance - $amount,
                    'outbound' => $currentBalance + $amount,
                    default => throw new RuntimeException('Tipo de movimiento no soportado para reversa.'),
                };

                $account->forceFill([
                    'current_balance' => $newBalance,
                ])->save();
            }

            $lockedMovement->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ])->save();

            // BEXIA_V5525C_SYNC_INVOICE_PAYMENT_CANCELLED
            $this->syncInvoicePaymentCancelled($lockedMovement->refresh());

            return $lockedMovement->refresh();
        });
    }

    private function syncInvoicePaymentPosted(TreasuryMovement $movement): void
    {
        if ((string) $movement->source_type !== 'invoice_payment' || empty($movement->source_id)) {
            return;
        }

        app(InvoicePaymentTreasuryService::class)
            ->markPaymentPosted((int) $movement->source_id, $movement);
    }

    private function syncInvoicePaymentCancelled(TreasuryMovement $movement): void
    {
        if ((string) $movement->source_type !== 'invoice_payment' || empty($movement->source_id)) {
            return;
        }

        app(InvoicePaymentTreasuryService::class)
            ->markPaymentCancelled((int) $movement->source_id, $movement);
    }


}
