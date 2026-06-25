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

    public ?string $employee_id = null;

    public ?string $department_id = null;

    public ?string $status = null;

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

        return $user->can('rrhh.asistencias.ver') || $user->can('company.update');
    }

    public function filters(): array
    {
        return [
            'company_id' => $this->companyId(),
            'from' => $this->from,
            'to' => $this->to,
            'employee_id' => $this->employee_id,
            'department_id' => $this->department_id,
            'status' => $this->status,
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

    public function employeeOptions(): array
    {
        $companyId = $this->companyId();

        if (! $companyId) {
            return [];
        }

        return Employee::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
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

    public function statusOptions(): array
    {
        return EmployeeAttendance::statusOptions();
    }

    public function clearFilters(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
        $this->employee_id = null;
        $this->department_id = null;
        $this->status = null;
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

    public function exportPdf(): StreamedResponse
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action('exportExcel'),

            Action::make('exportPdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action('exportPdf'),
        ];
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
