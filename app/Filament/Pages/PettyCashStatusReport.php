<?php

namespace App\Filament\Pages;

use App\Support\Expenses\ExpenseAccess;
use App\Support\Expenses\ExpenseReportBranding;
use App\Support\Expenses\PettyCashStatusReportService;
use App\Support\Security\BexiaTenantPermission;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PettyCashStatusReport extends Page
{
    protected static ?string $navigationIcon =
        'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Gastos';

    protected static ?string $navigationLabel =
        'Estado de cajas chicas';

    protected static ?string $title =
        'Estado de cajas chicas';

    protected static ?int $navigationSort = 40;

    protected static string $view =
        'filament.pages.petty-cash-status-report';

    public Collection $rows;

    public array $summary = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

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

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function loadReport(): void
    {
        $companyId = (int) (
            Filament::getTenant()?->getKey() ?? 0
        );

        abort_unless($companyId > 0, 404);

        $this->rows =
            PettyCashStatusReportService::rows(
                $companyId,
                $this->effectiveEmployeeId($companyId)
            );

        $this->summary =
            PettyCashStatusReportService::summary(
                $this->rows
            );
    }

    public function exportExcel(): BinaryFileResponse
    {
        abort_unless(static::canAccess(), 403);

        $companyId = (int) (
            Filament::getTenant()?->getKey() ?? 0
        );

        abort_unless($companyId > 0, 404);

        $tmp = tempnam(
            sys_get_temp_dir(),
            'bexia_petty_cash_status_'
        );

        if ($tmp === false) {
            throw new \RuntimeException(
                'No se pudo crear archivo temporal para Excel.'
            );
        }

        $path = $tmp . '.xlsx';

        PettyCashStatusReportService::writeExcel(
            $path,
            $companyId,
            $this->effectiveEmployeeId($companyId)
        );

        return response()
            ->download(
                $path,
                'estado_cajas_chicas_'
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

        $companyId = (int) (
            Filament::getTenant()?->getKey() ?? 0
        );

        abort_unless($companyId > 0, 404);

        $rows =
            PettyCashStatusReportService::rows(
                $companyId,
                $this->effectiveEmployeeId($companyId)
            );

        $summary =
            PettyCashStatusReportService::summary(
                $rows
            );

        $branding =
            ExpenseReportBranding::forCompany(
                $companyId
            );

        $pdf = app('dompdf.wrapper')
            ->loadView(
                'reports.expenses.petty-cash-status-report-pdf',
                [
                    'rows' => $rows,
                    'summary' => $summary,
                    'generatedAt' => now(),
                    'companyName' =>
                        $branding['companyName'],
                    'logoDataUri' =>
                        $branding['logoDataUri'],
                ]
            )
            ->setPaper('letter', 'landscape');

        return response()->streamDownload(
            function () use ($pdf): void {
                echo $pdf->output();
            },
            'estado_cajas_chicas_'
                . now()->format('Ymd_His')
                . '.pdf',
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }

    protected function effectiveEmployeeId(
        int $companyId
    ): ?int {
        if (ExpenseAccess::canViewAll()) {
            return null;
        }

        return ExpenseAccess::currentEmployeeId(
            $companyId
        ) ?? 0;
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
}
