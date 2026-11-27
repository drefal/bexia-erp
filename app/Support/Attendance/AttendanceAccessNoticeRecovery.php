<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class AttendanceAccessNoticeRecovery
{
    private const LOCK_NAMESPACE = 260611;

    private const LOCK_RESOURCE = 60402;

    public const MAX_BATCH = 50;

    public static function run(
        int $afterId = 0,
        int $limit = self::MAX_BATCH
    ): array {

        if (
            $afterId < 0
            || $limit < 1
            || $limit > self::MAX_BATCH
        ) {
            throw new \InvalidArgumentException(
                'INVALID_RECOVERY_BATCH'
            );
        }

        $connection = DB::connection();

        if (
            $connection->getDriverName() !== 'pgsql'
            || $connection->transactionLevel() !== 0
            || $connection->getPdo()->inTransaction()
        ) {
            throw new RuntimeException(
                'INVALID_RECOVERY_TRANSACTION_CONTEXT'
            );
        }

        $lock = $connection->selectOne(
            'SELECT CASE
                WHEN pg_try_advisory_lock(
                    CAST(? AS integer),
                    CAST(? AS integer)
                )
                THEN 1 ELSE 0
             END AS acquired',
            [
                self::LOCK_NAMESPACE,
                self::LOCK_RESOURCE,
            ],
            false
        );

        if ((int) $lock->acquired !== 1) {

            return [
                'status' => 'busy',
                'attempted' => 0,
                'created' => 0,
                'existing' => 0,
                'skipped' => 0,
                'errors' => 0,
                'next_cursor' => $afterId,
                'has_more' => true,
            ];
        }

        try {

            $timezone = new DateTimeZone(
                'America/Mexico_City'
            );

            $now = new DateTimeImmutable(
                'now',
                $timezone
            );

            $start = $now->setTime(0, 0, 0);

            $end = $start->modify('+1 day');

            // Select only current, unresolved decisions
            // supported by an open physical QR entry.

            $records = $connection
                ->table('hr_access_decisions as d')
                ->useWritePdo()

                ->join(
                    'companies as c',
                    'c.id',
                    '=',
                    'd.company_id'
                )

                ->join(
                    'employees as e',
                    function ($join) {

                        $join->on(
                            'e.id',
                            '=',
                            'd.employee_id'
                        )->on(
                            'e.company_id',
                            '=',
                            'd.company_id'
                        );
                    }
                )

                ->join(
                    'branches as b',
                    function ($join) {

                        $join->on(
                            'b.id',
                            '=',
                            'd.branch_id'
                        )->on(
                            'b.company_id',
                            '=',
                            'd.company_id'
                        );
                    }
                )

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

                ->where('c.active', true)
                ->where('e.active', true)
                ->where('b.active', true)

                ->where('d.id', '>', $afterId)

                ->where('d.status', 'hold')
                ->where(
                    'd.requires_authorization',
                    true
                )
                ->whereNull('d.resolved_at')

                ->whereIn(
                    'd.reason_code',
                    [
                        'late_requires_authorization',
                        'non_working_day',
                        'outside_working_hours',
                        'early_arrival_outside_limit',
                    ]
                )

                ->whereNotNull('a.clock_in_at')
                ->whereNull('a.clock_out_at')

                ->where(
                    'a.clock_in_method',
                    'terminal_qr'
                )

                ->whereColumn(
                    'a.clock_in_attendance_terminal_id',
                    'd.attendance_terminal_id'
                )

                ->whereRaw(
                    "a.clock_in_device_info::jsonb
                     ->> 'physical_company_id'
                     = d.company_id::text"
                )

                ->whereRaw(
                    "a.clock_in_device_info::jsonb
                     ->> 'physical_branch_id'
                     = d.branch_id::text"
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

                ->orderBy('d.id')
                ->limit($limit + 1)
                ->get([
                    'd.id',
                    'd.company_id',
                ]);

            $hasMore = $records->count() > $limit;

            $attempted = 0;
            $created = 0;
            $existing = 0;
            $skipped = 0;
            $errors = 0;
            $cursor = $afterId;

            foreach ($records->take($limit) as $row) {

                $cursor = (int) $row->id;

                $attempted++;

                try {

                    // The existing notification service
                    // revalidates current permissions,
                    // visibility and decision status.
                    //
                    // Recovery does not publish WebSocket
                    // events for every scanned record.

                    $stats =
                        AttendanceAccessNotifications::notifyPending(
                            (int) $row->company_id,
                            (int) $row->id,
                            false
                        );

                    $created += $stats['created'];
                    $existing += $stats['existing'];
                    $skipped += $stats['skipped'];
                    $errors += $stats['errors'];

                } catch (Throwable $error) {

                    $errors++;

                    self::warn(
                        (int) $row->company_id,
                        (int) $row->id,
                        $error
                    );
                }
            }

            return [
                'status' => $errors > 0
                    ? 'completed_with_errors'
                    : 'completed',

                'attempted' => $attempted,
                'created' => $created,
                'existing' => $existing,
                'skipped' => $skipped,
                'errors' => $errors,

                // Reset after completing a full sweep.
                // The scheduler must persist this cursor.

                'next_cursor' => $hasMore
                    ? $cursor
                    : 0,

                'has_more' => $hasMore,
            ];

        } finally {

            $released = $connection->selectOne(
                'SELECT CASE
                    WHEN pg_advisory_unlock(
                        CAST(? AS integer),
                        CAST(? AS integer)
                    )
                    THEN 1 ELSE 0
                 END AS released',
                [
                    self::LOCK_NAMESPACE,
                    self::LOCK_RESOURCE,
                ],
                false
            );

            if ((int) $released->released !== 1) {

                throw new RuntimeException(
                    'RECOVERY_LOCK_NOT_RELEASED'
                );
            }
        }
    }

    private static function warn(
        int $companyId,
        int $decisionId,
        Throwable $error
    ): void {

        try {

            \Illuminate\Support\Facades\Log::warning(
                'Attendance notice recovery failed',
                [
                    'company_id' => $companyId,
                    'decision_id' => $decisionId,
                    'exception_type' => get_class($error),
                ]
            );

        } catch (Throwable) {

            // Never interrupt recovery because
            // the logging channel is unavailable.
        }
    }
}
