<?php

namespace App\Filament\Pages;

use App\Support\EmployeeIncidentApprovalWorkflow;
use App\Support\PayrollRunApprovalWorkflow;
use App\Support\Treasury\CashTransferApprovalWorkflow;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MySentApprovalStatuses extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Mis enviados';

    protected static ?string $title = 'Estatus de mis documentos enviados';

    protected static ?string $navigationGroup = 'Inicio';

    protected static ?int $navigationSort = 30;

    protected static string $view = 'filament.pages.my-sent-approval-statuses';

    public array $rows = [];

    public function mount(): void
    {
        if (! auth()->check() || ! Schema::hasTable('approval_requests')) {
            $this->rows = [];
            return;
        }

        $this->rows = DB::table('approval_requests')
            ->where('requester_user_id', auth()->id())
            ->orderByDesc('created_at')
            ->limit(80)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'company_id' => (int) ($row->company_id ?? 0),
                'document_type' => (string) ($row->document_type ?? ''),
                'document_label' => $this->documentLabel((string) ($row->document_type ?? '')),
                'document_number' => (string) ($row->document_number ?? ''),
                'approvable_id' => (int) ($row->approvable_id ?? 0),
                'status' => (string) ($row->status ?? ''),
                'status_label' => $this->statusLabel((string) ($row->status ?? '')),
                'amount_total' => (float) ($row->amount_total ?? 0),
                'sent_at' => (string) ($row->sent_at ?? ''),
                'completed_at' => (string) ($row->completed_at ?? ''),
                'last_decision_reason' => (string) ($row->last_decision_reason ?? ''),
                'url' => $this->documentUrl($row),
            ])
            ->all();
    }

    protected function documentLabel(?string $type): string
    {
        return \App\Support\SalesApprovalWorkflow::documentTypeLabel($type);
    }


    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            'cancelled' => 'Cancelado',
            default => $status ?: '—',
        };
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


    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }


}
