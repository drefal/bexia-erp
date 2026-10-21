<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollAttendanceReviewDraftSync
{
    /**
     * Analiza la consistencia del borrador.
     *
     * No modifica registros.
     * No genera aprobaciones.
     * No actualiza fotografias.
     */
    public static function inspect(
        int $companyId,
        int $payrollRunId
    ): array {
        $batch = DB::table(
            'hr_payroll_attendance_review_batches'
        )
            ->where('company_id', $companyId)
            ->where('payroll_run_id', $payrollRunId)
            ->first();

        if (!$batch) {
            return [
                'status' => 'not_created',
                'company_id' => $companyId,
                'payroll_run_id' => $payrollRunId,
            ];
        }

        if ($batch->status !== 'draft') {
            return [
                'status' => 'not_draft',
                'batch_id' => (int) $batch->id,
                'batch_status' => $batch->status,
            ];
        }

        if ($batch->sent_at !== null) {
            return [
                'status' => 'already_sent',
                'batch_id' => (int) $batch->id,
            ];
        }

        $reviews = DB::table(
            'hr_payroll_attendance_reviews'
        )
            ->where('company_id', $companyId)
            ->where('batch_id', $batch->id)
            ->count();

        if ($reviews > 0) {
            return [
                'status' => 'reviews_exist',
                'batch_id' => (int) $batch->id,
                'review_count' => $reviews,
            ];
        }

        $distribution =
            PayrollAttendanceReviewDistribution::preview(
                $companyId,
                $payrollRunId
            );

        $run = DB::table('payroll_runs')
            ->where('company_id', $companyId)
            ->where('id', $payrollRunId)
            ->first([
                'id', 'period_start', 'period_end',
                'payroll_group_id',
            ]);

        if (!$run) {
            throw new RuntimeException(
                'Prenomina inexistente.'
            );
        }

        if (
            (string) $batch->period_start
                !== (string) $run->period_start
            || (string) $batch->period_end
                !== (string) $run->period_end
        ) {
            return [
                'status' => 'period_changed',
                'batch_id' => (int) $batch->id,
            ];
        }

        $items = DB::table(
            'hr_payroll_attendance_review_items'
        )
            ->where('company_id', $companyId)
            ->where('batch_id', $batch->id)
            ->orderBy('employee_id')
            ->get();

        $expected = [];

        foreach ($distribution['groups'] as $group) {
            foreach ($group['employee_ids'] as $employeeId) {
                $expected[(int) $employeeId] = true;
            }
        }

        foreach ($distribution['pending'] as $pending) {
            $expected[(int) $pending['employee_id']] = true;
        }

        $actualIds = $items
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $newEmployees = array_values(
            array_diff(
                array_keys($expected),
                $actualIds
            )
        );

        $removedEmployees = array_values(
            array_diff(
                $actualIds,
                array_keys($expected)
            )
        );

        sort($newEmployees);
        sort($removedEmployees);

        $assignmentChanges = [];
        $snapshotChanges = [];

        foreach ($items as $item) {
            $employeeId = (int) $item->employee_id;

            if (!isset($expected[$employeeId])) {
                continue;
            }

            $employee = DB::table('employees')
                ->where('company_id', $companyId)
                ->where('id', $employeeId)
                ->first(['id', 'hr_department_id']);

            if (!$employee) {
                throw new RuntimeException(
                    "Empleado no encontrado: {$employeeId}"
                );
            }

            $resolution =
                PayrollAttendanceReviewerResolver::resolve(
                    $companyId,
                    $employeeId
                );

            $currentDepartment =
                $employee->hr_department_id === null
                    ? null
                    : (int) $employee->hr_department_id;

            $savedDepartment =
                $item->department_id === null
                    ? null
                    : (int) $item->department_id;

            $currentReviewer =
                $resolution['reviewer_user_id'] === null
                    ? null
                    : (int) $resolution['reviewer_user_id'];

            $savedReviewer =
                $item->reviewer_user_id === null
                    ? null
                    : (int) $item->reviewer_user_id;

            if (
                $currentDepartment !== $savedDepartment
                || $currentReviewer !== $savedReviewer
                || $resolution['source']
                    !== $item->assignment_source
                || ($resolution['reason'] ?? null)
                    !== $item->assignment_issue
            ) {
                $assignmentChanges[] = [
                    'employee_id' => $employeeId,
                    'old_department_id' => $savedDepartment,
                    'new_department_id' => $currentDepartment,
                    'old_reviewer_user_id' => $savedReviewer,
                    'new_reviewer_user_id' => $currentReviewer,
                    'old_source' => $item->assignment_source,
                    'new_source' => $resolution['source'],
                ];
            }

            try {
                $integrity =
                    PayrollAttendanceSnapshotService::compareItem(
                        (int) $item->id
                    );

                $integrityStatus =
                    $integrity['status'] ?? 'unknown';

            } catch (\Throwable $e) {
                report($e);
                $integrityStatus = 'error';
            }

            if ($integrityStatus !== 'unchanged') {
                $snapshotChanges[] = [
                    'employee_id' => $employeeId,
                    'item_id' => (int) $item->id,
                    'status' => $integrityStatus,
                ];
            }
        }

        $hasChanges =
            count($newEmployees) > 0
            || count($removedEmployees) > 0
            || count($assignmentChanges) > 0
            || count($snapshotChanges) > 0;

        return [
            'status' => $hasChanges
                ? 'refresh_required'
                : 'ready',
            'batch_id' => (int) $batch->id,
            'batch_version' => (int) $batch->version,
            'batch_status' => $batch->status,
            'total_expected' =>
                $distribution['total_employees'],
            'total_stored' => $items->count(),
            'assigned_employees' =>
                $distribution['assigned_employees'],
            'pending_employees' =>
                $distribution['pending_employees'],
            'new_employees' => $newEmployees,
            'removed_employees' => $removedEmployees,
            'assignment_changes' => $assignmentChanges,
            'snapshot_changes' => $snapshotChanges,
        ];
    }
}
