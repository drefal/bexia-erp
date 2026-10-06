<?php

namespace App\Filament\Pages;

use App\Support\Expenses\ExpenseReportBranding;
use App\Support\Expenses\PettyCashAsOfReportService;
use App\Support\Security\BexiaTenantPermission;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PettyCashAsOfReport extends Page
{
    protected static ?string $navigationIcon =
        'heroicon-o-calendar-days';

    protected static ?string $navigationGroup =
        'Gastos';

    protected static ?string $navigationLabel =
        'Caja chica a una fecha';

    protected static ?string $title =
        'Caja chica a una fecha';

    protected static ?int $navigationSort =
        43;

    protected static string $view =
        'filament.pages.petty-cash-as-of-report';

    public string $asOfDate;

    public ?int $fundId = null;

    public array $fundOptions = [];

    public Collection $rows;

    public array $summary = [];

    public function mount(): void
    {
        abort_unless(
            static::canAccess(),
            403
        );

        $this->asOfDate =
            now()->toDateString();

        $this->fundOptions =
            PettyCashAsOfReportService::fundOptions(
                $this->companyId()
            );

        $this->loadReport();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (
            (bool) (
                $user->is_system_admin
                ?? false
            )
        ) {
            return true;
        }

        return BexiaTenantPermission::can(
            'expenses.admin'
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

    public function applyFilters(): void
    {
        $this->loadReport();
    }

    public function clearFilters(): void
    {
        $this->asOfDate =
            now()->toDateString();

        $this->fundId = null;

        $this->loadReport();
    }

    public function loadReport(): void
    {
        $this->rows =
            PettyCashAsOfReportService::rows(
                $this->companyId(),
                $this->asOfDate,
                $this->fundId
            );

        $this->summary =
            PettyCashAsOfReportService::summary(
                $this->rows
            );
    }

    public function exportExcel(): BinaryFileResponse
    {
        abort_unless(
            static::canAccess(),
            403
        );

        $tmp = tempnam(
            sys_get_temp_dir(),
            'bexia_petty_cash_asof_'
        );

        if ($tmp === false) {
            throw new \RuntimeException(
                'No se pudo crear archivo temporal.'
            );
        }

        $path = $tmp . '.xlsx';

        PettyCashAsOfReportService::writeExcel(
            $path,
            $this->companyId(),
            $this->asOfDate,
            $this->fundId
        );

        return response()
            ->download(
                $path,
                'caja_chica_a_'
                    . $this->asOfDate
                    . '.xlsx'
            )
            ->deleteFileAfterSend(true);
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(
            static::canAccess(),
            403
        );

        $branding =
            ExpenseReportBranding::forCompany(
                $this->companyId()
            );

        $pdf = app('dompdf.wrapper')
            ->loadView(
                'reports.expenses.petty-cash-as-of-report-pdf',
                [
                    'rows' =>
                        $this->rows,

                    'summary' =>
                        $this->summary,

                    'companyName' =>
                        $branding['companyName'],

                    'logoDataUri' =>
                        $branding['logoDataUri'],

                    'asOfDate' =>
                        $this->asOfDate,

                    'fundId' =>
                        $this->fundId,

                    'fundOptions' =>
                        $this->fundOptions,

                    'generatedAt' =>
                        now(),
                ]
            )
            ->setPaper(
                'letter',
                'landscape'
            );

        return response()->streamDownload(
            fn () =>
                print($pdf->output()),

            'caja_chica_a_'
                . $this->asOfDate
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
                ->icon(
                    'heroicon-o-arrow-path'
                )
                ->action('loadReport'),

            Action::make('excel')
                ->label('Exportar Excel')
                ->icon(
                    'heroicon-o-table-cells'
                )
                ->action('exportExcel'),

            Action::make('pdf')
                ->label('Exportar PDF')
                ->icon(
                    'heroicon-o-document-arrow-down'
                )
                ->action('exportPdf'),
        ];
    }

    protected function companyId(): int
    {
        $id = (int) (
            Filament::getTenant()?->getKey()
            ?? 0
        );

        abort_unless(
            $id > 0,
            404
        );

        return $id;
    }
}
