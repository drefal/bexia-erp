<?php

namespace App\Support;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PurchaseOrderApprovalEngine
{
    public static function initializeSourceSnapshot(int $purchaseOrderId): void
    {
        $order = PurchaseOrder::query()->find($purchaseOrderId);

        if (! $order) {
            return;
        }

        $requestHash = static::purchaseRequestHashForOrder($order);
        $orderHash = static::purchaseOrderHash($order);

        $data = [
            'source_snapshot_hash' => $order->source_snapshot_hash ?: $requestHash,
            'current_hash' => $orderHash,
            'differs_from_request' => $requestHash !== $orderHash,
            'updated_at' => now(),
        ];

        DB::table('purchase_orders')
            ->where('id', $order->id)
            ->update(static::filterColumns('purchase_orders', $data));
    }

    public static function confirmOrSendToReview(PurchaseOrder $order, ?int $actorUserId = null): array
    {
        static::recalculateLinesAndTotals($order);

        $order->refresh();

        $requestHash = $order->source_snapshot_hash ?: static::purchaseRequestHashForOrder($order);
        $orderHash = static::purchaseOrderHash($order);

        DB::table('purchase_orders')
            ->where('id', $order->id)
            ->update(static::filterColumns('purchase_orders', [
                'source_snapshot_hash' => $requestHash,
                'current_hash' => $orderHash,
                'differs_from_request' => $requestHash !== $orderHash,
                'updated_at' => now(),
            ]));

        if ($requestHash === $orderHash) {
            static::markConfirmed($order->id, $actorUserId, false);

            return [
                'status' => 'confirmed',
                'message' => 'La orden de compra coincide con la solicitud y fue confirmada.',
            ];
        }

        if (static::hasApprovedCurrentChange($order, $orderHash)) {
            static::markConfirmed($order->id, $actorUserId, true);

            return [
                'status' => 'confirmed_after_approval',
                'message' => 'La orden de compra modificada ya tenía aprobación y fue confirmada.',
            ];
        }

        $approvalId = static::sendToReview($order, $orderHash);

        return [
            'status' => 'sent_to_review',
            'approval_request_id' => $approvalId,
            'message' => 'La orden de compra cambió contra la solicitud y fue enviada a aprobación.',
        ];
    }

    public static function recalculateLinesAndTotals(PurchaseOrder $order): void
    {
        if (! Schema::hasTable('purchase_order_lines')) {
            return;
        }

        $lines = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $order->id)
            ->get();

        foreach ($lines as $line) {
            $qty = (float) ($line->ordered_quantity ?? 0);
            $factor = (float) ($line->purchase_unit_factor ?? 1);
            $baseQty = round($qty * max($factor, 1), 6);

            $unitWithoutTax = (float) ($line->unit_cost_without_tax ?? 0);
            $taxRate = (float) ($line->tax_rate ?? 0);
            $unitWithTax = round($unitWithoutTax * (1 + ($taxRate / 100)), 6);

            $lineWithoutTax = round($qty * $unitWithoutTax, 6);
            $lineWithTax = round($qty * $unitWithTax, 6);
            $lineTax = max(0, round($lineWithTax - $lineWithoutTax, 6));

            DB::table('purchase_order_lines')
                ->where('id', $line->id)
                ->update(static::filterColumns('purchase_order_lines', [
                    'base_quantity' => $baseQty,
                    'unit_cost_with_tax' => $unitWithTax,
                    'line_total_without_tax' => $lineWithoutTax,
                    'line_tax' => $lineTax,
                    'line_total_with_tax' => $lineWithTax,
                    'updated_at' => now(),
                ]));
        }

        $totals = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $order->id)
            ->selectRaw('
                COALESCE(SUM(line_total_without_tax), 0) as subtotal,
                COALESCE(SUM(line_tax), 0) as tax,
                COALESCE(SUM(line_total_with_tax), 0) as total
            ')
            ->first();

        DB::table('purchase_orders')
            ->where('id', $order->id)
            ->update(static::filterColumns('purchase_orders', [
                'total_without_tax' => (float) ($totals->subtotal ?? 0),
                'total_tax' => (float) ($totals->tax ?? 0),
                'total_with_tax' => (float) ($totals->total ?? 0),
                'updated_at' => now(),
            ]));
    }

    public static function purchaseOrderHash(PurchaseOrder $order): string
    {
        $lines = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $order->id)
            ->orderBy('id')
            ->get()
            ->map(fn ($line): array => [
                'product_id' => (int) ($line->product_id ?? 0),
                'product_variant_id' => (int) ($line->product_variant_id ?? 0),
                'unit' => (string) ($line->purchase_unit_label ?? ''),
                'factor' => static::num($line->purchase_unit_factor ?? 1),
                'sat' => (string) ($line->sat_unit_key ?? ''),
                'qty' => static::num($line->ordered_quantity ?? 0),
                'base_qty' => static::num($line->base_quantity ?? 0),
                'cost' => static::num($line->unit_cost_without_tax ?? 0),
                'tax' => static::num($line->tax_rate ?? 0),
            ])
            ->values()
            ->all();

        return hash('sha256', json_encode($lines, JSON_UNESCAPED_UNICODE));
    }

    public static function purchaseRequestHashForOrder(PurchaseOrder $order): string
    {
        if (! $order->purchase_request_id || ! Schema::hasTable('purchase_request_lines')) {
            return '';
        }

        $lines = DB::table('purchase_request_lines')
            ->where('purchase_request_id', $order->purchase_request_id)
            ->orderBy('id')
            ->get()
            ->map(fn ($line): array => [
                'product_id' => (int) ($line->product_id ?? 0),
                'product_variant_id' => (int) ($line->product_variant_id ?? 0),
                'unit' => (string) ($line->purchase_unit_label ?? ''),
                'factor' => static::num($line->purchase_unit_factor ?? 1),
                'sat' => (string) ($line->sat_unit_key ?? ''),
                'qty' => static::num($line->requested_quantity ?? 0),
                'base_qty' => static::num($line->base_quantity ?? 0),
                'cost' => static::num($line->unit_cost_without_tax ?? 0),
                'tax' => static::num($line->tax_rate ?? 0),
            ])
            ->values()
            ->all();

        return hash('sha256', json_encode($lines, JSON_UNESCAPED_UNICODE));
    }

    protected static function sendToReview(PurchaseOrder $order, string $orderHash): int
    {
        if (! Schema::hasTable('approval_workflows') || ! Schema::hasTable('approval_workflow_steps')) {
            throw new RuntimeException('No existen tablas de flujos de aprobación.');
        }

        if (! Schema::hasTable('approval_requests') || ! Schema::hasTable('approval_request_steps')) {
            throw new RuntimeException('No existen tablas de solicitudes de aprobación.');
        }

        $workflow = static::findWorkflow($order);

        if (! $workflow) {
            throw new RuntimeException('La OC cambió contra la solicitud, pero no existe un flujo activo para Órdenes de compra.');
        }

        $steps = static::workflowSteps($workflow, $order);

        if ($steps->isEmpty()) {
            throw new RuntimeException('El flujo de Órdenes de compra no tiene etapas activas.');
        }

        $approvalId = static::createOrUpdateApprovalRequest($order, $workflow, $steps);
        static::syncApprovalSteps($approvalId, $steps);

        DB::table('purchase_orders')
            ->where('id', $order->id)
            ->update(static::filterColumns('purchase_orders', [
                'status' => 'review',
                'approval_hash' => $orderHash,
                'differs_from_request' => true,
                'approval_required_reason' => 'La orden de compra fue modificada contra la solicitud aprobada.',
                'submitted_for_approval_at' => now(),
                'updated_at' => now(),
            ]));

        return $approvalId;
    }

    protected static function findWorkflow(PurchaseOrder $order): ?object
    {
        $query = DB::table('approval_workflows')
            ->where('document_type', 'purchase_order');

        if (Schema::hasColumn('approval_workflows', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('approval_workflows', 'company_id') && $order->company_id) {
            $query->where(function ($q) use ($order) {
                $q->where('company_id', $order->company_id)->orWhereNull('company_id');
            });
        }

        $amount = (float) ($order->total_with_tax ?? 0);

        if (Schema::hasColumn('approval_workflows', 'amount_min')) {
            $query->where(function ($q) use ($amount) {
                $q->whereNull('amount_min')->orWhere('amount_min', '<=', $amount);
            });
        }

        if (Schema::hasColumn('approval_workflows', 'amount_max')) {
            $query->where(function ($q) use ($amount) {
                $q->whereNull('amount_max')->orWhere('amount_max', '>=', $amount);
            });
        }

        if (Schema::hasColumn('approval_workflows', 'priority')) {
            $query->orderBy('priority');
        }

        return $query->orderBy('id')->first();
    }

    protected static function workflowSteps(object $workflow, PurchaseOrder $order)
    {
        $query = DB::table('approval_workflow_steps')
            ->where('approval_workflow_id', $workflow->id);

        if (Schema::hasColumn('approval_workflow_steps', 'is_active')) {
            $query->where('is_active', true);
        }

        $amount = (float) ($order->total_with_tax ?? 0);

        if (Schema::hasColumn('approval_workflow_steps', 'amount_min')) {
            $query->where(function ($q) use ($amount) {
                $q->whereNull('amount_min')->orWhere('amount_min', '<=', $amount);
            });
        }

        if (Schema::hasColumn('approval_workflow_steps', 'amount_max')) {
            $query->where(function ($q) use ($amount) {
                $q->whereNull('amount_max')->orWhere('amount_max', '>=', $amount);
            });
        }

        return $query
            ->orderBy(Schema::hasColumn('approval_workflow_steps', 'sort_order') ? 'sort_order' : 'id')
            ->get();
    }

    protected static function createOrUpdateApprovalRequest(PurchaseOrder $order, object $workflow, $steps): int
    {
        $approvableType = 'App\\Models\\PurchaseOrder';

        $existing = DB::table('approval_requests')
            ->where(function ($q) use ($order, $approvableType) {
                $q->where(function ($qq) use ($order, $approvableType) {
                    $qq->where('approvable_type', $approvableType)
                        ->where('approvable_id', $order->id);
                })->orWhere(function ($qq) use ($order) {
                    $qq->where('document_type', 'purchase_order')
                        ->where('document_number', $order->number);
                });
            })
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();

        $firstStep = $steps->first();
        $firstOrder = (int) ($firstStep->sort_order ?? 1);

        $requesterName = null;

        if ($order->created_by_user_id && Schema::hasTable('users')) {
            $requesterName = DB::table('users')->where('id', $order->created_by_user_id)->value('name');
        }

        $data = [
            'company_id' => $order->company_id,
            'approval_workflow_id' => $workflow->id,
            'approvable_type' => $approvableType,
            'approvable_id' => $order->id,
            'document_type' => 'purchase_order',
            'document_number' => $order->number,
            'requester_user_id' => $order->created_by_user_id,
            'requester_name' => $requesterName,
            'status' => 'pending',
            'current_step_order' => $firstOrder,
            'amount_total' => (float) ($order->total_with_tax ?? 0),
            'sent_at' => now(),
            'updated_at' => now(),
        ];

        $data = static::filterColumns('approval_requests', $data);

        if ($existing) {
            DB::table('approval_requests')->where('id', $existing->id)->update($data);

            return (int) $existing->id;
        }

        $data['created_at'] = now();

        return (int) DB::table('approval_requests')->insertGetId($data);
    }

    protected static function syncApprovalSteps(int $approvalRequestId, $steps): void
    {
        DB::table('approval_request_steps')
            ->where('approval_request_id', $approvalRequestId)
            ->delete();

        $firstOrder = null;

        foreach ($steps as $index => $step) {
            $order = (int) ($step->sort_order ?? ($index + 1));

            if ($firstOrder === null) {
                $firstOrder = $order;
            }

            $data = [
                'approval_request_id' => $approvalRequestId,
                'approval_workflow_step_id' => $step->id,
                'step_order' => $order,
                'step_name' => $step->name ?? ('Etapa ' . $order),
                'approver_type' => $step->approver_type ?? null,
                'approver_user_id' => $step->approver_user_id ?? null,
                'approver_role_name' => $step->approver_role_name ?? null,
                'status' => $order === $firstOrder ? 'pending' : 'waiting',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table('approval_request_steps')->insert(
                static::filterColumns('approval_request_steps', $data)
            );
        }
    }

    protected static function hasApprovedCurrentChange(PurchaseOrder $order, string $orderHash): bool
    {
        if ((string) ($order->approval_hash ?? '') !== $orderHash) {
            return false;
        }

        if (! Schema::hasTable('approval_requests')) {
            return false;
        }

        return DB::table('approval_requests')
            ->where('document_type', 'purchase_order')
            ->where('document_number', $order->number)
            ->where('status', 'approved')
            ->exists();
    }

    protected static function markConfirmed(int $orderId, ?int $actorUserId, bool $wasReapproved): void
    {
        DB::table('purchase_orders')
            ->where('id', $orderId)
            ->update(static::filterColumns('purchase_orders', [
                'status' => 'confirmed',
                'differs_from_request' => $wasReapproved,
                'approval_required_reason' => null,
                'confirmed_at' => now(),
                'confirmed_by_user_id' => $actorUserId,
                'updated_at' => now(),
            ]));
    }

    protected static function num($value): string
    {
        return number_format((float) $value, 6, '.', '');
    }

    protected static function filterColumns(string $table, array $data): array
    {
        return array_filter(
            $data,
            fn ($value, $key) => Schema::hasColumn($table, $key),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
