<?php

namespace App\Filament\Resources;


use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\Concerns\UsesTenantCompany;
use App\Filament\Resources\HrWorkScheduleResource\Pages;
use App\Filament\Resources\HrWorkScheduleResource\RelationManagers\DaysRelationManager;
use App\Models\HrWorkSchedule;
use App\Models\HrWorkScheduleDay;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HrWorkScheduleResource extends Resource
{
    /**
     * BEXIA_HR_WORK_SCHEDULE_RESOURCE_RESPONSIVE_V5_79_82C
     *
     * Visual-only responsive classes for HrWorkScheduleResource.
     */
    use UsesTenantCompany;

    protected static ?string $model = HrWorkSchedule::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'RRHH';
    protected static ?string $navigationLabel = 'Horarios';
    protected static ?string $modelLabel = 'horario';
    protected static ?string $pluralModelLabel = 'horarios';
    protected static ?int $navigationSort = 50;
    protected static ?string $tenantOwnershipRelationshipName = 'company';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Horario')
                ->extraAttributes([
                    'class' => 'bexia-hrws-section bexia-hrws-section-main',
                ])
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->extraAttributes([
                            'class' => 'bexia-hrws-field bexia-hrws-name-field bexia-hrws-wide-field',
                        ])
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('code')
                        ->extraAttributes([
                            'class' => 'bexia-hrws-field bexia-hrws-code-field bexia-hrws-compact-field',
                        ])
                        ->label('Código')
                        ->maxLength(80),

                    Forms\Components\Select::make('schedule_type')
                        ->extraAttributes([
                            'class' => 'bexia-hrws-field bexia-hrws-type-field bexia-hrws-medium-field',
                        ])
                        ->label('Tipo')
                        ->required()
                        ->options([
                            'fixed' => 'Fijo',
                            'flexible' => 'Flexible',
                            'rotating' => 'Rotativo',
                        ])
                        ->default('fixed'),

                    Forms\Components\TimePicker::make('start_time')
                        ->extraAttributes([
                            'class' => 'bexia-hrws-field bexia-hrws-time-field bexia-hrws-start-field bexia-hrws-compact-field',
                        ])
                        ->label('Entrada')
                        ->seconds(false)
                        ->live()
                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::updateCalculatedHours($get, $set)),

                    Forms\Components\TimePicker::make('end_time')
                        ->extraAttributes([
                            'class' => 'bexia-hrws-field bexia-hrws-time-field bexia-hrws-end-field bexia-hrws-compact-field',
                        ])
                        ->label('Salida')
                        ->seconds(false)
                        ->live()
                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::updateCalculatedHours($get, $set)),

                    Forms\Components\CheckboxList::make('work_days')
                        ->extraAttributes([
                            'class' => 'bexia-hrws-field bexia-hrws-days-field bexia-hrws-full-field bexia-hrws-checklist-field',
                        ])
                        ->label('Días laborales')
                        ->options([
                            'monday' => 'Lunes',
                            'tuesday' => 'Martes',
                            'wednesday' => 'Miércoles',
                            'thursday' => 'Jueves',
                            'friday' => 'Viernes',
                            'saturday' => 'Sábado',
                            'sunday' => 'Domingo',
                        ])
                        ->columns(3)
                        ->columnSpanFull()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::updateCalculatedHours($get, $set)),

                    Forms\Components\TextInput::make('hours_per_day')
                        ->extraAttributes([
                            'class' => 'bexia-hrws-field bexia-hrws-hours-field bexia-hrws-day-field bexia-hrws-compact-field',
                        ])
                        ->label('Horas por día')
                        ->numeric()
                        ->step('0.01')
                        ->disabled()
                        ->dehydrated()
                        ->helperText('Se calcula automáticamente con entrada, salida y descanso.'),

                    Forms\Components\TextInput::make('hours_per_week')
                        ->extraAttributes([
                            'class' => 'bexia-hrws-field bexia-hrws-hours-field bexia-hrws-week-field bexia-hrws-compact-field',
                        ])
                        ->label('Horas por semana')
                        ->numeric()
                        ->step('0.01')
                        ->disabled()
                        ->dehydrated()
                        ->helperText('Se calcula automáticamente según los días laborales.'),

                    Forms\Components\Toggle::make('is_active')
                        ->extraAttributes([
                            'class' => 'bexia-hrws-field bexia-hrws-toggle-field bexia-hrws-active-field',
                        ])
                        ->label('Activo')
                        ->default(true),
                ]),
        ]);
    }

    /**
     * V5.83.6J2AR10
     *
     * Calcula las horas generales del horario.
     *
     * Si el horario ya tiene detalle operativo por día, usa el descanso
     * configurado para los días seleccionados. Si no existe detalle todavía,
     * calcula la duración bruta entre entrada y salida.
     */
    public static function calculateScheduleHours(array $data, ?HrWorkSchedule $record = null): array
    {
        $startTime = $data['start_time'] ?? null;
        $endTime = $data['end_time'] ?? null;

        $workDays = array_values(array_filter(
            is_array($data['work_days'] ?? null) ? $data['work_days'] : []
        ));

        if (blank($startTime) || blank($endTime) || count($workDays) === 0) {
            return [
                'hours_per_day' => 0.0,
                'hours_per_week' => 0.0,
            ];
        }

        $dailyHours = [];

        $details = collect();

        if ($record && $record->exists) {
            $details = HrWorkScheduleDay::query()
                ->where('hr_work_schedule_id', $record->getKey())
                ->whereIn('day_of_week', $workDays)
                ->get()
                ->keyBy('day_of_week');
        }

        foreach ($workDays as $day) {
            $detail = $details->get($day);

            $breakMinutes = $detail && $detail->is_working_day
                ? max(0, (int) ($detail->break_minutes ?? 0))
                : 0;

            $hours = HrWorkScheduleDay::calculateExpectedHours(
                $startTime,
                $endTime,
                $breakMinutes
            );

            $dailyHours[$day] = max(0, (float) ($hours ?? 0));
        }

        $hoursPerWeek = round(array_sum($dailyHours), 2);

        $hoursPerDay = count($dailyHours) > 0
            ? round($hoursPerWeek / count($dailyHours), 2)
            : 0.0;

        return [
            'hours_per_day' => $hoursPerDay,
            'hours_per_week' => $hoursPerWeek,
        ];
    }

    public static function applyCalculatedHours(array $data, ?HrWorkSchedule $record = null): array
    {
        $hours = self::calculateScheduleHours($data, $record);

        $data['hours_per_day'] = number_format($hours['hours_per_day'], 2, '.', '');
        $data['hours_per_week'] = number_format($hours['hours_per_week'], 2, '.', '');

        return $data;
    }

    protected static function updateCalculatedHours(Forms\Get $get, Forms\Set $set): void
    {
        $hours = self::calculateScheduleHours([
            'start_time' => $get('start_time'),
            'end_time' => $get('end_time'),
            'work_days' => $get('work_days'),
        ]);

        $set('hours_per_day', number_format($hours['hours_per_day'], 2, '.', ''));
        $set('hours_per_week', number_format($hours['hours_per_week'], 2, '.', ''));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hrws-header bexia-hrws-col-name',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hrws-cell bexia-hrws-col-name bexia-hrws-col-wide',
                    ])->label('Nombre')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hrws-header bexia-hrws-col-code',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hrws-cell bexia-hrws-col-code bexia-hrws-col-compact',
                    ])->label('Código')->searchable(),
                Tables\Columns\TextColumn::make('schedule_type')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hrws-header bexia-hrws-col-type',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hrws-cell bexia-hrws-col-type bexia-hrws-col-badge',
                    ])->label('Tipo')->badge(),
                Tables\Columns\TextColumn::make('start_time')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hrws-header bexia-hrws-col-start',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hrws-cell bexia-hrws-col-start bexia-hrws-col-time',
                    ])->label('Entrada'),
                Tables\Columns\TextColumn::make('end_time')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hrws-header bexia-hrws-col-end',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hrws-cell bexia-hrws-col-end bexia-hrws-col-time',
                    ])->label('Salida'),
                Tables\Columns\TextColumn::make('hours_per_week')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hrws-header bexia-hrws-col-hours',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hrws-cell bexia-hrws-col-hours bexia-hrws-col-compact',
                    ])->label('Horas/semana'),
                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hrws-header bexia-hrws-col-active',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hrws-cell bexia-hrws-col-active bexia-hrws-col-bool',
                    ])->label('Activo')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }


    public static function getRelations(): array
    {
        return [
            DaysRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHrWorkSchedules::route('/'),
            'create' => Pages\CreateHrWorkSchedule::route('/create'),
            'edit' => Pages\EditHrWorkSchedule::route('/{record}/edit'),
        ];
    }

    /*
     * V5.64.1i-start
     * Control de permisos RRHH/Nomina.
     * Nota: superadmin puede operar estos catalogos aunque Spatie no resuelva
     * el company_id en auth()->user()->can() dentro de algunos contextos.
     */
    protected static function bexiaCanCatalogPermission(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((bool) ($user->is_system_admin ?? false)) {
            return true;
        }

        if (($user->email ?? null) === 'admin@bexiaerp.com') {
            return true;
        }

        return $user->can($permission);
    }

    public static function canViewAny(): bool
    {
        return static::bexiaCanCatalogPermission('rrhh.horarios.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanCatalogPermission('rrhh.horarios.ver');
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanCatalogPermission('rrhh.horarios.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanCatalogPermission('rrhh.horarios.editar');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanCatalogPermission('rrhh.horarios.eliminar');
    }

    public static function canDeleteAny(): bool
    {
        return static::bexiaCanCatalogPermission('rrhh.horarios.eliminar');
    }
    /*
     * V5.64.1i-end
     */

}
