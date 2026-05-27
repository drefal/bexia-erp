<?php

namespace App\Console\Commands;

use App\Support\Accounting\PayrollAccountingPoster;
use Illuminate\Console\Command;
use Throwable;

class PayrollAccountingCommand extends Command
{
    protected $signature = 'payroll:accounting
        {action=summary : summary|setup-defaults|dry-run|post|reverse}
        {--company= : ID de empresa}
        {--run= : ID de corrida de nómina}
        {--user= : ID de usuario auditoría}
        {--reason= : Motivo para reversa}';

    protected $description = 'Contabilidad de nómina: diagnóstico, dry-run, posteo y reversa.';

    public function handle(PayrollAccountingPoster $poster): int
    {
        $action = (string) $this->argument('action');
        $companyId = (int) $this->option('company');
        $runId = (int) ($this->option('run') ?: 0);
        $userId = $this->option('user') !== null && $this->option('user') !== ''
            ? (int) $this->option('user')
            : null;

        if ($companyId <= 0) {
            $this->error('--company es requerido.');
            return self::FAILURE;
        }

        try {
            $result = match ($action) {
                'setup-defaults' => $poster->setupDefaultMappings($companyId, $userId),
                'dry-run' => $this->runRequired($poster, 'dry-run', $companyId, $runId),
                'post' => $this->runRequired($poster, 'post', $companyId, $runId, $userId),
                'reverse' => $this->reverseRequired($poster, $companyId, $runId, $userId),
                'summary' => $this->runRequired($poster, 'summary', $companyId, $runId),
                default => throw new \RuntimeException('Acción no soportada: ' . $action),
            };

            $this->line(json_encode($this->normalize($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            $this->line(json_encode([
                'ok' => false,
                'action' => $action,
                'company_id' => $companyId,
                'run_id' => $runId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }
    }

    private function runRequired(PayrollAccountingPoster $poster, string $action, int $companyId, int $runId, ?int $userId = null): mixed
    {
        if ($runId <= 0) {
            throw new \RuntimeException('--run es requerido para ' . $action . '.');
        }

        return match ($action) {
            'dry-run' => $poster->dryRun($companyId, $runId),
            'post' => $poster->post($companyId, $runId, $userId),
            'summary' => $poster->summary($companyId, $runId),
            default => throw new \RuntimeException('Acción interna no soportada: ' . $action),
        };
    }

    private function reverseRequired(PayrollAccountingPoster $poster, int $companyId, int $runId, ?int $userId = null): mixed
    {
        if ($runId <= 0) {
            throw new \RuntimeException('--run es requerido para reverse.');
        }

        $reason = (string) ($this->option('reason') ?: 'Reversa contable de nómina');

        return $poster->reverse($companyId, $runId, $reason, $userId);
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->map(fn ($item) => $this->normalize($item))->all();
        }

        if ($value instanceof \stdClass) {
            return get_object_vars($value);
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->normalize($item), $value);
        }

        return $value;
    }
}
