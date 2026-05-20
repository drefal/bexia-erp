<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountPayablePaymentResource\Pages;
use App\Models\AccountPayablePayment;
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

class AccountPayablePaymentResource extends Resource
{
    protected static ?string $model = AccountPayablePayment::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Cuentas por pagar';

    protected static ?string $navigationLabel = 'Pagos a proveedores';

    protected static ?string $modelLabel = 'pago a proveedor';

    protected static ?string $pluralModelLabel = 'pagos a proveedores';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('accountPayable.number')
                    ->label('CxP')
                    ->searchable(),

                Tables\Columns\TextColumn::make('accountPayable.supplier_name')
                    ->label('Proveedor')
                    ->searchable()
                    ->limit(42),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Fecha pago')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Importe')
                    ->alignEnd()
                    ->formatStateUsing(function ($state, AccountPayablePayment $record): string {
                        return '$' . number_format((float) $state, 2) . ' ' . $record->currency;
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Referencia')
                    ->searchable()
                    ->placeholder('Sin referencia'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Pago a proveedor')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('accountPayable.number')->label('CxP'),
                        TextEntry::make('accountPayable.supplier_name')->label('Proveedor'),
                        TextEntry::make('status')->label('Estado')->badge(),
                        TextEntry::make('payment_date')->label('Fecha')->date(),
                        TextEntry::make('amount')
                            ->label('Importe')
                            ->formatStateUsing(function ($state, AccountPayablePayment $record): string {
                                return '$' . number_format((float) $state, 2) . ' ' . $record->currency;
                            }),
                        TextEntry::make('reference')->label('Referencia')->placeholder('Sin referencia'),
                        TextEntry::make('treasury_movement_id')->label('Movimiento tesorería')->placeholder('Pendiente'),
                        TextEntry::make('accounting_entry_id')->label('Póliza')->placeholder('Pendiente'),
                        TextEntry::make('posted_at')->label('Aplicado')->dateTime()->placeholder('Pendiente'),
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
        return static::userCanPermission('account_payables.pay')
            || static::userCanPermission('account_payables.view');
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountPayablePayments::route('/'),
            'view' => Pages\ViewAccountPayablePayment::route('/{record}'),
        ];
    }
}
