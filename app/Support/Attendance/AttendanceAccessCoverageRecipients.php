<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Snapshot local para un solo calculo de cobertura.
 *
 * No utilizar para conceder autorizaciones reales.
 * No conserva datos entre solicitudes.
 */
final class AttendanceAccessCoverageRecipients
{
    private array $employees = [];

    private array $departments = [];

    private array $eligibleUsers = [];

    private array $permissions = [];

    private array $hrUsers = [];

    private bool $valid = false;

    private const EMPTY_RESULT = [
        'assigned_user_id' => null,
        'assignment_source' => null,
        'recipient_user_ids' => [],
    ];

    public function __construct(
        private readonly int $companyId,
        private readonly int $branchId,
        private readonly array $policy
    ) {

        if (
            $companyId <= 0
            || $branchId <= 0
            || !($policy['policy_enabled'] ?? false)
        ) {
            return;
        }

        $company = Company::query()
            ->whereKey($companyId)
            ->where('active', true)
            ->first();

        if (!$company) {
            return;
        }

        $branchValid = DB::table('branches')
            ->where('id', $branchId)
            ->where('company_id', $companyId)
            ->where('active', true)
            ->exists();

        if (!$branchValid) {
            return;
        }

        // Load active employees once, preserving Eloquent
        // scopes and model casts used by the old resolver.
        foreach (
            Employee::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->get([
                    'id',
                    'company_id',
                    'active',
                    'user_id',
                    'manager_employee_id',
                    'hr_department_id',
                ]) as $employee
        ) {
            $this->employees[(int) $employee->id] =
                $employee;
        }

        foreach (
            DB::table('hr_departments')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->get([
                    'id',
                    'company_id',
                    'is_active',
                    'manager_employee_id',
                ]) as $department
        ) {
            $this->departments[
                (int) $department->id
            ] = $department;
        }

        $modelType = (new User())->getMorphClass();

        $allowed =
            AttendanceAccessAuthorization::permissions();

        // Direct permissions for this company only.
        $direct = DB::table(
            'model_has_permissions as mp'
        )
            ->join(
                'permissions as p',
                'p.id',
                '=',
                'mp.permission_id'
            )
            ->where('mp.model_type', $modelType)
            ->where('mp.company_id', $companyId)
            ->where('p.guard_name', 'web')
            ->whereIn('p.name', $allowed)
            ->get([
                'mp.model_id as user_id',
                'p.name',
            ]);

        // Inherited permissions with exact team and guard.
        $inherited = DB::table(
            'model_has_roles as mr'
        )
            ->join(
                'roles as r',
                'r.id',
                '=',
                'mr.role_id'
            )
            ->join(
                'role_has_permissions as rp',
                'rp.role_id',
                '=',
                'r.id'
            )
            ->join(
                'permissions as p',
                'p.id',
                '=',
                'rp.permission_id'
            )
            ->where('mr.model_type', $modelType)
            ->where('mr.company_id', $companyId)
            ->where('r.company_id', $companyId)
            ->where('r.guard_name', 'web')
            ->where('p.guard_name', 'web')
            ->whereIn('p.name', $allowed)
            ->get([
                'mr.model_id as user_id',
                'p.name',
            ]);

        foreach ($direct->merge($inherited) as $row) {

            $id = (int) $row->user_id;

            if ($id > 0) {
                $this->permissions[$id][$row->name] =
                    $row->name;
            }
        }

        foreach ($this->permissions as $id => $items) {

            $items = array_values($items);

            sort($items, SORT_STRING);

            $this->permissions[$id] = $items;
        }

        $candidateIds = array_keys(
            $this->permissions
        );

        // Include users linked to employees, even when
        // they do not have any access permission.
        foreach ($this->employees as $employee) {

            if ((int) $employee->user_id > 0) {

                $candidateIds[] =
                    (int) $employee->user_id;
            }
        }

        $candidateIds = array_values(
            array_unique($candidateIds)
        );

        if ($candidateIds !== []) {

            foreach (
                User::query()
                    ->whereIn('id', $candidateIds)
                    ->get() as $user
            ) {

                // Reuse the actual tenant access rules.
                // Execute once per distinct user.
                if ($user->canAccessTenant($company)) {

                    $this->eligibleUsers[
                        (int) $user->id
                    ] = true;
                }
            }
        }

        if ($policy['allow_hr_approval'] ?? false) {

            foreach ($this->permissions as $id => $items) {

                if (
                    in_array(
                        AttendanceAccessAuthorization::HR,
                        $items,
                        true
                    )
                ) {
                    $this->hrUsers[] = (int) $id;
                }
            }

            sort($this->hrUsers, SORT_NUMERIC);
        }

        $this->valid = true;
    }

    public function resolve(int $employeeId): array
    {
        if (
            !$this->valid
            || !isset($this->employees[$employeeId])
        ) {
            return self::EMPTY_RESULT;
        }

        $employee = $this->employees[$employeeId];

        $manager = $this->employees[
            (int) ($employee->manager_employee_id ?? 0)
        ] ?? null;

        $department = $this->departments[
            (int) ($employee->hr_department_id ?? 0)
        ] ?? null;

        $departmentManager = $this->employees[
            (int) ($department->manager_employee_id ?? 0)
        ] ?? null;

        $managerId =
            (int) ($manager?->user_id ?? 0);

        $departmentManagerId =
            (int) ($departmentManager?->user_id ?? 0);

        $employeeUserId =
            (int) ($employee->user_id ?? 0);

        $candidateIds = [];

        if (
            ($this->policy['allow_manager_approval'] ?? false)
            && $managerId > 0
        ) {
            $candidateIds[] = $managerId;
        }

        if (
            ($this->policy['allow_department_approval'] ?? false)
            && $departmentManagerId > 0
        ) {
            $candidateIds[] = $departmentManagerId;
        }

        if ($this->policy['allow_hr_approval'] ?? false) {

            $candidateIds = array_merge(
                $candidateIds,
                $this->hrUsers
            );
        }

        $candidateIds = array_values(
            array_unique(
                array_filter(
                    $candidateIds,
                    static fn (int $id): bool =>
                        $id > 0
                        && $id !== $employeeUserId
                )
            )
        );

        $recipients = [];

        foreach ($candidateIds as $id) {

            if (!isset($this->eligibleUsers[$id])) {
                continue;
            }

            $permissions =
                $this->permissions[$id] ?? [];

            if (
                !in_array(
                    AttendanceAccessAuthorization::VIEW,
                    $permissions,
                    true
                )
            ) {
                continue;
            }

            $identity = [

                'actor_user_id' => $id,
                'actor_exists' => true,

                'company_id' => $this->companyId,
                'tenant_access' => true,

                'employee_id' => $employeeId,

                'employee_company_id' =>
                    (int) $employee->company_id,

                'employee_user_id' =>
                    $employeeUserId,

                'employee_active' =>
                    (bool) $employee->active,

                'branch_scope_valid' => true,

                'manager_user_id' =>
                    isset(
                        $this->eligibleUsers[$managerId]
                    )
                        ? $managerId
                        : 0,

                'manager_company_id' =>
                    (int) ($manager?->company_id ?? 0),

                'manager_active' =>
                    (bool) ($manager?->active ?? false),

                'department_company_id' =>
                    (int) ($department->company_id ?? 0),

                'department_active' =>
                    (bool) ($department->is_active ?? false),

                'department_manager_user_id' =>
                    isset(
                        $this->eligibleUsers[
                            $departmentManagerId
                        ]
                    )
                        ? $departmentManagerId
                        : 0,

                'department_manager_company_id' =>
                    (int) (
                        $departmentManager?->company_id ?? 0
                    ),

                'department_manager_active' =>
                    (bool) (
                        $departmentManager?->active ?? false
                    ),

                'permissions' => $permissions,
            ];

            $context = array_replace(
                $identity,
                $this->policy,
                [
                    'hard_restriction' => false,
                    'temporary_restriction' => false,
                ]
            );

            // Same authorization engine as the original.
            $result = AttendanceAccessAuthorization
                ::evaluate(
                    $context,
                    'authorize_now'
                );

            if (!$result['allowed']) {
                continue;
            }

            $recipients[] = [
                'user_id' => $id,
                'source' => $result['source'],
            ];
        }

        if ($recipients === []) {
            return self::EMPTY_RESULT;
        }

        $priority = [
            'direct_manager' => 1,
            'department_manager' => 2,
            'hr_authority' => 3,
        ];

        usort(
            $recipients,
            static fn (array $a, array $b): int =>
                [
                    $priority[$a['source']] ?? 99,
                    $a['user_id'],
                ]
                <=>
                [
                    $priority[$b['source']] ?? 99,
                    $b['user_id'],
                ]
        );

        return [
            'assigned_user_id' =>
                $recipients[0]['user_id'],

            'assignment_source' =>
                $recipients[0]['source'],

            'recipient_user_ids' =>
                array_column(
                    $recipients,
                    'user_id'
                ),
        ];
    }
}
