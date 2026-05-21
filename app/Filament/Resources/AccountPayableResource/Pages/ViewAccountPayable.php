<?php

namespace App\Filament\Resources\AccountPayableResource\Pages;

use App\Filament\Resources\AccountPayableResource;
use App\Models\AccountPayable;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ViewAccountPayable extends ViewRecord
{
    protected static string $resource = AccountPayableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print_payable')
                ->label('Imprimir CxP')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('account-payables.print', [
                    'tenant' => $this->tenantCompanyId(),
                    'payable' => $this->record->id,
                ]))
                ->openUrlInNewTab(),

            Actions\Action::make('register_payment')
                ->label('Registrar pago')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->modalHeading(fn (): string => 'Registrar pago - ' . $this->record->number)
                ->modalSubmitActionLabel('Registrar pago')
                ->visible(function (): bool {
                    /** @var AccountPayable $record */
                    $record = $this->record;

                    return $this->userCanPermission('account_payables.pay')
                        && in_array((string) $record->status, ['open', 'partial'], true)
                        && (float) $record->balance_total > 0;
                })
                ->form([
                    Forms\Components\Placeholder::make('payable_summary')
                        ->label('Cuenta por pagar')
                        ->content(function (): string {
                            /** @var AccountPayable $record */
                            $record = $this->record;

                            return $record->number
                                . ' | ' . ($record->supplier_name ?: 'Proveedor sin nombre')
                                . ' | Saldo: $' . number_format((float) $record->balance_total, 2)
                                . ' ' . $record->currency;
                        }),

                    Forms\Components\Select::make('treasury_account_id')
                        ->label('Cuenta / Caja de tesorería')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(function (): array {
                            $companyId = $this->tenantCompanyId();

                            if (! $companyId) {
                                return [];
                            }

                            return DB::table('treasury_accounts')
                                ->where('company_id', $companyId)
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(function ($row): array {
                                    return [
                                        $row->id => $row->name
                                            . ' | Saldo: $' . number_format((float) $row->current_balance, 2)
                                            . ' ' . $row->currency_code,
                                    ];
                                })
                                ->all();
                        }),

                    Forms\Components\Select::make('payment_form_id')
                        ->label('Forma de pago')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(function (): array {
                            $companyId = $this->tenantCompanyId();

                            if (! $companyId) {
                                return [];
                            }

                            return DB::table('payment_forms')
                                ->where('company_id', $companyId)
                                ->where('is_active', true)
                                ->orderBy('sort_order')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(function ($row): array {
                                    $code = $row->code ?: $row->sat_payment_form_code;

                                    return [
                                        $row->id => trim(($code ? $code . ' - ' : '') . $row->name),
                                    ];
                                })
                                ->all();
                        }),

                    Forms\Components\DatePicker::make('payment_date')
                        ->label('Fecha de pago')
                        ->required()
                        ->default(fn (): string => now()->toDateString()),

                    Forms\Components\TextInput::make('amount')
                        ->label('Monto a pagar')
                        ->required()
                        ->numeric()
                        ->minValue(0.01)
                        ->maxValue(fn (): float => round((float) $this->record->balance_total, 2))
                        ->default(fn (): string => number_format(round((float) $this->record->balance_total, 2), 2, '.', ''))
                        ->helperText(fn (): string => 'Saldo pendiente: $' . number_format((float) $this->record->balance_total, 2) . ' ' . $this->record->currency)
                        ->rules([
                            fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                                $amount = round((float) $value, 2);
                                $balance = round((float) $this->record->balance_total, 2);

                                if ($amount > ($balance + 0.0001)) {
                                    $fail('El monto no puede ser mayor al saldo pendiente.');
                                }
                            },
                        ]),

                    Forms\Components\TextInput::make('reference')
                        ->label('Referencia')
                        ->maxLength(255)
                        ->placeholder('Ej. transferencia, folio, comprobante'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Notas')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    try {
                        $result = $this->registerPayment($data);

                        Notification::make()
                            ->title('Pago registrado')
                            ->body(
                                'Pago #' . $result['payment_id']
                                . '. Movimiento tesorería #' . $result['movement_id']
                                . '. Nuevo estado: ' . AccountPayableResource::statusLabel($result['new_status'])
                                . '.'
                            )
                            ->success()
                            ->send();

                        $this->record->refresh();
                    } catch (ValidationException $e) {
                        throw $e;
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('No se pudo registrar el pago')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function registerPayment(array $data): array
    {
        /** @var AccountPayable $record */
        $record = $this->record;

        $paymentId = null;
        $movementId = null;
        $newStatus = null;
        $newBalance = null;

        DB::transaction(function () use ($record, $data, &$paymentId, &$movementId, &$newStatus, &$newBalance): void {
            $inputAmount = round((float) ($data['amount'] ?? 0), 2);
            $treasuryAccountId = (int) ($data['treasury_account_id'] ?? 0);
            $paymentFormId = (int) ($data['payment_form_id'] ?? 0);
            $paymentDate = (string) ($data['payment_date'] ?? now()->toDateString());
            $reference = trim((string) ($data['reference'] ?? ''));
            $notes = trim((string) ($data['notes'] ?? ''));

            $payable = DB::table('account_payables')
                ->where('id', $record->id)
                ->lockForUpdate()
                ->first();

            if (! $payable) {
                throw new \RuntimeException('No se encontró la cuenta por pagar.');
            }

            if (! in_array((string) $payable->status, ['open', 'partial'], true)) {
                throw new \RuntimeException('Solo se pueden pagar cuentas abiertas o parciales.');
            }

            $balanceRaw = round((float) $payable->balance_total, 4);
            $balanceCurrency = round($balanceRaw, 2);

            if ($inputAmount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'El monto debe ser mayor a cero.',
                ]);
            }

            if ($inputAmount > ($balanceCurrency + 0.0001)) {
                throw ValidationException::withMessages([
                    'amount' => 'El monto no puede ser mayor al saldo pendiente.',
                ]);
            }

            $amount = $inputAmount;

            if (
                $inputAmount >= ($balanceCurrency - 0.0001)
                && abs($inputAmount - $balanceRaw) <= 0.02
            ) {
                $amount = $balanceRaw;
            }

            $treasuryAccount = DB::table('treasury_accounts')
                ->where('id', $treasuryAccountId)
                ->where('company_id', $payable->company_id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $treasuryAccount) {
                throw new \RuntimeException('La cuenta/caja de tesorería no existe o no está activa.');
            }

            if ((string) $treasuryAccount->currency_code !== (string) $payable->currency) {
                throw new \RuntimeException('La moneda de la cuenta/caja no coincide con la moneda de la CxP.');
            }

            $treasuryBalance = round((float) $treasuryAccount->current_balance, 4);

            if ($amount > ($treasuryBalance + 0.0001)) {
                throw new \RuntimeException('La cuenta/caja de tesorería no tiene saldo suficiente.');
            }

            $paymentForm = DB::table('payment_forms')
                ->where('id', $paymentFormId)
                ->where('company_id', $payable->company_id)
                ->where('is_active', true)
                ->first();

            if (! $paymentForm) {
                throw new \RuntimeException('La forma de pago no existe o no está activa.');
            }

            $newPaid = round((float) $payable->paid_total + $amount, 4);
            $newBalance = round(max(0, (float) $payable->total - $newPaid), 4);
            $newStatus = $newBalance <= 0.0001 ? 'paid' : 'partial';

            $paymentId = DB::table('account_payable_payments')->insertGetId([
                'company_id' => $payable->company_id,
                'account_payable_id' => $payable->id,
                'treasury_account_id' => $treasuryAccount->id,
                'payment_form_id' => $paymentForm->id,
                'treasury_movement_id' => null,
                'accounting_entry_id' => null,
                'amount' => $amount,
                'payment_date' => $paymentDate,
                'currency' => $payable->currency ?: 'MXN',
                'reference' => $reference !== '' ? $reference : null,
                'status' => 'posted',
                'posted_at' => now(),
                'cancelled_at' => null,
                'notes' => $notes !== '' ? $notes : null,
                'metadata' => json_encode([
                    'created_by_patch' => 'v5.56.4e',
                    'input_amount' => $inputAmount,
                    'posted_amount' => $amount,
                    'balance_before' => $balanceRaw,
                    'payable_number' => $payable->number,
                    'supplier_name' => $payable->supplier_name,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_by_user_id' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $movementId = DB::table('treasury_movements')->insertGetId([
                'company_id' => $payable->company_id,
                'treasury_account_id' => $treasuryAccount->id,
                'payment_form_id' => $paymentForm->id,
                'accounting_entry_id' => null,
                'type' => 'outflow',
                'source_type' => 'account_payable_payment',
                'source_id' => $paymentId,
                'movement_date' => $paymentDate,
                'amount' => $amount,
                'currency_code' => $payable->currency ?: 'MXN',
                'reference' => $reference !== '' ? $reference : null,
                'description' => 'Pago CxP ' . $payable->number . ' - ' . ($payable->supplier_name ?: 'Proveedor'),
                'status' => 'posted',
                'posted_at' => now(),
                'cancelled_at' => null,
                'created_by_user_id' => auth()->id(),
                'metadata' => json_encode([
                    'created_by_patch' => 'v5.56.4e',
                    'account_payable_id' => $payable->id,
                    'account_payable_number' => $payable->number,
                    'account_payable_payment_id' => $paymentId,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('account_payable_payments')
                ->where('id', $paymentId)
                ->update([
                    'treasury_movement_id' => $movementId,
                    'updated_at' => now(),
                ]);

            DB::table('treasury_accounts')
                ->where('id', $treasuryAccount->id)
                ->update([
                    'current_balance' => round($treasuryBalance - $amount, 4),
                    'updated_at' => now(),
                ]);

            DB::table('account_payables')
                ->where('id', $payable->id)
                ->update([
                    'paid_total' => $newPaid,
                    'balance_total' => $newBalance,
                    'status' => $newStatus,
                    'updated_at' => now(),
                ]);

            app(\App\Support\Accounting\AccountPayablePaymentAccountingPoster::class)
                ->postPayment((int) $paymentId, auth()->id());
        });

        return [
            'payment_id' => $paymentId,
            'movement_id' => $movementId,
            'new_status' => $newStatus,
            'new_balance' => $newBalance,
        ];
    }

    protected function tenantCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant && method_exists($tenant, 'getKey')
            ? (int) $tenant->getKey()
            : null;
    }

    protected function userCanPermission(string $permission): bool
    {
        $user = auth()->user();

        return $user && method_exists($user, 'can') && $user->can($permission);
    }
}
