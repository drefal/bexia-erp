<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class PayrollAttendanceReviewSentAccess
{
    /**
     * Autoriza acceso a una revision ya enviada.
     * No cambia estados ni concede permisos.
     */
    public static function allowed(
        int $userId,
        int $companyId,
        int $reviewId,
        string $permission
    ): bool {
        $authz = PayrollAttendanceReviewAuthorization::class;

        if (!in_array($permission, [
            $authz::VIEW,
            $authz::APPROVE,
        ], true)) {
            return false;
        }

        if (!$authz::allowed(
            $userId,
            $companyId,
            $permission
        )) {
            return false;
        }

        $review = DB::table(
            'hr_payroll_attendance_reviews'
        )
            ->where('id', $reviewId)
            ->where('company_id', $companyId)
            ->where('reviewer_user_id', $userId)
            ->first();

        if (!$review) {
            return false;
        }

        if (
            $permission === $authz::APPROVE
            && $review->status !== 'pending'
        ) {
            return false;
        }

        if (!in_array(
            $review->status,
            ['pending', 'approved', 'returned'],
            true
        )) {
            return false;
        }

        $batch = DB::table(
            'hr_payroll_attendance_review_batches'
        )
            ->where('id', $review->batch_id)
            ->where('company_id', $companyId)
            ->first();

        if (
            !$batch
            || $batch->status !== 'sent'
            || $batch->sent_at === null
            || $review->sent_at === null
            || (int) $review->version
                !== (int) $batch->version
        ) {
            return false;
        }

        $items = DB::table(
            'hr_payroll_attendance_review_items'
        )
            ->where('company_id', $companyId)
            ->where('batch_id', $batch->id)
            ->where('review_id', $reviewId)
            ->orderBy('employee_id')
            ->get();

        if ($items->isEmpty()) {
            return false;
        }

        $hashInput = [];

        foreach ($items as $item) {
            if (
                (int) ($item->reviewer_user_id ?? 0)
                    !== $userId
                || $item->assignment_issue !== null
            ) {
                return false;
            }

            if (!PayrollAttendanceReviewScope::allowed(
                $userId,
                $companyId,
                (int) $batch->id,
                (int) $item->employee_id,
                $permission
            )) {
                return false;
            }

            $hashInput[] = [
                'employee_id' => (int) $item->employee_id,
                'snapshot_hash' => $item->snapshot_hash,
            ];
        }

        $calculatedHash = hash(
            'sha256',
            json_encode(
                $hashInput,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            )
        );

        return is_string($review->snapshot_hash)
            && hash_equals(
                $review->snapshot_hash,
                $calculatedHash
            );
    }
}
