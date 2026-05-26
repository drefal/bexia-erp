<?php

namespace App\Console\Commands;

use App\Support\PayrollCfdi\PayrollCfdiReceiptPdfService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GeneratePayrollCfdiReceiptPdfCommand extends Command
{
    protected $signature = 'payroll:cfdi-generate-pdf
        {--company=5 : ID de empresa}
        {--receipt= : ID del recibo CFDI nomina}
        {--force : Regenerar aunque exista PDF}
        {--json : Mostrar salida JSON}';

    protected $description = 'Genera PDF del recibo CFDI nomina.';

    public function handle(PayrollCfdiReceiptPdfService $service): int
    {
        $companyId = (int) $this->option('company');
        $receiptId = $this->option('receipt');

        if (blank($receiptId)) {
            $receiptId = DB::table('payroll_cfdi_receipts')
                ->where('company_id', $companyId)
                ->whereIn('status', ['stamped', 'validated'])
                ->orderByRaw("CASE WHEN uuid IS NOT NULL THEN 0 ELSE 1 END")
                ->orderBy('id')
                ->value('id');
        }

        if (blank($receiptId)) {
            $this->error('No se encontro recibo disponible. Indica --receipt=ID.');
            return self::FAILURE;
        }

        $receiptId = (int) $receiptId;

        $result = $service->generate(
            companyId: $companyId,
            receiptId: $receiptId,
            userId: auth()->id(),
            force: (bool) $this->option('force'),
        );

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $this->line('');
        $this->info('V5.65.6a - PDF recibo CFDI nomina');
        $this->line('Empresa: ' . $companyId);
        $this->line('Recibo: ' . $receiptId);
        $this->line('Force: ' . ($this->option('force') ? 'SI' : 'NO'));
        $this->line('');

        if (! empty($result['summary'])) {
            $this->line('Resumen:');
            foreach ($result['summary'] as $key => $value) {
                if (is_bool($value)) {
                    $value = $value ? 'SI' : 'NO';
                }

                $this->line('- ' . $key . ': ' . (is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)));
            }
            $this->line('');
        }

        if (! ($result['success'] ?? false)) {
            $this->error($result['message'] ?? 'No se pudo generar PDF.');
            return self::FAILURE;
        }

        $this->info($result['message'] ?? 'PDF generado.');

        return self::SUCCESS;
    }
}
