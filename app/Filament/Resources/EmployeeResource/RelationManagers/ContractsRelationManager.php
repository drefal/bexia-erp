<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Filament\Resources\EmployeeContractResource;
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
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContractsRelationManager extends RelationManager
{
    protected static string $relationship = 'contracts';

    protected static ?string $title = 'Contratos';

    protected static ?string $modelLabel = 'contrato laboral';

    protected static ?string $pluralModelLabel = 'contratos laborales';

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

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return static::bexiaCanContratoPermission('rrhh.contratos.ver');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Contrato laboral')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('contract_number')
                                    ->label('Número / folio contrato')
                                    ->maxLength(255),

                                Select::make('contract_type')
                                    ->label('Tipo de contrato')
                                    ->options(EmployeeContractResource::contractTypeOptions())
                                    ->default('indefinite')
                                    ->required(),

                                Select::make('status')
                                    ->label('Estado')
                                    ->options(EmployeeContractResource::statusOptions())
                                    ->default('draft')
                                    ->required(),

                                Toggle::make('is_current')
                                    ->label('Contrato vigente')
                                    ->helperText('Al guardar como vigente, se desmarcarán otros contratos vigentes del empleado.'),

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
                            ]),
                    ]),

                Section::make('Condiciones laborales')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('hr_department_id')
                                    ->label('Departamento')
                                    ->options(fn () => $this->departmentOptions())
                                    ->searchable()
                                    ->preload(),

                                Select::make('hr_job_position_id')
                                    ->label('Puesto')
                                    ->options(fn () => $this->jobPositionOptions())
                                    ->searchable()
                                    ->preload(),

                                Select::make('hr_work_schedule_id')
                                    ->label('Horario laboral')
                                    ->options(fn () => $this->workScheduleOptions())
                                    ->searchable()
                                    ->preload(),

                                Select::make('payroll_employer_registration_id')
                                    ->label('Registro patronal')
                                    ->options(fn () => $this->employerRegistrationOptions())
                                    ->searchable()
                                    ->preload(),

                                Select::make('payroll_periodicity_id')
                                    ->label('Periodicidad nómina')
                                    ->options(fn () => $this->payrollPeriodicityOptions())
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
                            ->directory(fn (): string => 'employee-contracts/' . ($this->getOwnerRecord()->company_id ?? 'company') . '/' . ($this->getOwnerRecord()->id ?? 'employee'))
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

    protected function tenantId(): ?int
    {
        return Filament::getTenant()?->getKey() ?? $this->getOwnerRecord()->company_id;
    }

    protected function departmentOptions(): array
    {
        $tenantId = $this->tenantId();

        return HrDepartment::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function jobPositionOptions(): array
    {
        $tenantId = $this->tenantId();

        return HrJobPosition::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function workScheduleOptions(): array
    {
        $tenantId = $this->tenantId();

        return HrWorkSchedule::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function employerRegistrationOptions(): array
    {
        $tenantId = $this->tenantId();

        return PayrollEmployerRegistration::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function payrollPeriodicityOptions(): array
    {
        $tenantId = $this->tenantId();

        return PayrollPeriodicity::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('contract_number')
            ->columns([
                Tables\Columns\TextColumn::make('contract_number')
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contract_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => EmployeeContractResource::contractTypeOptions()[$state] ?? ($state ?: '-'))
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => EmployeeContractResource::statusOptions()[$state] ?? ($state ?: '-'))
                    ->badge(),

                Tables\Columns\IconColumn::make('is_current')
                    ->label('Vigente')
                    ->boolean(),

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

                Tables\Columns\TextColumn::make('base_salary')
                    ->label('Sueldo')
                    ->money('MXN')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('file_path')
                    ->label('Archivo')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->file_path)),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(EmployeeContractResource::statusOptions()),

                Filter::make('current')
                    ->label('Vigentes')
                    ->query(fn (Builder $query): Builder => $query->where('is_current', true)),

                Filter::make('expires_soon')
                    ->label('Por vencer 30 días')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('end_date')
                        ->whereDate('end_date', '>=', today())
                        ->whereDate('end_date', '<=', today()->addDays(30))),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar contrato')
                    ->mutateFormDataUsing(function (array $data): array {
                        $owner = $this->getOwnerRecord();

                        $data['company_id'] = $owner->company_id;
                        $data['created_by_user_id'] = auth()->id();
                        $data['updated_by_user_id'] = auth()->id();

                        if (($data['status'] ?? null) === 'active') {
                            $data['is_current'] = true;
                        }

                        return $data;
                    })
                    ->after(function (EmployeeContract $record): void {
                        EmployeeContractResource::syncCurrentContract($record);
                    })
                    ->visible(fn (): bool => static::bexiaCanContratoPermission('rrhh.contratos.crear')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['updated_by_user_id'] = auth()->id();

                        if (($data['status'] ?? null) === 'active') {
                            $data['is_current'] = true;
                        }

                        return $data;
                    })
                    ->after(function (EmployeeContract $record): void {
                        EmployeeContractResource::syncCurrentContract($record);
                    })
                    ->visible(fn (): bool => static::bexiaCanContratoPermission('rrhh.contratos.editar')),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn (): bool => static::bexiaCanContratoPermission('rrhh.contratos.eliminar')),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Eliminar seleccionados')
                    ->visible(fn (): bool => static::bexiaCanContratoPermission('rrhh.contratos.eliminar')),
            ]);
    }
}
