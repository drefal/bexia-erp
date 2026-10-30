<?php

namespace App\Http\Controllers;

use App\Support\Inventory\ProductKardexService;
use App\Support\Security\InventoryTenantScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class InventoryProductKardexExportController extends Controller
{
    public function print(Request $request)
    {
        $filters = $this->filters($request);
        $service = app(ProductKardexService::class);
        $rows = $service->rows($filters);
        $summary = $service->summary($filters);
        $company = $this->company($filters['company_id'] ?? null);
        $metadata = $this->exportMetadata($filters);
        $reconciliation = $this->exportReconciliation($filters);

        $pdf = Pdf::loadView('inventory.kardex-print', [
            'rows' => $rows,
            'summary' => $summary,
            'filters' => $filters,
            'company' => $company,
            'logoDataUri' => $this->companyLogoDataUri($company),
            'generatedAt' => now(),
            'metadata' => $metadata,
            'reconciliation' => $reconciliation,
        ])->setPaper('letter', 'landscape');

        return $pdf->stream('kardex_producto_' . now()->format('Ymd_His') . '.pdf');
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $filters = $this->filters($request);
        $service = app(ProductKardexService::class);
        $rows = $service->rows($filters);
        $summary = $service->summary($filters);
        $metadata = $this->exportMetadata($filters);
        $reconciliation = $this->exportReconciliation($filters);

        $headers = [
            'Empresa',
            'Almacén',
            'Ubicación origen',
            'Ubicación destino',
            'Producto',
            'Variante',
            'Lote',
            'Número de serie',
            'Fecha',
            'Documento',
            'Detalle documento',
            'Origen',
            'Entrada',
            'Salida',
            'Saldo',
            'Costo aplicado',
            'Costo registrado',
            'Valor movimiento',
            'Saldo valorizado',
            'Método valorización',
            'Método configurado',
            'Fuente de costo',
        ];

        $data = [];
        $data[] = ['Kardex por producto'];
        $data[] = ['Generado', now()->format('d/m/Y H:i:s')];
        $data[] = ['Empresa', $metadata['company']];
        $data[] = ['Almacén', $metadata['warehouse']];
        $data[] = ['Producto', $metadata['product']];
        $data[] = ['Periodo', $metadata['period']];
        if ($reconciliation !== null) {
            $data[] = ['Conciliación actual', now()->format('d/m/Y H:i:s')];
            $data[] = ['Existencia registrada', $reconciliation['quantity']];
            $data[] = ['Saldo por movimientos confirmados', $reconciliation['ledger']];
            $data[] = ['Diferencia por conciliar', $reconciliation['difference']];
            $data[] = ['Aviso', 'Comparación actual, no constituye apertura histórica'];
        }
        $data[] = [
            'Método',
            ($summary['rows'] ?? 0) > 0
                ? $this->methodText((string) ($summary['valuation_method'] ?? ''))
                : 'Sin historial',
        ];
        $data[] = [
            'Entradas', $summary['in_qty'] ?? 0,
            'Salidas', $summary['out_qty'] ?? 0,
            'Saldo', $summary['balance_qty'] ?? 0,
            'Saldo valorizado',
            ($summary['rows'] ?? 0) > 0
                ? ($summary['balance_value'] ?? 0)
                : 'No determinado',
        ];
        $data[] = [];
        $data[] = [
            'Aviso',
            'La valorización se basa en movimientos disponibles; '
            . 'el saldo histórico de apertura puede no estar documentado.',
        ];
        $data[] = [];
        $data[] = $headers;

        foreach ($rows as $row) {
            $data[] = [
                $row->company_name ?? '',
                $row->warehouse_name ?? '',
                $row->source_location_name ?? '',
                $row->destination_location_name ?? '',
                $row->product_name ?? '',
                $row->variant_name ?? '',
                $row->lot_number ?? ($row->lot_id ?? ''),
                $row->serial_number ?? ($row->stock_serial_number_id ?? ''),
                $row->date ? \Carbon\Carbon::parse($row->date)->format('d/m/Y H:i') : '',
                $row->reference ?? '',
                $this->documentText($row->origin_document ?? ''),
                $this->sourceText($row->source_type ?? 'legacy'),
                (float) ($row->in_qty ?? 0),
                (float) ($row->out_qty ?? 0),
                (float) ($row->balance_qty ?? 0),
                (float) ($row->applied_unit_cost ?? 0),
                (float) ($row->recorded_unit_cost ?? 0),
                (float) ($row->movement_value ?? 0),
                (float) ($row->balance_value ?? 0),
                $this->methodText((string) ($row->valuation_method ?? '')),
                $this->methodText((string) ($row->costing_method ?? '')),
                $this->costSourceText((string) ($row->cost_source ?? '')),
            ];
        }

        $filename = 'kardex_producto_' . now()->format('Ymd_His') . '.xlsx';
        $path = storage_path('app/' . $filename);

        $this->writeXlsx($path, $data);

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }


    protected function exportMetadata(array $filters): array
    {
        $companyId = (int) ($filters['company_id'] ?? 0);
        $allowed = $companyId > 0 && InventoryTenantScope::isAllowedCompany($companyId);
        $company = $allowed ? DB::table('companies')->where('id', $companyId)->value('name') : 'No seleccionada';
        $warehouse = 'Todos';
        $product = 'Todos';
        if ($allowed && !empty($filters['warehouse_id'])) {
            $w = DB::table('warehouses')->where('company_id', $companyId)
                ->where('id', (int) $filters['warehouse_id'])->first();
            $warehouse = $w ? ($w->name . ' #' . $w->id) : 'No autorizado';
        }
        if ($allowed && !empty($filters['product_id'])) {
            $p = DB::table('products')->where('id', (int) $filters['product_id'])->first();
            $product = $p ? ($p->name . ' #' . $p->id) : 'No encontrado';
        }
        return [
            'company' => $company ?: 'No seleccionada',
            'warehouse' => $warehouse,
            'product' => $product,
            'period' => ($filters['date_from'] ?: 'Inicio') . ' a ' . ($filters['date_to'] ?: 'Hoy'),
        ];
    }

    protected function exportReconciliation(array $filters): ?array
    {
        $companyId = (int) ($filters['company_id'] ?? 0);
        $warehouseId = (int) ($filters['warehouse_id'] ?? 0);
        $productId = (int) ($filters['product_id'] ?? 0);
        if (!$companyId || !$warehouseId || !$productId
            || !InventoryTenantScope::isAllowedCompany($companyId)
            || !empty($filters['location_id'])
            || !empty($filters['product_variant_id'])
            || !empty($filters['lot_id'])
            || !empty($filters['stock_serial_number_id'])
            || !Schema::hasTable('stock_quants')) {
            return null;
        }
        if (!DB::table('warehouses')->where('id', $warehouseId)->where('company_id', $companyId)->exists()) {
            return null;
        }
        $quantity = (float) DB::table('stock_quants')
            ->where('company_id', $companyId)->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)->sum('quantity');
        $historyFilters = $filters;
        unset($historyFilters['date_from'], $historyFilters['date_to']);
        $historyFilters['status'] = 'done';
        $summary = app(ProductKardexService::class)->summary($historyFilters);
        $ledger = (float) ($summary['balance_qty'] ?? 0);
        return [
            'quantity' => round($quantity, 6),
            'ledger' => round($ledger, 6),
            'difference' => round($quantity - $ledger, 6),
        ];
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
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'status' => (string) $request->query('status', 'done'),
            'valuation_method' => (string) $request->query('valuation_method', 'auto'),
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

    protected function methodText(string $value): string
    {
        return match (strtolower($value)) {
            'average' => 'Costo promedio',
            'fifo' => 'PEPS / FIFO',
            'standard' => 'Costo estándar',
            'recorded' => 'Registrado',
            'auto' => 'Automático',
            'mixed' => 'Mixto',
            default => $value ?: '—',
        };
    }

    protected function sourceText(string $value): string
    {
        return match (strtolower($value)) {
            'purchase_receipt' => 'Recepción de compra',
            'sale_delivery' => 'Entrega de venta',
            'pos_order' => 'Venta PDV',
            'pos_order_refund' => 'Devolución PDV',
            'stock_adjustment' => 'Ajuste de inventario',
            'legacy', '', 'null' => 'Histórico',
            default => ucfirst(str_replace('_', ' ', $value)),
        };
    }

    protected function costSourceText(string $value): string
    {
        return match (true) {
            str_contains($value, 'legacy.stock_movement_line.unit_cost:product_variant') => 'Costo histórico de la variante',
            str_contains($value, 'legacy.stock_movement_line.unit_cost:product') => 'Costo histórico del producto',
            str_contains($value, 'legacy.backfill.variant.average_cost_without_tax') => 'Costo promedio histórico de la variante',
            str_contains($value, 'legacy.backfill.product.average_cost_without_tax') => 'Costo promedio histórico del producto',
            str_contains($value, 'purchase_receipt.unit_cost_without_tax') => 'Costo de recepción sin IVA',
            str_contains($value, 'purchase_receipt') => 'Costo de recepción',
            str_contains($value, 'sale_delivery.unit_cost') => 'Costo de entrega',
            str_contains($value, 'pos_order.average_cost_at_sale') => 'Costo promedio al vender',
            str_contains($value, 'pos_order_refund.original_sale_cost') => 'Costo original de la venta',
            str_contains($value, 'stock_adjustment.unit_cost') => 'Costo del ajuste',
            $value === '' => '—',
            default => str_replace(['_', '.'], [' ', ' · '], $value),
        };
    }

    protected function documentText(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $clean = preg_replace('/^(purchase_receipt|sale_delivery|pos_order_refund|pos_order|stock_adjustment)[\:\-\s]*/i', '', $value);

        return match (true) {
            str_starts_with(strtolower($value), 'purchase_receipt') => 'Recepción de compra' . ($clean ? ': ' . $clean : ''),
            str_starts_with(strtolower($value), 'sale_delivery') => 'Entrega de venta' . ($clean ? ': ' . $clean : ''),
            str_starts_with(strtolower($value), 'pos_order_refund') => 'Devolución PDV' . ($clean ? ': ' . $clean : ''),
            str_starts_with(strtolower($value), 'pos_order') => 'Venta PDV' . ($clean ? ': ' . $clean : ''),
            str_starts_with(strtolower($value), 'stock_adjustment') => 'Ajuste de inventario' . ($clean ? ': ' . $clean : ''),
            default => $value,
        };
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
<sheets><sheet name="Kardex" sheetId="1" r:id="rId1"/></sheets>
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
