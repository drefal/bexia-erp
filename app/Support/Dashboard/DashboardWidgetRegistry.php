<?php

namespace App\Support\Dashboard;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

// BEXIA_V57210M2_INICIO_WIDGETS_SIN_PERMISO_OPERATIVO
class DashboardWidgetRegistry
{
    public function catalog(): array
    {
        // BEXIA_V582_P7G9_LEGACY_WIDGETS_REMOVED
        return [
                'hr_dashboard_section' => [
                    'key' => 'hr_dashboard_section',
                    'label' => 'Sección completa RRHH',
                    'description' => 'Agrupa empleados, nómina y CFDI en una sola sección lila.',
                    'module' => 'Recursos Humanos',
                    'default_visible' => true,
                    'sort' => 39,
                    'permissions_any' => ['dashboard.ver', 'rrhh.view', 'rrhh.empleados.ver', 'nomina.ver'],
                    'class' => \App\Filament\Widgets\BexiaHrDashboardSectionWidget::class,
                ],
                'accounting_dashboard_section' => [
                    'key' => 'accounting_dashboard_section',
                    'label' => 'Sección completa Contabilidad',
                    'description' => 'Agrupa indicadores contables en una sola sección azul.',
                    'module' => 'Contabilidad',
                    'default_visible' => true,
                    'sort' => 69,
                    'permissions_any' => ['dashboard.ver', 'contabilidad.ver', 'accounting.view'],
                    'class' => \App\Filament\Widgets\BexiaAccountingDashboardSectionWidget::class,
                ],
                'treasury_dashboard_section' => [
                    'key' => 'treasury_dashboard_section',
                    'label' => 'Sección completa Tesorería',
                    'description' => 'Agrupa efectivo, cajas, tránsito y movimientos en una sola sección verde.',
                    'module' => 'Tesorería',
                    'default_visible' => true,
                    'sort' => 80,
                    'permissions_any' => ['dashboard.ver', 'treasury.view', 'treasury.update'],
                    'class' => \App\Filament\Widgets\BexiaTreasuryDashboardSectionWidget::class,
                ],
            'approvals_summary' => [
                'key' => 'approvals_summary',
                'label' => 'Resumen de aprobaciones',
                'description' => 'Indicadores de documentos por aprobar, enviados pendientes y avisos.',
                'module' => 'Inicio',
                'default_visible' => true,
                'sort_order' => 10,
                'permissions_any' => [],
            ],
            'approvals_pending' => [
                'key' => 'approvals_pending',
                'label' => 'Aprobaciones pendientes',
                'description' => 'Listado de documentos pendientes para el usuario.',
                'module' => 'Inicio',
                'default_visible' => true,
                'sort_order' => 20,
                    'sort' => 20,
                'permissions_any' => [],
            ],
            'notices' => [
                'key' => 'notices',
                'label' => 'Mis avisos',
                'description' => 'Avisos y notificaciones recientes del usuario.',
                'module' => 'Inicio',
                'default_visible' => true,
                'sort_order' => 30,
                    'sort' => 30,
                'permissions_any' => [],
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
            $teamId = (int) app(PermissionRegistrar::class)->getPermissionsTeamId();

            if ($teamId > 0) {
                return $teamId;
            }
        } catch (\Throwable) {
        }

        $userId = (int) auth()->id();

        if ($userId > 0 && Schema::hasTable('dashboard_widget_user_settings')) {
            $companyIdFromPreferences = (int) DB::table('dashboard_widget_user_settings')
                ->where('user_id', $userId)
                ->orderBy('company_id')
                ->value('company_id');

            if ($companyIdFromPreferences > 0) {
                return $companyIdFromPreferences;
            }
        }

        if ($userId > 0) {
            foreach (['company_user', 'company_user_access', 'company_users'] as $pivotTable) {
                if (! Schema::hasTable($pivotTable)) {
                    continue;
                }

                $columns = Schema::getColumnListing($pivotTable);

                if (! in_array('user_id', $columns, true) || ! in_array('company_id', $columns, true)) {
                    continue;
                }

                $companyIdFromPivot = (int) DB::table($pivotTable)
                    ->where('user_id', $userId)
                    ->orderBy('company_id')
                    ->value('company_id');

                if ($companyIdFromPivot > 0) {
                    return $companyIdFromPivot;
                }
            }
        }

        return 0;
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
            ->map(function (array $definition) use ($preferences, $user, $companyId): array {
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

                $definition['allowed_by_permission'] = $this->userCanViewDefinition(
                    $user,
                    $definition,
                    (int) $companyId
                );

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

    public function userCanViewDefinition(
        User $user,
        array $definition,
        ?int $companyId = null
    ): bool {
        if ((bool) ($user->is_system_admin ?? false)) {
            return true;
        }

        $permissions = array_values(array_filter(array_map(
            'strval',
            (array) ($definition['permissions_any'] ?? [])
        )));

        if ($permissions === []) {
            return true;
        }

        $companyId = $companyId ?: $this->currentCompanyId();

        if ($companyId <= 0) {
            return false;
        }

        $permissionMap = $this->permissionNameMapForUserCompany(
            $user,
            $companyId
        );

        foreach ($permissions as $permission) {
            if (isset($permissionMap[$permission])) {
                return true;
            }
        }

        return false;
    }

    private function permissionNameMapForUserCompany(
        User $user,
        int $companyId
    ): array {
        if ($companyId <= 0 || ! $user->getKey()) {
            return [];
        }

        $cacheKey = implode('|', [
            (string) $user->getKey(),
            (string) $companyId,
            'web',
        ]);

        $request = app()->bound('request')
            ? request()
            : null;

        $cache = $request
            ? (array) $request->attributes->get(
                'bexia.dashboard.permission_name_maps',
                []
            )
            : [];

        if (array_key_exists($cacheKey, $cache)) {
            return (array) $cache[$cacheKey];
        }

        try {
            $modelType = $user->getMorphClass();

            $rolePermissionNames = DB::table(
                'model_has_roles as mhr'
            )
                ->join(
                    'role_has_permissions as rhp',
                    'rhp.role_id',
                    '=',
                    'mhr.role_id'
                )
                ->join(
                    'permissions as p',
                    'p.id',
                    '=',
                    'rhp.permission_id'
                )
                ->where('mhr.model_type', $modelType)
                ->where('mhr.model_id', $user->getKey())
                ->where('mhr.company_id', $companyId)
                ->where('p.guard_name', 'web')
                ->pluck('p.name');

            $directPermissionNames = DB::table(
                'model_has_permissions as mhp'
            )
                ->join(
                    'permissions as p',
                    'p.id',
                    '=',
                    'mhp.permission_id'
                )
                ->where('mhp.model_type', $modelType)
                ->where('mhp.model_id', $user->getKey())
                ->where('mhp.company_id', $companyId)
                ->where('p.guard_name', 'web')
                ->pluck('p.name');

            $permissionMap = $rolePermissionNames
                ->merge($directPermissionNames)
                ->mapWithKeys(
                    static fn ($name): array => [
                        (string) $name => true,
                    ]
                )
                ->all();
        } catch (\Throwable) {
            $permissionMap = [];
        }

        if ($request) {
            $cache[$cacheKey] = $permissionMap;
            $request->attributes->set(
                'bexia.dashboard.permission_name_maps',
                $cache
            );
        }

        return $permissionMap;
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
