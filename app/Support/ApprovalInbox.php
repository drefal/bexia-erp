<?php

namespace App\Support;

use App\Support\Treasury\CashTransferApprovalWorkflow;

use App\Filament\Resources\PurchaseRequestResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApprovalInbox
{
    public static function countForCurrentUser(): int
    {
        return static::rowsForCurrentUser(1000)->count();
    }

    public static function rowsForCurrentUser(int $limit = 20): Collection
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        return static::rowsForUser($user, $limit);
    }

    public static function rowsForUser($user, int $limit = 20): Collection
    {
        if (
            ! Schema::hasTable('approval_requests')
            || ! Schema::hasTable('approval_request_steps')
        ) {
            return collect();
        }

        $rows = DB::table('approval_request_steps as steps')
            ->join('approval_requests as requests', 'requests.id', '=', 'steps.approval_request_id')
            ->where('requests.status', 'pending')
            ->where('steps.status', 'pending')
            ->whereColumn('steps.step_order', 'requests.current_step_order')
            ->select([
                'steps.id as step_id',
                'steps.approval_request_id',
                'steps.step_order',
                'steps.step_name',
                'steps.approver_type',
                'steps.approver_user_id',
                'steps.approver_role_name',
                'steps.created_at as step_created_at',

                'requests.id as approval_request_id',
                'requests.approvable_type',
                'requests.approvable_id',
                'requests.document_type',
                'requests.document_number',
                'requests.requester_name',
                'requests.amount_total',
                'requests.sent_at',
                'requests.created_at as request_created_at',
            ])
            ->orderBy('requests.sent_at')
            ->orderBy('requests.id')
            ->limit(500)
            ->get();

        return $rows
            ->filter(fn ($row): bool => static::userCanActOnRow($user, $row))
            ->take($limit)
            ->map(function ($row) {
                $row->document_label = static::documentTypeLabel($row->document_type);
                $row->action_url = static::documentUrl($row);
                $row->approver_label = static::approverLabel($row);

                return $row;
            })
            ->values();
    }

    protected static function userCanActOnRow($user, object $row): bool
    {
        if (! $user) {
            return false;
        }

        if ($row->approver_user_id && (int) $row->approver_user_id === (int) $user->id) {
            return true;
        }

        if ($row->approver_role_name && static::userHasRole($user, (string) $row->approver_role_name)) {
            return true;
        }

        return match ($row->approver_type) {
            'company_admin' => static::userHasAnyRole($user, ['Admin Empresa', 'Administrador', 'admin', 'super_admin', 'Super Admin']),
            'group_admin' => static::userHasAnyRole($user, ['Admin Grupo', 'super_admin', 'Super Admin']),
            'warehouse_responsible' => static::userHasAnyRole($user, ['Inventarios', 'Admin Empresa', 'Administrador', 'admin']),
            'purchase_responsible' => static::userHasAnyRole($user, ['Compras', 'Admin Empresa', 'Administrador', 'admin']),
            'accounting_responsible' => static::userHasAnyRole($user, ['Contabilidad', 'Admin Empresa', 'Administrador', 'admin']),
            default => false,
        };
    }

    protected static function userHasRole($user, string $role): bool
    {
        if (! $user || $role === '') {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole($role);
        }

        return false;
    }

    protected static function userHasAnyRole($user, array $roles): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }

        foreach ($roles as $role) {
            if (static::userHasRole($user, $role)) {
                return true;
            }
        }

        return false;
    }

    public static function documentTypeLabel(?string $type): string
    {
        if (class_exists(\App\Support\SalesApprovalWorkflow::class)) {
            return \App\Support\SalesApprovalWorkflow::documentTypeLabel($type);
        }

        return match ((string) $type) {
            'purchase_request' => 'Solicitud de compra',
            'purchase_order' => 'Orden de compra',
            default => (string) $type,
        };
    }



    public static function documentUrl(object $row): string
    {
        $type = (string) ($row->document_type ?? '');
        $id = (int) ($row->approvable_id ?? 0);
        $tenantId = static::resolveDocumentCompanyId($row);

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
            'employee_incident' => \App\Support\EmployeeIncidentApprovalWorkflow::documentUrl($row),
            default => '#',
        };
    }

    public static function resolveDocumentCompanyId(object $row): int
    {
        $companyId = (int) ($row->company_id ?? 0);

        if ($companyId > 0) {
            return $companyId;
        }

        $type = (string) ($row->document_type ?? '');
        $id = (int) ($row->approvable_id ?? 0);
        $number = (string) ($row->document_number ?? '');

        if ($type === 'payroll_run' && $id > 0 && Schema::hasTable('payroll_runs')) {
            return (int) DB::table('payroll_runs')->where('id', $id)->value('company_id');
        }

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



    protected static function approverLabel(object $row): string
    {
        if ($row->approver_role_name) {
            return 'Rol: ' . $row->approver_role_name;
        }

        if ($row->approver_user_id) {
            return 'Usuario #' . $row->approver_user_id;
        }

        return match ($row->approver_type) {
            'requester_manager' => 'Coordinador del solicitante',
            'company_admin' => 'Admin de empresa',
            'group_admin' => 'Admin de grupo',
            'warehouse_responsible' => 'Responsable de almacén',
            'purchase_responsible' => 'Responsable de compras',
            'accounting_responsible' => 'Responsable de contabilidad',
            default => $row->approver_type ?: 'Aprobador',
        };
    }
}
