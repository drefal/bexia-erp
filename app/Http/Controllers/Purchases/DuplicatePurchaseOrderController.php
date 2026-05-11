<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DuplicatePurchaseOrderController extends Controller
{
    public function __invoke(PurchaseOrder $purchaseOrder)
    {
        abort_unless(auth()->check(), 403);

        $newId = DB::transaction(function () use ($purchaseOrder): int {
            $source = DB::table('purchase_orders')
                ->where('id', $purchaseOrder->getKey())
                ->lockForUpdate()
                ->first();

            abort_if(! $source, 404);

            $newNumber = $this->nextPurchaseOrderNumber((int) ($source->company_id ?? 0));

            $orderColumns = Schema::getColumnListing('purchase_orders');

            $skip = [
                'id',
                'number',
                'status',
                'purchase_request_id',
                'approval_request_id',
                'approval_status',
                'current_approval_step',
                'current_step_order',
                'source_snapshot_hash',
                'current_hash',
                'differs_from_request',
                'confirmed_at',
                'sent_at',
                'approved_at',
                'rejected_at',
                'cancelled_at',
                'received_at',
                'completed_at',
                'created_at',
                'updated_at',
            ];

            $newOrder = [];

            foreach ($orderColumns as $column) {
                if (in_array($column, $skip, true)) {
                    continue;
                }

                if (property_exists($source, $column)) {
                    $newOrder[$column] = $source->{$column};
                }
            }

            $newOrder['number'] = $newNumber;
            $newOrder['status'] = 'draft';
            $newOrder['created_at'] = now();
            $newOrder['updated_at'] = now();

            if (in_array('purchase_request_id', $orderColumns, true)) {
                $newOrder['purchase_request_id'] = null;
            }

            if (in_array('duplicated_from_purchase_order_id', $orderColumns, true)) {
                $newOrder['duplicated_from_purchase_order_id'] = $source->id;
            }

            if (in_array('origin', $orderColumns, true)) {
                $newOrder['origin'] = 'Duplicada de ' . ($source->number ?? ('OC #' . $source->id));
            }

            if (in_array('order_date', $orderColumns, true)) {
                $newOrder['order_date'] = now();
            }

            if (in_array('date', $orderColumns, true)) {
                $newOrder['date'] = now();
            }

            if (in_array('differs_from_request', $orderColumns, true)) {
                $newOrder['differs_from_request'] = false;
            }

            if (in_array('source_snapshot_hash', $orderColumns, true)) {
                $newOrder['source_snapshot_hash'] = null;
            }

            if (in_array('current_hash', $orderColumns, true)) {
                $newOrder['current_hash'] = null;
            }

            if (in_array('notes', $orderColumns, true)) {
                $sourceNote = trim((string) ($source->notes ?? ''));
                $newOrder['notes'] = trim(
                    ($sourceNote !== '' ? $sourceNote . "\n\n" : '')
                    . 'Duplicada de ' . ($source->number ?? ('OC #' . $source->id))
                );
            }

            $newId = DB::table('purchase_orders')->insertGetId($newOrder);

            $this->duplicateLines((int) $source->id, $newId);
            $this->recalculateTotals($newId);
            $this->writeHistory($newId, $source);

            return $newId;
        });

        $new = DB::table('purchase_orders')->where('id', $newId)->first();

        $tenantId = (int) ($new->company_id ?? request()->route('tenant') ?? 0);

        return redirect('/admin/' . $tenantId . '/purchase-orders/' . $newId . '/edit');
    }

    protected function duplicateLines(int $sourceOrderId, int $newOrderId): void
    {
        if (! Schema::hasTable('purchase_order_lines')) {
            return;
        }

        $columns = Schema::getColumnListing('purchase_order_lines');

        $skip = [
            'id',
            'purchase_order_id',
            'received_quantity',
            'received_base_quantity',
            'created_at',
            'updated_at',
        ];

        $sourceLines = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $sourceOrderId)
            ->orderBy('id')
            ->get();

        foreach ($sourceLines as $line) {
            $newLine = [];

            foreach ($columns as $column) {
                if (in_array($column, $skip, true)) {
                    continue;
                }

                if (property_exists($line, $column)) {
                    $newLine[$column] = $line->{$column};
                }
            }

            $newLine['purchase_order_id'] = $newOrderId;

            if (in_array('received_quantity', $columns, true)) {
                $newLine['received_quantity'] = 0;
            }

            if (in_array('received_base_quantity', $columns, true)) {
                $newLine['received_base_quantity'] = 0;
            }

            $newLine['created_at'] = now();
            $newLine['updated_at'] = now();

            DB::table('purchase_order_lines')->insert($newLine);
        }
    }

    protected function recalculateTotals(int $purchaseOrderId): void
    {
        if (
            ! Schema::hasTable('purchase_orders')
            || ! Schema::hasTable('purchase_order_lines')
        ) {
            return;
        }

        $orderColumns = Schema::getColumnListing('purchase_orders');
        $lineColumns = Schema::getColumnListing('purchase_order_lines');

        $subtotalColumn = $this->firstExistingColumn($lineColumns, [
            'line_total_without_tax',
            'subtotal_without_tax',
            'subtotal',
            'amount_without_tax',
        ]);

        $taxColumn = $this->firstExistingColumn($lineColumns, [
            'line_tax',
            'tax_amount',
            'iva_amount',
        ]);

        $totalColumn = $this->firstExistingColumn($lineColumns, [
            'line_total_with_tax',
            'total_with_tax',
            'amount_total',
            'total',
        ]);

        $selects = [];

        $selects[] = $subtotalColumn
            ? "COALESCE(SUM($subtotalColumn), 0) as subtotal"
            : "0 as subtotal";

        $selects[] = $taxColumn
            ? "COALESCE(SUM($taxColumn), 0) as tax"
            : "0 as tax";

        $selects[] = $totalColumn
            ? "COALESCE(SUM($totalColumn), 0) as total"
            : "0 as total";

        $totals = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $purchaseOrderId)
            ->selectRaw(implode(', ', $selects))
            ->first();

        $updates = [
            'updated_at' => now(),
        ];

        foreach ([
            'total_without_tax' => 'subtotal',
            'subtotal_without_tax' => 'subtotal',
            'subtotal' => 'subtotal',
            'total_tax' => 'tax',
            'tax_total' => 'tax',
            'iva_total' => 'tax',
            'total_with_tax' => 'total',
            'amount_total' => 'total',
            'total' => 'total',
        ] as $orderColumn => $valueKey) {
            if (in_array($orderColumn, $orderColumns, true)) {
                $updates[$orderColumn] = (float) ($totals->{$valueKey} ?? 0);
            }
        }

        DB::table('purchase_orders')
            ->where('id', $purchaseOrderId)
            ->update($updates);
    }

    protected function writeHistory(int $newOrderId, object $source): void
    {
        if (! class_exists(\App\Support\PurchaseOrderHistory::class)) {
            return;
        }

        \App\Support\PurchaseOrderHistory::log(
            $newOrderId,
            'duplicated',
            null,
            'draft',
            'Orden de compra duplicada desde ' . ($source->number ?? ('OC #' . $source->id)),
            [
                'duplicated_from_purchase_order_id' => (int) $source->id,
                'duplicated_from_purchase_order_number' => (string) ($source->number ?? ''),
                'duplicated_by_user_id' => (int) auth()->id(),
            ]
        );
    }

    protected function nextPurchaseOrderNumber(int $companyId): string
    {
        $prefix = 'OC-' . now()->format('Ymd') . '-';

        $query = DB::table('purchase_orders')
            ->where('number', 'like', $prefix . '%');

        if ($companyId > 0 && Schema::hasColumn('purchase_orders', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        $last = $query
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if ($last && preg_match('/-(\d+)$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    protected function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }
}
