<?php

namespace App\Support\Expenses;

use App\Models\ExpenseAdvance;
use App\Models\ExpenseReport;
use App\Support\Security\BexiaTenantPermission;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExpenseDraftCancellationService
{
    public static function canCancel(
        ExpenseAdvance|ExpenseReport $record
    ): bool {
        $companyId = (int) (
            Filament::getTenant()?->getKey() ?? 0
        );

        if (
            ! auth()->check()
            || $companyId <= 0
            || (int) $record->company_id !== $companyId
            || (string) $record->status !== 'draft'
        ) {
            return false;
        }

        $authorized = ExpenseAccess::isAdmin()
            || BexiaTenantPermission::can('expenses.update');

        return $authorized
            && ExpenseAccess::canAccessEmployee(
                $companyId,
                (int) $record->employee_id
            );
    }

    public function cancel(
        ExpenseAdvance|ExpenseReport $record,
        string $reason
    ): void {
        $reason = trim($reason);

        if (
            $reason === ''
            || mb_strlen($reason) > 1000
        ) {
            throw new RuntimeException(
                'Es obligatorio indicar un motivo válido.'
            );
        }

        if (! self::canCancel($record)) {
            throw new RuntimeException(
                'No tienes permiso para cancelar este documento.'
            );
        }

        DB::transaction(function () use ($record, $reason): void {
            $locked = $record instanceof ExpenseAdvance
                ? ExpenseAdvance::query()
                    ->whereKey($record->getKey())
                    ->lockForUpdate()
                    ->firstOrFail()
                : ExpenseReport::query()
                    ->whereKey($record->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

            if (
                (string) $locked->status !== 'draft'
                || (int) $locked->company_id
                    !== (int) $record->company_id
                || (int) $locked->employee_id
                    !== (int) $record->employee_id
            ) {
                throw new RuntimeException(
                    'El documento cambió y ya no puede cancelarse.'
                );
            }

            if ($locked instanceof ExpenseAdvance) {
                $this->assertAdvanceHasNoOperations($locked);
            } else {
                $this->assertReportHasNoOperations($locked);
            }

            $metadata = is_array($locked->metadata)
                ? $locked->metadata
                : [];

            $metadata['draft_cancellation'] = [
                'reason' => $reason,
                'user_id' => (int) auth()->id(),
                'cancelled_at' => now()->toIso8601String(),
            ];

            $locked->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'metadata' => $metadata,
            ])->save();
        });
    }

    private function assertAdvanceHasNoOperations(
        ExpenseAdvance $record
    ): void {
        foreach ([
            'treasury_cash_transfer_request_id',
            'treasury_movement_id',
            'petty_cash_fund_movement_id',
            'approval_request_id',
            'return_treasury_movement_id',
            'return_petty_cash_fund_movement_id',
            'reimbursement_transfer_request_id',
            'reimbursement_approval_request_id',
            'reimbursement_treasury_movement_id',
            'reimbursement_petty_cash_movement_id',
        ] as $field) {
            if ($record->{$field} !== null) {
                throw new RuntimeException(
                    'El anticipo tiene una operación vinculada.'
                );
            }
        }

        if (
            $record->expenseReports()
                ->where('status', '!=', 'cancelled')
                ->exists()
        ) {
            throw new RuntimeException(
                'Cancela primero las comprobaciones vinculadas.'
            );
        }
    }

    private function assertReportHasNoOperations(
        ExpenseReport $record
    ): void {
        foreach ([
            'approval_request_id',
            'treasury_movement_id',
        ] as $field) {
            if ($record->{$field} !== null) {
                throw new RuntimeException(
                    'La comprobación tiene una operación vinculada.'
                );
            }
        }

        if ($record->fundMovements()->exists()) {
            throw new RuntimeException(
                'La comprobación ya tiene movimientos de caja.'
            );
        }
    }
}
