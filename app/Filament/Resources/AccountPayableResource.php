<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountPayableResource\Pages;
use App\Models\AccountPayable;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AccountPayableResource extends Resource
{
    protected static ?string $model = AccountPayable::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Cuentas por pagar';

    protected static ?string $navigationLabel = 'Cuentas por pagar';

    protected static ?string $modelLabel = 'cuenta por pagar';

    protected static ?string $pluralModelLabel = 'cuentas por pagar';

    protected static ?int $navigationSort = 10;

    public static function statusLabel(?string $state): string
    {
        return match ($state) {
            'draft' => 'Borrador',
            'open' => 'Pendiente de pago',
            'partial' => 'Pago parcial',
            'paid' => 'Pagada',
            'cancelled' => 'Cancelada',
            default => filled($state) ? (string) $state : 'Sin estado',
        };
    }

    public static function statusColor(?string $state): string
    {
        return match ($state) {
            'draft' => 'gray',
            'open' => 'warning',
            'partial' => 'info',
            'paid' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    public static function accountingStatusLabel(?string $state): string
    {
        return match ($state) {
            null, '' => 'Pendiente',
            'pending' => 'Pendiente',
            'posted' => 'Contabilizada',
            'error' => 'Error',
            'cancelled' => 'Cancelada',
            default => (string) $state,
        };
    }

    public static function accountingStatusColor(?string $state): string
    {
        return match ($state) {
            'posted' => 'success',
            'error' => 'danger',
            'cancelled' => 'gray',
            default => 'warning',
        };
    }


    /*
     * BEXIA_APBL_RESOURCE_RESPONSIVE_V5_79_46C
     * Visual-only responsive marker.
     */

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->persistFiltersInSession()
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->extraHeaderAttributes(['class' => 'bexia-apbl-col-folio bexia-apbl-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-apbl-col-folio bexia-apbl-col-primary'])
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier_name')
                    ->extraHeaderAttributes(['class' => 'bexia-apbl-col-supplier bexia-apbl-col-wide'])
                    ->extraCellAttributes(['class' => 'bexia-apbl-col-supplier bexia-apbl-col-wide'])
                    ->label('Proveedor')
                    ->searchable()
                    ->limit(42),

                Tables\Columns\TextColumn::make('purchaseReceipt.number')
                    ->extraHeaderAttributes(['class' => 'bexia-apbl-col-receipt bexia-apbl-col-wrap'])
                    ->extraCellAttributes(['class' => 'bexia-apbl-col-receipt bexia-apbl-col-wrap'])
                    ->label('Recepción')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->extraHeaderAttributes(['class' => 'bexia-apbl-col-state'])
                    ->extraCellAttributes(['class' => 'bexia-apbl-col-state'])
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                    ->color(fn (?string $state): string => static::statusColor($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('issue_date')
                    ->extraHeaderAttributes(['class' => 'bexia-apbl-col-issue'])
                    ->extraCellAttributes(['class' => 'bexia-apbl-col-issue'])
                    ->label('Fecha')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->extraHeaderAttributes(['class' => 'bexia-apbl-col-due'])
                    ->extraCellAttributes(['class' => 'bexia-apbl-col-due'])
                    ->label('Vence')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->extraHeaderAttributes(['class' => 'bexia-apbl-col-gross bexia-apbl-col-money'])
                    ->extraCellAttributes(['class' => 'bexia-apbl-col-gross bexia-apbl-col-money'])
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, AccountPayable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency)
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_total')
                    ->extraHeaderAttributes(['class' => 'bexia-apbl-col-paid bexia-apbl-col-money'])
                    ->extraCellAttributes(['class' => 'bexia-apbl-col-paid bexia-apbl-col-money'])
                    ->label('Pagado')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, AccountPayable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency)
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance_total')
                    ->extraHeaderAttributes(['class' => 'bexia-apbl-col-bal bexia-apbl-col-money'])
                    ->extraCellAttributes(['class' => 'bexia-apbl-col-bal bexia-apbl-col-money'])
                    ->label('Saldo')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, AccountPayable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency)
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Información general')
                    ->extraAttributes(['class' => 'bexia-apbl-section bexia-apbl-section-main'])
                    ->columns(3)
                    ->schema([
                        TextEntry::make('number')->label('Folio')
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-folio']),

                        TextEntry::make('status')
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-state'])
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                            ->color(fn (?string $state): string => static::statusColor($state)),

                        TextEntry::make('supplier_name')->label('Proveedor')
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-supplier']),
                        TextEntry::make('purchaseOrder.number')->label('Orden de compra')->placeholder('Sin orden')
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-po']),
                        TextEntry::make('purchaseReceipt.number')->label('Recepción')->placeholder('Sin recepción')
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-receipt']),
                        TextEntry::make('supplier_reference')->label('Referencia proveedor')->placeholder('Sin referencia')
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-supplier-ref']),
                        TextEntry::make('issue_date')->label('Fecha')->date()
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-issue']),
                        TextEntry::make('due_date')->label('Vencimiento')->date()
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-due']),
                        TextEntry::make('currency')->label('Moneda')
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-curr']),
                    ]),

                Section::make('Importes')
                    ->extraAttributes(['class' => 'bexia-apbl-section bexia-apbl-section-nums'])
                    ->columns(4)
                    ->schema([
                        TextEntry::make('subtotal')
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-sub'])
                            ->label('Subtotal')
                            ->formatStateUsing(fn ($state, AccountPayable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('tax_total')
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-tax'])
                            ->label('Impuestos')
                            ->formatStateUsing(fn ($state, AccountPayable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('total')
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-gross'])
                            ->label('Total')
                            ->formatStateUsing(fn ($state, AccountPayable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('paid_total')
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-paid'])
                            ->label('Pagado')
                            ->formatStateUsing(fn ($state, AccountPayable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('balance_total')
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-bal'])
                            ->label('Saldo')
                            ->formatStateUsing(fn ($state, AccountPayable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                    ]),

                Section::make('Conexión contable')
                    ->extraAttributes(['class' => 'bexia-apbl-section bexia-apbl-section-acctg'])
                    ->description('CxP es un módulo separado. Estos campos solo muestran la póliza generada cuando se contabilice.')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('accounting_status')
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-acctgstate'])
                            ->label('Estado contable')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => static::accountingStatusLabel($state))
                            ->color(fn (?string $state): string => static::accountingStatusColor($state)),

                        TextEntry::make('accounting_entry_id')->label('Póliza')->placeholder('Sin póliza')
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-pol']),
                        TextEntry::make('accounting_posted_at')->label('Contabilizado')->dateTime()->placeholder('Pendiente')
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-acctgdt']),
                        TextEntry::make('accounting_error_message')->label('Error contable')->columnSpanFull()->placeholder('Sin error')
                            ->extraAttributes(['class' => 'bexia-apbl-item bexia-apbl-item-acctgerr']),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $tenant = Filament::getTenant();

        if ($tenant && method_exists($tenant, 'getKey')) {
            $query->where('company_id', $tenant->getKey());
        }

        return $query;
    }

    protected static function userCanPermission(string $permission): bool
    {
        $user = auth()->user();

        return $user && method_exists($user, 'can') && $user->can($permission);
    }

    public static function canViewAny(): bool
    {
        return static::userCanPermission('account_payables.view');
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::userCanPermission('account_payables.create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCanPermission('account_payables.update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCanPermission('account_payables.cancel');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountPayables::route('/'),
            'view' => Pages\ViewAccountPayable::route('/{record}'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.accountpayableresource',
            fn (): bool => method_exists(static::class, 'canViewAny')
                ? static::canViewAny()
                : (method_exists(static::class, 'canAccess') ? static::canAccess() : true),
        );
    }

}
