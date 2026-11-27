<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AttendanceAccessRecipients
{
    public static function resolve(
        int $companyId,
        int $branchId,
        int $employeeId,
        array $entryContext,
        ?array $proposedPolicy = null
    ): array {

        $empty = [
            'assigned_user_id' => null,
            'assignment_source' => null,
            'recipient_user_ids' => [],
        ];

        if (
            $companyId <= 0
            || $branchId <= 0
            || $employeeId <= 0
        ) {
            return $empty;
        }

        $company = Company::query()
            ->whereKey($companyId)
            ->where('active', true)
            ->first();

        $employee = Employee::query()
            ->whereKey($employeeId)
            ->where('company_id', $companyId)
            ->where('active', true)
            ->first();

        if (!$company || !$employee) {
            return $empty;
        }

        $branchValid = DB::table('branches')
            ->where('id', $branchId)
            ->where('company_id', $companyId)
            ->where('active', true)
            ->exists();

        if (!$branchValid) {
            return $empty;
        }

        $policy = $proposedPolicy
            ?? AttendanceAccessPolicyResolver::resolve(
                $companyId,
                $branchId,
                false
            );

        if (!$policy['policy_enabled']) {
            return $empty;
        }

        if (
            ($entryContext['hard_restriction'] ?? false)
        ) {
            // Explicit restrictions require their
            // separate revocation workflow.
            return $empty;
        }

        $candidateIds = [];

        // Priority 1: direct manager.

        if (
            $policy['allow_manager_approval']
            && $employee->manager_employee_id
        ) {

            $managerId = Employee::query()
                ->whereKey(
                    $employee->manager_employee_id
                )
                ->where('company_id', $companyId)
                ->where('active', true)
                ->value('user_id');

            if ($managerId) {
                $candidateIds[] = (int) $managerId;
            }
        }

        // Priority 2: department manager.

        if (
            $policy['allow_department_approval']
            && $employee->hr_department_id
        ) {

            $managerId = DB::table(
                'hr_departments as h'
            )
                ->join(
                    'employees as m',
                    function ($join) {

                        $join->on(
                            'm.id',
                            '=',
                            'h.manager_employee_id'
                        )->on(
                            'm.company_id',
                            '=',
                            'h.company_id'
                        );
                    }
                )
                ->where(
                    'h.id',
                    $employee->hr_department_id
                )
                ->where('h.company_id', $companyId)
                ->where('h.is_active', true)
                ->where('m.active', true)
                ->value('m.user_id');

            if ($managerId) {
                $candidateIds[] = (int) $managerId;
            }
        }

        // HR authority: exact company permissions.
        // No automatic administrator bypass.

        if ($policy['allow_hr_approval']) {

            $permissionId = DB::table('permissions')
                ->where(
                    'name',
                    AttendanceAccessAuthorization::HR
                )
                ->where('guard_name', 'web')
                ->value('id');

            if ($permissionId) {

                $modelType = (new User())
                    ->getMorphClass();

                $direct = DB::table(
                    'model_has_permissions'
                )
                    ->where(
                        'permission_id',
                        $permissionId
                    )
                    ->where('company_id', $companyId)
                    ->where('model_type', $modelType)
                    ->pluck('model_id');

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
                    ->where(
                        'rp.permission_id',
                        $permissionId
                    )
                    ->where(
                        'mr.company_id',
                        $companyId
                    )
                    ->where(
                        'r.company_id',
                        $companyId
                    )
                    ->where('r.guard_name', 'web')
                    ->where(
                        'mr.model_type',
                        $modelType
                    )
                    ->pluck('mr.model_id');

                $hrUsers = $direct
                    ->merge($inherited)
                    ->unique()
                    ->sort()
                    ->values();

                foreach ($hrUsers as $id) {
                    $candidateIds[] = (int) $id;
                }
            }
        }

        $candidateIds = array_values(
            array_unique(
                array_filter(
                    $candidateIds,
                    static fn (int $id): bool =>
                        $id > 0
                        && $id !== (int) $employee->user_id
                )
            )
        );

        $recipients = [];

        foreach ($candidateIds as $id) {

            $actor = User::query()->find($id);

            if (!$actor) {
                continue;
            }

            $identity = AttendanceAccessContextResolver
                ::resolve(
                    $actor,
                    $companyId,
                    $employeeId,
                    $branchId
                );

            if (
                !$identity
                || !in_array(
                    AttendanceAccessAuthorization::VIEW,
                    $identity['permissions'],
                    true
                )
            ) {
                continue;
            }

            $context = array_replace(
                $identity,
                $policy,
                [
                    'hard_restriction' =>
                        (bool) (
                            $entryContext['hard_restriction']
                            ?? false
                        ),

                    'temporary_restriction' =>
                        (bool) (
                            $entryContext['temporary_restriction']
                            ?? false
                        ),
                ]
            );

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
            return $empty;
        }

        // Deterministic assignment:
        // direct manager, department, HR.

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
