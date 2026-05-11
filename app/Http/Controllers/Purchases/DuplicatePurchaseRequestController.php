<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DuplicatePurchaseRequestController extends Controller
{
    public function __invoke(PurchaseRequest $purchaseRequest)
    {
        abort_unless(auth()->check(), 403);

        $newId = DB::transaction(function () use ($purchaseRequest): int {
            $source = DB::table('purchase_requests')
                ->where('id', $purchaseRequest->getKey())
                ->lockForUpdate()
                ->first();

            abort_if(! $source, 404);

            $newNumber = $this->nextPurchaseRequestNumber((int) ($source->company_id ?? 0));

            $requestColumns = Schema::getColumnListing('purchase_requests');

            $skip = [
                'id',
                'number',
                'status',
                'approval_status',
                'approval_request_id',
                'current_approval_step',
                'current_step_order',
                'sent_at',
                'approved_at',
                'rejected_at',
                'cancelled_at',
                'completed_at',
                'created_at',
                'updated_at',
            ];

            $newRequest = [];

            foreach ($requestColumns as $column) {
                if (in_array($column, $skip, true)) {
                    continue;
                }

                if (property_exists($source, $column)) {
                    $newRequest[$column] = $source->{$column};
                }
            }

            $newRequest['number'] = $newNumber;
            $newRequest['status'] = 'draft';
            $newRequest['created_at'] = now();
            $newRequest['updated_at'] = now();

            if (in_array('date', $requestColumns, true)) {
                $newRequest['date'] = now();
            }

            if (in_array('requested_at', $requestColumns, true)) {
                $newRequest['requested_at'] = now();
            }

            if (in_array('duplicated_from_purchase_request_id', $requestColumns, true)) {
                $newRequest['duplicated_from_purchase_request_id'] = $source->id;
            }

            if (in_array('notes', $requestColumns, true)) {
                $sourceNote = trim((string) ($source->notes ?? ''));

                $newRequest['notes'] = trim(
                    ($sourceNote !== '' ? $sourceNote . "\n\n" : '')
                    . 'Duplicada de ' . ($source->number ?? ('SC #' . $source->id))
                );
            }

            $newId = DB::table('purchase_requests')->insertGetId($newRequest);

            $this->duplicateLines((int) $source->id, $newId);
            $this->recalculateTotals($newId);

            return $newId;
        });

        $new = DB::table('purchase_requests')->where('id', $newId)->first();

        $tenantId = (int) ($new->company_id ?? request()->route('tenant') ?? 0);

        return redirect('/admin/' . $tenantId . '/purchase-requests/' . $newId . '/edit');
    }

    protected function duplicateLines(int $sourceRequestId, int $newRequestId): void
    {
        if (! Schema::hasTable('purchase_request_lines')) {
            return;
        }

        $columns = Schema::getColumnListing('purchase_request_lines');

        $skip = [
            'id',
            'purchase_request_id',
            'created_at',
            'updated_at',
        ];

        $sourceLines = DB::table('purchase_request_lines')
            ->where('purchase_request_id', $sourceRequestId)
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

            $newLine['purchase_request_id'] = $newRequestId;
            $newLine['created_at'] = now();
            $newLine['updated_at'] = now();

            DB::table('purchase_request_lines')->insert($newLine);
        }
    }

    protected function recalculateTotals(int $purchaseRequestId): void
    {
        if (
            ! Schema::hasTable('purchase_requests')
            || ! Schema::hasTable('purchase_request_lines')
        ) {
            return;
        }

        $requestColumns = Schema::getColumnListing('purchase_requests');
        $lineColumns = Schema::getColumnListing('purchase_request_lines');

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

        $selects = [
            $subtotalColumn ? "COALESCE(SUM($subtotalColumn), 0) as subtotal" : "0 as subtotal",
            $taxColumn ? "COALESCE(SUM($taxColumn), 0) as tax" : "0 as tax",
            $totalColumn ? "COALESCE(SUM($totalColumn), 0) as total" : "0 as total",
        ];

        $totals = DB::table('purchase_request_lines')
            ->where('purchase_request_id', $purchaseRequestId)
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
        ] as $requestColumn => $valueKey) {
            if (in_array($requestColumn, $requestColumns, true)) {
                $updates[$requestColumn] = (float) ($totals->{$valueKey} ?? 0);
            }
        }

        DB::table('purchase_requests')
            ->where('id', $purchaseRequestId)
            ->update($updates);
    }

    protected function nextPurchaseRequestNumber(int $companyId): string
    {
        $prefix = 'SC-' . now()->format('Ymd') . '-';

        $query = DB::table('purchase_requests')
            ->where('number', 'like', $prefix . '%');

        if ($companyId > 0 && Schema::hasColumn('purchase_requests', 'company_id')) {
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
