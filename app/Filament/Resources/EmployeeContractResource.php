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
use App\Support\PayrollSdiCalculator;
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

        return $user->can($permission);
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
                                    ->live()
                                    ->afterStateUpdated(function ($state, $get, $set): void {
                                        $employeeId = (int) ($state ?? 0);

                                        if ($employeeId <= 0) {
                                            $set('employee_hire_date', null);
                                            $set('integrated_daily_salary', null);
                                            return;
                                        }

                                        $employee = Employee::query()->find($employeeId);

                                        $set(
                                            'employee_hire_date',
                                            $employee?->hire_date?->format('Y-m-d')
                                        );

                                        static::calculateSdiForForm($get, $set);
                                    }),

                                DatePicker::make('employee_hire_date')
                                    ->label('Fecha de ingreso a la empresa')
                                    ->native(true)
                                    ->dehydrated(false)
                                    ->live()
                                    ->helperText('Se guarda en la ficha del empleado y se usa para antigüedad, vacaciones y cálculo del SDI.')
                                    ->afterStateHydrated(function ($state, $get, $set): void {
                                        $employeeId = (int) ($get('employee_id') ?? 0);

                                        if ($employeeId <= 0) {
                                            return;
                                        }

                                        $employee = Employee::query()->find($employeeId);

                                        if ($employee?->hire_date) {
                                            $set(
                                                'employee_hire_date',
                                                $employee->hire_date->format('Y-m-d')
                                            );

                                            static::calculateSdiForForm($get, $set);
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, $get, $set): void {
                                        $employeeId = (int) ($get('employee_id') ?? 0);

                                        if ($employeeId <= 0) {
                                            return;
                                        }

                                        $employee = Employee::query()->find($employeeId);

                                        if (! $employee) {
                                            return;
                                        }

                                        $employee->hire_date = filled($state)
                                            ? \Carbon\CarbonImmutable::parse($state)->toDateString()
                                            : null;

                                        $employee->save();

                                        static::calculateSdiForForm($get, $set);
                                    }),

                                TextInput::make('contract_number')
                                    ->label('Número / folio contrato')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-number-field'])
                                    ->maxLength(255)
                                    ->helperText('Opcional. Si se deja vacío, Bexia genera el folio automáticamente al guardar el contrato.')
                                    ->unique(
                                        table: 'employee_contracts',
                                        column: 'contract_number',
                                        ignorable: fn ($record) => $record
                                    ),

                                Select::make('contract_type')
                                    ->label('Tipo de contrato')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-type-field'])
                                    ->options(self::contractTypeOptions())
                                    ->default('indefinite')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, $get, $set): void {
                                        $satCode = match ((string) $state) {
                                            'indefinite' => '01',
                                            'fixed_term' => '03',
                                            'trial' => '05',
                                            'training' => '06',
                                            'temporary' => '04',
                                            'contractor' => '09',
                                            'internship', 'other' => '99',
                                            default => null,
                                        };

                                        if ($satCode !== null) {
                                            $set('sat_contract_type_code', $satCode);
                                        }
                                    }),

                                Select::make('status')
                                    ->label('Estado')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-status-field'])
                                    ->options(self::statusOptions())
                                    ->default('draft')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, $get, $set): void {
                                        $set(
                                            'is_current',
                                            (string) $state === 'active'
                                        );
                                    }),

                                DatePicker::make('start_date')
                                    ->label('Fecha de inicio de este contrato')
                                    ->native(true)
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-start-date-field'])
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, $get, $set): void {
                                        static::calculateSdiForForm($get, $set);
                                    }),

                                DatePicker::make('end_date')
                                    ->label('Fecha de fin de este contrato')
                                    ->native(true)
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-end-date-field']),

                                DatePicker::make('signed_at')
                                    ->label('Fecha de firma de este contrato')
                                    ->native(true)
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-signed-at-field']),

                                DatePicker::make('probation_end_date')
                                    ->label('Fin del periodo de prueba de este contrato')
                                    ->native(true)
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-probation-end-field']),

                                Toggle::make('is_current')
                                    ->label('Contrato vigente')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-current-field'])
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Se controla automáticamente: únicamente un contrato con estado Activo puede ser vigente.'),
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
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, $get, $set): void {
                                        $scheduleId = (int) ($state ?? 0);

                                        if ($scheduleId <= 0) {
                                            $set('sat_workday_type_code', null);
                                            return;
                                        }

                                        $schedule = HrWorkSchedule::query()
                                            ->find($scheduleId);

                                        if (! $schedule) {
                                            return;
                                        }

                                        $start = $schedule->start_time
                                            ? substr((string) $schedule->start_time, 0, 5)
                                            : null;

                                        $end = $schedule->end_time
                                            ? substr((string) $schedule->end_time, 0, 5)
                                            : null;

                                        $satCode = null;

                                        /*
                                         * Clasificación automática conservadora:
                                         * - horario completamente diurno: 01
                                         * - horario nocturno: 02
                                         * - cruza zonas diurna/nocturna: 03
                                         *
                                         * El campo SAT permanece editable.
                                         */
                                        if ($start && $end) {
                                            if ($start >= '06:00' && $end <= '20:00' && $end > $start) {
                                                $satCode = '01';
                                            } elseif (
                                                $start >= '20:00'
                                                || ($end <= '06:00' && $end < $start)
                                            ) {
                                                $satCode = '02';
                                            } else {
                                                $satCode = '03';
                                            }
                                        }

                                        if ($satCode !== null) {
                                            $set('sat_workday_type_code', $satCode);
                                        }

                                        if ($schedule->hours_per_week !== null) {
                                            $set(
                                                'hours_per_week',
                                                number_format(
                                                    (float) $schedule->hours_per_week,
                                                    2,
                                                    '.',
                                                    ''
                                                )
                                            );
                                        }
                                    }),

                                Select::make('payroll_employer_registration_id')
                                    ->label('Registro patronal')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-employer-registration-field'])
                                    ->options(fn () => self::employerRegistrationOptions())
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, $get, $set): void {
                                        $registrationId = (int) ($state ?? 0);

                                        if ($registrationId <= 0) {
                                            $set('sat_risk_position_code', null);
                                            return;
                                        }

                                        $registration = PayrollEmployerRegistration::query()
                                            ->find($registrationId);

                                        $riskCode = match ((string) ($registration?->risk_class ?? '')) {
                                            'I' => '1',
                                            'II' => '2',
                                            'III' => '3',
                                            'IV' => '4',
                                            'V' => '5',
                                            default => null,
                                        };

                                        if ($riskCode !== null) {
                                            $set('sat_risk_position_code', $riskCode);
                                        }
                                    }),

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
                                    ->prefix('$')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $get, $set): void {
                                        $baseSalary = (float) ($state ?? 0);
                                        $salaryType = (string) ($get('salary_type') ?? '');

                                        $dailySalary = match ($salaryType) {
                                            'weekly' => $baseSalary > 0 ? round($baseSalary / 7, 2) : null,
                                            'monthly' => $baseSalary > 0 ? round($baseSalary / 30, 2) : null,
                                            'daily' => $baseSalary > 0 ? round($baseSalary, 2) : null,
                                            default => null,
                                        };

                                        if ($dailySalary !== null) {
                                            $set('daily_salary', number_format($dailySalary, 2, '.', ''));
                                            static::calculateSdiForForm($get, $set);
                                        }
                                    }),

                                Select::make('payment_scheme')
                                    ->label('Esquema de pago')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-payment-scheme-field'])
                                    ->options([
                                        'fixed' => 'Sueldo fijo',
                                        'piecework' => 'Destajo',
                                        'mixed' => 'Mixto',
                                    ])
                                    ->default('fixed')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, $get, $set): void {
                                        static::calculateSdiForForm($get, $set);
                                    })
                                    ->helperText('Indica si el empleado se paga por sueldo fijo, destajo o una combinación de ambos.'),

                                Select::make('salary_type')
                                    ->label('Modalidad del sueldo')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-salary-type-field'])
                                    ->options([
                                        'weekly' => 'Semanal',
                                        'monthly' => 'Mensual',
                                        'daily' => 'Diario',
                                        'hourly' => 'Por hora',
                                        'commission' => 'Comisión',
                                        'mixed' => 'Mixto',
                                    ])
                                    ->live()
                                    ->afterStateUpdated(function ($state, $get, $set): void {
                                        $baseSalary = (float) ($get('base_salary') ?? 0);
                                        $salaryType = (string) ($state ?? '');

                                        $dailySalary = match ($salaryType) {
                                            'weekly' => $baseSalary > 0 ? round($baseSalary / 7, 2) : null,
                                            'monthly' => $baseSalary > 0 ? round($baseSalary / 30, 2) : null,
                                            'daily' => $baseSalary > 0 ? round($baseSalary, 2) : null,
                                            default => null,
                                        };

                                        if ($dailySalary !== null) {
                                            $set('daily_salary', number_format($dailySalary, 2, '.', ''));
                                            static::calculateSdiForForm($get, $set);
                                        }
                                    })
                                    ->helperText('Indica cómo está expresado el importe capturado en Sueldo base.'),

                                TextInput::make('currency')
                                    ->label('Moneda')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-currency-field'])
                                    ->default('MXN')
                                    ->maxLength(3),

                                TextInput::make('hours_per_week')
                                    ->label('Horas por semana')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-hours-field'])
                                    ->numeric()
                                    ->step('0.01')
                                    ->formatStateUsing(
                                        fn ($state) => filled($state)
                                            ? number_format((float) $state, 2, '.', '')
                                            : null
                                    )
                                    ->helperText('Se completa automáticamente desde el horario laboral. Puede ajustarse manualmente si este contrato lo requiere.'),
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

                                Select::make('sat_work_state_code')
                                    ->label('Entidad federativa donde labora SAT')
                                    ->extraAttributes([
                                        'class' => 'bexia-employee-contract-field bexia-employee-contract-sat-work-state-field',
                                    ])
                                    ->options([
                                        'AGU' => 'AGU - Aguascalientes',
                                        'BCN' => 'BCN - Baja California',
                                        'BCS' => 'BCS - Baja California Sur',
                                        'CAM' => 'CAM - Campeche',
                                        'CHH' => 'CHH - Chihuahua',
                                        'CHP' => 'CHP - Chiapas',
                                        'CMX' => 'CMX - Ciudad de México',
                                        'COA' => 'COA - Coahuila',
                                        'COL' => 'COL - Colima',
                                        'DUR' => 'DUR - Durango',
                                        'GRO' => 'GRO - Guerrero',
                                        'GUA' => 'GUA - Guanajuato',
                                        'HID' => 'HID - Hidalgo',
                                        'JAL' => 'JAL - Jalisco',
                                        'MEX' => 'MEX - Estado de México',
                                        'MIC' => 'MIC - Michoacán',
                                        'MOR' => 'MOR - Morelos',
                                        'NAY' => 'NAY - Nayarit',
                                        'NLE' => 'NLE - Nuevo León',
                                        'OAX' => 'OAX - Oaxaca',
                                        'PUE' => 'PUE - Puebla',
                                        'QUE' => 'QUE - Querétaro',
                                        'ROO' => 'ROO - Quintana Roo',
                                        'SIN' => 'SIN - Sinaloa',
                                        'SLP' => 'SLP - San Luis Potosí',
                                        'SON' => 'SON - Sonora',
                                        'TAB' => 'TAB - Tabasco',
                                        'TAM' => 'TAM - Tamaulipas',
                                        'TLA' => 'TLA - Tlaxcala',
                                        'VER' => 'VER - Veracruz',
                                        'YUC' => 'YUC - Yucatán',
                                        'ZAC' => 'ZAC - Zacatecas',
                                    ])
                                    ->searchable()
                                    ->preload()
                                    ->helperText(
                                        'Entidad federativa donde el trabajador presta sus servicios. '
                                        . 'No es necesariamente su domicilio, INE ni domicilio fiscal.'
                                    ),


                                TextInput::make('daily_salary')
                                    ->label('Salario diario')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-daily-salary-field'])
                                    ->numeric()
                                    ->step('0.01')
                                    ->formatStateUsing(fn ($state) => filled($state) ? number_format((float) $state, 2, '.', '') : null)
                                    ->prefix('$')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $get, $set): void {
                                        static::calculateSdiForForm($get, $set);
                                    })
                                    ->helperText('Se calcula automáticamente para sueldo semanal, mensual o diario. Puede ajustarse manualmente si es necesario.'),

                                TextInput::make('integrated_daily_salary')
                                    ->label('Salario diario integrado')
                                    ->extraAttributes(['class' => 'bexia-employee-contract-field bexia-employee-contract-integrated-salary-field'])
                                    ->numeric()
                                    ->step('0.0001')
                                    ->formatStateUsing(fn ($state) => filled($state) ? number_format((float) $state, 4, '.', '') : null)
                                    ->prefix('$')
                                    ->helperText('Para sueldo fijo se calcula con salario diario, antigüedad, aguinaldo y prima vacacional. Puede ajustarse manualmente.'),

                                /*
                                 * BEXIA_V5836_EXPLICIT_SBC_FIELD
                                 *
                                 * No copiar automáticamente el SDI:
                                 * debe capturarse el SBC real con el
                                 * que cotiza el trabajador.
                                 */
                                TextInput::make(
                                    'social_security_base_salary'
                                )
                                    ->label(
                                        'Salario base de cotización (SBC)'
                                    )
                                    ->numeric()
                                    ->step('0.0001')
                                    ->formatStateUsing(
                                        fn ($state) =>
                                            filled($state)
                                                ? number_format(
                                                    (float) $state,
                                                    4,
                                                    '.',
                                                    ''
                                                )
                                                : null
                                    )
                                    ->prefix('$')
                                    ->helperText(
                                        'SBC diario real usado para cuotas '
                                        . 'y aportaciones de seguridad social. '
                                        . 'No se llena automáticamente con el '
                                        . 'salario diario ni con el SDI.'
                                    ),

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

    protected static function calculateSdiForForm($get, $set): void
    {
        $paymentScheme = (string) ($get('payment_scheme') ?? 'fixed');

        if ($paymentScheme !== 'fixed') {
            return;
        }

        $employeeId = (int) ($get('employee_id') ?? 0);
        $dailySalary = (float) ($get('daily_salary') ?? 0);

        if ($employeeId <= 0 || $dailySalary <= 0) {
            return;
        }

        $employee = Employee::query()->find($employeeId);

        if (! $employee) {
            return;
        }

        $formHireDate = $get('employee_hire_date');

        if (filled($formHireDate)) {
            $hireDate = \Carbon\CarbonImmutable::parse($formHireDate);

            /*
             * Para el cálculo inmediato usamos la fecha visible en el
             * formulario, sin depender de una lectura posterior de BD.
             */
            $employee->setAttribute('hire_date', $hireDate);

            /*
             * La fecha también se conserva como dato maestro del empleado.
             */
            $storedHireDate = $employee->getOriginal('hire_date');

            if (
                blank($storedHireDate)
                || \Carbon\CarbonImmutable::parse($storedHireDate)->toDateString()
                    !== $hireDate->toDateString()
            ) {
                Employee::query()
                    ->whereKey($employee->getKey())
                    ->update([
                        'hire_date' => $hireDate->toDateString(),
                    ]);
            }
        }

        $asOf = filled($get('start_date'))
            ? \Carbon\CarbonImmutable::parse($get('start_date'))
            : null;

        $result = PayrollSdiCalculator::calculate(
            $employee,
            $dailySalary,
            (int) $employee->company_id,
            $asOf
        );

        if (! ($result['automatic_sdi'] ?? true)) {
            return;
        }

        if (! ($result['can_calculate'] ?? false)) {
            $set('integrated_daily_salary', null);
            return;
        }

        $set(
            'integrated_daily_salary',
            number_format(
                (float) $result['integrated_daily_salary'],
                4,
                '.',
                ''
            )
        );
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
            ->with('employerCompany')
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->where('state', 'vigente')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (PayrollEmployerRegistration $registration) => [
                $registration->id => $registration->displayLabel(),
            ])
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
        /*
         * La vigencia depende exclusivamente del estado.
         * Solo un contrato Activo puede ser el contrato vigente.
         */
        $shouldBeCurrent = $contract->status === 'active';

        if ((bool) $contract->is_current !== $shouldBeCurrent) {
            $contract->forceFill([
                'is_current' => $shouldBeCurrent,
                'updated_by_user_id' => auth()->id(),
            ])->saveQuietly();

            $contract->refresh();
        }

        if (! $shouldBeCurrent) {
            return;
        }

        EmployeeContract::query()
            ->where('company_id', $contract->company_id)
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
