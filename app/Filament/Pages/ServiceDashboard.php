<?php

namespace App\Filament\Pages;

use App\Support\Service\ServiceRepairSla;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServiceDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Atencion y Servicio';

    protected static ?string $navigationLabel = 'Dashboard Servicio';

    protected static ?string $title = 'Dashboard Servicio';

    protected static ?string $slug = 'service-dashboard';

    protected static ?int $navigationSort = 11;

    protected static string $view = 'filament.pages.service-dashboard';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        foreach (['service.menu.view', 'service.repairs.view'] as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('super_admin')
                || $user->hasRole('admin')
                || $user->hasRole('admin_grupo')
                || $user->hasRole('admin_empresa');
        }

        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function dashboardData(): array
    {
        if (! Schema::hasTable('repair_orders')) {
            return $this->emptyData();
        }

        $rows = $this->repairBaseQuery()
            ->orderByDesc(Schema::hasColumn('repair_orders', 'updated_at') ? 'updated_at' : 'id')
            ->get();

        $total = $rows->count();
        $open = $rows->filter(fn ($repair): bool => ! $this->isClosedRepair($repair))->count();
        $delivered = $rows->filter(fn ($repair): bool => $this->isDeliveredRepair($repair))->count();
        $inRepair = $rows->filter(fn ($repair): bool => (string) ($repair->workflow_stage ?? '') === 'in_repair')->count();
        $readyForDelivery = $rows->filter(fn ($repair): bool => (string) ($repair->workflow_stage ?? '') === 'ready_for_delivery')->count();

        $slaCounts = $rows
            ->map(fn ($repair): string => ServiceRepairSla::key($repair))
            ->countBy();

        $quoteCounts = $rows
            ->groupBy(fn ($repair): string => (string) ($repair->quote_status ?? 'sin_estado'))
            ->map(fn (Collection $group): int => $group->count());

        $economicCounts = $rows
            ->groupBy(fn ($repair): string => (string) ($repair->economic_status ?? 'sin_estado'))
            ->map(fn (Collection $group): int => $group->count());

        $paymentCounts = $rows
            ->groupBy(fn ($repair): string => (string) ($repair->economic_payment_status ?? 'sin_estado'))
            ->map(fn (Collection $group): int => $group->count());

        $cards = [
            [
                'label' => 'Total reparaciones',
                'value' => $total,
                'hint' => 'Registros del tenant actual',
                'tone' => 'slate',
            ],
            [
                'label' => 'Abiertas',
                'value' => $open,
                'hint' => 'No entregadas / no cerradas',
                'tone' => 'blue',
            ],
            [
                'label' => 'En reparación',
                'value' => $inRepair,
                'hint' => 'Trabajo activo',
                'tone' => 'indigo',
            ],
            [
                'label' => 'Listas entrega',
                'value' => $readyForDelivery,
                'hint' => 'Pendientes de entregar',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Entregadas',
                'value' => $delivered,
                'hint' => 'Cerradas con entrega',
                'tone' => 'green',
            ],
            [
                'label' => 'SLA vencidas',
                'value' => (int) (($slaCounts['vencida'] ?? 0) + ($slaCounts['cerrada_vencida'] ?? 0)),
                'hint' => 'Abiertas vencidas o cerradas tarde',
                'tone' => 'red',
            ],
            [
                'label' => 'Por vencer',
                'value' => (int) ($slaCounts['por_vencer'] ?? 0),
                'hint' => 'Dentro de las próximas 8 h',
                'tone' => 'amber',
            ],
            [
                'label' => 'Cobradas',
                'value' => (int) (($paymentCounts['paid'] ?? 0) + ($economicCounts['charged'] ?? 0)),
                'hint' => 'Pago sincronizado / cobrado',
                'tone' => 'emerald',
            ],
        ];

        return [
            'cards' => $cards,
            'sla' => [
                'sin_fecha' => (int) ($slaCounts['sin_fecha'] ?? 0),
                'en_tiempo' => (int) ($slaCounts['en_tiempo'] ?? 0),
                'por_vencer' => (int) ($slaCounts['por_vencer'] ?? 0),
                'vencida' => (int) ($slaCounts['vencida'] ?? 0),
                'cerrada_en_tiempo' => (int) ($slaCounts['cerrada_en_tiempo'] ?? 0),
                'cerrada_vencida' => (int) ($slaCounts['cerrada_vencida'] ?? 0),
            ],
            'workflow' => $rows
                ->groupBy(fn ($repair): string => (string) ($repair->workflow_stage ?: $repair->status ?: 'sin_estado'))
                ->map(fn (Collection $group): int => $group->count())
                ->sortKeys()
                ->toArray(),
            'quotes' => $quoteCounts->sortKeys()->toArray(),
            'economic' => [
                'counts' => $economicCounts->sortKeys()->toArray(),
                'payments' => $paymentCounts->sortKeys()->toArray(),
                'totals' => [
                    'quote_total' => $rows->sum(fn ($repair): float => (float) ($repair->quote_total ?? 0)),
                    'economic_total' => $rows->sum(fn ($repair): float => (float) ($repair->economic_total ?? 0)),
                    'parts_sale_total' => $rows->sum(fn ($repair): float => (float) ($repair->parts_sale_total ?? 0)),
                    'labor_sale_total' => $rows->sum(fn ($repair): float => (float) ($repair->labor_sale_total ?? 0)),
                    'profit_total' => $rows->sum(fn ($repair): float => (float) ($repair->total_profit_amount ?? 0)),
                ],
            ],
            'technicians' => $this->techniciansData()->toArray(),
            'time' => $this->timeMetrics($rows),
            'latest' => $rows
                ->take(10)
                ->map(fn ($repair): array => [
                    'id' => (int) ($repair->id ?? 0),
                    'folio' => (string) ($repair->folio ?? 'Sin folio'),
                    'product' => (string) ($repair->product_name ?? 'Sin producto'),
                    'stage' => (string) ($repair->workflow_stage ?: $repair->status ?: 'sin_estado'),
                    'stage_label' => $this->stageLabel((string) ($repair->workflow_stage ?: $repair->status ?: 'sin_estado')),
                    'quote_status' => (string) ($repair->quote_status ?? 'sin_estado'),
                    'quote_label' => $this->quoteLabel((string) ($repair->quote_status ?? 'sin_estado')),
                    'economic_status' => (string) ($repair->economic_status ?? 'sin_estado'),
                    'economic_label' => $this->economicLabel((string) ($repair->economic_status ?? 'sin_estado')),
                    'sla_key' => ServiceRepairSla::key($repair),
                    'sla_label' => ServiceRepairSla::label($repair),
                    'sla_description' => ServiceRepairSla::description($repair),
                    'total' => (float) ($repair->economic_total ?? $repair->quote_total ?? 0),
                    'updated_at' => (string) ($repair->updated_at ?? ''),
                    'edit_url' => $this->repairEditUrl((int) ($repair->id ?? 0)),
                ])
                ->values()
                ->toArray(),
        ];
    }

    public function money(mixed $amount): string
    {
        return '$' . number_format((float) $amount, 2);
    }

    public function percent(mixed $value): string
    {
        return number_format((float) $value, 1) . '%';
    }

    public function stageLabel(?string $stage): string
    {
        return match ((string) $stage) {
            'quote_draft' => 'Presupuesto borrador',
            'quote_submitted' => 'Presupuesto enviado',
            'quote_approved' => 'Presupuesto aprobado',
            'in_repair' => 'En reparación',
            'repaired' => 'Reparado',
            'supervisor_review' => 'Revisión supervisor',
            'ready_for_delivery' => 'Listo para entrega',
            'delivered', 'entregado' => 'Entregado',
            'cancelled', 'cancelado' => 'Cancelado',
            'closed', 'cerrado' => 'Cerrado',
            default => filled($stage) ? ucfirst(str_replace('_', ' ', (string) $stage)) : 'Sin estado',
        };
    }

    public function quoteLabel(?string $status): string
    {
        return match ((string) $status) {
            'draft' => 'Borrador',
            'pending' => 'Pendiente',
            'customer_pending' => 'Pendiente cliente',
            'customer_approved' => 'Aprobado cliente',
            'customer_rejected' => 'Rechazado cliente',
            'internal_pending' => 'Pendiente interno',
            'internal_approved' => 'Aprobado interno',
            'internal_rejected' => 'Rechazado interno',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            default => filled($status) ? ucfirst(str_replace('_', ' ', (string) $status)) : 'Sin presupuesto',
        };
    }

    public function economicLabel(?string $status): string
    {
        return match ((string) $status) {
            'pending' => 'Pendiente',
            'open' => 'Abierto',
            'draft' => 'Borrador',
            'ready_to_charge' => 'Listo para cobrar',
            'receivable_created' => 'CxC creada',
            'partially_charged' => 'Cobro parcial',
            'charged' => 'Cobrado',
            'cancelled' => 'Cancelado',
            default => filled($status) ? ucfirst(str_replace('_', ' ', (string) $status)) : 'Sin cierre',
        };
    }

    public function paymentLabel(?string $status): string
    {
        return match ((string) $status) {
            'pending' => 'Pendiente',
            'partial' => 'Parcial',
            'paid' => 'Pagado',
            'cancelled' => 'Cancelado',
            default => filled($status) ? ucfirst(str_replace('_', ' ', (string) $status)) : 'Sin pago',
        };
    }

    public function slaLabel(string $key): string
    {
        return match ($key) {
            'en_tiempo' => 'En tiempo',
            'por_vencer' => 'Por vencer',
            'vencida' => 'Vencida',
            'cerrada_en_tiempo' => 'Cerrada en tiempo',
            'cerrada_vencida' => 'Cerrada vencida',
            default => 'Sin fecha',
        };
    }

    public function badgeClass(string $key): string
    {
        return match ($key) {
            'en_tiempo', 'cerrada_en_tiempo', 'paid', 'charged', 'customer_approved', 'approved', 'delivered', 'entregado' => 'inline-flex items-center rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-green-600/20',
            'por_vencer', 'ready_for_delivery', 'pending', 'customer_pending', 'internal_pending' => 'inline-flex items-center rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-amber-600/20',
            'vencida', 'cerrada_vencida', 'customer_rejected', 'internal_rejected', 'rejected', 'cancelled', 'cancelado' => 'inline-flex items-center rounded-full bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-red-600/20',
            'in_repair', 'repaired', 'quote_approved', 'quote_submitted' => 'inline-flex items-center rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-blue-600/20',
            default => 'inline-flex items-center rounded-full bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-gray-600/20',
        };
    }

    protected function repairBaseQuery(): Builder
    {
        $query = DB::table('repair_orders');

        if (Schema::hasColumn('repair_orders', 'company_id') && $this->tenantCompanyId()) {
            $query->where('company_id', $this->tenantCompanyId());
        }

        return $query;
    }

    protected function techniciansData(): Collection
    {
        if (! Schema::hasTable('repair_orders') || ! Schema::hasTable('employees')) {
            return collect();
        }

        $query = DB::table('repair_orders as r')
            ->leftJoin('employees as e', 'e.id', '=', 'r.assigned_employee_id')
            ->select([
                'r.assigned_employee_id',
                'e.name as employee_name',
                'e.fiscal_name as employee_fiscal_name',
                'e.employee_number',
            ])
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when r.workflow_stage in ('delivered', 'entregado') then 1 else 0 end) as delivered_total")
            ->selectRaw("sum(case when r.workflow_stage not in ('delivered', 'entregado', 'cancelled', 'cancelado', 'closed', 'cerrado') then 1 else 0 end) as open_total")
            ->selectRaw('coalesce(sum(r.economic_total), 0) as economic_total')
            ->groupBy('r.assigned_employee_id', 'e.name', 'e.fiscal_name', 'e.employee_number')
            ->orderByDesc('total');

        if (Schema::hasColumn('repair_orders', 'company_id') && $this->tenantCompanyId()) {
            $query->where('r.company_id', $this->tenantCompanyId());
        }

        return $query->get()->map(fn ($row): array => [
            'assigned_employee_id' => $row->assigned_employee_id,
            'name' => $row->employee_name ?: ($row->employee_fiscal_name ?: ($row->assigned_employee_id ? 'Técnico #' . $row->assigned_employee_id : 'Sin técnico')),
            'employee_number' => $row->employee_number,
            'total' => (int) $row->total,
            'open_total' => (int) $row->open_total,
            'delivered_total' => (int) $row->delivered_total,
            'economic_total' => (float) $row->economic_total,
        ]);
    }

    protected function timeMetrics(Collection $rows): array
    {
        $mapped = $rows->map(function ($repair): array {
            $received = filled($repair->received_at ?? null) ? \Carbon\Carbon::parse($repair->received_at) : null;
            $startedValue = $repair->repair_started_at ?? $repair->started_at ?? null;
            $finishedValue = $repair->repair_finished_at ?? $repair->finished_at ?? null;
            $deliveredValue = $repair->delivered_at ?? null;

            $started = filled($startedValue) ? \Carbon\Carbon::parse($startedValue) : null;
            $finished = filled($finishedValue) ? \Carbon\Carbon::parse($finishedValue) : null;
            $delivered = filled($deliveredValue) ? \Carbon\Carbon::parse($deliveredValue) : null;

            return [
                'hours_to_start' => $received && $started ? round($received->diffInMinutes($started) / 60, 2) : null,
                'hours_to_finished' => $received && $finished ? round($received->diffInMinutes($finished) / 60, 2) : null,
                'hours_to_delivered' => $received && $delivered ? round($received->diffInMinutes($delivered) / 60, 2) : null,
                'actual_labor_hours' => filled($repair->actual_labor_hours ?? null) ? (float) $repair->actual_labor_hours : null,
            ];
        });

        return [
            'avg_hours_to_start' => round((float) $mapped->pluck('hours_to_start')->filter(fn ($value) => $value !== null)->avg(), 2),
            'avg_hours_to_finished' => round((float) $mapped->pluck('hours_to_finished')->filter(fn ($value) => $value !== null)->avg(), 2),
            'avg_hours_to_delivered' => round((float) $mapped->pluck('hours_to_delivered')->filter(fn ($value) => $value !== null)->avg(), 2),
            'avg_actual_labor_hours' => round((float) $mapped->pluck('actual_labor_hours')->filter(fn ($value) => $value !== null)->avg(), 2),
        ];
    }

    protected function repairEditUrl(int $repairId): string
    {
        if ($repairId <= 0) {
            return '#';
        }

        $tenantId = $this->tenantCompanyId();

        if ($tenantId) {
            return url('/admin/' . $tenantId . '/repair-orders/' . $repairId . '/edit');
        }

        return url('/admin/repair-orders/' . $repairId . '/edit');
    }

    protected function tenantCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        if ($tenant && isset($tenant->id)) {
            return (int) $tenant->id;
        }

        return null;
    }

    protected function isClosedRepair(object $repair): bool
    {
        $stage = strtolower((string) ($repair->workflow_stage ?? ''));
        $status = strtolower((string) ($repair->status ?? ''));

        $closed = ['delivered', 'entregado', 'cancelled', 'cancelado', 'closed', 'cerrado'];

        return in_array($stage, $closed, true) || in_array($status, $closed, true);
    }

    protected function isDeliveredRepair(object $repair): bool
    {
        $stage = strtolower((string) ($repair->workflow_stage ?? ''));
        $status = strtolower((string) ($repair->status ?? ''));

        return in_array($stage, ['delivered', 'entregado'], true)
            || in_array($status, ['delivered', 'entregado'], true);
    }

    protected function emptyData(): array
    {
        return [
            'cards' => [],
            'sla' => [],
            'workflow' => [],
            'quotes' => [],
            'economic' => [
                'counts' => [],
                'payments' => [],
                'totals' => [],
            ],
            'technicians' => [],
            'time' => [],
            'latest' => [],
        ];
    }
}
