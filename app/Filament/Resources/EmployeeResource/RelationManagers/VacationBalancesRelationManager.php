<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\EmployeeVacationBalance;
use App\Support\EmployeeVacationBalanceCalculator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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
