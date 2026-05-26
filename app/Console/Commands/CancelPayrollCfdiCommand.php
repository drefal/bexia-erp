<?php

namespace App\Console\Commands;

use App\Support\PayrollCfdi\PayrollCfdiCancelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CancelPayrollCfdiCommand extends Command
{
    protected $signature = 'payroll:cfdi-cancel
        {--company=5 : ID de empresa}
        {--receipt= : ID del recibo CFDI nomina}
        {--reason=02 : Motivo SAT de cancelacion 01, 02, 03 o 04}
        {--replacement-uuid= : UUID relacionado si el motivo lo requiere}
        {--json : Mostrar salida JSON}';

    protected $description = 'Cancela CFDI nomina. En DEV debe quedar bloqueado por guard.';

    public function handle(PayrollCfdiCancelService $service): int
    {
        $companyId = (int) $this->option('company');
        $receiptId = $this->option('receipt');

        if (blank($receiptId)) {
            $receiptId = DB::table('payroll_cfdi_receipts')
                ->where('company_id', $companyId)
                ->where('status', 'stamped')
                ->whereNotNull('uuid')
                ->orderBy('id')
                ->value('id');
        }

        if (blank($receiptId)) {
            $this->error('No se encontro recibo stamped con UUID. Indica --receipt=ID.');
            return self::FAILURE;
        }

        $receiptId = (int) $receiptId;
        $reason = (string) $this->option('reason');
        $replacementUuid = filled($this->option('replacement-uuid'))
            ? (string) $this->option('replacement-uuid')
            : null;

        $result = $service->cancel(
            companyId: $companyId,
            receiptId: $receiptId,
            reason: $reason,
            replacementUuid: $replacementUuid,
            userId: auth()->id(),
        );

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $this->line('');
        $this->info('V5.65.7c - Cancelacion CFDI nomina');
        $this->line('Empresa: ' . $companyId);
        $this->line('Recibo: ' . $receiptId);
        $this->line('Motivo: ' . $reason);
        $this->line('UUID relacionado: ' . ($replacementUuid ?: 'N/A'));
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

        if (! empty($result['warnings'])) {
            $this->warn('Advertencias:');
            foreach ($result['warnings'] as $warning) {
                $this->line('- ' . $warning);
            }
            $this->line('');
        }

        if (! ($result['success'] ?? false)) {
            $this->error(($result['blocked'] ?? false)
                ? 'RESULTADO: CANCELACION CFDI NOMINA BLOQUEADA'
                : 'RESULTADO: CANCELACION CFDI NOMINA NO REALIZADA');

            foreach (($result['errors'] ?? []) as $error) {
                $this->line('- ' . $error);
            }

            return self::FAILURE;
        }

        $this->info('RESULTADO: CFDI NOMINA CANCELADO');
        return self::SUCCESS;
    }
}
