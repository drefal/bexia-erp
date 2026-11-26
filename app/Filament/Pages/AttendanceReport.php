<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Support\EmployeeAttendanceReportService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'RRHH';

    protected static ?string $navigationLabel = 'Reporte de asistencia';

    protected static ?string $title = 'Reporte de asistencia';

    protected static ?string $slug = 'rrhh/reporte-asistencia';

    protected static ?int $navigationSort = 23;

    protected static string $view = 'filament.pages.attendance-report';

    public string $from = '';

    public string $to = '';

    public array $employee_ids = [];

    public string $employee_search = '';

    public bool $only_key_personnel = false;

    public ?string $department_id = null;

    public ?string $status = null;

    public string $group_by = 'day';

    #[\Livewire\Attributes\Locked]
    public ?string $pdfReportId = null;

    #[\Livewire\Attributes\Locked]
    public string $pdfReportStatus = 'idle';

    #[\Livewire\Attributes\Locked]
    public int $pdfReportProgress = 0;

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((bool) ($user->is_system_admin ?? false)) {
            return true;
        }

        if (($user->email ?? null) === 'admin@bexiaerp.com') {
            return true;
        }

        return $user->can('rrhh.asistencias.ver');
    }

    public function filters(): array
    {
        return [
            'company_id' => $this->companyId(),
            'from' => $this->from,
            'to' => $this->to,
            'employee_ids' => $this->employee_ids,
            'only_key_personnel' => $this->only_key_personnel,
            'department_id' => $this->department_id,
            'status' => $this->status,
            'group_by' => $this->group_by,
        ];
    }

    public function rows()
    {
        return EmployeeAttendanceReportService::rows($this->filters());
    }

    public function summary(): array
    {
        return EmployeeAttendanceReportService::summary($this->rows());
    }

    public function employeeSearchOptions(): array
    {
        $companyId = $this->companyId();
        $search = trim($this->employee_search);

        if (! $companyId || mb_strlen($search) < 2) {
            return [];
        }

        $selectedIds = collect($this->employee_ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        return DB::table('employees as e')
            ->leftJoin(
                'hr_departments as d',
                'd.id',
                '=',
                'e.hr_department_id'
            )
            ->where('e.company_id', $companyId)
            ->when(
                $selectedIds !== [],
                fn ($query) => $query->whereNotIn(
                    'e.id',
                    $selectedIds
                )
            )
            ->where(function ($query) use ($search): void {
                $query
                    ->where('e.name', 'ilike', '%' . $search . '%')
                    ->orWhere(
                        'e.employee_number',
                        'ilike',
                        '%' . $search . '%'
                    );
            })
            ->orderBy('e.name')
            ->limit(12)
            ->get([
                'e.id',
                'e.name',
                'e.employee_number',
                'd.name as department_name',
            ])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?: 'Empleado'),
                'employee_number' => (string) (
                    $row->employee_number ?? ''
                ),
                'department_name' => (string) (
                    $row->department_name ?? ''
                ),
            ])
            ->all();
    }

    public function selectedEmployeeOptions(): array
    {
        $companyId = $this->companyId();

        $ids = collect($this->employee_ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if (! $companyId || $ids->isEmpty()) {
            return [];
        }

        $rows = DB::table('employees as e')
            ->leftJoin(
                'hr_departments as d',
                'd.id',
                '=',
                'e.hr_department_id'
            )
            ->where('e.company_id', $companyId)
            ->whereIn('e.id', $ids->all())
            ->get([
                'e.id',
                'e.name',
                'e.employee_number',
                'd.name as department_name',
            ])
            ->keyBy('id');

        return $ids
            ->map(function (int $id) use ($rows): ?array {
                $row = $rows->get($id);

                if (! $row) {
                    return null;
                }

                return [
                    'id' => (int) $row->id,
                    'name' => (string) (
                        $row->name ?: 'Empleado'
                    ),
                    'employee_number' => (string) (
                        $row->employee_number ?? ''
                    ),
                    'department_name' => (string) (
                        $row->department_name ?? ''
                    ),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function addEmployee(int $employeeId): void
    {
        $companyId = $this->companyId();

        if (! $companyId || $employeeId <= 0) {
            return;
        }

        $exists = Employee::query()
            ->where('company_id', $companyId)
            ->whereKey($employeeId)
            ->exists();

        if (! $exists) {
            return;
        }

        $this->employee_ids = collect($this->employee_ids)
            ->push($employeeId)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->employee_search = '';
    }

    public function removeEmployee(int $employeeId): void
    {
        $this->employee_ids = collect($this->employee_ids)
            ->map(fn ($id): int => (int) $id)
            ->reject(
                fn (int $id): bool => $id === $employeeId
            )
            ->unique()
            ->values()
            ->all();
    }

    public function departmentOptions(): array
    {
        $companyId = $this->companyId();

        if (! $companyId || ! DB::getSchemaBuilder()->hasTable('hr_departments')) {
            return [];
        }

        return DB::table('hr_departments')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * V5.83.6J2AR41
     *
     * Indicador operativo de Personal Clave para el dia actual.
     *
     * El denominador considera empleados activos marcados como Personal Clave.
     * El numerador considera a quienes ya registraron entrada hoy.
     *
     * Respeta empresa y departamento, pero deliberadamente no usa:
     * - rango Desde/Hasta
     * - empleados seleccionados
     * - estado
     * - switch Solo Personal Clave
     */
    public function keyPersonnelAttendanceSnapshot(): array
    {
        $companyId = $this->companyId();
        $departmentId = filled($this->department_id)
            ? (int) $this->department_id
            : null;

        $now = now();
        $today = $now->toDateString();

        if ($companyId <= 0) {
            return [
                'attended' => 0,
                'total' => 0,
                'date' => $today,
                'as_of' => $now->format('h:i A'),
            ];
        }

        $employees = DB::table('employees as e')
            ->where('e.company_id', $companyId)
            ->where('e.is_key_personnel', true)
            ->where('e.active', true)
            ->when(
                $departmentId,
                fn ($query, $value) => $query->where(
                    'e.hr_department_id',
                    $value
                )
            );

        $total = (clone $employees)
            ->distinct()
            ->count('e.id');

        $attended = DB::table('employee_attendances as a')
            ->join(
                'employees as e',
                'e.id',
                '=',
                'a.employee_id'
            )
            ->where('a.company_id', $companyId)
            ->where('e.company_id', $companyId)
            ->where('e.is_key_personnel', true)
            ->where('e.active', true)
            ->whereDate('a.attendance_date', $today)
            ->whereNotNull('a.clock_in_at')
            ->when(
                $departmentId,
                fn ($query, $value) => $query->where(
                    'e.hr_department_id',
                    $value
                )
            )
            ->distinct()
            ->count('a.employee_id');

        return [
            'attended' => (int) $attended,
            'total' => (int) $total,
            'date' => $today,
            'as_of' => $now->format('h:i A'),
        ];
    }

    public function statusOptions(): array
    {
        return EmployeeAttendance::statusOptions();
    }

    public function setGrouping(string $grouping): void
    {
        if (in_array($grouping, ['day', 'employee'], true)) {
            $this->group_by = $grouping;
        }
    }

    public function clearFilters(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
        $this->employee_ids = [];
        $this->employee_search = '';
        $this->only_key_personnel = false;
        $this->department_id = null;
        $this->status = null;
        $this->group_by = 'day';
    }

    public function exportExcel(): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bexia_attendance_');

        if ($tmp === false) {
            throw new \RuntimeException('No se pudo crear archivo temporal para Excel.');
        }

        $path = $tmp . '.xlsx';
        @rename($tmp, $path);

        EmployeeAttendanceReportService::writeExcel($path, $this->filters());

        return response()
            ->download($path, $this->filename('xlsx'), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function exportPdf(): ?StreamedResponse
    {
        if (!config('reports.enabled', false)) {
            return $this->exportPdfLegacy();
        }

        $userId = (int) (auth()->id() ?? 0);
        $companyId = $this->companyId();

        if ($this->pdfReportId !== null) {

            $this->refreshPdfStatus();

            if ($this->pdfReportId === null) {
                return null;
            }

            if ($this->pdfReportStatus === 'completed') {

                $this->downloadPreparedPdf();

                return null;
            }

            return null;
        }

        try {

            $report = app(
                \App\Support\Reports\AttendanceReportRequestService::class
            )->request(
                $userId,
                $companyId,
                $this->filters()
            );

            $this->pdfReportId =
                (string) $report->getKey();

            $this->pdfReportStatus = 'pending';

            $this->pdfReportProgress = 0;

        } catch (
            \Illuminate\Validation\ValidationException $exception
        ) {

            \Filament\Notifications\Notification::make()
                ->title('No se pudo solicitar el reporte')
                ->body($exception->validator->errors()->first())
                ->warning()
                ->send();

        } catch (
            \Illuminate\Auth\Access\AuthorizationException $exception
        ) {

            abort(403);

        } catch (\Throwable $exception) {

            \Illuminate\Support\Facades\Log::warning(
                'Attendance PDF request failed',
                ['exception_type' => get_class($exception)]
            );

            \Filament\Notifications\Notification::make()
                ->title('Servicio de reportes no disponible')
                ->body('Intenta nuevamente.')
                ->danger()
                ->send();
        }

        return null;
    }

    public function companyIdForReports(): int
    {
        return $this->companyId();
    }

    protected function exportPdfLegacy(): StreamedResponse
    {
        if (! app()->bound('dompdf.wrapper')) {
            throw new \RuntimeException('No hay motor PDF instalado (barryvdh/laravel-dompdf).');
        }

        $data = EmployeeAttendanceReportService::data($this->filters());

        $pdf = app('dompdf.wrapper')
            ->loadView('reports.hr.attendance-report-pdf', $data)
            ->setPaper('letter', 'landscape');

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf->output();
        }, $this->filename('pdf'), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function statusLabel(?string $status): string
    {
        return EmployeeAttendanceReportService::statusLabel($status);
    }

    public function timeOnly(mixed $value): string
    {
        return EmployeeAttendanceReportService::timeOnly($value);
    }

    public function dateOnly(mixed $value): string
    {
        return EmployeeAttendanceReportService::dateOnly($value);
    }

    public function minutesToHours(int|float|null $minutes): string
    {
        return EmployeeAttendanceReportService::minutesToHours($minutes);
    }

    public function employeeEditUrl(int $employeeId): string
    {
        return \App\Filament\Resources\EmployeeResource::getUrl(
            'edit',
            ['record' => $employeeId],
            tenant: \Filament\Facades\Filament::getTenant(),
        );
    }
    public function attendancePhotoUrl(
        int $attendanceId,
        string $direction,
        ?int $breakId = null
    ): string
    {
        abort_unless(
            in_array($direction, ['in', 'meal_out', 'meal_in', 'out'], true),
            404
        );

        $parameters = [
            'tenant' => $this->companyId(),
            'attendance' => $attendanceId,
            'direction' => $direction,
        ];

        if ($breakId !== null) {
            abort_unless(
                $breakId > 0
                && in_array($direction, ['meal_out', 'meal_in'], true),
                404
            );

            $parameters['break_id'] = $breakId;
        }

        return route('rrhh.attendance.photo', $parameters);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action('exportExcel'),

            $this->pdfHeaderAction(),
        ];
    }


    protected function pdfHeaderAction(): Action
    {
        $action = Action::make('exportPdf')
            ->label('Exportar PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->action('exportPdf');

        if (!config('reports.enabled', false)) {
            return $action;
        }

        return $action
            ->label(fn (): string => match ($this->pdfReportStatus) {
                'pending' => 'Preparando PDF...',
                'processing' => 'Generando PDF... '
                    .$this->pdfReportProgress.'%',
                'completed' => 'Descargar PDF',
                'connection_error' => 'Consultar PDF',
                default => 'Exportar PDF',
            })
            ->extraAttributes(fn (): array =>
                $this->pdfReportId !== null
                && in_array(
                    $this->pdfReportStatus,
                    ['pending', 'processing', 'connection_error'],
                    true
                )
                    ? [
                        'wire:poll.3s' => 'refreshPdfStatus',
                        'aria-busy' => 'true',
                    ]
                    : []
            );
    }

    private function resetPdfStatus(): void
    {
        $this->pdfReportId = null;
        $this->pdfReportStatus = 'idle';
        $this->pdfReportProgress = 0;
    }

    private function pdfStatusWarning(string $message): void
    {
        \Filament\Notifications\Notification::make()
            ->title($message)
            ->warning()
            ->send();
    }

    private function findOwnedPdfReport():
        ?\App\Models\ReportExport
    {
        if (
            !config('reports.enabled', false)
            || $this->pdfReportId === null
        ) {
            return null;
        }

        $userId = (int) (auth()->id() ?? 0);
        $companyId = $this->companyId();

        if (!app(
            \App\Support\Reports\AttendanceReportAuthorization::class
        )->allows($userId, $companyId)) {
            return null;
        }

        return \App\Models\ReportExport::query()
            ->ownedBy($companyId, $userId)
            ->where(
                'report_type',
                \App\Models\ReportExport::ATTENDANCE_PDF
            )
            ->whereKey($this->pdfReportId)
            ->first();
    }

    public function refreshPdfStatus(): void
    {
        if ($this->pdfReportId === null) {
            return;
        }

        try {

            $report = $this->findOwnedPdfReport();

            if (!$report) {

                $this->resetPdfStatus();

                $this->pdfStatusWarning(
                    'El reporte ya no esta disponible.'
                );

                return;
            }

            if ($report->status === 'failed') {

                $message = match ($report->error_code) {
                    'report_limit' =>
                        'El reporte supera 6000 registros. Selecciona un periodo menor.',
                    'authorization_revoked' =>
                        'Ya no tienes permiso para generar este reporte.',
                    'generation_timeout', 'worker_interrupted' =>
                        'La generacion fue interrumpida. Intenta nuevamente.',
                    default =>
                        'No se pudo generar el PDF. Intenta nuevamente.',
                };

                $this->resetPdfStatus();

                $this->pdfStatusWarning($message);

                return;
            }

            if (
                $report->status === 'expired'
                || (
                    $report->status === 'completed'
                    && !$report->isDownloadable()
                )
            ) {

                $this->resetPdfStatus();

                $this->pdfStatusWarning(
                    'El documento ya no esta disponible.'
                );

                return;
            }

            if (!in_array(
                $report->status,
                ['pending', 'processing', 'completed'],
                true
            )) {

                $this->resetPdfStatus();

                return;
            }

            $this->pdfReportStatus =
                (string) $report->status;

            $this->pdfReportProgress = max(
                0,
                min(100, (int) $report->progress)
            );

        } catch (\Throwable $exception) {

            $this->pdfReportStatus = 'connection_error';

            \Illuminate\Support\Facades\Log::warning(
                'Attendance PDF status unavailable',
                ['exception_type' => get_class($exception)]
            );
        }
    }

    private function downloadPreparedPdf(): void
    {
        try {

            $report = $this->findOwnedPdfReport();

            if (!$report || !$report->isDownloadable()) {

                $this->resetPdfStatus();

                $this->pdfStatusWarning(
                    'El documento ya no esta disponible.'
                );

                return;
            }

            $url = route(
                'rrhh.attendance.exports.download',
                [
                    'tenant' => $this->companyId(),
                    'report' => $report->getKey(),
                ],
                false
            );

            $encodedUrl = json_encode(
                $url,
                JSON_THROW_ON_ERROR
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
            );

            // Request the normal authenticated HTTP
            // download instead of transmitting the PDF
            // as base64 in a Livewire response.

            $this->js(
                'const link = document.createElement("a");'
                .'link.href = '.$encodedUrl.';'
                .'link.download = "";'
                .'document.body.appendChild(link);'
                .'link.click();'
                .'link.remove();'
            );

            $this->resetPdfStatus();

        } catch (\Throwable $exception) {

            $this->pdfStatusWarning(
                'No se pudo iniciar la descarga. Intenta nuevamente.'
            );
        }
    }

    protected function companyId(): int
    {
        return (int) (Filament::getTenant()?->getKey() ?? 0);
    }

    protected function filename(string $extension): string
    {
        $from = str_replace('-', '', $this->from ?: now()->startOfMonth()->toDateString());
        $to = str_replace('-', '', $this->to ?: now()->toDateString());

        return "reporte_asistencia_{$from}_{$to}.{$extension}";
    }
}
