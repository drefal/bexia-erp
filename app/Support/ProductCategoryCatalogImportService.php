<?php

namespace App\Support;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProductCategoryCatalogImportService
{
    public static function validateForModalFromFilamentUpload(mixed $file): array
    {
        try {
            $path = static::resolveUploadedPath($file);
            $companyId = static::currentCompanyId();

            if ($companyId === null) {
                return static::modalErrorResult('No se pudo detectar la empresa actual.');
            }

            return static::validateFileForModal($path, $companyId);
        } catch (\Throwable $e) {
            return static::modalErrorResult($e->getMessage());
        }
    }

    public static function validateFileForModal(string $path, int $companyId): array
    {
        try {
            $result = static::processFile($path, $companyId, false);
            $result = static::appendCodeDuplicationValidation($path, $companyId, $result);

            $isClean = (int) ($result['errors'] ?? 0) === 0
                && (int) ($result['validated'] ?? 0) > 0;

            return [
                'ok' => $isClean,
                'hash' => file_exists($path) ? (hash_file('sha256', $path) ?: '') : '',
                'html' => static::modalValidationSummaryHtml($result, $isClean),
                'result' => $result,
            ];
        } catch (\Throwable $e) {
            return static::modalErrorResult($e->getMessage());
        }
    }

    public static function importValidatedModalUpload(mixed $file, ?string $expectedHash = null, bool $confirmed = false): void
    {
        if (! $confirmed) {
            throw ValidationException::withMessages([
                'confirm_apply' => 'Confirma que deseas aplicar el archivo validado.',
            ]);
        }

        $path = static::resolveUploadedPath($file);
        $companyId = static::currentCompanyId();

        if ($companyId === null) {
            throw ValidationException::withMessages([
                'file' => 'No se pudo detectar la empresa actual.',
            ]);
        }

        $currentHash = file_exists($path) ? (hash_file('sha256', $path) ?: '') : '';

        if ($expectedHash && $currentHash !== $expectedHash) {
            throw ValidationException::withMessages([
                'file' => 'El archivo cambió después de validarse. Vuelve a validarlo.',
            ]);
        }

        $validation = static::validateFileForModal($path, $companyId);

        if (! (bool) ($validation['ok'] ?? false)) {
            throw ValidationException::withMessages([
                'file' => 'El archivo no pasa la validación. Corrige los errores antes de importar.',
            ]);
        }

        try {
            $result = static::applyFileStrict($path, $companyId);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('CATEGORIAS_PRODUCTOS_IMPORT_STRICT_ERROR', [
                'company_id' => $companyId,
                'path' => $path,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw ValidationException::withMessages([
                'file' => 'No se aplicó ningún cambio. Error: ' . $e->getMessage(),
            ]);
        }

        Notification::make()
            ->title('Categorías importadas')
            ->body('Creadas: ' . (int) ($result['created'] ?? 0) . '. Actualizadas: ' . (int) ($result['updated'] ?? 0) . '. Omitidas: ' . (int) ($result['skipped'] ?? 0) . '.')
            ->success()
            ->send();
    }

    public static function processFile(string $path, int $companyId, bool $apply = false): array
    {
        if ($apply) {
            return static::applyFileStrict($path, $companyId);
        }

        if (! file_exists($path)) {
            throw new \RuntimeException('No se encontró el archivo a importar: ' . $path);
        }

        $rows = static::readRows($path);

        if (count($rows) < 2) {
            return static::emptyResult($companyId, 'El archivo no tiene líneas de categorías.');
        }

        $headers = static::normalizeHeaderRow($rows[0]);

        $result = [
            'company_id' => $companyId,
            'apply' => false,
            'created_at' => now()->toDateTimeString(),
            'total_rows' => max(0, count($rows) - 1),
            'created' => 0,
            'updated' => 0,
            'validated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'log_rows' => [],
        ];

        foreach (array_slice($rows, 1) as $index => $rawRow) {
            $lineNumber = $index + 2;
            $data = static::rowToAssoc($headers, $rawRow);

            if (static::isEmptyCategoryRow($data)) {
                $result['skipped']++;
                $result['log_rows'][] = static::logRow($lineNumber, 'omitir', 'OMITIDA', '', '', '', 'Línea vacía.');
                continue;
            }

            $action = static::normalizeAction($data['accion'] ?? 'crear_o_actualizar');

            if ($action === 'omitir') {
                $result['skipped']++;
                $result['log_rows'][] = static::logRow($lineNumber, $action, 'OMITIDA', $data['codigo'] ?? '', $data['nombre'] ?? '', '', 'Acción omitir.');
                continue;
            }

            try {
                $outcome = static::processRow($data, $action, $companyId, false);
                $result['validated']++;

                $result['log_rows'][] = static::logRow(
                    $lineNumber,
                    $action,
                    (string) ($outcome['status'] ?? 'OK'),
                    $data['codigo'] ?? '',
                    $data['nombre'] ?? '',
                    (string) ($outcome['category_id'] ?? ''),
                    (string) ($outcome['message'] ?? '')
                );
            } catch (\Throwable $e) {
                $result['errors']++;
                $result['log_rows'][] = static::logRow($lineNumber, $action, 'ERROR', $data['codigo'] ?? '', $data['nombre'] ?? '', '', $e->getMessage());
            }
        }

        return $result;
    }

    public static function applyFileStrict(string $path, int $companyId): array
    {
        $validation = static::processFile($path, $companyId, false);
        $validation = static::appendCodeDuplicationValidation($path, $companyId, $validation);

        if ((int) ($validation['errors'] ?? 0) > 0 || (int) ($validation['validated'] ?? 0) <= 0) {
            throw new \RuntimeException('El archivo no pasa la validación. No se aplicó ningún cambio.');
        }

        $rows = static::readRows($path);
        $headers = static::normalizeHeaderRow($rows[0]);

        $result = [
            'company_id' => $companyId,
            'apply' => true,
            'created_at' => now()->toDateTimeString(),
            'total_rows' => max(0, count($rows) - 1),
            'created' => 0,
            'updated' => 0,
            'validated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'log_rows' => [],
        ];

        DB::transaction(function () use (&$result, $rows, $headers, $companyId): void {
            foreach (array_slice($rows, 1) as $index => $rawRow) {
                $lineNumber = $index + 2;
                $data = static::rowToAssoc($headers, $rawRow);

                if (static::isEmptyCategoryRow($data)) {
                    $result['skipped']++;
                    $result['log_rows'][] = static::logRow($lineNumber, 'omitir', 'OMITIDA', '', '', '', 'Línea vacía.');
                    continue;
                }

                $action = static::normalizeAction($data['accion'] ?? 'crear_o_actualizar');

                if ($action === 'omitir') {
                    $result['skipped']++;
                    $result['log_rows'][] = static::logRow($lineNumber, $action, 'OMITIDA', $data['codigo'] ?? '', $data['nombre'] ?? '', '', 'Acción omitir.');
                    continue;
                }

                $outcome = static::processRow($data, $action, $companyId, true);

                if (($outcome['status'] ?? '') === 'CREADA') {
                    $result['created']++;
                } elseif (($outcome['status'] ?? '') === 'ACTUALIZADA') {
                    $result['updated']++;
                }

                $result['log_rows'][] = static::logRow(
                    $lineNumber,
                    $action,
                    (string) ($outcome['status'] ?? 'OK'),
                    $data['codigo'] ?? '',
                    $data['nombre'] ?? '',
                    (string) ($outcome['category_id'] ?? ''),
                    (string) ($outcome['message'] ?? '')
                );
            }

            static::recalculateTree($companyId);
        });

        return $result;
    }

    protected static function processRow(array $data, string $action, int $companyId, bool $apply): array
    {
        if (! Schema::hasTable('product_categories')) {
            throw new \RuntimeException('No existe tabla product_categories.');
        }

        $code = trim((string) ($data['codigo'] ?? ''));
        $name = trim((string) ($data['nombre'] ?? ''));
        $existing = static::findExistingCategory($companyId, $code, $data['id_solo_lectura'] ?? null);

        if ($action === 'crear' && $existing) {
            throw new \RuntimeException('Ya existe una categoría con ese código.');
        }

        if ($action === 'actualizar' && ! $existing) {
            throw new \RuntimeException('No se encontró categoría existente para actualizar.');
        }

        if (! $existing && $code === '') {
            throw new \RuntimeException('Para crear una categoría, el código es obligatorio.');
        }

        if (! $existing && $name === '') {
            throw new \RuntimeException('Para crear una categoría, el nombre es obligatorio.');
        }

        if ($existing && $code !== '') {
            $other = DB::table('product_categories')
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(TRIM(code)) = ?', [mb_strtolower($code)])
                ->where('id', '<>', (int) $existing->id)
                ->first();

            if ($other) {
                throw new \RuntimeException('El código ya pertenece a otra categoría de esta empresa.');
            }
        }

        $selfId = $existing ? (int) $existing->id : null;
        $parentId = static::parentId($companyId, trim((string) ($data['codigo_padre'] ?? '')), trim((string) ($data['nombre_padre'] ?? '')), $selfId);
        $payload = static::buildPayload($data, $companyId, $parentId, $existing);

        if (! $existing) {
            $payload['company_id'] = $companyId;
            $payload['created_at'] = now();
            $payload['updated_at'] = now();

            if (! $apply) {
                return ['status' => 'VALIDADO_CREAR', 'category_id' => '', 'message' => 'Categoría nueva válida. No aplicada.'];
            }

            $categoryId = DB::table('product_categories')->insertGetId($payload);

            return ['status' => 'CREADA', 'category_id' => $categoryId, 'message' => 'Categoría creada.'];
        }

        $payload['updated_at'] = now();

        if (! $apply) {
            return ['status' => 'VALIDADO_ACTUALIZAR', 'category_id' => (int) $existing->id, 'message' => 'Categoría existente válida. No aplicada.'];
        }

        DB::table('product_categories')
            ->where('id', $existing->id)
            ->where('company_id', $companyId)
            ->update($payload);

        return ['status' => 'ACTUALIZADA', 'category_id' => (int) $existing->id, 'message' => 'Categoría actualizada.'];
    }

    protected static function buildPayload(array $data, int $companyId, ?int $parentId, ?object $existing): array
    {
        $payload = [];

        static::setText($payload, 'code', $data, 'codigo', true);
        static::setText($payload, 'name', $data, 'nombre', true);
        static::setText($payload, 'description', $data, 'descripcion', false);

        if (Schema::hasColumn('product_categories', 'parent_id')) {
            $payload['parent_id'] = $parentId;
        }

        if (Schema::hasColumn('product_categories', 'costing_method') && array_key_exists('metodo_costeo', $data)) {
            $payload['costing_method'] = static::parseCostingMethod($data['metodo_costeo']);
        }

        static::setBool($payload, 'is_active', $data, 'activa');
        static::setInteger($payload, 'sort_order', $data, 'orden');

        if (! $existing) {
            foreach (['is_active' => true, 'sort_order' => 0, 'costing_method' => 'inherit'] as $column => $value) {
                if (Schema::hasColumn('product_categories', $column) && ! array_key_exists($column, $payload)) {
                    $payload[$column] = $value;
                }
            }
        }

        return $payload;
    }

    protected static function findExistingCategory(int $companyId, string $code, mixed $readOnlyId = null): ?object
    {
        $id = static::parseInteger($readOnlyId);

        if ($id !== null) {
            $category = DB::table('product_categories')
                ->where('company_id', $companyId)
                ->where('id', $id)
                ->first();

            if ($category) {
                return $category;
            }
        }

        if ($code !== '') {
            return DB::table('product_categories')
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(TRIM(code)) = ?', [mb_strtolower($code)])
                ->first();
        }

        return null;
    }

    protected static function parentId(int $companyId, string $code, string $name, ?int $selfId = null): ?int
    {
        if ($code === '' && $name === '') {
            return null;
        }

        $query = DB::table('product_categories')
            ->where('company_id', $companyId);

        if ($code !== '') {
            $query->whereRaw('LOWER(TRIM(code)) = ?', [mb_strtolower($code)]);
        } else {
            $query->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)]);
        }

        $parent = $query->first();

        if (! $parent) {
            throw new \RuntimeException('No se encontró categoría padre: ' . ($code !== '' ? $code : $name));
        }

        if ($selfId !== null && (int) $parent->id === $selfId) {
            throw new \RuntimeException('Una categoría no puede ser su propia categoría padre.');
        }

        return (int) $parent->id;
    }

    protected static function appendCodeDuplicationValidation(string $path, int $companyId, array $result): array
    {
        $rows = static::readRows($path);

        if (count($rows) < 2) {
            return $result;
        }

        $headers = static::normalizeHeaderRow($rows[0]);
        $codes = [];

        foreach (array_slice($rows, 1) as $index => $rawRow) {
            $lineNumber = $index + 2;
            $data = static::rowToAssoc($headers, $rawRow);

            if (static::isEmptyCategoryRow($data)) {
                continue;
            }

            if (static::normalizeAction($data['accion'] ?? 'crear_o_actualizar') === 'omitir') {
                continue;
            }

            $code = trim((string) ($data['codigo'] ?? ''));

            if ($code === '') {
                continue;
            }

            $key = mb_strtolower($code);
            $codes[$key]['code'] = $code;
            $codes[$key]['lines'][] = $lineNumber;
        }

        foreach ($codes as $item) {
            $lines = $item['lines'] ?? [];

            if (count($lines) > 1) {
                $result['errors'] = (int) ($result['errors'] ?? 0) + 1;
                $result['log_rows'][] = static::logRow((int) $lines[0], 'validar', 'ERROR_CODIGO_DUPLICADO_ARCHIVO', (string) ($item['code'] ?? ''), '', '', 'El código aparece más de una vez en el archivo. Líneas: ' . implode(', ', $lines));
            }
        }

        return $result;
    }

    protected static function recalculateTree(int $companyId): void
    {
        $categories = DB::table('product_categories')
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('id');

        $memo = [];

        $build = function (int $id) use (&$build, &$memo, $categories): array {
            if (isset($memo[$id])) {
                return $memo[$id];
            }

            $category = $categories[$id] ?? null;

            if (! $category) {
                return ['path' => '', 'level' => 0];
            }

            $name = trim((string) ($category->name ?? ''));
            $parentId = $category->parent_id ? (int) $category->parent_id : null;

            if ($parentId && isset($categories[$parentId]) && $parentId !== $id) {
                $parent = $build($parentId);
                $path = trim($parent['path'] . ' / ' . $name, ' /');
                $level = ((int) $parent['level']) + 1;
            } else {
                $path = $name;
                $level = 0;
            }

            return $memo[$id] = ['path' => $path, 'level' => $level];
        };

        foreach ($categories as $category) {
            $data = $build((int) $category->id);
            $payload = ['updated_at' => now()];

            if (Schema::hasColumn('product_categories', 'full_path')) {
                $payload['full_path'] = $data['path'];
            }

            if (Schema::hasColumn('product_categories', 'level')) {
                $payload['level'] = $data['level'];
            }

            DB::table('product_categories')->where('id', $category->id)->update($payload);
        }
    }

    protected static function setText(array &$payload, string $column, array $data, string $key, bool $skipEmpty): void
    {
        if (! Schema::hasColumn('product_categories', $column) || ! array_key_exists($key, $data)) {
            return;
        }

        $value = trim((string) $data[$key]);

        if ($value === '' && $skipEmpty) {
            return;
        }

        $payload[$column] = $value;
    }

    protected static function setBool(array &$payload, string $column, array $data, string $key): void
    {
        if (! Schema::hasColumn('product_categories', $column) || ! array_key_exists($key, $data)) {
            return;
        }

        $value = trim((string) $data[$key]);

        if ($value === '') {
            return;
        }

        $payload[$column] = static::parseBool($value);
    }

    protected static function setInteger(array &$payload, string $column, array $data, string $key): void
    {
        if (! Schema::hasColumn('product_categories', $column) || ! array_key_exists($key, $data)) {
            return;
        }

        $value = static::parseInteger($data[$key]);

        if ($value === null) {
            return;
        }

        $payload[$column] = $value;
    }

    protected static function parseCostingMethod(mixed $value): string
    {
        $value = static::normalizedText($value);

        return match ($value) {
            'promedio', 'average' => 'average',
            'fifo' => 'fifo',
            'estandar', 'estándar', 'standard' => 'standard',
            'heredar', 'inherit', '' => 'inherit',
            default => 'inherit',
        };
    }

    protected static function parseBool(mixed $value): bool
    {
        $value = static::normalizedText($value);

        return in_array($value, ['1', 'si', 'sí', 'true', 'verdadero', 'yes', 'x'], true);
    }

    protected static function parseInteger(mixed $value): ?int
    {
        $value = trim((string) $value);

        return $value !== '' && is_numeric($value) ? (int) $value : null;
    }

    protected static function normalizeAction(mixed $value): string
    {
        $value = static::normalizedText($value);

        return match ($value) {
            'crear' => 'crear',
            'actualizar' => 'actualizar',
            'crear_o_actualizar', 'crear o actualizar', 'crear/actualizar', 'upsert', '' => 'crear_o_actualizar',
            'omitir', 'skip' => 'omitir',
            default => 'crear_o_actualizar',
        };
    }

    protected static function normalizedText(mixed $value): string
    {
        $value = trim(mb_strtolower((string) $value));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return $ascii ? trim(mb_strtolower($ascii)) : $value;
    }

    protected static function readRows(string $path): array
    {
        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'xlsx' => static::readXlsxRows($path),
            'csv', 'txt' => static::readCsvRows($path),
            default => throw new \RuntimeException('Formato no soportado. Usa .xlsx o .csv.'),
        };
    }

    protected static function readCsvRows(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new \RuntimeException('No se pudo leer el CSV.');
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new \RuntimeException('No se pudo abrir el CSV.');
        }

        $rows = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    protected static function readXlsxRows(string $path): array
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('El servidor no tiene ZipArchive para leer XLSX.');
        }

        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            throw new \RuntimeException('No se pudo abrir el XLSX.');
        }

        $sharedStrings = static::xlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($sheetXml === false) {
            $zip->close();
            throw new \RuntimeException('No se encontró xl/worksheets/sheet1.xml en el XLSX.');
        }

        $zip->close();

        $xml = simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (! $xml) {
            throw new \RuntimeException('No se pudo leer XML de la hoja XLSX.');
        }

        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $rows = [];

        foreach ($xml->children($ns)->sheetData->children($ns)->row as $rowNode) {
            $row = [];

            foreach ($rowNode->children($ns)->c as $cell) {
                $ref = (string) $cell['r'];
                $columnIndex = static::columnIndexFromReference($ref);
                $row[$columnIndex - 1] = static::xlsxCellValue($cell, $sharedStrings, $ns);
            }

            if (! empty($row)) {
                ksort($row);
                $max = max(array_keys($row));
                $normalized = [];

                for ($i = 0; $i <= $max; $i++) {
                    $normalized[] = $row[$i] ?? '';
                }

                $rows[] = $normalized;
            }
        }

        return $rows;
    }

    protected static function xlsxSharedStrings(\ZipArchive $zip): array
    {
        $xmlString = $zip->getFromName('xl/sharedStrings.xml');

        if ($xmlString === false) {
            return [];
        }

        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (! $xml) {
            return [];
        }

        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $strings = [];

        foreach ($xml->children($ns)->si as $si) {
            $text = '';

            if (isset($si->children($ns)->t)) {
                $text .= (string) $si->children($ns)->t;
            }

            if (isset($si->children($ns)->r)) {
                foreach ($si->children($ns)->r as $run) {
                    $text .= (string) $run->children($ns)->t;
                }
            }

            $strings[] = $text;
        }

        return $strings;
    }

    protected static function xlsxCellValue(\SimpleXMLElement $cell, array $sharedStrings, string $ns): string
    {
        $type = (string) $cell['t'];
        $children = $cell->children($ns);

        if ($type === 's') {
            return (string) ($sharedStrings[(int) ($children->v ?? 0)] ?? '');
        }

        if ($type === 'inlineStr') {
            return (string) ($children->is->children($ns)->t ?? '');
        }

        if ($type === 'b') {
            return ((string) ($children->v ?? '0')) === '1' ? '1' : '0';
        }

        return (string) ($children->v ?? '');
    }

    protected static function columnIndexFromReference(string $reference): int
    {
        preg_match('/^[A-Z]+/i', $reference, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(1, $index);
    }

    protected static function normalizeHeaderRow(array $row): array
    {
        $headers = [];

        foreach ($row as $index => $header) {
            $normalized = static::normalizeHeader((string) $header);

            if ($normalized !== '') {
                $headers[$index] = $normalized;
            }
        }

        return $headers;
    }

    protected static function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header) ?: $header;
        $key = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $ascii) ?? '');
        $key = trim($key, '_');

        $aliases = [
            'categoria' => 'nombre',
            'clave' => 'codigo',
            'codigo_categoria' => 'codigo',
            'categoria_padre' => 'nombre_padre',
            'padre' => 'nombre_padre',
            'codigo_categoria_padre' => 'codigo_padre',
            'costeo' => 'metodo_costeo',
            'metodo_de_costeo' => 'metodo_costeo',
            'activo' => 'activa',
            'is_active' => 'activa',
            'sort_order' => 'orden',
        ];

        return $aliases[$key] ?? $key;
    }

    protected static function rowToAssoc(array $headers, array $row): array
    {
        $data = [];

        foreach ($headers as $index => $key) {
            $data[$key] = isset($row[$index]) ? trim((string) $row[$index]) : '';
        }

        return $data;
    }

    protected static function isEmptyCategoryRow(array $data): bool
    {
        foreach (['codigo', 'nombre', 'id_solo_lectura'] as $key) {
            if (trim((string) ($data[$key] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    protected static function emptyResult(int $companyId, string $message): array
    {
        return [
            'company_id' => $companyId,
            'apply' => false,
            'created_at' => now()->toDateTimeString(),
            'total_rows' => 0,
            'created' => 0,
            'updated' => 0,
            'validated' => 0,
            'skipped' => 0,
            'errors' => 1,
            'log_rows' => [static::logRow(0, '', 'ERROR', '', '', '', $message)],
        ];
    }

    protected static function logRow(int $line, string $action, string $result, string $code, string $name, string $categoryId, string $message): array
    {
        return [
            'linea' => $line,
            'accion' => $action,
            'resultado' => $result,
            'codigo' => $code,
            'nombre' => $name,
            'categoria_id' => $categoryId,
            'mensaje' => $message,
        ];
    }

    protected static function modalErrorResult(string $message): array
    {
        return [
            'ok' => false,
            'hash' => '',
            'html' => '<div class="rounded-lg border border-danger-300 bg-danger-50 p-3 text-sm text-danger-700"><div class="font-semibold">No se pudo validar el archivo</div><div class="mt-1">' . static::modalEscape($message) . '</div></div>',
            'result' => ['errors' => 1, 'validated' => 0, 'log_rows' => []],
        ];
    }

    protected static function modalValidationSummaryHtml(array $result, bool $isClean): string
    {
        $total = (int) ($result['total_rows'] ?? 0);
        $validated = (int) ($result['validated'] ?? 0);
        $skipped = (int) ($result['skipped'] ?? 0);
        $errors = (int) ($result['errors'] ?? 0);

        $wouldCreate = 0;
        $wouldUpdate = 0;

        foreach (($result['log_rows'] ?? []) as $row) {
            $status = (string) ($row['resultado'] ?? '');

            if ($status === 'VALIDADO_CREAR') {
                $wouldCreate++;
            }

            if ($status === 'VALIDADO_ACTUALIZAR') {
                $wouldUpdate++;
            }
        }

        $statusClass = $isClean ? 'border-success-300 bg-success-50 text-success-800' : 'border-danger-300 bg-danger-50 text-danger-800';
        $statusTitle = $isClean ? 'Validación limpia' : 'Validación con errores';

        $html = '<div class="rounded-lg border ' . $statusClass . ' p-3 text-sm">';
        $html .= '<div class="font-semibold">' . $statusTitle . '</div>';
        $html .= '<div class="mt-2 grid grid-cols-2 gap-2">';
        $html .= '<div>Total filas: <strong>' . $total . '</strong></div>';
        $html .= '<div>Validadas: <strong>' . $validated . '</strong></div>';
        $html .= '<div>Se crearán: <strong>' . $wouldCreate . '</strong></div>';
        $html .= '<div>Se actualizarán: <strong>' . $wouldUpdate . '</strong></div>';
        $html .= '<div>Omitidas: <strong>' . $skipped . '</strong></div>';
        $html .= '<div>Errores: <strong>' . $errors . '</strong></div>';
        $html .= '</div>';

        $errorRows = array_values(array_filter(
            $result['log_rows'] ?? [],
            fn (array $row): bool => str_contains((string) ($row['resultado'] ?? ''), 'ERROR')
        ));

        if (! empty($errorRows)) {
            $html .= '<div class="mt-3 font-semibold">Errores principales</div><ul class="mt-1 list-disc space-y-1 pl-5">';

            foreach (array_slice($errorRows, 0, 8) as $row) {
                $line = static::modalEscape((string) ($row['linea'] ?? ''));
                $code = static::modalEscape((string) ($row['codigo'] ?? ''));
                $message = static::modalEscape((string) ($row['mensaje'] ?? ''));
                $resultLabel = static::modalEscape((string) ($row['resultado'] ?? ''));

                $html .= '<li>'
                    . ($line !== '0' && $line !== '' ? 'Línea ' . $line . ': ' : '')
                    . ($code !== '' ? '[' . $code . '] ' : '')
                    . $resultLabel . ' - ' . $message
                    . '</li>';
            }

            $html .= '</ul>';
        }

        $html .= $isClean
            ? '<div class="mt-3 rounded-md bg-white/70 p-2">El archivo está listo. Se aplicará en modo todo-o-nada.</div>'
            : '<div class="mt-3 rounded-md bg-white/70 p-2">Corrige el archivo y vuelve a subirlo.</div>';

        $html .= '</div>';

        return $html;
    }

    protected static function modalEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected static function resolveUploadedPath(mixed $file): string
    {
        if (is_array($file)) {
            $file = reset($file);
        }

        if (is_object($file) && method_exists($file, 'getRealPath')) {
            $realPath = $file->getRealPath();

            if ($realPath && file_exists($realPath)) {
                return $realPath;
            }
        }

        $file = (string) $file;

        foreach ([$file, storage_path('app/' . $file), storage_path('app/private/' . $file), storage_path('app/public/' . $file)] as $candidate) {
            if ($candidate && file_exists($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('No se pudo resolver la ruta del archivo importado.');
    }

    public static function currentCompanyId(): ?int
    {
        try {
            $tenant = Filament::getTenant();

            if (is_object($tenant) && isset($tenant->id)) {
                return (int) $tenant->id;
            }

            if (is_numeric($tenant)) {
                return (int) $tenant;
            }
        } catch (\Throwable) {
        }

        try {
            foreach (['tenant', 'company', 'company_id'] as $parameter) {
                $value = request()->route($parameter);

                if (is_object($value) && isset($value->id)) {
                    return (int) $value->id;
                }

                if (is_numeric($value)) {
                    return (int) $value;
                }
            }

            $segment = request()->segment(2);

            if (is_numeric($segment)) {
                return (int) $segment;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
