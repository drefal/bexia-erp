<?php

namespace App\Support\PayrollCfdi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PayrollCfdiAlternateFlowService
{
    public const STATUS_INTERNAL_ONLY = 'internal_only';
    public const STATUS_EXTERNAL_STAMPED = 'external_stamped';
    public const STATUS_CFDI_NOT_REQUIRED = 'cfdi_not_required';

    public const ACTION_MARK_INTERNAL_ONLY = 'mark_internal_only';
    public const ACTION_UNMARK_INTERNAL_ONLY = 'unmark_internal_only';
    public const ACTION_REGISTER_EXTERNAL_STAMP = 'register_external_stamp';
    public const ACTION_REMOVE_EXTERNAL_STAMP = 'remove_external_stamp';
    public const ACTION_MARK_CFDI_NOT_REQUIRED = 'mark_cfdi_not_required';
    public const ACTION_UNMARK_CFDI_NOT_REQUIRED = 'unmark_cfdi_not_required';

    public function summary(int $companyId, int $receiptId): array
    {
        $receipt = $this->findReceipt($companyId, $receiptId);
        $metadata = $this->decodeMetadata($receipt->metadata ?? null);

        return [
            'receipt' => $receipt,
            'metadata' => $metadata,
            'xml_exists' => filled($receipt->xml_path ?? null)
                ? Storage::disk('local')->exists($receipt->xml_path)
                : false,
            'pdf_exists' => filled($receipt->pdf_path ?? null)
                ? Storage::disk('local')->exists($receipt->pdf_path)
                : false,
            'audits' => DB::table('payroll_cfdi_audits')
                ->where('company_id', $companyId)
                ->where('payroll_cfdi_receipt_id', $receiptId)
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
        ];
    }

    public function markInternalOnly(int $companyId, int $receiptId, ?string $reason = null, ?int $userId = null): array
    {
        return DB::transaction(function () use ($companyId, $receiptId, $reason, $userId) {
            $receipt = $this->findReceipt($companyId, $receiptId);
            $this->assertCanMarkAlternate($receipt);

            $metadata = $this->decodeMetadata($receipt->metadata ?? null);
            $metadata['alternate_flow'] = 'internal_only';
            $metadata['internal_only'] = true;
            $metadata['internal_only_reason'] = $reason;
            $metadata['internal_only_marked_by'] = $userId;
            $metadata['internal_only_marked_at'] = now()->toDateTimeString();
            $metadata['previous_status_before_alternate'] = $receipt->status ?? null;

            DB::table('payroll_cfdi_receipts')
                ->where('company_id', $companyId)
                ->where('id', $receiptId)
                ->update([
                    'status' => self::STATUS_INTERNAL_ONLY,
                    'pac_error_message' => null,
                    'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);

            $this->audit($companyId, $receipt, self::ACTION_MARK_INTERNAL_ONLY, 'success', [
                'reason' => $reason,
            ], [
                'new_status' => self::STATUS_INTERNAL_ONLY,
            ], 'Recibo marcado como interno sin CFDI.', $userId);

            return $this->summary($companyId, $receiptId);
        });
    }

    public function markCfdiNotRequired(int $companyId, int $receiptId, ?string $reason = null, ?int $userId = null): array
    {
        return DB::transaction(function () use ($companyId, $receiptId, $reason, $userId) {
            $receipt = $this->findReceipt($companyId, $receiptId);
            $this->assertCanMarkAlternate($receipt);

            $metadata = $this->decodeMetadata($receipt->metadata ?? null);
            $metadata['alternate_flow'] = 'cfdi_not_required';
            $metadata['cfdi_not_required'] = true;
            $metadata['cfdi_not_required_reason'] = $reason;
            $metadata['cfdi_not_required_by'] = $userId;
            $metadata['cfdi_not_required_at'] = now()->toDateTimeString();
            $metadata['previous_status_before_alternate'] = $receipt->status ?? null;

            DB::table('payroll_cfdi_receipts')
                ->where('company_id', $companyId)
                ->where('id', $receiptId)
                ->update([
                    'status' => self::STATUS_CFDI_NOT_REQUIRED,
                    'pac_error_message' => null,
                    'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);

            $this->audit($companyId, $receipt, self::ACTION_MARK_CFDI_NOT_REQUIRED, 'success', [
                'reason' => $reason,
            ], [
                'new_status' => self::STATUS_CFDI_NOT_REQUIRED,
            ], 'Recibo marcado como CFDI no requerido.', $userId);

            return $this->summary($companyId, $receiptId);
        });
    }

    public function registerExternalStamp(
        int $companyId,
        int $receiptId,
        string $uuid,
        ?string $xmlPath = null,
        ?string $notes = null,
        ?int $userId = null
    ): array {
        return DB::transaction(function () use ($companyId, $receiptId, $uuid, $xmlPath, $notes, $userId) {
            $receipt = $this->findReceipt($companyId, $receiptId);
            $this->assertCanMarkAlternate($receipt);
            $this->assertValidUuid($uuid);

            if ($xmlPath && ! Storage::disk('local')->exists($xmlPath)) {
                throw ValidationException::withMessages([
                    'xml_path' => "No existe el XML en storage local: {$xmlPath}",
                ]);
            }

            $metadata = $this->decodeMetadata($receipt->metadata ?? null);
            $metadata['alternate_flow'] = 'external_stamped';
            $metadata['external_stamp'] = true;
            $metadata['external_uuid'] = $uuid;
            $metadata['external_xml_path'] = $xmlPath;
            $metadata['external_notes'] = $notes;
            $metadata['external_registered_by'] = $userId;
            $metadata['external_registered_at'] = now()->toDateTimeString();
            $metadata['previous_status_before_alternate'] = $receipt->status ?? null;
            $metadata['previous_uuid_before_external'] = $receipt->uuid ?? null;
            $metadata['previous_pac_provider_before_external'] = $receipt->pac_provider ?? null;
            $metadata['previous_xml_path_before_external'] = $receipt->xml_path ?? null;

            $update = [
                'status' => self::STATUS_EXTERNAL_STAMPED,
                'uuid' => $uuid,
                'pac_provider' => 'external',
                'pac_error_message' => null,
                'stamped_at' => now(),
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ];

            if ($xmlPath) {
                $update['xml_path'] = $xmlPath;
            }

            DB::table('payroll_cfdi_receipts')
                ->where('company_id', $companyId)
                ->where('id', $receiptId)
                ->update($update);

            $this->audit($companyId, $receipt, self::ACTION_REGISTER_EXTERNAL_STAMP, 'success', [
                'uuid' => $uuid,
                'xml_path' => $xmlPath,
                'notes' => $notes,
            ], [
                'new_status' => self::STATUS_EXTERNAL_STAMPED,
                'pac_provider' => 'external',
            ], 'CFDI timbrado externamente registrado en Bexia.', $userId);

            return $this->summary($companyId, $receiptId);
        });
    }

    public function revertAlternate(int $companyId, int $receiptId, ?string $reason = null, bool $force = false, ?int $userId = null): array
    {
        return DB::transaction(function () use ($companyId, $receiptId, $reason, $force, $userId) {
            $receipt = $this->findReceipt($companyId, $receiptId);
            $metadata = $this->decodeMetadata($receipt->metadata ?? null);
            $status = (string) ($receipt->status ?? '');

            if (! in_array($status, [
                self::STATUS_INTERNAL_ONLY,
                self::STATUS_CFDI_NOT_REQUIRED,
                self::STATUS_EXTERNAL_STAMPED,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => "El recibo no esta en un estado alterno reversible. Estado actual: {$status}",
                ]);
            }

            if ($status === self::STATUS_EXTERNAL_STAMPED && ! $force) {
                throw ValidationException::withMessages([
                    'force' => 'Para revertir external_stamped se requiere --force.',
                ]);
            }

            $action = match ($status) {
                self::STATUS_INTERNAL_ONLY => self::ACTION_UNMARK_INTERNAL_ONLY,
                self::STATUS_CFDI_NOT_REQUIRED => self::ACTION_UNMARK_CFDI_NOT_REQUIRED,
                self::STATUS_EXTERNAL_STAMPED => self::ACTION_REMOVE_EXTERNAL_STAMP,
                default => 'revert_alternate',
            };

            $metadata['alternate_flow_reverted'] = $status;
            $metadata['alternate_flow_reverted_reason'] = $reason;
            $metadata['alternate_flow_reverted_by'] = $userId;
            $metadata['alternate_flow_reverted_at'] = now()->toDateTimeString();
            $metadata['alternate_flow'] = null;
            $metadata['internal_only'] = false;
            $metadata['cfdi_not_required'] = false;
            $metadata['external_stamp'] = false;

            $targetStatus = in_array(($metadata['previous_status_before_alternate'] ?? null), ['draft', 'validated', 'stamp_error'], true)
                ? $metadata['previous_status_before_alternate']
                : 'validated';

            $update = [
                'status' => $targetStatus,
                'pac_error_message' => null,
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ];

            if ($status === self::STATUS_EXTERNAL_STAMPED) {
                $update['uuid'] = $metadata['previous_uuid_before_external'] ?? null;
                $update['pac_provider'] = $metadata['previous_pac_provider_before_external'] ?? 'sw';
                $update['xml_path'] = $metadata['previous_xml_path_before_external'] ?? $receipt->xml_path;
                $update['stamped_at'] = null;
            }

            DB::table('payroll_cfdi_receipts')
                ->where('company_id', $companyId)
                ->where('id', $receiptId)
                ->update($update);

            $this->audit($companyId, $receipt, $action, 'success', [
                'reason' => $reason,
                'force' => $force,
                'old_status' => $status,
            ], [
                'new_status' => $targetStatus,
            ], 'Estado alterno CFDI nómina revertido.', $userId);

            return $this->summary($companyId, $receiptId);
        });
    }

    private function findReceipt(int $companyId, int $receiptId): object
    {
        $receipt = DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('id', $receiptId)
            ->first();

        if (! $receipt) {
            throw ValidationException::withMessages([
                'receipt' => "No existe recibo CFDI nómina {$receiptId} para company_id {$companyId}.",
            ]);
        }

        return $receipt;
    }

    private function assertCanMarkAlternate(object $receipt): void
    {
        $status = (string) ($receipt->status ?? '');

        if (in_array($status, ['stamped', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => "No se puede aplicar camino alterno a un recibo en estado {$status}.",
            ]);
        }

        if (in_array($status, [
            self::STATUS_INTERNAL_ONLY,
            self::STATUS_EXTERNAL_STAMPED,
            self::STATUS_CFDI_NOT_REQUIRED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => "El recibo ya está en estado alterno {$status}. Primero debe revertirse.",
            ]);
        }
    }

    private function assertValidUuid(string $uuid): void
    {
        if (! preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/', $uuid)) {
            throw ValidationException::withMessages([
                'uuid' => 'El UUID externo no tiene formato válido.',
            ]);
        }
    }

    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_object($metadata)) {
            return json_decode(json_encode($metadata), true) ?: [];
        }

        if (is_string($metadata) && trim($metadata) !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function audit(
        int $companyId,
        object $receipt,
        string $action,
        string $status,
        array $requestMeta,
        array $responseMeta,
        string $message,
        ?int $userId = null
    ): void {
        DB::table('payroll_cfdi_audits')->insert([
            'company_id' => $companyId,
            'payroll_cfdi_receipt_id' => $receipt->id ?? null,
            'payroll_run_id' => $receipt->payroll_run_id ?? null,
            'payroll_run_line_id' => $receipt->payroll_run_line_id ?? null,
            'employee_id' => $receipt->employee_id ?? null,
            'user_id' => $userId,
            'action' => $action,
            'status' => $status,
            'pac_provider' => $receipt->pac_provider ?? null,
            'pac_test_env' => $receipt->pac_test_env ?? null,
            'request_id' => null,
            'request_meta' => json_encode($requestMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'response_meta' => json_encode($responseMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'message' => $message,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
