<?php

namespace App\Support\FiscalSat;

use App\Models\SatDownloadRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpCfdi\SatWsDescargaMasiva\PackageReader\MetadataPackageReader;
use RuntimeException;

class SatMetadataPackageImporter
{
    public function importFromRequest(SatDownloadRequest $request): array
    {
        $request->refresh();

        if ($request->request_kind !== 'metadata') {
            throw new RuntimeException('Esta acción solo aplica para descargas de metadata.');
        }

        $packages = $this->downloadedPackagesFromRequest($request);

        if ($packages === []) {
            throw new RuntimeException('No se encontraron paquetes descargados para procesar.');
        }

        $result = [
            'ok' => true,
            'packages_count' => count($packages),
            'records_read' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'sample' => [],
        ];

        foreach ($packages as $package) {
            $zipPath = $package['zip_path'] ?? null;

            if (! $zipPath || ! Storage::disk('local')->exists($zipPath)) {
                $result['errors'][] = [
                    'package_id' => $package['package_id'] ?? null,
                    'message' => 'No se encontró el archivo ZIP descargado.',
                ];
                continue;
            }

            $absolutePath = Storage::disk('local')->path($zipPath);

            try {
                $reader = MetadataPackageReader::createFromFile($absolutePath);

                foreach ($reader->metadata() as $item) {
                    $data = method_exists($item, 'all') ? $item->all() : (array) $item->jsonSerialize();

                    $uuid = trim((string) ($data['uuid'] ?? ''));

                    if ($uuid === '') {
                        $result['skipped']++;
                        continue;
                    }

                    $import = $this->upsertCfdiDocument($request, $data, $package);

                    $result['records_read']++;

                    if ($import === 'inserted') {
                        $result['inserted']++;
                    } else {
                        $result['updated']++;
                    }

                    if (count($result['sample']) < 5) {
                        $result['sample'][] = [
                            'uuid' => $uuid,
                            'rfc_emisor' => $data['rfcEmisor'] ?? null,
                            'rfc_receptor' => $data['rfcReceptor'] ?? null,
                            'monto' => $data['monto'] ?? null,
                            'efecto' => $data['efectoComprobante'] ?? null,
                            'estatus' => $data['estatus'] ?? null,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $result['errors'][] = [
                    'package_id' => $package['package_id'] ?? null,
                    'zip_path' => $zipPath,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $result['ok'] = $result['errors'] === [];

        $message = "Metadata procesada: {$result['records_read']} CFDI. Nuevos: {$result['inserted']}. Actualizados: {$result['updated']}. Omitidos: {$result['skipped']}.";

        if ($result['errors'] !== []) {
            $message .= ' Errores: ' . count($result['errors']) . '.';
        }

        $request->forceFill([
            'sat_message' => $message,
            'error_message' => $result['errors'] === [] ? null : json_encode($result['errors'], JSON_UNESCAPED_UNICODE),
            'metadata' => array_merge($request->metadata ?? [], [
                'metadata_processed_at' => now()->toDateTimeString(),
                'metadata_import_result' => $result,
            ]),
        ])->save();

        return $result + [
            'message' => $message,
        ];
    }

    private function downloadedPackagesFromRequest(SatDownloadRequest $request): array
    {
        $metadata = $request->metadata ?? [];
        $packages = $metadata['downloaded_packages'] ?? [];

        if (is_array($packages) && $packages !== []) {
            return array_values(array_filter($packages, fn ($item) => is_array($item) && filled($item['zip_path'] ?? null)));
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

        $pathColumn = collect(['file_path', 'zip_path', 'storage_path'])
            ->first(fn ($column) => Schema::hasColumn('sat_download_packages', $column));

        if (! $requestColumn || ! $packageColumn || ! $pathColumn) {
            return [];
        }

        return DB::table('sat_download_packages')
            ->where($requestColumn, $request->id)
            ->whereNotNull($pathColumn)
            ->get()
            ->map(fn ($row) => [
                'package_id' => (string) ($row->{$packageColumn} ?? ''),
                'zip_path' => (string) ($row->{$pathColumn} ?? ''),
            ])
            ->filter(fn ($item) => filled($item['zip_path']))
            ->values()
            ->all();
    }

    private function upsertCfdiDocument(SatDownloadRequest $request, array $data, array $package): string
    {
        if (! Schema::hasTable('sat_cfdi_documents')) {
            throw new RuntimeException('No existe la tabla sat_cfdi_documents.');
        }

        $columns = Schema::getColumnListing('sat_cfdi_documents');
        $has = fn (string $column): bool => in_array($column, $columns, true);

        $uuid = strtoupper(trim((string) ($data['uuid'] ?? '')));

        $where = [];

        if ($has('company_id')) {
            $where['company_id'] = $request->company_id;
        }

        if ($has('uuid')) {
            $where['uuid'] = $uuid;
        }

        if ($has('direction')) {
            $where['direction'] = $request->direction;
        }

        if (! isset($where['uuid'])) {
            throw new RuntimeException('La tabla sat_cfdi_documents no tiene columna uuid.');
        }

        $values = [];

        $set = function (string $column, mixed $value) use (&$values, $has): void {
            if ($has($column)) {
                $values[$column] = $value;
            }
        };

        $set('company_id', $request->company_id);
        $set('direction', $request->direction);
        $set('uuid', $uuid);
        $set('source', 'sat_metadata');
        $set('status', $this->normalizeCfdiStatus($data['estatus'] ?? null));
        $set('cfdi_type', $this->normalizeCfdiType($data['efectoComprobante'] ?? null));
        $set('issuer_rfc', $data['rfcEmisor'] ?? null);
        $set('issuer_name', $data['nombreEmisor'] ?? null);
        $set('receiver_rfc', $data['rfcReceptor'] ?? null);
        $set('receiver_name', $data['nombreReceptor'] ?? null);
        $set('pac_rfc', $data['rfcPac'] ?? null);
        $set('issued_at', $this->parseDate($data['fechaEmision'] ?? null));
        $set('certified_at', $this->parseDate($data['fechaCertificacionSat'] ?? null));
        $set('canceled_at', $this->parseDate($data['fechaCancelacion'] ?? null));
        $set('total', $this->parseAmount($data['monto'] ?? null));
        $set('currency', 'MXN');
        $set('imported_at', now());
        $set('metadata', json_encode([
            'sat_metadata' => $data,
            'sat_download_request_id' => $request->id,
            'sat_request_uuid' => $request->request_uuid,
            'sat_package_id' => $package['package_id'] ?? null,
            'imported_from' => 'sat_metadata_package',
            'imported_at' => now()->toDateTimeString(),
        ], JSON_UNESCAPED_UNICODE));

        if ($has('updated_at')) {
            $values['updated_at'] = now();
        }

        $existingQuery = DB::table('sat_cfdi_documents');

        if (isset($where['company_id'])) {
            $existingQuery->where('company_id', $where['company_id']);
        }

        if (isset($where['direction'])) {
            $existingQuery->where('direction', $where['direction']);
        }

        if (isset($where['uuid'])) {
            $existingQuery->whereRaw('upper(uuid) = ?', [$uuid]);
        }

        $existing = $existingQuery->first();

        if ($existing) {
            $updateValues = $values;

            if (! empty($existing->source ?? null) && ($existing->source ?? null) !== 'sat_metadata') {
                unset($updateValues['source']);
            }

            DB::table('sat_cfdi_documents')
                ->where('id', $existing->id)
                ->update($updateValues);

            return 'updated';
        }

        if ($has('created_at')) {
            $values['created_at'] = now();
        }

        DB::table('sat_cfdi_documents')->insert($values);

        return 'inserted';
    }

    private function normalizeCfdiType(?string $value): ?string
    {
        $value = trim((string) $value);

        return match (mb_strtolower($value)) {
            'ingreso', 'i' => 'I',
            'egreso', 'e' => 'E',
            'pago', 'p' => 'P',
            'nomina', 'nómina', 'n' => 'N',
            'traslado', 't' => 'T',
            default => $value !== '' ? $value : null,
        };
    }

    private function normalizeCfdiStatus(?string $value): string
    {
        $value = trim((string) $value);

        return match (mb_strtolower($value)) {
            'vigente', '1' => 'vigente',
            'cancelado', 'cancelada', '0' => 'cancelado',
            default => $value !== '' ? mb_strtolower($value) : 'metadata',
        };
    }

    private function parseDate(?string $value): mixed
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseAmount(?string $value): ?float
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = str_replace([',', '$', ' '], '', $value);

        return is_numeric($value) ? (float) $value : null;
    }
}
