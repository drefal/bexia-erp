<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\PurchaseReceiptInventoryPoster;
use RuntimeException;

class PurchaseOrderReceiptController extends Controller
{
    public function edit(PurchaseOrder $purchaseOrder)
    {
        abort_unless(auth()->check(), 403);

        $order = DB::table('purchase_orders')
            ->where('id', $purchaseOrder->getKey())
            ->first();

        abort_if(! $order, 404);

        $this->authorizeTenant($order);

        if (! $this->canReceive($order)) {
            return redirect('/admin/' . $this->tenantId($order) . '/purchase-orders/' . $order->id . '/edit')
                ->with('error', 'Esta orden no está lista para recepción o no tiene cantidades pendientes.');
        }

        return view('purchases.receive-purchase-order', [
            'order' => $order,
            'lines' => $this->linesForReceipt((int) $order->id),
            'tenantId' => $this->tenantId($order),
        ]);
    }

    public function store(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless(auth()->check(), 403);

        $order = DB::table('purchase_orders')
            ->where('id', $purchaseOrder->getKey())
            ->first();

        abort_if(! $order, 404);

        $this->authorizeTenant($order);

        if (! $this->canReceive($order)) {
            return redirect()
                ->back()
                ->with('error', 'Esta orden no está lista para recepción.');
        }

        $quantities = $request->input('quantities', []);
        $notes = trim((string) $request->input('notes', ''));

        try {
            $receiptId = $this->createReceipt((int) $order->id, $quantities, $notes);

            return redirect('/admin/' . $this->tenantId($order) . '/purchase-orders/' . $order->id . '/edit')
                ->with('success', 'Recepción guardada correctamente. Folio recepción #' . $receiptId . '.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    protected function createReceipt(int $purchaseOrderId, array $quantities, string $notes): int
    {
        return DB::transaction(function () use ($purchaseOrderId, $quantities, $notes): int {
            $order = DB::table('purchase_orders')
                ->where('id', $purchaseOrderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new RuntimeException('No se encontró la orden de compra.');
            }

            $lines = DB::table('purchase_order_lines')
                ->where('purchase_order_id', $purchaseOrderId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $receiptLines = [];
            $totalWithoutTax = 0.0;
            $totalTax = 0.0;
            $totalWithTax = 0.0;

            foreach ($lines as $line) {
                $lineId = (int) $line->id;
                $receiveQty = round((float) ($quantities[$lineId] ?? 0), 6);

                if ($receiveQty <= 0) {
                    continue;
                }

                $orderedQty = (float) ($line->ordered_quantity ?? 0);
                $alreadyReceived = (float) ($line->received_quantity ?? 0);
                $pendingQty = max($orderedQty - $alreadyReceived, 0);

                if ($receiveQty > $pendingQty + 0.000001) {
                    throw new RuntimeException('La cantidad a recibir supera el pendiente para: ' . ($line->product_label ?? 'producto'));
                }

                $baseFactor = $this->baseFactor($line);
                $receivedBaseQty = round($receiveQty * $baseFactor, 6);

                $unitCost = (float) ($line->unit_cost_without_tax ?? 0);
                $taxRate = (float) ($line->tax_rate ?? 0);

                $lineWithoutTax = round($receiveQty * $unitCost, 6);
                $lineTax = round($lineWithoutTax * ($taxRate / 100), 6);
                $lineWithTax = round($lineWithoutTax + $lineTax, 6);

                $receiptLines[] = [
                    'source_line' => $line,
                    'received_quantity' => $receiveQty,
                    'received_base_quantity' => $receivedBaseQty,
                    'line_total_without_tax' => $lineWithoutTax,
                    'line_tax' => $lineTax,
                    'line_total_with_tax' => $lineWithTax,
                ];

                $totalWithoutTax += $lineWithoutTax;
                $totalTax += $lineTax;
                $totalWithTax += $lineWithTax;
            }

            if (count($receiptLines) === 0) {
                throw new RuntimeException('Captura al menos una cantidad a recibir.');
            }

            $receiptId = $this->insertReceipt($order, $notes, $totalWithoutTax, $totalTax, $totalWithTax);

            foreach ($receiptLines as $receiptLine) {
                $this->insertReceiptLine($receiptId, $order, $receiptLine);
                $this->updateOrderLineReceipt($receiptLine['source_line'], $receiptLine['received_quantity'], $receiptLine['received_base_quantity']);
            }

            $this->refreshOrderReceiptStatus($purchaseOrderId);

            app(PurchaseReceiptInventoryPoster::class)->post($receiptId);

            return $receiptId;
        });
    }

    protected function insertReceipt(object $order, string $notes, float $totalWithoutTax, float $totalTax, float $totalWithTax): int
    {
        $columns = Schema::getColumnListing('purchase_receipts');

        $data = [];

        $this->set($data, $columns, 'company_id', $order->company_id ?? null);
        $this->set($data, $columns, 'purchase_order_id', $order->id);
        $this->set($data, $columns, 'number', $this->nextReceiptNumber((int) ($order->company_id ?? 0)));
        $this->set($data, $columns, 'status', 'received');
        $this->set($data, $columns, 'received_at', now());
        $this->set($data, $columns, 'warehouse_id', $order->warehouse_id ?? null);
        $this->set($data, $columns, 'location_id', $order->location_id ?? null);
        $this->set($data, $columns, 'received_by_user_id', auth()->id());
        $this->set($data, $columns, 'total_without_tax', round($totalWithoutTax, 6));
        $this->set($data, $columns, 'total_tax', round($totalTax, 6));
        $this->set($data, $columns, 'total_with_tax', round($totalWithTax, 6));
        $this->set($data, $columns, 'notes', $notes);
        $this->set($data, $columns, 'created_at', now());
        $this->set($data, $columns, 'updated_at', now());

        return DB::table('purchase_receipts')->insertGetId($data);
    }

    protected function insertReceiptLine(int $receiptId, object $order, array $receiptLine): void
    {
        $line = $receiptLine['source_line'];
        $columns = Schema::getColumnListing('purchase_receipt_lines');

        $data = [];

        $this->set($data, $columns, 'purchase_receipt_id', $receiptId);
        $this->set($data, $columns, 'purchase_order_id', $order->id);
        $this->set($data, $columns, 'purchase_order_line_id', $line->id);
        $this->set($data, $columns, 'product_id', $line->product_id ?? null);
        $this->set($data, $columns, 'product_variant_id', $line->product_variant_id ?? null);
        $this->set($data, $columns, 'variant_id', $line->variant_id ?? null);
        $this->set($data, $columns, 'product_label', $line->product_label ?? null);
        $this->set($data, $columns, 'variant_label', $line->variant_label ?? null);
        $this->set($data, $columns, 'purchase_unit_label', $line->purchase_unit_label ?? null);
        $this->set($data, $columns, 'received_quantity', $receiptLine['received_quantity']);
        $this->set($data, $columns, 'received_base_quantity', $receiptLine['received_base_quantity']);
        $this->set($data, $columns, 'unit_cost_without_tax', $line->unit_cost_without_tax ?? 0);
        $this->set($data, $columns, 'tax_rate', $line->tax_rate ?? 0);
        $this->set($data, $columns, 'line_total_without_tax', $receiptLine['line_total_without_tax']);
        $this->set($data, $columns, 'line_tax', $receiptLine['line_tax']);
        $this->set($data, $columns, 'line_total_with_tax', $receiptLine['line_total_with_tax']);
        $this->set($data, $columns, 'created_at', now());
        $this->set($data, $columns, 'updated_at', now());

        DB::table('purchase_receipt_lines')->insert($data);
    }

    protected function updateOrderLineReceipt(object $line, float $receiveQty, float $receiveBaseQty): void
    {
        $columns = Schema::getColumnListing('purchase_order_lines');

        $orderedQty = (float) ($line->ordered_quantity ?? 0);
        $newReceived = round((float) ($line->received_quantity ?? 0) + $receiveQty, 6);
        $newBaseReceived = round((float) ($line->received_base_quantity ?? 0) + $receiveBaseQty, 6);

        if ($newReceived <= 0) {
            $lineStatus = 'pending';
        } elseif ($newReceived + 0.000001 >= $orderedQty) {
            $lineStatus = 'received';
        } else {
            $lineStatus = 'partial';
        }

        $updates = [];

        $this->set($updates, $columns, 'received_quantity', $newReceived);
        $this->set($updates, $columns, 'received_base_quantity', $newBaseReceived);
        $this->set($updates, $columns, 'receipt_status', $lineStatus);
        $this->set($updates, $columns, 'last_received_at', now());
        $this->set($updates, $columns, 'updated_at', now());

        DB::table('purchase_order_lines')
            ->where('id', $line->id)
            ->update($updates);
    }

    protected function refreshOrderReceiptStatus(int $purchaseOrderId): void
    {
        $lines = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $purchaseOrderId)
            ->get();

        $totalOrdered = 0.0;
        $totalReceived = 0.0;

        foreach ($lines as $line) {
            $totalOrdered += (float) ($line->ordered_quantity ?? 0);
            $totalReceived += (float) ($line->received_quantity ?? 0);
        }

        if ($totalOrdered <= 0) {
            $status = 'confirmed';
        } elseif ($totalReceived <= 0) {
            $status = 'confirmed';
        } elseif ($totalReceived + 0.000001 >= $totalOrdered) {
            $status = 'received';
        } else {
            $status = 'partially_received';
        }

        $columns = Schema::getColumnListing('purchase_orders');

        $updates = [];

        $this->set($updates, $columns, 'status', $status);
        $this->set($updates, $columns, 'updated_at', now());

        DB::table('purchase_orders')
            ->where('id', $purchaseOrderId)
            ->update($updates);
    }

    protected function linesForReceipt(int $purchaseOrderId)
    {
        return DB::table('purchase_order_lines')
            ->where('purchase_order_id', $purchaseOrderId)
            ->orderBy('id')
            ->get()
            ->map(function ($line) {
                $ordered = (float) ($line->ordered_quantity ?? 0);
                $received = (float) ($line->received_quantity ?? 0);
                $pending = max($ordered - $received, 0);

                $line->ordered_for_view = $ordered;
                $line->received_for_view = $received;
                $line->pending_for_view = $pending;

                return $line;
            });
    }

    protected function canReceive(object $order): bool
    {
        if (! in_array((string) ($order->status ?? ''), ['confirmed', 'partially_received'], true)) {
            return false;
        }

        if (! Schema::hasTable('purchase_order_lines')) {
            return false;
        }

        $lines = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $order->id)
            ->get();

        foreach ($lines as $line) {
            $ordered = (float) ($line->ordered_quantity ?? 0);
            $received = (float) ($line->received_quantity ?? 0);

            if ($ordered - $received > 0.000001) {
                return true;
            }
        }

        return false;
    }

    protected function baseFactor(object $line): float
    {
        $factor = (float) ($line->purchase_unit_factor ?? 0);

        if ($factor > 0) {
            return $factor;
        }

        $ordered = (float) ($line->ordered_quantity ?? 0);
        $base = (float) ($line->base_quantity ?? 0);

        if ($ordered > 0 && $base > 0) {
            return $base / $ordered;
        }

        return 1.0;
    }

    protected function nextReceiptNumber(int $companyId): string
    {
        $prefix = 'REC-' . now()->format('Ymd') . '-';

        $query = DB::table('purchase_receipts')
            ->where('number', 'like', $prefix . '%');

        if ($companyId > 0 && Schema::hasColumn('purchase_receipts', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        $last = $query->orderByDesc('number')->value('number');
        $next = 1;

        if ($last && preg_match('/-(\d+)$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
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
