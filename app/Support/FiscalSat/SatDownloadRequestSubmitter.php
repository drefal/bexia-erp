<?php

namespace App\Support\FiscalSat;

use App\Models\SatCompanyCredential;
use App\Models\SatDownloadRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTimePeriod;
use PhpCfdi\SatWsDescargaMasiva\Shared\DocumentStatus;
use PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType;
use PhpCfdi\SatWsDescargaMasiva\Shared\RequestType;
use RuntimeException;

class SatDownloadRequestSubmitter
{
    public function submit(SatDownloadRequest $request): array
    {
        $request->refresh();

        try {
            $credential = $this->credentialForRequest($request);

            $queryParameters = $this->buildQueryParameters($request);

            $service = app(SatMassDownloadClientFactory::class)
                ->createForCredential($credential);

            $query = $service->query($queryParameters);

            $status = $query->getStatus();
            $accepted = $status->isAccepted();

            $message = method_exists($status, 'getMessage')
                ? $status->getMessage()
                : (string) $status;

            $statusCode = method_exists($status, 'getCode')
                ? (string) $status->getCode()
                : null;

            $requestId = $accepted ? $query->getRequestId() : null;

            $request->forceFill([
                'status' => $accepted ? 'requested' : 'error',
                'requested_at' => now(),
                'request_uuid' => $requestId,
                'sat_status_code' => $statusCode,
                'sat_message' => $message,
                'error_message' => $accepted ? null : $message,
                'metadata' => array_merge($request->metadata ?? [], [
                    'submitted_at' => now()->toDateTimeString(),
                    'credential_id' => $credential->id,
                    'request_kind' => $request->request_kind,
                    'direction' => $request->direction,
                    'forced_document_status_active' => $this->shouldForceActiveStatus($request),
                ]),
            ])->save();

            return [
                'ok' => $accepted,
                'request_id' => $requestId,
                'status_code' => $statusCode,
                'message' => $message,
            ];
        } catch (\Throwable $e) {
            $request->forceFill([
                'status' => 'error',
                'requested_at' => now(),
                'error_message' => $e->getMessage(),
                'sat_message' => $e->getMessage(),
            ])->save();

            return [
                'ok' => false,
                'request_id' => null,
                'status_code' => null,
                'message' => $e->getMessage(),
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

    private function buildQueryParameters(SatDownloadRequest $request): QueryParameters
    {
        $from = Carbon::parse($request->date_from)->format('Y-m-d H:i:s');
        $to = Carbon::parse($request->date_to)->format('Y-m-d H:i:s');

        if (Carbon::parse($from)->greaterThanOrEqualTo(Carbon::parse($to))) {
            throw new RuntimeException('La fecha inicial debe ser menor que la fecha final.');
        }

        $parameters = QueryParameters::create(
            DateTimePeriod::createFromValues($from, $to)
        );

        $parameters = $parameters->withDownloadType(
            $request->direction === 'received'
                ? DownloadType::received()
                : DownloadType::issued()
        );

        $parameters = $parameters->withRequestType(
            $request->request_kind === 'xml'
                ? RequestType::xml()
                : RequestType::metadata()
        );

        if ($this->shouldForceActiveStatus($request)) {
            $parameters = $parameters->withDocumentStatus(DocumentStatus::active());
        }

        return $parameters;
    }

    private function shouldForceActiveStatus(SatDownloadRequest $request): bool
    {
        return $request->direction === 'received'
            && $request->request_kind === 'xml';
    }
}
