<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PayrollAttendanceReviewDispatch
{
    private const JSON_FLAGS =
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR;

    /**
     * Prepara y envia revisiones en una sola transaccion.
     *
     * El caller debe estar autenticado y pasar su propio ID.
     * No sustituye el middleware de autenticacion de la UI.
     * No envia correos ni modifica datos economicos.
     */
    public static function send(
        int $companyId,
        int $payrollRunId,
        int $actorUserId
    ): array {
        return DB::transaction(function () use (
            $companyId,
            $payrollRunId,
            $actorUserId
        ) {
            $authz =
                PayrollAttendanceReviewAuthorization::class;

            if (!$authz::allowed(
                $actorUserId,
                $companyId,
                $authz::MANAGE
            )) {
                throw new RuntimeException(
                    'Sin permiso de gestion de revisiones.'
                );
            }

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
                    'No existe borrador disponible para enviar.'
                );
            }

            $run = DB::table('payroll_runs')
                ->where('company_id', $companyId)
                ->where('id', $payrollRunId)
                ->first();

            if (
                !$run
                || $run->status !== 'calculated'
                || (bool) $run->is_locked
            ) {
                throw new RuntimeException(
                    'Prenomina no disponible para revision.'
                );
            }

            $inspection =
                PayrollAttendanceReviewDraftSync::inspect(
                    $companyId,
                    $payrollRunId
                );

            if ($inspection['status'] !== 'ready') {
                throw new RuntimeException(
                    'Borrador desactualizado: '
                    . $inspection['status']
                );
            }

            if ((int) $inspection['batch_id']
                !== (int) $batch->id
                || (int) $inspection['batch_version']
                !== (int) $batch->version
                || (int) $inspection['pending_employees'] !== 0
            ) {
                throw new RuntimeException(
                    'Borrador con diferencias o responsables pendientes.'
                );
            }

            $items = DB::table(
                'hr_payroll_attendance_review_items'
            )
                ->where('company_id', $companyId)
                ->where('batch_id', $batch->id)
                ->orderBy('employee_id')
                ->lockForUpdate()
                ->get();

            $payrollIds = DB::table('payroll_run_lines')
                ->where('company_id', $companyId)
                ->where('payroll_run_id', $payrollRunId)
                ->distinct()
                ->orderBy('employee_id')
                ->pluck('employee_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $itemIds = $items->pluck('employee_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            sort($payrollIds);
            sort($itemIds);

            if (
                $items->isEmpty()
                || count($payrollIds) !== $items->count()
                || $payrollIds !== $itemIds
            ) {
                throw new RuntimeException(
                    'La poblacion del borrador no coincide '
                    . 'con las lineas de prenomina.'
                );
            }

            if (DB::table(
                'hr_payroll_attendance_reviews'
            )->where('batch_id', $batch->id)->exists()) {
                throw new RuntimeException(
                    'Ya existen revisiones para este lote.'
                );
            }

            $groups = [];

            foreach ($items as $item) {
                $reviewerId =
                    (int) ($item->reviewer_user_id ?? 0);

                if (
                    $reviewerId <= 0
                    || $item->review_id !== null
                    || $item->assignment_issue !== null
                ) {
                    throw new RuntimeException(
                        'Empleado sin asignacion valida.'
                    );
                }

                $resolution =
                    PayrollAttendanceReviewerResolver::resolve(
                        $companyId,
                        (int) $item->employee_id
                    );

                if (
                    (int) ($resolution['reviewer_user_id'] ?? 0)
                        !== $reviewerId
                    || $resolution['source']
                        !== $item->assignment_source
                ) {
                    throw new RuntimeException(
                        'La asignacion del responsable cambio.'
                    );
                }

                if (
                    !$authz::allowed(
                        $reviewerId,
                        $companyId,
                        $authz::VIEW
                    )
                    || !$authz::allowed(
                        $reviewerId,
                        $companyId,
                        $authz::APPROVE
                    )
                ) {
                    throw new RuntimeException(
                        'Responsable sin permisos de revision.'
                    );
                }

                $comparison =
                    PayrollAttendanceSnapshotService::compareItem(
                        (int) $item->id
                    );

                if (
                    ($comparison['status'] ?? null)
                        !== 'unchanged'
                ) {
                    throw new RuntimeException(
                        'Fotografia de asistencia desactualizada.'
                    );
                }

                $groups[$reviewerId][] = $item;
            }

            ksort($groups, SORT_NUMERIC);
            $now = now();
            $created = [];

            foreach ($groups as $reviewerId => $groupItems) {
                $hashInput = [];

                foreach ($groupItems as $item) {
                    $hashInput[] = [
                        'employee_id' => (int) $item->employee_id,
                        'snapshot_hash' => $item->snapshot_hash,
                    ];
                }

                $groupHash = hash(
                    'sha256',
                    json_encode($hashInput, self::JSON_FLAGS)
                );

                $reviewId = DB::table(
                    'hr_payroll_attendance_reviews'
                )->insertGetId([
                    'batch_id' => $batch->id,
                    'company_id' => $companyId,
                    'reviewer_user_id' => $reviewerId,
                    'status' => 'pending',
                    'version' => (int) $batch->version,
                    'snapshot_hash' => $groupHash,
                    'reviewer_comment' => null,
                    'sent_at' => $now,
                    'reviewed_at' => null,
                    'reviewed_by_user_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($groupItems as $item) {
                    $affected = DB::table(
                        'hr_payroll_attendance_review_items'
                    )
                        ->where('id', $item->id)
                        ->where('batch_id', $batch->id)
                        ->whereNull('review_id')
                        ->update([
                            'review_id' => $reviewId,
                            'updated_at' => $now,
                        ]);

                    if ($affected !== 1) {
                        throw new RuntimeException(
                            'No se pudo vincular un elemento.'
                        );
                    }
                }

                DB::table(
                    'hr_payroll_attendance_review_events'
                )->insert([
                    'batch_id' => $batch->id,
                    'review_id' => $reviewId,
                    'company_id' => $companyId,
                    'actor_user_id' => $actorUserId,
                    'action' => 'review_sent',
                    'previous_status' => 'draft',
                    'new_status' => 'pending',
                    'review_version' => (int) $batch->version,
                    'snapshot_hash' => $groupHash,
                    'comment' =>
                        'Revision de asistencia enviada al responsable.',
                    'metadata' => json_encode([
                        'payroll_run_id' => $payrollRunId,
                        'reviewer_user_id' => $reviewerId,
                        'employee_ids' => array_map(
                            fn ($item) => (int) $item->employee_id,
                            $groupItems
                        ),
                        'batch_version' => (int) $batch->version,
                    ], self::JSON_FLAGS),
                    'created_at' => $now,
                ]);

                $created[] = [
                    'review_id' => (int) $reviewId,
                    'reviewer_user_id' => (int) $reviewerId,
                    'employee_count' => count($groupItems),
                ];
            }

            $updated = DB::table(
                'hr_payroll_attendance_review_batches'
            )
                ->where('id', $batch->id)
                ->where('company_id', $companyId)
                ->where('status', 'draft')
                ->whereNull('sent_at')
                ->update([
                    'status' => 'sent',
                    'sent_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($updated !== 1) {
                throw new RuntimeException(
                    'No fue posible marcar el lote como enviado.'
                );
            }

            DB::table(
                'hr_payroll_attendance_review_events'
            )->insert([
                'batch_id' => $batch->id,
                'review_id' => null,
                'company_id' => $companyId,
                'actor_user_id' => $actorUserId,
                'action' => 'batch_sent',
                'previous_status' => 'draft',
                'new_status' => 'sent',
                'review_version' => (int) $batch->version,
                'snapshot_hash' => null,
                'comment' =>
                    'Envio de revisiones de asistencia por responsable.',
                'metadata' => json_encode([
                    'payroll_run_id' => $payrollRunId,
                    'reviews_created' => count($created),
                    'employees_sent' => $items->count(),
                ], self::JSON_FLAGS),
                'created_at' => $now,
            ]);

            return [
                'status' => 'sent',
                'batch_id' => (int) $batch->id,
                'batch_version' => (int) $batch->version,
                'reviews_created' => count($created),
                'employees_sent' => $items->count(),
                'reviews' => $created,
            ];
        }, 1);
    }
}
