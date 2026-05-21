<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountReceivablePaymentResource\Pages;
use App\Models\AccountReceivablePayment;
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

class AccountReceivablePaymentResource extends Resource
{
    protected static ?string $model = AccountReceivablePayment::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Cuentas por cobrar';

    protected static ?string $navigationLabel = 'Cobros de clientes';

    protected static ?string $modelLabel = 'cobro de cliente';

    protected static ?string $pluralModelLabel = 'cobros de clientes';

    protected static ?int $navigationSort = 20;

    public static function statusLabel(?string $state): string
    {
        return match ($state) {
            'draft' => 'Borrador',
            'posted' => 'Aplicado',
            'cancelled' => 'Cancelado',
            default => filled($state) ? (string) $state : 'Sin estado',
        };
    }

    public static function statusColor(?string $state): string
    {
        return match ($state) {
            'posted' => 'success',
            'cancelled' => 'danger',
            'draft' => 'gray',
            default => 'gray',
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
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('accountReceivable.number')
                    ->label('CxC')
                    ->searchable(),

                Tables\Columns\TextColumn::make('accountReceivable.customer_name')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(42),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Importe')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, AccountReceivablePayment $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency)
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                    ->color(fn (?string $state): string => static::statusColor($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Referencia')
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'posted' => 'Aplicado',
                        'cancelled' => 'Cancelado',
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
                Section::make('Cobro')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('accountReceivable.number')->label('CxC')->placeholder('Sin CxC'),
                        TextEntry::make('accountReceivable.customer_name')->label('Cliente')->placeholder('Sin cliente'),

                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                            ->color(fn (?string $state): string => static::statusColor($state)),

                        TextEntry::make('payment_date')->label('Fecha de cobro')->date(),
                        TextEntry::make('amount')
                            ->label('Importe')
                            ->formatStateUsing(fn ($state, AccountReceivablePayment $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),

                        TextEntry::make('treasuryAccount.name')->label('Caja/Banco')->placeholder('Sin cuenta'),
                        TextEntry::make('paymentForm.name')->label('Forma de pago')->placeholder('Sin forma'),
                        TextEntry::make('reference')->label('Referencia')->placeholder('Sin referencia'),

                        TextEntry::make('accounting_entry_id')->label('Póliza')->placeholder('Sin póliza'),
                        TextEntry::make('posted_at')->label('Aplicado')->dateTime()->placeholder('Pendiente'),
                        TextEntry::make('cancelled_at')->label('Cancelado')->dateTime()->placeholder('No cancelado'),
                        TextEntry::make('notes')->label('Notas')->columnSpanFull()->placeholder('Sin notas'),
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
        return static::userCanPermission('account_receivables.view')
            || static::userCanPermission('account_receivables.collect');
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::userCanPermission('account_receivables.collect');
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
            'index' => Pages\ListAccountReceivablePayments::route('/'),
            'view' => Pages\ViewAccountReceivablePayment::route('/{record}'),
        ];
    }
}
