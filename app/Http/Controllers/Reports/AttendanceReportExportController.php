<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Models\ReportExport;
use App\Support\Reports\AttendanceReportAuthorization;
use App\Support\Reports\ReportStorage;
use App\Support\Reports\ReportClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AttendanceReportExportController
{
    private function authorizeCompany(
        Request $request,
        int $companyId,
        AttendanceReportAuthorization $authorization
    ): int {

        abort_unless(
            config('reports.enabled', false),
            404
        );

        $userId = (int) (
            $request->user()?->getAuthIdentifier()
            ?? 0
        );

        abort_unless(
            $userId > 0
            && $authorization->allows(
                $userId,
                $companyId
            ),
            403
        );

        return $userId;
    }

    public function index(
        Request $request,
        int $tenant,
        AttendanceReportAuthorization $authorization
    ): JsonResponse {

        $userId = $this->authorizeCompany(
            $request,
            $tenant,
            $authorization
        );

        $reportId = $request->query('report_id');

        abort_unless(
            $reportId === null
            || (
                is_string($reportId)
                && \Illuminate\Support\Str::isUuid($reportId)
            ),
            422
        );

        $reports = ReportExport::query()
            ->ownedBy($tenant, $userId)
            ->when(
                $reportId !== null,
                fn ($query) => $query->whereKey($reportId)
            )
            ->where(
                'report_type',
                ReportExport::ATTENDANCE_PDF
            )
            ->where(function ($query): void {

                $query
                    ->whereIn('status', [
                        ReportExport::PENDING,
                        ReportExport::PROCESSING,
                    ])
                    ->orWhere(
                        'created_at',
                        '>=',
                        ReportClock::sql(ReportClock::now()->subHours(48))
                    )
                    ->orWhere(function ($query): void {

                        $query
                            ->where(
                                'status',
                                ReportExport::COMPLETED
                            )
                            ->where(
                                'expires_at',
                                '>',
                                ReportClock::sql(ReportClock::now())
                            );
                    });
            })
            ->orderByRaw(
                "CASE WHEN status IN "
                ."('pending','processing') "
                ."THEN 0 ELSE 1 END"
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $items = $reports->map(
            function (ReportExport $report) use ($tenant): array {

                $filters = $report->filters ?? [];

                $available =
                    $report->isDownloadable();

                $status = $report->status;

                if (
                    $status === ReportExport::COMPLETED
                    && !$available
                ) {
                    $status = ReportExport::EXPIRED;
                }

                return [
                    'id' => (string) $report->getKey(),

                    'status' => $status,

                    'progress' => $report->progress,

                    'row_count' => $report->row_count,

                    'completed_blocks' =>
                        $report->completed_blocks,

                    'total_blocks' =>
                        $report->total_blocks,

                    'from' => $filters['from'] ?? null,

                    'to' => $filters['to'] ?? null,

                    'group_by' =>
                        $filters['group_by'] ?? 'day',

                    'created_at' =>
                        ReportClock::display($report->created_at),

                    'expires_at' =>
                        ReportClock::display($report->expires_at),

                    'message' => match ($status) {

                        ReportExport::FAILED =>
                            $this->failureMessage(
                                $report->error_code
                            ),

                        ReportExport::EXPIRED =>
                            'El documento ya no esta disponible.',

                        default => null,
                    },

                    'download_url' => $available
                        ? route(
                            'rrhh.attendance.exports.download',
                            [
                                'tenant' => $tenant,
                                'report' =>
                                    $report->getKey(),
                            ],
                            false
                        )
                        : null,
                ];
            }
        )->values();

        return response()
            ->json(['reports' => $items])
            ->header(
                'Cache-Control',
                'private, no-store, max-age=0'
            )
            ->header(
                'X-Content-Type-Options',
                'nosniff'
            );
    }

    public function download(
        Request $request,
        int $tenant,
        string $report,
        AttendanceReportAuthorization $authorization,
        ReportStorage $storage
    ): BinaryFileResponse {

        $userId = $this->authorizeCompany(
            $request,
            $tenant,
            $authorization
        );

        $document = ReportExport::query()
            ->ownedBy($tenant, $userId)
            ->where(
                'report_type',
                ReportExport::ATTENDANCE_PDF
            )
            ->whereKey($report)
            ->firstOrFail();

        abort_unless(
            $document->isDownloadable(),
            404
        );

        $path = $storage->path(
            (string) $document->getKey()
        );

        clearstatcache(true, $path);

        abort_unless(
            is_file($path)
                && !is_link($path)
                && is_readable($path),
            404
        );

        abort_unless(
            (fileperms($path) & 07777) === 0600,
            404
        );

        abort_unless(
            filesize($path)
                === $document->file_size,
            404
        );

        $digest = hash_file('sha256', $path);

        abort_unless(
            is_string($digest)
                && hash_equals(
                    $document->file_sha256,
                    $digest
                ),
            404
        );

        // Recheck authorization after reading
        // the document integrity metadata.

        abort_unless(
            $authorization->allows(
                $userId,
                $tenant
            ),
            403
        );

        abort_unless(
            $document->expires_at?->greaterThan(ReportClock::now()),
            404
        );

        $filters = $document->filters ?? [];

        $from = preg_replace(
            '/[^0-9]/',
            '',
            (string) ($filters['from'] ?? '')
        );

        $to = preg_replace(
            '/[^0-9]/',
            '',
            (string) ($filters['to'] ?? '')
        );

        $filename =
            "reporte_asistencia_{$from}_{$to}.pdf";

        $response = response()->download(
            $path,
            $filename,
            [
                'Content-Type' => 'application/pdf',

                'Cache-Control' =>
                    'private, no-store, max-age=0',

                'X-Content-Type-Options' =>
                    'nosniff',

                'Referrer-Policy' =>
                    'no-referrer',

                'X-Robots-Tag' =>
                    'noindex, nofollow',
            ]
        );

        $response->setPrivate();

        return $response;
    }

    private function failureMessage(
        ?string $code
    ): string {

        return match ($code) {

            'authorization_revoked' =>
                'Ya no tienes permiso para generar este reporte.',

            'report_limit' =>
                'El reporte supera el limite de registros. '
                .'Selecciona un periodo menor.',

            'invalid_filters' =>
                'Los filtros seleccionados ya no son validos.',

            'storage_limit',
            'storage_unavailable' =>
                'El servicio de almacenamiento no esta disponible.',

            'generation_timeout',
            'worker_interrupted' =>
                'La generacion fue interrumpida. '
                .'Solicita nuevamente el reporte.',

            default =>
                'No se pudo generar el documento. '
                .'Intenta nuevamente.',
        };
    }
}
