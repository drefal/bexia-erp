<?php

namespace App\Filament\Pages;

use App\Support\Expenses\ExpenseAccess;
use App\Support\Expenses\ExpenseReportBranding;
use App\Support\Expenses\PettyCashMovementsReportService;
use App\Support\Security\BexiaTenantPermission;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PettyCashMovementsReport extends Page
{
    protected static ?string $navigationIcon =
        'heroicon-o-list-bullet';

    protected static ?string $navigationGroup = 'Gastos';

    protected static ?string $navigationLabel =
        'Movimientos de caja chica';

    protected static ?string $title =
        'Movimientos de caja chica';

    protected static ?int $navigationSort = 41;

    protected static string $view =
        'filament.pages.petty-cash-movements-report';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?int $fundId = null;

    public ?string $movementType = null;

    public ?int $fundingSourceId = null;

    public Collection $rows;

    public array $summary = [];

    public array $fundOptions = [];

    public array $typeOptions = [];

    public array $fundingSourceOptions = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->dateFrom = now()
            ->startOfMonth()
            ->toDateString();

        $this->dateTo = now()->toDateString();

        $companyId = $this->companyId();

        $this->fundOptions =
            PettyCashMovementsReportService::fundOptions(
                $companyId,
                $this->effectiveEmployeeId()
            );

        $this->typeOptions =
            PettyCashMovementsReportService::typeOptions();

        $this->fundingSourceOptions =
            PettyCashMovementsReportService::fundingSourceOptions(
                $companyId
            );

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

        return BexiaTenantPermission::can(
            'expenses.admin'
        )
            || BexiaTenantPermission::can(
                'expenses.reports.view'
            )
            || BexiaTenantPermission::can(
                'petty_cash.view'
            )
            || BexiaTenantPermission::can(
                'petty_cash.manage'
            );
    }

    /*
     * IMPORTANTE:
     * Se deja FALSE durante el primer discovery.
     * El script la activa solo si la ruta existe.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function applyFilters(): void
    {
        $this->loadReport();
    }

    public function clearFilters(): void
    {
        $this->dateFrom = now()
            ->startOfMonth()
            ->toDateString();

        $this->dateTo = now()->toDateString();

        $this->fundId = null;
        $this->movementType = null;
        $this->fundingSourceId = null;

        $this->loadReport();
    }

    public function loadReport(): void
    {
        $companyId = $this->companyId();

        $this->rows =
            PettyCashMovementsReportService::rows(
                $companyId,
                $this->dateFrom,
                $this->dateTo,
                $this->fundId,
                $this->movementType,
                $this->fundingSourceId,
                $this->effectiveEmployeeId()
            );

        $this->summary =
            PettyCashMovementsReportService::summary(
                $this->rows
            );

        $this->summary['funds_included'] = $this->rows
            ->pluck('petty_cash_fund_id')
            ->unique()
            ->count();

        $this->summary['net_change'] =
            (float) ($this->summary['entries'] ?? 0)
            - (float) ($this->summary['exits'] ?? 0);
    }

    public function exportExcel(): BinaryFileResponse
    {
        abort_unless(static::canAccess(), 403);

        $tmp = tempnam(
            sys_get_temp_dir(),
            'bexia_petty_cash_movements_'
        );

        if ($tmp === false) {
            throw new \RuntimeException(
                'No se pudo crear archivo temporal para Excel.'
            );
        }

        $path = $tmp . '.xlsx';

        PettyCashMovementsReportService::writeExcel(
            $path,
            $this->companyId(),
            $this->dateFrom,
            $this->dateTo,
            $this->fundId,
            $this->movementType,
            $this->fundingSourceId,
            $this->effectiveEmployeeId()
        );

        return response()
            ->download(
                $path,
                'movimientos_caja_chica_'
                    . now()->format('Ymd_His')
                    . '.xlsx',
                [
                    'Content-Type' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            )
            ->deleteFileAfterSend(true);
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        if (! app()->bound('dompdf.wrapper')) {
            throw new \RuntimeException(
                'No hay motor PDF instalado.'
            );
        }

        $companyId = $this->companyId();

        $rows =
            PettyCashMovementsReportService::rows(
                $companyId,
                $this->dateFrom,
                $this->dateTo,
                $this->fundId,
                $this->movementType,
                $this->fundingSourceId,
                $this->effectiveEmployeeId()
            );

        $summary =
            PettyCashMovementsReportService::summary(
                $rows
            );

        $branding =
            ExpenseReportBranding::forCompany(
                $companyId
            );

        $pdf = app('dompdf.wrapper')
            ->loadView(
                'reports.expenses.petty-cash-movements-report-pdf',
                [
                    'rows' => $rows,
                    'summary' => $summary,
                    'generatedAt' => now(),
                    'companyName' =>
                        $branding['companyName'],
                    'logoDataUri' =>
                        $branding['logoDataUri'],
                    'dateFrom' => $this->dateFrom,
                    'dateTo' => $this->dateTo,
                    'fundId' => $this->fundId,
                    'movementType' =>
                        $this->movementType,
                    'fundOptions' =>
                        $this->fundOptions,
                    'typeOptions' =>
                        $this->typeOptions,
                    'fundingSourceId' =>
                        $this->fundingSourceId,
                    'fundingSourceOptions' =>
                        $this->fundingSourceOptions,
                ]
            )
            ->setPaper('letter', 'landscape');

        return response()->streamDownload(
            function () use ($pdf): void {
                echo $pdf->output();
            },
            'movimientos_caja_chica_'
                . now()->format('Ymd_His')
                . '.pdf',
            [
                'Content-Type' => 'application/pdf',
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
            return null;
        }

        return ExpenseAccess::currentEmployeeId(
            $this->companyId()
        ) ?? 0;
    }

    protected function companyId(): int
    {
        $companyId = (int) (
            Filament::getTenant()?->getKey() ?? 0
        );

        abort_unless($companyId > 0, 404);

        return $companyId;
    }
}
