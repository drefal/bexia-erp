<?php

namespace App\Http\Controllers;

use App\Support\Inventory\InventoryAsOfDateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class InventoryAsOfDateExportController extends Controller
{
    public function print(Request $request)
    {
        $filters = $this->filters($request);
        $service = app(InventoryAsOfDateService::class);

        $rows = $service->rows($filters);
        $summary = $service->summary($filters);
        $company = $this->company($filters['company_id'] ?? null);

        $pdf = Pdf::loadView('inventory.as-of-date-print', [
            'rows' => $rows,
            'summary' => $summary,
            'filters' => $filters,
            'company' => $company,
            'logoDataUri' => $this->companyLogoDataUri($company),
            'generatedAt' => now(),
        ])->setPaper('letter', 'landscape');

        return $pdf->stream('inventario_a_fecha_' . now()->format('Ymd_His') . '.pdf');
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $filters = $this->filters($request);
        $service = app(InventoryAsOfDateService::class);

        $rows = $service->rows($filters);
        $summary = $service->summary($filters);

        $data = [];
        $data[] = ['Inventario a fecha'];
        $data[] = ['Generado', now()->format('d/m/Y H:i:s')];
        $data[] = ['Corte aplicado', $summary['cutoff_at'] ?? ''];
        $data[] = [
            'Líneas',
            $summary['lines'] ?? 0,
            'Cantidad total',
            $summary['total_quantity'] ?? 0,
            'Positivos',
            $summary['positive_lines'] ?? 0,
            'Negativos',
            $summary['negative_lines'] ?? 0,
            'Con lote',
            $summary['with_lot'] ?? 0,
        ];

        $data[] = [];
        $data[] = [
            'Empresa',
            'Almacén',
            'Ubicación',
            'Producto',
            'Variante',
            'Lote',
            'Existencia al corte',
            'Fecha/hora de corte',
        ];

        foreach ($rows as $row) {
            $variantName = trim((string) ($row->variant_name ?? ''));
            $lotNumber = trim((string) ($row->lot_number ?? ''));

            $data[] = [
                $row->company_name ?: '',
                $row->warehouse_name ?: '',
                $row->location_name ?: '',
                $row->product_name ?: '',
                $variantName !== '' ? $variantName : 'Sin variante',
                $lotNumber !== '' ? $lotNumber : 'Sin lote',
                (float) ($row->quantity_as_of ?? 0),
                $row->cutoff_at ?? ($summary['cutoff_at'] ?? ''),
            ];
        }

        $filename = 'inventario_a_fecha_' . now()->format('Ymd_His') . '.xlsx';
        $path = storage_path('app/' . $filename);

        $this->writeXlsx($path, $data);

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    protected function filters(Request $request): array
    {
        return [
            'company_id' => $this->nullableInt($request->query('company_id')),
            'warehouse_id' => $this->nullableInt($request->query('warehouse_id')),
            'location_id' => $this->nullableInt($request->query('location_id')),
            'product_id' => $this->nullableInt($request->query('product_id')),
            'product_variant_id' => $this->nullableInt($request->query('product_variant_id')),
            'lot_id' => $this->nullableInt($request->query('lot_id')),
            'cutoff_date' => $request->query('cutoff_date') ?: now()->toDateString(),
            'cutoff_time' => $request->query('cutoff_time') ?: '23:59',
            'limit' => min(5000, max(50, (int) $request->query('limit', 1000))),
            'show_zero' => filter_var($request->query('show_zero', false), FILTER_VALIDATE_BOOLEAN),
            'only_negative' => filter_var($request->query('only_negative', false), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    protected function nullableInt($value): ?int
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        return (int) $value;
    }

    protected function company(?int $companyId): ?object
    {
        if (! $companyId || ! Schema::hasTable('companies')) {
            return null;
        }

        return DB::table('companies')->where('id', $companyId)->first();
    }

    protected function companyLogoDataUri(?object $company): ?string
    {
        if (! $company) {
            return null;
        }

        foreach ([
            'logo_path',
            'logo',
            'logo_url',
            'logo_file',
            'logo_file_path',
            'image',
            'image_path',
        ] as $column) {
            if (! isset($company->{$column}) || ! $company->{$column}) {
                continue;
            }

            $value = (string) $company->{$column};

            if (str_starts_with($value, 'data:image')) {
                return $value;
            }

            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                return $value;
            }

            foreach ([
                public_path($value),
                public_path('storage/' . ltrim($value, '/')),
                storage_path('app/public/' . ltrim($value, '/')),
                storage_path('app/' . ltrim($value, '/')),
            ] as $path) {
                if (is_file($path)) {
                    $mime = mime_content_type($path) ?: 'image/png';

                    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
                }
            }
        }

        return null;
    }

    protected function writeXlsx(string $path, array $rows): void
    {
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el archivo Excel.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Inventario a fecha" sheetId="1" r:id="rId1"/></sheets>
</workbook>');

        $sheet = '<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $r => $row) {
            $rowNumber = $r + 1;
            $sheet .= '<row r="' . $rowNumber . '">';

            foreach (array_values($row) as $c => $value) {
                $cell = $this->cellName($c + 1, $rowNumber);

                if (is_numeric($value) && $value !== '') {
                    $sheet .= '<c r="' . $cell . '"><v>' . $value . '</v></c>';
                } else {
                    $sheet .= '<c r="' . $cell . '" t="inlineStr"><is><t>' . htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
                }
            }

            $sheet .= '</row>';
        }

        $sheet .= '</sheetData></worksheet>';

        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
    }

    protected function cellName(int $column, int $row): string
    {
        $name = '';

        while ($column > 0) {
            $mod = ($column - 1) % 26;
            $name = chr(65 + $mod) . $name;
            $column = intdiv($column - $mod, 26);
        }

        return $name . $row;
    }
}
