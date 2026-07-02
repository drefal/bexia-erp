<?php

namespace App\Filament\Resources;


use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\Concerns\UsesTenantCompany;
use App\Filament\Resources\HrWorkScheduleResource\Pages;
use App\Filament\Resources\HrWorkScheduleResource\RelationManagers\DaysRelationManager;
use App\Models\HrWorkSchedule;
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
                        ->seconds(false),

                    Forms\Components\TimePicker::make('end_time')
                        ->extraAttributes([
                            'class' => 'bexia-hrws-field bexia-hrws-time-field bexia-hrws-end-field bexia-hrws-compact-field',
                        ])
                        ->label('Salida')
                        ->seconds(false),

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
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('hours_per_day')
                        ->extraAttributes([
                            'class' => 'bexia-hrws-field bexia-hrws-hours-field bexia-hrws-day-field bexia-hrws-compact-field',
                        ])
                        ->label('Horas por día')
                        ->numeric()
                        ->step('0.01'),

                    Forms\Components\TextInput::make('hours_per_week')
                        ->extraAttributes([
                            'class' => 'bexia-hrws-field bexia-hrws-hours-field bexia-hrws-week-field bexia-hrws-compact-field',
                        ])
                        ->label('Horas por semana')
                        ->numeric()
                        ->step('0.01'),

                    Forms\Components\Toggle::make('is_active')
                        ->extraAttributes([
                            'class' => 'bexia-hrws-field bexia-hrws-toggle-field bexia-hrws-active-field',
                        ])
                        ->label('Activo')
                        ->default(true),
                ]),
        ]);
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
        return static::bexiaCanCatalogPermission('rrhh.catalogos.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanCatalogPermission('rrhh.catalogos.ver');
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanCatalogPermission('rrhh.catalogos.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanCatalogPermission('rrhh.catalogos.editar');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanCatalogPermission('rrhh.catalogos.eliminar');
    }

    public static function canDeleteAny(): bool
    {
        return static::bexiaCanCatalogPermission('rrhh.catalogos.eliminar');
    }
    /*
     * V5.64.1i-end
     */

}
