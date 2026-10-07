<?php

namespace App\Support\Expenses;

use App\Models\Employee;
use App\Models\PettyCashFund;
use App\Models\TreasuryAccount;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PettyCashFundService
{
    public function create(array $data, ?int $userId = null): PettyCashFund
    {
        return DB::transaction(function () use ($data, $userId): PettyCashFund {
            $companyId = (int) ($data['company_id'] ?? 0);
            $employeeId = (int) ($data['employee_id'] ?? 0);

            if ($companyId <= 0 || $employeeId <= 0) {
                throw new RuntimeException('Empresa y empleado son obligatorios.');
            }

            $employee = Employee::query()
                ->whereKey($employeeId)
                ->where('company_id', $companyId)
                ->where('active', true)
                ->first();

            if (! $employee) {
                throw new RuntimeException(
                    'El empleado no existe, está inactivo o pertenece a otra empresa.'
                );
            }

            $fundingAccountId = filled($data['funding_treasury_account_id'] ?? null)
                ? (int) $data['funding_treasury_account_id']
                : null;

            $fundingAccount = null;

            if ($fundingAccountId) {
                $fundingAccount = TreasuryAccount::query()
                    ->whereKey($fundingAccountId)
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->first();

                if (! $fundingAccount) {
                    throw new RuntimeException(
                        'La caja origen de fondeo no pertenece a la empresa o está inactiva.'
                    );
                }
            }

            $name = trim((string) ($data['name'] ?? ''));

            if ($name === '') {
                $name = 'Caja chica - ' . $employee->name;
            }

            $authorizedAmount = round(
                (float) ($data['authorized_amount'] ?? 0),
                6
            );

            if ($authorizedAmount < 0) {
                throw new RuntimeException(
                    'El monto autorizado no puede ser negativo.'
                );
            }

            /*
             * El fondo tiene una cuenta real de Tesoreria.
             * NO cargamos aquí el monto autorizado al saldo.
             * El saldo se afectará posteriormente mediante un
             * fondeo real desde Tesoreria.
             */
            $treasuryAccount = TreasuryAccount::query()->create([
                'company_id' => $companyId,
                'type' => 'cash',
                'name' => $name,
                'currency_code' => (string) ($data['currency_code'] ?? 'MXN'),
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_active' => true,
                'notes' => 'Cuenta creada automáticamente para fondo de caja chica de '
                    . $employee->name . '.',
                'branch_id' => $employee->branch_id,
                'warehouse_id' => null,
                'pos_point_id' => null,
                'parent_treasury_account_id' => null,
                'cash_scope' => 'petty_cash',
                'requires_approval' => true,
                'is_default_concentrator' => false,
            ]);

            $fund = PettyCashFund::query()->create([
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'treasury_account_id' => $treasuryAccount->id,
                'funding_treasury_account_id' => $fundingAccount?->id,
                'number' => null,
                'name' => $name,
                'authorized_amount' => $authorizedAmount,
                'operational_balance' => 0,
                'currency_code' => (string) ($data['currency_code'] ?? 'MXN'),
                'status' => (string) ($data['status'] ?? 'active'),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'assigned_at' => $data['assigned_at'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $userId,
            ]);

            $fund->forceFill([
                'number' => 'CC-' . str_pad(
                    (string) $fund->id,
                    6,
                    '0',
                    STR_PAD_LEFT
                ),
            ])->save();

            return $fund->refresh();
        });
    }

    public function update(PettyCashFund $fund, array $data): PettyCashFund
    {
        return DB::transaction(function () use ($fund, $data): PettyCashFund {
            $fund = PettyCashFund::query()
                ->whereKey($fund->id)
                ->lockForUpdate()
                ->firstOrFail();

            $fundingAccountId = filled($data['funding_treasury_account_id'] ?? null)
                ? (int) $data['funding_treasury_account_id']
                : null;

            if ($fundingAccountId) {
                $valid = TreasuryAccount::query()
                    ->whereKey($fundingAccountId)
                    ->where('company_id', $fund->company_id)
                    ->where('is_active', true)
                    ->exists();

                if (! $valid) {
                    throw new RuntimeException(
                        'La caja origen no pertenece a la empresa o está inactiva.'
                    );
                }
            }

            $authorizedAmount = round(
                (float) ($data['authorized_amount'] ?? $fund->authorized_amount),
                6
            );

            if ($authorizedAmount < 0) {
                throw new RuntimeException(
                    'El monto autorizado no puede ser negativo.'
                );
            }

            $fund->update([
                'funding_treasury_account_id' => $fundingAccountId,
                'name' => trim((string) ($data['name'] ?? $fund->name)),
                'authorized_amount' => $authorizedAmount,
                'status' => (string) ($data['status'] ?? $fund->status),
                'is_active' => (bool) ($data['is_active'] ?? $fund->is_active),
                'assigned_at' => $data['assigned_at'] ?? $fund->assigned_at,
                'notes' => $data['notes'] ?? null,
            ]);

            /*
             * Conservamos sincronizado únicamente el nombre y estado
             * operativo de la cuenta. Nunca sobrescribimos su saldo.
             */
            TreasuryAccount::query()
                ->whereKey($fund->treasury_account_id)
                ->where('company_id', $fund->company_id)
                ->update([
                    'name' => $fund->name,
                    'is_active' => $fund->is_active,
                    'updated_at' => now(),
                ]);

            return $fund->refresh();
        });
    }
}
