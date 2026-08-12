<?php

namespace App\Filament\Resources;


use App\Support\Service\ServiceRepairReceivableSyncer;
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
use Illuminate\Support\Facades\DB;

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


    /*
     * BEXIA_ACCOUNT_RECEIVABLE_PAYMENT_RESOURCE_RESPONSIVE_V5_79_41C
     * Visual-only responsive marker.
     */

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->persistFiltersInSession()
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->extraHeaderAttributes(['class' => 'bexia-cxc-pay-col-id bexia-cxc-pay-col-numeric'])
                    ->extraCellAttributes(['class' => 'bexia-cxc-pay-col-id bexia-cxc-pay-col-numeric'])
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('accountReceivable.number')
                    ->extraHeaderAttributes(['class' => 'bexia-cxc-pay-col-doc bexia-cxc-pay-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-cxc-pay-col-doc bexia-cxc-pay-col-primary'])
                    ->label('CxC')
                    ->searchable(),

                Tables\Columns\TextColumn::make('accountReceivable.customer_name')
                    ->extraHeaderAttributes(['class' => 'bexia-cxc-pay-col-client'])
                    ->extraCellAttributes(['class' => 'bexia-cxc-pay-col-client'])
                    ->label('Cliente')
                    ->searchable()
                    ->limit(42),

                Tables\Columns\TextColumn::make('payment_date')
                    ->extraHeaderAttributes(['class' => 'bexia-cxc-pay-col-date'])
                    ->extraCellAttributes(['class' => 'bexia-cxc-pay-col-date'])
                    ->label('Fecha')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->extraHeaderAttributes(['class' => 'bexia-cxc-pay-col-money bexia-cxc-pay-col-numeric'])
                    ->extraCellAttributes(['class' => 'bexia-cxc-pay-col-money bexia-cxc-pay-col-numeric'])
                    ->label('Importe')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, AccountReceivablePayment $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency)
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->extraHeaderAttributes(['class' => 'bexia-cxc-pay-col-state'])
                    ->extraCellAttributes(['class' => 'bexia-cxc-pay-col-state'])
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                    ->color(fn (?string $state): string => static::statusColor($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference')
                    ->extraHeaderAttributes(['class' => 'bexia-cxc-pay-col-ref'])
                    ->extraCellAttributes(['class' => 'bexia-cxc-pay-col-ref'])
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
                    ->extraAttributes(['class' => 'bexia-cxc-pay-section bexia-cxc-pay-section-main'])
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')->label('ID')
                            ->extraAttributes(['class' => 'bexia-cxc-pay-entry bexia-cxc-pay-entry-id']),
                        TextEntry::make('accountReceivable.number')->label('CxC')->placeholder('Sin CxC')
                            ->extraAttributes(['class' => 'bexia-cxc-pay-entry bexia-cxc-pay-entry-doc']),
                        TextEntry::make('accountReceivable.customer_name')->label('Cliente')->placeholder('Sin cliente')
                            ->extraAttributes(['class' => 'bexia-cxc-pay-entry bexia-cxc-pay-entry-client']),

                        TextEntry::make('status')
                            ->extraAttributes(['class' => 'bexia-cxc-pay-entry bexia-cxc-pay-entry-state'])
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                            ->color(fn (?string $state): string => static::statusColor($state)),

                        TextEntry::make('payment_date')->label('Fecha de cobro')->date()
                            ->extraAttributes(['class' => 'bexia-cxc-pay-entry bexia-cxc-pay-entry-date']),
                        TextEntry::make('amount')
                            ->extraAttributes(['class' => 'bexia-cxc-pay-entry bexia-cxc-pay-entry-money'])
                            ->label('Importe')
                            ->formatStateUsing(fn ($state, AccountReceivablePayment $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),

                        TextEntry::make('treasuryAccount.name')->label('Caja/Banco')->placeholder('Sin cuenta')
                            ->extraAttributes(['class' => 'bexia-cxc-pay-entry bexia-cxc-pay-entry-box']),
                        TextEntry::make('paymentForm.name')->label('Forma de pago')->placeholder('Sin forma')
                            ->extraAttributes(['class' => 'bexia-cxc-pay-entry bexia-cxc-pay-entry-method']),
                        TextEntry::make('reference')->label('Referencia')->placeholder('Sin referencia')
                            ->extraAttributes(['class' => 'bexia-cxc-pay-entry bexia-cxc-pay-entry-ref']),

                        TextEntry::make('accounting_entry_id')->label('Póliza')->placeholder('Sin póliza')
                            ->extraAttributes(['class' => 'bexia-cxc-pay-entry bexia-cxc-pay-entry-policy']),
                        TextEntry::make('posted_at')->label('Aplicado')->dateTime()->placeholder('Pendiente')
                            ->extraAttributes(['class' => 'bexia-cxc-pay-entry bexia-cxc-pay-entry-posted']),
                        TextEntry::make('cancelled_at')->label('Cancelado')->dateTime()->placeholder('No cancelado')
                            ->extraAttributes(['class' => 'bexia-cxc-pay-entry bexia-cxc-pay-entry-cancelled']),
                        TextEntry::make('notes')->label('Notas')->columnSpanFull()->placeholder('Sin notas')
                            ->extraAttributes(['class' => 'bexia-cxc-pay-entry bexia-cxc-pay-entry-notes']),
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

    /*
     * BEXIA_V582_B28G1K_LEGACY_CXC_PAYMENT_READONLY
     *
     * Los cobros historicos migrados son documentos de consulta.
     * No se deben cancelar ni afectar CxC, tesoreria o contabilidad.
     *
     * No usar locked por si solo: un cobro nativo puede adquirir
     * bloqueo operativo sin convertirse en documento historico.
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
        AccountReceivablePayment $record
    ): bool {
        return static::legacyPaymentFlagsAreReadOnly(
            $record->is_legacy ?? false,
            $record->locked ?? false,
        );
    }

    public static function canCancelPayment(
        AccountReceivablePayment $record
    ): bool {
        return ! static::isLegacyReadOnlyPayment($record)
            && (string) $record->status === 'posted';
    }

    public static function cancelPostedPayment(int $paymentId): array
    {
        $result = [
            'receivable_status' => null,
            'receivable_balance' => null,
            'treasury_balance' => null,
            'reversal_entry_id' => null,
        ];

        DB::transaction(function () use ($paymentId, &$result): void {
            $payment = DB::table('account_receivable_payments')
                ->where('id', $paymentId)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw new \RuntimeException('No se encontró el cobro.');
            }

            /*
             * Defensa backend:
             * aunque una llamada omita la visibilidad de Filament,
             * un cobro historico bloqueado nunca puede cancelarse.
             */
            if (static::legacyPaymentFlagsAreReadOnly(
                $payment->is_legacy ?? false,
                $payment->locked ?? false,
            )) {
                throw new \RuntimeException(
                    'El cobro histórico es de solo lectura y no puede cancelarse.'
                );
            }

            if ((string) $payment->status !== 'posted') {
                throw new \RuntimeException('Solo se pueden cancelar cobros aplicados.');
            }

            $reversalEntry = null;

            if ($payment->accounting_entry_id !== null) {
                $reversalEntry = app(\App\Support\Accounting\AccountReceivablePaymentAccountingPoster::class)
                    ->cancelPayment((int) $payment->id, auth()->id());
            }

            $receivable = DB::table('account_receivables')
                ->where('id', $payment->account_receivable_id)
                ->where('company_id', $payment->company_id)
                ->lockForUpdate()
                ->first();

            if (! $receivable) {
                throw new \RuntimeException('No se encontró la cuenta por cobrar relacionada.');
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
                throw new \RuntimeException('El importe del cobro no es válido.');
            }

            $newTreasuryBalance = round((float) $treasuryAccount->current_balance - $amount, 4);

            $newCollected = round(max(0, (float) $receivable->collected_total - $amount), 4);
            $newBalance = round(max(0, (float) $receivable->total - $newCollected), 4);

            if ($newBalance <= 0.0001) {
                $newReceivableStatus = 'paid';
            } elseif ($newCollected > 0.0001) {
                $newReceivableStatus = 'partial';
            } else {
                $newReceivableStatus = 'open';
            }

            DB::table('account_receivable_payments')
                ->where('id', $payment->id)
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'updated_at' => now(),
                    'metadata' => json_encode(array_merge(
                        json_decode((string) ($payment->metadata ?? '{}'), true) ?: [],
                        [
                            'cancelled_by_patch' => 'v5.57.5',
                            'cancelled_reason' => 'manual_cancel_from_cxc_payment',
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
                                'cancelled_by_patch' => 'v5.57.5',
                                'cancelled_reason' => 'manual_cancel_from_cxc_payment',
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

            DB::table('account_receivables')
                ->where('id', $receivable->id)
                ->update([
                    'collected_total' => $newCollected,
                    'balance_total' => $newBalance,
                    'status' => $newReceivableStatus,
                    'updated_at' => now(),
                ]);

            $result = [
                'receivable_status' => $newReceivableStatus,
                'receivable_balance' => $newBalance,
                'treasury_balance' => $newTreasuryBalance,
                'reversal_entry_id' => $reversalEntry?->id,
            ];
        });

        ServiceRepairReceivableSyncer::syncFromPayment((int) $paymentId);

        return $result;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountReceivablePayments::route('/'),
            'view' => Pages\ViewAccountReceivablePayment::route('/{record}'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.accountreceivablepaymentresource',
            fn (): bool => method_exists(static::class, 'canViewAny')
                ? static::canViewAny()
                : (method_exists(static::class, 'canAccess') ? static::canAccess() : true),
        );
    }

}
