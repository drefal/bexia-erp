<?php

namespace App\Support\Expenses;

use App\Models\ExpenseAdvance;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExpenseAdvanceReconciliationService
{
    private const EPSILON = 0.005;

    public function finalize(
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
                    !== ExpenseAdvance::STATUS_PENDING_RECONCILIATION
                ) {
                    throw new RuntimeException(
                        'Solo puede finalizarse un anticipo '
                        . 'pendiente de comprobar.'
                    );
                }

                /*
                 * No cerramos mientras exista trabajo documental
                 * todavía editable o pendiente de autorización.
                 */
                $draftCount = $locked->expenseReports()
                    ->where('status', 'draft')
                    ->count();

                if ($draftCount > 0) {
                    throw new RuntimeException(
                        'El anticipo tiene '
                        . $draftCount
                        . ' comprobación'
                        . ($draftCount === 1 ? '' : 'es')
                        . ' en borrador. '
                        . 'Debes enviarla, cancelarla o eliminarla '
                        . 'antes de finalizar.'
                    );
                }

                $pendingCount = $locked->expenseReports()
                    ->where('status', 'pending_approval')
                    ->count();

                if ($pendingCount > 0) {
                    throw new RuntimeException(
                        'El anticipo tiene '
                        . $pendingCount
                        . ' comprobación'
                        . ($pendingCount === 1 ? '' : 'es')
                        . ' pendiente'
                        . ($pendingCount === 1 ? '' : 's')
                        . ' de aprobación.'
                    );
                }

                /*
                 * Solo comprobaciones APROBADAS participan
                 * en la conciliación final.
                 *
                 * Rechazadas/canceladas no cuentan.
                 */
                $approved = round(
                    (float) $locked->expenseReports()
                        ->where('status', 'approved')
                        ->sum('total_amount'),
                    6
                );

                $advanceAmount = round(
                    (float) $locked->amount,
                    6
                );

                $difference = round(
                    $approved - $advanceAmount,
                    6
                );

                $returnDue = 0.0;
                $reimbursementDue = 0.0;
                $nextStatus = null;
                $closedAt = null;

                if (abs($difference) <= self::EPSILON) {
                    /*
                     * CUADRADO:
                     * lo comprobado coincide con lo entregado.
                     * No hay nuevo movimiento financiero.
                     */
                    $nextStatus =
                        ExpenseAdvance::STATUS_CLOSED;

                    $closedAt = now();
                } elseif ($difference < 0) {
                    /*
                     * SOBRANTE:
                     * el empleado debe regresar efectivo
                     * a la misma caja chica.
                     */
                    $nextStatus =
                        ExpenseAdvance::STATUS_PENDING_RETURN;

                    $returnDue = round(
                        abs($difference),
                        6
                    );
                } else {
                    /*
                     * EXCESO:
                     * el empleado gastó más de lo recibido.
                     * Bexia deberá reembolsar únicamente
                     * esta diferencia después de aprobación.
                     */
                    $nextStatus =
                        ExpenseAdvance::STATUS_PENDING_REIMBURSEMENT;

                    $reimbursementDue = round(
                        $difference,
                        6
                    );
                }

                $metadata = is_array($locked->metadata)
                    ? $locked->metadata
                    : [];

                $metadata['reconciliation'] = [
                    'advance_amount' =>
                        $advanceAmount,
                    'approved_amount' =>
                        $approved,
                    'difference' =>
                        $difference,
                    'return_due_amount' =>
                        $returnDue,
                    'reimbursement_due_amount' =>
                        $reimbursementDue,
                    'result_status' =>
                        $nextStatus,
                    'finalized_by_user_id' =>
                        $userId,
                    'finalized_at' =>
                        now()->toDateTimeString(),
                ];

                $locked->forceFill([
                    'status' =>
                        $nextStatus,

                    'reconciled_amount' =>
                        $approved,

                    'return_due_amount' =>
                        $returnDue,

                    'reimbursement_due_amount' =>
                        $reimbursementDue,

                    'reconciliation_finalized_at' =>
                        now(),

                    'reconciliation_finalized_by_user_id' =>
                        $userId,

                    'closed_at' =>
                        $closedAt,

                    'metadata' =>
                        $metadata,
                ])->save();

                return $locked->refresh();
            }
        );
    }

    public function resultDescription(
        ExpenseAdvance $advance
    ): string {
        $approved =
            $advance->approvedReconciledAmount();

        $amount =
            round((float) $advance->amount, 6);

        $difference =
            round($approved - $amount, 6);

        if (abs($difference) <= self::EPSILON) {
            return sprintf(
                'Anticipo $%s / comprobado $%s. '
                . 'El anticipo quedará cerrado sin movimientos '
                . 'financieros adicionales.',
                number_format($amount, 2),
                number_format($approved, 2)
            );
        }

        if ($difference < 0) {
            return sprintf(
                'Anticipo $%s / comprobado $%s. '
                . 'Quedará pendiente una devolución del empleado '
                . 'por $%s a la misma caja chica.',
                number_format($amount, 2),
                number_format($approved, 2),
                number_format(abs($difference), 2)
            );
        }

        return sprintf(
            'Anticipo $%s / comprobado $%s. '
            . 'Quedará pendiente un reembolso al empleado '
            . 'por $%s, sujeto a aprobación.',
            number_format($amount, 2),
            number_format($approved, 2),
            number_format($difference, 2)
        );
    }
}
