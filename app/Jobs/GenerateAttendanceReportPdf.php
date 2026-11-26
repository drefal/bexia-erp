<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ReportExport;
use App\Support\AttendanceReportPdfGenerator;
use App\Support\EmployeeAttendanceReportService;
use App\Support\Reports\AttendanceReportAuthorization;
use App\Support\Reports\ReportExportStateService;
use App\Support\Reports\ReportGenerationLock;
use App\Support\Reports\ReportStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class GenerateAttendanceReportPdf
    implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 360;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly string $reportId
    ) {

        if (
            preg_match(
                '/\A[0-9a-f]{8}-[0-9a-f]{4}-'
                .'[0-9a-f]{4}-[0-9a-f]{4}-'
                .'[0-9a-f]{12}\z/',
                $reportId
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Invalid report identifier.'
            );
        }

        $this->onConnection('reports_database');
        $this->onQueue('reports');
    }

    public function handle(
        ReportExportStateService $states,
        AttendanceReportAuthorization $authorization,
        ReportStorage $storage
    ): void {

        $claimed = false;
        $temporary = null;
        $errorCode = 'generation_failed';

        $lock = new ReportGenerationLock($storage);

        try {

            $report = ReportExport::query()
                ->find($this->reportId);

            if (!$report) {
                return;
            }

            if (
                $report->status
                !== ReportExport::PENDING
            ) {
                return;
            }

            if (!config('reports.enabled', false)) {

                $states->fail(
                    $this->reportId,
                    ReportExport::PENDING,
                    'generation_failed'
                );

                return;
            }

            if (
                $report->report_type
                !== ReportExport::ATTENDANCE_PDF
            ) {

                $states->fail(
                    $this->reportId,
                    ReportExport::PENDING,
                    'invalid_filters'
                );

                return;
            }

            // Single permanent worker. Unexpected lock
            // contention fails safely rather than rendering
            // concurrently.

            $errorCode = 'storage_unavailable';

            if (!$lock->acquire()) {
                throw new RuntimeException(
                    'Report generation lock unavailable.'
                );
            }

            if (!$states->claim($this->reportId)) {
                return;
            }

            $claimed = true;

            $report->refresh();

            $errorCode = 'authorization_revoked';

            if (!$authorization->allows(
                $report->user_id,
                $report->company_id
            )) {
                throw new RuntimeException(
                    'Report authorization unavailable.'
                );
            }

            $errorCode = 'invalid_filters';

            if (!is_array($report->filters)) {
                throw new RuntimeException(
                    'Report filters unavailable.'
                );
            }

            $filters = $report->filters;

            if (
                (int) ($filters['company_id'] ?? 0)
                !== $report->company_id
            ) {
                throw new RuntimeException(
                    'Report company mismatch.'
                );
            }

            // The company is always taken from the
            // persisted request, not the job payload.

            $filters['company_id'] =
                $report->company_id;

            $filters = (new
                \App\Support\Reports\AttendanceReportFilters()
            )->normalize(
                $report->company_id,
                $filters
            );

            (new
                \App\Support\Reports\AttendanceReportFilterScope()
            )->validate(
                $report->company_id,
                $filters
            );


            $filters =
                EmployeeAttendanceReportService
                    ::normalizeFilters($filters);

            $errorCode = 'storage_limit';

            $this->assertStorageCapacity(
                $storage,
                0
            );

            $errorCode = 'generation_failed';

            $maxRows = (int) config(
                'reports.tested_monthly_rows',
                6000
            );

            if ($maxRows < 1 || $maxRows > 6000) {
                throw new RuntimeException(
                    'Invalid report capacity configuration.'
                );
            }

            try {

                $data =
                    EmployeeAttendanceReportService
                        ::data($filters, $maxRows);

            } catch (\OverflowException $exception) {

                $errorCode = 'report_limit';

                throw $exception;
            }

            $rows = collect($data['rows']);

            $errorCode = 'report_limit';

            if ($rows->count() > $maxRows) {
                throw new RuntimeException(
                    'Report exceeds validated capacity.'
                );
            }

            $blocks =
                AttendanceReportPdfGenerator::blocks(
                    $rows,
                    $filters['group_by']
                );

            $errorCode = 'generation_failed';

            $states->initialize(
                $this->reportId,
                $rows->count(),
                count($blocks)
            );

            unset($blocks, $rows);

            $temporary =
                AttendanceReportPdfGenerator::generate(
                    $data,
                    null,
                    (int) config(
                        'reports.generation_timeout',
                        300
                    ),
                    function (
                        int $completed,
                        int $total
                    ) use ($states): void {

                        $states->updateProgress(
                            $this->reportId,
                            $completed,
                            $total
                        );
                    }
                );

            unset($data);

            // Recheck authorization immediately
            // before publishing the document.

            $errorCode = 'authorization_revoked';

            if (!$authorization->allows(
                $report->user_id,
                $report->company_id
            )) {
                throw new RuntimeException(
                    'Report authorization revoked.'
                );
            }

            $errorCode = 'storage_unavailable';

            $size = filesize($temporary);

            if ($size === false || $size < 1) {
                throw new RuntimeException(
                    'Invalid generated document.'
                );
            }

            $errorCode = 'storage_limit';

            $this->assertStorageCapacity(
                $storage,
                $size
            );

            $errorCode = 'storage_unavailable';

            $published = $storage->publish(
                $this->reportId,
                $temporary
            );

            clearstatcache(true, $published);

            $publishedSize = filesize($published);

            $sha256 = hash_file(
                'sha256',
                $published
            );

            if (
                $publishedSize !== $size
                || !is_string($sha256)
            ) {
                throw new RuntimeException(
                    'Invalid publication metadata.'
                );
            }

            $errorCode = 'generation_failed';

            $states->complete(
                $this->reportId,
                $publishedSize,
                $sha256,
                (int) config(
                    'reports.expires_after_hours',
                    24
                )
            );

        } catch (Throwable $exception) {

            // Do not delete a published report here:
            // the database commit might have succeeded
            // before a connection error was returned.

            Log::warning(
                'Attendance report generation failed',
                [
                    'report_id' => $this->reportId,
                    'error_code' => $errorCode,
                    'exception_type' =>
                        get_class($exception),
                ]
            );

            try {

                $states->fail(
                    $this->reportId,
                    $claimed
                        ? ReportExport::PROCESSING
                        : ReportExport::PENDING,
                    $errorCode
                );

            } catch (Throwable $stateException) {

                Log::warning(
                    'Report state update unavailable',
                    [
                        'report_id' => $this->reportId,
                        'exception_type' =>
                            get_class($stateException),
                    ]
                );
            }

            // A generic message prevents attendance
            // filters or SQL details entering failed_jobs.

            $this->fail(
                new RuntimeException(
                    'Attendance report generation failed.'
                )
            );

        } finally {

            if (
                is_string($temporary)
                && is_file($temporary)
                && !is_link($temporary)
            ) {
                @unlink($temporary);
            }

            $lock->release();
        }
    }

    private function assertStorageCapacity(
        ReportStorage $storage,
        int $additionalBytes
    ): void {

        $root = $storage->root();

        $free = disk_free_space($root);

        $minimumFree = (int) config(
            'reports.minimum_free_bytes',
            1073741824
        );

        if (
            $free === false
            || $free - $additionalBytes < $minimumFree
        ) {
            throw new RuntimeException(
                'Insufficient report storage space.'
            );
        }

        $used = 0;

        foreach (
            new \FilesystemIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS
            ) as $file
        ) {

            if ($file->isLink()) {
                throw new RuntimeException(
                    'Unexpected storage symbolic link.'
                );
            }

            if ($file->isFile()) {
                $used += $file->getSize();
            }
        }

        $maximum = (int) config(
            'reports.max_storage_bytes',
            536870912
        );

        if (
            $used + $additionalBytes > $maximum
        ) {
            throw new RuntimeException(
                'Report storage capacity exceeded.'
            );
        }
    }

    public function failed(
        ?Throwable $exception
    ): void {

        // Never overwrite a completed document.
        // Interrupted processing is reconciled
        // separately using the generation lock.

        try {

            app(ReportExportStateService::class)
                ->fail(
                    $this->reportId,
                    ReportExport::PENDING,
                    'generation_failed'
                );

        } catch (Throwable $stateException) {

            Log::warning(
                'Report failure reconciliation required',
                [
                    'report_id' => $this->reportId,
                    'exception_type' =>
                        get_class($stateException),
                ]
            );
        }
    }
}
