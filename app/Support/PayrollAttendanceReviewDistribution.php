<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PayrollAttendanceReviewDistribution
{
    /**
     * Vista previa de responsables para una corrida.
     *
     * No crea revisiones, no asigna permisos y no escribe
     * en ninguna tabla.
     */
    public static function preview(
        int $companyId,
        int $payrollRunId
    ): array {
        $run = DB::table('payroll_runs')
            ->where('company_id', $companyId)
            ->where('id', $payrollRunId)
            ->first([
                'id',
                'company_id',
                'payroll_group_id',
                'period_start',
                'period_end',
                'status',
            ]);

        if (!$run) {
            throw new \RuntimeException(
                'Prenomina inexistente en la empresa.'
            );
        }

        $company = Company::query()->find($companyId);

        if (!$company) {
            throw new \RuntimeException(
                'Empresa inexistente.'
            );
        }

        $query = DB::table('employees')
            ->where('company_id', $companyId)
            ->where(function ($q) use ($run) {
                $q->where('active', true)
                    ->orWhereDate(
                        'termination_date',
                        '>=',
                        $run->period_start
                    );
            })
            ->where(function ($q) use ($run) {
                $q->whereNull('hire_date')
                    ->orWhereDate(
                        'hire_date',
                        '<=',
                        $run->period_end
                    );
            });

        if ($run->payroll_group_id !== null) {
            $query->where(
                'payroll_group_id',
                $run->payroll_group_id
            );
        }

        $employees = $query
            ->orderBy('id')
            ->get(['id', 'hr_department_id']);

        $groups = [];
        $pending = [];

        foreach ($employees as $employee) {
            $resolution =
                PayrollAttendanceReviewerResolver::resolve(
                    $companyId,
                    (int) $employee->id
                );

            $reviewerId = $resolution['reviewer_user_id'];

            if ($reviewerId === null) {
                $pending[] = [
                    'employee_id' => (int) $employee->id,
                    'department_id' =>
                        $employee->hr_department_id,
                    'reason' => $resolution['reason'],
                ];

                continue;
            }

            $user = User::query()->find($reviewerId);

            if (!$user || !$user->canAccessTenant($company)) {
                $pending[] = [
                    'employee_id' => (int) $employee->id,
                    'department_id' =>
                        $employee->hr_department_id,
                    'reason' =>
                        'El responsable no tiene acceso a la empresa',
                ];

                continue;
            }

            $key = (int) $reviewerId;

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'reviewer_user_id' => $key,
                    'employee_ids' => [],
                    'department_ids' => [],
                ];
            }

            $groups[$key]['employee_ids'][] =
                (int) $employee->id;

            if ($employee->hr_department_id !== null) {
                $groups[$key]['department_ids'][] =
                    (int) $employee->hr_department_id;
            }
        }

        foreach ($groups as &$group) {
            $group['department_ids'] = array_values(
                array_unique($group['department_ids'])
            );
            sort($group['department_ids']);
        }
        unset($group);

        ksort($groups);

        $assigned = array_sum(array_map(
            fn ($group) => count($group['employee_ids']),
            $groups
        ));

        if ($assigned + count($pending) !== $employees->count()) {
            throw new \RuntimeException(
                'La distribucion no cuadra.'
            );
        }

        return [
            'company_id' => $companyId,
            'payroll_run_id' => $payrollRunId,
            'period_start' => (string) $run->period_start,
            'period_end' => (string) $run->period_end,
            'total_employees' => $employees->count(),
            'assigned_employees' => $assigned,
            'pending_employees' => count($pending),
            'groups' => array_values($groups),
            'pending' => $pending,
        ];
    }
}
