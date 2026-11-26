<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Models\ReportExport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class ReportMaintenanceService
{
    public function __construct(
        private readonly ReportStorage $storage,
        private readonly ReportExportStateService $states
    ) {
    }

    public function run(): array
    {
        $result = [
            'disabled' => false,
            'busy' => false,
            'interrupted' => 0,
            'pending_missing_job' => 0,
            'expired' => 0,
            'deleted' => 0,
            'orphans' => 0,
            'publication_temporaries' => 0,
            'publication_bytes_released' => 0,
            'errors' => 0,
        ];

        if (!config('reports.enabled', false)) {

            $result['disabled'] = true;

            return $result;
        }

        $lock = new ReportGenerationLock(
            $this->storage
        );

        if (!$lock->acquire()) {

            $result['busy'] = true;

            return $result;
        }

        try {

            $publicationCleanup =
                $this->storage
                    ->cleanupAbandonedPublications(900);

            $result['publication_temporaries'] =
                $publicationCleanup['deleted'];

            $result['publication_bytes_released'] =
                $publicationCleanup['bytes'];

            $result['errors'] +=
                $publicationCleanup['errors'];

            $this->recoverInterrupted($result);

            $this->recoverPendingWithoutJob($result);

            $this->expireDocuments($result);

            $this->removeUnavailableDocuments($result);

            $this->removeOrphanDocuments($result);

        } finally {

            $lock->release();
        }

        return $result;
    }

    private function recoverInterrupted(
        array &$result
    ): void {

        // Greater than the job timeout and retry_after.
        // The generation lock must already be held.

        $seconds = max(
            600,
            (int) config(
                'reports.retry_after',
                420
            ) + 180
        );

        $cutoff = ReportClock::sql(
            ReportClock::now()->subSeconds($seconds)
        );

        $reports = ReportExport::query()
            ->where(
                'report_type',
                ReportExport::ATTENDANCE_PDF
            )
            ->where(
                'status',
                ReportExport::PROCESSING
            )
            ->where(function ($query) use ($cutoff): void {

                $query
                    ->where(
                        'heartbeat_at',
                        '<=',
                        $cutoff
                    )
                    ->orWhere(function ($query) use ($cutoff): void {

                        $query
                            ->whereNull('heartbeat_at')
                            ->where(
                                'updated_at',
                                '<=',
                                $cutoff
                            );
                    });
            })
            ->orderBy('updated_at')
            ->limit(100)
            ->get();

        foreach ($reports as $report) {

            $id = (string) $report->getKey();

            try {

                if ($this->states->fail(
                    $id,
                    ReportExport::PROCESSING,
                    'worker_interrupted'
                )) {

                    $result['interrupted']++;
                }

            } catch (Throwable $exception) {

                $this->recordError(
                    $id,
                    $exception,
                    $result
                );
            }
        }
    }


    private function recoverPendingWithoutJob(
        array &$result
    ): void {

        $db = (new ReportExport())->getConnection();

        $queue = config(
            'queue.connections.reports_database'
        );

        if (
            !is_array($queue)
            || ($queue['driver'] ?? null) !== 'database'
            || ($queue['queue'] ?? null) !== 'reports'
            || DB::connection(
                $queue['connection'] ?? null
            ) !== $db
        ) {
            throw new RuntimeException(
                'Report queue configuration mismatch.'
            );
        }

        $table = $queue['table'] ?? 'jobs';

        if (
            !is_string($table)
            || !preg_match(
                '/\A[a-zA-Z_][a-zA-Z0-9_]*\z/',
                $table
            )
        ) {
            throw new RuntimeException(
                'Invalid report queue table.'
            );
        }

        $recovered = $db->transaction(
            function () use ($db, $table): int {

                $db->statement(
                    "SET LOCAL lock_timeout = '3s'"
                );

                // Same admission lock used when
                // creating report requests.

                $db->selectOne(
                    'SELECT pg_advisory_xact_lock(?, ?)',
                    [20261126, 27003]
                );

                $cutoff = ReportClock::sql(
                    ReportClock::now()->subMinutes(10)
                );

                $reports = ReportExport::query()
                    ->where(
                        'report_type',
                        ReportExport::ATTENDANCE_PDF
                    )
                    ->where(
                        'status',
                        ReportExport::PENDING
                    )
                    ->whereNotNull('queue_job_id')
                    ->where('updated_at', '<=', $cutoff)
                    ->whereNotExists(
                        function ($query) use ($table): void {

                            $query->selectRaw('1')
                                ->from($table.' as q')
                                ->whereColumn(
                                    'q.id',
                                    'report_exports.queue_job_id'
                                )
                                ->where(
                                    'q.queue',
                                    'reports'
                                );
                        }
                    )
                    ->orderBy('updated_at')
                    ->limit(100)
                    ->get();

                $count = 0;

                foreach ($reports as $report) {

                    if ($this->states->fail(
                        (string) $report->getKey(),
                        ReportExport::PENDING,
                        'queue_dispatch_failed'
                    )) {
                        $count++;
                    }
                }

                return $count;
            },
            1
        );

        $result['pending_missing_job'] += $recovered;
    }

    private function expireDocuments(
        array &$result
    ): void {

        $reports = ReportExport::query()
            ->where(
                'report_type',
                ReportExport::ATTENDANCE_PDF
            )
            ->where(
                'status',
                ReportExport::COMPLETED
            )
            ->whereNotNull('expires_at')
            ->where(
                'expires_at',
                '<=',
                ReportClock::sql(ReportClock::now())
            )
            ->orderBy('expires_at')
            ->limit(100)
            ->get();

        foreach ($reports as $report) {

            $id = (string) $report->getKey();

            try {

                // Update the database first.
                // An expired document cannot be
                // downloaded even if deletion fails.

                if ($this->states->expire($id)) {

                    $result['expired']++;
                }

            } catch (Throwable $exception) {

                $this->recordError(
                    $id,
                    $exception,
                    $result
                );
            }
        }
    }

    private function removeUnavailableDocuments(
        array &$result
    ): void {

        // Iterate existing files instead of repeatedly
        // scanning historical database records.

        $root = $this->storage->root();

        foreach (
            new \FilesystemIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS
            ) as $file
        ) {

            $id = $this->documentId($file);

            if ($id === null) {
                continue;
            }

            try {

                $report = ReportExport::query()
                    ->whereKey($id)
                    ->where(
                        'report_type',
                        ReportExport::ATTENDANCE_PDF
                    )
                    ->first();

                if (!$report) {
                    continue;
                }

                if (!in_array(
                    $report->status,
                    [
                        ReportExport::FAILED,
                        ReportExport::EXPIRED,
                    ],
                    true
                )) {
                    continue;
                }

                // Grace period for downloads that
                // may have started before expiration.

                // Compare against the persisted database
                // timestamp using the same SQL binding
                // convention as the state transitions.
                $eligible = ReportExport::query()
                    ->whereKey($id)
                    ->where(
                        'report_type',
                        ReportExport::ATTENDANCE_PDF
                    )
                    ->whereIn('status', [
                        ReportExport::FAILED,
                        ReportExport::EXPIRED,
                    ])
                    ->where(
                        'updated_at',
                        '<=',
                        ReportClock::sql(
                            ReportClock::now()->subMinutes(10)
                        )
                    )
                    ->exists();

                if (!$eligible) {
                    continue;
                }

                if (!$this->storage->delete($id)) {

                    throw new RuntimeException(
                        'Report deletion unavailable.'
                    );
                }

                $result['deleted']++;

            } catch (Throwable $exception) {

                $this->recordError(
                    $id,
                    $exception,
                    $result
                );
            }
        }
    }

    private function removeOrphanDocuments(
        array &$result
    ): void {

        $root = $this->storage->root();

        $cutoff = ReportClock::now()
            ->subHours(24)
            ->getTimestamp();

        foreach (
            new \FilesystemIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS
            ) as $file
        ) {

            $id = $this->documentId($file);

            if ($id === null) {
                continue;
            }

            if ($file->getMTime() > $cutoff) {
                continue;
            }

            try {

                // Check all report types before deleting.
                // Never remove a document owned by
                // another future reporting module.

                if (
                    ReportExport::query()
                        ->whereKey($id)
                        ->exists()
                ) {
                    continue;
                }

                if (!$this->storage->delete($id)) {

                    throw new RuntimeException(
                        'Orphan deletion unavailable.'
                    );
                }

                $result['orphans']++;

            } catch (Throwable $exception) {

                $this->recordError(
                    $id,
                    $exception,
                    $result
                );
            }
        }
    }

    private function documentId(
        \SplFileInfo $file
    ): ?string {

        if (
            $file->isLink()
            || !$file->isFile()
        ) {
            return null;
        }

        if (
            preg_match(
                '/\A([0-9a-f]{8}-[0-9a-f]{4}-'
                .'[0-9a-f]{4}-[0-9a-f]{4}-'
                .'[0-9a-f]{12})\.pdf\z/',
                $file->getFilename(),
                $matches
            ) !== 1
        ) {
            return null;
        }

        return $matches[1];
    }

    private function recordError(
        string $id,
        Throwable $exception,
        array &$result
    ): void {

        $result['errors']++;

        Log::warning(
            'Report maintenance operation failed',
            [
                'report_id' => $id,
                'exception_type' =>
                    get_class($exception),
            ]
        );
    }
}
