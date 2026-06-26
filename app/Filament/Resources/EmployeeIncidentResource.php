<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeIncidentResource\Pages;
use App\Models\Employee;
use App\Models\EmployeeIncident;
use App\Models\HrIncidentType;
use App\Support\EmployeeIncidentApprovalWorkflow;
use App\Support\EmployeeVacationBalanceCalculator;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

// BEXIA_EMPLOYEE_INCIDENT_RESOURCE_RESPONSIVE_V5_79_30C
class EmployeeIncidentResource extends Resource
{
    protected static ?string $model = EmployeeIncident::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'RRHH';

    protected static ?string $navigationLabel = 'Incidencias';

    protected static ?string $modelLabel = 'incidencia';

    protected static ?string $pluralModelLabel = 'incidencias';

    protected static ?int $navigationSort = 21;

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
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
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
                    ->extraAttributes(['class' => 'bexia-employee-incident-section bexia-employee-incident-main-section'])
                    ->description('Registra eventos como retardos, faltas, permisos, vacaciones, incapacidad u horas extra.')
                    ->schema([
                        Grid::make(2)
                            ->extraAttributes(['class' => 'bexia-employee-incident-form-grid'])
                            ->schema([
                                Select::make('employee_id')
                                    ->label('Empleado')
                                    ->extraAttributes(['class' => 'bexia-employee-incident-field bexia-employee-incident-employee-field'])
                                    ->live()
                                    ->options(fn () => self::employeeOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('hr_incident_type_id')
                                    ->label('Tipo de incidencia')
                                    ->extraAttributes(['class' => 'bexia-employee-incident-field bexia-employee-incident-type-field'])
                                    ->live()
                                    ->options(fn () => self::incidentTypeOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                TextInput::make('title')
                                    ->label('Título')
                                    ->extraAttributes(['class' => 'bexia-employee-incident-field bexia-employee-incident-title-field'])
                                    ->required()
                                    ->maxLength(255),

                                Select::make('status')
                                    ->label('Estado')
                                    ->extraAttributes(['class' => 'bexia-employee-incident-field bexia-employee-incident-status-field'])
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
                                    ->extraAttributes(['class' => 'bexia-employee-incident-field bexia-employee-incident-start-date-field'])
                                    ->live()
                                    ->native(false)
                                    ->required(),

                                DatePicker::make('end_date')
                                    ->label('Fecha fin')
                                    ->extraAttributes(['class' => 'bexia-employee-incident-field bexia-employee-incident-end-date-field'])
                                    ->live()
                                    ->native(false),

                                TextInput::make('start_time')
                                    ->label('Hora inicio')
                                    ->extraAttributes(['class' => 'bexia-employee-incident-field bexia-employee-incident-start-time-field'])
                                    ->type('time'),

                                TextInput::make('end_time')
                                    ->label('Hora fin')
                                    ->extraAttributes(['class' => 'bexia-employee-incident-field bexia-employee-incident-end-time-field'])
                                    ->type('time'),

                                TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->extraAttributes(['class' => 'bexia-employee-incident-field bexia-employee-incident-quantity-field'])
                                    ->live(debounce: 500)
                                    ->numeric()
                                    ->helperText('Ej. 1 día, 2 horas, 15 minutos.'),

                                Select::make('quantity_unit')
                                    ->label('Unidad')
                                    ->extraAttributes(['class' => 'bexia-employee-incident-field bexia-employee-incident-quantity-unit-field'])
                                    ->live()
                                    ->options([
                                        'minutes' => 'Minutos',
                                        'hours' => 'Horas',
                                        'days' => 'Días',
                                        'events' => 'Eventos',
                                    ]),

                                Placeholder::make('vacation_balance_summary')
                                    ->label('Resumen de vacaciones')
                                    ->extraAttributes(['class' => 'bexia-employee-incident-vacation-wrapper'])
                                    ->content(fn (Get $get): HtmlString => self::vacationBalanceSummary(
                                        $get('employee_id'),
                                        $get('hr_incident_type_id'),
                                        $get('quantity'),
                                        $get('quantity_unit'),
                                        $get('start_date'),
                                        $get('end_date'),
                                    ))
                                    ->visible(fn (Get $get): bool => self::isVacationTypeId($get('hr_incident_type_id')))
                                    ->columnSpanFull(),

                                Toggle::make('requires_approval')
                                    ->label('Requiere aprobación')
                                    ->visible(false)
                                    ->dehydrated(false),

                                Toggle::make('affects_payroll')
                                    ->label('Afecta nómina')
                                    ->visible(false)
                                    ->dehydrated(false),

                                TextInput::make('payroll_amount')
                                    ->label('Monto nómina')
                                    ->visible(false)
                                    ->dehydrated(false)
                                    ->numeric()
                                    ->prefix('$'),

                                FileUpload::make('attachment_path')
                                    ->label('Soporte / evidencia')
                                    ->extraAttributes(['class' => 'bexia-employee-incident-field bexia-employee-incident-attachment-field'])
                                    ->visible(false)
                                    ->dehydrated(false)
                                    ->disk('public')
                                    ->directory('employee-incidents')
                                    ->visibility('public')
                                    ->downloadable()
                                    ->openable()
                                    ->columnSpanFull(),

                                Textarea::make('description')
                                    ->label('Descripción')
                                    ->extraAttributes(['class' => 'bexia-employee-incident-field bexia-employee-incident-description-field'])
                                    ->rows(4)
                                    ->columnSpanFull(),

                                Textarea::make('resolution_notes')
                                    ->label('Notas de resolución')
                                    ->extraAttributes(['class' => 'bexia-employee-incident-field bexia-employee-incident-resolution-notes-field'])
                                    ->visible(false)
                                    ->dehydrated(false)
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

    public static function isVacationTypeId(mixed $incidentTypeId): bool
    {
        if (blank($incidentTypeId)) {
            return false;
        }

        return HrIncidentType::query()
            ->whereKey($incidentTypeId)
            ->where('code', EmployeeVacationBalanceCalculator::VACATION_INCIDENT_CODE)
            ->exists();
    }

    public static function vacationBalanceSummary(
        mixed $employeeId,
        mixed $incidentTypeId,
        mixed $quantity,
        mixed $quantityUnit,
        mixed $startDate,
        mixed $endDate,
    ): HtmlString {
        if (! self::isVacationTypeId($incidentTypeId)) {
            return new HtmlString('');
        }

        if (blank($employeeId)) {
            return self::vacationSummaryBox(
                'Selecciona un empleado para ver su saldo de vacaciones.',
                'info'
            );
        }

        $employee = Employee::query()->find($employeeId);

        if (! $employee) {
            return self::vacationSummaryBox('No se encontró el empleado seleccionado.', 'danger');
        }

        if (blank($employee->hire_date)) {
            return self::vacationSummaryBox(
                'El empleado no tiene fecha de ingreso. Captúrala en Empleados > Contrato y nómina antes de solicitar vacaciones.',
                'warning'
            );
        }

        $period = EmployeeVacationBalanceCalculator::currentPeriod($employee);

        if (! $period) {
            return self::vacationSummaryBox('No se pudo determinar el periodo actual de vacaciones.', 'warning');
        }

        $periodStart = $period['period_start'];
        $periodEnd = $period['period_end'];

        $existing = DB::table('employee_vacation_balances')
            ->where('employee_id', $employee->id)
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereDate('period_end', $periodEnd->toDateString())
            ->first();

        $entitled = (float) $period['entitled_days'];
        $carried = (float) ($existing->carried_over_days ?? 0);
        $adjusted = (float) ($existing->adjusted_days ?? 0);
        $expired = (float) ($existing->expired_days ?? 0);

        $taken = EmployeeVacationBalanceCalculator::calculateTakenDays($employee, $periodStart, $periodEnd);
        $available = max(0, round($entitled + $carried + $adjusted - $taken - $expired, 2));
        $requested = self::requestedVacationDaysFromForm($quantity, $quantityUnit, $startDate, $endDate);
        $after = round($available - $requested, 2);

        $tone = $requested > $available ? 'danger' : 'success';

        $message = sprintf(
            'Periodo: %s a %s | Antigüedad: %s años | Asignados: %.2f | Tomados aprobados: %.2f | Disponibles: %.2f | Solicitados: %.2f | %s',
            $periodStart->format('d/m/Y'),
            $periodEnd->format('d/m/Y'),
            (int) $period['years_of_service'],
            $entitled,
            $taken,
            $available,
            $requested,
            $requested > $available
                ? 'Excede el saldo disponible.'
                : 'Disponible después: ' . number_format(max(0, $after), 2)
        );

        return self::vacationSummaryBox($message, $tone);
    }

    protected static function requestedVacationDaysFromForm(
        mixed $quantity,
        mixed $quantityUnit,
        mixed $startDate,
        mixed $endDate,
    ): float {
        if ($quantity !== null && $quantity !== '' && $quantityUnit === 'days') {
            return round((float) $quantity, 2);
        }

        if (blank($startDate)) {
            return 0.0;
        }

        try {
            $start = \Carbon\CarbonImmutable::parse($startDate);
            $end = blank($endDate)
                ? $start
                : \Carbon\CarbonImmutable::parse($endDate);

            return round((float) ($start->diffInDays($end) + 1), 2);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    protected static function vacationSummaryBox(string $message, string $tone): HtmlString
    {
        $classes = match ($tone) {
            'danger' => 'border-red-300 bg-red-50 text-red-800 dark:border-red-700 dark:bg-red-950 dark:text-red-200',
            'warning' => 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200',
            'success' => 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
            default => 'border-slate-300 bg-slate-50 text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200',
        };

        return new HtmlString(
            '<div class="bexia-employee-incident-vacation-summary rounded-xl border px-4 py-3 text-sm ' . $classes . '">'
            . e($message)
            . '</div>'
        );
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
                    ->extraHeaderAttributes(['class' => 'bexia-employee-incident-col-employee'])
                    ->extraCellAttributes(['class' => 'bexia-employee-incident-col-employee']),

                Tables\Columns\TextColumn::make('incidentType.name')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-employee-incident-col-type'])
                    ->extraCellAttributes(['class' => 'bexia-employee-incident-col-type']),

                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-employee-incident-col-title'])
                    ->extraCellAttributes(['class' => 'bexia-employee-incident-col-title']),

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
                    })
                    ->extraHeaderAttributes(['class' => 'bexia-employee-incident-col-status'])
                    ->extraCellAttributes(['class' => 'bexia-employee-incident-col-status']),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'bexia-employee-incident-col-start-date'])
                    ->extraCellAttributes(['class' => 'bexia-employee-incident-col-start-date']),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable()
                    ->extraHeaderAttributes(['class' => 'bexia-employee-incident-col-end-date'])
                    ->extraCellAttributes(['class' => 'bexia-employee-incident-col-end-date']),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad / Monto / Minutos')
                    ->toggleable()
                    ->extraHeaderAttributes(['class' => 'bexia-employee-incident-col-quantity'])
                    ->extraCellAttributes(['class' => 'bexia-employee-incident-col-quantity']),

                Tables\Columns\TextColumn::make('quantity_unit')
                    ->label('Unidad')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraHeaderAttributes(['class' => 'bexia-employee-incident-col-quantity-unit'])
                    ->extraCellAttributes(['class' => 'bexia-employee-incident-col-quantity-unit']),

                Tables\Columns\IconColumn::make('requires_approval')
                    ->label('Aprobación')
                    ->boolean()
                    ->toggleable()
                    ->extraHeaderAttributes(['class' => 'bexia-employee-incident-col-approval'])
                    ->extraCellAttributes(['class' => 'bexia-employee-incident-col-approval']),

                Tables\Columns\IconColumn::make('affects_payroll')
                    ->label('Nómina')
                    ->boolean()
                    ->toggleable()
                    ->extraHeaderAttributes(['class' => 'bexia-employee-incident-col-payroll'])
                    ->extraCellAttributes(['class' => 'bexia-employee-incident-col-payroll']),

                Tables\Columns\IconColumn::make('attachment_path')
                    ->label('Soporte')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->attachment_path))
                    ->extraHeaderAttributes(['class' => 'bexia-employee-incident-col-attachment'])
                    ->extraCellAttributes(['class' => 'bexia-employee-incident-col-attachment']),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraHeaderAttributes(['class' => 'bexia-employee-incident-col-created'])
                    ->extraCellAttributes(['class' => 'bexia-employee-incident-col-created']),
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
                Tables\Actions\Action::make('send_to_approval')
                    ->label('Enviar a aprobación')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->visible(fn (EmployeeIncident $record): bool => in_array((string) $record->status, ['draft', 'rejected'], true))
                    ->action(function (EmployeeIncident $record): void {
                        try {
                            $request = EmployeeIncidentApprovalWorkflow::sendToApproval($record);

                            Notification::make()
                                ->title('Incidencia enviada a aprobación')
                                ->body('Solicitud #' . $request->id . ' creada correctamente.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo enviar a aprobación')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
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
