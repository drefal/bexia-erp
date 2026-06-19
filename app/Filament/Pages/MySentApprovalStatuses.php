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
                'document_type' => $this->documentLabel((string) ($row->document_type ?? '')),
                'open_url' => $this->serviceApprovalRepairUrl($row),
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
    protected function serviceApprovalRepairUrl(object|array $row): ?string
    {
        $type = (string) data_get($row, 'document_type', '');

        if (! in_array($type, [
            'service_repair_quote_internal',
            'service_repair_parts_request',
            'service_repair_warranty',
            'service_repair_delivery',
        ], true)) {
            return null;
        }

        $repairId = (int) (
            data_get($row, 'approvable_id')
            ?: data_get($row, 'repair_order_id')
            ?: data_get($row, 'document_id')
            ?: data_get($row, 'record_id')
            ?: 0
        );

        $tenantId = null;

        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            $tenantId = $tenant ? (int) $tenant->getKey() : null;
        } catch (\Throwable) {
            $tenantId = null;
        }

        if ($repairId <= 0 && filled(data_get($row, 'folio'))) {
            try {
                $repair = \Illuminate\Support\Facades\DB::table('repair_orders')
                    ->where('folio', (string) data_get($row, 'folio'))
                    ->first();

                if ($repair) {
                    $repairId = (int) ($repair->id ?? 0);
                    $tenantId = $tenantId ?: (int) ($repair->company_id ?? 0);
                }
            } catch (\Throwable) {
                $repairId = 0;
            }
        }

        if ($repairId > 0 && ! $tenantId) {
            try {
                $tenantId = (int) \Illuminate\Support\Facades\DB::table('repair_orders')
                    ->where('id', $repairId)
                    ->value('company_id');
            } catch (\Throwable) {
                $tenantId = null;
            }
        }

        if ($repairId <= 0) {
            return null;
        }

        if (! $tenantId) {
            $tenantId = (int) (data_get($row, 'company_id') ?: 1);
        }

        return \App\Filament\Resources\RepairOrderResource::getUrl('edit', [
            'tenant' => $tenantId,
            'record' => $repairId,
        ]);
    }

    protected function openServiceApprovalRepair(object|array $row): void
    {
        $url = $this->serviceApprovalRepairUrl($row);

        if (! $url) {
            \Filament\Notifications\Notification::make()
                ->title('No se pudo abrir la reparación')
                ->body('No se encontró el folio o identificador relacionado con esta aprobación.')
                ->danger()
                ->send();

            return;
        }

        $this->redirect($url);
    }



    protected function documentLabel(?string $type): string
     {

        return \App\Support\Service\ServiceAccess::approvalWorkflowDocumentTypeLabel((string) $type);

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

        if (in_array((string) ($row->document_type ?? ''), [
            'service_repair_quote_internal',
            'service_repair_parts_request',
            'service_repair_warranty',
            'service_repair_delivery',
        ], true)) {
            return $this->serviceApprovalRepairUrl($row) ?: '#';
        }

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
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'pages.mysentapprovalstatuses',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        return false;
    }


}
