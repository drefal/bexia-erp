<?php

namespace App\Filament\Pages;

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class AttendancePeriodSummary extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'RRHH';
    protected static ?string $navigationLabel = 'Resumen de asistencia';
    protected static ?string $title = 'Resumen de asistencia';
    protected static ?string $slug = 'rrhh/resumen-asistencia';
    protected static ?int $navigationSort = 24;
    protected static string $view = 'filament.pages.attendance-period-summary';

    public string $from = '';
    public string $to = '';
    public string $payrollRunId = '';
    public string $payrollGroupId = '';

    public bool $showReviewResendConfirmation = false;
    public array $reviewResendPreview = [];
    public string $reviewResendReason = '';

    public bool $showReviewSendConfirmation = false;
    public array $reviewSendPreview = [];

    public ?int $selectedEmployeeId = null;
    public ?string $selectedDate = null;

    public function mount(): void
    {
        $this->from = now()->subDays(6)->toDateString();
        $this->to = now()->toDateString();
    }

    public static function canAccess(): bool
    {
        return AttendanceReport::canAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function companyId(): int
    {
        return (int) (Filament::getTenant()?->getKey() ?? 0);
    }

    /**
     * Seguimiento informativo de vistos buenos.
     * No crea, envia ni aprueba revisiones.
     */
    public function attendanceReviewOverview(): ?array
    {
        if (!static::canAccess()) {
            return null;
        }

        $companyId = $this->companyId();

        if ($companyId <= 0 || $this->payrollRunId === '') {
            return null;
        }

        $run = DB::table('payroll_runs')
            ->where('company_id', $companyId)
            ->where('id', (int) $this->payrollRunId)
            ->first([
                'id', 'name', 'payroll_group_id',
                'period_start', 'period_end',
            ]);

        if (!$run) {
            return null;
        }

        // No asociar un visto bueno a filtros distintos de la corrida.
        if (
            $this->from !== (string) $run->period_start
            || $this->to !== (string) $run->period_end
            || $this->payrollGroupId
                !== (string) ($run->payroll_group_id ?? '')
        ) {
            return [
                'filter_mismatch' => true,
                'run_name' => $run->name,
            ];
        }

        $batch = DB::table(
            'hr_payroll_attendance_review_batches'
        )
            ->where('company_id', $companyId)
            ->where('payroll_run_id', (int) $run->id)
            ->first();

        $distribution =
            \App\Support\PayrollAttendanceReviewDistribution
                ::preview($companyId, (int) $run->id);

        $items = collect();
        $reviewStatuses = collect();

        if ($batch) {
            $items = DB::table(
                'hr_payroll_attendance_review_items'
            )
                ->where('company_id', $companyId)
                ->where('batch_id', $batch->id)
                ->orderBy('employee_id')
                ->get([
                    'id', 'employee_id', 'reviewer_user_id',
                    'assignment_issue', 'snapshot_hash',
                ]);

            $reviewStatuses = DB::table(
                'hr_payroll_attendance_reviews'
            )
                ->where('company_id', $companyId)
                ->where('batch_id', $batch->id)
                ->select('status')
                ->get()
                ->countBy('status');
        }

        $integrity = [
            'unchanged' => 0,
            'changed' => 0,
            'invalid_snapshot' => 0,
            'missing_snapshot' => 0,
        ];

        foreach ($items as $item) {
            try {
                $result =
                    \App\Support\PayrollAttendanceSnapshotService
                        ::compareItem((int) $item->id);

                $status = $result['status'] ?? 'invalid_snapshot';
            } catch (\Throwable $e) {
                report($e);
                $status = 'invalid_snapshot';
            }

            $integrity[$status] =
                ($integrity[$status] ?? 0) + 1;
        }

        $pendingIds = collect($distribution['pending'])
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $names = DB::table('employees')
            ->where('company_id', $companyId)
            ->whereIn('id', $pendingIds)
            ->pluck('name', 'id');

        $pending = array_map(
            static fn (array $row) => [
                'employee_id' => $row['employee_id'],
                'employee_name' => (string) (
                    $names[$row['employee_id']] ?? (
                        'Empleado #' . $row['employee_id']
                    )
                ),
                'department_id' => $row['department_id'],
                'reason' => $row['reason'],
            ],
            $distribution['pending']
        );

        $draftInspection = $batch
            ? \App\Support\PayrollAttendanceReviewDraftSync::inspect(
                $companyId,
                (int) $run->id
            )
            : ['status' => 'not_created'];

        $actorUserId = (int) (auth()->id() ?? 0);

        $canManageReviews = $actorUserId > 0
            && \App\Support\PayrollAttendanceReviewAuthorization::allowed(
                $actorUserId,
                $companyId,
                \App\Support\PayrollAttendanceReviewAuthorization::MANAGE
            );

        // Seguimiento exclusivo para responsables de gestion RRHH.
        // No habilita acceso a coordinadores ni modifica revisiones.
        $reviewTracking = [];

        if (
            $canManageReviews
            && $batch
            && $batch->status === 'sent'
        ) {
            $reviewRows = DB::table(
                'hr_payroll_attendance_reviews as r'
            )
                ->leftJoin(
                    'users as u',
                    'u.id',
                    '=',
                    'r.reviewer_user_id'
                )
                ->where('r.company_id', $companyId)
                ->where('r.batch_id', $batch->id)
                ->orderBy('r.reviewer_user_id')
                ->get([
                    'r.id',
                    'r.reviewer_user_id',
                    'u.name as reviewer_name',
                    'r.status',
                    'r.version',
                    'r.sent_at',
                    'r.reviewed_at',
                    'r.reviewer_comment',
                ]);

            foreach ($reviewRows as $reviewRow) {
                $employeeIds = DB::table(
                    'hr_payroll_attendance_review_items'
                )
                    ->where('company_id', $companyId)
                    ->where('batch_id', $batch->id)
                    ->where('review_id', $reviewRow->id)
                    ->orderBy('employee_id')
                    ->pluck('employee_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $employeeNames = DB::table('employees')
                    ->where('company_id', $companyId)
                    ->whereIn('id', $employeeIds)
                    ->pluck('name', 'id');

                $reviewTracking[] = [
                    'review_id' => (int) $reviewRow->id,
                    'reviewer_user_id' => (int) $reviewRow->reviewer_user_id,
                    'reviewer_name' => (string) (
                        $reviewRow->reviewer_name
                        ?: 'Usuario #' . $reviewRow->reviewer_user_id
                    ),
                    'status' => (string) $reviewRow->status,
                    'version' => (int) $reviewRow->version,
                    'sent_at' => $reviewRow->sent_at,
                    'reviewed_at' => $reviewRow->reviewed_at,
                    'comment' => $reviewRow->reviewer_comment,
                    'employees' => array_map(
                        static fn (int $id): array => [
                            'id' => $id,
                            'name' => (string) (
                                $employeeNames[$id]
                                ?? ('Empleado #' . $id)
                            ),
                        ],
                        $employeeIds
                    ),
                ];
            }
        }

        $draftReady = ($draftInspection['status'] ?? null) === 'ready'
            && (int) $distribution['pending_employees'] === 0;

        return [
            'draft_inspection_status' => $draftInspection['status'],
            'draft_ready' => $draftReady,
            'can_manage_reviews' => $canManageReviews,
            'operational_send_enabled' => false,
            'filter_mismatch' => false,
            'run_name' => (string) (
                $run->name ?: 'Prenomina #' . $run->id
            ),
            'batch_id' => $batch?->id,
            'batch_status' => $batch?->status ?? 'not_created',
            'total_employees' =>
                $distribution['total_employees'],
            'assigned_employees' =>
                $distribution['assigned_employees'],
            'pending_employees' =>
                $distribution['pending_employees'],
            'reviewer_groups' => count($distribution['groups']),
            'review_statuses' => $reviewStatuses->all(),
            'review_tracking' => $reviewTracking,
            'snapshot_integrity' => $integrity,
            'pending' => $pending,
        ];
    }

    /**
     * Prevalidacion informativa.
     * Nunca envia revisiones ni modifica la base de datos.
     */
    public function checkAttendanceReviewSendReadiness(): void
    {
        abort_unless(static::canAccess(), 403);

        $companyId = $this->companyId();
        $actorUserId = (int) (auth()->id() ?? 0);

        abort_unless(
            $companyId > 0
            && $actorUserId > 0
            && \App\Support\PayrollAttendanceReviewAuthorization::allowed(
                $actorUserId,
                $companyId,
                \App\Support\PayrollAttendanceReviewAuthorization::MANAGE
            ),
            403
        );

        $notify = static function (string $message): void {
            \Filament\Notifications\Notification::make()
                ->title('Comprobacion de envio')
                ->body($message)
                ->warning()
                ->send();
        };

        if (
            $this->payrollRunId === ''
            || !ctype_digit($this->payrollRunId)
        ) {
            $notify('Selecciona una prenomina existente.');
            return;
        }

        $runId = (int) $this->payrollRunId;

        $run = DB::table('payroll_runs')
            ->where('company_id', $companyId)
            ->where('id', $runId)
            ->first();

        if (!$run) {
            $notify('La prenomina no corresponde a esta empresa.');
            return;
        }

        if (
            $this->from !== (string) $run->period_start
            || $this->to !== (string) $run->period_end
            || $this->payrollGroupId
                !== (string) ($run->payroll_group_id ?? '')
        ) {
            $notify('Los filtros no coinciden con la prenomina.');
            return;
        }

        if (
            $run->status !== 'calculated'
            || (bool) $run->is_locked
        ) {
            $notify('La prenomina no esta disponible para envio.');
            return;
        }

        $inspection =
            \App\Support\PayrollAttendanceReviewDraftSync::inspect(
                $companyId,
                $runId
            );

        if (
            ($inspection['status'] ?? null) !== 'ready'
            || (int) ($inspection['pending_employees'] ?? -1) !== 0
        ) {
            $notify('El borrador requiere actualizacion o asignaciones.');
            return;
        }

        $batch = DB::table(
            'hr_payroll_attendance_review_batches'
        )
            ->where('company_id', $companyId)
            ->where('payroll_run_id', $runId)
            ->first();

        if (!$batch || $batch->status !== 'draft') {
            $notify('El lote ya no se encuentra en borrador.');
            return;
        }

        $items = DB::table(
            'hr_payroll_attendance_review_items'
        )
            ->where('company_id', $companyId)
            ->where('batch_id', $batch->id)
            ->get(['employee_id', 'reviewer_user_id']);

        $payrollIds = DB::table('payroll_run_lines')
            ->where('company_id', $companyId)
            ->where('payroll_run_id', $runId)
            ->distinct()
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->sort()->values()->all();

        $itemIds = $items->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->sort()->values()->all();

        if ($items->isEmpty() || $payrollIds !== $itemIds) {
            $notify('Los empleados no coinciden con la prenomina calculada.');
            return;
        }

        $reviewerIds = $items->pluck('reviewer_user_id')
            ->unique()->all();

        foreach ($reviewerIds as $reviewerId) {
            $reviewerId = (int) $reviewerId;

            if (
                $reviewerId <= 0
                || !\App\Support\PayrollAttendanceReviewAuthorization::allowed(
                    $reviewerId,
                    $companyId,
                    \App\Support\PayrollAttendanceReviewAuthorization::VIEW
                )
                || !\App\Support\PayrollAttendanceReviewAuthorization::allowed(
                    $reviewerId,
                    $companyId,
                    \App\Support\PayrollAttendanceReviewAuthorization::APPROVE
                )
            ) {
                $notify(
                    'Faltan permisos de consulta o visto bueno '
                    . 'en uno o mas responsables.'
                );
                return;
            }
        }

        \Filament\Notifications\Notification::make()
            ->title('Datos preparados')
            ->body(
                'Las comprobaciones preliminares pasaron. '
                . 'El envio real sigue deshabilitado.'
            )
            ->success()
            ->send();
    }

    public function openReviewSendConfirmation(): void
    {
        $this->showReviewSendConfirmation = false;
        $this->reviewSendPreview = [];

        abort_unless(static::canAccess(), 403);

        $companyId = $this->companyId();
        $userId = (int) (auth()->id() ?? 0);

        abort_unless(
            $companyId > 0
            && $userId > 0
            && \App\Support\PayrollAttendanceReviewAuthorization::allowed(
                $userId,
                $companyId,
                \App\Support\PayrollAttendanceReviewAuthorization::MANAGE
            ),
            403
        );

        if (
            $this->payrollRunId === ''
            || !ctype_digit($this->payrollRunId)
        ) {
            return;
        }

        $runId = (int) $this->payrollRunId;
        $run = DB::table('payroll_runs')
            ->where('company_id', $companyId)
            ->where('id', $runId)
            ->first();

        if (
            !$run
            || $this->from !== (string) $run->period_start
            || $this->to !== (string) $run->period_end
            || $this->payrollGroupId
                !== (string) ($run->payroll_group_id ?? '')
            || $run->status !== 'calculated'
            || (bool) $run->is_locked
        ) {
            return;
        }

        $inspection =
            \App\Support\PayrollAttendanceReviewDraftSync::inspect(
                $companyId,
                $runId
            );

        $distribution =
            \App\Support\PayrollAttendanceReviewDistribution::preview(
                $companyId,
                $runId
            );

        $batch = DB::table(
            'hr_payroll_attendance_review_batches'
        )
            ->where('company_id', $companyId)
            ->where('payroll_run_id', $runId)
            ->first();

        if (
            !$batch
            || $batch->status !== 'draft'
            || ($inspection['status'] ?? null) !== 'ready'
            || (int) $distribution['pending_employees'] !== 0
            || (int) $distribution['total_employees'] <= 0
        ) {
            return;
        }

        $this->reviewSendPreview = [
            'run_name' => (string) $run->name,
            'period_start' => (string) $run->period_start,
            'period_end' => (string) $run->period_end,
            'employees' => (int) $distribution['total_employees'],
            'reviewers' => count($distribution['groups']),
            'batch_version' => (int) $batch->version,
            'batch_id' => (int) $batch->id,
            'run_id' => $runId,
            'company_id' => $companyId,
        ];

        $this->showReviewSendConfirmation = true;
    }

    /**
     * Envia exclusivamente al confirmar un usuario autorizado.
     * Revalida tenant, actor, filtros, lote y version en servidor.
     */
    public function confirmReviewSend(): void
    {
        abort_unless(static::canAccess(), 403);

        $companyId = $this->companyId();
        $actorId = (int) (auth()->id() ?? 0);

        abort_unless(
            $companyId > 0
            && $actorId > 0
            && \App\Support\PayrollAttendanceReviewAuthorization::allowed(
                $actorId,
                $companyId,
                \App\Support\PayrollAttendanceReviewAuthorization::MANAGE
            ),
            403
        );

        if (!$this->showReviewSendConfirmation) {
            \Filament\Notifications\Notification::make()
                ->title('Confirmación no disponible')
                ->body('Abre nuevamente la ventana de revisión.')
                ->warning()
                ->send();
            return;
        }

        $preview = $this->reviewSendPreview;

        // Solo se admite la prenomina previamente previsualizada.
        $runId = (int) ($preview['run_id'] ?? 0);
        $batchId = (int) ($preview['batch_id'] ?? 0);
        $version = (int) ($preview['batch_version'] ?? 0);

        $validIds = $runId > 0
            && $batchId > 0
            && $version > 0
            && (int) ($preview['company_id'] ?? 0) === $companyId
            && $this->payrollRunId === (string) $runId;

        $run = $validIds
            ? DB::table('payroll_runs')
                ->where('company_id', $companyId)
                ->where('id', $runId)
                ->first()
            : null;

        $batch = $validIds
            ? DB::table('hr_payroll_attendance_review_batches')
                ->where('company_id', $companyId)
                ->where('payroll_run_id', $runId)
                ->where('id', $batchId)
                ->first()
            : null;

        if (
            !$run
            || !$batch
            || $this->from !== (string) $run->period_start
            || $this->to !== (string) $run->period_end
            || $this->payrollGroupId
                !== (string) ($run->payroll_group_id ?? '')
            || $run->status !== 'calculated'
            || (bool) $run->is_locked
            || $batch->status !== 'draft'
            || $batch->sent_at !== null
            || (int) $batch->version !== $version
        ) {
            $this->closeReviewSendConfirmation();

            \Filament\Notifications\Notification::make()
                ->title('Envío cancelado')
                ->body(
                    'Los datos de la prenómina o del lote cambiaron. '
                    . 'Actualiza la página y vuelve a comprobarlos.'
                )
                ->warning()
                ->send();
            return;
        }

        try {
            // El servicio realiza las comprobaciones finales y
            // el bloqueo transaccional contra envios duplicados.
            \App\Support\PayrollAttendanceReviewDispatch::send(
                $companyId,
                $runId,
                $actorId
            );

            $this->closeReviewSendConfirmation();

            \Filament\Notifications\Notification::make()
                ->title('Revisiones enviadas')
                ->body(
                    'El lote fue enviado a los responsables asignados. '
                    . 'Ya puedes consultar su seguimiento.'
                )
                ->success()
                ->send();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'RRHH attendance review dispatch failed',
                [
                    'company_id' => $companyId,
                    'payroll_run_id' => $runId,
                    'actor_user_id' => $actorId,
                    'exception' => $e,
                ]
            );

            $this->closeReviewSendConfirmation();

            \Filament\Notifications\Notification::make()
                ->title('No se pudo completar el envío')
                ->body(
                    'No se confirmó el envío. Revisa los datos '
                    . 'y vuelve a comprobar la preparación.'
                )
                ->danger()
                ->send();
        }
    }

    public function openReviewResendConfirmation(): void
    {
        $this->closeReviewResendConfirmation();
        abort_unless(static::canAccess(), 403);

        $companyId = $this->companyId();
        $userId = (int) (auth()->id() ?? 0);

        abort_unless(
            $companyId > 0
            && $userId > 0
            && \App\Support\PayrollAttendanceReviewAuthorization::allowed(
                $userId,
                $companyId,
                \App\Support\PayrollAttendanceReviewAuthorization::MANAGE
            ),
            403
        );

        if (
            $this->payrollRunId === ''
            || !ctype_digit($this->payrollRunId)
        ) {
            return;
        }

        $runId = (int) $this->payrollRunId;
        $run = DB::table('payroll_runs')
            ->where('id', $runId)
            ->where('company_id', $companyId)
            ->first();

        if (
            !$run
            || $this->from !== (string) $run->period_start
            || $this->to !== (string) $run->period_end
            || $this->payrollGroupId
                !== (string) ($run->payroll_group_id ?? '')
        ) {
            return;
        }

        $preview =
            \App\Support\PayrollAttendanceReviewResendPreview::inspect(
                $companyId,
                $runId,
                $userId
            );

        if (($preview['status'] ?? null) !== 'eligible_for_reprepare') {
            \Filament\Notifications\Notification::make()
                ->title('Reenvío no disponible')
                ->body('Existen revisiones pendientes, aprobadas o inconsistentes.')
                ->warning()
                ->send();
            return;
        }

        $returned = collect($preview['reviews'] ?? [])
            ->where('status', 'returned')
            ->values()
            ->all();

        $userIds = collect($returned)
            ->pluck('reviewer_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $names = DB::table('users')
            ->whereIn('id', $userIds)
            ->pluck('name', 'id');

        $this->reviewResendPreview = [
            'company_id' => $companyId,
            'run_id' => $runId,
            'batch_id' => (int) $preview['batch_id'],
            'version' => (int) $preview['batch_version'],
            'run_name' => (string) $run->name,
            'returned' => array_map(
                static fn (array $row): array => [
                    'reviewer_name' => (string) (
                        $names[$row['reviewer_user_id']]
                        ?? ('Usuario #' . $row['reviewer_user_id'])
                    ),
                    'employee_count' => count($row['employees'] ?? []),
                    'comment' => (string) ($row['comment'] ?? ''),
                ],
                $returned
            ),
        ];

        $this->showReviewResendConfirmation = true;
    }

    public function closeReviewResendConfirmation(): void
    {
        $this->showReviewResendConfirmation = false;
        $this->reviewResendPreview = [];
        $this->reviewResendReason = '';
    }

    public function confirmReviewResend(): void
    {
        abort_unless(static::canAccess(), 403);

        $companyId = $this->companyId();
        $userId = (int) (auth()->id() ?? 0);

        abort_unless(
            $companyId > 0
            && $userId > 0
            && \App\Support\PayrollAttendanceReviewAuthorization::allowed(
                $userId,
                $companyId,
                \App\Support\PayrollAttendanceReviewAuthorization::MANAGE
            ),
            403
        );

        if (!$this->showReviewResendConfirmation) {
            return;
        }

        $preview = $this->reviewResendPreview;
        $runId = (int) ($preview['run_id'] ?? 0);
        $batchId = (int) ($preview['batch_id'] ?? 0);
        $version = (int) ($preview['version'] ?? 0);
        $reason = trim($this->reviewResendReason);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            \Filament\Notifications\Notification::make()
                ->title('Justificación requerida')
                ->body('Escribe un motivo de entre 10 y 2000 caracteres.')
                ->warning()
                ->send();
            return;
        }

        $run = $runId > 0
            ? DB::table('payroll_runs')
                ->where('company_id', $companyId)
                ->where('id', $runId)
                ->first()
            : null;

        $batch = $batchId > 0
            ? DB::table('hr_payroll_attendance_review_batches')
                ->where('company_id', $companyId)
                ->where('payroll_run_id', $runId)
                ->where('id', $batchId)
                ->first()
            : null;

        if (
            !$run
            || !$batch
            || (int) ($preview['company_id'] ?? 0) !== $companyId
            || $this->payrollRunId !== (string) $runId
            || $this->from !== (string) $run->period_start
            || $this->to !== (string) $run->period_end
            || $this->payrollGroupId
                !== (string) ($run->payroll_group_id ?? '')
            || $batch->status !== 'sent'
            || (int) $batch->version !== $version
        ) {
            $this->closeReviewResendConfirmation();

            \Filament\Notifications\Notification::make()
                ->title('Reenvío cancelado')
                ->body('La revisión cambió. Actualiza la página.')
                ->warning()
                ->send();
            return;
        }

        try {
            $fresh =
                \App\Support\PayrollAttendanceReviewResendPreview::inspect(
                    $companyId,
                    $runId,
                    $userId
                );

            if (
                ($fresh['status'] ?? null) !== 'eligible_for_reprepare'
                || (int) $fresh['batch_id'] !== $batchId
                || (int) $fresh['batch_version'] !== $version
            ) {
                throw new \RuntimeException(
                    'El lote dejó de estar disponible para reenvío.'
                );
            }

            // El servicio vuelve a validar y bloquea el lote
            // dentro de su propia transaccion.
            \App\Support\PayrollAttendanceReviewResend::resend(
                $companyId,
                $runId,
                $userId,
                $reason
            );

            $this->closeReviewResendConfirmation();

            \Filament\Notifications\Notification::make()
                ->title('Revisión reenviada')
                ->body('Se generó una nueva versión para los responsables.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'RRHH attendance review resend failed',
                [
                    'company_id' => $companyId,
                    'run_id' => $runId,
                    'actor_id' => $userId,
                    'error' => $e->getMessage(),
                ]
            );

            $this->closeReviewResendConfirmation();

            \Filament\Notifications\Notification::make()
                ->title('No se pudo reenviar')
                ->body('Comprueba nuevamente el estado y las asignaciones.')
                ->danger()
                ->send();
        }
    }

    public function closeReviewSendConfirmation(): void
    {
        $this->showReviewSendConfirmation = false;
        $this->reviewSendPreview = [];
    }

    public function payrollRuns(): array
    {
        $companyId = $this->companyId();

        if (!$companyId) {
            return [];
        }

        return DB::table('payroll_runs')
            ->where('company_id', $companyId)
            ->orderByDesc('period_start')
            ->limit(50)
            ->get([
                'id', 'name', 'period_start',
                'period_end', 'payroll_group_id'
            ])
            ->mapWithKeys(fn ($r) => [
                (string) $r->id =>
                    ($r->name ?: 'Nómina #' . $r->id)
                    . ' (' . $r->period_start
                    . ' a ' . $r->period_end . ')'
            ])->all();
    }

    public function payrollGroups(): array
    {
        $companyId = $this->companyId();

        if (!$companyId) {
            return [];
        }

        return DB::table('payroll_groups')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [
                (string) $id => $name
            ])->all();
    }

    public function updatedPayrollRunId(): void
    {
        if (!$this->payrollRunId) {
            return;
        }

        $run = DB::table('payroll_runs')
            ->where('company_id', $this->companyId())
            ->where('id', (int) $this->payrollRunId)
            ->first();

        if (!$run) {
            $this->payrollRunId = '';
            return;
        }

        $this->from = (string) $run->period_start;
        $this->to = (string) $run->period_end;
        $this->payrollGroupId = (string) ($run->payroll_group_id ?? '');
    }

    public function showDay(int $employeeId, string $date): void
    {
        $this->selectedEmployeeId = null;
        $this->selectedDate = null;

        $companyId = $this->companyId();

        if ($companyId <= 0) {
            return;
        }

        try {
            $selected = CarbonImmutable::parse($date)->toDateString();
            $from = CarbonImmutable::parse($this->from)->toDateString();
            $to = CarbonImmutable::parse($this->to)->toDateString();
        } catch (\Throwable) {
            return;
        }

        if ($selected < $from || $selected > $to) {
            return;
        }

        $employee = DB::table('employees')
            ->where('company_id', $companyId)
            ->where('id', $employeeId)
            ->when(
                $this->payrollGroupId !== '',
                fn ($q) => $q->where(
                    'payroll_group_id',
                    (int) $this->payrollGroupId
                )
            )
            ->exists();

        if (!$employee) {
            return;
        }

        $this->selectedEmployeeId = $employeeId;
        $this->selectedDate = $selected;
    }

    public function closeDay(): void
    {
        $this->selectedEmployeeId = null;
        $this->selectedDate = null;
    }

    public function dayDetail(): ?array
    {
        if (!$this->selectedEmployeeId || !$this->selectedDate) {
            return null;
        }

        $companyId = $this->companyId();

        $employee = DB::table('employees')
            ->where('company_id', $companyId)
            ->where('id', $this->selectedEmployeeId)
            ->when(
                $this->payrollGroupId !== '',
                fn ($q) => $q->where(
                    'payroll_group_id',
                    (int) $this->payrollGroupId
                )
            )
            ->first(['id', 'name']);

        if (!$employee) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse(
                $this->selectedDate
            )->toDateString();
            $from = CarbonImmutable::parse($this->from)->toDateString();
            $to = CarbonImmutable::parse($this->to)->toDateString();
        } catch (\Throwable) {
            return null;
        }

        if ($date < $from || $date > $to) {
            return null;
        }

        $attendance = DB::table('employee_attendances')
            ->where('company_id', $companyId)
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $date)
            ->orderByDesc('id')
            ->first();

        $breaks = collect();

        if (
            $attendance
            && DB::getSchemaBuilder()->hasTable(
                'employee_attendance_breaks'
            )
            && DB::getSchemaBuilder()->hasColumn(
                'employee_attendance_breaks',
                'employee_attendance_id'
            )
        ) {
            $breaks = DB::table('employee_attendance_breaks')
                ->where('employee_attendance_id', $attendance->id)
                ->orderBy('id')
                ->get();
        }

        $incidents = DB::table('employee_incidents as i')
            ->join(
                'hr_incident_types as t',
                't.id',
                '=',
                'i.hr_incident_type_id'
            )
            ->where('i.company_id', $companyId)
            ->where('i.employee_id', $employee->id)
            ->whereDate('i.start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('i.end_date')
                  ->orWhereDate('i.end_date', '>=', $date);
            })
            ->orderBy('i.id')
            ->get([
                't.name as type_name',
                't.code',
                'i.status',
                'i.start_time',
                'i.end_time',
                'i.quantity',
                'i.quantity_unit',
            ]);

        return [
            'employee' => $employee->name,
            'date' => $date,
            'attendance' => $attendance,
            'breaks' => $breaks,
            'incidents' => $incidents,
        ];
    }

    protected function exportMetadata(): array
    {
        $companyId = $this->companyId();

        $company = DB::table('companies')
            ->where('id', $companyId)->value('name');

        $group = 'Todos los empleados';

        if ($this->payrollGroupId !== '') {
            $group = DB::table('payroll_groups')
                ->where('company_id', $companyId)
                ->where('id', (int) $this->payrollGroupId)
                ->value('name') ?: 'Grupo no disponible';
        }

        $run = 'Periodo personalizado';

        if ($this->payrollRunId !== '') {
            $run = DB::table('payroll_runs')
                ->where('company_id', $companyId)
                ->where('id', (int) $this->payrollRunId)
                ->value('name') ?: 'Periodo personalizado';
        }

        return [
            'company' => (string) ($company ?: 'Empresa'),
            'run' => (string) $run,
            'group' => (string) $group,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }

    public function exportExcel()
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->matrix();

        if ($data['error'] !== null) {
            throw new \RuntimeException($data['error']);
        }

        $bytes = \App\Support\AttendancePeriodSummaryExporter
            ::xlsx($data, $this->exportMetadata());

        return response()->streamDownload(
            static function () use ($bytes): void {
                echo $bytes;
            },
            'resumen_asistencia_' . $this->from
                . '_' . $this->to . '.xlsx',
            [
                'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }

    public function exportPdf()
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->matrix();

        if ($data['error'] !== null) {
            throw new \RuntimeException($data['error']);
        }

        $bytes = \App\Support\AttendancePeriodSummaryExporter
            ::pdf($data, $this->exportMetadata());

        return response()->streamDownload(
            static function () use ($bytes): void {
                echo $bytes;
            },
            'resumen_asistencia_' . $this->from
                . '_' . $this->to . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function matrix(): array
    {
        $empty = [
            'days' => [],
            'rows' => [],
            'totals' => [
                'employees' => 0,
                'attendance' => 0,
                'incomplete' => 0,
                'possible_absences' => 0,
                'unknown' => 0,
                'late_days' => 0,
                'early_days' => 0,
                'overtime_minutes' => 0,
            ],
            'error' => null,
        ];

        $companyId = $this->companyId();

        if (!$companyId) {
            $empty['error'] = 'Selecciona una empresa.';
            return $empty;
        }

        try {
            $from = CarbonImmutable::parse($this->from)->startOfDay();
            $to = CarbonImmutable::parse($this->to)->startOfDay();
        } catch (\Throwable) {
            $empty['error'] = 'Fechas no válidas.';
            return $empty;
        }

        if ($from->greaterThan($to) || $from->diffInDays($to) > 30) {
            $empty['error'] = 'Selecciona un rango de 1 a 31 días.';
            return $empty;
        }

        $days = [];

        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $days[] = [
                'date' => $day->toDateString(),
                'label' => $day->format('d/m'),
                'weekday' => ['D', 'L', 'M', 'M', 'J', 'V', 'S'][
                    $day->dayOfWeek
                ],
                'weekday_number' => (int) $day->format('N'),
            ];
        }

        $employees = DB::table('employees')
            ->where('company_id', $companyId)
            ->where(function ($q) use ($from) {
                $q->where('active', true)
                    ->orWhereDate('termination_date', '>=', $from->toDateString());
            })
            ->where(function ($q) use ($to) {
                $q->whereNull('hire_date')
                    ->orWhereDate('hire_date', '<=', $to->toDateString());
            })
            ->when(
                $this->payrollGroupId !== '',
                fn ($q) => $q->where(
                    'payroll_group_id',
                    (int) $this->payrollGroupId
                )
            )
            ->orderBy('name')
            ->get([
                'id', 'name', 'employee_number',
                'hr_work_schedule_id', 'hire_date',
                'termination_date'
            ]);

        $attendances = DB::table('employee_attendances')
            ->where('company_id', $companyId)
            ->whereBetween('attendance_date', [
                $from->toDateString(), $to->toDateString()
            ])
            ->get([
                'id', 'employee_id', 'attendance_date',
                'clock_in_at', 'clock_out_at',
                'status', 'late_minutes',
                'early_leave_minutes', 'overtime_minutes'
            ])
            ->keyBy(fn ($a) =>
                $a->employee_id . '|' . $a->attendance_date
            );

        /*
         * Horario directo y, cuando no existe, contrato vigente
         * para cada fecha consultada.
         * No cambia las asignaciones de empleados.
         */
        $contracts = DB::table('employee_contracts')
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employees->pluck('id')->all())
            ->where('status', 'active')
            ->where('is_current', true)
            ->whereNotNull('hr_work_schedule_id')
            ->whereDate('start_date', '<=', $to->toDateString())
            ->where(function ($q) use ($from) {
                $q->whereNull('end_date')
                  ->orWhereDate('end_date', '>=', $from->toDateString());
            })
            ->orderByDesc('start_date')
            ->get([
                'employee_id', 'hr_work_schedule_id',
                'start_date', 'end_date'
            ])
            ->groupBy('employee_id');

        $scheduleIds = $employees
            ->pluck('hr_work_schedule_id')
            ->merge(
                $contracts->flatten(1)
                    ->pluck('hr_work_schedule_id')
            )
            ->filter()->unique()->values()->all();

        $schedules = DB::table('hr_work_schedules')
            ->where('company_id', $companyId)
            ->whereIn('id', $scheduleIds)
            ->get(['id', 'name', 'schedule_type'])
            ->keyBy('id');

        $scheduleDays = DB::table('hr_work_schedule_days')
            ->where('company_id', $companyId)
            ->whereIn('hr_work_schedule_id', $scheduleIds)
            ->get([
                'hr_work_schedule_id',
                'day_index', 'is_working_day'
            ])
            ->keyBy(fn ($s) =>
                $s->hr_work_schedule_id . '|' . $s->day_index
            );

        $incidentRows = DB::table('employee_incidents as i')
            ->join('hr_incident_types as t',
                't.id', '=', 'i.hr_incident_type_id')
            ->where('i.company_id', $companyId)
            ->whereIn('i.status', [
                'approved', 'pending', 'draft'
            ])
            ->whereDate('i.start_date', '<=', $to->toDateString())
            ->where(function ($q) use ($from) {
                $q->whereNull('i.end_date')
                  ->orWhereDate(
                      'i.end_date', '>=', $from->toDateString()
                  );
            })
            ->whereIn('t.code', [
                'PERMISO_CON_GOCE',
                'PERMISO_SIN_GOCE',
                'VACACIONES',
                'INCAPACIDAD',
            ])
            ->get([
                'i.employee_id', 'i.start_date',
                'i.end_date', 'i.start_time', 'i.end_time',
                'i.quantity', 'i.quantity_unit',
                'i.status', 't.code'
            ])
            ->groupBy('employee_id');

        $today = now()->toDateString();
        $rows = [];
        $totals = $empty['totals'];

        foreach ($employees as $employee) {
            $counts = [
                'A' => 0, 'R' => 0, 'I' => 0,
                'F?' => 0, 'D' => 0, 'SH' => 0,
                'P' => 0, 'J' => 0, 'NA' => 0,
                'HOY' => 0, 'V' => 0, 'INC' => 0,
                'PSG' => 0, 'PP' => 0, 'RH' => 0,
                'FL' => 0,
            ];

            $daily = [];
            $schedule = $schedules->get($employee->hr_work_schedule_id);
            $effectiveScheduleName = $schedule->name ?? 'Sin horario';

            foreach ($days as $day) {
                $date = $day['date'];
                $attendance = $attendances->get($employee->id . '|' . $date);

                $outsideEmployment =
                    ($employee->hire_date && $date < $employee->hire_date)
                    || ($employee->termination_date
                        && $date > $employee->termination_date);

                $scheduleId = $employee->hr_work_schedule_id;

                if (!$scheduleId) {
                    $contract = $contracts
                        ->get($employee->id, collect())
                        ->first(fn ($c) =>
                            substr((string) $c->start_date, 0, 10) <= $date
                            && (
                                !$c->end_date
                                || substr((string) $c->end_date, 0, 10) >= $date
                            )
                        );

                    $scheduleId = $contract->hr_work_schedule_id ?? null;
                }

                $daySchedule = $schedules->get($scheduleId);
                if ($daySchedule && !$schedule) {
                    $effectiveScheduleName = $daySchedule->name;
                }

                $incident = $incidentRows
                    ->get($employee->id, collect())
                    ->filter(fn ($i) =>
                        substr((string) $i->start_date, 0, 10) <= $date
                        && (
                            !$i->end_date
                            || substr((string) $i->end_date, 0, 10) >= $date
                        )
                    )
                    ->sortBy(fn ($i) => match ($i->status) {
                        'approved' => 0,
                        'pending' => 1,
                        default => 2,
                    })
                    ->first();

                $configuredDay = $scheduleDays->get(
                    $scheduleId . '|' . $day['weekday_number']
                );

                $incidentIsPartial = $incident
                    && (
                        $incident->start_time
                        || $incident->end_time
                        || (
                            $incident->quantity_unit === 'hours'
                            || $incident->quantity_unit === 'minutes'
                        )
                    );

                if ($outsideEmployment) {
                    $code = 'NA';
                } elseif ($attendance) {
                    $code = $attendance->clock_in_at
                        && $attendance->clock_out_at
                        ? ((int) $attendance->late_minutes > 0 ? 'R' : 'A')
                        : 'I';
                } elseif ($incident) {
                    if ($incidentIsPartial) {
                        $code = 'RH';
                    } elseif ($incident->status !== 'approved') {
                        $code = 'PP';
                    } else {
                        $code = match ((string) $incident->code) {
                            'PERMISO_CON_GOCE' => 'P',
                            'PERMISO_SIN_GOCE' => 'PSG',
                            'VACACIONES' => 'V',
                            'INCAPACIDAD' => 'INC',
                            default => 'RH',
                        };
                    }
                } elseif (!$daySchedule || !$configuredDay) {
                    $code = 'SH';
                } elseif (!$configuredDay->is_working_day) {
                    $code = 'D';
                } elseif ($date >= $today) {
                    $code = 'HOY';
                } elseif (in_array(
                    $daySchedule->schedule_type,
                    ['flexible', 'open'],
                    true
                )) {
                    $code = 'FL';
                } else {
                    $code = 'F?';
                }

                if (
                    $attendance
                    && (int) ($attendance->late_minutes ?? 0) > 0
                ) {
                    $totals['late_days']++;
                }

                if (
                    $attendance
                    && (int) ($attendance->early_leave_minutes ?? 0) > 0
                ) {
                    $totals['early_days']++;
                }

                if ($attendance) {
                    $totals['overtime_minutes'] += max(
                        0,
                        (int) ($attendance->overtime_minutes ?? 0)
                    );
                }

                $daily[] = [
                    'date' => $date,
                    'code' => $code,
                    'attendance_id' => $attendance->id ?? null,
                    'has_incident' => (bool) $incident,
                    'incident_status' => $incident->status ?? null,
                ];

                $counts[$code]++;
            }

            $totals['attendance'] += $counts['A'] + $counts['R'];
            $totals['incomplete'] += $counts['I'];
            $totals['possible_absences'] += $counts['F?'];
            $totals['unknown'] += $counts['SH'];

            $rows[] = [
                'id' => $employee->id,
                'name' => $employee->name,
                'number' => $employee->employee_number,
                'schedule' => $effectiveScheduleName,
                'days' => $daily,
                'counts' => $counts,
            ];
        }

        $totals['employees'] = count($rows);

        return [
            'days' => $days,
            'rows' => $rows,
            'totals' => $totals,
            'error' => null,
        ];
    }
}
