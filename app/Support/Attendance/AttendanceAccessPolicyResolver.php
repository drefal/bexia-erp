<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use Illuminate\Support\Facades\DB;

final class AttendanceAccessPolicyResolver
{
    public static function defaults(): array
    {
        return [
            'policy_id' => null,
            'policy_enabled' => false,
            'hold_on_late' => false,
            'access_tolerance_minutes' => null,
            'early_arrival_minutes' => null,
            'hold_on_rest_day' => true,
            'hold_on_approved_absence' => true,
            'allow_manager_approval' => false,
            'allow_department_approval' => false,
            'allow_hr_approval' => false,
        ];
    }

    public static function resolve(
        int $companyId,
        int $branchId,
        bool $lock = false
    ): array {
        if ($companyId <= 0 || $branchId <= 0) {
            return self::defaults();
        }

        $validBranch = DB::table('branches')
            ->where('id', $branchId)
            ->where('company_id', $companyId)
            ->exists();

        if (!$validBranch) {
            return self::defaults();
        }

        $query = DB::table('hr_access_policies')
            ->where('company_id', $companyId)
            ->where(function ($query) use ($branchId) {
                $query
                    ->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            })
            ->orderByRaw(
                'CASE WHEN branch_id IS NULL ' .
                'THEN 1 ELSE 0 END'
            );

        if ($lock) {
            $query->sharedLock();
        }

        $row = $query->first();

        return self::normalize($row);
    }

    public static function normalize(
        ?object $row
    ): array {
        if (!$row) {
            return self::defaults();
        }

        return [
            'policy_id' => (int) $row->id,

            'policy_enabled' =>
                (bool) $row->enabled,

            'hold_on_late' =>
                (bool) $row->hold_on_late,

            'access_tolerance_minutes' =>
                $row->access_tolerance_minutes === null
                    ? null
                    : (int) $row->access_tolerance_minutes,

            'early_arrival_minutes' =>
                $row->early_arrival_minutes === null
                    ? null
                    : (int) $row->early_arrival_minutes,

            'hold_on_rest_day' =>
                (bool) $row->hold_on_rest_day,

            'hold_on_approved_absence' =>
                (bool) $row->hold_on_approved_absence,

            'allow_manager_approval' =>
                (bool) $row->allow_manager_approval,

            'allow_department_approval' =>
                (bool) $row->allow_department_approval,

            'allow_hr_approval' =>
                (bool) $row->allow_hr_approval,
        ];
    }
}
