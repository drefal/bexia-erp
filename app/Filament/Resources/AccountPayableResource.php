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
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier_name')
                    ->label('Proveedor')
                    ->searchable()
                    ->limit(42),

                Tables\Columns\TextColumn::make('purchaseReceipt.number')
                    ->label('Recepción')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                    ->color(fn (?string $state): string => static::statusColor($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('issue_date')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Vence')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, AccountPayable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency)
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_total')
                    ->label('Pagado')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, AccountPayable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency)
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance_total')
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
                    ->columns(3)
                    ->schema([
                        TextEntry::make('number')->label('Folio'),

                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                            ->color(fn (?string $state): string => static::statusColor($state)),

                        TextEntry::make('supplier_name')->label('Proveedor'),
                        TextEntry::make('purchaseOrder.number')->label('Orden de compra')->placeholder('Sin orden'),
                        TextEntry::make('purchaseReceipt.number')->label('Recepción')->placeholder('Sin recepción'),
                        TextEntry::make('supplier_reference')->label('Referencia proveedor')->placeholder('Sin referencia'),
                        TextEntry::make('issue_date')->label('Fecha')->date(),
                        TextEntry::make('due_date')->label('Vencimiento')->date(),
                        TextEntry::make('currency')->label('Moneda'),
                    ]),

                Section::make('Importes')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->formatStateUsing(fn ($state, AccountPayable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('tax_total')
                            ->label('Impuestos')
                            ->formatStateUsing(fn ($state, AccountPayable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('total')
                            ->label('Total')
                            ->formatStateUsing(fn ($state, AccountPayable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('paid_total')
                            ->label('Pagado')
                            ->formatStateUsing(fn ($state, AccountPayable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('balance_total')
                            ->label('Saldo')
                            ->formatStateUsing(fn ($state, AccountPayable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                    ]),

                Section::make('Conexión contable')
                    ->description('CxP es un módulo separado. Estos campos solo muestran la póliza generada cuando se contabilice.')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('accounting_status')
                            ->label('Estado contable')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => static::accountingStatusLabel($state))
                            ->color(fn (?string $state): string => static::accountingStatusColor($state)),

                        TextEntry::make('accounting_entry_id')->label('Póliza')->placeholder('Sin póliza'),
                        TextEntry::make('accounting_posted_at')->label('Contabilizado')->dateTime()->placeholder('Pendiente'),
                        TextEntry::make('accounting_error_message')->label('Error contable')->columnSpanFull()->placeholder('Sin error'),
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
}
