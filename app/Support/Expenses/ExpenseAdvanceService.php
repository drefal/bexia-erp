<?php

namespace App\Support\Expenses;

use App\Models\Employee;
use App\Models\ExpenseAdvance;
use App\Models\ExpenseAdvanceSetting;
use App\Models\PettyCashFund;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExpenseAdvanceService
{
    public function settingsForCompany(
        int $companyId
    ): ExpenseAdvanceSetting {
        return ExpenseAdvanceSetting::query()
            ->firstOrCreate(
                ['company_id' => $companyId],
                ExpenseAdvanceSetting::defaults()
            );
    }

    public function openAdvancesForEmployee(
        int $companyId,
        int $employeeId,
        ?int $ignoreAdvanceId = null
    ) {
        return ExpenseAdvance::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereIn(
                'status',
                ExpenseAdvance::openStatuses()
            )
            ->when(
                $ignoreAdvanceId,
                fn ($query) =>
                    $query->whereKeyNot($ignoreAdvanceId)
            )
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    public function pendingMessage(
        int $companyId,
        ?int $employeeId,
        ?int $ignoreAdvanceId = null
    ): ?string {
        if (! $employeeId) {
            return null;
        }

        $settings = $this->settingsForCompany($companyId);

        if (
            ! $settings->is_active
            || $settings->pending_rule
                === ExpenseAdvanceSetting::RULE_OFF
        ) {
            return null;
        }

        $open = $this->openAdvancesForEmployee(
            $companyId,
            $employeeId,
            $ignoreAdvanceId
        );

        if ($open->isEmpty()) {
            return null;
        }

        $overdue = $open->filter(
            fn (ExpenseAdvance $advance): bool =>
                $advance->due_date
                && $advance->due_date->lt(today())
        );

        $message =
            'El empleado tiene '
            . $open->count()
            . ' anticipo'
            . ($open->count() === 1 ? '' : 's')
            . ' pendiente'
            . ($open->count() === 1 ? '' : 's')
            . ' de resolver';

        if ($overdue->isNotEmpty()) {
            $message .= ', '
                . $overdue->count()
                . ' vencido'
                . ($overdue->count() === 1 ? '' : 's');
        }

        $message .= '.';

        if (
            $settings->pending_rule
            === ExpenseAdvanceSetting::RULE_BLOCK
        ) {
            $message .=
                ' La configuración actual bloquea un nuevo anticipo.';
        } else {
            $message .=
                ' La configuración actual permite continuar con advertencia.';
        }

        return $message;
    }

    public function assertCanCreate(
        int $companyId,
        int $employeeId,
        ?int $ignoreAdvanceId = null
    ): void {
        $settings = $this->settingsForCompany($companyId);

        if (
            ! $settings->is_active
            || $settings->pending_rule
                !== ExpenseAdvanceSetting::RULE_BLOCK
        ) {
            return;
        }

        $count = $this->openAdvancesForEmployee(
            $companyId,
            $employeeId,
            $ignoreAdvanceId
        )->count();

        if ($count > 0) {
            throw new RuntimeException(
                'No se puede crear un nuevo anticipo. '
                . 'El empleado tiene '
                . $count
                . ' anticipo'
                . ($count === 1 ? '' : 's')
                . ' pendiente'
                . ($count === 1 ? '' : 's')
                . ' de resolver.'
            );
        }
    }

    public function prepareForCreate(array $data): array
    {
        $companyId = (int) ($data['company_id'] ?? 0);
        $employeeId = (int) ($data['employee_id'] ?? 0);
        $fundId = (int) ($data['petty_cash_fund_id'] ?? 0);

        if ($companyId <= 0) {
            throw new RuntimeException(
                'No se pudo determinar la empresa.'
            );
        }

        if ($employeeId <= 0) {
            throw new RuntimeException(
                'Selecciona al empleado que recibe el anticipo.'
            );
        }

        if ($fundId <= 0) {
            throw new RuntimeException(
                'Selecciona una caja chica.'
            );
        }

        /*
         * El autorizador es un EMPLEADO.
         *
         * Si el empleado receptor tiene jefe directo,
         * dicho jefe se impone desde servidor aunque
         * el cliente intente enviar otro valor.
         *
         * Si no tiene jefe directo, entonces sí es
         * obligatorio seleccionar manualmente un
         * empleado autorizador de la misma empresa.
         */
        $employee = Employee::query()
            ->whereKey($employeeId)
            ->where('company_id', $companyId)
            ->where('active', true)
            ->first();

        if (! $employee) {
            throw new RuntimeException(
                'El empleado que recibe el anticipo '
                . 'no es válido o no está activo.'
            );
        }

        if ($employee->manager_employee_id) {
            $manager = Employee::query()
                ->whereKey(
                    (int) $employee->manager_employee_id
                )
                ->where('company_id', $companyId)
                ->where('active', true)
                ->first();

            if (! $manager) {
                throw new RuntimeException(
                    'El jefe directo configurado para '
                    . 'el empleado no es válido o no '
                    . 'está activo.'
                );
            }

            $data['authorized_by_employee_id'] =
                (int) $manager->id;
        } else {
            $authorizedByEmployeeId = (int) (
                $data['authorized_by_employee_id']
                ?? 0
            );

            if ($authorizedByEmployeeId <= 0) {
                throw new RuntimeException(
                    'El empleado no tiene jefe directo. '
                    . 'Selecciona al empleado que '
                    . 'autoriza el anticipo.'
                );
            }

            $authorizer = Employee::query()
                ->whereKey($authorizedByEmployeeId)
                ->where('company_id', $companyId)
                ->where('active', true)
                ->first();

            if (! $authorizer) {
                throw new RuntimeException(
                    'El empleado autorizador seleccionado '
                    . 'no es válido o no está activo.'
                );
            }

            $data['authorized_by_employee_id'] =
                (int) $authorizer->id;
        }

        $fund = PettyCashFund::query()
            ->whereKey($fundId)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('is_active', true)
            ->first();

        if (! $fund) {
            throw new RuntimeException(
                'La caja chica seleccionada no es válida.'
            );
        }

        $amount = round(
            (float) ($data['amount'] ?? 0),
            6
        );

        if ($amount <= 0) {
            throw new RuntimeException(
                'El importe debe ser mayor a cero.'
            );
        }

        /*
         * En 4A1 aún no descontamos dinero.
         * Sí impedimos capturar un importe superior al saldo
         * disponible actual para no crear solicitudes imposibles.
         * En 4A2 se volverá a validar con lockForUpdate.
         */
        if (
            $amount
            > round((float) $fund->operational_balance, 6)
                + 0.000001
        ) {
            throw new RuntimeException(
                'El importe del anticipo excede el saldo '
                . 'disponible de la caja chica.'
            );
        }

        $this->assertCanCreate(
            $companyId,
            $employeeId
        );

        $settings = $this->settingsForCompany($companyId);

        $requestDate = Carbon::parse(
            $data['request_date']
                ?? now()->toDateString()
        )->startOfDay();

        $dueDays = max(
            0,
            min(
                365,
                (int) (
                    $data['due_days']
                    ?? $settings->due_days
                    ?? 5
                )
            )
        );

        $data['request_date'] =
            $requestDate->toDateString();

        $data['due_days'] = $dueDays;

        $data['due_date'] =
            $requestDate
                ->copy()
                ->addDays($dueDays)
                ->toDateString();

        $data['currency_code'] =
            $fund->currency_code ?: 'MXN';

        $data['status'] =
            ExpenseAdvance::STATUS_DRAFT;

        $data['created_by_user_id'] =
            auth()->id();

        return $data;
    }

    public function assignNumber(
        ExpenseAdvance $advance
    ): ExpenseAdvance {
        if ($advance->number) {
            return $advance;
        }

        return DB::transaction(
            function () use ($advance): ExpenseAdvance {
                $locked = ExpenseAdvance::query()
                    ->whereKey($advance->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->number) {
                    return $locked;
                }

                $date = (
                    $locked->request_date
                        ?: now()
                )->format('Ymd');

                $prefix = 'ANT-' . $date . '-';

                /*
                 * El ID garantiza unicidad incluso si dos usuarios
                 * crean anticipos al mismo tiempo.
                 */
                $locked->forceFill([
                    'number' =>
                        $prefix
                        . str_pad(
                            (string) $locked->id,
                            4,
                            '0',
                            STR_PAD_LEFT
                        ),
                ])->save();

                return $locked->refresh();
            }
        );
    }

    public function refreshDueDate(
        ExpenseAdvance $advance
    ): ExpenseAdvance {
        $settings = $this->settingsForCompany(
            (int) $advance->company_id
        );

        $requestDate = Carbon::parse(
            $advance->request_date
                ?: now()->toDateString()
        )->startOfDay();

        $dueDays = max(
            0,
            min(
                365,
                (int) (
                    $advance->due_days
                    ?? $settings->due_days
                    ?? 5
                )
            )
        );

        $advance->forceFill([
            'due_days' => $dueDays,
            'due_date' => $requestDate
                ->copy()
                ->addDays($dueDays)
                ->toDateString(),
        ])->save();

        return $advance->refresh();
    }

    public function dueStatusLabel(
        ExpenseAdvance $advance
    ): string {
        if (! $advance->due_date) {
            return 'Sin fecha límite';
        }

        if (
            in_array(
                $advance->status,
                [
                    ExpenseAdvance::STATUS_CLOSED,
                    ExpenseAdvance::STATUS_REJECTED,
                    ExpenseAdvance::STATUS_CANCELLED,
                ],
                true
            )
        ) {
            return 'Finalizado';
        }

        $due = $advance->due_date->copy()->startOfDay();
        $today = today();

        if ($due->isSameDay($today)) {
            return 'Vence hoy';
        }

        if ($due->lt($today)) {
            $days = $due->diffInDays($today);

            return 'Vencido '
                . $days
                . ' día'
                . ($days === 1 ? '' : 's');
        }

        $days = $today->diffInDays($due);

        return 'En tiempo · '
            . $days
            . ' día'
            . ($days === 1 ? '' : 's');
    }
}
