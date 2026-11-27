<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AttendanceAccessCoverage
{
    public static function report(
        int $companyId,
        int $branchId,
        ?array $proposedValues = null
    ): array {

        $sessionActor = auth()->user();

        if (!$sessionActor instanceof User) {
            throw new AuthorizationException();
        }

        $actor = User::query()
            ->find($sessionActor->getKey());

        $company = Company::query()
            ->whereKey($companyId)
            ->where('active', true)
            ->first();

        if (
            !$actor
            || !$company
            || !AttendanceAccessPolicyService
                ::canConfigure($actor, $company)
        ) {
            throw new AuthorizationException();
        }

        $branch = DB::table('branches')
            ->where('id', $branchId)
            ->where('company_id', $companyId)
            ->where('active', true)
            ->first(['id', 'name']);

        if (!$branch) {
            throw new AuthorizationException();
        }

        $policy = AttendanceAccessPolicyResolver
            ::resolve($companyId, $branchId);

        if ($proposedValues !== null) {

            $proposedValues =
                AttendanceAccessPolicyService
                    ::normalize($proposedValues);

            $policy = array_replace(
                $policy,
                $proposedValues,
                [
                    'policy_enabled' =>
                        $proposedValues['enabled'],
                ]
            );
        }

        return self::calculate(
            $companyId,
            $branchId,
            $policy
        );
    }

    private static function calculate(
        int $companyId,
        int $branchId,
        array $policy
    ): array {

        $start = hrtime(true);

        // Include all active employees of the company.
        // Physical terminal scope may differ from the
        // employee's administrative branch.

        $employees = DB::table('employees as e')
            ->leftJoin('employees as m', function ($join) {
                $join->on(
                    'm.id',
                    '=',
                    'e.manager_employee_id'
                )->on(
                    'm.company_id',
                    '=',
                    'e.company_id'
                );
            })
            ->where('e.company_id', $companyId)
            ->where('e.active', true)
            ->orderBy('e.name')
            ->get([
                'e.id',
                'e.name',
                'e.branch_id',
                'e.manager_employee_id',
                'e.user_id',
                'e.hr_department_id',
                'e.attendance_qr_enabled',
                'm.name as manager_name',
                'm.active as manager_active',
                'm.user_id as manager_user_id',
            ]);

        $summary = [
            'total' => 0,
            'covered' => 0,
            'uncovered' => 0,
            'not_evaluated' => 0,
            'assigned_to_branch' => 0,
            'assigned_elsewhere' => 0,
            'without_branch' => 0,
            'qr_enabled' => 0,
            'qr_uncovered' => 0,
            'direct_manager' => 0,
            'department_manager' => 0,
            'hr_authority' => 0,
        ];

        $rows = [];

        $resolver = new AttendanceAccessCoverageRecipients(
            $companyId,
            $branchId,
            $policy
        );

        foreach ($employees as $employee) {

            ++$summary['total'];

            $employeeBranch = $employee->branch_id
                === null
                    ? null
                    : (int) $employee->branch_id;

            if ($employeeBranch === null) {

                ++$summary['without_branch'];

                $scope = 'without_branch';

            } elseif ($employeeBranch === $branchId) {

                ++$summary['assigned_to_branch'];

                $scope = 'selected_branch';

            } else {

                ++$summary['assigned_elsewhere'];

                $scope = 'other_branch';
            }

            $qrEnabled =
                (bool) $employee->attendance_qr_enabled;

            if ($qrEnabled) {
                ++$summary['qr_enabled'];
            }

            $assignment = $resolver->resolve(
                (int) $employee->id
            );

            $source =
                $assignment['assignment_source'];

            $covered =
                $assignment['assigned_user_id']
                    !== null;

            if (!$policy['policy_enabled']) {

                ++$summary['not_evaluated'];

                $status = 'not_evaluated';
                $reason = 'policy_disabled';

            } elseif ($covered) {

                ++$summary['covered'];

                if (isset($summary[$source])) {
                    ++$summary[$source];
                }

                $status = 'covered';
                $reason = $source;

            } else {

                ++$summary['uncovered'];

                if ($qrEnabled) {
                    ++$summary['qr_uncovered'];
                }

                $status = 'uncovered';

                $reason = match (true) {

                    !$employee->manager_employee_id
                        => 'no_direct_manager',

                    !$employee->manager_name
                        => 'invalid_direct_manager',

                    !$employee->manager_active
                        => 'inactive_direct_manager',

                    !$employee->manager_user_id
                        => 'manager_without_user',

                    default
                        => 'no_eligible_authorizer',
                };
            }

            $rows[] = [
                'employee_id' =>
                    (int) $employee->id,

                'employee_name' =>
                    $employee->name,

                'employee_branch_id' =>
                    $employeeBranch,

                'scope' => $scope,

                'qr_enabled' => $qrEnabled,

                'manager_employee_id' =>
                    $employee->manager_employee_id,

                'manager_name' =>
                    $employee->manager_name,

                'manager_user_id' =>
                    $employee->manager_user_id,

                'status' => $status,

                'reason_code' => $reason,

                'assigned_user_id' =>
                    $assignment['assigned_user_id'],

                'assignment_source' => $source,

                'recipient_user_ids' =>
                    $assignment['recipient_user_ids'],
            ];
        }

        return [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'policy_enabled' =>
                $policy['policy_enabled'],

            'summary' => $summary,

            'rows' => $rows,

            'elapsed_ms' => round(
                (hrtime(true) - $start) / 1000000,
                2
            ),
        ];
    }
}
