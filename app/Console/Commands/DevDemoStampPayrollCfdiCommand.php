<?php

namespace App\Console\Commands;

use App\Support\PayrollCfdi\PayrollCfdiDevDemoStampService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DevDemoStampPayrollCfdiCommand extends Command
{
    protected $signature = 'payroll:cfdi-demo-stamp
        {--company=5 : ID de empresa}
        {--receipt= : ID del recibo CFDI nomina}
        {--restore : Revierte el timbrado demo del recibo}
        {--json : Mostrar salida JSON}';

    protected $description = 'Genera o revierte timbrado DEMO DEV para CFDI nomina. No fiscal. No PAC/SAT. Prohibido en PROD.';

    public function handle(PayrollCfdiDevDemoStampService $service): int
    {
        $companyId = (int) $this->option('company');
        $receiptId = $this->option('receipt');

        if (blank($receiptId)) {
            $receiptId = DB::table('payroll_cfdi_receipts')
                ->where('company_id', $companyId)
                ->whereNull('uuid')
                ->whereNotNull('xml_path')
                ->whereIn('status', ['validated', 'error'])
                ->orderBy('id')
                ->value('id');
        }

        if (blank($receiptId)) {
            $this->error('No se encontro recibo disponible. Indica --receipt=ID.');
            return self::FAILURE;
        }

        $receiptId = (int) $receiptId;

        $result = $this->option('restore')
            ? $service->restoreDemo($companyId, $receiptId, auth()->id())
            : $service->stampDemo($companyId, $receiptId, auth()->id());

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $this->line('');
        $this->info('V5.65.5e - Timbrado DEMO DEV CFDI nomina');
        $this->line('Empresa: ' . $companyId);
        $this->line('Recibo: ' . $receiptId);
        $this->line('Modo: ' . ($this->option('restore') ? 'RESTORE' : 'DEMO STAMP'));
        $this->line('');

        if (! empty($result['summary'])) {
            $this->line('Resumen:');
            foreach ($result['summary'] as $key => $value) {
                $this->line('- ' . $key . ': ' . (is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)));
            }
            $this->line('');
        }

        if (! ($result['success'] ?? false)) {
            $this->error($result['message'] ?? 'No se pudo generar timbrado demo.');
            return self::FAILURE;
        }

        $this->warn('IMPORTANTE: Este timbrado es DEMO DEV. No fiscal. No PAC/SAT.');
        $this->info($result['message'] ?? 'OK');

        return self::SUCCESS;
    }
}
