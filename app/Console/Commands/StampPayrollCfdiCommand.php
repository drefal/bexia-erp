<?php

namespace App\Console\Commands;

use App\Support\PayrollCfdi\PayrollCfdiStampService;
use Illuminate\Console\Command;

class StampPayrollCfdiCommand extends Command
{
    protected $signature = 'payroll:cfdi-stamp
        {--company=5 : ID de empresa}
        {--receipt= : ID del recibo CFDI nomina}
        {--json : Mostrar salida JSON}';

    protected $description = 'Intenta timbrar CFDI nomina. En DEV debe quedar bloqueado por guard.';

    public function handle(PayrollCfdiStampService $service): int
    {
        $companyId = (int) $this->option('company');
        $receiptOption = $this->option('receipt');

        if (blank($receiptOption)) {
            $this->error('Debes indicar --receipt=<ID>.');
            return self::FAILURE;
        }

        $receiptId = (int) $receiptOption;

        $result = $service->stamp(
            companyId: $companyId,
            receiptId: $receiptId,
            userId: auth()->id(),
        );

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $this->line('');
        $this->info('V5.65.5c - Timbrado CFDI nomina');
        $this->line('Empresa: ' . $companyId);
        $this->line('Recibo: ' . $receiptId);
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
            $this->error(($result['blocked'] ?? false) ? 'Bloqueos:' : 'Errores:');
            foreach ($result['errors'] as $error) {
                $this->line('- ' . $error);
            }

            $this->line('');
            $this->error(($result['blocked'] ?? false)
                ? 'RESULTADO: TIMBRADO CFDI NOMINA BLOQUEADO'
                : 'RESULTADO: TIMBRADO CFDI NOMINA NO REALIZADO');

            return self::FAILURE;
        }

        $this->line('');
        $this->info('RESULTADO: CFDI NOMINA TIMBRADO');
        return self::SUCCESS;
    }
}
