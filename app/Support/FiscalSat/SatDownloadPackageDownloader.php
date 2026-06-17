<?php

namespace App\Support\FiscalSat;

use App\Models\SatCompanyCredential;
use App\Models\SatDownloadRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpCfdi\SatWsDescargaMasiva\PackageReader\CfdiPackageReader;
use PhpCfdi\SatWsDescargaMasiva\PackageReader\MetadataPackageReader;
use RuntimeException;

class SatDownloadPackageDownloader
{
    public function downloadPackages(SatDownloadRequest $request): array
    {
        $request->refresh();

        try {
            $packages = $this->packagesFromRequest($request);

            if ($packages === []) {
                throw new RuntimeException('La solicitud no tiene paquetes disponibles para descargar.');
            }

            $credential = $this->credentialForRequest($request);

            $service = app(SatMassDownloadClientFactory::class)
                ->createForCredential($credential);

            $downloaded = [];
            $errors = [];

            foreach ($packages as $packageId) {
                try {
                    $download = $service->download((string) $packageId);
                    $status = $download->getStatus();

                    if (! $status->isAccepted()) {
                        $errors[] = [
                            'package_id' => $packageId,
                            'message' => $this->safeStatus($status),
                        ];

                        $this->storePackageRow($request, (string) $packageId, [
                            'status' => 'error',
                            'error_message' => $this->safeStatus($status),
                        ]);

                        continue;
                    }

                    $content = $download->getPackageContent();

                    if (! is_string($content) || $content === '') {
                        throw new RuntimeException('SAT devolvió un paquete vacío.');
                    }

                    $zipPath = $this->zipPath($request, (string) $packageId);

                    Storage::disk('local')->put($zipPath, $content);

                    $absolutePath = Storage::disk('local')->path($zipPath);
                    $recordsCount = $this->countRecords($request, $absolutePath);

                    $downloaded[] = [
                        'package_id' => $packageId,
                        'zip_path' => $zipPath,
                        'size_bytes' => strlen($content),
                        'records_count' => $recordsCount,
                    ];

                    $this->storePackageRow($request, (string) $packageId, [
                        'status' => 'downloaded',
                        'file_path' => $zipPath,
                        'size_bytes' => strlen($content),
                        'records_count' => $recordsCount,
                        'error_message' => null,
                    ]);
                } catch (\Throwable $e) {
                    $errors[] = [
                        'package_id' => $packageId,
                        'message' => $e->getMessage(),
                    ];

                    $this->storePackageRow($request, (string) $packageId, [
                        'status' => 'error',
                        'error_message' => $e->getMessage(),
                    ]);
                }
            }

            $downloadedCount = count($downloaded);
            $totalCount = count($packages);

            $metadata = array_merge($request->metadata ?? [], [
                'downloaded_at' => now()->toDateTimeString(),
                'downloaded_packages_count' => $downloadedCount,
                'downloaded_packages' => $downloaded,
                'download_errors' => $errors,
            ]);

            $request->forceFill([
                'sat_message' => $errors === []
                    ? "Paquetes descargados: {$downloadedCount} de {$totalCount}"
                    : "Paquetes descargados: {$downloadedCount} de {$totalCount}. Errores: " . count($errors),
                'error_message' => $errors === [] ? null : json_encode($errors, JSON_UNESCAPED_UNICODE),
                'metadata' => $metadata,
            ])->save();

            return [
                'ok' => $errors === [],
                'downloaded_count' => $downloadedCount,
                'total_count' => $totalCount,
                'downloaded' => $downloaded,
                'errors' => $errors,
                'message' => $errors === []
                    ? "Paquetes descargados: {$downloadedCount} de {$totalCount}"
                    : "Paquetes descargados: {$downloadedCount} de {$totalCount}. Errores: " . count($errors),
            ];
        } catch (\Throwable $e) {
            $request->forceFill([
                'error_message' => $e->getMessage(),
                'sat_message' => $e->getMessage(),
                'metadata' => array_merge($request->metadata ?? [], [
                    'download_failed_at' => now()->toDateTimeString(),
                    'download_error' => $e->getMessage(),
                ]),
            ])->save();

            return [
                'ok' => false,
                'downloaded_count' => 0,
                'total_count' => 0,
                'downloaded' => [],
                'errors' => [
                    [
                        'message' => $e->getMessage(),
                    ],
                ],
                'message' => $e->getMessage(),
            ];
        }
    }

    private function packagesFromRequest(SatDownloadRequest $request): array
    {
        $metadata = $request->metadata ?? [];

        $packages = $metadata['packages'] ?? [];

        if (is_string($packages)) {
            $decoded = json_decode($packages, true);
            $packages = is_array($decoded) ? $decoded : [];
        }

        if (is_array($packages) && $packages !== []) {
            return array_values(array_filter(array_map('strval', $packages)));
        }

        if (! Schema::hasTable('sat_download_packages')) {
            return [];
        }

        $requestColumn = Schema::hasColumn('sat_download_packages', 'sat_download_request_id')
            ? 'sat_download_request_id'
            : (Schema::hasColumn('sat_download_packages', 'download_request_id') ? 'download_request_id' : null);

        $packageColumn = Schema::hasColumn('sat_download_packages', 'package_id')
            ? 'package_id'
            : (Schema::hasColumn('sat_download_packages', 'package_uuid') ? 'package_uuid' : null);

        if (! $requestColumn || ! $packageColumn) {
            return [];
        }

        return DB::table('sat_download_packages')
            ->where($requestColumn, $request->id)
            ->pluck($packageColumn)
            ->filter()
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all();
    }

    private function credentialForRequest(SatDownloadRequest $request): SatCompanyCredential
    {
        $credential = SatCompanyCredential::query()
            ->where('company_id', $request->company_id)
            ->where('credential_status', 'verified')
            ->where('is_enabled', true)
            ->latest('id')
            ->first();

        if (! $credential) {
            throw new RuntimeException('No hay e.firma verificada y activa para esta empresa.');
        }

        return $credential;
    }

    private function zipPath(SatDownloadRequest $request, string $packageId): string
    {
        $safePackage = preg_replace('/[^A-Za-z0-9_\\-]/', '_', $packageId);

        return "fiscal-sat/packages/{$request->company_id}/{$request->id}/{$safePackage}.zip";
    }

    private function countRecords(SatDownloadRequest $request, string $absolutePath): ?int
    {
        try {
            if ($request->request_kind === 'metadata') {
                $reader = MetadataPackageReader::createFromFile($absolutePath);

                $count = 0;

                foreach ($reader->metadata() as $metadata) {
                    $count++;
                }

                return $count;
            }

            if ($request->request_kind === 'xml') {
                $reader = CfdiPackageReader::createFromFile($absolutePath);

                $count = 0;

                foreach ($reader->cfdis() as $uuid => $content) {
                    $count++;
                }

                return $count;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function storePackageRow(SatDownloadRequest $request, string $packageId, array $data): void
    {
        if (! Schema::hasTable('sat_download_packages')) {
            return;
        }

        $requestColumn = Schema::hasColumn('sat_download_packages', 'sat_download_request_id')
            ? 'sat_download_request_id'
            : (Schema::hasColumn('sat_download_packages', 'download_request_id') ? 'download_request_id' : null);

        $packageColumn = Schema::hasColumn('sat_download_packages', 'package_id')
            ? 'package_id'
            : (Schema::hasColumn('sat_download_packages', 'package_uuid') ? 'package_uuid' : null);

        if (! $requestColumn || ! $packageColumn) {
            return;
        }

        $where = [
            $requestColumn => $request->id,
            $packageColumn => $packageId,
        ];

        $values = [];

        if (Schema::hasColumn('sat_download_packages', 'company_id')) {
            $values['company_id'] = $request->company_id;
        }

        if (Schema::hasColumn('sat_download_packages', 'status')) {
            $values['status'] = $data['status'] ?? 'available';
        }

        foreach ([
            'file_path' => ['file_path', 'zip_path', 'storage_path'],
            'size_bytes' => ['size_bytes', 'file_size'],
            'records_count' => ['records_count', 'cfdi_count', 'items_count'],
            'error_message' => ['error_message'],
        ] as $sourceKey => $possibleColumns) {
            foreach ($possibleColumns as $column) {
                if (Schema::hasColumn('sat_download_packages', $column) && array_key_exists($sourceKey, $data)) {
                    $values[$column] = $data[$sourceKey];
                    break;
                }
            }
        }

        if (Schema::hasColumn('sat_download_packages', 'downloaded_at') && ($data['status'] ?? null) === 'downloaded') {
            $values['downloaded_at'] = now();
        }

        if (Schema::hasColumn('sat_download_packages', 'metadata')) {
            $values['metadata'] = json_encode([
                'updated_at' => now()->toDateTimeString(),
                'request_uuid' => $request->request_uuid,
                'package_id' => $packageId,
                'data' => $data,
            ], JSON_UNESCAPED_UNICODE);
        }

        if (Schema::hasColumn('sat_download_packages', 'created_at')) {
            $values['created_at'] = $values['created_at'] ?? now();
        }

        if (Schema::hasColumn('sat_download_packages', 'updated_at')) {
            $values['updated_at'] = now();
        }

        DB::table('sat_download_packages')->updateOrInsert($where, $values);
    }

    private function safeStatus(object $status): string
    {
        if (method_exists($status, 'getCode') && method_exists($status, 'getMessage')) {
            return trim((string) $status->getCode() . ' - ' . (string) $status->getMessage());
        }

        if (method_exists($status, 'getValue')) {
            return (string) $status->getValue();
        }

        return (string) $status;
    }
}
