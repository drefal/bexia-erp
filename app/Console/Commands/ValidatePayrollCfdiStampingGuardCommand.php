<?php

namespace App\Console\Commands;

use App\Support\PayrollCfdi\PayrollCfdiStampingGuardService;
use Illuminate\Console\Command;

class ValidatePayrollCfdiStampingGuardCommand extends Command
{
    protected $signature = 'payroll:cfdi-stamping-guard
        {--company=5 : ID de empresa}
        {--receipt= : ID opcional del recibo CFDI nomina}
        {--json : Mostrar salida JSON}';

    protected $description = 'Valida si el ambiente y la empresa permiten timbrado real CFDI nomina. No timbra.';

    public function handle(PayrollCfdiStampingGuardService $service): int
    {
        $companyId = (int) $this->option('company');
        $receiptOption = $this->option('receipt');
        $receiptId = filled($receiptOption) ? (int) $receiptOption : null;

        $result = $service->validate($companyId, $receiptId);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $this->line('');
        $this->info('V5.65.5a - Guard timbrado CFDI nomina');
        $this->line('Empresa: ' . $companyId);
        $this->line('Recibo: ' . ($receiptId ?: 'sin recibo especifico'));
        $this->line('');

        $this->line('Resumen:');
        foreach (($result['summary'] ?? []) as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'SI' : 'NO';
            }

            $this->line('- ' . $key . ': ' . (is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)));
        }

        if (! empty($result['warnings'])) {
            $this->line('');
            $this->warn('Advertencias:');
            foreach ($result['warnings'] as $warning) {
                $this->line('- ' . $warning);
            }
        }

        if (! empty($result['errors'])) {
            $this->line('');
            $this->error('Bloqueos:');
            foreach ($result['errors'] as $error) {
                $this->line('- ' . $error);
            }

            $this->line('');
            $this->error('RESULTADO: TIMBRADO CFDI NOMINA BLOQUEADO');
            return self::FAILURE;
        }

        $this->line('');
        $this->info('RESULTADO: TIMBRADO CFDI NOMINA PERMITIDO PARA ESTE AMBIENTE');
        return self::SUCCESS;
    }
}
