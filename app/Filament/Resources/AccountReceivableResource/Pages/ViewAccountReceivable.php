<?php

namespace App\Filament\Resources\AccountReceivableResource\Pages;


use App\Support\Service\ServiceRepairReceivableSyncer;
use App\Filament\Resources\AccountReceivableResource;
use App\Models\AccountReceivable;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ViewAccountReceivable extends ViewRecord
{
    protected static string $resource = AccountReceivableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print_receivable')
                ->label('Imprimir PDF')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('account-receivables.print', [
                    'tenant' => $this->tenantCompanyId(),
                    'receivable' => $this->record->id,
                ]))
                ->openUrlInNewTab(),

            Actions\Action::make('register_collection')
                ->label('Registrar cobro')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->modalHeading(fn (): string => 'Registrar cobro - ' . $this->record->number)
                ->modalSubmitActionLabel('Registrar cobro')
                ->visible(function (): bool {
                    /** @var AccountReceivable $record */
                    $record = $this->record;

                    return $this->canCollectReceivable()
                        && in_array((string) $record->status, ['open', 'partial'], true)
                        && (float) $record->balance_total > 0;
                })
                ->form([
                    Forms\Components\Placeholder::make('receivable_summary')
                        ->label('Cuenta por cobrar')
                        ->content(function (): string {
                            /** @var AccountReceivable $record */
                            $record = $this->record;

                            return $record->number
                                . ' | ' . ($record->customer_name ?: 'Cliente sin nombre')
                                . ' | Saldo: $' . number_format((float) $record->balance_total, 2)
                                . ' ' . $record->currency;
                        }),

                    Forms\Components\Select::make('treasury_account_id')
                        ->label('Cuenta / Caja de tesorería')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->default(fn (): ?int => $this->defaultTreasuryAccountId())
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
                        ->default(fn (): ?int => $this->defaultPaymentFormId())
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
                        ->label('Fecha de cobro')
                        ->required()
                        ->default(fn (): string => now()->toDateString()),

                    Forms\Components\TextInput::make('amount')
                        ->label('Monto a cobrar')
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
                        $result = $this->registerCollection($data);

                        Notification::make()
                            ->title('Cobro registrado')
                            ->body(
                                'Cobro #' . $result['payment_id']
                                . '. Movimiento tesorería #' . $result['movement_id']
                                . '. Nuevo estado: ' . AccountReceivableResource::statusLabel($result['new_status'])
                                . '.'
                            )
                            ->success()
                            ->send();

                        $this->record->refresh();
                    } catch (ValidationException $e) {
                        throw $e;
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('No se pudo registrar el cobro')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function registerCollection(array $data): array
    {
        /** @var AccountReceivable $record */
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

            $receivable = DB::table('account_receivables')
                ->where('id', $record->id)
                ->lockForUpdate()
                ->first();

            if (! $receivable) {
                throw new \RuntimeException('No se encontró la cuenta por cobrar.');
            }

            if (! in_array((string) $receivable->status, ['open', 'partial'], true)) {
                throw new \RuntimeException('Solo se pueden cobrar cuentas abiertas o parciales.');
            }

            $balanceRaw = round((float) $receivable->balance_total, 4);
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
                ->where('company_id', $receivable->company_id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $treasuryAccount) {
                throw new \RuntimeException('La cuenta/caja de tesorería no existe o no está activa.');
            }

            if ((string) $treasuryAccount->currency_code !== (string) $receivable->currency) {
                throw new \RuntimeException('La moneda de la cuenta/caja no coincide con la moneda de la CxC.');
            }

            $paymentForm = DB::table('payment_forms')
                ->where('id', $paymentFormId)
                ->where('company_id', $receivable->company_id)
                ->where('is_active', true)
                ->first();

            if (! $paymentForm) {
                throw new \RuntimeException('La forma de pago no existe o no está activa.');
            }

            $treasuryBalance = round((float) $treasuryAccount->current_balance, 4);
            $newCollected = round((float) $receivable->collected_total + $amount, 4);
            $newBalance = round(max(0, (float) $receivable->total - $newCollected), 4);
            $newStatus = $newBalance <= 0.0001 ? 'paid' : 'partial';

            $paymentId = DB::table('account_receivable_payments')->insertGetId([
                'company_id' => $receivable->company_id,
                'account_receivable_id' => $receivable->id,
                'treasury_account_id' => $treasuryAccount->id,
                'payment_form_id' => $paymentForm->id,
                'treasury_movement_id' => null,
                'accounting_entry_id' => null,
                'amount' => $amount,
                'payment_date' => $paymentDate,
                'currency' => $receivable->currency ?: 'MXN',
                'reference' => $reference !== '' ? $reference : null,
                'status' => 'posted',
                'posted_at' => now(),
                'cancelled_at' => null,
                'notes' => $notes !== '' ? $notes : null,
                'metadata' => json_encode([
                    'created_by_patch' => 'v5.57.4',
                    'input_amount' => $inputAmount,
                    'posted_amount' => $amount,
                    'balance_before' => $balanceRaw,
                    'receivable_number' => $receivable->number,
                    'customer_name' => $receivable->customer_name,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_by_user_id' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $movementId = DB::table('treasury_movements')->insertGetId([
                'company_id' => $receivable->company_id,
                'treasury_account_id' => $treasuryAccount->id,
                'payment_form_id' => $paymentForm->id,
                'accounting_entry_id' => null,
                'type' => 'inflow',
                'source_type' => 'account_receivable_payment',
                'source_id' => $paymentId,
                'movement_date' => $paymentDate,
                'amount' => $amount,
                'currency_code' => $receivable->currency ?: 'MXN',
                'reference' => $reference !== '' ? $reference : null,
                'description' => 'Cobro CxC ' . $receivable->number . ' - ' . ($receivable->customer_name ?: 'Cliente'),
                'status' => 'posted',
                'posted_at' => now(),
                'cancelled_at' => null,
                'created_by_user_id' => auth()->id(),
                'metadata' => json_encode([
                    'created_by_patch' => 'v5.57.4',
                    'account_receivable_id' => $receivable->id,
                    'account_receivable_number' => $receivable->number,
                    'account_receivable_payment_id' => $paymentId,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('account_receivable_payments')
                ->where('id', $paymentId)
                ->update([
                    'treasury_movement_id' => $movementId,
                    'updated_at' => now(),
                ]);

            DB::table('treasury_accounts')
                ->where('id', $treasuryAccount->id)
                ->update([
                    'current_balance' => round($treasuryBalance + $amount, 4),
                    'updated_at' => now(),
                ]);

            DB::table('account_receivables')
                ->where('id', $receivable->id)
                ->update([
                    'collected_total' => $newCollected,
                    'balance_total' => $newBalance,
                    'status' => $newStatus,
                    'updated_at' => now(),
                ]);

            app(\App\Support\Accounting\AccountReceivablePaymentAccountingPoster::class)
                ->postPayment((int) $paymentId, auth()->id());
        });

        ServiceRepairReceivableSyncer::syncFromReceivable((int) $record->id);

        return [
            'payment_id' => $paymentId,
            'movement_id' => $movementId,
            'new_status' => $newStatus,
            'new_balance' => $newBalance,
        ];
    }

    protected function canCollectReceivable(): bool
    {
        if (
            $this->userCanPermission('account_receivables.collect')
            || $this->userCanPermission('account_receivables.update')
            || $this->userCanPermission('account_receivables.create')
        ) {
            return true;
        }

        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $roleNames = [];

        if (method_exists($user, 'getRoleNames')) {
            try {
                $roleNames = $user->getRoleNames()
                    ->map(fn ($role): string => (string) $role)
                    ->all();
            } catch (Throwable $e) {
                $roleNames = [];
            }
        }

        foreach ($roleNames as $roleName) {
            $normalized = strtolower((string) $roleName);

            if (
                str_contains($normalized, 'super')
                && (
                    str_contains($normalized, 'admin')
                    || str_contains($normalized, 'administrador')
                )
            ) {
                return true;
            }
        }

        return false;
    }

    protected function tenantCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant && method_exists($tenant, 'getKey')
            ? (int) $tenant->getKey()
            : null;
    }

    protected function defaultTreasuryAccountId(): ?int
    {
        $companyId = $this->tenantCompanyId();

        if (! $companyId) {
            return null;
        }

        return DB::table('treasury_accounts')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderByRaw("case when type = 'bank' then 0 when type = 'cash' then 1 else 2 end")
            ->orderBy('name')
            ->value('id');
    }

    protected function defaultPaymentFormId(): ?int
    {
        $companyId = $this->tenantCompanyId();

        if (! $companyId) {
            return null;
        }

        return DB::table('payment_forms')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderByRaw("case when coalesce(code, sat_payment_form_code) = '03' then 0 when coalesce(code, sat_payment_form_code) = '01' then 1 else 2 end")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->value('id');
    }

    protected function userCanPermission(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $superAdminRoles = [
            'Super Administrador',
            'Super Admin',
            'super_admin',
            'super-administrador',
            'super_administrador',
        ];

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($superAdminRoles)) {
            return true;
        }

        return method_exists($user, 'can') && $user->can($permission);
    }
}
