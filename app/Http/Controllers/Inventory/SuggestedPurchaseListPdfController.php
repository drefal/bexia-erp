<?php

namespace App\Http\Controllers\Inventory;

use App\Filament\Pages\SuggestedPurchaseList;
use App\Http\Controllers\Controller;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SuggestedPurchaseListPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(auth()->check(), 403, 'No tienes permiso para ver este PDF.');

        $filters = [
            'company_id' => $request->integer('company_id') ?: $this->currentCompanyId(),
            'warehouse_id' => $request->integer('warehouse_id') ?: null,
            'location_id' => $request->integer('location_id') ?: null,
            'priority' => trim((string) $request->query('priority', '')),
            'search' => trim((string) $request->query('search', '')),
            'only_shortages' => $request->boolean('only_shortages', true),
        ];

        $budget = \App\Filament\Pages\SuggestedPurchaseList::normalizeBudgetAmount($request->query('budget_amount', 0));

        $rows = SuggestedPurchaseList::buildRows($filters);
        $rows = SuggestedPurchaseList::applyBudget($rows, $budget);

        $groupedRows = [];

        foreach ($rows as $row) {
            $key = $row['supplier'] ?: 'Sin proveedor sugerido';
            $groupedRows[$key][] = $row;
        }

        ksort($groupedRows);

        $totals = [
            'lines' => count($rows),
            'included_lines' => collect($rows)->where('included_in_budget', true)->count(),
            'out_lines' => collect($rows)->where('included_in_budget', false)->count(),
            'full_total' => collect($rows)->sum('estimated_total'),
            'included_total' => collect($rows)->where('included_in_budget', true)->sum('estimated_total'),
            'out_total' => collect($rows)->where('included_in_budget', false)->sum('estimated_total'),
            'budget' => $budget,
        ];

        $data = [
            'title' => 'Lista sugerida de compra',
            'generatedAt' => now()->format('d/m/Y H:i'),
            'companyName' => $this->companyName((int) ($filters['company_id'] ?? 0)),
            'logoPath' => $this->logoPath((int) ($filters['company_id'] ?? 0)),
            'filters' => $this->filterLabels($filters, $budget),
            'groupedRows' => $groupedRows,
            'totals' => $totals,
        ];

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.inventory.suggested-purchase-list', $data)
                ->setPaper('letter', 'landscape');

            return $pdf->stream('lista-sugerida-compra-' . now()->format('Ymd-His') . '.pdf');
        }

        if (app()->bound('dompdf.wrapper')) {
            $pdf = app('dompdf.wrapper');
            $pdf->loadView('pdfs.inventory.suggested-purchase-list', $data);
            $pdf->setPaper('letter', 'landscape');

            return $pdf->stream('lista-sugerida-compra-' . now()->format('Ymd-His') . '.pdf');
        }

        abort(500, 'DOMPDF no está disponible.');
    }

    protected function filterLabels(array $filters, float $budget): array
    {
        return [
            'Almacén' => $filters['warehouse_id'] ? $this->labelFromTable('warehouses', $filters['warehouse_id'], ['code'], ['name']) : 'Todos',
            'Ubicación' => $filters['location_id'] ? $this->labelFromTable('stock_locations', $filters['location_id'], ['code'], ['name']) : 'Todas',
            'Prioridad' => $filters['priority'] !== '' ? $this->priorityLabel($filters['priority']) : 'Todas',
            'Solo faltantes' => $filters['only_shortages'] ? 'Sí' : 'No',
            'Presupuesto' => $budget > 0 ? '$ ' . number_format($budget, 2) : 'Sin límite',
            'Búsqueda' => $filters['search'] ?: '-',
        ];
    }

    protected function priorityLabel(?string $priority): string
    {
        return match ($priority) {
            'low' => 'Baja',
            'high' => 'Alta',
            'critical' => 'Crítica',
            default => 'Normal',
        };
    }

    protected function labelFromTable(string $table, $id, array $codeColumns, array $nameColumns): string
    {
        if (! $id || ! Schema::hasTable($table)) {
            return '-';
        }

        $row = DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '-';
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

        return trim(($code ? $code . ' - ' : '') . ($name ?: ('#' . $id)));
    }

    protected function companyName(?int $companyId = null): string
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            foreach (['name', 'business_name', 'legal_name', 'razon_social', 'company_name'] as $field) {
                if (isset($tenant->{$field}) && trim((string) $tenant->{$field}) !== '') {
                    return (string) $tenant->{$field};
                }
            }
        }

        if ($companyId && Schema::hasTable('companies')) {
            $company = DB::table('companies')->where('id', $companyId)->first();

            if ($company) {
                foreach (['name', 'business_name', 'legal_name', 'razon_social', 'company_name'] as $field) {
                    if (isset($company->{$field}) && trim((string) $company->{$field}) !== '') {
                        return (string) $company->{$field};
                    }
                }
            }
        }

        return config('app.name', 'Bexia ERP');
    }

    protected function logoPath(?int $companyId = null): ?string
    {
        $candidates = [];

        $tenant = Filament::getTenant();

        if ($tenant) {
            foreach (['logo_path', 'logo', 'image_path', 'logo_url', 'brand_logo', 'company_logo'] as $field) {
                if (isset($tenant->{$field}) && trim((string) $tenant->{$field}) !== '') {
                    $candidates[] = (string) $tenant->{$field};
                }
            }
        }

        if ($companyId && Schema::hasTable('companies')) {
            $company = DB::table('companies')->where('id', $companyId)->first();

            if ($company) {
                foreach (['logo_path', 'logo', 'image_path', 'logo_url', 'brand_logo', 'company_logo'] as $field) {
                    if (isset($company->{$field}) && trim((string) $company->{$field}) !== '') {
                        $candidates[] = (string) $company->{$field};
                    }
                }
            }
        }

        foreach ($candidates as $candidate) {
            $path = trim($candidate);

            if ($path === '') {
                continue;
            }

            $path = parse_url($path, PHP_URL_PATH) ?: $path;
            $path = ltrim($path, '/');

            $possiblePaths = [
                base_path($path),
                public_path($path),
                public_path('storage/' . preg_replace('#^storage/#', '', $path)),
                storage_path('app/public/' . preg_replace('#^(storage|public)/#', '', $path)),
                storage_path('app/' . preg_replace('#^storage/#', '', $path)),
            ];

            foreach ($possiblePaths as $possiblePath) {
                if ($possiblePath && is_file($possiblePath)) {
                    return $possiblePath;
                }
            }
        }

        foreach ([public_path('images/logo.png'), public_path('logo.png'), public_path('favicon.png')] as $fallback) {
            if (is_file($fallback)) {
                return $fallback;
            }
        }

        return null;
    }

    protected function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        if ($tenant && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        $user = auth()->user();

        if ($user && isset($user->company_id)) {
            return (int) $user->company_id;
        }

        return null;
    }
}
