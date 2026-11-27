<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AttendanceAccessContextResolver
{
    /**
     * Contexto de identidad y jerarquia obtenido
     * exclusivamente desde el servidor.
     *
     * No concede autorizaciones por si mismo.
     */
    public static function resolve(
        User $authenticatedActor,
        int $companyId,
        int $employeeId,
        ?int $branchId = null
    ): ?array {
        if (
            $companyId <= 0
            || $employeeId <= 0
        ) {
            return null;
        }

        $actor = User::query()->find(
            $authenticatedActor->getKey()
        );

        $company = Company::query()
            ->whereKey($companyId)
            ->where('active', true)
            ->first();

        if (
            !$actor
            || !$company
            || !$actor->canAccessTenant($company)
        ) {
            return null;
        }

        $employee = Employee::query()
            ->whereKey($employeeId)
            ->where('company_id', $companyId)
            ->first();

        if (!$employee) {
            return null;
        }

        $branchValid = $branchId === null;

        if ($branchId !== null && $branchId > 0) {
            $branchValid = DB::table('branches')
                ->where('id', $branchId)
                ->where('company_id', $companyId)
                ->exists();
        }

        if (!$branchValid) {
            return null;
        }

        $manager = null;

        if ($employee->manager_employee_id) {
            $manager = Employee::query()
                ->whereKey(
                    $employee->manager_employee_id
                )
                ->where('company_id', $companyId)
                ->where('active', true)
                ->first();
        }

        $managerUserId = 0;

        if ($manager && $manager->user_id) {
            $managerUser = User::query()->find(
                $manager->user_id
            );

            if (
                $managerUser
                && $managerUser->canAccessTenant(
                    $company
                )
            ) {
                $managerUserId =
                    (int) $managerUser->getKey();
            }
        }

        $department = null;

        if ($employee->hr_department_id) {
            $department = DB::table(
                'hr_departments'
            )
                ->where(
                    'id',
                    $employee->hr_department_id
                )
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->first();
        }

        $departmentManager = null;

        if (
            $department
            && $department->manager_employee_id
        ) {
            $departmentManager = Employee::query()
                ->whereKey(
                    $department->manager_employee_id
                )
                ->where('company_id', $companyId)
                ->where('active', true)
                ->first();
        }

        $departmentManagerUserId = 0;

        if (
            $departmentManager
            && $departmentManager->user_id
        ) {
            $departmentManagerUser =
                User::query()->find(
                    $departmentManager->user_id
                );

            if (
                $departmentManagerUser
                && $departmentManagerUser
                    ->canAccessTenant($company)
            ) {
                $departmentManagerUserId =
                    (int) $departmentManagerUser
                        ->getKey();
            }
        }

        return [
            'actor_user_id' =>
                (int) $actor->getKey(),

            'actor_exists' => true,

            'company_id' => $companyId,

            'tenant_access' => true,

            'employee_id' =>
                (int) $employee->getKey(),

            'employee_company_id' =>
                (int) $employee->company_id,

            'employee_user_id' =>
                (int) ($employee->user_id ?? 0),

            'employee_active' =>
                (bool) $employee->active,

            'branch_scope_valid' => $branchValid,

            'manager_user_id' => $managerUserId,

            'manager_company_id' =>
                (int) ($manager?->company_id ?? 0),

            'manager_active' =>
                (bool) ($manager?->active ?? false),

            'department_company_id' =>
                (int) ($department->company_id ?? 0),

            'department_active' =>
                (bool) ($department->is_active ?? false),

            'department_manager_user_id' =>
                $departmentManagerUserId,

            'department_manager_company_id' =>
                (int) (
                    $departmentManager?->company_id ?? 0
                ),

            'department_manager_active' =>
                (bool) (
                    $departmentManager?->active ?? false
                ),

            'permissions' =>
                AttendanceAccessPermissionResolver
                    ::permissionsFor(
                        $actor,
                        $company
                    ),

            // Mientras no exista una politica real,
            // ninguna autorizacion puede concederse.
            'policy_enabled' => false,

            'allow_manager_approval' => false,

            'allow_department_approval' => false,

            'allow_hr_approval' => false,
        ];
    }
}
