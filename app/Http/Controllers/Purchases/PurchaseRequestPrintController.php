<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseRequestPrintController extends Controller
{
    public function __invoke(Request $request, PurchaseRequest $purchaseRequest)
    {
        abort_unless(auth()->check(), 403);
        abort_unless($this->canPrint(auth()->user(), $purchaseRequest), 403);

        $lines = method_exists($purchaseRequest, 'lines')
            ? $purchaseRequest->lines()->get()
            : collect();

        $subtotal = (float) (
            $purchaseRequest->total_without_tax
            ?? $purchaseRequest->subtotal_without_tax
            ?? $lines->sum('line_total_without_tax')
        );

        $taxTotal = (float) (
            $purchaseRequest->total_tax
            ?? $purchaseRequest->tax_total
            ?? $lines->sum('line_tax')
        );

        $total = (float) (
            $purchaseRequest->total_with_tax
            ?? ($subtotal + $taxTotal)
        );

        $pdf = Pdf::loadView('pdfs.purchases.purchase-request', [
            'purchaseRequest' => $purchaseRequest,
            'lines' => $lines,
            'subtotal' => $subtotal,
            'taxTotal' => $taxTotal,
            'total' => $total,
            'companyName' => $this->resolveCompanyName($purchaseRequest),
            'companyLogo' => $this->resolveCompanyLogoDataUri($purchaseRequest),
            'warehouseLabel' => $this->resolveWarehouseLabel($purchaseRequest),
            'locationLabel' => $this->resolveLocationLabel($purchaseRequest),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ]);

        return $pdf->stream('solicitud-compra-' . ($purchaseRequest->number ?? $purchaseRequest->id) . '.pdf');
    }

    protected function canPrint($user, PurchaseRequest $purchaseRequest): bool
    {
        if (! $user) {
            return false;
        }

        if ((int) ($user->id ?? 0) === 1) {
            return true;
        }

        if (method_exists($user, 'hasAnyRole')) {
            try {
                if ($user->hasAnyRole([
                    'super_admin',
                    'Super Administrador',
                    'admin',
                    'Administrador',
                    'Admin Grupo',
                    'Admin Empresa',
                    'Compras',
                    'Reportes',
                    'Inventarios',
                ])) {
                    return true;
                }
            } catch (\Throwable $e) {
                //
            }
        }

        foreach ([
            'purchase_requests.print',
            'purchase_requests.view',
            'purchase_requests.view_any',
            'purchase_requests.manage',
            'purchase_requests.approve',
            'purchases.print',
            'purchases.view',
            'purchases.create',
            'purchases.approve',
            'compras.view',
            'compras.manage',
            'reports.view',
            'reports.purchases',
            'approvals.view',
            'approvals.manage',
        ] as $permission) {
            try {
                if (method_exists($user, 'can') && $user->can($permission)) {
                    return true;
                }
            } catch (\Throwable $e) {
                //
            }
        }

        if (
            isset($purchaseRequest->requested_by_user_id)
            && (int) $purchaseRequest->requested_by_user_id === (int) $user->id
        ) {
            return true;
        }

        if (
            Schema::hasTable('approval_requests')
            && Schema::hasTable('approval_request_steps')
        ) {
            $approvableType = 'App\\Models\\PurchaseRequest';

            return DB::table('approval_request_steps')
                ->join('approval_requests', 'approval_requests.id', '=', 'approval_request_steps.approval_request_id')
                ->where('approval_request_steps.approver_user_id', $user->id)
                ->where(function ($query) use ($purchaseRequest, $approvableType) {
                    $query->where(function ($q) use ($purchaseRequest, $approvableType) {
                        $q->where('approval_requests.approvable_type', $approvableType)
                            ->where('approval_requests.approvable_id', $purchaseRequest->id);
                    })->orWhere(function ($q) use ($purchaseRequest) {
                        $q->where('approval_requests.document_type', 'purchase_request')
                            ->where('approval_requests.document_number', $purchaseRequest->number);
                    });
                })
                ->exists();
        }

        return false;
    }

    protected function resolveWarehouseLabel(PurchaseRequest $purchaseRequest): string
    {
        $direct = $this->firstModelValue($purchaseRequest, [
            'warehouse_label',
            'warehouse_name',
            'destination_warehouse_label',
            'destination_warehouse_name',
            'inventory_warehouse_label',
            'inventory_warehouse_name',
        ]);

        if ($direct) {
            return $direct;
        }

        $warehouseId = $this->firstModelValue($purchaseRequest, [
            'warehouse_id',
            'destination_warehouse_id',
            'inventory_warehouse_id',
            'stock_warehouse_id',
        ]);

        if (! $warehouseId) {
            return '—';
        }

        return $this->resolveLabelFromTables((int) $warehouseId, [
            'warehouses',
            'inventory_warehouses',
            'stock_warehouses',
            'warehouse_locations',
        ]);
    }

    protected function resolveLocationLabel(PurchaseRequest $purchaseRequest): string
    {
        $direct = $this->firstModelValue($purchaseRequest, [
            'location_label',
            'location_name',
            'reception_location_label',
            'reception_location_name',
            'destination_location_label',
            'destination_location_name',
            'inventory_location_label',
            'inventory_location_name',
        ]);

        if ($direct) {
            return $direct;
        }

        $locationId = $this->firstModelValue($purchaseRequest, [
            'location_id',
            'reception_location_id',
            'destination_location_id',
            'inventory_location_id',
            'stock_location_id',
        ]);

        if (! $locationId) {
            return '—';
        }

        return $this->resolveLabelFromTables((int) $locationId, [
            'inventory_locations',
            'stock_locations',
            'locations',
            'warehouse_locations',
            'storage_locations',
        ]);
    }

    protected function resolveLabelFromTables(int $id, array $tables): string
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $row = DB::table($table)->where('id', $id)->first();

            if (! $row) {
                continue;
            }

            return $this->rowLabel($row);
        }

        return '—';
    }

    protected function rowLabel(object $row): string
    {
        $code = null;
        $name = null;

        foreach (['code', 'short_code', 'internal_reference', 'reference', 'slug'] as $column) {
            if (property_exists($row, $column) && trim((string) $row->{$column}) !== '') {
                $code = trim((string) $row->{$column});
                break;
            }
        }

        foreach (['name', 'display_name', 'label', 'description'] as $column) {
            if (property_exists($row, $column) && trim((string) $row->{$column}) !== '') {
                $name = trim((string) $row->{$column});
                break;
            }
        }

        if ($code && $name && $code !== $name) {
            return $code . ' - ' . $name;
        }

        return $name ?: ($code ?: '—');
    }

    protected function resolveCompanyName(PurchaseRequest $purchaseRequest): string
    {
        $company = $this->companyRow($purchaseRequest);

        if (! $company) {
            return 'BexiaERP';
        }

        foreach (['commercial_name', 'trade_name', 'business_name', 'legal_name', 'name', 'razon_social'] as $column) {
            if (property_exists($company, $column) && trim((string) $company->{$column}) !== '') {
                return trim((string) $company->{$column});
            }
        }

        return 'BexiaERP';
    }

    protected function resolveCompanyLogoDataUri(PurchaseRequest $purchaseRequest): ?string
    {
        $company = $this->companyRow($purchaseRequest);

        if (! $company) {
            return null;
        }

        $logoValue = null;

        foreach ([
            'logo_path',
            'logo',
            'logo_url',
            'logo_file',
            'logo_file_path',
            'image_path',
            'brand_logo',
            'avatar',
            'photo',
        ] as $column) {
            if (property_exists($company, $column) && trim((string) $company->{$column}) !== '') {
                $logoValue = trim((string) $company->{$column});
                break;
            }
        }

        if (! $logoValue) {
            return null;
        }

        if (str_starts_with($logoValue, 'data:image')) {
            return $logoValue;
        }

        if (preg_match('/^https?:\\/\\//i', $logoValue)) {
            return $logoValue;
        }

        $paths = $this->possibleLogoPaths($logoValue);

        foreach ($paths as $path) {
            if (is_file($path)) {
                $mime = function_exists('mime_content_type')
                    ? mime_content_type($path)
                    : $this->mimeFromExtension($path);

                $contents = file_get_contents($path);

                if ($contents === false) {
                    continue;
                }

                return 'data:' . $mime . ';base64,' . base64_encode($contents);
            }
        }

        return null;
    }

    protected function companyRow(PurchaseRequest $purchaseRequest): ?object
    {
        $companyId = $this->firstModelValue($purchaseRequest, [
            'company_id',
            'empresa_id',
            'tenant_id',
        ]);

        if (! $companyId) {
            return null;
        }

        foreach (['companies', 'empresas', 'businesses', 'tenants'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $row = DB::table($table)->where('id', $companyId)->first();

            if ($row) {
                return $row;
            }
        }

        return null;
    }

    protected function possibleLogoPaths(string $value): array
    {
        $value = ltrim($value, '/');

        return array_values(array_unique([
            public_path($value),
            public_path('storage/' . $value),
            public_path(str_replace('public/', 'storage/', $value)),
            storage_path('app/' . $value),
            storage_path('app/public/' . $value),
            base_path($value),
        ]));
    }

    protected function mimeFromExtension(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
    }

    protected function firstModelValue(PurchaseRequest $purchaseRequest, array $columns): mixed
    {
        foreach ($columns as $column) {
            $value = $purchaseRequest->{$column} ?? null;

            if ($value !== null && trim((string) $value) !== '') {
                return $value;
            }
        }

        return null;
    }
}
