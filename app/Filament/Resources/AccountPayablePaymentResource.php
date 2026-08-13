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


    /*
     * BEXIA_APPM_RESOURCE_RESPONSIVE_V5_79_44C
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
            ->columns([
                Tables\Columns\TextColumn::make('accountPayable.number')
                    ->extraHeaderAttributes(['class' => 'bexia-appm-col-num bexia-appm-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-appm-col-num bexia-appm-col-primary'])
                    ->label('CxP')
                    ->searchable(),

                Tables\Columns\TextColumn::make('accountPayable.supplier_name')
                    ->extraHeaderAttributes(['class' => 'bexia-appm-col-sup'])
                    ->extraCellAttributes(['class' => 'bexia-appm-col-sup'])
                    ->label('Proveedor')
                    ->searchable()
                    ->limit(42),

                Tables\Columns\TextColumn::make('payment_date')
                    ->extraHeaderAttributes(['class' => 'bexia-appm-col-dt'])
                    ->extraCellAttributes(['class' => 'bexia-appm-col-dt'])
                    ->label('Fecha pago')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->extraHeaderAttributes(['class' => 'bexia-appm-col-amt bexia-appm-col-money'])
                    ->extraCellAttributes(['class' => 'bexia-appm-col-amt bexia-appm-col-money'])
                    ->label('Importe')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, AccountPayablePayment $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency)
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->extraHeaderAttributes(['class' => 'bexia-appm-col-st'])
                    ->extraCellAttributes(['class' => 'bexia-appm-col-st'])
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                    ->color(fn (?string $state): string => static::statusColor($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference')
                    ->extraHeaderAttributes(['class' => 'bexia-appm-col-ref'])
                    ->extraCellAttributes(['class' => 'bexia-appm-col-ref'])
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
                    ->extraAttributes(['class' => 'bexia-appm-section bexia-appm-section-main'])
                    ->columns(3)
                    ->schema([
                        TextEntry::make('accountPayable.number')->label('CxP')
                            ->extraAttributes(['class' => 'bexia-appm-entry bexia-appm-entry-num']),
                        TextEntry::make('accountPayable.supplier_name')->label('Proveedor')
                            ->extraAttributes(['class' => 'bexia-appm-entry bexia-appm-entry-sup']),

                        TextEntry::make('status')
                            ->extraAttributes(['class' => 'bexia-appm-entry bexia-appm-entry-st'])
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                            ->color(fn (?string $state): string => static::statusColor($state)),

                        TextEntry::make('payment_date')->label('Fecha')->date()
                            ->extraAttributes(['class' => 'bexia-appm-entry bexia-appm-entry-dt']),
                        TextEntry::make('amount')
                            ->extraAttributes(['class' => 'bexia-appm-entry bexia-appm-entry-amt'])
                            ->label('Importe')
                            ->formatStateUsing(fn ($state, AccountPayablePayment $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('reference')->label('Referencia')->placeholder('Sin referencia')
                            ->extraAttributes(['class' => 'bexia-appm-entry bexia-appm-entry-ref']),
                        TextEntry::make('treasury_movement_id')->label('Movimiento tesorería')->placeholder('Pendiente')
                            ->extraAttributes(['class' => 'bexia-appm-entry bexia-appm-entry-tm']),
                        TextEntry::make('accounting_entry_id')->label('Póliza')->placeholder('Pendiente')
                            ->extraAttributes(['class' => 'bexia-appm-entry bexia-appm-entry-ae']),
                        TextEntry::make('posted_at')->label('Aplicado')->dateTime()->placeholder('Pendiente')
                            ->extraAttributes(['class' => 'bexia-appm-entry bexia-appm-entry-pa']),
                        TextEntry::make('cancelled_at')->label('Cancelado')->dateTime()->placeholder('No cancelado')
                            ->extraAttributes(['class' => 'bexia-appm-entry bexia-appm-entry-ca']),
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

    /*
     * BEXIA_V582_B28H3_LEGACY_AP_PAYMENT_READONLY
     */
    private static function legacyFlagIsTrue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return in_array(
            strtolower(trim((string) $value)),
            ['1', 'true', 't', 'yes', 'y', 'on'],
            true,
        );
    }

    private static function legacyPaymentFlagsAreReadOnly(
        mixed $isLegacy,
        mixed $locked,
    ): bool {
        return static::legacyFlagIsTrue($isLegacy)
            && static::legacyFlagIsTrue($locked);
    }

    public static function isLegacyReadOnlyPayment(
        AccountPayablePayment $record
    ): bool {
        return static::legacyPaymentFlagsAreReadOnly(
            $record->is_legacy ?? false,
            $record->locked ?? false,
        );
    }

    public static function canCancelPayment(
        AccountPayablePayment $record
    ): bool {
        /*
         * Los pagos nativos aplicados conservan su flujo normal.
         * Los pagos legacy + locked son solo consulta.
         */
        return ! static::isLegacyReadOnlyPayment($record)
            && (string) $record->status === 'posted';
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

            /*
             * Defensa backend: no permitir cancelacion aunque
             * la accion UI sea omitida.
             */
            if (static::legacyPaymentFlagsAreReadOnly(
                $payment->is_legacy ?? false,
                $payment->locked ?? false,
            )) {
                throw new \RuntimeException(
                    'El pago histórico es de solo lectura y no puede cancelarse.'
                );
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
