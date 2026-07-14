<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TreasuryCashTransferRequestResource\Pages;
use App\Models\TreasuryCashTransferRequest;
use App\Support\Treasury\CashTransferService;
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
use Illuminate\Support\Facades\DB;
use Throwable;

// BEXIA_TREASURY_CASH_TRANSFER_REQUEST_RESOURCE_RESPONSIVE_V5_79_32C
class TreasuryCashTransferRequestResource extends Resource
{
    protected static ?string $model = TreasuryCashTransferRequest::class;

    /*
     * El panel Filament usa tenant por URL (/admin/{tenant}), pero este modelo
     * no tiene relacion companies(). Filtramos manualmente por company_id en
     * getEloquentQuery(), por eso se desactiva el ownership relationship.
     */
    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Tesorería';

    protected static ?string $navigationLabel = 'Solicitudes de efectivo';

    protected static ?string $modelLabel = 'Solicitud de efectivo';

    protected static ?string $pluralModelLabel = 'Solicitudes de efectivo';

    protected static ?int $navigationSort = 25;


    public static function getBreadcrumb(): string
    {
        return 'Solicitudes de efectivo';
    }

    public static function getNavigationBadge(): ?string
    {
        // BEXIA_V582_PERF7A_NAV_BADGE_DISABLED: evita conteos caros en cada carga del panel.
        return null;

        $companyId = static::currentCompanyId();

        $query = static::getModel()::query()
            ->whereIn('status', ['requested', 'approved', 'delivered']);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $count = $query->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->can('treasury.view');
    }

    public static function canView($record): bool
    {
        return auth()->check() && auth()->user()->can('treasury.view');
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->can('treasury.create');
    }

    public static function canEdit($record): bool
    {
        return auth()->check()
            && auth()->user()->can('treasury.update')
            && in_array((string) $record->status, ['draft', 'requested'], true);
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        /*
         * No usamos parent::getEloquentQuery() porque el panel tenant intenta
         * aplicar una relacion ownership por defecto. Este recurso se filtra
         * manualmente por company_id usando el tenant de la URL.
         */
        $model = static::getModel();
        $query = $model::query();

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->latest('created_at');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Flujo de efectivo')
                    ->extraAttributes(['class' => 'bexia-treasury-cash-transfer-section bexia-treasury-cash-transfer-flow-section'])
                    ->description('Define de qué caja sale el dinero y a qué caja entra. La sucursal, almacén o PDV se determinan por las cajas seleccionadas.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->extraAttributes(['class' => 'bexia-treasury-cash-transfer-field bexia-treasury-cash-transfer-type-field'])
                            ->required()
                            ->options(static::typeLabels())
                            ->default('transfer')
                            ->searchable(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Monto')
                            ->extraAttributes(['class' => 'bexia-treasury-cash-transfer-field bexia-treasury-cash-transfer-amount-field'])
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('$')
                            ->step('0.01'),

                        Forms\Components\Select::make('source_treasury_account_id')
                            ->label('Caja origen')
                            ->extraAttributes(['class' => 'bexia-treasury-cash-transfer-field bexia-treasury-cash-transfer-source-account-field'])
                            ->options(fn (): array => static::treasuryAccountOptions())
                            ->searchable()
                            ->preload()
                            ->helperText('Para retiro de PDV, selecciona la caja del PDV.'),

                        Forms\Components\Select::make('destination_treasury_account_id')
                            ->label('Caja destino')
                            ->extraAttributes(['class' => 'bexia-treasury-cash-transfer-field bexia-treasury-cash-transfer-destination-account-field'])
                            ->options(fn (): array => static::treasuryAccountOptions())
                            ->searchable()
                            ->preload()
                            ->helperText('Para retiro de PDV, selecciona la caja de tienda/sucursal.'),

                        Forms\Components\TextInput::make('currency_code')
                            ->label('Moneda')
                            ->extraAttributes(['class' => 'bexia-treasury-cash-transfer-field bexia-treasury-cash-transfer-currency-field'])
                            ->default('MXN')
                            ->required()
                            ->maxLength(10),
                    ]),

                Forms\Components\Section::make('Justificación')
                    ->extraAttributes(['class' => 'bexia-treasury-cash-transfer-section bexia-treasury-cash-transfer-justification-section'])
                    ->columns(1)
                    ->schema([
                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo')
                            ->extraAttributes(['class' => 'bexia-treasury-cash-transfer-field bexia-treasury-cash-transfer-reason-field'])
                            ->rows(3)
                            ->required()
                            ->maxLength(2000),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas internas')
                            ->extraAttributes(['class' => 'bexia-treasury-cash-transfer-field bexia-treasury-cash-transfer-notes-field'])
                            ->rows(3)
                            ->maxLength(3000),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Solicitud')
                    ->extraAttributes(['class' => 'bexia-treasury-cash-transfer-infolist-section bexia-treasury-cash-transfer-request-section'])
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('number')
                            ->label('Folio')
                            ->placeholder('Sin folio'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                            ->color(fn (?string $state): string => static::statusColor($state)),

                        Infolists\Components\TextEntry::make('type')
                            ->label('Tipo')
                            ->formatStateUsing(fn (?string $state): string => static::typeLabel($state)),

                        Infolists\Components\TextEntry::make('amount')
                            ->label('Monto')
                            ->money('MXN'),

                        Infolists\Components\TextEntry::make('currency_code')
                            ->label('Moneda'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Creada')
                            ->dateTime('d/m/Y H:i'),
                    ]),

                Infolists\Components\Section::make('Cajas')
                    ->extraAttributes(['class' => 'bexia-treasury-cash-transfer-infolist-section bexia-treasury-cash-transfer-accounts-section'])
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('source_treasury_account_id')
                            ->label('Caja origen')
                            ->formatStateUsing(fn ($state): string => static::accountLabel($state)),

                        Infolists\Components\TextEntry::make('destination_treasury_account_id')
                            ->label('Caja destino')
                            ->formatStateUsing(fn ($state): string => static::accountLabel($state)),
                    ]),

                Infolists\Components\Section::make('Motivo y notas')
                    ->extraAttributes(['class' => 'bexia-treasury-cash-transfer-infolist-section bexia-treasury-cash-transfer-notes-section'])
                    ->schema([
                        Infolists\Components\TextEntry::make('reason')
                            ->label('Motivo')
                            ->placeholder('Sin motivo'),

                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('Sin notas'),

                        Infolists\Components\TextEntry::make('rejection_reason')
                            ->label('Motivo de rechazo')
                            ->placeholder('Sin rechazo'),
                    ]),

                Infolists\Components\Section::make('Aprobación electrónica')
                    ->extraAttributes(['class' => 'bexia-treasury-cash-transfer-infolist-section bexia-treasury-cash-transfer-approval-section'])
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('requested_by_user_id')
                            ->label('Solicitó')
                            ->formatStateUsing(fn ($state): string => static::userLabel($state)),

                        Infolists\Components\TextEntry::make('requested_at')
                            ->label('Fecha solicitud')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Pendiente'),

                        Infolists\Components\TextEntry::make('approved_by_user_id')
                            ->label('Aprobó')
                            ->formatStateUsing(fn ($state): string => static::userLabel($state)),

                        Infolists\Components\TextEntry::make('approved_at')
                            ->label('Fecha aprobación')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Pendiente'),

                        Infolists\Components\TextEntry::make('received_by_user_id')
                            ->label('Recibió')
                            ->formatStateUsing(fn ($state): string => static::userLabel($state)),

                        Infolists\Components\TextEntry::make('received_at')
                            ->label('Fecha recepción')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Pendiente'),
                    ]),

                Infolists\Components\Section::make('Bitácora')
                    ->extraAttributes(['class' => 'bexia-treasury-cash-transfer-infolist-section bexia-treasury-cash-transfer-log-section'])
                    ->schema([
                        Infolists\Components\TextEntry::make('id')
                            ->label('Eventos')
                            ->formatStateUsing(fn ($state): string => static::approvalLogText((int) $state))
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Folio')
                    ->searchable()
                    ->placeholder('Sin folio')
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-treasury-cash-transfer-col-number'])
                    ->extraCellAttributes(['class' => 'bexia-treasury-cash-transfer-col-number']),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                    ->color(fn (?string $state): string => static::statusColor($state))
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'bexia-treasury-cash-transfer-col-status'])
                    ->extraCellAttributes(['class' => 'bexia-treasury-cash-transfer-col-status']),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => static::typeLabel($state))
                    ->sortable()
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-treasury-cash-transfer-col-type'])
                    ->extraCellAttributes(['class' => 'bexia-treasury-cash-transfer-col-type']),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('MXN')
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'bexia-treasury-cash-transfer-col-amount'])
                    ->extraCellAttributes(['class' => 'bexia-treasury-cash-transfer-col-amount']),

                Tables\Columns\TextColumn::make('source_treasury_account_id')
                    ->label('Origen')
                    ->formatStateUsing(fn ($state): string => static::accountShortLabel($state))
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-treasury-cash-transfer-col-source'])
                    ->extraCellAttributes(['class' => 'bexia-treasury-cash-transfer-col-source']),

                Tables\Columns\TextColumn::make('destination_treasury_account_id')
                    ->label('Destino')
                    ->formatStateUsing(fn ($state): string => static::accountShortLabel($state))
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-treasury-cash-transfer-col-destination'])
                    ->extraCellAttributes(['class' => 'bexia-treasury-cash-transfer-col-destination']),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'bexia-treasury-cash-transfer-col-created'])
                    ->extraCellAttributes(['class' => 'bexia-treasury-cash-transfer-col-created']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(static::statusLabels()),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(static::typeLabels()),

                Tables\Filters\SelectFilter::make('source_treasury_account_id')
                    ->label('Caja origen')
                    ->options(fn (): array => static::treasuryAccountOptions()),

                Tables\Filters\SelectFilter::make('destination_treasury_account_id')
                    ->label('Caja destino')
                    ->options(fn (): array => static::treasuryAccountOptions()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\EditAction::make()
                    ->visible(fn (TreasuryCashTransferRequest $record): bool => static::canEdit($record)),

                static::approveAction(),
                static::rejectAction(),
                static::deliverAction(),
                static::receiveAction(),
                static::postAction(),
                static::cancelAction(),
            ])
            ->bulkActions([]);
    }

    public static function approveAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('approve')
            ->label('Aprobar en flujo general')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Aprobar solicitud de efectivo')
            ->modalDescription('La aprobación se atiende desde Mis aprobaciones usando el flujo general configurado.')
            ->form([
                Forms\Components\Textarea::make('notes')
                    ->label('Notas de aprobación')
                    ->rows(3),
            ])
            ->visible(fn (TreasuryCashTransferRequest $record): bool => auth()->user()?->can('treasury.update') && in_array((string) $record->status, ['draft', 'requested'], true))
            ->action(function (TreasuryCashTransferRequest $record, array $data): void {
                try {
                    app(CashTransferService::class)->approve($record->id, auth()->id(), $data['notes'] ?? null);

                    Notification::make()
                        ->title('Solicitud aprobada')
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    static::notifyError($e);
                }
            });
    }

    public static function rejectAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('reject')
            ->label('Rechazar')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Rechazar solicitud de efectivo')
            ->form([
                Forms\Components\Textarea::make('reason')
                    ->label('Motivo de rechazo')
                    ->required()
                    ->rows(3),
            ])
            ->visible(fn (TreasuryCashTransferRequest $record): bool => auth()->user()?->can('treasury.update') && in_array((string) $record->status, ['draft', 'requested', 'approved'], true))
            ->action(function (TreasuryCashTransferRequest $record, array $data): void {
                try {
                    app(CashTransferService::class)->reject($record->id, auth()->id(), $data['reason'] ?? null);

                    Notification::make()
                        ->title('Solicitud rechazada')
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    static::notifyError($e);
                }
            });
    }

    public static function deliverAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('deliver')
            ->label('Entregado')
            ->icon('heroicon-o-truck')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Marcar efectivo como entregado')
            ->form([
                Forms\Components\Textarea::make('notes')
                    ->label('Notas de entrega')
                    ->rows(3),
            ])
            ->visible(fn (TreasuryCashTransferRequest $record): bool => auth()->user()?->can('treasury.update') && (string) $record->status === 'approved')
            ->action(function (TreasuryCashTransferRequest $record, array $data): void {
                try {
                    app(CashTransferService::class)->markDelivered($record->id, auth()->id(), $data['notes'] ?? null);

                    Notification::make()
                        ->title('Solicitud marcada como entregada')
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    static::notifyError($e);
                }
            });
    }

    public static function receiveAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('receive')
            ->label('Recibido')
            ->icon('heroicon-o-inbox-arrow-down')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Marcar efectivo como recibido')
            ->form([
                Forms\Components\Textarea::make('notes')
                    ->label('Notas de recepción')
                    ->rows(3),
            ])
            ->visible(fn (TreasuryCashTransferRequest $record): bool => auth()->user()?->can('treasury.update') && (string) $record->status === 'delivered')
            ->action(function (TreasuryCashTransferRequest $record, array $data): void {
                try {
                    app(CashTransferService::class)->markReceived($record->id, auth()->id(), $data['notes'] ?? null);

                    Notification::make()
                        ->title('Solicitud marcada como recibida')
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    static::notifyError($e);
                }
            });
    }

    public static function postAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('post')
            ->label('Aplicar')
            ->icon('heroicon-o-banknotes')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Aplicar traspaso de efectivo')
            ->modalDescription('Esta acción afectará los saldos: resta de la caja origen y suma a la caja destino.')
            ->visible(fn (TreasuryCashTransferRequest $record): bool => auth()->user()?->can('treasury.update') && in_array((string) $record->status, ['approved', 'delivered', 'received'], true) && blank($record->posted_at))
            ->action(function (TreasuryCashTransferRequest $record): void {
                try {
                    $result = app(CashTransferService::class)->postApprovedTransfer($record->id, auth()->id());

                    Notification::make()
                        ->title('Traspaso contabilizado')
                        ->body('Salida #' . ($result['outflow_movement_id'] ?? 'N/A') . ' / Entrada #' . ($result['inflow_movement_id'] ?? 'N/A'))
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    static::notifyError($e);
                }
            });
    }

    public static function cancelAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('cancel')
            ->label('Cancelar')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Cancelar solicitud de efectivo')
            ->form([
                Forms\Components\Textarea::make('notes')
                    ->label('Motivo / notas de cancelación')
                    ->required()
                    ->rows(3),
            ])
            ->visible(fn (TreasuryCashTransferRequest $record): bool => auth()->user()?->can('treasury.update') && ! in_array((string) $record->status, ['posted', 'cancelled', 'rejected'], true))
            ->action(function (TreasuryCashTransferRequest $record, array $data): void {
                try {
                    app(CashTransferService::class)->cancel($record->id, auth()->id(), $data['notes'] ?? null);

                    Notification::make()
                        ->title('Solicitud cancelada')
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    static::notifyError($e);
                }
            });
    }


    public static function cashTransferStatusLabel(?string $state): string
    {
        return match ((string) $state) {
            'draft' => 'Borrador',
            'requested' => 'Solicitada',
            'pending_approval' => 'Pendiente de aprobación',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'cancelled' => 'Cancelada',
            'posted' => 'Aplicada',
            'completed' => 'Completada',
            default => $state ? str_replace('_', ' ', ucfirst((string) $state)) : '-',
        };
    }

    public static function cashTransferStatusColor(?string $state): string
    {
        return match ((string) $state) {
            'draft' => 'gray',
            'requested', 'pending_approval' => 'warning',
            'approved' => 'success',
            'rejected', 'cancelled' => 'danger',
            'posted', 'completed' => 'primary',
            default => 'gray',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTreasuryCashTransferRequests::route('/'),
            'create' => Pages\CreateTreasuryCashTransferRequest::route('/create'),
            'view' => Pages\ViewTreasuryCashTransferRequest::route('/{record}'),
            'edit' => Pages\EditTreasuryCashTransferRequest::route('/{record}/edit'),
        ];
    }

    public static function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant?->id ? (int) $tenant->id : null;
    }

    public static function treasuryAccountOptions(): array
    {
        $companyId = static::currentCompanyId();

        $query = DB::table('treasury_accounts')
            ->where('is_active', true)
            ->orderBy('cash_scope')
            ->orderBy('name');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->get(['id', 'name', 'cash_scope', 'type', 'current_balance', 'currency_code'])
            ->mapWithKeys(function ($row): array {
                $scope = static::cashScopeLabel($row->cash_scope ?? $row->type ?? null);
                $balance = number_format((float) $row->current_balance, 2);
                $currency = $row->currency_code ?: 'MXN';

                return [
                    $row->id => '[' . $scope . '] ' . $row->name . ' - ' . $currency . ' $' . $balance,
                ];
            })
            ->toArray();
    }

    public static function branchOptions(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('branches')) {
            return [];
        }

        $companyId = static::currentCompanyId();

        $query = DB::table('branches')->orderBy('name');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->get(['id', 'name', 'code'])
            ->mapWithKeys(fn ($row): array => [$row->id => trim(($row->code ? $row->code . ' - ' : '') . $row->name)])
            ->toArray();
    }

    public static function warehouseOptions(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('warehouses')) {
            return [];
        }

        $companyId = static::currentCompanyId();

        $query = DB::table('warehouses')->orderBy('name');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->get(['id', 'name', 'code'])
            ->mapWithKeys(fn ($row): array => [$row->id => trim(($row->code ? $row->code . ' - ' : '') . $row->name)])
            ->toArray();
    }

    public static function posPointOptions(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('pos_points')) {
            return [];
        }

        $companyId = static::currentCompanyId();

        $query = DB::table('pos_points')->orderBy('name');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->get(['id', 'name', 'code'])
            ->mapWithKeys(fn ($row): array => [$row->id => trim(($row->code ? $row->code . ' - ' : '') . $row->name)])
            ->toArray();
    }

    public static function statusLabels(): array
    {
        return [
            'draft' => 'Borrador',
            'requested' => 'Solicitada',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'delivered' => 'Entregada',
            'received' => 'Recibida',
            'posted' => 'Contabilizada',
            'cancelled' => 'Cancelada',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return static::statusLabels()[$status ?: ''] ?? ($status ?: 'Sin estado');
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'draft' => 'gray',
            'requested', 'pending_approval' => 'warning',
            'approved' => 'success',
            'delivered' => 'info',
            'received' => 'primary',
            'posted' => 'success',
            'rejected', 'cancelled' => 'danger',
            default => 'gray',
        };
    }

    public static function typeLabels(): array
    {
        return [
            'transfer' => 'Traspaso entre cajas',
            'pos_withdrawal' => 'Retiro de PDV a sucursal',
            'branch_to_admin' => 'Sucursal a administración',
            'admin_to_bank' => 'Administración a banco',
            'branch_to_branch' => 'Sucursal a sucursal',
            'cash_adjustment' => 'Ajuste de efectivo',
        ];
    }

    public static function typeLabel(?string $type): string
    {
        return static::typeLabels()[$type ?: ''] ?? ($type ?: 'Sin tipo');
    }

    public static function cashScopeLabel(?string $scope): string
    {
        return match ($scope) {
            'pdv' => 'PDV',
            'branch_cash' => 'Sucursal',
            'admin_cash' => 'Administración',
            'general_cash' => 'Caja general',
            'bank' => 'Banco',
            default => $scope ?: 'Caja',
        };
    }

    public static function accountLabel($id): string
    {
        if (! $id) {
            return 'Sin caja';
        }

        $row = DB::table('treasury_accounts')->where('id', $id)->first();

        if (! $row) {
            return 'Caja #' . $id;
        }

        return '[' . static::cashScopeLabel($row->cash_scope ?? $row->type ?? null) . '] '
            . $row->name
            . ' - '
            . ($row->currency_code ?: 'MXN')
            . ' $'
            . number_format((float) $row->current_balance, 2);
    }

    public static function accountShortLabel($id): string
    {
        if (! $id) {
            return 'Sin caja';
        }

        $row = DB::table('treasury_accounts')->where('id', $id)->first();

        if (! $row) {
            return 'Caja #' . $id;
        }

        return $row->name;
    }

    public static function branchLabel($id): string
    {
        if (! $id) {
            return 'Sin sucursal';
        }

        $row = DB::table('branches')->where('id', $id)->first();

        return $row ? trim(($row->code ? $row->code . ' - ' : '') . $row->name) : 'Sucursal #' . $id;
    }

    public static function posPointLabel($id): string
    {
        if (! $id) {
            return 'Sin PDV';
        }

        $row = DB::table('pos_points')->where('id', $id)->first();

        return $row ? trim(($row->code ? $row->code . ' - ' : '') . $row->name) : 'PDV #' . $id;
    }

    public static function userLabel($id): string
    {
        if (! $id) {
            return 'Pendiente';
        }

        $row = DB::table('users')->where('id', $id)->first();

        return $row?->name ?: ('Usuario #' . $id);
    }

    public static function approvalLogText(int $requestId): string
    {
        $logs = DB::table('treasury_cash_transfer_approval_logs')
            ->where('treasury_cash_transfer_request_id', $requestId)
            ->orderBy('created_at')
            ->get();

        if ($logs->isEmpty()) {
            return '<span class="bexia-treasury-cash-transfer-log-empty text-gray-500">Sin eventos registrados.</span>';
        }

        return $logs
            ->map(function ($log): string {
                $user = static::userLabel($log->user_id);
                $date = $log->created_at ? date('d/m/Y H:i', strtotime((string) $log->created_at)) : '';
                $from = $log->from_status ? static::statusLabel($log->from_status) : 'Inicio';
                $to = $log->to_status ? static::statusLabel($log->to_status) : '';
                $notes = $log->notes ? '<br><span class="bexia-treasury-cash-transfer-log-note text-gray-500">' . e($log->notes) . '</span>' : '';

                return '<div class="bexia-treasury-cash-transfer-log-item mb-2">'
                    . '<strong>' . e($date) . '</strong> - '
                    . e($user) . ' - '
                    . e($log->action) . ' - '
                    . e($from . ' → ' . $to)
                    . $notes
                    . '</div>';
            })
            ->implode('');
    }

    public static function notifyError(Throwable $e): void
    {
        report($e);

        Notification::make()
            ->title('No se pudo completar la acción')
            ->body($e->getMessage())
            ->danger()
            ->send();
    }
}
