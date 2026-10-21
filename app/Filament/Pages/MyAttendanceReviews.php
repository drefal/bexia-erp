<?php

namespace App\Filament\Pages;

use App\Support\PayrollAttendanceReviewAuthorization as ReviewAuth;
use App\Support\PayrollAttendanceReviewSentAccess;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class MyAttendanceReviews extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'RRHH';
    protected static ?string $navigationLabel = 'Mis vistos buenos';
    protected static ?string $title = 'Mis vistos buenos';
    protected static ?string $slug = 'rrhh/mis-vistos-buenos';
    protected static ?int $navigationSort = 25;
    protected static string $view = 'filament.pages.my-attendance-reviews';

    public bool $decisionModalOpen = false;
    public int $decisionReviewId = 0;
    public int $decisionReviewVersion = 0;
    public string $decisionType = '';
    public string $decisionComment = '';

    public static function canAccess(): bool
    {
        $userId = (int) (auth()->id() ?? 0);
        $companyId = (int) (Filament::getTenant()?->getKey() ?? 0);

        return $userId > 0
            && $companyId > 0
            && ReviewAuth::allowed(
                $userId,
                $companyId,
                ReviewAuth::VIEW
            );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function openDecision(int $reviewId, string $type): void
    {
        abort_unless(static::canAccess(), 403);

        if (!in_array($type, ['approved', 'returned'], true)) {
            abort(422);
        }

        $companyId = (int) (Filament::getTenant()?->getKey() ?? 0);
        $userId = (int) (auth()->id() ?? 0);

        abort_unless(
            PayrollAttendanceReviewSentAccess::allowed(
                $userId,
                $companyId,
                $reviewId,
                ReviewAuth::APPROVE
            ),
            403
        );

        $review = DB::table('hr_payroll_attendance_reviews')
            ->where('id', $reviewId)
            ->where('company_id', $companyId)
            ->where('reviewer_user_id', $userId)
            ->where('status', 'pending')
            ->first();

        abort_unless($review, 403);

        $this->decisionReviewId = $reviewId;
        $this->decisionReviewVersion = (int) $review->version;
        $this->decisionType = $type;
        $this->decisionComment = '';
        $this->decisionModalOpen = true;
    }

    public function closeDecision(): void
    {
        $this->decisionModalOpen = false;
        $this->decisionReviewId = 0;
        $this->decisionReviewVersion = 0;
        $this->decisionType = '';
        $this->decisionComment = '';
    }

    public function confirmDecision(): void
    {
        abort_unless(static::canAccess(), 403);

        $companyId = (int) (Filament::getTenant()?->getKey() ?? 0);
        $userId = (int) (auth()->id() ?? 0);
        $reviewId = $this->decisionReviewId;
        $type = $this->decisionType;
        $version = $this->decisionReviewVersion;
        $comment = trim($this->decisionComment);

        if (
            !$this->decisionModalOpen
            || $reviewId <= 0
            || $version <= 0
            || !in_array($type, ['approved', 'returned'], true)
        ) {
            $this->closeDecision();
            abort(422);
        }

        if ($type === 'returned' && $comment === '') {
            \Filament\Notifications\Notification::make()
                ->title('Observaciones obligatorias')
                ->body('Indica el motivo de la devolución.')
                ->warning()->send();
            return;
        }

        if (mb_strlen($comment) > 2000) {
            \Filament\Notifications\Notification::make()
                ->title('Observaciones demasiado extensas')
                ->body('Máximo 2000 caracteres.')
                ->warning()->send();
            return;
        }

        // No confiar en el ID, tipo ni version enviados por Livewire.
        // Revalidar el alcance contra el servidor antes de decidir.
        $review = DB::table('hr_payroll_attendance_reviews')
            ->where('id', $reviewId)
            ->where('company_id', $companyId)
            ->where('reviewer_user_id', $userId)
            ->where('status', 'pending')
            ->where('version', $version)
            ->first();

        if (
            !$review
            || !PayrollAttendanceReviewSentAccess::allowed(
                $userId,
                $companyId,
                $reviewId,
                ReviewAuth::APPROVE
            )
        ) {
            $this->closeDecision();

            \Filament\Notifications\Notification::make()
                ->title('Revisión no disponible')
                ->body('La revisión cambió o ya fue respondida.')
                ->warning()->send();
            return;
        }

        try {
            \App\Support\PayrollAttendanceReviewDecision::decide(
                $companyId,
                $reviewId,
                $userId,
                $type,
                $type === 'returned' ? $comment : null
            );

            $this->closeDecision();

            \Filament\Notifications\Notification::make()
                ->title(
                    $type === 'approved'
                        ? 'Visto bueno registrado'
                        : 'Revisión devuelta a RRHH'
                )
                ->success()->send();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'RRHH review decision failed',
                [
                    'company_id' => $companyId,
                    'review_id' => $reviewId,
                    'actor_user_id' => $userId,
                    'error' => $e->getMessage(),
                ]
            );

            $this->closeDecision();

            \Filament\Notifications\Notification::make()
                ->title('No se registró la decisión')
                ->body('Actualiza la página y verifica la revisión.')
                ->danger()->send();
        }
    }

    public function reviewCards(): array
    {
        abort_unless(static::canAccess(), 403);

        $userId = (int) auth()->id();
        $companyId = (int) Filament::getTenant()->getKey();

        // El filtro por responsable y empresa se aplica en BD.
        $rows = DB::table('hr_payroll_attendance_reviews as r')
            ->join(
                'hr_payroll_attendance_review_batches as b',
                'b.id',
                '=',
                'r.batch_id'
            )
            ->join(
                'payroll_runs as pr',
                'pr.id',
                '=',
                'b.payroll_run_id'
            )
            ->where('r.company_id', $companyId)
            ->where('b.company_id', $companyId)
            ->where('pr.company_id', $companyId)
            ->where('r.reviewer_user_id', $userId)
            ->where('b.status', 'sent')
            ->orderByDesc('r.sent_at')
            ->orderByDesc('r.id')
            ->get([
                'r.id',
                'r.status',
                'r.version',
                'r.sent_at',
                'r.reviewed_at',
                'r.reviewer_comment',
                'pr.name as payroll_name',
                'b.period_start',
                'b.period_end',
            ]);

        $cards = [];

        foreach ($rows as $row) {
            $reviewId = (int) $row->id;

            // Autorizar revision por revision, nunca solo por rol.
            if (!PayrollAttendanceReviewSentAccess::allowed(
                $userId,
                $companyId,
                $reviewId,
                ReviewAuth::VIEW
            )) {
                continue;
            }

            $items = DB::table(
                'hr_payroll_attendance_review_items'
            )
                ->where('company_id', $companyId)
                ->where('review_id', $reviewId)
                ->where('reviewer_user_id', $userId)
                ->orderBy('employee_id')
                ->get([
                    'id',
                    'employee_id',
                    'attendance_snapshot',
                    'snapshot_hash',
                ]);

            $employeeIds = $items->pluck('employee_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (count($employeeIds) === 0) {
                continue;
            }

            $names = DB::table('employees')
                ->where('company_id', $companyId)
                ->whereIn('id', $employeeIds)
                ->pluck('name', 'id');

            $employees = [];

            foreach ($items as $item) {
                $employeeId = (int) $item->employee_id;

                // El acceso a la revision completa ya fue validado
                // con SentAccess. Revalidar tambien el item concreto.
                if (!\App\Support\PayrollAttendanceReviewScope::allowed(
                    $userId,
                    $companyId,
                    (int) DB::table(
                        'hr_payroll_attendance_reviews'
                    )->where('id', $reviewId)
                     ->where('company_id', $companyId)
                     ->value('batch_id'),
                    $employeeId,
                    ReviewAuth::VIEW
                )) {
                    continue;
                }

                $snapshot = json_decode(
                    (string) $item->attendance_snapshot,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                if (
                    !is_array($snapshot)
                    || (int) ($snapshot['company_id'] ?? 0)
                        !== $companyId
                    || (int) ($snapshot['employee_id'] ?? 0)
                        !== $employeeId
                    || !hash_equals(
                        (string) $item->snapshot_hash,
                        \App\Support\PayrollAttendanceSnapshotService
                            ::hash($snapshot)
                    )
                ) {
                    throw new \RuntimeException(
                        'Fotografia de asistencia invalida.'
                    );
                }

                $attendanceRows = [];

                foreach (($snapshot['attendances'] ?? []) as $a) {
                    $attendanceRows[] = [
                        'date' => (string) ($a['attendance_date'] ?? ''),
                        'status' => (string) ($a['status'] ?? ''),
                        'expected_in' => $a['expected_start_at'] ?? null,
                        'expected_out' => $a['expected_end_at'] ?? null,
                        'clock_in' => $a['clock_in_at'] ?? null,
                        'clock_out' => $a['clock_out_at'] ?? null,
                        'late' => (int) ($a['late_minutes'] ?? 0),
                        'early' => (int) ($a['early_leave_minutes'] ?? 0),
                        'worked' => (int) ($a['worked_minutes'] ?? 0),
                        'overtime' => (int) ($a['overtime_minutes'] ?? 0),
                    ];
                }

                usort(
                    $attendanceRows,
                    static fn (array $a, array $b): int =>
                        strcmp($a['date'], $b['date'])
                );

                $typeNames = [];

                foreach (($snapshot['incident_types'] ?? []) as $type) {
                    $typeNames[(int) ($type['id'] ?? 0)] =
                        (string) ($type['name'] ?? 'Incidencia');
                }

                $incidentRows = [];

                foreach (($snapshot['incidents'] ?? []) as $incident) {
                    $typeId = (int) (
                        $incident['hr_incident_type_id'] ?? 0
                    );

                    $incidentRows[] = [
                        'name' => $typeNames[$typeId]
                            ?? ('Tipo #' . $typeId),
                        'status' => (string) (
                            $incident['status'] ?? ''
                        ),
                        'from' => (string) (
                            $incident['start_date'] ?? ''
                        ),
                        'to' => (string) (
                            $incident['end_date']
                            ?? $incident['start_date']
                            ?? ''
                        ),
                    ];
                }

                $employees[] = [
                    'id' => $employeeId,
                    'name' => (string) (
                        $names[$employeeId]
                        ?? 'Empleado #' . $employeeId
                    ),
                    'attendance_rows' => $attendanceRows,
                    'incident_rows' => $incidentRows,
                    'snapshot_valid' => true,
                ];
            }

            $cards[] = [
                'review_id' => $reviewId,
                'status' => (string) $row->status,
                'version' => (int) $row->version,
                'payroll_name' => (string) $row->payroll_name,
                'period_start' => (string) $row->period_start,
                'period_end' => (string) $row->period_end,
                'sent_at' => $row->sent_at,
                'reviewed_at' => $row->reviewed_at,
                'comment' => $row->reviewer_comment,
                'employees' => $employees,
                'can_decide' => (
                    $row->status === 'pending'
                    && PayrollAttendanceReviewSentAccess::allowed(
                        $userId,
                        $companyId,
                        $reviewId,
                        ReviewAuth::APPROVE
                    )
                ),
            ];
        }

        return $cards;
    }
}
