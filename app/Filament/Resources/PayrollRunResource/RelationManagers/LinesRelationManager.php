<?php

namespace App\Filament\Resources\PayrollRunResource\RelationManagers;

use App\Models\PayrollRunLine;
use App\Support\PayrollRunExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Detalle por empleado';

    protected static ?string $modelLabel = 'línea de pre-nómina';

    protected static ?string $pluralModelLabel = 'líneas de pre-nómina';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('employee.name')
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Empleado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('base_salary')
                    ->label('Sueldo')
                    ->money('MXN'),

                Tables\Columns\TextColumn::make('salary_type')
                    ->label('Tipo')
                    ->badge(),

                Tables\Columns\TextColumn::make('period_days')
                    ->label('Días'),

                Tables\Columns\TextColumn::make('worked_hours')
                    ->label('Horas'),

                Tables\Columns\TextColumn::make('late_minutes')
                    ->label('Retardo')
                    ->suffix(' min'),

                Tables\Columns\TextColumn::make('absence_days')
                    ->label('Faltas'),

                Tables\Columns\TextColumn::make('overtime_hours')
                    ->label('H. extra'),

                Tables\Columns\TextColumn::make('base_amount')
                    ->label('Base')
                    ->money('MXN'),

                Tables\Columns\TextColumn::make('overtime_amount')
                    ->label('Extra')
                    ->money('MXN'),

                Tables\Columns\TextColumn::make('incident_perceptions')
                    ->label('Percepciones')
                    ->money('MXN'),

                Tables\Columns\TextColumn::make('incident_deductions')
                    ->label('Deducciones')
                    ->money('MXN'),

                Tables\Columns\TextColumn::make('net_amount')
                    ->label('Neto')
                    ->money('MXN')
                    ->weight('bold'),
            ])
            ->actions([
                Tables\Actions\Action::make('receipt_pdf')
                    ->label('Recibo PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->visible(fn (PayrollRunLine $record): bool => filled($record->payroll_run_id) && filled($record->employee_id))
                    ->action(fn (PayrollRunLine $record): StreamedResponse => PayrollRunExportService::exportReceiptPdf($record)),
            ])
            ->defaultSort('employee.name');
    }
}
