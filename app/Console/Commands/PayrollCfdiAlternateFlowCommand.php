<?php

namespace App\Console\Commands;

use App\Support\PayrollCfdi\PayrollCfdiAlternateFlowService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class PayrollCfdiAlternateFlowCommand extends Command
{
    protected $signature = 'payroll:cfdi-alternate
        {action : summary|internal-only|not-required|external-stamp|revert}
        {--company= : ID de la empresa}
        {--receipt= : ID del recibo CFDI nomina}
        {--reason= : Motivo de la accion}
        {--uuid= : UUID para CFDI timbrado externo}
        {--xml-path= : Ruta local storage del XML externo ya cargado}
        {--notes= : Notas para registro externo}
        {--force : Forzar reversa cuando aplique}';

    protected $description = 'Gestiona caminos alternos de CFDI nómina sin enviar al PAC/SAT.';

    public function handle(PayrollCfdiAlternateFlowService $service): int
    {
        $action = (string) $this->argument('action');
        $companyId = (int) $this->option('company');
        $receiptId = (int) $this->option('receipt');

        if ($companyId <= 0 || $receiptId <= 0) {
            $this->error('Debe indicar --company y --receipt.');
            return self::FAILURE;
        }

        try {
            $result = match ($action) {
                'summary' => $service->summary($companyId, $receiptId),

                'internal-only' => $service->markInternalOnly(
                    companyId: $companyId,
                    receiptId: $receiptId,
                    reason: $this->option('reason'),
                    userId: null,
                ),

                'not-required' => $service->markCfdiNotRequired(
                    companyId: $companyId,
                    receiptId: $receiptId,
                    reason: $this->option('reason'),
                    userId: null,
                ),

                'external-stamp' => $service->registerExternalStamp(
                    companyId: $companyId,
                    receiptId: $receiptId,
                    uuid: (string) $this->option('uuid'),
                    xmlPath: $this->option('xml-path'),
                    notes: $this->option('notes'),
                    userId: null,
                ),

                'revert' => $service->revertAlternate(
                    companyId: $companyId,
                    receiptId: $receiptId,
                    reason: $this->option('reason'),
                    force: (bool) $this->option('force'),
                    userId: null,
                ),

                default => throw ValidationException::withMessages([
                    'action' => "Acción no soportada: {$action}",
                ]),
            };

            $this->info('RESULTADO: OK');
            $this->line(json_encode($this->normalize($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            $this->error('RESULTADO: ERROR DE VALIDACION');
            $this->line(json_encode($exception->errors(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('RESULTADO: ERROR');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function normalize(mixed $value): mixed
    {
        return json_decode(json_encode($value), true);
    }
}
