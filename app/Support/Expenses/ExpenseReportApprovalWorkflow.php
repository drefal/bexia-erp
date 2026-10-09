<?php

namespace App\Support\Expenses;

use App\Models\ApprovalRequest;
use App\Models\ExpenseReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ExpenseReportApprovalWorkflow
{
    public const DOCUMENT_TYPE = 'expense_report';

    public static function sendToApproval(
        ExpenseReport $report
    ): ApprovalRequest {
        ExpenseReportSubmissionValidator::assertCanSubmit(
            $report
        );

        if (
            ! Schema::hasTable('approval_workflows')
            || ! Schema::hasTable('approval_workflow_steps')
            || ! Schema::hasTable('approval_requests')
            || ! Schema::hasTable('approval_request_steps')
        ) {
            throw new RuntimeException(
                'El motor de aprobaciones no está instalado.'
            );
        }

        $workflow = static::findApplicableWorkflow(
            $report
        );

        if (! $workflow) {
            throw new RuntimeException(
                'No existe un flujo activo para Comprobaciones de gastos. '
                . 'Configúralo en Configuración empresa > Flujos de aprobación.'
            );
        }

        $steps = static::workflowSteps(
            $workflow,
            $report
        );

        if ($steps->isEmpty()) {
            throw new RuntimeException(
                'El flujo de Comprobaciones de gastos no tiene etapas activas aplicables.'
            );
        }

        return DB::transaction(
            function () use (
                $report,
                $workflow,
                $steps
            ): ApprovalRequest {
                $locked = ExpenseReport::query()
                    ->lockForUpdate()
                    ->findOrFail($report->id);

                if (
                    ! in_array(
                        (string) $locked->status,
                        ['draft', 'rejected'],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'La comprobación ya no puede enviarse a aprobación.'
                    );
                }

                ExpenseReportSubmissionValidator::assertCanSubmit(
                    $locked
                );

                DB::table('approval_requests')
                    ->where(
                        'approvable_type',
                        ExpenseReport::class
                    )
                    ->where(
                        'approvable_id',
                        $locked->id
                    )
                    ->where(
                        'document_type',
                        self::DOCUMENT_TYPE
                    )
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'cancelled',
                        'completed_at' => now(),
                        'last_decision_reason' =>
                            'Reemplazada por una nueva solicitud de aprobación.',
                        'updated_at' => now(),
                    ]);

                $requesterId = auth()->id()
                    ?: $locked->created_by_user_id;

                $requester = $requesterId
                    ? DB::table('users')
                        ->where('id', $requesterId)
                        ->first()
                    : null;

                $approvalRequestId =
                    DB::table('approval_requests')
                        ->insertGetId([
                            'company_id' =>
                                $locked->company_id,

                            'approval_workflow_id' =>
                                $workflow->id,

                            'approvable_type' =>
                                ExpenseReport::class,

                            'approvable_id' =>
                                $locked->id,

                            'document_type' =>
                                self::DOCUMENT_TYPE,

                            'document_number' =>
                                $locked->number
                                ?: ('GAS-' . $locked->id),

                            'requester_user_id' =>
                                $requesterId,

                            'requester_name' =>
                                $requester->name
                                ?? $requester->email
                                ?? null,

                            'status' =>
                                'pending',

                            'current_step_order' =>
                                (int) (
                                    $steps->first()->sort_order
                                    ?? 1
                                ),

                            'amount_total' =>
                                $locked->total_amount,

                            'sent_at' =>
                                now(),

                            'notes' =>
                                'Comprobación de gastos enviada a aprobación.',

                            'created_at' =>
                                now(),

                            'updated_at' =>
                                now(),
                        ]);

                foreach ($steps as $step) {
                    $requestStepData = [
                        'approval_request_id' =>
                            $approvalRequestId,

                        'approval_workflow_step_id' =>
                            $step->id,

                        'step_order' =>
                            (int) (
                                $step->sort_order ?? 1
                            ),

                        'step_name' =>
                            (string) (
                                $step->name
                                ?? 'Aprobación'
                            ),

                        'approver_type' =>
                            (string) (
                                $step->approver_type
                                ?? 'specific_user'
                            ),

                        'approver_user_id' =>
                            $step->approver_user_id
                            ?? null,

                        'approver_role_name' =>
                            $step->approver_role_name
                            ?? null,

                        'status' =>
                            'pending',


                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ];

                /*
                 * Compatibilidad con instalaciones sin la extensión
                 * multi-aprobador. Gastos no exige approval_mode.
                 */
                if (
                    Schema::hasColumn(
                        'approval_request_steps',
                        'approval_mode'
                    )
                ) {
                    $requestStepData['approval_mode'] =
                        $step->approval_mode
                        ?? 'any';
                }

                DB::table(
                    'approval_request_steps'
                )->insert($requestStepData);
                }

                $metadata = is_array(
                    $locked->metadata
                )
                    ? $locked->metadata
                    : [];

                $metadata['approval_engine'] = [
                    'document_type' =>
                        self::DOCUMENT_TYPE,

                    'approval_request_id' =>
                        $approvalRequestId,

                    'approval_workflow_id' =>
                        $workflow->id,

                    'sent_at' =>
                        now()->toDateTimeString(),
                ];

                $locked->update([
                    'status' =>
                        'pending_approval',

                    'approval_request_id' =>
                        $approvalRequestId,

                    'approval_status' =>
                        'pending',

                    /*
                     * Al reenviar una comprobación previamente rechazada,
                     * conservar el historial en approval_requests pero
                     * limpiar el rechazo activo del documento.
                     */
                    'rejected_by_user_id' =>
                        null,

                    'rejected_at' =>
                        null,

                    'rejection_reason' =>
                        null,

                    'submitted_by_user_id' =>
                        $requesterId,

                    'submitted_at' =>
                        now(),

                    'approval_requested_at' =>
                        now(),

                    'metadata' =>
                        $metadata,
                ]);

                return ApprovalRequest::query()
                    ->findOrFail(
                        $approvalRequestId
                    );
            }
        );
    }

    protected static function findApplicableWorkflow(
        ExpenseReport $report
    ): ?object {
        $query = DB::table('approval_workflows')
            ->where(
                'company_id',
                $report->company_id
            )
            ->where(
                'document_type',
                self::DOCUMENT_TYPE
            )
            ->where('is_active', true)
            ->where(
                function ($query) use ($report) {
                    $query
                        ->whereNull('amount_min')
                        ->orWhere(
                            'amount_min',
                            '<=',
                            $report->total_amount
                        );
                }
            )
            ->where(
                function ($query) use ($report) {
                    $query
                        ->whereNull('amount_max')
                        ->orWhere(
                            'amount_max',
                            '>=',
                            $report->total_amount
                        );
                }
            )
            ->orderByDesc('priority')
            ->orderBy('id');

        return $query->first();
    }

    protected static function workflowSteps(
        object $workflow,
        ExpenseReport $report
    ) {
        return DB::table(
            'approval_workflow_steps'
        )
            ->where(
                'approval_workflow_id',
                $workflow->id
            )
            ->where('is_active', true)
            ->where(
                function ($query) use ($report) {
                    $query
                        ->whereNull('amount_min')
                        ->orWhere(
                            'amount_min',
                            '<=',
                            $report->total_amount
                        );
                }
            )
            ->where(
                function ($query) use ($report) {
                    $query
                        ->whereNull('amount_max')
                        ->orWhere(
                            'amount_max',
                            '>=',
                            $report->total_amount
                        );
                }
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public static function markApproved(
        object $approvalRequest,
        int $userId,
        ?string $comment = null
    ): void {
        $report = ExpenseReport::query()
            ->where('id', (int) ($approvalRequest->approvable_id ?? 0))
            ->where('company_id', (int) ($approvalRequest->company_id ?? 0))
            ->first();

        if (! $report) {
            throw new RuntimeException(
                'No se encontró la comprobación relacionada.'
            );
        }

        $report->update([
            'status' => 'approved',
            'approval_status' => 'approved',
            'approved_by_user_id' => $userId,
            'approved_at' => now(),
            'rejected_by_user_id' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        /*
         * Si es Caja Chica, la aprobación final debe reflejar
         * inmediatamente la salida financiera real.
         * El servicio es idempotente.
         */
        app(ExpenseReportPostingService::class)
            ->postApprovedPettyCash(
                $report->refresh(),
                $userId
            );
    }

    public static function markRejected(
        object $approvalRequest,
        int $userId,
        string $reason
    ): void {
        $report = ExpenseReport::query()
            ->where('id', (int) ($approvalRequest->approvable_id ?? 0))
            ->where('company_id', (int) ($approvalRequest->company_id ?? 0))
            ->first();

        if (! $report) {
            throw new RuntimeException(
                'No se encontró la comprobación relacionada.'
            );
        }

        $report->update([
            'status' => 'rejected',
            'approval_status' => 'rejected',
            'rejected_by_user_id' => $userId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public static function documentUrl(object|array $row): string
    {
        $id = (int) data_get($row, 'approvable_id', 0);
        $companyId = (int) data_get($row, 'company_id', 0);

        if ($id <= 0) {
            return '#';
        }

        if ($companyId <= 0) {
            $companyId = (int) ExpenseReport::query()
                ->whereKey($id)
                ->value('company_id');
        }

        if ($companyId <= 0) {
            return '#';
        }

        return url(
            '/admin/'
            . $companyId
            . '/expense-reports/'
            . $id
        );
    }


    public static function currentPendingRequest(
        ExpenseReport $report
    ): ?object {
        return DB::table('approval_requests')
            ->where('id', $report->approval_request_id)
            ->where('company_id', $report->company_id)
            ->where('document_type', self::DOCUMENT_TYPE)
            ->where('approvable_id', $report->id)
            ->where('status', 'pending')
            ->first();
    }

    public static function currentPendingStep(
        ExpenseReport $report
    ): ?object {
        $request = static::currentPendingRequest(
            $report
        );

        if (! $request) {
            return null;
        }

        return DB::table('approval_request_steps')
            ->where(
                'approval_request_id',
                $request->id
            )
            ->where(
                'step_order',
                $request->current_step_order
            )
            ->where('status', 'pending')
            ->orderBy('id')
            ->first();
    }

    public static function canCurrentUserAct(
        ExpenseReport $report,
        ?object $user = null
    ): bool {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        if (! ExpenseAccess::isAdmin()) {
            return false;
        }

        $step = static::currentPendingStep(
            $report
        );

        if (! $step) {
            return false;
        }

        if (
            ! empty($step->approver_user_id)
            && (int) $step->approver_user_id
                === (int) $user->id
        ) {
            return true;
        }

        $roleName = trim(
            (string) (
                $step->approver_role_name
                ?? ''
            )
        );

        if (
            $roleName !== ''
            && method_exists($user, 'hasRole')
            && $user->hasRole($roleName)
        ) {
            return true;
        }

        return false;
    }

    public static function approveFromDocument(
        ExpenseReport $report,
        object $user
    ): void {
        ExpenseAccess::assertAdmin();

        DB::transaction(
            function () use ($report, $user): void {
                $lockedReport =
                    ExpenseReport::query()
                        ->lockForUpdate()
                        ->findOrFail($report->id);

                $request = DB::table(
                    'approval_requests'
                )
                    ->where(
                        'id',
                        $lockedReport->approval_request_id
                    )
                    ->where(
                        'document_type',
                        self::DOCUMENT_TYPE
                    )
                    ->where(
                        'approvable_id',
                        $lockedReport->id
                    )
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if (! $request) {
                    throw new RuntimeException(
                        'La solicitud de aprobación ya no está pendiente.'
                    );
                }

                $step = DB::table(
                    'approval_request_steps'
                )
                    ->where(
                        'approval_request_id',
                        $request->id
                    )
                    ->where(
                        'step_order',
                        $request->current_step_order
                    )
                    ->where('status', 'pending')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (! $step) {
                    throw new RuntimeException(
                        'No existe una etapa pendiente para esta comprobación.'
                    );
                }

                if (
                    ! static::userCanActOnStep(
                        $step,
                        $user
                    )
                ) {
                    throw new RuntimeException(
                        'Tu usuario no es el aprobador de esta etapa.'
                    );
                }

                $comment =
                    'Comprobación de gastos aprobada.';

                $update = [
                    'status' => 'approved',
                    'acted_by_user_id' =>
                        $user->id,
                    'acted_by_name' =>
                        $user->name
                        ?? $user->email
                        ?? 'Usuario',
                    'acted_at' => now(),
                    'comments' => $comment,
                    'updated_at' => now(),
                ];

                if (
                    Schema::hasColumn(
                        'approval_request_steps',
                        'decision_reason'
                    )
                ) {
                    $update['decision_reason'] =
                        $comment;
                }

                DB::table(
                    'approval_request_steps'
                )
                    ->where('id', $step->id)
                    ->update($update);

                $mode = in_array(
                    (string) (
                        $step->approval_mode
                        ?? ''
                    ),
                    ['any', 'all'],
                    true
                )
                    ? (string) $step->approval_mode
                    : 'all';

                if ($mode === 'any') {
                    DB::table(
                        'approval_request_steps'
                    )
                        ->where(
                            'approval_request_id',
                            $request->id
                        )
                        ->where(
                            'step_order',
                            $step->step_order
                        )
                        ->where(
                            'id',
                            '<>',
                            $step->id
                        )
                        ->whereIn(
                            'status',
                            ['pending', 'waiting']
                        )
                        ->update([
                            'status' => 'skipped',
                            'updated_at' => now(),
                        ]);
                } else {
                    $remaining = DB::table(
                        'approval_request_steps'
                    )
                        ->where(
                            'approval_request_id',
                            $request->id
                        )
                        ->where(
                            'step_order',
                            $step->step_order
                        )
                        ->where(
                            'status',
                            'pending'
                        )
                        ->exists();

                    if ($remaining) {
                        return;
                    }
                }

                $nextOrder = DB::table(
                    'approval_request_steps'
                )
                    ->where(
                        'approval_request_id',
                        $request->id
                    )
                    ->whereIn(
                        'status',
                        ['pending', 'waiting']
                    )
                    ->where(
                        'step_order',
                        '>',
                        $step->step_order
                    )
                    ->min('step_order');

                if ($nextOrder !== null) {
                    DB::table(
                        'approval_request_steps'
                    )
                        ->where(
                            'approval_request_id',
                            $request->id
                        )
                        ->where(
                            'step_order',
                            $nextOrder
                        )
                        ->where(
                            'status',
                            'waiting'
                        )
                        ->update([
                            'status' => 'pending',
                            'updated_at' => now(),
                        ]);

                    DB::table(
                        'approval_requests'
                    )
                        ->where('id', $request->id)
                        ->update([
                            'current_step_order' =>
                                (int) $nextOrder,
                            'last_decision_reason' =>
                                $comment,
                            'updated_at' => now(),
                        ]);

                    return;
                }

                DB::table('approval_requests')
                    ->where('id', $request->id)
                    ->update([
                        'status' => 'approved',
                        'completed_at' => now(),
                        'last_decision_reason' =>
                            $comment,
                        'updated_at' => now(),
                    ]);

                static::markApproved(
                    $request,
                    (int) $user->id,
                    $comment
                );
            }
        );
    }

    public static function rejectFromDocument(
        ExpenseReport $report,
        object $user,
        string $reason
    ): void {
        ExpenseAccess::assertAdmin();

        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException(
                'El motivo de rechazo es obligatorio.'
            );
        }

        DB::transaction(
            function () use (
                $report,
                $user,
                $reason
            ): void {
                $lockedReport =
                    ExpenseReport::query()
                        ->lockForUpdate()
                        ->findOrFail($report->id);

                $request = DB::table(
                    'approval_requests'
                )
                    ->where(
                        'id',
                        $lockedReport->approval_request_id
                    )
                    ->where(
                        'document_type',
                        self::DOCUMENT_TYPE
                    )
                    ->where(
                        'approvable_id',
                        $lockedReport->id
                    )
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if (! $request) {
                    throw new RuntimeException(
                        'La solicitud de aprobación ya no está pendiente.'
                    );
                }

                $step = DB::table(
                    'approval_request_steps'
                )
                    ->where(
                        'approval_request_id',
                        $request->id
                    )
                    ->where(
                        'step_order',
                        $request->current_step_order
                    )
                    ->where('status', 'pending')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (! $step) {
                    throw new RuntimeException(
                        'No existe una etapa pendiente para esta comprobación.'
                    );
                }

                if (
                    ! static::userCanActOnStep(
                        $step,
                        $user
                    )
                ) {
                    throw new RuntimeException(
                        'Tu usuario no es el aprobador de esta etapa.'
                    );
                }

                $update = [
                    'status' => 'rejected',
                    'acted_by_user_id' =>
                        $user->id,
                    'acted_by_name' =>
                        $user->name
                        ?? $user->email
                        ?? 'Usuario',
                    'acted_at' => now(),
                    'comments' => $reason,
                    'updated_at' => now(),
                ];

                if (
                    Schema::hasColumn(
                        'approval_request_steps',
                        'decision_reason'
                    )
                ) {
                    $update['decision_reason'] =
                        $reason;
                }

                DB::table(
                    'approval_request_steps'
                )
                    ->where('id', $step->id)
                    ->update($update);

                DB::table(
                    'approval_request_steps'
                )
                    ->where(
                        'approval_request_id',
                        $request->id
                    )
                    ->where(
                        'id',
                        '<>',
                        $step->id
                    )
                    ->whereIn(
                        'status',
                        ['pending', 'waiting']
                    )
                    ->update([
                        'status' => 'skipped',
                        'updated_at' => now(),
                    ]);

                DB::table(
                    'approval_requests'
                )
                    ->where('id', $request->id)
                    ->update([
                        'status' => 'rejected',
                        'completed_at' => now(),
                        'last_decision_reason' =>
                            $reason,
                        'updated_at' => now(),
                    ]);

                static::markRejected(
                    $request,
                    (int) $user->id,
                    $reason
                );
            }
        );
    }

    protected static function userCanActOnStep(
        object $step,
        object $user
    ): bool {
        if (
            ! empty($step->approver_user_id)
            && (int) $step->approver_user_id
                === (int) $user->id
        ) {
            return true;
        }

        $roleName = trim(
            (string) (
                $step->approver_role_name
                ?? ''
            )
        );

        return $roleName !== ''
            && method_exists($user, 'hasRole')
            && $user->hasRole($roleName);
    }

}
