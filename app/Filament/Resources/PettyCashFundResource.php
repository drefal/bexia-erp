<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PettyCashFundResource\Pages;
use App\Models\Employee;
use App\Models\PettyCashFund;
use App\Models\FundingSource;
use App\Models\TreasuryAccount;
use App\Support\Expenses\PettyCashTransferService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class PettyCashFundResource extends Resource
{
    protected static ?string $model = PettyCashFund::class;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationGroup = 'Gastos';

    protected static ?string $navigationLabel = 'Cajas chicas';

    protected static ?string $modelLabel = 'caja chica';

    protected static ?string $pluralModelLabel = 'cajas chicas';

    protected static ?int $navigationSort = 10;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function bexiaCanExpensePermission(string $permission): bool
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

        return \App\Support\Security\BexiaTenantPermission::can($permission);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::bexiaCanExpensePermission('expenses.admin')
            || static::bexiaCanExpensePermission('petty_cash.view')
            || static::bexiaCanExpensePermission('petty_cash.manage')
            || static::bexiaCanExpensePermission('petty_cash.transfer');
    }

    public static function canViewAny(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanExpensePermission('expenses.admin')
            || static::bexiaCanExpensePermission('petty_cash.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanExpensePermission('expenses.admin')
            || static::bexiaCanExpensePermission('petty_cash.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()
            ->with([
                'employee',
                'treasuryAccount',
                'fundingTreasuryAccount',
            ])
            ->when(
                $tenantId,
                fn (Builder $query) => $query->where('company_id', $tenantId)
            );
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Responsable')
                ->description(
                    'Asigna el fondo a un empleado. La caja chica tendrá una cuenta real de Tesorería.'
                )
                ->schema([
                    Forms\Components\Select::make('employee_id')
                        ->label('Empleado responsable')
                        ->options(fn (): array => static::employeeOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled(
                            fn (?PettyCashFund $record): bool => $record !== null
                        )
                        ->dehydrated(),

                    Forms\Components\TextInput::make('name')
                        ->label('Nombre de la caja chica')
                        ->placeholder('Caja chica - Nombre del empleado')
                        ->maxLength(150)
                        ->helperText(
                            'Si se deja vacío al crear, se genera automáticamente.'
                        ),

                    Forms\Components\TextInput::make('authorized_amount')
                        ->label('Monto autorizado')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('$')
                        ->default(0)
                        ->required(),

                    Forms\Components\Select::make('currency_code')
                        ->label('Moneda')
                        ->options([
                            'MXN' => 'MXN - Peso mexicano',
                            'USD' => 'USD - Dólar',
                        ])
                        ->default('MXN')
                        ->required()
                        ->disabled(
                            fn (?PettyCashFund $record): bool => $record !== null
                        )
                        ->dehydrated(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Fondeo')
                ->schema([
                    Forms\Components\Select::make('funding_treasury_account_id')
                        ->label('Caja / cuenta origen')
                        ->options(fn (): array => static::fundingAccountOptions())
                        ->searchable()
                        ->preload()
                        ->helperText(
                            'Esta cuenta se utilizará posteriormente para entregar y reponer efectivo al fondo.'
                        ),

                    Forms\Components\DatePicker::make('assigned_at')
                        ->label('Fecha de asignación')
                        ->native(true)
                        ->default(now()),

                    Forms\Components\Select::make('status')
                        ->label('Estado')
                        ->options([
                            'active' => 'Activa',
                            'suspended' => 'Suspendida',
                            'closed' => 'Cerrada',
                        ])
                        ->default('active')
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Disponible para operar')
                        ->default(true),

                    Forms\Components\Textarea::make('notes')
                        ->label('Notas')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Responsable')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Caja chica')
                    ->searchable(),

                Tables\Columns\TextColumn::make('authorized_amount')
                    ->label('Autorizado')
                    ->money(
                        fn (PettyCashFund $record): string =>
                            $record->currency_code ?: 'MXN'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('treasuryAccount.current_balance')
                    ->label('Saldo Tesorería')
                    ->money(
                        fn (PettyCashFund $record): string =>
                            $record->currency_code ?: 'MXN'
                    ),

                Tables\Columns\TextColumn::make('operational_balance')
                    ->label('Saldo operativo')
                    ->money(
                        fn (PettyCashFund $record): string =>
                            $record->currency_code ?: 'MXN'
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'Activa',
                        'suspended' => 'Suspendida',
                        'closed' => 'Cerrada',
                        default => $state ?: '—',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Disponible')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active' => 'Activa',
                        'suspended' => 'Suspendida',
                        'closed' => 'Cerrada',
                    ]),
            ])
            ->actions([
                static::initialFundingAction(),
                static::replenishmentAction(),
                static::returnCashAction(),
                static::fundTransferAction(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    protected static function pettyCashDestinationOptions(
        PettyCashFund $sourceFund
    ): array {
        return PettyCashFund::query()
            ->with(['employee', 'treasuryAccount'])
            ->where('company_id', $sourceFund->company_id)
            ->where('id', '!=', $sourceFund->id)
            ->where('is_active', true)
            ->where('status', 'active')
            ->where('currency_code', $sourceFund->currency_code)
            ->whereHas(
                'treasuryAccount',
                fn (Builder $query) =>
                    $query->where('is_active', true)
            )
            ->orderBy('number')
            ->get()
            ->mapWithKeys(function (PettyCashFund $fund): array {
                $responsible =
                    $fund->employee?->name ?: 'Sin responsable';

                $balance = (float) (
                    $fund->treasuryAccount?->current_balance ?? 0
                );

                return [
                    $fund->id =>
                        ($fund->number ?: ('#' . $fund->id))
                        . ' · '
                        . $fund->name
                        . ' · '
                        . $responsible
                        . ' · Saldo $'
                        . number_format($balance, 2),
                ];
            })
            ->toArray();
    }

    public static function fundTransferAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('fundTransfer')
            ->label('Transferir')
            ->icon('heroicon-o-arrows-right-left')
            ->color('warning')
            ->form([
                Forms\Components\Placeholder::make('source_info')
                    ->label('Caja origen')
                    ->content(
                        fn (PettyCashFund $record): string =>
                            ($record->number ?: ('#' . $record->id))
                            . ' · '
                            . $record->name
                            . ' · Saldo $'
                            . number_format(
                                (float) (
                                    $record->treasuryAccount
                                        ?->current_balance ?? 0
                                ),
                                2
                            )
                    ),

                Forms\Components\Select::make(
                    'destination_petty_cash_fund_id'
                )
                    ->label('Caja chica destino')
                    ->options(
                        fn (PettyCashFund $record): array =>
                            static::pettyCashDestinationOptions(
                                $record
                            )
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText(
                        'Solo se muestran cajas activas de la misma empresa y moneda.'
                    ),

                Forms\Components\TextInput::make('amount')
                    ->label('Monto a transferir')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->step('0.01')
                    ->prefix('$'),

                Forms\Components\Textarea::make('reason')
                    ->label('Motivo')
                    ->required()
                    ->rows(3)
                    ->maxLength(2000),

                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->rows(3)
                    ->maxLength(3000),
            ])
            ->requiresConfirmation()
            ->modalHeading('Transferir entre cajas chicas')
            ->modalDescription(
                'Se generará una solicitud y el dinero se moverá únicamente después de completar el flujo de aprobación.'
            )
            ->visible(function (PettyCashFund $record): bool {
                if (! (
                    auth()->user()?->can('expenses.admin')
                    || auth()->user()?->can('petty_cash.transfer')
                )) {
                    return false;
                }

                if (
                    ! $record->is_active
                    || (string) $record->status !== 'active'
                ) {
                    return false;
                }

                return (float) (
                    $record->treasuryAccount?->current_balance ?? 0
                ) > 0.000001;
            })
            ->action(function (
                PettyCashFund $record,
                array $data
            ): void {
                try {
                    $request = app(
                        PettyCashTransferService::class
                    )->requestFundTransfer(
                        $record,
                        (int) $data[
                            'destination_petty_cash_fund_id'
                        ],
                        (float) $data['amount'],
                        auth()->id(),
                        (string) $data['reason'],
                        $data['notes'] ?? null
                    );

                    Notification::make()
                        ->title(
                            'Solicitud de transferencia creada'
                        )
                        ->body(
                            'Tesorería '
                            . (
                                $request->number
                                ?: ('#' . $request->id)
                            )
                            . ' fue enviada al flujo de aprobación.'
                        )
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title(
                            'No se pudo crear la transferencia'
                        )
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    protected static function fundingSourceOptions(
        ?PettyCashFund $record = null
    ): array {
        $companyId = $record?->company_id
            ?: (int) (Filament::getTenant()?->getKey() ?? 0);

        if ($companyId <= 0) {
            return [];
        }

        return FundingSource::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(
                fn (FundingSource $source): array => [
                    $source->id =>
                        ($source->code ? $source->code . ' · ' : '')
                        . $source->name,
                ]
            )
            ->toArray();
    }

    public static function initialFundingAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('initialFunding')
            ->label('Fondear')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->form([
                Forms\Components\TextInput::make('amount')
                    ->label('Monto a fondear')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->prefix('$'),

                Forms\Components\Select::make('source_treasury_account_id')
                    ->label('Cuenta origen')
                    ->options(
                        fn (PettyCashFund $record): array =>
                            static::fundingAccountOptions($record)
                    )
                    ->default(
                        fn (PettyCashFund $record): ?int =>
                            $record->funding_treasury_account_id
                                ? (int) $record->funding_treasury_account_id
                                : null
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText(
                        'Cuenta real de Tesorería de donde saldrá el dinero.'
                    ),

                Forms\Components\Select::make('funding_source_id')
                    ->label('Origen de fondos')
                    ->options(
                        fn (PettyCashFund $record): array =>
                            static::fundingSourceOptions($record)
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->placeholder('Selecciona el origen del dinero'),

                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->modalHeading('Fondear caja chica')
            ->modalDescription(
                'Se creará una solicitud de Tesorería y deberá pasar por el flujo general de aprobación.'
            )
            ->visible(function (PettyCashFund $record): bool {
                if (! (
                    auth()->user()?->can('expenses.admin')
                    || auth()->user()?->can('petty_cash.transfer')
                )) {
                    return false;
                }

                if (! $record->is_active || (string) $record->status !== 'active') {
                    return false;
                }

                return (float) ($record->treasuryAccount?->current_balance ?? 0) <= 0.000001;
            })
            ->action(function (PettyCashFund $record, array $data): void {
                try {
                    $request = app(PettyCashTransferService::class)
                        ->requestInitialFunding(
                            $record,
                            (float) $data['amount'],
                            auth()->id(),
                            $data['notes'] ?? null,
                            isset($data['funding_source_id'])
                                ? (int) $data['funding_source_id']
                                : null,
                            isset($data['source_treasury_account_id'])
                                ? (int) $data['source_treasury_account_id']
                                : null
                        );

                    Notification::make()
                        ->title('Solicitud de fondeo creada')
                        ->body(
                            'Tesorería '
                            . ($request->number ?: ('#' . $request->id))
                            . ' fue enviada al flujo de aprobación.'
                        )
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('No se pudo solicitar el fondeo')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function replenishmentAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('replenishment')
            ->label('Reponer')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->form([
                Forms\Components\TextInput::make('amount')
                    ->label('Monto a reponer')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->prefix('$'),

                Forms\Components\Select::make('source_treasury_account_id')
                    ->label('Cuenta origen')
                    ->options(
                        fn (PettyCashFund $record): array =>
                            static::fundingAccountOptions($record)
                    )
                    ->default(
                        fn (PettyCashFund $record): ?int =>
                            $record->funding_treasury_account_id
                                ? (int) $record->funding_treasury_account_id
                                : null
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText(
                        'Cuenta real de Tesorería de donde saldrá el dinero.'
                    ),

                Forms\Components\Select::make('funding_source_id')
                    ->label('Origen de fondos')
                    ->options(
                        fn (PettyCashFund $record): array =>
                            static::fundingSourceOptions($record)
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->placeholder('Selecciona el origen del dinero'),

                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->modalHeading('Reponer caja chica')
            ->modalDescription(
                'El saldo final no podrá superar el monto autorizado.'
            )
            ->visible(function (PettyCashFund $record): bool {
                if (! (
                    auth()->user()?->can('expenses.admin')
                    || auth()->user()?->can('petty_cash.transfer')
                )) {
                    return false;
                }

                if (! $record->is_active || (string) $record->status !== 'active') {
                    return false;
                }

                $balance = (float) ($record->treasuryAccount?->current_balance ?? 0);
                $authorized = (float) $record->authorized_amount;

                return $balance > 0.000001
                    && $balance + 0.000001 < $authorized;
            })
            ->action(function (PettyCashFund $record, array $data): void {
                try {
                    $request = app(PettyCashTransferService::class)
                        ->requestReplenishment(
                            $record,
                            (float) $data['amount'],
                            auth()->id(),
                            $data['notes'] ?? null,
                            isset($data['funding_source_id'])
                                ? (int) $data['funding_source_id']
                                : null,
                            isset($data['source_treasury_account_id'])
                                ? (int) $data['source_treasury_account_id']
                                : null
                        );

                    Notification::make()
                        ->title('Solicitud de reposición creada')
                        ->body(
                            'Tesorería '
                            . ($request->number ?: ('#' . $request->id))
                            . ' fue enviada al flujo de aprobación.'
                        )
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('No se pudo solicitar la reposición')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function returnCashAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('returnCash')
            ->label('Devolver')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->form([
                Forms\Components\TextInput::make('amount')
                    ->label('Monto a devolver')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->prefix('$'),

                Forms\Components\Textarea::make('notes')
                    ->label('Motivo / notas')
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->modalHeading('Devolver efectivo')
            ->modalDescription(
                'Se transferirá de la caja chica hacia su caja de fondeo, después de aprobación.'
            )
            ->visible(function (PettyCashFund $record): bool {
                if (! (
                    auth()->user()?->can('expenses.admin')
                    || auth()->user()?->can('petty_cash.transfer')
                )) {
                    return false;
                }

                return $record->is_active
                    && (string) $record->status === 'active'
                    && (float) ($record->treasuryAccount?->current_balance ?? 0) > 0.000001;
            })
            ->action(function (PettyCashFund $record, array $data): void {
                try {
                    $request = app(PettyCashTransferService::class)
                        ->requestReturn(
                            $record,
                            (float) $data['amount'],
                            auth()->id(),
                            $data['notes'] ?? null
                        );

                    Notification::make()
                        ->title('Solicitud de devolución creada')
                        ->body(
                            'Tesorería '
                            . ($request->number ?: ('#' . $request->id))
                            . ' fue enviada al flujo de aprobación.'
                        )
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('No se pudo solicitar la devolución')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Caja chica')
                ->schema([
                    Infolists\Components\TextEntry::make('number')
                        ->label('Folio'),

                    Infolists\Components\TextEntry::make('employee.name')
                        ->label('Responsable'),

                    Infolists\Components\TextEntry::make('name')
                        ->label('Nombre'),

                    Infolists\Components\TextEntry::make('authorized_amount')
                        ->label('Monto autorizado')
                        ->money(
                            fn (PettyCashFund $record): string =>
                                $record->currency_code ?: 'MXN'
                        ),

                    Infolists\Components\TextEntry::make(
                        'treasuryAccount.current_balance'
                    )
                        ->label('Saldo real en Tesorería')
                        ->money(
                            fn (PettyCashFund $record): string =>
                                $record->currency_code ?: 'MXN'
                        ),

                    Infolists\Components\TextEntry::make('operational_balance')
                        ->label('Saldo operativo')
                        ->money(
                            fn (PettyCashFund $record): string =>
                                $record->currency_code ?: 'MXN'
                        ),

                    Infolists\Components\TextEntry::make(
                        'fundingTreasuryAccount.name'
                    )
                        ->label('Caja origen')
                        ->placeholder('Sin configurar'),

                    Infolists\Components\TextEntry::make(
                        'treasuryAccount.name'
                    )
                        ->label('Cuenta Tesorería'),

                    Infolists\Components\TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => match ($state) {
                            'active' => 'Activa',
                            'suspended' => 'Suspendida',
                            'closed' => 'Cerrada',
                            default => $state ?: '—',
                        })
                        ->color(fn (?string $state): string => match ($state) {
                            'active' => 'success',
                            'suspended' => 'warning',
                            'closed' => 'gray',
                            default => 'gray',
                        }),

                    Infolists\Components\TextEntry::make('notes')
                        ->label('Notas')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    protected static function employeeOptions(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return Employee::query()
            ->when(
                $tenantId,
                fn ($query) => $query->where('company_id', $tenantId)
            )
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function fundingAccountOptions(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return TreasuryAccount::query()
            ->when(
                $tenantId,
                fn ($query) => $query->where('company_id', $tenantId)
            )
            ->where('is_active', true)
            ->where(function ($query): void {
                $query
                    ->whereNull('cash_scope')
                    ->orWhere('cash_scope', '<>', 'petty_cash');
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPettyCashFunds::route('/'),
            'create' => Pages\CreatePettyCashFund::route('/create'),
            'view' => Pages\ViewPettyCashFund::route('/{record}'),
            'edit' => Pages\EditPettyCashFund::route('/{record}/edit'),
        ];
    }
}
