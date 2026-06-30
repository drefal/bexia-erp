<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeTerminationResource\Pages;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeTermination;
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

class EmployeeTerminationResource extends Resource
{
    protected static ?string $model = EmployeeTermination::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-minus';

    protected static ?string $navigationGroup = 'RRHH';

    protected static ?string $navigationLabel = 'Bajas';

    protected static ?string $modelLabel = 'baja laboral';

    protected static ?string $pluralModelLabel = 'bajas laborales';

    protected static ?int $navigationSort = 19;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function bexiaCanBajaPermission(string $permission): bool
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
            || $user->can('rrhh.bajas.ver')
            || $user->can('company.update');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::bexiaCanBajaPermission('rrhh.bajas.ver');
    }

    public static function canViewAny(): bool
    {
        return static::bexiaCanBajaPermission('rrhh.bajas.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanBajaPermission('rrhh.bajas.ver');
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanBajaPermission('rrhh.bajas.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanBajaPermission('rrhh.bajas.editar');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanBajaPermission('rrhh.bajas.eliminar');
    }

    public static function canDeleteAny(): bool
    {
        return static::bexiaCanBajaPermission('rrhh.bajas.eliminar');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()
            ->with(['employee', 'contract'])
            ->when($tenantId, fn (Builder $query) => $query->where('company_id', $tenantId));
    }


    /*
     * BEXIA_ETERM_RESOURCE_RESPONSIVE_V5_79_52C
     * Visual-only responsive marker.
     */

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Baja laboral')
                    ->extraAttributes(['class' => 'bexia-eterm-section bexia-eterm-section-main'])
                    ->description('Registra la separación laboral del empleado y, si aplica, cierra su contrato vigente.')
                    ->schema([
                        Grid::make(2)
                            ->extraAttributes(['class' => 'bexia-eterm-grid bexia-eterm-grid-main'])
                            ->schema([
                                Select::make('employee_id')
                                    ->extraAttributes(['class' => 'bexia-eterm-field bexia-eterm-field-employee'])
                                    ->label('Empleado')
                                    ->options(fn () => self::employeeOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live(),

                                Select::make('employee_contract_id')
                                    ->extraAttributes(['class' => 'bexia-eterm-field bexia-eterm-field-contract'])
                                    ->label('Contrato relacionado')
                                    ->options(fn () => self::contractOptions())
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Opcional. Si se deja vacío, al completar la baja se cerrará el contrato vigente del empleado.'),

                                TextInput::make('termination_number')
                                    ->extraAttributes(['class' => 'bexia-eterm-field bexia-eterm-field-number'])
                                    ->label('Folio baja')
                                    ->maxLength(255),

                                Select::make('termination_type')
                                    ->extraAttributes(['class' => 'bexia-eterm-field bexia-eterm-field-type'])
                                    ->label('Tipo de baja')
                                    ->options(self::terminationTypeOptions())
                                    ->default('resignation')
                                    ->required(),

                                Select::make('status')
                                    ->extraAttributes(['class' => 'bexia-eterm-field bexia-eterm-field-status'])
                                    ->label('Estado')
                                    ->options(self::statusOptions())
                                    ->default('draft')
                                    ->required(),

                                DatePicker::make('termination_date')
                                    ->extraAttributes(['class' => 'bexia-eterm-field bexia-eterm-field-date bexia-eterm-field-termination-date'])
                                    ->label('Fecha de baja')
                                    ->native(false)
                                    ->required(),

                                DatePicker::make('last_working_day')
                                    ->extraAttributes(['class' => 'bexia-eterm-field bexia-eterm-field-date bexia-eterm-field-last-day'])
                                    ->label('Último día laborado')
                                    ->native(false),

                                DatePicker::make('notice_date')
                                    ->extraAttributes(['class' => 'bexia-eterm-field bexia-eterm-field-date bexia-eterm-field-notice-date'])
                                    ->label('Fecha de aviso')
                                    ->native(false),

                                Toggle::make('rehire_eligible')
                                    ->extraAttributes(['class' => 'bexia-eterm-toggle bexia-eterm-toggle-rehire'])
                                    ->label('Recontratable')
                                    ->default(false),

                                TextInput::make('settlement_amount')
                                    ->extraAttributes(['class' => 'bexia-eterm-field bexia-eterm-field-money bexia-eterm-field-settlement'])
                                    ->label('Monto finiquito / liquidación')
                                    ->numeric()
                                    ->prefix('$'),

                                TextInput::make('currency')
                                    ->extraAttributes(['class' => 'bexia-eterm-field bexia-eterm-field-currency'])
                                    ->label('Moneda')
                                    ->default('MXN')
                                    ->maxLength(3),

                                Placeholder::make('completion_warning')
                                    ->extraAttributes(['class' => 'bexia-eterm-placeholder bexia-eterm-placeholder-warning'])
                                    ->label('Efecto al completar')
                                    ->content(new HtmlString('<div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">Si el estado es Completada, el empleado se marcará inactivo, se guardará la fecha de baja y se cerrará su contrato vigente.</div>'))
                                    ->columnSpanFull(),

                                Textarea::make('reason')
                                    ->extraAttributes(['class' => 'bexia-eterm-field bexia-eterm-field-reason'])
                                    ->label('Motivo')
                                    ->rows(3)
                                    ->columnSpanFull(),

                                FileUpload::make('file_path')
                                    ->label('Carta / finiquito / evidencia')
                                    ->disk('public')
                                    ->directory('employee-terminations')
                                    ->visibility('public')
                                    ->downloadable()
                                    ->openable()
                                    ->columnSpanFull(),

                                Textarea::make('notes')
                                    ->extraAttributes(['class' => 'bexia-eterm-field bexia-eterm-field-notes'])
                                    ->label('Notas internas')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    public static function terminationTypeOptions(): array
    {
        return [
            'resignation' => 'Renuncia voluntaria',
            'dismissal' => 'Despido',
            'contract_end' => 'Fin de contrato',
            'mutual_agreement' => 'Mutuo acuerdo',
            'abandonment' => 'Abandono de empleo',
            'retirement' => 'Jubilación / retiro',
            'death' => 'Fallecimiento',
            'other' => 'Otro',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'draft' => 'Borrador',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
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
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function contractOptions(): array
    {
        $tenantId = self::tenantId();

        return EmployeeContract::query()
            ->with('employee')
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->orderByDesc('is_current')
            ->orderByDesc('start_date')
            ->get()
            ->mapWithKeys(fn (EmployeeContract $contract) => [
                $contract->id => trim(($contract->employee?->name ?? 'Empleado') . ' - ' . ($contract->contract_number ?: 'Contrato #' . $contract->id)),
            ])
            ->toArray();
    }

    public static function applyTermination(EmployeeTermination $termination, ?int $userId = null): void
    {
        if ($termination->status !== 'completed') {
            return;
        }

        DB::transaction(function () use ($termination, $userId): void {
            $userId = $userId ?: auth()->id();

            $employee = Employee::query()->lockForUpdate()->find($termination->employee_id);

            if (! $employee) {
                throw new \RuntimeException('No se encontró el empleado de la baja.');
            }

            $terminationDate = $termination->termination_date?->toDateString();

            if (! $terminationDate) {
                throw new \RuntimeException('La baja debe tener fecha de baja.');
            }

            $contract = null;

            if ($termination->employee_contract_id) {
                $contract = EmployeeContract::query()
                    ->where('employee_id', $employee->id)
                    ->where('id', $termination->employee_contract_id)
                    ->first();
            }

            if (! $contract) {
                $contract = EmployeeContract::query()
                    ->where('employee_id', $employee->id)
                    ->where('is_current', true)
                    ->orderByDesc('start_date')
                    ->first();
            }

            if ($contract) {
                $contract->forceFill([
                    'status' => 'terminated',
                    'end_date' => $terminationDate,
                    'is_current' => false,
                    'updated_by_user_id' => $userId,
                ])->save();

                if (! $termination->employee_contract_id) {
                    $termination->forceFill([
                        'employee_contract_id' => $contract->id,
                    ])->save();
                }
            }

            $employee->forceFill([
                'active' => false,
                'pos_active' => false,
                'termination_date' => $terminationDate,
            ])->save();

            $termination->forceFill([
                'completed_by_user_id' => $termination->completed_by_user_id ?: $userId,
                'completed_at' => $termination->completed_at ?: now(),
                'updated_by_user_id' => $userId,
            ])->save();
        });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->extraHeaderAttributes(['class' => 'bexia-eterm-col-employee bexia-eterm-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-eterm-col-employee bexia-eterm-col-primary'])
                    ->label('Empleado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('termination_number')
                    ->extraHeaderAttributes(['class' => 'bexia-eterm-col-number'])
                    ->extraCellAttributes(['class' => 'bexia-eterm-col-number'])
                    ->label('Folio')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('termination_type')
                    ->extraHeaderAttributes(['class' => 'bexia-eterm-col-type'])
                    ->extraCellAttributes(['class' => 'bexia-eterm-col-type'])
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => self::terminationTypeOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->extraHeaderAttributes(['class' => 'bexia-eterm-col-status bexia-eterm-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-eterm-col-status bexia-eterm-col-badge'])
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => self::statusOptions()[$state] ?? ($state ?: '-'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('termination_date')
                    ->extraHeaderAttributes(['class' => 'bexia-eterm-col-date bexia-eterm-col-termination-date'])
                    ->extraCellAttributes(['class' => 'bexia-eterm-col-date bexia-eterm-col-termination-date'])
                    ->label('Fecha baja')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_working_day')
                    ->extraHeaderAttributes(['class' => 'bexia-eterm-col-date bexia-eterm-col-last-day'])
                    ->extraCellAttributes(['class' => 'bexia-eterm-col-date bexia-eterm-col-last-day'])
                    ->label('Último día')
                    ->date('d/m/Y')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('rehire_eligible')
                    ->extraHeaderAttributes(['class' => 'bexia-eterm-col-rehire'])
                    ->extraCellAttributes(['class' => 'bexia-eterm-col-rehire'])
                    ->label('Recontratable')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('settlement_amount')
                    ->extraHeaderAttributes(['class' => 'bexia-eterm-col-money bexia-eterm-col-settlement'])
                    ->extraCellAttributes(['class' => 'bexia-eterm-col-money bexia-eterm-col-settlement'])
                    ->label('Finiquito')
                    ->money('MXN')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('file_path')
                    ->extraHeaderAttributes(['class' => 'bexia-eterm-col-file'])
                    ->extraCellAttributes(['class' => 'bexia-eterm-col-file'])
                    ->label('Archivo')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->file_path)),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(self::statusOptions()),

                SelectFilter::make('termination_type')
                    ->label('Tipo de baja')
                    ->options(self::terminationTypeOptions()),

                Filter::make('current_month')
                    ->label('Mes actual')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDate('termination_date', '>=', now()->startOfMonth())
                        ->whereDate('termination_date', '<=', now()->endOfMonth())),
            ])
            ->actions([
                Tables\Actions\Action::make('complete')
                    ->label('Completar baja')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn (EmployeeTermination $record): bool => $record->status !== 'completed')
                    ->action(function (EmployeeTermination $record): void {
                        try {
                            $record->forceFill([
                                'status' => 'completed',
                                'updated_by_user_id' => auth()->id(),
                            ])->save();

                            self::applyTermination($record, auth()->id());

                            Notification::make()
                                ->title('Baja completada')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo completar la baja')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make()
                    ->after(function (EmployeeTermination $record): void {
                        self::applyTermination($record, auth()->id());
                    }),

                Tables\Actions\DeleteAction::make()->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Eliminar seleccionadas'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeTerminations::route('/'),
            'create' => Pages\CreateEmployeeTermination::route('/create'),
            'edit' => Pages\EditEmployeeTermination::route('/{record}/edit'),
        ];
    }
}
