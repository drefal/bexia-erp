<?php

namespace App\Filament\Resources\EmployeePayrollPurchaseResource\Pages;

use App\Filament\Resources\EmployeePayrollPurchaseResource;
use App\Support\EmployeePayrollPurchaseService;
use App\Models\EmployeePayrollPurchase;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListEmployeePayrollPurchases extends ListRecords
{
    protected static string $resource = EmployeePayrollPurchaseResource::class;

    // V5.83.5b14 - TABS ESTADO COMPRAS VIA NOMINA
    protected function statusCount(?string $status = null): int
    {
        $companyId = (int) (Filament::getTenant()?->getKey() ?: 0);

        if ($companyId <= 0) {
            return 0;
        }

        return EmployeePayrollPurchase::query()
            ->where('company_id', $companyId)
            ->when(
                filled($status),
                fn (Builder $query) => $query->where('status', $status)
            )
            ->count();
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos')
                ->badge($this->statusCount()),

            'draft' => Tab::make('Borrador')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', 'draft')
                )
                ->badge($this->statusCount('draft')),

            'confirmed' => Tab::make('Confirmadas')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', 'confirmed')
                )
                ->badge($this->statusCount('confirmed')),

            'paid' => Tab::make('Pagadas')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', 'paid')
                )
                ->badge($this->statusCount('paid')),

            'cancelled' => Tab::make('Canceladas')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', 'cancelled')
                )
                ->badge($this->statusCount('cancelled')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva compra'),

            Actions\Action::make('weekly_report')
                ->label('Reporte semanal')
                ->icon('heroicon-o-document-chart-bar')
                ->form([
                    Forms\Components\DatePicker::make('week_date')
                        ->label('Fecha dentro de la semana')
                        ->native(false)
                        ->default(now())
                        ->required(),

                    Forms\Components\Select::make('format')
                        ->label('Formato')
                        ->options([
                            'pdf' => 'PDF',
                            'csv' => 'CSV',
                        ])
                        ->default('pdf')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $companyId = (int) (Filament::getTenant()?->getKey() ?: 0);

                    if ($companyId <= 0) {
                        throw new \RuntimeException('Selecciona una empresa.');
                    }

                    $report = EmployeePayrollPurchaseService::weeklyReportData(
                        $companyId,
                        $data['week_date']
                    );

                    $from = $report['from']->format('Ymd');
                    $to = $report['to']->format('Ymd');

                    if (($data['format'] ?? 'pdf') === 'csv') {
                        return new StreamedResponse(function () use ($report): void {
                            $out = fopen('php://output', 'wb');

                            fwrite($out, "\xEF\xBB\xBF");

                            fputcsv($out, [
                                'Empleado',
                                'Compra',
                                'Productos',
                                'Pago',
                                'Fecha',
                                'Importe programado',
                                'Importe aplicado',
                                'Estado',
                                'Saldo compra',
                            ]);

                            foreach ($report['rows'] as $row) {
                                fputcsv($out, [
                                    $row['employee'],
                                    $row['purchase_number'],
                                    $row['products'],
                                    $row['installment'],
                                    $row['due_date'],
                                    number_format((float) $row['scheduled_amount'], 2, '.', ''),
                                    number_format((float) $row['applied_amount'], 2, '.', ''),
                                    $row['status'],
                                    number_format((float) $row['outstanding_amount'], 2, '.', ''),
                                ]);
                            }

                            fputcsv($out, []);
                            fputcsv($out, ['TOTAL PROGRAMADO', '', '', '', '', number_format($report['scheduled_total'], 2, '.', '')]);
                            fputcsv($out, ['TOTAL PENDIENTE', '', '', '', '', number_format($report['pending_total'], 2, '.', '')]);
                            fputcsv($out, ['TOTAL APLICADO', '', '', '', '', number_format($report['applied_total'], 2, '.', '')]);

                            fclose($out);
                        }, 200, [
                            'Content-Type' => 'text/csv; charset=UTF-8',
                            'Content-Disposition' => "attachment; filename=\"deducciones_semana_{$from}_{$to}.csv\"",
                        ]);
                    }

                    if (! app()->bound('dompdf.wrapper')) {
                        throw new \RuntimeException('No hay motor PDF instalado.');
                    }

                    $pdf = app('dompdf.wrapper')
                        ->loadView('reports.hr.employee-payroll-purchases-weekly-pdf', $report)
                        ->setPaper('letter', 'landscape');

                    return response()->streamDownload(
                        function () use ($pdf): void {
                            echo $pdf->output();
                        },
                        "deducciones_semana_{$from}_{$to}.pdf",
                        ['Content-Type' => 'application/pdf']
                    );
                }),
        ];
    }
}
