<?php

namespace App\Support;

use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\Process\Process;

final class AttendanceReportPdfGenerator
{
    public const BLOCK_SIZE = 150;

    public const SYNC_MAX_ROWS = 600;

    private const PHP_BIN = '/usr/local/bin/php';

    private const PDFUNITE_BIN = '/usr/bin/pdfunite';

    private const PDFINFO_BIN = '/usr/bin/pdfinfo';

    public static function blocks(
        Collection $rows,
        string $groupBy
    ): array {
        if ($rows->isEmpty()) {
            return [collect()];
        }

        if ($groupBy !== 'employee') {
            return $rows->values()
                ->chunk(self::BLOCK_SIZE)
                ->map(fn ($block) => $block->values())
                ->all();
        }

        $blocks = [];
        $current = collect();

        foreach (
            $rows->groupBy('employee_id')
            as $employeeRows
        ) {
            // Un empleado excepcionalmente grande puede
            // dividirse sin repetir su resumen individual.
            foreach (
                $employeeRows->values()
                    ->chunk(self::BLOCK_SIZE)
                as $part
            ) {
                if (
                    $current->isNotEmpty()
                    && $current->count() + $part->count()
                        > self::BLOCK_SIZE
                ) {
                    $blocks[] = $current->values();
                    $current = collect();
                }

                $current = $current->concat($part);
            }
        }

        if ($current->isNotEmpty()) {
            $blocks[] = $current->values();
        }

        return $blocks;
    }

    public static function generate(
        array $data,
        ?callable $renderHtml = null,
        int $wallSeconds = 50,
        ?callable $onProgress = null
    ): string {
        foreach ([
            self::PHP_BIN,
            self::PDFUNITE_BIN,
            self::PDFINFO_BIN,
        ] as $binary) {
            if (! is_executable($binary)) {
                throw new RuntimeException(
                    'Motor de exportacion PDF no disponible.'
                );
            }
        }

        if (! function_exists('proc_open')) {
            throw new RuntimeException(
                'Procesos PDF independientes no disponibles.'
            );
        }

        $rows = collect($data['rows'])->values();

        $groupBy = $data['filters']['group_by'] ?? 'day';

        $blocks = self::blocks($rows, $groupBy);

        $renderHtml ??= static fn (array $payload): string =>
            view(
                'reports.hr.attendance-report-pdf',
                $payload
            )->render();

        $directory = rtrim(
            sys_get_temp_dir(),
            DIRECTORY_SEPARATOR
        ).'/bexia_attendance_pdf_'.bin2hex(random_bytes(16));

        if (! mkdir($directory, 0700)) {
            throw new RuntimeException(
                'No se pudo preparar el reporte PDF.'
            );
        }

        $deadline = microtime(true) + $wallSeconds;
        $parts = [];
        $seenEmployees = [];
        $previousGroup = null;
        $expectedPages = 0;
        $final = null;
        $completed = false;

        try {
            foreach ($blocks as $index => $block) {
                if ($onProgress !== null) {
                    $onProgress($index, count($blocks));
                }


                $employeeIds = $block
                    ->pluck('employee_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->all();

                $rowIds = $block
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $payload = $data;

                $payload['rows'] = $block;

                $payload['incidentMap'] =
                    array_intersect_key(
                        $data['incidentMap'] ?? [],
                        array_flip($rowIds)
                    );

                $payload['employeeSummaries'] =
                    array_intersect_key(
                        $data['employeeSummaries'] ?? [],
                        array_flip($employeeIds)
                    );

                $payload['employeeSummaries'] =
                    array_diff_key(
                        $payload['employeeSummaries'],
                        $seenEmployees
                    );

                $payload['pdfBlockIndex'] = $index;

                $payload['pdfBlockTotal'] = count($blocks);

                $payload['pdfPreviousGroup'] =
                    $previousGroup;

                $html = $renderHtml($payload);

                $part = sprintf(
                    '%s/part_%05d.pdf',
                    $directory,
                    $index
                );

                self::renderPart(
                    $html,
                    $part,
                    $deadline
                );

                unset($html, $payload);

                $expectedPages += self::pageCount(
                    $part,
                    $deadline
                );

                $parts[] = $part;

                foreach ($employeeIds as $employeeId) {
                    $seenEmployees[$employeeId] = true;
                }

                if ($block->isNotEmpty()) {
                    $last = $block->last();

                    $previousGroup = $groupBy === 'employee'
                        ? 'employee:'.(int) $last->employee_id
                        : 'day:'.EmployeeAttendanceReportService::dateOnly(
                            $last->attendance_date
                        );
                }
            }

            $merged = $directory.'/merged.pdf';

            if (count($parts) === 1) {

                if (! rename($parts[0], $merged)) {
                    throw new RuntimeException(
                        'No se pudo preparar PDF final.'
                    );
                }

            } else {

                self::run(
                    array_merge(
                        [self::PDFUNITE_BIN],
                        $parts,
                        [$merged]
                    ),
                    $deadline
                );
            }

            if (
                self::pageCount($merged, $deadline)
                !== $expectedPages
            ) {
                throw new RuntimeException(
                    'PDF incompleto: paginas incorrectas.'
                );
            }

            $final = tempnam(
                sys_get_temp_dir(),
                'bexia_attendance_final_'
            );

            if ($final === false) {
                $final = null;

                throw new RuntimeException(
                    'No se pudo preparar descarga PDF.'
                );
            }

            if (! rename($merged, $final)) {
                throw new RuntimeException(
                    'No se pudo finalizar descarga PDF.'
                );
            }

            if (! chmod($final, 0600)) {
                throw new RuntimeException(
                    'No se pudo proteger el documento.'
                );
            }

            if ($onProgress !== null) {
                $onProgress(count($blocks), count($blocks));
            }

            $completed = true;

            return $final;

        } finally {

            foreach (glob($directory.'/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            @rmdir($directory);

            if (! $completed && is_string($final)) {
                @unlink($final);
            }
        }
    }

    private static function renderPart(
        string $html,
        string $destination,
        float $deadline
    ): void {
        $worker = <<<'WORKER'
umask(0077);

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';

$app->make(
    \Illuminate\Contracts\Console\Kernel::class
)->bootstrap();

$html = stream_get_contents(STDIN);

$pdf = app('dompdf.wrapper')
    ->loadHTML($html)
    ->setPaper('letter', 'landscape');

$pdf->setOption('isRemoteEnabled', false);

$binary = $pdf->output();

if (! str_starts_with($binary, '%PDF-')) {
    exit(2);
}

if (
    file_put_contents($argv[1], $binary, LOCK_EX)
    !== strlen($binary)
) {
    exit(3);
}

echo "PASS PDF PART\n";
echo "PEAK_MB=".
    round(memory_get_peak_usage(true)/1048576, 2).
    PHP_EOL;
WORKER;

        $process = self::run([
            self::PHP_BIN,
            '-d',
            'memory_limit=512M',
            '-d',
            'max_execution_time=30',
            '-d',
            'display_errors=0',
            '-r',
            $worker,
            '--',
            $destination,
        ], $deadline, $html);

        if (
            ! str_contains(
                $process->getOutput(),
                'PASS PDF PART'
            )
        ) {
            throw new RuntimeException(
                'No se pudo generar un bloque PDF.'
            );
        }
    }

    private static function pageCount(
        string $path,
        float $deadline
    ): int {
        clearstatcache(true, $path);

        if (! is_file($path) || filesize($path) < 5) {
            throw new RuntimeException(
                'Archivo PDF incompleto.'
            );
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException(
                'Archivo PDF no disponible.'
            );
        }

        try {
            $header = fread($handle, 5);
        } finally {
            fclose($handle);
        }

        if ($header !== '%PDF-') {
            throw new RuntimeException(
                'Formato PDF incorrecto.'
            );
        }

        $process = self::run(
            [self::PDFINFO_BIN, $path],
            $deadline
        );

        if (! preg_match(
            '/^Pages:\s+(\d+)\s*$/m',
            $process->getOutput(),
            $matches
        )) {
            throw new RuntimeException(
                'No se pudo validar el numero de paginas.'
            );
        }

        return (int) $matches[1];
    }

    private static function run(
        array $command,
        float $deadline,
        ?string $input = null
    ): Process {
        $remaining = $deadline - microtime(true);

        if ($remaining <= 0) {
            throw new RuntimeException(
                'Tiempo de generacion PDF excedido.'
            );
        }

        $process = new Process(
            $command,
            base_path(),
            ['LC_ALL' => 'C']
        );

        $process->setTimeout(min(30, $remaining));

        if ($input !== null) {
            $process->setInput($input);
        }

        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'Error generando reporte PDF. Codigo: '.
                $process->getExitCode()
            );
        }

        return $process;
    }
}
