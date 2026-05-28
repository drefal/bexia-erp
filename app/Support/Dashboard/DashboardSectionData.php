<?php

namespace App\Support\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardSectionData
{
    public function currentCompanyId(): ?int
    {
        try {
            return app(DashboardWidgetRegistry::class)->currentCompanyId();
        } catch (\Throwable) {
            return null;
        }
    }

    public function companyName(?int $companyId): ?string
    {
        if (! $companyId || ! Schema::hasTable('companies')) {
            return null;
        }

        return DB::table('companies')->where('id', $companyId)->value('name');
    }

    public function widgetVisible(string $key, ?int $companyId = null): bool
    {
        try {
            $user = auth()->user();

            if (! $user || ! Schema::hasTable('dashboard_widget_user_settings')) {
                return true;
            }

            $companyId = $companyId ?: $this->currentCompanyId() ?: 5;

            $row = DB::table('dashboard_widget_user_settings')
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->where('widget_key', $key)
                ->first();

            return $row ? (bool) $row->is_visible : true;
        } catch (\Throwable) {
            return true;
        }
    }

    public function hr(?int $companyId = null): array
    {
        $companyId = $companyId ?: $this->currentCompanyId();

        $employees = $this->metrics('hr_employees_summary');
        $payroll = $this->metrics('payroll_runs_summary');
        $cfdi = $this->metrics('payroll_cfdi_summary');

        return [
            'company_id' => $companyId,
            'company_name' => $this->companyName($companyId),
            'updated_at' => now()->format('H:i:s'),
            'cards' => [
                [
                    'label' => 'Empleados activos',
                    'value' => $employees['active'] ?? $employees['active_count'] ?? 0,
                    'description' => 'Personal activo en la empresa',
                ],
                [
                    'label' => 'Empleados inactivos',
                    'value' => $employees['inactive'] ?? $employees['inactive_count'] ?? 0,
                    'description' => 'Personal inactivo',
                ],
                [
                    'label' => 'Total empleados',
                    'value' => $employees['total'] ?? (($employees['active'] ?? 0) + ($employees['inactive'] ?? 0)),
                    'description' => 'Activos + inactivos',
                ],
                [
                    'label' => 'Nóminas cerradas',
                    'value' => $payroll['closed'] ?? $payroll['closed_count'] ?? 0,
                    'description' => 'Corridas cerradas',
                ],
                [
                    'label' => 'Nóminas aprobadas',
                    'value' => $payroll['approved'] ?? $payroll['approved_count'] ?? 0,
                    'description' => 'Con aprobación completada',
                ],
                [
                    'label' => 'Neto acumulado',
                    'value' => '$ ' . number_format((float) ($payroll['net_total'] ?? $payroll['net_accumulated'] ?? $payroll['total_net'] ?? 0), 2),
                    'description' => 'Total neto de nóminas',
                ],
                [
                    'label' => 'CFDI validados',
                    'value' => $cfdi['validated'] ?? $cfdi['valid'] ?? 0,
                    'description' => 'Recibos validados sin timbrado SAT',
                ],
                [
                    'label' => 'CFDI timbrados',
                    'value' => $cfdi['stamped'] ?? $cfdi['timbrados'] ?? 0,
                    'description' => 'Timbrados por PAC o externos',
                ],
                [
                    'label' => 'Internos / no requeridos',
                    'value' => $cfdi['internal'] ?? $cfdi['not_required'] ?? 0,
                    'description' => 'Recibos sin CFDI fiscal',
                ],
                [
                    'label' => 'Errores CFDI',
                    'value' => $cfdi['errors'] ?? $cfdi['error'] ?? 0,
                    'description' => 'Recibos con error',
                ],
            ],
        ];
    }

    public function accounting(?int $companyId = null): array
    {
        $companyId = $companyId ?: $this->currentCompanyId();

        $metrics = $this->metrics('payroll_accounting_summary');

        return [
            'company_id' => $companyId,
            'company_name' => $this->companyName($companyId),
            'updated_at' => now()->format('H:i:s'),
            'cards' => [
                [
                    'label' => 'Nóminas contabilizadas',
                    'value' => $metrics['accounted'] ?? $metrics['posted'] ?? 0,
                    'description' => 'Pólizas activas de nómina',
                ],
                [
                    'label' => 'Pendientes de póliza',
                    'value' => $metrics['pending'] ?? $metrics['pending_policy'] ?? 0,
                    'description' => 'Nóminas cerradas sin póliza activa',
                ],
                [
                    'label' => 'Pólizas reversadas',
                    'value' => $metrics['reversed'] ?? $metrics['reversals'] ?? 0,
                    'description' => 'Reversas contables de nómina',
                ],
            ],
        ];
    }

    public function treasury(?int $companyId = null): array
    {
        $companyId = $companyId ?: $this->currentCompanyId();

        $cashScopes = ['pdv', 'branch_cash', 'general_cash', 'admin_cash', 'cedis_cash'];

        $accounts = collect();
        $movements = collect();
        $transit = collect();
        $flow = collect(range(0, 23))->mapWithKeys(fn (int $hour): array => [
            $hour => [
                'hour' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00',
                'in' => 0.0,
                'out' => 0.0,
            ],
        ]);

        if ($companyId && Schema::hasTable('treasury_accounts')) {
            $accounts = DB::table('treasury_accounts')
                ->where('company_id', $companyId)
                ->whereIn('cash_scope', $cashScopes)
                ->where('is_active', true)
                ->orderByRaw("
                    case cash_scope
                        when 'pdv' then 10
                        when 'branch_cash' then 20
                        when 'general_cash' then 30
                        when 'admin_cash' then 40
                        when 'cedis_cash' then 50
                        else 99
                    end
                ")
                ->orderBy('name')
                ->get();
        }

        if ($companyId && Schema::hasTable('treasury_movements')) {
            $movements = DB::table('treasury_movements as tm')
                ->leftJoin('treasury_accounts as ta', 'ta.id', '=', 'tm.treasury_account_id')
                ->where('tm.company_id', $companyId)
                ->select([
                    'tm.id',
                    'tm.type',
                    'tm.status',
                    'tm.amount',
                    'tm.reference',
                    'tm.created_at',
                    'tm.posted_at',
                    'ta.name as account_name',
                    'ta.cash_scope',
                ])
                ->orderByDesc('tm.id')
                ->limit(10)
                ->get();

            DB::table('treasury_movements')
                ->where('company_id', $companyId)
                ->whereDate('created_at', now()->toDateString())
                ->selectRaw("extract(hour from created_at)::int as hour, type, coalesce(sum(amount), 0) as total")
                ->groupBy(DB::raw("extract(hour from created_at)::int"), 'type')
                ->get()
                ->each(function ($row) use (&$flow): void {
                    $hour = (int) $row->hour;
                    $type = (string) $row->type;
                    $amount = (float) $row->total;

                    if (! $flow->has($hour)) {
                        return;
                    }

                    $data = $flow->get($hour);

                    if (in_array($type, ['inflow', 'inbound'], true)) {
                        $data['in'] += $amount;
                    }

                    if (in_array($type, ['outflow', 'outbound'], true)) {
                        $data['out'] += $amount;
                    }

                    $flow->put($hour, $data);
                });
        }

        if ($companyId && Schema::hasTable('treasury_cash_transfer_requests')) {
            $transit = DB::table('treasury_cash_transfer_requests as r')
                ->leftJoin('treasury_accounts as src', 'src.id', '=', 'r.source_treasury_account_id')
                ->leftJoin('treasury_accounts as dst', 'dst.id', '=', 'r.destination_treasury_account_id')
                ->where('r.company_id', $companyId)
                ->whereNull('r.posted_at')
                ->whereIn('r.status', ['requested', 'pending_approval', 'approved', 'delivered', 'received'])
                ->select([
                    'r.id',
                    'r.number',
                    'r.type',
                    'r.status',
                    'r.approval_status',
                    'r.amount',
                    'r.created_at',
                    'src.name as source_name',
                    'dst.name as destination_name',
                ])
                ->orderByDesc('r.id')
                ->limit(8)
                ->get();
        }

        $totalCash = (float) $accounts->sum(fn ($row): float => (float) ($row->current_balance ?? 0));
        $todayIn = (float) $flow->sum('in');
        $todayOut = (float) $flow->sum('out');
        $transitTotal = (float) $transit->sum(fn ($row): float => (float) ($row->amount ?? 0));

        $maxBalance = max(1.0, (float) $accounts->max(fn ($row): float => abs((float) ($row->current_balance ?? 0))));

        $columns = $accounts->map(function ($row) use ($maxBalance): array {
            $balance = (float) ($row->current_balance ?? 0);
            $percent = $balance <= 0 ? 4 : min(100, max(8, round(($balance / $maxBalance) * 100, 2)));

            return [
                'name' => (string) $row->name,
                'scope' => (string) ($row->cash_scope ?? ''),
                'scope_label' => $this->scopeLabel((string) ($row->cash_scope ?? '')),
                'balance' => $balance,
                'money' => '$ ' . number_format($balance, 2),
                'percent' => $percent,
                'color' => $this->columnColor($percent, $balance),
            ];
        })->values();

        return [
            'company_id' => $companyId,
            'company_name' => $this->companyName($companyId),
            'updated_at' => now()->format('H:i:s'),
            'refresh_seconds' => $this->refreshSeconds('tesoreria', $companyId),
            'refresh_label' => $this->refreshLabel($this->refreshSeconds('tesoreria', $companyId)),
            'total_cash' => $totalCash,
            'transit_total' => $transitTotal,
            'today_in' => $todayIn,
            'today_out' => $todayOut,
            'columns' => $columns,
            'flow' => $flow->values(),
            'flow_max' => max(1.0, (float) $flow->values()->max(fn (array $row): float => max($row['in'], $row['out']))),
            'transit' => $transit,
            'movements' => $movements,
        ];
    }

    private function metrics(string $key): array
    {
        try {
            return app(DashboardWidgetRegistry::class)->metrics($key);
        } catch (\Throwable) {
            return [];
        }
    }

    private function scopeLabel(string $scope): string
    {
        return match ($scope) {
            'pdv' => 'Caja PDV',
            'branch_cash' => 'Caja sucursal',
            'general_cash' => 'Caja general',
            'admin_cash' => 'Administración',
            'cedis_cash' => 'Bodega / CEDIS',
            default => $scope !== '' ? str_replace('_', ' ', $scope) : 'Caja',
        };
    }

    private function columnColor(float $percent, float $balance): string
    {
        if ($balance <= 0) {
            return '#94a3b8';
        }

        return match (true) {
            $percent < 25 => '#ef4444',
            $percent < 50 => '#f59e0b',
            $percent < 80 => '#3b82f6',
            default => '#22c55e',
        };
    }

    public function refreshSeconds(string $sectionKey = 'tesoreria', ?int $companyId = null): int
    {
        $allowed = [30, 60, 120, 300, 600];

        try {
            $user = auth()->user();

            if (! $user || ! \Illuminate\Support\Facades\Schema::hasTable('dashboard_section_user_settings')) {
                return 60;
            }

            $companyId = $companyId ?: $this->currentCompanyId() ?: 5;

            $seconds = (int) \Illuminate\Support\Facades\DB::table('dashboard_section_user_settings')
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->where('section_key', $sectionKey)
                ->value('refresh_seconds');

            return in_array($seconds, $allowed, true) ? $seconds : 60;
        } catch (\Throwable) {
            return 60;
        }
    }

    public function refreshLabel(int $seconds): string
    {
        return match ($seconds) {
            30 => '30 segundos',
            60 => '1 minuto',
            120 => '2 minutos',
            300 => '5 minutos',
            600 => '10 minutos',
            default => $seconds . ' segundos',
        };
    }

}
