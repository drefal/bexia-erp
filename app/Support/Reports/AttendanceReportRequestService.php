<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Jobs\GenerateAttendanceReportPdf;
use App\Models\ReportExport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class AttendanceReportRequestService
{
    public function __construct(
        private readonly
            AttendanceReportAuthorization $authorization,

        private readonly
            AttendanceReportFilters $filters
    ) {
    }

    public function request(
        int $userId,
        int $companyId,
        array $input
    ): ReportExport {

        if (!config('reports.enabled', false)) {
            throw ValidationException::withMessages([
                'report' =>
                    'La generacion de reportes no esta habilitada.',
            ]);
        }

        if (!$this->authorization->allows(
            $userId,
            $companyId
        )) {
            throw new AuthorizationException(
                'Reporte no autorizado.'
            );
        }

        $filters = $this->filters->normalize(
            $companyId,
            $input
        );

        $db = (new ReportExport())->getConnection();

        if ($db->getDriverName() !== 'pgsql') {
            throw new RuntimeException(
                'PostgreSQL report connection required.'
            );
        }

        if (
            $db->transactionLevel() !== 0
        ) {
            throw new RuntimeException(
                'Unexpected outer report transaction.'
            );
        }

        $queueConfig = config(
            'queue.connections.reports_database'
        );

        if (
            !is_array($queueConfig)
            || ($queueConfig['driver'] ?? null)
                !== 'database'
            || ($queueConfig['queue'] ?? null)
                !== 'reports'
            || ($queueConfig['after_commit'] ?? null)
                !== false
        ) {
            throw new RuntimeException(
                'Invalid report queue configuration.'
            );
        }

        $queueDb = DB::connection(
            $queueConfig['connection'] ?? null
        );

        // Both inserts must use the same Laravel
        // database connection and transaction.

        if ($queueDb !== $db) {
            throw new RuntimeException(
                'Report queue transaction mismatch.'
            );
        }

        return $db->transaction(
            function () use (
                $db,
                $userId,
                $companyId,
                $filters
            ): ReportExport {

                $db->statement(
                    "SET LOCAL lock_timeout = '3s'"
                );

                // Serialize report admissions only.
                // PostgreSQL releases this lock
                // automatically at transaction end.

                $db->selectOne(
                    'SELECT pg_advisory_xact_lock(?, ?)',
                    [20261126, 27003]
                );

                // Confirm authorization again after
                // acquiring the admission lock.

                if (!$this->authorization->allows(
                    $userId,
                    $companyId
                )) {
                    throw new AuthorizationException(
                        'Reporte no autorizado.'
                    );
                }

                (new AttendanceReportFilterScope())
                    ->validate($companyId, $filters);

                $owned = ReportExport::query()
                    ->ownedBy(
                        $companyId,
                        $userId
                    )
                    ->where(
                        'report_type',
                        ReportExport::ATTENDANCE_PDF
                    )
                    ->active()
                    ->get();

                // Reuse an identical active request
                // instead of enqueueing a duplicate.

                foreach ($owned as $existing) {

                    if (
                        $existing->filters == $filters
                    ) {
                        return $existing;
                    }
                }

                $userPending = ReportExport::query()
                    ->where('user_id', $userId)
                    ->active()
                    ->count();

                if (
                    $userPending >= (int) config(
                        'reports.max_pending_per_user',
                        3
                    )
                ) {
                    throw ValidationException::withMessages([
                        'report' =>
                            'Ya tienes reportes pendientes. '
                            .'Espera a que finalicen.',
                    ]);
                }

                $globalPending = ReportExport::query()
                    ->active()
                    ->count();

                if (
                    $globalPending >= (int) config(
                        'reports.max_pending_total',
                        20
                    )
                ) {
                    throw ValidationException::withMessages([
                        'report' =>
                            'La cola de reportes esta llena. '
                            .'Intenta nuevamente mas tarde.',
                    ]);
                }

                $report = new ReportExport();

                $report->forceFill([
                    'company_id' => $companyId,
                    'user_id' => $userId,

                    'report_type' =>
                        ReportExport::ATTENDANCE_PDF,

                    'status' =>
                        ReportExport::PENDING,

                    'filters' => $filters,
                ]);

                $report->saveOrFail();

                $job = new GenerateAttendanceReportPdf(
                    (string) $report->getKey()
                );

                // Explicit immediate database insertion,
                // inside the current transaction.

                $job->beforeCommit();

                $jobId = Queue::connection(
                    'reports_database'
                )->push(
                    $job,
                    '',
                    'reports'
                );

                if (
                    !is_numeric($jobId)
                    || (int) $jobId < 1
                ) {
                    throw new RuntimeException(
                        'Report job dispatch failed.'
                    );
                }

                $report->forceFill([
                    'queue_job_id' => (int) $jobId,
                ]);

                $report->saveOrFail();

                return $report;
            },
            1
        );
    }
}
