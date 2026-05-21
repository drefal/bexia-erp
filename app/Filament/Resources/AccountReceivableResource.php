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

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(42),

                Tables\Columns\TextColumn::make('customer_reference')
                    ->label('Referencia')
                    ->searchable()
                    ->toggleable(),

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
                    ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency)
                    ->sortable(),

                Tables\Columns\TextColumn::make('collected_total')
                    ->label('Cobrado')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency)
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance_total')
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
                    ->columns(3)
                    ->schema([
                        TextEntry::make('number')->label('Folio'),

                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                            ->color(fn (?string $state): string => static::statusColor($state)),

                        TextEntry::make('customer_name')->label('Cliente'),
                        TextEntry::make('customer_reference')->label('Referencia cliente')->placeholder('Sin referencia'),
                        TextEntry::make('sale_order_id')->label('Venta')->placeholder('Sin venta'),
                        TextEntry::make('invoice_id')->label('Factura')->placeholder('Sin factura'),
                        TextEntry::make('issue_date')->label('Fecha')->date(),
                        TextEntry::make('due_date')->label('Vencimiento')->date(),
                        TextEntry::make('currency')->label('Moneda'),
                    ]),

                Section::make('Importes')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('tax_total')
                            ->label('Impuestos')
                            ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('total')
                            ->label('Total')
                            ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('collected_total')
                            ->label('Cobrado')
                            ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('balance_total')
                            ->label('Saldo')
                            ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                    ]),

                Section::make('Conexión contable')
                    ->description('CxC es un módulo separado. Estos campos mostrarán la póliza generada cuando se contabilice.')
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
}
