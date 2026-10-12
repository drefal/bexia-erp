<?php

namespace App\Support\PayrollCfdi;

use App\Models\PayrollRun;

class PayrollCfdiModeGuard
{
    public static function validate(
        int $companyId,
        int $payrollRunId
    ): array {
        $run = PayrollRun::query()
            ->where('company_id', $companyId)
            ->whereKey($payrollRunId)
            ->first();

        if (! $run) {
            return [
                'success' => false,
                'message' =>
                    'No existe la corrida de nómina indicada.',
                'error' =>
                    "No existe payroll_run_id={$payrollRunId} "
                    . "para company_id={$companyId}.",
                'run' => null,
            ];
        }

        if (! $run->enablesCfdi()) {
            return [
                'success' => false,
                'message' =>
                    'La corrida no está configurada '
                    . 'para generar CFDI.',
                'error' =>
                    'El modo de la corrida es '
                    . $run->calculationMode()
                    . '; se requiere fiscal_cfdi.',
                'run' => $run,
            ];
        }

        return [
            'success' => true,
            'message' => 'Modo CFDI válido.',
            'error' => null,
            'run' => $run,
        ];
    }
}
