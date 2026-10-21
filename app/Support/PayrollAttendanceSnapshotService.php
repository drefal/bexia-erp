<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class PayrollAttendanceSnapshotService
{
    private const JSON_FLAGS =
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR;

    public static function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }

        return $value;
    }

    public static function hash(array $data): string
    {
        return hash('sha256', json_encode(
            self::canonicalize($data),
            self::JSON_FLAGS
        ));
    }

    private static function rows($query, array $columns): array
    {
        return $query->get($columns)
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public static function capture(
        int $companyId,
        int $employeeId,
        int $payrollRunId
    ): array {
        $run = DB::table('payroll_runs')
            ->where('company_id', $companyId)
            ->where('id', $payrollRunId)
            ->first([
                'id', 'period_start', 'period_end',
            ]);

        if (!$run) {
            throw new \RuntimeException(
                'Prenomina no encontrada en la empresa.'
            );
        }

        $from = (string) $run->period_start;
        $to = (string) $run->period_end;

        $employee = DB::table('employees')
            ->where('company_id', $companyId)
            ->where('id', $employeeId)
            ->first([
                'id', 'hr_department_id',
                'hr_work_schedule_id',
                'payroll_group_id',
                'hire_date', 'termination_date',
                'updated_at',
            ]);

        if (!$employee) {
            throw new \RuntimeException(
                'Empleado no encontrado en la empresa.'
            );
        }

        $attendances = self::rows(
            DB::table('employee_attendances')
                ->where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->whereBetween('attendance_date', [$from, $to])
                ->orderBy('attendance_date')
                ->orderBy('id'),
            [
                'id', 'attendance_date', 'status',
                'hr_work_schedule_id',
                'expected_start_at', 'expected_end_at',
                'clock_in_at', 'clock_out_at',
                'clock_in_method', 'clock_out_method',
                'break_minutes', 'worked_minutes',
                'late_minutes', 'early_leave_minutes',
                'overtime_minutes', 'mobile_review_status',
                'updated_at',
            ]
        );

        $breaks = self::rows(
            DB::table('employee_attendance_breaks')
                ->where('company_id', $companyId)
                ->whereIn(
                    'employee_attendance_id',
                    array_column($attendances, 'id')
                )
                ->orderBy('employee_attendance_id')
                ->orderBy('id'),
            [
                'id', 'employee_attendance_id',
                'break_type', 'started_at', 'ended_at',
                'start_method', 'end_method', 'updated_at',
            ]
        );

        $incidents = self::rows(
            DB::table('employee_incidents')
                ->where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->whereDate('start_date', '<=', $to)
                ->where(function ($q) use ($from) {
                    $q->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $from);
                })
                ->orderBy('start_date')
                ->orderBy('id'),
            [
                'id', 'hr_incident_type_id',
                'employee_attendance_id',
                'status', 'start_date', 'end_date',
                'start_time', 'end_time',
                'quantity', 'quantity_unit',
                'requires_approval', 'affects_payroll',
                'approved_by_user_id', 'approved_at',
                'updated_at',
            ]
        );

        $types = self::rows(
            DB::table('hr_incident_types')
                ->where('company_id', $companyId)
                ->whereIn(
                    'id',
                    array_values(array_unique(
                        array_column(
                            $incidents,
                            'hr_incident_type_id'
                        )
                    ))
                )
                ->orderBy('id'),
            [
                'id', 'code', 'name', 'effect',
                'requires_approval', 'affects_payroll',
            ]
        );

        $contracts = self::rows(
            DB::table('employee_contracts')
                ->where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->where(function ($q) use ($to) {
                    $q->whereNull('start_date')
                        ->orWhereDate('start_date', '<=', $to);
                })
                ->where(function ($q) use ($from) {
                    $q->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $from);
                })
                ->orderBy('start_date')
                ->orderBy('id'),
            [
                'id', 'status', 'start_date', 'end_date',
                'is_current', 'hr_work_schedule_id',
                'hr_department_id', 'updated_at',
            ]
        );

        $scheduleIds = array_values(array_unique(
            array_filter(
                array_merge(
                    [$employee->hr_work_schedule_id],
                    array_column(
                        $attendances,
                        'hr_work_schedule_id'
                    ),
                    array_column(
                        $contracts,
                        'hr_work_schedule_id'
                    )
                ),
                fn ($id) => $id !== null
            )
        ));

        $schedules = self::rows(
            DB::table('hr_work_schedules')
                ->where('company_id', $companyId)
                ->whereIn('id', $scheduleIds)
                ->orderBy('id'),
            [
                'id', 'name', 'code', 'schedule_type',
                'start_time', 'end_time', 'work_days',
                'break_minutes', 'break_sessions_allowed',
                'tolerance_late_minutes',
                'tolerance_early_leave_minutes',
                'updated_at',
            ]
        );

        $scheduleDays = self::rows(
            DB::table('hr_work_schedule_days')
                ->where('company_id', $companyId)
                ->whereIn('hr_work_schedule_id', $scheduleIds)
                ->orderBy('hr_work_schedule_id')
                ->orderBy('day_index')
                ->orderBy('id'),
            [
                'id', 'hr_work_schedule_id',
                'day_of_week', 'day_index',
                'is_working_day', 'start_time', 'end_time',
                'break_minutes', 'break_applies',
                'tolerance_late_minutes',
                'tolerance_early_leave_minutes',
                'updated_at',
            ]
        );

        $payrollIndicators = self::rows(
            DB::table('payroll_run_lines')
                ->where('company_id', $companyId)
                ->where('payroll_run_id', $payrollRunId)
                ->where('employee_id', $employeeId)
                ->orderBy('id'),
            [
                'id', 'status', 'employee_contract_id',
                'attendance_records', 'worked_minutes',
                'late_minutes', 'early_leave_minutes',
                'overtime_minutes', 'absence_days',
                'approved_incidents_count', 'updated_at',
            ]
        );

        return [
            'schema_version' => 1,
            'company_id' => $companyId,
            'payroll_run_id' => $payrollRunId,
            'employee_id' => $employeeId,
            'period_start' => $from,
            'period_end' => $to,
            'employee_schedule_context' => (array) $employee,
            'contracts' => $contracts,
            'schedules' => $schedules,
            'schedule_days' => $scheduleDays,
            'attendances' => $attendances,
            'breaks' => $breaks,
            'incidents' => $incidents,
            'incident_types' => $types,
            'payroll_attendance_indicators' => $payrollIndicators,
        ];
    }

    public static function compareItem(int $itemId): array
    {
        $item = DB::table(
            'hr_payroll_attendance_review_items as i'
        )
            ->join(
                'hr_payroll_attendance_review_batches as b',
                'b.id',
                '=',
                'i.batch_id'
            )
            ->where('i.id', $itemId)
            ->whereColumn('i.company_id', 'b.company_id')
            ->first([
                'i.id', 'i.company_id', 'i.employee_id',
                'i.attendance_snapshot', 'i.snapshot_hash',
                'b.payroll_run_id',
            ]);

        if (!$item) {
            throw new \RuntimeException(
                'Elemento de revision inexistente.'
            );
        }

        if (
            $item->attendance_snapshot === null
            || $item->snapshot_hash === null
        ) {
            return [
                'item_id' => $itemId,
                'status' => 'missing_snapshot',
            ];
        }

        $saved = json_decode(
            $item->attendance_snapshot,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $savedHash = self::hash($saved);

        if (!hash_equals($item->snapshot_hash, $savedHash)) {
            return [
                'item_id' => $itemId,
                'status' => 'invalid_snapshot',
                'stored_hash' => $item->snapshot_hash,
                'calculated_saved_hash' => $savedHash,
            ];
        }

        $current = self::capture(
            (int) $item->company_id,
            (int) $item->employee_id,
            (int) $item->payroll_run_id
        );

        $currentHash = self::hash($current);

        return [
            'item_id' => $itemId,
            'status' => hash_equals($savedHash, $currentHash)
                ? 'unchanged'
                : 'changed',
            'saved_hash' => $savedHash,
            'current_hash' => $currentHash,
        ];
    }
}
