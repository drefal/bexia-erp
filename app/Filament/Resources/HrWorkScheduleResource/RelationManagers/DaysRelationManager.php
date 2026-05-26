<?php

namespace App\Filament\Resources\HrWorkScheduleResource\RelationManagers;

use App\Models\HrWorkScheduleDay;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DaysRelationManager extends RelationManager
{
    protected static string $relationship = 'days';

    protected static ?string $title = 'Detalle por día';

    protected static ?string $modelLabel = 'día de horario';

    protected static ?string $pluralModelLabel = 'días de horario';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Día operativo')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('day_of_week')
                            ->label('Día')
                            ->options(HrWorkScheduleDay::DAY_LABELS)
                            ->required()
                            ->live(),

                        Forms\Components\Toggle::make('is_working_day')
                            ->label('Día laborable')
                            ->default(true)
                            ->live(),

                        Forms\Components\TimePicker::make('start_time')
                            ->label('Entrada')
                            ->seconds(false)
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('is_working_day')),

                        Forms\Components\TimePicker::make('end_time')
                            ->label('Salida')
                            ->seconds(false)
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('is_working_day')),

                        Forms\Components\TextInput::make('break_minutes')
                            ->label('Minutos de comida / descanso')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('is_working_day')),

                        Forms\Components\TextInput::make('expected_hours')
                            ->label('Horas esperadas')
                            ->numeric()
                            ->step('0.01')
                            ->helperText('Si se deja vacío, se calcula con entrada, salida y comida.')
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('is_working_day')),

                        Forms\Components\TextInput::make('tolerance_late_minutes')
                            ->label('Tolerancia entrada tarde')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->suffix('min')
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('is_working_day')),

                        Forms\Components\TextInput::make('tolerance_early_leave_minutes')
                            ->label('Tolerancia salida temprana')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->suffix('min')
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('is_working_day')),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('day_of_week')
            ->columns([
                Tables\Columns\TextColumn::make('day_index')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('day_of_week')
                    ->label('Día')
                    ->formatStateUsing(fn (?string $state): string => HrWorkScheduleDay::DAY_LABELS[$state] ?? ($state ?: '-'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_working_day')
                    ->label('Laborable')
                    ->boolean(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Entrada'),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('Salida'),

                Tables\Columns\TextColumn::make('break_minutes')
                    ->label('Comida')
                    ->suffix(' min'),

                Tables\Columns\TextColumn::make('expected_hours')
                    ->label('Horas'),

                Tables\Columns\TextColumn::make('tolerance_late_minutes')
                    ->label('Tol. entrada')
                    ->suffix(' min'),

                Tables\Columns\TextColumn::make('tolerance_early_leave_minutes')
                    ->label('Tol. salida')
                    ->suffix(' min'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar día')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;

                        return $data;
                    }),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
            ])
            ->defaultSort('day_index');
    }
}
