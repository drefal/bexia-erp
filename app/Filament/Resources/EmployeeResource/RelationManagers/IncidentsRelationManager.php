<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\HrIncidentType;
use App\Support\EmployeeIncidentApprovalWorkflow;
use Filament\Facades\Filament;
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
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class IncidentsRelationManager extends RelationManager
{
    protected static string $relationship = 'incidents';

    protected static ?string $title = 'Incidencias';

    protected static ?string $modelLabel = 'incidencia';

    protected static ?string $pluralModelLabel = 'incidencias';

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
            || $user->can('rrhh.incidencias.ver')
            || $user->can('company.update');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return static::bexiaCanIncidenciaPermission('rrhh.incidencias.ver');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Incidencia del empleado')
                    ->description('Eventos laborales relacionados con este empleado.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('hr_incident_type_id')
                                    ->label('Tipo de incidencia')
                                    ->live()
                                    ->options(fn () => $this->incidentTypeOptions())
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
                                    ->live()
                                    ->native(false)
                                    ->required(),

                                DatePicker::make('end_date')
                                    ->label('Fecha fin')
                                    ->live()
                                    ->native(false),

                                TextInput::make('start_time')
                                    ->label('Hora inicio')
                                    ->type('time'),

                                TextInput::make('end_time')
                                    ->label('Hora fin')
                                    ->type('time'),

                                TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->live(debounce: 500)
                                    ->numeric(),

                                Select::make('quantity_unit')
                                    ->label('Unidad')
                                    ->live()
                                    ->options([
                                        'minutes' => 'Minutos',
                                        'hours' => 'Horas',
                                        'days' => 'Días',
                                        'events' => 'Eventos',
                                    ]),

                                Placeholder::make('vacation_balance_summary')
                                    ->label('Resumen de vacaciones')
                                    ->content(fn (Get $get): HtmlString => \App\Filament\Resources\EmployeeIncidentResource::vacationBalanceSummary(
                                        $this->getOwnerRecord()->id,
                                        $get('hr_incident_type_id'),
                                        $get('quantity'),
                                        $get('quantity_unit'),
                                        $get('start_date'),
                                        $get('end_date'),
                                    ))
                                    ->visible(fn (Get $get): bool => \App\Filament\Resources\EmployeeIncidentResource::isVacationTypeId($get('hr_incident_type_id')))
                                    ->columnSpanFull(),

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
                                    ->directory(fn (): string => 'employee-incidents/' . ($this->getOwnerRecord()->company_id ?? 'company') . '/' . ($this->getOwnerRecord()->id ?? 'employee'))
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

    protected function incidentTypeOptions(): array
    {
        $tenantId = Filament::getTenant()?->getKey() ?? $this->getOwnerRecord()->company_id;

        return HrIncidentType::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
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

                Tables\Columns\IconColumn::make('affects_payroll')
                    ->label('Nómina')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('attachment_path')
                    ->label('Soporte')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->attachment_path)),
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
                    ->label('Tipo')
                    ->options(fn () => $this->incidentTypeOptions()),

                Filter::make('current_month')
                    ->label('Mes actual')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDate('start_date', '>=', now()->startOfMonth())
                        ->whereDate('start_date', '<=', now()->endOfMonth())),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar incidencia')
                    ->mutateFormDataUsing(function (array $data): array {
                        $owner = $this->getOwnerRecord();

                        $data['company_id'] = $owner->company_id;
                        $data['created_by_user_id'] = auth()->id();
                        $data['updated_by_user_id'] = auth()->id();

                        if (($data['status'] ?? null) === 'approved') {
                            $data['approved_by_user_id'] = auth()->id();
                            $data['approved_at'] = now();
                        }

                        return $data;
                    })
                    ->visible(fn (): bool => static::bexiaCanIncidenciaPermission('rrhh.incidencias.crear')),
            ])
            ->actions([
                Tables\Actions\Action::make('send_to_approval')
                    ->label('Enviar a aprobación')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => in_array((string) $record->status, ['draft', 'rejected'], true))
                    ->action(function ($record): void {
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

                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['updated_by_user_id'] = auth()->id();

                        return $data;
                    })
                    ->visible(fn (): bool => static::bexiaCanIncidenciaPermission('rrhh.incidencias.editar')),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn (): bool => static::bexiaCanIncidenciaPermission('rrhh.incidencias.eliminar')),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Eliminar seleccionados')
                    ->visible(fn (): bool => static::bexiaCanIncidenciaPermission('rrhh.incidencias.eliminar')),
            ]);
    }
}
