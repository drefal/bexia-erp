
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

    $v5327bAssignedIds = collect();

    if (! $v5327bCanManageAllPos && $v5327bUser) {
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

        $v5327bAssignedIds = $v5327bAssignedIds->filter()->unique()->values();

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

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Bexia PDV - Abrir sesión</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { margin:0; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background:#f4f7fb; color:#0f172a; }
        .wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:36px; }
        .panel { width:min(980px, 100%); background:#fff; border:1px solid #dbe3ef; border-radius:28px; box-shadow:0 24px 70px rgba(15,23,42,.10); padding:32px; }
        .brand { display:flex; align-items:center; gap:12px; font-size:30px; font-weight:950; color:#2563eb; }
        .badge { background:#2563eb; color:white; border-radius:10px; padding:5px 9px; font-size:18px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:16px; margin-top:28px; }
        .cashier { border:1px solid #dbe3ef; background:#fff; border-radius:22px; padding:22px; text-decoration:none; color:#0f172a; transition:.15s ease; display:block; }
        .cashier:hover { transform:translateY(-2px); border-color:#93c5fd; box-shadow:0 16px 32px rgba(37,99,235,.12); }
        .avatar { width:58px; height:58px; border-radius:18px; display:flex; align-items:center; justify-content:center; background:#eff6ff; color:#2563eb; font-weight:950; font-size:24px; }
        .name { margin-top:14px; font-size:18px; font-weight:900; }
        .meta { margin-top:6px; color:#64748b; font-size:13px; }
        .pin { margin-top:12px; display:inline-flex; border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8; border-radius:999px; padding:4px 9px; font-size:12px; font-weight:800; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="panel">
            <div style="display:flex; justify-content:space-between; gap:20px; align-items:flex-start; flex-wrap:wrap;">
                <div>
                    <div class="brand">
                        Bexia <span class="badge">PDV</span>
                    </div>
                    <h1 style="margin:18px 0 0; font-size:28px;">Abrir sesión de caja</h1>
                    <p style="margin:8px 0 0; color:#64748b;">
                        {{ $pos->name }} · {{ $warehouseName }} · {{ $locationName }} · Selecciona el cajero que operará esta venta.
                    </p>
                </div>

                <a href="javascript:window.close()" style="text-decoration:none; border:1px solid #cbd5e1; color:#0f172a; background:#fff; border-radius:14px; padding:10px 14px; font-weight:800;">
                    Cerrar
                </a>
            </div>

            <div class="grid">
                @forelse($cashiers as $cashier)
                    @php
                        $staffKey = ! empty($cashier->employee_id)
                            ? ('emp_' . $cashier->employee_id)
                            : ('cashier_' . ($cashier->legacy_cashier_id ?? $cashier->id));
                        $hasPin = ! empty($cashier->pos_pin_hash) || ! empty($cashier->pin_hash) || ! empty($cashier->pin_code);
                    @endphp
                    <a class="cashier" href="{{ url('/pos/' . $pos->id . '/cashiers/' . $staffKey) }}">
                        <div class="avatar">{{ mb_substr($cashier->name, 0, 1) }}</div>
                        <div class="name">{{ $cashier->name }}</div>
                        <div class="meta">{{ $cashier->code ?: 'Cajero' }}</div>
                        <div class="pin">{{ $hasPin ? 'Requiere clave' : 'Entrada directa' }}</div>
                        <div class="meta">Rol: {{ ($cashier->role ?? 'cashier') === 'seller' ? 'Vendedor' : (($cashier->role ?? 'cashier') === 'mixed' ? 'Mixto' : 'Cajero') }}</div>
                        <div class="meta">
                            Ticket: {{ ($cashier->can_create_ticket ?? true) ? 'Sí' : 'No' }}
                            · Cobro: {{ ($cashier->can_charge ?? true) ? 'Sí' : 'No' }}
                        </div>
                    </a>
                @empty
                    <div style="grid-column:1/-1; border:1px dashed #cbd5e1; border-radius:18px; padding:24px; color:#64748b;">
                        Este PDV no tiene cajeros activos.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</body>
</html>
