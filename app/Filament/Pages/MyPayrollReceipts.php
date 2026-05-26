<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Models\PayrollRunLine;
use App\Support\PayrollRunExportService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MyPayrollReceipts extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Mi portal';

    protected static ?string $navigationLabel = 'Mis recibos';

    protected static ?string $title = 'Mis recibos';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.my-payroll-receipts';

    public ?Employee $employee = null;

    public static function shouldRegisterNavigation(): bool
    {
        return static::employeeForCurrentUserQuery()->exists();
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $this->employee = static::employeeForCurrentUserQuery()
            ->with(['hrDepartment', 'hrJobPosition'])
            ->first();
    }

    public function receipts(): Collection
    {
        if (! $this->employee) {
            return collect();
        }

        return PayrollRunLine::query()
            ->with([
                'payrollRun',
                'employee.hrDepartment',
                'employee.hrJobPosition',
                'contract',
            ])
            ->where('payroll_run_lines.employee_id', $this->employee->id)
            ->where('payroll_run_lines.company_id', $this->employee->company_id)
            ->whereHas('payrollRun', function ($query): void {
                $query->whereIn('status', ['calculated', 'approved', 'closed']);
            })
            ->join('payroll_runs as r', 'r.id', '=', 'payroll_run_lines.payroll_run_id')
            ->orderByDesc('r.period_end')
            ->orderByDesc('payroll_run_lines.id')
            ->select('payroll_run_lines.*')
            ->get();
    }

    public function summary(): array
    {
        $receipts = $this->receipts();

        return [
            'count' => $receipts->count(),
            'net_total' => (float) $receipts->sum(fn (PayrollRunLine $line): float => (float) $line->net_amount),
            'gross_total' => (float) $receipts->sum(fn (PayrollRunLine $line): float => (float) $line->gross_amount),
            'deductions_total' => (float) $receipts->sum(fn (PayrollRunLine $line): float => (float) $line->deductions_amount),
            'latest' => $receipts->first(),
        ];
    }

    public function downloadReceipt(int $lineId): StreamedResponse
    {
        $employee = static::employeeForCurrentUserQuery()->first();

        if (! $employee) {
            abort(403, 'Tu usuario no está ligado a un empleado activo.');
        }

        $line = PayrollRunLine::query()
            ->with(['payrollRun', 'employee', 'contract'])
            ->where('payroll_run_lines.id', $lineId)
            ->where('payroll_run_lines.employee_id', $employee->id)
            ->where('payroll_run_lines.company_id', $employee->company_id)
            ->whereHas('payrollRun', function ($query): void {
                $query->whereIn('status', ['calculated', 'approved', 'closed']);
            })
            ->firstOrFail();

        return PayrollRunExportService::exportReceiptPdf($line);
    }

    public function money(mixed $value): string
    {
        return PayrollRunExportService::money($value);
    }

    public function dateOnly(mixed $value): string
    {
        return PayrollRunExportService::dateOnly($value);
    }

    public function statusLabel(?string $status): string
    {
        return \App\Models\PayrollRun::statusOptions()[$status] ?? ($status ?: '-');
    }

    public function periodTypeLabel(?string $type): string
    {
        return \App\Models\PayrollRun::periodTypeOptions()[$type] ?? ($type ?: '-');
    }

    protected static function employeeForCurrentUserQuery()
    {
        return Employee::query()
            ->where('user_id', auth()->id())
            ->where('active', true);
    }
}
