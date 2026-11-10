



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

        if (! $isAdmin) {
            // BEXIA_V582_P3_XLSM_A34B3_UNIFIED_POS_ACCESS
            // La tarjeta del PDV debe usar las mismas fuentes efectivas que
            // la apertura: usuario-PDV, empleado-PDV y cajero legacy.
            $allowedIds = collect();

            if (
                Schema::hasTable('pos_point_user')
                && Schema::hasColumn('pos_point_user', 'user_id')
                && Schema::hasColumn('pos_point_user', 'pos_point_id')
            ) {
                $pointUserQuery = DB::table('pos_point_user')
                    ->where('user_id', $userId);

                if (Schema::hasColumn('pos_point_user', 'is_active')) {
                    $pointUserQuery->where('is_active', true);
                }

                $allowedIds = $allowedIds->merge(
                    $pointUserQuery->pluck('pos_point_id')
                );
            }

            if (
                Schema::hasTable('employees')
                && Schema::hasTable('pos_point_employee')
                && Schema::hasColumn('employees', 'user_id')
                && Schema::hasColumn('pos_point_employee', 'employee_id')
                && Schema::hasColumn('pos_point_employee', 'pos_point_id')
            ) {
                $employeeQuery = DB::table('employees')
                    ->where('user_id', $userId);

                if (Schema::hasColumn('employees', 'active')) {
                    $employeeQuery->where('active', true);
                }

                if (Schema::hasColumn('employees', 'pos_active')) {
                    $employeeQuery->where('pos_active', true);
                }

                if (
                    $companyId > 0
                    && Schema::hasColumn('employees', 'company_id')
                ) {
                    $employeeQuery->where('company_id', $companyId);
                }

                $employeeIds = $employeeQuery
                    ->pluck('id')
                    ->filter()
                    ->values()
                    ->all();

                if ($employeeIds) {
                    $assignmentQuery = DB::table('pos_point_employee')
                        ->whereIn('employee_id', $employeeIds);

                    if (
                        Schema::hasColumn(
                            'pos_point_employee',
                            'is_active'
                        )
                    ) {
                        $assignmentQuery->where('is_active', true);
                    }

                    if (
                        $companyId > 0
                        && Schema::hasColumn(
                            'pos_point_employee',
                            'company_id'
                        )
                    ) {
                        $assignmentQuery->where('company_id', $companyId);
                    }

                    $allowedIds = $allowedIds->merge(
                        $assignmentQuery->pluck('pos_point_id')
                    );
                }
            }

            if (
                Schema::hasTable('pos_cashiers')
                && Schema::hasColumn('pos_cashiers', 'user_id')
                && Schema::hasColumn('pos_cashiers', 'pos_point_id')
            ) {
                $legacyQuery = DB::table('pos_cashiers')
                    ->where('user_id', $userId);

                if (Schema::hasColumn('pos_cashiers', 'is_active')) {
                    $legacyQuery->where('is_active', true);
                }

                if (
                    $companyId > 0
                    && Schema::hasColumn('pos_cashiers', 'company_id')
                ) {
                    $legacyQuery->where('company_id', $companyId);
                }

                $allowedIds = $allowedIds->merge(
                    $legacyQuery->pluck('pos_point_id')
                );
            }

            $allowedIds = $allowedIds
                ->filter(
                    fn ($id): bool =>
                        is_numeric($id) && (int) $id > 0
                )
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
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
                    <div
                        class="bexia-pos-point-card"
                        data-bexia-pos-point-id="{{ $pos->id }}"
                        style="flex:0 1 360px; width:100%; max-width:360px; min-width:0; background:#fff; border:1px solid #dbe3ef; border-radius:20px; padding:20px; box-shadow:0 10px 26px rgba(15,23,42,.05); overflow-wrap:anywhere;"
                    >
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

                        <div
                            data-bexia-pos-session-state="{{ $v5486bOpenSession ? 'open' : 'closed' }}"
                            data-bexia-pos-session-id="{{ $v5486bOpenSession?->id ?? '' }}"
                            style="margin-top:16px;"
                        >
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
                                    data-bexia-pos-primary-action="{{ $pos->id }}"
                                    style="display:flex; justify-content:center; align-items:center; width:100%; min-height:46px; border-radius:14px; background:#16a34a; color:white; font-weight:900; text-decoration:none; box-shadow:0 12px 28px rgba(22,163,74,.22);"
                                >
                                    Entrar a sesión
                                </a>
                            @else
                                <a
                                    href="{{ $openUrl($pos->id) }}"
                                    target="_blank"
                                    data-bexia-pos-primary-action="{{ $pos->id }}"
                                    data-bexia-pos-open-link="{{ $pos->id }}"
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


    <script id="BEXIA_V5829H6_POS_STATUS_ENDPOINT_SYNC">
    /*
     * BEXIA_V5829H6_POS_STATUS_ENDPOINT_SYNC
     *
     * Consulta un endpoint JSON independiente de Filament y actualiza
     * directamente la tarjeta de cada Punto de Venta.
     *
     * No depende de window.opener, localStorage, BroadcastChannel,
     * contenido HTML remoto ni recargas completas de la página.
     */
    (function () {
        if (window.BEXIA_POS_STATUS_ENDPOINT_SYNC_V5829H6_READY) {
            return;
        }

        window.BEXIA_POS_STATUS_ENDPOINT_SYNC_V5829H6_READY = true;

        const currentCompanyId =
            Number(@json((int) $companyId));

        const endpointTemplate =
            @json(url('/pos/points/__POS_ID__/session-state'));

        const normalDelay = 2000;
        const retryDelay = 4000;

        let timer = null;
        let running = false;

        function cards() {
            return Array.from(
                document.querySelectorAll(
                    '[data-bexia-pos-point-id]'
                )
            );
        }

        function endpointFor(posId) {
            const endpoint =
                endpointTemplate.replace(
                    '__POS_ID__',
                    encodeURIComponent(String(posId))
                );

            const url = new URL(endpoint, window.location.origin);

            url.searchParams.set(
                '_bexia_state_at',
                String(Date.now())
            );

            return url.toString();
        }

        function createStatusBox(state, sessionNumber) {
            const box = document.createElement('div');
            const label = document.createElement('span');
            const value = document.createElement('strong');

            Object.assign(box.style, {
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: '10px',
                borderRadius: '14px',
                padding: '10px 12px',
                fontSize: '13px',
                fontWeight: '900',
            });

            if (state === 'open') {
                box.style.border = '1px solid #bbf7d0';
                box.style.background = '#f0fdf4';
                box.style.color = '#166534';

                label.textContent = 'Sesión abierta';
                value.textContent =
                    sessionNumber || 'Abierta';
            } else {
                box.style.border = '1px solid #e2e8f0';
                box.style.background = '#f8fafc';
                box.style.color = '#64748b';

                label.textContent = 'Sin sesión abierta';
                value.textContent = 'Disponible';
            }

            box.appendChild(label);
            box.appendChild(value);

            return box;
        }

        function stylePrimaryAction(action, state) {
            if (!action) {
                return;
            }

            Object.assign(action.style, {
                display: 'flex',
                justifyContent: 'center',
                alignItems: 'center',
                width: '100%',
                minHeight: '46px',
                borderRadius: '14px',
                color: '#ffffff',
                fontWeight: '900',
                textDecoration: 'none',
            });

            if (state === 'open') {
                action.style.background = '#16a34a';
                action.style.boxShadow =
                    '0 12px 28px rgba(22,163,74,.22)';
                action.textContent = 'Entrar a sesión';
            } else {
                action.style.background = '#2563eb';
                action.style.boxShadow =
                    '0 12px 28px rgba(37,99,235,.22)';
                action.textContent = 'Abrir sesión';
            }
        }

        function applyState(card, payload) {
            const stateNode = card.querySelector(
                '[data-bexia-pos-session-state]'
            );

            const action = card.querySelector(
                '[data-bexia-pos-primary-action]'
            );

            if (!stateNode || !action) {
                return;
            }

            const state =
                payload.state === 'open'
                    ? 'open'
                    : 'closed';

            const session = payload.session || null;
            const sessionId =
                session && session.id
                    ? String(session.id)
                    : '';

            const sessionNumber =
                session && session.number
                    ? String(session.number)
                    : '';

            stateNode.dataset.bexiaPosSessionState = state;
            stateNode.dataset.bexiaPosSessionId = sessionId;

            stateNode.replaceChildren(
                createStatusBox(
                    state,
                    sessionNumber
                )
            );

            if (state === 'open') {
                action.href =
                    payload.screen_url || '#';

                action.removeAttribute(
                    'data-bexia-pos-open-link'
                );
            } else {
                action.href =
                    payload.open_url || '#';

                action.setAttribute(
                    'data-bexia-pos-open-link',
                    String(payload.pos_point_id || '')
                );
            }

            stylePrimaryAction(action, state);

            card.dataset.bexiaLastStateVersion =
                String(payload.state_version || '');
        }

        async function refreshCard(card) {
            const posId =
                Number(
                    card.getAttribute(
                        'data-bexia-pos-point-id'
                    ) || 0
                );

            if (
                posId <= 0
                || card.dataset.bexiaStateChecking === '1'
            ) {
                return;
            }

            card.dataset.bexiaStateChecking = '1';

            try {
                const response = await window.fetch(
                    endpointFor(posId),
                    {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Accept': 'application/json',
                            'X-Bexia-Pos-State-Check':
                                'V5.82.9h6',
                        },
                    }
                );

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();

                if (!payload || payload.ok !== true) {
                    return;
                }

                if (
                    Number(payload.company_id || 0)
                    !== currentCompanyId
                ) {
                    return;
                }

                if (
                    Number(payload.pos_point_id || 0)
                    !== posId
                ) {
                    return;
                }

                applyState(card, payload);
            } catch (error) {
                /*
                 * Una falla temporal de red no cambia lo mostrado.
                 * Se vuelve a consultar automáticamente.
                 */
            } finally {
                delete card.dataset.bexiaStateChecking;
            }
        }

        async function refreshAll() {
            if (running) {
                return;
            }

            if (document.visibilityState === 'hidden') {
                schedule(retryDelay);
                return;
            }

            running = true;

            try {
                await Promise.allSettled(
                    cards().map(refreshCard)
                );
            } finally {
                running = false;
                schedule(normalDelay);
            }
        }

        function schedule(delay) {
            window.clearTimeout(timer);

            timer = window.setTimeout(
                refreshAll,
                typeof delay === 'number'
                    ? delay
                    : normalDelay
            );
        }

        function immediateRefresh() {
            schedule(25);
        }

        function boot() {
            try {
                window.localStorage.removeItem(
                    'bexia.pos.session.state.v5829h5'
                );
            } catch (error) {
                //
            }

            immediateRefresh();
        }

        if (document.readyState === 'loading') {
            document.addEventListener(
                'DOMContentLoaded',
                boot,
                { once: true }
            );
        } else {
            boot();
        }

        document.addEventListener(
            'visibilitychange',
            function () {
                if (
                    document.visibilityState === 'visible'
                ) {
                    immediateRefresh();
                }
            }
        );

        window.addEventListener(
            'focus',
            immediateRefresh
        );

        window.addEventListener(
            'pageshow',
            immediateRefresh
        );

        document.addEventListener(
            'livewire:navigated',
            immediateRefresh
        );

        document.addEventListener(
            'click',
            function (event) {
                const action =
                    event.target?.closest?.(
                        '[data-bexia-pos-primary-action]'
                    );

                if (!action) {
                    return;
                }

                /*
                 * Después de abrir otra ventana comenzamos a consultar
                 * inmediatamente el estado real del servidor.
                 */
                schedule(300);
            },
            true
        );
    })();
    </script>

</x-filament-panels::page>
