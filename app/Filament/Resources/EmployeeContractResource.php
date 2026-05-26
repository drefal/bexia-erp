<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeContractResource\Pages;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\HrDepartment;
use App\Models\HrJobPosition;
use App\Models\HrWorkSchedule;
use App\Models\PayrollEmployerRegistration;
use App\Models\PayrollPeriodicity;
use Filament\Facades\Filament;
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

class EmployeeContractResource extends Resource
{
    protected static ?string $model = EmployeeContract::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'RRHH';

    protected static ?string $navigationLabel = 'Contratos';

    protected static ?string $modelLabel = 'contrato laboral';

    protected static ?string $pluralModelLabel = 'contratos laborales';

    protected static ?int $navigationSort = 18;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function bexiaCanContratoPermission(string $permission): bool
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
            || $user->can('rrhh.contratos.ver')
            || $user->can('company.update');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::bexiaCanContratoPermission('rrhh.contratos.ver');
    }

    public static function canViewAny(): bool
    {
        return static::bexiaCanContratoPermission('rrhh.contratos.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanContratoPermission('rrhh.contratos.ver');
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanContratoPermission('rrhh.contratos.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanContratoPermission('rrhh.contratos.editar');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanContratoPermission('rrhh.contratos.eliminar');
    }

    public static function canDeleteAny(): bool
    {
        return static::bexiaCanContratoPermission('rrhh.contratos.eliminar');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()
            ->with([
                'employee',
                'department',
                'jobPosition',
                'workSchedule',
                'employerRegistration',
                'payrollPeriodicity',
            ])
            ->when($tenantId, fn (Builder $query) => $query->where('company_id', $tenantId));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Contrato laboral')
                    ->description('Historial contractual del empleado: vigencia, puesto, sueldo, horario y archivo firmado.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('employee_id')
                                    ->label('Empleado')
                                    ->options(fn () => self::employeeOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live(),

                                TextInput::make('contract_number')
                                    ->label('Número / folio contrato')
                                    ->maxLength(255),

                                Select::make('contract_type')
                                    ->label('Tipo de contrato')
                                    ->options(self::contractTypeOptions())
                                    ->default('indefinite')
                                    ->required(),

                                Select::make('status')
                                    ->label('Estado')
                                    ->options(self::statusOptions())
                                    ->default('draft')
                                    ->required(),

                                DatePicker::make('start_date')
                                    ->label('Fecha inicio')
                                    ->native(false)
                                    ->required(),

                                DatePicker::make('end_date')
                                    ->label('Fecha fin')
                                    ->native(false),

                                DatePicker::make('signed_at')
                                    ->label('Fecha firma')
                                    ->native(false),

                                DatePicker::make('probation_end_date')
                                    ->label('Fin periodo prueba')
                                    ->native(false),

                                Toggle::make('is_current')
                                    ->label('Contrato vigente')
                                    ->helperText('Al guardar como vigente, se desmarcarán otros contratos vigentes del empleado.'),
                            ]),
                    ]),

                Section::make('Condiciones laborales')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('hr_department_id')
                                    ->label('Departamento')
                                    ->options(fn () => self::departmentOptions())
                                    ->searchable()
                                    ->preload(),

                                Select::make('hr_job_position_id')
                                    ->label('Puesto')
                                    ->options(fn () => self::jobPositionOptions())
                                    ->searchable()
                                    ->preload(),

                                Select::make('hr_work_schedule_id')
                                    ->label('Horario laboral')
                                    ->options(fn () => self::workScheduleOptions())
                                    ->searchable()
                                    ->preload(),

                                Select::make('payroll_employer_registration_id')
                                    ->label('Registro patronal')
                                    ->options(fn () => self::employerRegistrationOptions())
                                    ->searchable()
                                    ->preload(),

                                Select::make('payroll_periodicity_id')
                                    ->label('Periodicidad nómina')
                                    ->options(fn () => self::payrollPeriodicityOptions())
                                    ->searchable()
                                    ->preload(),

                                TextInput::make('base_salary')
                                    ->label('Sueldo base')
                                    ->numeric()
                                    ->prefix('$'),

                                Select::make('salary_type')
                                    ->label('Tipo de sueldo')
                                    ->options([
                                        'monthly' => 'Mensual',
                                        'daily' => 'Diario',
                                        'hourly' => 'Por hora',
                                        'commission' => 'Comisión',
                                        'mixed' => 'Mixto',
                                    ]),

                                TextInput::make('currency')
                                    ->label('Moneda')
                                    ->default('MXN')
                                    ->maxLength(3),

                                TextInput::make('hours_per_week')
                                    ->label('Horas por semana')
                                    ->numeric(),
                            ]),
                    ]),

                Section::make('Archivo y notas')
                    ->schema([
                        FileUpload::make('file_path')
                            ->label('Contrato firmado')
                            ->disk('public')
                            ->directory('employee-contracts')
                            ->visibility('public')
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function contractTypeOptions(): array
    {
        return [
            'indefinite' => 'Tiempo indeterminado',
            'fixed_term' => 'Tiempo determinado',
            'trial' => 'Periodo de prueba',
            'training' => 'Capacitación inicial',
            'temporary' => 'Temporal',
            'internship' => 'Practicante',
            'contractor' => 'Honorarios / contratista',
            'other' => 'Otro',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'draft' => 'Borrador',
            'active' => 'Activo',
            'expired' => 'Vencido',
            'terminated' => 'Terminado',
            'renewed' => 'Renovado',
            'cancelled' => 'Cancelado',
        ];
    }

    protected static function tenantId(): ?int
    {
        return Filament::getTenant()?->getKey();
    }

    protected static function employeeOptions(): array
    {
        $tenantId = self::tenantId();

        return Employee::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function departmentOptions(): array
    {
        $tenantId = self::tenantId();

        return HrDepartment::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function jobPositionOptions(): array
    {
        $tenantId = self::tenantId();

        return HrJobPosition::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function workScheduleOptions(): array
    {
        $tenantId = self::tenantId();

        return HrWorkSchedule::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function employerRegistrationOptions(): array
    {
        $tenantId = self::tenantId();

        return PayrollEmployerRegistration::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function payrollPeriodicityOptions(): array
    {
        $tenantId = self::tenantId();

        return PayrollPeriodicity::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public static function syncCurrentContract(EmployeeContract $contract): void
    {
        if (! $contract->is_current) {
            return;
        }

        EmployeeContract::query()
            ->where('employee_id', $contract->employee_id)
            ->where('id', '!=', $contract->id)
            ->where('is_current', true)
            ->update([
                'is_current' => false,
                'updated_by_user_id' => auth()->id(),
                'updated_at' => now(),
            ]);

        $employeePayload = [
            'hr_department_id' => $contract->hr_department_id,
            'hr_job_position_id' => $contract->hr_job_position_id,
            'hr_work_schedule_id' => $contract->hr_work_schedule_id,
            'payroll_employer_registration_id' => $contract->payroll_employer_registration_id,
            'payroll_periodicity_id' => $contract->payroll_periodicity_id,
            'hire_date' => $contract->start_date,
        ];

        Employee::query()
            ->where('id', $contract->employee_id)
            ->update(array_filter($employeePayload, fn ($value) => $value !== null));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Empleado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contract_number')
                    ->label('Folio')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('contract_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => self::contractTypeOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => self::statusOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_current')
                    ->label('Vigente')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('jobPosition.name')
                    ->label('Puesto')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departamento')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('base_salary')
                    ->label('Sueldo')
                    ->money('MXN')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('file_path')
                    ->label('Archivo')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->file_path)),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(self::statusOptions()),

                SelectFilter::make('contract_type')
                    ->label('Tipo de contrato')
                    ->options(self::contractTypeOptions()),

                Filter::make('current')
                    ->label('Vigentes')
                    ->query(fn (Builder $query): Builder => $query->where('is_current', true)),

                Filter::make('expires_soon')
                    ->label('Por vencer 30 días')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('end_date')
                        ->whereDate('end_date', '>=', today())
                        ->whereDate('end_date', '<=', today()->addDays(30))),

                Filter::make('expired')
                    ->label('Vencidos')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('end_date')
                        ->whereDate('end_date', '<', today())),
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
            'index' => Pages\ListEmployeeContracts::route('/'),
            'create' => Pages\CreateEmployeeContract::route('/create'),
            'edit' => Pages\EditEmployeeContract::route('/{record}/edit'),
        ];
    }
}
