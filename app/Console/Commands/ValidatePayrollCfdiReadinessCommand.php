<?php

namespace App\Console\Commands;

use App\Support\PayrollCfdi\PayrollCfdiReadinessService;
use Illuminate\Console\Command;

class ValidatePayrollCfdiReadinessCommand extends Command
{
    protected $signature = 'payroll:cfdi-readiness
        {--company=5 : ID de empresa a validar}
        {--payroll-run= : ID opcional de corrida de nómina}
        {--json : Mostrar salida JSON}';

    protected $description = 'Valida preparación fiscal para CFDI de nómina sin timbrar.';

    public function handle(PayrollCfdiReadinessService $service): int
    {
        $companyId = (int) $this->option('company');
        $payrollRunOption = $this->option('payroll-run');
        $payrollRunId = filled($payrollRunOption) ? (int) $payrollRunOption : null;

        $result = $service->validateCompany($companyId, $payrollRunId);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        $this->line('');
        $this->info('V5.65.1 - Validación CFDI nómina');
        $this->line('Empresa: ' . $companyId);
        $this->line('Corrida nómina: ' . ($payrollRunId ?: 'sin corrida específica'));
        $this->line('');

        $this->line('Resumen:');
        foreach (($result['summary'] ?? []) as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'SI' : 'NO';
            }

            $this->line('- ' . $key . ': ' . (is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)));
        }

        $this->line('');

        if (! empty($result['warnings'])) {
            $this->warn('Advertencias:');
            foreach ($result['warnings'] as $warning) {
                $this->line('- ' . $warning);
            }
            $this->line('');
        }

        if (! empty($result['errors'])) {
            $this->error('Errores que bloquean CFDI nómina:');
            foreach ($result['errors'] as $error) {
                $this->line('- ' . $error);
            }
            $this->line('');
            $this->error('RESULTADO: NO LISTO PARA CFDI NOMINA');
            return self::FAILURE;
        }

        $this->info('RESULTADO: LISTO PARA PREPARAR CFDI NOMINA');
        return self::SUCCESS;
    }
}
