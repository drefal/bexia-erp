<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeIncidentResource\Pages;
use App\Models\Employee;
use App\Models\EmployeeIncident;
use App\Models\HrIncidentType;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmployeeIncidentResource extends Resource
{
    protected static ?string $model = EmployeeIncident::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'RRHH';

    protected static ?string $navigationLabel = 'Incidencias';

    protected static ?string $modelLabel = 'incidencia';

    protected static ?string $pluralModelLabel = 'incidencias';

    protected static ?int $navigationSort = 16;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function bexiaCanIncidenciaPermission(string $permission): bool
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
            || $user->can('rrhh.catalogos.ver')
            || $user->can('company.update');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::bexiaCanIncidenciaPermission('rrhh.incidencias.ver');
    }

    public static function canViewAny(): bool
    {
        return static::bexiaCanIncidenciaPermission('rrhh.incidencias.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanIncidenciaPermission('rrhh.incidencias.ver');
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanIncidenciaPermission('rrhh.incidencias.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanIncidenciaPermission('rrhh.incidencias.editar');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanIncidenciaPermission('rrhh.incidencias.eliminar');
    }

    public static function canDeleteAny(): bool
    {
        return static::bexiaCanIncidenciaPermission('rrhh.incidencias.eliminar');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()
            ->with(['employee', 'incidentType'])
            ->when($tenantId, fn (Builder $query) => $query->where('company_id', $tenantId));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Incidencia del empleado')
                    ->description('Registra eventos como retardos, faltas, permisos, vacaciones, incapacidad u horas extra.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('employee_id')
                                    ->label('Empleado')
                                    ->options(fn () => self::employeeOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('hr_incident_type_id')
                                    ->label('Tipo de incidencia')
                                    ->options(fn () => self::incidentTypeOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                TextInput::make('title')
                                    ->label('Título')
                                    ->required()
                                    ->maxLength(255),

                                Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'draft' => 'Borrador',
                                        'pending' => 'Pendiente',
                                        'approved' => 'Aprobada',
                                        'rejected' => 'Rechazada',
                                        'cancelled' => 'Cancelada',
                                    ])
                                    ->default('draft')
                                    ->required(),

                                DatePicker::make('start_date')
                                    ->label('Fecha inicio')
                                    ->native(false)
                                    ->required(),

                                DatePicker::make('end_date')
                                    ->label('Fecha fin')
                                    ->native(false),

                                TextInput::make('start_time')
                                    ->label('Hora inicio')
                                    ->type('time'),

                                TextInput::make('end_time')
                                    ->label('Hora fin')
                                    ->type('time'),

                                TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->helperText('Ej. 1 día, 2 horas, 15 minutos.'),

                                Select::make('quantity_unit')
                                    ->label('Unidad')
                                    ->options([
                                        'minutes' => 'Minutos',
                                        'hours' => 'Horas',
                                        'days' => 'Días',
                                        'events' => 'Eventos',
                                    ]),

                                Toggle::make('requires_approval')
                                    ->label('Requiere aprobación'),

                                Toggle::make('affects_payroll')
                                    ->label('Afecta nómina'),

                                TextInput::make('payroll_amount')
                                    ->label('Monto nómina')
                                    ->numeric()
                                    ->prefix('$'),

                                FileUpload::make('attachment_path')
                                    ->label('Soporte / evidencia')
                                    ->disk('public')
                                    ->directory('employee-incidents')
                                    ->visibility('public')
                                    ->downloadable()
                                    ->openable()
                                    ->columnSpanFull(),

                                Textarea::make('description')
                                    ->label('Descripción')
                                    ->rows(4)
                                    ->columnSpanFull(),

                                Textarea::make('resolution_notes')
                                    ->label('Notas de resolución')
                                    ->rows(3)
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

    protected static function incidentTypeOptions(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return HrIncidentType::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
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

                Tables\Columns\TextColumn::make('incidentType.name')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'Borrador',
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobada',
                        'rejected' => 'Rechazada',
                        'cancelled' => 'Cancelada',
                        default => $state ?: '-',
                    }),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quantity_unit')
                    ->label('Unidad')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('requires_approval')
                    ->label('Aprobación')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('affects_payroll')
                    ->label('Nómina')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('attachment_path')
                    ->label('Soporte')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->attachment_path)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobada',
                        'rejected' => 'Rechazada',
                        'cancelled' => 'Cancelada',
                    ]),

                SelectFilter::make('hr_incident_type_id')
                    ->label('Tipo de incidencia')
                    ->options(fn () => self::incidentTypeOptions()),

                Filter::make('current_month')
                    ->label('Mes actual')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDate('start_date', '>=', now()->startOfMonth())
                        ->whereDate('start_date', '<=', now()->endOfMonth())),

                Filter::make('affects_payroll')
                    ->label('Afecta nómina')
                    ->query(fn (Builder $query): Builder => $query->where('affects_payroll', true)),
            ])
            ->actions([
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
            'index' => Pages\ListEmployeeIncidents::route('/'),
            'create' => Pages\CreateEmployeeIncident::route('/create'),
            'edit' => Pages\EditEmployeeIncident::route('/{record}/edit'),
        ];
    }
}
