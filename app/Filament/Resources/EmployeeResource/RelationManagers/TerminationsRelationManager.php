<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Filament\Resources\EmployeeTerminationResource;
use App\Models\EmployeeContract;
use App\Models\EmployeeTermination;
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
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class TerminationsRelationManager extends RelationManager
{
    protected static string $relationship = 'terminations';

    protected static ?string $title = 'Bajas';

    protected static ?string $modelLabel = 'baja laboral';

    protected static ?string $pluralModelLabel = 'bajas laborales';

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

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return static::bexiaCanBajaPermission('rrhh.bajas.ver');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Baja laboral')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('employee_contract_id')
                                    ->label('Contrato relacionado')
                                    ->options(fn () => $this->contractOptions())
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Opcional. Si se deja vacío, al completar la baja se cerrará el contrato vigente.'),

                                TextInput::make('termination_number')
                                    ->label('Folio baja')
                                    ->maxLength(255),

                                Select::make('termination_type')
                                    ->label('Tipo de baja')
                                    ->options(EmployeeTerminationResource::terminationTypeOptions())
                                    ->default('resignation')
                                    ->required(),

                                Select::make('status')
                                    ->label('Estado')
                                    ->options(EmployeeTerminationResource::statusOptions())
                                    ->default('draft')
                                    ->required(),

                                DatePicker::make('termination_date')
                                    ->label('Fecha de baja')
                                    ->native(false)
                                    ->required(),

                                DatePicker::make('last_working_day')
                                    ->label('Último día laborado')
                                    ->native(false),

                                DatePicker::make('notice_date')
                                    ->label('Fecha de aviso')
                                    ->native(false),

                                Toggle::make('rehire_eligible')
                                    ->label('Recontratable')
                                    ->default(false),

                                TextInput::make('settlement_amount')
                                    ->label('Monto finiquito / liquidación')
                                    ->numeric()
                                    ->prefix('$'),

                                TextInput::make('currency')
                                    ->label('Moneda')
                                    ->default('MXN')
                                    ->maxLength(3),

                                Placeholder::make('completion_warning')
                                    ->label('Efecto al completar')
                                    ->content(new HtmlString('<div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">Si el estado es Completada, el empleado se marcará inactivo, se guardará la fecha de baja y se cerrará su contrato vigente.</div>'))
                                    ->columnSpanFull(),

                                Textarea::make('reason')
                                    ->label('Motivo')
                                    ->rows(3)
                                    ->columnSpanFull(),

                                FileUpload::make('file_path')
                                    ->label('Carta / finiquito / evidencia')
                                    ->disk('public')
                                    ->directory(fn (): string => 'employee-terminations/' . ($this->getOwnerRecord()->company_id ?? 'company') . '/' . ($this->getOwnerRecord()->id ?? 'employee'))
                                    ->visibility('public')
                                    ->downloadable()
                                    ->openable()
                                    ->columnSpanFull(),

                                Textarea::make('notes')
                                    ->label('Notas internas')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    protected function contractOptions(): array
    {
        $owner = $this->getOwnerRecord();

        return EmployeeContract::query()
            ->where('employee_id', $owner->id)
            ->orderByDesc('is_current')
            ->orderByDesc('start_date')
            ->get()
            ->mapWithKeys(fn (EmployeeContract $contract) => [
                $contract->id => trim(($contract->contract_number ?: 'Contrato #' . $contract->id) . ' - ' . ($contract->status ?: '')),
            ])
            ->toArray();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('termination_number')
            ->columns([
                Tables\Columns\TextColumn::make('termination_number')
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('termination_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => EmployeeTerminationResource::terminationTypeOptions()[$state] ?? ($state ?: '-'))
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => EmployeeTerminationResource::statusOptions()[$state] ?? ($state ?: '-'))
                    ->badge(),

                Tables\Columns\TextColumn::make('termination_date')
                    ->label('Fecha baja')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_working_day')
                    ->label('Último día')
                    ->date('d/m/Y')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('rehire_eligible')
                    ->label('Recontratable')
                    ->boolean(),

                Tables\Columns\IconColumn::make('file_path')
                    ->label('Archivo')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->file_path)),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Registrar baja')
                    ->mutateFormDataUsing(function (array $data): array {
                        $owner = $this->getOwnerRecord();

                        $data['company_id'] = $owner->company_id;
                        $data['created_by_user_id'] = auth()->id();
                        $data['updated_by_user_id'] = auth()->id();

                        if (($data['status'] ?? null) === 'completed') {
                            $data['completed_by_user_id'] = auth()->id();
                            $data['completed_at'] = now();
                        }

                        return $data;
                    })
                    ->after(function (EmployeeTermination $record): void {
                        try {
                            EmployeeTerminationResource::applyTermination($record, auth()->id());

                            if ($record->status === 'completed') {
                                Notification::make()
                                    ->title('Baja completada')
                                    ->success()
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo aplicar la baja')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (): bool => static::bexiaCanBajaPermission('rrhh.bajas.crear')),
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

                            EmployeeTerminationResource::applyTermination($record, auth()->id());

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
                        EmployeeTerminationResource::applyTermination($record, auth()->id());
                    })
                    ->visible(fn (): bool => static::bexiaCanBajaPermission('rrhh.bajas.editar')),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn (): bool => static::bexiaCanBajaPermission('rrhh.bajas.eliminar')),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Eliminar seleccionadas')
                    ->visible(fn (): bool => static::bexiaCanBajaPermission('rrhh.bajas.eliminar')),
            ]);
    }
}
