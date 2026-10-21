<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PayrollAttendanceReviewDecision
{
    /**
     * Registra la decision de un coordinador.
     *
     * Estados permitidos:
     * pending -> approved
     * pending -> returned
     *
     * No modifica payroll_runs ni payroll_run_lines.
     */
    public static function decide(
        int $companyId,
        int $reviewId,
        int $actorUserId,
        string $decision,
        ?string $comment = null
    ): array {
        if (!in_array($decision, ['approved', 'returned'], true)) {
            throw new RuntimeException('Decision no permitida.');
        }

        $comment = trim((string) $comment);

        if ($decision === 'returned' && $comment === '') {
            throw new RuntimeException(
                'La devolucion requiere observaciones.'
            );
        }

        if (mb_strlen($comment) > 2000) {
            throw new RuntimeException(
                'Observaciones demasiado extensas.'
            );
        }

        return DB::transaction(function () use (
            $companyId,
            $reviewId,
            $actorUserId,
            $decision,
            $comment
        ) {
            $review = DB::table(
                'hr_payroll_attendance_reviews'
            )
                ->where('id', $reviewId)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (
                !$review
                || $review->status !== 'pending'
                || (int) $review->reviewer_user_id
                    !== $actorUserId
            ) {
                throw new RuntimeException(
                    'Revision no pendiente o usuario no asignado.'
                );
            }

            $batch = DB::table(
                'hr_payroll_attendance_review_batches'
            )
                ->where('id', $review->batch_id)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (
                !$batch
                || $batch->status !== 'sent'
                || $batch->sent_at === null
                || (int) $batch->version
                    !== (int) $review->version
            ) {
                throw new RuntimeException(
                    'Lote no enviado o version distinta.'
                );
            }

            $run = DB::table('payroll_runs')
                ->where('company_id', $companyId)
                ->where('id', $batch->payroll_run_id)
                ->first(['id', 'status', 'is_locked']);

            if (
                !$run
                || $run->status !== 'calculated'
                || (bool) $run->is_locked
            ) {
                throw new RuntimeException(
                    'Prenomina no disponible para visto bueno.'
                );
            }

            if (!PayrollAttendanceReviewSentAccess::allowed(
                $actorUserId,
                $companyId,
                $reviewId,
                PayrollAttendanceReviewAuthorization::APPROVE
            )) {
                throw new RuntimeException(
                    'Sin autorizacion o fotografia desactualizada.'
                );
            }

            $now = now();

            $updated = DB::table(
                'hr_payroll_attendance_reviews'
            )
                ->where('id', $reviewId)
                ->where('company_id', $companyId)
                ->where('status', 'pending')
                ->where('version', $batch->version)
                ->update([
                    'status' => $decision,
                    'reviewer_comment' =>
                        $comment === '' ? null : $comment,
                    'reviewed_at' => $now,
                    'reviewed_by_user_id' => $actorUserId,
                    'updated_at' => $now,
                ]);

            if ($updated !== 1) {
                throw new RuntimeException(
                    'No se pudo registrar la decision.'
                );
            }

            DB::table(
                'hr_payroll_attendance_review_events'
            )->insert([
                'batch_id' => $batch->id,
                'review_id' => $reviewId,
                'company_id' => $companyId,
                'actor_user_id' => $actorUserId,
                'action' => $decision === 'approved'
                    ? 'review_approved'
                    : 'review_returned',
                'previous_status' => 'pending',
                'new_status' => $decision,
                'review_version' => $review->version,
                'snapshot_hash' => $review->snapshot_hash,
                'comment' =>
                    $comment === '' ? null : $comment,
                'metadata' => json_encode([
                    'payroll_run_id' => (int) $batch->payroll_run_id,
                    'reviewer_user_id' => $actorUserId,
                    'decision' => $decision,
                    'batch_version' => (int) $batch->version,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);

            return [
                'status' => $decision,
                'review_id' => $reviewId,
                'batch_id' => (int) $batch->id,
                'version' => (int) $review->version,
                'reviewed_by_user_id' => $actorUserId,
            ];
        }, 1);
    }
}
