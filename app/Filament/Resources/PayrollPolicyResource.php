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

    /*
     * BEXIA_PPOL_RESOURCE_RESPONSIVE_V5_79_59C
     * Visual-only responsive marker.
     */


    public static function form(Form $form): Form
    {
        $tenantId = Filament::getTenant()?->getKey();

        return $form
            ->schema([
                Forms\Components\Section::make('Datos generales')
                    ->extraAttributes(['class' => 'bexia-ppol-section bexia-ppol-section-general'])
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->extraAttributes(['class' => 'bexia-ppol-field bexia-ppol-field-company bexia-ppol-select'])
                            ->label('Empresa')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default($tenantId)
                            ->disabled(filled($tenantId))
                            ->dehydrated(),

                        Forms\Components\TextInput::make('name')
                            ->extraAttributes(['class' => 'bexia-ppol-field bexia-ppol-field-name'])
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->default('Política estándar de nómina'),

                        Forms\Components\Select::make('status')
                            ->extraAttributes(['class' => 'bexia-ppol-field bexia-ppol-field-status bexia-ppol-select'])
                            ->label('Estado')
                            ->options(PayrollPolicy::statusOptions())
                            ->required()
                            ->default('active'),

                        Forms\Components\Toggle::make('is_active')
                            ->extraAttributes(['class' => 'bexia-ppol-field bexia-ppol-field-active bexia-ppol-toggle'])
                            ->label('Política activa')
                            ->helperText('Solo puede existir una política activa por empresa.')
                            ->default(true),

                        Forms\Components\Textarea::make('notes')
                            ->extraAttributes(['class' => 'bexia-ppol-field bexia-ppol-field-notes'])
                            ->label('Notas')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Horas extra y días trabajados')
                    ->extraAttributes(['class' => 'bexia-ppol-section bexia-ppol-section-overtime'])
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('overtime_multiplier')
                            ->extraAttributes(['class' => 'bexia-ppol-field bexia-ppol-field-overtime bexia-ppol-number-field'])
                            ->label('Multiplicador horas extra')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.0001')
                            ->required()
                            ->default(2),

                        Forms\Components\TextInput::make('rest_day_overtime_multiplier')
                            ->extraAttributes(['class' => 'bexia-ppol-field bexia-ppol-field-rest-day-overtime bexia-ppol-number-field'])
                            ->label('Multiplicador descanso trabajado')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.0001')
                            ->required()
                            ->default(2),

                        Forms\Components\TextInput::make('holiday_overtime_multiplier')
                            ->extraAttributes(['class' => 'bexia-ppol-field bexia-ppol-field-holiday-overtime bexia-ppol-number-field'])
                            ->label('Multiplicador festivo trabajado')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.0001')
                            ->required()
                            ->default(2),

                        Forms\Components\Select::make('rest_day_worked_mode')
                            ->extraAttributes(['class' => 'bexia-ppol-field bexia-ppol-field-rest-day-mode bexia-ppol-select'])
                            ->label('Descanso trabajado')
                            ->options(PayrollPolicy::workedDayModeOptions())
                            ->required()
                            ->default('informational'),

                        Forms\Components\Select::make('holiday_worked_mode')
                            ->extraAttributes(['class' => 'bexia-ppol-field bexia-ppol-field-holiday-mode bexia-ppol-select'])
                            ->label('Festivo trabajado')
                            ->options(PayrollPolicy::workedDayModeOptions())
                            ->required()
                            ->default('informational'),
                    ]),

                Forms\Components\Section::make('Retardos, salidas tempranas y faltas')
                    ->extraAttributes(['class' => 'bexia-ppol-section bexia-ppol-section-attendance'])
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('late_tolerance_minutes')
                            ->extraAttributes(['class' => 'bexia-ppol-field bexia-ppol-field-late-tolerance bexia-ppol-number-field'])
                            ->label('Tolerancia adicional retardo (min)')
                            ->numeric()
                            ->minValue(0)
                            ->integer()
                            ->required()
                            ->default(0)
                            ->helperText('Se aplica sobre los minutos de retardo ya calculados por asistencia.'),

                        Forms\Components\Select::make('late_discount_mode')
                            ->extraAttributes(['class' => 'bexia-ppol-field bexia-ppol-field-late-mode bexia-ppol-select'])
                            ->label('Modo retardo')
                            ->options(PayrollPolicy::lateDiscountModeOptions())
                            ->required()
                            ->default('none'),

                        Forms\Components\TextInput::make('late_minutes_to_absence')
                            ->extraAttributes(['class' => 'bexia-ppol-field bexia-ppol-field-late-minutes-absence bexia-ppol-number-field'])
                            ->label('Minutos retardo = 1 falta')
                            ->numeric()
                            ->minValue(0)
                            ->integer()
                            ->required()
                            ->default(0),

                        Forms\Components\Select::make('early_leave_discount_mode')
                            ->extraAttributes(['class' => 'bexia-ppol-field bexia-ppol-field-early-leave-mode bexia-ppol-select'])
                            ->label('Salida temprana')
                            ->options(PayrollPolicy::earlyLeaveDiscountModeOptions())
                            ->required()
                            ->default('none'),

                        Forms\Components\Select::make('absence_discount_mode')
                            ->extraAttributes(['class' => 'bexia-ppol-field bexia-ppol-field-absence-mode bexia-ppol-select'])
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
                    ->extraHeaderAttributes(['class' => 'bexia-ppol-col-active bexia-ppol-col-icon'])
                    ->extraCellAttributes(['class' => 'bexia-ppol-col-active bexia-ppol-col-icon'])
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('company.name')
                    ->extraHeaderAttributes(['class' => 'bexia-ppol-col-company bexia-ppol-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-ppol-col-company bexia-ppol-col-primary'])
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes(['class' => 'bexia-ppol-col-name'])
                    ->extraCellAttributes(['class' => 'bexia-ppol-col-name'])
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->extraHeaderAttributes(['class' => 'bexia-ppol-col-status bexia-ppol-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-ppol-col-status bexia-ppol-col-badge'])
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => PayrollPolicy::statusOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('overtime_multiplier')
                    ->extraHeaderAttributes(['class' => 'bexia-ppol-col-overtime bexia-ppol-col-number'])
                    ->extraCellAttributes(['class' => 'bexia-ppol-col-overtime bexia-ppol-col-number'])
                    ->label('Extra')
                    ->suffix('x')
                    ->sortable(),

                Tables\Columns\TextColumn::make('late_tolerance_minutes')
                    ->extraHeaderAttributes(['class' => 'bexia-ppol-col-late-tolerance bexia-ppol-col-number'])
                    ->extraCellAttributes(['class' => 'bexia-ppol-col-late-tolerance bexia-ppol-col-number'])
                    ->label('Tol. retardo')
                    ->suffix(' min')
                    ->sortable(),

                Tables\Columns\TextColumn::make('late_discount_mode')
                    ->extraHeaderAttributes(['class' => 'bexia-ppol-col-late-mode bexia-ppol-col-mode'])
                    ->extraCellAttributes(['class' => 'bexia-ppol-col-late-mode bexia-ppol-col-mode'])
                    ->label('Retardos')
                    ->formatStateUsing(fn (?string $state): string => PayrollPolicy::lateDiscountModeOptions()[$state] ?? ($state ?: '-'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('absence_discount_mode')
                    ->extraHeaderAttributes(['class' => 'bexia-ppol-col-absence-mode bexia-ppol-col-mode'])
                    ->extraCellAttributes(['class' => 'bexia-ppol-col-absence-mode bexia-ppol-col-mode'])
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
