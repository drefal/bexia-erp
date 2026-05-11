@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;
    use Filament\Facades\Filament;

    $tenant = request()->route('tenant');

    if (is_object($tenant) && method_exists($tenant, 'getRouteKey')) {
        $tenantKey = $tenant->getRouteKey();
    } elseif ($tenant) {
        $tenantKey = $tenant;
    } else {
        try {
            $filamentTenant = Filament::getTenant();

            if (is_object($filamentTenant) && method_exists($filamentTenant, 'getRouteKey')) {
                $tenantKey = $filamentTenant->getRouteKey();
            } elseif ($filamentTenant) {
                $tenantKey = $filamentTenant;
            } else {
                $tenantKey = null;
            }
        } catch (\Throwable $e) {
            $tenantKey = null;
        }
    }

    $companyId = is_numeric($tenantKey) ? (int) $tenantKey : 0;

    $tab = request()->query('tab', 'requests');
    $search = trim((string) request()->query('q', ''));

    $validTabs = ['requests', 'orders', 'approval', 'cancelled', 'all'];

    if (! in_array($tab, $validTabs, true)) {
        $tab = 'requests';
    }

    $baseUrl = url('/admin/' . $tenantKey . '/purchase-documents');

    $tabUrl = function (string $targetTab) use ($baseUrl, $search) {
        $query = ['tab' => $targetTab];

        if ($search !== '') {
            $query['q'] = $search;
        }

        return $baseUrl . '?' . http_build_query($query);
    };

    $requestUrl = fn ($id) => url('/admin/' . $tenantKey . '/purchase-requests/' . $id . '/edit');
    $orderUrl = fn ($id) => url('/admin/' . $tenantKey . '/purchase-orders/' . $id . '/edit');

    $newRequestUrl = url('/admin/' . $tenantKey . '/purchase-requests/create');
    $newOrderUrl = url('/admin/' . $tenantKey . '/purchase-orders/create');
    $fromXmlUrl = url('/admin/' . $tenantKey . '/purchase-orders/from-xml');

    $statusLabel = function (?string $status, string $type = 'order') {
        return match ((string) $status) {
            'draft' => 'Borrador',
            'pending', 'pending_approval', 'submitted', 'submitted_for_approval' => 'Por aprobar',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'confirmed' => $type === 'request' ? 'Aprobada' : 'Orden de compra',
            'partially_received', 'partial_received' => 'Parcialmente recibida',
            'received' => 'Recibida',
            'cancelled', 'canceled' => 'Cancelada',
            default => $status ?: 'Sin estado',
        };
    };

    $badgeClass = function (?string $status) {
        return match ((string) $status) {
            'draft' => 'background:#f8fafc;color:#475569;border-color:#cbd5e1;',
            'pending', 'pending_approval', 'submitted', 'submitted_for_approval' => 'background:#fffbeb;color:#92400e;border-color:#fde68a;',
            'approved', 'confirmed' => 'background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;',
            'partially_received', 'partial_received' => 'background:#fff7ed;color:#9a3412;border-color:#fed7aa;',
            'received' => 'background:#f0fdf4;color:#166534;border-color:#bbf7d0;',
            'cancelled', 'canceled', 'rejected' => 'background:#fef2f2;color:#991b1b;border-color:#fecaca;',
            default => 'background:#f8fafc;color:#475569;border-color:#cbd5e1;',
        };
    };

    $money = fn ($value) => '$ ' . number_format((float) $value, 2) . ' MXN';
    $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i') : '—';

    $requests = collect();
    $orders = collect();

    if (Schema::hasTable('purchase_requests') && in_array($tab, ['requests', 'approval', 'cancelled', 'all'], true)) {
        $query = DB::table('purchase_requests');

        if ($companyId > 0 && Schema::hasColumn('purchase_requests', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'ilike', '%' . $search . '%')
                    ->orWhere('supplier_name', 'ilike', '%' . $search . '%')
                    ->orWhere('notes', 'ilike', '%' . $search . '%');
            });
        }

        if ($tab === 'approval') {
            $query->whereIn('status', ['pending', 'pending_approval', 'submitted', 'submitted_for_approval']);
        } elseif ($tab === 'cancelled') {
            $query->whereIn('status', ['cancelled', 'canceled', 'rejected']);
        } elseif ($tab === 'requests') {
            $query->whereNotIn('status', ['cancelled', 'canceled']);
        }

        $requests = $query
            ->orderByDesc('id')
            ->limit(60)
            ->get();
    }

    if (Schema::hasTable('purchase_orders') && in_array($tab, ['orders', 'approval', 'cancelled', 'all'], true)) {
        $query = DB::table('purchase_orders');

        if ($companyId > 0 && Schema::hasColumn('purchase_orders', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'ilike', '%' . $search . '%')
                    ->orWhere('supplier_name', 'ilike', '%' . $search . '%')
                    ->orWhere('origin', 'ilike', '%' . $search . '%')
                    ->orWhere('notes', 'ilike', '%' . $search . '%');
            });
        }

        if ($tab === 'approval') {
            $query->whereIn('status', ['pending', 'pending_approval', 'submitted', 'submitted_for_approval']);
        } elseif ($tab === 'cancelled') {
            $query->whereIn('status', ['cancelled', 'canceled']);
        } elseif ($tab === 'orders') {
            $query->whereNotIn('status', ['cancelled', 'canceled']);
        }

        $orders = $query
            ->orderByDesc('id')
            ->limit(60)
            ->get();
    }

    $tabs = [
        'requests' => 'Solicitudes',
        'orders' => 'Órdenes de compra',
        'approval' => 'Por aprobar',
        'cancelled' => 'Canceladas',
        'all' => 'Todas',
    ];

    $searchForm = function () use ($baseUrl, $tab, $search) {
        ob_start();
        @endphp
            <form method="GET" action="{{ $baseUrl }}" style="display:flex; justify-content:flex-end;">
                <input type="hidden" name="tab" value="{{ $tab }}">

                <div style="display:flex; gap:8px; align-items:center;">
                    <div style="position:relative;">
                        <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:15px;">
                            ⌕
                        </span>

                        <input
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Buscar"
                            style="width:260px; border:1px solid #dbe3ef; border-radius:12px; padding:10px 12px 10px 34px; background:#ffffff; font-size:14px;"
                        >
                    </div>

                    <button
                        type="submit"
                        style="border-radius:10px; background:#2563eb; border:1px solid #2563eb; color:#ffffff; padding:10px 14px; font-weight:800; box-shadow:0 10px 24px rgba(37,99,235,.18);"
                    >
                        Buscar
                    </button>

                    @if($search !== '')
                        <a
                            href="{{ $baseUrl . '?' . http_build_query(['tab' => $tab]) }}"
                            style="display:inline-flex; align-items:center; border-radius:10px; background:#ffffff; border:1px solid #cbd5e1; color:#0f172a; padding:10px 14px; font-weight:700; text-decoration:none;"
                        >
                            Limpiar
                        </a>
                    @endif
                </div>
            </form>
        @php
        return ob_get_clean();
    };
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div style="display:flex; justify-content:flex-end; gap:10px; align-items:center; flex-wrap:wrap;">
            <a
                href="{{ $newRequestUrl }}"
                style="display:inline-flex; align-items:center; justify-content:center; border-radius:10px; background:#ffffff; border:1px solid #cbd5e1; color:#0f172a; padding:10px 14px; font-size:14px; font-weight:700; text-decoration:none; box-shadow:0 8px 20px rgba(15,23,42,.04);"
            >
                Nueva solicitud
            </a>

            <a
                href="{{ $newOrderUrl }}"
                style="display:inline-flex; align-items:center; justify-content:center; border-radius:10px; background:#2563eb; border:1px solid #2563eb; color:#ffffff; padding:10px 14px; font-size:14px; font-weight:800; text-decoration:none; box-shadow:0 10px 24px rgba(37,99,235,.22);"
            >
                Nueva OC
            </a>

            <a
                href="{{ $fromXmlUrl }}"
                style="display:inline-flex; align-items:center; justify-content:center; border-radius:10px; background:#ffffff; border:1px solid #cbd5e1; color:#0f172a; padding:10px 14px; font-size:14px; font-weight:700; text-decoration:none; box-shadow:0 8px 20px rgba(15,23,42,.04);"
            >
                Crear OC desde XML
            </a>
        </div>

        <div style="display:flex; justify-content:center;">
            <div style="display:inline-flex; gap:4px; border:1px solid #dbe3ef; border-radius:14px; background:#ffffff; padding:8px; box-shadow:0 8px 24px rgba(15,23,42,.04);">
                @foreach($tabs as $key => $label)
                    <a
                        href="{{ $tabUrl($key) }}"
                        style="
                            border-radius:10px;
                            padding:9px 14px;
                            font-size:14px;
                            font-weight:700;
                            text-decoration:none;
                            {{ $tab === $key ? 'background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;' : 'color:#475569;border:1px solid transparent;' }}
                        "
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        @if(in_array($tab, ['requests', 'approval', 'cancelled', 'all'], true))
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm" style="overflow:hidden;">
                <div style="display:flex; justify-content:flex-end; align-items:center; gap:12px; min-height:70px; padding:12px 18px; border-bottom:1px solid #e2e8f0;">
                    {!! $searchForm() !!}
                </div>

                <div style="padding:14px 18px; border-bottom:1px solid #e2e8f0; font-weight:800;">
                    Solicitudes de compra
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Folio</th>
                                <th class="px-4 py-3">Proveedor</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($requests as $request)
                                <tr class="border-t border-gray-100">
                                    <td class="px-4 py-3 font-semibold text-gray-950">{{ $request->number }}</td>
                                    <td class="px-4 py-3">{{ $request->supplier_name ?: '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span style="display:inline-flex; border:1px solid; border-radius:999px; padding:3px 8px; font-size:12px; font-weight:700; {{ $badgeClass($request->status) }}">
                                            {{ $statusLabel($request->status, 'request') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ $money($request->total_with_tax ?? 0) }}</td>
                                    <td class="px-4 py-3">{{ $date($request->requested_at ?? $request->created_at ?? null) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ $requestUrl($request->id) }}" style="font-weight:700; color:#2563eb; text-decoration:none;">
                                            Abrir
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                        No hay solicitudes para este filtro.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="padding:14px 18px; border-top:1px solid #e2e8f0; color:#334155; font-size:14px;">
                    Se muestran {{ $requests->count() }} solicitudes.
                </div>
            </div>
        @endif

        @if(in_array($tab, ['orders', 'approval', 'cancelled', 'all'], true))
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm" style="overflow:hidden;">
                <div style="display:flex; justify-content:flex-end; align-items:center; gap:12px; min-height:70px; padding:12px 18px; border-bottom:1px solid #e2e8f0;">
                    {!! $searchForm() !!}
                </div>

                <div style="padding:14px 18px; border-bottom:1px solid #e2e8f0; font-weight:800;">
                    Órdenes de compra
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Folio</th>
                                <th class="px-4 py-3">Proveedor</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3">Origen</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                                <tr class="border-t border-gray-100">
                                    <td class="px-4 py-3 font-semibold text-gray-950">{{ $order->number }}</td>
                                    <td class="px-4 py-3">{{ $order->supplier_name ?: '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span style="display:inline-flex; border:1px solid; border-radius:999px; padding:3px 8px; font-size:12px; font-weight:700; {{ $badgeClass($order->status) }}">
                                            {{ $statusLabel($order->status, 'order') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">{{ $order->origin ?: '—' }}</td>
                                    <td class="px-4 py-3 text-right">{{ $money($order->total_with_tax ?? 0) }}</td>
                                    <td class="px-4 py-3">{{ $date($order->order_date ?? $order->created_at ?? null) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ $orderUrl($order->id) }}" style="font-weight:700; color:#2563eb; text-decoration:none;">
                                            Abrir
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                        No hay órdenes para este filtro.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="padding:14px 18px; border-top:1px solid #e2e8f0; color:#334155; font-size:14px;">
                    Se muestran {{ $orders->count() }} órdenes.
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
