<?php

namespace App\Support;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductCategoryCatalogExportService
{
    public static function downloadCategoriesXlsx(): StreamedResponse
    {
        $companyId = static::currentCompanyId();

        return static::downloadXlsx(
            'categorias_productos_' . static::safeSlug(static::companyLabel($companyId)) . '_' . now()->format('Ymd_His') . '.xlsx',
            'Categorias',
            static::headers(),
            static::categoryRows($companyId)
        );
    }

    public static function downloadTemplateXlsx(): StreamedResponse
    {
        $companyId = static::currentCompanyId();

        return static::downloadXlsx(
            'plantilla_categorias_productos_' . static::safeSlug(static::companyLabel($companyId)) . '_' . now()->format('Ymd_His') . '.xlsx',
            'Plantilla',
            static::headers(),
            [
                [
                    'accion' => 'crear_o_actualizar',
                    'codigo' => 'CAT-EJEMPLO',
                    'nombre' => 'Categoría ejemplo',
                    'codigo_padre' => '',
                    'nombre_padre' => '',
                    'metodo_costeo' => 'heredar',
                    'activa' => 'sí',
                    'orden' => '10',
                    'descripcion' => 'Ejemplo de categoría para carga masiva',
                    'ruta_solo_lectura' => '',
                    'nivel_solo_lectura' => '',
                    'productos_solo_lectura' => '',
                    'id_solo_lectura' => '',
                ],
            ]
        );
    }

    public static function headers(): array
    {
        return [
            'accion',
            'codigo',
            'nombre',
            'codigo_padre',
            'nombre_padre',
            'metodo_costeo',
            'activa',
            'orden',
            'descripcion',
            'ruta_solo_lectura',
            'nivel_solo_lectura',
            'productos_solo_lectura',
            'id_solo_lectura',
        ];
    }

    public static function categoryRows(?int $companyId = null): array
    {
        if (! Schema::hasTable('product_categories')) {
            return [];
        }

        $query = DB::table('product_categories as c')
            ->leftJoin('product_categories as p', 'p.id', '=', 'c.parent_id');

        if ($companyId !== null && Schema::hasColumn('product_categories', 'company_id')) {
            $query->where('c.company_id', $companyId);
        }

        $rows = $query
            ->select([
                'c.id',
                'c.company_id',
                'c.parent_id',
                'c.code',
                'c.name',
                'c.description',
                'c.costing_method',
                'c.is_active',
                'c.full_path',
                'c.level',
                'c.sort_order',
                'p.code as parent_code',
                'p.name as parent_name',
            ])
            ->orderBy('c.full_path')
            ->orderBy('c.code')
            ->orderBy('c.name')
            ->get();

        $productCounts = static::productCounts($companyId);

        return $rows
            ->map(function ($row) use ($productCounts): array {
                $id = (int) $row->id;

                return [
                    'accion' => 'actualizar',
                    'codigo' => (string) ($row->code ?? ''),
                    'nombre' => (string) ($row->name ?? ''),
                    'codigo_padre' => (string) ($row->parent_code ?? ''),
                    'nombre_padre' => (string) ($row->parent_name ?? ''),
                    'metodo_costeo' => static::costingMethodLabel($row->costing_method ?? ''),
                    'activa' => static::boolValue($row->is_active ?? true),
                    'orden' => (string) ($row->sort_order ?? ''),
                    'descripcion' => (string) ($row->description ?? ''),
                    'ruta_solo_lectura' => (string) ($row->full_path ?? ''),
                    'nivel_solo_lectura' => (string) ($row->level ?? ''),
                    'productos_solo_lectura' => (string) ($productCounts[$id] ?? 0),
                    'id_solo_lectura' => (string) $id,
                ];
            })
            ->all();
    }

    protected static function productCounts(?int $companyId): array
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'product_category_id')) {
            return [];
        }

        $query = DB::table('products')
            ->select('product_category_id', DB::raw('COUNT(*) as qty'))
            ->whereNotNull('product_category_id')
            ->groupBy('product_category_id');

        if ($companyId !== null && Schema::hasColumn('products', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->product_category_id => (int) $row->qty])
            ->all();
    }

    protected static function downloadXlsx(string $filename, string $sheetName, array $headers, array $rows): StreamedResponse
    {
        if (! class_exists(\ZipArchive::class)) {
            return static::downloadCsv(str_replace('.xlsx', '.csv', $filename), $headers, $rows);
        }

        $xlsx = static::buildXlsx($sheetName, $headers, $rows);

        return response()->streamDownload(
            fn () => print($xlsx),
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
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    protected static function buildXlsx(string $sheetName, array $headers, array $rows): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bexia_categories_xlsx_');

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
            $width = in_array($i, [2, 3, 4, 5, 9, 10], true) ? 28 : 18;
            $xml .= '<col min="' . $i . '" max="' . $i . '" width="' . $width . '" customWidth="1"/>';
        }

        $xml .= '</cols><sheetData><row r="1">';

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

                if (in_array($header, ['orden', 'nivel_solo_lectura', 'productos_solo_lectura', 'id_solo_lectura'], true) && is_numeric((string) $value)) {
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
        $xml .= '</worksheet>';

        return $xml;
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

    protected static function numeric(mixed $value): string
    {
        return str_replace(',', '.', (string) $value);
    }

    protected static function xml(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected static function boolValue(mixed $value): string
    {
        return (bool) $value ? 'sí' : 'no';
    }

    protected static function costingMethodLabel(mixed $value): string
    {
        return match ((string) $value) {
            'average' => 'promedio',
            'fifo' => 'fifo',
            'standard' => 'estándar',
            'inherit' => 'heredar',
            default => (string) ($value ?? ''),
        };
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
