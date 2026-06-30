<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountReceivableResource\Pages;
use App\Models\AccountReceivable;
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

class AccountReceivableResource extends Resource
{
    protected static ?string $model = AccountReceivable::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Cuentas por cobrar';

    protected static ?string $navigationLabel = 'Cuentas por cobrar';

    protected static ?string $modelLabel = 'cuenta por cobrar';

    protected static ?string $pluralModelLabel = 'cuentas por cobrar';

    protected static ?int $navigationSort = 10;

    public static function statusLabel(?string $state): string
    {
        return match ($state) {
            'draft' => 'Borrador',
            'open' => 'Pendiente de cobro',
            'partial' => 'Cobro parcial',
            'paid' => 'Cobrada',
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
        return match ((string) $state) {
            'posted' => 'Contabilizado',
            'not_posted' => 'Pendiente',
            'posting_error' => 'Error al contabilizar',
            'error' => 'Error',
            'cancelled' => 'Cancelado',
            default => filled($state) ? ucfirst(str_replace('_', ' ', (string) $state)) : 'Pendiente',
        };
    }


    public static function accountingStatusColor(?string $state): string
    {
        return match ((string) $state) {
            'posted' => 'success',
            'not_posted' => 'warning',
            'posting_error', 'error' => 'danger',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }



    /*
     * BEXIA_ARCV_RESOURCE_RESPONSIVE_V5_79_45C
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
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-folio bexia-arcv-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-folio bexia-arcv-col-primary'])
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-cust bexia-arcv-col-wide'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-cust bexia-arcv-col-wide'])
                    ->label('Cliente')
                    ->searchable()
                    ->limit(42),

                Tables\Columns\TextColumn::make('customer_reference')
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-custref bexia-arcv-col-wrap'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-custref bexia-arcv-col-wrap'])
                    ->label('Referencia')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-state'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-state'])
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                    ->color(fn (?string $state): string => static::statusColor($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('issue_date')
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-issue'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-issue'])
                    ->label('Fecha')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-due'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-due'])
                    ->label('Vence')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-gross bexia-arcv-col-money'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-gross bexia-arcv-col-money'])
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency)
                    ->sortable(),

                Tables\Columns\TextColumn::make('collected_total')
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-coll bexia-arcv-col-money'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-coll bexia-arcv-col-money'])
                    ->label('Cobrado')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency)
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance_total')
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-bal bexia-arcv-col-money'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-bal bexia-arcv-col-money'])
                    ->label('Saldo')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency)
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'open' => 'Pendiente de cobro',
                        'partial' => 'Cobro parcial',
                        'paid' => 'Cobrada',
                        'cancelled' => 'Cancelada',
                    ]),
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
                    ->extraAttributes(['class' => 'bexia-arcv-section bexia-arcv-section-main'])
                    ->columns(3)
                    ->schema([
                        TextEntry::make('number')->label('Folio')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-folio']),

                        TextEntry::make('status')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-state'])
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                            ->color(fn (?string $state): string => static::statusColor($state)),

                        TextEntry::make('customer_name')->label('Cliente')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-cust']),
                        TextEntry::make('customer_reference')->label('Referencia cliente')->placeholder('Sin referencia')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-custref']),
                        TextEntry::make('sale_order_id')->label('Venta')->placeholder('Sin venta')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-sale']),
                        TextEntry::make('invoice_id')->label('Factura')->placeholder('Sin factura')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-inv']),
                        TextEntry::make('issue_date')->label('Fecha')->date()
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-issue']),
                        TextEntry::make('due_date')->label('Vencimiento')->date()
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-due']),
                        TextEntry::make('currency')->label('Moneda')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-curr']),
                    ]),

                Section::make('Importes')
                    ->extraAttributes(['class' => 'bexia-arcv-section bexia-arcv-section-nums'])
                    ->columns(4)
                    ->schema([
                        TextEntry::make('subtotal')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-sub'])
                            ->label('Subtotal')
                            ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('tax_total')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-tax'])
                            ->label('Impuestos')
                            ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('total')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-gross'])
                            ->label('Total')
                            ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('collected_total')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-coll'])
                            ->label('Cobrado')
                            ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('balance_total')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-bal'])
                            ->label('Saldo')
                            ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                    ]),

                Section::make('Conexión contable')
                    ->extraAttributes(['class' => 'bexia-arcv-section bexia-arcv-section-acctg'])
                    ->description('CxC es un módulo separado. Estos campos mostrarán la póliza generada cuando se contabilice.')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('accounting_status')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-acctgstate'])
                            ->label('Estado contable')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => static::accountingStatusLabel($state))
                            ->color(fn (?string $state): string => static::accountingStatusColor($state)),

                        TextEntry::make('accounting_entry_id')->label('Póliza')->placeholder('Sin póliza')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-pol']),
                        TextEntry::make('accounting_posted_at')->label('Contabilizado')->dateTime()->placeholder('Pendiente')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-acctgdt']),
                        TextEntry::make('accounting_error_message')->label('Error contable')->columnSpanFull()->placeholder('Sin error')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-acctgerr']),
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
        return static::userCanPermission('account_receivables.view');
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::userCanPermission('account_receivables.create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCanPermission('account_receivables.update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCanPermission('account_receivables.cancel');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountReceivables::route('/'),
            'view' => Pages\ViewAccountReceivable::route('/{record}'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.accountreceivableresource',
            fn (): bool => method_exists(static::class, 'canViewAny')
                ? static::canViewAny()
                : (method_exists(static::class, 'canAccess') ? static::canAccess() : true),
        );
    }

}
