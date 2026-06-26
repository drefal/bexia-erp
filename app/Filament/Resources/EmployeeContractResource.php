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

// BEXIA_EMPLOYEE_CONTRACT_RESOURCE_RESPONSIVE_V5_79_33C
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
                    ->extraAttributes(['class' => 'bexia-employee-contract-section bexia-employee-contract-labor-section'])
                    ->description('Historial contractual del empleado: vigencia, puesto, sueldo, horario y archivo firmado.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('employee_id')
                                    ->label('Empleado')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-employee-field'])
                                    ->options(fn () => self::employeeOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live(),

                                TextInput::make('contract_number')
                                    ->label('Número / folio contrato')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-number-field'])
                                    ->maxLength(255),

                                Select::make('contract_type')
                                    ->label('Tipo de contrato')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-type-field'])
                                    ->options(self::contractTypeOptions())
                                    ->default('indefinite')
                                    ->required(),

                                Select::make('status')
                                    ->label('Estado')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-status-field'])
                                    ->options(self::statusOptions())
                                    ->default('draft')
                                    ->required(),

                                DatePicker::make('start_date')
                                    ->label('Fecha inicio')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-start-date-field'])
                                    ->native(false)
                                    ->required(),

                                DatePicker::make('end_date')
                                    ->label('Fecha fin')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-end-date-field'])
                                    ->native(false),

                                DatePicker::make('signed_at')
                                    ->label('Fecha firma')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-signed-at-field'])
                                    ->native(false),

                                DatePicker::make('probation_end_date')
                                    ->label('Fin periodo prueba')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-probation-end-field'])
                                    ->native(false),

                                Toggle::make('is_current')
                                    ->label('Contrato vigente')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-current-field'])
                                    ->helperText('Al guardar como vigente, se desmarcarán otros contratos vigentes del empleado.'),
                            ]),
                    ]),

                Section::make('Condiciones laborales')
                    ->extraAttributes(['class' => 'bexia-employee-contract-section bexia-employee-contract-work-section'])
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('hr_department_id')
                                    ->label('Departamento')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-department-field'])
                                    ->options(fn () => self::departmentOptions())
                                    ->searchable()
                                    ->preload(),

                                Select::make('hr_job_position_id')
                                    ->label('Puesto')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-job-position-field'])
                                    ->options(fn () => self::jobPositionOptions())
                                    ->searchable()
                                    ->preload(),

                                Select::make('hr_work_schedule_id')
                                    ->label('Horario laboral')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-work-schedule-field'])
                                    ->options(fn () => self::workScheduleOptions())
                                    ->searchable()
                                    ->preload(),

                                Select::make('payroll_employer_registration_id')
                                    ->label('Registro patronal')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-employer-registration-field'])
                                    ->options(fn () => self::employerRegistrationOptions())
                                    ->searchable()
                                    ->preload(),

                                Select::make('payroll_periodicity_id')
                                    ->label('Periodicidad nómina')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-periodicity-field'])
                                    ->options(fn () => self::payrollPeriodicityOptions())
                                    ->searchable()
                                    ->preload(),

                                TextInput::make('base_salary')
                                    ->label('Sueldo base')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-base-salary-field'])
                                    ->numeric()
                                    ->prefix('$'),

                                Select::make('salary_type')
                                    ->label('Tipo de sueldo')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-salary-type-field'])
                                    ->options([
                                        'monthly' => 'Mensual',
                                        'daily' => 'Diario',
                                        'hourly' => 'Por hora',
                                        'commission' => 'Comisión',
                                        'mixed' => 'Mixto',
                                    ]),

                                TextInput::make('currency')
                                    ->label('Moneda')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-currency-field'])
                                    ->default('MXN')
                                    ->maxLength(3),

                                TextInput::make('hours_per_week')
                                    ->label('Horas por semana')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-hours-field'])
                                    ->numeric(),
                            ]),
                    ]),

                Section::make('CFDI nómina SAT')
                    ->extraAttributes(['class' => 'bexia-employee-contract-section bexia-employee-contract-sat-section'])
                    ->description('Datos fiscales laborales usados para validar y preparar el complemento de nómina. No timbra.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('sat_contract_type_code')
                                    ->label('Tipo contrato SAT')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-sat-contract-type-field'])
                                    ->options([
                                        '01' => '01 - Contrato de trabajo por tiempo indeterminado',
                                        '02' => '02 - Contrato de trabajo para obra determinada',
                                        '03' => '03 - Contrato de trabajo por tiempo determinado',
                                        '04' => '04 - Contrato de trabajo por temporada',
                                        '05' => '05 - Contrato de trabajo sujeto a prueba',
                                        '06' => '06 - Contrato de trabajo con capacitación inicial',
                                        '07' => '07 - Modalidad de contratación por pago de hora laborada',
                                        '08' => '08 - Modalidad de trabajo por comisión laboral',
                                        '09' => '09 - Modalidades de contratación donde no existe relación de trabajo',
                                        '10' => '10 - Jubilación, pensión, retiro',
                                        '99' => '99 - Otro contrato',
                                    ])
                                    ->searchable()
                                    ->preload(),

                                Select::make('sat_workday_type_code')
                                    ->label('Tipo jornada SAT')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-sat-workday-field'])
                                    ->options([
                                        '01' => '01 - Diurna',
                                        '02' => '02 - Nocturna',
                                        '03' => '03 - Mixta',
                                        '04' => '04 - Por hora',
                                        '05' => '05 - Reducida',
                                        '06' => '06 - Continuada',
                                        '07' => '07 - Partida',
                                        '08' => '08 - Por turnos',
                                        '99' => '99 - Otra jornada',
                                    ])
                                    ->searchable()
                                    ->preload(),

                                Select::make('sat_regime_type_code')
                                    ->label('Tipo régimen SAT')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-sat-regime-field'])
                                    ->options([
                                        '02' => '02 - Sueldos',
                                        '03' => '03 - Jubilados',
                                        '04' => '04 - Pensionados',
                                        '05' => '05 - Asimilados miembros sociedades cooperativas',
                                        '06' => '06 - Asimilados integrantes sociedades y asociaciones civiles',
                                        '07' => '07 - Asimilados miembros consejos',
                                        '08' => '08 - Asimilados comisionistas',
                                        '09' => '09 - Asimilados honorarios',
                                        '10' => '10 - Asimilados acciones',
                                        '11' => '11 - Asimilados otros',
                                        '99' => '99 - Otro régimen',
                                    ])
                                    ->default('02')
                                    ->searchable()
                                    ->preload(),

                                Select::make('sat_risk_position_code')
                                    ->label('Riesgo puesto SAT')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-sat-risk-field'])
                                    ->options([
                                        '1' => 'Clase I',
                                        '2' => 'Clase II',
                                        '3' => 'Clase III',
                                        '4' => 'Clase IV',
                                        '5' => 'Clase V',
                                        '99' => 'No aplica',
                                    ])
                                    ->searchable()
                                    ->preload(),

                                TextInput::make('daily_salary')
                                    ->label('Salario diario')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-daily-salary-field'])
                                    ->numeric()
                                    ->prefix('$')
                                    ->helperText('Salario diario usado para CFDI nómina.'),

                                TextInput::make('integrated_daily_salary')
                                    ->label('Salario diario integrado')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-integrated-salary-field'])
                                    ->numeric()
                                    ->prefix('$')
                                    ->helperText('SDI usado para IMSS/CFDI nómina.'),

                                Toggle::make('is_unionized')
                                    ->label('Sindicalizado')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-unionized-field'])
                                    ->helperText('Dato informativo para complemento de nómina.'),
                            ]),
                    ]),

                Section::make('Archivo y notas')
                    ->extraAttributes(['class' => 'bexia-employee-contract-section bexia-employee-contract-file-section'])
                    ->schema([
                        FileUpload::make('file_path')
                            ->label('Contrato firmado')
                            ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-file-upload-field'])
                            ->disk('public')
                            ->directory('employee-contracts')
                            ->visibility('public')
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-notes-field'])
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
                    ->sortable()
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-employee-contract-col-employee'])
                    ->extraCellAttributes(['class' => 'bexia-employee-contract-col-employee']),

                Tables\Columns\TextColumn::make('contract_number')
                    ->label('Folio')
                    ->searchable()
                    ->toggleable()
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-employee-contract-col-number'])
                    ->extraCellAttributes(['class' => 'bexia-employee-contract-col-number']),

                Tables\Columns\TextColumn::make('contract_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => self::contractTypeOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable()
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-employee-contract-col-type'])
                    ->extraCellAttributes(['class' => 'bexia-employee-contract-col-type']),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => self::statusOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable()
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-employee-contract-col-status'])
                    ->extraCellAttributes(['class' => 'bexia-employee-contract-col-status']),

                Tables\Columns\IconColumn::make('is_current')
                    ->label('Vigente')
                    ->boolean()
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'bexia-employee-contract-col-current'])
                    ->extraCellAttributes(['class' => 'bexia-employee-contract-col-current']),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'bexia-employee-contract-col-start-date'])
                    ->extraCellAttributes(['class' => 'bexia-employee-contract-col-start-date']),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'bexia-employee-contract-col-end-date'])
                    ->extraCellAttributes(['class' => 'bexia-employee-contract-col-end-date']),

                Tables\Columns\TextColumn::make('jobPosition.name')
                    ->label('Puesto')
                    ->toggleable()
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-employee-contract-col-job-position'])
                    ->extraCellAttributes(['class' => 'bexia-employee-contract-col-job-position']),

                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departamento')
                    ->toggleable()
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-employee-contract-col-department'])
                    ->extraCellAttributes(['class' => 'bexia-employee-contract-col-department']),

                Tables\Columns\TextColumn::make('base_salary')
                    ->label('Sueldo')
                    ->money('MXN')
                    ->sortable()
                    ->toggleable()
                    ->extraHeaderAttributes(['class' => 'bexia-employee-contract-col-salary'])
                    ->extraCellAttributes(['class' => 'bexia-employee-contract-col-salary']),

                Tables\Columns\IconColumn::make('file_path')
                    ->label('Archivo')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->file_path))
                    ->extraHeaderAttributes(['class' => 'bexia-employee-contract-col-file'])
                    ->extraCellAttributes(['class' => 'bexia-employee-contract-col-file']),
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
