<?php

namespace App\Support\PayrollCfdi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use RuntimeException;

class PayrollCfdiReceiptPdfService
{
    public function generate(int $companyId, int $receiptId, ?int $userId = null, bool $force = false): array
    {
        $this->assertSchema();

        $receipt = DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->where('id', $receiptId)
            ->first();

        if (! $receipt) {
            return $this->failure("No existe recibo CFDI nomina {$receiptId} para empresa {$companyId}.");
        }

        if (! $force && filled($receipt->pdf_path ?? null) && Storage::disk('local')->exists($receipt->pdf_path)) {
            return [
                'success' => true,
                'message' => 'PDF ya existente.',
                'summary' => [
                    'company_id' => $companyId,
                    'receipt_id' => $receiptId,
                    'pdf_path' => $receipt->pdf_path,
                    'generated' => false,
                ],
            ];
        }

        $company = DB::table('companies')->where('id', $companyId)->first();

        $data = $this->buildViewData($receipt, $company);

        $pdfBytes = $this->renderPdf($data);

        if (strlen($pdfBytes) < 1000) {
            throw new RuntimeException('El PDF generado parece estar vacio o incompleto.');
        }

        $path = 'payroll-cfdi/pdfs/company_' . $companyId
            . '/run_' . $receipt->payroll_run_id
            . '/receipt_' . $receipt->id . '.pdf';

        Storage::disk('local')->put($path, $pdfBytes);

        if (Schema::hasColumn('payroll_cfdi_receipts', 'pdf_path')) {
            DB::table('payroll_cfdi_receipts')
                ->where('company_id', $companyId)
                ->where('id', $receiptId)
                ->update([
                    'pdf_path' => $path,
                    'updated_at' => now(),
                ]);
        }

        $this->audit([
            'company_id' => $companyId,
            'payroll_cfdi_receipt_id' => $receiptId,
            'payroll_run_id' => $receipt->payroll_run_id,
            'payroll_run_line_id' => $receipt->payroll_run_line_id,
            'employee_id' => $receipt->employee_id,
            'user_id' => $userId,
            'action' => 'generate_pdf',
            'status' => 'success',
            'pac_provider' => $receipt->pac_provider ?? null,
            'pac_test_env' => $receipt->pac_test_env ?? null,
            'request_id' => null,
            'request_meta' => [
                'pdf_path' => $path,
                'force' => $force,
                'is_demo' => $data['isDemo'] ?? false,
                'is_internal_only' => $data['isInternalOnly'] ?? false,
                'is_external_stamped' => $data['isExternalStamped'] ?? false,
                'is_cfdi_not_required' => $data['isCfdiNotRequired'] ?? false,
                'alternate_status_label' => $data['alternateStatusLabel'] ?? null,
            ],
            'response_meta' => [
                'pdf_size' => strlen($pdfBytes),
            ],
            'message' => 'PDF de recibo CFDI nomina generado.',
        ]);

        return [
            'success' => true,
            'message' => 'PDF de recibo CFDI nomina generado.',
            'summary' => [
                'company_id' => $companyId,
                'receipt_id' => $receiptId,
                'pdf_path' => $path,
                'pdf_size' => strlen($pdfBytes),
                'generated' => true,
                'is_demo' => $data['isDemo'] ?? false,
                'is_internal_only' => $data['isInternalOnly'] ?? false,
                'is_external_stamped' => $data['isExternalStamped'] ?? false,
                'is_cfdi_not_required' => $data['isCfdiNotRequired'] ?? false,
                'alternate_status_label' => $data['alternateStatusLabel'] ?? null,
            ],
        ];
    }

    public function buildViewData(object $receipt, ?object $company = null): array
    {
        $issuer = $this->decode($receipt->issuer_snapshot ?? null);
        $employee = $this->decode($receipt->employee_snapshot ?? null);
        $contract = $this->decode($receipt->contract_snapshot ?? null);
        $totals = $this->decode($receipt->totals_snapshot ?? null);
        $metadata = $this->decode($receipt->metadata ?? null);

        $lineConcepts = $metadata['line_concepts'] ?? [];

        $receiptStatus = (string) ($receipt->status ?? '');

        $isInternalOnly = $receiptStatus === 'internal_only' || (bool) ($metadata['internal_only'] ?? false);
        $isExternalStamped = $receiptStatus === 'external_stamped' || (bool) ($metadata['external_stamp'] ?? false);
        $isCfdiNotRequired = $receiptStatus === 'cfdi_not_required' || (bool) ($metadata['cfdi_not_required'] ?? false);

        $isDemo = (bool) ($metadata['dev_demo_stamp'] ?? false) || (string) ($receipt->pac_provider ?? '') === 'dev-demo';

        $alternateStatusLabel = match (true) {
            $isInternalOnly => 'Recibo interno',
            $isExternalStamped => 'Timbrado externo',
            $isCfdiNotRequired => 'CFDI no requerido',
            default => null,
        };

        $watermarkText = match (true) {
            $isInternalOnly => 'RECIBO INTERNO - NO CFDI',
            $isCfdiNotRequired => 'CFDI NO REQUERIDO',
            $isExternalStamped => 'TIMBRADO EXTERNO',
            $isDemo => 'DEMO - NO FISCAL',
            default => null,
        };

        $bannerText = match (true) {
            $isInternalOnly => 'RECIBO INTERNO SIN VALIDEZ CFDI: este documento es solo para control interno y no fue timbrado ante PAC/SAT.',
            $isCfdiNotRequired => 'CFDI NO REQUERIDO: este recibo fue marcado fuera del flujo fiscal de timbrado en Bexia.',
            $isExternalStamped => 'TIMBRADO EXTERNO: el UUID fue registrado manualmente porque el CFDI se timbró fuera de Bexia.',
            $isDemo => 'DEMO - RECIBO SIN VALIDEZ FISCAL REAL.',
            default => null,
        };

        $perceptions = array_values(array_filter($lineConcepts, fn ($line) => ($line['type'] ?? null) === 'perception'));
        $deductions = array_values(array_filter($lineConcepts, fn ($line) => ($line['type'] ?? null) === 'deduction'));

        $payrollRun = DB::table('payroll_runs')
            ->where('id', $receipt->payroll_run_id)
            ->first();

        return [
            'receipt' => $receipt,
            'company' => $company,
            'payrollRun' => $payrollRun,
            'issuer' => $issuer,
            'employee' => $employee,
            'contract' => $contract,
            'totals' => $totals,
            'metadata' => $metadata,
            'perceptions' => $perceptions,
            'deductions' => $deductions,
            'isDemo' => $isDemo,
            'isInternalOnly' => $isInternalOnly,
            'isExternalStamped' => $isExternalStamped,
            'isCfdiNotRequired' => $isCfdiNotRequired,
            'alternateStatusLabel' => $alternateStatusLabel,
            'watermarkText' => $watermarkText,
            'bannerText' => $bannerText,
            'bannerClass' => 'demo-banner',
            'logoDataUri' => $this->companyLogoDataUri($company),
            'generatedAt' => now(),
        ];
    }

    protected function renderPdf(array $data): string
    {
        $view = 'payroll-cfdi.receipt-pdf';

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView($view, $data)
                ->setPaper('letter')
                ->output();
        }

        if (class_exists(\Dompdf\Dompdf::class)) {
            $dompdf = new \Dompdf\Dompdf([
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ]);

            $dompdf->loadHtml(View::make($view, $data)->render());
            $dompdf->setPaper('letter');
            $dompdf->render();

            return $dompdf->output();
        }

        throw new RuntimeException('No se encontro DomPDF instalado.');
    }

    protected function companyLogoDataUri(?object $company): ?string
    {
        $path = (string) ($company->logo_compact_path ?? $company->logo_path ?? '');

        if ($path === '') {
            return null;
        }

        foreach (['public', 'local'] as $disk) {
            if (! Storage::disk($disk)->exists($path)) {
                continue;
            }

            $bytes = Storage::disk($disk)->get($path);
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                default => 'image/png',
            };

            return 'data:' . $mime . ';base64,' . base64_encode($bytes);
        }

        return null;
    }

    protected function assertSchema(): void
    {
        foreach (['payroll_cfdi_receipts', 'payroll_cfdi_audits', 'companies'] as $table) {
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
