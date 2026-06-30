<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeePayrollDeductionResource\Pages;
use App\Models\EmployeePayrollDeduction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmployeePayrollDeductionResource extends Resource
{
    protected static ?string $model = EmployeePayrollDeduction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Nómina';

    protected static ?string $navigationLabel = 'Descuentos de empleados';

    protected static ?string $modelLabel = 'descuento de empleado';

    protected static ?string $pluralModelLabel = 'descuentos de empleados';

    protected static ?int $navigationSort = 26;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function bexiaCanDeductionPermission(string $permission): bool
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
        return static::bexiaCanDeductionPermission('nomina.descuentos.ver');
    }

    public static function canViewAny(): bool
    {
        return static::bexiaCanDeductionPermission('nomina.descuentos.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanDeductionPermission('nomina.descuentos.ver');
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanDeductionPermission('nomina.descuentos.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanDeductionPermission('nomina.descuentos.editar');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanDeductionPermission('nomina.descuentos.eliminar');
    }

    /*
     * BEXIA_EPDED_RESOURCE_RESPONSIVE_V5_79_55C
     * Visual-only responsive marker.
     */


    public static function form(Form $form): Form
    {
        $tenantId = Filament::getTenant()?->getKey();

        return $form
            ->schema([
                Forms\Components\Section::make('Datos generales')
                    ->extraAttributes(['class' => 'bexia-epded-section bexia-epded-section-general'])
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->extraAttributes(['class' => 'bexia-epded-field bexia-epded-field-company bexia-epded-select'])
                            ->label('Empresa')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default($tenantId)
                            ->disabled(filled($tenantId))
                            ->dehydrated(),

                        Forms\Components\Select::make('employee_id')
                            ->extraAttributes(['class' => 'bexia-epded-field bexia-epded-field-employee bexia-epded-select'])
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
                            ->extraAttributes(['class' => 'bexia-epded-field bexia-epded-field-type bexia-epded-select'])
                            ->label('Tipo')
                            ->options(EmployeePayrollDeduction::typeOptions())
                            ->required()
                            ->default('loan')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                $code = EmployeePayrollDeduction::defaultCodeForType((string) $state);
                                $set('code', $code);
                                $set('name', EmployeePayrollDeduction::typeOptions()[(string) $state] ?? 'Descuento de empleado');
                            }),

                        Forms\Components\TextInput::make('code')
                            ->extraAttributes(['class' => 'bexia-epded-field bexia-epded-field-code bexia-epded-mono'])
                            ->label('Código')
                            ->required()
                            ->maxLength(80)
                            ->default('PRESTAMO_EMPLEADO')
                            ->uppercase(),

                        Forms\Components\TextInput::make('name')
                            ->extraAttributes(['class' => 'bexia-epded-field bexia-epded-field-name'])
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->default('Préstamo empleado'),

                        Forms\Components\Select::make('payroll_concept_id')
                            ->extraAttributes(['class' => 'bexia-epded-field bexia-epded-field-concept bexia-epded-select'])
                            ->label('Concepto de nómina')
                            ->relationship(
                                'concept',
                                'name',
                                fn (Builder $query) => $tenantId ? $query->where('company_id', $tenantId)->where('type', 'deduction') : $query->where('type', 'deduction')
                            )
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Select::make('status')
                            ->extraAttributes(['class' => 'bexia-epded-field bexia-epded-field-status bexia-epded-select'])
                            ->label('Estado')
                            ->options(EmployeePayrollDeduction::statusOptions())
                            ->required()
                            ->default('active'),
                    ]),

                Forms\Components\Section::make('Montos y calendario')
                    ->extraAttributes(['class' => 'bexia-epded-section bexia-epded-section-amounts'])
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('original_amount')
                            ->extraAttributes(['class' => 'bexia-epded-field bexia-epded-field-money bexia-epded-field-original'])
                            ->label('Monto original')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->required()
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                if ((float) ($get('outstanding_amount') ?? 0) <= 0) {
                                    $set('outstanding_amount', (float) $state);
                                }
                            }),

                        Forms\Components\TextInput::make('outstanding_amount')
                            ->extraAttributes(['class' => 'bexia-epded-field bexia-epded-field-money bexia-epded-field-outstanding'])
                            ->label('Saldo pendiente')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->required()
                            ->default(0),

                        Forms\Components\TextInput::make('period_amount')
                            ->extraAttributes(['class' => 'bexia-epded-field bexia-epded-field-money bexia-epded-field-period'])
                            ->label('Monto por periodo')
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('$')
                            ->required()
                            ->default(0),

                        Forms\Components\DatePicker::make('start_date')
                            ->extraAttributes(['class' => 'bexia-epded-field bexia-epded-field-date bexia-epded-field-start'])
                            ->label('Fecha inicio')
                            ->default(now())
                            ->required(),

                        Forms\Components\DatePicker::make('end_date')
                            ->extraAttributes(['class' => 'bexia-epded-field bexia-epded-field-date bexia-epded-field-end'])
                            ->label('Fecha fin')
                            ->nullable(),

                        Forms\Components\TextInput::make('max_periods')
                            ->extraAttributes(['class' => 'bexia-epded-field bexia-epded-field-periods bexia-epded-field-max-periods'])
                            ->label('Máximo periodos')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->nullable(),

                        Forms\Components\TextInput::make('applied_periods')
                            ->extraAttributes(['class' => 'bexia-epded-field bexia-epded-field-periods bexia-epded-field-applied-periods'])
                            ->label('Periodos aplicados')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                    ]),

                Forms\Components\Section::make('Notas')
                    ->extraAttributes(['class' => 'bexia-epded-section bexia-epded-section-notes'])
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->extraAttributes(['class' => 'bexia-epded-field bexia-epded-field-notes'])
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
                    ->extraHeaderAttributes(['class' => 'bexia-epded-col-employee bexia-epded-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-epded-col-employee bexia-epded-col-primary'])
                    ->label('Empleado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->extraHeaderAttributes(['class' => 'bexia-epded-col-type bexia-epded-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-epded-col-type bexia-epded-col-badge'])
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => EmployeePayrollDeduction::typeOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes(['class' => 'bexia-epded-col-name bexia-epded-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-epded-col-name bexia-epded-col-primary'])
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('original_amount')
                    ->extraHeaderAttributes(['class' => 'bexia-epded-col-money bexia-epded-col-original'])
                    ->extraCellAttributes(['class' => 'bexia-epded-col-money bexia-epded-col-original'])
                    ->label('Original')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('outstanding_amount')
                    ->extraHeaderAttributes(['class' => 'bexia-epded-col-money bexia-epded-col-outstanding'])
                    ->extraCellAttributes(['class' => 'bexia-epded-col-money bexia-epded-col-outstanding'])
                    ->label('Pendiente')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_amount')
                    ->extraHeaderAttributes(['class' => 'bexia-epded-col-money bexia-epded-col-period'])
                    ->extraCellAttributes(['class' => 'bexia-epded-col-money bexia-epded-col-period'])
                    ->label('Por periodo')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('applied_periods')
                    ->extraHeaderAttributes(['class' => 'bexia-epded-col-periods bexia-epded-mono'])
                    ->extraCellAttributes(['class' => 'bexia-epded-col-periods bexia-epded-mono'])
                    ->label('Aplicados')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->extraHeaderAttributes(['class' => 'bexia-epded-col-status bexia-epded-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-epded-col-status bexia-epded-col-badge'])
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => EmployeePayrollDeduction::statusOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(EmployeePayrollDeduction::typeOptions()),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(EmployeePayrollDeduction::statusOptions()),
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
            'index' => Pages\ListEmployeePayrollDeductions::route('/'),
            'create' => Pages\CreateEmployeePayrollDeduction::route('/create'),
            'edit' => Pages\EditEmployeePayrollDeduction::route('/{record}/edit'),
        ];
    }
}
