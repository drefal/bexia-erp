<?php

namespace App\Http\Controllers;

use App\Support\Inventory\InventoryValuationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class InventoryValuationExportController extends Controller
{
    public function print(Request $request)
    {
        $filters = $this->filters($request);
        $service = app(InventoryValuationService::class);
        $rows = $service->rows($filters);
        $summary = $service->summary($filters);
        $company = $this->company($filters['company_id'] ?? null);

        $pdf = Pdf::loadView('inventory.valuation-print', [
            'rows' => $rows,
            'summary' => $summary,
            'filters' => $filters,
            'company' => $company,
            'logoDataUri' => $this->companyLogoDataUri($company),
            'generatedAt' => now(),
        ])->setPaper('letter', 'landscape');

        return $pdf->stream('valorizacion_inventario_' . now()->format('Ymd_His') . '.pdf');
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $filters = $this->filters($request);
        $service = app(InventoryValuationService::class);
        $rows = $service->rows($filters);
        $summary = $service->summary($filters);

        $data = [];
        $data[] = ['Valorización de inventario'];
        $data[] = ['Generado', now()->format('d/m/Y H:i:s')];
        $data[] = ['Partidas', $summary['lines'] ?? 0, 'Cantidad total', $summary['quantity'] ?? 0, 'Valor total', $summary['value'] ?? 0, 'Alertas', $summary['with_alerts'] ?? 0];
        $data[] = [];
        $data[] = [
            'Empresa',
            'Almacén',
            'Ubicación',
            'Producto',
            'Producto ID',
            'Variante',
            'Variante ID',
            'Lote',
            'Número de serie',
            'Cantidad',
            'Costo unitario',
            'Valor',
            'Método',
            'Fuente de costo',
            'Alertas',
        ];

        foreach ($rows as $row) {
            $data[] = [
                $row->company_name ?? '',
                $row->warehouse_name ?? '',
                $row->location_name ?? '',
                $row->product_name ?? '',
                $row->product_id ?? '',
                $row->variant_name ?? '',
                $row->product_variant_id ?? '',
                $row->lot_number ?? ($row->lot_id ?? ''),
                $row->serial_number ?? ($row->stock_serial_number_id ?? ''),
                (float) ($row->quantity ?? 0),
                (float) ($row->unit_cost ?? 0),
                (float) ($row->total_value ?? 0),
                $row->costing_method ?? '',
                $row->cost_source ?? '',
                $row->alerts ?? '',
            ];
        }

        $filename = 'valorizacion_inventario_' . now()->format('Ymd_His') . '.xlsx';
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
            'product_search' => (string) $request->query('product_search', ''),
            'product_variant_id' => $this->nullableInt($request->query('product_variant_id')),
            'lot_id' => $this->nullableInt($request->query('lot_id')),
            'stock_serial_number_id' => $this->nullableInt($request->query('stock_serial_number_id')),
            'only_positive' => filter_var($request->query('only_positive', true), FILTER_VALIDATE_BOOLEAN),
            'limit' => min(5000, max(50, (int) $request->query('limit', 500))),
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
<sheets><sheet name="Valorizacion" sheetId="1" r:id="rId1"/></sheets>
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
