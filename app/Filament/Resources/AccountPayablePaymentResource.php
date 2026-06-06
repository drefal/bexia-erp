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
use Illuminate\Support\Facades\DB;

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
            'draft' => 'gray',
            'posted' => 'success',
            'cancelled' => 'danger',
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
                    ->formatStateUsing(fn ($state, AccountPayablePayment $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency)
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
                    ->placeholder('Sin referencia'),
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
                Section::make('Pago a proveedor')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('accountPayable.number')->label('CxP'),
                        TextEntry::make('accountPayable.supplier_name')->label('Proveedor'),

                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                            ->color(fn (?string $state): string => static::statusColor($state)),

                        TextEntry::make('payment_date')->label('Fecha')->date(),
                        TextEntry::make('amount')
                            ->label('Importe')
                            ->formatStateUsing(fn ($state, AccountPayablePayment $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('reference')->label('Referencia')->placeholder('Sin referencia'),
                        TextEntry::make('treasury_movement_id')->label('Movimiento tesorería')->placeholder('Pendiente'),
                        TextEntry::make('accounting_entry_id')->label('Póliza')->placeholder('Pendiente'),
                        TextEntry::make('posted_at')->label('Aplicado')->dateTime()->placeholder('Pendiente'),
                        TextEntry::make('cancelled_at')->label('Cancelado')->dateTime()->placeholder('No cancelado'),
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

    public static function canCancelPayment(AccountPayablePayment $record): bool
    {
        /*
         * Se permite cancelar pagos aplicados aunque ya tengan póliza.
         * Si tiene accounting_entry_id, cancelPostedPayment() genera reversa contable.
         */
        return (string) $record->status === 'posted';
    }

    public static function cancelPostedPayment(int $paymentId): array
    {
        $result = [
            'payable_status' => null,
            'payable_balance' => null,
            'treasury_balance' => null,
            'reversal_entry_id' => null,
        ];

        DB::transaction(function () use ($paymentId, &$result): void {
            $payment = DB::table('account_payable_payments')
                ->where('id', $paymentId)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw new \RuntimeException('No se encontró el pago.');
            }

            if ((string) $payment->status !== 'posted') {
                throw new \RuntimeException('Solo se pueden cancelar pagos aplicados.');
            }

            $reversalEntry = null;

            if ($payment->accounting_entry_id !== null) {
                $reversalEntry = app(\App\Support\Accounting\AccountPayablePaymentAccountingPoster::class)
                    ->cancelPayment((int) $payment->id, auth()->id());
            }

            $payable = DB::table('account_payables')
                ->where('id', $payment->account_payable_id)
                ->where('company_id', $payment->company_id)
                ->lockForUpdate()
                ->first();

            if (! $payable) {
                throw new \RuntimeException('No se encontró la cuenta por pagar relacionada.');
            }

            $treasuryAccount = DB::table('treasury_accounts')
                ->where('id', $payment->treasury_account_id)
                ->where('company_id', $payment->company_id)
                ->lockForUpdate()
                ->first();

            if (! $treasuryAccount) {
                throw new \RuntimeException('No se encontró la cuenta/caja de tesorería relacionada.');
            }

            $movement = null;

            if ($payment->treasury_movement_id) {
                $movement = DB::table('treasury_movements')
                    ->where('id', $payment->treasury_movement_id)
                    ->where('company_id', $payment->company_id)
                    ->lockForUpdate()
                    ->first();
            }

            if ($movement && (string) $movement->status === 'cancelled') {
                throw new \RuntimeException('El movimiento de tesorería ya está cancelado.');
            }

            $amount = round((float) $payment->amount, 4);

            if ($amount <= 0) {
                throw new \RuntimeException('El importe del pago no es válido.');
            }

            $newTreasuryBalance = round((float) $treasuryAccount->current_balance + $amount, 4);

            $newPaid = round(max(0, (float) $payable->paid_total - $amount), 4);
            $newBalance = round(max(0, (float) $payable->total - $newPaid), 4);

            if ($newBalance <= 0.0001) {
                $newPayableStatus = 'paid';
            } elseif ($newPaid > 0.0001) {
                $newPayableStatus = 'partial';
            } else {
                $newPayableStatus = 'open';
            }

            DB::table('account_payable_payments')
                ->where('id', $payment->id)
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'updated_at' => now(),
                    'metadata' => json_encode(array_merge(
                        json_decode((string) ($payment->metadata ?? '{}'), true) ?: [],
                        [
                            'cancelled_by_patch' => 'v5.56.6l',
                            'cancelled_reason' => 'manual_cancel_from_cxp_payment',
                            'reversal_entry_id' => $reversalEntry?->id,
                        ]
                    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

            if ($movement) {
                DB::table('treasury_movements')
                    ->where('id', $movement->id)
                    ->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'updated_at' => now(),
                        'metadata' => json_encode(array_merge(
                            json_decode((string) ($movement->metadata ?? '{}'), true) ?: [],
                            [
                                'cancelled_by_patch' => 'v5.56.6l',
                                'cancelled_reason' => 'manual_cancel_from_cxp_payment',
                                'reversal_entry_id' => $reversalEntry?->id,
                            ]
                        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
            }

            DB::table('treasury_accounts')
                ->where('id', $treasuryAccount->id)
                ->update([
                    'current_balance' => $newTreasuryBalance,
                    'updated_at' => now(),
                ]);

            DB::table('account_payables')
                ->where('id', $payable->id)
                ->update([
                    'paid_total' => $newPaid,
                    'balance_total' => $newBalance,
                    'status' => $newPayableStatus,
                    'updated_at' => now(),
                ]);

            $result = [
                'payable_status' => $newPayableStatus,
                'payable_balance' => $newBalance,
                'treasury_balance' => $newTreasuryBalance,
                'reversal_entry_id' => $reversalEntry?->id,
            ];
        });

        return $result;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountPayablePayments::route('/'),
            'view' => Pages\ViewAccountPayablePayment::route('/{record}'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.accountpayablepaymentresource',
            fn (): bool => method_exists(static::class, 'canViewAny')
                ? static::canViewAny()
                : (method_exists(static::class, 'canAccess') ? static::canAccess() : true),
        );
    }

}
