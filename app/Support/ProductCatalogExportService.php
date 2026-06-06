<?php

namespace App\Support;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductCatalogExportService
{
    public static function downloadProductsXlsx(): StreamedResponse
    {
        $companyId = static::currentCompanyId();
        $headers = static::headers();
        $rows = static::productRows($companyId);

        return static::downloadXlsx(
            'productos_' . static::safeSlug(static::companyLabel($companyId)) . '_' . now()->format('Ymd_His') . '.xlsx',
            'Productos',
            $headers,
            $rows
        );
    }

    public static function downloadTemplateXlsx(): StreamedResponse
    {
        $companyId = static::currentCompanyId();
        $headers = static::headers();

        $rows = [
            [
                'accion' => 'crear_o_actualizar',
                'referencia_interna' => 'EJEMPLO-001',
                'sku' => '7500000000000',
                'codigo_barras' => '7500000000000',
                'nombre' => 'Producto ejemplo',
                'categoria_codigo' => '',
                'categoria_nombre' => 'General',
                'tipo_producto' => 'almacenable',
                'tipo_seguimiento' => 'sin seguimiento',
                'metodo_costeo' => 'promedio',
                'precio_venta' => '0.0000',
                'costo_promedio_sin_iva' => '0.0000',
                'costo_estandar' => '0.0000',
                'ultimo_costo_compra' => '0.0000',
                'se_puede_vender' => 'sí',
                'se_puede_comprar' => 'sí',
                'activo' => 'sí',
                'disponible_pdv' => 'sí',
                'favorito_pdv' => 'no',
                'permitir_venta_sin_stock' => 'no',
                'marca' => '',
                'modelo' => '',
                'material' => '',
                'color' => '',
                'linea_producto' => '',
                'sat_clave_producto' => '',
                'sat_clave_unidad' => '',
                'objeto_impuesto_sat' => '',
                'descripcion_venta' => '',
                'descripcion_compra' => '',
                'stock_actual_solo_lectura' => '',
                'id_solo_lectura' => '',
            ],
        ];

        return static::downloadXlsx(
            'plantilla_productos_carga_masiva_' . static::safeSlug(static::companyLabel($companyId)) . '_' . now()->format('Ymd_His') . '.xlsx',
            'Plantilla',
            $headers,
            $rows
        );
    }

    public static function downloadProductsPdf(): Response
    {
        @set_time_limit(180);
        @ini_set('memory_limit', '512M');

        $companyId = static::currentCompanyId();
        $rows = static::productRows($companyId);

        if (! app()->bound('dompdf.wrapper')) {
            abort(500, 'No hay motor PDF instalado (barryvdh/laravel-dompdf).');
        }

        try {
            $pdf = app('dompdf.wrapper');

            $pdf->loadHTML(static::pdfHtml($companyId, $rows))
                ->setPaper('letter', 'landscape');

            return $pdf->download(
                'productos_' . static::safeSlug(static::companyLabel($companyId)) . '_' . now()->format('Ymd_His') . '.pdf'
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PRODUCTOS_PDF_EXPORT_ERROR', [
                'company_id' => $companyId,
                'rows' => count($rows),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            abort(500, 'No se pudo generar el PDF de productos. Usa Exportar Excel para el catálogo completo o revisa el log del servidor.');
        }
    }

    public static function productRows(?int $companyId = null): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        $productColumns = Schema::getColumnListing('products');

        $query = DB::table('products as p')
            ->leftJoin('product_categories as c', 'c.id', '=', 'p.product_category_id');

        if ($companyId !== null && in_array('company_id', $productColumns, true)) {
            $query->where('p.company_id', $companyId);
        }

        $products = $query
            ->select([
                'p.id',
                'p.company_id',
                'p.internal_reference',
                'p.sku',
                'p.barcode',
                'p.name',
                'p.product_category_id',
                'c.code as category_code',
                'c.name as category_name',
                'p.product_type',
                'p.tracking',
                'p.costing_method',
                'p.sale_price',
                'p.average_cost_without_tax',
                'p.standard_cost',
                'p.last_purchase_cost',
                'p.can_be_sold',
                'p.can_be_purchased',
                'p.is_active',
                'p.available_in_pos',
                'p.is_pos_favorite',
                'p.allow_out_of_stock_sales',
                'p.brand',
                'p.model',
                'p.material',
                'p.color',
                'p.product_line',
                'p.sat_product_service_code',
                'p.sat_unit_code',
                'p.sat_tax_object_code',
                'p.sale_description',
                'p.purchase_description',
            ])
            ->orderBy('p.internal_reference')
            ->orderBy('p.name')
            ->get();

        $stockByProduct = static::stockByProduct($companyId);

        return $products
            ->map(function ($product) use ($stockByProduct): array {
                $productId = (int) $product->id;

                return [
                    'accion' => 'actualizar',
                    'referencia_interna' => (string) ($product->internal_reference ?? ''),
                    'sku' => (string) ($product->sku ?? ''),
                    'codigo_barras' => (string) ($product->barcode ?? ''),
                    'nombre' => (string) ($product->name ?? ''),
                    'categoria_codigo' => (string) ($product->category_code ?? ''),
                    'categoria_nombre' => (string) ($product->category_name ?? ''),
                    'tipo_producto' => static::productTypeLabel($product->product_type ?? ''),
                    'tipo_seguimiento' => static::trackingLabel($product->tracking ?? 'none'),
                    'metodo_costeo' => static::costingMethodLabel($product->costing_method ?? ''),
                    'precio_venta' => static::decimal($product->sale_price ?? 0),
                    'costo_promedio_sin_iva' => static::decimal($product->average_cost_without_tax ?? 0),
                    'costo_estandar' => static::decimal($product->standard_cost ?? 0),
                    'ultimo_costo_compra' => static::decimal($product->last_purchase_cost ?? 0),
                    'se_puede_vender' => static::boolValue($product->can_be_sold ?? true),
                    'se_puede_comprar' => static::boolValue($product->can_be_purchased ?? true),
                    'activo' => static::boolValue($product->is_active ?? true),
                    'disponible_pdv' => static::boolValue($product->available_in_pos ?? false),
                    'favorito_pdv' => static::boolValue($product->is_pos_favorite ?? false),
                    'permitir_venta_sin_stock' => static::boolValue($product->allow_out_of_stock_sales ?? false),
                    'marca' => (string) ($product->brand ?? ''),
                    'modelo' => (string) ($product->model ?? ''),
                    'material' => (string) ($product->material ?? ''),
                    'color' => (string) ($product->color ?? ''),
                    'linea_producto' => (string) ($product->product_line ?? ''),
                    'sat_clave_producto' => (string) ($product->sat_product_service_code ?? ''),
                    'sat_clave_unidad' => (string) ($product->sat_unit_code ?? ''),
                    'objeto_impuesto_sat' => (string) ($product->sat_tax_object_code ?? ''),
                    'descripcion_venta' => (string) ($product->sale_description ?? ''),
                    'descripcion_compra' => (string) ($product->purchase_description ?? ''),
                    'stock_actual_solo_lectura' => static::decimal($stockByProduct[$productId] ?? 0),
                    'id_solo_lectura' => (string) $productId,
                ];
            })
            ->all();
    }

    public static function headers(): array
    {
        return [
            'accion',
            'referencia_interna',
            'sku',
            'codigo_barras',
            'nombre',
            'categoria_codigo',
            'categoria_nombre',
            'tipo_producto',
            'tipo_seguimiento',
            'metodo_costeo',
            'precio_venta',
            'costo_promedio_sin_iva',
            'costo_estandar',
            'ultimo_costo_compra',
            'se_puede_vender',
            'se_puede_comprar',
            'activo',
            'disponible_pdv',
            'favorito_pdv',
            'permitir_venta_sin_stock',
            'marca',
            'modelo',
            'material',
            'color',
            'linea_producto',
            'sat_clave_producto',
            'sat_clave_unidad',
            'objeto_impuesto_sat',
            'descripcion_venta',
            'descripcion_compra',
            'stock_actual_solo_lectura',
            'id_solo_lectura',
        ];
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

    protected static function stockByProduct(?int $companyId): array
    {
        if (! Schema::hasTable('stock_quants')) {
            return [];
        }

        $query = DB::table('stock_quants')
            ->select('product_id', DB::raw('SUM(quantity - COALESCE(reserved_quantity, 0)) as qty'))
            ->whereNotNull('product_id')
            ->groupBy('product_id');

        if ($companyId !== null && Schema::hasColumn('stock_quants', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->product_id => (float) $row->qty])
            ->all();
    }

    protected static function downloadXlsx(string $filename, string $sheetName, array $headers, array $rows): StreamedResponse
    {
        if (! class_exists(\ZipArchive::class)) {
            return static::downloadCsv(
                str_replace('.xlsx', '.csv', $filename),
                $headers,
                $rows
            );
        }

        $xlsx = static::buildXlsx($sheetName, $headers, $rows);

        return response()->streamDownload(
            function () use ($xlsx): void {
                echo $xlsx;
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0, no-cache, must-revalidate',
            ]
        );
    }

    protected static function downloadCsv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($headers, $rows): void {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $headers, ';');

                foreach ($rows as $row) {
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

    protected static function buildXlsx(string $sheetName, array $headers, array $rows): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bexia_products_xlsx_');

        if ($tmp === false) {
            throw new \RuntimeException('No se pudo crear archivo temporal XLSX.');
        }

        $zip = new \ZipArchive();

        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo abrir archivo temporal XLSX.');
        }

        $zip->addFromString('[Content_Types].xml', static::contentTypesXml());
        $zip->addFromString('_rels/.rels', static::rootRelsXml());
        $zip->addFromString('xl/workbook.xml', static::workbookXml($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', static::workbookRelsXml());
        $zip->addFromString('xl/styles.xml', static::stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', static::sheetXml($headers, $rows));
        $zip->close();

        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        if ($bytes === false) {
            throw new \RuntimeException('No se pudo leer XLSX temporal.');
        }

        return $bytes;
    }

    protected static function sheetXml(array $headers, array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="15"/>';
        $xml .= '<cols>';

        for ($i = 1; $i <= count($headers); $i++) {
            $width = $i <= 7 ? 22 : 18;
            $xml .= '<col min="' . $i . '" max="' . $i . '" width="' . $width . '" customWidth="1"/>';
        }

        $xml .= '</cols>';
        $xml .= '<sheetData>';

        $xml .= '<row r="1">';

        foreach (array_values($headers) as $index => $header) {
            $cell = static::cellReference($index + 1, 1);
            $xml .= '<c r="' . $cell . '" t="inlineStr" s="1"><is><t>' . static::xml($header) . '</t></is></c>';
        }

        $xml .= '</row>';

        $rowNumber = 2;

        foreach ($rows as $row) {
            $xml .= '<row r="' . $rowNumber . '">';

            foreach (array_values($headers) as $index => $header) {
                $cell = static::cellReference($index + 1, $rowNumber);
                $value = $row[$header] ?? '';

                if (static::isNumericCell($header, $value)) {
                    $xml .= '<c r="' . $cell . '"><v>' . static::numeric($value) . '</v></c>';
                } else {
                    $xml .= '<c r="' . $cell . '" t="inlineStr"><is><t>' . static::xml((string) $value) . '</t></is></c>';
                }
            }

            $xml .= '</row>';
            $rowNumber++;
        }

        $lastColumn = static::columnLetter(count($headers));
        $xml .= '</sheetData>';
        $xml .= '<autoFilter ref="A1:' . $lastColumn . max(1, count($rows) + 1) . '"/>';
        $xml .= '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>';
        $xml .= '</worksheet>';

        return $xml;
    }

    protected static function pdfHtml(?int $companyId, array $rows): string
    {
        $company = e(static::companyLabel($companyId));
        $createdAt = e(now()->format('Y-m-d H:i:s'));
        $totalRows = count($rows);

        $html = '<!doctype html><html><head><meta charset="utf-8">';
        $html .= '<style>
            @page{margin:18px 16px}
            body{font-family:DejaVu Sans,sans-serif;font-size:7px;color:#111827}
            h1{font-size:15px;margin:0 0 3px}
            .meta{font-size:8px;color:#4b5563;margin-bottom:8px}
            table{width:100%;border-collapse:collapse;table-layout:fixed}
            th{background:#eff6ff;border:1px solid #d1d5db;padding:3px;text-align:left;font-size:6.5px;line-height:1.1}
            td{border:1px solid #e5e7eb;padding:2px;font-size:6.5px;line-height:1.1;vertical-align:top;word-wrap:break-word}
            .num{text-align:right}
            .w-ref{width:8%}
            .w-prod{width:25%}
            .w-cat{width:13%}
            .w-small{width:8%}
            .w-num{width:7%}
            .w-sku{width:12%}
            .footer{font-size:7px;color:#6b7280;margin-top:6px}
        </style>';
        $html .= '</head><body>';
        $html .= '<h1>Listado de productos</h1>';
        $html .= '<div class="meta">Empresa: ' . $company . ' · Generado: ' . $createdAt . ' · Registros: ' . $totalRows . '</div>';
        $html .= '<table><thead><tr>';
        $html .= '<th class="w-ref">Ref.</th>';
        $html .= '<th class="w-prod">Producto</th>';
        $html .= '<th class="w-cat">Categoría</th>';
        $html .= '<th class="w-small">Tipo</th>';
        $html .= '<th class="w-small">Tipo seguimiento</th>';
        $html .= '<th class="w-num">Stock</th>';
        $html .= '<th class="w-num">Precio</th>';
        $html .= '<th class="w-num">Costo</th>';
        $html .= '<th class="w-sku">SKU / Código</th>';
        $html .= '<th class="w-small">Activo</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<td>' . e($row['referencia_interna'] ?? '') . '</td>';
            $html .= '<td>' . e($row['nombre'] ?? '') . '</td>';
            $html .= '<td>' . e($row['categoria_nombre'] ?? '') . '</td>';
            $html .= '<td>' . e($row['tipo_producto'] ?? '') . '</td>';
            $html .= '<td>' . e($row['tipo_seguimiento'] ?? $row['tracking'] ?? '') . '</td>';
            $html .= '<td class="num">' . e($row['stock_actual_solo_lectura'] ?? '') . '</td>';
            $html .= '<td class="num">' . e($row['precio_venta'] ?? '') . '</td>';
            $html .= '<td class="num">' . e($row['costo_promedio_sin_iva'] ?? '') . '</td>';
            $html .= '<td>' . e(trim((string) ($row['sku'] ?? '') . ' ' . (string) ($row['codigo_barras'] ?? ''))) . '</td>';
            $html .= '<td>' . e(($row['activo'] ?? '') === 'sí' ? 'Sí' : 'No') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer">Para edición masiva usa Exportar Excel o Plantilla carga masiva. El PDF es solo consulta/impresión.</div>';
        $html .= '</body></html>';

        return $html;
    }

    protected static function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
    }

    protected static function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    protected static function workbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="' . static::xml(mb_substr($sheetName, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets>
</workbook>';
    }

    protected static function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    }

    protected static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>
<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>
<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>
<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>';
    }

    protected static function cellReference(int $column, int $row): string
    {
        return static::columnLetter($column) . $row;
    }

    protected static function columnLetter(int $column): string
    {
        $letter = '';

        while ($column > 0) {
            $mod = ($column - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $column = intdiv($column - $mod, 26);
        }

        return $letter;
    }

    protected static function isNumericCell(string $header, mixed $value): bool
    {
        if ($value === '' || $value === null) {
            return false;
        }

        return in_array($header, [
            'precio_venta',
            'costo_promedio_sin_iva',
            'costo_estandar',
            'ultimo_costo_compra',
            'stock_actual_solo_lectura',
        ], true) && is_numeric((string) $value);
    }

    protected static function numeric(mixed $value): string
    {
        return str_replace(',', '.', (string) $value);
    }

    protected static function xml(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected static function decimal(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 4, '.', '');
    }

    protected static function boolValue(mixed $value): string
    {
        return (bool) $value ? 'sí' : 'no';
    }

    protected static function productTypeLabel(mixed $value): string
    {
        return match ((string) $value) {
            'stockable' => 'almacenable',
            'service' => 'servicio',
            'consumable' => 'consumible',
            default => (string) ($value ?? ''),
        };
    }

    protected static function trackingLabel(mixed $value): string
    {
        return match ((string) $value) {
            'none', '' => 'sin seguimiento',
            'serial', 'series' => 'series',
            'lot', 'lots', 'lote' => 'lotes',
            default => (string) ($value ?? ''),
        };
    }

    protected static function costingMethodLabel(mixed $value): string
    {
        return match ((string) $value) {
            'average' => 'promedio',
            'fifo' => 'fifo',
            'standard' => 'estándar',
            default => (string) ($value ?? ''),
        };
    }

    protected static function companyLabel(?int $companyId): string
    {
        if ($companyId === null || ! Schema::hasTable('companies')) {
            return 'empresa';
        }

        $company = DB::table('companies')->where('id', $companyId)->first();

        return (string) ($company->name ?? ('empresa_' . $companyId));
    }

    protected static function safeSlug(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?? 'empresa');
        $value = trim($value, '_');

        return $value !== '' ? $value : 'empresa';
    }
}
