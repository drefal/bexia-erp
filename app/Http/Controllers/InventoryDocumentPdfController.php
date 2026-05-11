<?php

namespace App\Http\Controllers;

use App\Models\StockAdjustment;
use App\Models\StockMovement;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InventoryDocumentPdfController extends Controller
{
    public function stockAdjustment(StockAdjustment $record): Response
    {
        $this->authorizeInventoryPdf();

        if (! app()->bound('dompdf.wrapper')) {
            abort(500, 'No hay motor PDF instalado.');
        }

        $company = $this->company($record->company_id ? (int) $record->company_id : null);

        $lines = DB::table('stock_adjustment_lines')
            ->where('stock_adjustment_id', $record->id)
            ->orderBy('id')
            ->get()
            ->map(function ($line): array {
                return [
                    'product' => $this->productLabel($line->product_id ?? null),
                    'variant' => $this->variantLabel($line->product_variant_id ?? null),
                    'current_quantity' => (float) ($line->current_quantity ?? 0),
                    'counted_quantity' => (float) ($line->counted_quantity ?? 0),
                    'difference_quantity' => (float) ($line->difference_quantity ?? 0),
                    'unit_cost' => $line->unit_cost !== null ? (float) $line->unit_cost : null,
                    'notes' => (string) ($line->notes ?? ''),
                ];
            })
            ->all();

        $pdf = app('dompdf.wrapper')
            ->loadView('pdfs.inventory.stock-adjustment', [
                'record' => $record,
                'company' => $company,
                'logoDataUri' => $this->logoDataUri($company),
                'warehouseLabel' => $this->warehouseLabel($record->warehouse_id ?? null),
                'locationLabel' => $this->locationLabel($record->location_id ?? null),
                'lines' => $lines,
            ])
            ->setPaper('letter', 'portrait');

        return $pdf->stream($this->safeFileName($record->reference ?: ('ajuste-' . $record->id)) . '.pdf');
    }

    public function stockMovement(StockMovement $record): Response
    {
        $this->authorizeInventoryPdf();

        if (! app()->bound('dompdf.wrapper')) {
            abort(500, 'No hay motor PDF instalado.');
        }

        $company = $this->company($record->company_id ? (int) $record->company_id : null);

        $lines = DB::table('stock_movement_lines')
            ->where('stock_movement_id', $record->id)
            ->orderBy('id')
            ->get()
            ->map(function ($line): array {
                return [
                    'product' => $this->productLabel($line->product_id ?? null),
                    'variant' => $this->variantLabel($line->product_variant_id ?? null),
                    'quantity' => (float) ($line->done_quantity ?? $line->requested_quantity ?? 0),
                    'unit_cost' => $line->unit_cost !== null ? (float) $line->unit_cost : null,
                    'notes' => (string) ($line->notes ?? ''),
                ];
            })
            ->all();

        $pdf = app('dompdf.wrapper')
            ->loadView('pdfs.inventory.stock-transfer', [
                'record' => $record,
                'company' => $company,
                'logoDataUri' => $this->logoDataUri($company),
                'operationLabel' => $this->operationTypeLabel($record->stock_operation_type_id ?? null),
                'warehouseLabel' => $this->warehouseLabel($record->warehouse_id ?? null),
                'sourceLabel' => $this->locationLabel($record->source_location_id ?? null),
                'destinationLabel' => $this->locationLabel($record->destination_location_id ?? null),
                'lines' => $lines,
            ])
            ->setPaper('letter', 'portrait');

        return $pdf->stream($this->safeFileName($record->reference ?: ('traslado-' . $record->id)) . '.pdf');
    }

    protected function authorizeInventoryPdf(): void
    {
        /*
         * El botón PDF solo se expone dentro de Filament.
         * Dejamos la protección en "usuario autenticado" para evitar falsos 403
         * por diferencias de nombres de roles/permisos entre paneles.
         */

        $user = auth()->user();

        if (! $user && class_exists(\Filament\Facades\Filament::class)) {
            try {
                $user = \Filament\Facades\Filament::auth()->user();
            } catch (\Throwable $exception) {
                $user = null;
            }
        }

        if ($user) {
            return;
        }

        abort(403, 'No autenticado.');
    }





    protected function company(?int $companyId): ?object
    {
        if (! $companyId || ! Schema::hasTable('companies')) {
            return null;
        }

        return DB::table('companies')->where('id', $companyId)->first();
    }

    protected function logoDataUri(?object $company): ?string
    {
        if (! $company) {
            return null;
        }

        foreach (['logo_path', 'logo', 'logo_url', 'image_path', 'image', 'brand_logo', 'avatar', 'avatar_url'] as $column) {
            if (! property_exists($company, $column)) {
                continue;
            }

            $value = trim((string) ($company->{$column} ?? ''));

            if ($value === '') {
                continue;
            }

            if (str_starts_with($value, 'data:image/')) {
                return $value;
            }

            if (preg_match('/^https?:\/\//i', $value)) {
                continue;
            }

            $normalized = ltrim($value, '/');

            if (str_starts_with($normalized, 'storage/')) {
                $normalized = substr($normalized, strlen('storage/'));
            }

            $candidates = [
                storage_path('app/public/' . $normalized),
                public_path('storage/' . $normalized),
                public_path($value),
                base_path($value),
            ];

            foreach ($candidates as $path) {
                if (! is_file($path)) {
                    continue;
                }

                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                    default => 'image/png',
                };

                return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
            }

            if (Storage::disk('public')->exists($normalized)) {
                $path = Storage::disk('public')->path($normalized);
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                    default => 'image/png',
                };

                return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
            }
        }

        return null;
    }

    protected function companyName(?object $company): string
    {
        if (! $company) {
            return config('app.name', 'BexiaERP');
        }

        foreach (['commercial_name', 'trade_name', 'name', 'legal_name', 'business_name', 'razon_social'] as $column) {
            if (property_exists($company, $column) && trim((string) ($company->{$column} ?? '')) !== '') {
                return trim((string) $company->{$column});
            }
        }

        return config('app.name', 'BexiaERP');
    }

    protected function warehouseLabel($id): string
    {
        return $this->labelFromTable('warehouses', $id, ['code'], ['name']);
    }

    protected function locationLabel($id): string
    {
        return $this->labelFromTable('stock_locations', $id, ['code'], ['name']);
    }

    protected function operationTypeLabel($id): string
    {
        return $this->labelFromTable('stock_operation_types', $id, ['reference_prefix', 'code'], ['name']);
    }

    protected function productLabel($id): string
    {
        return $this->labelFromTable('products', $id, ['internal_reference', 'sku', 'barcode', 'code'], ['name', 'description']);
    }

    protected function variantLabel($id): string
    {
        if (! $id || ! Schema::hasTable('products')) {
            return '—';
        }

        $row = DB::table('products')->where('id', $id)->first();

        if (! $row) {
            return '—';
        }

        $reference = '';

        foreach (['internal_reference', 'sku', 'barcode', 'code'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                $value = trim((string) ($row->{$column} ?? ''));

                if ($value !== '') {
                    $reference = $value;
                    break;
                }
            }
        }

        $group = Schema::hasColumn('products', 'variant_group')
            ? trim((string) ($row->variant_group ?? ''))
            : '';

        $value = Schema::hasColumn('products', 'variant_value')
            ? trim((string) ($row->variant_value ?? ''))
            : '';

        $variantText = '';

        if ($group !== '' && $value !== '') {
            $variantText = $group . ': ' . $value;
        } elseif ($value !== '') {
            $variantText = $value;
        } elseif (Schema::hasColumn('products', 'name')) {
            $variantText = trim((string) ($row->name ?? ''));
        }

        if ($reference !== '' && $variantText !== '') {
            return $reference . ' - ' . $variantText;
        }

        return $variantText ?: ($reference ?: ('Variante #' . $id));
    }

    protected function labelFromTable(string $table, $id, array $codeColumns = [], array $nameColumns = []): string
    {
        if (! $id || ! Schema::hasTable($table)) {
            return '—';
        }

        $row = DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '—';
        }

        $code = '';
        $name = '';

        foreach ($codeColumns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $value = trim((string) ($row->{$column} ?? ''));

                if ($value !== '') {
                    $code = $value;
                    break;
                }
            }
        }

        foreach ($nameColumns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $value = trim((string) ($row->{$column} ?? ''));

                if ($value !== '') {
                    $name = $value;
                    break;
                }
            }
        }

        if ($code !== '' && $name !== '') {
            return $code . ' - ' . $name;
        }

        return $name ?: ($code ?: ('#' . $id));
    }

    protected function safeFileName(string $value): string
    {
        $value = Str::ascii($value);
        $value = preg_replace('/[^A-Za-z0-9\-_\.]+/', '-', $value);
        $value = trim((string) $value, '-');

        return $value !== '' ? $value : 'documento';
    }
}
