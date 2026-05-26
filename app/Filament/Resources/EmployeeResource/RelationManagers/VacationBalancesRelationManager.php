<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\EmployeeVacationBalance;
use App\Models\EmployeeIncident;
use App\Models\HrIncidentType;
use App\Support\EmployeeIncidentApprovalWorkflow;
use App\Support\EmployeeVacationBalanceCalculator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class VacationBalancesRelationManager extends RelationManager
{
    protected static string $relationship = 'vacationBalances';

    protected static ?string $title = 'Vacaciones';

    protected static ?string $modelLabel = 'saldo de vacaciones';

    protected static ?string $pluralModelLabel = 'saldos de vacaciones';

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
            || $user->can('rrhh.vacaciones.ver')
            || $user->can('company.update');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return static::bexiaCanVacacionesPermission('rrhh.vacaciones.ver');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Saldo de vacaciones')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'open' => 'Abierto',
                                        'closed' => 'Cerrado',
                                        'expired' => 'Vencido',
                                    ])
                                    ->default('open')
                                    ->required(),

                                TextInput::make('policy_code')
                                    ->label('Política')
                                    ->default(EmployeeVacationBalanceCalculator::POLICY_MX_LFT_2023),

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

                                Textarea::make('notes')
                                    ->label('Notas')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    protected function vacationRequestSummary(mixed $quantity, mixed $startDate, mixed $endDate): HtmlString
    {
        $owner = $this->getOwnerRecord();

        if (! $owner) {
            return $this->vacationSummaryBox('No se encontró el empleado.', 'danger');
        }

        if (blank($owner->hire_date)) {
            return $this->vacationSummaryBox(
                'El empleado no tiene fecha de ingreso. Captúrala antes de solicitar vacaciones.',
                'warning'
            );
        }

        $period = EmployeeVacationBalanceCalculator::currentPeriod($owner);

        if (! $period) {
            return $this->vacationSummaryBox('No se pudo determinar el periodo actual de vacaciones.', 'warning');
        }

        $periodStart = $period['period_start'];
        $periodEnd = $period['period_end'];

        $balance = EmployeeVacationBalanceCalculator::generateCurrentBalance($owner, auth()->id());
        $available = (float) $balance->pending_days;
        $taken = (float) $balance->taken_days;
        $entitled = (float) $balance->entitled_days;
        $requested = $this->requestedVacationDaysFromForm($quantity, $startDate, $endDate);
        $after = round($available - $requested, 2);

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

        return $this->vacationSummaryBox($message, $requested > $available ? 'danger' : 'success');
    }

    protected function requestedVacationDaysFromForm(mixed $quantity, mixed $startDate, mixed $endDate): float
    {
        if ($quantity !== null && $quantity !== '') {
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

    protected function vacationSummaryBox(string $message, string $tone): HtmlString
    {
        $classes = match ($tone) {
            'danger' => 'border-red-300 bg-red-50 text-red-800 dark:border-red-700 dark:bg-red-950 dark:text-red-200',
            'warning' => 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200',
            'success' => 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
            default => 'border-slate-300 bg-slate-50 text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200',
        };

        return new HtmlString(
            '<div class="rounded-xl border px-4 py-3 text-sm ' . $classes . '">'
            . e($message)
            . '</div>'
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('period_start')
            ->columns([
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
                    ->numeric(2),

                Tables\Columns\TextColumn::make('taken_days')
                    ->label('Tomados')
                    ->numeric(2),

                Tables\Columns\TextColumn::make('pending_days')
                    ->label('Disponibles')
                    ->numeric(2),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'open' => 'Abierto',
                        'closed' => 'Cerrado',
                        'expired' => 'Vencido',
                        default => $state ?: '-',
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('request_vacation')
                    ->label('Solicitar vacaciones')
                    ->icon('heroicon-o-paper-airplane')
                    ->form([
                        Section::make('Solicitud de vacaciones')
                            ->description('Crea una incidencia tipo Vacaciones desde la ficha del empleado.')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        DatePicker::make('start_date')
                                            ->label('Fecha inicio')
                                            ->native(false)
                                            ->required()
                                            ->live(),

                                        DatePicker::make('end_date')
                                            ->label('Fecha fin')
                                            ->native(false)
                                            ->required()
                                            ->live(),

                                        TextInput::make('quantity')
                                            ->label('Días solicitados')
                                            ->numeric()
                                            ->required()
                                            ->default(1)
                                            ->live(debounce: 500),

                                        Toggle::make('send_to_approval')
                                            ->label('Enviar a aprobación al guardar')
                                            ->default(true),

                                        Placeholder::make('vacation_balance_summary')
                                            ->label('Resumen de vacaciones')
                                            ->content(fn (Get $get): HtmlString => $this->vacationRequestSummary(
                                                $get('quantity'),
                                                $get('start_date'),
                                                $get('end_date'),
                                            ))
                                            ->columnSpanFull(),

                                        Textarea::make('description')
                                            ->label('Notas')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $owner = $this->getOwnerRecord();

                            $vacationType = HrIncidentType::query()
                                ->where('company_id', $owner->company_id)
                                ->where('code', EmployeeVacationBalanceCalculator::VACATION_INCIDENT_CODE)
                                ->where('is_active', true)
                                ->first();

                            if (! $vacationType) {
                                throw new \RuntimeException('No existe un tipo de incidencia activo con código VACACIONES.');
                            }

                            if (blank($owner->hire_date)) {
                                throw new \RuntimeException('El empleado no tiene fecha de ingreso. Captúrala antes de solicitar vacaciones.');
                            }

                            $incident = EmployeeIncident::query()->create([
                                'company_id' => $owner->company_id,
                                'employee_id' => $owner->id,
                                'hr_incident_type_id' => $vacationType->id,
                                'title' => 'Solicitud de vacaciones',
                                'status' => 'draft',
                                'start_date' => $data['start_date'],
                                'end_date' => $data['end_date'],
                                'quantity' => $data['quantity'],
                                'quantity_unit' => 'days',
                                'affects_payroll' => false,
                                'requires_approval' => true,
                                'description' => $data['description'] ?? null,
                                'created_by_user_id' => auth()->id(),
                                'updated_by_user_id' => auth()->id(),
                            ]);

                            if ((bool) ($data['send_to_approval'] ?? true)) {
                                $request = EmployeeIncidentApprovalWorkflow::sendToApproval($incident);

                                Notification::make()
                                    ->title('Solicitud enviada a aprobación')
                                    ->body('Solicitud #' . $request->id . ' creada correctamente.')
                                    ->success()
                                    ->send();

                                return;
                            }

                            EmployeeVacationBalanceCalculator::generateCurrentBalance($owner->fresh(), auth()->id());

                            Notification::make()
                                ->title('Solicitud creada en borrador')
                                ->body('Se creó la incidencia de vacaciones. Puedes enviarla a aprobación desde Incidencias.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo solicitar vacaciones')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (): bool => static::bexiaCanVacacionesPermission('rrhh.vacaciones.crear')),

                Tables\Actions\Action::make('generate_current_balance')
                    ->label('Generar saldo actual')
                    ->icon('heroicon-o-calculator')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        try {
                            EmployeeVacationBalanceCalculator::generateCurrentBalance($this->getOwnerRecord(), auth()->id());

                            Notification::make()
                                ->title('Saldo actual generado')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo generar saldo')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (): bool => static::bexiaCanVacacionesPermission('rrhh.vacaciones.crear')),

                Tables\Actions\CreateAction::make()
                    ->label('Agregar saldo')
                    ->mutateFormDataUsing(function (array $data): array {
                        $owner = $this->getOwnerRecord();

                        $data['company_id'] = $owner->company_id;
                        $data['created_by_user_id'] = auth()->id();
                        $data['updated_by_user_id'] = auth()->id();

                        return $data;
                    })
                    ->visible(fn (): bool => static::bexiaCanVacacionesPermission('rrhh.vacaciones.crear')),
            ])
            ->actions([
                Tables\Actions\Action::make('recalculate')
                    ->label('Recalcular')
                    ->icon('heroicon-o-arrow-path')
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

                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['updated_by_user_id'] = auth()->id();

                        return $data;
                    })
                    ->visible(fn (): bool => static::bexiaCanVacacionesPermission('rrhh.vacaciones.editar')),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn (): bool => static::bexiaCanVacacionesPermission('rrhh.vacaciones.eliminar')),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Eliminar seleccionados')
                    ->visible(fn (): bool => static::bexiaCanVacacionesPermission('rrhh.vacaciones.eliminar')),
            ]);
    }
}
