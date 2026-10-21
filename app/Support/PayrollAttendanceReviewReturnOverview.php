<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PayrollAttendanceReviewReturnOverview
{
    /**
     * Consulta de decisiones para RRHH.
     * No cambia estados ni datos economicos.
     */
    public static function get(
        int $companyId,
        int $payrollRunId,
        int $actorUserId
    ): array {
        if (!PayrollAttendanceReviewAuthorization::allowed(
            $actorUserId,
            $companyId,
            PayrollAttendanceReviewAuthorization::MANAGE
        )) {
            throw new RuntimeException(
                'Sin permiso para gestionar revisiones.'
            );
        }

        $batch = DB::table(
            'hr_payroll_attendance_review_batches'
        )
            ->where('company_id', $companyId)
            ->where('payroll_run_id', $payrollRunId)
            ->first();

        if (!$batch) {
            throw new RuntimeException(
                'Lote de revisiones inexistente.'
            );
        }

        $reviews = DB::table(
            'hr_payroll_attendance_reviews'
        )
            ->where('company_id', $companyId)
            ->where('batch_id', $batch->id)
            ->orderBy('reviewer_user_id')
            ->get();

        $result = [];
        $counts = [
            'pending' => 0,
            'approved' => 0,
            'returned' => 0,
            'other' => 0,
        ];

        foreach ($reviews as $review) {
            $status = (string) $review->status;

            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            } else {
                $counts['other']++;
            }

            $items = DB::table(
                'hr_payroll_attendance_review_items'
            )
                ->where('company_id', $companyId)
                ->where('batch_id', $batch->id)
                ->where('review_id', $review->id)
                ->orderBy('employee_id')
                ->pluck('employee_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $events = DB::table(
                'hr_payroll_attendance_review_events'
            )
                ->where('company_id', $companyId)
                ->where('batch_id', $batch->id)
                ->where('review_id', $review->id)
                ->orderBy('id')
                ->get([
                    'id',
                    'action',
                    'previous_status',
                    'new_status',
                    'actor_user_id',
                    'review_version',
                    'snapshot_hash',
                    'comment',
                    'created_at',
                ]);

            $result[] = [
                'review_id' => (int) $review->id,
                'reviewer_user_id' =>
                    (int) $review->reviewer_user_id,
                'status' => $status,
                'version' => (int) $review->version,
                'snapshot_hash' => $review->snapshot_hash,
                'employee_ids' => $items,
                'reviewer_comment' =>
                    $review->reviewer_comment,
                'reviewed_at' => $review->reviewed_at,
                'reviewed_by_user_id' =>
                    $review->reviewed_by_user_id,
                'events' => $events->toArray(),
            ];
        }

        return [
            'batch_id' => (int) $batch->id,
            'company_id' => $companyId,
            'payroll_run_id' => $payrollRunId,
            'batch_status' => $batch->status,
            'batch_version' => (int) $batch->version,
            'total_reviews' => count($result),
            'counts' => $counts,
            'reviews' => $result,
        ];
    }
}
