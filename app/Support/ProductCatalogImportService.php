<?php

namespace App\Support;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductCatalogImportService
{
    public static function importFromFilamentUpload(mixed $file, bool $apply = false): StreamedResponse
    {
        $path = static::resolveUploadedPath($file);
        $companyId = static::currentCompanyId();

        if ($companyId === null) {
            abort(422, 'No se pudo detectar la empresa actual.');
        }

        $result = static::processFile($path, $companyId, $apply);

        return static::downloadLog($result, $apply);
    }


    public static function validateFromFilamentUpload(mixed $file): StreamedResponse
    {
        $path = static::resolveUploadedPath($file);
        $companyId = static::currentCompanyId();

        if ($companyId === null) {
            abort(422, 'No se pudo detectar la empresa actual.');
        }

        $result = static::processFile($path, $companyId, false);
        $result = static::appendReferenceDuplicationValidation($path, $companyId, $result);

        $isClean = (int) ($result['errors'] ?? 0) === 0
            && (int) ($result['validated'] ?? 0) > 0;

        if ($isClean) {
            session([
                'bexia_products_import_validation_ok' => true,
                'bexia_products_import_validated_path' => $path,
                'bexia_products_import_validated_hash' => file_exists($path) ? hash_file('sha256', $path) : null,
                'bexia_products_import_validated_company_id' => $companyId,
                'bexia_products_import_validated_at' => now()->toDateTimeString(),
            ]);

            $result['log_rows'][] = static::logRow(
                0,
                'validar',
                'VALIDACION_LIMPIA',
                '',
                '',
                '',
                '',
                'Archivo validado correctamente. Ya puedes usar Procesar validación limpia.'
            );
        } else {
            static::clearCleanValidationSession();

            $result['log_rows'][] = static::logRow(
                0,
                'validar',
                'VALIDACION_NO_APROBADA',
                '',
                '',
                '',
                '',
                'Corrige los errores del archivo antes de importar.'
            );
        }

        return static::downloadLog($result, false);
    }

    public static function importLastValidatedFromSession(): StreamedResponse
    {
        $companyId = static::currentCompanyId();

        if ($companyId === null) {
            abort(422, 'No se pudo detectar la empresa actual.');
        }

        $path = (string) session('bexia_products_import_validated_path');
        $hash = (string) session('bexia_products_import_validated_hash');
        $validatedCompanyId = (int) session('bexia_products_import_validated_company_id');

        if (! static::hasCleanValidationSession()) {
            abort(422, 'Primero debes validar un archivo sin errores.');
        }

        if ($validatedCompanyId !== $companyId) {
            static::clearCleanValidationSession();
            abort(422, 'La validación pertenece a otra empresa. Vuelve a validar el archivo.');
        }

        if (! file_exists($path) || hash_file('sha256', $path) !== $hash) {
            static::clearCleanValidationSession();
            abort(422, 'El archivo validado ya no existe o cambió. Vuelve a validarlo.');
        }

        $preflight = static::processFile($path, $companyId, false);
        $preflight = static::appendReferenceDuplicationValidation($path, $companyId, $preflight);

        if ((int) ($preflight['errors'] ?? 0) > 0 || (int) ($preflight['validated'] ?? 0) <= 0) {
            static::clearCleanValidationSession();

            $preflight['log_rows'][] = static::logRow(
                0,
                'importar',
                'IMPORTACION_BLOQUEADA',
                '',
                '',
                '',
                '',
                'El archivo ya no pasa la validación. No se aplicaron cambios.'
            );

            return static::downloadLog($preflight, false);
        }

        $result = static::processFile($path, $companyId, true);

        static::clearCleanValidationSession();

        return static::downloadLog($result, true);
    }

    public static function hasCleanValidationSession(): bool
    {
        $path = (string) session('bexia_products_import_validated_path');
        $hash = (string) session('bexia_products_import_validated_hash');

        return session('bexia_products_import_validation_ok') === true
            && $path !== ''
            && $hash !== ''
            && file_exists($path)
            && hash_file('sha256', $path) === $hash;
    }

    protected static function clearCleanValidationSession(): void
    {
        session()->forget([
            'bexia_products_import_validation_ok',
            'bexia_products_import_validated_path',
            'bexia_products_import_validated_hash',
            'bexia_products_import_validated_company_id',
            'bexia_products_import_validated_at',
        ]);
    }

    protected static function appendReferenceDuplicationValidation(string $path, int $companyId, array $result): array
    {
        try {
            $rows = static::readRows($path);

            if (count($rows) < 2) {
                return $result;
            }

            $headers = static::normalizeHeaderRow($rows[0]);
            $references = [];

            foreach (array_slice($rows, 1) as $index => $rawRow) {
                $lineNumber = $index + 2;
                $data = static::rowToAssoc($headers, $rawRow);

                if (static::isEmptyProductRow($data)) {
                    continue;
                }

                $action = static::normalizeAction($data['accion'] ?? 'crear_o_actualizar');

                if ($action === 'omitir') {
                    continue;
                }

                $reference = trim((string) ($data['referencia_interna'] ?? ''));

                if ($reference === '') {
                    continue;
                }

                $key = mb_strtolower($reference);
                $references[$key]['reference'] = $reference;
                $references[$key]['lines'][] = $lineNumber;
            }

            foreach ($references as $item) {
                $lines = $item['lines'] ?? [];

                if (count($lines) > 1) {
                    $result['errors'] = (int) ($result['errors'] ?? 0) + 1;

                    $result['log_rows'][] = static::logRow(
                        (int) $lines[0],
                        'validar',
                        'ERROR_REFERENCIA_DUPLICADA_ARCHIVO',
                        (string) ($item['reference'] ?? ''),
                        '',
                        '',
                        '',
                        'La referencia_interna aparece más de una vez en el archivo. Líneas: ' . implode(', ', $lines)
                    );
                }
            }

            $fileReferences = array_values(array_unique(array_map(
                fn (array $item): string => (string) ($item['reference'] ?? ''),
                $references
            )));

            if (! empty($fileReferences) && Schema::hasTable('products') && Schema::hasColumn('products', 'internal_reference')) {
                $dbDuplicates = DB::table('products')
                    ->where('company_id', $companyId)
                    ->whereIn('internal_reference', $fileReferences)
                    ->select('internal_reference', DB::raw('COUNT(*) as qty'))
                    ->groupBy('internal_reference')
                    ->havingRaw('COUNT(*) > 1')
                    ->get();

                foreach ($dbDuplicates as $dup) {
                    $result['errors'] = (int) ($result['errors'] ?? 0) + 1;

                    $result['log_rows'][] = static::logRow(
                        0,
                        'validar',
                        'ERROR_REFERENCIA_DUPLICADA_EMPRESA',
                        (string) $dup->internal_reference,
                        '',
                        '',
                        '',
                        'La referencia_interna ya está duplicada en esta empresa. Cantidad encontrada: ' . $dup->qty
                    );
                }
            }
        } catch (\Throwable $e) {
            $result['errors'] = (int) ($result['errors'] ?? 0) + 1;

            $result['log_rows'][] = static::logRow(
                0,
                'validar',
                'ERROR_VALIDACION_REFERENCIAS',
                '',
                '',
                '',
                '',
                $e->getMessage()
            );
        }

        return $result;
    }



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
            if (! file_exists($path)) {
                return static::modalErrorResult('No se encontró el archivo a validar.');
            }

            $result = static::processFile($path, $companyId, false);
            $result = static::appendReferenceDuplicationValidation($path, $companyId, $result);

            $isClean = (int) ($result['errors'] ?? 0) === 0
                && (int) ($result['validated'] ?? 0) > 0;

            return [
                'ok' => $isClean,
                'hash' => hash_file('sha256', $path) ?: '',
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
            throw \Illuminate\Validation\ValidationException::withMessages([
                'confirm_apply' => 'Confirma que deseas aplicar el archivo validado.',
            ]);
        }

        $path = static::resolveUploadedPath($file);
        $companyId = static::currentCompanyId();

        if ($companyId === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => 'No se pudo detectar la empresa actual.',
            ]);
        }

        if (! file_exists($path)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => 'El archivo validado ya no existe. Vuelve a subirlo.',
            ]);
        }

        $currentHash = hash_file('sha256', $path) ?: '';

        if ($expectedHash && $currentHash !== $expectedHash) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => 'El archivo cambio despues de validarse. Vuelve a validarlo.',
            ]);
        }

        $validation = static::validateFileForModal($path, $companyId);

        if (! (bool) ($validation['ok'] ?? false)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => 'El archivo no pasa la validacion. Corrige los errores antes de importar.',
            ]);
        }

        try {
            $result = static::applyFileStrict($path, $companyId);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PRODUCTOS_IMPORT_STRICT_ERROR', [
                'company_id' => $companyId,
                'path' => $path,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => 'No se aplico ningun cambio. Error: ' . $e->getMessage(),
            ]);
        }

        $created = (int) ($result['created'] ?? 0);
        $updated = (int) ($result['updated'] ?? 0);
        $skipped = (int) ($result['skipped'] ?? 0);

        \Filament\Notifications\Notification::make()
            ->title('Productos importados')
            ->body("Creados: {$created}. Actualizados: {$updated}. Omitidos: {$skipped}.")
            ->success()
            ->send();
    }

    protected static function modalErrorResult(string $message): array
    {
        $safe = static::modalEscape($message);

        return [
            'ok' => false,
            'hash' => '',
            'html' => '<div class="rounded-lg border border-danger-300 bg-danger-50 p-3 text-sm text-danger-700">'
                . '<div class="font-semibold">No se pudo validar el archivo</div>'
                . '<div class="mt-1">' . $safe . '</div>'
                . '</div>',
            'result' => [
                'errors' => 1,
                'validated' => 0,
                'log_rows' => [],
            ],
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

        $statusClass = $isClean
            ? 'border-success-300 bg-success-50 text-success-800'
            : 'border-danger-300 bg-danger-50 text-danger-800';

        $statusTitle = $isClean
            ? 'Validacion limpia'
            : 'Validacion con errores';

        $html = '<div class="rounded-lg border ' . $statusClass . ' p-3 text-sm">';
        $html .= '<div class="font-semibold">' . $statusTitle . '</div>';
        $html .= '<div class="mt-2 grid grid-cols-2 gap-2">';
        $html .= '<div>Total filas: <strong>' . $total . '</strong></div>';
        $html .= '<div>Validadas: <strong>' . $validated . '</strong></div>';
        $html .= '<div>Se crearan: <strong>' . $wouldCreate . '</strong></div>';
        $html .= '<div>Se actualizaran: <strong>' . $wouldUpdate . '</strong></div>';
        $html .= '<div>Omitidas: <strong>' . $skipped . '</strong></div>';
        $html .= '<div>Errores: <strong>' . $errors . '</strong></div>';
        $html .= '</div>';

        $errorRows = array_values(array_filter(
            $result['log_rows'] ?? [],
            fn (array $row): bool => str_contains((string) ($row['resultado'] ?? ''), 'ERROR')
                || str_contains((string) ($row['resultado'] ?? ''), 'BLOQUEADA')
                || str_contains((string) ($row['resultado'] ?? ''), 'NO_APROBADA')
        ));

        if (! empty($errorRows)) {
            $html .= '<div class="mt-3 font-semibold">Errores principales</div>';
            $html .= '<ul class="mt-1 list-disc space-y-1 pl-5">';

            foreach (array_slice($errorRows, 0, 8) as $row) {
                $line = static::modalEscape((string) ($row['linea'] ?? ''));
                $reference = static::modalEscape((string) ($row['referencia_interna'] ?? ''));
                $message = static::modalEscape((string) ($row['mensaje'] ?? ''));
                $resultLabel = static::modalEscape((string) ($row['resultado'] ?? ''));

                $html .= '<li>'
                    . ($line !== '0' && $line !== '' ? 'Linea ' . $line . ': ' : '')
                    . ($reference !== '' ? '[' . $reference . '] ' : '')
                    . $resultLabel . ' - ' . $message
                    . '</li>';
            }

            if (count($errorRows) > 8) {
                $html .= '<li>Hay mas errores. Corrige el archivo y vuelve a subirlo.</li>';
            }

            $html .= '</ul>';
        }

        if ($isClean) {
            $html .= '<div class="mt-3 rounded-md bg-white/70 p-2">'
                . 'El archivo esta listo. Se aplicara en modo todo-o-nada: si aparece un error al procesar, no se actualizara ningun producto.'
                . '</div>';
        } else {
            $html .= '<div class="mt-3 rounded-md bg-white/70 p-2">'
                . 'Corrige el archivo y vuelve a subirlo. No se aplicaran cambios mientras existan errores.'
                . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    protected static function modalEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }


    public static function applyFileStrict(string $path, int $companyId): array
    {
        if (! file_exists($path)) {
            throw new \RuntimeException('No se encontro el archivo a importar.');
        }

        $rows = static::readRows($path);

        if (count($rows) < 2) {
            throw new \RuntimeException('El archivo no tiene lineas de productos.');
        }

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

                if (static::isEmptyProductRow($data)) {
                    $result['skipped']++;
                    $result['log_rows'][] = static::logRow($lineNumber, 'omitir', 'OMITIDA', '', '', '', '', 'Linea vacia.');
                    continue;
                }

                $action = static::normalizeAction($data['accion'] ?? 'crear_o_actualizar');

                if ($action === 'omitir') {
                    $result['skipped']++;
                    $result['log_rows'][] = static::logRow(
                        $lineNumber,
                        $action,
                        'OMITIDA',
                        $data['referencia_interna'] ?? '',
                        $data['sku'] ?? '',
                        $data['codigo_barras'] ?? '',
                        '',
                        'Accion omitir.'
                    );
                    continue;
                }

                $outcome = static::processRow($data, $action, $companyId, true);

                if (($outcome['status'] ?? '') === 'CREADO') {
                    $result['created']++;
                } elseif (($outcome['status'] ?? '') === 'ACTUALIZADO') {
                    $result['updated']++;
                }

                $result['log_rows'][] = static::logRow(
                    $lineNumber,
                    $action,
                    (string) ($outcome['status'] ?? 'OK'),
                    $data['referencia_interna'] ?? '',
                    $data['sku'] ?? '',
                    $data['codigo_barras'] ?? '',
                    (string) ($outcome['product_id'] ?? ''),
                    (string) ($outcome['message'] ?? '')
                );
            }
        });

        return $result;
    }

    public static function processFile(string $path, int $companyId, bool $apply = false): array
    {
        if (! file_exists($path)) {
            throw new \RuntimeException('No se encontró el archivo a importar: ' . $path);
        }

        $rows = static::readRows($path);

        if (count($rows) < 2) {
            return static::emptyResult($companyId, $apply, 'El archivo no tiene líneas de productos.');
        }

        $headers = static::normalizeHeaderRow($rows[0]);

        $result = [
            'company_id' => $companyId,
            'apply' => $apply,
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

            if (static::isEmptyProductRow($data)) {
                $result['skipped']++;
                $result['log_rows'][] = static::logRow($lineNumber, 'omitir', 'OMITIDA', '', '', '', '', 'Línea vacía.');
                continue;
            }

            $action = static::normalizeAction($data['accion'] ?? 'crear_o_actualizar');

            if ($action === 'omitir') {
                $result['skipped']++;
                $result['log_rows'][] = static::logRow(
                    $lineNumber,
                    $action,
                    'OMITIDA',
                    $data['referencia_interna'] ?? '',
                    $data['sku'] ?? '',
                    $data['codigo_barras'] ?? '',
                    '',
                    'Acción omitir.'
                );
                continue;
            }

            try {
                DB::transaction(function () use (&$result, $lineNumber, $data, $action, $companyId, $apply): void {
                    $outcome = static::processRow($data, $action, $companyId, $apply);

                    if (($outcome['status'] ?? '') === 'CREADO') {
                        $result['created']++;
                    } elseif (($outcome['status'] ?? '') === 'ACTUALIZADO') {
                        $result['updated']++;
                    } elseif (str_starts_with((string) ($outcome['status'] ?? ''), 'VALIDADO')) {
                        $result['validated']++;
                    }

                    $result['log_rows'][] = static::logRow(
                        $lineNumber,
                        $action,
                        (string) ($outcome['status'] ?? 'OK'),
                        $data['referencia_interna'] ?? '',
                        $data['sku'] ?? '',
                        $data['codigo_barras'] ?? '',
                        (string) ($outcome['product_id'] ?? ''),
                        (string) ($outcome['message'] ?? '')
                    );
                });
            } catch (\Throwable $e) {
                $result['errors']++;
                $result['log_rows'][] = static::logRow(
                    $lineNumber,
                    $action,
                    'ERROR',
                    $data['referencia_interna'] ?? '',
                    $data['sku'] ?? '',
                    $data['codigo_barras'] ?? '',
                    '',
                    $e->getMessage()
                );
            }
        }

        return $result;
    }

    protected static function processRow(array $data, string $action, int $companyId, bool $apply): array
    {
        $reference = trim((string) ($data['referencia_interna'] ?? ''));
        $sku = trim((string) ($data['sku'] ?? ''));
        $barcode = trim((string) ($data['codigo_barras'] ?? ''));
        $name = trim((string) ($data['nombre'] ?? ''));

        $existing = static::findExistingProduct($companyId, $reference, $sku, $barcode, $data['id_solo_lectura'] ?? null);

        if ($action === 'crear' && $existing) {
            throw new \RuntimeException('Ya existe producto con esa referencia/SKU/código.');
        }

        if ($action === 'actualizar' && ! $existing) {
            throw new \RuntimeException('No se encontró producto existente para actualizar.');
        }

        if (! $existing && $name === '') {
            throw new \RuntimeException('Para crear un producto, el nombre es obligatorio.');
        }

        if (! $existing && $reference === '' && $sku === '' && $barcode === '') {
            throw new \RuntimeException('Para crear un producto se requiere referencia_interna, sku o codigo_barras.');
        }

        $payload = static::buildProductPayload($data, $companyId, $existing);

        if (! $existing) {
            $payload['company_id'] = $companyId;
            $payload['created_at'] = now();
            $payload['updated_at'] = now();

            if (! $apply) {
                return [
                    'status' => 'VALIDADO_CREAR',
                    'product_id' => '',
                    'message' => 'Producto nuevo válido. No aplicado.',
                ];
            static::sanitizeNullableUniqueProductPayload($payload);
            }

            $productId = DB::table('products')->insertGetId($payload);

            return [
                'status' => 'CREADO',
                'product_id' => $productId,
                'message' => 'Producto creado.',
            ];
        }

        $payload['updated_at'] = now();

        if (! $apply) {
            return [
                'status' => 'VALIDADO_ACTUALIZAR',
                'product_id' => (int) $existing->id,
                'message' => 'Producto existente válido. No aplicado.',
            ];
        static::sanitizeNullableUniqueProductPayload($payload);
        }

        DB::table('products')
            ->where('id', $existing->id)
            ->where('company_id', $companyId)
            ->update($payload);

        return [
            'status' => 'ACTUALIZADO',
            'product_id' => (int) $existing->id,
            'message' => 'Producto actualizado.',
        ];
    }

    protected static function buildProductPayload(array $data, int $companyId, ?object $existing): array
    {
        $payload = [];

        static::setText($payload, 'internal_reference', $data, 'referencia_interna');
        if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'sku') && array_key_exists('sku', $data)) {
            $payload['sku'] = static::normalizeNullableUniqueText($data['sku']);
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'barcode') && array_key_exists('codigo_barras', $data)) {
            $payload['barcode'] = static::normalizeNullableUniqueText($data['codigo_barras']);
        }
        static::setText($payload, 'name', $data, 'nombre');

        $categoryId = static::categoryId(
            $companyId,
            trim((string) ($data['categoria_codigo'] ?? '')),
            trim((string) ($data['categoria_nombre'] ?? ''))
        );

        if ($categoryId !== null && Schema::hasColumn('products', 'product_category_id')) {
            $payload['product_category_id'] = $categoryId;
        }

        if (Schema::hasColumn('products', 'product_type') && array_key_exists('tipo_producto', $data)) {
            $payload['product_type'] = static::parseProductType($data['tipo_producto']);
        }

        if (Schema::hasColumn('products', 'tracking') && array_key_exists('tracking', $data)) {
            $payload['tracking'] = static::parseTracking($data['tracking']);
        }

        if (Schema::hasColumn('products', 'costing_method') && array_key_exists('metodo_costeo', $data)) {
            $payload['costing_method'] = static::parseCostingMethod($data['metodo_costeo']);
        }

        static::setDecimal($payload, 'sale_price', $data, 'precio_venta');
        static::setDecimal($payload, 'average_cost_without_tax', $data, 'costo_promedio_sin_iva');
        static::setDecimal($payload, 'standard_cost', $data, 'costo_estandar');
        static::setDecimal($payload, 'last_purchase_cost', $data, 'ultimo_costo_compra');

        static::setBool($payload, 'can_be_sold', $data, 'se_puede_vender');
        static::setBool($payload, 'can_be_purchased', $data, 'se_puede_comprar');
        static::setBool($payload, 'is_active', $data, 'activo');
        static::setBool($payload, 'available_in_pos', $data, 'disponible_pdv');
        static::setBool($payload, 'is_pos_favorite', $data, 'favorito_pdv');
        static::setBool($payload, 'allow_out_of_stock_sales', $data, 'permitir_venta_sin_stock');

        static::setText($payload, 'brand', $data, 'marca');
        static::setText($payload, 'model', $data, 'modelo');
        static::setText($payload, 'material', $data, 'material');
        static::setText($payload, 'color', $data, 'color');
        static::setText($payload, 'product_line', $data, 'linea_producto');
        static::setText($payload, 'sat_product_service_code', $data, 'sat_clave_producto');
        static::setText($payload, 'sat_unit_code', $data, 'sat_clave_unidad');
        static::setText($payload, 'sat_tax_object_code', $data, 'objeto_impuesto_sat');
        static::setText($payload, 'sale_description', $data, 'descripcion_venta');
        static::setText($payload, 'purchase_description', $data, 'descripcion_compra');

        if (! $existing) {
            $defaults = [
                'product_type' => 'stockable',
                'tracking' => 'none',
                'costing_method' => 'average',
                'can_be_sold' => true,
                'can_be_purchased' => true,
                'is_active' => true,
                'available_in_pos' => true,
                'is_pos_favorite' => false,
                'allow_out_of_stock_sales' => false,
            ];

            foreach ($defaults as $column => $value) {
                if (Schema::hasColumn('products', $column) && ! array_key_exists($column, $payload)) {
                    $payload[$column] = $value;
                }
            }
        }

        return array_filter(
            $payload,
            fn ($value): bool => $value !== '__BEXIA_SKIP__'
        );
    }

    protected static function findExistingProduct(int $companyId, string $reference, string $sku, string $barcode, mixed $readOnlyId = null): ?object
    {
        if (! Schema::hasTable('products')) {
            throw new \RuntimeException('No existe tabla products.');
        }

        foreach ([
            ['internal_reference', $reference],
            ['sku', $sku],
            ['barcode', $barcode],
        ] as [$column, $value]) {
            if ($value === '' || ! Schema::hasColumn('products', $column)) {
                continue;
            }

            $product = DB::table('products')
                ->where('company_id', $companyId)
                ->where($column, $value)
                ->first();

            if ($product) {
                return $product;
            }
        }

        $id = static::parseInteger($readOnlyId);

        if ($id !== null) {
            return DB::table('products')
                ->where('company_id', $companyId)
                ->where('id', $id)
                ->first();
        }

        return null;
    }

    protected static function categoryId(int $companyId, string $code, string $name): ?int
    {
        if (! Schema::hasTable('product_categories')) {
            return null;
        }

        if ($code !== '' && Schema::hasColumn('product_categories', 'code')) {
            $category = DB::table('product_categories')
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->first();

            if ($category) {
                return (int) $category->id;
            }
        }

        if ($name !== '') {
            $category = DB::table('product_categories')
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();

            if ($category) {
                return (int) $category->id;
            }
        }

        if ($name === '') {
            return null;
        }

        $newCode = $code !== '' ? $code : static::categoryCodeFromName($name);

        return DB::table('product_categories')->insertGetId([
            'company_id' => $companyId,
            'code' => $newCode,
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected static function categoryCodeFromName(string $name): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name) ?: 'CAT', 0, 10));
        $base = $base !== '' ? $base : 'CAT';
        $candidate = 'IMP-' . $base;
        $suffix = 1;

        while (DB::table('product_categories')->where('code', $candidate)->exists()) {
            $suffix++;
            $candidate = 'IMP-' . $base . '-' . $suffix;
        }

        return $candidate;
    }

    protected static function setText(array &$payload, string $column, array $data, string $key): void
    {
        if (! Schema::hasColumn('products', $column) || ! array_key_exists($key, $data)) {
            return;
        }

        $payload[$column] = trim((string) $data[$key]);
    }

    protected static function setDecimal(array &$payload, string $column, array $data, string $key): void
    {
        if (! Schema::hasColumn('products', $column) || ! array_key_exists($key, $data)) {
            return;
        }

        $value = trim((string) $data[$key]);

        if ($value === '') {
            return;
        }

        $payload[$column] = static::parseDecimal($value);
    }

    protected static function setBool(array &$payload, string $column, array $data, string $key): void
    {
        if (! Schema::hasColumn('products', $column) || ! array_key_exists($key, $data)) {
            return;
        }

        $value = trim((string) $data[$key]);

        if ($value === '') {
            return;
        }

        $payload[$column] = static::parseBool($value);
    }

    protected static function parseProductType(mixed $value): string
    {
        $value = static::normalizedText($value);

        return match ($value) {
            'almacenable', 'producto almacenable', 'stockable' => 'stockable',
            'servicio', 'service' => 'service',
            'consumible', 'consumable' => 'consumable',
            default => 'stockable',
        };
    }

    protected static function parseTracking(mixed $value): string
    {
        $value = static::normalizedText($value);

        return match ($value) {
            'series', 'serie', 'serial', 'seriales' => 'serial',
            'lotes', 'lote', 'lot', 'lots' => 'lot',
            'sin seguimiento', 'ninguno', 'none', '' => 'none',
            default => 'none',
        };
    }


    protected static function normalizeNullableUniqueText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected static function sanitizeNullableUniqueProductPayload(array &$payload): void
    {
        foreach (['sku', 'barcode'] as $column) {
            if (array_key_exists($column, $payload)) {
                $payload[$column] = static::normalizeNullableUniqueText($payload[$column]);
            }
        }
    }

    protected static function parseCostingMethod(mixed $value): string
    {
        $value = static::normalizedText($value);

        return match ($value) {
            'promedio', 'average', '' => 'average',
            'fifo' => 'fifo',
            'estandar', 'estándar', 'standard' => 'standard',
            default => 'average',
        };
    }

    protected static function parseBool(mixed $value): bool
    {
        $value = static::normalizedText($value);

        return in_array($value, ['1', 'si', 'sí', 'true', 'verdadero', 'yes', 'x'], true);
    }

    protected static function parseDecimal(mixed $value): float
    {
        $value = trim((string) $value);
        $value = str_replace(['$', 'MXN', 'mxn', ' '], '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        return (float) $value;
    }

    protected static function parseInteger(mixed $value): ?int
    {
        $value = trim((string) $value);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    protected static function normalizeAction(mixed $value): string
    {
        $value = static::normalizedText($value);

        return match ($value) {
            'crear' => 'crear',
            'actualizar' => 'actualizar',
            'crear o actualizar', 'crear_o_actualizar', 'crear/actualizar', 'upsert', '' => 'crear_o_actualizar',
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

        $xml = simplexml_load_string($sheetXml);

        if (! $xml) {
            throw new \RuntimeException('No se pudo leer XML de la hoja XLSX.');
        }

        $xml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];

        foreach ($xml->sheetData->row as $rowNode) {
            $row = [];

            foreach ($rowNode->c as $cell) {
                $ref = (string) $cell['r'];
                $columnIndex = static::columnIndexFromReference($ref);
                $row[$columnIndex - 1] = static::xlsxCellValue($cell, $sharedStrings);
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

        $xml = simplexml_load_string($xmlString);

        if (! $xml) {
            return [];
        }

        $strings = [];

        foreach ($xml->si as $si) {
            $text = '';

            if (isset($si->t)) {
                $text .= (string) $si->t;
            }

            if (isset($si->r)) {
                foreach ($si->r as $run) {
                    $text .= (string) $run->t;
                }
            }

            $strings[] = $text;
        }

        return $strings;
    }

    protected static function xlsxCellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 's') {
            $index = (int) ($cell->v ?? 0);
            return (string) ($sharedStrings[$index] ?? '');
        }

        if ($type === 'inlineStr') {
            return (string) ($cell->is->t ?? '');
        }

        if ($type === 'b') {
            return ((string) ($cell->v ?? '0')) === '1' ? '1' : '0';
        }

        return (string) ($cell->v ?? '');
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
            'codigo_de_barras' => 'codigo_barras',
            'codigo_barras' => 'codigo_barras',
            'tipo_seguimiento' => 'tracking',
            'tipo_de_seguimiento' => 'tracking',
            'seguimiento' => 'tracking',
            'rastreo' => 'tracking',
            'referencia' => 'referencia_interna',
            'ref' => 'referencia_interna',
            'producto' => 'nombre',
            'categoria' => 'categoria_nombre',
            'precio' => 'precio_venta',
            'costo_promedio' => 'costo_promedio_sin_iva',
            'activo_pdv' => 'disponible_pdv',
            'favorito' => 'favorito_pdv',
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

    protected static function isEmptyProductRow(array $data): bool
    {
        foreach (['referencia_interna', 'sku', 'codigo_barras', 'nombre'] as $key) {
            if (trim((string) ($data[$key] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    protected static function emptyResult(int $companyId, bool $apply, string $message): array
    {
        return [
            'company_id' => $companyId,
            'apply' => $apply,
            'created_at' => now()->toDateTimeString(),
            'total_rows' => 0,
            'created' => 0,
            'updated' => 0,
            'validated' => 0,
            'skipped' => 0,
            'errors' => 1,
            'log_rows' => [
                static::logRow(0, '', 'ERROR', '', '', '', '', $message),
            ],
        ];
    }

    protected static function logRow(int $line, string $action, string $result, string $reference, string $sku, string $barcode, string $productId, string $message): array
    {
        return [
            'linea' => $line,
            'accion' => $action,
            'resultado' => $result,
            'referencia_interna' => $reference,
            'sku' => $sku,
            'codigo_barras' => $barcode,
            'producto_id' => $productId,
            'mensaje' => $message,
        ];
    }

    protected static function downloadLog(array $result, bool $apply): StreamedResponse
    {
        $filename = ($apply ? 'import_productos_aplicado_' : 'validacion_productos_')
            . 'empresa_' . ($result['company_id'] ?? 'x')
            . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(
            function () use ($result): void {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");

                fputcsv($out, ['resumen', 'valor'], ';');
                fputcsv($out, ['empresa_id', $result['company_id'] ?? ''], ';');
                fputcsv($out, ['aplicado', ($result['apply'] ?? false) ? 'sí' : 'no'], ';');
                fputcsv($out, ['total_filas', $result['total_rows'] ?? 0], ';');
                fputcsv($out, ['validadas', $result['validated'] ?? 0], ';');
                fputcsv($out, ['creadas', $result['created'] ?? 0], ';');
                fputcsv($out, ['actualizadas', $result['updated'] ?? 0], ';');
                fputcsv($out, ['omitidas', $result['skipped'] ?? 0], ';');
                fputcsv($out, ['errores', $result['errors'] ?? 0], ';');
                fputcsv($out, [], ';');

                $headers = ['linea', 'accion', 'resultado', 'referencia_interna', 'sku', 'codigo_barras', 'producto_id', 'mensaje'];
                fputcsv($out, $headers, ';');

                foreach ($result['log_rows'] ?? [] as $row) {
                    fputcsv($out, array_map(fn ($header) => $row[$header] ?? '', $headers), ';');
                }

                fclose($out);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
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

        $candidates = [
            $file,
            storage_path('app/' . $file),
            storage_path('app/private/' . $file),
            storage_path('app/public/' . $file),
        ];

        foreach ($candidates as $candidate) {
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
            // Continuar con ruta.
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
