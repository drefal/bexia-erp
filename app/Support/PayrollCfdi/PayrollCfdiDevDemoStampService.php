<?php

namespace App\Support\PayrollCfdi;

use DOMDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PayrollCfdiDevDemoStampService
{
    public function stampDemo(int $companyId, int $receiptId, ?int $userId = null): array
    {
        $this->assertNotProduction();
        $this->assertSchema();

        $receipt = DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('id', $receiptId)
            ->first();

        if (! $receipt) {
            return $this->failure("No existe el recibo CFDI nomina {$receiptId} para empresa {$companyId}.");
        }

        if (filled($receipt->uuid ?? null) && (string) ($receipt->pac_provider ?? '') !== 'dev-demo') {
            return $this->failure('El recibo ya tiene UUID no demo; no se modifica.');
        }

        if (empty($receipt->xml_path) || ! Storage::disk('local')->exists($receipt->xml_path)) {
            return $this->failure('El recibo no tiene XML disponible para simular timbrado.');
        }

        $originalXmlPath = (string) $receipt->xml_path;
        $uuid = $this->demoUuid($receiptId);
        $xml = Storage::disk('local')->get($originalXmlPath);
        $stampedXml = $this->addDemoTimbre($xml, $uuid, $receipt);

        $demoPath = 'payroll-cfdi/demo-stamped/company_' . $companyId
            . '/run_' . $receipt->payroll_run_id
            . '/receipt_' . $receipt->id . '.xml';

        Storage::disk('local')->put($demoPath, $stampedXml);

        $metadata = $this->decode($receipt->metadata ?? null);
        $metadata['dev_demo_stamp'] = true;
        $metadata['dev_demo_stamp_warning'] = 'TIMBRADO DEMO DEV. NO FISCAL. NO PAC/SAT.';
        $metadata['dev_demo_stamp_at'] = now()->toDateTimeString();
        $metadata['demo_stamp_original_xml_path'] = $metadata['demo_stamp_original_xml_path'] ?? $originalXmlPath;
        $metadata['demo_stamp_xml_path'] = $demoPath;

        DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('id', $receiptId)
            ->update([
                'status' => 'stamped',
                'uuid' => $uuid,
                'pac_provider' => 'dev-demo',
                'pac_test_env' => true,
                'pac_request_id' => 'DEV-DEMO-' . $receiptId . '-' . now()->format('YmdHis'),
                'pac_error_message' => null,
                'xml_path' => $demoPath,
                'stamped_at' => now(),
                'metadata' => $this->json($metadata),
                'updated_at' => now(),
            ]);

        $this->audit([
            'company_id' => $companyId,
            'payroll_cfdi_receipt_id' => $receiptId,
            'payroll_run_id' => $receipt->payroll_run_id,
            'payroll_run_line_id' => $receipt->payroll_run_line_id,
            'employee_id' => $receipt->employee_id,
            'user_id' => $userId,
            'action' => 'dev_demo_stamp',
            'status' => 'success',
            'pac_provider' => 'dev-demo',
            'pac_test_env' => true,
            'request_id' => 'DEV-DEMO-' . $receiptId,
            'request_meta' => [
                'original_xml_path' => $originalXmlPath,
                'demo_xml_path' => $demoPath,
                'warning' => 'No enviado a PAC/SAT.',
            ],
            'response_meta' => [
                'uuid' => $uuid,
                'demo' => true,
            ],
            'message' => 'Timbrado demo DEV generado para revisar PDF. No fiscal.',
        ]);

        $this->syncPayrollRunStatus($companyId, (int) $receipt->payroll_run_id);

        return [
            'success' => true,
            'message' => 'Timbrado demo DEV generado.',
            'summary' => [
                'company_id' => $companyId,
                'receipt_id' => $receiptId,
                'uuid' => $uuid,
                'xml_path' => $demoPath,
                'warning' => 'NO FISCAL. NO PAC/SAT.',
            ],
        ];
    }

    public function restoreDemo(int $companyId, int $receiptId, ?int $userId = null): array
    {
        $this->assertNotProduction();
        $this->assertSchema();

        $receipt = DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('id', $receiptId)
            ->first();

        if (! $receipt) {
            return $this->failure("No existe el recibo CFDI nomina {$receiptId} para empresa {$companyId}.");
        }

        $metadata = $this->decode($receipt->metadata ?? null);

        if (! ($metadata['dev_demo_stamp'] ?? false)) {
            return $this->failure('El recibo no tiene timbrado demo para restaurar.');
        }

        $originalXmlPath = (string) ($metadata['demo_stamp_original_xml_path'] ?? '');

        if ($originalXmlPath === '') {
            return $this->failure('No se encontro demo_stamp_original_xml_path para restaurar.');
        }

        unset(
            $metadata['dev_demo_stamp'],
            $metadata['dev_demo_stamp_warning'],
            $metadata['dev_demo_stamp_at'],
            $metadata['demo_stamp_xml_path']
        );

        DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('id', $receiptId)
            ->update([
                'status' => 'validated',
                'uuid' => null,
                'pac_provider' => 'sw',
                'pac_test_env' => true,
                'pac_request_id' => null,
                'pac_error_message' => null,
                'xml_path' => $originalXmlPath,
                'stamped_at' => null,
                'metadata' => $this->json($metadata),
                'updated_at' => now(),
            ]);

        $this->audit([
            'company_id' => $companyId,
            'payroll_cfdi_receipt_id' => $receiptId,
            'payroll_run_id' => $receipt->payroll_run_id,
            'payroll_run_line_id' => $receipt->payroll_run_line_id,
            'employee_id' => $receipt->employee_id,
            'user_id' => $userId,
            'action' => 'dev_demo_stamp_restore',
            'status' => 'success',
            'pac_provider' => 'dev-demo',
            'pac_test_env' => true,
            'request_id' => 'DEV-DEMO-RESTORE-' . $receiptId,
            'request_meta' => [
                'restored_xml_path' => $originalXmlPath,
            ],
            'response_meta' => null,
            'message' => 'Timbrado demo DEV revertido.',
        ]);

        $this->syncPayrollRunStatus($companyId, (int) $receipt->payroll_run_id);

        return [
            'success' => true,
            'message' => 'Timbrado demo DEV revertido.',
            'summary' => [
                'company_id' => $companyId,
                'receipt_id' => $receiptId,
                'xml_path' => $originalXmlPath,
            ],
        ];
    }

    protected function addDemoTimbre(string $xml, string $uuid, object $receipt): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        if (! $doc->loadXML($xml)) {
            throw new RuntimeException('No se pudo cargar XML para timbrado demo.');
        }

        $comprobante = $doc->documentElement;

        if (! $comprobante) {
            throw new RuntimeException('XML sin nodo comprobante.');
        }

        $complemento = null;

        foreach ($comprobante->childNodes as $child) {
            if ($child->nodeName === 'cfdi:Complemento') {
                $complemento = $child;
                break;
            }
        }

        if (! $complemento) {
            $complemento = $doc->createElement('cfdi:Complemento');
            $comprobante->appendChild($complemento);
        }

        $comment = $doc->createComment(' TIMBRADO DEMO DEV - NO FISCAL - NO PAC/SAT ');
        $complemento->appendChild($comment);

        $tfd = $doc->createElementNS('http://www.sat.gob.mx/TimbreFiscalDigital', 'tfd:TimbreFiscalDigital');
        $tfd->setAttribute('Version', '1.1');
        $tfd->setAttribute('UUID', $uuid);
        $tfd->setAttribute('FechaTimbrado', now()->format('Y-m-d\TH:i:s'));
        $tfd->setAttribute('RfcProvCertif', 'DEV010101DEV');
        $tfd->setAttribute('SelloCFD', 'DEMO_NO_FISCAL');
        $tfd->setAttribute('NoCertificadoSAT', '00001000000000000000');
        $tfd->setAttribute('SelloSAT', 'DEMO_NO_FISCAL');
        $complemento->appendChild($tfd);

        return $doc->saveXML() ?: throw new RuntimeException('No se pudo guardar XML demo.');
    }

    protected function demoUuid(int $receiptId): string
    {
        $tail = str_pad((string) $receiptId, 12, '0', STR_PAD_LEFT);

        return '00000000-0000-4000-8000-' . $tail;
    }

    protected function syncPayrollRunStatus(int $companyId, int $payrollRunId): void
    {
        if (! Schema::hasTable('payroll_runs')) {
            return;
        }

        $total = DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('payroll_run_id', $payrollRunId)
            ->count();

        $stamped = DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('payroll_run_id', $payrollRunId)
            ->where('status', 'stamped')
            ->count();

        $errors = DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('payroll_run_id', $payrollRunId)
            ->where('status', 'error')
            ->count();

        $status = $stamped > 0 && $stamped === $total
            ? 'stamped'
            : ($stamped > 0 ? 'partial_stamped' : ($errors > 0 ? 'stamp_error' : 'xml_drafts_prepared'));

        DB::table('payroll_runs')
            ->where('company_id', $companyId)
            ->where('id', $payrollRunId)
            ->update([
                'payroll_cfdi_status' => $status,
                'payroll_cfdi_ready_lines_count' => $stamped,
                'payroll_cfdi_error_lines_count' => $errors,
                'updated_at' => now(),
            ]);
    }

    protected function assertNotProduction(): void
    {
        if ((string) config('app.env') === 'production') {
            throw new RuntimeException('El timbrado demo DEV esta prohibido en production.');
        }
    }

    protected function assertSchema(): void
    {
        foreach (['payroll_cfdi_receipts', 'payroll_cfdi_audits'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("No existe la tabla requerida: {$table}");
            }
        }
    }

    protected function audit(array $data): void
    {
        if (! Schema::hasTable('payroll_cfdi_audits')) {
            return;
        }

        $data['request_meta'] = array_key_exists('request_meta', $data)
            ? $this->json($data['request_meta'])
            : null;

        $data['response_meta'] = array_key_exists('response_meta', $data)
            ? $this->json($data['response_meta'])
            : null;

        $data['ip_address'] = null;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('payroll_cfdi_audits')->insert($data);
    }

    protected function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function failure(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'errors' => [$message],
            'summary' => [],
        ];
    }
}
