<?php

namespace App\Support\Dashboard;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

class DashboardWidgetRegistry
{
    public function catalog(): array
    {
        return [
            'approvals_summary' => [
                'key' => 'approvals_summary',
                'label' => 'Resumen de aprobaciones',
                'description' => 'Indicadores de documentos por aprobar, enviados pendientes y avisos.',
                'module' => 'Inicio',
                'default_visible' => true,
                'sort_order' => 10,
                'permissions_any' => ['approvals.approve', 'approvals.view_workflows', 'dashboard.ver'],
            ],
            'approvals_pending' => [
                'key' => 'approvals_pending',
                'label' => 'Aprobaciones pendientes',
                'description' => 'Listado de documentos pendientes para el usuario.',
                'module' => 'Inicio',
                'default_visible' => true,
                'sort_order' => 20,
                'permissions_any' => ['approvals.approve', 'dashboard.ver'],
            ],
            'notices' => [
                'key' => 'notices',
                'label' => 'Mis avisos',
                'description' => 'Avisos y notificaciones recientes del usuario.',
                'module' => 'Inicio',
                'default_visible' => true,
                'sort_order' => 30,
                'permissions_any' => ['dashboard.ver'],
            ],
            'hr_employees_summary' => [
                'key' => 'hr_employees_summary',
                'label' => 'Resumen RRHH',
                'description' => 'Empleados activos e inactivos de la empresa.',
                'module' => 'Recursos Humanos',
                'default_visible' => true,
                'sort_order' => 40,
                'permissions_any' => [
                    'rrhh.empleados.ver',
                    'hr.employees.view',
                    'employees.view',
                    'dashboard.ver',
                ],
            ],
            'payroll_runs_summary' => [
                'key' => 'payroll_runs_summary',
                'label' => 'Resumen de nómina',
                'description' => 'Nóminas abiertas, cerradas, aprobadas y totales netos.',
                'module' => 'Recursos Humanos',
                'default_visible' => true,
                'sort_order' => 50,
                'permissions_any' => [
                    'nomina.prenomina.ver',
                    'nomina.prenomina.cerrar',
                    'dashboard.ver',
                ],
            ],
            'payroll_cfdi_summary' => [
                'key' => 'payroll_cfdi_summary',
                'label' => 'CFDI nómina',
                'description' => 'Recibos CFDI de nómina por estado.',
                'module' => 'Recursos Humanos',
                'default_visible' => true,
                'sort_order' => 60,
                'permissions_any' => [
                    'nomina.cfdi.ver',
                    'nomina.prenomina.ver',
                    'dashboard.ver',
                ],
            ],
            'payroll_accounting_summary' => [
                'key' => 'payroll_accounting_summary',
                'label' => 'Contabilidad de nómina',
                'description' => 'Pólizas de nómina, reversas y nóminas pendientes de contabilizar.',
                'module' => 'Contabilidad',
                'default_visible' => true,
                'sort_order' => 70,
                'permissions_any' => [
                    'accounting.view',
                    'accounting.post',
                    'nomina.prenomina.cerrar',
                    'dashboard.ver',
                ],
            ],
        ];
    }

    public function currentCompanyId(): int
    {
        try {
            $tenant = filament()->getTenant();

            if ($tenant && isset($tenant->id)) {
                return (int) $tenant->id;
            }
        } catch (\Throwable) {
        }

        $sessionCompanyId = (int) (session('company_id') ?? 0);

        if ($sessionCompanyId > 0) {
            return $sessionCompanyId;
        }

        try {
            return (int) app(PermissionRegistrar::class)->getPermissionsTeamId();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function visibleForUser(?User $user = null, ?int $companyId = null): Collection
    {
        $user = $user ?: auth()->user();

        if (! $user) {
            return collect();
        }

        $companyId = $companyId ?: $this->currentCompanyId();

        if ($companyId <= 0) {
            return collect();
        }

        $preferences = $this->preferencesFor((int) $companyId, (int) $user->id);

        return collect($this->catalog())
            ->map(function (array $definition) use ($preferences, $user): array {
                $key = (string) $definition['key'];
                $preference = $preferences->get($key);

                $definition['is_visible'] = $preference
                    ? (bool) $preference->is_visible
                    : (bool) ($definition['default_visible'] ?? true);

                $definition['sort_order'] = $preference
                    ? (int) $preference->sort_order
                    : (int) ($definition['sort_order'] ?? 100);

                $definition['settings'] = $preference && $preference->settings
                    ? json_decode((string) $preference->settings, true)
                    : [];

                $definition['allowed_by_permission'] = $this->userCanViewDefinition($user, $definition);

                return $definition;
            })
            ->filter(fn (array $definition): bool => (bool) $definition['is_visible'] && (bool) $definition['allowed_by_permission'])
            ->sortBy([
                ['sort_order', 'asc'],
                ['label', 'asc'],
            ])
            ->values();
    }

    public function preferencesFor(int $companyId, int $userId): Collection
    {
        if (
            $companyId <= 0
            || $userId <= 0
            || ! Schema::hasTable('dashboard_widget_user_settings')
        ) {
            return collect();
        }

        return DB::table('dashboard_widget_user_settings')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('widget_key');
    }

    public function userCanViewDefinition(User $user, array $definition): bool
    {
        if ((bool) ($user->is_system_admin ?? false)) {
            return true;
        }

        $permissions = (array) ($definition['permissions_any'] ?? []);

        if ($permissions === []) {
            return true;
        }

        foreach ($permissions as $permission) {
            try {
                if ($user->can((string) $permission)) {
                    return true;
                }
            } catch (\Throwable) {
            }
        }

        return false;
    }

    public function metrics(string $widgetKey, ?int $companyId = null, ?int $userId = null): array
    {
        $companyId = $companyId ?: $this->currentCompanyId();
        $userId = $userId ?: (int) auth()->id();

        return match ($widgetKey) {
            'approvals_summary' => $this->approvalMetrics($companyId, $userId),
            'approvals_pending' => $this->approvalMetrics($companyId, $userId),
            'notices' => $this->noticeMetrics($userId),
            'hr_employees_summary' => $this->employeeMetrics($companyId),
            'payroll_runs_summary' => $this->payrollRunMetrics($companyId),
            'payroll_cfdi_summary' => $this->payrollCfdiMetrics($companyId),
            'payroll_accounting_summary' => $this->payrollAccountingMetrics($companyId),
            default => [],
        };
    }

    protected function approvalMetrics(int $companyId, int $userId): array
    {
        $pendingToApprove = 0;
        $sentPending = 0;
        $totalPendingCompany = 0;

        if (
            $companyId > 0
            && $userId > 0
            && Schema::hasTable('approval_requests')
            && Schema::hasTable('approval_request_steps')
        ) {
            $pendingToApprove = DB::table('approval_request_steps as steps')
                ->join('approval_requests as requests', 'requests.id', '=', 'steps.approval_request_id')
                ->where('requests.company_id', $companyId)
                ->where('steps.status', 'pending')
                ->where('requests.status', 'pending')
                ->whereColumn('steps.step_order', 'requests.current_step_order')
                ->where('steps.approver_user_id', $userId)
                ->count();

            $sentPending = DB::table('approval_requests')
                ->where('company_id', $companyId)
                ->where('requester_user_id', $userId)
                ->where('status', 'pending')
                ->count();

            $totalPendingCompany = DB::table('approval_requests')
                ->where('company_id', $companyId)
                ->where('status', 'pending')
                ->count();
        }

        return [
            'pending_to_approve' => $pendingToApprove,
            'sent_pending' => $sentPending,
            'company_pending' => $totalPendingCompany,
        ];
    }

    protected function noticeMetrics(int $userId): array
    {
        $unread = 0;
        $total = 0;

        if (class_exists(\App\Support\BexiaUserNotification::class)) {
            $unread = (int) \App\Support\BexiaUserNotification::unreadCountForUser($userId);
        }

        if ($userId > 0 && Schema::hasTable('bexia_notifications')) {
            $total = DB::table('bexia_notifications')
                ->where('user_id', $userId)
                ->count();
        }

        return [
            'unread' => $unread,
            'total' => $total,
        ];
    }

    protected function employeeMetrics(int $companyId): array
    {
        $active = 0;
        $inactive = 0;

        if ($companyId > 0 && Schema::hasTable('employees')) {
            $active = DB::table('employees')
                ->where('company_id', $companyId)
                ->where('active', true)
                ->count();

            $inactive = DB::table('employees')
                ->where('company_id', $companyId)
                ->where('active', false)
                ->count();
        }

        return [
            'active' => $active,
            'inactive' => $inactive,
            'total' => $active + $inactive,
        ];
    }

    protected function payrollRunMetrics(int $companyId): array
    {
        if ($companyId <= 0 || ! Schema::hasTable('payroll_runs')) {
            return [
                'total' => 0,
                'closed' => 0,
                'approved' => 0,
                'pending' => 0,
                'net_total' => 0.0,
            ];
        }

        $rows = DB::table('payroll_runs')
            ->where('company_id', $companyId)
            ->select('status', DB::raw('count(*) as total'), DB::raw('coalesce(sum(net_total), 0) as net_total'))
            ->groupBy('status')
            ->get();

        return [
            'total' => (int) $rows->sum('total'),
            'closed' => (int) ($rows->firstWhere('status', 'closed')->total ?? 0),
            'approved' => (int) DB::table('payroll_runs')
                ->where('company_id', $companyId)
                ->where('approval_status', 'approved')
                ->count(),
            'pending' => (int) ($rows->firstWhere('status', 'draft')->total ?? 0),
            'net_total' => (float) $rows->sum('net_total'),
        ];
    }

    protected function payrollCfdiMetrics(int $companyId): array
    {
        if ($companyId <= 0 || ! Schema::hasTable('payroll_cfdi_receipts')) {
            return [];
        }

        return DB::table('payroll_cfdi_receipts')
            ->where('company_id', $companyId)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }

    protected function payrollAccountingMetrics(int $companyId): array
    {
        if ($companyId <= 0 || ! Schema::hasTable('accounting_entries')) {
            return [
                'posted' => 0,
                'cancelled' => 0,
                'reversals' => 0,
                'pending_payroll_runs' => 0,
            ];
        }

        $posted = DB::table('accounting_entries')
            ->where('company_id', $companyId)
            ->where('source_type', 'payroll_run')
            ->whereIn('status', ['draft', 'posted'])
            ->count();

        $cancelled = DB::table('accounting_entries')
            ->where('company_id', $companyId)
            ->where('source_type', 'payroll_run')
            ->where('status', 'cancelled')
            ->count();

        $reversals = DB::table('accounting_entries')
            ->where('company_id', $companyId)
            ->where('source_type', 'payroll_run_reversal')
            ->count();

        $pendingPayrollRuns = 0;

        if (Schema::hasTable('payroll_runs')) {
            $pendingPayrollRuns = DB::table('payroll_runs as pr')
                ->where('pr.company_id', $companyId)
                ->whereIn('pr.status', ['closed', 'approved', 'paid'])
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('accounting_entries as ae')
                        ->whereColumn('ae.company_id', 'pr.company_id')
                        ->whereColumn('ae.source_id', 'pr.id')
                        ->where('ae.source_type', 'payroll_run')
                        ->whereIn('ae.status', ['draft', 'posted']);
                })
                ->count();
        }

        return [
            'posted' => (int) $posted,
            'cancelled' => (int) $cancelled,
            'reversals' => (int) $reversals,
            'pending_payroll_runs' => (int) $pendingPayrollRuns,
        ];
    }
}
