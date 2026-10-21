<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class PayrollAttendanceReviewScope
{
    /**
     * Comprueba si un usuario puede acceder a un elemento
     * concreto de un lote de asistencia.
     *
     * Solo consulta datos. No concede permisos ni aprobaciones.
     */
    public static function allowed(
        int $userId,
        int $companyId,
        int $batchId,
        int $employeeId,
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

        $batch = DB::table(
            'hr_payroll_attendance_review_batches'
        )
            ->where('id', $batchId)
            ->where('company_id', $companyId)
            ->first();

        if (!$batch) {
            return false;
        }

        $item = DB::table(
            'hr_payroll_attendance_review_items'
        )
            ->where('batch_id', $batchId)
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->first();

        if (
            !$item
            || (int) ($item->reviewer_user_id ?? 0)
                !== $userId
        ) {
            return false;
        }

        $resolution =
            PayrollAttendanceReviewerResolver::resolve(
                $companyId,
                $employeeId
            );

        if (
            (int) ($resolution['reviewer_user_id'] ?? 0)
                !== $userId
            || $resolution['source']
                !== $item->assignment_source
        ) {
            return false;
        }

        $run = DB::table('payroll_runs')
            ->where('id', $batch->payroll_run_id)
            ->where('company_id', $companyId)
            ->first(['id','period_start','period_end']);

        if (
            !$run
            || (string) $run->period_start
                !== (string) $batch->period_start
            || (string) $run->period_end
                !== (string) $batch->period_end
        ) {
            return false;
        }

        $inPayroll = DB::table('payroll_run_lines')
            ->where('company_id', $companyId)
            ->where('payroll_run_id', $run->id)
            ->where('employee_id', $employeeId)
            ->exists();

        if (!$inPayroll) {
            return false;
        }

        $integrity =
            PayrollAttendanceSnapshotService::compareItem(
                (int) $item->id
            );

        if (($integrity['status'] ?? null) !== 'unchanged') {
            return false;
        }

        // En borrador solamente se prueban alcances.
        // El acceso operativo requerira una revision enviada
        // y vinculada a este elemento.
        return true;
    }
}
