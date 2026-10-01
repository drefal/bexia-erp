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

    protected $listeners = [
        'hr-work-schedule-days-refresh' => '$refresh',
    ];

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
                            ->default(fn () => $this->getOwnerRecord()->start_time)
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('is_working_day')),

                        Forms\Components\TimePicker::make('end_time')
                            ->label('Salida')
                            ->seconds(false)
                            ->default(fn () => $this->getOwnerRecord()->end_time)
                            ->visible(
                                fn (Forms\Get $get): bool =>
                                    (bool) $get('is_working_day')
                                    && (string) ($this->getOwnerRecord()->schedule_type ?? '') !== 'open'
                            ),

                        Forms\Components\Toggle::make('break_applies')
                            ->label('Aplica descanso')
                            ->default(
                                fn (): bool =>
                                    (int) ($this->getOwnerRecord()->break_minutes ?? 0) > 0
                            )
                            ->live()
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('is_working_day')),

                        Forms\Components\TextInput::make('break_sessions_allowed')
                            ->label('Salidas de descanso permitidas')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(20)
                            ->default(
                                fn (): int =>
                                    (int) ($this->getOwnerRecord()->break_sessions_allowed ?? 0)
                            )
                            ->visible(
                                fn (Forms\Get $get): bool =>
                                    (bool) $get('is_working_day')
                                    && (bool) $get('break_applies')
                            )
                            ->helperText('Puede modificarse si este día tiene una excepción.'),

                        Forms\Components\TextInput::make('expected_hours')
                            ->label('Horas esperadas')
                            ->numeric()
                            ->step('0.01')
                            ->helperText('Si se deja vacío, se calcula con entrada, salida y comida.')
                            ->visible(
                                fn (Forms\Get $get): bool =>
                                    (bool) $get('is_working_day')
                                    && (string) ($this->getOwnerRecord()->schedule_type ?? '') !== 'open'
                            ),

                        Forms\Components\TextInput::make('tolerance_late_minutes')
                            ->label('Tolerancia entrada tarde')
                            ->numeric()
                            ->minValue(0)
                            ->default(
                                fn (): int =>
                                    (int) ($this->getOwnerRecord()->tolerance_late_minutes ?? 0)
                            )
                            ->suffix('min')
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('is_working_day')),

                        Forms\Components\TextInput::make('tolerance_early_leave_minutes')
                            ->label('Tolerancia salida temprana')
                            ->numeric()
                            ->minValue(0)
                            ->default(
                                fn (): int =>
                                    (int) ($this->getOwnerRecord()->tolerance_early_leave_minutes ?? 0)
                            )
                            ->suffix('min')
                            ->visible(
                                fn (Forms\Get $get): bool =>
                                    (bool) $get('is_working_day')
                                    && (string) ($this->getOwnerRecord()->schedule_type ?? '') !== 'open'
                            ),

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

                Tables\Columns\IconColumn::make('break_applies')
                    ->label('Descanso')
                    ->boolean(),

                Tables\Columns\TextColumn::make('break_sessions_allowed')
                    ->label('Salidas')
                    ->formatStateUsing(
                        fn ($state, HrWorkScheduleDay $record): string =>
                            $record->break_applies
                                ? (string) ((int) $state)
                                : '0'
                    ),

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
                        $schedule = $this->getOwnerRecord();
                        $isOpenSchedule =
                            (string) ($schedule->schedule_type ?? '') === 'open';

                        $data['company_id'] = $schedule->company_id;

                        if ($isOpenSchedule) {
                            $data['end_time'] = null;
                            $data['tolerance_early_leave_minutes'] = 0;
                        }

                        $breakApplies =
                            (bool) ($data['is_working_day'] ?? true)
                            && (bool) ($data['break_applies'] ?? false);

                        $data['break_applies'] = $breakApplies;

                        $data['break_sessions_allowed'] = $breakApplies
                            ? max(
                                0,
                                (int) (
                                    $data['break_sessions_allowed']
                                    ?? $schedule->break_sessions_allowed
                                    ?? 0
                                )
                            )
                            : 0;

                        /*
                         * Compatibilidad:
                         * break_minutes físico se conserva, pero ya no se captura
                         * por día. Se deriva de la política general del horario.
                         */
                        $data['break_minutes'] = $breakApplies
                            ? max(0, (int) ($schedule->break_minutes ?? 0))
                            : 0;

                        $data['tolerance_late_minutes'] = max(
                            0,
                            (int) (
                                $data['tolerance_late_minutes']
                                ?? $schedule->tolerance_late_minutes
                                ?? 0
                            )
                        );

                        $data['tolerance_early_leave_minutes'] = max(
                            0,
                            (int) (
                                $data['tolerance_early_leave_minutes']
                                ?? $schedule->tolerance_early_leave_minutes
                                ?? 0
                            )
                        );

                        $data['expected_hours'] = $isOpenSchedule
                            ? null
                            : HrWorkScheduleDay::calculateExpectedHours(
                                $data['start_time'] ?? $schedule->start_time,
                                $data['end_time'] ?? $schedule->end_time,
                                $data['break_minutes'],
                            );

                        return $data;
                    })
                    ->after(function (): void {
                        $this->recalculateOwnerScheduleSummary();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $schedule = $this->getOwnerRecord();
                        $isOpenSchedule =
                            (string) ($schedule->schedule_type ?? '') === 'open';

                        $data['company_id'] = $schedule->company_id;

                        if ($isOpenSchedule) {
                            $data['end_time'] = null;
                            $data['tolerance_early_leave_minutes'] = 0;
                        }

                        $breakApplies =
                            (bool) ($data['is_working_day'] ?? true)
                            && (bool) ($data['break_applies'] ?? false);

                        $data['break_applies'] = $breakApplies;

                        $data['break_sessions_allowed'] = $breakApplies
                            ? max(
                                0,
                                (int) ($data['break_sessions_allowed'] ?? 0)
                            )
                            : 0;

                        $data['break_minutes'] = $breakApplies
                            ? max(0, (int) ($schedule->break_minutes ?? 0))
                            : 0;

                        $data['tolerance_late_minutes'] = max(
                            0,
                            (int) ($data['tolerance_late_minutes'] ?? 0)
                        );

                        $data['tolerance_early_leave_minutes'] = max(
                            0,
                            (int) ($data['tolerance_early_leave_minutes'] ?? 0)
                        );

                        $data['expected_hours'] = $isOpenSchedule
                            ? null
                            : HrWorkScheduleDay::calculateExpectedHours(
                                $data['start_time'] ?? $schedule->start_time,
                                $data['end_time'] ?? $schedule->end_time,
                                $data['break_minutes'],
                            );

                        return $data;
                    })
                    ->after(function (): void {
                        $this->recalculateOwnerScheduleSummary();
                    }),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
            ])
            ->defaultSort('day_index');
    }

    protected function recalculateOwnerScheduleSummary(): void
    {
        $schedule = $this->getOwnerRecord()->fresh();

        $days = HrWorkScheduleDay::query()
            ->where(
                'hr_work_schedule_id',
                $schedule->getKey()
            )
            ->orderBy('day_index')
            ->get();

        $workingDays = $days
            ->where('is_working_day', true)
            ->values();

        $isOpenSchedule =
            (string) ($schedule->schedule_type ?? '') === 'open';

        $weekHours = $isOpenSchedule
            ? 0.0
            : round(
                $workingDays->sum(
                    fn (HrWorkScheduleDay $day): float =>
                        (float) ($day->expected_hours ?? 0)
                ),
                2
            );

        $count = $workingDays->count();

        $averageHours = $count > 0
            ? round($weekHours / $count, 2)
            : 0;

        $schedule->forceFill([
            'work_days' => $workingDays
                ->pluck('day_of_week')
                ->values()
                ->all(),
            'hours_per_day' => $averageHours,
            'hours_per_week' => $weekHours,
        ])->saveQuietly();

        $this->dispatch(
            'hr-work-schedule-header-refresh'
        );
    }

}
