<?php

namespace App\Support\FiscalSat;

use App\Models\SatCompanyCredential;
use App\Models\SatDownloadRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SatDownloadRequestVerifier
{
    public function verify(SatDownloadRequest $request): array
    {
        $request->refresh();

        try {
            if (! $request->request_uuid) {
                throw new RuntimeException('La solicitud no tiene UUID SAT para verificar.');
            }

            $credential = $this->credentialForRequest($request);

            $service = app(SatMassDownloadClientFactory::class)
                ->createForCredential($credential);

            $verify = $service->verify((string) $request->request_uuid);

            $status = $verify->getStatus();

            if (! $status->isAccepted()) {
                $message = method_exists($status, 'getMessage')
                    ? $status->getMessage()
                    : 'SAT no aceptó la verificación.';

                $this->markError($request, $message, [
                    'verify_status' => $this->safeStatus($status),
                ]);

                return [
                    'ok' => false,
                    'ready' => false,
                    'message' => $message,
                    'packages_count' => 0,
                    'packages' => [],
                ];
            }

            $codeRequest = $verify->getCodeRequest();
            $statusRequest = $verify->getStatusRequest();

            if (! $codeRequest->isAccepted()) {
                $message = method_exists($codeRequest, 'getMessage')
                    ? $codeRequest->getMessage()
                    : 'La solicitud fue rechazada por SAT.';

                $this->markError($request, $message, [
                    'code_request' => $this->safeStatus($codeRequest),
                    'status_request' => $this->safeStatus($statusRequest),
                ]);

                return [
                    'ok' => false,
                    'ready' => false,
                    'message' => $message,
                    'packages_count' => 0,
                    'packages' => [],
                ];
            }

            $packages = iterator_to_array($verify->getPackagesIds());
            $packagesCount = count($packages);

            if ($statusRequest->isFinished()) {
                $this->storePackages($request, $packages);

                $request->forceFill([
                    'status' => 'completed',
                    'finished_at' => now(),
                    'sat_status_code' => method_exists($codeRequest, 'getCode') ? (string) $codeRequest->getCode() : $request->sat_status_code,
                    'sat_message' => 'Solicitud completada. Paquetes disponibles: ' . $packagesCount,
                    'error_message' => null,
                    'metadata' => array_merge($request->metadata ?? [], [
                        'verified_at' => now()->toDateTimeString(),
                        'verify_status' => $this->safeStatus($status),
                        'code_request' => $this->safeStatus($codeRequest),
                        'status_request' => $this->safeStatus($statusRequest),
                        'packages_count' => $packagesCount,
                        'packages' => $packages,
                    ]),
                ])->save();

                return [
                    'ok' => true,
                    'ready' => true,
                    'message' => 'Solicitud completada. Paquetes disponibles: ' . $packagesCount,
                    'packages_count' => $packagesCount,
                    'packages' => $packages,
                ];
            }

            if ($statusRequest->isInProgress() || $statusRequest->isAccepted()) {
                $request->forceFill([
                    'status' => 'processing',
                    'sat_message' => 'SAT sigue procesando la solicitud.',
                    'error_message' => null,
                    'metadata' => array_merge($request->metadata ?? [], [
                        'verified_at' => now()->toDateTimeString(),
                        'verify_status' => $this->safeStatus($status),
                        'code_request' => $this->safeStatus($codeRequest),
                        'status_request' => $this->safeStatus($statusRequest),
                        'packages_count' => $packagesCount,
                    ]),
                ])->save();

                return [
                    'ok' => true,
                    'ready' => false,
                    'message' => 'SAT sigue procesando la solicitud. Intenta verificar más tarde.',
                    'packages_count' => $packagesCount,
                    'packages' => $packages,
                ];
            }

            $message = 'La solicitud no se puede completar. Estado SAT: ' . $this->safeStatus($statusRequest);

            $this->markError($request, $message, [
                'verify_status' => $this->safeStatus($status),
                'code_request' => $this->safeStatus($codeRequest),
                'status_request' => $this->safeStatus($statusRequest),
                'packages_count' => $packagesCount,
            ]);

            return [
                'ok' => false,
                'ready' => false,
                'message' => $message,
                'packages_count' => $packagesCount,
                'packages' => $packages,
            ];
        } catch (\Throwable $e) {
            $this->markError($request, $e->getMessage());

            return [
                'ok' => false,
                'ready' => false,
                'message' => $e->getMessage(),
                'packages_count' => 0,
                'packages' => [],
            ];
        }
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

    private function markError(SatDownloadRequest $request, string $message, array $extraMetadata = []): void
    {
        $request->forceFill([
            'status' => 'error',
            'error_message' => $message,
            'sat_message' => $message,
            'metadata' => array_merge($request->metadata ?? [], [
                'verified_at' => now()->toDateTimeString(),
            ], $extraMetadata),
        ])->save();
    }

    private function storePackages(SatDownloadRequest $request, array $packages): void
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

        foreach ($packages as $packageId) {
            $where = [
                $requestColumn => $request->id,
                $packageColumn => $packageId,
            ];

            $values = [];

            if (Schema::hasColumn('sat_download_packages', 'company_id')) {
                $values['company_id'] = $request->company_id;
            }

            if (Schema::hasColumn('sat_download_packages', 'status')) {
                $values['status'] = 'available';
            }

            if (Schema::hasColumn('sat_download_packages', 'metadata')) {
                $values['metadata'] = json_encode([
                    'found_at' => now()->toDateTimeString(),
                    'request_uuid' => $request->request_uuid,
                ], JSON_UNESCAPED_UNICODE);
            }

            if (Schema::hasColumn('sat_download_packages', 'created_at')) {
                $values['created_at'] = now();
            }

            if (Schema::hasColumn('sat_download_packages', 'updated_at')) {
                $values['updated_at'] = now();
            }

            DB::table('sat_download_packages')->updateOrInsert($where, $values);
        }
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
