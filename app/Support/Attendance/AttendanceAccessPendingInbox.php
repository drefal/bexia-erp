<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Models\Company;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AttendanceAccessPendingInbox
{
    public static function canAccess(
        User $actor,
        Company $company
    ): bool {

        if (
            !$company->active
            || !$actor->canAccessTenant($company)
        ) {
            return false;
        }

        $permissions = AttendanceAccessPermissionResolver
            ::permissionsFor($actor, $company);

        return in_array(
            AttendanceAccessAuthorization::VIEW,
            $permissions,
            true
        ) && count(array_intersect(
            $permissions,
            [
                AttendanceAccessAuthorization::MANAGER,
                AttendanceAccessAuthorization::DEPARTMENT,
                AttendanceAccessAuthorization::HR,
            ]
        )) > 0;
    }

    public static function pendingCount(
        User $actor,
        Company $company
    ): int {

        return count(
            self::rows($actor, $company)
        );
    }

    public static function rows(
        User $actor,
        Company $company
    ): array {

        if (
            !self::canAccess($actor, $company)
            || !Schema::hasTable('hr_access_decisions')
        ) {
            return [];
        }

        $companyId = (int) $company->id;

        $timezone = new DateTimeZone(
            'America/Mexico_City'
        );

        $now = new DateTimeImmutable(
            'now',
            $timezone
        );

        $start = $now->setTime(0, 0, 0);

        $end = $start->modify('+1 day');

        $permissions = AttendanceAccessPermissionResolver
            ::permissionsFor($actor, $company);

        $query = DB::table('hr_access_decisions as d')
            ->join('employees as e', function ($join) {

                $join->on('e.id', '=', 'd.employee_id')
                    ->on('e.company_id', '=', 'd.company_id');

            })
            ->join('branches as b', function ($join) {

                $join->on('b.id', '=', 'd.branch_id')
                    ->on('b.company_id', '=', 'd.company_id');

            })
            ->join(
                'employee_attendances as a',
                function ($join) {

                    $join->on(
                        'a.id',
                        '=',
                        'd.employee_attendance_id'
                    )->on(
                        'a.company_id',
                        '=',
                        'd.company_id'
                    )->on(
                        'a.employee_id',
                        '=',
                        'd.employee_id'
                    );

                }
            )
            ->where('d.company_id', $companyId)
            ->where('d.status', 'hold')
            ->where('d.requires_authorization', true)
            ->whereNull('d.resolved_at')
            ->where('e.active', true)
            ->where('b.active', true)
            ->whereNotNull('a.clock_in_at')
            ->whereNull('a.clock_out_at')
            ->where('a.clock_in_method', 'terminal_qr')
            ->whereColumn(
                'a.clock_in_attendance_terminal_id',
                'd.attendance_terminal_id'
            )
            ->whereRaw(
                "a.clock_in_device_info::jsonb ->> 'physical_company_id' = d.company_id::text"
            )
            ->whereRaw(
                "a.clock_in_device_info::jsonb ->> 'physical_branch_id' = d.branch_id::text"
            )
            ->where(
                'd.entry_at',
                '>=',
                $start->format(DATE_ATOM)
            )
            ->where(
                'd.entry_at',
                '<',
                $end->format(DATE_ATOM)
            )
            ->where(
                'd.entry_at',
                '<=',
                $now->format('Y-m-d H:i:s.uP')
            )
            ->where(function ($q) use ($actor) {

                $q->whereNull('e.user_id')
                    ->orWhere(
                        'e.user_id',
                        '!=',
                        $actor->id
                    );

            });

        // Reduce candidates before resolving permissions.
        // HR authority is still verified per decision.

        if (!in_array(
            AttendanceAccessAuthorization::HR,
            $permissions,
            true
        )) {

            $query->where(function ($q) use (
                $actor,
                $companyId,
                $permissions
            ) {

                $q->whereRaw('1 = 0');

                if (in_array(
                    AttendanceAccessAuthorization::MANAGER,
                    $permissions,
                    true
                )) {

                    $q->orWhereIn(
                        'e.manager_employee_id',
                        DB::table('employees')
                            ->where(
                                'company_id',
                                $companyId
                            )
                            ->where('active', true)
                            ->where('user_id', $actor->id)
                            ->select('id')
                    );
                }

                if (in_array(
                    AttendanceAccessAuthorization::DEPARTMENT,
                    $permissions,
                    true
                )) {

                    $q->orWhereIn(
                        'e.hr_department_id',
                        DB::table('hr_departments as h')
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
                            ->where('h.company_id', $companyId)
                            ->where('h.is_active', true)
                            ->where('m.active', true)
                            ->where('m.user_id', $actor->id)
                            ->select('h.id')
                    );
                }
            });
        }

        $records = $query
            ->orderBy('d.entry_at')
            ->orderBy('d.id')
            ->get([
                'd.id',
                'd.employee_id',
                'd.branch_id',
                'd.entry_at',
                'd.reason_code',
                'd.version',
                'd.evaluation_snapshot',
                'e.name as employee_name',
                'b.name as branch_name',
            ]);

        $rows = [];

        foreach ($records as $record) {

            $identity = AttendanceAccessContextResolver
                ::resolve(
                    $actor,
                    $companyId,
                    (int) $record->employee_id,
                    (int) $record->branch_id
                );

            if (!$identity) {
                continue;
            }

            $current = AttendanceAccessEntryContext
                ::resolve(
                    $companyId,
                    (int) $record->branch_id,
                    (int) $record->employee_id,
                    $now
                );

            $context = array_replace(
                $identity,
                $current
            );

            $approve = AttendanceAccessAuthorization
                ::evaluate(
                    $context,
                    'authorize_now'
                );

            $reject = AttendanceAccessAuthorization
                ::evaluate(
                    $context,
                    'reject_access'
                );

            $ordinary = in_array(
                $record->reason_code,
                [
                    'late_requires_authorization',
                    'non_working_day',
                    'outside_working_hours',
                    'early_arrival_outside_limit',
                ],
                true
            );

            $canApprove = $approve['allowed']
                && $ordinary
                && (
                    !$current['hold_on_approved_absence']
                    || $current['absence_evaluated']
                );

            $canReject = $reject['allowed'];

            if (!$canApprove && !$canReject) {
                continue;
            }

            $snapshot = json_decode(
                $record->evaluation_snapshot,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $entry = (
                new DateTimeImmutable(
                    $record->entry_at
                )
            )->setTimezone($timezone);

            $scheduleStart = null;
            $tolerance = null;
            $lateMinutes = null;

            if (!empty($snapshot['schedule_start'])) {

                $scheduleStart = (
                    new DateTimeImmutable(
                        $snapshot['schedule_start']
                    )
                )->setTimezone($timezone);

                $tolerance = max(
                    0,
                    (int) (
                        $snapshot['access_tolerance_minutes']
                        ?? $snapshot['schedule_tolerance_minutes']
                        ?? 0
                    )
                );

                $lateSeconds = max(
                    0,
                    $entry->getTimestamp()
                    - $scheduleStart->getTimestamp()
                    - ($tolerance * 60)
                );

                $lateMinutes = $lateSeconds / 60;
            }

            $rows[] = [
                'id' => (int) $record->id,
                'version' => (int) $record->version,
                'employee_name' =>
                    (string) $record->employee_name,
                'branch_name' =>
                    (string) $record->branch_name,
                'entry_at' =>
                    $entry->format('d/m/Y H:i:s'),
                'schedule_start' =>
                    $scheduleStart?->format('H:i'),
                'tolerance' => $tolerance,
                'late_minutes' => $lateMinutes,
                'reason_code' => $record->reason_code,
                'can_approve' => $canApprove,
                'can_reject' => $canReject,
            ];
        }

        return $rows;
    }
}
