<?php

namespace App\Filament\Pages;

use App\Support\BexiaUserNotification;
use App\Support\EmployeeIncidentApprovalWorkflow;
use App\Support\PayrollRunApprovalWorkflow;
use App\Support\Treasury\CashTransferApprovalWorkflow;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MyPendingApprovals extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-check-circle';

    protected static ?string $navigationLabel = 'Mis aprobaciones';

    protected static ?string $title = 'Mis aprobaciones pendientes';

    protected static ?string $navigationGroup = 'Inicio';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.my-pending-approvals';
protected static function canUseApprovalsPage(): bool
{
    return auth()->user()?->can('approvals.approve') ?? false;
}

public static function shouldRegisterNavigation(): bool
{
    return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
        'pages.mypendingapprovals',
        fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
    );
}

protected static function bexiaBaseShouldRegisterNavigation(): bool
{
    return static::canUseApprovalsPage();
}

public static function canAccess(): bool
{
    return static::canUseApprovalsPage();
}
    public array $rows = [];

    public ?int $rejectStepId = null;

    public string $rejectReason = '';

    public string $rejectDocumentLabel = '';

    public function mount(): void
    {
        $this->refreshRows();
    }

    public static function getNavigationBadge(): ?string
    {
if (! static::canUseApprovalsPage()) {
    return null;
}

        $count = static::pendingCountForUser((int) auth()->id());

        return $count > 0 ? (string) $count : null;
    }

    public static function pendingCountForUser(int $userId): int
    {
        if (
            $userId <= 0
            || ! Schema::hasTable('approval_requests')
            || ! Schema::hasTable('approval_request_steps')
        ) {
            return 0;
        }

        return DB::table('approval_request_steps as steps')
            ->join('approval_requests as requests', 'requests.id', '=', 'steps.approval_request_id')
            ->where('steps.status', 'pending')
            ->where('requests.status', 'pending')
            ->whereColumn('steps.step_order', 'requests.current_step_order')
            ->where('steps.approver_user_id', $userId)
            ->count();
    }

    public function refreshRows(): void
    {
        $userId = (int) auth()->id();

        if (
            $userId <= 0
            || ! Schema::hasTable('approval_requests')
            || ! Schema::hasTable('approval_request_steps')
        ) {
            $this->rows = [];
            return;
        }

        $rows = DB::table('approval_request_steps as steps')
            ->join('approval_requests as requests', 'requests.id', '=', 'steps.approval_request_id')
            ->where('steps.status', 'pending')
            ->where('requests.status', 'pending')
            ->whereColumn('steps.step_order', 'requests.current_step_order')
            ->where('steps.approver_user_id', $userId)
            ->orderBy('requests.sent_at')
            ->orderBy('steps.step_order')
            ->select([
                'steps.id as step_id',
                'steps.step_name',
                'steps.step_order',
                'requests.id as request_id',
                'requests.company_id',
                'requests.approvable_type',
                'requests.approvable_id',
                'requests.document_type',
                'requests.document_number',
                'requests.requester_user_id',
                'requests.requester_name',
                'requests.amount_total',
                'requests.sent_at',
                'requests.current_step_order',
            ])
            ->get();

        $this->rows = $rows
            ->map(fn ($row): array => [
                'step_id' => (int) $row->step_id,
                'request_id' => (int) $row->request_id,
                'company_id' => (int) ($row->company_id ?? 0),
                'document_type' => (string) ($row->document_type ?? ''),
                'document_label' => $this->documentLabel((string) ($row->document_type ?? '')),
                'document_number' => (string) ($row->document_number ?? ''),
                'approvable_id' => (int) ($row->approvable_id ?? 0),
                'step_name' => (string) ($row->step_name ?? ''),
                'requester_name' => (string) ($row->requester_name ?? '—'),
                'amount_total' => (float) ($row->amount_total ?? 0),
                'sent_at' => (string) ($row->sent_at ?? ''),
                'url' => $this->documentUrl($row),
            ])
            ->values()
            ->all();
    }

    public function approveStep(int $stepId): void
    {
        try {
            $this->actOnStep($stepId, 'approved');

            $this->refreshRows();

            Notification::make()
                ->title('Documento aprobado')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('No se pudo aprobar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function openRejectModal(int $stepId): void
    {
        $row = collect($this->rows)->first(fn ($item): bool => (int) $item['step_id'] === $stepId);

        if (! $row) {
            Notification::make()
                ->title('Aprobación no encontrada')
                ->danger()
                ->send();

            return;
        }

        $this->rejectStepId = $stepId;
        $this->rejectReason = '';
        $this->rejectDocumentLabel = trim($row['document_label'] . ' ' . $row['document_number']);
    }

    public function cancelReject(): void
    {
        $this->rejectStepId = null;
        $this->rejectReason = '';
        $this->rejectDocumentLabel = '';
    }

    public function confirmRejectStep(): void
    {
        $reason = trim($this->rejectReason);

        if (! $this->rejectStepId) {
            return;
        }

        if (mb_strlen($reason) < 5) {
            Notification::make()
                ->title('Motivo obligatorio')
                ->body('Escribe un motivo de rechazo de al menos 5 caracteres.')
                ->warning()
                ->send();

            return;
        }

        try {
            $this->actOnStep($this->rejectStepId, 'rejected', $reason);

            $this->cancelReject();
            $this->refreshRows();

            Notification::make()
                ->title('Documento rechazado')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('No se pudo rechazar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function actOnStep(int $stepId, string $decision, ?string $reason = null): void
    {
        $user = auth()->user();

        if (! $user) {
            throw new \RuntimeException('Sesión no válida.');
        }

        DB::transaction(function () use ($stepId, $decision, $reason, $user): void {
            $step = DB::table('approval_request_steps')
                ->where('id', $stepId)
                ->lockForUpdate()
                ->first();

            if (! $step || (string) $step->status !== 'pending') {
                throw new \RuntimeException('Esta aprobación ya fue atendida.');
            }

            $request = DB::table('approval_requests')
                ->where('id', $step->approval_request_id)
                ->lockForUpdate()
                ->first();

            if (! $request || (string) $request->status !== 'pending') {
                throw new \RuntimeException('La solicitud de aprobación ya no está pendiente.');
            }

            if ((int) ($step->approver_user_id ?? 0) !== (int) $user->id) {
                throw new \RuntimeException('Tu usuario no es el aprobador asignado a esta etapa.');
            }

            if ($decision === 'approved') {
                $this->approveCurrentStep($step, $request, $user);
                return;
            }

            $this->rejectCurrentStep($step, $request, $user, trim((string) $reason));
        });
    }

    protected function approveCurrentStep(object $step, object $request, object $user): void
    {
        $comment = $this->approvalComment($request, 'approved');

        $stepUpdate = [
            'status' => 'approved',
            'acted_by_user_id' => $user->id,
            'acted_by_name' => $user->name ?? $user->email ?? 'Usuario',
            'acted_at' => now(),
            'comments' => $comment,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('approval_request_steps', 'decision_reason')) {
            $stepUpdate['decision_reason'] = $comment;
        }

        DB::table('approval_request_steps')
            ->where('id', $step->id)
            ->update($stepUpdate);

        $nextStep = DB::table('approval_request_steps')
            ->where('approval_request_id', $request->id)
            ->whereIn('status', ['pending', 'waiting'])
            ->where('step_order', '>', $step->step_order)
            ->orderBy('step_order')
            ->first();

        if ($nextStep) {
            DB::table('approval_request_steps')
                ->where('id', $nextStep->id)
                ->update([
                    'status' => 'pending',
                    'updated_at' => now(),
                ]);

            $requestUpdate = [
                'current_step_order' => $nextStep->step_order,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('approval_requests', 'last_decision_reason')) {
                $requestUpdate['last_decision_reason'] = $comment;
            }

            DB::table('approval_requests')
                ->where('id', $request->id)
                ->update($requestUpdate);

            $this->notifyRequester(
                $request,
                $this->documentLabel($request->document_type) . ' aprobado parcialmente',
                'El documento ' . $request->document_number . ' fue aprobado en una etapa y continúa pendiente.',
                'approved_step',
                $comment
            );

            return;
        }

        $requestUpdate = [
            'status' => 'approved',
            'completed_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('approval_requests', 'last_decision_reason')) {
            $requestUpdate['last_decision_reason'] = $comment;
        }

        \App\Support\SalesApprovalWorkflow::markSalesApprovedFromRequest($request, auth()->id());

        DB::table('approval_requests')
            ->where('id', $request->id)
            ->update($requestUpdate);

        if (in_array((string) ($request->document_type ?? ''), ['sales_quote', 'sales_:quote', 'sale_quote', 'sales_order', 'sales_margin_approval'], true)) {
            \App\Support\SalesApprovalWorkflow::markSalesApprovedFromRequest($request, $user->id);
            $order = DB::table('sales_orders')->where('id', $request->approvable_id)->first();
            if ($order) {
                \App\Support\SalesApprovalWorkflow::logEvent($order, 'approval_approved', 'Cotización aprobada por flujo', 'La cotización fue aprobada y ya puede confirmarse.', ['approval_request_id' => $request->id], $user->id);
            }
            \App\Support\SalesApprovalWorkflow::notifyRequester($request, 'Cotización aprobada', 'Tu cotización fue aprobada y ya puede confirmarse.');
        }

        $this->markDocumentApproved($request, $user, $comment);
    }

    protected function rejectCurrentStep(object $step, object $request, object $user, string $reason): void
    {
        if ($reason === '') {
            throw new \RuntimeException('El motivo de rechazo es obligatorio.');
        }

        $stepUpdate = [
            'status' => 'rejected',
            'acted_by_user_id' => $user->id,
            'acted_by_name' => $user->name ?? $user->email ?? 'Usuario',
            'acted_at' => now(),
            'comments' => $reason,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('approval_request_steps', 'decision_reason')) {
            $stepUpdate['decision_reason'] = $reason;
        }

        DB::table('approval_request_steps')
            ->where('id', $step->id)
            ->update($stepUpdate);

        DB::table('approval_request_steps')
            ->where('approval_request_id', $request->id)
            ->whereIn('status', ['pending', 'waiting'])
            ->update([
                'status' => 'skipped',
                'updated_at' => now(),
            ]);

        $requestUpdate = [
            'status' => 'rejected',
            'completed_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('approval_requests', 'last_decision_reason')) {
            $requestUpdate['last_decision_reason'] = $reason;
        }

        DB::table('approval_requests')
            ->where('id', $request->id)
            ->update($requestUpdate);

        if (in_array((string) ($request->document_type ?? ''), ['sales_quote', 'sales_:quote', 'sale_quote', 'sales_order', 'sales_margin_approval'], true)) {
            \App\Support\SalesApprovalWorkflow::markSalesRejectedFromRequest($request, $reason, $user->id);
            $order = DB::table('sales_orders')->where('id', $request->approvable_id)->first();
            if ($order) {
                \App\Support\SalesApprovalWorkflow::logEvent($order, 'approval_rejected', 'Cotización rechazada por flujo', $reason, ['approval_request_id' => $request->id], $user->id);
            }
            \App\Support\SalesApprovalWorkflow::notifyRequester($request, 'Cotización rechazada', $reason);
        }

        $this->markDocumentRejected($request, $user, $reason);
    }

    protected function markDocumentApproved(object $request, object $user, string $comment): void
    {
        $type = (string) ($request->document_type ?? '');

        if ($type === CashTransferApprovalWorkflow::DOCUMENT_TYPE) {
            CashTransferApprovalWorkflow::markApproved($request, $user->id, $comment ?? null);

            return;
        }


        if ($type === 'employee_incident') {
            EmployeeIncidentApprovalWorkflow::markApproved($request, $user->id, $comment);

            $this->notifyRequester(
                $request,
                'Incidencia RRHH aprobada',
                'La incidencia ' . $request->document_number . ' fue aprobada.',
                'employee_incident_approved',
                $comment
            );

            return;
        }

        if ($type === 'purchase_order' && Schema::hasTable('purchase_orders')) {
            DB::table('purchase_orders')
                ->where('id', $request->approvable_id)
                ->update([
                    'status' => 'confirmed',
                    'updated_at' => now(),
                ]);

            $this->logPurchaseOrder($request, 'approved', 'review', 'confirmed', 'Orden de compra aprobada y confirmada.', $user, $comment);

            $this->notifyRequester(
                $request,
                'OC aprobada',
                'La orden ' . $request->document_number . ' fue aprobada y quedó confirmada.',
                'purchase_order_approved',
                $comment
            );

            return;
        }

        if ($type === 'purchase_request' && Schema::hasTable('purchase_requests')) {
            DB::table('purchase_requests')
                ->where('id', $request->approvable_id)
                ->update([
                    'status' => 'approved',
                    'updated_at' => now(),
                ]);

            $this->notifyRequester(
                $request,
                'Solicitud de compra aprobada',
                'La solicitud ' . $request->document_number . ' fue aprobada.',
                'purchase_request_approved',
                $comment
            );
        }
    }

    protected function markDocumentRejected(object $request, object $user, string $reason): void
    {
        $type = (string) ($request->document_type ?? '');

        if ($type === CashTransferApprovalWorkflow::DOCUMENT_TYPE) {
            CashTransferApprovalWorkflow::markRejected($request, $user->id, $reason ?? null);

            return;
        }


        if ($type === 'employee_incident') {
            EmployeeIncidentApprovalWorkflow::markRejected($request, $user->id, $reason);

            $this->notifyRequester(
                $request,
                'Incidencia RRHH rechazada',
                'La incidencia ' . $request->document_number . ' fue rechazada. Motivo: ' . $reason,
                'employee_incident_rejected',
                $reason
            );

            return;
        }

        if ($type === 'purchase_order' && Schema::hasTable('purchase_orders')) {
            DB::table('purchase_orders')
                ->where('id', $request->approvable_id)
                ->update([
                    'status' => 'draft',
                    'updated_at' => now(),
                ]);

            $this->logPurchaseOrder($request, 'rejected', 'review', 'draft', 'Orden de compra rechazada. Regresa a borrador para corrección.', $user, $reason);

            $this->notifyRequester(
                $request,
                'OC rechazada',
                'La orden ' . $request->document_number . ' fue rechazada. Motivo: ' . $reason,
                'purchase_order_rejected',
                $reason
            );

            return;
        }

        if ($type === 'purchase_request' && Schema::hasTable('purchase_requests')) {
            DB::table('purchase_requests')
                ->where('id', $request->approvable_id)
                ->update([
                    'status' => 'draft',
                    'updated_at' => now(),
                ]);

            $this->notifyRequester(
                $request,
                'Solicitud de compra rechazada',
                'La solicitud ' . $request->document_number . ' fue rechazada. Motivo: ' . $reason,
                'purchase_request_rejected',
                $reason
            );
        }
    }

    protected function notifyRequester(object $request, string $title, string $body, string $type, string $reason): void
    {
        $requesterId = (int) ($request->requester_user_id ?? 0);
        $companyId = (int) ($request->company_id ?? 0);
        $approvableId = (int) ($request->approvable_id ?? 0);

        if ($requesterId <= 0 || $companyId <= 0 || $approvableId <= 0 || ! class_exists(BexiaUserNotification::class)) {
            return;
        }

        $url = $this->documentUrl($request);

        BexiaUserNotification::send(
            $requesterId,
            $title,
            $body,
            $url,
            $companyId,
            $type,
            [
                'approval_request_id' => (int) $request->id,
                'document_type' => (string) ($request->document_type ?? ''),
                'document_number' => (string) ($request->document_number ?? ''),
                'approvable_id' => $approvableId,
                'reason' => $reason,
            ]
        );
    }

    protected function logPurchaseOrder(
        object $request,
        string $event,
        ?string $fromStatus,
        ?string $toStatus,
        string $detail,
        object $user,
        string $reason
    ): void {
        if (class_exists(\App\Support\PurchaseOrderHistory::class)) {
            \App\Support\PurchaseOrderHistory::log(
                (int) $request->approvable_id,
                $event,
                $fromStatus,
                $toStatus,
                $detail,
                [
                    'approval_request_id' => (int) $request->id,
                    'acted_by_user_id' => (int) $user->id,
                    'acted_by_name' => $user->name ?? $user->email ?? 'Usuario',
                    'reason' => $reason,
                ]
            );
        }
    }

    protected function approvalComment(object $request, string $decision): string
    {
        $type = (string) ($request->document_type ?? '');

        if ($type === 'purchase_order') {
            return $decision === 'approved'
                ? 'Orden de compra aprobada.'
                : 'Orden de compra rechazada.';
        }

        if ($type === 'purchase_request') {
            return $decision === 'approved'
                ? 'Solicitud de compra aprobada.'
                : 'Solicitud de compra rechazada.';
        }

        if ($type === 'employee_incident') {
            return $decision === 'approved'
                ? 'Incidencia RRHH aprobada.'
                : 'Incidencia RRHH rechazada.';
        }

        return $decision === 'approved'
            ? 'Documento aprobado.'
            : 'Documento rechazado.';
    }

    protected function documentLabel(?string $type): string
    {
        return \App\Support\SalesApprovalWorkflow::documentTypeLabel($type);
    }


    protected function documentUrl(object $row): string
    {
        $type = (string) ($row->document_type ?? '');
        $id = (int) ($row->approvable_id ?? 0);
        $tenantId = $this->resolveDocumentCompanyId($row);

        if ($tenantId <= 0 || $id <= 0) {
            return '#';
        }

        return match ($type) {
            'purchase_request' => url('/admin/' . $tenantId . '/purchase-requests/' . $id),
            'purchase_order' => url('/admin/' . $tenantId . '/purchase-orders/' . $id . '/edit'),
            'sales_quote', 'sales_:quote', 'sale_quote', 'sales_order', 'sales_margin_approval' => \App\Filament\Resources\SaleOrderResource::getUrl('edit', [
                'record' => $id,
                'tenant' => $tenantId,
                'from_tab' => in_array($type, ['sales_quote', 'sales_:quote', 'sale_quote', 'sales_margin_approval'], true) ? 'por_aprobar' : 'ordenes',
            ]),
            'employee_incident' => EmployeeIncidentApprovalWorkflow::documentUrl($row),
            'payroll_run' => PayrollRunApprovalWorkflow::documentUrl($row),
            'treasury_cash_transfer_request' => CashTransferApprovalWorkflow::documentUrl($row),
            default => '#',
        };
    }

    protected function resolveDocumentCompanyId(object $row): int
    {
        $companyId = (int) ($row->company_id ?? 0);

        if ($companyId > 0) {
            return $companyId;
        }

        $type = (string) ($row->document_type ?? '');
        $id = (int) ($row->approvable_id ?? 0);
        $number = (string) ($row->document_number ?? '');

        if ($type === 'employee_incident' && $id > 0 && Schema::hasTable('employee_incidents')) {
            return (int) DB::table('employee_incidents')->where('id', $id)->value('company_id');
        }

        if ($type === 'purchase_order' && $id > 0 && Schema::hasTable('purchase_orders')) {
            return (int) DB::table('purchase_orders')->where('id', $id)->value('company_id');
        }

        if ($type === 'purchase_request' && $id > 0 && Schema::hasTable('purchase_requests')) {
            return (int) DB::table('purchase_requests')->where('id', $id)->value('company_id');
        }

        if (in_array($type, ['sales_quote', 'sales_:quote', 'sale_quote', 'sales_order', 'sales_margin_approval'], true) && $id > 0 && Schema::hasTable('sales_orders')) {
            return (int) DB::table('sales_orders')->where('id', $id)->value('company_id');
        }

        if ($number !== '') {
            if (str_starts_with($number, 'OC-') && Schema::hasTable('purchase_orders')) {
                return (int) DB::table('purchase_orders')->where('number', $number)->value('company_id');
            }

            if (str_starts_with($number, 'SC-') && Schema::hasTable('purchase_requests')) {
                return (int) DB::table('purchase_requests')->where('number', $number)->value('company_id');
            }

            if (str_starts_with($number, 'VTA-') && Schema::hasTable('sales_orders')) {
                return (int) DB::table('sales_orders')->where('number', $number)->value('company_id');
            }
        }

        return 0;
    }


}
