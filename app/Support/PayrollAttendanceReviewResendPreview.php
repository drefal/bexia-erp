<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PayrollAttendanceReviewResendPreview
{
    public static function inspect(
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
                'Lote no encontrado.'
            );
        }

        $reviews = DB::table(
            'hr_payroll_attendance_reviews'
        )
            ->where('batch_id', $batch->id)
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->get();

        $counts = [
            'pending' => 0,
            'approved' => 0,
            'returned' => 0,
            'other' => 0,
        ];

        $details = [];
        $invalid = false;

        foreach ($reviews as $review) {
            $status = (string) $review->status;

            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            } else {
                $counts['other']++;
                $invalid = true;
            }

            if ((int) $review->version !== (int) $batch->version) {
                $invalid = true;
            }

            $items = DB::table(
                'hr_payroll_attendance_review_items'
            )
                ->where('company_id', $companyId)
                ->where('batch_id', $batch->id)
                ->where('review_id', $review->id)
                ->get(['id', 'employee_id', 'reviewer_user_id']);

            if ($items->isEmpty()) {
                $invalid = true;
            }

            foreach ($items as $item) {
                if (
                    (int) $item->reviewer_user_id
                    !== (int) $review->reviewer_user_id
                ) {
                    $invalid = true;
                }
            }

            $details[] = [
                'review_id' => (int) $review->id,
                'reviewer_user_id' =>
                    (int) $review->reviewer_user_id,
                'status' => $status,
                'version' => (int) $review->version,
                'employees' => $items
                    ->pluck('employee_id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
                'comment' => $review->reviewer_comment,
            ];
        }

        $unlinkedItems = DB::table(
            'hr_payroll_attendance_review_items'
        )
            ->where('batch_id', $batch->id)
            ->where('company_id', $companyId)
            ->whereNull('review_id')
            ->count();

        if ($unlinkedItems > 0 && $batch->status === 'sent') {
            $invalid = true;
        }

        $canPrepare =
            !$invalid
            && $batch->status === 'sent'
            && $batch->sent_at !== null
            && $reviews->isNotEmpty()
            && $counts['returned'] > 0
            && $counts['pending'] === 0
            && $counts['approved'] === 0;

        $reason = null;

        if ($invalid) {
            $reason = 'inconsistent_reviews';
        } elseif ($batch->status !== 'sent') {
            $reason = 'batch_not_sent';
        } elseif ($batch->sent_at === null) {
            $reason = 'batch_missing_sent_date';
        } elseif ($reviews->isEmpty()) {
            $reason = 'no_reviews';
        } elseif ($counts['returned'] === 0) {
            $reason = 'no_returns';
        } elseif ($counts['pending'] > 0) {
            $reason = 'pending_reviews_exist';
        } elseif ($counts['approved'] > 0) {
            $reason = 'approved_reviews_exist';
        }

        return [
            'status' => $canPrepare
                ? 'eligible_for_reprepare'
                : 'blocked',
            'reason' => $reason,
            'batch_id' => (int) $batch->id,
            'batch_status' => $batch->status,
            'batch_version' => (int) $batch->version,
            'counts' => $counts,
            'reviews' => $details,
            'unlinked_items' => $unlinkedItems,
            'database_writes' => false,
        ];
    }
}
