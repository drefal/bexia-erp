<?php

namespace App\Support\FiscalSat;

use App\Models\SatDownloadRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

class SatXmlPackageImporter
{
    public function importFromRequest(SatDownloadRequest $request): array
    {
        if ($request->request_kind !== 'xml') {
            throw new RuntimeException('Esta acción solo aplica para descargas XML.');
        }

        $packages = $this->downloadedPackagesFromRequest($request);

        if ($packages === []) {
            throw new RuntimeException('No se encontraron paquetes XML descargados para procesar.');
        }

        $result = [
            'packages_count' => count($packages),
            'xml_read' => 0,
            'imported' => 0,
            'errors_count' => 0,
            'errors' => [],
            'packages' => [],
        ];

        foreach ($packages as $package) {
            $packageId = (string) ($package['package_id'] ?? '');
            $zipPath = (string) ($package['zip_path'] ?? $package['file_path'] ?? '');

            $packageResult = [
                'package_id' => $packageId,
                'zip_path' => $zipPath,
                'xml_read' => 0,
                'imported' => 0,
                'errors_count' => 0,
            ];

            try {
                if ($zipPath === '') {
                    throw new RuntimeException('El paquete no tiene ruta ZIP registrada.');
                }

                $absolutePath = Storage::disk('local')->path($zipPath);

                if (! is_file($absolutePath)) {
                    throw new RuntimeException('No existe el archivo ZIP: ' . $zipPath);
                }

                $zip = new ZipArchive();

                if ($zip->open($absolutePath) !== true) {
                    throw new RuntimeException('No se pudo abrir el ZIP: ' . $zipPath);
                }

                try {
                    for ($index = 0; $index < $zip->numFiles; $index++) {
                        $entryName = (string) $zip->getNameIndex($index);

                        if (! str_ends_with(strtolower($entryName), '.xml')) {
                            continue;
                        }

                        $content = $zip->getFromIndex($index);

                        if (! is_string($content) || trim($content) === '') {
                            continue;
                        }

                        $result['xml_read']++;
                        $packageResult['xml_read']++;

                        try {
                            app(SatCfdiXmlImportService::class)->importXmlContent(
                                xmlContent: $content,
                                companyId: (int) $request->company_id,
                                direction: (string) $request->direction,
                                userId: $request->requested_by_id ? (int) $request->requested_by_id : null,
                                source: 'sat_xml_package',
                            );

                            $result['imported']++;
                            $packageResult['imported']++;
                        } catch (Throwable $e) {
                            $error = [
                                'package_id' => $packageId,
                                'entry' => $entryName,
                                'error' => $e->getMessage(),
                            ];

                            $result['errors'][] = $error;
                            $result['errors_count']++;
                            $packageResult['errors_count']++;
                        }
                    }
                } finally {
                    $zip->close();
                }

                $this->updatePackageRow($request, $packageId, [
                    'status' => $packageResult['errors_count'] > 0 ? 'imported_with_errors' : 'imported',
                    'documents_count' => $packageResult['xml_read'],
                    'imported_at' => now(),
                    'error_message' => $packageResult['errors_count'] > 0
                        ? 'XML procesado con errores: ' . $packageResult['errors_count']
                        : null,
                    'metadata' => [
                        'xml_imported_at' => now()->toDateTimeString(),
                        'xml_import_result' => $packageResult,
                    ],
                ]);
            } catch (Throwable $e) {
                $error = [
                    'package_id' => $packageId,
                    'zip_path' => $zipPath,
                    'error' => $e->getMessage(),
                ];

                $result['errors'][] = $error;
                $result['errors_count']++;
                $packageResult['errors_count']++;

                $this->updatePackageRow($request, $packageId, [
                    'status' => 'import_error',
                    'error_message' => $e->getMessage(),
                    'metadata' => [
                        'xml_import_failed_at' => now()->toDateTimeString(),
                        'xml_import_error' => $e->getMessage(),
                    ],
                ]);
            }

            $result['packages'][] = $packageResult;
        }

        $message = "XML procesados: {$result['xml_read']}. Importados/actualizados: {$result['imported']}. Errores: {$result['errors_count']}.";

        $metadata = array_merge($request->metadata ?? [], [
            'xml_import_result' => $result,
        ]);

        if ($result['errors_count'] === 0) {
            $metadata['xml_processed_at'] = now()->toDateTimeString();
        } else {
            $metadata['xml_last_error_at'] = now()->toDateTimeString();
        }

        $request->forceFill([
            'sat_message' => $message,
            'metadata' => $metadata,
            'finished_at' => now(),
        ])->save();

        $result['message'] = $message;

        return $result;
    }

    private function downloadedPackagesFromRequest(SatDownloadRequest $request): array
    {
        $metadata = $request->metadata ?? [];
        $packages = $metadata['downloaded_packages'] ?? [];

        if (is_array($packages) && $packages !== []) {
            return array_values(array_filter($packages, function ($item): bool {
                return is_array($item) && filled($item['zip_path'] ?? $item['file_path'] ?? null);
            }));
        }

        if (! Schema::hasTable('sat_download_packages')) {
            return [];
        }

        return DB::table('sat_download_packages')
            ->where('sat_download_request_id', $request->id)
            ->whereNotNull('file_path')
            ->orderBy('id')
            ->get()
            ->map(fn ($row): array => [
                'package_id' => (string) ($row->package_id ?? ''),
                'zip_path' => (string) ($row->file_path ?? ''),
            ])
            ->all();
    }

    private function updatePackageRow(SatDownloadRequest $request, string $packageId, array $data): void
    {
        if (! Schema::hasTable('sat_download_packages') || $packageId === '') {
            return;
        }

        $values = [
            'updated_at' => now(),
        ];

        foreach (['status', 'documents_count', 'imported_at', 'error_message'] as $column) {
            if (Schema::hasColumn('sat_download_packages', $column) && array_key_exists($column, $data)) {
                $values[$column] = $data[$column];
            }
        }

        if (Schema::hasColumn('sat_download_packages', 'metadata') && array_key_exists('metadata', $data)) {
            $values['metadata'] = json_encode($data['metadata'], JSON_UNESCAPED_UNICODE);
        }

        DB::table('sat_download_packages')
            ->where('sat_download_request_id', $request->id)
            ->where('package_id', $packageId)
            ->update($values);
    }
}
