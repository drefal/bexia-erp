<?php

namespace App\Console\Commands;

use App\Support\PayrollCfdi\PayrollCfdiXmlDraftService;
use Illuminate\Console\Command;

class PreparePayrollCfdiXmlDraftsCommand extends Command
{
    protected $signature = 'payroll:cfdi-prepare-xml-drafts
        {--company=5 : ID de empresa}
        {--payroll-run= : ID de corrida de nomina}
        {--force : Regenerar XML aunque ya exista}
        {--json : Mostrar salida JSON}';

    protected $description = 'Genera XML CFDI de nomina en borrador local, sin timbrar y sin enviar al PAC/SAT.';

    public function handle(PayrollCfdiXmlDraftService $service): int
    {
        $companyId = (int) $this->option('company');
        $payrollRunOption = $this->option('payroll-run');

        if (blank($payrollRunOption)) {
            $this->error('Debes indicar --payroll-run=<ID>.');
            return self::FAILURE;
        }

        $payrollRunId = (int) $payrollRunOption;

        $result = $service->prepareForRun(
            companyId: $companyId,
            payrollRunId: $payrollRunId,
            userId: auth()->id(),
            force: (bool) $this->option('force'),
        );

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $this->line('');
        $this->info('V5.65.4a - XML CFDI nomina borrador');
        $this->line('Empresa: ' . $companyId);
        $this->line('Nomina: ' . $payrollRunId);
        $this->line('Force: ' . ($this->option('force') ? 'SI' : 'NO'));
        $this->line('');

        $this->line('Resumen:');
        foreach (($result['summary'] ?? []) as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'SI' : 'NO';
            }

            $this->line('- ' . $key . ': ' . (is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)));
        }

        $this->line('');
        $this->line('Generados/actualizados: ' . ($result['updated'] ?? 0));
        $this->line('Omitidos: ' . ($result['skipped'] ?? 0));

        if (! empty($result['xml_paths'])) {
            $this->line('');
            $this->line('XML paths:');
            foreach ($result['xml_paths'] as $path) {
                $this->line('- ' . $path);
            }
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
            $this->error('Errores:');
            foreach ($result['errors'] as $error) {
                $this->line('- ' . $error);
            }

            return self::FAILURE;
        }

        $this->line('');
        $this->info($result['message'] ?? 'XML borrador generado.');
        return self::SUCCESS;
    }
}
