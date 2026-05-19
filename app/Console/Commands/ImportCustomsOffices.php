<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImportCustomsOffices extends Command
{
    protected $signature = 'bexia:import-customs-offices
        {file : Ruta del archivo CSV/TXT dentro del contenedor}
        {--company-id= : ID de empresa. Si se omite, se importa como catálogo global}
        {--truncate : Borra aduanas existentes del mismo company_id antes de importar}';

    protected $description = 'Importa catálogo de aduanas desde CSV/TXT';

    public function handle(): int
    {
        if (! Schema::hasTable('customs_offices')) {
            $this->error('No existe la tabla customs_offices. Ejecuta migraciones primero.');
            return self::FAILURE;
        }

        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("No existe el archivo: {$file}");
            return self::FAILURE;
        }

        $companyIdOption = $this->option('company-id');
        $companyId = $companyIdOption === null || $companyIdOption === ''
            ? null
            : (int) $companyIdOption;

        if ($this->option('truncate')) {
            DB::table('customs_offices')
                ->where('company_id', $companyId)
                ->delete();

            $this->warn('Se eliminaron aduanas existentes para company_id=' . ($companyId ?? 'GLOBAL'));
        }

        $handle = fopen($file, 'rb');

        if (! $handle) {
            $this->error("No se pudo abrir el archivo: {$file}");
            return self::FAILURE;
        }

        $firstRow = null;
        $headers = null;
        $imported = 0;
        $skipped = 0;
        $lineNumber = 0;
        $now = now();

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $lineNumber++;

            if ($lineNumber === 1) {
                $row = $this->removeBom($row);
            }

            $row = array_map(fn ($value) => trim((string) $value), $row);

            if ($this->isEmptyRow($row)) {
                $skipped++;
                continue;
            }

            if ($headers === null) {
                if ($this->looksLikeHeader($row)) {
                    $headers = array_map(fn ($value) => $this->normalizeHeader($value), $row);
                    continue;
                }

                $headers = ['code', 'name', 'display_name', 'is_active'];
                $firstRow = $row;
            }

            $record = $firstRow !== null
                ? $this->rowToRecord($headers, $firstRow)
                : $this->rowToRecord($headers, $row);

            $firstRow = null;

            $code = $this->normalizeCustomsCode($record['code'] ?? null);
            $name = $this->clean($record['name'] ?? null);
            $displayName = $this->clean($record['display_name'] ?? null);
            $isActive = $this->booleanValue($record['is_active'] ?? true);

            if ($name === null && $displayName !== null) {
                $name = $displayName;
            }

            if ($displayName === null && $name !== null) {
                $displayName = $name;
            }

            if ($name === null) {
                $this->warn("Linea {$lineNumber}: sin nombre, omitida.");
                $skipped++;
                continue;
            }

            DB::table('customs_offices')->updateOrInsert(
                [
                    'company_id' => $companyId,
                    'name' => mb_strtoupper($name),
                ],
                [
                    'code' => $code,
                    'display_name' => $displayName,
                    'is_active' => $isActive,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $imported++;
        }

        fclose($handle);

        $this->info("Importación terminada.");
        $this->line("Importadas/actualizadas: {$imported}");
        $this->line("Omitidas: {$skipped}");

        return self::SUCCESS;
    }

    protected function removeBom(array $row): array
    {
        if (isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
        }

        return $row;
    }

    protected function looksLikeHeader(array $row): bool
    {
        $normalized = array_map(fn ($value) => $this->normalizeHeader((string) $value), $row);

        return in_array('name', $normalized, true)
            || in_array('nombre', $normalized, true)
            || in_array('aduana', $normalized, true)
            || in_array('display_name', $normalized, true)
            || in_array('nombre_mostrar', $normalized, true);
    }

    protected function normalizeHeader(string $value): string
    {
        $value = Str::ascii(trim(mb_strtolower($value)));
        $value = str_replace([' ', '-', '.', '/', '\\'], '_', $value);
        $value = preg_replace('/_+/', '_', $value) ?: $value;

        return match ($value) {
            'codigo', 'clave', 'c_aduana', 'aduana_codigo' => 'code',
            'nombre', 'aduana', 'descripcion', 'description' => 'name',
            'nombre_mostrar', 'mostrar', 'display', 'label', 'etiqueta' => 'display_name',
            'activo', 'activa', 'active', 'is_active' => 'is_active',
            default => $value,
        };
    }

    protected function rowToRecord(array $headers, array $row): array
    {
        $record = [];

        foreach ($headers as $index => $header) {
            $record[$header] = $row[$index] ?? null;
        }

        return $record;
    }


    protected function normalizeCustomsCode(mixed $value): ?string
    {
        $value = $this->clean($value);

        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';

        if ($digits !== '') {
            return str_pad(substr($digits, 0, 3), 3, '0', STR_PAD_LEFT);
        }

        return $value;
    }


    protected function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return preg_replace('/\s+/', ' ', $value) ?: $value;
    }

    protected function booleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = trim(mb_strtolower((string) $value));

        if ($value === '') {
            return true;
        }

        return in_array($value, ['1', 'si', 'sí', 'true', 'activo', 'activa', 'yes', 'y'], true);
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
