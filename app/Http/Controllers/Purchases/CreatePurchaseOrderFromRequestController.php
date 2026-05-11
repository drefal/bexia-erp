<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseOrderFromRequestController extends Controller
{
    public function __invoke(Request $request, PurchaseRequest $purchaseRequest)
    {
        abort_unless(auth()->check(), 403);

        $status = (string) ($purchaseRequest->status ?? '');

        abort_unless(
            in_array($status, ['approved', 'aprobada'], true),
            422,
            'La solicitud debe estar aprobada para crear una orden de compra.'
        );

        $existing = PurchaseOrder::query()
            ->where('purchase_request_id', $purchaseRequest->getKey())
            ->first();

        if ($existing) {
            return redirect($this->purchaseOrderEditUrl(
                (int) $existing->id,
                (int) ($existing->company_id ?? $purchaseRequest->company_id ?? 0)
            ));
        }

        $orderId = DB::transaction(function () use ($purchaseRequest) {
            $requestRow = DB::table('purchase_requests')
                ->where('id', $purchaseRequest->getKey())
                ->first();

            $number = $this->nextPurchaseOrderNumber();

            $orderData = [
                'company_id' => $requestRow->company_id ?? null,
                'purchase_request_id' => $requestRow->id,
                'number' => $number,
                'status' => 'draft',
                'supplier_contact_id' => $requestRow->supplier_contact_id ?? null,
                'supplier_name' => $requestRow->supplier_name ?? 'Sin proveedor',
                'warehouse_id' => $requestRow->warehouse_id ?? null,
                'location_id' => $requestRow->location_id ?? null,
                'warehouse_label' => $requestRow->warehouse_label ?? null,
                'location_label' => $requestRow->location_label ?? null,
                'order_date' => now(),
                'currency' => $requestRow->currency ?? 'MXN',
                'origin' => $requestRow->number ?? null,
                'total_without_tax' => (float) ($requestRow->total_without_tax ?? 0),
                'total_tax' => (float) ($requestRow->total_tax ?? 0),
                'total_with_tax' => (float) ($requestRow->total_with_tax ?? 0),
                'notes' => $requestRow->notes ?? null,
                'created_by_user_id' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $orderId = DB::table('purchase_orders')->insertGetId(
                $this->filterColumns('purchase_orders', $orderData)
            );

            $requestLines = DB::table('purchase_request_lines')
                ->where('purchase_request_id', $requestRow->id)
                ->orderBy('id')
                ->get();

            foreach ($requestLines as $line) {
                $qty = (float) ($line->requested_quantity ?? 0);
                $baseQty = (float) ($line->base_quantity ?? $qty);
                $unitWithoutTax = (float) ($line->unit_cost_without_tax ?? 0);
                $taxRate = (float) ($line->tax_rate ?? 0);
                $unitWithTax = (float) ($line->unit_cost_with_tax ?? ($unitWithoutTax * (1 + ($taxRate / 100))));
                $lineWithoutTax = (float) ($line->line_total_without_tax ?? ($qty * $unitWithoutTax));
                $lineWithTax = (float) ($line->line_total_with_tax ?? ($qty * $unitWithTax));
                $lineTax = (float) ($line->line_tax ?? max(0, $lineWithTax - $lineWithoutTax));

                $lineData = [
                    'purchase_order_id' => $orderId,
                    'company_id' => $requestRow->company_id ?? null,
                    'product_id' => $line->product_id ?? null,
                    'product_variant_id' => $line->product_variant_id ?? null,
                    'product_label' => $line->product_label ?? null,
                    'variant_label' => $line->variant_label ?? null,
                    'purchase_unit_type' => $line->purchase_unit_type ?? null,
                    'purchase_unit_label' => $line->purchase_unit_label ?? null,
                    'purchase_unit_factor' => (float) ($line->purchase_unit_factor ?? 1),
                    'sat_unit_key' => $line->sat_unit_key ?? null,
                    'sat_unit_name' => $line->sat_unit_name ?? null,
                    'ordered_quantity' => $qty,
                    'base_quantity' => $baseQty,
                    'received_quantity' => 0,
                    'received_base_quantity' => 0,
                    'unit_cost_without_tax' => $unitWithoutTax,
                    'tax_rate' => $taxRate,
                    'unit_cost_with_tax' => $unitWithTax,
                    'line_total_without_tax' => $lineWithoutTax,
                    'line_tax' => $lineTax,
                    'line_total_with_tax' => $lineWithTax,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                DB::table('purchase_order_lines')->insert(
                    $this->filterColumns('purchase_order_lines', $lineData)
                );
            }

            $this->recalculateTotals($orderId);

            if (class_exists(\App\Support\PurchaseOrderApprovalEngine::class)) {
                \App\Support\PurchaseOrderApprovalEngine::initializeSourceSnapshot((int) $orderId);
            }

            return $orderId;
        });

        $tenantId = (int) ($purchaseRequest->company_id ?? 0);

        if ($tenantId <= 0) {
            $tenantId = (int) DB::table('purchase_orders')
                ->where('id', $orderId)
                ->value('company_id');
        }

        return redirect($this->purchaseOrderEditUrl((int) $orderId, $tenantId));
    }

    protected function purchaseOrderEditUrl(int $purchaseOrderId, int $tenantId): string
    {
        if ($tenantId <= 0) {
            $tenantId = (int) (auth()->user()?->company_id ?? 0);
        }

        if ($tenantId <= 0) {
            $tenantId = (int) DB::table('purchase_orders')
                ->where('id', $purchaseOrderId)
                ->value('company_id');
        }

        return url('/admin/' . $tenantId . '/purchase-orders/' . $purchaseOrderId . '/edit');
    }

    protected function nextPurchaseOrderNumber(): string
    {
        $prefix = 'OC-' . now()->format('Ymd') . '-';

        $last = DB::table('purchase_orders')
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if ($last && preg_match('/(\d+)$/', $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    protected function recalculateTotals(int $orderId): void
    {
        $totals = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $orderId)
            ->selectRaw('
                COALESCE(SUM(line_total_without_tax), 0) as subtotal,
                COALESCE(SUM(line_tax), 0) as tax,
                COALESCE(SUM(line_total_with_tax), 0) as total
            ')
            ->first();

        DB::table('purchase_orders')
            ->where('id', $orderId)
            ->update([
                'total_without_tax' => (float) ($totals->subtotal ?? 0),
                'total_tax' => (float) ($totals->tax ?? 0),
                'total_with_tax' => (float) ($totals->total ?? 0),
                'updated_at' => now(),
            ]);
    }

    protected function filterColumns(string $table, array $data): array
    {
        return array_filter(
            $data,
            fn ($value, $key) => Schema::hasColumn($table, $key),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
