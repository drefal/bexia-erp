<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeePayrollPerceptionResource\Pages;
use App\Models\EmployeePayrollPerception;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmployeePayrollPerceptionResource extends Resource
{
    protected static ?string $model = EmployeePayrollPerception::class;

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationGroup = 'Nómina';

    protected static ?string $navigationLabel = 'Percepciones de empleados';

    protected static ?string $modelLabel = 'percepción de empleado';

    protected static ?string $pluralModelLabel = 'percepciones de empleados';

    protected static ?int $navigationSort = 27;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function bexiaCanPerceptionPermission(string $permission): bool
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

        return $user->can($permission) || $user->can('company.update');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::bexiaCanPerceptionPermission('nomina.percepciones.ver');
    }

    public static function canViewAny(): bool
    {
        return static::bexiaCanPerceptionPermission('nomina.percepciones.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanPerceptionPermission('nomina.percepciones.ver');
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanPerceptionPermission('nomina.percepciones.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanPerceptionPermission('nomina.percepciones.editar');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanPerceptionPermission('nomina.percepciones.eliminar');
    }

    /*
     * BEXIA_EPPER_RESOURCE_RESPONSIVE_V5_79_56C
     * Visual-only responsive marker.
     */


    public static function form(Form $form): Form
    {
        $tenantId = Filament::getTenant()?->getKey();

        return $form
            ->schema([
                Forms\Components\Section::make('Datos generales')
                    ->extraAttributes(['class' => 'bexia-epper-section bexia-epper-section-general'])
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->extraAttributes(['class' => 'bexia-epper-field bexia-epper-field-company bexia-epper-select'])
                            ->label('Empresa')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default($tenantId)
                            ->disabled(filled($tenantId))
                            ->dehydrated(),

                        Forms\Components\Select::make('employee_id')
                            ->extraAttributes(['class' => 'bexia-epper-field bexia-epper-field-employee bexia-epper-select'])
                            ->label('Empleado')
                            ->relationship(
                                'employee',
                                'name',
                                fn (Builder $query) => $tenantId ? $query->where('company_id', $tenantId) : $query
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('type')
                            ->extraAttributes(['class' => 'bexia-epper-field bexia-epper-field-type bexia-epper-select'])
                            ->label('Tipo')
                            ->options(EmployeePayrollPerception::typeOptions())
                            ->required()
                            ->default('bonus')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                $code = EmployeePayrollPerception::defaultCodeForType((string) $state);
                                $set('code', $code);
                                $set('name', EmployeePayrollPerception::typeOptions()[(string) $state] ?? 'Percepción de empleado');
                            }),

                        Forms\Components\TextInput::make('code')
                            ->extraAttributes(['class' => 'bexia-epper-field bexia-epper-field-code bexia-epper-mono'])
                            ->label('Código')
                            ->required()
                            ->maxLength(80)
                            ->default('BONO_PRODUCTIVIDAD')
                            ->uppercase(),

                        Forms\Components\TextInput::make('name')
                            ->extraAttributes(['class' => 'bexia-epper-field bexia-epper-field-name'])
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->default('Bono productividad'),

                        Forms\Components\Select::make('payroll_concept_id')
                            ->extraAttributes(['class' => 'bexia-epper-field bexia-epper-field-concept bexia-epper-select'])
                            ->label('Concepto de nómina')
                            ->relationship(
                                'concept',
                                'name',
                                fn (Builder $query) => $tenantId ? $query->where('company_id', $tenantId)->where('type', 'perception') : $query->where('type', 'perception')
                            )
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Select::make('status')
                            ->extraAttributes(['class' => 'bexia-epper-field bexia-epper-field-status bexia-epper-select'])
                            ->label('Estado')
                            ->options(EmployeePayrollPerception::statusOptions())
                            ->required()
                            ->default('active'),
                    ]),

                Forms\Components\Section::make('Montos y calendario')
                    ->extraAttributes(['class' => 'bexia-epper-section bexia-epper-section-amounts'])
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('original_amount')
                            ->extraAttributes(['class' => 'bexia-epper-field bexia-epper-field-money bexia-epper-field-original'])
                            ->label('Monto original')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->required()
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                if ((float) ($get('remaining_amount') ?? 0) <= 0) {
                                    $set('remaining_amount', (float) $state);
                                }
                            }),

                        Forms\Components\TextInput::make('remaining_amount')
                            ->extraAttributes(['class' => 'bexia-epper-field bexia-epper-field-money bexia-epper-field-remaining'])
                            ->label('Saldo pendiente por pagar')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->required()
                            ->default(0),

                        Forms\Components\TextInput::make('period_amount')
                            ->extraAttributes(['class' => 'bexia-epper-field bexia-epper-field-money bexia-epper-field-period'])
                            ->label('Monto por periodo')
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('$')
                            ->required()
                            ->default(0),

                        Forms\Components\DatePicker::make('start_date')
                            ->extraAttributes(['class' => 'bexia-epper-field bexia-epper-field-date bexia-epper-field-start'])
                            ->label('Fecha inicio')
                            ->default(now())
                            ->required(),

                        Forms\Components\DatePicker::make('end_date')
                            ->extraAttributes(['class' => 'bexia-epper-field bexia-epper-field-date bexia-epper-field-end'])
                            ->label('Fecha fin')
                            ->nullable(),

                        Forms\Components\TextInput::make('max_periods')
                            ->extraAttributes(['class' => 'bexia-epper-field bexia-epper-field-periods bexia-epper-field-max-periods'])
                            ->label('Máximo periodos')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->nullable(),

                        Forms\Components\TextInput::make('applied_periods')
                            ->extraAttributes(['class' => 'bexia-epper-field bexia-epper-field-periods bexia-epper-field-applied-periods'])
                            ->label('Periodos aplicados')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                    ]),

                Forms\Components\Section::make('Notas')
                    ->extraAttributes(['class' => 'bexia-epper-section bexia-epper-section-notes'])
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->extraAttributes(['class' => 'bexia-epper-field bexia-epper-field-notes'])
                            ->label('Notas')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->extraHeaderAttributes(['class' => 'bexia-epper-col-employee bexia-epper-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-epper-col-employee bexia-epper-col-primary'])
                    ->label('Empleado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->extraHeaderAttributes(['class' => 'bexia-epper-col-type bexia-epper-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-epper-col-type bexia-epper-col-badge'])
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => EmployeePayrollPerception::typeOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes(['class' => 'bexia-epper-col-name bexia-epper-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-epper-col-name bexia-epper-col-primary'])
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('original_amount')
                    ->extraHeaderAttributes(['class' => 'bexia-epper-col-money bexia-epper-col-original'])
                    ->extraCellAttributes(['class' => 'bexia-epper-col-money bexia-epper-col-original'])
                    ->label('Original')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->extraHeaderAttributes(['class' => 'bexia-epper-col-money bexia-epper-col-remaining'])
                    ->extraCellAttributes(['class' => 'bexia-epper-col-money bexia-epper-col-remaining'])
                    ->label('Pendiente')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_amount')
                    ->extraHeaderAttributes(['class' => 'bexia-epper-col-money bexia-epper-col-period'])
                    ->extraCellAttributes(['class' => 'bexia-epper-col-money bexia-epper-col-period'])
                    ->label('Por periodo')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('applied_periods')
                    ->extraHeaderAttributes(['class' => 'bexia-epper-col-periods bexia-epper-mono'])
                    ->extraCellAttributes(['class' => 'bexia-epper-col-periods bexia-epper-mono'])
                    ->label('Aplicados')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->extraHeaderAttributes(['class' => 'bexia-epper-col-status bexia-epper-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-epper-col-status bexia-epper-col-badge'])
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => EmployeePayrollPerception::statusOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(EmployeePayrollPerception::typeOptions()),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(EmployeePayrollPerception::statusOptions()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['employee', 'concept']);

        $tenantId = Filament::getTenant()?->getKey();

        if ($tenantId) {
            $query->where('company_id', $tenantId);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeePayrollPerceptions::route('/'),
            'create' => Pages\CreateEmployeePayrollPerception::route('/create'),
            'edit' => Pages\EditEmployeePayrollPerception::route('/{record}/edit'),
        ];
    }
}
