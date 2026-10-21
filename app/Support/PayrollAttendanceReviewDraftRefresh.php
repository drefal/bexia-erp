<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollAttendanceReviewDraftRefresh
{
    private const JSON_FLAGS =
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR;

    /**
     * Actualiza exclusivamente borradores sin revisiones.
     *
     * No crea altas/bajas de empleados.
     * No modifica registros de asistencia ni prenomina.
     * No autoriza ni envia vistos buenos.
     */
    public static function refresh(
        int $companyId,
        int $payrollRunId,
        ?int $actorUserId = null
    ): array {
        return DB::transaction(function () use (
            $companyId,
            $payrollRunId,
            $actorUserId
        ) {
            $batch = DB::table(
                'hr_payroll_attendance_review_batches'
            )
                ->where('company_id', $companyId)
                ->where('payroll_run_id', $payrollRunId)
                ->lockForUpdate()
                ->first();

            if (
                !$batch
                || $batch->status !== 'draft'
                || $batch->sent_at !== null
            ) {
                throw new RuntimeException(
                    'Solo puede actualizarse un borrador no enviado.'
                );
            }

            if ($actorUserId !== null) {
                $actor = \App\Models\User::find($actorUserId);
                $company = \App\Models\Company::find($companyId);

                if (
                    !$actor
                    || !$company
                    || !$actor->canAccessTenant($company)
                ) {
                    throw new RuntimeException(
                        'Actor sin acceso a la empresa.'
                    );
                }

                // La autorizacion especifica de gestionar
                // revisiones se incorporara en el controlador
                // antes de exponer esta operacion en la UI.
            }

            $reviewCount = DB::table(
                'hr_payroll_attendance_reviews'
            )
                ->where('company_id', $companyId)
                ->where('batch_id', $batch->id)
                ->count();

            if ($reviewCount !== 0) {
                throw new RuntimeException(
                    'Hay revisiones existentes. No actualizar.'
                );
            }

            $inspection =
                PayrollAttendanceReviewDraftSync::inspect(
                    $companyId,
                    $payrollRunId
                );

            if (
                !in_array(
                    $inspection['status'],
                    ['ready', 'refresh_required'],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Estado no actualizable: '
                    . $inspection['status']
                );
            }

            if (
                (int) $inspection['batch_id']
                    !== (int) $batch->id
                || (int) $inspection['batch_version']
                    !== (int) $batch->version
            ) {
                throw new RuntimeException(
                    'La version del borrador cambio.'
                );
            }

            if (
                !empty($inspection['new_employees'])
                || !empty($inspection['removed_employees'])
            ) {
                throw new RuntimeException(
                    'Cambio de poblacion detectado. '
                    . 'Se requiere conciliacion de altas o bajas.'
                );
            }

            if ($inspection['status'] === 'ready') {
                return [
                    'status' => 'already_current',
                    'batch_id' => (int) $batch->id,
                    'version' => (int) $batch->version,
                    'updated_items' => 0,
                ];
            }

            foreach ($inspection['snapshot_changes'] as $change) {
                if (
                    in_array(
                        $change['status'],
                        ['invalid_snapshot', 'error', 'missing_snapshot'],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Fotografia historica invalida o faltante. '
                        . 'No puede sobrescribirse.'
                    );
                }
            }

            $changedIds = [];

            foreach ($inspection['assignment_changes'] as $change) {
                $changedIds[(int) $change['employee_id']] = true;
            }

            foreach ($inspection['snapshot_changes'] as $change) {
                $changedIds[(int) $change['employee_id']] = true;
            }

            $newVersion = (int) $batch->version + 1;
            $updated = 0;

            foreach (array_keys($changedIds) as $employeeId) {
                $item = DB::table(
                    'hr_payroll_attendance_review_items'
                )
                    ->where('company_id', $companyId)
                    ->where('batch_id', $batch->id)
                    ->where('employee_id', $employeeId)
                    ->lockForUpdate()
                    ->first();

                if (!$item || $item->review_id !== null) {
                    throw new RuntimeException(
                        'Elemento inexistente o ya asignado.'
                    );
                }

                $oldSnapshot = json_decode(
                    $item->attendance_snapshot,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                if (
                    !is_array($oldSnapshot)
                    || !is_string($item->snapshot_hash)
                    || !hash_equals(
                        $item->snapshot_hash,
                        PayrollAttendanceSnapshotService::hash(
                            $oldSnapshot
                        )
                    )
                ) {
                    throw new RuntimeException(
                        'La fotografia anterior no es integra.'
                    );
                }

                $employee = DB::table('employees')
                    ->where('company_id', $companyId)
                    ->where('id', $employeeId)
                    ->first(['hr_department_id']);

                if (!$employee) {
                    throw new RuntimeException(
                        'Empleado ya no pertenece a la empresa.'
                    );
                }

                $resolution =
                    PayrollAttendanceReviewerResolver::resolve(
                        $companyId,
                        $employeeId
                    );

                $newSnapshot =
                    PayrollAttendanceSnapshotService::capture(
                        $companyId,
                        $employeeId,
                        $payrollRunId
                    );

                $newHash =
                    PayrollAttendanceSnapshotService::hash(
                        $newSnapshot
                    );

                $newDepartment = $employee->hr_department_id;
                $newReviewer = $resolution['reviewer_user_id'];

                // Registrar evidencia anterior antes del reemplazo.
                DB::table(
                    'hr_payroll_attendance_review_events'
                )->insert([
                    'batch_id' => $batch->id,
                    'review_id' => null,
                    'company_id' => $companyId,
                    'actor_user_id' => $actorUserId,
                    'action' => 'draft_item_refreshed',
                    'previous_status' => 'draft',
                    'new_status' => 'draft',
                    'review_version' => $newVersion,
                    'snapshot_hash' => $newHash,
                    'comment' =>
                        'Actualizacion de borrador con '
                        . 'evidencia historica conservada.',
                    'metadata' => json_encode([
                        'employee_id' => $employeeId,
                        'old_department_id' => $item->department_id,
                        'new_department_id' => $newDepartment,
                        'old_reviewer_user_id' =>
                            $item->reviewer_user_id,
                        'new_reviewer_user_id' => $newReviewer,
                        'old_assignment_source' =>
                            $item->assignment_source,
                        'new_assignment_source' =>
                            $resolution['source'],
                        'old_snapshot_hash' =>
                            $item->snapshot_hash,
                        'new_snapshot_hash' => $newHash,
                        'old_snapshot' => $oldSnapshot,
                    ], self::JSON_FLAGS),
                    'created_at' => now(),
                ]);

                DB::table(
                    'hr_payroll_attendance_review_items'
                )
                    ->where('id', $item->id)
                    ->where('company_id', $companyId)
                    ->where('batch_id', $batch->id)
                    ->update([
                        'department_id' => $newDepartment,
                        'reviewer_user_id' => $newReviewer,
                        'assignment_source' =>
                            $resolution['source'],
                        'assignment_issue' =>
                            $resolution['reason'],
                        'attendance_snapshot' => json_encode(
                            $newSnapshot,
                            self::JSON_FLAGS
                        ),
                        'snapshot_hash' => $newHash,
                        'updated_at' => now(),
                    ]);

                $updated++;
            }

            if ($updated === 0) {
                throw new RuntimeException(
                    'Se detectaron diferencias sin elementos actualizables.'
                );
            }

            DB::table(
                'hr_payroll_attendance_review_batches'
            )
                ->where('id', $batch->id)
                ->where('company_id', $companyId)
                ->update([
                    'version' => $newVersion,
                    'updated_at' => now(),
                ]);

            $after = PayrollAttendanceReviewDraftSync::inspect(
                $companyId,
                $payrollRunId
            );

            if ($after['status'] !== 'ready') {
                throw new RuntimeException(
                    'Persisten diferencias tras actualizar. '
                    . 'Se revierte la transaccion.'
                );
            }

            return [
                'status' => 'refreshed',
                'batch_id' => (int) $batch->id,
                'version' => $newVersion,
                'updated_items' => $updated,
            ];
        }, 1);
    }
}
