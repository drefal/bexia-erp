<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeVacationBalanceResource\Pages;
use App\Models\Employee;
use App\Models\EmployeeVacationBalance;
use App\Support\EmployeeVacationBalanceCalculator;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmployeeVacationBalanceResource extends Resource
{
    protected static ?string $model = EmployeeVacationBalance::class;

    protected static ?string $navigationIcon = 'heroicon-o-sun';

    protected static ?string $navigationGroup = 'RRHH';

    protected static ?string $navigationLabel = 'Vacaciones / Saldos';

    protected static ?string $modelLabel = 'saldo de vacaciones';

    protected static ?string $pluralModelLabel = 'saldos de vacaciones';

    protected static ?int $navigationSort = 17;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function bexiaCanVacacionesPermission(string $permission): bool
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

        return $user->can($permission)
            || $user->can('rrhh.incidencias.ver')
            || $user->can('company.update');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::bexiaCanVacacionesPermission('rrhh.vacaciones.ver');
    }

    public static function canViewAny(): bool
    {
        return static::bexiaCanVacacionesPermission('rrhh.vacaciones.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanVacacionesPermission('rrhh.vacaciones.ver');
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanVacacionesPermission('rrhh.vacaciones.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanVacacionesPermission('rrhh.vacaciones.editar');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanVacacionesPermission('rrhh.vacaciones.eliminar');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()
            ->with(['employee'])
            ->when($tenantId, fn (Builder $query) => $query->where('company_id', $tenantId));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Saldo de vacaciones')
                    ->description('Control anual de días asignados, tomados y disponibles.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('employee_id')
                                    ->label('Empleado')
                                    ->options(fn () => self::employeeOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'open' => 'Abierto',
                                        'closed' => 'Cerrado',
                                        'expired' => 'Vencido',
                                    ])
                                    ->default('open')
                                    ->required(),

                                DatePicker::make('period_start')
                                    ->label('Inicio del periodo')
                                    ->native(false)
                                    ->required(),

                                DatePicker::make('period_end')
                                    ->label('Fin del periodo')
                                    ->native(false)
                                    ->required(),

                                TextInput::make('years_of_service')
                                    ->label('Años de servicio')
                                    ->numeric()
                                    ->default(0),

                                TextInput::make('entitled_days')
                                    ->label('Días asignados')
                                    ->numeric()
                                    ->default(0),

                                TextInput::make('carried_over_days')
                                    ->label('Días arrastrados')
                                    ->numeric()
                                    ->default(0),

                                TextInput::make('adjusted_days')
                                    ->label('Ajustes')
                                    ->numeric()
                                    ->default(0),

                                TextInput::make('taken_days')
                                    ->label('Días tomados')
                                    ->numeric()
                                    ->default(0),

                                TextInput::make('pending_days')
                                    ->label('Días disponibles')
                                    ->numeric()
                                    ->default(0),

                                TextInput::make('expired_days')
                                    ->label('Días vencidos')
                                    ->numeric()
                                    ->default(0),

                                TextInput::make('policy_code')
                                    ->label('Política')
                                    ->default(EmployeeVacationBalanceCalculator::POLICY_MX_LFT_2023)
                                    ->maxLength(60),

                                Textarea::make('notes')
                                    ->label('Notas')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    protected static function employeeOptions(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return Employee::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Empleado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_start')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_end')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('years_of_service')
                    ->label('Antigüedad')
                    ->sortable(),

                Tables\Columns\TextColumn::make('entitled_days')
                    ->label('Asignados')
                    ->numeric(2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('taken_days')
                    ->label('Tomados')
                    ->numeric(2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('pending_days')
                    ->label('Disponibles')
                    ->numeric(2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'open' => 'Abierto',
                        'closed' => 'Cerrado',
                        'expired' => 'Vencido',
                        default => $state ?: '-',
                    }),

                Tables\Columns\TextColumn::make('policy_code')
                    ->label('Política')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'open' => 'Abierto',
                        'closed' => 'Cerrado',
                        'expired' => 'Vencido',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('recalculate')
                    ->label('Recalcular')
                    ->icon('heroicon-o-calculator')
                    ->requiresConfirmation()
                    ->action(function (EmployeeVacationBalance $record): void {
                        try {
                            EmployeeVacationBalanceCalculator::generateCurrentBalance($record->employee, auth()->id());

                            Notification::make()
                                ->title('Saldo recalculado')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo recalcular')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Eliminar seleccionados'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeVacationBalances::route('/'),
            'create' => Pages\CreateEmployeeVacationBalance::route('/create'),
            'edit' => Pages\EditEmployeeVacationBalance::route('/{record}/edit'),
        ];
    }
}
