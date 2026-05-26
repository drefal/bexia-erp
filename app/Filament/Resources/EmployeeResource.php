<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\PayrollPeriodicity;
use App\Models\PayrollEmployerRegistration;
use App\Models\HrWorkSchedule;
use App\Models\HrJobPosition;
use App\Models\HrDepartment;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $pluralModelLabel = 'empleados';

    protected static ?string $modelLabel = 'empleado';

    protected static ?string $navigationLabel = 'Empleados';
    protected static bool $isScopedToTenant = false;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'RRHH';
    protected static ?int $navigationSort = 10;
    protected static ?string $tenantOwnershipRelationshipName = null;

public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->can('company.update');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->check() && auth()->user()->can('company.update');
    }

    public static function canDeleteAny(): bool
    {
        return auth()->check() && auth()->user()->can('company.update');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->check() && auth()->user()->can('company.update');
    }

    public static function getNavigationLabel(): string
    {
        return 'Empleados';
    }

    public static function getModelLabel(): string
    {
        return 'Empleado';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Empleados';
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()
            ->with(['user', 'manager', 'coach', 'branch', 'hrDepartment', 'hrJobPosition', 'hrWorkSchedule', 'payrollPeriodicity', 'payrollEmployerRegistration'])
            ->when($tenantId, fn (Builder $query) => $query->where('company_id', $tenantId));
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('contacts.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('contacts.view')
            );
    }

    public static function form(Form $form): Form
    {
        return $form->schema([

                Forms\Components\Section::make('Accesos operativos / PDV')
                    ->description('Define si el empleado puede operar como cajero o vendedor en PDV. Los permisos específicos por caja se asignan en Punto de Venta > Personal de cajas.')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Toggle::make('pos_active')
                            ->label('Activo en PDV')
                            ->default(true),

                        Forms\Components\Toggle::make('is_pos_cashier')
                            ->label('Es cajero PDV')
                            ->helperText('Habilita al empleado como candidato para cobrar en cajas donde tenga permiso.'),

                        Forms\Components\Toggle::make('is_pos_seller')
                            ->label('Es vendedor PDV')
                            ->helperText('Habilita al empleado como candidato para generar tickets en cajas donde tenga permiso.'),

                        Forms\Components\TextInput::make('plain_pos_pin')
                            ->label('PIN / clave PDV')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('Déjalo vacío para conservar la clave actual. Si el empleado no tiene clave, puede entrar directo según la configuración.'),
                    ]),

            Grid::make(12)
                ->schema([
                    Section::make('Ficha laboral')
                        ->description('Datos principales del empleado dentro de la empresa actual.')
                        ->schema([
                            Grid::make(12)
                                ->schema([
                                    FileUpload::make('avatar_path')
                                        ->label('Foto')
                                        ->image()
                                        ->disk('public')
                                        ->directory('employees/avatars')
                                        ->visibility('public')
                                        ->imagePreviewHeight('140')
                                        ->columnSpan(2),

                                    Grid::make(10)
                                        ->schema([
                                            TextInput::make('name')
                                                ->label('Nombre')
                                                ->required()
                                                ->maxLength(255),

                                            Select::make('hr_job_position_id')
                                                ->label('Puesto (catálogo RRHH)')
                                                ->options(fn () => self::hrJobPositionOptions())
                                                ->searchable()
                                                ->preload()
                                                ->helperText('Catálogo activo de puestos de la empresa actual.'),

                                            TextInput::make('position')
                                                ->label('Puesto libre / legado')
                                                ->maxLength(255)
                                                ->helperText('Campo anterior. Úsalo solo si el puesto no está en catálogo.'),

                                            TextInput::make('phone')
                                                ->label('Teléfono de trabajo')
                                                ->maxLength(50),

                                            TextInput::make('work_mobile')
                                                ->label('Teléfono laboral')
                                                ->maxLength(50),

                                            TextInput::make('email')
                                                ->label('Correo de trabajo')
                                                ->email()
                                                ->maxLength(255),

                                            Select::make('hr_department_id')
                                                ->label('Departamento (catálogo RRHH)')
                                                ->options(fn () => self::hrDepartmentOptions())
                                                ->searchable()
                                                ->preload()
                                                ->helperText('Catálogo activo de departamentos de la empresa actual.'),

                                            TextInput::make('department')
                                                ->label('Departamento libre / legado')
                                                ->maxLength(255)
                                                ->helperText('Campo anterior. Úsalo solo si el departamento no está en catálogo.'),

                                            Select::make('manager_employee_id')
                                                ->label('Gerente')
                                                ->options(fn () => self::employeeOptions())
                                                ->searchable()
                                                ->preload(),

                                            Select::make('coach_employee_id')
                                                ->label('Instructor')
                                                ->options(fn () => self::employeeOptions())
                                                ->searchable()
                                                ->preload(),

                                            TextInput::make('ssn')
                                                ->label('Número de seguro social')
                                                ->maxLength(255),

                                            TextInput::make('curp')
                                                ->label('Clave CURP')
                                                ->maxLength(255),

                                            TextInput::make('rfc')
                                                ->label('Clave RFC')
                                                ->maxLength(255),

                                            Placeholder::make('empresa_actual')
                                                ->label('Empresa')
                                                ->content(fn () => Filament::getTenant()?->name ?? '-'),
                                        ])
                                        ->columns(2)
                                        ->columnSpan(10),
                                ]),
                        ])
                        ->columnSpanFull(),

                    Tabs::make('EmpleadoTabs')
                        ->tabs([
                            Tabs\Tab::make('Trabajo y horario')
                                ->schema([
                                    Section::make('Ubicación')
                                        ->schema([
                                            Textarea::make('work_address')
                                                ->label('Dirección laboral')
                                                ->rows(4)
                                                ->columnSpanFull(),

                                            Select::make('branch_id')
                                                ->label('Ubicación de trabajo')
                                                ->options(fn () => self::branchOptions())
                                                ->searchable()
                                                ->preload(),
                                        ])
                                        ->columns(2),

                                    Section::make('Programar')
                                        ->schema([
                                            Select::make('hr_work_schedule_id')
                                                ->label('Horario laboral (catálogo RRHH)')
                                                ->options(fn () => self::hrWorkScheduleOptions())
                                                ->searchable()
                                                ->preload(),

                                            Toggle::make('flexible_hours')
                                                ->label('Horas flexibles')
                                                ->default(false),

                                            TextInput::make('working_schedule')
                                                ->label('Horas laborables / legado')
                                                ->placeholder('Ej. Estándar de 40 horas a la semana')
                                                ->maxLength(255),

                                            TextInput::make('work_timezone')
                                                ->label('Zona horaria')
                                                ->default('America/Mexico_City')
                                                ->maxLength(255),
                                        ])
                                        ->columns(2),
                                ]),

                            Tabs\Tab::make('Insignias recibidas')
                                ->schema([
                                    Placeholder::make('badges_placeholder')
                                        ->label('Insignias')
                                        ->content('Módulo pendiente. Aquí se podrán mostrar insignias y reconocimientos del empleado.'),
                                ]),

                            Tabs\Tab::make('Datos personales')
                                ->schema([
                                    Section::make('Contacto privado')
                                        ->schema([
                                            Textarea::make('private_address')
                                                ->label('Dirección')
                                                ->rows(3)
                                                ->columnSpanFull(),

                                            TextInput::make('private_email')
                                                ->label('Correo electrónico')
                                                ->email()
                                                ->maxLength(255),

                                            TextInput::make('private_phone')
                                                ->label('Teléfono')
                                                ->maxLength(50),

                                            TextInput::make('bank_account')
                                                ->label('Número de cuenta bancaria')
                                                ->maxLength(255),

                                            TextInput::make('language')
                                                ->label('Idioma')
                                                ->maxLength(100),

                                            TextInput::make('distance_home_work')
                                                ->label('Distancia casa-trabajo')
                                                ->numeric()
                                                ->suffix('Km'),
                                        ])
                                        ->columns(2),

                                    Section::make('Estado familiar')
                                        ->schema([
                                            Select::make('marital_status')
                                                ->label('Estado civil')
                                                ->options([
                                                    'single' => 'Soltero(a)',
                                                    'married' => 'Casado(a)',
                                                    'divorced' => 'Divorciado(a)',
                                                    'widowed' => 'Viudo(a)',
                                                    'other' => 'Otro',
                                                ]),

                                            TextInput::make('dependent_children')
                                                ->label('Número de hijos dependientes')
                                                ->numeric()
                                                ->default(0),
                                        ])
                                        ->columns(2),

                                    Section::make('Emergencia')
                                        ->schema([
                                            TextInput::make('emergency_contact_name')
                                                ->label('Nombre del contacto')
                                                ->maxLength(255),

                                            TextInput::make('emergency_contact_phone')
                                                ->label('Teléfono del contacto')
                                                ->maxLength(50),
                                        ])
                                        ->columns(2),

                                    Section::make('Ciudadanía')
                                        ->schema([
                                            TextInput::make('nationality')
                                                ->label('Nacionalidad (país)')
                                                ->maxLength(255),

                                            TextInput::make('identification_number')
                                                ->label('Número de identificación')
                                                ->maxLength(255),

                                            TextInput::make('passport_number')
                                                ->label('Número de pasaporte')
                                                ->maxLength(255),

                                            Select::make('gender')
                                                ->label('Género')
                                                ->options([
                                                    'female' => 'Femenino',
                                                    'male' => 'Masculino',
                                                    'other' => 'Otro',
                                                    'prefer_not' => 'Prefiero no decir',
                                                ]),

                                            DatePicker::make('birth_date')
                                                ->label('Fecha de nacimiento')
                                                ->native(false),

                                            TextInput::make('birth_place')
                                                ->label('Lugar de nacimiento')
                                                ->maxLength(255),

                                            TextInput::make('birth_country')
                                                ->label('País de nacimiento')
                                                ->maxLength(255),
                                        ])
                                        ->columns(2),

                                    Section::make('Educación')
                                        ->schema([
                                            Select::make('certificate_level')
                                                ->label('Nivel de certificado')
                                                ->options([
                                                    'basic' => 'Básico',
                                                    'high_school' => 'Preparatoria',
                                                    'technical' => 'Técnico',
                                                    'bachelor' => 'Licenciatura',
                                                    'master' => 'Maestría',
                                                    'doctorate' => 'Doctorado',
                                                    'other' => 'Otro',
                                                ]),

                                            TextInput::make('study_field')
                                                ->label('Campo de estudio')
                                                ->maxLength(255),

                                            TextInput::make('school')
                                                ->label('Escuela')
                                                ->maxLength(255),
                                        ])
                                        ->columns(2),

                                    Section::make('Permiso de trabajo')
                                        ->schema([
                                            TextInput::make('visa_number')
                                                ->label('Número de visa')
                                                ->maxLength(255),

                                            TextInput::make('work_permit_number')
                                                ->label('Número de permiso de trabajo')
                                                ->maxLength(255),

                                            DatePicker::make('visa_expiration_date')
                                                ->label('Visa Expiration Date')
                                                ->native(false),

                                            DatePicker::make('work_permit_expiration_date')
                                                ->label('Fecha de expiración del permiso de trabajo')
                                                ->native(false),

                                            FileUpload::make('work_permit_file')
                                                ->label('Permiso de trabajo')
                                                ->disk('public')
                                                ->directory('employees/work-permits')
                                                ->visibility('public')
                                                ->columnSpanFull(),
                                        ])
                                        ->columns(2),
                                ]),

                            Tabs\Tab::make('Contrato y nómina')
                                ->schema([
                                    Section::make('Relación laboral')
                                        ->description('Clasificación del empleado, relación con usuario del sistema y parámetros de nómina.')
                                        ->schema([
                                            Select::make('employee_type')
                                                ->label('Tipo de empleado')
                                                ->options([
                                                    'employee' => 'Empleado',
                                                    'contractor' => 'Contratista',
                                                    'intern' => 'Practicante',
                                                    'temporary' => 'Temporal',
                                                ])
                                                ->default('employee'),

                                            Select::make('payroll_periodicity_id')
                                                ->label('Periodicidad de nómina')
                                                ->options(fn () => self::payrollPeriodicityOptions())
                                                ->searchable()
                                                ->preload(),

                                            Select::make('payroll_employer_registration_id')
                                                ->label('Registro patronal')
                                                ->options(fn () => self::payrollEmployerRegistrationOptions())
                                                ->searchable()
                                                ->preload()
                                                ->helperText('Para demo puedes usar DEMO-0000000. Para nómina real captura el registro patronal oficial del IMSS.'),

                                            Select::make('user_id')
                                                ->label('Usuario relacionado')
                                                ->options(fn () => self::userOptions())
                                                ->searchable()
                                                ->preload(),

                                            Toggle::make('active')
                                                ->label('Activo')
                                                ->default(true),
                                        ])
                                        ->columns(2),

                                    Section::make('Control interno')
                                        ->schema([
                                            TextInput::make('hourly_cost')
                                                ->label('Costo por hora')
                                                ->numeric()
                                                ->prefix('$'),

                                            TextInput::make('fleet_card')
                                                ->label('Tarjeta de movilidad de flota')
                                                ->maxLength(255),

                                            TextInput::make('employee_number')
                                                ->label('Número de empleado')
                                                ->maxLength(100),

                                            Textarea::make('notes')
                                                ->label('Notas')
                                                ->rows(4)
                                                ->columnSpanFull(),
                                        ])
                                        ->columns(2),
                                ]),

                            Tabs\Tab::make('Punto de venta')
                                ->schema([
                                    Section::make('Asistencia / Punto de venta')
                                        ->schema([
                                            TextInput::make('pin_code')
                                                ->label('Código NIP')
                                                ->maxLength(100),

                                            TextInput::make('badge_id')
                                                ->label('ID de credencial')
                                                ->maxLength(100),
                                        ])
                                        ->columns(2),
                                ]),
                        ])
                        ->columnSpanFull(),
                ]),
        ]);
    }

    protected static function employeeOptions(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return Employee::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function userOptions(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return User::query()
            ->when($tenantId, function ($query) use ($tenantId) {
                $query->whereHas('companies', fn ($q) => $q->where('companies.id', $tenantId));
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function branchOptions(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return Branch::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }


    /*
     * V5.64.2c-start
     * Opciones de catalogos RRHH/Nomina por tenant.
     */
    protected static function hrDepartmentOptions(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return HrDepartment::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function hrJobPositionOptions(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return HrJobPosition::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function hrWorkScheduleOptions(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return HrWorkSchedule::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function payrollPeriodicityOptions(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return PayrollPeriodicity::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function payrollEmployerRegistrationOptions(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return PayrollEmployerRegistration::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
    /*
     * V5.64.2c-end
     */

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\IconColumn::make('pos_active')
                    ->label('PDV')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_pos_cashier')
                    ->label('Cajero PDV')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_pos_seller')
                    ->label('Vendedor PDV')
                    ->boolean()
                    ->toggleable(),


                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('hrJobPosition.name')
                    ->label('Puesto RRHH')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('hrDepartment.name')
                    ->label('Departamento RRHH')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('hrWorkSchedule.name')
                    ->label('Horario')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('payrollPeriodicity.name')
                    ->label('Periodicidad')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('payrollEmployerRegistration.name')
                    ->label('Registro patronal')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('position')
                    ->label('Puesto legado')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('department')
                    ->label('Departamento legado')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Ubicación')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('manager.name')
                    ->label('Gerente')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo de trabajo')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
