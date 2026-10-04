<?php

namespace App\Filament\Pages;

use App\Support\ComputerRental\ComputerRentalReportService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComputerRentalReport extends Page
{
    protected static ?string $navigationIcon =
        'heroicon-o-chart-bar';

    protected static ?string $navigationGroup =
        'Punto de Venta';

    protected static ?string $navigationLabel =
        'Reporte de Renta';

    protected static ?string $title =
        'Reporte de Renta de equipos';

    protected static ?string $slug =
        'renta-equipos/reporte-operativo';

    protected static ?int $navigationSort = 81;

    protected static string $view =
        'filament.pages.computer-rental-report';

    public string $from = '';

    public string $to = '';

    public ?string $station_id = null;

    public ?string $status = null;

    public ?string $billing_mode = null;

    public ?string $user_id = null;

    public function mount(): void
    {
        $this->from =
            now()
                ->startOfMonth()
                ->toDateString();

        $this->to =
            now()->toDateString();
    }

    /**
     * CIBER3R3_STRICT_ACCESS
     *
     * Reutiliza exactamente el acceso del panel de
     * Renta de equipos:
     * - Papelón company_id 3
     * - pertenencia real al tenant
     * - computer_rental.view
     */
    public static function canAccess(): bool
    {
        return ComputerRentalControl::canAccess();
    }

    public static function shouldRegisterNavigation():
        bool
    {
        return static::canAccess();
    }

    public function filters(): array
    {
        return [
            'company_id' => $this->companyId(),
            'from' => $this->from,
            'to' => $this->to,
            'station_id' => $this->station_id,
            'status' => $this->status,
            'billing_mode' =>
                $this->billing_mode,
            'user_id' => $this->user_id,
        ];
    }

    public function rows(): Collection
    {
        return ComputerRentalReportService::rows(
            $this->filters()
        );
    }

    public function summary(): array
    {
        return ComputerRentalReportService::summary(
            $this->rows()
        );
    }

    public function stationSummary(): Collection
    {
        return ComputerRentalReportService
            ::stationSummary(
                $this->rows()
            );
    }

    public function stationOptions(): array
    {
        $companyId = $this->companyId();

        if ($companyId <= 0) {
            return [];
        }

        return DB::table(
            'computer_rental_stations'
        )
            ->where(
                'company_id',
                $companyId
            )
            ->orderBy('code')
            ->get([
                'id',
                'code',
                'name',
            ])
            ->mapWithKeys(
                fn ($row): array => [
                    (int) $row->id =>
                        trim(
                            (string) $row->code
                            . ' · '
                            . (string) $row->name
                        ),
                ]
            )
            ->all();
    }

    public function userOptions(): array
    {
        $companyId = $this->companyId();

        if ($companyId <= 0) {
            return [];
        }

        /*
         * Usuarios realmente presentes en las sesiones.
         * Evita mostrar todo el catálogo de usuarios.
         */
        $ids = DB::table(
            'computer_rental_sessions'
        )
            ->where(
                'company_id',
                $companyId
            )
            ->select('opened_by_user_id')
            ->whereNotNull(
                'opened_by_user_id'
            )
            ->pluck('opened_by_user_id')
            ->merge(
                DB::table(
                    'computer_rental_sessions'
                )
                    ->where(
                        'company_id',
                        $companyId
                    )
                    ->whereNotNull(
                        'closed_by_user_id'
                    )
                    ->pluck(
                        'closed_by_user_id'
                    )
            )
            ->map(
                fn ($id): int =>
                    (int) $id
            )
            ->filter(
                fn (int $id): bool =>
                    $id > 0
            )
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('users')
            ->whereIn(
                'id',
                $ids->all()
            )
            ->orderBy('name')
            ->pluck(
                'name',
                'id'
            )
            ->toArray();
    }

    public function statusOptions(): array
    {
        return [
            'active' => 'En uso',
            'finished' => 'Finalizada',
            'sent_to_pos' =>
                'Enviada a PDV',
            'paid' => 'Pagada',
            'cancelled' => 'Cancelada',
        ];
    }

    public function billingModeOptions(): array
    {
        return [
            'open' => 'Abierta',
            'prepaid' =>
                'Prepago / paquete',
        ];
    }

    public function clearFilters(): void
    {
        $this->from =
            now()
                ->startOfMonth()
                ->toDateString();

        $this->to =
            now()->toDateString();

        $this->station_id = null;
        $this->status = null;
        $this->billing_mode = null;
        $this->user_id = null;
    }

    public function statusLabel(
        ?string $status
    ): string {
        return ComputerRentalReportService
            ::statusLabel($status);
    }

    public function billingModeLabel(
        ?string $mode
    ): string {
        return ComputerRentalReportService
            ::billingModeLabel($mode);
    }

    public function dateTime(
        mixed $value
    ): string {
        if (! $value) {
            return '—';
        }

        try {
            return \Carbon\Carbon::parse(
                $value
            )->format(
                'd/m/Y H:i'
            );
        } catch (\Throwable) {
            return '—';
        }
    }

    public function duration(
        int|float|null $seconds
    ): string {
        $seconds = max(
            0,
            (int) ($seconds ?? 0)
        );

        $hours = intdiv(
            $seconds,
            3600
        );

        $minutes = intdiv(
            $seconds % 3600,
            60
        );

        $remainingSeconds =
            $seconds % 60;

        if ($hours > 0) {
            return sprintf(
                '%dh %02dm',
                $hours,
                $minutes
            );
        }

        if ($minutes > 0) {
            return sprintf(
                '%dm %02ds',
                $minutes,
                $remainingSeconds
            );
        }

        return sprintf(
            '%ds',
            $remainingSeconds
        );
    }


    /**
     * CIBER3R4_EXPORT_EXCEL
     */
    public function exportExcel():
        BinaryFileResponse
    {
        $tmp = tempnam(
            sys_get_temp_dir(),
            'bexia_renta_'
        );

        if ($tmp === false) {
            throw new \RuntimeException(
                'No se pudo crear archivo temporal para Excel.'
            );
        }

        $path = $tmp . '.xlsx';

        @rename(
            $tmp,
            $path
        );

        ComputerRentalReportService::writeExcel(
            $path,
            $this->filters()
        );

        return response()
            ->download(
                $path,
                $this->filename('xlsx'),
                [
                    'Content-Type' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            )
            ->deleteFileAfterSend(true);
    }

    /**
     * CIBER3R4_EXPORT_PDF
     */
    public function exportPdf():
        StreamedResponse
    {
        if (! app()->bound('dompdf.wrapper')) {
            throw new \RuntimeException(
                'No hay motor PDF instalado (barryvdh/laravel-dompdf).'
            );
        }

        $data =
            ComputerRentalReportService::data(
                $this->filters()
            );

        $pdf = app('dompdf.wrapper')
            ->loadView(
                'reports.computer-rental.operational-report-pdf',
                $data
            )
            ->setPaper(
                'letter',
                'landscape'
            );

        return response()->streamDownload(
            function () use ($pdf): void {
                echo $pdf->output();
            },
            $this->filename('pdf'),
            [
                'Content-Type' =>
                    'application/pdf',
            ]
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Exportar Excel')
                ->icon(
                    'heroicon-o-table-cells'
                )
                ->color('success')
                ->action('exportExcel'),

            Action::make('exportPdf')
                ->label('Exportar PDF')
                ->icon(
                    'heroicon-o-document-arrow-down'
                )
                ->color('gray')
                ->action('exportPdf'),
        ];
    }

    protected function filename(
        string $extension
    ): string {
        $from = str_replace(
            '-',
            '',
            $this->from
                ?: now()
                    ->startOfMonth()
                    ->toDateString()
        );

        $to = str_replace(
            '-',
            '',
            $this->to
                ?: now()->toDateString()
        );

        return
            "reporte_renta_equipos_{$from}_{$to}.{$extension}";
    }

    protected function companyId(): int
    {
        return (int) (
            Filament::getTenant()
                ?->getKey()
            ?? 0
        );
    }
}
