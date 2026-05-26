<?php

namespace App\Console\Commands;

use App\Support\PayrollCfdi\PayrollCfdiReceiptPreparationService;
use Illuminate\Console\Command;

class PreparePayrollCfdiReceiptsCommand extends Command
{
    protected $signature = 'payroll:cfdi-prepare-receipts
        {--company=5 : ID de empresa}
        {--payroll-run= : ID de corrida de nomina cerrada}
        {--force : Regenerar borradores existentes que no esten timbrados/cancelados}
        {--json : Mostrar salida JSON}';

    protected $description = 'Prepara recibos CFDI de nomina en borrador desde una corrida cerrada, sin timbrar.';

    public function handle(PayrollCfdiReceiptPreparationService $service): int
    {
        $companyId = (int) $this->option('company');
        $payrollRunOption = $this->option('payroll-run');

        if (blank($payrollRunOption)) {
            $this->error('Debes indicar --payroll-run=<ID> de una nomina cerrada.');
            return self::FAILURE;
        }

        $payrollRunId = (int) $payrollRunOption;
        $force = (bool) $this->option('force');

        $result = $service->prepare(
            companyId: $companyId,
            payrollRunId: $payrollRunId,
            userId: auth()->id(),
            force: $force,
        );

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $this->line('');
        $this->info('V5.65.3a - Preparar recibos CFDI nomina');
        $this->line('Empresa: ' . $companyId);
        $this->line('Nomina: ' . $payrollRunId);
        $this->line('Force: ' . ($force ? 'SI' : 'NO'));
        $this->line('');

        $this->line('Resumen:');
        foreach (($result['summary'] ?? []) as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'SI' : 'NO';
            }

            $this->line('- ' . $key . ': ' . (is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)));
        }

        $this->line('');
        $this->line('Creados: ' . ($result['created'] ?? 0));
        $this->line('Actualizados: ' . ($result['updated'] ?? 0));
        $this->line('Omitidos: ' . ($result['skipped'] ?? 0));

        if (! empty($result['warnings'])) {
            $this->line('');
            $this->warn('Advertencias:');
            foreach ($result['warnings'] as $warning) {
                $this->line('- ' . $warning);
            }
        }

        if (! empty($result['errors'])) {
            $this->line('');
            $this->error('Errores:');
            foreach ($result['errors'] as $error) {
                $this->line('- ' . $error);
            }

            return self::FAILURE;
        }

        $this->line('');
        $this->info($result['message'] ?? 'Recibos preparados.');
        return self::SUCCESS;
    }
}
