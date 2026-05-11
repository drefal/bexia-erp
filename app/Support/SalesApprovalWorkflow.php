<?php

namespace App\Support;

use App\Models\SaleOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesApprovalWorkflow
{
    public static function canReturnToQuote(object $order): bool
    {
        $status = (string) ($order->status ?? '');

        if ($status === 'draft') {
            return false;
        }

        $delivered = (float) ($order->delivered_total_quantity ?? 0);
        $invoiceStatus = (string) ($order->invoice_status ?? 'not_invoiced');
        $paymentStatus = (string) ($order->payment_status ?? 'unpaid');

        if ($delivered > 0) {
            return false;
        }

        if (! in_array($invoiceStatus, ['not_invoiced', '', null], true)) {
            return false;
        }

        if (! in_array($paymentStatus, ['unpaid', '', null], true)) {
            return false;
        }

        return true;
    }

    public static function returnToQuote(object $order): void
    {
        if (! static::canReturnToQuote($order)) {
            throw new \RuntimeException('No se puede regresar a cotización porque ya tiene entrega, factura o pago.');
        }

        $summary = static::approvalRequirementSummary($order);

        DB::table('sales_orders')
            ->where('id', $order->id)
            ->update(static::filterColumns('sales_orders', [
                'status' => 'draft',
                'confirmed_at' => null,
                'confirmed_by_user_id' => null,
                'margin_approval_required' => $summary['requires_approval'],
                'margin_approval_status' => $summary['requires_approval'] ? 'required' : 'not_required',
                'margin_approval_reason' => $summary['reason'],
                'margin_approval_requested_at' => null,
                'margin_approved_by_user_id' => null,
                'margin_approved_at' => null,
                'margin_rejected_by_user_id' => null,
                'margin_rejected_at' => null,
                'margin_rejection_reason' => null,
                'updated_at' => now(),
            ]));

        if (Schema::hasTable('approval_requests')) {
            DB::table('approval_requests')
                ->where('approvable_type', SaleOrder::class)
                ->where('approvable_id', $order->id)
                ->whereIn('document_type', ['sales_quote', 'sales_order', 'sales_margin_approval'])
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                    'completed_at' => now(),
                    'last_decision_reason' => 'Documento regresado a cotización.',
                    'updated_at' => now(),
                ]);
        }
    }

    public static function ensureCanConfirm(SaleOrder $order): array
    {
        $summary = static::approvalRequirementSummary($order);

        if (! $summary['requires_approval']) {
            DB::table('sales_orders')
                ->where('id', $order->id)
                ->update(static::filterColumns('sales_orders', [
                    'margin_approval_required' => false,
                    'margin_approval_status' => 'not_required',
                    'margin_approval_reason' => null,
                    'updated_at' => now(),
                ]));

            return [
                'ok' => true,
                'message' => 'No requiere aprobación.',
            ];
        }

        if (static::approvedRequestExists($order)) {
            DB::table('sales_orders')
                ->where('id', $order->id)
                ->update(static::filterColumns('sales_orders', [
                    'margin_approval_required' => true,
                    'margin_approval_status' => 'approved',
                    'margin_approval_reason' => $summary['reason'],
                    'margin_approved_at' => now(),
                    'updated_at' => now(),
                ]));

            return [
                'ok' => true,
                'message' => 'Aprobación encontrada. Ya se puede confirmar.',
            ];
        }

        $workflow = $summary['workflow'];

        if (! $workflow) {
            DB::table('sales_orders')
                ->where('id', $order->id)
                ->update(static::filterColumns('sales_orders', [
                    'margin_approval_required' => true,
                    'margin_approval_status' => 'required',
                    'margin_approval_reason' => $summary['reason'],
                    'updated_at' => now(),
                ]));

            return [
                'ok' => false,
                'message' => 'La cotización requiere aprobación, pero no hay flujo activo aplicable.',
            ];
        }

        $request = static::createOrGetPendingRequest($order, $workflow, $summary['reason']);

        DB::table('sales_orders')
            ->where('id', $order->id)
            ->update(static::filterColumns('sales_orders', [
                'margin_approval_required' => true,
                'margin_approval_status' => 'pending',
                'margin_approval_user_id' => static::firstApproverUserId($workflow),
                'margin_approval_reason' => $summary['reason'],
                'margin_approval_requested_at' => now(),
                'updated_at' => now(),
            ]));

        return [
            'ok' => false,
            'message' => 'Cotización enviada a aprobación: ' . ($request->document_number ?? $order->number),
        ];
    }

    public static function approvalRequirementSummary(object $order): array
    {
        $risk = static::marginRiskSummary($order);

        if ($risk['requires_approval']) {
            $workflow = static::findApplicableWorkflow($order, ['sales_margin_approval', 'sales_quote']);

            return [
                'requires_approval' => true,
                'reason' => $risk['reason'],
                'workflow' => $workflow,
                'source' => 'margin',
            ];
        }

        $quoteWorkflow = static::findApplicableWorkflow($order, ['sales_quote']);

        if ($quoteWorkflow) {
            return [
                'requires_approval' => true,
                'reason' => 'La cotización requiere aprobación según el flujo configurado.',
                'workflow' => $quoteWorkflow,
                'source' => 'sales_quote',
            ];
        }

        return [
            'requires_approval' => false,
            'reason' => null,
            'workflow' => null,
            'source' => null,
        ];
    }

    public static function marginRiskSummary(object $order): array
    {
        if (! Schema::hasTable('sales_order_lines')) {
            return [
                'requires_approval' => false,
                'warning_count' => 0,
                'danger_count' => 0,
                'reason' => null,
            ];
        }

        $risk = DB::table('sales_order_lines')
            ->where('sales_order_id', $order->id)
            ->whereIn('margin_status', ['warning', 'danger'])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN margin_status = 'danger' THEN 1 ELSE 0 END), 0) as danger_count,
                COALESCE(SUM(CASE WHEN margin_status = 'warning' THEN 1 ELSE 0 END), 0) as warning_count
            ")
            ->first();

        $danger = (int) ($risk->danger_count ?? 0);
        $warning = (int) ($risk->warning_count ?? 0);

        $parts = [];

        if ($danger > 0) {
            $parts[] = "{$danger} línea(s) con precio debajo del costo";
        }

        if ($warning > 0) {
            $parts[] = "{$warning} línea(s) con margen bajo";
        }

        return [
            'requires_approval' => ($danger + $warning) > 0,
            'warning_count' => $warning,
            'danger_count' => $danger,
            'reason' => count($parts) > 0 ? implode('. ', $parts) . '.' : null,
        ];
    }

    public static function findApplicableWorkflow(object $order, array $documentTypes): ?object
    {
        if (! Schema::hasTable('approval_workflows')) {
            return null;
        }

        $companyId = (int) ($order->company_id ?? 0);
        $amount = (float) ($order->total_with_tax ?? 0);
        $warehouseId = (int) ($order->warehouse_id ?? 0);
        $requesterId = (int) (($order->created_by_user_id ?? null) ?: auth()->id());

        $query = DB::table('approval_workflows')
            ->whereIn('document_type', $documentTypes)
            ->where('is_active', true);

        if ($companyId > 0 && Schema::hasColumn('approval_workflows', 'company_id')) {
            $query->where('company_id', $companyId);
        }

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

        if (Schema::hasColumn('approval_workflows', 'applies_to_user_id') && $requesterId > 0) {
            $query->where(function ($q) use ($requesterId) {
                $q->whereNull('applies_to_user_id')->orWhere('applies_to_user_id', $requesterId);
            });
        }

        if (Schema::hasColumn('approval_workflows', 'applies_to_warehouse_id') && $warehouseId > 0) {
            $query->where(function ($q) use ($warehouseId) {
                $q->whereNull('applies_to_warehouse_id')->orWhere('applies_to_warehouse_id', $warehouseId);
            });
        }

        return $query
            ->orderBy('priority')
            ->orderBy('id')
            ->first();
    }

    public static function firstApproverUserId(?object $workflow): ?int
    {
        if (! $workflow || ! Schema::hasTable('approval_workflow_steps')) {
            return null;
        }

        $step = DB::table('approval_workflow_steps')
            ->where('approval_workflow_id', $workflow->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if (! $step) {
            return null;
        }

        if ((string) ($step->approver_type ?? '') === 'specific_user' && ! empty($step->approver_user_id)) {
            return (int) $step->approver_user_id;
        }

        if ((string) ($step->approver_type ?? '') === 'role' && ! empty($step->approver_role_name)) {
            if (! Schema::hasTable('users') || ! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
                return null;
            }

            $id = DB::table('users')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', $step->approver_role_name)
                ->orderBy('users.id')
                ->value('users.id');

            return $id ? (int) $id : null;
        }

        return null;
    }

    public static function approvedRequestExists(object $order): bool
    {
        if (! Schema::hasTable('approval_requests')) {
            return false;
        }

        return DB::table('approval_requests')
            ->where('approvable_type', SaleOrder::class)
            ->where('approvable_id', $order->id)
            ->whereIn('document_type', ['sales_quote', 'sales_order', 'sales_margin_approval'])
            ->where('status', 'approved')
            ->exists();
    }

    public static function createOrGetPendingRequest(object $order, object $workflow, ?string $reason): object
    {
        if (! Schema::hasTable('approval_requests') || ! Schema::hasTable('approval_request_steps')) {
            throw new \RuntimeException('No existen tablas de solicitudes de aprobación.');
        }

        $existing = DB::table('approval_requests')
            ->where('approvable_type', SaleOrder::class)
            ->where('approvable_id', $order->id)
            ->whereIn('document_type', ['sales_quote', 'sales_order', 'sales_margin_approval'])
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $requester = auth()->user();
        $now = now();

        $requestId = DB::table('approval_requests')->insertGetId(static::filterColumns('approval_requests', [
            'company_id' => (int) ($order->company_id ?? 0),
            'approval_workflow_id' => (int) $workflow->id,
            'approvable_type' => SaleOrder::class,
            'approvable_id' => (int) $order->id,
            'document_type' => (string) $workflow->document_type,
            'document_number' => (string) ($order->number ?? ('Venta #' . $order->id)),
            'requester_user_id' => $requester?->id,
            'requester_name' => $requester?->name,
            'status' => 'pending',
            'current_step_order' => 1,
            'amount_total' => (float) ($order->total_with_tax ?? 0),
            'sent_at' => $now,
            'notes' => $reason,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        $steps = Schema::hasTable('approval_workflow_steps')
            ? DB::table('approval_workflow_steps')
                ->where('approval_workflow_id', $workflow->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : collect();

        foreach ($steps as $step) {
            DB::table('approval_request_steps')->insert(static::filterColumns('approval_request_steps', [
                'approval_request_id' => $requestId,
                'approval_workflow_step_id' => $step->id,
                'step_order' => (int) ($step->sort_order ?? 1),
                'step_name' => (string) ($step->name ?? 'Aprobación'),
                'approver_type' => (string) ($step->approver_type ?? 'specific_user'),
                'approver_user_id' => $step->approver_user_id ?? null,
                'approver_role_name' => $step->approver_role_name ?? null,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $createdRequest = DB::table('approval_requests')->where('id', $requestId)->first();

        static::logEvent(
            $order,
            'approval_requested',
            'Solicitud enviada a aprobación',
            'Se envió la cotización al flujo de aprobación.',
            ['approval_request_id' => $requestId, 'document_type' => $workflow->document_type]
        );

        return $createdRequest;
    }

    public static function markSalesApprovedFromRequest(object $request, ?int $userId = null): void
    {
        if (! Schema::hasTable('sales_orders')) {
            return;
        }

        if (! in_array((string) ($request->document_type ?? ''), ['sales_quote', 'sales_order', 'sales_margin_approval'], true)) {
            return;
        }

        DB::table('sales_orders')
            ->where('id', $request->approvable_id)
            ->update(static::filterColumns('sales_orders', [
                'margin_approval_required' => true,
                'margin_approval_status' => 'approved',
                'margin_approved_by_user_id' => $userId ?: auth()->id(),
                'margin_approved_at' => now(),
                'updated_at' => now(),
            ]));

        static::confirmApprovedQuoteFromRequest($request, $userId ?: auth()->id());

        static::refreshApprovalSnapshot((int) ($request->approvable_id ?? 0), $userId ?: auth()->id());
    }

    public static function markSalesRejectedFromRequest(object $request, ?string $reason = null, ?int $userId = null): void
    {
        if (! Schema::hasTable('sales_orders')) {
            return;
        }

        if (! in_array((string) ($request->document_type ?? ''), ['sales_quote', 'sales_order', 'sales_margin_approval'], true)) {
            return;
        }

        DB::table('sales_orders')
            ->where('id', $request->approvable_id)
            ->update(static::filterColumns('sales_orders', [
                'margin_approval_required' => true,
                'margin_approval_status' => 'rejected',
                'margin_rejected_by_user_id' => $userId ?: auth()->id(),
                'margin_rejected_at' => now(),
                'margin_rejection_reason' => $reason,
                'updated_at' => now(),
            ]));
    }

    public static function filterColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $columns = Schema::getColumnListing($table);

        return array_filter(
            $data,
            fn ($value, $key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }
    public static function pendingRequestForOrder(object $order): ?object
    {
        if (! Schema::hasTable('approval_requests')) {
            return null;
        }

        return DB::table('approval_requests')
            ->where('approvable_type', SaleOrder::class)
            ->where('approvable_id', $order->id)
            ->whereIn('document_type', ['sales_quote', 'sales_order', 'sales_margin_approval'])
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();
    }

    public static function currentUserCanActOnPendingRequest(object $order): bool
    {
        $request = static::pendingRequestForOrder($order);
        $user = auth()->user();

        if (! $request || ! $user) {
            return false;
        }

        if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
            return true;
        }

        if (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
            return true;
        }

        if (method_exists($user, 'can') && ($user->can('approvals.approve') || $user->can('sales.approve_margin'))) {
            return true;
        }

        if (! Schema::hasTable('approval_request_steps')) {
            return false;
        }

        $steps = DB::table('approval_request_steps')
            ->where('approval_request_id', $request->id)
            ->where('status', 'pending')
            ->get();

        foreach ($steps as $step) {
            if ((int) ($step->approver_user_id ?? 0) === (int) $user->id) {
                return true;
            }

            $roleName = trim((string) ($step->approver_role_name ?? ''));

            if ($roleName !== '' && Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
                $hasRole = DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_id', $user->id)
                    ->where('roles.name', $roleName)
                    ->exists();

                if ($hasRole) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function approvePendingRequestForOrder(object $order): void
    {
        $request = static::pendingRequestForOrder($order);

        if (! $request) {
            throw new \RuntimeException('No hay solicitud pendiente para aprobar.');
        }

        if (! static::currentUserCanActOnPendingRequest($order)) {
            throw new \RuntimeException('No tienes permiso para aprobar esta solicitud.');
        }

        $now = now();
        $userId = auth()->id();

        if (Schema::hasTable('approval_request_steps')) {
            DB::table('approval_request_steps')
                ->where('approval_request_id', $request->id)
                ->where('status', 'pending')
                ->update(static::filterColumns('approval_request_steps', [
                    'status' => 'approved',
                    'acted_by_user_id' => $userId,
                    'acted_at' => $now,
                    'updated_at' => $now,
                ]));
        }

        DB::table('approval_requests')
            ->where('id', $request->id)
            ->update(static::filterColumns('approval_requests', [
                'status' => 'approved',
                'completed_at' => $now,
                'last_decision_reason' => null,
                'updated_at' => $now,
            ]));

        $order = DB::table('sales_orders')->where('id', $request->approvable_id)->first();

        static::markSalesApprovedFromRequest($request, $userId);

        if ($order) {
            static::logEvent($order, 'approval_approved', 'Cotización aprobada por flujo', 'La cotización fue aprobada y ya puede confirmarse.', ['approval_request_id' => $request->id], $userId);
        }

        static::notifyRequester($request, 'Cotización aprobada', 'Tu cotización fue aprobada y ya puede confirmarse.');
    }

    public static function rejectPendingRequestForOrder(object $order, string $reason): void
    {
        $request = static::pendingRequestForOrder($order);

        if (! $request) {
            throw new \RuntimeException('No hay solicitud pendiente para rechazar.');
        }

        if (! static::currentUserCanActOnPendingRequest($order)) {
            throw new \RuntimeException('No tienes permiso para rechazar esta solicitud.');
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new \RuntimeException('Debes capturar un motivo de rechazo.');
        }

        $now = now();
        $userId = auth()->id();

        if (Schema::hasTable('approval_request_steps')) {
            DB::table('approval_request_steps')
                ->where('approval_request_id', $request->id)
                ->where('status', 'pending')
                ->update(static::filterColumns('approval_request_steps', [
                    'status' => 'rejected',
                    'acted_by_user_id' => $userId,
                    'acted_at' => $now,
                    'decision_reason' => $reason,
                    'updated_at' => $now,
                ]));
        }

        DB::table('approval_requests')
            ->where('id', $request->id)
            ->update(static::filterColumns('approval_requests', [
                'status' => 'rejected',
                'completed_at' => $now,
                'last_decision_reason' => $reason,
                'updated_at' => $now,
            ]));

        $order = DB::table('sales_orders')->where('id', $request->approvable_id)->first();

        static::markSalesRejectedFromRequest($request, $reason, $userId);

        if ($order) {
            static::logEvent($order, 'approval_rejected', 'Cotización rechazada por flujo', $reason, ['approval_request_id' => $request->id], $userId);
        }

        static::notifyRequester($request, 'Cotización rechazada', $reason);
    }

    public static function notifyRequester(object $request, string $title, string $body): void
    {
        try {
            if (empty($request->requester_user_id)) {
                return;
            }

            $url = static::approvalRequestUrl($request);

            if (class_exists(\App\Support\BexiaUserNotification::class)) {
                $class = \App\Support\BexiaUserNotification::class;

                foreach (['send', 'create', 'notify', 'make'] as $method) {
                    if (! method_exists($class, $method)) {
                        continue;
                    }

                    foreach ([
                        [(int) $request->requester_user_id, $title, $body, $url],
                        [(int) $request->requester_user_id, $title, $body],
                        [[
                            'user_id' => (int) $request->requester_user_id,
                            'title' => $title,
                            'message' => $body,
                            'body' => $body,
                            'url' => $url,
                            'type' => 'approval',
                        ]],
                    ] as $args) {
                        try {
                            $class::$method(...$args);
                            return;
                        } catch (\Throwable $e) {
                            //
                        }
                    }
                }
            }

            if (class_exists(\Filament\Notifications\Notification::class) && Schema::hasTable('users')) {
                $user = \App\Models\User::query()->find($request->requester_user_id);

                if ($user && Schema::hasTable('notifications')) {
                    \Filament\Notifications\Notification::make()
                        ->title($title)
                        ->body($body)
                        ->warning()
                        ->sendToDatabase($user);
                }
            }
        } catch (\Throwable $e) {
            //
        }
    }



    public static function documentTypeLabel(?string $type): string
    {
        return match ((string) $type) {
            'purchase_request' => 'Solicitud de compra',
            'purchase_order' => 'Orden de compra',
            'sales_quote', 'sales_:quote', 'sale_quote' => 'Cotización de venta',
            'sales_order' => 'Orden de venta',
            'sales_margin_approval' => 'Aprobación de margen de venta',
            default => (string) $type,
        };
    }

    public static function approvalRequestUrl(object $request): string
    {
        $type = (string) ($request->document_type ?? '');
        $id = (int) ($request->approvable_id ?? 0);

        if (in_array($type, ['sales_quote', 'sales_:quote', 'sale_quote', 'sales_order', 'sales_margin_approval'], true) && $id > 0) {
            return \App\Filament\Resources\SaleOrderResource::getUrl('edit', [
                'record' => $id,
                'from_tab' => in_array($type, ['sales_quote', 'sales_:quote', 'sale_quote', 'sales_margin_approval'], true) ? 'por_aprobar' : 'ordenes',
            ]);
        }

        return '#';
    }

    public static function logEvent(object $order, string $eventType, string $title, ?string $description = null, ?array $payload = null, ?int $userId = null): void
    {
        if (! Schema::hasTable('sales_order_events')) {
            return;
        }

        DB::table('sales_order_events')->insert(static::filterColumns('sales_order_events', [
            'company_id' => $order->company_id ?? null,
            'sales_order_id' => $order->id,
            'user_id' => $userId ?: auth()->id(),
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }


    public static function approvalPriorityData(object $request): array
    {
        $type = (string) ($request->document_type ?? '');

        if (! in_array($type, ['sales_quote', 'sales_:quote', 'sale_quote', 'sales_order', 'sales_margin_approval'], true)) {
            return [
                'level' => 'normal',
                'label' => 'Normal',
                'message' => null,
                'style' => 'background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;',
            ];
        }

        $orderId = (int) ($request->approvable_id ?? 0);

        if ($orderId <= 0 || ! Schema::hasTable('sales_order_lines')) {
            return [
                'level' => 'important',
                'label' => 'Importante',
                'message' => trim((string) ($request->notes ?? 'Requiere revisión de venta.')),
                'style' => 'background:#fef9c3;color:#854d0e;border:1px solid #fde68a;',
            ];
        }

        $risk = DB::table('sales_order_lines')
            ->where('sales_order_id', $orderId)
            ->whereIn('margin_status', ['warning', 'danger'])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN margin_status = 'danger' THEN 1 ELSE 0 END), 0) as danger_count,
                COALESCE(SUM(CASE WHEN margin_status = 'warning' THEN 1 ELSE 0 END), 0) as warning_count
            ")
            ->first();

        $danger = (int) ($risk->danger_count ?? 0);
        $warning = (int) ($risk->warning_count ?? 0);

        if ($danger > 0) {
            return [
                'level' => 'urgent',
                'label' => 'Urgente',
                'message' => "{$danger} línea(s) debajo del costo" . ($warning > 0 ? " y {$warning} con margen bajo." : "."),
                'style' => 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca;',
            ];
        }

        if ($warning > 0) {
            return [
                'level' => 'important',
                'label' => 'Importante',
                'message' => "{$warning} línea(s) con margen bajo.",
                'style' => 'background:#fef9c3;color:#854d0e;border:1px solid #fde68a;',
            ];
        }

        return [
            'level' => 'normal',
            'label' => 'Normal',
            'message' => trim((string) ($request->notes ?? 'Aprobación estándar.')),
            'style' => 'background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;',
        ];
    }

    public static function approvalPriorityBadgeHtml(object $request): string
    {
        $data = static::approvalPriorityData($request);

        $label = e($data['label']);
        $style = $data['style'];

        return '<span style="display:inline-flex;align-items:center;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:600;' . $style . '">' . $label . '</span>';
    }

    public static function approvalPriorityMessage(object $request): ?string
    {
        $data = static::approvalPriorityData($request);

        return $data['message'] ?: null;
    }


    public static function confirmApprovedQuoteFromRequest(object $request, ?int $userId = null): void
    {
        if (! Schema::hasTable('sales_orders')) {
            return;
        }

        if (! in_array((string) ($request->document_type ?? ''), ['sales_quote', 'sales_:quote', 'sale_quote', 'sales_order', 'sales_margin_approval'], true)) {
            return;
        }

        $order = DB::table('sales_orders')
            ->where('id', $request->approvable_id)
            ->first();

        if (! $order) {
            return;
        }

        // Solo una cotización en borrador se convierte automáticamente.
        if ((string) ($order->status ?? '') !== 'draft') {
            return;
        }

        $now = now();
        $approverId = $userId ?: auth()->id();

        DB::table('sales_orders')
            ->where('id', $order->id)
            ->update(static::filterColumns('sales_orders', [
                'status' => 'confirmed',
                'confirmed_at' => $now,
                'confirmed_by_user_id' => $approverId,
                'margin_approval_required' => true,
                'margin_approval_status' => 'approved',
                'margin_approved_by_user_id' => $approverId,
                'margin_approved_at' => $now,
                'updated_at' => $now,
            ]));

        $updatedOrder = DB::table('sales_orders')
            ->where('id', $order->id)
            ->first();

        if ($updatedOrder) {
            static::refreshApprovalSnapshot($updatedOrder, $approverId);

            static::logEvent(
                $updatedOrder,
                'confirmed_after_approval',
                'Cotización convertida en orden de venta',
                'La cotización fue aprobada y se convirtió automáticamente en orden de venta.',
                [
                    'approval_request_id' => $request->id ?? null,
                    'approved_by_user_id' => $approverId,
                ],
                $approverId
            );
        }
    }


    public static function salesOrderApprovalHash(object|int $order): string
    {
        $orderId = is_object($order) ? (int) ($order->id ?? 0) : (int) $order;

        if ($orderId <= 0 || ! Schema::hasTable('sales_orders')) {
            return sha1('missing-order');
        }

        $header = DB::table('sales_orders')->where('id', $orderId)->first();

        if (! $header) {
            return sha1('missing-order');
        }

        $headerData = [
            'customer_contact_id' => $header->customer_contact_id ?? null,
            'price_list_id' => $header->price_list_id ?? null,
            'warehouse_id' => $header->warehouse_id ?? null,
            'location_id' => $header->location_id ?? null,
            'delivery_policy' => $header->delivery_policy ?? null,
            'delivery_contact_id' => $header->delivery_contact_id ?? null,
            'delivery_address' => $header->delivery_address ?? null,
            'billing_address' => $header->billing_address ?? null,
            'currency' => $header->currency ?? null,
            'subtotal_without_tax' => $header->subtotal_without_tax ?? null,
            'tax_total' => $header->tax_total ?? null,
            'total_with_tax' => $header->total_with_tax ?? null,
        ];

        $lines = [];

        if (Schema::hasTable('sales_order_lines')) {
            $lines = DB::table('sales_order_lines')
                ->where('sales_order_id', $orderId)
                ->orderBy('id')
                ->get()
                ->map(fn ($line): array => [
                    'product_id' => $line->product_id ?? null,
                    'variant_id' => $line->variant_id ?? null,
                    'unit_label' => $line->unit_label ?? null,
                    'quantity' => (float) ($line->quantity ?? 0),
                    'unit_price_without_tax' => (float) ($line->unit_price_without_tax ?? 0),
                    'tax_rate' => (float) ($line->tax_rate ?? 0),
                    'line_total_without_tax' => (float) ($line->line_total_without_tax ?? 0),
                    'line_tax' => (float) ($line->line_tax ?? 0),
                    'line_total_with_tax' => (float) ($line->line_total_with_tax ?? 0),
                    'margin_status' => $line->margin_status ?? null,
                ])
                ->values()
                ->all();
        }

        return sha1(json_encode([
            'header' => $headerData,
            'lines' => $lines,
        ], JSON_UNESCAPED_UNICODE));
    }

    public static function refreshApprovalSnapshot(object|int $order, ?int $userId = null): void
    {
        $orderId = is_object($order) ? (int) ($order->id ?? 0) : (int) $order;

        if ($orderId <= 0 || ! Schema::hasTable('sales_orders')) {
            return;
        }

        DB::table('sales_orders')
            ->where('id', $orderId)
            ->update(static::filterColumns('sales_orders', [
                'approval_snapshot_hash' => static::salesOrderApprovalHash($orderId),
                'approval_snapshot_at' => now(),
                'approval_changed_after_approval' => false,
                'updated_at' => now(),
            ]));
    }

    public static function markOrderChangedAfterApproval(int $orderId, string $description = 'La orden de venta fue modificada.'): void
    {
        if ($orderId <= 0 || ! Schema::hasTable('sales_orders')) {
            return;
        }

        $order = DB::table('sales_orders')->where('id', $orderId)->first();

        if (! $order) {
            return;
        }

        if (! in_array((string) ($order->status ?? ''), ['confirmed', 'partially_delivered', 'delivered'], true)) {
            return;
        }

        $previousHash = (string) ($order->approval_snapshot_hash ?? '');
        $currentHash = static::salesOrderApprovalHash($order);

        if ($previousHash !== '' && hash_equals($previousHash, $currentHash)) {
            return;
        }

        $summary = static::approvalRequirementSummary($order);
        $requiresApproval = (bool) ($summary['requires_approval'] ?? false);

        if (! $requiresApproval) {
            DB::table('sales_orders')
                ->where('id', $orderId)
                ->update(static::filterColumns('sales_orders', [
                    'margin_approval_required' => false,
                    'margin_approval_status' => 'not_required',
                    'margin_approval_reason' => null,
                    'approval_changed_after_approval' => true,
                    'updated_at' => now(),
                ]));

            static::logEvent(
                $order,
                'order_changed_no_reapproval',
                'Orden modificada sin reaprobación requerida',
                'Se modificó la orden, pero no existe una regla que requiera aprobación.',
                null,
                auth()->id()
            );

            return;
        }

        $reason = trim('Orden de venta modificada después de aprobación. ' . (string) ($summary['reason'] ?? ''));

        DB::table('sales_orders')
            ->where('id', $orderId)
            ->update(static::filterColumns('sales_orders', [
                'margin_approval_required' => true,
                'margin_approval_status' => 'required',
                'margin_approval_reason' => $reason,
                'margin_approval_requested_at' => null,
                'margin_approved_by_user_id' => null,
                'margin_approved_at' => null,
                'margin_rejected_by_user_id' => null,
                'margin_rejected_at' => null,
                'margin_rejection_reason' => null,
                'approval_changed_after_approval' => true,
                'updated_at' => now(),
            ]));

        static::logEvent(
            $order,
            'order_changed_reapproval_required',
            'Orden modificada: requiere reaprobación',
            $reason,
            [
                'previous_hash' => $previousHash,
                'current_hash' => $currentHash,
            ],
            auth()->id()
        );
    }

    public static function needsOrderReapproval(object $order): bool
    {
        if (! in_array((string) ($order->status ?? ''), ['confirmed', 'partially_delivered', 'delivered'], true)) {
            return false;
        }

        return in_array((string) ($order->margin_approval_status ?? ''), ['required', 'rejected'], true)
            && (bool) ($order->margin_approval_required ?? false);
    }

    public static function requestOrderReapproval(object $order): object
    {
        $summary = static::approvalRequirementSummary($order);

        if (! (bool) ($summary['requires_approval'] ?? false)) {
            throw new \RuntimeException('La orden no requiere aprobación según las reglas actuales.');
        }

        $workflow = $summary['workflow'] ?? null;

        if (! $workflow) {
            throw new \RuntimeException('La orden requiere aprobación, pero no hay flujo activo aplicable.');
        }

        $reason = trim('Reaprobación por cambios en orden de venta. ' . (string) ($summary['reason'] ?? ''));

        $request = static::createOrGetPendingRequest($order, $workflow, $reason);

        DB::table('sales_orders')
            ->where('id', $order->id)
            ->update(static::filterColumns('sales_orders', [
                'margin_approval_required' => true,
                'margin_approval_status' => 'pending',
                'margin_approval_reason' => $reason,
                'margin_approval_requested_at' => now(),
                'updated_at' => now(),
            ]));

        static::logEvent(
            $order,
            'order_reapproval_requested',
            'Orden enviada a reaprobación',
            $reason,
            [
                'approval_request_id' => $request->id ?? null,
            ],
            auth()->id()
        );

        return $request;
    }


    public static function salesOrderWorkflowNotice(object|int $order): ?array
    {
        if (is_int($order)) {
            if (! Schema::hasTable('sales_orders')) {
                return null;
            }

            $order = DB::table('sales_orders')->where('id', $order)->first();
        }

        if (! $order) {
            return null;
        }

        $status = (string) ($order->status ?? '');
        $approvalStatus = (string) ($order->margin_approval_status ?? '');
        $reason = trim((string) (
            $order->margin_rejection_reason
            ?? $order->margin_approval_reason
            ?? ''
        ));

        if (in_array($status, ['cancelled', 'canceled'], true)) {
            return [
                'type' => 'gray',
                'title' => 'Cotización cancelada',
                'body' => 'Este documento está cancelado. Se conserva solo para historial y trazabilidad.',
            ];
        }

        if ($approvalStatus === 'pending') {
            return [
                'type' => 'warning',
                'title' => 'Pendiente de aprobación',
                'body' => 'Este documento ya fue enviado a aprobación. No debe modificarse hasta que sea aprobado o rechazado.',
            ];
        }

        if ($approvalStatus === 'rejected') {
            return [
                'type' => 'danger',
                'title' => 'Cotización rechazada',
                'body' => $reason !== ''
                    ? 'Motivo: ' . $reason
                    : 'Revisa el historial, ajusta la cotización y vuelve a enviarla si corresponde.',
            ];
        }

        if (method_exists(static::class, 'needsOrderReapproval') && static::needsOrderReapproval($order)) {
            return [
                'type' => 'warning',
                'title' => 'Orden modificada después de aprobación',
                'body' => $reason !== ''
                    ? $reason
                    : 'Esta orden fue modificada después de aprobarse. Debe enviarse nuevamente a aprobación.',
            ];
        }

        if ($status === 'draft' && in_array($approvalStatus, ['required'], true)) {
            return [
                'type' => 'warning',
                'title' => 'Requiere aprobación',
                'body' => $reason !== ''
                    ? $reason
                    : 'Esta cotización requiere aprobación antes de convertirse en orden de venta.',
            ];
        }

        if ($status === 'draft' && $approvalStatus === 'approved') {
            return [
                'type' => 'success',
                'title' => 'Cotización aprobada',
                'body' => 'La cotización ya fue aprobada y puede convertirse en orden de venta.',
            ];
        }

        if (in_array($status, ['confirmed'], true)) {
            return [
                'type' => 'success',
                'title' => 'Orden de venta confirmada',
                'body' => 'La orden está confirmada. Si cambias productos, cantidades o precios, se reevaluará la aprobación.',
            ];
        }

        if (in_array($status, ['partially_delivered', 'partial_delivered'], true)) {
            return [
                'type' => 'info',
                'title' => 'Orden parcialmente entregada',
                'body' => 'Esta orden ya tiene entregas parciales. Los cambios deben controlarse para no afectar inventario.',
            ];
        }

        if (in_array($status, ['delivered'], true)) {
            return [
                'type' => 'success',
                'title' => 'Orden entregada',
                'body' => 'Esta orden ya fue entregada. No debe modificarse desde ventas.',
            ];
        }

        return null;
    }

    public static function canEditSalesOrderLines(object|int $order): bool
    {
        if (is_int($order)) {
            if (! Schema::hasTable('sales_orders')) {
                return false;
            }

            $order = DB::table('sales_orders')->where('id', $order)->first();
        }

        if (! $order) {
            return false;
        }

        $status = (string) ($order->status ?? '');
        $approvalStatus = (string) ($order->margin_approval_status ?? '');

        if (in_array($status, ['cancelled', 'canceled', 'delivered'], true)) {
            return false;
        }

        if ($approvalStatus === 'pending') {
            return false;
        }

        if (in_array($status, ['partially_delivered', 'partial_delivered'], true)) {
            return false;
        }

        return true;
    }


}
