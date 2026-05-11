<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Support\PurchaseOrderApprovalEngine;
use App\Support\PurchaseOrderHistory;
use Illuminate\Http\Request;

class ConfirmPurchaseOrderController extends Controller
{
    public function __invoke(Request $request, PurchaseOrder $purchaseOrder)
    {
        // BEXIA_XML_MAPPING_GUARD_V5_15_0
        if (\Illuminate\Support\Facades\Schema::hasTable('purchase_order_lines')
            && \Illuminate\Support\Facades\Schema::hasColumn('purchase_order_lines', 'xml_requires_mapping')) {
            $pendingXmlLines = \Illuminate\Support\Facades\DB::table('purchase_order_lines')
                ->where('purchase_order_id', $purchaseOrder->getKey())
                ->where('xml_requires_mapping', true);

            if (\Illuminate\Support\Facades\Schema::hasColumn('purchase_order_lines', 'product_id')) {
                $pendingXmlLines->whereNull('product_id');
            } elseif (\Illuminate\Support\Facades\Schema::hasColumn('purchase_order_lines', 'product_label')) {
                $pendingXmlLines->where(function ($q): void {
                    $q->whereNull('product_label')
                        ->orWhere('product_label', 'like', 'PENDIENTE%');
                });
            }

            if ($pendingXmlLines->count() > 0) {
                return redirect()
                    ->back()
                    ->with('error', 'No se puede confirmar la OC: hay líneas del XML pendientes de asignar a producto interno.');
            }
        }


        abort_unless(auth()->check(), 403);

        $fromStatus = (string) ($purchaseOrder->status ?? '');

        try {
            $result = PurchaseOrderApprovalEngine::confirmOrSendToReview(
                $purchaseOrder,
                auth()->id()
            );

            $purchaseOrder->refresh();

            $event = match ($result['status'] ?? null) {
                'sent_to_review' => 'sent_to_review',
                'confirmed', 'confirmed_after_approval' => 'confirmed',
                default => 'confirm_order',
            };

            if (class_exists(PurchaseOrderHistory::class)) {
                PurchaseOrderHistory::log(
                    (int) $purchaseOrder->id,
                    $event,
                    $fromStatus,
                    (string) ($purchaseOrder->status ?? ''),
                    $result['message'] ?? 'Orden procesada.',
                    [
                        'total' => (float) ($purchaseOrder->total_with_tax ?? 0),
                        'differs_from_request' => (bool) ($purchaseOrder->differs_from_request ?? false),
                    ]
                );
            }

            return redirect($this->purchaseOrderEditUrl($purchaseOrder))
                ->with('success', $result['message'] ?? 'Orden procesada.');
        } catch (\Throwable $e) {
            if (class_exists(PurchaseOrderHistory::class)) {
                PurchaseOrderHistory::log(
                    (int) $purchaseOrder->id,
                    'approval_error',
                    $fromStatus,
                    (string) ($purchaseOrder->status ?? ''),
                    $e->getMessage()
                );
            }

            return redirect($this->purchaseOrderEditUrl($purchaseOrder))
                ->with('error', $e->getMessage());
        }
    }

    protected function purchaseOrderEditUrl(PurchaseOrder $purchaseOrder): string
    {
        $tenantId = (int) ($purchaseOrder->company_id ?? 0);

        if ($tenantId <= 0) {
            $tenantId = (int) (auth()->user()?->company_id ?? 0);
        }

        return url('/admin/' . $tenantId . '/purchase-orders/' . $purchaseOrder->getKey() . '/edit');
    }
}
