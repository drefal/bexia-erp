<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollPolicyResource\Pages;
use App\Models\PayrollPolicy;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PayrollPolicyResource extends Resource
{
    protected static ?string $model = PayrollPolicy::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Nómina';

    protected static ?string $navigationLabel = 'Políticas de nómina';

    protected static ?string $modelLabel = 'política de nómina';

    protected static ?string $pluralModelLabel = 'políticas de nómina';

    protected static ?int $navigationSort = 24;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function bexiaCanPolicyPermission(string $permission): bool
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
        return static::bexiaCanPolicyPermission('nomina.politicas.ver');
    }

    public static function canViewAny(): bool
    {
        return static::bexiaCanPolicyPermission('nomina.politicas.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanPolicyPermission('nomina.politicas.ver');
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanPolicyPermission('nomina.politicas.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanPolicyPermission('nomina.politicas.editar');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanPolicyPermission('nomina.politicas.eliminar');
    }

    public static function form(Form $form): Form
    {
        $tenantId = Filament::getTenant()?->getKey();

        return $form
            ->schema([
                Forms\Components\Section::make('Datos generales')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default($tenantId)
                            ->disabled(filled($tenantId))
                            ->dehydrated(),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->default('Política estándar de nómina'),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options(PayrollPolicy::statusOptions())
                            ->required()
                            ->default('active'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Política activa')
                            ->helperText('Solo puede existir una política activa por empresa.')
                            ->default(true),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Horas extra y días trabajados')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('overtime_multiplier')
                            ->label('Multiplicador horas extra')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.0001')
                            ->required()
                            ->default(2),

                        Forms\Components\TextInput::make('rest_day_overtime_multiplier')
                            ->label('Multiplicador descanso trabajado')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.0001')
                            ->required()
                            ->default(2),

                        Forms\Components\TextInput::make('holiday_overtime_multiplier')
                            ->label('Multiplicador festivo trabajado')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.0001')
                            ->required()
                            ->default(2),

                        Forms\Components\Select::make('rest_day_worked_mode')
                            ->label('Descanso trabajado')
                            ->options(PayrollPolicy::workedDayModeOptions())
                            ->required()
                            ->default('informational'),

                        Forms\Components\Select::make('holiday_worked_mode')
                            ->label('Festivo trabajado')
                            ->options(PayrollPolicy::workedDayModeOptions())
                            ->required()
                            ->default('informational'),
                    ]),

                Forms\Components\Section::make('Retardos, salidas tempranas y faltas')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('late_tolerance_minutes')
                            ->label('Tolerancia adicional retardo (min)')
                            ->numeric()
                            ->minValue(0)
                            ->integer()
                            ->required()
                            ->default(0)
                            ->helperText('Se aplica sobre los minutos de retardo ya calculados por asistencia.'),

                        Forms\Components\Select::make('late_discount_mode')
                            ->label('Modo retardo')
                            ->options(PayrollPolicy::lateDiscountModeOptions())
                            ->required()
                            ->default('none'),

                        Forms\Components\TextInput::make('late_minutes_to_absence')
                            ->label('Minutos retardo = 1 falta')
                            ->numeric()
                            ->minValue(0)
                            ->integer()
                            ->required()
                            ->default(0),

                        Forms\Components\Select::make('early_leave_discount_mode')
                            ->label('Salida temprana')
                            ->options(PayrollPolicy::earlyLeaveDiscountModeOptions())
                            ->required()
                            ->default('none'),

                        Forms\Components\Select::make('absence_discount_mode')
                            ->label('Faltas')
                            ->options(PayrollPolicy::absenceDiscountModeOptions())
                            ->required()
                            ->default('incident_only'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => PayrollPolicy::statusOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('overtime_multiplier')
                    ->label('Extra')
                    ->suffix('x')
                    ->sortable(),

                Tables\Columns\TextColumn::make('late_tolerance_minutes')
                    ->label('Tol. retardo')
                    ->suffix(' min')
                    ->sortable(),

                Tables\Columns\TextColumn::make('late_discount_mode')
                    ->label('Retardos')
                    ->formatStateUsing(fn (?string $state): string => PayrollPolicy::lateDiscountModeOptions()[$state] ?? ($state ?: '-'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('absence_discount_mode')
                    ->label('Faltas')
                    ->formatStateUsing(fn (?string $state): string => PayrollPolicy::absenceDiscountModeOptions()[$state] ?? ($state ?: '-'))
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activa'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(PayrollPolicy::statusOptions()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
            ])
            ->defaultSort('is_active', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $tenantId = Filament::getTenant()?->getKey();

        if ($tenantId) {
            $query->where('company_id', $tenantId);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollPolicies::route('/'),
            'create' => Pages\CreatePayrollPolicy::route('/create'),
            'edit' => Pages\EditPayrollPolicy::route('/{record}/edit'),
        ];
    }
}
