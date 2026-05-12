<?php

namespace App\Support\Billing;

use App\Models\Invoice;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InvoiceCfdiCancelService
{
    public function reasonOptions(): array
    {
        if (! Schema::hasTable('sat_cfdi_cancellation_reasons')) {
            return $this->fallbackReasonOptions();
        }

        $items = DB::table('sat_cfdi_cancellation_reasons')
            ->where('active', true)
            ->orderBy('code')
            ->get();

        if ($items->isEmpty()) {
            return $this->fallbackReasonOptions();
        }

        return $items
            ->mapWithKeys(fn ($item) => [
                (string) $item->code => $item->code.' - '.$item->name,
            ])
            ->all();
    }

    public function prepareCancellation(Invoice $invoice, mixed $user, array $data): array
    {
        $invoice->refresh();

        if ((string) ($invoice->cfdi_status ?? '') !== 'stamped') {
            return [
                'success' => false,
                'message' => 'Solo se puede preparar cancelación fiscal de facturas timbradas.',
            ];
        }

        if (blank($invoice->cfdi_uuid ?? null)) {
            return [
                'success' => false,
                'message' => 'La factura no tiene UUID CFDI.',
            ];
        }

        $reasonCode = str_pad(trim((string) ($data['reason_code'] ?? '')), 2, '0', STR_PAD_LEFT);
        $replacementUuid = strtoupper(trim((string) ($data['replacement_uuid'] ?? '')));
        $internalComment = trim((string) ($data['internal_comment'] ?? ''));

        if ($internalComment === '') {
            return [
                'success' => false,
                'message' => 'El comentario interno de cancelación es obligatorio.',
            ];
        }

        $reason = $this->findReason($reasonCode);

        if (! $reason) {
            return [
                'success' => false,
                'message' => 'Motivo de cancelación SAT inválido o inactivo.',
            ];
        }

        $requiresReplacement = (bool) ($reason->requires_replacement_uuid ?? false);

        if ($requiresReplacement && $replacementUuid === '') {
            return [
                'success' => false,
                'message' => 'El motivo 01 requiere UUID del CFDI sustituto.',
            ];
        }

        if ($replacementUuid !== '' && ! $this->isUuid($replacementUuid)) {
            return [
                'success' => false,
                'message' => 'El UUID sustituto no tiene formato válido.',
            ];
        }

        if ($replacementUuid !== '' && strtoupper((string) $invoice->cfdi_uuid) === $replacementUuid) {
            return [
                'success' => false,
                'message' => 'El UUID sustituto no puede ser el mismo CFDI que se desea cancelar.',
            ];
        }

        if (! $requiresReplacement) {
            $replacementUuid = null;
        }

        $message = 'Cancelación preparada. Pendiente de envío real al PAC/SAT.';

        try {
            $updates = [
                'cfdi_cancel_reason_code' => $reasonCode,
                'cfdi_cancel_replacement_uuid' => $replacementUuid,
                'cfdi_cancel_status' => 'ready_to_cancel',
                'cfdi_cancel_status_message' => $message,
                'cfdi_cancel_internal_comment' => $internalComment !== '' ? $internalComment : null,
            ];

            foreach (array_keys($updates) as $column) {
                if (! Schema::hasColumn('invoices', $column)) {
                    unset($updates[$column]);
                }
            }

            if ($updates !== []) {
                $invoice->forceFill($updates)->save();
                $invoice->refresh();
            }

            $this->audit($invoice, $user, [
                'action' => 'prepare_cfdi_cancel',
                'status' => 'ready_to_cancel',
                'message' => $message,
                'request_meta' => [
                    'reason_code' => $reasonCode,
                    'reason_name' => (string) ($reason->name ?? ''),
                    'replacement_uuid' => $replacementUuid,
                    'internal_comment' => $internalComment,
                    'invoice_id' => (int) $invoice->id,
                    'uuid' => (string) $invoice->cfdi_uuid,
                ],
                'response_meta' => [
                    'sent_to_pac' => false,
                    'stage' => 'R9A',
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('CFDI_CANCEL_PREPARE_ERROR', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'No se pudo preparar la cancelación: '.$e->getMessage(),
            ];
        }

        return [
            'success' => true,
            'message' => $message,
        ];
    }


    public function sendCancellationToPac(Invoice $invoice, mixed $user): array
    {
        $invoice->refresh();

        if ((string) ($invoice->cfdi_status ?? '') !== 'stamped') {
            return [
                'success' => false,
                'message' => 'Solo se puede enviar cancelación fiscal de facturas timbradas.',
            ];
        }

        if ((string) ($invoice->cfdi_cancel_status ?? '') !== 'ready_to_cancel') {
            return [
                'success' => false,
                'message' => 'Primero registra la solicitud de cancelación.',
            ];
        }

        $uuid = strtoupper(trim((string) ($invoice->cfdi_uuid ?? '')));
        $reasonCode = str_pad(trim((string) ($invoice->cfdi_cancel_reason_code ?? '')), 2, '0', STR_PAD_LEFT);
        $replacementUuid = strtoupper(trim((string) ($invoice->cfdi_cancel_replacement_uuid ?? '')));

        if ($uuid === '') {
            return [
                'success' => false,
                'message' => 'La factura no tiene UUID CFDI.',
            ];
        }

        $reason = $this->findReason($reasonCode);

        if (! $reason) {
            return [
                'success' => false,
                'message' => 'Motivo de cancelación SAT inválido o inactivo.',
            ];
        }

        if ((bool) ($reason->requires_replacement_uuid ?? false) && $replacementUuid === '') {
            return [
                'success' => false,
                'message' => 'El motivo 01 requiere UUID sustituto antes de enviar al PAC/SAT.',
            ];
        }

        $company = Company::query()->find((int) $invoice->company_id);

        if (! $company) {
            return [
                'success' => false,
                'message' => 'La factura no tiene empresa válida.',
            ];
        }

        DB::table('invoices')
            ->where('id', (int) $invoice->id)
            ->update([
                'cfdi_cancel_status' => 'sending_to_pac',
                'cfdi_cancel_status_message' => 'Enviando cancelación al PAC/SAT.',
                'cfdi_cancel_requested_at' => now(),
                'pac_error_message' => null,
                'updated_at' => now(),
            ]);

        $invoice->refresh();

        $result = app(SwPacClient::class)->cancelCfdi(
            $company,
            $uuid,
            $reasonCode,
            $replacementUuid !== '' ? $replacementUuid : null
        );

        if (! ($result['success'] ?? false)) {
            return $this->cancelError(
                $invoice->refresh(),
                $user,
                (string) ($result['message'] ?? 'No se pudo cancelar con SW.'),
                $result
            );
        }

        $isCancelled = (string) ($result['status'] ?? '') === 'cancelled';
        $basePath = 'invoices/cfdi/company_'.$invoice->company_id.'/invoice_'.$invoice->id;
        $ackPath = $basePath.'/cancelacion_sw_'.now()->format('Ymd_His').'.json';

        Storage::disk('local')->put($ackPath, json_encode([
            'invoice_id' => (int) $invoice->id,
            'uuid' => $uuid,
            'reason_code' => $reasonCode,
            'replacement_uuid' => $replacementUuid !== '' ? $replacementUuid : null,
            'result' => $result,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $message = (string) ($result['message'] ?? (
            $isCancelled
                ? 'CFDI cancelado correctamente con SW.'
                : 'Solicitud de cancelación enviada a SW.'
        ));

        $updates = [
            'cfdi_cancel_status' => $isCancelled ? 'cancelled' : 'cancel_requested',
            'cfdi_cancel_status_message' => $message,
            'cfdi_cancel_ack_path' => $ackPath,
            'cfdi_cancel_requested_at' => now(),
            'pac_provider' => 'sw',
            'pac_environment' => (string) ($result['environment'] ?? ''),
            'pac_request_id' => $result['request_id'] ?? null,
            'pac_error_message' => null,
            'updated_at' => now(),
        ];

        if ($isCancelled) {
            $updates['cfdi_status'] = 'cancelled';
            $updates['cfdi_cancelled_at'] = now();
            $updates['status'] = 'cancelled';
        } else {
            $updates['cfdi_status'] = 'cancel_requested';
        }

        DB::table('invoices')
            ->where('id', (int) $invoice->id)
            ->update($updates);

        $invoice->refresh();

        $this->audit($invoice, $user, [
            'action' => 'send_cfdi_cancel',
            'status' => $updates['cfdi_cancel_status'],
            'message' => $message,
            'request_meta' => [
                'invoice_id' => (int) $invoice->id,
                'invoice_number' => (string) ($invoice->number ?? ''),
                'uuid' => $uuid,
                'reason_code' => $reasonCode,
                'replacement_uuid' => $replacementUuid !== '' ? $replacementUuid : null,
            ],
            'response_meta' => [
                'ack_path' => $ackPath,
                'cancel_code' => $result['cancel_code'] ?? null,
                'http_status' => $result['http_status'] ?? null,
                'endpoint' => $result['endpoint'] ?? null,
                'environment' => $result['environment'] ?? null,
                'response' => $result['response_meta'] ?? null,
            ],
        ]);

        return [
            'success' => true,
            'status' => $updates['cfdi_cancel_status'],
            'message' => $message,
            'ack_path' => $ackPath,
        ];
    }

    private function cancelError(Invoice $invoice, mixed $user, string $message, array $meta = []): array
    {
        $safeMessage = mb_substr(trim($message), 0, 1000);

        DB::table('invoices')
            ->where('id', (int) $invoice->id)
            ->update([
                'cfdi_cancel_status' => 'cancel_error',
                'cfdi_cancel_status_message' => $safeMessage,
                'pac_error_message' => $safeMessage,
                'updated_at' => now(),
            ]);

        $invoice->refresh();

        $this->audit($invoice, $user, [
            'action' => 'send_cfdi_cancel',
            'status' => 'cancel_error',
            'message' => $safeMessage,
            'request_meta' => [
                'invoice_id' => (int) $invoice->id,
                'invoice_number' => (string) ($invoice->number ?? ''),
                'uuid' => (string) ($invoice->cfdi_uuid ?? ''),
                'reason_code' => (string) ($invoice->cfdi_cancel_reason_code ?? ''),
                'replacement_uuid' => (string) ($invoice->cfdi_cancel_replacement_uuid ?? ''),
            ],
            'response_meta' => [
                'error' => $safeMessage,
                'meta' => $meta['meta'] ?? $meta,
            ],
        ]);

        return [
            'success' => false,
            'status' => 'cancel_error',
            'message' => $safeMessage,
            'meta' => $meta,
        ];
    }


    private function findReason(string $code): ?object
    {
        if (! Schema::hasTable('sat_cfdi_cancellation_reasons')) {
            $fallback = [
                '01' => (object) ['code' => '01', 'name' => 'Comprobante emitido con errores con relación', 'requires_replacement_uuid' => true],
                '02' => (object) ['code' => '02', 'name' => 'Comprobante emitido con errores sin relación', 'requires_replacement_uuid' => false],
                '03' => (object) ['code' => '03', 'name' => 'No se llevó a cabo la operación', 'requires_replacement_uuid' => false],
                '04' => (object) ['code' => '04', 'name' => 'Operación nominativa relacionada en una factura global', 'requires_replacement_uuid' => false],
            ];

            return $fallback[$code] ?? null;
        }

        return DB::table('sat_cfdi_cancellation_reasons')
            ->where('code', $code)
            ->where('active', true)
            ->first();
    }

    private function fallbackReasonOptions(): array
    {
        return [
            '01' => '01 - Comprobante emitido con errores con relación',
            '02' => '02 - Comprobante emitido con errores sin relación',
            '03' => '03 - No se llevó a cabo la operación',
            '04' => '04 - Operación nominativa relacionada en una factura global',
        ];
    }

    private function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/i', $value);
    }

    private function audit(Invoice $invoice, mixed $user, array $payload): void
    {
        try {
            $validator = app(InvoiceCfdiValidator::class);

            if (method_exists($validator, 'audit')) {
                $validator->audit($invoice, $user, [
                    'action' => $payload['action'] ?? 'prepare_cfdi_cancel',
                    'status' => $payload['status'] ?? 'ready_to_cancel',
                    'pac_provider' => (string) ($invoice->pac_provider ?? 'sw'),
                    'pac_environment' => (string) ($invoice->pac_environment ?? ''),
                    'message' => $payload['message'] ?? '',
                    'request_meta' => $payload['request_meta'] ?? [],
                    'response_meta' => $payload['response_meta'] ?? [],
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('CFDI_CANCEL_PREPARE_AUDIT_ERROR', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
