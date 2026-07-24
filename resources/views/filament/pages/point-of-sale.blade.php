



@php
    $v5327bUser = auth()->user();

    $v5327bCanManageAllPos = false;

    if ($v5327bUser) {
        foreach (['isSystemAdmin', 'isGroupAdmin', 'isCompanyAdmin', 'isAdmin'] as $method) {
            if (method_exists($v5327bUser, $method) && $v5327bUser->{$method}()) {
                $v5327bCanManageAllPos = true;
            }
        }

        foreach (['role', 'type', 'role_name'] as $attribute) {
            $value = strtolower((string) ($v5327bUser->{$attribute} ?? ''));

            if (in_array($value, [
                'super_admin',
                'system_admin',
                'admin',
                'administrator',
                'company_admin',
                'group_admin',
                'admin_empresa',
                'admin_grupo',
                'super administrador',
                'administrador',
            ], true)) {
                $v5327bCanManageAllPos = true;
            }
        }

        try {
            if (! $v5327bCanManageAllPos && method_exists($v5327bUser, 'roles')) {
                $roles = $v5327bUser->roles()->pluck('name')->map(fn ($name) => strtolower((string) $name))->all();

                foreach ($roles as $role) {
                    if (in_array($role, [
                        'super_admin',
                        'system_admin',
                        'admin',
                        'administrator',
                        'company_admin',
                        'group_admin',
                        'admin_empresa',
                        'admin_grupo',
                        'super administrador',
                        'administrador',
                    ], true)) {
                        $v5327bCanManageAllPos = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            //
        }

        if (! $v5327bCanManageAllPos && method_exists($v5327bUser, 'can')) {
            foreach ([
                'company.update',
                'pos.manage',
                'pos.view_all',
                'pos_points.view_all',
                'punto_de_venta.manage',
                'punto_de_venta.view_all',
            ] as $permission) {
                try {
                    if ($v5327bUser->can($permission)) {
                        $v5327bCanManageAllPos = true;
                    }
                } catch (\Throwable $e) {
                    //
                }
            }
        }
    }

    // BEXIA_V582_P3_XLSM_A30A_UNIFIED_POS_ASSIGNMENTS
    // Unica lista efectiva: relacion usuario-PDV, empleado-PDV y cajero legacy.
    $v5327bAssignedIds = collect();

    if (! $v5327bCanManageAllPos && $v5327bUser) {
        if (
            \Illuminate\Support\Facades\Schema::hasTable('pos_point_user')
            && \Illuminate\Support\Facades\Schema::hasColumn('pos_point_user', 'user_id')
            && \Illuminate\Support\Facades\Schema::hasColumn('pos_point_user', 'pos_point_id')
        ) {
            $pointUserQuery = \Illuminate\Support\Facades\DB::table('pos_point_user')
                ->where('user_id', $v5327bUser->id);

            if (\Illuminate\Support\Facades\Schema::hasColumn('pos_point_user', 'is_active')) {
                $pointUserQuery->where('is_active', true);
            }

            $v5327bAssignedIds = $v5327bAssignedIds->merge($pointUserQuery->pluck('pos_point_id'));
        }

        if (
            \Illuminate\Support\Facades\Schema::hasTable('employees')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_point_employee')
            && \Illuminate\Support\Facades\Schema::hasColumn('employees', 'user_id')
        ) {
            $employeeQuery = \Illuminate\Support\Facades\DB::table('employees')
                ->where('user_id', $v5327bUser->id);

            if (\Illuminate\Support\Facades\Schema::hasColumn('employees', 'pos_active')) {
                $employeeQuery->where('pos_active', true);
            }

            $employeeIds = $employeeQuery->pluck('id')->values()->all();

            if ($employeeIds) {
                $v5327bAssignedIds = $v5327bAssignedIds->merge(
                    \Illuminate\Support\Facades\DB::table('pos_point_employee')
                        ->whereIn('employee_id', $employeeIds)
                        ->where('is_active', true)
                        ->pluck('pos_point_id')
                );
            }
        }

        if (
            \Illuminate\Support\Facades\Schema::hasTable('pos_cashiers')
            && \Illuminate\Support\Facades\Schema::hasColumn('pos_cashiers', 'user_id')
        ) {
            $legacyQuery = \Illuminate\Support\Facades\DB::table('pos_cashiers')
                ->where('user_id', $v5327bUser->id);

            if (\Illuminate\Support\Facades\Schema::hasColumn('pos_cashiers', 'is_active')) {
                $legacyQuery->where('is_active', true);
            }

            $v5327bAssignedIds = $v5327bAssignedIds->merge($legacyQuery->pluck('pos_point_id'));
        }

        // BEXIA_V582_P3_XLSM_A30A_FILTER_ONCE
        $v5327bAssignedIds = $v5327bAssignedIds
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if (isset($posPoints)) {
            $posPoints = collect($posPoints)->whereIn('id', $v5327bAssignedIds->all())->values();
        }

        if (isset($points)) {
            $points = collect($points)->whereIn('id', $v5327bAssignedIds->all())->values();
        }

        if (isset($pdvs)) {
            $pdvs = collect($pdvs)->whereIn('id', $v5327bAssignedIds->all())->values();
        }
    }

    $currentCompanyLabel = $currentCompanyLabel ?? null;

    if (! $currentCompanyLabel) {
        $currentCompanyIdForLabel = $currentCompanyId
            ?? $companyId
            ?? $tenantId
            ?? null;

        try {
            $tenantForLabel = \Filament\Facades\Filament::getTenant();

            if (! $currentCompanyIdForLabel && is_object($tenantForLabel) && method_exists($tenantForLabel, 'getKey')) {
                $currentCompanyIdForLabel = $tenantForLabel->getKey();
            } elseif (! $currentCompanyIdForLabel && is_numeric($tenantForLabel)) {
                $currentCompanyIdForLabel = $tenantForLabel;
            }
        } catch (\Throwable $e) {
            //
        }

        if (! $currentCompanyIdForLabel) {
            $routeTenantForLabel = request()->route('tenant');

            if (is_object($routeTenantForLabel) && method_exists($routeTenantForLabel, 'getKey')) {
                $currentCompanyIdForLabel = $routeTenantForLabel->getKey();
            } elseif (is_numeric($routeTenantForLabel)) {
                $currentCompanyIdForLabel = $routeTenantForLabel;
            }
        }

        $currentCompanyLabel = $currentCompanyIdForLabel ?: '—';

        if ($currentCompanyIdForLabel && \Illuminate\Support\Facades\Schema::hasTable('companies')) {
            $companyForLabel = \Illuminate\Support\Facades\DB::table('companies')
                ->where('id', $currentCompanyIdForLabel)
                ->first();

            if ($companyForLabel) {
                $currentCompanyLabel = $companyForLabel->name
                    ?? $companyForLabel->commercial_name
                    ?? $companyForLabel->business_name
                    ?? $companyForLabel->legal_name
                    ?? ('Empresa #' . $currentCompanyIdForLabel);
            }
        }
    }
@endphp


@php
    $currentCompanyIdForLabel = $currentCompanyId
        ?? $companyId
        ?? $tenantId
        ?? null;

    try {
        $tenantForLabel = \Filament\Facades\Filament::getTenant();

        if (! $currentCompanyIdForLabel && is_object($tenantForLabel) && method_exists($tenantForLabel, 'getKey')) {
            $currentCompanyIdForLabel = $tenantForLabel->getKey();
        } elseif (! $currentCompanyIdForLabel && is_numeric($tenantForLabel)) {
            $currentCompanyIdForLabel = $tenantForLabel;
        }
    } catch (\Throwable $e) {
        //
    }

    if (! $currentCompanyIdForLabel) {
        $routeTenantForLabel = request()->route('tenant');

        if (is_object($routeTenantForLabel) && method_exists($routeTenantForLabel, 'getKey')) {
            $currentCompanyIdForLabel = $routeTenantForLabel->getKey();
        } elseif (is_numeric($routeTenantForLabel)) {
            $currentCompanyIdForLabel = $routeTenantForLabel;
        }
    }

    $currentCompanyLabel = $currentCompanyIdForLabel ?: '—';

    if (
        $currentCompanyIdForLabel
        && \Illuminate\Support\Facades\Schema::hasTable('companies')
    ) {
        $companyForLabel = \Illuminate\Support\Facades\DB::table('companies')
            ->where('id', $currentCompanyIdForLabel)
            ->first();

        if ($companyForLabel) {
            $currentCompanyLabel = $companyForLabel->name
                ?? $companyForLabel->business_name
                ?? $companyForLabel->commercial_name
                ?? $companyForLabel->legal_name
                ?? ('Empresa #' . $currentCompanyIdForLabel);
        }
    }
@endphp

@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;
    use Filament\Facades\Filament;

    $tenant = request()->route('tenant');

    if (is_object($tenant) && method_exists($tenant, 'getKey')) {
        $companyId = (int) $tenant->getKey();
    } elseif (is_numeric($tenant)) {
        $companyId = (int) $tenant;
    } else {
        try {
            $filamentTenant = Filament::getTenant();
            $companyId = is_object($filamentTenant) && method_exists($filamentTenant, 'getKey')
                ? (int) $filamentTenant->getKey()
                : (is_numeric($filamentTenant) ? (int) $filamentTenant : (int) (auth()->user()?->company_id ?? 0));
        } catch (\Throwable $e) {
            $companyId = (int) (auth()->user()?->company_id ?? 0);
        }
    }

    $user = auth()->user();
    $userId = (int) ($user?->id ?? 0);

    $isAdmin = $user && (
        (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin())
        || (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin())
    );

    $posPoints = collect();

    if (Schema::hasTable('pos_points')) {
        $query = DB::table('pos_points')
            ->where('status', 'active');

        if ($companyId > 0 && Schema::hasColumn('pos_points', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if (! $isAdmin && Schema::hasTable('pos_point_user')) {
            $allowedIds = DB::table('pos_point_user')
                ->where('user_id', $userId)
                ->pluck('pos_point_id')
                ->all();

            $query->whereIn('id', $allowedIds ?: [-1]);
        }

        $posPoints = $query->orderBy('name')->get();
    }

    $warehouseNames = collect();

    if ($posPoints->isNotEmpty() && Schema::hasTable('warehouses')) {
        $warehouseNames = DB::table('warehouses')
            ->whereIn('id', $posPoints->pluck('warehouse_id')->filter()->unique()->values())
            ->pluck('name', 'id');
    }

    $locationNames = collect();

    if ($posPoints->isNotEmpty() && Schema::hasTable('stock_locations')) {
        $locationNames = DB::table('stock_locations')
            ->whereIn('id', $posPoints->pluck('stock_location_id')->filter()->unique()->values())
            ->pluck('name', 'id');
    }

    $openUrl = fn ($id) => url('/pos/' . $id . '/open');

    $v5486bOpenSessionsByPosPoint = collect();

    if (
        $posPoints->isNotEmpty()
        && Schema::hasTable('pos_sessions')
    ) {
        $v5486bOpenSessionsByPosPoint = DB::table('pos_sessions')
            ->whereIn('pos_point_id', $posPoints->pluck('id')->filter()->unique()->values())
            ->where('status', 'open')
            ->orderByDesc('id')
            ->get()
            ->groupBy('pos_point_id')
            ->map(fn ($rows) => $rows->first());
    }

    $v5486bSessionUrl = fn ($sessionId) => url('/pos/sessions/' . $sessionId . '/screen');
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap;">
            <div>
                <h2 style="font-size:22px; font-weight:900; margin:0; color:#0f172a;">Selecciona un Punto de Venta</h2>
                <p style="margin-top:6px; color:#64748b;">
                    Abre una sesión de caja para vender desde el PDV.
                </p>
            </div>

            <div style="display:flex; gap:10px; flex-wrap:wrap;">
</div>

            <div style="border:1px solid #dbe3ef; border-radius:14px; background:#fff; padding:12px 16px; color:#334155;">
                Empresa actual: <strong>{{ $currentCompanyLabel }}</strong>
            </div>
        </div>

        @if($posPoints->isEmpty())
            <div style="border:1px dashed #cbd5e1; background:#f8fafc; border-radius:18px; padding:24px; color:#475569;">
                No hay puntos de venta activos relacionados a tu usuario.
                Ejecuta el instalador de datos de prueba incluido en V5.31.1 o crea un PDV manualmente.
            </div>
        @else
            {{-- BEXIA_V5828B2_POS_CARDS_GLOBAL --}}
            <div class="bexia-pos-point-grid" data-bexia-version="V5.82.8b2" style="display:flex; flex-wrap:wrap; gap:18px; align-items:stretch; justify-content:flex-start; width:100%;">
                @foreach($posPoints as $pos)
                    <div class="bexia-pos-point-card" style="flex:0 1 360px; width:100%; max-width:360px; min-width:0; background:#fff; border:1px solid #dbe3ef; border-radius:20px; padding:20px; box-shadow:0 10px 26px rgba(15,23,42,.05); overflow-wrap:anywhere;">
                        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start;">
                            <div>
                                <div style="font-size:13px; font-weight:800; color:#2563eb;">{{ $pos->code ?: ('PDV-' . $pos->id) }}</div>
                                <div style="font-size:20px; font-weight:950; color:#0f172a; margin-top:4px;">
                                    {{ $pos->name }}
                                </div>
                            </div>

                            <span style="border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:800;">
                                Activo
                            </span>
                        </div>

                        <div style="margin-top:16px; display:grid; gap:8px; color:#475569; font-size:14px;">
                            <div>
                                <strong style="color:#0f172a;">Almacén:</strong>
                                {{ $warehouseNames->get($pos->warehouse_id) ?: '—' }}
                            </div>

                            <div>
                                <strong style="color:#0f172a;">Ubicación:</strong>
                                {{ $locationNames->get($pos->stock_location_id) ?: '—' }}
                            </div>

                            <div>
                                <strong style="color:#0f172a;">Lista de precios:</strong>
                                {{ $pos->price_list_name ?: 'Precio público' }}
                            </div>
                        </div>

                        @php
                            $v5486bOpenSession = $v5486bOpenSessionsByPosPoint->get($pos->id);
                        @endphp

                        <div style="margin-top:16px;">
                            @if($v5486bOpenSession)
                                <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; border:1px solid #bbf7d0; background:#f0fdf4; color:#166534; border-radius:14px; padding:10px 12px; font-size:13px; font-weight:900;">
                                    <span>Sesión abierta</span>
                                    <strong>{{ $v5486bOpenSession->number ?? ('#' . $v5486bOpenSession->id) }}</strong>
                                </div>
                            @else
                                <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; border:1px solid #e2e8f0; background:#f8fafc; color:#64748b; border-radius:14px; padding:10px 12px; font-size:13px; font-weight:900;">
                                    <span>Sin sesión abierta</span>
                                    <strong>Disponible</strong>
                                </div>
                            @endif
                        </div>

                        <div style="margin-top:12px; display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            @if($v5486bOpenSession)
                                <a
                                    href="{{ $v5486bSessionUrl($v5486bOpenSession->id) }}"
                                    target="_blank"
                                    style="display:flex; justify-content:center; align-items:center; width:100%; min-height:46px; border-radius:14px; background:#16a34a; color:white; font-weight:900; text-decoration:none; box-shadow:0 12px 28px rgba(22,163,74,.22);"
                                >
                                    Entrar a sesión
                                </a>
                            @else
                                <a
                                    href="{{ $openUrl($pos->id) }}"
                                    target="_blank"
                                    style="display:flex; justify-content:center; align-items:center; width:100%; min-height:46px; border-radius:14px; background:#2563eb; color:white; font-weight:900; text-decoration:none; box-shadow:0 12px 28px rgba(37,99,235,.22);"
                                >
                                    Abrir sesión
                                </a>
                            @endif

                            <a
                                href="{{ url('/admin/' . $companyId . '/pos-points/' . $pos->id . '/edit') }}"
                                style="display:flex; justify-content:center; align-items:center; width:100%; min-height:46px; border-radius:14px; background:#fff; color:#0f172a; border:1px solid #cbd5e1; font-weight:900; text-decoration:none;"
                            >
                                Configurar PDV
                            </a>
                        </div>
                    </div>
                @endforeach
                </div>
        @endif
    </div>

</x-filament-panels::page>
