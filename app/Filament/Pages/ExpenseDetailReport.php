<?php

namespace App\Filament\Pages;

use App\Support\Expenses\ExpenseAccess;
use App\Support\Expenses\ExpenseAnalyticsReportService;
use App\Support\Expenses\ExpenseDetailReportService;
use App\Support\Expenses\ExpenseReportBranding;
use App\Support\Security\BexiaTenantPermission;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseDetailReport extends Page
{
    protected static ?string $navigationIcon =
        'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationGroup = 'Gastos';

    protected static ?string $navigationLabel =
        'Reporte de comprobaciones';

    protected static ?string $title =
        'Comprobaciones de gastos';

    protected static ?int $navigationSort = 42;

    protected static string $view =
        'filament.pages.expense-detail-report';

    public string $viewMode = 'detail';

    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public ?string $reportType = null;
    public ?string $reportStatus = null;
    public ?int $employeeId = null;
    public ?int $categoryId = null;
    public ?int $projectId = null;
    public ?string $supplier = null;

    public array $selectedLineIds = [];
    public array $selectableExpenseOptions = [];

    public array $employeeOptions = [];
    public array $categoryOptions = [];
    public array $projectOptions = [];
    public array $typeOptions = [];
    public array $statusOptions = [];

    public Collection $rows;
    public Collection $employeeRows;
    public Collection $categoryRows;

    public array $summary = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->dateFrom =
            now()->startOfMonth()->toDateString();

        $this->dateTo =
            now()->toDateString();

        $companyId = $this->companyId();

        $this->employeeOptions =
            ExpenseAccess::employeeOptions(
                $companyId
            );

        if (! ExpenseAccess::canViewAll()) {
            $this->employeeId =
                ExpenseAccess::currentEmployeeId(
                    $companyId
                ) ?? 0;
        }

        $this->categoryOptions =
            ExpenseDetailReportService::categoryOptions(
                $companyId
            );

        $this->projectOptions =
            ExpenseDetailReportService::projectOptions(
                $companyId
            );

        $this->typeOptions =
            ExpenseDetailReportService::typeOptions();

        $this->statusOptions =
            ExpenseDetailReportService::statusOptions();

        $this->refreshSelectableExpenses();
        $this->loadReport();
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

        return BexiaTenantPermission::can('expenses.admin')
            || BexiaTenantPermission::can(
                'expenses.reports.view'
            )
            || BexiaTenantPermission::can('expenses.view')
            || BexiaTenantPermission::can('expenses.view_all');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function setViewMode(string $mode): void
    {
        if (! in_array(
            $mode,
            ['detail', 'employee', 'category'],
            true
        )) {
            return;
        }

        $this->viewMode = $mode;
        $this->loadReport();
    }

    public function applyFilters(): void
    {
        $this->selectedLineIds = [];

        $this->refreshSelectableExpenses();
        $this->loadReport();
    }

    public function clearFilters(): void
    {
        $this->dateFrom =
            now()->startOfMonth()->toDateString();

        $this->dateTo =
            now()->toDateString();

        $this->reportType = null;
        $this->reportStatus = null;
        $this->employeeId =
            ExpenseAccess::canViewAll()
                ? null
                : (
                    ExpenseAccess::currentEmployeeId(
                        $this->companyId()
                    ) ?? 0
                );
        $this->categoryId = null;
        $this->projectId = null;
        $this->supplier = null;
        $this->selectedLineIds = [];

        $this->refreshSelectableExpenses();
        $this->loadReport();
    }

    public function refreshSelectableExpenses(): void
    {
        $rows =
            ExpenseDetailReportService::selectableRows(
                $this->companyId(),
                $this->dateFrom,
                $this->dateTo,
                $this->reportType,
                $this->reportStatus,
                $this->effectiveEmployeeId(),
                $this->categoryId,
                $this->projectId,
                $this->supplier
            );

        $this->selectableExpenseOptions =
            $rows
                ->mapWithKeys(
                    fn ($row) => [
                        (int) $row->id =>
                            ExpenseDetailReportService::optionLabel(
                                $row
                            ),
                    ]
                )
                ->all();
    }

    public function applySelection(): void
    {
        $this->selectedLineIds =
            collect($this->selectedLineIds)
                ->map(fn ($id) => (int) $id)
                ->filter(
                    fn ($id) =>
                        $id > 0
                        && array_key_exists(
                            $id,
                            $this->selectableExpenseOptions
                        )
                )
                ->unique()
                ->values()
                ->all();

        $this->loadReport();
    }

    public function selectAllVisible(): void
    {
        $this->selectedLineIds =
            array_map(
                'intval',
                array_keys(
                    $this->selectableExpenseOptions
                )
            );

        $this->loadReport();
    }

    public function clearSelection(): void
    {
        $this->selectedLineIds = [];
        $this->loadReport();
    }

    public function loadReport(): void
    {
        $this->rows =
            ExpenseDetailReportService::rows(
                $this->companyId(),
                $this->dateFrom,
                $this->dateTo,
                $this->reportType,
                $this->reportStatus,
                $this->effectiveEmployeeId(),
                $this->categoryId,
                $this->projectId,
                $this->supplier,
                $this->selectedLineIds
            );

        $this->employeeRows =
            ExpenseAnalyticsReportService::byEmployee(
                $this->rows
            );

        $this->categoryRows =
            ExpenseAnalyticsReportService::byCategory(
                $this->rows
            );

        $this->summary = match ($this->viewMode) {
            'employee' =>
                ExpenseAnalyticsReportService::employeeSummary(
                    $this->employeeRows
                ),

            'category' =>
                ExpenseAnalyticsReportService::categorySummary(
                    $this->categoryRows
                ),

            default =>
                ExpenseDetailReportService::summary(
                    $this->rows
                ),
        };
    }

    public function exportExcel(): BinaryFileResponse
    {
        abort_unless(static::canAccess(), 403);

        $tmp = tempnam(
            sys_get_temp_dir(),
            'bexia_expense_report_'
        );

        if ($tmp === false) {
            throw new \RuntimeException(
                'No se pudo crear el archivo temporal.'
            );
        }

        $path = $tmp . '.xlsx';

        if ($this->viewMode === 'employee') {
            ExpenseAnalyticsReportService::writeEmployeeExcel(
                $path,
                $this->employeeRows
            );

            $filename =
                'gastos_por_empleado_';
        } elseif ($this->viewMode === 'category') {
            ExpenseAnalyticsReportService::writeCategoryExcel(
                $path,
                $this->categoryRows
            );

            $filename =
                'gastos_por_categoria_';
        } else {
            ExpenseDetailReportService::writeExcel(
                $path,
                $this->companyId(),
                $this->dateFrom,
                $this->dateTo,
                $this->reportType,
                $this->reportStatus,
                $this->effectiveEmployeeId(),
                $this->categoryId,
                $this->projectId,
                $this->supplier,
                $this->selectedLineIds
            );

            $filename =
                'comprobaciones_gastos_';
        }

        return response()
            ->download(
                $path,
                $filename
                    . now()->format('Ymd_His')
                    . '.xlsx'
            )
            ->deleteFileAfterSend(true);
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        $branding =
            ExpenseReportBranding::forCompany(
                $this->companyId()
            );

        if ($this->viewMode === 'employee') {
            $view =
                'reports.expenses.expenses-by-employee-report-pdf';

            $rows = $this->employeeRows;
            $summary =
                ExpenseAnalyticsReportService::employeeSummary(
                    $rows
                );

            $filename =
                'gastos_por_empleado_';
        } elseif ($this->viewMode === 'category') {
            $view =
                'reports.expenses.expenses-by-category-report-pdf';

            $rows = $this->categoryRows;
            $summary =
                ExpenseAnalyticsReportService::categorySummary(
                    $rows
                );

            $filename =
                'gastos_por_categoria_';
        } else {
            $view =
                'reports.expenses.expense-detail-report-pdf';

            $rows = $this->rows;
            $summary =
                ExpenseDetailReportService::summary(
                    $rows
                );

            $filename =
                'comprobaciones_gastos_';
        }

        $pdf = app('dompdf.wrapper')
            ->loadView(
                $view,
                [
                    'rows' => $rows,
                    'summary' => $summary,
                    'companyName' =>
                        $branding['companyName'],
                    'logoDataUri' =>
                        $branding['logoDataUri'],
                    'generatedAt' => now(),
                    'dateFrom' =>
                        $this->dateFrom,
                    'dateTo' =>
                        $this->dateTo,
                ]
            )
            ->setPaper(
                'letter',
                'landscape'
            );

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename
                . now()->format('Ymd_His')
                . '.pdf',
            [
                'Content-Type' =>
                    'application/pdf',
            ]
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Actualizar')
                ->icon('heroicon-o-arrow-path')
                ->action('loadReport'),

            Action::make('exportExcel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-table-cells')
                ->action('exportExcel'),

            Action::make('exportPdf')
                ->label('Exportar PDF')
                ->icon(
                    'heroicon-o-document-arrow-down'
                )
                ->action('exportPdf'),
        ];
    }

    protected function effectiveEmployeeId(): ?int
    {
        if (ExpenseAccess::canViewAll()) {
            return $this->employeeId;
        }

        return ExpenseAccess::currentEmployeeId(
            $this->companyId()
        ) ?? 0;
    }

    protected function companyId(): int
    {
        $id = (int) (
            Filament::getTenant()?->getKey()
            ?? 0
        );

        abort_unless($id > 0, 404);

        return $id;
    }
}
