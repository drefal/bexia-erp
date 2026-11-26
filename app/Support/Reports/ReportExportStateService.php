<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Models\ReportExport;
use LogicException;
use RuntimeException;

final class ReportExportStateService
{
    private const ERROR_CODES = [
        'authorization_revoked',
        'invalid_filters',
        'generation_failed',
        'storage_unavailable',
        'storage_limit',
        'generation_timeout',
        'worker_interrupted',
        'queue_dispatch_failed',
        'document_unavailable',
        'report_limit',
    ];

    public function claim(string $id): bool
    {
        $now = ReportClock::now();

        return ReportExport::query()
            ->whereKey($id)
            ->where(
                'status',
                ReportExport::PENDING
            )
            ->update([
                'status' => ReportExport::PROCESSING,
                'started_at' => ReportClock::sql($now),
                'heartbeat_at' => ReportClock::sql($now),
                'updated_at' => ReportClock::sql($now),
            ]) === 1;
    }

    public function initialize(
        string $id,
        int $rows,
        int $blocks
    ): void {

        if ($rows < 0 || $blocks < 1) {
            throw new LogicException(
                'Invalid report dimensions.'
            );
        }

        $now = ReportClock::now();

        $affected = ReportExport::query()
            ->whereKey($id)
            ->where(
                'status',
                ReportExport::PROCESSING
            )
            ->where('total_blocks', 0)
            ->update([
                'row_count' => $rows,
                'total_blocks' => $blocks,
                'completed_blocks' => 0,
                'progress' => 0,
                'heartbeat_at' => ReportClock::sql($now),
                'updated_at' => ReportClock::sql($now),
            ]);

        if ($affected !== 1) {
            throw new RuntimeException(
                'Unable to initialize report.'
            );
        }
    }

    public function updateProgress(
        string $id,
        int $completed,
        int $total
    ): void {

        $progress = ReportExport::progressFor(
            $completed,
            $total
        );

        $now = ReportClock::now();

        $affected = ReportExport::query()
            ->whereKey($id)
            ->where(
                'status',
                ReportExport::PROCESSING
            )
            ->where('total_blocks', $total)
            ->where(
                'completed_blocks',
                '<=',
                $completed
            )
            ->update([
                'completed_blocks' => $completed,
                'progress' => $progress,
                'heartbeat_at' => ReportClock::sql($now),
                'updated_at' => ReportClock::sql($now),
            ]);

        if ($affected !== 1) {
            throw new RuntimeException(
                'Unable to update report progress.'
            );
        }
    }

    public function complete(
        string $id,
        int $fileSize,
        string $sha256,
        int $expiresAfterHours = 24
    ): void {

        if (
            $fileSize < 1
            || $expiresAfterHours < 1
            || $expiresAfterHours > 168
            || preg_match(
                '/\A[a-f0-9]{64}\z/',
                $sha256
            ) !== 1
        ) {
            throw new LogicException(
                'Invalid report publication metadata.'
            );
        }

        $now = ReportClock::now();
        $expires = $now->copy()
            ->addHours($expiresAfterHours);

        $affected = ReportExport::query()
            ->whereKey($id)
            ->where(
                'status',
                ReportExport::PROCESSING
            )
            ->where('total_blocks', '>=', 1)
            ->whereColumn(
                'completed_blocks',
                'total_blocks'
            )
            ->update([
                'status' => ReportExport::COMPLETED,
                'progress' => 100,
                'file_size' => $fileSize,
                'file_sha256' => $sha256,
                'completed_at' => ReportClock::sql($now),
                'expires_at' => ReportClock::sql($expires),
                'heartbeat_at' => ReportClock::sql($now),
                'error_code' => null,
                'updated_at' => ReportClock::sql($now),
            ]);

        if ($affected !== 1) {
            throw new RuntimeException(
                'Unable to complete report.'
            );
        }
    }

    public function fail(
        string $id,
        string $expectedStatus,
        string $errorCode
    ): bool {

        if (!in_array(
            $expectedStatus,
            [
                ReportExport::PENDING,
                ReportExport::PROCESSING,
            ],
            true
        )) {
            throw new LogicException(
                'Invalid expected failure state.'
            );
        }

        if (!in_array(
            $errorCode,
            self::ERROR_CODES,
            true
        )) {
            throw new LogicException(
                'Invalid report error code.'
            );
        }

        $now = ReportClock::sql(ReportClock::now());

        return ReportExport::query()
            ->whereKey($id)
            ->where('status', $expectedStatus)
            ->update([
                'status' => ReportExport::FAILED,
                'error_code' => $errorCode,
                'heartbeat_at' => $now,
                'updated_at' => $now,
            ]) === 1;
    }

    public function expire(string $id): bool
    {
        $now = ReportClock::now();

        return ReportExport::query()
            ->whereKey($id)
            ->where(
                'status',
                ReportExport::COMPLETED
            )
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', ReportClock::sql($now))
            ->update([
                'status' => ReportExport::EXPIRED,
                'updated_at' => ReportClock::sql($now),
            ]) === 1;
    }
}
