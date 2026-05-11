<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseOrderXmlLineMappingController extends Controller
{
    public function edit(PurchaseOrder $purchaseOrder)
    {
        abort_unless(auth()->check(), 403);

        $order = DB::table('purchase_orders')
            ->where('id', $purchaseOrder->getKey())
            ->first();

        abort_if(! $order, 404);

        $this->authorizeTenant($order);

        $tenantId = $this->tenantId($order);

        return view('purchases.map-purchase-order-xml-lines', [
            'order' => $order,
            'lines' => $this->xmlLines((int) $order->id),
            'products' => $this->productOptions($tenantId),
            'variants' => $this->variantOptions($tenantId),
            'tenantId' => $tenantId,
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless(auth()->check(), 403);

        $order = DB::table('purchase_orders')
            ->where('id', $purchaseOrder->getKey())
            ->first();

        abort_if(! $order, 404);

        $this->authorizeTenant($order);

        $mappings = $request->input('mappings', []);

        DB::transaction(function () use ($order, $mappings): void {
            foreach ($mappings as $lineId => $data) {
                $lineId = (int) $lineId;
                $productId = (int) ($data['product_id'] ?? 0);
                $variantId = (int) ($data['variant_id'] ?? 0);

                if ($lineId <= 0 || $productId <= 0) {
                    continue;
                }

                $line = DB::table('purchase_order_lines')
                    ->where('id', $lineId)
                    ->where('purchase_order_id', $order->id)
                    ->first();

                if (! $line) {
                    continue;
                }

                $product = DB::table('products')
                    ->where('id', $productId)
                    ->first();

                if (! $product) {
                    continue;
                }

                $variant = $this->findVariantForProduct($variantId, $productId);

                $columns = Schema::getColumnListing('purchase_order_lines');

                $updates = [];

                $this->set($updates, $columns, 'product_id', $productId);
                $this->set($updates, $columns, 'product_variant_id', $variant?->id);
                $this->set($updates, $columns, 'variant_id', $variant?->id);
                $this->set($updates, $columns, 'product_label', $this->productLabel($product));
                $this->set($updates, $columns, 'variant_label', $variant ? $this->variantLabel($variant) : '—');
                $this->set($updates, $columns, 'xml_requires_mapping', false);
                $this->set($updates, $columns, 'xml_mapping_status', 'mapped');

                if (in_array('updated_at', $columns, true)) {
                    $updates['updated_at'] = now();
                }

                DB::table('purchase_order_lines')
                    ->where('id', $lineId)
                    ->update($updates);
            }

            $this->refreshPendingCount((int) $order->id);
        });

        return redirect('/admin/' . $this->tenantId($order) . '/purchase-orders/' . $order->id . '/edit')
            ->with('success', 'Productos XML mapeados correctamente.');
    }

    protected function xmlLines(int $orderId)
    {
        $query = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $orderId);

        if (Schema::hasColumn('purchase_order_lines', 'xml_description')) {
            $query->whereNotNull('xml_description');
        }

        return $query
            ->orderBy('id')
            ->get();
    }

    protected function productOptions(int $companyId): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        $columns = Schema::getColumnListing('products');

        $query = DB::table('products');

        if ($companyId > 0 && in_array('company_id', $columns, true)) {
            $query->where('company_id', $companyId);
        }

        foreach (['is_active', 'active'] as $activeColumn) {
            if (in_array($activeColumn, $columns, true)) {
                $query->where($activeColumn, true);
            }
        }

        /*
         * En Bexia las variantes viven en products.
         * Para el primer selector mostramos productos padre, no variantes hijas.
         */
        if (in_array('is_variant', $columns, true)) {
            $query->where(function ($q): void {
                $q->whereNull('is_variant')
                    ->orWhere('is_variant', false)
                    ->orWhere('is_variant', 0);
            });
        }

        return $query
            ->orderBy(in_array('name', $columns, true) ? 'name' : 'id')
            ->limit(10000)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'label' => $this->productLabel($row),
            ])
            ->values()
            ->all();
    }

    protected function variantOptions(int $companyId): array
    {
        /*
         * Caso actual de Bexia:
         * No existe product_variants.
         * Las variantes son registros en products con:
         * is_variant = true
         * parent_product_id = producto padre
         */
        if (Schema::hasTable('products')) {
            $columns = Schema::getColumnListing('products');

            if (in_array('is_variant', $columns, true) && in_array('parent_product_id', $columns, true)) {
                $query = DB::table('products')
                    ->where('is_variant', true)
                    ->whereNotNull('parent_product_id');

                if ($companyId > 0 && in_array('company_id', $columns, true)) {
                    $query->where('company_id', $companyId);
                }

                foreach (['is_active', 'active'] as $activeColumn) {
                    if (in_array($activeColumn, $columns, true)) {
                        $query->where($activeColumn, true);
                    }
                }

                return $query
                    ->orderBy('parent_product_id')
                    ->orderBy(in_array('variant_value', $columns, true) ? 'variant_value' : 'id')
                    ->limit(20000)
                    ->get()
                    ->map(fn ($row): array => [
                        'id' => (int) $row->id,
                        'product_id' => (int) ($row->parent_product_id ?? 0),
                        'label' => $this->variantLabel($row),
                    ])
                    ->filter(fn (array $row): bool => $row['id'] > 0 && $row['product_id'] > 0)
                    ->values()
                    ->all();
            }
        }

        /*
         * Fallback por si en otro cliente sí existe tabla product_variants.
         */
        if (! Schema::hasTable('product_variants')) {
            return [];
        }

        $columns = Schema::getColumnListing('product_variants');

        $productIdColumn = null;

        foreach (['product_id', 'product_template_id', 'parent_product_id'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                $productIdColumn = $candidate;
                break;
            }
        }

        if (! $productIdColumn) {
            return [];
        }

        return DB::table('product_variants')
            ->orderBy($productIdColumn)
            ->orderBy(in_array('name', $columns, true) ? 'name' : 'id')
            ->limit(20000)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'product_id' => (int) ($row->{$productIdColumn} ?? 0),
                'label' => $this->variantLabel($row),
            ])
            ->filter(fn (array $row): bool => $row['id'] > 0 && $row['product_id'] > 0)
            ->values()
            ->all();
    }

    protected function findVariantForProduct(int $variantId, int $productId): ?object
    {
        if ($variantId <= 0 || $productId <= 0) {
            return null;
        }

        /*
         * Caso Bexia: variante como producto hijo.
         */
        if (Schema::hasTable('products')) {
            $columns = Schema::getColumnListing('products');

            if (in_array('parent_product_id', $columns, true)) {
                $variant = DB::table('products')
                    ->where('id', $variantId)
                    ->where('parent_product_id', $productId);

                if (in_array('is_variant', $columns, true)) {
                    $variant->where('is_variant', true);
                }

                $found = $variant->first();

                if ($found) {
                    return $found;
                }
            }
        }

        /*
         * Fallback si existiera product_variants.
         */
        if (Schema::hasTable('product_variants')) {
            $columns = Schema::getColumnListing('product_variants');

            $query = DB::table('product_variants')
                ->where('id', $variantId);

            if (in_array('product_id', $columns, true)) {
                $query->where('product_id', $productId);
            }

            return $query->first();
        }

        return null;
    }

    protected function refreshPendingCount(int $orderId): void
    {
        $lineColumns = Schema::getColumnListing('purchase_order_lines');
        $orderColumns = Schema::getColumnListing('purchase_orders');

        $query = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $orderId);

        if (in_array('xml_requires_mapping', $lineColumns, true)) {
            $query->where('xml_requires_mapping', true);
        }

        if (in_array('product_id', $lineColumns, true)) {
            $query->whereNull('product_id');
        }

        $pending = $query->count();

        $updates = [];

        $this->set($updates, $orderColumns, 'xml_mapping_pending_count', $pending);
        $this->set($updates, $orderColumns, 'xml_import_status', $pending > 0 ? 'pending_mapping' : 'mapped');

        if (in_array('updated_at', $orderColumns, true)) {
            $updates['updated_at'] = now();
        }

        DB::table('purchase_orders')
            ->where('id', $orderId)
            ->update($updates);
    }

    protected function productLabel(object $product): string
    {
        return $this->label(
            $product,
            ['internal_reference', 'sku', 'code', 'barcode'],
            ['name', 'description']
        );
    }

    protected function variantLabel(object $variant): string
    {
        return $this->label(
            $variant,
            ['internal_reference', 'sku', 'code', 'barcode'],
            ['variant_value', 'variant_name', 'variant_signature', 'name', 'description']
        );
    }

    protected function label(object $row, array $codeColumns, array $nameColumns): string
    {
        $code = null;
        $name = null;

        foreach ($codeColumns as $column) {
            if (property_exists($row, $column) && trim((string) $row->{$column}) !== '') {
                $code = trim((string) $row->{$column});
                break;
            }
        }

        foreach ($nameColumns as $column) {
            if (property_exists($row, $column) && trim((string) $row->{$column}) !== '') {
                $name = trim((string) $row->{$column});
                break;
            }
        }

        return trim(($code ? $code . ' - ' : '') . ($name ?: ('ID ' . $row->id)));
    }

    protected function tenantId(object $order): int
    {
        if ((int) ($order->company_id ?? 0) > 0) {
            return (int) $order->company_id;
        }

        $tenant = request()->route('tenant');

        return is_numeric($tenant)
            ? (int) $tenant
            : (int) (auth()->user()?->company_id ?? 0);
    }

    protected function authorizeTenant(object $order): void
    {
        $tenant = request()->route('tenant');

        if (is_numeric($tenant) && (int) $tenant > 0 && (int) ($order->company_id ?? 0) > 0) {
            abort_if((int) $tenant !== (int) $order->company_id, 403);
        }
    }

    protected function set(array &$array, array $columns, string $column, mixed $value): void
    {
        if (in_array($column, $columns, true)) {
            $array[$column] = $value;
        }
    }
}
