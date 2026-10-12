<?php

namespace App\Filament\Resources\PayrollRunResource\RelationManagers;

use App\Models\PayrollRunVersion;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'Historial de versiones';

    protected static ?string $modelLabel = 'versión';

    protected static ?string $pluralModelLabel = 'versiones';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('version_number')
                    ->label('Versión')
                    ->formatStateUsing(
                        fn ($state): string => 'V' . $state
                    )
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('snapshot_reason')
                    ->label('Origen')
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'baseline' => 'Inicial',
                            'calculation' => 'Cálculo',
                            default => $state ?: '-',
                        }
                    )
                    ->badge(),

                Tables\Columns\TextColumn::make('snapshot_at')
                    ->label('Guardada')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('snapshotBy.name')
                    ->label('Usuario')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('employees_count')
                    ->label('Empleados'),

                Tables\Columns\TextColumn::make('base_total')
                    ->label('Base')
                    ->money('MXN'),

                Tables\Columns\TextColumn::make('gross_total')
                    ->label('Bruto')
                    ->money('MXN'),

                Tables\Columns\TextColumn::make('deductions_total')
                    ->label('Deducciones')
                    ->money('MXN'),

                Tables\Columns\TextColumn::make('net_total')
                    ->label('Neto')
                    ->money('MXN')
                    ->weight('bold'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_lines')
                    ->label('Ver detalle')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(
                        fn (PayrollRunVersion $record): string =>
                            'Versión V' . $record->version_number
                    )
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->modalContent(
                        fn (PayrollRunVersion $record) =>
                            view(
                                'filament.payroll.version-lines-modal',
                                [
                                    'version' => $record->load('lines'),
                                ]
                            )
                    ),

                Tables\Actions\Action::make('download_csv')
                    ->label('CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(
                        fn (PayrollRunVersion $record): StreamedResponse =>
                            static::downloadCsv($record)
                    ),
            ])
            ->defaultSort('version_number', 'desc');
    }

    protected static function downloadCsv(
        PayrollRunVersion $version
    ): StreamedResponse {
        $version->load('lines');

        $filename = sprintf(
            'prenomina_%d_v%d_%s.csv',
            $version->payroll_run_id,
            $version->version_number,
            optional($version->snapshot_at)->format('Ymd_His')
                ?: now()->format('Ymd_His')
        );

        return response()->streamDownload(
            function () use ($version): void {
                $out = fopen('php://output', 'w');

                fwrite($out, "\xEF\xBB\xBF");

                fputcsv($out, [
                    'Version',
                    'Empleado',
                    'Contrato',
                    'Sueldo base',
                    'Tipo sueldo',
                    'Salario diario',
                    'Dias periodo',
                    'Dias pagados',
                    'Horas trabajadas',
                    'Retardo min',
                    'Faltas',
                    'Horas extra',
                    'Base',
                    'Extra',
                    'Percepciones',
                    'Deducciones incidencias',
                    'Bruto',
                    'Deducciones',
                    'Neto',
                ]);

                foreach ($version->lines as $line) {
                    fputcsv($out, [
                        $version->version_number,
                        $line->employee_name_snapshot,
                        $line->contract_number_snapshot,
                        $line->base_salary,
                        $line->salary_type,
                        $line->daily_salary,
                        $line->period_days,
                        $line->payable_days,
                        $line->worked_hours,
                        $line->late_minutes,
                        $line->absence_days,
                        $line->overtime_hours,
                        $line->base_amount,
                        $line->overtime_amount,
                        $line->incident_perceptions,
                        $line->incident_deductions,
                        $line->gross_amount,
                        $line->deductions_amount,
                        $line->net_amount,
                    ]);
                }

                fclose($out);
            },
            $filename,
            [
                'Content-Type' =>
                    'text/csv; charset=UTF-8',
            ]
        );
    }
}
