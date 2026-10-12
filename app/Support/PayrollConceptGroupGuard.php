<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\PayrollConcept;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollConceptGroupGuard
{
    public static function eligibleQuery(
        ?int $employeeId,
        string $type,
        ?int $companyId = null,
        bool $manualOnly = true
    ): Builder {
        $query = PayrollConcept::query()
            ->whereRaw('1 = 0');

        if (! $employeeId) {
            return $query;
        }

        $employee = Employee::query()
            ->select([
                'id',
                'company_id',
                'payroll_group_id',
            ])
            ->find($employeeId);

        if (
            ! $employee
            || ! $employee->payroll_group_id
        ) {
            return $query;
        }

        $resolvedCompanyId =
            $companyId ?: (int) $employee->company_id;

        if (
            $companyId
            && (int) $employee->company_id !== (int) $companyId
        ) {
            return $query;
        }

        $query = PayrollConcept::query()
            ->where(
                'payroll_concepts.company_id',
                $resolvedCompanyId
            )
            ->where(
                'payroll_concepts.type',
                $type
            )
            ->where(
                'payroll_concepts.is_active',
                true
            )
            ->whereExists(function ($subquery) use ($employee): void {
                $subquery
                    ->selectRaw('1')
                    ->from(
                        'payroll_concept_payroll_group as pcpg'
                    )
                    ->whereColumn(
                        'pcpg.payroll_concept_id',
                        'payroll_concepts.id'
                    )
                    ->where(
                        'pcpg.payroll_group_id',
                        (int) $employee->payroll_group_id
                    );
            });

        if ($manualOnly) {
            $query->where(
                'payroll_concepts.source',
                'manual'
            );
        }

        return $query;
    }

    public static function options(
        ?int $employeeId,
        string $type,
        ?int $companyId = null
    ): array {
        return static::eligibleQuery(
            $employeeId,
            $type,
            $companyId,
            true
        )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
            ])
            ->mapWithKeys(
                fn (PayrollConcept $concept): array => [
                    $concept->id =>
                        trim(
                            $concept->code
                            . ' - '
                            . $concept->name
                        ),
                ]
            )
            ->all();
    }

    public static function resolveByCode(
        ?int $employeeId,
        ?int $companyId,
        string $type,
        ?string $code
    ): ?int {
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        return static::eligibleQuery(
            $employeeId,
            $type,
            $companyId,
            true
        )
            ->where(
                'payroll_concepts.code',
                $code
            )
            ->value('payroll_concepts.id');
    }

    public static function assertEligible(
        ?int $employeeId,
        ?int $conceptId,
        string $type,
        ?int $companyId = null
    ): void {
        /*
         * payroll_concept_id continúa siendo nullable.
         * Si no se asigna concepto, el flujo histórico por code/name
         * puede seguir funcionando.
         */
        if (! $conceptId) {
            return;
        }

        if (! $employeeId) {
            throw ValidationException::withMessages([
                'employee_id' =>
                    'Selecciona primero un empleado.',
            ]);
        }

        $employee = Employee::query()
            ->select([
                'id',
                'company_id',
                'payroll_group_id',
            ])
            ->find($employeeId);

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee_id' =>
                    'El empleado seleccionado no existe.',
            ]);
        }

        if (! $employee->payroll_group_id) {
            throw ValidationException::withMessages([
                'employee_id' =>
                    'El empleado no tiene grupo de nómina asignado.',
            ]);
        }

        if (
            $companyId
            && (int) $employee->company_id !== (int) $companyId
        ) {
            throw ValidationException::withMessages([
                'employee_id' =>
                    'El empleado no pertenece a la empresa seleccionada.',
            ]);
        }

        $exists = static::eligibleQuery(
            (int) $employee->id,
            $type,
            $companyId ?: (int) $employee->company_id,
            true
        )
            ->where(
                'payroll_concepts.id',
                $conceptId
            )
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'payroll_concept_id' =>
                    'El concepto no está activo o no está '
                    . 'habilitado para el grupo de nómina '
                    . 'de este empleado.',
            ]);
        }
    }

    public static function attachConceptToEmployeeGroup(
        PayrollConcept $concept,
        int $employeeId
    ): void {
        $employee = Employee::query()
            ->select([
                'id',
                'company_id',
                'payroll_group_id',
            ])
            ->find($employeeId);

        if (! $employee) {
            throw new \RuntimeException(
                'No se encontró el empleado de la operación.'
            );
        }

        if (! $employee->payroll_group_id) {
            throw new \RuntimeException(
                'El empleado no tiene grupo de nómina asignado.'
            );
        }

        if (
            (int) $employee->company_id
            !== (int) $concept->company_id
        ) {
            throw new \RuntimeException(
                'El concepto y el empleado pertenecen '
                . 'a empresas diferentes.'
            );
        }

        DB::table(
            'payroll_concept_payroll_group'
        )->insertOrIgnore([
            'payroll_concept_id' => (int) $concept->id,
            'payroll_group_id' =>
                (int) $employee->payroll_group_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
