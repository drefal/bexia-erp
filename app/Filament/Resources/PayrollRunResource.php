<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollRunResource\Pages;
use App\Filament\Resources\PayrollRunResource\RelationManagers\LinesRelationManager;
use App\Models\PayrollRun;
use App\Support\PayrollRunCalculator;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PayrollRunResource extends Resource
{
    protected static ?string $model = PayrollRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'RRHH';

    protected static ?string $navigationLabel = 'Pre-nómina';

    protected static ?string $modelLabel = 'pre-nómina';

    protected static ?string $pluralModelLabel = 'pre-nómina';

    protected static ?int $navigationSort = 23;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function bexiaCanPayrollPermission(string $permission): bool
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

        return $user->can($permission) || $user->can('company.update');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::bexiaCanPayrollPermission('nomina.prenomina.ver');
    }

    public static function canViewAny(): bool
    {
        return static::bexiaCanPayrollPermission('nomina.prenomina.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanPayrollPermission('nomina.prenomina.ver');
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanPayrollPermission('nomina.prenomina.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanPayrollPermission('nomina.prenomina.editar');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanPayrollPermission('nomina.prenomina.eliminar');
    }

    public static function canDeleteAny(): bool
    {
        return static::bexiaCanPayrollPermission('nomina.prenomina.eliminar');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()
            ->withCount('lines')
            ->when($tenantId, fn (Builder $query) => $query->where('company_id', $tenantId));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Periodo de pre-nómina')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('period_type')
                            ->label('Periodicidad')
                            ->options(PayrollRun::periodTypeOptions())
                            ->default('quincenal')
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options(PayrollRun::statusOptions())
                            ->default('draft')
                            ->disabled()
                            ->dehydrated(true),

                        Forms\Components\DatePicker::make('period_start')
                            ->label('Inicio')
                            ->native(false)
                            ->default(now()->startOfMonth())
                            ->required(),

                        Forms\Components\DatePicker::make('period_end')
                            ->label('Fin')
                            ->native(false)
                            ->default(now()->endOfMonth())
                            ->required(),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Fecha de pago')
                            ->native(false),

                        Forms\Components\TextInput::make('currency')
                            ->label('Moneda')
                            ->default('MXN')
                            ->maxLength(3),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->columnSpanFull()
                            ->rows(3),
                    ]),

                Forms\Components\Section::make('Totales')
                    ->columns(4)
                    ->schema([
                        Forms\Components\TextInput::make('employees_count')->label('Empleados')->disabled(),
                        Forms\Components\TextInput::make('base_total')->label('Sueldo base')->disabled()->prefix('$'),
                        Forms\Components\TextInput::make('overtime_total')->label('Horas extra')->disabled()->prefix('$'),
                        Forms\Components\TextInput::make('perceptions_total')->label('Percepciones')->disabled()->prefix('$'),
                        Forms\Components\TextInput::make('deductions_total')->label('Deducciones')->disabled()->prefix('$'),
                        Forms\Components\TextInput::make('gross_total')->label('Bruto')->disabled()->prefix('$'),
                        Forms\Components\TextInput::make('net_total')->label('Neto')->disabled()->prefix('$'),
                        Forms\Components\TextInput::make('calculated_at')->label('Calculada')->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Pre-nómina')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_type')
                    ->label('Periodicidad')
                    ->formatStateUsing(fn (?string $state): string => PayrollRun::periodTypeOptions()[$state] ?? ($state ?: '-'))
                    ->badge(),

                Tables\Columns\TextColumn::make('period_start')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_end')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => PayrollRun::statusOptions()[$state] ?? ($state ?: '-')),

                Tables\Columns\TextColumn::make('employees_count')
                    ->label('Empleados')
                    ->sortable(),

                Tables\Columns\TextColumn::make('gross_total')
                    ->label('Bruto')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('deductions_total')
                    ->label('Deducciones')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('net_total')
                    ->label('Neto')
                    ->money('MXN')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('calculate')
                    ->label('Calcular')
                    ->icon('heroicon-o-calculator')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PayrollRun $record): bool => static::bexiaCanPayrollPermission('nomina.prenomina.calcular') && in_array($record->status, ['draft', 'calculated'], true))
                    ->action(function (PayrollRun $record): void {
                        try {
                            PayrollRunCalculator::calculate($record, auth()->id());

                            Notification::make()
                                ->title('Pre-nómina calculada')
                                ->body('Se generaron las líneas y totales del periodo.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo calcular')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Eliminar seleccionadas'),
            ])
            ->defaultSort('period_start', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollRuns::route('/'),
            'create' => Pages\CreatePayrollRun::route('/create'),
            'edit' => Pages\EditPayrollRun::route('/{record}/edit'),
        ];
    }
}
