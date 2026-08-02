<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Bexia PDV</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing:border-box; }
        body { margin:0; font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background:#f8fbff; color:#0f172a; }
        .top { height:76px; display:flex; align-items:center; gap:18px; padding:12px 26px; border-bottom:1px solid #e2e8f0; background:#fff; }
        .brand { font-size:28px; font-weight:950; color:#2563eb; white-space:nowrap; }
        .badge { font-size:16px; background:#2563eb; color:white; border-radius:10px; padding:4px 8px; margin-left:5px; }
        .selectbox { border-left:1px solid #e2e8f0; padding-left:22px; min-width:160px; }
        .label { font-size:12px; color:#64748b; font-weight:700; }
        .value { margin-top:4px; font-weight:850; }
        .shell { height:calc(100vh - 76px); display:grid; grid-template-columns: 1fr 410px; }
        .main { padding:22px 26px; overflow:auto; }
        .cart { border-left:1px solid #e2e8f0; background:#fff; padding:22px; overflow:auto; }
        .categories { display:flex; gap:14px; overflow:auto; padding-bottom:10px; }
        .cat {
            min-width:105px;
            height:74px;
            border-radius:14px;
            color:#fff;
            display:flex;
            flex-direction:column;
            justify-content:center;
            align-items:center;
            font-size:11px;
            line-height:1.12;
            text-align:center;
            font-weight:900;
            padding:7px;
            box-shadow:0 8px 18px rgba(15,23,42,.10);
        }
        .cat .icon {
            font-size:20px;
            margin-bottom:4px;
            line-height:1;
        }
        .search { margin-top:16px; display:flex; gap:12px; }
        .search input { flex:1; border:1px solid #dbe3ef; border-radius:14px; padding:15px 18px; font-size:15px; }
        .btn { border:1px solid #dbe3ef; background:#fff; border-radius:14px; padding:13px 17px; font-weight:850; }
        .products {
            margin-top:12px;
            display:grid;
            grid-template-columns:repeat(auto-fill, minmax(118px, 1fr));
            gap:8px;
        }
        .product {
            background:#fff;
            border:1px solid #e2e8f0;
            border-radius:13px;
            padding:8px;
            min-height:122px;
            cursor:pointer;
            position:relative;
        }
        .product.disabled { opacity:.55; cursor:not-allowed; }
        .pimg {
            height:38px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:27px;
        }
        .pname {
            margin-top:3px;
            min-height:26px;
            font-size:11px;
            line-height:1.10;
            font-weight:850;
        }
        .code {
            color:#64748b;
            font-size:9px;
            margin-top:2px;
            min-height:10px;
            line-height:1.05;
            word-break:break-word;
        }
        .price {
            margin-top:4px;
            font-size:13px;
            line-height:1.05;
            font-weight:950;
            color:#2563eb;
        }
        .stock {
            font-size:9px;
            color:#475569;
            margin-top:2px;
            line-height:1.05;
        }
        .no-stock { color:#dc2626; font-weight:850; }
.cart-title { display:flex; justify-content:space-between; align-items:center; }
        .cart-title h2 { margin:0; font-size:22px; }
        .danger { color:#dc2626; font-weight:850; text-decoration:none; }
        .client { margin-top:18px; border:1px solid #dbe3ef; border-radius:16px; padding:14px; }
        .item { margin-top:12px; display:grid; grid-template-columns:52px 1fr auto; gap:12px; align-items:center; border:1px solid #eef2f7; border-radius:16px; padding:12px; }
        .thumb { height:52px; width:52px; border-radius:12px; background:#eff6ff; display:flex; align-items:center; justify-content:center; font-size:26px; }
        .totals { margin-top:18px; border:1px solid #dbe3ef; border-radius:18px; padding:16px; }
        .row { display:flex; justify-content:space-between; margin:10px 0; color:#334155; }
        .total { display:flex; justify-content:space-between; align-items:center; border-top:1px solid #e2e8f0; margin-top:12px; padding-top:14px; font-size:18px; font-weight:950; }
        .total strong { color:#2563eb; font-size:28px; }
        .payments { position:fixed; left:0; right:410px; bottom:0; background:#fff; border-top:1px solid #e2e8f0; display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:14px; padding:14px 26px; }
        .pay { border:1px solid #dbe3ef; border-radius:18px; padding:18px; font-weight:900; text-align:center; }
        .charge { position:fixed; right:22px; bottom:18px; width:366px; border-radius:18px; background:#2563eb; color:#fff; padding:20px 24px; font-size:24px; font-weight:950; display:flex; justify-content:space-between; align-items:center; box-shadow:0 18px 38px rgba(37,99,235,.28); }
        .notice { margin-top:12px; border:1px solid #bfdbfe; background:#eff6ff; color:#1e40af; border-radius:14px; padding:10px 12px; font-size:13px; }
        .warning { margin-top:12px; border:1px solid #fed7aa; background:#fff7ed; color:#9a3412; border-radius:14px; padding:10px 12px; font-size:13px; }
        .success { margin-top:12px; border:1px solid #bbf7d0; background:#f0fdf4; color:#166534; border-radius:14px; padding:10px 12px; font-size:13px; }
        .btn-disabled { opacity:.45; pointer-events:none; cursor:not-allowed; }
        .role-pill {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            border:1px solid #bfdbfe;
            background:#eff6ff;
            color:#1d4ed8;
            border-radius:999px;
            padding:2px 6px;
            font-size:9px;
            line-height:1;
            font-weight:800;
            white-space:nowrap;
        }

        .code-empty {
            min-height:0;
            margin-top:0;
            display:none;
        }

        .empty { margin-top:18px; border:1px dashed #cbd5e1; background:#fff; border-radius:18px; padding:24px; color:#64748b; }
    </style>




<style id="v5336-hide-bottom-payments">
    .payments {
        display: none !important;
    }
</style>




<style id="v5481e5-stock-style-neutral">
    .product .stock[data-v5481e5-refreshed="1"],
    .product .stock.no-stock[data-v5481e5-refreshed="1"] {
        color: #475569 !important;
        font-size: 9px !important;
        margin-top: 2px !important;
        line-height: 1.05 !important;
        font-weight: 400 !important;
    }
</style>


<style id="v5527a-pos-product-images-style">
    /*
     * BEXIA_V5527A_POS_PRODUCT_IMAGES
     * Mostrar imagen real del producto en PDV cuando exista image_path/image_url.
     */
    .product .pimg {
        height: 58px;
        border-radius: 10px;
        background: #f8fafc;
        overflow: hidden;
        margin-bottom: 4px;
    }

    .product .pimg img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
    }

    .product .pimg .v5527a-product-placeholder {
        width: 100%;
        height: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 27px;
    }

    .product .pimg img + .v5527a-product-placeholder {
        display: none;
    }

    .product .pimg.v5527a-img-error .v5527a-product-placeholder {
        display: inline-flex;
    }

    .thumb.v5527a-cart-thumb {
        overflow: hidden;
        background: #f8fafc;
    }

    .thumb.v5527a-cart-thumb img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
    }
</style>


{{-- BEXIA_V5828B5G_FAVORITE_CARD_STYLE --}}
<style id="bexia-v5828b5g-favorite-card-style">
    /*
     * Los favoritos aparecen primero visualmente incluso si algún
     * script posterior reorganiza las tarjetas.
     */
    .products > .product[data-product-is-favorite="1"] {
        order: -1000;
        border: 2px solid #f59e0b;
        background:
            linear-gradient(
                145deg,
                #fff7ed 0%,
                #fffbeb 58%,
                #ffffff 100%
            );
        box-shadow:
            0 10px 24px rgba(245, 158, 11, .20);
    }

    .products > .product[data-product-is-favorite="1"]::before {
        content: "⭐ FAVORITO";
        position: absolute;
        top: 6px;
        right: 6px;
        z-index: 3;
        padding: 3px 6px;
        border: 1px solid #f59e0b;
        border-radius: 999px;
        background: #fef3c7;
        color: #92400e;
        font-size: 8px;
        line-height: 1;
        font-weight: 950;
        letter-spacing: .25px;
        box-shadow: 0 3px 8px rgba(146, 64, 14, .16);
    }

    .products > .product[data-product-is-favorite="1"] .pimg {
        margin-top: 17px;
        background: #fffdf7;
    }

    .products > .product[data-product-is-favorite="1"] .pname {
        color: #78350f;
    }

    .products > .product[data-product-is-favorite="1"] .price {
        color: #b45309;
    }

    .products > .product[data-product-is-favorite="1"]:hover {
        border-color: #d97706;
        box-shadow:
            0 14px 30px rgba(217, 119, 6, .25);
        transform: translateY(-1px);
    }
</style>


<style id="bexia-v582-p3-a34c2-switch-style">
    /* BEXIA_V582_P3_XLSM_A34C2_SWITCH_UI */
    .v582p3-a34c2-employee-value {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .v582p3-a34c2-switch-button {
        width: 28px;
        height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #bfdbfe;
        border-radius: 999px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 16px;
        line-height: 1;
        font-weight: 950;
        cursor: pointer;
    }

    .v582p3-a34c2-switch-button:hover {
        background: #dbeafe;
    }

    .v582p3-a34c2-switch-modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 2147483200;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(15, 23, 42, .58);
        backdrop-filter: blur(2px);
    }

    .v582p3-a34c2-switch-modal.is-open {
        display: flex;
    }

    .v582p3-a34c2-switch-card {
        width: min(460px, 100%);
        border-radius: 22px;
        background: #fff;
        box-shadow: 0 28px 70px rgba(15, 23, 42, .30);
        overflow: hidden;
    }

    .v582p3-a34c2-switch-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 18px 20px;
        border-bottom: 1px solid #e2e8f0;
    }

    .v582p3-a34c2-switch-body {
        display: grid;
        gap: 14px;
        padding: 20px;
    }

    .v582p3-a34c2-switch-field {
        display: grid;
        gap: 6px;
    }

    .v582p3-a34c2-switch-field label {
        font-size: 12px;
        font-weight: 900;
        color: #475569;
    }

    .v582p3-a34c2-switch-field select,
    .v582p3-a34c2-switch-field input {
        width: 100%;
        min-height: 46px;
        border: 1px solid #cbd5e1;
        border-radius: 13px;
        padding: 10px 12px;
        background: #fff;
        color: #0f172a;
        font-size: 15px;
    }

    .v582p3-a34c2-switch-warning {
        border: 1px solid #fed7aa;
        border-radius: 13px;
        padding: 10px 12px;
        background: #fff7ed;
        color: #9a3412;
        font-size: 12px;
        line-height: 1.4;
    }

    .v582p3-a34c2-switch-error {
        display: none;
        border: 1px solid #fecaca;
        border-radius: 13px;
        padding: 10px 12px;
        background: #fef2f2;
        color: #b91c1c;
        font-size: 12px;
        font-weight: 800;
    }

    .v582p3-a34c2-switch-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 0 20px 20px;
    }

    .v582p3-a34c2-switch-actions button {
        min-height: 42px;
        border-radius: 13px;
        padding: 10px 16px;
        font-weight: 900;
        cursor: pointer;
    }

    .v582p3-a34c2-switch-cancel {
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #334155;
    }

    .v582p3-a34c2-switch-confirm {
        border: 1px solid #2563eb;
        background: #2563eb;
        color: #fff;
    }

    .v582p3-a34c2-switch-confirm:disabled {
        opacity: .55;
        cursor: wait;
    }
</style>

</head>
<body>
@php
    $staffPermissions = $staffPermissions ?? [
        'role' => 'mixed',
        'can_create_ticket' => true,
        'can_charge' => true,
        'can_discount' => true,
        'can_cancel' => true,
        'can_open_cash_drawer' => false,
        'max_discount_percent' => 0,
    ];

    $canCreateTicket = (bool) ($staffPermissions['can_create_ticket'] ?? true);
    $canCharge = (bool) ($staffPermissions['can_charge'] ?? true);
    $canDiscount = (bool) ($staffPermissions['can_discount'] ?? true);

    // V5.51.7B - Descuento requiere permiso real además del permiso operativo del empleado.
    try {
        $v5517bDiscountUser = auth()->user();

        $v5517bCanApplyDiscountByPermission = false;

        if ($v5517bDiscountUser) {
            $v5517bCanApplyDiscountByPermission =
                (method_exists($v5517bDiscountUser, 'isSystemAdmin') && $v5517bDiscountUser->isSystemAdmin())
                || (method_exists($v5517bDiscountUser, 'isGroupAdmin') && $v5517bDiscountUser->isGroupAdmin())
                || (method_exists($v5517bDiscountUser, 'can') && $v5517bDiscountUser->can('pos.discount.apply'));
        }

        $canDiscount = $canDiscount && $v5517bCanApplyDiscountByPermission;
    } catch (\Throwable $e) {
        $canDiscount = false;
    }
    $canCancel = (bool) ($staffPermissions['can_cancel'] ?? true);
    $canOpenCashDrawer = (bool) ($staffPermissions['can_open_cash_drawer'] ?? false);
    $effectiveRoleLabel = $effectiveRoleLabel ?? 'Mixto';
@endphp
    <div class="top">
        <div class="brand">
            @if(!empty($ticketLogoUrl))
                @endif
            Bexia <span class="badge">PDV</span>
        </div>
        <div style="font-size:28px;">☰</div>
        <div>
            <div style="font-size:12px; color:#64748b; margin-top:3px;">Sesión {{ $session->number ?? ('#' . $session->id) }}</div>
        </div>

        <div style="flex:1;"></div>

        <div class="selectbox">
            <div class="label">Sucursal / PDV</div>
            <div class="value">{{ $pos->name }}</div>
        </div>

        <div class="selectbox">
            <div class="label">Almacén</div>
            <div class="value">{{ $warehouseName }}</div>
        </div>

        <div class="selectbox">
            <div class="label">Empleado</div>
            <div class="value v582p3-a34c2-employee-value">
                <span>{{ $cashier->name ?? 'Cajero' }}</span>
                @if(($canSwitchCashier ?? false) && count($switchableStaff ?? []) > 1)
                    <button
                        id="v582p3-a34c2-switch-button"
                        class="v582p3-a34c2-switch-button"
                        type="button"
                        title="Cambiar cajero sin cerrar la sesión"
                        aria-label="Cambiar cajero sin cerrar la sesión"
                    >⇄</button>
                @endif
            </div>
            <div style="margin-top:3px;"><span class="role-pill">{{ $effectiveRoleLabel }}</span></div>
        </div>
    </div>

    @if(($canSwitchCashier ?? false) && count($switchableStaff ?? []) > 1)
        <div
            id="v582p3-a34c2-switch-modal"
            class="v582p3-a34c2-switch-modal"
            aria-hidden="true"
        >
            <div
                class="v582p3-a34c2-switch-card"
                role="dialog"
                aria-modal="true"
                aria-labelledby="v582p3-a34c2-switch-title"
            >
                <div class="v582p3-a34c2-switch-header">
                    <div>
                        <div
                            id="v582p3-a34c2-switch-title"
                            style="font-size:18px;font-weight:950;"
                        >Cambiar cajero</div>
                        <div style="margin-top:3px;font-size:12px;color:#64748b;">
                            Sesión {{ $session->number ?? ('#' . $session->id) }}
                        </div>
                    </div>
                    <button
                        id="v582p3-a34c2-switch-close"
                        type="button"
                        aria-label="Cerrar"
                        style="border:0;background:transparent;font-size:24px;cursor:pointer;"
                    >×</button>
                </div>

                <form id="v582p3-a34c2-switch-form">
                    <div class="v582p3-a34c2-switch-body">
                        <div class="v582p3-a34c2-switch-warning">
                            Guarda el ticket pendiente o vacía el carrito antes de cambiar.
                            La sesión, fondo inicial, movimientos y tickets anteriores se conservan.
                        </div>

                        <div class="v582p3-a34c2-switch-field">
                            <label for="v582p3-a34c2-switch-staff">Nuevo cajero</label>
                            <select
                                id="v582p3-a34c2-switch-staff"
                                name="staff_key"
                                required
                            >
                                <option value="">Selecciona un empleado</option>
                                @foreach(($switchableStaff ?? collect()) as $staffRow)
                                    @php
                                        $v582p3A34c2StaffKey = ! empty($staffRow->employee_id)
                                            ? ('emp_' . (int) $staffRow->employee_id)
                                            : ('cashier_' . (int) ($staffRow->legacy_cashier_id ?? $staffRow->id ?? 0));
                                        $v582p3A34c2IsCurrent = $v582p3A34c2StaffKey === ($currentStaffKey ?? '');
                                    @endphp
                                    <option
                                        value="{{ $v582p3A34c2StaffKey }}"
                                        @disabled($v582p3A34c2IsCurrent)
                                    >
                                        {{ $staffRow->name ?? 'Empleado' }}
                                        @if($v582p3A34c2IsCurrent) (actual) @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="v582p3-a34c2-switch-field">
                            <label for="v582p3-a34c2-switch-pin">
                                NIP del nuevo cajero
                            </label>
                            <input
                                id="v582p3-a34c2-switch-pin"
                                name="pin"
                                type="password"
                                inputmode="numeric"
                                autocomplete="off"
                                maxlength="64"
                                required
                            >
                        </div>

                        <div
                            id="v582p3-a34c2-switch-error"
                            class="v582p3-a34c2-switch-error"
                        ></div>
                    </div>

                    <div class="v582p3-a34c2-switch-actions">
                        <button
                            id="v582p3-a34c2-switch-cancel"
                            class="v582p3-a34c2-switch-cancel"
                            type="button"
                        >Cancelar</button>
                        <button
                            id="v582p3-a34c2-switch-confirm"
                            class="v582p3-a34c2-switch-confirm"
                            type="submit"
                        >Cambiar cajero</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="shell">
        <main class="main">
            <div class="categories">
                <a class="cat" data-v5828b5e-category="favorites" href="{{ request()->fullUrlWithQuery(['pos_category' => 'favorites']) }}" style="background:#b91c1c; text-decoration:none;">
                    <div class="icon">⭐</div>
                    Favoritos
                </a>

                <a class="cat" data-v5828b5e-category="top_sellers" href="{{ request()->fullUrlWithQuery(['pos_category' => 'top_sellers']) }}" style="background:#0f172a; text-decoration:none;">
                    <div class="icon">🏆</div>
                    Más Vendido
                </a>

                @foreach($categories as $cat)
                    @php
                        $v5451CatId = $cat['id'] ?? null;
                        $v5451CatName = (string) ($cat['name'] ?? '');
                        // V5.51.5D - "Todas/Todos" debe mandar pos_category=all.
                        // Si no manda parámetro, el default vuelve a Favoritos.
                        $v5451CatNameNormalized = mb_strtolower(trim($v5451CatName));

                        $v5451CatHref = in_array($v5451CatNameNormalized, ['todas', 'todos', 'todo', 'all'], true)
                            ? request()->fullUrlWithQuery(['pos_category' => 'all'])
                            : request()->fullUrlWithQuery(['pos_category' => $v5451CatId]);
                    @endphp

                    <a
                        class="cat"
                        data-v5828b5e-category="{{ in_array($v5451CatNameNormalized, ['todas', 'todos', 'todo', 'all'], true) ? 'all' : (string) $v5451CatId }}"
                        href="{{ $v5451CatHref }}"
                        style="background:{{ $cat['color'] }}; text-decoration:none;"
                    >
                        <div class="icon">{{ $cat['icon'] }}</div>
                        {{ $cat['name'] }}
                    </a>
                @endforeach
            </div>

            <div class="search">
                <input id="v5490b-product-search" type="search" placeholder="Buscar por nombre, código, SKU o código de barras" autocomplete="off" inputmode="search">
                <button class="btn" id="v5490b-code-button" type="button" style="display:none;">Código</button>
                <button class="btn" id="v5490b-clear-search" type="button">Limpiar</button>
            </div>
@if($canCreateTicket && ! $canCharge)
@elseif(! $canCreateTicket && $canCharge)
@elseif($canCreateTicket && $canCharge)
@else
                <div class="warning">
                    Este empleado no tiene permisos activos para crear ticket ni cobrar en esta caja.
                </div>
            @endif

@php
    // V5.51.2A - Abrir Favoritos por defecto cuando no venga categoría en URL.
    // V5.51.2C - Categoría inicial configurable por Punto de Venta.
    $v5512cDefaultPosCategory = 'favorites';

    try {
        if (isset($pos) && is_object($pos)) {
            $v5512cMode = (string) ($pos->default_pos_category_mode ?? 'favorites');

            if ($v5512cMode === 'all') {
                $v5512cDefaultPosCategory = 'all';
            } elseif ($v5512cMode === 'top_sellers') {
                $v5512cDefaultPosCategory = 'top_sellers';
            } elseif ($v5512cMode === 'category' && ! empty($pos->initial_category_id)) {
                $v5512cDefaultPosCategory = (string) $pos->initial_category_id;
            } else {
                $v5512cDefaultPosCategory = 'favorites';
            }
        }
    } catch (\Throwable $e) {
        $v5512cDefaultPosCategory = 'favorites';
    }

    $v5336SelectedCategory = request()->query('pos_category', $v5512cDefaultPosCategory);

    // V5.51.5C - Normalizar categoría seleccionada.
    // all/todos/vacío significa "sin filtro de categoría".
    $v5515cSelectedCategoryRaw = $v5336SelectedCategory;
    $v5515cIsAllCategories = in_array((string) $v5515cSelectedCategoryRaw, ['', 'all', 'todos', '0'], true);

    $v5336TopSellerNames = collect();

    if (
        $v5336SelectedCategory === 'top_sellers'
        && \Illuminate\Support\Facades\Schema::hasTable('sales_order_lines')
    ) {
        $v5336ProductColumn = null;

        foreach (['product_label', 'name', 'description'] as $candidateColumn) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('sales_order_lines', $candidateColumn)) {
                $v5336ProductColumn = $candidateColumn;
                break;
            }
        }

        $v5336QuantityColumn = null;

        foreach (['quantity', 'qty'] as $candidateColumn) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('sales_order_lines', $candidateColumn)) {
                $v5336QuantityColumn = $candidateColumn;
                break;
            }
        }

        if ($v5336ProductColumn && $v5336QuantityColumn) {
            $v5336TopQuery = \Illuminate\Support\Facades\DB::table('sales_order_lines')
                ->selectRaw($v5336ProductColumn . ' as product_name, SUM(COALESCE(' . $v5336QuantityColumn . ', 0)) as sold_qty')
                ->whereNotNull($v5336ProductColumn)
                ->groupBy($v5336ProductColumn)
                ->orderByDesc('sold_qty')
                ->limit(20);

            if (
                isset($pos)
                && ! empty($pos->company_id)
                && \Illuminate\Support\Facades\Schema::hasColumn('sales_order_lines', 'company_id')
            ) {
                $v5336TopQuery->where('company_id', (int) $pos->company_id);
            }

            $v5336TopSellerNames = $v5336TopQuery
                ->pluck('product_name')
                ->map(fn ($name) => mb_strtolower(trim((string) $name)))
                ->filter()
                ->values();
        }
    }

    $v5336Products = collect($products ?? []);

    // V5.51.5E - Mantener una colección global para búsqueda.
    // La categoría filtra la vista inicial, pero la búsqueda debe encontrar en todos.
    $v5515eAllSearchProducts = $v5336Products;

    if (! ($allowOutOfStockSales ?? false)) {
        $v5336Products = $v5336Products->filter(function ($product) {
            $stock = data_get($product, 'stock', data_get($product, 'quantity', 0));
            $canSell = data_get($product, 'can_sell', true);
            $productType = (string) data_get($product, 'product_type', '');
            $isService = (bool) data_get($product, 'is_service', false) || $productType === 'service';

            return $isService || (((float) $stock) > 0 && (bool) $canSell);
        })->values();

        // V5.51.5E - La búsqueda global respeta la misma regla de existencia.
        $v5515eAllSearchProducts = $v5515eAllSearchProducts->filter(function ($product) {
            $stock = data_get($product, 'stock', data_get($product, 'quantity', 0));
            $canSell = data_get($product, 'can_sell', true);
            $productType = (string) data_get($product, 'product_type', '');
            $isService = (bool) data_get($product, 'is_service', false) || $productType === 'service';

            return $isService || (((float) $stock) > 0 && (bool) $canSell);
        })->values();
    }

    if (! ($v5515cIsAllCategories ?? false) && $v5336SelectedCategory === 'top_sellers') {
        if ($v5336TopSellerNames->isEmpty()) {
            $v5336Products = collect();
        } else {
            $v5336Products = $v5336Products
                ->filter(function ($product) use ($v5336TopSellerNames) {
                    $name = mb_strtolower(trim((string) data_get($product, 'name', '')));

                    return $name !== '' && $v5336TopSellerNames->contains($name);
                })
                ->take(20)
                ->values();
        }
    } elseif (! ($v5515cIsAllCategories ?? false) && $v5336SelectedCategory === 'favorites') {
        $v5336Products = $v5336Products
            ->filter(fn ($product) => (bool) data_get($product, 'is_pos_favorite', false))
            ->values();
    } elseif (! ($v5515cIsAllCategories ?? false) && is_numeric($v5336SelectedCategory)) {
        $v5336CategoryId = (int) $v5336SelectedCategory;

        // V5.51.5D - Filtro de categoría más robusto.
        $v5336Products = $v5336Products
            ->filter(function ($product) use ($v5336CategoryId) {
                $categoryCandidates = [
                    data_get($product, 'category_id'),
                    data_get($product, 'product_category_id'),
                    data_get($product, 'pos_category_id'),
                    data_get($product, 'categ_id'),
                ];

                foreach ($categoryCandidates as $candidate) {
                    if ((int) $candidate === $v5336CategoryId) {
                        return true;
                    }
                }

                $categoryIds = data_get($product, 'category_ids', data_get($product, 'pos_category_ids', []));

                if (is_string($categoryIds)) {
                    $categoryIds = array_filter(array_map('trim', explode(',', $categoryIds)));
                }

                if (is_array($categoryIds)) {
                    foreach ($categoryIds as $candidate) {
                        if ((int) $candidate === $v5336CategoryId) {
                            return true;
                        }
                    }
                }

                return false;
            })
            ->values();
    }


    /*
     * BEXIA_V5828B5G_FAVORITES_FIRST
     *
     * En la vista Todas, los favoritos se envian primero desde
     * Blade. El resto conserva su orden original.
     */
    if ($v5515cIsAllCategories ?? false) {
        $v5336Products = $v5336Products
            ->sortByDesc(function ($product): int {
                return (bool) data_get(
                    $product,
                    'is_pos_favorite',
                    false
                ) ? 1 : 0;
            })
            ->values();
    }

    /*
     * BEXIA_V5527A_POS_PRODUCT_IMAGE_URL_HELPER
     */
    $v5527aProductImageUrl = function ($product): ?string {
        $raw = trim((string) (
            data_get($product, 'image_url')
            ?: data_get($product, 'image_path')
            ?: data_get($product, 'photo_path')
            ?: data_get($product, 'thumbnail_path')
            ?: ''
        ));

        if ($raw === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $raw)) {
            return $raw;
        }

        $path = ltrim($raw, '/');

        if (str_starts_with($path, 'storage/')) {
            return '/'.$path;
        }

        foreach ([
            'public/',
            'app/public/',
            'storage/app/public/',
        ] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    };


    // V5.51.5E - Productos visibles por categoría.
    $v5515eVisibleProductIds = $v5336Products
        ->map(fn ($product) => (string) data_get($product, 'id', ''))
        ->filter()
        ->values()
        ->all();

    // Productos extra que no se muestran por la categoría, pero sí deben existir para búsqueda.
    $v5515eHiddenSearchProducts = $v5515eAllSearchProducts
        ->reject(function ($product) use ($v5515eVisibleProductIds) {
            return in_array((string) data_get($product, 'id', ''), $v5515eVisibleProductIds, true);
        })
        ->values();

@endphp

            @if(! $canCreateTicket)
                <div class="empty">
                    Esta caja está en modo cobro. Aquí se mostrarán los tickets pendientes para cobrar.
                </div>
            @elseif($v5336Products->isEmpty())
                <div class="empty">
                    No hay productos disponibles para este PDV. Revisa que los productos estén activos, puedan venderse y estén disponibles para PDV.
                </div>
            @else
                <div id="v5828b5i-main-products-grid" class="products">
@foreach($v5336Products as $product)

                        @php
                            $v5451ProductType = (string) ($product['product_type'] ?? 'stockable');
                            $v5451IsService = (bool) ($product['is_service'] ?? false) || $v5451ProductType === 'service';
                            $v5451IsFavorite = (bool) ($product['is_pos_favorite'] ?? false);

                            $productReference = $product['internal_reference']
                                ?? $product['default_code']
                                ?? $product['reference']
                                ?? $product['sku']
                                ?? $product['barcode']
                                ?? $product['code']
                                ?? null;

                            if (! $productReference && ! is_numeric($product['code'] ?? null)) {
                                $productReference = $product['code'] ?? null;
                            }

                            $v5490bProductBarcode = $product['barcode'] ?? '';
                            $v5490bProductSku = $product['sku'] ?? '';
                            $v5490bProductCode = $product['code'] ?? ($product['internal_reference'] ?? '');
                            $v5490bProductSearch = trim(implode(' ', array_filter([
                                $product['name'] ?? '',
                                $productReference ?? '',
                                $v5490bProductBarcode,
                                $v5490bProductSku,
                                $v5490bProductCode,
                                $product['description'] ?? '',
                            ])));

                            $v5527aImageUrl = is_callable($v5527aProductImageUrl ?? null)
                                ? $v5527aProductImageUrl($product)
                                : null;

                            $v5345TaxRate = (float) (
                                $product['tax_rate']
                                ?? $product['iva_rate']
                                ?? $product['vat_rate']
                                ?? 0.16
                            );

                            if ($v5345TaxRate > 1) {
                                $v5345TaxRate = $v5345TaxRate / 100;
                            }

                            if ($v5345TaxRate <= 0) {
                                $v5345TaxRate = 0.16;
                            }

                            /*
                             * En Bexia, product['price'] viene del precio base/sin IVA.
                             * Por eso aquí se calcula el precio final que debe ver/cobrar el PDV.
                             */
                            $v5345BasePrice = (float) (
                                $product['price_without_tax']
                                ?? $product['sale_price']
                                ?? $product['price']
                                ?? 0
                            );

                            $v5345ExplicitPriceWithTax = (float) (
                                $product['price_with_tax']
                                ?? $product['sale_price_with_tax']
                                ?? 0
                            );

                            $v5345PriceWithTax = $v5345ExplicitPriceWithTax > 0
                                ? round($v5345ExplicitPriceWithTax, 2)
                                : round($v5345BasePrice * (1 + $v5345TaxRate), 2);
                        @endphp


                    <!-- BEXIA_V582P3_A35E2_PRODUCT_NAME_DATASET_ESCAPE -->
                        <div
                            class="product {{ $product['can_sell'] ? '' : 'disabled' }}"
                            data-product-id="{{ $product['id'] ?? '' }}"
                            data-product-category-id="{{ (int) ($product['category_id'] ?? 0) }}"
                            data-product-is-favorite="{{ $v5451IsFavorite ? '1' : '0' }}"
                            data-product-name="{{ $product['name'] ?? '' }}"
                            data-product-reference="{{ e($productReference ?? '') }}"
                            data-product-barcode="{{ e($v5490bProductBarcode) }}"
                            data-product-sku="{{ e($v5490bProductSku) }}"
                            data-product-code="{{ e($v5490bProductCode) }}"
                            data-product-search="{{ e($v5490bProductSearch) }}"
                            data-product-price="{{ $v5345PriceWithTax }}"
                            data-product-stock="{{ (float) ($product['stock'] ?? 0) }}"
                            data-product-can-sell="{{ !empty($product['can_sell']) ? '1' : '0' }}"
                            data-product-tax-rate="{{ $v5345TaxRate }}"
                            data-product-type="{{ e($v5451ProductType) }}"
                            data-product-is-service="{{ $v5451IsService ? '1' : '0' }}"
                            data-product-image-url="{{ e($v5527aImageUrl ?? '') }}"
                        >
                            <div class="pimg">
                                @if(!empty($v5527aImageUrl))
                                    <img src="{{ $v5527aImageUrl }}" alt="{{ e($product['name'] ?? 'Producto') }}" loading="lazy" onerror="this.closest('.pimg').classList.add('v5527a-img-error'); this.remove();">
                                    <span class="v5527a-product-placeholder">🛍️</span>
                                @else
                                    <span class="v5527a-product-placeholder">🛍️</span>
                                @endif
                            </div>

                            <div class="pname">{{ $product['name'] }}</div>

                            @if(!empty($productReference))
                                <div class="code">
                                    {{ $productReference }}
                                </div>
                            @else
                                <div class="code code-empty"></div>
                            @endif

                            <div class="price">
                                ${{ number_format($v5345PriceWithTax, 2, '.', ',') }} MXN
                            </div>

                            @if($showStock)
                                @if($v5451IsService)
                                    <div class="stock">
                                        Servicio
                                    </div>
                                @else
                                    <div class="stock {{ $product['stock'] <= 0 ? 'no-stock' : '' }}">
                                        Stock: {{ number_format($product['stock'], 2, '.', ',') }}
                                    </div>
                                @endif
                            @endif

                            @if($v5451IsFavorite)
                                <div class="code" style="font-weight:900;">⭐ Favorito PDV</div>
                            @endif

                            @if(! $product['can_sell'])
                                <div class="no-stock" style="font-size:12px; margin-top:6px;">
                                    Sin existencia para venta
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- V5.51.5E - Productos ocultos para búsqueda global. --}}
                @if(($v5515eHiddenSearchProducts ?? collect())->isNotEmpty())
                    <div id="v5515e-global-search-products" style="display:none;">
                        @foreach($v5515eHiddenSearchProducts as $product)
                            @php
                                $v5451ProductType = (string) ($product['product_type'] ?? 'stockable');
                                $v5451IsService = (bool) ($product['is_service'] ?? false) || $v5451ProductType === 'service';
                                $v5451IsFavorite = (bool) ($product['is_pos_favorite'] ?? false);

                                $productReference = $product['internal_reference']
                                    ?? $product['default_code']
                                    ?? $product['reference']
                                    ?? $product['sku']
                                    ?? $product['barcode']
                                    ?? $product['code']
                                    ?? null;

                                if (! $productReference && ! is_numeric($product['code'] ?? null)) {
                                    $productReference = $product['code'] ?? null;
                                }

                                $v5490bProductBarcode = $product['barcode'] ?? '';
                                $v5490bProductSku = $product['sku'] ?? '';
                                $v5490bProductCode = $product['code'] ?? ($product['internal_reference'] ?? '');
                                $v5490bProductSearch = trim(implode(' ', array_filter([
                                    $product['name'] ?? '',
                                    $productReference ?? '',
                                    $v5490bProductBarcode,
                                    $v5490bProductSku,
                                    $v5490bProductCode,
                                    $product['description'] ?? '',
                                ])));

                                $v5345TaxRate = (float) (
                                    $product['tax_rate']
                                    ?? $product['iva_rate']
                                    ?? $product['vat_rate']
                                    ?? 0.16
                                );

                                if ($v5345TaxRate > 1) {
                                    $v5345TaxRate = $v5345TaxRate / 100;
                                }

                                if ($v5345TaxRate <= 0) {
                                    $v5345TaxRate = 0.16;
                                }

                                $v5345BasePrice = (float) (
                                    $product['price_without_tax']
                                    ?? $product['sale_price']
                                    ?? $product['price']
                                    ?? 0
                                );

                                $v5345ExplicitPriceWithTax = (float) (
                                    $product['price_with_tax']
                                    ?? $product['sale_price_with_tax']
                                    ?? 0
                                );

                                $v5345PriceWithTax = $v5345ExplicitPriceWithTax > 0
                                    ? round($v5345ExplicitPriceWithTax, 2)
                                    : round($v5345BasePrice * (1 + $v5345TaxRate), 2);
                            @endphp

                            <div
                                class="product v5515e-hidden-search-product {{ $product['can_sell'] ? '' : 'disabled' }}"
                                data-product-id="{{ $product['id'] ?? '' }}"
                            data-product-category-id="{{ (int) ($product['category_id'] ?? 0) }}"
                            data-product-is-favorite="{{ $v5451IsFavorite ? '1' : '0' }}"
                                data-product-name="{{ $product['name'] ?? '' }}"
                                data-product-reference="{{ e($productReference ?? '') }}"
                                data-product-barcode="{{ e($v5490bProductBarcode) }}"
                                data-product-sku="{{ e($v5490bProductSku) }}"
                                data-product-code="{{ e($v5490bProductCode) }}"
                                data-product-search="{{ e($v5490bProductSearch) }}"
                                data-product-price="{{ $v5345PriceWithTax }}"
                                data-product-stock="{{ (float) ($product['stock'] ?? 0) }}"
                                data-product-can-sell="{{ !empty($product['can_sell']) ? '1' : '0' }}"
                                data-product-tax-rate="{{ $v5345TaxRate }}"
                                data-product-type="{{ e($v5451ProductType) }}"
                                data-product-is-service="{{ $v5451IsService ? '1' : '0' }}"
                            data-product-image-url="{{ e($v5527aImageUrl ?? '') }}"
                                style="display:none;"
                            >
                                <div class="pimg">
                                    @if(!empty($v5527aImageUrl))
                                        <img src="{{ $v5527aImageUrl }}" alt="{{ e($product['name'] ?? 'Producto') }}" loading="lazy" onerror="this.closest('.pimg').classList.add('v5527a-img-error'); this.remove();">
                                        <span class="v5527a-product-placeholder">🛍️</span>
                                    @else
                                        <span class="v5527a-product-placeholder">🛍️</span>
                                    @endif
                                </div>
                                <div class="pname">{{ $product['name'] }}</div>

                                @if(!empty($productReference))
                                    <div class="code">{{ $productReference }}</div>
                                @else
                                    <div class="code code-empty"></div>
                                @endif

                                <div class="price">${{ number_format($v5345PriceWithTax, 2, '.', ',') }} MXN</div>

                                @if($showStock)
                                    @if($v5451IsService)
                                        <div class="stock">Servicio</div>
                                    @else
                                        <div class="stock {{ $product['stock'] <= 0 ? 'no-stock' : '' }}">
                                            Stock: {{ number_format($product['stock'], 2, '.', ',') }}
                                        </div>
                                    @endif
                                @endif

                                @if($v5451IsFavorite)
                                    <div class="code" style="font-weight:900;">⭐ Favorito PDV</div>
                                @endif

                                @if(! $product['can_sell'])
                                    <div class="no-stock" style="font-size:12px; margin-top:6px;">
                                        Sin existencia para venta
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif

            <div style="height:110px;"></div>
        </main>

        <aside class="cart">
            <div class="cart-title">
                <h2>Carrito (<span id="v5339-cart-count">0</span>)</h2>
                <a class="danger" href="#" id="v5339-cart-clear">Vaciar</a>
            </div>

            <div class="client" id="v5495c-current-customer"
                data-customer-id="{{ (int) ($customer->id ?? 0) }}"
                data-customer-price-list-id="{{ (int) ($customer->customer_price_list_id ?? 0) }}">
                <div class="label">Cliente</div>
                <div class="value">{{ $customer->name ?? 'Cliente general' }}</div>

                @if(!empty($customer->rfc))
                    <div data-v5392-customer-rfc="1" style="color:#64748b; font-size:13px; margin-top:6px;">RFC: {{ $customer->rfc }}</div>
                @endif
            </div>

            <div id="v5339-cart-items">
                <div class="item" id="v5339-cart-empty">
                    <div class="thumb">🛒</div>
                    <div>
                        <strong>Agrega productos</strong>
                        <div style="color:#64748b; margin-top:5px;">
                            Selecciona productos del panel izquierdo para agregarlos al carrito.
                        </div>
                    </div>
                    <div style="font-weight:950;">$0.00</div>
                </div>
            </div>

            @php
                // V5.49.4A - Listas de precios locales desde configuracion del PDV
                $v5494aAllowedPriceListIds = [];
                $v5494aRawAllowedPriceLists = $pos->available_price_list_ids ?? [];

                if (is_string($v5494aRawAllowedPriceLists)) {
                    $v5494aDecodedAllowedPriceLists = json_decode($v5494aRawAllowedPriceLists, true);
                    $v5494aAllowedPriceListIds = is_array($v5494aDecodedAllowedPriceLists)
                        ? $v5494aDecodedAllowedPriceLists
                        : preg_split('/\s*,\s*/', $v5494aRawAllowedPriceLists);
                } elseif (is_array($v5494aRawAllowedPriceLists)) {
                    $v5494aAllowedPriceListIds = $v5494aRawAllowedPriceLists;
                }

                $v5494aAllowedPriceListIds = collect($v5494aAllowedPriceListIds)
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                $v5494aDefaultPriceListId = (int) ($pos->default_price_list_id ?? 0);

                if ($v5494aDefaultPriceListId > 0 && ! in_array($v5494aDefaultPriceListId, $v5494aAllowedPriceListIds, true)) {
                    array_unshift($v5494aAllowedPriceListIds, $v5494aDefaultPriceListId);
                }

                $v5494aPriceLists = [];

                if (! empty($v5494aAllowedPriceListIds) && \Illuminate\Support\Facades\Schema::hasTable('sales_price_lists')) {
                    $v5494aRows = \Illuminate\Support\Facades\DB::table('sales_price_lists')
                        ->whereIn('id', $v5494aAllowedPriceListIds)
                        ->get(['id', 'name'])
                        ->keyBy('id');

                    foreach ($v5494aAllowedPriceListIds as $v5494aListId) {
                        $v5494aRow = $v5494aRows->get($v5494aListId);

                        if (! $v5494aRow) {
                            continue;
                        }

                        $v5494aPriceLists[] = [
                            'id' => (int) $v5494aRow->id,
                            'name' => (string) ($v5494aRow->name ?? ('Lista #' . $v5494aRow->id)),
                            'is_default' => (int) $v5494aRow->id === $v5494aDefaultPriceListId,
                        ];
                    }
                }

                if (empty($v5494aPriceLists)) {
                    $v5494aPriceLists[] = [
                        'id' => 0,
                        'name' => 'Precio público',
                        'is_default' => true,
                    ];
                }

                $v5494aSelectedPriceList = collect($v5494aPriceLists)->firstWhere('id', $v5494aDefaultPriceListId)
                    ?: ($v5494aPriceLists[0] ?? ['id' => 0, 'name' => 'Precio público']);

                $v5494aSelectedPriceListId = (int) ($v5494aSelectedPriceList['id'] ?? 0);
                $v5494aSelectedPriceListName = (string) ($v5494aSelectedPriceList['name'] ?? 'Precio público');
            @endphp

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:18px;">
                <button type="button" class="btn" id="v5419-cart-note" onclick="window.BEXIA_POS_OPEN_NOTE && window.BEXIA_POS_OPEN_NOTE(event)">Nota</button>
                <button type="button" class="btn {{ $canDiscount ? '' : 'btn-disabled' }}" id="v5419-cart-discount" data-can-discount="{{ $canDiscount ? '1' : '0' }}" title="{{ $canDiscount ? 'Aplicar descuento' : 'No tienes permiso para aplicar descuentos' }}" onclick="window.BEXIA_POS_OPEN_DISCOUNT && window.BEXIA_POS_OPEN_DISCOUNT(event)">Descuento</button>
                <button type="button" class="btn {{ ($canChangePriceList ?? true) ? '' : 'btn-disabled' }}" id="v5492d-open-price-list" data-can-change-price-list="{{ ($canChangePriceList ?? true) ? '1' : '0' }}" data-price-lists='@json($v5494aPriceLists)' data-selected-price-list-id="{{ $v5494aSelectedPriceListId }}" data-selected-price-list-name="{{ $v5494aSelectedPriceListName }}" title="{{ ($canChangePriceList ?? true) ? 'Cambiar lista de precios' : 'No tienes permiso para cambiar la lista de precios' }}">Lista: {{ $v5494aSelectedPriceListName }}</button>
                <button type="button" class="btn btn-disabled" id="v5492d-cart-free-slot" disabled>Disponible</button>
            </div>

            <div class="totals">
                <div class="row"><span>Subtotal</span><strong id="v5339-subtotal">$0.00</strong></div>
                <div class="row" id="v5419-discount-row" style="display:none;"><span>Descuento</span><strong id="v5419-discount-total">-$0.00</strong></div>
                <div class="row"><span>IVA</span><strong id="v5339-tax">$0.00</strong></div>
                <div class="total"><span>Total</span><strong id="v5339-total">$0.00</strong></div>
            </div>
        </aside>
    </div>



<style id="v5492d-price-list-cart-style">
    #v5492d-price-list-modal {
        position: fixed;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10050;
        background: rgba(15, 23, 42, .45);
        padding: 24px;
    }

    #v5492d-price-list-modal.is-open {
        display: flex;
    }

    #v5492d-price-list-card {
        width: min(480px, 96vw);
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 22px;
        box-shadow: 0 24px 80px rgba(15, 23, 42, .35);
        overflow: hidden;
    }

    #v5492d-price-list-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 18px 22px;
        border-bottom: 1px solid #e2e8f0;
    }

    #v5492d-price-list-title {
        margin: 0;
        font-size: 20px;
        font-weight: 950;
        color: #0f172a;
    }

    #v5492d-price-list-close {
        border: 1px solid #cbd5e1;
        background: #fff;
        border-radius: 12px;
        padding: 8px 12px;
        font-weight: 900;
        cursor: pointer;
    }

    #v5492d-price-list-body {
        padding: 20px 22px;
    }

    #v5492d-price-list-label {
        display: block;
        font-size: 12px;
        color: #64748b;
        font-weight: 900;
        margin-bottom: 7px;
    }

    #v5492d-price-list-select {
        width: 100%;
        border: 1px solid #dbe3ef;
        border-radius: 14px;
        padding: 12px 14px;
        font-size: 15px;
        font-weight: 750;
        outline: none;
        background: #fff;
        color: #0f172a;
    }

    #v5492d-price-list-status {
        margin-top: 12px;
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        color: #1e40af;
        border-radius: 14px;
        padding: 10px 12px;
        font-size: 13px;
        font-weight: 800;
        line-height: 1.35;
    }

    #v5492d-price-list-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 0 22px 20px;
    }

    .v5492d-price-list-secondary {
        border: 1px solid #cbd5e1;
        background: #fff;
        border-radius: 14px;
        padding: 12px 16px;
        font-weight: 900;
        cursor: pointer;
    }

    .v5492d-price-list-primary {
        border: 0;
        background: #2563eb;
        color: #fff;
        border-radius: 14px;
        padding: 12px 18px;
        font-weight: 950;
        cursor: pointer;
    }

    .v5492d-price-list-primary:disabled,
    .v5492d-price-list-secondary:disabled {
        opacity: .55;
        cursor: not-allowed;
    }
</style>

<div id="v5492d-price-list-modal" aria-hidden="true">
    <div id="v5492d-price-list-card">
        <div id="v5492d-price-list-header">
            <h2 id="v5492d-price-list-title">Lista de precios</h2>
            <button type="button" id="v5492d-price-list-close">Cerrar</button>
        </div>

        <div id="v5492d-price-list-body">
            <label id="v5492d-price-list-label" for="v5492d-price-list-select">
                Selecciona la lista para este ticket
            </label>

            <select id="v5492d-price-list-select"></select>

            <div id="v5492d-price-list-status">
                Cargando listas permitidas...
            </div>
        </div>

        <div id="v5492d-price-list-actions">
            <button type="button" class="v5492d-price-list-secondary" id="v5492d-price-list-cancel">Cancelar</button>
            <button type="button" class="v5492d-price-list-primary" id="v5492d-price-list-apply">Aplicar y actualizar</button>
        </div>
    </div>
</div>


<script id="v5492d-price-list-cart-script">
(function () {
    'use strict';

    if (window.__v5492dPriceListCartLoaded) {
        return;
    }

    window.__v5492dPriceListCartLoaded = true;

    function sessionId() {
        const match = String(window.location.pathname).match(/\/pos\/sessions\/(\d+)/);
        return match ? match[1] : null;
    }

    function status(message) {
        const el = document.getElementById('v5492d-price-list-status');
        if (el) {
            el.textContent = message;
        }
    }

    function priceListButton() {
        return document.getElementById('v5492d-open-price-list');
    }
function setPriceListButtonLabel(name, id) {
        const button = priceListButton();

        if (!button) {
            return;
        }

        const cleanName = String(name || '').trim();
        const label = cleanName !== '' ? cleanName : (id ? ('Lista #' + id) : 'Precio público');

        button.textContent = 'Lista: ' + label;
        button.title = 'Lista de precios actual: ' + label;
        button.dataset.selectedPriceListId = String(id || 0);
        button.dataset.selectedPriceListName = label;
    }

    function localPriceListsData() {
        const button = priceListButton();

        if (!button) {
            return {
                price_lists: [],
                selected_price_list_id: 0,
                selected_price_list_name: ''
            };
        }

        let lists = [];

        try {
            lists = JSON.parse(button.dataset.priceLists || '[]');
        } catch (error) {
            console.warn('No se pudieron leer listas locales de precio:', error);
            lists = [];
        }

        return {
            price_lists: Array.isArray(lists) ? lists : [],
            selected_price_list_id: Number(button.dataset.selectedPriceListId || 0),
            selected_price_list_name: String(button.dataset.selectedPriceListName || '')
        };
    }

    function money(value) {
        return '$' + Number(value || 0).toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' MXN';
    }

    function setLoading(loading) {
        const apply = document.getElementById('v5492d-price-list-apply');
        const select = document.getElementById('v5492d-price-list-select');

        if (apply) {
            apply.disabled = loading;
            apply.textContent = loading ? 'Actualizando...' : 'Aplicar y actualizar';
        }

        if (select) {
            select.disabled = loading;
        }
    }

    function openModal(event) {
        // V5.49.9A - bloquear cambio manual de lista de precios si el usuario no tiene permiso.
        if (event && event.preventDefault) {
            event.preventDefault();
        }

        const openButton = document.getElementById('v5492d-open-price-list');

        if (openButton && String(openButton.dataset.canChangePriceList || '1') !== '1') {
            if (typeof window.notice === 'function') {
                window.notice('No tienes permiso para cambiar la lista de precios.', 'warning');
            } else if (typeof window.showPosNotice === 'function') {
                window.showPosNotice('No tienes permiso para cambiar la lista de precios.', 'warning');
            } else {
                alert('No tienes permiso para cambiar la lista de precios.');
            }

            return;
        }

        const modal = document.getElementById('v5492d-price-list-modal');
        if (modal) {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            loadLists();
        }
    }

    function closeModal() {
        const modal = document.getElementById('v5492d-price-list-modal');
        if (modal) {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    async function loadLists() {
        const select = document.getElementById('v5492d-price-list-select');

        if (!select) {
            status('No se pudo cargar el selector de listas.');
            return;
        }

        const data = localPriceListsData();
        const lists = Array.isArray(data.price_lists) ? data.price_lists : [];

        if (lists.length === 0) {
            status('No hay listas configuradas para este PDV.');
            return;
        }

        select.innerHTML = '';

        const currentId = Number(window.BEXIA_POS_SELECTED_PRICE_LIST_ID || data.selected_price_list_id || 0);

        lists.forEach(function (list) {
            const option = document.createElement('option');
            option.value = String(list.id || 0);
            option.textContent = list.name || ('Lista #' + list.id);
            option.selected = Number(list.id || 0) === currentId;
            select.appendChild(option);
        });

        const selected = lists.find(function (list) {
            return Number(list.id || 0) === currentId;
        }) || lists[0];

        window.BEXIA_POS_SELECTED_PRICE_LIST_ID = Number(selected.id || 0);
        window.BEXIA_POS_SELECTED_PRICE_LIST_NAME = String(selected.name || '');

        setPriceListButtonLabel(selected.name || '', selected.id || 0);

        status('Lista actual: ' + (selected.name || 'Precio público'));
    }

    function updateVisibleCards(products) {
        let changed = 0;

        products.forEach(function (item) {
            const cards = document.querySelectorAll('.product[data-product-id="' + String(item.id || '') + '"]');

            cards.forEach(function (card) {
                const price = Number(item.price || item.public_price || 0);
                const stock = Number(item.available_quantity ?? item.stock_quantity ?? 0);
                const isService = card.dataset.productIsService === '1' || card.dataset.productType === 'service';

                card.dataset.productPrice = String(price.toFixed(2));
                card.dataset.productStock = String(stock);
                card.dataset.productPriceListId = String(item.price_list_id || '');

                const priceEl = card.querySelector('.price');
                if (priceEl) {
                    priceEl.textContent = money(price);
                }

                const stockEl = card.querySelector('.stock');
                if (stockEl && !isService) {
                    stockEl.textContent = 'Stock: ' + Number(stock || 0).toLocaleString('es-MX', {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 2
                    });
                }

                changed++;
            });
        });

        return changed;
    }

    function updateCart(products) {
        if (window.BEXIA_POS_CART_API && typeof window.BEXIA_POS_CART_API.refreshProductData === 'function') {
            return Number(window.BEXIA_POS_CART_API.refreshProductData(products) || 0);
        }

        return 0;
    }

    function applyProducts(products) {
        if (typeof window.BEXIA_POS_REFRESH_PRODUCTS_APPLY === 'function') {
            const applied = window.BEXIA_POS_REFRESH_PRODUCTS_APPLY(products) || {};
            return {
                cardsChanged: Number(applied.cardsChanged || 0),
                cartChanged: Number(applied.cartChanged || 0)
            };
        }

        return {
            cardsChanged: updateVisibleCards(products),
            cartChanged: updateCart(products)
        };
    }

    async function applyList() {
        const sid = sessionId();
        const select = document.getElementById('v5492d-price-list-select');
        const priceListId = select ? select.value : 0;

        // V5.50.0A - auditoria cambio manual lista precios.
        const previousPriceListId = Number(window.BEXIA_POS_SELECTED_PRICE_LIST_ID || priceListButton()?.dataset.selectedPriceListId || 0);
        const previousPriceListName = String(window.BEXIA_POS_SELECTED_PRICE_LIST_NAME || priceListButton()?.dataset.selectedPriceListName || '');

        if (!sid) {
            status('No se pudo detectar la sesión del PDV.');
            return;
        }

        setLoading(true);
        status('Aplicando lista y actualizando productos...');

        try {
            const response = await fetch(
                '/pos/sessions/' + encodeURIComponent(sid)
                    + '/products-refresh?price_list_id=' + encodeURIComponent(priceListId)
                    + '&previous_price_list_id=' + encodeURIComponent(previousPriceListId)
                    + '&previous_price_list_name=' + encodeURIComponent(previousPriceListName)
                    + '&price_list_change_source=applyList',
                {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }
            );

            const data = await response.json();

            if (!response.ok || !data.ok || !Array.isArray(data.products)) {
                status('No se pudo aplicar la lista.');
                return;
            }

            window.BEXIA_POS_SELECTED_PRICE_LIST_ID = Number(data.selected_price_list_id || priceListId || 0);
            window.BEXIA_POS_SELECTED_PRICE_LIST_NAME = String(data.selected_price_list_name || '');

            setPriceListButtonLabel(
                data.selected_price_list_name || '',
                data.selected_price_list_id || priceListId || 0
            );

            const result = applyProducts(data.products);

            status(
                'Aplicada: ' + (data.selected_price_list_name || 'Lista #' + priceListId)
                + '. Productos actualizados: ' + result.cardsChanged
                + '. Líneas de carrito actualizadas: ' + result.cartChanged + '.'
            );

            if (typeof window.BEXIA_POS_AUDIT_PRICE_LIST_CHANGE === 'function') {
                window.BEXIA_POS_AUDIT_PRICE_LIST_CHANGE({
                    source: 'manual',
                    previous_price_list_id: previousPriceListId,
                    previous_price_list_name: previousPriceListName,
                    new_price_list_id: Number(data.selected_price_list_id || priceListId || 0),
                    new_price_list_name: String(data.selected_price_list_name || '')
                });
            }

            setTimeout(closeModal, 900);
        } catch (error) {
            console.error(error);
            status('Error aplicando lista de precios.');
        } finally {
            setLoading(false);
        }
    }


    window.BEXIA_POS_APPLY_PRICE_LIST_BY_ID = async function (priceListId, options) {
        options = options || {};

        const sid = sessionId();
        const id = Number(priceListId || 0);

        if (!sid || id <= 0) {
            return false;
        }

        if (!options.silent) {
            status('Aplicando lista y actualizando productos...');
        }

        const response = await fetch(
            '/pos/sessions/' + encodeURIComponent(sid)
                + '/products-refresh?price_list_id=' + encodeURIComponent(id)
                + '&previous_price_list_id=' + encodeURIComponent(Number(window.BEXIA_POS_SELECTED_PRICE_LIST_ID || priceListButton()?.dataset.selectedPriceListId || 0))
                + '&previous_price_list_name=' + encodeURIComponent(String(window.BEXIA_POS_SELECTED_PRICE_LIST_NAME || priceListButton()?.dataset.selectedPriceListName || ''))
                + '&price_list_change_source=applyById',
            {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }
        );

        const data = await response.json();

        if (!response.ok || !data.ok || !Array.isArray(data.products)) {
            throw new Error(data.message || 'No se pudo aplicar la lista de precios.');
        }

        window.BEXIA_POS_SELECTED_PRICE_LIST_ID = Number(data.selected_price_list_id || id || 0);
        window.BEXIA_POS_SELECTED_PRICE_LIST_NAME = String(data.selected_price_list_name || '');

        setPriceListButtonLabel(
            data.selected_price_list_name || '',
            data.selected_price_list_id || id || 0
        );

        const result = applyProducts(data.products);

        if (!options.silent) {
            status(
                'Aplicada: ' + (data.selected_price_list_name || 'Lista #' + id)
                + '. Productos actualizados: ' + result.cardsChanged
                + '. Líneas de carrito actualizadas: ' + result.cartChanged + '.'
            );
        }

        document.dispatchEvent(new CustomEvent('bexia:pos-price-list-applied', {
            detail: {
                price_list_id: window.BEXIA_POS_SELECTED_PRICE_LIST_ID,
                price_list_name: window.BEXIA_POS_SELECTED_PRICE_LIST_NAME,
                source: options.source || 'manual'
            }
        }));

        return true;
    };

    document.addEventListener('DOMContentLoaded', function () {
        const open = document.getElementById('v5492d-open-price-list');
        const close = document.getElementById('v5492d-price-list-close');
        const cancel = document.getElementById('v5492d-price-list-cancel');
        const apply = document.getElementById('v5492d-price-list-apply');
        const modal = document.getElementById('v5492d-price-list-modal');

        if (open) {
            open.addEventListener('click', openModal);
        }

        if (close) {
            close.addEventListener('click', closeModal);
        }

        if (cancel) {
            cancel.addEventListener('click', closeModal);
        }

        if (apply) {
            apply.addEventListener('click', applyList);
        }

        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
        }
    });
})();
</script>


<style id="v5422-note-discount-modal-style">
    .v5422-note-discount-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .45);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10020;
        padding: 24px;
    }

    .v5422-note-discount-backdrop.is-open {
        display: flex;
    }

    .v5422-note-discount-card {
        width: min(520px, 96vw);
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 22px;
        box-shadow: 0 24px 80px rgba(15, 23, 42, .35);
        overflow: hidden;
    }

    .v5422-note-discount-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 18px 22px;
        border-bottom: 1px solid #e2e8f0;
    }

    .v5422-note-discount-title {
        margin: 0;
        font-size: 20px;
        font-weight: 950;
        color: #0f172a;
    }

    .v5422-note-discount-close {
        border: 1px solid #cbd5e1;
        background: #fff;
        border-radius: 12px;
        padding: 8px 12px;
        font-weight: 900;
        cursor: pointer;
    }

    .v5422-note-discount-body {
        padding: 20px 22px;
    }

    .v5422-note-discount-label {
        display: block;
        font-size: 12px;
        color: #64748b;
        font-weight: 900;
        margin-bottom: 7px;
    }

    .v5422-note-discount-input,
    .v5422-note-discount-select,
    .v5422-note-discount-textarea {
        width: 100%;
        border: 1px solid #dbe3ef;
        border-radius: 14px;
        padding: 12px 14px;
        font-size: 15px;
        font-weight: 750;
        outline: none;
        background: #fff;
        color: #0f172a;
    }

    .v5422-note-discount-textarea {
        min-height: 110px;
        resize: vertical;
        line-height: 1.35;
    }

    .v5422-note-discount-grid {
        display: grid;
        grid-template-columns: 160px 1fr;
        gap: 12px;
    }

    .v5422-note-discount-help {
        margin-top: 9px;
        color: #64748b;
        font-size: 12px;
        font-weight: 750;
    }

    .v5422-note-discount-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 0 22px 20px;
    }

    .v5422-note-discount-secondary {
        border: 1px solid #cbd5e1;
        background: #fff;
        border-radius: 14px;
        padding: 12px 16px;
        font-weight: 900;
        cursor: pointer;
    }

    .v5422-note-discount-primary {
        border: 0;
        background: #2563eb;
        color: #fff;
        border-radius: 14px;
        padding: 12px 18px;
        font-weight: 950;
        cursor: pointer;
    }

    .v5422-note-discount-danger {
        border: 1px solid #fecaca;
        background: #fff;
        color: #b91c1c;
        border-radius: 14px;
        padding: 12px 16px;
        font-weight: 900;
        cursor: pointer;
        margin-right: auto;
    }
</style>

<div id="v5422-note-discount-modal" class="v5422-note-discount-backdrop" aria-hidden="true">
    <div class="v5422-note-discount-card">
        <div class="v5422-note-discount-header">
            <h2 class="v5422-note-discount-title" id="v5422-note-discount-title">Nota</h2>
            <button type="button" class="v5422-note-discount-close" id="v5422-note-discount-close">Cerrar</button>
        </div>

        <div class="v5422-note-discount-body">
            <div id="v5422-note-panel">
                <label class="v5422-note-discount-label" for="v5422-note-value">Nota del ticket</label>
                <textarea id="v5422-note-value" class="v5422-note-discount-textarea" placeholder="Escribe una nota para este ticket..."></textarea>
                <div class="v5422-note-discount-help">La nota se guardará en el ticket pendiente y se recuperará al cargarlo.</div>
            </div>

            <div id="v5422-discount-panel" style="display:none;">
                <div class="v5422-note-discount-grid">
                    <div>
                        <label class="v5422-note-discount-label" for="v5422-discount-type">Tipo</label>
                        <select id="v5422-discount-type" class="v5422-note-discount-select">
                            <option value="percent">Porcentaje</option>
                            <option value="amount">Importe fijo</option>
                        </select>
                    </div>
                    <div>
                        <label class="v5422-note-discount-label" for="v5422-discount-value">Valor</label>
                        <input id="v5422-discount-value" class="v5422-note-discount-input" type="number" step="0.01" min="0" placeholder="Ej. 10">
                    </div>
                </div>
                <div class="v5422-note-discount-help">
                    El descuento guardará qué usuario lo aplicó y se reflejará en el total del carrito.
                </div>
            </div>
        </div>

        <div class="v5422-note-discount-actions">
            <button type="button" class="v5422-note-discount-danger" id="v5422-note-discount-clear">Quitar</button>
            <button type="button" class="v5422-note-discount-secondary" id="v5422-note-discount-cancel">Cancelar</button>
            <button type="button" class="v5422-note-discount-primary" id="v5422-note-discount-save">Guardar</button>
        </div>
    </div>
</div>


    <div class="payments">
        
@php
    $v5334PaymentMethods = collect($paymentMethods ?? []);

    $v5334AllowedPaymentIds = [];

    if (isset($pos)) {
        $rawPaymentIds = $pos->payment_method_ids ?? null;

        if (is_string($rawPaymentIds) && trim($rawPaymentIds) !== '') {
            $decodedPaymentIds = json_decode($rawPaymentIds, true);
            $v5334AllowedPaymentIds = is_array($decodedPaymentIds) ? $decodedPaymentIds : [];
        } elseif (is_array($rawPaymentIds)) {
            $v5334AllowedPaymentIds = $rawPaymentIds;
        }
    }

    $v5334AllowedPaymentIds = collect($v5334AllowedPaymentIds)
        ->filter(fn ($id) => is_numeric($id))
        ->map(fn ($id) => (int) $id)
        ->values()
        ->all();

    if (! empty($v5334AllowedPaymentIds)) {
        $v5334PaymentMethods = $v5334PaymentMethods
            ->filter(function ($method) use ($v5334AllowedPaymentIds) {
                $id = data_get($method, 'id');

                return $id && in_array((int) $id, $v5334AllowedPaymentIds, true);
            })
            ->values();
    }
@endphp

@foreach($v5334PaymentMethods as $method)
            @php
                $methodText = trim((string) $method);
                $icon = match (mb_strtolower($methodText)) {
                    'efectivo' => '💵',
                    'tarjeta', 'tarjeta de crédito', 'tarjeta de debito', 'tarjeta de débito' => '💳',
                    'transferencia' => '🏦',
                    'cheque' => '🧾',
                    default => '💰',
                };
            @endphp

            <div class="pay">{{ $icon }} {{ $methodText }}</div>
        @endforeach
    </div>

    <div class="charge {{ ($canCreateTicket || $canCharge) ? '' : 'btn-disabled' }}">
        @if($canCharge)
            <span>🛒 Finalizar venta</span>
        @elseif($canCreateTicket)
            <span>🧾 Crear ticket</span>
        @else
            <span>Sin permisos</span>
        @endif
        <span id="v5339-charge-total">$0.00</span>
    </div>



<style id="v5333-pos-stable-ui">
    .v5333-clock {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 9px;
        border: 1px solid #cbd5e1;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        color: #334155;
        background: #fff;
        white-space: nowrap;
        margin-left: 10px;
    }

    .v5333-close-session {
        border: 1px solid #ef4444;
        color: #b91c1c;
        background: #fff;
        border-radius: 10px;
        padding: 7px 11px;
        font-size: 12px;
        font-weight: 800;
        cursor: pointer;
        margin-left: 8px;
    }

    /* Compactar visualmente sin esconder nada */
    .product,
    .product-card,
    [class*="product"],
    [class*="Product"] {
        min-height: 130px;
    }

    .category,
    [class*="category"],
    [class*="Category"] {
        text-align: center;
        font-size: 12px;
        line-height: 1.15;
    }

    .category *,
    [class*="category"] *,
    [class*="Category"] * {
        text-align: center;
        font-size: 12px;
        line-height: 1.15;
    }
</style>

<script id="v5333-pos-stable-script">
document.addEventListener('DOMContentLoaded', function () {
    function norm(text) {
        return (text || '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    // Reloj: solo agrega, no oculta nada.
    if (!document.querySelector('.v5333-clock')) {
        const clock = document.createElement('span');
        clock.className = 'v5333-clock';
        clock.innerHTML = '🕒 <span>--:--:--</span>';

        function tick() {
            const d = new Date();
            const hh = String(d.getHours()).padStart(2, '0');
            const mm = String(d.getMinutes()).padStart(2, '0');
            const ss = String(d.getSeconds()).padStart(2, '0');
            const value = clock.querySelector('span');

            if (value) {
                value.textContent = hh + ':' + mm + ':' + ss;
            }
        }

        tick();
        setInterval(tick, 1000);

        const host = Array.from(document.querySelectorAll('header, .topbar, .pos-header, .fi-header, body > div'))
            .find(function (el) {
                const txt = norm(el.textContent);
                return txt.includes('venta rápida') || txt.includes('sesión pos-') || txt.includes('empleado');
            });

        if (host) {
            host.appendChild(clock);
        } else {
            document.body.prepend(clock);
        }
    }

    // Cerrar sesión: solo agrega, no oculta nada.
    if (!document.getElementById('v5333-close-session-form')) {
        const match = window.location.pathname.match(/\/pos\/sessions\/(\d+)\/screen/);

        if (match) {
            const form = document.createElement('form');
            form.id = 'v5333-close-session-form';
            form.method = 'POST';
            form.action = '/pos/sessions/' + match[1] + '/close';
            form.style.display = 'inline-flex';
            form.style.margin = '0';

            const token = document.createElement('input');
            token.type = 'hidden';
            token.name = '_token';
            token.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const btn = document.createElement('button');
            btn.type = 'submit';
            btn.className = 'v5333-close-session';
            btn.textContent = 'Cerrar sesión';

            form.addEventListener('submit', function (event) {
                if (!confirm('¿Cerrar la sesión actual del PDV?')) {
                    event.preventDefault();
                }
            });

            form.appendChild(token);
            form.appendChild(btn);

            const clock = document.querySelector('.v5333-clock');
            if (clock && clock.parentElement) {
                clock.parentElement.appendChild(form);
            } else {
                document.body.prepend(form);
            }
        }
    }

    // Agregar "Más vendido" antes de "Todas", pero sin filtrar todavía.
    if (!document.getElementById('v5333-top-seller-button')) {
        const allButton = Array.from(document.querySelectorAll('button, a'))
            .find(function (el) {
                return norm(el.textContent) === 'todas';
            });

        if (allButton && allButton.parentElement) {
            const topButton = allButton.cloneNode(true);
            topButton.id = 'v5333-top-seller-button';
            topButton.textContent = 'Más vendido';
            topButton.addEventListener('click', function (event) {
                event.preventDefault();
            });

            allButton.parentElement.insertBefore(topButton, allButton);
        }
    }
});
</script>


<style id="v5335-payment-modal-style">
    .payments {
        display: none !important;
    }

    .charge {
        cursor: pointer;
    }

    .v5335-payment-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .45);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 24px;
    }

    .v5335-payment-backdrop.is-open {
        display: flex;
    }

    .v5335-payment-modal {
        width: min(720px, 96vw);
        max-height: 90vh;
        overflow: auto;
        background: #fff;
        border-radius: 22px;
        box-shadow: 0 24px 80px rgba(15, 23, 42, .35);
        border: 1px solid #e2e8f0;
    }

    .v5335-payment-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 24px;
        border-bottom: 1px solid #e2e8f0;
    }

    .v5335-payment-title {
        font-size: 22px;
        font-weight: 950;
        margin: 0;
        color: #0f172a;
    }

    .v5335-payment-close {
        border: 1px solid #cbd5e1;
        background: #fff;
        border-radius: 12px;
        padding: 8px 12px;
        font-weight: 900;
        cursor: pointer;
    }

    .v5335-payment-body {
        padding: 22px 24px;
    }

    .v5335-payment-total {
        border: 1px solid #dbeafe;
        background: #eff6ff;
        color: #1d4ed8;
        border-radius: 18px;
        padding: 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 900;
        margin-bottom: 18px;
    }

    .v5335-payment-total strong {
        font-size: 28px;
    }

    .v5335-payment-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: 12px;
    }

    .v5335-payment-option {
        border: 1px solid #dbe3ef;
        background: #fff;
        border-radius: 18px;
        padding: 18px;
        font-weight: 900;
        text-align: center;
        cursor: pointer;
        transition: transform .12s ease, box-shadow .12s ease;
    }

    .v5335-payment-option:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 24px rgba(15, 23, 42, .10);
    }

    .v5335-payment-actions {
        margin-top: 20px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .v5335-payment-confirm {
        border: 0;
        background: #2563eb;
        color: #fff;
        border-radius: 14px;
        padding: 13px 18px;
        font-weight: 950;
        cursor: pointer;
    }

    .v5335-payment-cancel {
        border: 1px solid #cbd5e1;
        background: #fff;
        border-radius: 14px;
        padding: 13px 18px;
        font-weight: 900;
        cursor: pointer;
    }
</style>
<div id="v5335-payment-modal" class="v5335-payment-backdrop" aria-hidden="true">
    <div class="v5335-payment-modal">
        <div class="v5335-payment-header">
            <h2 class="v5335-payment-title">Cobrar ticket</h2>
            <button type="button" class="v5335-payment-close" id="v5335-payment-close">Cerrar</button>
        </div>

        <div class="v5335-payment-body">
            <div class="v5335-payment-total">
                <span>Total a cobrar</span>
                <strong id="v5335-payment-total">$0.00</strong>
            </div>

            <div id="v5335-payment-options" class="v5335-payment-grid"></div>

            <div class="v5335-payment-actions">
                <button type="button" class="v5335-payment-cancel" id="v5335-payment-cancel">Cancelar</button>
                <button type="button" class="v5335-payment-confirm" id="v5335-payment-confirm">Registrar cobro</button>
            </div>
        </div>
    </div>
</div>

<style id="v5339-cart-style">
    .v5339-cart-line {
        margin-top:10px;
        display:grid;
        grid-template-columns:44px 1fr auto;
        gap:10px;
        align-items:center;
        border:1px solid #eef2f7;
        border-radius:14px;
        padding:10px;
        background:#fff;
    }

    .v5339-cart-line-name {
        font-size:13px;
        font-weight:900;
        line-height:1.15;
    }

    .v5339-cart-line-ref {
        font-size:10px;
        color:#64748b;
        margin-top:2px;
        min-height:10px;
    }

    .v5339-cart-line-price {
        font-size:12px;
        color:#2563eb;
        font-weight:900;
        margin-top:4px;
    }

    .v5339-cart-controls {
        display:flex;
        align-items:center;
        justify-content:flex-end;
        gap:5px;
        margin-top:6px;
    }

    .v5339-cart-controls button {
        width:24px;
        height:24px;
        border-radius:8px;
        border:1px solid #cbd5e1;
        background:#fff;
        font-weight:950;
        cursor:pointer;
        line-height:1;
    }

    /* BEXIA_V582P3_A35B_QTY_KEYBOARD_CSS */
    .v5339-cart-qty {
        width:64px;
        min-width:64px;
        max-width:76px;
        box-sizing:border-box;
        border:1px solid #cbd5e1;
        border-radius:8px;
        padding:5px 6px;
        background:#fff;
        color:#0f172a;
        text-align:center;
        font-size:13px;
        font-weight:900;
        line-height:1.2;
        appearance:textfield;
        -moz-appearance:textfield;
    }

    .v5339-cart-qty::selection {
        background:#bfdbfe;
        color:#0f172a;
    }

    .v5339-cart-qty:focus {
        outline:2px solid #2563eb;
        outline-offset:1px;
        border-color:#2563eb;
    }

    .v5339-cart-line-total {
        font-size:13px;
        font-weight:950;
        text-align:right;
        color:#0f172a;
        white-space:nowrap;
    }

    .v5339-cart-remove {
        border:0 !important;
        color:#dc2626;
        background:transparent !important;
        font-size:13px;
        width:auto !important;
        height:auto !important;
        padding:0 !important;
        cursor:pointer;
    }

    .v5339-stock-warning {
        margin-top:10px;
        border:1px solid #fed7aa;
        background:#fff7ed;
        color:#9a3412;
        border-radius:12px;
        padding:9px 10px;
        font-size:12px;
        font-weight:800;
        display:none;
    }
</style>

<script id="v5339-cart-script">
document.addEventListener('DOMContentLoaded', function () {
    const cart = new Map();

    let cartNote = '';
    let cartDiscount = null;

    const formatter = new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    const cartItems = document.getElementById('v5339-cart-items');
    const cartEmpty = document.getElementById('v5339-cart-empty');
    const cartCount = document.getElementById('v5339-cart-count');
    const clearBtn = document.getElementById('v5339-cart-clear');
    const subtotalEl = document.getElementById('v5339-subtotal');
    const taxEl = document.getElementById('v5339-tax');
    const totalEl = document.getElementById('v5339-total');
    const chargeTotalEl = document.getElementById('v5339-charge-total');
    const discountRow = document.getElementById('v5419-discount-row');
    const discountTotalEl = document.getElementById('v5419-discount-total');

    if (!cartItems) {
        return;
    }

    function money(value) {
        return formatter.format(Number(value || 0));
    }

    function toNumber(value, fallback = 0) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function lineKey(product) {
        return String(product.id || product.name || Math.random());
    }

    function setWarning(message) {
        let warning = document.getElementById('v5339-stock-warning');

        if (!warning) {
            warning = document.createElement('div');
            warning.id = 'v5339-stock-warning';
            warning.className = 'warning';
            cartItems.parentElement.insertBefore(warning, cartItems);
        }

        warning.textContent = message;
        warning.style.display = 'block';

        window.clearTimeout(window.v5339WarningTimer);
        window.v5339WarningTimer = window.setTimeout(function () {
            warning.style.display = 'none';
        }, 3500);
    }

    function grossTotals() {
        let subtotal = 0;
        let tax = 0;
        let total = 0;

        cart.forEach(function (item) {
            const lineTotal = Number(item.qty || 0) * Number(item.price || 0);
            const taxRate = Number(item.taxRate || 0);

            let lineSubtotal = lineTotal;
            let lineTax = 0;

            if (taxRate > 0) {
                lineSubtotal = lineTotal / (1 + taxRate);
                lineTax = lineTotal - lineSubtotal;
            }

            subtotal += lineSubtotal;
            tax += lineTax;
            total += lineTotal;
        });

        return { subtotal: subtotal, tax: tax, total: total };
    }

    function discountAmount(grossTotal) {
        if (!cartDiscount || grossTotal <= 0) {
            return 0;
        }

        const type = cartDiscount.type || 'amount';
        const value = Number(cartDiscount.value || 0);

        if (!Number.isFinite(value) || value <= 0) {
            return 0;
        }

        const amount = type === 'percent'
            ? grossTotal * (value / 100)
            : value;

        return Math.max(0, Math.min(amount, grossTotal));
    }

    function renderMetaSummary() {
        let box = document.getElementById('v5419-cart-meta-summary');

        const noteText = String(cartNote || '').trim();
        const hasNote = noteText !== '';
        const hasDiscount = !!cartDiscount;

        if (!hasNote && !hasDiscount) {
            if (box) {
                box.remove();
            }

            return;
        }

        if (!box) {
            box = document.createElement('div');
            box.id = 'v5419-cart-meta-summary';
            box.style.marginTop = '10px';
            box.style.fontSize = '12px';
            box.style.fontWeight = '800';
            box.style.color = '#334155';

            const totalsBox = document.querySelector('.totals');

            if (totalsBox && totalsBox.parentNode) {
                totalsBox.parentNode.insertBefore(box, totalsBox);
            }
        }

        box.textContent = '';

        if (hasNote) {
            const noteLine = document.createElement('div');
            noteLine.textContent = 'Nota: ' + noteText;
            box.appendChild(noteLine);
        }

        if (hasDiscount) {
            const discountLine = document.createElement('div');
            const userName = cartDiscount.user_name || 'Usuario';
            const type = cartDiscount.type === 'percent' ? 'porcentaje' : 'importe';
            const value = Number(cartDiscount.value || 0);

            discountLine.style.color = '#b91c1c';
            discountLine.style.marginTop = hasNote ? '4px' : '0';
            discountLine.style.fontWeight = '950';
            discountLine.textContent = 'Descuento aplicado por ' + userName + ' (' + type + ': ' + value + (cartDiscount.type === 'percent' ? '%' : '') + ')';

            box.appendChild(discountLine);
        }
    }

    function totals() {
        const gross = grossTotals();
        const discountValue = discountAmount(gross.total);
        const factor = gross.total > 0 ? Math.max(0, (gross.total - discountValue) / gross.total) : 1;

        const subtotal = gross.subtotal * factor;
        const tax = gross.tax * factor;
        const total = Math.max(0, gross.total - discountValue);

        if (subtotalEl) subtotalEl.textContent = money(subtotal);

        if (discountRow && discountTotalEl) {
            if (discountValue > 0) {
                discountRow.style.display = '';
                discountTotalEl.textContent = '-' + money(discountValue);
            } else {
                discountRow.style.display = 'none';
                discountTotalEl.textContent = '-$0.00';
            }
        }

        if (taxEl) taxEl.textContent = money(tax);
        if (totalEl) totalEl.textContent = money(total);
        if (chargeTotalEl) chargeTotalEl.textContent = money(total);

        const v5481fBottomChargeTotalEl = document.getElementById('v5349-charge-total');
        if (v5481fBottomChargeTotalEl) v5481fBottomChargeTotalEl.textContent = money(total);

        renderMetaSummary();
    }

    // BEXIA_V582P3_A35C_QTY_KEYBOARD_DOCUMENT_GUARD
    // Protege el campo contra atajos y lectores de código que escuchen
    // el teclado en document antes de que el valor pueda capturarse.
    document.addEventListener('keydown', function (event) {
        const target = event.target;

        if (
            !target
            || !target.matches
            || !target.matches('input[data-bexia-qty-keyboard="A35C"]')
        ) {
            return;
        }

        const allowedNavigation = [
            'Backspace',
            'Delete',
            'ArrowLeft',
            'ArrowRight',
            'Home',
            'End',
            'Tab',
        ];

        const isDigit = /^[0-9]$/.test(String(event.key || ''));
        const isShortcut = event.ctrlKey || event.metaKey;

        if (event.key === 'Enter') {
            event.preventDefault();
            event.stopImmediatePropagation();
            target.dispatchEvent(new Event('change', { bubbles: false }));
            target.blur();

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopImmediatePropagation();
            target.value = String(target.dataset.originalQty || '1');
            target.blur();

            return;
        }

        if (isDigit || isShortcut || allowedNavigation.includes(event.key)) {
            event.stopImmediatePropagation();

            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
    }, true);

    function render() {
        Array.from(cartItems.querySelectorAll('.v5339-cart-line')).forEach(function (el) {
            el.remove();
        });

        if (cartEmpty) {
            cartEmpty.style.display = cart.size === 0 ? '' : 'none';
        }

        if (cartCount) {
            let count = 0;
            cart.forEach(function (item) {
                count += Number(item.qty || 0);
            });
            cartCount.textContent = String(count);
        }

        cart.forEach(function (item, key) {
            const line = document.createElement('div');
            line.className = 'item v5339-cart-line';
            line.dataset.key = key;

            const thumb = document.createElement('div');
            thumb.className = 'thumb v5527a-cart-thumb';

            if (item.imageUrl) {
                const img = document.createElement('img');
                img.src = item.imageUrl;
                img.alt = item.name || 'Producto';
                img.loading = 'lazy';
                img.onerror = function () {
                    thumb.textContent = '🛍️';
                };
                thumb.appendChild(img);
            } else {
                thumb.textContent = '🛍️';
            }

            const info = document.createElement('div');

            const name = document.createElement('strong');
            name.textContent = item.name;

            const ref = document.createElement('div');
            ref.style.color = '#64748b';
            ref.style.marginTop = '4px';
            ref.textContent = item.reference || '';

            const price = document.createElement('div');
            price.style.color = '#2563eb';
            price.style.fontWeight = '900';
            price.style.marginTop = '5px';
            price.textContent = money(item.price);

            const controls = document.createElement('div');
            controls.style.display = 'flex';
            controls.style.gap = '6px';
            controls.style.alignItems = 'center';
            controls.style.marginTop = '8px';

            const minus = document.createElement('button');
            minus.type = 'button';
            minus.className = 'btn';
            minus.style.padding = '5px 9px';
            minus.textContent = '−';
            minus.addEventListener('click', function () { changeQty(key, -1); });

            // BEXIA_V582P3_A35C_QTY_KEYBOARD_RUNTIME
            // Campo de texto numérico para evitar que el navegador lo presente
            // solamente como control de flechas y facilitar captura masiva.
            const qty = document.createElement('input');
            qty.type = 'text';
            qty.className = 'v5339-cart-qty';
            qty.dataset.bexiaQtyKeyboard = 'A35C';
            qty.dataset.cartKey = String(key);
            qty.dataset.originalQty = String(item.qty);
            qty.inputMode = 'numeric';
            qty.pattern = '[0-9]*';
            qty.autocomplete = 'off';
            qty.spellcheck = false;
            qty.readOnly = false;
            qty.disabled = false;
            qty.tabIndex = 0;
            qty.value = String(item.qty);
            qty.title = 'Escribe la cantidad y presiona Enter';
            qty.setAttribute(
                'aria-label',
                'Cantidad de ' + String(item.name || item.product_name || 'producto')
            );

            qty.style.pointerEvents = 'auto';
            qty.style.cursor = 'text';
            qty.style.userSelect = 'text';
            qty.style.webkitUserSelect = 'text';

            qty.addEventListener('click', function (event) {
                event.stopPropagation();
            });

            qty.addEventListener('focus', function () {
                qty.dataset.originalQty = String(item.qty);
                window.setTimeout(function () {
                    qty.select();
                }, 0);
            });

            qty.addEventListener('input', function () {
                const sanitized = String(qty.value || '').replace(/[^0-9]/g, '');

                if (qty.value !== sanitized) {
                    qty.value = sanitized;
                }
            });

            function applyTypedQuantity() {
                const currentQty = Number(item.qty || 0);
                const raw = String(qty.value || '').trim();
                const requestedQty = Number(raw);

                if (
                    raw === ''
                    || !Number.isFinite(requestedQty)
                    || !Number.isInteger(requestedQty)
                    || requestedQty < 1
                ) {
                    setWarning('Captura una cantidad entera mayor a cero.');
                    qty.value = String(currentQty);

                    return false;
                }

                if (requestedQty === currentQty) {
                    qty.value = String(currentQty);

                    return true;
                }

                if (
                    Number(item.stock || 0) > 0
                    && requestedQty > Number(item.stock || 0)
                ) {
                    setWarning(
                        'No hay existencia suficiente para capturar '
                        + requestedQty
                        + ' unidades.'
                    );
                    qty.value = String(currentQty);

                    return false;
                }

                changeQty(key, requestedQty - currentQty);

                return true;
            }

            qty.addEventListener('change', function () {
                applyTypedQuantity();
            });

            const plus = document.createElement('button');
            plus.type = 'button';
            plus.className = 'btn';
            plus.style.padding = '5px 9px';
            plus.textContent = '+';
            plus.addEventListener('click', function () { changeQty(key, 1); });

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn';
            remove.style.padding = '5px 9px';
            remove.textContent = 'Quitar';
            remove.addEventListener('click', function () {
                cart.delete(key);
                render();
            });

            controls.appendChild(minus);
            controls.appendChild(qty);
            controls.appendChild(plus);
            controls.appendChild(remove);

            info.appendChild(name);
            info.appendChild(ref);
            info.appendChild(price);
            info.appendChild(controls);

            const lineTotal = document.createElement('div');
            lineTotal.style.fontWeight = '950';
            lineTotal.textContent = money(item.qty * item.price);

            line.appendChild(thumb);
            line.appendChild(info);
            line.appendChild(lineTotal);

            cartItems.appendChild(line);
        });

        totals();
    }

    function changeQty(key, delta) {
        const item = cart.get(key);
        if (!item) return;

        const nextQty = Number(item.qty || 0) + delta;

        if (nextQty <= 0) {
            cart.delete(key);
            render();
            return;
        }

        if (item.stock > 0 && nextQty > item.stock) {
            setWarning('No hay existencia suficiente para agregar más de este producto.');
            return;
        }

        item.qty = nextQty;
        cart.set(key, item);
        render();
    }

    function addProduct(card) {
        const canSell = card.dataset.productCanSell === '1';

        if (!canSell || card.classList.contains('disabled')) {
            setWarning('Este producto no está disponible para venta.');
            return;
        }

        const product = {
            id: card.dataset.productId || '',
            name: card.dataset.productName || card.querySelector('.pname')?.textContent?.trim() || 'Producto',
            reference: card.dataset.productReference || '',
            price: toNumber(card.dataset.productPrice, 0),
            stock: toNumber(card.dataset.productStock, 0),
            taxRate: toNumber(card.dataset.productTaxRate, 0.16),
            type: card.dataset.productType || 'stockable',
            isService: card.dataset.productIsService === '1' || card.dataset.productType === 'service',
            imageUrl: card.dataset.productImageUrl || '',
        };

        if (!product.price || product.price < 0) {
            setWarning('Este producto no tiene precio válido.');
            return;
        }

        if (!product.isService && product.stock <= 0) {
            setWarning('Este producto no tiene existencia disponible.');
            return;
        }

        const key = lineKey(product);
        const existing = cart.get(key);

        if (existing) {
            if (!existing.isService && existing.stock > 0 && existing.qty + 1 > existing.stock) {
                setWarning('No hay existencia suficiente para agregar más de este producto.');
                return;
            }
            existing.qty += 1;
            cart.set(key, existing);
        } else {
            cart.set(key, { ...product, qty: 1 });
        }

        // BEXIA_V5829C_KEEP_PENDING_ORDER_ON_CART_CHANGE
        // Agregar o incrementar productos no convierte el carrito en una venta nueva.
        // La identidad del ticket pendiente solo se limpia al vaciar o finalizar el carrito.
        render();
    }

    document.querySelectorAll('.product').forEach(function (card) {
        card.addEventListener('click', function () {
            addProduct(card);
        });
    });

    clearBtn?.addEventListener('click', function (event) {
        event.preventDefault();

        if (cart.size === 0) return;

        if (!window.BEXIA_POS_CLEAR_CART_CONFIRMED) {
            if (typeof window.BEXIA_POS_SHOW_CLEAR_CART_CONFIRM === 'function') {
                window.BEXIA_POS_SHOW_CLEAR_CART_CONFIRM();
                return;
            }

            if (!confirm('¿Vaciar el carrito?')) return;
        }

        window.BEXIA_POS_CLEAR_CART_CONFIRMED = false;

        cart.clear();
        cartNote = '';
        cartDiscount = null;
        window.BEXIA_POS_LOADED_PENDING_ORDER = null;
        window.BEXIA_POS_SERIAL_SELECTIONS = {};
        window.BEXIA_POS_SERIAL_LOCKED_BY_PENDING = {};
        window.BEXIA_V5544D_PENDING_SERIAL_LOCKS = {};
        // BEXIA_V5545I3_CLEAR_BUTTON_SERIAL_SELECTIONS
        render();
    });

    const charge = document.querySelector('.charge');
    charge?.addEventListener('click', function (event) {
        if (cart.size === 0) {
            event.preventDefault();
            setWarning('Agrega al menos un producto antes de finalizar la venta.');
        }
    }, true);

    window.BEXIA_POS_CART_API = {
        getItems: function () {
            return Array.from(cart.values()).map(function (item) {
                const maps = window.BEXIA_POS_SERIAL_SELECTIONS || {};
                const itemId = Number(item.id || 0) || null;
                let finalProductId = Number(item.product_id || item.parent_product_id || item.id || 0) || null;
                let finalVariantId = Number(item.product_variant_id || item.variant_id || 0) || null;

                let selectedSerial = null;

                const keys = [
                    String(finalProductId || '') + ':' + String(finalVariantId || ''),
                    String(finalProductId || '') + ':',
                    String(itemId || '') + ':' + String(finalVariantId || ''),
                    String(itemId || '') + ':'
                ];

                for (const key of keys) {
                    if (maps[key]) {
                        selectedSerial = maps[key];
                        break;
                    }
                }

                if (selectedSerial) {
                    const selectedProductId = Number(selectedSerial.product_id || 0) || null;
                    const selectedVariantId = Number(selectedSerial.product_variant_id || 0) || null;

                    if (selectedProductId) {
                        finalProductId = selectedProductId;
                    }

                    if (selectedVariantId) {
                        finalVariantId = selectedVariantId;
                    }
                }

                const serialId = Number(
                    item.stock_serial_number_id
                    || item.serial_number_id
                    || (selectedSerial ? selectedSerial.stock_serial_number_id : 0)
                    || (selectedSerial ? selectedSerial.id : 0)
                    || 0
                ) || null;

                const serialNumber = String(
                    item.serial_number
                    || (selectedSerial ? selectedSerial.serial_number : '')
                    || (selectedSerial ? selectedSerial.label : '')
                    || ''
                );

                // BEXIA_V5829C_PRESERVE_PENDING_LOT_IN_CART
                const lotId = Number(item.stock_lot_id || item.lot_id || 0) || null;
                const lotNumber = String(item.lot_number || item.stock_lot_number || '');

                return {
                    product_id: finalProductId || item.id,
                    product_variant_id: finalVariantId,
                    variant_id: finalVariantId,
                    stock_serial_number_id: serialId,
                    serial_number_id: serialId,
                    serial_number: serialNumber,
                    serial_locked_from_pending: Boolean(item.serial_locked_from_pending),
                    stock_lot_id: lotId,
                    lot_id: lotId,
                    lot_number: lotNumber,
                    // BEXIA_V5829C_GETITEMS_LOT_FIELDS
                    // BEXIA_V5545I3_GETITEMS_SERIAL_FROM_GLOBAL
                    name: item.name,
                    reference: item.reference,
                    price: item.price,
                    qty: item.qty,
                    tax_rate: item.taxRate,
                    stock: item.stock,
                };
            });
        },

        getTotal: function () {
            const gross = grossTotals();
            const discountValue = discountAmount(gross.total);
            return Math.max(0, gross.total - discountValue);
        },
        getNote: function () {
            return cartNote || '';
        },
        getDiscount: function () {
            return cartDiscount || null;
        },
        setNote: function (note) {
            cartNote = String(note || '').trim();
            render();
        },
        setDiscount: function (discount) {
            cartDiscount = discount || null;
            render();
        },
        clear: function () {
            cart.clear();
            cartNote = '';
            cartDiscount = null;
            window.BEXIA_POS_LOADED_PENDING_ORDER = null;
            window.BEXIA_POS_SERIAL_SELECTIONS = {};
            window.BEXIA_POS_SERIAL_LOCKED_BY_PENDING = {};
            window.BEXIA_V5544D_PENDING_SERIAL_LOCKS = {};
            window.BEXIA_POS_PRICE_LIST_CHANGED_AFTER_PENDING_LOAD = false;
            // BEXIA_V5545N_CLEAR_PRICE_LIST_CHANGED_FLAG
            // BEXIA_V5545I3_CLEAR_SERIAL_SELECTIONS
            render();
        },
        loadPendingOrderItems: function (items) {
            cart.clear();
            window.BEXIA_POS_PRICE_LIST_CHANGED_AFTER_PENDING_LOAD = false;
            window.BEXIA_POS_SERIAL_SELECTIONS = {};
            window.BEXIA_POS_SERIAL_LOCKED_BY_PENDING = {};
            window.BEXIA_V5544D_PENDING_SERIAL_LOCKS = {};

            const loadedOrder = window.BEXIA_POS_LOADED_PENDING_ORDER || {};
            const loadedMeta = loadedOrder.metadata || {};

            cartNote = loadedOrder.order_note || loadedMeta.order_note || '';
            cartDiscount = loadedOrder.discount || loadedMeta.discount || null;

            (items || []).forEach(function (item) {
                const qty = Number(item.qty || item.quantity || 1);
                const total = Number(item.total || item.line_total || 0);
                const price = Number(item.price || item.unit_price || (qty > 0 && total > 0 ? total / qty : 0));

                const pendingProductId = Number(item.product_id || item.parent_product_id || 0) || null;
                const pendingVariantId = Number(item.product_variant_id || item.variant_id || 0) || null;
                const pendingSerialId = Number(item.stock_serial_number_id || item.serial_number_id || 0) || null;
                const pendingSerialNumber = String(item.serial_number || item.serial_label || item.line_serial_number || '');
                const pendingLotId = Number(item.stock_lot_id || item.lot_id || 0) || null;
                const pendingLotNumber = String(item.lot_number || item.stock_lot_number || '');

                const product = {
                    id: pendingVariantId || pendingProductId || item.id || '',
                    product_id: pendingProductId || item.product_id || item.id || '',
                    parent_product_id: pendingProductId || null,
                    product_variant_id: pendingVariantId,
                    variant_id: pendingVariantId,
                    stock_serial_number_id: pendingSerialId,
                    serial_number_id: pendingSerialId,
                    serial_number: pendingSerialNumber,
                    serial_locked_from_pending: Boolean(pendingSerialId),
                    stock_lot_id: pendingLotId,
                    lot_id: pendingLotId,
                    lot_number: pendingLotNumber,
                    lot_locked_from_pending: Boolean(pendingLotId),
                    // BEXIA_V5829C_LOAD_PENDING_LOT_FIELDS
                    pending_price_locked_until_price_list_change: true,
                    original_pending_price: Number.isFinite(price) ? price : 0,
                    original_pending_tax_rate: Number(item.tax_rate || 0.16),
                    // BEXIA_V5545N_PENDING_PRICE_LOCK_UNTIL_MANUAL_PRICE_LIST
                    // BEXIA_V5545M_PENDING_ONLY_LOCK_SERIAL
                    // BEXIA_V5545I3_LOAD_PENDING_SERIAL_FIELDS
                    name: item.name || item.product_name || 'Producto',
                    reference: item.reference || item.product_reference || '',
                    price: Number.isFinite(price) ? price : 0,
                    stock: Number(item.stock || item.available_quantity || item.stock_quantity || 999999),
                    taxRate: Number(item.tax_rate || 0.16),
                    qty: Number.isFinite(qty) && qty > 0 ? qty : 1,
                };

                if (pendingSerialId) {
                    const serialPayload = {
                        id: pendingSerialId,
                        stock_serial_number_id: pendingSerialId,
                        product_id: pendingProductId,
                        product_variant_id: pendingVariantId,
                        serial_number: pendingSerialNumber,
                        label: pendingSerialNumber || ('Serie #' + pendingSerialId),
                        status: 'pending_selected'
                    };

                    const keys = [
                        String(pendingProductId || '') + ':' + String(pendingVariantId || ''),
                        String(pendingProductId || '') + ':',
                        String(product.id || '') + ':' + String(pendingVariantId || ''),
                        String(product.id || '') + ':'
                    ];

                    keys.forEach(function (key) {
                        window.BEXIA_POS_SERIAL_SELECTIONS[key] = serialPayload;
                        window.BEXIA_POS_SERIAL_LOCKED_BY_PENDING[key] = serialPayload;
                        window.BEXIA_V5544D_PENDING_SERIAL_LOCKS[key] = serialPayload;
                    });
                }

                const key = lineKey(product);
                cart.set(key, product);
            });

            render();

            const v5481fBottomChargeTotalEl = document.getElementById('v5349-charge-total');
            if (v5481fBottomChargeTotalEl && typeof money === 'function') {
                v5481fBottomChargeTotalEl.textContent = money(this.getTotal());
            }
        },

        refreshProductData: function (products) {
            const rows = Array.isArray(products) ? products : [];
            const byId = new Map();

            rows.forEach(function (row) {
                byId.set(Number(row.id), row);
            });

            let changed = 0;

            cart.forEach(function (item, key) {
                const updated = byId.get(Number(item.id || item.product_variant_id || item.product_id));

                if (!updated) {
                    return;
                }

                const oldPrice = Number(item.price || 0);
                const oldUnitPrice = Number(item.unit_price ?? item.price ?? 0);
                const oldTaxRate = Number(item.taxRate ?? 0.16);
                const oldStock = Number(item.stock ?? 0);

                const newPrice = Number(updated.price || updated.public_price || item.price || 0);
                const newStock = Number(updated.available_quantity ?? updated.stock_quantity ?? item.stock ?? 0);
                const rawTax = Number(updated.sale_tax_rate ?? item.taxRate ?? 0.16);
                const taxRate = rawTax > 1 ? rawTax / 100 : rawTax;

                const pendingPriceLocked = Boolean(item.pending_price_locked_until_price_list_change)
                    && !Boolean(window.BEXIA_POS_PRICE_LIST_CHANGED_AFTER_PENDING_LOAD);

                // BEXIA_V5545N_PENDING_PRICE_ONLY_CHANGES_AFTER_MANUAL_PRICE_LIST
                // Pendiente cargado:
                // - conserva precio/impuesto original durante refrescos automáticos.
                // - si el usuario cambia lista de precios manualmente, se comporta como carrito normal.
                if (pendingPriceLocked) {
                    item.price = Number(item.original_pending_price ?? item.price ?? 0);
                    item.unit_price = Number(item.original_pending_price ?? item.unit_price ?? item.price ?? 0);
                    item.taxRate = Number(item.original_pending_tax_rate ?? item.taxRate ?? 0.16);
                    item.stock = newStock;

                    const visibleChanged = (
                        Number(item.price || 0) !== oldPrice
                        || Number(item.unit_price ?? item.price ?? 0) !== oldUnitPrice
                        || Number(item.taxRate ?? 0.16) !== oldTaxRate
                    );

                    cart.set(key, item);

                    if (visibleChanged) {
                        changed++;
                    }

                    return;
                }

                item.pending_price_locked_until_price_list_change = false;
                item.price = newPrice;
                item.unit_price = newPrice;
                item.stock = newStock;
                item.taxRate = taxRate;

                const visibleChanged = (
                    Number(item.price || 0) !== oldPrice
                    || Number(item.unit_price ?? item.price ?? 0) !== oldUnitPrice
                    || Number(item.taxRate ?? 0.16) !== oldTaxRate
                    || Number(item.stock ?? 0) !== oldStock
                );

                if (visibleChanged) {
                    cart.set(key, item);
                    changed++;
                }
            });

            if (changed > 0) {
                render();
            }

            return changed;
        },

        size: function () {
            return cart.size;
        },
        setWarning: setWarning,
        money: money,
    };

    



    // V5.42.8 - Nota solo con modal.
    function v5428NoteModal() {
        return document.getElementById('v5422-note-discount-modal');
    }

    function v5428OpenNoteModal() {
        const modal = v5428NoteModal();
        const title = document.getElementById('v5422-note-discount-title');
        const notePanel = document.getElementById('v5422-note-panel');
        const discountPanel = document.getElementById('v5422-discount-panel');
        const noteValue = document.getElementById('v5422-note-value');

        if (!modal || !noteValue) {
            return;
        }

        const api = window.BEXIA_POS_CART_API || null;

        modal.dataset.mode = 'note';

        if (title) {
            title.textContent = 'Nota del ticket';
        }

        if (notePanel) {
            notePanel.style.display = '';
        }

        if (discountPanel) {
            discountPanel.style.display = 'none';
        }

        noteValue.value = api && typeof api.getNote === 'function'
            ? (api.getNote() || '')
            : '';

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');

        window.setTimeout(function () {
            noteValue.focus();
        }, 80);
    }

    function v5428CloseNoteModal() {
        const modal = v5428NoteModal();

        if (!modal || modal.dataset.mode !== 'note') {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    function v5428SaveNoteModal() {
        const modal = v5428NoteModal();

        if (!modal || modal.dataset.mode !== 'note') {
            return;
        }

        const api = window.BEXIA_POS_CART_API || null;
        const noteValue = document.getElementById('v5422-note-value');

        if (api && typeof api.setNote === 'function') {
            api.setNote(noteValue ? noteValue.value : '');
        }

        v5428CloseNoteModal();
    }

    function v5428ClearNoteModal() {
        const modal = v5428NoteModal();

        if (!modal || modal.dataset.mode !== 'note') {
            return;
        }

        const api = window.BEXIA_POS_CART_API || null;

        if (api && typeof api.setNote === 'function') {
            api.setNote('');
        }

        v5428CloseNoteModal();
    }

    document.getElementById('v5422-note-discount-close')?.addEventListener('click', v5428CloseNoteModal);
    document.getElementById('v5422-note-discount-cancel')?.addEventListener('click', v5428CloseNoteModal);
    document.getElementById('v5422-note-discount-save')?.addEventListener('click', v5428SaveNoteModal);
    document.getElementById('v5422-note-discount-clear')?.addEventListener('click', v5428ClearNoteModal);

    v5428NoteModal()?.addEventListener('click', function (event) {
        const modal = v5428NoteModal();

        if (event.target === modal && modal.dataset.mode === 'note') {
            v5428CloseNoteModal();
        }
    });

    window.BEXIA_POS_OPEN_NOTE = function (event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        const api = window.BEXIA_POS_CART_API || null;

        if (!api || typeof api.setNote !== 'function') {
            return;
        }

        v5428OpenNoteModal();
    };


    



    // V5.43.7 - Descuento visual solo con modal.
    function v5437DiscountModal() {
        return document.getElementById('v5422-note-discount-modal');
    }

    function v5437OpenDiscountModal() {
        const modal = v5437DiscountModal();
        const title = document.getElementById('v5422-note-discount-title');
        const notePanel = document.getElementById('v5422-note-panel');
        const discountPanel = document.getElementById('v5422-discount-panel');
        const discountType = document.getElementById('v5422-discount-type');
        const discountValue = document.getElementById('v5422-discount-value');

        if (!modal || !discountType || !discountValue) {
            return;
        }

        const api = window.BEXIA_POS_CART_API || null;
        const currentDiscount = api && typeof api.getDiscount === 'function'
            ? api.getDiscount()
            : null;

        modal.dataset.mode = 'discount';

        if (title) {
            title.textContent = 'Descuento del ticket';
        }

        if (notePanel) {
            notePanel.style.display = 'none';
        }

        if (discountPanel) {
            discountPanel.style.display = '';
        }

        discountType.value = currentDiscount && currentDiscount.type
            ? currentDiscount.type
            : 'percent';

        discountValue.value = currentDiscount && currentDiscount.value
            ? currentDiscount.value
            : '';

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');

        window.setTimeout(function () {
            discountValue.focus();
        }, 80);
    }

    function v5437CloseDiscountModal() {
        const modal = v5437DiscountModal();

        if (!modal || modal.dataset.mode !== 'discount') {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    function v5437SaveDiscountModal() {
        const modal = v5437DiscountModal();

        if (!modal || modal.dataset.mode !== 'discount') {
            return;
        }

        const api = window.BEXIA_POS_CART_API || null;
        const discountType = document.getElementById('v5422-discount-type');
        const discountValue = document.getElementById('v5422-discount-value');

        if (!api || typeof api.setDiscount !== 'function') {
            return;
        }

        const type = discountType ? discountType.value : 'amount';
        const value = Number(discountValue ? discountValue.value : 0);

        if (!Number.isFinite(value) || value <= 0) {
            setWarning('El descuento no es válido.');
            return;
        }

        if (type === 'percent' && value > 100) {
            setWarning('El porcentaje de descuento no puede ser mayor a 100%.');
            return;
        }

        api.setDiscount({
            type: type === 'percent' ? 'percent' : 'amount',
            value: value,
            user_id: @json(auth()->id()),
            user_name: @json(auth()->user()->name ?? auth()->user()->email ?? 'Usuario'),
            applied_at: new Date().toISOString()
        });

        v5437CloseDiscountModal();
    }

    function v5437ClearDiscountModal() {
        const modal = v5437DiscountModal();

        if (!modal || modal.dataset.mode !== 'discount') {
            return;
        }

        const api = window.BEXIA_POS_CART_API || null;

        if (api && typeof api.setDiscount === 'function') {
            api.setDiscount(null);
        }

        v5437CloseDiscountModal();
    }

    document.getElementById('v5422-note-discount-close')?.addEventListener('click', function () {
        const modal = v5437DiscountModal();

        if (modal && modal.dataset.mode === 'discount') {
            v5437CloseDiscountModal();
        }
    });

    document.getElementById('v5422-note-discount-cancel')?.addEventListener('click', function () {
        const modal = v5437DiscountModal();

        if (modal && modal.dataset.mode === 'discount') {
            v5437CloseDiscountModal();
        }
    });

    document.getElementById('v5422-note-discount-save')?.addEventListener('click', function () {
        const modal = v5437DiscountModal();

        if (modal && modal.dataset.mode === 'discount') {
            v5437SaveDiscountModal();
        }
    });

    document.getElementById('v5422-note-discount-clear')?.addEventListener('click', function () {
        const modal = v5437DiscountModal();

        if (modal && modal.dataset.mode === 'discount') {
            v5437ClearDiscountModal();
        }
    });

    v5437DiscountModal()?.addEventListener('click', function (event) {
        const modal = v5437DiscountModal();

        if (event.target === modal && modal.dataset.mode === 'discount') {
            v5437CloseDiscountModal();
        }
    });

    window.BEXIA_POS_OPEN_DISCOUNT = function (event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        const button = document.getElementById('v5419-cart-discount');

        if (!button || button.dataset.canDiscount !== '1') {
            setWarning('Este usuario no tiene permiso para aplicar descuento.');
            return;
        }

        v5437OpenDiscountModal();
    };



    // V5.43.8 - Fix robusto guardar descuento visual
    function v5438OpenDiscountModal() {
        const modal = document.getElementById('v5422-note-discount-modal');
        const title = document.getElementById('v5422-note-discount-title');
        const notePanel = document.getElementById('v5422-note-panel');
        const discountPanel = document.getElementById('v5422-discount-panel');
        const discountType = document.getElementById('v5422-discount-type');
        const discountValue = document.getElementById('v5422-discount-value');

        if (!modal || !discountType || !discountValue) {
            setWarning('No se encontró el modal de descuento.');
            return;
        }

        const currentDiscount = window.BEXIA_POS_CART_API && typeof window.BEXIA_POS_CART_API.getDiscount === 'function'
            ? window.BEXIA_POS_CART_API.getDiscount()
            : null;

        modal.dataset.mode = 'discount';

        if (title) {
            title.textContent = 'Descuento del ticket';
        }

        if (notePanel) {
            notePanel.style.display = 'none';
        }

        if (discountPanel) {
            discountPanel.style.display = '';
        }

        discountType.value = currentDiscount && currentDiscount.type
            ? currentDiscount.type
            : 'percent';

        discountValue.value = currentDiscount && currentDiscount.value
            ? currentDiscount.value
            : '';

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');

        window.setTimeout(function () {
            discountValue.focus();
        }, 80);
    }

    function v5438CloseModalIfDiscount() {
        const modal = document.getElementById('v5422-note-discount-modal');

        if (!modal || modal.dataset.mode !== 'discount') {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    function v5438ApplyDiscountFromModal() {
        const modal = document.getElementById('v5422-note-discount-modal');

        if (!modal || modal.dataset.mode !== 'discount') {
            return false;
        }

        const api = window.BEXIA_POS_CART_API || null;
        const discountType = document.getElementById('v5422-discount-type');
        const discountValue = document.getElementById('v5422-discount-value');

        if (!api || typeof api.setDiscount !== 'function') {
            setWarning('No se encontró la función para aplicar descuento.');
            return true;
        }

        const type = discountType && discountType.value === 'percent' ? 'percent' : 'amount';
        const rawValue = discountValue ? String(discountValue.value || '').trim() : '';
        const value = Number(rawValue);

        if (!Number.isFinite(value) || value <= 0) {
            setWarning('Escribe un descuento válido.');
            return true;
        }

        if (type === 'percent' && value > 100) {
            setWarning('El porcentaje de descuento no puede ser mayor a 100%.');
            return true;
        }

        api.setDiscount({
            type: type,
            value: value,
            user_id: @json(auth()->id()),
            user_name: @json(auth()->user()->name ?? auth()->user()->email ?? 'Usuario'),
            applied_at: new Date().toISOString()
        });

        v5438CloseModalIfDiscount();

        return true;
    }

    function v5438ClearDiscountFromModal() {
        const modal = document.getElementById('v5422-note-discount-modal');

        if (!modal || modal.dataset.mode !== 'discount') {
            return false;
        }

        const api = window.BEXIA_POS_CART_API || null;

        if (api && typeof api.setDiscount === 'function') {
            api.setDiscount(null);
        }

        v5438CloseModalIfDiscount();

        return true;
    }

    window.BEXIA_POS_OPEN_DISCOUNT = function (event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        const button = document.getElementById('v5419-cart-discount');

        if (!button || button.dataset.canDiscount !== '1') {
            setWarning('Este usuario no tiene permiso para aplicar descuento.');
            return;
        }

        v5438OpenDiscountModal();
    };

    document.getElementById('v5422-note-discount-save')?.addEventListener('click', function (event) {
        const handled = v5438ApplyDiscountFromModal();

        if (handled) {
            event.preventDefault();
            event.stopPropagation();

            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }
        }
    }, true);

    document.getElementById('v5422-note-discount-clear')?.addEventListener('click', function (event) {
        const handled = v5438ClearDiscountFromModal();

        if (handled) {
            event.preventDefault();
            event.stopPropagation();

            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }
        }
    }, true);

    document.getElementById('v5422-note-discount-cancel')?.addEventListener('click', function (event) {
        const modal = document.getElementById('v5422-note-discount-modal');

        if (modal && modal.dataset.mode === 'discount') {
            event.preventDefault();
            event.stopPropagation();

            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }

            v5438CloseModalIfDiscount();
        }
    }, true);

    document.getElementById('v5422-note-discount-close')?.addEventListener('click', function (event) {
        const modal = document.getElementById('v5422-note-discount-modal');

        if (modal && modal.dataset.mode === 'discount') {
            event.preventDefault();
            event.stopPropagation();

            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }

            v5438CloseModalIfDiscount();
        }
    }, true);
    // END V5.43.8



    // V5.43.9 - Adjuntar nota/descuento al crear ticket pendiente sin tocar el handler original.
    if (!window.BEXIA_POS_FETCH_PATCHED_FOR_NOTE_DISCOUNT) {
        window.BEXIA_POS_FETCH_PATCHED_FOR_NOTE_DISCOUNT = true;

        const v5439OriginalFetch = window.fetch.bind(window);

        window.fetch = function (input, init) {
            try {
                const url = typeof input === 'string'
                    ? input
                    : (input && input.url ? input.url : '');

                const method = init && init.method
                    ? String(init.method).toUpperCase()
                    : 'GET';

                if (
                    method === 'POST' &&
                    /\/pos\/sessions\/\d+\/orders/.test(url) &&
                    init &&
                    init.body
                ) {
                    const api = window.BEXIA_POS_CART_API || null;
                    const body = JSON.parse(init.body);

                    if (api && typeof api.getNote === 'function' && typeof body.order_note === 'undefined') {
                        body.order_note = api.getNote() || '';
                    }

                    if (api && typeof api.getDiscount === 'function') {
                        const discount = api.getDiscount();

                        if (discount) {
                            body.discount = discount;
                        }
                    }

                    init = {
                        ...init,
                        body: JSON.stringify(body),
                    };
                }
            } catch (error) {
                // Si algo falla aquí, dejamos que el fetch original continúe.
            }

            return v5439OriginalFetch(input, init);
        };
    }


    render();
});
</script>





@php
    // V5.50.4A - permisos de tickets pendientes en PDV.
    $v5504aUser = auth()->user();

    $v5504aCanCreatePendingTicket =
        $v5504aUser
        && (
            (method_exists($v5504aUser, 'isSystemAdmin') && $v5504aUser->isSystemAdmin())
            || (method_exists($v5504aUser, 'isGroupAdmin') && $v5504aUser->isGroupAdmin())
            || $v5504aUser->can('pos.pending_tickets.create')
        );

    $v5504aCanViewPendingTickets =
        $v5504aUser
        && (
            (method_exists($v5504aUser, 'isSystemAdmin') && $v5504aUser->isSystemAdmin())
            || (method_exists($v5504aUser, 'isGroupAdmin') && $v5504aUser->isGroupAdmin())
            || $v5504aUser->can('pos.pending_tickets.view')
        );

    $v5504aCanLoadPendingTickets =
        $v5504aUser
        && (
            (method_exists($v5504aUser, 'isSystemAdmin') && $v5504aUser->isSystemAdmin())
            || (method_exists($v5504aUser, 'isGroupAdmin') && $v5504aUser->isGroupAdmin())
            || $v5504aUser->can('pos.pending_tickets.load')
        );

    $v5504aCanPrintPendingTickets =
        $v5504aUser
        && (
            (method_exists($v5504aUser, 'isSystemAdmin') && $v5504aUser->isSystemAdmin())
            || (method_exists($v5504aUser, 'isGroupAdmin') && $v5504aUser->isGroupAdmin())
            || $v5504aUser->can('pos.pending_tickets.print')
        );

    $v5505aCanCancelPendingTickets =
        $v5504aUser
        && (
            (method_exists($v5504aUser, 'isSystemAdmin') && $v5504aUser->isSystemAdmin())
            || (method_exists($v5504aUser, 'isGroupAdmin') && $v5504aUser->isGroupAdmin())
            || $v5504aUser->can('pos.pending_tickets.cancel')
        );
@endphp

<style id="v5350-pending-tickets-style">
    .v5350-pending-btn {
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0f172a;
        border-radius: 10px;
        padding: 7px 11px;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
        margin-left: 8px;
    }

    .v5350-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .45);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        padding: 24px;
    }

    .v5350-backdrop.is-open {
        display: flex;
    }

    .v5350-modal {
        width: min(860px, 96vw);
        max-height: 90vh;
        overflow: auto;
        background: #fff;
        border-radius: 22px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 24px 80px rgba(15, 23, 42, .35);
    }

    .v5350-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 22px;
        border-bottom: 1px solid #e2e8f0;
    }

    .v5350-header h2 {
        margin: 0;
        font-size: 22px;
        font-weight: 950;
        color: #0f172a;
    }

    .v5350-close {
        border: 1px solid #cbd5e1;
        background: #fff;
        border-radius: 12px;
        padding: 8px 12px;
        font-weight: 900;
        cursor: pointer;
    }

    .v5350-body {
        padding: 18px 22px 22px;
        display: grid;
        grid-template-columns: 1fr 1.15fr;
        gap: 16px;
    }

    .v5350-list,
    .v5350-detail {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        background: #fff;
    }

    .v5350-section-title {
        padding: 12px 14px;
        font-size: 13px;
        font-weight: 950;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .v5350-orders {
        max-height: 440px;
        overflow: auto;
    }

    .v5350-order {
        padding: 12px 14px;
        border-bottom: 1px solid #eef2f7;
        cursor: pointer;
    }

    .v5350-order:hover,
    .v5350-order.is-active {
        background: #eff6ff;
    }

    .v5350-order strong {
        display: block;
        font-size: 13px;
    }

    .v5350-order span {
        display: block;
        font-size: 12px;
        color: #64748b;
        margin-top: 3px;
    }

    .v5350-detail-body {
        padding: 14px;
        max-height: 440px;
        overflow: auto;
    }

    .v5350-line {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 10px;
        padding: 9px 0;
        border-bottom: 1px solid #eef2f7;
        font-size: 13px;
    }

    .v5350-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 14px;
        padding: 14px;
        border-radius: 14px;
        background: #eff6ff;
        color: #1d4ed8;
        font-weight: 950;
    }

    .v5350-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 14px;
        border-top: 1px solid #e2e8f0;
    }

    .v5350-primary {
        border: 0;
        background: #2563eb;
        color: #fff;
        border-radius: 12px;
        padding: 11px 15px;
        font-weight: 950;
        cursor: pointer;
    }
.v5360-ticket-actions {
        display: flex;
        gap: 7px;
        align-items: center;
        justify-content: flex-end;
        margin-top: 8px;
    }

    .v5360-ticket-actions button {
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        background: #fff;
        padding: 7px 9px;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
    }

    .v5360-ticket-actions .primary {
        border-color: #2563eb;
        background: #2563eb;
        color: #fff;
    }

    .v5350-primary:disabled {
        opacity: .5;
        cursor: not-allowed;
    }
</style>







<style id="v5384-pending-qr-search-style">
    .v5383-pending-qr-search {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
        max-width: 520px;
        margin: 0 14px;
    }

    .v5383-pending-qr-search input {
        width: 100%;
        height: 38px;
        border: 1px solid #dbe3ef;
        border-radius: 999px;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 850;
        outline: none;
        background: #ffffff;
        color: #0f172a;
    }

    .v5383-pending-qr-search input::placeholder {
        color: #94a3b8;
        font-weight: 800;
    }

    .v5383-pending-qr-search input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
    }

    #v5384-pending-qr-search-button {
        height: 38px;
        border: 0;
        border-radius: 999px;
        padding: 0 16px;
        background: #2563eb;
        color: #ffffff;
        font-size: 13px;
        font-weight: 900;
        cursor: pointer;
        white-space: nowrap;
    }

    #v5384-pending-qr-search-button:disabled {
        opacity: .65;
        cursor: wait;
    }


@php
    // V5.51.4C - Permisos visuales cierre caja / cancelar pendientes.
    $v5514cUser = auth()->user();

    $v5514cCanCloseSession = false;
    $v5514cCanCancelPendingTickets = false;

    try {
        if ($v5514cUser) {
            $v5514cIsElevated =
                (method_exists($v5514cUser, 'isSystemAdmin') && $v5514cUser->isSystemAdmin())
                || (method_exists($v5514cUser, 'isGroupAdmin') && $v5514cUser->isGroupAdmin());

            $v5514cCanCloseSession =
                $v5514cIsElevated
                || (method_exists($v5514cUser, 'can') && (
                    $v5514cUser->can('pos.session.close')
                    || $v5514cUser->can('pos.sessions.close')
                    || $v5514cUser->can('pos.close_session')
                    || $v5514cUser->can('pos.session_close')
                ));

            $v5514cCanCancelPendingTickets =
                $v5514cIsElevated
                || (method_exists($v5514cUser, 'can') && (
                    $v5514cUser->can('pos.ticket.cancel_pending')
                    || $v5514cUser->can('pos.pending_tickets.cancel')
                ));
        }
    } catch (\Throwable $e) {
        $v5514cCanCloseSession = false;
        $v5514cCanCancelPendingTickets = false;
    }
@endphp


@php
    // V5.51.4C - Sobrescribir permiso visual de cancelación pendiente con permiso nuevo.
    $v5505aCanCancelPendingTickets = (bool) ($v5514cCanCancelPendingTickets ?? $v5505aCanCancelPendingTickets ?? false);
@endphp

<style id="v5505d-cancel-pending-style">
    .v5505d-cancel-backdrop {
        position: fixed;
        inset: 0;
        z-index: 13000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 22px;
        background: rgba(15, 23, 42, .50);
    }

    .v5505d-cancel-backdrop.is-open {
        display: flex;
    }

    .v5505d-cancel-modal {
        width: min(520px, 94vw);
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 22px;
        box-shadow: 0 24px 80px rgba(15, 23, 42, .35);
        overflow: hidden;
    }

    .v5505d-cancel-header {
        padding: 20px 22px 12px;
    }

    .v5505d-cancel-title {
        margin: 0;
        font-size: 20px;
        line-height: 1.15;
        font-weight: 950;
        color: #0f172a;
    }

    .v5505d-cancel-subtitle {
        margin-top: 6px;
        color: #64748b;
        font-size: 13px;
        font-weight: 700;
        line-height: 1.4;
    }

    .v5505d-cancel-body {
        padding: 0 22px 18px;
    }

    .v5505d-cancel-ticket {
        border: 1px solid #fee2e2;
        background: #fef2f2;
        color: #991b1b;
        border-radius: 14px;
        padding: 10px 12px;
        font-size: 13px;
        font-weight: 900;
        margin-bottom: 12px;
    }

    .v5505d-cancel-label {
        display: block;
        font-size: 13px;
        font-weight: 900;
        color: #334155;
        margin-bottom: 7px;
    }

    .v5505d-cancel-textarea {
        width: 100%;
        min-height: 96px;
        resize: vertical;
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        padding: 12px 13px;
        font-size: 14px;
        font-weight: 700;
        color: #0f172a;
        outline: none;
    }

    .v5505d-cancel-textarea:focus {
        border-color: #dc2626;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, .10);
    }

    .v5505d-cancel-error {
        display: none;
        margin-top: 8px;
        color: #b91c1c;
        font-size: 12px;
        font-weight: 900;
    }

    .v5505d-cancel-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 16px 22px 20px;
        border-top: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .v5505d-cancel-secondary,
    .v5505d-cancel-danger {
        min-height: 42px;
        border-radius: 14px;
        padding: 0 16px;
        font-weight: 950;
        cursor: pointer;
    }

    .v5505d-cancel-secondary {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #0f172a;
    }

    .v5505d-cancel-danger {
        border: 1px solid #dc2626;
        background: #dc2626;
        color: #ffffff;
    }

    .v5505d-cancel-danger:disabled {
        opacity: .65;
        cursor: wait;
    }
</style>


<style id="v5505e-cancel-overlay-fix-style">
    /* V5.50.5E - overlay correction */
    #v5505d-cancel-pending-modal.v5505d-cancel-backdrop {
        position: fixed !important;
        inset: 0 !important;
        left: 0 !important;
        top: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 2147483000 !important;
        display: none !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 22px !important;
        background: rgba(15, 23, 42, .55) !important;
    }

    #v5505d-cancel-pending-modal.v5505d-cancel-backdrop.is-open {
        display: flex !important;
    }

    #v5505d-cancel-pending-modal .v5505d-cancel-modal {
        position: relative !important;
        margin: 0 auto !important;
        transform: none !important;
        width: min(520px, 94vw) !important;
        max-height: calc(100vh - 44px) !important;
        overflow: auto !important;
    }
</style>

</style>

<div id="v5350-pending-tickets-modal" class="v5350-backdrop" aria-hidden="true">
    <div class="v5350-modal">
        <div class="v5350-header">
            <h2>Tickets pendientes</h2>
            <div class="v5383-pending-qr-search" id="v5383-pending-qr-search">
                <input id="v5383-pending-qr-input" type="text" placeholder="Escanea el QR o escribe el número de ticket" autocomplete="off">
                <button type="button" id="v5384-pending-qr-search-button" onclick="window.BEXIA_POS_SEARCH_PENDING_TICKET && window.BEXIA_POS_SEARCH_PENDING_TICKET()">Buscar</button>
            </div>

            <button type="button" class="v5350-close" id="v5350-close-pending">Cerrar</button>
        </div>

        <div class="v5350-body">
            <div class="v5350-list">
<div class="v5350-section-title">Pendientes de cobro</div>
                <div id="v5350-orders" class="v5350-orders">
                    <div style="padding:14px; color:#64748b;">Cargando...</div>
                </div>
            </div>

            <div class="v5350-detail">
                <div class="v5350-section-title">Detalle del ticket</div>
                <div id="v5350-detail-body" class="v5350-detail-body">
                    <div style="color:#64748b;">Selecciona un ticket pendiente.</div>
                </div>
                <div class="v5350-actions">
                    @if($v5504aCanLoadPendingTickets ?? false)
                    <button type="button" class="v5350-primary" id="v5350-load-ticket" disabled>
                        Cargar al carrito
                    </button>
                    @else
                    <button type="button" class="v5350-primary" disabled title="No tienes permiso para cargar tickets pendientes">
                        Sin permiso para cargar
                    </button>
                    @endif

                    @if($v5505aCanCancelPendingTickets ?? false)
                    <button type="button" class="v5350-primary" id="v5505a-cancel-ticket" disabled style="background:#dc2626;border-color:#dc2626;margin-top:8px;">
                        Cancelar ticket pendiente
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>


<style id="v5389-clear-cart-modal-style">
    .v5389-clear-cart-backdrop {
        position: fixed;
        inset: 0;
        z-index: 10050;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 22px;
        background: rgba(15, 23, 42, .45);
    }

    .v5389-clear-cart-backdrop.is-open {
        display: flex;
    }

    .v5389-clear-cart-modal {
        width: min(420px, 94vw);
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 22px;
        box-shadow: 0 24px 80px rgba(15, 23, 42, .35);
        overflow: hidden;
    }

    .v5389-clear-cart-header {
        padding: 20px 22px 12px 22px;
    }

    .v5389-clear-cart-title {
        margin: 0;
        font-size: 20px;
        line-height: 1.15;
        font-weight: 950;
        color: #0f172a;
    }

    .v5389-clear-cart-body {
        padding: 0 22px 20px 22px;
        color: #64748b;
        font-size: 14px;
        line-height: 1.45;
        font-weight: 700;
    }

    .v5389-clear-cart-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 16px 22px 20px 22px;
        border-top: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .v5389-clear-cart-cancel,
    .v5389-clear-cart-confirm {
        height: 42px;
        border-radius: 14px;
        padding: 0 16px;
        font-weight: 900;
        cursor: pointer;
    }

    .v5389-clear-cart-cancel {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #0f172a;
    }

    .v5389-clear-cart-confirm {
        border: 1px solid #dc2626;
        background: #dc2626;
        color: #ffffff;
    }
</style>

<div id="v5389-clear-cart-modal" class="v5389-clear-cart-backdrop" aria-hidden="true">
    <div class="v5389-clear-cart-modal" role="dialog" aria-modal="true" aria-labelledby="v5389-clear-cart-title">
        <div class="v5389-clear-cart-header">
            <h2 id="v5389-clear-cart-title" class="v5389-clear-cart-title">Vaciar carrito</h2>
        </div>

        <div class="v5389-clear-cart-body">
            Se eliminarán todos los productos agregados al carrito actual. Esta acción no elimina tickets pendientes ya creados.
        </div>

        <div class="v5389-clear-cart-actions">
            <button type="button" class="v5389-clear-cart-cancel" id="v5389-clear-cart-cancel">Cancelar</button>
            <button type="button" class="v5389-clear-cart-confirm" id="v5389-clear-cart-confirm">Sí, vaciar</button>
        </div>
    </div>
</div>

<script id="v5504a-pending-ticket-permissions">
window.BEXIA_POS_PENDING_TICKET_PERMISSIONS = {
    create: @json((bool) ($v5504aCanCreatePendingTicket ?? false)),
    view: @json((bool) ($v5504aCanViewPendingTickets ?? false)),
    load: @json((bool) ($v5504aCanLoadPendingTickets ?? false)),
    print: @json((bool) ($v5504aCanPrintPendingTickets ?? false)),
    cancel: @json((bool) ($v5505aCanCancelPendingTickets ?? false))
};
</script>


<div id="v5505d-cancel-pending-modal" class="v5505d-cancel-backdrop" aria-hidden="true">
    <div class="v5505d-cancel-modal" role="dialog" aria-modal="true" aria-labelledby="v5505d-cancel-title">
        <div class="v5505d-cancel-header">
            <h2 id="v5505d-cancel-title" class="v5505d-cancel-title">Cancelar ticket pendiente</h2>
            <div class="v5505d-cancel-subtitle">
                Esta acción marcará el ticket como cancelado. No genera devolución porque el ticket aún no fue cobrado.
            </div>
        </div>

        <div class="v5505d-cancel-body">
            <div id="v5505d-cancel-ticket" class="v5505d-cancel-ticket">
                Ticket pendiente
            </div>

            <label for="v5505d-cancel-reason" class="v5505d-cancel-label">
                Motivo de cancelación
            </label>

            <textarea
                id="v5505d-cancel-reason"
                class="v5505d-cancel-textarea"
                placeholder="Ej. Cliente no regresó, error de captura, ticket duplicado..."
            ></textarea>

            <div id="v5505d-cancel-error" class="v5505d-cancel-error">
                El motivo es obligatorio.
            </div>
        </div>

        <div class="v5505d-cancel-actions">
            <button type="button" id="v5505d-cancel-close" class="v5505d-cancel-secondary">
                Regresar
            </button>
            <button type="button" id="v5505d-cancel-confirm" class="v5505d-cancel-danger">
                Sí, cancelar ticket
            </button>
        </div>
    </div>
</div>

<script id="v5350-pending-tickets-script">
document.addEventListener('DOMContentLoaded', function () {
    function money(value) {
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(Number(value || 0));
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function sessionId() {
        const match = window.location.pathname.match(/\/pos\/sessions\/(\d+)\/screen/);
        return match ? match[1] : null;
    }

    function showPosNotice(message, type = 'info') {
        const box = document.getElementById('v5356-pos-notice');

        if (!box) {
            alert(message);
            return;
        }

        box.textContent = message;
        box.className = 'v5356-pos-notice';

        if (type === 'warning') {
            box.classList.add('is-warning');
        }

        if (type === 'error') {
            box.classList.add('is-error');
        }

        box.style.display = 'block';

        window.clearTimeout(window.v5350NoticeTimer);
        window.v5350NoticeTimer = window.setTimeout(function () {
            box.style.display = 'none';
        }, 3500);
    }

    async function fetchJson(url) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json().catch(function () {
            return {};
        });

        if (!response.ok || data.ok === false) {
            throw new Error(data.message || 'No se pudo completar la operación.');
        }

        return data;
    }

    function v5505aCsrfToken() {
        return window.csrfToken
            || window.csrf
            || (window.Laravel && window.Laravel.csrfToken)
            || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value
            || '{{ csrf_token() }}';
    }

    async function v5505aPostJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': v5505aCsrfToken()
            },
            body: JSON.stringify(payload || {})
        });

        const data = await response.json().catch(function () {
            return {};
        });

        if (!response.ok || data.ok === false) {
            throw new Error(data.message || 'No se pudo completar la operación.');
        }

        return data;
    }

    function v5505dOpenCancelModal() {
        if (!selectedOrder || !selectedOrder.id) {
            showPosNotice('Selecciona un ticket pendiente antes de cancelarlo.', 'warning');
            return;
        }

        if (!v5504aPerms.cancel) {
            showPosNotice('No tienes permiso para cancelar tickets pendientes.', 'warning');
            return;
        }

        if (v5505dCancelTicket) {
            v5505dCancelTicket.textContent = 'Ticket: ' + (selectedOrder.number || ('#' + selectedOrder.id));
        }

        if (v5505dCancelReason) {
            v5505dCancelReason.value = '';
        }

        if (v5505dCancelError) {
            v5505dCancelError.style.display = 'none';
        }

        if (v5505dCancelConfirm) {
            v5505dCancelConfirm.disabled = false;
            v5505dCancelConfirm.textContent = 'Sí, cancelar ticket';
        }

        if (v5505dCancelModal) {
            v5505eMoveCancelModalToBody();
            v5505dCancelModal.style.position = 'fixed';
            v5505dCancelModal.style.inset = '0';
            v5505dCancelModal.style.width = '100vw';
            v5505dCancelModal.style.height = '100vh';
            v5505dCancelModal.style.zIndex = '2147483000';
            v5505dCancelModal.style.alignItems = 'center';
            v5505dCancelModal.style.justifyContent = 'center';
            v5505dCancelModal.classList.add('is-open');
            v5505dCancelModal.setAttribute('aria-hidden', 'false');
        }

        setTimeout(function () {
            v5505dCancelReason?.focus();
        }, 50);
    }

    function v5505dCloseCancelModal() {
        if (v5505dCancelModal) {
            v5505dCancelModal.classList.remove('is-open');
            v5505dCancelModal.setAttribute('aria-hidden', 'true');
        }
    }

    async function v5505aCancelPendingTicket() {
        v5505dOpenCancelModal();
    }

    async function v5505dConfirmCancelPendingTicket() {
        if (!selectedOrder || !selectedOrder.id) {
            v5505dCloseCancelModal();
            showPosNotice('Selecciona un ticket pendiente antes de cancelarlo.', 'warning');
            return;
        }

        const reason = String(v5505dCancelReason?.value || '').trim();

        if (!reason) {
            if (v5505dCancelError) {
                v5505dCancelError.style.display = 'block';
            }

            v5505dCancelReason?.focus();
            return;
        }

        try {
            if (v5505dCancelConfirm) {
                v5505dCancelConfirm.disabled = true;
                v5505dCancelConfirm.textContent = 'Cancelando...';
            }

            await v5505aPostJson('/pos/orders/' + selectedOrder.id + '/cancel-pending', {
                reason: reason
            });

            const cancelledNumber = selectedOrder.number;

            v5505dCloseCancelModal();

            showPosNotice('Ticket pendiente cancelado: ' + cancelledNumber, 'info');

            selectedOrder = null;
            selectedLines = [];
            detailBox.innerHTML = '<div style="color:#64748b;">Selecciona un ticket pendiente.</div>';

            if (loadBtn) {
                loadBtn.disabled = true;
            }

            if (v5505aCancelBtn) {
                v5505aCancelBtn.disabled = true;
            }

            await loadOrders();
        } catch (error) {
            if (v5505dCancelConfirm) {
                v5505dCancelConfirm.disabled = false;
                v5505dCancelConfirm.textContent = 'Sí, cancelar ticket';
            }

            showPosNotice(error.message || 'No se pudo cancelar el ticket pendiente.', 'error');
        }
    }


    // V5.50.4A - permisos JS tickets pendientes.
    const v5504aPerms = window.BEXIA_POS_PENDING_TICKET_PERMISSIONS || {};
    const modal = document.getElementById('v5350-pending-tickets-modal');
    const closeBtn = document.getElementById('v5350-close-pending');
    const ordersBox = document.getElementById('v5350-orders');
    const detailBox = document.getElementById('v5350-detail-body');
    const loadBtn = document.getElementById('v5350-load-ticket');
    const v5505aCancelBtn = document.getElementById('v5505a-cancel-ticket');
    const v5505dCancelModal = document.getElementById('v5505d-cancel-pending-modal');
    const v5505dCancelTicket = document.getElementById('v5505d-cancel-ticket');
    const v5505dCancelReason = document.getElementById('v5505d-cancel-reason');
    const v5505dCancelError = document.getElementById('v5505d-cancel-error');
    const v5505dCancelClose = document.getElementById('v5505d-cancel-close');
    const v5505dCancelConfirm = document.getElementById('v5505d-cancel-confirm');

    function v5505eMoveCancelModalToBody() {
        if (
            v5505dCancelModal
            && document.body
            && v5505dCancelModal.parentElement !== document.body
        ) {
            document.body.appendChild(v5505dCancelModal);
        }
    }

    v5505eMoveCancelModalToBody();

    let selectedOrder = null;
    let selectedLines = [];

    function renderOrders(orders) {
        if (!orders.length) {
            ordersBox.innerHTML = '<div style="padding:14px; color:#64748b;">No hay tickets pendientes.</div>';
            return;
        }

        ordersBox.innerHTML = '';

        orders.forEach(function (order) {
            const row = document.createElement('div');
            row.className = 'v5350-order';
            row.dataset.orderId = order.id;
            const scopeLabel = order.pending_scope_label || 'Pendiente';
            const scopeColor = order.pending_scope === 'previous' ? '#92400e' : '#1d4ed8';
            const scopeBg = order.pending_scope === 'previous' ? '#fef3c7' : '#dbeafe';
            const sessionLabel = order.session_number ? (' · ' + order.session_number) : '';

            row.innerHTML =
                '<div>' +
                    '<strong>' + escapeHtml(order.number) + '</strong>' +
                    '<div style="margin-top:5px;">' +
                        '<span style="display:inline-flex;align-items:center;border-radius:999px;padding:3px 8px;font-size:11px;font-weight:950;color:' + scopeColor + ';background:' + scopeBg + ';">' + escapeHtml(scopeLabel) + escapeHtml(sessionLabel) + '</span>' +
                    '</div>' +
                    '<div style="color:#64748b;font-size:12px;margin-top:5px;">Vendedor: ' + escapeHtml(order.seller_name || 'Sin vendedor asignado') + '</div>' +
                    '<div style="color:#64748b;font-size:12px;margin-top:2px;">Pendiente de cobro · ' + money(order.total) + '</div>' +
                '</div>';

            row.addEventListener('click', function () {
                Array.from(ordersBox.children).forEach(function (child) {
                    child.classList.remove('is-active');
                });

                row.classList.add('is-active');
                loadOrder(order.id);
            });

            ordersBox.appendChild(row);
        });
    }

    async function loadOrders() {
        const sid = sessionId();

        if (!sid) {
            ordersBox.innerHTML = '<div style="padding:14px; color:#b91c1c;">No se pudo identificar la sesión.</div>';
            return;
        }

        if (!v5504aPerms.view) {
            ordersBox.innerHTML = '<div style="padding:14px; color:#b91c1c;">No tienes permiso para ver tickets pendientes.</div>';
            return;
        }

        ordersBox.innerHTML = '<div style="padding:14px; color:#64748b;">Cargando...</div>';
        detailBox.innerHTML = '<div style="color:#64748b;">Selecciona un ticket pendiente.</div>';
        loadBtn.disabled = true;

        if (v5505aCancelBtn) {
            v5505aCancelBtn.disabled = true;
        }

        selectedOrder = null;
        selectedLines = [];

        try {
            const data = await fetchJson('/pos/sessions/' + sid + '/pending-orders');
            renderOrders(data.orders || []);
        } catch (error) {
            ordersBox.innerHTML = '<div style="padding:14px; color:#b91c1c;">' + escapeHtml(error.message || 'Error al cargar tickets.') + '</div>';
        }
    }

    async function loadOrder(orderId) {
        detailBox.innerHTML = '<div style="color:#64748b;">Cargando detalle...</div>';
        loadBtn.disabled = true;

        if (v5505aCancelBtn) {
            v5505aCancelBtn.disabled = true;
        }

        selectedOrder = null;
        selectedLines = [];

        try {
            const data = await fetchJson('/pos/orders/' + orderId);

            selectedOrder = data.order;
            selectedLines = data.order.items || data.order.lines || data.lines || [];

            renderDetail(selectedOrder, selectedLines);
        } catch (error) {
            detailBox.innerHTML = '<div style="color:#b91c1c;">' + escapeHtml(error.message || 'Error al cargar detalle.') + '</div>';
        }
    }

    function renderDetail(order, lines) {
        let html = '';

        html += '<div style="font-weight:950; font-size:16px;">' + escapeHtml(order.number) + '</div>';
        const v5411CustomerName = (order.customer && order.customer.name) || order.customer_name || '';
        if (v5411CustomerName) {
            html += '<div style="color:#64748b; font-size:12px; margin-top:4px;">Cliente: ' + v5411CustomerName + '</div>';
        }
        html += '<div style="color:#64748b; font-size:12px; margin-top:4px;">' + escapeHtml(order.status_label || 'Pendiente de cobro') + '</div>';
        if (order.customer && order.customer.name) {
        }
        if (order.customer && order.customer.name) {
        }

        html += '<div style="margin-top:14px;">';

        lines.forEach(function (line) {
            html += '<div class="v5350-line">';
            html += '<div>';
            html += '<strong>' + escapeHtml(line.qty + ' x ' + line.name) + '</strong>';

            if (line.reference) {
                html += '<div style="color:#64748b; font-size:11px; margin-top:2px;">' + escapeHtml(line.reference) + '</div>';
            }

            html += '<div style="color:#64748b; font-size:12px; margin-top:2px;">' + money(line.price) + ' c/u</div>';
            html += '</div>';
            html += '<div style="font-weight:950;">' + money(line.line_total || line.total || 0) + '</div>';
            html += '</div>';
        });

        html += '</div>';

        html += '<div class="v5350-total">';
        html += '<span>Total adeudado</span>';
        html += '<strong>' + money(order.total) + '</strong>';
        html += '</div>';
        if (order && order.id && v5504aPerms.print) {
            html += '<div style="margin-top:12px;">';
            html += '<a href="/pos/orders/' + order.id + '/pending-ticket/print" target="_blank" rel="noopener" class="v5379-print-pending-ticket" style="display:flex;align-items:center;justify-content:center;width:100%;height:40px;border-radius:12px;border:1px solid #dbe3ef;background:#ffffff;color:#0f172a;font-weight:900;text-decoration:none;">Reimprimir ticket pendiente</a>';
            html += '</div>';
        }


        detailBox.innerHTML = html;
        loadBtn.disabled = lines.length === 0;

        if (v5505aCancelBtn) {
            v5505aCancelBtn.disabled = !(order && order.id && v5504aPerms.cancel);
        }
    }

    v5505aCancelBtn?.addEventListener('click', function () {
        v5505aCancelPendingTicket();
    });

    v5505dCancelClose?.addEventListener('click', function () {
        v5505dCloseCancelModal();
    });

    v5505dCancelConfirm?.addEventListener('click', function () {
        v5505dConfirmCancelPendingTicket();
    });

    v5505dCancelModal?.addEventListener('click', function (event) {
        if (event.target === v5505dCancelModal) {
            v5505dCloseCancelModal();
        }
    });

    v5505dCancelReason?.addEventListener('input', function () {
        if (v5505dCancelError) {
            v5505dCancelError.style.display = 'none';
        }
    });

    loadBtn?.addEventListener('click', function () {
        if (!v5504aPerms.load) {
            showPosNotice('No tienes permiso para cargar tickets pendientes.', 'warning');
            return;
        }

        if (!selectedOrder) {
            showPosNotice('Selecciona un ticket pendiente antes de cargarlo al carrito.', 'warning');
            return;
        }

        selectedLines = selectedLines && selectedLines.length
            ? selectedLines
            : (selectedOrder.items || selectedOrder.lines || []);

        if (!selectedLines.length) {
            showPosNotice('El ticket pendiente no tiene productos para cargar.', 'warning');
            return;
        }

        if (!window.BEXIA_POS_CART_API || typeof window.BEXIA_POS_CART_API.loadPendingOrderItems !== 'function') {
            showPosNotice('No se encontró la función para cargar el ticket al carrito.', 'error');
            return;
        }

        window.BEXIA_POS_LOADED_PENDING_ORDER = selectedOrder;

        const v5496aPendingPriceListId = Number(
            selectedOrder.price_list_id
            || selectedOrder.selected_price_list_id
            || selectedOrder.metadata?.price_list_id
            || selectedOrder.metadata?.selected_price_list_id
            || 0
        );

        const v5496aPendingPriceListName = String(
            selectedOrder.price_list_name
            || selectedOrder.selected_price_list_name
            || selectedOrder.metadata?.price_list_name
            || selectedOrder.metadata?.selected_price_list_name
            || ''
        );

        if (v5496aPendingPriceListId > 0) {
            window.BEXIA_POS_SELECTED_PRICE_LIST_ID = v5496aPendingPriceListId;
            window.BEXIA_POS_SELECTED_PRICE_LIST_NAME = v5496aPendingPriceListName;

            const v5496aPriceListButton = document.getElementById('v5492d-open-price-list');

            if (v5496aPriceListButton) {
                v5496aPriceListButton.dataset.selectedPriceListId = String(v5496aPendingPriceListId);
                v5496aPriceListButton.dataset.selectedPriceListName = v5496aPendingPriceListName || ('Lista #' + v5496aPendingPriceListId);
                v5496aPriceListButton.textContent = 'Lista: ' + (v5496aPendingPriceListName || ('Lista #' + v5496aPendingPriceListId));
                v5496aPriceListButton.title = 'Lista de precios actual: ' + (v5496aPendingPriceListName || ('Lista #' + v5496aPendingPriceListId));
            }

            if (typeof window.BEXIA_POS_APPLY_PRICE_LIST_BY_ID === 'function') {
                window.BEXIA_POS_APPLY_PRICE_LIST_BY_ID(v5496aPendingPriceListId, {
                    silent: true,
                    source: 'pending_order_load'
                }).catch(function (error) {
                    console.warn('No se pudo reaplicar la lista del ticket pendiente:', error);
                });
            }
        }


        const v5411Customer = selectedOrder.customer || (
            selectedOrder.customer_id
                ? {
                    id: selectedOrder.customer_id,
                    name: selectedOrder.customer_name || 'Cliente seleccionado',
                    rfc: selectedOrder.customer_rfc || '',
                    email: selectedOrder.customer_email || '',
                    phone: selectedOrder.customer_phone || ''
                }
                : null
        );

        if (v5411Customer && v5411Customer.id) {
            window.BEXIA_POS_SELECTED_CUSTOMER_ID = v5411Customer.id;
            window.BEXIA_POS_SELECTED_CUSTOMER = v5411Customer;

            const clientBox = document.querySelector('aside.cart .client');
            const clientValue = clientBox ? clientBox.querySelector('.value') : null;

            if (clientValue) {
                clientValue.textContent = v5411Customer.name || 'Público en General';
            }

            if (clientBox) {
                clientBox.querySelectorAll('[data-v5391b-customer-rfc]').forEach(function (node) {
                    node.remove();
                });

                let rfcLine = clientBox.querySelector('[data-v5392-customer-rfc]');

                if (!rfcLine) {
                    rfcLine = Array.from(clientBox.children).find(function (node) {
                        return node.textContent && node.textContent.trim().startsWith('RFC:');
                    });

                    if (rfcLine) {
                        rfcLine.setAttribute('data-v5392-customer-rfc', '1');
                    }
                }

                if (rfcLine) {
                    if (v5411Customer.rfc) {
                        rfcLine.textContent = 'RFC: ' + v5411Customer.rfc;
                        rfcLine.style.display = '';
                    } else {
                        rfcLine.textContent = '';
                        rfcLine.style.display = 'none';
                    }
                }
            }
        }

        window.BEXIA_POS_CART_API.loadPendingOrderItems(selectedLines);

        showPosNotice('Ticket ' + selectedOrder.number + ' cargado al carrito para cobro.', 'info');

        closeModal();
    });

    function ensureButton() {
        if (document.getElementById('v5350-open-pending')) {
            return;
        }

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'v5350-open-pending';
        btn.className = 'v5350-pending-btn';
        btn.textContent = 'Tickets pendientes';

        if (!v5504aPerms.view) {
            btn.style.display = 'none';
        }

        btn.addEventListener('click', function () {
            openModal();
        });

        const closeSession = document.getElementById('v5333-close-session-form')
            || document.getElementById('v5332-close-session-form')
            || document.getElementById('v5331-close-session-form');

        if (closeSession && closeSession.parentElement) {
            closeSession.parentElement.insertBefore(btn, closeSession);
        } else {
            document.body.prepend(btn);
        }
    }

    async function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        await loadOrders();
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    closeBtn?.addEventListener('click', closeModal);

    modal?.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    ensureButton();
});
</script>




</div>


<style id="v5349-pdv-flow-style">
    body > .charge,
    .charge:not(#v5349-charge-ticket) {
        display: none !important;
    }

    .v5349-bottom-actions {
        position: fixed;
        right: 18px;
        bottom: 18px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        z-index: 70;
        width: min(315px, calc(100vw - 36px));
    }

    .v5349-create-pending-ticket,
    .v5349-charge-ticket {
        width: 100%;
        min-height: 64px;
        border-radius: 20px;
        padding: 14px 18px;
        font-weight: 950;
        font-size: 15px;
        cursor: pointer;
        border: 1px solid transparent;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .12);
        transition: transform .12s ease, box-shadow .12s ease, filter .12s ease;
    }

    .v5349-create-pending-ticket:hover,
    .v5349-charge-ticket:hover {
        transform: translateY(-1px);
        box-shadow: 0 16px 34px rgba(15, 23, 42, .16);
    }

    .v5349-create-pending-ticket:hover {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border-color: #60a5fa;
    }

    .v5349-create-pending-ticket:active,
    .v5349-charge-ticket:active {
        transform: translateY(0);
        filter: brightness(.98);
    }

    .v5349-create-pending-ticket {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border-color: #93c5fd;
        color: #1d4ed8;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .v5349-charge-ticket {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        border-color: #1d4ed8;
    }

    .v5349-charge-ticket span {
        display: block;
        font-size: 19px;
        margin-top: 2px;
        font-weight: 950;
        line-height: 1.05;
    }

    .v5349-ticket-preview {
        white-space: pre-line;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
        text-align: left;
        line-height: 1.35;
    }

    .v5349-error {
        color: #b91c1c;
        font-weight: 800;
        padding: 10px;
    }
@media (max-width: 900px) {
}


    .v5356-pos-notice {
        position: fixed;
        top: 18px;
        right: 18px;
        z-index: 12000;
        min-width: 280px;
        max-width: 420px;
        border-radius: 16px;
        padding: 14px 16px;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .18);
        font-size: 14px;
        font-weight: 800;
        display: none;
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        color: #1e3a8a;
    }

    .v5356-pos-notice.is-warning {
        border-color: #fde68a;
        background: #fffbeb;
        color: #92400e;
    }

    .v5356-pos-notice.is-error {
        border-color: #fecaca;
        background: #fef2f2;
        color: #991b1b;
    }


    @media (max-width: 900px) {
        .v5349-bottom-actions {
            left: 12px;
            right: 12px;
            width: auto;
        }
    }
</style>

<div id="v5356-pos-notice" class="v5356-pos-notice" role="status" aria-live="polite"></div>


<div class="v5349-bottom-actions" id="v5349-bottom-actions">
    <button type="button" class="v5349-create-pending-ticket" id="v5349-create-pending-ticket">
        🧾 Crear ticket pendiente
    </button>

    <button type="button" class="v5349-charge-ticket" id="v5349-charge-ticket">
        🛒
        <div>
            Cobrar ticket
            <span id="v5349-charge-total">$0.00</span>
        </div>
    </button>
</div>



@php
    /*
     * Logo configurado en PDV > Ticket / Facturación.
     * Se convierte a URL pública para imprimir en ticket 80mm.
     */
    $v5356TicketLogoUrl = null;
    $v5356TicketLogoRaw = null;

    foreach ([
        'ticket_logo_path',
        'ticket_logo',
        'receipt_logo_path',
        'receipt_logo',
        'pos_ticket_logo_path',
        'logo_ticket_path',
        'ticket_logo_file',
        'receipt_logo_file',
        'logo_path',
        'logo',
    ] as $logoField) {
        $logoValue = data_get($pos ?? null, $logoField);

        if (! empty($logoValue)) {
            $v5356TicketLogoRaw = (string) $logoValue;
            break;
        }
    }

    if (! empty($v5356TicketLogoRaw)) {
        $logoValue = trim((string) $v5356TicketLogoRaw);

        if (
            str_starts_with($logoValue, 'http://')
            || str_starts_with($logoValue, 'https://')
            || str_starts_with($logoValue, 'data:')
        ) {
            $v5356TicketLogoUrl = $logoValue;
        } else {
            $logoValue = ltrim($logoValue, '/');

            if (str_starts_with($logoValue, 'public/')) {
                $logoValue = substr($logoValue, strlen('public/'));
            }

            if (str_starts_with($logoValue, 'storage/')) {
                $v5356TicketLogoUrl = asset($logoValue);
            } else {
                $v5356TicketLogoUrl = asset('storage/' . $logoValue);
            }
        }
    }
@endphp

<script id="v5356-ticket-logo-data">
window.BEXIA_POS_TICKET_LOGO_URL = @json($v5356TicketLogoUrl);
window.BEXIA_POS_TICKET_LOGO_RAW = @json($v5356TicketLogoRaw);

/* BEXIA_V582P6A_PENDING_TICKET_PRINT_SETTING */
window.BEXIA_POS_PRINT_PENDING_TICKET_ON_CREATE =
    @json((bool) ($pos->print_pending_ticket_on_create ?? true));
</script>

<script id="v5349-pdv-flow-script">
document.addEventListener('DOMContentLoaded', function () {
    const csrf = @json(csrf_token());
    let creatingPendingTicket = false;

    function money(value) {
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(Number(value || 0));
    }

    function sessionId() {
        const match = window.location.pathname.match(/\/pos\/sessions\/(\d+)\/screen/);
        return match ? match[1] : null;
    }

    function cartApi() {
        return window.BEXIA_POS_CART_API || null;
    }

    function syncChargeTotal() {
        const api = cartApi();
        const total = api && typeof api.getTotal === 'function' ? api.getTotal() : 0;

        const oldTotal = document.getElementById('v5339-charge-total');
        const newTotal = document.getElementById('v5349-charge-total');

        if (oldTotal) {
            oldTotal.textContent = money(total);
        }

        if (newTotal) {
            newTotal.textContent = money(total);
        }
    }

    setInterval(syncChargeTotal, 300);
    syncChargeTotal();

    async function jsonFetch(url, options) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options && options.headers ? options.headers : {}),
            },
        });

        const data = await response.json().catch(function () {
            return {};
        });

        if (!response.ok || data.ok === false) {
            if (response.status === 401) {
                throw new Error(data.message || 'Tu sesión expiró. Vuelve a iniciar sesión para continuar.');
            }

            if (response.status === 419) {
                throw new Error('Tu sesión expiró o el formulario perdió validez. Recarga la página e inténtalo de nuevo.');
            }

            throw new Error(data.message || 'No se pudo completar la operación.');
        }

        return data;
    }

    function showPosNotice(message, type) {
        type = type || 'info';

        const box = document.getElementById('v5356-pos-notice');

        if (box) {
            box.textContent = message;
            box.className = 'v5356-pos-notice';

            if (type === 'warning') {
                box.classList.add('is-warning');
            }

            if (type === 'error') {
                box.classList.add('is-error');
            }

            box.style.display = 'block';

            window.clearTimeout(window.v5416NoticeTimer);
            window.v5416NoticeTimer = window.setTimeout(function () {
                box.style.display = 'none';
            }, 4500);

            return;
        }

        alert(message);
    }

async function createPendingTicket() {
        const notice = function (message, type) {
            type = type || 'info';

            if (typeof window.showPosNotice === 'function') {
                window.notice(message, type);
                return;
            }

            const box = document.getElementById('v5356-pos-notice');

            if (box) {
                box.textContent = message;
                box.className = 'v5356-pos-notice';

                if (type === 'warning') {
                    box.classList.add('is-warning');
                }

                if (type === 'error') {
                    box.classList.add('is-error');
                }

                box.style.display = 'block';

                window.clearTimeout(window.v5415NoticeTimer);
                window.v5415NoticeTimer = window.setTimeout(function () {
                    box.style.display = 'none';
                }, 4500);

                return;
            }

            alert(message);
        };

        if (creatingPendingTicket) {
            return;
        }

        const api = cartApi();

        if (!api || api.size() === 0) {
            notice('Agrega productos antes de crear el ticket pendiente.', 'warning');
            return;
        }

        const id = sessionId();

        if (!id) {
            notice('No se pudo identificar la sesión del PDV.', 'error');
            return;
        }

        const items = api.getItems ? api.getItems() : [];

        if (!items.length) {
            notice('Agrega productos antes de crear el ticket pendiente.', 'warning');
            return;
        }

        // Si el carrito viene completo de un ticket pendiente, no permitimos duplicarlo.
        // Pero si el usuario vació o empezó una venta nueva, esta variable ya debe estar limpia.
        if (!(window.BEXIA_POS_PENDING_TICKET_PERMISSIONS || {}).create) {
            notice('No tienes permiso para crear tickets pendientes.', 'warning');
            return;
        }

        // BEXIA_V5829E_SAVE_OVER_LOADED_PENDING_BASE
        const loadedPendingOrder = window.BEXIA_POS_LOADED_PENDING_ORDER || null;
        const isUpdatingPending = Boolean(loadedPendingOrder && loadedPendingOrder.id);

        const payload = {
            items: items,
            customer_id: window.BEXIA_POS_SELECTED_CUSTOMER_ID || null,
            price_list_id: window.BEXIA_POS_SELECTED_PRICE_LIST_ID || null,
            price_list_name: window.BEXIA_POS_SELECTED_PRICE_LIST_NAME || null,
            selected_price_list_id: window.BEXIA_POS_SELECTED_PRICE_LIST_ID || null,
            selected_price_list_name: window.BEXIA_POS_SELECTED_PRICE_LIST_NAME || null,
            payment_label: null,
            payment_form_id: null,
            status: 'pending_payment',
            order_note: api.getNote ? api.getNote() : '',
            discount: api.getDiscount ? api.getDiscount() : null,
            total: api.getTotal ? api.getTotal() : null,
            pos_session_id: Number(id)
        };

        const button = document.getElementById('v5349-create-pending-ticket');
        const originalText = button ? button.textContent : '';

        /* BEXIA_V582P6A_PENDING_TICKET_CONDITIONAL_POPUP */
        const shouldPrintPendingTicket =
            window.BEXIA_POS_PRINT_PENDING_TICKET_ON_CREATE !== false;

        let printWindow = null;

        if (shouldPrintPendingTicket) {
            try {
            printWindow = window.open('', '_blank', 'width=420,height=720');

            if (printWindow) {
                printWindow.document.open();
                printWindow.document.write(
                    '<!doctype html>' +
                    '<html><head><meta charset="utf-8"><title>Preparando ticket</title></head>' +
                    '<body style="font-family:Arial;text-align:center;padding:30px;">' +
                    '<strong>Preparando ticket pendiente...</strong><br><span>Espere un momento.</span>' +
                    '



</body></html>'
                );
                printWindow.document.close();
            }
            } catch (error) {
                printWindow = null;
            }
        }

        creatingPendingTicket = true;

        if (button) {
            button.disabled = true;
            button.textContent = isUpdatingPending ? 'Guardando cambios...' : 'Creando ticket...';
        }

        try {
            const pendingEndpoint = isUpdatingPending
                ? '/pos/orders/' + loadedPendingOrder.id + '/pending-update'
                : '/pos/sessions/' + id + '/orders';

            const data = await jsonFetch(pendingEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(payload),
            });

            const orderId = data.order_id || data.id || (data.order && data.order.id) || null;
            const number = data.number || (data.order && data.order.number) || 'folio generado';
            const printUrl = data.print_url || (orderId ? '/pos/orders/' + orderId + '/pending-ticket/print?initial=1' : '');

            window.BEXIA_POS_LOADED_PENDING_ORDER = null;

            if (api && typeof api.clear === 'function') {
                api.clear();
            }

            notice(
                (isUpdatingPending ? 'Ticket pendiente actualizado: ' : 'Ticket pendiente creado: ') + number,
                'info'
            );

            // BEXIA_V582P6A_PENDING_TICKET_PRINT_DECISION
            if (shouldPrintPendingTicket && printUrl) {
                if (printWindow && !printWindow.closed) {
                    printWindow.location.href = printUrl;
                } else {
                    window.open(printUrl, '_blank', 'width=420,height=720');
                }
            } else if (printWindow && !printWindow.closed) {
                printWindow.close();
            }
        } catch (error) {
            if (printWindow && !printWindow.closed) {
                printWindow.document.open();
                printWindow.document.write(
                    '<!doctype html>' +
                    '<html><head><meta charset="utf-8"><title>Error</title></head>' +
                    '<body style="font-family:Arial;text-align:center;padding:30px;color:#991b1b;">' +
                    '<strong>No se pudo preparar la impresión del ticket.</strong><br>' +
                    '<span>' + String(error.message || 'Error desconocido') + '</span>' +
                    '</body></html>'
                );
                printWindow.document.close();
            }

            notice(error.message || 'No se pudo crear el ticket pendiente.', 'error');
        } finally {
            creatingPendingTicket = false;

            if (button) {
                button.disabled = false;
                button.textContent = originalText || '🧾 Crear ticket pendiente';
            }
        }
    }

    
    function v5418PaymentMethods() {
        return window.BEXIA_POS_PAYMENT_METHODS || [];
    }

    function v5418PaymentTotal() {
        const api = cartApi();
        return Number(api && api.getTotal ? api.getTotal() : 0);
    }

    function v5418RenderPaymentRows(total) {
        const optionsBox = document.getElementById('v5335-payment-options');

        if (!optionsBox) {
            return;
        }

        const methods = v5418PaymentMethods();

        optionsBox.innerHTML = '';

        const wrapper = document.createElement('div');
        wrapper.style.display = 'grid';
        wrapper.style.gap = '10px';
        wrapper.dataset.v5418Rows = '1';

        const rowsBox = document.createElement('div');
        rowsBox.style.display = 'grid';
        rowsBox.style.gap = '10px';
        rowsBox.dataset.v5418RowsBox = '1';

        const summary = document.createElement('div');
        summary.style.fontSize = '12px';
        summary.style.fontWeight = '900';
        summary.style.color = '#0f172a';
        summary.dataset.v5418Summary = '1';

        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'v5335-payment-cancel';
        addBtn.textContent = 'Agregar otro método de pago';

        function updateSummary() {
            let sum = 0;

            rowsBox.querySelectorAll('[data-v5418-amount]').forEach(function (input) {
                sum += Number(input.value || 0);
            });

            const remaining = Number(total || 0) - sum;
            summary.textContent = 'Pagado: ' + money(sum) + ' · Restante: ' + money(remaining);
        }

        function addRow(amount) {
            const row = document.createElement('div');
            row.style.display = 'grid';
            row.style.gridTemplateColumns = '1fr 130px auto';
            row.style.gap = '8px';
            row.style.alignItems = 'center';

            const select = document.createElement('select');
            select.dataset.v5418PaymentForm = '1';
            select.style.height = '40px';
            select.style.border = '1px solid #dbe3ef';
            select.style.borderRadius = '12px';
            select.style.padding = '0 10px';
            select.style.fontWeight = '800';

            methods.forEach(function (method) {
                const opt = document.createElement('option');
                opt.value = method.id || '';
                opt.textContent = method.label || 'Método de pago';
                opt.dataset.label = method.label || 'Método de pago';
                select.appendChild(opt);
            });

            const input = document.createElement('input');
            input.type = 'number';
            input.step = '0.01';
            input.min = '0';
            input.dataset.v5418Amount = '1';
            input.value = Number(amount || 0).toFixed(2);
            input.style.height = '40px';
            input.style.border = '1px solid #dbe3ef';
            input.style.borderRadius = '12px';
            input.style.padding = '0 10px';
            input.style.fontWeight = '800';
            input.style.textAlign = 'right';

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'v5335-payment-cancel';
            remove.textContent = 'Quitar';

            remove.addEventListener('click', function () {
                if (rowsBox.children.length <= 1) {
                    return;
                }

                row.remove();
                updateSummary();
            });

            input.addEventListener('input', updateSummary);

            row.appendChild(select);
            row.appendChild(input);
            row.appendChild(remove);

            rowsBox.appendChild(row);
            updateSummary();
        }

        addBtn.addEventListener('click', function () {
            let sum = 0;

            rowsBox.querySelectorAll('[data-v5418-amount]').forEach(function (input) {
                sum += Number(input.value || 0);
            });

            const remaining = Math.max(0, Number(total || 0) - sum);

            addRow(remaining);
        });

        wrapper.appendChild(rowsBox);
        wrapper.appendChild(addBtn);
        wrapper.appendChild(summary);

        optionsBox.appendChild(wrapper);

        addRow(total);
    }

    function v5418CollectPayments() {
        const optionsBox = document.getElementById('v5335-payment-options');
        const payments = [];

        if (!optionsBox) {
            return payments;
        }

        optionsBox.querySelectorAll('[data-v5418-rows-box] > div').forEach(function (row) {
            const select = row.querySelector('[data-v5418-payment-form]');
            const input = row.querySelector('[data-v5418-amount]');

            if (!select || !input) {
                return;
            }

            const selected = select.options[select.selectedIndex];
            const amount = Number(input.value || 0);

            if (amount > 0) {
                const label = selected ? (selected.dataset.label || selected.textContent || 'Pago') : 'Pago';
                const isCash = String(label).toLowerCase().includes('efectivo')
                    || String(label).toLowerCase().includes('cash');

                payments.push({
                    payment_form_id: select.value || null,
                    payment_label: label,
                    amount: amount,
                    tendered_amount: amount,
                    cash_received: isCash ? amount : null,
                    is_cash_frontend: isCash,
                });
            }
        });

        return payments;
    }

    async function loadPaymentMethodsIntoModal() {
        const id = sessionId();
        const optionsBox = document.getElementById('v5335-payment-options');

        if (!optionsBox) {
            return;
        }

        optionsBox.innerHTML = '<div class="v5335-payment-option">Cargando métodos de pago...</div>';

        try {
            const data = await jsonFetch('/pos/sessions/' + id + '/payment-methods');
            window.BEXIA_POS_PAYMENT_METHODS = data.methods || [];

            if (!window.BEXIA_POS_PAYMENT_METHODS.length) {
                optionsBox.innerHTML = '<div class="v5335-payment-option">No hay métodos de pago configurados para este PDV.</div>';
                return;
            }

            v5418RenderPaymentRows(v5418PaymentTotal());
        } catch (error) {
            optionsBox.innerHTML = '<div class="v5349-error">' + (error.message || 'No se pudieron cargar los métodos de pago.') + '</div>';
        }
    }


    
    function openPaymentModal() {
        const api = cartApi();

        if (!api || api.size() === 0) {
            showPosNotice('Agrega productos antes de cobrar.', 'warning');
            return;
        }

        const pendingOrder = window.BEXIA_POS_LOADED_PENDING_ORDER || null;

        if (!pendingOrder || !pendingOrder.id) {
            showPosNotice('Primero carga un ticket pendiente al carrito para cobrarlo.', 'warning');
            return;
        }

        const modal = document.getElementById('v5335-payment-modal');
        const totalBox = document.getElementById('v5335-payment-total');
        const confirmBtn = document.getElementById('v5335-payment-confirm');

        const total = v5418PaymentTotal();

        if (totalBox) {
            totalBox.textContent = money(total);
        }

        if (confirmBtn) {
            confirmBtn.textContent = 'Registrar cobro';
            confirmBtn.onclick = async function (event) {
                event.preventDefault();

                const payments = v5418CollectPayments();
                const sum = payments.reduce(function (carry, payment) {
                    return carry + Number(payment.amount || 0);
                }, 0);

                if (!payments.length) {
                    showPosNotice('Agrega al menos un pago.', 'warning');
                    return;
                }

                // V5_53_0C5: efectivo puede ser mayor al total para calcular cambio.
                const v5530c5Total = Number(total.toFixed(2));
                const v5530c5Paid = Number(sum.toFixed(2));
                const v5530c5Over = Number((v5530c5Paid - v5530c5Total).toFixed(2));
                const v5530c5HasCash = payments.some(function (payment) {
                    const label = String(payment.payment_label || '').toLowerCase();

                    return payment.is_cash_frontend === true
                        || label.includes('efectivo')
                        || label.includes('cash');
                });

                if (v5530c5Paid + 0.01 < v5530c5Total) {
                    showPosNotice('El pago recibido es menor al total. Total: ' + money(v5530c5Total) + ' / Recibido: ' + money(v5530c5Paid), 'warning');
                    return;
                }

                if (v5530c5Over > 0.01 && !v5530c5HasCash) {
                    showPosNotice('Solo el pago en efectivo puede ser mayor al total para calcular cambio.', 'warning');
                    return;
                }

                confirmBtn.disabled = true;
                confirmBtn.textContent = 'Registrando cobro...';

                try {
                    const data = await jsonFetch('/pos/orders/' + pendingOrder.id + '/pay', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({
                            payments: payments,
                        }),
                    });
                    }

                    if (api && typeof api.getItems === 'function') {
                        const v5481e5PaidItems = api.getItems();

                        if (typeof window.BEXIA_POS_REFRESH_STOCK_TEXT_ONLY === 'function') {
                            await window.BEXIA_POS_REFRESH_STOCK_TEXT_ONLY(v5481e5PaidItems);
                        }
                    }

                    if (api && typeof api.clear === 'function') {
                        api.clear();
                    }

                    window.BEXIA_POS_LOADED_PENDING_ORDER = null;

                    if (modal) {
                        modal.classList.remove('is-open');
                        modal.setAttribute('aria-hidden', 'true');
                    }

                    showPosNotice('Cobro registrado: ' + (data.number || pendingOrder.number || 'ticket'), 'info');
                } catch (error) {
                    showPosNotice(error.message || 'No se pudo registrar el cobro.', 'error');
                } finally {
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'Registrar cobro';
                }
            };
        }

        if (modal) {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        }

        loadPaymentMethodsIntoModal();
    }


    document.getElementById('v5349-create-pending-ticket')?.addEventListener('click', function (event) {
        event.preventDefault();
        console.log('BEXIA PDV: click Crear ticket pendiente');
        createPendingTicket();
    });

    document.getElementById('v5349-charge-ticket')?.addEventListener('click', function (event) {
        event.preventDefault();
        openPaymentModal();
    });

    // V5.44.8 split payment inside v5349 start
    (function () {
        if (window.BEXIA_POS_SPLIT_PAYMENT_V5448_READY) {
            return;
        }

        window.BEXIA_POS_SPLIT_PAYMENT_V5448_READY = true;

        const formatter = new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        let paymentMethods = [];

        function money(value) {
            return formatter.format(Number(value || 0));
        }

        function csrfToken() {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            return metaToken || @json(csrf_token());
        }

        function sessionId() {
            const match = window.location.pathname.match(/\/pos\/sessions\/(\d+)\/screen/);
            return match ? match[1] : null;
        }

        function modal() {
            return document.getElementById('v5335-payment-modal');
        }

        function optionsBox() {
            return document.getElementById('v5335-payment-options');
        }

        function totalBox() {
            return document.getElementById('v5335-payment-total');
        }

        function confirmBtn() {
            return document.getElementById('v5335-payment-confirm');
        }

        function currentOrder() {
            return window.BEXIA_POS_LOADED_PENDING_ORDER || null;
        }

        function currentTotal() {
            const order = currentOrder();

            if (order && Number(order.total || 0) > 0) {
                return Number(order.total || 0);
            }

            if (window.BEXIA_POS_CART_API && typeof window.BEXIA_POS_CART_API.getTotal === 'function') {
                return Number(window.BEXIA_POS_CART_API.getTotal() || 0);
            }

            return 0;
        }

        function warn(message) {
            if (window.BEXIA_POS_CART_API && typeof window.BEXIA_POS_CART_API.setWarning === 'function') {
                window.BEXIA_POS_CART_API.setWarning(message);
                return;
            }

            window.alert(message);
        }

        async function jsonFetch(url, options) {
            const response = await fetch(url, {
                credentials: 'same-origin',
                ...options,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(options && options.headers ? options.headers : {}),
                },
            });

            const data = await response.json().catch(function () {
                return {};
            });

            if (!response.ok || data.ok === false) {
                throw new Error(data.message || 'No se pudo completar la operación.');
            }

            return data;
        }

        async function loadPaymentMethods() {
            const sid = sessionId();

            if (!sid) {
                throw new Error('No se pudo identificar la sesión del PDV.');
            }

            const data = await jsonFetch('/pos/sessions/' + sid + '/payment-methods', {
                method: 'GET',
            });

            const methods = data.methods || [];

            if (!methods.length) {
                throw new Error('No hay métodos de pago configurados para este PDV.');
            }

            return methods;
        }

        function openModal() {
            const m = modal();

            if (!m) {
                warn('No se encontró el modal de cobro.');
                return;
            }

            m.classList.add('is-open');
            m.setAttribute('aria-hidden', 'false');
        }

        function closeModal() {
            const m = modal();

            if (!m) {
                return;
            }

            m.classList.remove('is-open');
            m.setAttribute('aria-hidden', 'true');
        }

        function rowsBox() {
            return document.getElementById('v5448-payment-rows');
        }

        function paymentRows() {
            const box = rowsBox();

            if (!box) {
                return [];
            }

            return Array.from(box.querySelectorAll('[data-v5448-payment-row="1"]'));
        }

        function paidTotal() {
            return paymentRows().reduce(function (sum, row) {
                const input = row.querySelector('[data-v5448-payment-amount="1"]');
                const value = Number(input ? input.value : 0);

                return sum + (Number.isFinite(value) ? value : 0);
            }, 0);
        }

        function updateSummary() {
            const summary = document.getElementById('v5448-payment-summary');
            const button = confirmBtn();

            if (!summary) {
                return;
            }

            const total = Number(currentTotal().toFixed(2));
            const paid = Number(paidTotal().toFixed(2));
            const diff = Number((total - paid).toFixed(2));
            const exact = Math.abs(diff) <= 0.01 && paid > 0;

            summary.innerHTML =
                '<div style="display:flex;justify-content:space-between;gap:12px;"><span>Total</span><strong>' + money(total) + '</strong></div>' +
                '<div style="display:flex;justify-content:space-between;gap:12px;"><span>Pagado</span><strong>' + money(paid) + '</strong></div>' +
                '<div style="display:flex;justify-content:space-between;gap:12px;color:' + (exact ? '#166534' : '#b91c1c') + ';"><span>' + (diff >= 0 ? 'Saldo' : 'Diferencia') + '</span><strong>' + money(Math.abs(diff)) + '</strong></div>';

            if (button) {
                button.disabled = !exact;
                button.textContent = 'Registrar cobro';
            }
        }

        function buildMethodSelect() {
            const select = document.createElement('select');
            select.setAttribute('data-v5448-payment-form', '1');
            select.style.width = '100%';
            select.style.border = '1px solid #dbe3ef';
            select.style.borderRadius = '14px';
            select.style.padding = '12px';
            select.style.fontWeight = '850';
            select.style.background = '#fff';

            paymentMethods.forEach(function (method) {
                const label = method.label || method.name || method.description || 'Pago';

                const option = document.createElement('option');
                option.value = method.id || '';
                option.textContent = label;
                option.dataset.label = label;
                select.appendChild(option);
            });

            return select;
        }

        function addPaymentRow(amount) {
            const box = rowsBox();

            if (!box) {
                return;
            }

            const row = document.createElement('div');
            row.setAttribute('data-v5448-payment-row', '1');
            row.style.display = 'grid';
            row.style.gridTemplateColumns = '1fr 145px 38px';
            row.style.gap = '10px';
            row.style.alignItems = 'center';

            const select = buildMethodSelect();

            const input = document.createElement('input');
            input.type = 'number';
            input.step = '0.01';
            input.min = '0';
            input.inputMode = 'decimal';
            input.value = Number(amount || 0).toFixed(2);
            input.setAttribute('data-v5448-payment-amount', '1');
            input.style.width = '100%';
            input.style.border = '1px solid #dbe3ef';
            input.style.borderRadius = '14px';
            input.style.padding = '12px';
            input.style.fontWeight = '850';

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = '×';
            remove.style.border = '1px solid #fecaca';
            remove.style.background = '#fff';
            remove.style.color = '#b91c1c';
            remove.style.borderRadius = '12px';
            remove.style.fontWeight = '950';
            remove.style.padding = '10px 0';
            remove.style.cursor = 'pointer';

            select.addEventListener('change', updateSummary);
            input.addEventListener('input', updateSummary);

            remove.addEventListener('click', function () {
                row.remove();

                if (paymentRows().length === 0) {
                    addPaymentRow(currentTotal());
                }

                updateSummary();
            });

            row.appendChild(select);
            row.appendChild(input);
            row.appendChild(remove);

            box.appendChild(row);
            updateSummary();
        }

        function collectPayments() {
            return paymentRows().map(function (row) {
                const select = row.querySelector('[data-v5448-payment-form="1"]');
                const input = row.querySelector('[data-v5448-payment-amount="1"]');

                if (!select || !input) {
                    return null;
                }

                const option = select.options[select.selectedIndex];
                const amount = Number(input.value || 0);

                if (!Number.isFinite(amount) || amount <= 0) {
                    return null;
                }

                const label = option ? (option.dataset.label || option.textContent || 'Pago') : 'Pago';
                const isCash = String(label).toLowerCase().includes('efectivo')
                    || String(label).toLowerCase().includes('cash');

                return {
                    payment_form_id: select.value || null,
                    payment_label: label,
                    amount: Number(amount.toFixed(2)),
                    tendered_amount: Number(amount.toFixed(2)),
                    cash_received: isCash ? Number(amount.toFixed(2)) : null,
                    is_cash_frontend: isCash,
                };
            }).filter(Boolean);
        }

        function renderPaymentRows() {
            const box = optionsBox();

            if (!box) {
                throw new Error('No se encontró el contenedor de métodos de pago.');
            }

            box.className = '';
            box.innerHTML = '';

            const wrapper = document.createElement('div');
            wrapper.style.display = 'grid';
            wrapper.style.gap = '12px';

            const rows = document.createElement('div');
            rows.id = 'v5448-payment-rows';
            rows.style.display = 'grid';
            rows.style.gap = '10px';

            const addButton = document.createElement('button');
            addButton.type = 'button';
            addButton.className = 'v5335-payment-cancel';
            addButton.style.width = '100%';
            addButton.textContent = '+ Agregar otro método de pago';

            const summary = document.createElement('div');
            summary.id = 'v5448-payment-summary';
            summary.style.border = '1px solid #e2e8f0';
            summary.style.borderRadius = '14px';
            summary.style.padding = '12px 14px';
            summary.style.display = 'grid';
            summary.style.gap = '8px';
            summary.style.fontWeight = '850';

            addButton.addEventListener('click', function () {
                const remaining = Math.max(0, Number((currentTotal() - paidTotal()).toFixed(2)));
                addPaymentRow(remaining > 0 ? remaining : 0);
            });

            wrapper.appendChild(rows);
            wrapper.appendChild(addButton);
            wrapper.appendChild(summary);

            box.appendChild(wrapper);

            addPaymentRow(currentTotal());
        }


        // V5.45.0 direct charge create order start
        async function v5450EnsureOrderForPayment() {
            const existingOrder = currentOrder();

            if (existingOrder && existingOrder.id) {
                return existingOrder;
            }

            const api = window.BEXIA_POS_CART_API || null;

            if (!api || typeof api.size !== 'function' || api.size() === 0) {
                warn('Agrega productos antes de cobrar.');
                return null;
            }

            if (typeof api.getItems !== 'function') {
                warn('No se encontró la función para leer el carrito.');
                return null;
            }

            const items = api.getItems();

            if (!items || !items.length) {
                warn('Agrega productos antes de cobrar.');
                return null;
            }

            const sid = sessionId();

            if (!sid) {
                warn('No se pudo identificar la sesión del PDV.');
                return null;
            }

            if (window.BEXIA_POS_CREATING_DIRECT_ORDER_FOR_PAYMENT) {
                return null;
            }

            const chargeButton = document.getElementById('v5349-charge-ticket');

            window.BEXIA_POS_CREATING_DIRECT_ORDER_FOR_PAYMENT = true;

            if (chargeButton) {
                chargeButton.disabled = true;
            }

            try {
                const payload = {
                    items: items,
                    customer_id: window.BEXIA_POS_SELECTED_CUSTOMER_ID || null,
                    price_list_id: window.BEXIA_POS_SELECTED_PRICE_LIST_ID || null,
                    price_list_name: window.BEXIA_POS_SELECTED_PRICE_LIST_NAME || null,
                    selected_price_list_id: window.BEXIA_POS_SELECTED_PRICE_LIST_ID || null,
                    selected_price_list_name: window.BEXIA_POS_SELECTED_PRICE_LIST_NAME || null,
                    payment_label: null,
                    order_note: typeof api.getNote === 'function' ? (api.getNote() || '') : '',
                    discount: typeof api.getDiscount === 'function' ? (api.getDiscount() || null) : null,
                };

                const data = await jsonFetch('/pos/sessions/' + sid + '/orders', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify(payload),
                });

                const orderData = data.order || {};

                const createdOrder = {
                    id: data.order_id || data.id || orderData.id || null,
                    number: data.number || orderData.number || '',
                    status: data.status || orderData.status || 'pending_payment',
                    status_label: data.status_label || orderData.status_label || 'Pendiente de cobro',
                    total: Number(data.total || orderData.total || (typeof api.getTotal === 'function' ? api.getTotal() : 0)),
                    customer_id: payload.customer_id,
                    customer: window.BEXIA_POS_SELECTED_CUSTOMER || null,
                    customer_name: window.BEXIA_POS_SELECTED_CUSTOMER?.name || '',
                                                            metadata: {
                        order_note: payload.order_note || '',
                        discount: payload.discount || null,
                    },
                    order_note: payload.order_note || '',
                    discount: payload.discount || null,
                    items: items,
                };

                if (!createdOrder.id) {
                    throw new Error('Se creó el ticket, pero no se recibió el ID de la orden.');
                }

                window.BEXIA_POS_LOADED_PENDING_ORDER = createdOrder;

                return createdOrder;
            } catch (error) {
                warn(error.message || 'No se pudo crear el ticket para cobrar.');
                return null;
            } finally {
                window.BEXIA_POS_CREATING_DIRECT_ORDER_FOR_PAYMENT = false;

                if (chargeButton) {
                    chargeButton.disabled = false;
                }
            }
        }
        // V5.45.0 direct charge create order end


        async function openPaymentFlow(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();

                if (typeof event.stopImmediatePropagation === 'function') {
                    event.stopImmediatePropagation();
                }
            }

            const order = await v5450EnsureOrderForPayment();

            if (!order || !order.id) {
                return;
            }

            const total = currentTotal();

            if (!total || total <= 0) {
                warn('El ticket no tiene total válido para cobrar.');
                return;
            }

            if (totalBox()) {
                totalBox().textContent = money(total);
            }

            if (optionsBox()) {
                optionsBox().className = '';
                optionsBox().innerHTML = 'Cargando métodos de pago...';
            }

            if (confirmBtn()) {
                confirmBtn().disabled = true;
                confirmBtn().textContent = 'Registrar cobro';
            }

            openModal();

            try {
                paymentMethods = await loadPaymentMethods();
                renderPaymentRows();
            } catch (error) {
                if (optionsBox()) {
                    optionsBox().innerHTML = '<div class="warning">' + (error.message || 'No se pudieron cargar métodos de pago.') + '</div>';
                }
            }
        }

        async function createOrderFromCartBeforePay() {
        const api = window.BEXIA_POS_CART_API || null;

        if (!api || typeof api.getItems !== 'function' || typeof api.size !== 'function' || api.size() === 0) {
            throw new Error('Agrega productos al carrito antes de cobrar.');
        }

        const sid = sessionId();

        if (!sid) {
            throw new Error('No se pudo identificar la sesión del PDV.');
        }

        const items = api.getItems();
        const discount = typeof api.getDiscount === 'function' ? api.getDiscount() : null;
        const note = typeof api.getNote === 'function' ? api.getNote() : '';

        const data = await getJson('/pos/sessions/' + sid + '/orders', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                items: items,
                discount: discount,
                order_note: note,
                total: currentTotal(),
            }),
        });

        const order = {
            id: data.id || data.order_id || (data.order && data.order.id) || null,
            number: data.number || (data.order && data.order.number) || '',
            total: currentTotal(),
            status: 'pending_payment',
            items: items,
        };

        if (!order.id) {
            throw new Error('Se creó el ticket, pero no se recibió el ID para cobrarlo.');
        }

        window.BEXIA_POS_LOADED_PENDING_ORDER = order;

        return order;
    }

    async function registerPayment(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();

                if (typeof event.stopImmediatePropagation === 'function') {
                    event.stopImmediatePropagation();
                }
            }

            const order = currentOrder();

            if (!order || !order.id) {
                warn('Primero carga un ticket pendiente al carrito para cobrarlo.');
                return;
            }

            const total = Number(currentTotal().toFixed(2));
            const payments = collectPayments();
            const paid = Number(payments.reduce(function (sum, payment) {
                return sum + Number(payment.amount || 0);
            }, 0).toFixed(2));

            if (!payments.length) {
                warn('Agrega al menos un pago.');
                return;
            }

            // V5_53_0C5: efectivo puede ser mayor al total para calcular cambio.
            const v5530c5Over = Number((paid - total).toFixed(2));
            const v5530c5HasCash = payments.some(function (payment) {
                const label = String(payment.payment_label || '').toLowerCase();

                return payment.is_cash_frontend === true
                    || label.includes('efectivo')
                    || label.includes('cash');
            });

            if (paid + 0.01 < total) {
                warn('El pago recibido es menor al total. Total: ' + money(total) + ' / Recibido: ' + money(paid));
                return;
            }

            if (v5530c5Over > 0.01 && !v5530c5HasCash) {
                warn('Solo el pago en efectivo puede ser mayor al total para calcular cambio.');
                return;
            }

            const button = confirmBtn();

            if (button) {
                button.disabled = true;
                button.textContent = 'Registrando...';
            }

            try {
                const data = await jsonFetch('/pos/orders/' + order.id + '/pay', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({
                        payments: payments,
                    }),
                });
                }

                if (window.BEXIA_POS_CART_API && typeof window.BEXIA_POS_CART_API.getItems === 'function') {
                    const v5481e5PaidItems = window.BEXIA_POS_CART_API.getItems();

                    if (typeof window.BEXIA_POS_REFRESH_STOCK_TEXT_ONLY === 'function') {
                        await window.BEXIA_POS_REFRESH_STOCK_TEXT_ONLY(v5481e5PaidItems);
                    }
                }

                if (window.BEXIA_POS_CART_API && typeof window.BEXIA_POS_CART_API.clear === 'function') {
                    window.BEXIA_POS_CART_API.clear();
                }

                window.BEXIA_POS_LOADED_PENDING_ORDER = null;

                closeModal();

                if (data.print_url) {
                    window.open(data.print_url, '_blank', 'width=420,height=720');
                }

                warn('Cobro registrado correctamente: ' + (data.number || order.number || ''));
            } catch (error) {
                warn(error.message || 'No se pudo registrar el cobro.');
            } finally {
                if (button) {
                    button.disabled = false;
                    button.textContent = 'Registrar cobro';
                }
            }
        }

        document.addEventListener('click', function (event) {
            const chargeButton = event.target.closest ? event.target.closest('#v5349-charge-ticket') : null;

            if (chargeButton) {
                openPaymentFlow(event);
                return;
            }

            const confirmButton = event.target.closest ? event.target.closest('#v5335-payment-confirm') : null;

            if (confirmButton) {
                registerPayment(event);
                return;
            }

            const closeButton = event.target.closest ? event.target.closest('#v5335-payment-close, #v5335-payment-cancel') : null;

            if (closeButton) {
                event.preventDefault();
                closeModal();
            }
        }, true);

        modal()?.addEventListener('click', function (event) {
            if (event.target === modal()) {
                closeModal();
            }
        });
    })();
    // V5.44.8 split payment inside v5349 end


});
</script>




<style id="v5390-clear-cart-modal-style">
    .v5390-clear-cart-backdrop {
        position: fixed;
        inset: 0;
        z-index: 10080;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 22px;
        background: rgba(15, 23, 42, .48);
    }

    .v5390-clear-cart-backdrop.is-open {
        display: flex;
    }

    .v5390-clear-cart-modal {
        width: min(430px, 94vw);
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 22px;
        box-shadow: 0 24px 80px rgba(15, 23, 42, .35);
        overflow: hidden;
    }

    .v5390-clear-cart-header {
        padding: 20px 22px 10px 22px;
    }

    .v5390-clear-cart-title {
        margin: 0;
        color: #0f172a;
        font-size: 20px;
        font-weight: 950;
        line-height: 1.15;
    }

    .v5390-clear-cart-body {
        padding: 0 22px 20px 22px;
        color: #64748b;
        font-size: 14px;
        font-weight: 750;
        line-height: 1.45;
    }

    .v5390-clear-cart-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 16px 22px 20px 22px;
        border-top: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .v5390-clear-cart-cancel,
    .v5390-clear-cart-confirm {
        height: 42px;
        border-radius: 14px;
        padding: 0 16px;
        font-weight: 900;
        cursor: pointer;
    }

    .v5390-clear-cart-cancel {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #0f172a;
    }

    .v5390-clear-cart-confirm {
        border: 1px solid #dc2626;
        background: #dc2626;
        color: #ffffff;
    }
</style>

<div id="v5390-clear-cart-modal" class="v5390-clear-cart-backdrop" aria-hidden="true">
    <div class="v5390-clear-cart-modal" role="dialog" aria-modal="true" aria-labelledby="v5390-clear-cart-title">
        <div class="v5390-clear-cart-header">
            <h2 id="v5390-clear-cart-title" class="v5390-clear-cart-title">Vaciar carrito</h2>
        </div>

        <div class="v5390-clear-cart-body">
            Se eliminarán todos los productos agregados al carrito actual. Esta acción no elimina tickets pendientes ya creados.
        </div>

        <div class="v5390-clear-cart-actions">
            <button type="button" class="v5390-clear-cart-cancel" id="v5390-clear-cart-cancel">Cancelar</button>
            <button type="button" class="v5390-clear-cart-confirm" id="v5390-clear-cart-confirm">Sí, vaciar</button>
        </div>
    </div>
</div>

<script id="v5390-clear-cart-modal-script">
(function () {
    window.BEXIA_POS_CLEAR_CART_CONFIRMED = false;
    window.BEXIA_POS_CLEAR_CART_BYPASS = false;

    function modal() {
        return document.getElementById('v5390-clear-cart-modal');
    }

    function openModal() {
        var box = modal();

        if (!box) {
            return;
        }

        box.classList.add('is-open');
        box.setAttribute('aria-hidden', 'false');

        setTimeout(function () {
            var cancel = document.getElementById('v5390-clear-cart-cancel');

            if (cancel) {
                cancel.focus();
            }
        }, 50);
    }

    function closeModal() {
        var box = modal();

        if (!box) {
            return;
        }

        box.classList.remove('is-open');
        box.setAttribute('aria-hidden', 'true');
    }

    window.BEXIA_POS_SHOW_CLEAR_CART_CONFIRM = openModal;

    document.addEventListener('click', function (event) {
        var clearButton = event.target.closest && event.target.closest('#v5339-cart-clear');

        if (!clearButton) {
            return;
        }

        if (window.BEXIA_POS_CLEAR_CART_BYPASS || window.BEXIA_POS_CLEAR_CART_CONFIRMED) {
            window.BEXIA_POS_CLEAR_CART_BYPASS = false;
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        openModal();
    }, true);

    document.addEventListener('click', function (event) {
        if (event.target.closest && event.target.closest('#v5390-clear-cart-cancel')) {
            event.preventDefault();
            closeModal();
            return;
        }

        if (event.target.closest && event.target.closest('#v5390-clear-cart-confirm')) {
            event.preventDefault();

            closeModal();

            window.BEXIA_POS_CLEAR_CART_CONFIRMED = true;
            window.BEXIA_POS_CLEAR_CART_BYPASS = true;

            var clearButton = document.getElementById('v5339-cart-clear');

            if (clearButton) {
                clearButton.click();
            }

            setTimeout(function () {
                window.BEXIA_POS_CLEAR_CART_CONFIRMED = false;
                window.BEXIA_POS_CLEAR_CART_BYPASS = false;
            }, 250);

            return;
        }

        var box = modal();

        if (box && event.target === box) {
            closeModal();
        }
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    }, true);
})();
</script>


<style id="v5391b-customer-modal-style">
    .v5391b-customer-backdrop {
        position: fixed;
        inset: 0;
        z-index: 10090;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 22px;
        background: rgba(15, 23, 42, .48);
    }

    .v5391b-customer-backdrop.is-open {
        display: flex;
    }

    .v5391b-customer-modal {
        width: min(720px, 96vw);
        max-height: min(720px, 92vh);
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 22px;
        box-shadow: 0 24px 80px rgba(15, 23, 42, .35);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .v5391b-customer-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 20px 22px 14px 22px;
        border-bottom: 1px solid #e2e8f0;
    }

    .v5391b-customer-title {
        margin: 0;
        font-size: 22px;
        font-weight: 950;
        color: #0f172a;
    }

    .v5391b-customer-close {
        height: 38px;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        background: #ffffff;
        padding: 0 14px;
        font-weight: 900;
        cursor: pointer;
    }

    .v5391b-customer-search {
        padding: 16px 22px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .v5391b-customer-search input {
        width: 100%;
        height: 42px;
        border: 1px solid #dbe3ef;
        border-radius: 14px;
        padding: 9px 13px;
        font-size: 14px;
        font-weight: 800;
        outline: none;
        background: #fff;
        color: #0f172a;
    }

    .v5391b-customer-search input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
    }

    .v5391b-customer-list {
        overflow: auto;
        padding: 10px 22px 20px 22px;
    }

    .v5391b-customer-row {
        width: 100%;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        border-radius: 14px;
        padding: 12px 14px;
        margin-top: 10px;
        text-align: left;
        cursor: pointer;
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 8px;
    }

    .v5391b-customer-row:hover {
        border-color: #2563eb;
        background: #eff6ff;
    }

    .v5391b-customer-row strong {
        display: block;
        color: #0f172a;
        font-weight: 950;
        font-size: 14px;
    }

    .v5391b-customer-meta {
        color: #64748b;
        font-size: 12px;
        font-weight: 750;
        margin-top: 4px;
    }

    .v5391b-change-customer {
        margin-top: 10px;
        width: 100%;
        height: 36px;
        border: 1px solid #dbe3ef;
        border-radius: 12px;
        background: #ffffff;
        color: #2563eb;
        font-weight: 950;
        cursor: pointer;
    }

    .v5391b-change-customer:hover {
        background: #eff6ff;
        border-color: #2563eb;
    }
</style>

<div id="v5391b-customer-modal" class="v5391b-customer-backdrop" aria-hidden="true">
    <div class="v5391b-customer-modal" role="dialog" aria-modal="true" aria-labelledby="v5391b-customer-title">
        <div class="v5391b-customer-header">
            <h2 id="v5391b-customer-title" class="v5391b-customer-title">Seleccionar cliente</h2>
            <button type="button" class="v5391b-customer-close" id="v5391b-customer-close">Cerrar</button>
        </div>

        <div class="v5391b-customer-search">
            <input id="v5391b-customer-search-input" type="text" placeholder="Buscar por nombre, RFC o correo..." autocomplete="off">
        </div>

        <div id="v5391b-customer-list" class="v5391b-customer-list">
            <div style="padding:14px;color:#64748b;font-weight:800;">Cargando clientes...</div>
        </div>
    </div>
</div>

<script id="v5391b-customer-modal-script">
(function () {
    window.BEXIA_POS_SELECTED_CUSTOMER_ID = @json($customer->id ?? null);
    window.BEXIA_POS_SELECTED_CUSTOMER = {
        id: @json($customer->id ?? null),
        name: @json($customer->name ?? 'Público en General'),
        rfc: @json($customer->rfc ?? 'XAXX010101000'),
        email: @json($customer->email ?? null),
        phone: @json($customer->phone ?? null)
    };

    function sessionId() {
        var match = window.location.pathname.match(/\/pos\/sessions\/(\d+)\/screen/);
        return match ? match[1] : null;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function notice(message, type) {
        type = type || 'info';

        var box = document.getElementById('v5356-pos-notice');

        if (box) {
            box.textContent = message;
            box.className = 'v5356-pos-notice';

            if (type === 'warning') box.classList.add('is-warning');
            if (type === 'error') box.classList.add('is-error');

            box.style.display = 'block';

            window.clearTimeout(window.v5391bNoticeTimer);
            window.v5391bNoticeTimer = window.setTimeout(function () {
                box.style.display = 'none';
            }, 3500);

            return;
        }

        alert(message);
    }

    function modal() {
        return document.getElementById('v5391b-customer-modal');
    }

    function openModal() {
        var box = modal();

        if (!box) return;

        box.classList.add('is-open');
        box.setAttribute('aria-hidden', 'false');

        loadCustomers('');

        setTimeout(function () {
            document.getElementById('v5391b-customer-search-input')?.focus();
        }, 80);
    }

    function closeModal() {
        var box = modal();

        if (!box) return;

        box.classList.remove('is-open');
        box.setAttribute('aria-hidden', 'true');
    }

    async function fetchJson(url) {
        var response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        var data = await response.json().catch(function () {
            return {};
        });

        if (!response.ok || data.ok === false) {
            throw new Error(data.message || 'No se pudieron cargar los clientes.');
        }

        return data;
    }

    async function loadCustomers(query) {
        var list = document.getElementById('v5391b-customer-list');
        var sid = sessionId();

        if (!list || !sid) return;

        list.innerHTML = '<div style="padding:14px;color:#64748b;font-weight:800;">Cargando clientes...</div>';

        try {
            var data = await fetchJson('/pos/sessions/' + sid + '/customers?q=' + encodeURIComponent(query || ''));
            var customers = data.customers || [];

            if (!customers.length) {
                list.innerHTML = '<div style="padding:14px;color:#64748b;font-weight:800;">No se encontraron clientes.</div>';
                return;
            }

            list.innerHTML = customers.map(function (customer) {
                var meta = [];

                if (customer.rfc) meta.push('RFC: ' + customer.rfc);
                if (customer.email) meta.push(customer.email);

                return '' +
                    '<button type="button" class="v5391b-customer-row" data-customer-id="' + customer.id + '">' +
                        '<span>' +
                            '<strong>' + escapeHtml(customer.name) + '</strong>' +
                            '<span class="v5391b-customer-meta">' + escapeHtml(meta.join(' · ')) + '</span>' +
                        '</span>' +
                        '<span style="color:#2563eb;font-weight:950;">Elegir</span>' +
                    '</button>';
            }).join('');

            customers.forEach(function (customer) {
                var row = list.querySelector('[data-customer-id="' + customer.id + '"]');

                if (!row) return;

                row.addEventListener('click', function () {
                    selectCustomer(customer);
                });
            });
        } catch (error) {
            list.innerHTML = '<div style="padding:14px;color:#b91c1c;font-weight:800;">' + escapeHtml(error.message || 'Error al cargar clientes.') + '</div>';
        }
    }

    function updateCustomerBox(customer) {
        var box = document.querySelector('aside.cart .client');

        if (!box) return;

        var value = box.querySelector('.value');

        if (value) {
            value.textContent = customer.name || 'Público en General';
        }

        // Quitar líneas dinámicas duplicadas creadas por versiones anteriores.
        box.querySelectorAll('[data-v5391b-customer-rfc]').forEach(function (node) {
            node.remove();
        });

        // Reutilizar el RFC original del bloque cliente si existe.
        var rfcLine = box.querySelector('[data-v5392-customer-rfc]');

        if (!rfcLine) {
            rfcLine = Array.from(box.children).find(function (node) {
                return node.textContent && node.textContent.trim().startsWith('RFC:');
            });

            if (rfcLine) {
                rfcLine.setAttribute('data-v5392-customer-rfc', '1');
            }
        }

        if (!rfcLine) {
            rfcLine = document.createElement('div');
            rfcLine.setAttribute('data-v5392-customer-rfc', '1');
            rfcLine.style.color = '#64748b';
            rfcLine.style.fontSize = '13px';
            rfcLine.style.marginTop = '6px';
            box.insertBefore(rfcLine, document.getElementById('v5391b-change-customer') || null);
        }

        if (customer.rfc) {
            rfcLine.textContent = 'RFC: ' + customer.rfc;
            rfcLine.style.display = '';
        } else {
            rfcLine.textContent = '';
            rfcLine.style.display = 'none';
        }
    }

    function ensureCustomerButton() {
        var box = document.querySelector('aside.cart .client');

        if (!box || document.getElementById('v5391b-change-customer')) return;

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'v5391b-change-customer';
        btn.className = 'v5391b-change-customer';
        btn.textContent = 'Cambiar cliente';

        btn.addEventListener('click', function (event) {
            event.preventDefault();
            openModal();
        });

        box.appendChild(btn);
    }

    function selectCustomer(customer) {
        window.BEXIA_POS_SELECTED_CUSTOMER_ID = customer.id;
        window.BEXIA_POS_SELECTED_CUSTOMER = customer;

        updateCustomerBox(customer);
        closeModal();

        notice('Cliente seleccionado: ' + customer.name, 'info');

        if (typeof window.BEXIA_POS_APPLY_CUSTOMER_PRICE_LIST === 'function') {
            window.BEXIA_POS_APPLY_CUSTOMER_PRICE_LIST(customer, 'v5391b-select-customer')
                .then(function (applied) {
                    if (applied) {
                        notice('Cliente seleccionado: ' + customer.name + '. Lista de precios actualizada.', 'info');
                    }
                })
                .catch(function (error) {
                    console.error(error);
                    notice(error.message || 'No se pudo actualizar la lista de precios del cliente.', 'warning');
                });
        }
    }

    var searchTimer = null;

    document.addEventListener('input', function (event) {
        var input = event.target.closest && event.target.closest('#v5391b-customer-search-input');

        if (!input) return;

        window.clearTimeout(searchTimer);

        searchTimer = window.setTimeout(function () {
            loadCustomers(input.value || '');
        }, 250);
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    }, true);

    document.addEventListener('click', function (event) {
        if (event.target.closest && event.target.closest('#v5391b-customer-close')) {
            event.preventDefault();
            closeModal();
            return;
        }

        var box = modal();

        if (box && event.target === box) {
            closeModal();
        }
    }, true);

    document.addEventListener('DOMContentLoaded', function () {
        ensureCustomerButton();
        updateCustomerBox(window.BEXIA_POS_SELECTED_CUSTOMER || {});
    });

    ensureCustomerButton();
    updateCustomerBox(window.BEXIA_POS_SELECTED_CUSTOMER || {});
})();
</script>


<script id="v5393-pending-customer-script">
(function () {
    function notice(message, type) {
        type = type || 'info';

        var box = document.getElementById('v5356-pos-notice');

        if (box) {
            box.textContent = message;
            box.className = 'v5356-pos-notice';

            if (type === 'warning') box.classList.add('is-warning');
            if (type === 'error') box.classList.add('is-error');

            box.style.display = 'block';

            window.clearTimeout(window.v5393NoticeTimer);
            window.v5393NoticeTimer = window.setTimeout(function () {
                box.style.display = 'none';
            }, 3500);

            return;
        }

        alert(message);
    }

    function applyCustomerToCart(customer) {
        if (!customer || !customer.id) {
            return;
        }

        window.BEXIA_POS_SELECTED_CUSTOMER_ID = customer.id;
        window.BEXIA_POS_SELECTED_CUSTOMER = customer;

        var box = document.querySelector('aside.cart .client');

        if (!box) {
            return;
        }

        var value = box.querySelector('.value');

        if (value) {
            value.textContent = customer.name || 'Público en General';
        }

        box.querySelectorAll('[data-v5391b-customer-rfc]').forEach(function (node) {
            node.remove();
        });

        var rfcLine = box.querySelector('[data-v5392-customer-rfc]');

        if (!rfcLine) {
            rfcLine = Array.from(box.children).find(function (node) {
                return node.textContent && node.textContent.trim().startsWith('RFC:');
            });

            if (rfcLine) {
                rfcLine.setAttribute('data-v5392-customer-rfc', '1');
            }
        }

        if (!rfcLine) {
            rfcLine = document.createElement('div');
            rfcLine.setAttribute('data-v5392-customer-rfc', '1');
            rfcLine.style.color = '#64748b';
            rfcLine.style.fontSize = '13px';
            rfcLine.style.marginTop = '6px';

            var changeButton = document.getElementById('v5391b-change-customer');
            box.insertBefore(rfcLine, changeButton || null);
        }

        if (customer.rfc) {
            rfcLine.textContent = 'RFC: ' + customer.rfc;
            rfcLine.style.display = '';
        } else {
            rfcLine.textContent = '';
            rfcLine.style.display = 'none';
        }
    }

    window.BEXIA_POS_APPLY_CUSTOMER_FROM_PENDING_ORDER = function (order) {
        if (!order) {
            return;
        }

        var customer = order.customer || null;

        if (!customer && order.customer_id) {
            customer = {
                id: order.customer_id,
                name: order.customer_name || 'Cliente seleccionado',
                rfc: order.customer_rfc || '',
                email: order.customer_email || '',
                phone: order.customer_phone || ''
            };
        }

        if (customer && customer.id) {
            applyCustomerToCart(customer);
            notice('Cliente cargado: ' + (customer.name || 'Cliente'), 'info');
        }
    };
})();
</script>




<script id="v5399-create-pending-initial-print-script">
(function () {
    var csrfToken = @json(csrf_token());

    function sessionId() {
        var match = window.location.pathname.match(/\/pos\/sessions\/(\d+)\/screen/);
        return match ? match[1] : null;
    }

    function notice(message, type) {
        type = type || 'info';

        var box = document.getElementById('v5356-pos-notice');

        if (box) {
            box.textContent = message;
            box.className = 'v5356-pos-notice';

            if (type === 'warning') box.classList.add('is-warning');
            if (type === 'error') box.classList.add('is-error');

            box.style.display = 'block';

            window.clearTimeout(window.v5399NoticeTimer);
            window.v5399NoticeTimer = window.setTimeout(function () {
                box.style.display = 'none';
            }, 4500);

            return;
        }

        alert(message);
    }

    function cartApi() {
        return window.BEXIA_POS_CART_API || null;
    }

    function cartItems() {
        var api = cartApi();

        if (!api || typeof api.getItems !== 'function') {
            return [];
        }

        return api.getItems() || [];
    }

    function clearCartWithoutConfirm() {
        var clearButton = document.getElementById('v5339-cart-clear');

        window.BEXIA_POS_CLEAR_CART_CONFIRMED = true;
        window.BEXIA_POS_CLEAR_CART_BYPASS = true;

        if (clearButton) {
            clearButton.click();

            setTimeout(function () {
                window.BEXIA_POS_CLEAR_CART_CONFIRMED = false;
                window.BEXIA_POS_CLEAR_CART_BYPASS = false;
            }, 500);

            return;
        }

        var api = cartApi();

        if (api && typeof api.clear === 'function') {
            api.clear();
        } else if (api && typeof api.clearCart === 'function') {
            api.clearCart();
        } else if (api && typeof api.loadPendingOrderItems === 'function') {
            api.loadPendingOrderItems([]);
        }

        setTimeout(function () {
            window.BEXIA_POS_CLEAR_CART_CONFIRMED = false;
            window.BEXIA_POS_CLEAR_CART_BYPASS = false;
        }, 500);
    }

    function openPreparingWindow() {
        var win = window.open('', '_blank', 'width=420,height=720');

        if (!win) {
            return null;
        }

        win.document.open();
        win.document.write(
            '<!doctype html>' +
            '<html><head><meta charset="utf-8"><title>Preparando ticket</title></head>' +
            '<body style="font-family:Arial;text-align:center;padding:30px;">' +
            '<strong>Preparando ticket pendiente...</strong><br><span>Espere un momento.</span>' +
            '
</body></html>'
        );
        win.document.close();

        return win;
    }

    function isCreatePendingButton(target) {
        var button = target.closest && target.closest(
            '#v5349-create-pending-ticket, ' +
            '.v5349-create-pending-ticket, ' +
            '#v5348b-create-pending-ticket, ' +
            '.v5348b-create-pending-ticket, ' +
            'button, a'
        );

        if (!button) {
            return null;
        }

        var text = (button.textContent || '').toLowerCase();

        if (
            button.id === 'v5349-create-pending-ticket' ||
            button.id === 'v5348b-create-pending-ticket' ||
            button.classList.contains('v5349-create-pending-ticket') ||
            button.classList.contains('v5348b-create-pending-ticket') ||
            text.includes('crear ticket pendiente')
        ) {
            return button;
        }

        return null;
    }

    async function createPendingTicket(button) {
        var sid = sessionId();

        if (!sid) {
            notice('No se pudo identificar la sesión del PDV.', 'error');
            return;
        }

        // BEXIA_V5829E_SAVE_OVER_LOADED_PENDING_CAPTURE
        var loadedPendingOrder = window.BEXIA_POS_LOADED_PENDING_ORDER || null;
        var isUpdatingPending = Boolean(loadedPendingOrder && loadedPendingOrder.id);

        var api = window.BEXIA_POS_CART_API || null;
        var items = [];

        if (api && typeof api.getItems === 'function') {
            items = api.getItems() || [];
        } else if (typeof cartItems === 'function') {
            items = cartItems() || [];
        }

        if (!Array.isArray(items) || !items.length) {
            notice('Agrega productos antes de crear el ticket pendiente.', 'warning');
            return;
        }

        button = button || document.getElementById('v5349-create-pending-ticket');

        var printWindow = null;

        try {
            if (typeof openPreparingWindow === 'function') {
                printWindow = openPreparingWindow();
            } else {
                printWindow = window.open('', '_blank', 'width=420,height=720');

                if (printWindow) {
                    printWindow.document.open();
                    printWindow.document.write(
                        '<!doctype html>' +
                        '<html><head><meta charset="utf-8"><title>Preparando ticket</title></head>' +
                        '<body style="font-family:Arial;text-align:center;padding:30px;">' +
                        '<strong>Preparando ticket pendiente...</strong><br><span>Espere un momento.</span>' +
                        '</body></html>'
                    );
                    printWindow.document.close();
                }
            }
        } catch (error) {
            printWindow = null;
        }

        var originalHtml = button ? button.innerHTML : '';

        if (button) {
            button.disabled = true;
            button.innerHTML = isUpdatingPending ? 'Guardando cambios...' : 'Creando ticket...';
        }

        try {
            var csrf = window.csrfToken
                || window.csrf
                || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || '';

            var payload = {
                items: items,
                customer_id: window.BEXIA_POS_SELECTED_CUSTOMER_ID || null,
                price_list_id: window.BEXIA_POS_SELECTED_PRICE_LIST_ID || null,
                price_list_name: window.BEXIA_POS_SELECTED_PRICE_LIST_NAME || null,
                selected_price_list_id: window.BEXIA_POS_SELECTED_PRICE_LIST_ID || null,
                selected_price_list_name: window.BEXIA_POS_SELECTED_PRICE_LIST_NAME || null,
                payment_label: null,
                payment_form_id: null,
                status: 'pending_payment',
                order_note: api && typeof api.getNote === 'function' ? api.getNote() : '',
                discount: api && typeof api.getDiscount === 'function' ? api.getDiscount() : null,
                total: api && typeof api.getTotal === 'function' ? api.getTotal() : null,
                pos_session_id: Number(sid)
            };

            var pendingEndpoint = isUpdatingPending
                ? '/pos/orders/' + encodeURIComponent(loadedPendingOrder.id) + '/pending-update'
                : '/pos/sessions/' + encodeURIComponent(sid) + '/orders';

            var response = await fetch(pendingEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify(payload)
            });

            var data = await response.json().catch(function () {
                return {};
            });

            if (!response.ok || data.ok === false) {
                throw new Error(data.message || 'No se pudo crear el ticket pendiente.');
            }

            var orderId = data.order_id || data.id || (data.order && data.order.id) || null;
            var number = data.number || (data.order && data.order.number) || 'folio generado';
            var printUrl = data.print_url || (orderId ? '/pos/orders/' + orderId + '/pending-ticket/print?initial=1' : '');

            window.BEXIA_POS_LOADED_PENDING_ORDER = null;

            if (typeof clearCartWithoutConfirm === 'function') {
                clearCartWithoutConfirm();
            } else if (api && typeof api.clear === 'function') {
                api.clear();
            }

            notice(
                (isUpdatingPending ? 'Ticket pendiente actualizado: ' : 'Ticket pendiente creado: ') + number,
                'info'
            );

            if (printUrl) {
                if (printWindow && !printWindow.closed) {
                    printWindow.location.href = printUrl;
                    printWindow.focus();
                } else {
                    window.open(printUrl, '_blank', 'width=420,height=720');
                }
            } else if (printWindow && !printWindow.closed) {
                printWindow.close();
            }
        } catch (error) {
            console.error(error);

            if (printWindow && !printWindow.closed) {
                printWindow.close();
            }

            notice(error.message || 'No se pudo crear el ticket pendiente.', 'error');
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        }
    }

    document.addEventListener('click', function (event) {
        var button = isCreatePendingButton(event.target);

        if (!button) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        createPendingTicket(button);
    }, true);
})();
</script>

<script id="v5388-pending-search-final-script">
(function () {
    function sessionId() {
        var match = window.location.pathname.match(/\/pos\/sessions\/(\d+)\/screen/);
        return match ? match[1] : null;
    }

    function notice(message, type) {
        type = type || 'info';

        var box = document.getElementById('v5356-pos-notice');

        if (box) {
            box.textContent = message;
            box.className = 'v5356-pos-notice';

            if (type === 'warning') {
                box.classList.add('is-warning');
            }

            if (type === 'error') {
                box.classList.add('is-error');
            }

            box.style.display = 'block';

            window.clearTimeout(window.v5388NoticeTimer);
            window.v5388NoticeTimer = window.setTimeout(function () {
                box.style.display = 'none';
            }, 4500);

            return;
        }

        alert(message);
    }

    function normalizeTicket(value) {
        value = String(value || '').trim();

        try {
            if (value.indexOf('://') !== -1) {
                var url = new URL(value);
                var ticket = url.searchParams.get('ticket') || url.searchParams.get('folio');

                if (ticket) {
                    value = ticket;
                } else {
                    var parts = url.pathname.split('/').filter(Boolean);

                    if (parts.length) {
                        value = parts[parts.length - 1];
                    }
                }
            }
        } catch (error) {}

        value = String(value || '').trim();

        value = value
            .replace(/[’'`´]/g, '-')
            .replace(/[\s_]+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '')
            .toUpperCase();

        var compact = value.match(/^([A-Z]+)(\d{8})(\d{5})$/);

        if (compact) {
            value = compact[1] + '-' + compact[2] + '-' + compact[3];
        }

        return value;
    }

    async function fetchJson(url) {
        var response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        var data = await response.json().catch(function () {
            return {};
        });

        if (!response.ok || data.ok === false) {
            throw new Error(data.message || 'No se pudo buscar el ticket pendiente.');
        }

        return data;
    }

    function closeModal() {
        var modal = document.getElementById('v5350-pending-tickets-modal');

        if (modal) {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    function loadToCart(order) {
        var lines = order.items || order.lines || [];

        if (!lines.length) {
            notice('El ticket pendiente no tiene productos para cargar.', 'warning');
            return false;
        }

        if (!window.BEXIA_POS_CART_API || typeof window.BEXIA_POS_CART_API.loadPendingOrderItems !== 'function') {
            notice('No se encontró la función para cargar el ticket al carrito.', 'error');
            return false;
        }

        window.BEXIA_POS_LOADED_PENDING_ORDER = order;
        if (typeof window.BEXIA_POS_APPLY_CUSTOMER_FROM_PENDING_ORDER === 'function') {
            window.BEXIA_POS_APPLY_CUSTOMER_FROM_PENDING_ORDER(order);
        }
        window.BEXIA_POS_CART_API.loadPendingOrderItems(lines);

        notice('Ticket ' + order.number + ' cargado al carrito.', 'info');
        closeModal();

        return true;
    }

    window.BEXIA_POS_NORMALIZE_PENDING_TICKET = normalizeTicket;

    window.BEXIA_POS_SEARCH_PENDING_TICKET = async function () {
        var input = document.getElementById('v5383-pending-qr-input');
        var button = document.getElementById('v5384-pending-qr-search-button');

        if (!input) {
            notice('No se encontró el campo para buscar ticket.', 'error');
            return;
        }

        var folio = normalizeTicket(input.value);

        if (!folio) {
            notice('Escanea el QR o escribe el número de ticket.', 'warning');
            input.focus();
            return;
        }

        input.value = folio;

        var sid = sessionId();

        if (!sid) {
            notice('No se pudo identificar la sesión del PDV.', 'error');
            return;
        }

        input.disabled = true;

        if (button) {
            button.disabled = true;
            button.textContent = 'Buscando...';
        }

        try {
            var data = await fetchJson('/pos/sessions/' + sid + '/pending-orders-search?ticket=' + encodeURIComponent(folio));

            if (!data.order) {
                throw new Error('No se encontró el ticket pendiente.');
            }

            input.value = '';
            loadToCart(data.order);
        } catch (error) {
            notice(error.message || 'No se encontró el ticket pendiente.', 'error');
        } finally {
            input.disabled = false;

            if (button) {
                button.disabled = false;
                button.textContent = 'Buscar';
            }

            input.focus();
        }
    };

    document.addEventListener('input', function (event) {
        var input = event.target.closest && event.target.closest('#v5383-pending-qr-input');

        if (!input) {
            return;
        }

        var normalized = normalizeTicket(input.value);

        if (normalized !== input.value) {
            input.value = normalized;
        }
    }, true);

    document.addEventListener('click', function (event) {
        var button = event.target.closest && event.target.closest('#v5384-pending-qr-search-button');

        if (!button) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        window.BEXIA_POS_SEARCH_PENDING_TICKET();
    }, true);

    document.addEventListener('keydown', function (event) {
        var input = event.target.closest && event.target.closest('#v5383-pending-qr-input');

        if (!input || event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        window.BEXIA_POS_SEARCH_PENDING_TICKET();
    }, true);
})();
</script>


<script id="v5481e5-stock-refresh-main-page">
(function () {
    if (window.BEXIA_POS_STOCK_REFRESH_E5_READY) {
        return;
    }

    window.BEXIA_POS_STOCK_REFRESH_E5_READY = true;

    function sessionId() {
        const match = window.location.pathname.match(/\/pos\/sessions\/(\d+)\/screen/);
        return match ? match[1] : null;
    }

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        const input = document.querySelector('input[name="_token"]');

        return (meta && meta.content)
            || (input && input.value)
            || @json(csrf_token());
    }

    function idsFromItems(items) {
        const ids = [];

        (items || []).forEach(function (item) {
            const raw = item && typeof item === 'object'
                ? (item.product_id || item.id)
                : item;

            const id = Number(raw);

            if (Number.isFinite(id) && id > 0 && !ids.includes(id)) {
                ids.push(id);
            }
        });

        return ids;
    }

    function formatStock(value) {
        return Number(value || 0).toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function updateCardStockText(productId, stock) {
        const card = document.querySelector('.product[data-product-id="' + String(productId) + '"]');

        if (!card) {
            return;
        }

        const isService = card.dataset.productIsService === '1' || card.dataset.productType === 'service';

        card.dataset.productStock = String(stock);

        const stockEl = card.querySelector('.stock');

        if (!stockEl || isService) {
            return;
        }

        stockEl.classList.remove('no-stock');
        stockEl.dataset.v5481e5Refreshed = '1';
        stockEl.textContent = 'Stock: ' + formatStock(stock);

        stockEl.style.color = '#475569';
        stockEl.style.fontSize = '9px';
        stockEl.style.marginTop = '2px';
        stockEl.style.lineHeight = '1.05';
        stockEl.style.fontWeight = '400';

        if (Number(stock) > 0) {
            Array.from(card.querySelectorAll('.no-stock')).forEach(function (el) {
                if (el !== stockEl && String(el.textContent || '').toLowerCase().includes('sin existencia')) {
                    el.style.display = 'none';
                }
            });
        }
    }

    window.BEXIA_POS_REFRESH_STOCK_TEXT_ONLY = async function (itemsOrIds) {
        const sid = sessionId();
        const productIds = idsFromItems(itemsOrIds);

        if (!sid || !productIds.length) {
            return null;
        }

        try {
            const response = await fetch('/pos/sessions/' + sid + '/stock-refresh', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    product_ids: productIds,
                }),
            });

            const data = await response.json().catch(function () {
                return {};
            });

            if (!response.ok || data.ok === false) {
                return data;
            }

            Object.entries(data.stocks || {}).forEach(function ([productId, payload]) {
                updateCardStockText(productId, Number(payload.stock ?? payload.quantity ?? 0));
            });

            return data;
        } catch (error) {
            console.warn('BEXIA POS stock refresh E5 failed', error);
            return null;
        }
    };
})();
</script>


<script id="v5481f-sync-charge-total-fallback">
document.addEventListener('DOMContentLoaded', function () {
    function money(value) {
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(Number(value || 0));
    }

    function syncChargeTotal() {
        const api = window.BEXIA_POS_CART_API || null;
        const total = api && typeof api.getTotal === 'function' ? api.getTotal() : 0;

        const oldTotal = document.getElementById('v5339-charge-total');
        const newTotal = document.getElementById('v5349-charge-total');

        if (oldTotal) oldTotal.textContent = money(total);
        if (newTotal) newTotal.textContent = money(total);
    }

    document.addEventListener('click', function (event) {
        if (
            event.target.closest('.product') ||
            event.target.closest('.v5339-cart-controls') ||
            event.target.closest('#v5339-cart-clear') ||
            event.target.closest('[data-v5360-charge]') ||
            event.target.closest('[data-v5360-load]') ||
            event.target.closest('#v5335-payment-confirm')
        ) {
            window.setTimeout(syncChargeTotal, 80);
            window.setTimeout(syncChargeTotal, 300);
        }
    }, true);

    window.BEXIA_POS_SYNC_CHARGE_TOTAL = syncChargeTotal;

    window.setTimeout(syncChargeTotal, 250);
});
</script>




<script id="v5481i-payment-modal-buttons-stable">
document.addEventListener('DOMContentLoaded', function () {
    if (window.BEXIA_POS_PAYMENT_MODAL_V5481I_READY) {
        return;
    }

    window.BEXIA_POS_PAYMENT_MODAL_V5481I_READY = true;

    const formatter = new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    let methods = [];

    function money(value) {
        return formatter.format(Number(value || 0));
    }

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        const input = document.querySelector('input[name="_token"]');

        return (meta && meta.content)
            || (input && input.value)
            || @json(csrf_token());
    }

    function sessionId() {
        const match = window.location.pathname.match(/\/pos\/sessions\/(\d+)\/screen/);
        return match ? match[1] : null;
    }

    function modal() {
        return document.getElementById('v5335-payment-modal');
    }

    function optionsBox() {
        return document.getElementById('v5335-payment-options');
    }

    function totalBox() {
        return document.getElementById('v5335-payment-total');
    }

    function confirmButton() {
        return document.getElementById('v5335-payment-confirm');
    }

    function currentOrder() {
        return window.BEXIA_POS_LOADED_PENDING_ORDER || null;
    }

    function currentTotal() {
        /*
         * Para tickets pendientes cargados, el total que manda es el actual del carrito,
         * porque el usuario puede aplicar descuento antes de cobrar.
         */
        const api = window.BEXIA_POS_CART_API || null;

        if (api && typeof api.getTotal === 'function') {
            const cartTotal = Number(api.getTotal() || 0);

            if (Number.isFinite(cartTotal) && cartTotal > 0) {
                return cartTotal;
            }
        }

        const order = currentOrder();

        if (order && Number(order.total || 0) > 0) {
            return Number(order.total || 0);
        }

        return 0;
    }

    function warn(message) {
        const api = window.BEXIA_POS_CART_API || null;

        if (api && typeof api.setWarning === 'function') {
            api.setWarning(message);
            return;
        }

        alert(message);
    }

    async function getJson(url, options) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...(options || {}),
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...((options && options.headers) || {}),
            },
        });

        const data = await response.json().catch(function () {
            return {};
        });

        if (!response.ok || data.ok === false) {
            throw new Error(data.message || 'No se pudo completar la operación.');
        }

        return data;
    }

    function openModal() {
        const m = modal();

        if (!m) {
            warn('No se encontró el modal de cobro.');
            return;
        }

        m.classList.add('is-open');
        m.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        const m = modal();

        if (!m) {
            return;
        }

        m.classList.remove('is-open');
        m.setAttribute('aria-hidden', 'true');
    }

    async function loadMethods() {
        const sid = sessionId();

        if (!sid) {
            throw new Error('No se pudo identificar la sesión del PDV.');
        }

        const data = await getJson('/pos/sessions/' + sid + '/payment-methods', {
            method: 'GET',
        });

        const rows = data.methods || data.payment_methods || [];

        if (!rows.length) {
            throw new Error('No hay métodos de pago configurados para este PDV.');
        }

        return rows.map(function (method) {
            const label = method.label || method.name || method.description || method.code || 'Pago';
            const code = String(method.code || method.payment_form_code || '').trim();
            const isCash = Boolean(method.is_cash)
                || code === '01'
                || String(label).toLowerCase().includes('efectivo')
                || String(label).toLowerCase().includes('cash');

            return {
                id: method.id || method.payment_form_id || '',
                label: label,
                code: code,
                is_cash: isCash,
                is_credit: Boolean(method.is_credit),
            };
        });
    }

    function rowsBox() {
        return document.getElementById('v5481i-payment-rows');
    }

    function paymentRows() {
        const box = rowsBox();

        if (!box) {
            return [];
        }

        return Array.from(box.querySelectorAll('[data-v5481i-row="1"]'));
    }

    function paidTotal() {
        return paymentRows().reduce(function (sum, row) {
            const input = row.querySelector('[data-v5481i-amount="1"]');
            const value = Number(input ? input.value : 0);

            return sum + (Number.isFinite(value) ? value : 0);
        }, 0);
    }

    // V5_53_0C_pos_cash_change_dev
    function v5530cIsCashOption(option) {
        if (!option) {
            return false;
        }

        const label = String(option.dataset.label || option.textContent || '').toLowerCase();
        const code = String(option.dataset.code || '').trim();

        return option.dataset.isCash === '1'
            || code === '01'
            || label.includes('efectivo')
            || label.includes('cash');
    }

    function v5530cIsCashRow(row) {
        const select = row ? row.querySelector('[data-v5481i-method="1"]') : null;

        if (!select) {
            return false;
        }

        return v5530cIsCashOption(select.options[select.selectedIndex]);
    }

    function v5530cPaymentAnalysis(total) {
        const rows = paymentRows();
        let tendered = 0;
        let cashTendered = 0;
        let hasCash = false;
        let nonCashTendered = 0;

        rows.forEach(function (row) {
            const input = row.querySelector('[data-v5481i-amount="1"]');
            const value = Number(input ? input.value : 0);
            const amount = Number.isFinite(value) ? value : 0;

            if (amount <= 0) {
                return;
            }

            tendered += amount;

            if (v5530cIsCashRow(row)) {
                hasCash = true;
                cashTendered += amount;
            } else {
                nonCashTendered += amount;
            }
        });

        tendered = Number(tendered.toFixed(2));
        cashTendered = Number(cashTendered.toFixed(2));
        nonCashTendered = Number(nonCashTendered.toFixed(2));

        const diff = Number((Number(total || 0) - tendered).toFixed(2));
        const over = Number((tendered - Number(total || 0)).toFixed(2));
        const change = hasCash && over > 0 ? over : 0;
        const applied = change > 0 ? Number((tendered - change).toFixed(2)) : tendered;

        return {
            total: Number(Number(total || 0).toFixed(2)),
            tendered: tendered,
            applied: applied,
            diff: diff,
            over: over,
            change: Number(change.toFixed(2)),
            hasCash: hasCash,
            cashTendered: cashTendered,
            nonCashTendered: nonCashTendered,
            isCovered: tendered + 0.01 >= Number(total || 0),
            isExact: Math.abs(diff) <= 0.01 && tendered > 0,
        };
    }

    function updateSummary() {
        const summary = document.getElementById('v5481i-payment-summary');
        const button = confirmButton();

        const analysis = v5530cPaymentAnalysis(currentTotal());
        const okColor = '#166534';
        const warnColor = '#b91c1c';

        if (summary) {
            let statusLine = '';

            if (analysis.change > 0) {
                statusLine =
                    '<div style="display:flex;justify-content:space-between;gap:12px;color:' + okColor + ';"><span>Cambio a entregar</span><strong>' + money(analysis.change) + '</strong></div>';
            } else {
                statusLine =
                    '<div style="display:flex;justify-content:space-between;gap:12px;color:' + (analysis.isCovered ? okColor : warnColor) + ';"><span>Saldo</span><strong>' + money(Math.max(0, analysis.diff)) + '</strong></div>';
            }

            summary.innerHTML =
                '<div style="display:flex;justify-content:space-between;gap:12px;"><span>Total</span><strong>' + money(analysis.total) + '</strong></div>' +
                '<div style="display:flex;justify-content:space-between;gap:12px;"><span>Recibido</span><strong>' + money(analysis.tendered) + '</strong></div>' +
                '<div style="display:flex;justify-content:space-between;gap:12px;"><span>Aplicado a venta</span><strong>' + money(analysis.applied) + '</strong></div>' +
                statusLine;
        }

        /*
         * No lo dejamos disabled porque algunos navegadores/handlers no disparan click
         * sobre botones deshabilitados. La validación final se hace al registrar.
         */
        if (button) {
            button.disabled = false;
            button.textContent = 'Registrar cobro';
        }
    }

    function buildSelect() {
        const select = document.createElement('select');
        select.setAttribute('data-v5481i-method', '1');
        select.style.width = '100%';
        select.style.border = '1px solid #dbe3ef';
        select.style.borderRadius = '14px';
        select.style.padding = '12px';
        select.style.fontWeight = '850';
        select.style.background = '#fff';

        methods.forEach(function (method) {
            const option = document.createElement('option');
            option.value = method.id || '';
            option.textContent = method.label || 'Pago';
            option.dataset.label = method.label || 'Pago';
                option.dataset.code = method.code || '';
                option.dataset.isCash = method.is_cash ? '1' : '0';
            select.appendChild(option);
        });

        return select;
    }

    function addPaymentRow(amount) {
        const box = rowsBox();

        if (!box) {
            return;
        }

        const row = document.createElement('div');
        row.setAttribute('data-v5481i-row', '1');
        row.style.display = 'grid';
        row.style.gridTemplateColumns = '1fr 145px 38px';
        row.style.gap = '10px';
        row.style.alignItems = 'center';

        const select = buildSelect();

        const input = document.createElement('input');
        input.type = 'number';
        input.step = '0.01';
        input.min = '0';
        input.inputMode = 'decimal';
        input.value = Number(amount || 0).toFixed(2);
        input.setAttribute('data-v5481i-amount', '1');
        input.style.width = '100%';
        input.style.border = '1px solid #dbe3ef';
        input.style.borderRadius = '14px';
        input.style.padding = '12px';
        input.style.fontWeight = '850';

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = '×';
        remove.style.border = '1px solid #fecaca';
        remove.style.background = '#fff';
        remove.style.color = '#b91c1c';
        remove.style.borderRadius = '12px';
        remove.style.fontWeight = '950';
        remove.style.padding = '10px 0';
        remove.style.cursor = 'pointer';

        select.addEventListener('change', updateSummary);
        input.addEventListener('input', updateSummary);

        remove.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            row.remove();

            if (paymentRows().length === 0) {
                addPaymentRow(currentTotal());
            }

            updateSummary();
        });

        row.appendChild(select);
        row.appendChild(input);
        row.appendChild(remove);

        box.appendChild(row);

        updateSummary();
    }

    function renderPaymentForm() {
        const box = optionsBox();

        if (!box) {
            throw new Error('No se encontró el contenedor de métodos de pago.');
        }

        box.className = '';
        box.innerHTML = '';

        const wrapper = document.createElement('div');
        wrapper.style.display = 'grid';
        wrapper.style.gap = '12px';

        const rows = document.createElement('div');
        rows.id = 'v5481i-payment-rows';
        rows.style.display = 'grid';
        rows.style.gap = '10px';

        const addButton = document.createElement('button');
        addButton.type = 'button';
        addButton.className = 'v5335-payment-cancel';
        addButton.style.width = '100%';
        addButton.textContent = '+ Agregar otro método de pago';

        const summary = document.createElement('div');
        summary.id = 'v5481i-payment-summary';
        summary.style.border = '1px solid #e2e8f0';
        summary.style.borderRadius = '14px';
        summary.style.padding = '12px 14px';
        summary.style.display = 'grid';
        summary.style.gap = '8px';
        summary.style.fontWeight = '850';

        addButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            const remaining = Math.max(0, Number((currentTotal() - paidTotal()).toFixed(2)));
            addPaymentRow(remaining > 0 ? remaining : 0);
        });

        wrapper.appendChild(rows);
        wrapper.appendChild(addButton);
        wrapper.appendChild(summary);

        box.appendChild(wrapper);

        addPaymentRow(currentTotal());
    }

    async function openPaymentFlow(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();

            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }
        }

        const order = currentOrder();
        const api = window.BEXIA_POS_CART_API || null;

        if ((!order || !order.id) && (!api || typeof api.size !== 'function' || api.size() === 0)) {
            warn('Agrega productos al carrito o carga un ticket pendiente antes de cobrar.');
            return;
        }

        const total = currentTotal();

        if (!total || total <= 0) {
            warn('El ticket no tiene total válido para cobrar.');
            return;
        }

        if (totalBox()) {
            totalBox().textContent = money(total);
        }

        if (optionsBox()) {
            optionsBox().className = '';
            optionsBox().innerHTML = 'Cargando métodos de pago...';
        }

        if (confirmButton()) {
            confirmButton().disabled = false;
            confirmButton().textContent = 'Registrar cobro';
        }

        openModal();

        try {
            methods = await loadMethods();
            renderPaymentForm();
        } catch (error) {
            if (optionsBox()) {
                optionsBox().innerHTML = '<div class="warning">' + (error.message || 'No se pudieron cargar métodos de pago.') + '</div>';
            }
        }
    }

    function collectPayments() {
        return paymentRows().map(function (row) {
            const select = row.querySelector('[data-v5481i-method="1"]');
            const input = row.querySelector('[data-v5481i-amount="1"]');

            if (!select || !input) {
                return null;
            }

            const option = select.options[select.selectedIndex];
            const amount = Number(input.value || 0);
            const isCash = v5530cIsCashOption(option);

            if (!Number.isFinite(amount) || amount <= 0) {
                return null;
            }

            return {
                payment_form_id: select.value || null,
                payment_label: option ? (option.dataset.label || option.textContent || 'Pago') : 'Pago',
                amount: Number(amount.toFixed(2)),
                tendered_amount: Number(amount.toFixed(2)),
                cash_received: isCash ? Number(amount.toFixed(2)) : null,
                is_cash_frontend: isCash,
            };
        }).filter(Boolean);
    }

    async function registerPayment(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();

            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }
        }

        let order = currentOrder();

        if (!order || !order.id) {
            try {
                order = await window.BEXIA_POS_CREATE_ORDER_FROM_CART_BEFORE_PAY();
            } catch (error) {
                warn(error.message || 'No se pudo crear el ticket para cobrar.');
                return;
            }
        }

        const total = Number(currentTotal().toFixed(2));
        const payments = collectPayments();

        if (!payments.length) {
            warn('Agrega al menos un pago.');
            return;
        }

        const paid = Number(payments.reduce(function (sum, payment) {
            return sum + Number(payment.amount || 0);
        }, 0).toFixed(2));

        // V5_53_0D3: permitir excedente; el backend valida si aplica como cambio en efectivo.
        if (paid + 0.01 < total) {
            warn('El pago recibido es menor al total. Total: ' + money(total) + ' / Recibido: ' + money(paid));
            return;
        }

        const button = confirmButton();

        if (button) {
            button.disabled = true;
            button.textContent = 'Registrando...';
        }

        try {
            const paidItems = window.BEXIA_POS_CART_API && typeof window.BEXIA_POS_CART_API.getItems === 'function'
                ? window.BEXIA_POS_CART_API.getItems()
                : [];

            const currentApi = window.BEXIA_POS_CART_API || null;
            const currentDiscount = currentApi && typeof currentApi.getDiscount === 'function'
                ? currentApi.getDiscount()
                : null;

            const currentItems = currentApi && typeof currentApi.getItems === 'function'
                ? currentApi.getItems()
                : [];

            // BEXIA_V5829C_PAYLOAD_CURRENT_CART_ITEMS
            // El pago del ticket pendiente envia sus lineas actuales, total y descuento.
            const data = await getJson('/pos/orders/' + order.id + '/pay', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    payments: payments,
                    discount: currentDiscount,
                    items: currentItems,
                    total: total,
                    paying_session_id: sessionId(),
                }),
            });

            if (typeof window.BEXIA_POS_REFRESH_STOCK_TEXT_ONLY === 'function') {
                await window.BEXIA_POS_REFRESH_STOCK_TEXT_ONLY(paidItems);
            }

            if (window.BEXIA_POS_CART_API && typeof window.BEXIA_POS_CART_API.clear === 'function') {
                window.BEXIA_POS_CART_API.clear();
            }

            window.BEXIA_POS_LOADED_PENDING_ORDER = null;

            closeModal();

            if (data.print_url) {
                window.open(data.print_url, '_blank', 'width=420,height=720');
            }

            warn('Cobro registrado correctamente: ' + (data.number || order.number || ''));
        } catch (error) {
            warn(error.message || 'No se pudo registrar el cobro.');
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = 'Registrar cobro';
            }
        }
    }

    document.addEventListener('click', function (event) {
        const closeBtn = event.target.closest ? event.target.closest('#v5335-payment-close') : null;
        const cancelBtn = event.target.closest ? event.target.closest('#v5335-payment-cancel') : null;
        const chargeBtn = event.target.closest ? event.target.closest('#v5349-charge-ticket') : null;
        const registerBtn = event.target.closest ? event.target.closest('#v5335-payment-confirm') : null;

        if (closeBtn || cancelBtn) {
            event.preventDefault();
            event.stopPropagation();

            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }

            closeModal();
            return;
        }

        if (chargeBtn) {
            openPaymentFlow(event);
            return;
        }

        if (registerBtn && modal() && modal().classList.contains('is-open')) {
            registerPayment(event);
            return;
        }
    }, true);

    window.BEXIA_POS_OPEN_PAYMENT_FLOW_V5481I = openPaymentFlow;
    window.BEXIA_POS_CLOSE_PAYMENT_MODAL_V5481I = closeModal;
});
</script>



<script id="v5481k2-create-order-before-pay-global">
document.addEventListener('DOMContentLoaded', function () {
    if (window.BEXIA_POS_CREATE_ORDER_BEFORE_PAY_K2_READY) {
        return;
    }

    window.BEXIA_POS_CREATE_ORDER_BEFORE_PAY_K2_READY = true;

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        const input = document.querySelector('input[name="_token"]');

        return (meta && meta.content)
            || (input && input.value)
            || @json(csrf_token());
    }

    function sessionId() {
        const match = window.location.pathname.match(/\/pos\/sessions\/(\d+)\/screen/);
        return match ? match[1] : null;
    }

    async function postJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(payload || {}),
        });

        const data = await response.json().catch(function () {
            return {};
        });

        if (!response.ok || data.ok === false) {
            throw new Error(data.message || 'No se pudo crear el ticket para cobrar.');
        }

        return data;
    }

    window.BEXIA_POS_CREATE_ORDER_FROM_CART_BEFORE_PAY = async function () {
        const api = window.BEXIA_POS_CART_API || null;

        if (!api || typeof api.getItems !== 'function' || typeof api.size !== 'function' || api.size() === 0) {
            throw new Error('Agrega productos al carrito antes de cobrar.');
        }

        const sid = sessionId();

        if (!sid) {
            throw new Error('No se pudo identificar la sesión del PDV.');
        }

        const items = api.getItems();
        const discount = typeof api.getDiscount === 'function' ? api.getDiscount() : null;
        const note = typeof api.getNote === 'function' ? api.getNote() : '';
        const total = typeof api.getTotal === 'function' ? Number(api.getTotal() || 0) : 0;

        if (!items.length || total <= 0) {
            throw new Error('El carrito no tiene productos o total válido.');
        }

        const data = await postJson('/pos/sessions/' + sid + '/orders', {
            items: items,
            discount: discount,
            order_note: note,
            total: total,
        });

        const order = {
            id: data.id || data.order_id || (data.order && data.order.id) || null,
            number: data.number || (data.order && data.order.number) || '',
            total: total,
            status: 'pending_payment',
            items: items,
        };

        if (!order.id) {
            throw new Error('Se creó el ticket, pero no se recibió el ID para cobrarlo.');
        }

        window.BEXIA_POS_LOADED_PENDING_ORDER = order;

        return order;
    };

    /*
     * Alias por si algún script quedó llamando el nombre anterior.
     */
    window.createOrderFromCartBeforePay = window.BEXIA_POS_CREATE_ORDER_FROM_CART_BEFORE_PAY;
});
</script>



@if(! ($v5514cCanCloseSession ?? false))
<style id="v5514c-hide-close-session-style">
    #v5333-close-session-form,
    #v5332-close-session-form,
    #v5331-close-session-form,
    .v5333-close-session,
    #v5484-close-session-confirm {
        display: none !important;
    }
</style>
<script id="v5514c-hide-close-session-script">
document.addEventListener('DOMContentLoaded', function () {
    try {
        [
            'v5333-close-session-form',
            'v5332-close-session-form',
            'v5331-close-session-form',
            'v5484-close-session-confirm'
        ].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                el.style.display = 'none';
                el.setAttribute('aria-hidden', 'true');
                if ('disabled' in el) {
                    el.disabled = true;
                }
            }
        });

        document.querySelectorAll('.v5333-close-session').forEach(function (el) {
            el.style.display = 'none';
            el.setAttribute('aria-hidden', 'true');
            if ('disabled' in el) {
                el.disabled = true;
            }
        });
    } catch (e) {}
});
</script>
@endif

<style id="v5484-close-session-modal-style">
    .v5484-close-backdrop { position: fixed; inset: 0; z-index: 12000; display: none; align-items: center; justify-content: center; padding: 24px; background: rgba(15, 23, 42, .52); }
    .v5484-close-backdrop.is-open { display: flex; }
    .v5484-close-card { width: min(760px, 96vw); max-height: 92vh; overflow: auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 22px; box-shadow: 0 24px 80px rgba(15, 23, 42, .35); }
    .v5484-close-header { display: flex; justify-content: space-between; align-items: center; gap: 14px; padding: 18px 22px; border-bottom: 1px solid #e2e8f0; }
    .v5484-close-header h2 { margin: 0; font-size: 22px; font-weight: 950; color: #0f172a; }
    .v5484-close-body { padding: 18px 22px; display: grid; gap: 14px; }
    .v5484-close-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .v5484-close-kpi { border: 1px solid #e2e8f0; border-radius: 16px; padding: 12px; background: #f8fafc; }
    .v5484-close-kpi span { display: block; color: #64748b; font-size: 12px; font-weight: 850; }
    .v5484-close-kpi strong { display: block; margin-top: 5px; color: #0f172a; font-size: 20px; font-weight: 950; }
    .v5484-close-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .v5484-close-table th, .v5484-close-table td { padding: 9px 8px; border-bottom: 1px solid #e2e8f0; text-align: left; }
    .v5484-close-table th { background: #f8fafc; color: #475569; font-size: 12px; font-weight: 950; }
    .v5484-close-actions { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 22px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .v5484-close-secondary, .v5484-close-primary, .v5484-close-danger { border-radius: 14px; padding: 11px 16px; font-weight: 950; cursor: pointer; }
    .v5484-close-secondary { border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a; }
    .v5484-close-primary { border: 1px solid #2563eb; background: #2563eb; color: #ffffff; }
    .v5484-close-danger { border: 1px solid #dc2626; background: #dc2626; color: #ffffff; }
</style>

<div id="v5484-close-session-modal" class="v5484-close-backdrop" aria-hidden="true">
    <div class="v5484-close-card" role="dialog" aria-modal="true">
        <div class="v5484-close-header">
            <h2>Cierre de sesión PDV</h2>
            <button type="button" class="v5484-close-secondary" id="v5484-close-session-cancel-top">Cerrar</button>
        </div>
        <div class="v5484-close-body" id="v5484-close-session-body">Cargando resumen...</div>
        <div class="v5484-close-actions">
            <button type="button" class="v5484-close-secondary" id="v5484-close-session-cancel">Cancelar</button>
            <button type="button" class="v5484-close-primary" id="v5488i-close-session-ticket">Imprimir ticket de cierre</button>
            <button type="button" class="v5484-close-primary" id="v5484-close-session-download">Descargar reporte de venta</button>
            <button type="button" class="v5484-close-danger" id="v5484-close-session-confirm">Confirmar cierre</button>
        </div>
    </div>
</div>

<script id="v5484-close-session-modal-script">
document.addEventListener('DOMContentLoaded', function () {
    if (window.BEXIA_POS_CLOSE_SESSION_MODAL_V5484_READY) return;
    window.BEXIA_POS_CLOSE_SESSION_MODAL_V5484_READY = true;

    const modal = document.getElementById('v5484-close-session-modal');
    const body = document.getElementById('v5484-close-session-body');
    const cancel = document.getElementById('v5484-close-session-cancel');
    const cancelTop = document.getElementById('v5484-close-session-cancel-top');
    const download = document.getElementById('v5484-close-session-download');
    const confirm = document.getElementById('v5484-close-session-confirm');

    let closeForm = null;

    function sessionId() {
        const match = window.location.pathname.match(/\/pos\/sessions\/(\d+)\/screen/);
        return match ? match[1] : null;
    }

    function money(value) {
        return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
    }

    function escapeHtml(value) {
        return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }

    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    function renderSummary(summary) {
        const totals = summary.totals || {};
        const session = summary.session || {};
        const pos = summary.pos || {};
        const cashier = summary.cashier || {};
        const payments = summary.payments_by_method || [];
        const paidOrders = summary.paid_orders || [];

        let html = '';
        html += '<div style="display:grid;gap:4px;">';
        html += '<strong style="font-size:16px;">' + escapeHtml(session.number || '') + '</strong>';
        html += '<span style="color:#64748b;font-size:13px;font-weight:800;">' + escapeHtml(pos.name || '') + ' · Cajero: ' + escapeHtml(cashier.name || 'Sin cajero') + '</span>';
        html += '<span style="color:#64748b;font-size:12px;">Apertura: ' + escapeHtml(session.opened_at || '') + '</span>';
        html += '</div>';

        html += '<div class="v5484-close-summary">';
        html += '<div class="v5484-close-kpi"><span>Total vendido</span><strong>' + money(totals.paid_total || 0) + '</strong></div>';
        html += '<div class="v5484-close-kpi"><span>Tickets cobrados</span><strong>' + Number(totals.paid_tickets || 0) + '</strong></div>';
        html += '<div class="v5484-close-kpi"><span>Pendientes sesión</span><strong>' + Number(totals.pending_tickets_created_in_session || 0) + '</strong></div>';
        html += '<div class="v5484-close-kpi"><span>Reservas activas</span><strong>' + Number(totals.active_reservations || 0) + '</strong></div>';
        html += '</div>';

        html += '<h3 style="margin:4px 0 8px;font-size:15px;">Ventas por método de pago</h3>';
        html += '<table class="v5484-close-table"><thead><tr><th>Método</th><th style="text-align:right;">Pagos</th><th style="text-align:right;">Total</th></tr></thead><tbody>';

        if (payments.length) {
            payments.forEach(function (row) {
                html += '<tr><td>' + escapeHtml(row.method || 'Sin método') + '</td><td style="text-align:right;">' + Number(row.payments_count || 0) + '</td><td style="text-align:right;font-weight:950;">' + money(row.total || 0) + '</td></tr>';
            });
        } else {
            html += '<tr><td colspan="3" style="color:#64748b;">Sin pagos registrados.</td></tr>';
        }

        html += '</tbody></table>';

        html += '<h3 style="margin:4px 0 8px;font-size:15px;">Tickets cobrados</h3>';
        html += '<table class="v5484-close-table"><thead><tr><th>Ticket</th><th>Tipo</th><th style="text-align:right;">Total</th></tr></thead><tbody>';

        if (paidOrders.length) {
            paidOrders.slice(0, 12).forEach(function (order) {
                html += '<tr><td>' + escapeHtml(order.number || '') + '</td><td>' + (order.created_in_current_session ? 'Creado en sesión' : 'Pendiente anterior') + '</td><td style="text-align:right;">' + money(order.total || 0) + '</td></tr>';
            });
        } else {
            html += '<tr><td colspan="3" style="color:#64748b;">Sin tickets cobrados.</td></tr>';
        }

        html += '</tbody></table>';
        body.innerHTML = html;
    }

    async function loadSummary() {
        const sid = sessionId();
        if (!sid) {
            body.innerHTML = '<div style="color:#b91c1c;">No se pudo identificar la sesión.</div>';
            return;
        }

        body.innerHTML = 'Cargando resumen...';

        try {
            const response = await fetch('/pos/sessions/' + sid + '/close-summary', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            const data = await response.json().catch(function () { return {}; });

            if (!response.ok || data.ok === false) throw new Error(data.message || 'No se pudo cargar el resumen.');

            renderSummary(data.summary || {});
        } catch (error) {
            body.innerHTML = '<div style="color:#b91c1c;font-weight:900;">' + escapeHtml(error.message || 'Error al cargar resumen.') + '</div>';
        }
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!form || !form.id || !String(form.id).includes('close-session')) return;

        event.preventDefault();
        event.stopPropagation();

        if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();

        closeForm = form;
        openModal();
        loadSummary();
    }, true);

    cancel?.addEventListener('click', closeModal);
    cancelTop?.addEventListener('click', closeModal);

    modal?.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });

    download?.addEventListener('click', function () {
        const sid = sessionId();
        if (sid) window.open('/pos/sessions/' + sid + '/sales-report', '_blank');
    });

    confirm?.addEventListener('click', function () {
        if (!closeForm) return;
        confirm.disabled = true;
        confirm.textContent = 'Cerrando...';
        closeForm.submit();
    });
});
</script>


<style id="v5485-cash-control-style">
    .v5485-cash-btn {
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0f172a;
        border-radius: 10px;
        padding: 7px 11px;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
        margin-left: 8px;
    }

    .v5485-cash-btn.cash-out {
        border-color: #fecaca;
        color: #b91c1c;
    }

    .v5485-cash-btn.cash-in {
        border-color: #bbf7d0;
        color: #166534;
    }

    .v5485-backdrop {
        position: fixed;
        inset: 0;
        z-index: 13000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(15, 23, 42, .52);
    }

    .v5485-backdrop.is-open {
        display: flex;
    }

    .v5485-card {
        width: min(520px, 96vw);
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 22px;
        box-shadow: 0 24px 80px rgba(15, 23, 42, .35);
        overflow: hidden;
    }

    .v5485-header {
        padding: 18px 22px;
        border-bottom: 1px solid #e2e8f0;
    }

    .v5485-header h2 {
        margin: 0;
        font-size: 21px;
        font-weight: 950;
        color: #0f172a;
    }

    .v5485-body {
        padding: 18px 22px;
        display: grid;
        gap: 12px;
    }

    .v5485-label {
        display: block;
        font-size: 12px;
        font-weight: 950;
        color: #475569;
        margin-bottom: 5px;
    }

    .v5485-input,
    .v5485-textarea {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        padding: 11px 12px;
        font-size: 14px;
        font-weight: 800;
    }

    .v5485-textarea {
        min-height: 76px;
        resize: vertical;
    }

    .v5485-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 16px 22px 20px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    .v5485-secondary,
    .v5485-primary {
        border-radius: 14px;
        padding: 11px 16px;
        font-weight: 950;
        cursor: pointer;
    }

    .v5485-secondary {
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0f172a;
    }

    .v5485-primary {
        border: 1px solid #2563eb;
        background: #2563eb;
        color: #fff;
    }

    .v5485-cash-count {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 12px;
        background: #f8fafc;
    }

    .v5485-denom-row {
        display: grid;
        grid-template-columns: 1fr 90px 110px;
        gap: 8px;
        align-items: center;
        margin-bottom: 7px;
    }

    .v5485-denom-row input {
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 8px;
        font-weight: 850;
        text-align: right;
    }

    .v5485-close-note {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        padding: 10px;
        min-height: 70px;
        resize: vertical;
    }
</style>

<div id="v5485-cash-movement-modal" class="v5485-backdrop" aria-hidden="true">
    <div class="v5485-card">
        <div class="v5485-header">
            <h2 id="v5485-cash-movement-title">Movimiento de efectivo</h2>
        </div>

        <div class="v5485-body">
            <div>
                <label class="v5485-label">Tipo de movimiento</label>
                <select id="v5485-cash-movement-type" class="v5485-input">
                    <option value="cash_in">Entrada de efectivo</option>
                    <option value="cash_out">Retiro de efectivo</option>
                </select>
            </div>

            <div>
                <label class="v5485-label">Importe</label>
                <input id="v5485-cash-movement-amount" class="v5485-input" type="number" min="0" step="0.01" inputmode="decimal">
            </div>

            <div>
                <label class="v5485-label">Motivo</label>
                <input id="v5485-cash-movement-reason" class="v5485-input" type="text" placeholder="Ej. Cambio para caja / Retiro supervisor">
            </div>

            <div>
                <label class="v5485-label">Supervisor</label>
                <select id="v5485-cash-movement-supervisor-employee" class="v5485-input">
                    <option value="">Cargando empleados...</option>
                </select>
                <input id="v5485-cash-movement-supervisor" type="hidden">
            </div>

            <div>
                <label class="v5485-label">Notas</label>
                <textarea id="v5485-cash-movement-notes" class="v5485-textarea" placeholder="Notas opcionales"></textarea>
            </div>
        </div>

        <div class="v5485-actions">
            <button type="button" class="v5485-secondary" id="v5485-cash-movement-cancel">Cancelar</button>
            <button type="button" class="v5485-primary" id="v5485-cash-movement-save">Guardar e imprimir</button>
        </div>
    </div>
</div>

<script id="v5485-cash-control-script">
document.addEventListener('DOMContentLoaded', function () {
    if (window.BEXIA_POS_CASH_CONTROL_V5485_READY) return;
    window.BEXIA_POS_CASH_CONTROL_V5485_READY = true;

    let lastCloseSummary = null;
    let activeCloseForm = null;

    function sessionId() {
        const match = window.location.pathname.match(/\/pos\/sessions\/(\d+)\/screen/);
        return match ? match[1] : null;
    }

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        const input = document.querySelector('input[name="_token"]');
        return (meta && meta.content) || (input && input.value) || @json(csrf_token());
    }

    function money(value) {
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(Number(value || 0));
    }

    function notice(message) {
        const api = window.BEXIA_POS_CART_API || null;
        if (api && typeof api.setWarning === 'function') {
            api.setWarning(message);
        } else {
            alert(message);
        }
    }

    let v5485CashEmployeesLoaded = false;
    let v5485CashEmployees = [];

    async function loadCashEmployees() {
        const sid = sessionId();
        const select = document.getElementById('v5485-cash-movement-supervisor-employee');

        if (!select || !sid) {
            return;
        }

        if (v5485CashEmployeesLoaded) {
            renderCashEmployees();
            return;
        }

        select.innerHTML = '<option value="">Cargando empleados...</option>';

        try {
            const response = await fetch('/pos/sessions/' + sid + '/cash-employees', {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json().catch(function () {
                return {};
            });

            if (!response.ok || data.ok === false) {
                throw new Error(data.message || 'No se pudieron cargar empleados.');
            }

            v5485CashEmployees = data.employees || [];
            v5485CashEmployeesLoaded = true;
            renderCashEmployees();
        } catch (error) {
            select.innerHTML = '<option value="">No se pudieron cargar empleados</option>';
            notice(error.message || 'No se pudieron cargar empleados.');
        }
    }

    function renderCashEmployees() {
        const select = document.getElementById('v5485-cash-movement-supervisor-employee');

        if (!select) {
            return;
        }

        let html = '<option value="">Selecciona supervisor</option>';

        v5485CashEmployees.forEach(function (employee) {
            html += '<option value="' + String(employee.id) + '" data-name="' + String(employee.name || '').replaceAll('"', '&quot;') + '">' + String(employee.label || employee.name || ('Empleado #' + employee.id)) + '</option>';
        });

        select.innerHTML = html;
    }

    function insertHeaderButtons() {
        if (document.getElementById('v5485-cash-movement-btn')) return;

        const movementBtn = document.createElement('button');
        movementBtn.type = 'button';
        movementBtn.id = 'v5485-cash-movement-btn';
        movementBtn.className = 'v5485-cash-btn';
        movementBtn.textContent = 'Movimiento efectivo';
        movementBtn.addEventListener('click', function () {
            openMovementModal('cash_in');
        });

        const closeSession = document.getElementById('v5333-close-session-form')
            || document.getElementById('v5332-close-session-form')
            || document.getElementById('v5331-close-session-form');

        if (closeSession && closeSession.parentElement) {
            closeSession.parentElement.insertBefore(movementBtn, closeSession);
        } else {
            document.body.prepend(movementBtn);
        }
    }

    function openMovementModal(type) {
        const modal = document.getElementById('v5485-cash-movement-modal');
        const title = document.getElementById('v5485-cash-movement-title');
        const typeSelect = document.getElementById('v5485-cash-movement-type');

        if (typeSelect) {
            typeSelect.value = type || 'cash_in';
        }

        document.getElementById('v5485-cash-movement-amount').value = '';
        document.getElementById('v5485-cash-movement-reason').value = '';
        document.getElementById('v5485-cash-movement-supervisor').value = '';
        const supervisorSelect = document.getElementById('v5485-cash-movement-supervisor-employee');
        if (supervisorSelect) supervisorSelect.value = '';
        document.getElementById('v5485-cash-movement-notes').value = '';

        loadCashEmployees();

        title.textContent = 'Movimiento de efectivo';

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');

        setTimeout(function () {
            document.getElementById('v5485-cash-movement-amount')?.focus();
        }, 80);
    }

    function closeMovementModal() {
        const modal = document.getElementById('v5485-cash-movement-modal');
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.getElementById('v5485-cash-movement-cancel')?.addEventListener('click', closeMovementModal);

    document.getElementById('v5485-cash-movement-modal')?.addEventListener('click', function (event) {
        if (event.target === event.currentTarget) {
            closeMovementModal();
        }
    });

    document.getElementById('v5485-cash-movement-save')?.addEventListener('click', async function () {
        const sid = sessionId();

        if (!sid) {
            notice('No se pudo identificar la sesión.');
            return;
        }

        const payload = {
            type: document.getElementById('v5485-cash-movement-type').value,
            amount: Number(document.getElementById('v5485-cash-movement-amount').value || 0),
            reason: document.getElementById('v5485-cash-movement-reason').value || '',
            supervisor_employee_id: document.getElementById('v5485-cash-movement-supervisor-employee')?.value || '',
            supervisor_name: document.getElementById('v5485-cash-movement-supervisor-employee')?.selectedOptions?.[0]?.dataset?.name || '',
            notes: document.getElementById('v5485-cash-movement-notes').value || '',
        };

        try {
            const response = await fetch('/pos/sessions/' + sid + '/cash-movements', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json().catch(function () {
                return {};
            });

            if (!response.ok || data.ok === false) {
                throw new Error(data.message || 'No se pudo guardar el movimiento.');
            }

            closeMovementModal();

            if (data.print_url) {
                window.open(data.print_url, '_blank', 'width=420,height=760');
            }

            notice(data.message || 'Movimiento registrado.');
        } catch (error) {
            notice(error.message || 'No se pudo guardar el movimiento.');
        }
    });

    function buildCashCount(summary) {
        const totals = summary.totals || {};
        const denominations = summary.denominations || [];
        const movements = summary.cash_movements || [];

        let html = '';

        html += '<div class="v5485-cash-count">';
        html += '<h3 style="margin:0 0 10px;font-size:15px;">Conteo de efectivo</h3>';

        html += '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:12px;">';
        html += '<div><small>Esperado</small><strong style="display:block;">' + money(totals.expected_cash || 0) + '</strong></div>';
        html += '<div><small>Ventas efectivo</small><strong style="display:block;">' + money(totals.cash_payments_total || 0) + '</strong></div>';
        html += '<div><small>Entradas</small><strong style="display:block;">' + money(totals.cash_in_total || 0) + '</strong></div>';
        html += '<div><small>Retiros</small><strong style="display:block;">' + money(totals.cash_out_total || 0) + '</strong></div>';
        html += '</div>';

        html += '<div id="v5485-denominations">';

        denominations.forEach(function (denom, index) {
            html += '<div class="v5485-denom-row" data-value="' + Number(denom.value || 0) + '">';
            html += '<div><strong>' + (denom.name || money(denom.value || 0)) + '</strong></div>';
            html += '<input type="number" min="0" step="1" value="0" data-v5485-denom-qty="' + index + '">';
            html += '<div style="text-align:right;font-weight:950;" data-v5485-denom-total="' + index + '">' + money(0) + '</div>';
            html += '</div>';
        });

        html += '</div>';

        html += '<div style="border-top:1px solid #e2e8f0;margin-top:10px;padding-top:10px;display:grid;gap:5px;">';
        html += '<div style="display:flex;justify-content:space-between;"><span>Efectivo contado</span><strong id="v5485-counted-cash">' + money(0) + '</strong></div>';
        html += '<div style="display:flex;justify-content:space-between;"><span>Diferencia</span><strong id="v5485-cash-difference">' + money(0) + '</strong></div>';
        html += '</div>';

        html += '<div style="margin-top:10px;">';
        html += '<label id="v5485-closing-note-label" style="font-size:12px;font-weight:950;color:#475569;">Nota de cierre</label>';
        html += '<textarea id="v5485-closing-note" class="v5485-close-note" placeholder="Obligatoria si hay diferencia"></textarea>';
        html += '<div id="v5485-closing-note-warning" style="display:none;margin-top:8px;border:1px solid #fecaca;background:#fef2f2;color:#b91c1c;border-radius:12px;padding:9px 11px;font-size:12px;font-weight:900;">Hay diferencia en el corte. La nota de cierre es obligatoria.</div>';
        html += '</div>';

        if (movements.length) {
            html += '<h3 style="margin:14px 0 8px;font-size:15px;">Movimientos de efectivo</h3>';
            html += '<table class="v5484-close-table"><thead><tr><th>Tipo</th><th>Motivo</th><th style="text-align:right;">Importe</th><th>Comprobante</th></tr></thead><tbody>';

            movements.forEach(function (row) {
                html += '<tr>';
                html += '<td>' + row.type_label + '</td>';
                html += '<td>' + row.reason + '</td>';
                html += '<td style="text-align:right;font-weight:950;">' + money(row.amount || 0) + '</td>';
                html += '<td><a href="' + row.print_url + '" target="_blank">Imprimir</a></td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
        }

        html += '</div>';

        return html;
    }

    function recalcCashCount() {
        if (!lastCloseSummary) return;

        const expected = Number((lastCloseSummary.totals || {}).expected_cash || 0);
        let counted = 0;
        const cashCount = [];

        document.querySelectorAll('#v5485-denominations .v5485-denom-row').forEach(function (row) {
            const value = Number(row.dataset.value || 0);
            const input = row.querySelector('[data-v5485-denom-qty]');
            const qty = Number(input ? input.value : 0);
            const total = value * qty;
            const totalBox = row.querySelector('[data-v5485-denom-total]');

            counted += total;

            if (totalBox) {
                totalBox.textContent = money(total);
            }

            cashCount.push({
                value: value,
                quantity: qty,
                total: total,
            });
        });

        const diff = counted - expected;

        const countedBox = document.getElementById('v5485-counted-cash');
        const diffBox = document.getElementById('v5485-cash-difference');

        if (countedBox) countedBox.textContent = money(counted);
        if (diffBox) {
            diffBox.textContent = money(diff);
            diffBox.style.color = Math.abs(diff) > 0.009 ? '#b91c1c' : '#166534';
        }

        const noteLabel = document.getElementById('v5485-closing-note-label');
        const noteWarning = document.getElementById('v5485-closing-note-warning');
        const noteBox = document.getElementById('v5485-closing-note');

        if (Math.abs(diff) > 0.009) {
            if (noteLabel) {
                noteLabel.style.color = '#b91c1c';
                noteLabel.textContent = 'Nota de cierre *';
            }

            if (noteBox) {
                noteBox.style.borderColor = '#ef4444';
                noteBox.style.background = '#fff7f7';
            }

            if (noteWarning) {
                noteWarning.style.display = 'block';
            }
        } else {
            if (noteLabel) {
                noteLabel.style.color = '#475569';
                noteLabel.textContent = 'Nota de cierre';
            }

            if (noteBox) {
                noteBox.style.borderColor = '#cbd5e1';
                noteBox.style.background = '#ffffff';
            }

            if (noteWarning) {
                noteWarning.style.display = 'none';
            }
        }

        return {
            counted: counted,
            difference: diff,
            cashCount: cashCount,
        };
    }

    document.addEventListener('input', function (event) {
        if (event.target && event.target.matches('[data-v5485-denom-qty]')) {
            recalcCashCount();
        }

        if (event.target && event.target.id === 'v5485-closing-note') {
            const result = recalcCashCount();
            const noteWarning = document.getElementById('v5485-closing-note-warning');

            if (noteWarning && event.target.value.trim() !== '' && result && Math.abs(result.difference) > 0.009) {
                noteWarning.style.display = 'none';
            }
        }
    }, true);

    const originalRenderSummary = window.renderSummary;

    /*
     * Enganche no invasivo: buscamos el modal de cierre ya renderizado por V5.48.4
     * y agregamos el bloque de conteo después de que cargue el resumen.
     */
    const observer = new MutationObserver(function () {
        const closeBody = document.getElementById('v5484-close-session-body');

        if (!closeBody || closeBody.dataset.v5485CashCountAttached === '1') {
            return;
        }

        // El summary real se captura desde fetch interceptado abajo.
        if (!lastCloseSummary) {
            return;
        }

        closeBody.insertAdjacentHTML('beforeend', buildCashCount(lastCloseSummary));
        closeBody.dataset.v5485CashCountAttached = '1';
        recalcCashCount();
    });

    const closeBody = document.getElementById('v5484-close-session-body');
    if (closeBody) {
        observer.observe(closeBody, { childList: true, subtree: true });
    }

    if (!window.BEXIA_POS_FETCH_PATCHED_FOR_CLOSE_SUMMARY_V5485) {
        window.BEXIA_POS_FETCH_PATCHED_FOR_CLOSE_SUMMARY_V5485 = true;

        const originalFetch = window.fetch.bind(window);

        window.fetch = async function (input, init) {
            const response = await originalFetch(input, init);

            try {
                const url = typeof input === 'string' ? input : (input && input.url ? input.url : '');

                if (/\/pos\/sessions\/\d+\/close-summary/.test(url)) {
                    const clone = response.clone();
                    const data = await clone.json();

                    if (data && data.summary) {
                        lastCloseSummary = data.summary;

                        const closeBody = document.getElementById('v5484-close-session-body');
                        if (closeBody) {
                            closeBody.dataset.v5485CashCountAttached = '';
                        }
                    }
                }
            } catch (error) {
                //
            }

            return response;
        };
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!form || !form.id || !String(form.id).includes('close-session')) {
            return;
        }

        activeCloseForm = form;
    }, true);

    document.addEventListener('click', function (event) {
        const confirm = event.target && event.target.closest ? event.target.closest('#v5484-close-session-confirm') : null;

        if (!confirm) {
            return;
        }

        const result = recalcCashCount();

        if (!result) {
            return;
        }

        const note = document.getElementById('v5485-closing-note')?.value || '';

        if (Math.abs(result.difference) > 0.009 && note.trim() === '') {
            event.preventDefault();
            event.stopPropagation();

            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }

            const noteWarning = document.getElementById('v5485-closing-note-warning');
            const noteBox = document.getElementById('v5485-closing-note');
            const noteLabel = document.getElementById('v5485-closing-note-label');

            if (noteLabel) {
                noteLabel.style.color = '#b91c1c';
                noteLabel.textContent = 'Nota de cierre *';
            }

            if (noteBox) {
                noteBox.style.borderColor = '#ef4444';
                noteBox.style.background = '#fff7f7';
                noteBox.focus();
            }

            if (noteWarning) {
                noteWarning.style.display = 'block';
                noteWarning.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            return;
        }

        const form = activeCloseForm
            || document.getElementById('v5333-close-session-form')
            || document.getElementById('v5332-close-session-form')
            || document.getElementById('v5331-close-session-form');

        if (!form) {
            return;
        }

        function hidden(name, value) {
            let input = form.querySelector('input[name="' + name + '"]');

            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                form.appendChild(input);
            }

            input.value = value;
        }

        hidden('closing_amount', result.counted.toFixed(2));
        hidden('closing_difference', result.difference.toFixed(2));
        hidden('closing_note', note);
        hidden('cash_count', JSON.stringify(result.cashCount || []));
    }, true);

    insertHeaderButtons();
});
</script>


<script id="v5485g-fix-close-form-post">
document.addEventListener('DOMContentLoaded', function () {
    function v5485gEnsureCloseFormPost() {
        const forms = [
            document.getElementById('v5333-close-session-form'),
            document.getElementById('v5332-close-session-form'),
            document.getElementById('v5331-close-session-form'),
        ].filter(Boolean);

        forms.forEach(function (form) {
            form.method = 'POST';

            if (!form.querySelector('input[name="_token"]')) {
                const token = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = '_token';
                input.value = token;
                form.appendChild(input);
            }
        });
    }

    v5485gEnsureCloseFormPost();

    const observer = new MutationObserver(v5485gEnsureCloseFormPost);
    observer.observe(document.body, { childList: true, subtree: true });
});
</script>


<script id="v5485h-force-close-post">
document.addEventListener('DOMContentLoaded', function () {
    function sessionIdFromUrl() {
        const match = window.location.pathname.match(/\/pos\/sessions\/(\d+)\/screen/);
        return match ? match[1] : null;
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
    }

    function ensureCloseForms() {
        const sid = sessionIdFromUrl();

        if (!sid) {
            return;
        }

        const forms = [
            document.getElementById('v5333-close-session-form'),
            document.getElementById('v5332-close-session-form'),
            document.getElementById('v5331-close-session-form'),
        ].filter(Boolean);

        forms.forEach(function (form) {
            form.method = 'POST';
            form.action = '/pos/sessions/' + sid + '/close';

            if (!form.querySelector('input[name="_token"]')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = '_token';
                input.value = csrfToken();
                form.appendChild(input);
            }
        });
    }

    ensureCloseForms();

    const observer = new MutationObserver(ensureCloseForms);
    observer.observe(document.body, { childList: true, subtree: true });

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!form || !form.id || !String(form.id).includes('close-session')) {
            return;
        }

        ensureCloseForms();
    }, true);
});
</script>


<script id="v5485i-fix-419-close-fetch">
document.addEventListener('DOMContentLoaded', function () {
    if (window.BEXIA_POS_CLOSE_FETCH_V5485I_READY) {
        return;
    }

    window.BEXIA_POS_CLOSE_FETCH_V5485I_READY = true;

    function sessionIdFromUrl() {
        const match = window.location.pathname.match(/\/pos\/sessions\/(\d+)\/screen/);
        return match ? match[1] : null;
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
    }

    function moneyNumberFromText(text) {
        const raw = String(text || '')
            .replace(/[^0-9.\-]/g, '');

        const value = Number(raw || 0);

        return Number.isFinite(value) ? value : 0;
    }

    function closeForm() {
        return document.getElementById('v5333-close-session-form')
            || document.getElementById('v5332-close-session-form')
            || document.getElementById('v5331-close-session-form');
    }

    function showCloseWarning(message) {
        const noteWarning = document.getElementById('v5485-closing-note-warning');
        const noteBox = document.getElementById('v5485-closing-note');
        const noteLabel = document.getElementById('v5485-closing-note-label');

        if (noteLabel) {
            noteLabel.style.color = '#b91c1c';
            noteLabel.textContent = 'Nota de cierre *';
        }

        if (noteBox) {
            noteBox.style.borderColor = '#ef4444';
            noteBox.style.background = '#fff7f7';
            noteBox.focus();
        }

        if (noteWarning) {
            noteWarning.textContent = message || 'Hay diferencia en el corte. La nota de cierre es obligatoria.';
            noteWarning.style.display = 'block';
            noteWarning.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        alert(message || 'Hay diferencia en el corte. La nota de cierre es obligatoria.');
    }

    function buildCashCountPayload() {
        const rows = Array.from(document.querySelectorAll('#v5485-denominations .v5485-denom-row'));
        const cashCount = [];

        let counted = 0;

        rows.forEach(function (row) {
            const value = Number(row.dataset.value || 0);
            const input = row.querySelector('[data-v5485-denom-qty]');
            const quantity = Number(input ? input.value : 0);
            const total = value * quantity;

            counted += total;

            cashCount.push({
                value: value,
                quantity: quantity,
                total: total,
            });
        });

        const countedBox = document.getElementById('v5485-counted-cash');
        const diffBox = document.getElementById('v5485-cash-difference');

        const countedFromDom = countedBox ? moneyNumberFromText(countedBox.textContent) : counted;
        const differenceFromDom = diffBox ? moneyNumberFromText(diffBox.textContent) : 0;

        return {
            counted: Number((counted || countedFromDom || 0).toFixed(2)),
            difference: Number((differenceFromDom || 0).toFixed(2)),
            cashCount: cashCount,
        };
    }

    async function submitCloseByFetch(event) {
        const button = event.target && event.target.closest
            ? event.target.closest('#v5484-close-session-confirm')
            : null;

        if (!button) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }

        if (!window.BEXIA_POS_CLOSE_FINAL_CONFIRMED_V5485J) {
            if (typeof window.BEXIA_POS_OPEN_FINAL_CLOSE_CONFIRM_V5485J === 'function') {
                window.BEXIA_POS_OPEN_FINAL_CLOSE_CONFIRM_V5485J(button);
                return;
            }

            if (!confirm('¿Estás seguro de cerrar la sesión? No olvides descargar el reporte de cierre.')) {
                return;
            }
        }

        window.BEXIA_POS_CLOSE_FINAL_CONFIRMED_V5485J = false;

        const sid = sessionIdFromUrl();
        const form = closeForm();

        if (!sid || !form) {
            showCloseWarning('No se pudo identificar la sesión o el formulario de cierre.');
            return;
        }

        const payload = buildCashCountPayload();
        const note = document.getElementById('v5485-closing-note')?.value || '';

        if (Math.abs(payload.difference) > 0.009 && note.trim() === '') {
            showCloseWarning('Hay diferencia en el corte. La nota de cierre es obligatoria.');
            return;
        }

        const data = new FormData(form);

        data.set('_token', csrfToken());
        data.set('closing_amount', payload.counted.toFixed(2));
        data.set('closing_difference', payload.difference.toFixed(2));
        data.set('closing_note', note);
        data.set('cash_count', JSON.stringify(payload.cashCount || []));

        button.disabled = true;
        button.textContent = 'Cerrando...';

        try {
            const response = await fetch('/pos/sessions/' + sid + '/close', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: data,
                redirect: 'follow',
            });

            if (response.status === 419) {
                throw new Error('La sesión de seguridad expiró. Recarga el PDV con Ctrl+F5 e intenta cerrar nuevamente.');
            }

            if (!response.ok) {
                throw new Error('No se pudo cerrar la sesión. Código HTTP: ' + response.status);
            }

            // BEXIA_V582P3_A35G_CLOSE_PDV_WINDOW_AFTER_SESSION_CLOSE
            // BEXIA_V582P3_A35G2_CLOSE_WITHOUT_OPENER
            //
            // El PDV puede abrirse con target="_blank" o mediante Filament.
            // En esos casos la pestaña puede ser cerrable aunque window.opener
            // sea null. Por eso el cierre ya no depende del opener.
            const closeFallbackUrl = (
                response.redirected
                && response.url
            )
                ? response.url
                : '/admin/{{ filament()->getTenant()?->getKey() ?? 3 }}/point-of-sale';

            try {
                if (window.opener && !window.opener.closed) {
                    try {
                        window.opener.postMessage(
                            {
                                type: 'bexia:pos-session-closed',
                                sessionId: sid,
                            },
                            window.location.origin
                        );
                    } catch (postMessageError) {
                        console.warn(
                            'No se pudo notificar el cierre al opener.',
                            postMessageError
                        );
                    }

                    try {
                        window.opener.location.reload();
                    } catch (reloadError) {
                        console.warn(
                            'No se pudo actualizar la ventana principal.',
                            reloadError
                        );
                    }
                }
            } catch (openerError) {
                console.warn(
                    'No fue posible comunicarse con la ventana administrativa.',
                    openerError
                );
            }

            // Primer intento inmediato, exista o no window.opener.
            try {
                window.close();
            } catch (firstCloseError) {
                console.warn(
                    'Primer intento de cierre bloqueado por el navegador.',
                    firstCloseError
                );
            }

            // Segundo intento antes de ejecutar la redirección de respaldo.
            window.setTimeout(function () {
                if (window.closed) {
                    return;
                }

                try {
                    window.close();
                } catch (secondCloseError) {
                    console.warn(
                        'Segundo intento de cierre bloqueado por el navegador.',
                        secondCloseError
                    );
                }
            }, 180);

            // Solo se utiliza cuando el navegador no permite cerrar la pestaña.
            window.setTimeout(function () {
                if (!window.closed) {
                    window.location.replace(closeFallbackUrl);
                }
            }, 900);

            return;
        } catch (error) {
            button.disabled = false;
            button.textContent = 'Confirmar cierre';
            showCloseWarning(error.message || 'No se pudo cerrar la sesión.');
        }
    }

    document.addEventListener('click', submitCloseByFetch, true);
});
</script>


<style id="v5485j-final-close-confirm-style">
    .v5485j-final-backdrop {
        position: fixed;
        inset: 0;
        z-index: 15000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(15, 23, 42, .58);
    }

    .v5485j-final-backdrop.is-open {
        display: flex;
    }

    .v5485j-final-card {
        width: min(520px, 96vw);
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 22px;
        box-shadow: 0 24px 80px rgba(15, 23, 42, .38);
        overflow: hidden;
    }

    .v5485j-final-header {
        padding: 20px 22px 12px;
    }

    .v5485j-final-header h2 {
        margin: 0;
        color: #0f172a;
        font-size: 22px;
        font-weight: 950;
    }

    .v5485j-final-body {
        padding: 0 22px 18px;
        color: #334155;
        font-size: 14px;
        line-height: 1.45;
    }

    .v5485j-final-warning {
        margin-top: 12px;
        border: 1px solid #fed7aa;
        background: #fff7ed;
        color: #9a3412;
        border-radius: 14px;
        padding: 11px 13px;
        font-size: 13px;
        font-weight: 900;
    }

    .v5485j-final-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 16px 22px 20px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    .v5485j-final-secondary,
    .v5485j-final-danger {
        border-radius: 14px;
        padding: 11px 16px;
        font-weight: 950;
        cursor: pointer;
    }

    .v5485j-final-secondary {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #0f172a;
    }

    .v5485j-final-danger {
        border: 1px solid #dc2626;
        background: #dc2626;
        color: #ffffff;
    }
</style>

<div id="v5485j-final-close-confirm-modal" class="v5485j-final-backdrop" aria-hidden="true">
    <div class="v5485j-final-card" role="dialog" aria-modal="true">
        <div class="v5485j-final-header">
            <h2>Confirmar cierre de sesión</h2>
        </div>

        <div class="v5485j-final-body">
            <p style="margin:0;">
                ¿Estás seguro de que deseas cerrar esta sesión de PDV?
            </p>

            <div class="v5485j-final-warning">
                Antes de confirmar, no olvides descargar el reporte de cierre.
                Después de cerrar, ya no se podrá operar esta sesión.
            </div>
        </div>

        <div class="v5485j-final-actions">
            <button type="button" class="v5485j-final-secondary" id="v5485j-final-close-cancel">
                Volver al corte
            </button>

            <button type="button" class="v5485j-final-danger" id="v5485j-final-close-confirm">
                Sí, cerrar sesión
            </button>
        </div>
    </div>
</div>

<script id="v5485j-final-close-confirm-script">
document.addEventListener('DOMContentLoaded', function () {
    if (window.BEXIA_POS_FINAL_CLOSE_CONFIRM_V5485J_READY) {
        return;
    }

    window.BEXIA_POS_FINAL_CLOSE_CONFIRM_V5485J_READY = true;
    window.BEXIA_POS_CLOSE_FINAL_CONFIRMED_V5485J = false;
    window.BEXIA_POS_PENDING_CLOSE_BUTTON_V5485J = null;

    const modal = document.getElementById('v5485j-final-close-confirm-modal');
    const cancel = document.getElementById('v5485j-final-close-cancel');
    const confirm = document.getElementById('v5485j-final-close-confirm');

    window.BEXIA_POS_OPEN_FINAL_CLOSE_CONFIRM_V5485J = function (button) {
        window.BEXIA_POS_PENDING_CLOSE_BUTTON_V5485J = button || document.getElementById('v5484-close-session-confirm');

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    };

    function closeFinalConfirm() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    cancel?.addEventListener('click', function () {
        window.BEXIA_POS_CLOSE_FINAL_CONFIRMED_V5485J = false;
        closeFinalConfirm();
    });

    modal?.addEventListener('click', function (event) {
        if (event.target === modal) {
            window.BEXIA_POS_CLOSE_FINAL_CONFIRMED_V5485J = false;
            closeFinalConfirm();
        }
    });

    confirm?.addEventListener('click', function () {
        const button = window.BEXIA_POS_PENDING_CLOSE_BUTTON_V5485J
            || document.getElementById('v5484-close-session-confirm');

        window.BEXIA_POS_CLOSE_FINAL_CONFIRMED_V5485J = true;
        closeFinalConfirm();

        if (button) {
            button.click();
        }
    });
});
</script>


<script id="v5488i-close-ticket-modal-button">
document.addEventListener('DOMContentLoaded', function () {
    if (window.BEXIA_POS_CLOSE_TICKET_V5488I_READY) {
        return;
    }

    window.BEXIA_POS_CLOSE_TICKET_V5488I_READY = true;

    function sessionIdFromUrl() {
        const match = window.location.pathname.match(/\/pos\/sessions\/(\d+)\/screen/);
        return match ? match[1] : null;
    }

    const ticketButton = document.getElementById('v5488i-close-session-ticket');

    if (ticketButton) {
        ticketButton.addEventListener('click', function (event) {
            event.preventDefault();

            const sid = sessionIdFromUrl();

            if (!sid) {
                alert('No se pudo identificar la sesión del PDV.');
                return;
            }

            window.open('/pos/sessions/' + sid + '/close-ticket/print', '_blank', 'width=420,height=760');
        });
    }
});
</script>



<script id="v5489b-refresh-products-pdv-script">
document.addEventListener('DOMContentLoaded', function () {
    if (window.BEXIA_POS_REFRESH_PRODUCTS_V5489E_READY) {
        return;
    }

    window.BEXIA_POS_REFRESH_PRODUCTS_V5489E_READY = true;

    function sessionIdFromUrl() {
        const match = window.location.pathname.match(/\/pos\/sessions\/(\d+)\/screen/);
        return match ? match[1] : null;
    }

    function moneyNumber(value) {
        return Number(value || 0).toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function money(value) {
        return '$' + moneyNumber(value) + ' MXN';
    }

    function normalizedTaxRate(row, fallback) {
        const raw = Number(row.sale_tax_rate ?? fallback ?? 0.16);

        if (!Number.isFinite(raw) || raw <= 0) {
            return 0.16;
        }

        return raw > 1 ? raw / 100 : raw;
    }

    function updateVisibleCards(updatedProducts) {
        let changed = 0;

        updatedProducts.forEach(function (item) {
            const id = String(item.id || '');
            const price = Number(item.price || item.public_price || 0);
            const stock = Number(item.available_quantity ?? item.stock_quantity ?? 0);

            if (!id) {
                return;
            }

            const cards = document.querySelectorAll('.product[data-product-id="' + id + '"]');

            cards.forEach(function (card) {
                const isService = card.dataset.productIsService === '1' || card.dataset.productType === 'service';
                const taxRate = normalizedTaxRate(item, card.dataset.productTaxRate);

                card.dataset.productPrice = String(price.toFixed(2));
                card.dataset.productStock = String(stock);
                card.dataset.productTaxRate = String(taxRate);

                if (!isService) {
                    card.dataset.productCanSell = stock > 0 ? '1' : '0';
                    card.classList.toggle('disabled', stock <= 0);
                }

                const priceEl = card.querySelector('.price');

                if (priceEl) {
                    priceEl.textContent = money(price);
                }

                const stockEl = card.querySelector('.stock');

                if (stockEl) {
                    if (isService) {
                        stockEl.textContent = 'Servicio';
                        stockEl.classList.remove('no-stock');
                    } else {
                        stockEl.textContent = 'Stock: ' + moneyNumber(stock);
                        stockEl.classList.toggle('no-stock', stock <= 0);
                    }
                }

                changed++;
            });
        });

        return changed;
    }

    function updateCart(updatedProducts) {
        const api = window.BEXIA_POS_CART_API || null;

        if (api && typeof api.refreshProductData === 'function') {
            return Number(api.refreshProductData(updatedProducts) || 0);
        }

        return 0;
    }

    function notify(message, type) {
        if (window.FilamentNotification && typeof window.FilamentNotification === 'function') {
            const notification = new window.FilamentNotification().title(message);

            if (type === 'error') {
                notification.danger();
            } else {
                notification.success();
            }

            notification.send();
            return;
        }

        if (type === 'error') {
            alert(message);
            return;
        }

        console.log(message);
    }

    async function refreshProducts(button) {
        const sid = sessionIdFromUrl();

        if (!sid) {
            notify('No se pudo identificar la sesión del PDV.', 'error');
            return;
        }

        const originalText = button.textContent;

        button.disabled = true;
        button.textContent = 'Actualizando...';

        try {
            const priceListId = window.BEXIA_POS_SELECTED_PRICE_LIST_ID || 0;
            const url = '/pos/sessions/' + sid + '/products-refresh' + (priceListId > 0 ? ('?price_list_id=' + encodeURIComponent(priceListId)) : '');

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const data = await response.json();

            if (!data || !data.ok || !Array.isArray(data.products)) {
                throw new Error('Respuesta inválida del servidor.');
            }

            window.BEXIA_POS_REFRESH_PRODUCTS_APPLY = function (products) {
                const cardsChanged = updateVisibleCards(products);
                const cartChanged = updateCart(products);

                // BEXIA_V582P4B_REFRESH_APPLY_SEARCH_METADATA
                if (typeof window.BEXIA_POS_APPLY_SEARCH_METADATA === 'function') {
                    window.BEXIA_POS_APPLY_SEARCH_METADATA(products);
                }

                return {
                    cardsChanged: cardsChanged,
                    cartChanged: cartChanged
                };
            };

            const applied = window.BEXIA_POS_REFRESH_PRODUCTS_APPLY(data.products);
            const cardsChanged = applied.cardsChanged;
            const cartChanged = applied.cartChanged;

            document.dispatchEvent(new CustomEvent('bexia:pos-products-refreshed', {
                detail: {
                    products: data.products,
                    cardsChanged: cardsChanged,
                    cartChanged: cartChanged
                }
            }));

            button.textContent = 'Actualizado';

            notify('Productos actualizados: ' + cardsChanged + ' tarjetas, ' + cartChanged + ' líneas en carrito.', 'success');

            setTimeout(function () {
                button.textContent = originalText;
            }, 1400);
        } catch (error) {
            console.error(error);
            button.textContent = originalText;
            notify('No se pudieron actualizar los productos.', 'error');
        } finally {
            button.disabled = false;
        }
    }

    function createButton() {
        if (document.getElementById('v5489b-refresh-products-button')) {
            return;
        }

        const button = document.createElement('button');
        button.id = 'v5489b-refresh-products-button';
        button.type = 'button';
        button.textContent = 'Actualizar productos';
        button.style.display = 'inline-flex';
        button.style.alignItems = 'center';
        button.style.justifyContent = 'center';
        button.style.minHeight = '40px';
        button.style.borderRadius = '14px';
        button.style.padding = '10px 14px';
        button.style.border = '1px solid #2563eb';
        button.style.background = '#2563eb';
        button.style.color = '#ffffff';
        button.style.fontWeight = '950';
        button.style.cursor = 'pointer';
        button.style.boxShadow = '0 10px 22px rgba(37,99,235,.22)';

        button.addEventListener('click', function () {
            refreshProducts(button);
        });

        const targets = [
            document.querySelector('[data-v5360-products-toolbar]'),
            document.querySelector('.pos-products-toolbar'),
            document.querySelector('.products-toolbar'),
            document.querySelector('input[placeholder*="Buscar"]')?.parentElement,
            document.querySelector('input[type="search"]')?.parentElement
        ].filter(Boolean);

        if (targets.length > 0) {
            targets[0].appendChild(button);
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.style.position = 'fixed';
        wrapper.style.right = '24px';
        wrapper.style.bottom = '96px';
        wrapper.style.zIndex = '9999';
        wrapper.appendChild(button);

        document.body.appendChild(wrapper);
    }

    createButton();

    const observer = new MutationObserver(function () {
        createButton();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});
</script>


<script id="v5490b-unified-product-search-script">
document.addEventListener('DOMContentLoaded', function () {
    if (window.BEXIA_POS_UNIFIED_SEARCH_V5490B_READY) {
        return;
    }

    window.BEXIA_POS_UNIFIED_SEARCH_V5490B_READY = true;

    const input = document.getElementById('v5490b-product-search') || document.querySelector('.search input');
    const clearButton = document.getElementById('v5490b-clear-search');

    if (!input) {
        return;
    }

    function normalize(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function digitsOnly(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function cards() {
        return Array.from(document.querySelectorAll('.product[data-product-id]'));
    }

    function cardHaystack(card) {
        return normalize([
            card.dataset.productName || '',
            card.dataset.productReference || '',
            card.dataset.productBarcode || '',
            card.dataset.productSku || '',
            card.dataset.productCode || '',
            card.dataset.productSearch || '',
            card.querySelector('.pname')?.textContent || '',
            card.querySelector('.code')?.textContent || '',
        ].join(' '));
    }

    function isExactCodeMatch(card, rawQuery) {
        const q = normalize(rawQuery);
        const qDigits = digitsOnly(rawQuery);

        const values = [
            card.dataset.productReference || '',
            card.dataset.productBarcode || '',
            card.dataset.productSku || '',
            card.dataset.productCode || '',
            card.dataset.productId || '',
        ];

        return values.some(function (value) {
            const normalized = normalize(value);
            const numeric = digitsOnly(value);

            if (normalized && normalized === q) {
                return true;
            }

            if (qDigits && numeric && numeric === qDigits) {
                return true;
            }

            return false;
        });
    }

    function visibleCards() {
        return cards().filter(function (card) {
            return card.style.display !== 'none';
        });
    }

    function applySearch() {
        const query = normalize(input.value);
        const tokens = query.split(' ').filter(Boolean);
        let visible = 0;

        cards().forEach(function (card) {
            const haystack = cardHaystack(card);
            const show = tokens.length === 0 || tokens.every(function (token) {
                return haystack.includes(token);
            });

            card.style.display = show ? '' : 'none';

            if (show) {
                visible++;
            }
        });

        let counter = document.getElementById('v5490b-search-counter');

        if (!counter) {
            counter = document.createElement('div');
            counter.id = 'v5490b-search-counter';
            counter.style.fontSize = '12px';
            counter.style.color = '#64748b';
            counter.style.fontWeight = '800';
            counter.style.marginTop = '8px';

            const search = input.closest('.search');

            if (search && search.parentNode) {
                search.parentNode.insertBefore(counter, search.nextSibling);
            }
        }

        if (query) {
            counter.textContent = visible + ' producto(s) encontrados';
            counter.style.display = '';
        } else {
            counter.textContent = '';
            counter.style.display = 'none';
        }

        return visible;
    }

    function addExactMatchOrSingleVisible() {
        const raw = input.value || '';

        if (!raw.trim()) {
            return false;
        }

        const exact = cards().filter(function (card) {
            return isExactCodeMatch(card, raw);
        });

        const candidates = exact.length ? exact : visibleCards();

        if (candidates.length === 1) {
            candidates[0].click();
            input.value = '';
            applySearch();
            return true;
        }

        return false;
    }

    input.addEventListener('input', applySearch);

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            addExactMatchOrSingleVisible();
        }

        if (event.key === 'Escape') {
            input.value = '';
            applySearch();
        }
    });

    clearButton?.addEventListener('click', function () {
        input.value = '';
        applySearch();
        input.focus();
    });

    /*
     * Soporte para lectores de código de barras:
     * normalmente escriben el código y envían Enter.
     */
    input.placeholder = 'Buscar o escanear código de barras';
    input.setAttribute('autocomplete', 'off');

    applySearch();
});
</script>


<script id="v5495c-customer-price-list-auto">
(function () {
    'use strict';

    if (window.__v5495cCustomerPriceListReady) {
        return;
    }

    window.__v5495cCustomerPriceListReady = true;

    function sessionId() {
        const match = String(window.location.pathname).match(/\/pos\/sessions\/(\d+)/);
        return match ? match[1] : null;
    }

    function money(value) {
        return '$' + Number(value || 0).toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' MXN';
    }

    function button() {
        return document.getElementById('v5492d-open-price-list');
    }

    function priceListNameFromButtonData(id, fallback) {
        const btn = button();
        let lists = [];

        if (btn) {
            try {
                lists = JSON.parse(btn.dataset.priceLists || '[]');
            } catch (error) {
                lists = [];
            }
        }

        const found = Array.isArray(lists)
            ? lists.find(function (list) {
                return Number(list.id || 0) === Number(id || 0);
            })
            : null;

        return found && found.name ? String(found.name) : (fallback || (id ? ('Lista #' + id) : 'Precio público'));
    }

    function setPriceListButtonLabel(id, name) {
        const btn = button();

        if (!btn) {
            return;
        }

        const label = priceListNameFromButtonData(id, name);

        btn.textContent = 'Lista: ' + label;
        btn.title = 'Lista de precios actual: ' + label;
        btn.dataset.selectedPriceListId = String(id || 0);
        btn.dataset.selectedPriceListName = label;

        window.BEXIA_POS_SELECTED_PRICE_LIST_ID = Number(id || 0);
        window.BEXIA_POS_SELECTED_PRICE_LIST_NAME = label;
    }

    function updateVisibleCards(products) {
        let changed = 0;

        products.forEach(function (item) {
            const cards = document.querySelectorAll('.product[data-product-id="' + String(item.id || '') + '"]');

            cards.forEach(function (card) {
                const price = Number(item.price || item.public_price || 0);
                const stock = Number(item.available_quantity ?? item.stock_quantity ?? 0);
                const isService = card.dataset.productIsService === '1' || card.dataset.productType === 'service';

                card.dataset.productPrice = String(price.toFixed(2));
                card.dataset.productStock = String(stock);
                card.dataset.productPriceListId = String(item.price_list_id || '');

                const priceEl = card.querySelector('.price');

                if (priceEl) {
                    priceEl.textContent = money(price);
                }

                const stockEl = card.querySelector('.stock');

                if (stockEl && !isService) {
                    stockEl.textContent = 'Stock: ' + Number(stock || 0).toLocaleString('es-MX', {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 2
                    });
                }

                changed++;
            });
        });

        return changed;
    }

    function applyProducts(products) {
        if (typeof window.BEXIA_POS_REFRESH_PRODUCTS_APPLY === 'function') {
            return window.BEXIA_POS_REFRESH_PRODUCTS_APPLY(products) || {};
        }

        let cartChanged = 0;

        if (window.BEXIA_POS_CART_API && typeof window.BEXIA_POS_CART_API.refreshProductData === 'function') {
            cartChanged = Number(window.BEXIA_POS_CART_API.refreshProductData(products) || 0);
        }

        return {
            cardsChanged: updateVisibleCards(products),
            cartChanged: cartChanged
        };
    }

    async function applyPriceListId(priceListId, priceListName, source) {
        const sid = sessionId();
        const id = Number(priceListId || 0);

        if (!sid || id <= 0) {
            return false;
        }

        const current = Number(window.BEXIA_POS_SELECTED_PRICE_LIST_ID || button()?.dataset.selectedPriceListId || 0);
        const previousPriceListId = current;
        const previousPriceListName = String(window.BEXIA_POS_SELECTED_PRICE_LIST_NAME || button()?.dataset.selectedPriceListName || '');

        if (current === id) {
            setPriceListButtonLabel(id, priceListName);
            return true;
        }

        const response = await fetch(
            '/pos/sessions/' + encodeURIComponent(sid)
                + '/products-refresh?price_list_id=' + encodeURIComponent(id)
                + '&previous_price_list_id=' + encodeURIComponent(Number(window.BEXIA_POS_SELECTED_PRICE_LIST_ID || priceListButton()?.dataset.selectedPriceListId || 0))
                + '&previous_price_list_name=' + encodeURIComponent(String(window.BEXIA_POS_SELECTED_PRICE_LIST_NAME || priceListButton()?.dataset.selectedPriceListName || ''))
                + '&price_list_change_source=applyById',
            {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }
        );

        const data = await response.json();

        if (!response.ok || !data.ok || !Array.isArray(data.products)) {
            throw new Error(data.message || 'No se pudo actualizar la lista de precios.');
        }

        applyProducts(data.products);

        setPriceListButtonLabel(
            Number(data.selected_price_list_id || id),
            data.selected_price_list_name || priceListName || ''
        );

        document.dispatchEvent(new CustomEvent('bexia:pos-price-list-applied', {
            detail: {
                source: source || 'customer',
                previous_price_list_id: previousPriceListId,
                previous_price_list_name: previousPriceListName,
                price_list_id: Number(data.selected_price_list_id || id),
                price_list_name: data.selected_price_list_name || priceListName || ''
            }
        }));

        return true;
    }

    async function applyCustomerId(customerId, source) {
        const sid = sessionId();
        const id = Number(customerId || 0);

        if (!sid || id <= 0) {
            return false;
        }

        const response = await fetch(
            '/pos/sessions/' + encodeURIComponent(sid) + '/customers/' + encodeURIComponent(id) + '/price-list',
            {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }
        );

        const data = await response.json();

        if (!response.ok || !data.ok) {
            throw new Error(data.message || 'No se pudo consultar la lista del cliente.');
        }

        const selectedId = Number(data.selected_price_list_id || 0);

        if (selectedId <= 0) {
            return false;
        }

        return applyPriceListId(
            selectedId,
            data.selected_price_list_name || '',
            source || 'customer'
        );
    }

    window.BEXIA_POS_APPLY_CUSTOMER_PRICE_LIST = function (customer, source) {
        const data = customer || {};
        const customerId = Number(data.customer_id || data.id || data.contact_id || 0);
        const directPriceListId = Number(data.customer_price_list_id || data.price_list_id || 0);

        if (directPriceListId > 0) {
            return applyPriceListId(
                directPriceListId,
                data.customer_price_list_name || data.price_list_name || '',
                source || 'customer'
            );
        }

        if (customerId > 0) {
            return applyCustomerId(customerId, source || 'customer');
        }

        return Promise.resolve(false);
    };

    window.BEXIA_POS_APPLY_PRICE_LIST_ID = function (priceListId, priceListName, source) {
        return applyPriceListId(priceListId, priceListName, source || 'manual');
    };

    function customerIdFromElement(el) {
        if (!el) {
            return 0;
        }

        const key = String((el.name || '') + ' ' + (el.id || '') + ' ' + (el.getAttribute('data-field') || '')).toLowerCase();

        if (
            !key.includes('customer')
            && !key.includes('cliente')
            && !key.includes('contact')
            && !key.includes('client')
        ) {
            return 0;
        }

        return Number(el.value || el.dataset.customerId || 0);
    }

    document.addEventListener('change', function (event) {
        const el = event.target;
        const customerId = customerIdFromElement(el);

        if (customerId <= 0) {
            return;
        }

        applyCustomerId(customerId, 'customer-change').catch(function (error) {
            console.error(error);
        });
    }, true);

    [
        'bexia:pos-customer-selected',
        'bexia:pos-customer-changed',
        'bexia:customer-selected',
        'bexia:client-selected',
        'bexia:contact-selected'
    ].forEach(function (eventName) {
        document.addEventListener(eventName, function (event) {
            window.BEXIA_POS_APPLY_CUSTOMER_PRICE_LIST(event.detail || {}, eventName).catch(function (error) {
                console.error(error);
            });
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        const currentCustomer = document.getElementById('v5495c-current-customer');

        if (!currentCustomer) {
            return;
        }

        const customerId = Number(currentCustomer.dataset.customerId || 0);
        const customerPriceListId = Number(currentCustomer.dataset.customerPriceListId || 0);

        if (customerPriceListId > 0) {
            applyPriceListId(customerPriceListId, '', 'current-customer').catch(function (error) {
                console.error(error);
            });

            return;
        }

        if (customerId > 0) {
            applyCustomerId(customerId, 'current-customer').catch(function (error) {
                console.error(error);
            });
        }
    });
})();
</script>


<script id="v5496f-force-create-pending-ticket">
(function () {
    'use strict';

    if (window.__v5496fForceCreatePendingTicketReady) {
        return;
    }

    window.__v5496fForceCreatePendingTicketReady = true;

    function notify(message, type) {
        type = type || 'info';

        if (typeof window.notice === 'function') {
            window.notice(message, type);
            return;
        }

        if (typeof window.showPosNotice === 'function') {
            window.showPosNotice(message, type);
            return;
        }

        if (typeof window.BEXIA_POS_NOTICE === 'function') {
            window.BEXIA_POS_NOTICE(message, type);
            return;
        }

        console.log('[PDV]', type, message);
    }

    function sessionId() {
        var match = String(window.location.pathname || '').match(/\/pos\/sessions\/(\d+)/);
        return match ? match[1] : null;
    }

    function csrfToken() {
        return window.csrfToken
            || window.csrf
            || (window.Laravel && window.Laravel.csrfToken)
            || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value
            || '{{ csrf_token() }}';
    }

    function getCartApi() {
        return window.BEXIA_POS_CART_API || null;
    }

    function getItems() {
        var api = getCartApi();

        if (api && typeof api.getItems === 'function') {
            return api.getItems() || [];
        }

        if (typeof window.cartItems === 'function') {
            return window.cartItems() || [];
        }

        if (typeof cartItems === 'function') {
            return cartItems() || [];
        }

        return [];
    }

    function openPreparingWindowSafe() {
        try {
            if (typeof window.openPreparingWindow === 'function') {
                return window.openPreparingWindow();
            }

            if (typeof openPreparingWindow === 'function') {
                return openPreparingWindow();
            }

            var w = window.open('', '_blank', 'width=420,height=720');

            if (w) {
                w.document.open();
                w.document.write(
                    '<!doctype html>' +
                    '<html><head><meta charset="utf-8"><title>Preparando ticket</title></head>' +
                    '<body style="font-family:Arial;text-align:center;padding:30px;">' +
                    '<strong>Preparando ticket pendiente...</strong><br><span>Espere un momento.</span>' +
                    '</body></html>'
                );
                w.document.close();
            }

            return w;
        } catch (error) {
            console.warn(error);
            return null;
        }
    }

    function clearCartSafe() {
        var api = getCartApi();

        if (typeof window.clearCartWithoutConfirm === 'function') {
            window.clearCartWithoutConfirm();
            return;
        }

        if (typeof clearCartWithoutConfirm === 'function') {
            clearCartWithoutConfirm();
            return;
        }

        if (api && typeof api.clear === 'function') {
            api.clear();
        }
    }

    window.BEXIA_POS_CREATE_PENDING_TICKET_ROBUST = async function (button) {
        var sid = sessionId();

        if (!sid) {
            notify('No se pudo identificar la sesión del PDV.', 'error');
            return;
        }

        // BEXIA_V5829E_SAVE_OVER_LOADED_PENDING_ROBUST
        var loadedPendingOrder = window.BEXIA_POS_LOADED_PENDING_ORDER || null;
        var isUpdatingPending = Boolean(loadedPendingOrder && loadedPendingOrder.id);

        var api = getCartApi();
        var items = getItems();

        if (!Array.isArray(items) || !items.length) {
            notify('Agrega productos antes de crear el ticket pendiente.', 'warning');
            return;
        }

        button = button || document.getElementById('v5349-create-pending-ticket');

        var originalHtml = button ? button.innerHTML : '';
        var printWindow = openPreparingWindowSafe();

        if (button) {
            button.disabled = true;
            button.innerHTML = isUpdatingPending ? 'Guardando cambios...' : 'Creando ticket...';
        }

        try {
            var payload = {
                items: items,
                customer_id: window.BEXIA_POS_SELECTED_CUSTOMER_ID || null,
                price_list_id: window.BEXIA_POS_SELECTED_PRICE_LIST_ID || null,
                price_list_name: window.BEXIA_POS_SELECTED_PRICE_LIST_NAME || null,
                selected_price_list_id: window.BEXIA_POS_SELECTED_PRICE_LIST_ID || null,
                selected_price_list_name: window.BEXIA_POS_SELECTED_PRICE_LIST_NAME || null,
                payment_label: null,
                payment_form_id: null,
                status: 'pending_payment',
                order_note: api && typeof api.getNote === 'function' ? api.getNote() : '',
                discount: api && typeof api.getDiscount === 'function' ? api.getDiscount() : null,
                total: api && typeof api.getTotal === 'function' ? api.getTotal() : null,
                pos_session_id: Number(sid)
            };

            var pendingEndpoint = isUpdatingPending
                ? '/pos/orders/' + encodeURIComponent(loadedPendingOrder.id) + '/pending-update'
                : '/pos/sessions/' + encodeURIComponent(sid) + '/orders';

            var response = await fetch(pendingEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken()
                },
                body: JSON.stringify(payload)
            });

            var data = await response.json().catch(function () {
                return {};
            });

            if (!response.ok || data.ok === false) {
                throw new Error(data.message || 'No se pudo crear el ticket pendiente.');
            }

            var orderId = data.order_id || data.id || (data.order && data.order.id) || null;
            var number = data.number || (data.order && data.order.number) || 'folio generado';
            var printUrl = data.print_url || (orderId ? '/pos/orders/' + orderId + '/pending-ticket/print?initial=1' : '');

            window.BEXIA_POS_LOADED_PENDING_ORDER = null;

            clearCartSafe();

            notify(
                (isUpdatingPending ? 'Ticket pendiente actualizado: ' : 'Ticket pendiente creado: ') + number,
                'info'
            );

            if (printUrl) {
                if (printWindow && !printWindow.closed) {
                    printWindow.location.href = printUrl;
                    printWindow.focus();
                } else {
                    window.open(printUrl, '_blank', 'width=420,height=720');
                }
            } else if (printWindow && !printWindow.closed) {
                printWindow.close();
            }
        } catch (error) {
            console.error(error);

            if (printWindow && !printWindow.closed) {
                printWindow.close();
            }

            notify(error.message || 'No se pudo crear el ticket pendiente.', 'error');
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        }
    };

    document.addEventListener('click', function (event) {
        var button = event.target && event.target.closest
            ? event.target.closest('#v5349-create-pending-ticket, #v5348b-create-pending-ticket, .v5349-create-pending-ticket, .v5348b-create-pending-ticket')
            : null;

        if (!button) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        window.BEXIA_POS_CREATE_PENDING_TICKET_ROBUST(button);
    }, true);
})();
</script>



<script id="v5500a-price-list-audit-script">
(function () {
    'use strict';

    if (window.__v5500aPriceListAuditLoaded) {
        return;
    }

    window.__v5500aPriceListAuditLoaded = true;

    function sessionId() {
        const match = String(window.location.pathname || '').match(/\/pos\/sessions\/(\d+)/);
        return match ? match[1] : null;
    }

    function csrfToken() {
        return window.csrfToken
            || window.csrf
            || (window.Laravel && window.Laravel.csrfToken)
            || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value
            || '{{ csrf_token() }}';
    }

    function currentCustomerId() {
        return Number(
            window.BEXIA_POS_SELECTED_CUSTOMER_ID
            || document.getElementById('v5495c-current-customer')?.dataset.customerId
            || 0
        );
    }

    window.BEXIA_POS_AUDIT_PRICE_LIST_CHANGE = function (detail) {
        const sid = sessionId();
        const data = detail || {};

        if (!sid || !data.new_price_list_id) {
            return Promise.resolve(false);
        }

        const previousId = Number(data.previous_price_list_id || 0);
        const newId = Number(data.new_price_list_id || 0);
        const previousName = String(data.previous_price_list_name || '');
        const newName = String(data.new_price_list_name || '');

        if (previousId === newId && previousName === newName) {
            return Promise.resolve(false);
        }

        return fetch('/pos/sessions/' + encodeURIComponent(sid) + '/price-list-changes', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify({
                source: data.source || 'manual',
                previous_price_list_id: previousId || null,
                previous_price_list_name: previousName || null,
                new_price_list_id: newId || null,
                new_price_list_name: newName || null,
                customer_id: data.customer_id || currentCustomerId() || null
            })
        }).then(function (response) {
            if (!response.ok) {
                console.warn('Auditoría lista precios falló HTTP:', response.status);
            }

            return response.json().catch(function () {
                return {};
            });
        }).catch(function (error) {
            console.warn('No se pudo registrar auditoría de lista de precios:', error);
            return false;
        });
    };

    document.addEventListener('bexia:pos-price-list-applied', function (event) {
        const detail = event.detail || {};

        window.BEXIA_POS_AUDIT_PRICE_LIST_CHANGE({
            source: detail.source || 'customer',
            previous_price_list_id: detail.previous_price_list_id || 0,
            previous_price_list_name: detail.previous_price_list_name || '',
            new_price_list_id: detail.price_list_id || detail.new_price_list_id || 0,
            new_price_list_name: detail.price_list_name || detail.new_price_list_name || '',
            customer_id: detail.customer_id || currentCustomerId() || null
        });
    });
})();
</script>

{{-- BEXIA_V5828B5C_CATEGORY_LOADING --}}
<style id="bexia-v5828b5a-category-loading-style">
    #bexia-v5828b5a-category-loading {
        position: fixed;
        inset: 0;
        z-index: 30000;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(15, 23, 42, .60);
        backdrop-filter: blur(2px);
    }

    #bexia-v5828b5a-category-loading.is-visible {
        display: flex;
    }

    .bexia-v5828b5a-category-card {
        min-width: 260px;
        max-width: 90vw;
        padding: 24px;
        border-radius: 22px;
        background: #ffffff;
        color: #0f172a;
        text-align: center;
        box-shadow: 0 25px 80px rgba(15, 23, 42, .35);
    }

    .bexia-v5828b5a-category-spinner {
        width: 40px;
        height: 40px;
        margin: 0 auto 14px;
        border: 4px solid #dbeafe;
        border-top-color: #2563eb;
        border-radius: 999px;
        animation: bexia-v5828b5a-category-spin .8s linear infinite;
    }

    @keyframes bexia-v5828b5a-category-spin {
        to { transform: rotate(360deg); }
    }
</style>

<div
    id="bexia-v5828b5a-category-loading"
    aria-hidden="true"
    aria-live="polite"
>
    <div class="bexia-v5828b5a-category-card">
        <div class="bexia-v5828b5a-category-spinner"></div>
        <div style="font-size:18px;font-weight:950;">
            Cargando productos
        </div>
        <div style="margin-top:6px;color:#64748b;font-weight:700;">
            Actualizando categoria y existencias...
        </div>
    </div>
</div>

<script id="bexia-v5828b5a-category-loading-script">
document.addEventListener('DOMContentLoaded', function () {
    const overlay = document.getElementById(
        'bexia-v5828b5a-category-loading'
    );

    function hideOverlay() {
        if (!overlay) return;

        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('.categories a.cat').forEach(function (link) {
        link.addEventListener('click', function (event) {
            if (
                event.button !== 0
                || event.ctrlKey
                || event.metaKey
                || event.shiftKey
                || event.altKey
            ) {
                return;
            }

            if (!overlay) return;

            overlay.classList.add('is-visible');
            overlay.setAttribute('aria-hidden', 'false');
        });
    });

    window.addEventListener('pageshow', hideOverlay);
});
</script>

{{-- BEXIA_V5828B5E_LOCAL_CATEGORY_FILTER --}}
{{-- BEXIA_V5828B5F_OVERLAY_IDS_ALIGNED --}}
<style id="bexia-v5828b5e-local-category-style">
    .categories a.cat.v5828b5e-active {
        outline: 4px solid rgba(15, 23, 42, .22);
        outline-offset: 2px;
        transform: translateY(-1px);
    }

    #v5828b5e-category-status {
        margin-top: 8px;
        color: #475569;
        font-size: 12px;
        font-weight: 850;
    }
</style>

<script id="bexia-v5828b5e-local-category-script">
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const grid = document.getElementById(
        'v5828b5i-main-products-grid'
    );

    if (!grid) {
        return;
    }

    const cards = Array.from(
        grid.children
    ).filter(function (element) {
        return element.matches(
            '.product[data-product-id]'
        );
    });

    const links = Array.from(
        document.querySelectorAll(
            '.categories a.cat[data-v5828b5e-category]'
        )
    );

    if (cards.length === 0 || links.length === 0) {
        return;
    }

    const urlCategory = new URL(
        window.location.href
    ).searchParams.get('pos_category');

    /*
     * El filtrado local se habilita cuando la pagina contiene
     * el catalogo completo: carga inicial Todas.
     *
     * Mas vendido conserva recarga porque su calculo viene
     * del servidor.
     */
    const localCatalogAvailable =
        !urlCategory
        || urlCategory === ''
        || urlCategory === 'all'
        || urlCategory === 'todos'
        || urlCategory === '0';

    cards.forEach(function (card, index) {
        card.dataset.v5828b5eOriginalIndex =
            String(index);
    });

    function favoriteValue(card) {
        return card.dataset.productIsFavorite === '1'
            ? 1
            : 0;
    }

    function sortFavoritesFirst() {
        const sorted = cards.slice().sort(
            function (left, right) {
                const favoriteDifference =
                    favoriteValue(right)
                    - favoriteValue(left);

                if (favoriteDifference !== 0) {
                    return favoriteDifference;
                }

                return Number(
                    left.dataset.v5828b5eOriginalIndex || 0
                ) - Number(
                    right.dataset.v5828b5eOriginalIndex || 0
                );
            }
        );

        sorted.forEach(function (card) {
            grid.appendChild(card);
        });
    }

    function statusElement() {
        let status = document.getElementById(
            'v5828b5e-category-status'
        );

        if (!status) {
            status = document.createElement('div');
            status.id = 'v5828b5e-category-status';

            const search = document.querySelector('.search');

            if (search && search.parentNode) {
                search.insertAdjacentElement(
                    'afterend',
                    status
                );
            }
        }

        return status;
    }

    function labelForCategory(key) {
        const link = links.find(function (candidate) {
            return String(
                candidate.dataset.v5828b5eCategory || ''
            ) === String(key);
        });

        return link
            ? String(link.textContent || '')
                .replace(/\s+/g, ' ')
                .trim()
            : 'Categoria';
    }

    function setActiveCategory(key) {
        links.forEach(function (link) {
            const active =
                String(
                    link.dataset.v5828b5eCategory || ''
                ) === String(key);

            link.classList.toggle(
                'v5828b5e-active',
                active
            );
        });
    }

    function hideServerOverlay() {
        const overlay = document.getElementById(
            'bexia-v5828b5a-category-loading'
        );

        if (overlay) {
            overlay.classList.remove('is-visible');
            overlay.setAttribute('aria-hidden', 'true');
        }
    }

    function applyCategory(key) {
        let visible = 0;

        cards.forEach(function (card) {
            const cardCategory = String(
                card.dataset.productCategoryId || '0'
            );

            const isFavorite =
                card.dataset.productIsFavorite === '1';

            let show = false;

            if (key === 'all') {
                show = true;
            } else if (key === 'favorites') {
                show = isFavorite;
            } else if (/^\d+$/.test(String(key))) {
                show = cardCategory === String(key);
            }

            card.style.display = show ? '' : 'none';

            if (show) {
                visible++;
            }
        });

        setActiveCategory(key);
        hideServerOverlay();

        const status = statusElement();

        if (status) {
            status.textContent =
                labelForCategory(key)
                + ': '
                + visible
                + ' producto'
                + (visible === 1 ? '' : 's');
        }
    }

    if (localCatalogAvailable) {
        sortFavoritesFirst();
        setActiveCategory('all');

        const status = statusElement();

        if (status) {
            const favoriteCount = cards.filter(
                function (card) {
                    return favoriteValue(card) === 1;
                }
            ).length;

            status.textContent =
                'Todas: '
                + cards.length
                + ' productos'
                + (
                    favoriteCount > 0
                        ? ' · '
                            + favoriteCount
                            + ' favoritos primero'
                        : ''
                );
        }
    }

    /*
     * Captura el clic antes del listener anterior que muestra
     * el overlay y deja navegar. Las categorias locales no
     * realizan ninguna solicitud al servidor.
     */
    document.addEventListener(
        'click',
        function (event) {
            const link = event.target.closest(
                '.categories a.cat'
                + '[data-v5828b5e-category]'
            );

            if (!link || !localCatalogAvailable) {
                return;
            }

            const key = String(
                link.dataset.v5828b5eCategory || ''
            );

            if (key === 'top_sellers') {
                return;
            }

            if (
                key !== 'all'
                && key !== 'favorites'
                && !/^\d+$/.test(key)
            ) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            if (
                typeof event.stopImmediatePropagation
                === 'function'
            ) {
                event.stopImmediatePropagation();
            }

            const searchInput = document.getElementById(
                'v5490b-product-search'
            );

            if (searchInput) {
                searchInput.value = '';
            }

            const searchCounter = document.getElementById(
                'v5490b-search-counter'
            );

            if (searchCounter) {
                searchCounter.textContent = '';
            }

            applyCategory(key);
        },
        true
    );

    /*
     * La busqueda existente es global. Al empezar a escribir,
     * la categoria activa se presenta como Todas.
     */
    const searchInput = document.getElementById(
        'v5490b-product-search'
    );

    if (searchInput && localCatalogAvailable) {
        searchInput.addEventListener(
            'input',
            function () {
                if (String(searchInput.value || '').trim()) {
                    setActiveCategory('all');
                }
            }
        );
    }
});
</script>

{{-- BEXIA_V5828B5I_FIXED_FAVORITES_SECTION --}}
{{-- BEXIA_V5828B5K_RUNTIME --}}
@php
    /*
     * B5K:
     * La lista se limita a los productos que ya recibió esta vista.
     * La consulta es exclusivamente de lectura.
     */
    $v5828b5kCatalogProductIds = collect($products ?? [])
        ->pluck('id')
        ->map(fn ($id): int => (int) $id)
        ->filter(fn ($id): bool => $id > 0)
        ->unique()
        ->values();

    $v5828b5kFavoriteIdsFromDatabase = collect();

    try {
        if (
            $v5828b5kCatalogProductIds->isNotEmpty()
            && \Illuminate\Support\Facades\Schema::hasTable(
                'products'
            )
            && \Illuminate\Support\Facades\Schema::hasColumn(
                'products',
                'is_pos_favorite'
            )
        ) {
            $v5828b5kFavoriteIdsFromDatabase =
                \Illuminate\Support\Facades\DB::table('products')
                    ->whereIn(
                        'id',
                        $v5828b5kCatalogProductIds->all()
                    )
                    ->where('is_pos_favorite', true)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->filter(fn ($id): bool => $id > 0)
                    ->unique()
                    ->values();
        }
    } catch (\Throwable $e) {
        $v5828b5kFavoriteIdsFromDatabase = collect();
    }

    /*
     * Respaldo: si la consulta no devuelve registros, usar la
     * bandera que ya llegó en el payload del controlador.
     */
    $v5828b5kFavoriteIdsFromPayload = collect($products ?? [])
        ->filter(
            fn ($product): bool =>
                (bool) data_get(
                    $product,
                    'is_pos_favorite',
                    false
                )
        )
        ->pluck('id')
        ->map(fn ($id): int => (int) $id)
        ->filter(fn ($id): bool => $id > 0)
        ->unique()
        ->values();

    $v5828b5iFavoriteProductIds =
        $v5828b5kFavoriteIdsFromDatabase->isNotEmpty()
            ? $v5828b5kFavoriteIdsFromDatabase
                ->map(fn ($id): string => (string) $id)
                ->values()
                ->all()
            : $v5828b5kFavoriteIdsFromPayload
                ->map(fn ($id): string => (string) $id)
                ->values()
                ->all();
@endphp

<style id="v5828b5i-fixed-favorites-style">
    #v5828b5i-favorites-section {
        margin-top: 12px;
        margin-bottom: 14px;
        padding: 12px;
        border: 2px solid #f59e0b;
        border-radius: 17px;
        background:
            linear-gradient(
                135deg,
                #fffbeb 0%,
                #fff7ed 55%,
                #ffffff 100%
            );
        box-shadow:
            0 10px 26px rgba(245, 158, 11, .14);
    }

    #v5828b5i-favorites-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 9px;
    }

    #v5828b5i-favorites-title {
        display: flex;
        align-items: center;
        gap: 7px;
        color: #92400e;
        font-size: 15px;
        font-weight: 950;
    }

    #v5828b5i-favorites-count {
        border: 1px solid #f59e0b;
        border-radius: 999px;
        padding: 3px 8px;
        background: #fef3c7;
        color: #92400e;
        font-size: 10px;
        font-weight: 950;
    }

    #v5828b5i-favorites-grid {
        margin-top: 0;
        grid-template-columns:
            repeat(auto-fill, minmax(118px, 150px));
        justify-content: start;
    }

    #v5828b5i-favorites-grid > .product {
        order: initial !important;
        border: 2px solid #f59e0b !important;
        background:
            linear-gradient(
                145deg,
                #fff7ed 0%,
                #fffbeb 65%,
                #ffffff 100%
            ) !important;
        box-shadow:
            0 9px 22px rgba(217, 119, 6, .20) !important;
    }

    #v5828b5i-favorites-grid > .product::before {
        content: "⭐ FAVORITO";
    }

    #v5828b5i-favorites-grid > .product .pname {
        color: #78350f !important;
    }

    #v5828b5i-favorites-grid > .product .price {
        color: #b45309 !important;
    }

    #v5828b5i-main-label {
        margin-top: 12px;
        margin-bottom: -3px;
        color: #475569;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .45px;
    }

    @media (max-width: 720px) {
        #v5828b5i-favorites-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }
    }
</style>

<script id="v5828b5i-fixed-favorites-script">
(function () {
    'use strict';

    /*
     * BEXIA_V5828B5K_RUNTIME
     *
     * Funciona tanto durante carga HTML normal como cuando la
     * pantalla ya terminó de cargar.
     */
    if (window.__BEXIA_V5828B5K_BOOTSTRAPPED) {
        if (
            typeof window.BEXIA_POS_INSTALL_FIXED_FAVORITES
            === 'function'
        ) {
            window.setTimeout(
                window.BEXIA_POS_INSTALL_FIXED_FAVORITES,
                0
            );
        }

        return;
    }

    window.__BEXIA_V5828B5K_BOOTSTRAPPED = true;

    const favoriteIds = @json(
        $v5828b5iFavoriteProductIds
    ).map(String);

    function ensureSection(mainGrid) {
        let section = document.getElementById(
            'v5828b5i-favorites-section'
        );

        if (!section) {
            section = document.createElement('section');
            section.id = 'v5828b5i-favorites-section';

            const header = document.createElement('div');
            header.id = 'v5828b5i-favorites-header';

            const title = document.createElement('div');
            title.id = 'v5828b5i-favorites-title';
            title.textContent = '⭐ Productos favoritos';

            const count = document.createElement('div');
            count.id = 'v5828b5i-favorites-count';

            header.appendChild(title);
            header.appendChild(count);

            const favoritesGrid =
                document.createElement('div');

            favoritesGrid.id =
                'v5828b5i-favorites-grid';

            favoritesGrid.className = 'products';

            section.appendChild(header);
            section.appendChild(favoritesGrid);

            mainGrid.parentNode.insertBefore(
                section,
                mainGrid
            );
        }

        let mainLabel = document.getElementById(
            'v5828b5i-main-label'
        );

        if (!mainLabel) {
            mainLabel = document.createElement('div');
            mainLabel.id = 'v5828b5i-main-label';
            mainLabel.textContent = 'Todos los productos';

            mainGrid.parentNode.insertBefore(
                mainLabel,
                mainGrid
            );
        }

        return {
            section,
            favoritesGrid: document.getElementById(
                'v5828b5i-favorites-grid'
            ),
            count: document.getElementById(
                'v5828b5i-favorites-count'
            ),
        };
    }

    function cardSelector(productId) {
        const safeId = (
            window.CSS
            && typeof window.CSS.escape === 'function'
        )
            ? window.CSS.escape(String(productId))
            : String(productId).replace(
                /["\\]/g,
                '\\$&'
            );

        return (
            '.product[data-product-id="'
            + safeId
            + '"]'
        );
    }

    function findCard(productId, mainGrid, favoritesGrid) {
        const selector = cardSelector(productId);

        return (
            mainGrid.querySelector(selector)
            || document
                .getElementById(
                    'v5515e-global-search-products'
                )
                ?.querySelector(selector)
            || favoritesGrid.querySelector(selector)
            || document.querySelector(selector)
            || null
        );
    }

    function selectedCategoryKey() {
        const params = new URL(
            window.location.href
        ).searchParams;

        return String(
            params.get('pos_category') || 'all'
        );
    }

    function categoryShowsFavorites(key) {
        const normalized = String(key || 'all');

        return (
            normalized === 'all'
            || normalized === 'todos'
            || normalized === '0'
            || normalized === 'favorites'
            || normalized === ''
        );
    }

    function installFavorites() {
        const mainGrid = document.getElementById(
            'v5828b5i-main-products-grid'
        );

        if (!mainGrid || favoriteIds.length === 0) {
            window.BEXIA_V5828B5K_RUNTIME_STATE = {
                favoriteIds,
                installed: 0,
                mainGridFound: Boolean(mainGrid),
                reason: favoriteIds.length === 0
                    ? 'no_favorite_ids'
                    : 'main_grid_missing',
                readyState: document.readyState,
            };

            return 0;
        }

        const refs = ensureSection(mainGrid);

        if (!refs.favoritesGrid) {
            return 0;
        }

        let installed = 0;

        favoriteIds.forEach(function (productId) {
            const card = findCard(
                productId,
                mainGrid,
                refs.favoritesGrid
            );

            if (!card) {
                return;
            }

            card.dataset.productIsFavorite = '1';

            card.classList.remove(
                'v5515e-hidden-search-product'
            );

            card.style.removeProperty('display');

            if (card.parentElement !== refs.favoritesGrid) {
                refs.favoritesGrid.appendChild(card);
            }

            installed++;
        });

        if (refs.count) {
            refs.count.textContent =
                installed
                + ' favorito'
                + (installed === 1 ? '' : 's');
        }

        refs.section.hidden = (
            installed === 0
            || !categoryShowsFavorites(
                selectedCategoryKey()
            )
        );

        refs.section.dataset.favoriteCardsMoved =
            String(installed);

        refs.section.dataset.runtimeVersion =
            'V5.82.8b5k2';

        window.BEXIA_V5828B5K_RUNTIME_STATE = {
            favoriteIds,
            installed,
            mainGridFound: true,
            sectionFound: true,
            readyState: document.readyState,
            executedAt: new Date().toISOString(),
        };

        console.info(
            'BEXIA V5.82.8b5k2 favoritos instalados:',
            window.BEXIA_V5828B5K_RUNTIME_STATE
        );

        return installed;
    }

    window.BEXIA_POS_INSTALL_FIXED_FAVORITES =
        installFavorites;

    function scheduleInstall() {
        [0, 60, 250, 900].forEach(function (delay) {
            window.setTimeout(
                installFavorites,
                delay
            );
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            scheduleInstall,
            { once: true }
        );
    } else {
        scheduleInstall();
    }

    window.addEventListener(
        'pageshow',
        scheduleInstall
    );

    document.addEventListener(
        'bexia:pos-products-refreshed',
        scheduleInstall
    );

    document.addEventListener(
        'click',
        function (event) {
            const link = event.target.closest(
                '.categories a.cat'
                + '[data-v5828b5e-category]'
            );

            if (!link) {
                return;
            }

            const key = String(
                link.dataset.v5828b5eCategory || ''
            );

            window.requestAnimationFrame(function () {
                const section = document.getElementById(
                    'v5828b5i-favorites-section'
                );

                if (section) {
                    section.hidden =
                        !categoryShowsFavorites(key);
                }

                if (categoryShowsFavorites(key)) {
                    installFavorites();
                }
            });
        },
        true
    );

    /*
     * Primera ejecución inmediata, incluso si DOMContentLoaded
     * ya ocurrió antes de evaluar este script.
     */
    installFavorites();
})();
</script>


<script id="bexia-v582-p3-a34c2-switch-script">
document.addEventListener('DOMContentLoaded', function () {
    if (window.BEXIA_POS_SWITCH_CASHIER_A34C2_READY) return;
    window.BEXIA_POS_SWITCH_CASHIER_A34C2_READY = true;

    const button = document.getElementById('v582p3-a34c2-switch-button');
    const modal = document.getElementById('v582p3-a34c2-switch-modal');
    const form = document.getElementById('v582p3-a34c2-switch-form');
    const staff = document.getElementById('v582p3-a34c2-switch-staff');
    const pin = document.getElementById('v582p3-a34c2-switch-pin');
    const errorBox = document.getElementById('v582p3-a34c2-switch-error');
    const closeButton = document.getElementById('v582p3-a34c2-switch-close');
    const cancelButton = document.getElementById('v582p3-a34c2-switch-cancel');
    const confirmButton = document.getElementById('v582p3-a34c2-switch-confirm');

    if (!button || !modal || !form || !staff || !pin) return;

    function currentCartItems() {
        const api = window.BEXIA_POS_CART_API || null;
        if (!api || typeof api.getItems !== 'function') return null;
        const items = api.getItems();
        return Array.isArray(items) ? items : [];
    }

    function showNotice(message) {
        if (typeof window.showPosNotice === 'function') {
            window.showPosNotice(message, 'warning');
            return;
        }

        const notice = document.getElementById('v5356-pos-notice');
        if (notice) {
            notice.textContent = message;
            notice.style.display = 'block';
            return;
        }

        window.alert(message);
    }

    function setError(message) {
        if (!errorBox) return;
        errorBox.textContent = message || '';
        errorBox.style.display = message ? 'block' : 'none';
    }

    function cartIsSafeToSwitch() {
        const items = currentCartItems();

        if (items === null) {
            showNotice('Espera a que termine de cargar el carrito.');
            return false;
        }

        if (
            items.length > 0
            || (
                window.BEXIA_POS_LOADED_PENDING_ORDER
                && window.BEXIA_POS_LOADED_PENDING_ORDER.id
            )
        ) {
            showNotice(
                'Guarda el ticket pendiente o vacía el carrito antes de cambiar de cajero.'
            );
            return false;
        }

        return true;
    }

    function openModal() {
        if (!cartIsSafeToSwitch()) return;

        setError('');
        staff.value = '';
        pin.value = '';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(function () { staff.focus(); }, 40);
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        setError('');
        pin.value = '';
    }

    button.addEventListener('click', openModal);
    closeButton?.addEventListener('click', closeModal);
    cancelButton?.addEventListener('click', closeModal);

    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (!cartIsSafeToSwitch()) return;

        const staffKey = String(staff.value || '').trim();
        const pinValue = String(pin.value || '').trim();

        if (!staffKey) {
            setError('Selecciona el nuevo cajero.');
            staff.focus();
            return;
        }

        if (!pinValue) {
            setError('Captura el NIP del nuevo cajero.');
            pin.focus();
            return;
        }

        setError('');

        if (confirmButton) {
            confirmButton.disabled = true;
            confirmButton.textContent = 'Validando...';
        }

        try {
            const response = await fetch(
                '/pos/sessions/{{ (int) $session->id }}/switch-cashier',
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        staff_key: staffKey,
                        pin: pinValue,
                    }),
                }
            );

            const data = await response.json().catch(function () {
                return {};
            });

            if (!response.ok || data.ok === false) {
                throw new Error(
                    data.message
                    || data.errors?.pin?.[0]
                    || data.errors?.staff_key?.[0]
                    || 'No se pudo cambiar de cajero.'
                );
            }

            pin.value = '';

            if (confirmButton) {
                confirmButton.textContent = 'Cajero cambiado';
            }

            window.location.assign(
                data.redirect_url
                || '/pos/sessions/{{ (int) $session->id }}/screen'
            );
        } catch (error) {
            setError(error.message || 'No se pudo cambiar de cajero.');
            pin.value = '';
            pin.focus();

            if (confirmButton) {
                confirmButton.disabled = false;
                confirmButton.textContent = 'Cambiar cajero';
            }
        }
    });
});
</script>



<script id="v582p6e-pending-print-window-guard-v2">
/*
 * BEXIA_V582P6E_PENDING_PRINT_GUARD_V2
 *
 * Detecta la creación de tickets pendientes por eventos,
 * texto del control, llamada al endpoint o stack JavaScript.
 * Solo actúa cuando la configuración del PDV está apagada.
 */
(function () {
    'use strict';

    if (
        window.BEXIA_POS_PENDING_PRINT_GUARD_V2_INSTALLED
    ) {
        return;
    }

    window.BEXIA_POS_PENDING_PRINT_GUARD_V2_INSTALLED =
        true;

    window.BEXIA_POS_PRINT_PENDING_TICKET_SOURCE =
        'p6e_pending_print_guard_v2';

    const nativeOpen =
        window.BEXIA_POS_NATIVE_WINDOW_OPEN
        || window.BEXIA_POS_ORIGINAL_WINDOW_OPEN
        || window.open.bind(window);

    window.BEXIA_POS_NATIVE_WINDOW_OPEN = nativeOpen;

    const nativeFetch =
        window.BEXIA_POS_PENDING_PRINT_NATIVE_FETCH
        || window.fetch.bind(window);

    window.BEXIA_POS_PENDING_PRINT_NATIVE_FETCH =
        nativeFetch;

    let pendingContextUntil = 0;

    const state = {
        installed: true,
        triggerCount: 0,
        blockedCount: 0,
        lastTrigger: null,
        lastBlockedUrl: null,
        lastBlockedAt: null,
        lastStack: null
    };

    window.BEXIA_POS_PENDING_PRINT_GUARD_STATE = state;

    function settingIsDisabled() {
        return (
            window
                .BEXIA_POS_PRINT_PENDING_TICKET_ON_CREATE
            === false
        );
    }

    function normalize(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/\s+/g, ' ')
            .trim();
    }

    function armPendingContext(reason) {
        if (!settingIsDisabled()) {
            pendingContextUntil = 0;
            return;
        }

        pendingContextUntil = Date.now() + 45000;
        state.triggerCount += 1;
        state.lastTrigger = String(reason || 'unknown');

        console.info(
            'BEXIA: contexto de ticket pendiente '
            + 'detectado:',
            state.lastTrigger
        );
    }

    function controlDescription(target) {
        if (
            !target
            || typeof target.closest !== 'function'
        ) {
            return null;
        }

        const control = target.closest(
            'button, a, [role="button"], '
            + 'input[type="button"], '
            + 'input[type="submit"]'
        );

        if (!control) {
            return null;
        }

        const id = normalize(control.id);
        const text = normalize(
            control.innerText
            || control.textContent
            || control.value
            || control.getAttribute('aria-label')
            || control.getAttribute('title')
        );

        const action = normalize(
            control.getAttribute('data-action')
            || control.getAttribute('data-testid')
            || control.getAttribute('name')
        );

        const combined = [
            id,
            text,
            action
        ].join(' ');

        if (
            combined.includes('pending')
            || combined.includes('pendiente')
            || combined.includes('ticket pendiente')
        ) {
            return combined;
        }

        return null;
    }

    function detectControlEvent(event) {
        const description = controlDescription(
            event.target
        );

        if (description) {
            armPendingContext(
                event.type + ':' + description
            );
        }
    }

    [
        'pointerdown',
        'mousedown',
        'touchstart',
        'click'
    ].forEach(function (eventName) {
        document.addEventListener(
            eventName,
            detectControlEvent,
            true
        );
    });

    document.addEventListener(
        'keydown',
        function (event) {
            if (
                event.key !== 'Enter'
                && event.key !== ' '
            ) {
                return;
            }

            const description = controlDescription(
                document.activeElement
            );

            if (description) {
                armPendingContext(
                    'keydown:' + description
                );
            }
        },
        true
    );

    window.fetch = function (input, options) {
        const url = normalize(
            typeof input === 'string'
                ? input
                : (
                    input
                    && typeof input.url === 'string'
                        ? input.url
                        : ''
                )
        );

        const method = normalize(
            options
            && options.method
                ? options.method
                : 'get'
        );

        const isPendingSave =
            method === 'post'
            && (
                /\/pos\/sessions\/\d+\/orders/.test(url)
                || /\/pos\/orders\/\d+\/pending-update/.test(
                    url
                )
            );

        if (isPendingSave) {
            armPendingContext(
                'fetch:' + method + ':' + url
            );
        }

        return nativeFetch(input, options);
    };

    function createBlockedWindow() {
        const blockedLocation = {};

        Object.defineProperty(
            blockedLocation,
            'href',
            {
                configurable: true,
                enumerable: true,
                get: function () {
                    return '';
                },
                set: function () {
                    return true;
                }
            }
        );

        return {
            closed: false,
            location: blockedLocation,
            document: {
                open: function () {},
                write: function () {},
                close: function () {}
            },
            close: function () {
                this.closed = true;
            },
            focus: function () {}
        };
    }

    function activeControlLooksPending() {
        return Boolean(
            controlDescription(
                document.activeElement
            )
        );
    }

    function stackLooksPending(stack) {
        const normalizedStack = normalize(stack);

        return (
            normalizedStack.includes(
                'creatependingticket'
            )
            || normalizedStack.includes(
                'pendingticket'
            )
            || normalizedStack.includes(
                'pending-ticket'
            )
            || normalizedStack.includes(
                'pending'
            )
        );
    }

    window.open = function (url, target, features) {
        const normalizedUrl = normalize(url);
        const stack = String(
            new Error('BEXIA_PENDING_PRINT_TRACE').stack
            || ''
        );

        const pendingPrintUrl =
            normalizedUrl.includes(
                '/pending-ticket/print'
            );

        const blankWindow =
            normalizedUrl === ''
            || normalizedUrl === 'about:blank';

        const pendingContextActive =
            Date.now() <= pendingContextUntil;

        const pendingOrigin =
            pendingContextActive
            || activeControlLooksPending()
            || stackLooksPending(stack);

        const shouldBlock =
            settingIsDisabled()
            && (
                pendingPrintUrl
                || (
                    blankWindow
                    && pendingOrigin
                )
            );

        if (shouldBlock) {
            pendingContextUntil = 0;

            state.blockedCount += 1;
            state.lastBlockedUrl =
                normalizedUrl || '(blank)';
            state.lastBlockedAt =
                new Date().toISOString();
            state.lastStack = stack;

            console.info(
                'BEXIA: impresión automática del '
                + 'ticket pendiente bloqueada.',
                {
                    url: state.lastBlockedUrl,
                    trigger: state.lastTrigger,
                    blockedCount: state.blockedCount
                }
            );

            return createBlockedWindow();
        }

        return nativeOpen(url, target, features);
    };
})();
</script>



<style id="v582p6f-pending-created-modal-style">
/* BEXIA_V582P6F_PENDING_CREATE_SINGLE_FLIGHT_MODAL */
.v582p6f-modal {
    position: fixed;
    inset: 0;
    z-index: 2147483000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, .38);
    backdrop-filter: blur(2px);
}

.v582p6f-modal.is-open {
    display: flex;
}

.v582p6f-card {
    width: min(390px, 100%);
    border: 1px solid #dbeafe;
    border-radius: 22px;
    padding: 26px 24px 22px;
    background: #ffffff;
    text-align: center;
    box-shadow: 0 25px 65px rgba(15, 23, 42, .28);
}

.v582p6f-icon {
    width: 62px;
    height: 62px;
    margin: 0 auto 14px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #dcfce7;
    color: #15803d;
    font-size: 34px;
    font-weight: 950;
}

.v582p6f-title {
    margin: 0;
    color: #0f172a;
    font-size: 21px;
    font-weight: 950;
}

.v582p6f-message {
    margin: 9px 0 0;
    color: #475569;
    font-size: 14px;
    line-height: 1.45;
    font-weight: 700;
}

.v582p6f-folio {
    display: inline-block;
    margin-top: 12px;
    padding: 7px 11px;
    border-radius: 10px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 13px;
    font-weight: 950;
}

.v582p6f-close {
    width: 100%;
    margin-top: 18px;
    border: 0;
    border-radius: 13px;
    padding: 12px 16px;
    background: #2563eb;
    color: #ffffff;
    font-size: 14px;
    font-weight: 900;
    cursor: pointer;
}
</style>

<div
    id="v582p6f-pending-created-modal"
    class="v582p6f-modal"
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    aria-labelledby="v582p6f-pending-created-title"
>
    <div class="v582p6f-card">
        <div
            class="v582p6f-icon"
            aria-hidden="true"
        >
            ✓
        </div>

        <h2
            id="v582p6f-pending-created-title"
            class="v582p6f-title"
        >
            Ticket pendiente generado
        </h2>

        <p
            id="v582p6f-pending-created-message"
            class="v582p6f-message"
        >
            El ticket se guardó correctamente.
        </p>

        <div
            id="v582p6f-pending-created-folio"
            class="v582p6f-folio"
            hidden
        ></div>

        <button
            type="button"
            id="v582p6f-pending-created-close"
            class="v582p6f-close"
        >
            Aceptar
        </button>
    </div>
</div>

<script id="v582p6f-pending-create-single-flight">
/*
 * BEXIA_V582P6F_PENDING_CREATE_SINGLE_FLIGHT_MODAL
 *
 * Evita solicitudes repetidas al crear o actualizar un
 * ticket pendiente y muestra un modal corto de confirmación.
 */
(function () {
    'use strict';

    if (
        window
            .BEXIA_POS_PENDING_CREATE_SINGLE_FLIGHT_INSTALLED
    ) {
        return;
    }

    window
        .BEXIA_POS_PENDING_CREATE_SINGLE_FLIGHT_INSTALLED =
        true;

    const nativeFetch =
        window.fetch.bind(window);

    const state = {
        installed: true,
        inFlight: false,
        requestCount: 0,
        blockedClicks: 0,
        blockedRequests: 0,
        lastRequestKey: null,
        lastOrderId: null,
        lastNumber: null,
        lastMode: null,
        lastSuccessAt: null,
        lockUntil: 0
    };

    window.BEXIA_POS_PENDING_CREATE_STATE = state;

    let modalTimer = null;

    function modal() {
        return document.getElementById(
            'v582p6f-pending-created-modal'
        );
    }

    function openModal(options) {
        const root = modal();

        if (!root) {
            return;
        }

        const title = document.getElementById(
            'v582p6f-pending-created-title'
        );

        const message = document.getElementById(
            'v582p6f-pending-created-message'
        );

        const folio = document.getElementById(
            'v582p6f-pending-created-folio'
        );

        const values = options || {};

        if (title) {
            title.textContent =
                values.title
                || 'Ticket pendiente generado';
        }

        if (message) {
            message.textContent =
                values.message
                || 'El ticket se guardó correctamente.';
        }

        if (folio) {
            const number = String(
                values.number || ''
            ).trim();

            folio.textContent = number;

            folio.hidden = number === '';
        }

        root.classList.add('is-open');
        root.setAttribute('aria-hidden', 'false');

        window.clearTimeout(modalTimer);

        modalTimer = window.setTimeout(
            closeModal,
            Number(values.timeout || 2600)
        );
    }

    function closeModal() {
        const root = modal();

        if (!root) {
            return;
        }

        root.classList.remove('is-open');
        root.setAttribute('aria-hidden', 'true');
    }

    window.BEXIA_POS_SHOW_PENDING_CREATED_MODAL =
        openModal;

    document
        .getElementById(
            'v582p6f-pending-created-close'
        )
        ?.addEventListener(
            'click',
            closeModal
        );

    modal()?.addEventListener(
        'click',
        function (event) {
            if (event.target === modal()) {
                closeModal();
            }
        }
    );

    function createButton(target) {
        if (
            !target
            || typeof target.closest !== 'function'
        ) {
            return null;
        }

        return target.closest(
            '#v5349-create-pending-ticket'
        );
    }

    function isTemporarilyLocked() {
        return (
            state.inFlight
            || Date.now() < state.lockUntil
        );
    }

    function showBlockedMessage() {
        if (state.inFlight) {
            openModal({
                title: 'Generando ticket',
                message:
                    'Espera un momento. '
                    + 'El ticket ya se está guardando.',
                timeout: 1800
            });

            return;
        }

        openModal({
            title: 'Ticket ya generado',
            message:
                'El ticket pendiente ya fue creado.',
            number: state.lastNumber,
            timeout: 2200
        });
    }

    document.addEventListener(
        'click',
        function (event) {
            if (!createButton(event.target)) {
                return;
            }

            if (!isTemporarilyLocked()) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            state.blockedClicks += 1;

            showBlockedMessage();

            console.info(
                'BEXIA: clic repetido para crear '
                + 'ticket pendiente bloqueado.',
                {
                    blockedClicks:
                        state.blockedClicks,
                    lastNumber:
                        state.lastNumber
                }
            );
        },
        true
    );

    document.addEventListener(
        'keydown',
        function (event) {
            if (
                event.key !== 'Enter'
                && event.key !== ' '
            ) {
                return;
            }

            if (
                !createButton(
                    document.activeElement
                )
            ) {
                return;
            }

            if (!isTemporarilyLocked()) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            state.blockedClicks += 1;

            showBlockedMessage();
        },
        true
    );

    function requestUrl(input) {
        if (typeof input === 'string') {
            return input;
        }

        if (
            input
            && typeof input.url === 'string'
        ) {
            return input.url;
        }

        return '';
    }

    function requestMethod(input, options) {
        if (
            options
            && options.method
        ) {
            return String(
                options.method
            ).toUpperCase();
        }

        if (
            input
            && typeof input.method === 'string'
        ) {
            return String(
                input.method
            ).toUpperCase();
        }

        return 'GET';
    }

    function requestBody(input, options) {
        if (
            options
            && typeof options.body === 'string'
        ) {
            return options.body;
        }

        return '';
    }

    function isPendingSave(url, method) {
        if (method !== 'POST') {
            return false;
        }

        return (
            /\/pos\/sessions\/\d+\/orders(?:\?|$)/.test(
                url
            )
            || /\/pos\/orders\/\d+\/pending-update(?:\?|$)/.test(
                url
            )
        );
    }

    function duplicateResponse() {
        return new Response(
            JSON.stringify({
                ok: false,
                duplicate_prevented: true,
                order_id: state.lastOrderId,
                number: state.lastNumber,
                message:
                    state.inFlight
                        ? 'El ticket ya se está generando.'
                        : 'El ticket pendiente ya fue generado.'
            }),
            {
                status: 409,
                headers: {
                    'Content-Type':
                        'application/json'
                }
            }
        );
    }

    window.fetch = function (input, options) {
        const url = requestUrl(input);
        const method = requestMethod(
            input,
            options
        );

        if (!isPendingSave(url, method)) {
            return nativeFetch(input, options);
        }

        const body = requestBody(
            input,
            options
        );

        const key = [
            method,
            url,
            body
        ].join('|');

        const duplicateByFlight =
            state.inFlight;

        const duplicateByCooldown =
            Date.now() < state.lockUntil
            && state.lastRequestKey === key;

        if (
            duplicateByFlight
            || duplicateByCooldown
        ) {
            state.blockedRequests += 1;

            showBlockedMessage();

            console.info(
                'BEXIA: solicitud repetida de '
                + 'ticket pendiente bloqueada.',
                {
                    blockedRequests:
                        state.blockedRequests,
                    inFlight:
                        state.inFlight,
                    lastNumber:
                        state.lastNumber
                }
            );

            return Promise.resolve(
                duplicateResponse()
            );
        }

        state.inFlight = true;
        state.requestCount += 1;
        state.lastRequestKey = key;
        state.lastMode =
            url.includes('/pending-update')
                ? 'update'
                : 'create';

        const request = nativeFetch(
            input,
            options
        );

        return request
            .then(async function (response) {
                let data = {};

                try {
                    data = await response
                        .clone()
                        .json();
                } catch (error) {
                    data = {};
                }

                if (
                    response.ok
                    && data.ok !== false
                ) {
                    state.lastOrderId =
                        data.order_id
                        || data.id
                        || (
                            data.order
                            && data.order.id
                        )
                        || null;

                    state.lastNumber =
                        data.number
                        || (
                            data.order
                            && data.order.number
                        )
                        || '';

                    state.lastSuccessAt =
                        new Date().toISOString();

                    state.lockUntil =
                        Date.now() + 7000;

                    openModal({
                        title:
                            state.lastMode === 'update'
                                ? 'Ticket pendiente actualizado'
                                : 'Ticket pendiente generado',
                        message:
                            state.lastMode === 'update'
                                ? 'Los cambios se guardaron correctamente.'
                                : 'El ticket se guardó correctamente.',
                        number:
                            state.lastNumber,
                        timeout: 3000
                    });

                    console.info(
                        'BEXIA: ticket pendiente '
                        + 'confirmado por single-flight.',
                        {
                            orderId:
                                state.lastOrderId,
                            number:
                                state.lastNumber,
                            requestCount:
                                state.requestCount
                        }
                    );
                }

                return response;
            })
            .catch(function (error) {
                state.lockUntil = 0;
                throw error;
            })
            .finally(function () {
                state.inFlight = false;
            });
    };
})();
</script>

</body>
</html>


<script id="v5514c-close-session-visual-guard">
window.BEXIA_CAN_CLOSE_POS_SESSION_V5514C = @json((bool) ($v5514cCanCloseSession ?? false));
window.BEXIA_CAN_CANCEL_PENDING_TICKETS_V5514C = @json((bool) ($v5514cCanCancelPendingTickets ?? false));

document.addEventListener('submit', function (event) {
    const form = event.target;

    if (!form || !form.id || !String(form.id).includes('close-session')) {
        return;
    }

    if (!window.BEXIA_CAN_CLOSE_POS_SESSION_V5514C) {
        event.preventDefault();
        event.stopPropagation();

        if (typeof showPosNotice === 'function') {
            showPosNotice('No tienes permiso para cerrar sesiones de caja.', 'warning');
        } else {
            alert('No tienes permiso para cerrar sesiones de caja.');
        }
    }
}, true);

document.addEventListener('click', function (event) {
    const closeButton = event.target && event.target.closest
        ? event.target.closest('#v5484-close-session-confirm, .v5333-close-session')
        : null;

    if (closeButton && !window.BEXIA_CAN_CLOSE_POS_SESSION_V5514C) {
        event.preventDefault();
        event.stopPropagation();

        if (typeof showPosNotice === 'function') {
            showPosNotice('No tienes permiso para cerrar sesiones de caja.', 'warning');
        } else {
            alert('No tienes permiso para cerrar sesiones de caja.');
        }
    }
}, true);
</script>


{{-- V5.51.5C_FORCE_ALL_URL_DONE --}}


<script id="v5515e-global-search-script">
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('v5490b-product-search');
    const clearButton = document.getElementById('v5490b-clear-search');
    const visibleGrid = document.getElementById(
        'v5828b5i-main-products-grid'
    );
    const hiddenPool = document.getElementById('v5515e-global-search-products');

    if (!searchInput || !visibleGrid || !hiddenPool) {
        return;
    }

    const originalVisibleProducts = Array.from(visibleGrid.querySelectorAll('.product:not(.v5515e-hidden-search-product)'));
    const hiddenProducts = Array.from(hiddenPool.querySelectorAll('.v5515e-hidden-search-product'));

    function normalize(value) {
        return (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function matches(product, query) {
        if (!query) {
            return true;
        }

        const haystack = normalize([
            product.dataset.productSearch || '',
            product.dataset.productName || '',
            product.dataset.productReference || '',
            product.dataset.productBarcode || '',
            product.dataset.productSku || '',
            product.dataset.productCode || ''
        ].join(' '));

        return haystack.includes(query);
    }

    function restoreCategoryView() {
        hiddenProducts.forEach(function (product) {
            product.style.display = 'none';
            hiddenPool.appendChild(product);
        });

        originalVisibleProducts.forEach(function (product) {
            product.style.display = '';
        });
    }

    function applyGlobalSearch() {
        const query = normalize(searchInput.value);

        if (!query) {
            restoreCategoryView();
            return;
        }

        originalVisibleProducts.forEach(function (product) {
            product.style.display = matches(product, query) ? '' : 'none';
        });

        hiddenProducts.forEach(function (product) {
            if (matches(product, query)) {
                product.style.display = '';
                visibleGrid.appendChild(product);
            } else {
                product.style.display = 'none';
                hiddenPool.appendChild(product);
            }
        });
    }

    searchInput.addEventListener('input', applyGlobalSearch);
    searchInput.addEventListener('search', applyGlobalSearch);
    searchInput.addEventListener('keyup', applyGlobalSearch);

    if (clearButton) {
        clearButton.addEventListener('click', function () {
            setTimeout(function () {
                searchInput.value = '';
                restoreCategoryView();
            }, 0);
        });
    }
});
</script>


<style id="v5543b1-pdv-serial-selector-style">
    /* BEXIA_V5543B1_PDV_SERIAL_SELECTOR */
    .v5543b1-serial-box {
        grid-column: 1 / -1;
        margin-top: 8px;
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        border-radius: 12px;
        padding: 8px 10px;
        display: grid;
        gap: 6px;
    }

    .v5543b1-serial-label {
        font-size: 11px;
        font-weight: 950;
        color: #1e3a8a;
    }

    .v5543b1-serial-select {
        width: 100%;
        border: 1px solid #93c5fd;
        border-radius: 10px;
        padding: 8px 9px;
        background: #ffffff;
        color: #0f172a;
        font-size: 12px;
        font-weight: 850;
    }

    .v5543b1-serial-warning {
        color: #b91c1c;
        font-size: 11px;
        font-weight: 900;
    }

    .v5544b-serial-readonly {
        border: 1px solid #86efac;
        background: #f0fdf4;
        color: #14532d;
        border-radius: 10px;
        padding: 8px 9px;
        font-size: 12px;
        font-weight: 950;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .v5544b-serial-readonly small {
        color: #166534;
        font-size: 10px;
        font-weight: 800;
        white-space: nowrap;
    }
</style>


<style id="v5554i2-pdv-lot-selector-style">
    .v5554i2-lot-box {
        grid-column: 1 / -1;
        margin-top: 8px;
        padding: 9px 10px;
        border: 1px solid #f59e0b;
        background: #fffbeb;
        border-radius: 12px;
        display: grid;
        gap: 6px;
    }
    .v5554i2-lot-title {
        font-size: 11px;
        font-weight: 950;
        color: #92400e;
    }
    .v5554i2-lot-select {
        width: 100%;
        border: 1px solid #f59e0b;
        background: #ffffff;
        border-radius: 10px;
        padding: 8px 9px;
        font-size: 12px;
        font-weight: 850;
        color: #0f172a;
    }
    .v5554i2-lot-help {
        font-size: 11px;
        font-weight: 800;
        color: #92400e;
    }
    .v5554i2-lot-help.is-ok {
        color: #166534;
    }
    .v5554i2-lot-help.is-error {
        color: #b91c1c;
    }
</style>

<script id="v5554i2-pdv-lot-selector-script">
(function () {
    "use strict";

    if (window.BEXIA_V5554J_PDV_LOT_SELECTOR_READY) {
        return;
    }

    window.BEXIA_V5554J_PDV_LOT_SELECTOR_READY = true;
    window.BEXIA_POS_LOT_SELECTIONS = window.BEXIA_POS_LOT_SELECTIONS || {};
    window.BEXIA_POS_LOT_REQUIREMENTS = window.BEXIA_POS_LOT_REQUIREMENTS || {};
    window.BEXIA_V5554J_LOT_INTERACT_UNTIL = 0;

    const lotCache = {};

    function sessionId() {
        const match = String(window.location.pathname || "").match(/\/pos\/sessions\/(\d+)\/screen/);
        return match ? match[1] : null;
    }

    function markLotInteracting() {
        window.BEXIA_V5554J_LOT_INTERACT_UNTIL = Date.now() + 2200;
    }

    function lotSelectorIsActive() {
        const active = document.activeElement;

        if (Date.now() < Number(window.BEXIA_V5554J_LOT_INTERACT_UNTIL || 0)) {
            return true;
        }

        return Boolean(
            active
            && active.closest
            && active.closest("[data-v5554i2-lot-box='1']")
        );
    }

    function api() {
        return window.BEXIA_POS_CART_API || null;
    }

    function originalItems() {
        const cartApi = api();

        if (!cartApi) {
            return [];
        }

        if (typeof cartApi.__v5554jOriginalGetItems === "function") {
            return cartApi.__v5554jOriginalGetItems() || [];
        }

        if (typeof cartApi.__v5554i2OriginalGetItems === "function") {
            return cartApi.__v5554i2OriginalGetItems() || [];
        }

        if (typeof cartApi.__v5543b1OriginalGetItems === "function") {
            return cartApi.__v5543b1OriginalGetItems() || [];
        }

        if (typeof cartApi.getItems === "function") {
            return cartApi.getItems() || [];
        }

        return [];
    }

    function itemProductId(item) {
        return Number((item && (item.product_id || item.id || (item.raw && item.raw.product_id) || (item.metadata && item.metadata.product_id))) || 0);
    }

    function itemVariantId(item) {
        return Number((item && (item.product_variant_id || item.variant_id || (item.raw && item.raw.product_variant_id) || (item.metadata && item.metadata.product_variant_id))) || 0);
    }

    function itemKey(item) {
        return String(itemProductId(item) || "") + ":" + String(itemVariantId(item) || "");
    }

    function selectedForItem(item) {
        return window.BEXIA_POS_LOT_SELECTIONS[itemKey(item)] || null;
    }

    function setSelectedForItem(item, lot) {
        const key = itemKey(item);

        if (!lot || !lot.stock_lot_id) {
            delete window.BEXIA_POS_LOT_SELECTIONS[key];
            return;
        }

        window.BEXIA_POS_LOT_SELECTIONS[key] = {
            stock_lot_id: Number(lot.stock_lot_id || lot.id || lot.lot_id || 0),
            lot_id: Number(lot.stock_lot_id || lot.id || lot.lot_id || 0),
            lot_number: String(lot.lot_number || ""),
            label: String(lot.label || lot.lot_number || ""),
            product_variant_id: Number(lot.product_variant_id || itemVariantId(item) || 0) || null
        };
    }

    function setNotice(message) {
        const cartApi = api();

        if (cartApi && typeof cartApi.setWarning === "function") {
            cartApi.setWarning(message);
            return;
        }

        alert(message);
    }

    async function fetchLotsForItem(item) {
        const sid = sessionId();
        const productId = itemProductId(item);
        const variantId = itemVariantId(item);

        if (!sid || productId <= 0) {
            return { ok: false, requires_lot: false, lots: [] };
        }

        const cacheKey = String(productId) + ":" + String(variantId || "");

        if (lotCache[cacheKey]) {
            return lotCache[cacheKey];
        }

        const url = "/pos/sessions/" + encodeURIComponent(sid)
            + "/lots?product_id=" + encodeURIComponent(productId)
            + (variantId > 0 ? ("&product_variant_id=" + encodeURIComponent(variantId)) : "");

        lotCache[cacheKey] = fetch(url, {
            method: "GET",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json",
                "X-Requested-With": "XMLHttpRequest"
            }
        })
            .then(function (response) {
                return response.json().catch(function () {
                    return {};
                }).then(function (data) {
                    if (!response.ok || data.ok === false) {
                        return {
                            ok: false,
                            requires_lot: false,
                            lots: [],
                            message: data.message || "No se pudieron cargar lotes."
                        };
                    }

                    return data;
                });
            })
            .catch(function (error) {
                return {
                    ok: false,
                    requires_lot: false,
                    lots: [],
                    message: error.message || "No se pudieron cargar lotes."
                };
            });

        return lotCache[cacheKey];
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? "" : value)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll("\"", "&quot;")
            .replaceAll("'", "&#039;");
    }

    function guardLotBox(box) {
        if (!box || box.__v5554jGuarded) {
            return;
        }

        box.__v5554jGuarded = true;

        [
            "pointerdown",
            "mousedown",
            "mouseup",
            "click",
            "dblclick",
            "touchstart",
            "touchend",
            "keydown",
            "keyup",
            "focusin",
            "focusout"
        ].forEach(function (eventName) {
            box.addEventListener(eventName, function (event) {
                markLotInteracting();
                event.stopPropagation();

                if (typeof event.stopImmediatePropagation === "function") {
                    event.stopImmediatePropagation();
                }
            }, true);
        });
    }

    function renderLotBox(line, item, data) {
        if (!line || !item) {
            return;
        }

        const lots = Array.isArray(data.lots) ? data.lots : [];
        const requiresLot = Boolean(data.requires_lot || lots.length > 0);

        window.BEXIA_POS_LOT_REQUIREMENTS[itemKey(item)] = requiresLot;

        let box = line.querySelector("[data-v5554i2-lot-box='1']");

        if (box && lotSelectorIsActive() && box.contains(document.activeElement)) {
            return;
        }

        if (!requiresLot) {
            if (box && !lotSelectorIsActive()) {
                box.remove();
            }
            return;
        }

        if (!box) {
            box = document.createElement("div");
            box.className = "v5554i2-lot-box";
            box.setAttribute("data-v5554i2-lot-box", "1");
            line.appendChild(box);
        }

        guardLotBox(box);

        const selected = selectedForItem(item);
        const selectedId = selected ? Number(selected.stock_lot_id || 0) : 0;

        const hash = JSON.stringify({
            key: itemKey(item),
            selectedId: selectedId,
            lots: lots.map(function (lot) {
                return [
                    Number(lot.stock_lot_id || lot.id || lot.lot_id || 0),
                    String(lot.label || lot.lot_number || "")
                ];
            })
        });

        if (box.dataset.v5554jHash === hash) {
            return;
        }

        if (box.contains(document.activeElement) || lotSelectorIsActive()) {
            return;
        }

        let html = "";
        html += "<div class='v5554i2-lot-title'>Lote requerido</div>";

        if (!lots.length) {
            html += "<div class='v5554i2-lot-help is-error'>No hay lotes disponibles para este producto.</div>";
            box.innerHTML = html;
            box.dataset.v5554jHash = hash;
            guardLotBox(box);
            return;
        }

        html += "<select class='v5554i2-lot-select' data-v5554i2-lot-select='1'>";
        html += "<option value=''>Selecciona lote...</option>";

        lots.forEach(function (lot) {
            const id = Number(lot.stock_lot_id || lot.id || lot.lot_id || 0);
            const label = String(lot.label || lot.lot_number || ("Lote #" + id));
            const lotNumber = String(lot.lot_number || label);
            const variantId = Number(lot.product_variant_id || itemVariantId(item) || 0) || "";
            const selectedAttr = selectedId && selectedId === id ? " selected" : "";

            html += "<option value='" + id + "'"
                + " data-lot-number='" + escapeHtml(lotNumber) + "'"
                + " data-product-variant-id='" + escapeHtml(variantId) + "'"
                + selectedAttr + ">"
                + escapeHtml(label)
                + "</option>";
        });

        html += "</select>";
        html += "<div class='v5554i2-lot-help" + (selectedId ? " is-ok" : "") + "'>"
            + (selectedId ? "Lote seleccionado." : "Selecciona el lote físico que se vende.")
            + "</div>";

        box.innerHTML = html;
        box.dataset.v5554jHash = hash;
        guardLotBox(box);

        const select = box.querySelector("[data-v5554i2-lot-select='1']");
        const help = box.querySelector(".v5554i2-lot-help");

        if (select) {
            select.addEventListener("pointerdown", markLotInteracting, true);
            select.addEventListener("mousedown", markLotInteracting, true);
            select.addEventListener("focus", markLotInteracting, true);
            select.addEventListener("click", function (event) {
                markLotInteracting();
                event.stopPropagation();

                if (typeof event.stopImmediatePropagation === "function") {
                    event.stopImmediatePropagation();
                }
            }, true);

            select.addEventListener("change", function () {
                markLotInteracting();

                const option = select.options[select.selectedIndex];

                if (!select.value) {
                    setSelectedForItem(item, null);

                    if (help) {
                        help.textContent = "Selecciona el lote físico que se vende.";
                        help.classList.remove("is-ok");
                    }

                    box.dataset.v5554jHash = "";

                    return;
                }

                setSelectedForItem(item, {
                    stock_lot_id: Number(select.value),
                    lot_number: option ? option.dataset.lotNumber : "",
                    product_variant_id: option ? Number(option.dataset.productVariantId || 0) || null : null
                });

                if (help) {
                    help.textContent = "Lote seleccionado: " + (option ? option.textContent : select.value);
                    help.classList.add("is-ok");
                }

                box.dataset.v5554jHash = "";
            });
        }
    }

    function patchCartApi() {
        const cartApi = api();

        if (!cartApi || typeof cartApi.getItems !== "function") {
            return false;
        }

        if (!cartApi.__v5554jOriginalGetItems) {
            cartApi.__v5554jOriginalGetItems = cartApi.getItems.bind(cartApi);

            cartApi.getItems = function () {
                const rows = cartApi.__v5554jOriginalGetItems() || [];

                return rows.map(function (item) {
                    const selected = selectedForItem(item);

                    if (!selected || !selected.stock_lot_id) {
                        return item;
                    }

                    return {
                        ...item,
                        stock_lot_id: Number(selected.stock_lot_id),
                        lot_id: Number(selected.stock_lot_id),
                        lot_number: selected.lot_number || "",
                        product_variant_id: selected.product_variant_id || item.product_variant_id || item.variant_id || null,
                        variant_id: selected.product_variant_id || item.variant_id || null
                    };
                });
            };
        }

        return true;
    }

    function hydrateCart() {
        if (lotSelectorIsActive()) {
            return;
        }

        if (!patchCartApi()) {
            return;
        }

        const cartBox = document.getElementById("v5339-cart-items");

        if (!cartBox) {
            return;
        }

        const lines = Array.from(cartBox.querySelectorAll(".v5339-cart-line"));
        const items = originalItems();

        lines.forEach(function (line, index) {
            const item = items[index];

            if (!item || itemProductId(item) <= 0) {
                return;
            }

            fetchLotsForItem(item).then(function (data) {
                renderLotBox(line, item, data || {});
            });
        });
    }

    function validateBeforePay() {
        const cartApi = api();
        const items = cartApi && typeof cartApi.getItems === "function"
            ? cartApi.getItems()
            : [];

        for (const item of items) {
            const key = itemKey(item);
            const requires = Boolean(window.BEXIA_POS_LOT_REQUIREMENTS[key]);

            if (!requires) {
                continue;
            }

            if (!item.stock_lot_id && !item.lot_id) {
                setNotice("Selecciona lote para " + String(item.name || item.product_name || "el producto") + " antes de cobrar.");
                return false;
            }
        }

        return true;
    }

    function patchFetch() {
        if (window.BEXIA_V5554J_FETCH_PATCHED) {
            return;
        }

        window.BEXIA_V5554J_FETCH_PATCHED = true;

        const originalFetch = window.fetch.bind(window);

        window.fetch = function (input, init) {
            const url = typeof input === "string" ? input : (input && input.url ? input.url : "");
            const method = String((init && init.method) || "GET").toUpperCase();

            if (method === "POST" && /\/pos\/sessions\/\d+\/orders/.test(url)) {
                if (!validateBeforePay()) {
                    return Promise.reject(new Error("Selecciona lote antes de cobrar."));
                }
            }

            return originalFetch(input, init);
        };
    }

    function boot() {
        patchCartApi();
        patchFetch();
        hydrateCart();

        const cartBox = document.getElementById("v5339-cart-items");

        if (cartBox && !cartBox.__v5554jLotObserver) {
            cartBox.__v5554jLotObserver = new MutationObserver(function () {
                if (lotSelectorIsActive()) {
                    return;
                }

                clearTimeout(window.__v5554jLotHydrateTimer);
                window.__v5554jLotHydrateTimer = setTimeout(hydrateCart, 120);
            });

            cartBox.__v5554jLotObserver.observe(cartBox, {
                childList: true,
                subtree: true
            });
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        setTimeout(boot, 250);
        setTimeout(boot, 900);
        setTimeout(boot, 1600);
    });

    setInterval(function () {
        if (!lotSelectorIsActive()) {
            hydrateCart();
        }
    }, 3500);
})();
</script>
<script id="v5543b1-pdv-serial-selector-script">
(function () {
    'use strict';

    if (window.BEXIA_V5543B1_PDV_SERIAL_SELECTOR_READY) {
        return;
    }

    window.BEXIA_V5543B1_PDV_SERIAL_SELECTOR_READY = true;
    window.BEXIA_POS_SERIAL_SELECTIONS = window.BEXIA_POS_SERIAL_SELECTIONS || {};
    window.BEXIA_POS_SERIAL_REQUIREMENTS = window.BEXIA_POS_SERIAL_REQUIREMENTS || {};
    // BEXIA_V5544C_PENDING_SERIAL_LOCK_MAP
    window.BEXIA_POS_SERIAL_LOCKED_BY_PENDING = window.BEXIA_POS_SERIAL_LOCKED_BY_PENDING || {};

    const serialCache = {};

    function clearSerialStateWhenCartIsEmpty() {
        // BEXIA_V5543C4_CLEAR_SERIAL_CACHE_WHEN_CART_EMPTY
        const cartBox = document.getElementById('v5339-cart-items');

        if (!cartBox) {
            return false;
        }

        const visibleLines = cartBox.querySelectorAll('.v5339-cart-line').length;
        const items = baseItems();

        if (visibleLines > 0 || items.length > 0) {
            return false;
        }

        Object.keys(serialCache || {}).forEach(function (key) {
            delete serialCache[key];
        });

        window.BEXIA_POS_SERIAL_SELECTIONS = {};
        window.BEXIA_POS_SERIAL_REQUIREMENTS = {};
        window.BEXIA_POS_SERIAL_LOCKED_BY_PENDING = {};

        document.querySelectorAll('[data-v5543b1-serial-box="1"]').forEach(function (box) {
            box.remove();
        });

        return true;
    }

    function sessionId() {
        const match = String(window.location.pathname || '').match(/\/pos\/sessions\/(\d+)\/screen/);
        return match ? match[1] : null;
    }

    function notice(message, type) {
        type = type || 'warning';

        const api = window.BEXIA_POS_CART_API || null;

        if (api && typeof api.setWarning === 'function') {
            api.setWarning(message);
            return;
        }

        const box = document.getElementById('v5356-pos-notice');

        if (box) {
            box.textContent = message;
            box.className = 'v5356-pos-notice';

            if (type === 'warning') {
                box.classList.add('is-warning');
            }

            if (type === 'error') {
                box.classList.add('is-error');
            }

            box.style.display = 'block';
            return;
        }

        alert(message);
    }

    function itemProductId(item) {
        return Number(item && (item.product_id || item.id) || 0);
    }

    function itemVariantId(item) {
        return Number(item && (item.product_variant_id || item.variant_id) || 0);
    }

    function itemKey(item) {
        const productId = itemProductId(item);
        const variantId = itemVariantId(item);

        return String(productId || '') + ':' + String(variantId || '');
    }

    function fallbackKey(item) {
        return String(itemProductId(item) || '') + ':';
    }

    function cacheKey(productId, variantId) {
        return String(productId || '') + ':' + String(variantId || '');
    }

    async function fetchSerials(item) {
        const sid = sessionId();
        const productId = itemProductId(item);
        const variantId = itemVariantId(item);

        if (!sid || productId <= 0) {
            return { ok: false, requires_serial: false, serials: [] };
        }

        // BEXIA_V5545I3_SELECTED_SERIAL_CACHE_KEY
        // Cuando viene de ticket pendiente, no reutilizar el cache de una venta nueva.
        const selectedSerialId = Number(
            item
            && item.serial_locked_from_pending
                ? (item.stock_serial_number_id || item.serial_number_id || 0)
                : 0
        );

        const key = cacheKey(productId, variantId) + (selectedSerialId > 0 ? (':selected:' + String(selectedSerialId)) : '');

        if (serialCache[key]) {
            return serialCache[key];
        }

        serialCache[key] = fetch(
            '/pos/sessions/' + encodeURIComponent(sid)
                + '/serials?product_id=' + encodeURIComponent(productId)
                + (variantId > 0 ? ('&product_variant_id=' + encodeURIComponent(variantId)) : '')
                + (selectedSerialId > 0 ? ('&selected_serial_id=' + encodeURIComponent(selectedSerialId)) : ''),
            {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }
        )
            .then(function (response) {
                return response.json().catch(function () {
                    return {};
                }).then(function (data) {
                    if (!response.ok || data.ok === false) {
                        return { ok: false, requires_serial: false, serials: [], message: data.message || 'No se pudieron cargar series.' };
                    }

                    return data;
                });
            })
            .catch(function (error) {
                return { ok: false, requires_serial: false, serials: [], message: error.message || 'No se pudieron cargar series.' };
            });

        return serialCache[key];
    }

    function selectedForItem(item) {
        return window.BEXIA_POS_SERIAL_SELECTIONS[itemKey(item)]
            || window.BEXIA_POS_SERIAL_SELECTIONS[fallbackKey(item)]
            || null;
    }

    function seedSelectionFromItem(item) {
        if (!item || !item.stock_serial_number_id) {
            return;
        }

        const serial = {
            stock_serial_number_id: Number(item.stock_serial_number_id),
            product_variant_id: Number(item.product_variant_id || item.variant_id || 0) || null,
            serial_number: item.serial_number || ''
        };

        lockSerialForPendingItem(item, serial);

        const existing = selectedForItem(item);

        if (existing && existing.stock_serial_number_id) {
            return;
        }

        setSelectedForItem(item, serial);
    }

    /* BEXIA_V5543C2_SEED_PENDING_SERIAL_SELECTION */

    function setSelectedForItem(item, serial) {
        const mainKey = itemKey(item);
        const fbKey = fallbackKey(item);

        if (!serial) {
            delete window.BEXIA_POS_SERIAL_SELECTIONS[mainKey];
            delete window.BEXIA_POS_SERIAL_SELECTIONS[fbKey];
            return;
        }

        window.BEXIA_POS_SERIAL_SELECTIONS[mainKey] = serial;
        window.BEXIA_POS_SERIAL_SELECTIONS[fbKey] = serial;
    }

    function lockSerialForPendingItem(item, serial) {
        if (!item || !serial || !serial.stock_serial_number_id) {
            return;
        }

        const lock = {
            stock_serial_number_id: Number(serial.stock_serial_number_id),
            product_variant_id: Number(serial.product_variant_id || item.product_variant_id || item.variant_id || 0) || null,
            serial_number: serial.serial_number || item.serial_number || ''
        };

        window.BEXIA_POS_SERIAL_LOCKED_BY_PENDING[itemKey(item)] = lock;
        window.BEXIA_POS_SERIAL_LOCKED_BY_PENDING[fallbackKey(item)] = lock;
    }

    function lockedSerialForPendingItem(item) {
        if (!item) {
            return null;
        }

        return window.BEXIA_POS_SERIAL_LOCKED_BY_PENDING[itemKey(item)]
            || window.BEXIA_POS_SERIAL_LOCKED_BY_PENDING[fallbackKey(item)]
            || null;
    }

    function patchCartApi() {
        const api = window.BEXIA_POS_CART_API || null;

        if (!api || typeof api.getItems !== 'function') {
            return false;
        }

        if (!api.__v5543b1OriginalGetItems) {
            api.__v5543b1OriginalGetItems = api.getItems.bind(api);

            api.getItems = function () {
                const rows = api.__v5543b1OriginalGetItems() || [];

                return rows.map(function (item) {
                    const selected = selectedForItem(item);

                    if (!selected || !selected.stock_serial_number_id) {
                        return item;
                    }

                    return {
                        ...item,
                        product_variant_id: selected.product_variant_id || item.product_variant_id || item.variant_id || null,
                        variant_id: selected.product_variant_id || item.variant_id || null,
                        stock_serial_number_id: selected.stock_serial_number_id,
                        serial_number_id: selected.stock_serial_number_id,
                        serial_number: selected.serial_number || ''
                    };
                });
            };
        }

        return true;
    }

    function baseItems() {
        const api = window.BEXIA_POS_CART_API || null;

        if (!api) {
            return [];
        }

        if (typeof api.__v5543b1OriginalGetItems === 'function') {
            return api.__v5543b1OriginalGetItems() || [];
        }

        if (typeof api.getItems === 'function') {
            return api.getItems() || [];
        }

        return [];
    }

    function guardSerialBoxEvents(box) {
        if (!box || box.__v5543b2SerialGuard) {
            return;
        }

        box.__v5543b2SerialGuard = true;

        ['pointerdown', 'mousedown', 'mouseup', 'click', 'dblclick', 'keydown', 'keyup', 'focusin'].forEach(function (eventName) {
            box.addEventListener(eventName, function (event) {
                event.stopPropagation();

                if (typeof event.stopImmediatePropagation === 'function') {
                    event.stopImmediatePropagation();
                }
            }, true);
        });
    }

    function serialSelectorIsActive() {
        const active = document.activeElement;

        return !!(
            active
            && active.closest
            && active.closest('[data-v5543b1-serial-box="1"]')
        );
    }

    /* BEXIA_V5543B2_FIX_SERIAL_DROPDOWN */
    function renderSerialBox(line, item, data) {
        let box = line.querySelector('[data-v5543b1-serial-box="1"]');

        if (box) {
            guardSerialBoxEvents(box);

            if (box.contains(document.activeElement)) {
                return;
            }
        }

        const requires = Boolean(data && data.requires_serial);
        let serials = Array.isArray(data && data.serials) ? data.serials : [];
        const key = itemKey(item);

        window.BEXIA_POS_SERIAL_REQUIREMENTS[key] = requires;

        if (!requires && serials.length === 0) {
            if (box) {
                box.remove();
            }
            return;
        }

        if (!box) {
            box = document.createElement('div');
            box.className = 'v5543b1-serial-box';
            box.setAttribute('data-v5543b1-serial-box', '1');
            line.appendChild(box);
        }

        guardSerialBoxEvents(box);

        let selected = selectedForItem(item);
        let selectedId = selected ? Number(selected.stock_serial_number_id || 0) : 0;

        // BEXIA_V5545F_CLEAR_STALE_SELECTED_FOR_NEW_LINE
        // Si es una línea nueva, no debe heredar la serie seleccionada de una prueba anterior.
        if (
            selectedId
            && item
            && ! item.serial_locked_from_pending
            && ! Number(item.stock_serial_number_id || item.serial_number_id || 0)
        ) {
            setSelectedForItem(item, null);
            selected = null;
            selectedId = 0;
        }

        // BEXIA_V5544_KEEP_PENDING_SERIAL_SELECTION
        // Si el item viene de un ticket pendiente con stock_serial_number_id,
        // conservar esa selección aunque el endpoint normal de disponibles no la regrese.
        const selectedComesFromPendingItem = Boolean(
            selectedId
            && item
            && Number(item.stock_serial_number_id || item.serial_number_id || 0) === selectedId
        );

        if (
            selectedId
            && ! selectedComesFromPendingItem
            && ! serials.some(function (serial) {
                return Number(serial.id || 0) === selectedId;
            })
        ) {
            setSelectedForItem(item, null);
            selected = null;
            selectedId = 0;
        }

        if (
            selectedComesFromPendingItem
            && ! serials.some(function (serial) {
                return Number(serial.id || 0) === selectedId;
            })
        ) {
            serials.unshift({
                id: selectedId,
                serial_number: selected.serial_number || item.serial_number || ('Serie #' + selectedId),
                label: selected.serial_number || item.serial_number || ('Serie #' + selectedId),
                product_id: itemProductId(item),
                product_variant_id: selected.product_variant_id || itemVariantId(item) || null,
                status: 'pending_selected'
            });
        }

        const qty = Number(item.qty || item.quantity || 0);
        const currentHash = JSON.stringify({
            requires: requires,
            selectedId: selectedId,
            qty: qty,
            serials: serials.map(function (serial) {
                return [
                    Number(serial.id || 0),
                    String(serial.label || serial.serial_number || ''),
                    Number(serial.product_variant_id || 0)
                ];
            })
        });

        if (box.dataset.v5543b2Hash === currentHash) {
            return;
        }

        let html = '';
        html += '<div class="v5543b1-serial-label">Número de serie requerido</div>';

        if (serials.length === 0) {
            html += '<div class="v5543b1-serial-warning">No hay series disponibles para este producto en este PDV.</div>';
            box.innerHTML = html;
            box.dataset.v5543b2Hash = currentHash;
            return;
        }

        const pendingLockedSerial = lockedSerialForPendingItem(item);
        const shouldRenderPendingReadonly = Boolean(
            (selectedComesFromPendingItem && selectedId)
            || (pendingLockedSerial && pendingLockedSerial.stock_serial_number_id)
        );

        if (shouldRenderPendingReadonly) {
            // BEXIA_V5544B_PENDING_SERIAL_READONLY
            const readonlyId = Number(
                selectedId
                || (pendingLockedSerial ? pendingLockedSerial.stock_serial_number_id : 0)
                || 0
            );

            const readonlySerialLabel = String(
                (pendingLockedSerial ? pendingLockedSerial.serial_number : '')
                || (selected ? selected.serial_number : '')
                || item.serial_number
                || (
                    serials.find(function (serial) {
                        return Number(serial.id || 0) === readonlyId;
                    }) || {}
                ).serial_number
                || ('Serie #' + readonlyId)
            );

            html += '<div class="v5544b-serial-readonly">';
            html += '<span>Serie: ' + escapeHtml(readonlySerialLabel) + '</span>';
            html += '<small>Ticket pendiente</small>';
            html += '</div>';

            box.innerHTML = html;
            box.dataset.v5543b2Hash = currentHash;
            return;
        }

        const pendingLoadedSerialIdForSelect = Number(item && (item.stock_serial_number_id || item.serial_number_id) || 0);

        if (pendingLoadedSerialIdForSelect > 0) {
            // BEXIA_V5544E_PENDING_DROPDOWN_SINGLE_OPTION
            serials = serials.filter(function (serial) {
                return Number(serial.id || 0) === pendingLoadedSerialIdForSelect;
            });

            if (serials.length === 0) {
                serials = [{
                    id: pendingLoadedSerialIdForSelect,
                    serial_number: (selected && selected.serial_number) || item.serial_number || ('Serie #' + pendingLoadedSerialIdForSelect),
                    label: (selected && selected.serial_number) || item.serial_number || ('Serie #' + pendingLoadedSerialIdForSelect),
                    product_id: itemProductId(item),
                    product_variant_id: itemVariantId(item) || null,
                    status: 'pending_selected'
                }];
            }

            setSelectedForItem(item, {
                stock_serial_number_id: pendingLoadedSerialIdForSelect,
                product_variant_id: itemVariantId(item) || null,
                serial_number: serials[0].serial_number || serials[0].label || ('Serie #' + pendingLoadedSerialIdForSelect)
            });
        }

        html += '<select class="v5543b1-serial-select" data-v5543b1-serial-select="1"' + (pendingLoadedSerialIdForSelect > 0 ? ' disabled' : '') + '>';

        if (pendingLoadedSerialIdForSelect <= 0) {
            html += '<option value="">Selecciona serie...</option>';
        }

        serials.forEach(function (serial) {
            const id = Number(serial.id || 0);
            const label = String(serial.label || serial.serial_number || ('Serie #' + id));
            const variantId = Number(serial.product_variant_id || 0);
            const selectedAttr = id === selectedId ? ' selected' : '';

            html += '<option value="' + String(id) + '" data-serial-number="' + escapeHtml(label) + '" data-product-id="' + String(Number(serial.product_id || 0) || itemProductId(item) || '') + '" data-product-variant-id="' + String(variantId || '') + '"' + selectedAttr + '>' + escapeHtml(label) + '</option>';
        });

        html += '</select>';

        if (qty !== 1) {
            html += '<div class="v5543b1-serial-warning">Este producto usa serie. La cantidad debe ser 1 por línea.</div>';
        }

        box.innerHTML = html;
        box.dataset.v5543b2Hash = currentHash;

        const select = box.querySelector('[data-v5543b1-serial-select="1"]');

        if (select) {
            select.addEventListener('change', function () {
                const option = select.options[select.selectedIndex];

                if (!select.value) {
                    setSelectedForItem(item, null);
                    return;
                }

                setSelectedForItem(item, {
                    stock_serial_number_id: Number(select.value),
                    product_id: Number(option.dataset.productId || 0) || itemProductId(item) || null,
                    product_variant_id: Number(option.dataset.productVariantId || 0) || itemVariantId(item) || null,
                    serial_number: option.dataset.serialNumber || option.textContent || ''
                });
            });
        }
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    async function hydrateVisibleCart() {
        if (!patchCartApi()) {
            return;
        }

        const cartBox = document.getElementById('v5339-cart-items');

        if (!cartBox) {
            return;
        }

        const items = baseItems();
        const lines = Array.from(cartBox.querySelectorAll('.v5339-cart-line'));

        items.forEach(seedSelectionFromItem);

        lines.forEach(function (line, index) {
            const item = items[index];

            if (!item || itemProductId(item) <= 0) {
                return;
            }

            fetchSerials(item).then(function (data) {
                renderSerialBox(line, item, data || {});
            });
        });
    }

    function validateSerialsForItems(items) {
        const missing = [];

        (items || []).forEach(function (item) {
            const key = itemKey(item);
            const fbKey = fallbackKey(item);
            const requires = Boolean(window.BEXIA_POS_SERIAL_REQUIREMENTS[key] || window.BEXIA_POS_SERIAL_REQUIREMENTS[fbKey]);
            const selected = selectedForItem(item);
            const qty = Number(item.qty || item.quantity || 0);

            if (!requires) {
                return;
            }

            if (qty !== 1) {
                missing.push('El producto "' + String(item.name || item.product_name || item.product_id || '') + '" usa número de serie y debe venderse con cantidad 1.');
                return;
            }

            if (!selected || !selected.stock_serial_number_id) {
                missing.push('Selecciona número de serie para "' + String(item.name || item.product_name || item.product_id || '') + '".');
            }
        });

        return missing;
    }


    /* BEXIA_V5543B3_SELECT_CHANGE_ALLOWED */
    document.addEventListener('change', function (event) {
        const select = event.target && event.target.closest
            ? event.target.closest('[data-v5543b1-serial-select="1"]')
            : null;

        if (!select) {
            return;
        }

        const box = select.closest('[data-v5543b1-serial-box="1"]');
        const line = select.closest('.v5339-cart-line');
        const cartBox = document.getElementById('v5339-cart-items');

        if (!box || !line || !cartBox) {
            return;
        }

        const lines = Array.from(cartBox.querySelectorAll('.v5339-cart-line'));
        const index = lines.indexOf(line);
        const items = baseItems();
        const item = items[index];

        if (!item) {
            return;
        }

        const option = select.options[select.selectedIndex];

        if (!select.value) {
            setSelectedForItem(item, null);
            return;
        }

        setSelectedForItem(item, {
            stock_serial_number_id: Number(select.value),
            product_variant_id: Number(option.dataset.productVariantId || 0) || itemVariantId(item) || null,
            serial_number: option.dataset.serialNumber || option.textContent || ''
        });
    }, true);

    function patchFetchValidation() {
        if (window.BEXIA_V5543B1_FETCH_PATCHED) {
            return;
        }

        window.BEXIA_V5543B1_FETCH_PATCHED = true;

        const originalFetch = window.fetch.bind(window);

        window.fetch = function (input, init) {
            try {
                const url = typeof input === 'string'
                    ? input
                    : (input && input.url ? input.url : '');

                const method = init && init.method
                    ? String(init.method).toUpperCase()
                    : 'GET';

                if (
                    method === 'POST'
                    && /\/pos\/sessions\/\d+\/orders/.test(url)
                    && init
                    && init.body
                ) {
                    const body = JSON.parse(init.body);

                    if (body && Array.isArray(body.items)) {
                        body.items = body.items.map(function (item) {
                            const selected = selectedForItem(item);

                            if (!selected || !selected.stock_serial_number_id) {
                                return item;
                            }

                            return {
                                ...item,
                                product_variant_id: selected.product_variant_id || item.product_variant_id || item.variant_id || null,
                                variant_id: selected.product_variant_id || item.variant_id || null,
                                stock_serial_number_id: selected.stock_serial_number_id,
                                serial_number_id: selected.stock_serial_number_id,
                                serial_number: selected.serial_number || ''
                            };
                        });

                        const errors = validateSerialsForItems(body.items);

                        if (errors.length) {
                            const message = errors[0];
                            notice(message, 'warning');

                            return Promise.reject(new Error(message));
                        }

                        init = {
                            ...init,
                            body: JSON.stringify(body)
                        };
                    }
                }
            } catch (error) {
                if (error && error.message) {
                    return Promise.reject(error);
                }
            }

            return originalFetch(input, init);
        };
    }

    function boot() {
        patchCartApi();
        patchFetchValidation();
        hydrateVisibleCart();

        const cartBox = document.getElementById('v5339-cart-items');

        if (cartBox && !cartBox.__v5543b1Observer) {
            cartBox.__v5543b1Observer = new MutationObserver(function () {
                window.clearTimeout(window.__v5543b1HydrateTimer);

                if (clearSerialStateWhenCartIsEmpty()) {
                    return;
                }

                window.__v5543b1HydrateTimer = window.setTimeout(function () {
                    clearSerialStateWhenCartIsEmpty();
                    hydrateVisibleCart();
                }, 80);
            });

            cartBox.__v5543b1Observer.observe(cartBox, {
                childList: true,
                subtree: true
            });
        }

        window.setInterval(function () {
            if (serialSelectorIsActive()) {
                return;
            }

            if (clearSerialStateWhenCartIsEmpty()) {
                return;
            }

            hydrateVisibleCart();
        }, 2500);
    }

    document.addEventListener('DOMContentLoaded', function () {
        window.setTimeout(boot, 250);
        window.setTimeout(boot, 900);
    });
})();
</script>


<script id="v5545n-price-list-manual-change-detector">
(function () {
    'use strict';

    if (window.BEXIA_V5545N_PRICE_LIST_MANUAL_CHANGE_DETECTOR_READY) {
        return;
    }

    window.BEXIA_V5545N_PRICE_LIST_MANUAL_CHANGE_DETECTOR_READY = true;
    window.BEXIA_POS_PRICE_LIST_CHANGED_AFTER_PENDING_LOAD = window.BEXIA_POS_PRICE_LIST_CHANGED_AFTER_PENDING_LOAD || false;

    function isPriceListControl(element) {
        if (!element) {
            return false;
        }

        const raw = [
            element.id || '',
            element.name || '',
            element.getAttribute('data-field') || '',
            element.getAttribute('data-name') || '',
            element.getAttribute('wire:model') || '',
            element.getAttribute('x-model') || '',
            element.className || ''
        ].join(' ').toLowerCase();

        return raw.includes('price_list')
            || raw.includes('pricelist')
            || raw.includes('price-list')
            || raw.includes('lista_precio')
            || raw.includes('lista-precio')
            || raw.includes('price list')
            || raw.includes('lista de precio');
    }

    function markManualPriceListChange(event) {
        const target = event && event.target ? event.target : null;

        if (!isPriceListControl(target)) {
            return;
        }

        window.BEXIA_POS_PRICE_LIST_CHANGED_AFTER_PENDING_LOAD = true;
    }

    // BEXIA_V5545N_PRICE_LIST_MANUAL_CHANGE_DETECTOR
    // Captura antes que otros listeners para que refreshProductData ya sepa que fue cambio manual.
    document.addEventListener('change', markManualPriceListChange, true);
    document.addEventListener('input', markManualPriceListChange, true);
})();
</script>


<script>
// BEXIA_V582P3_A35D1_PAYMENT_AMOUNT_EXACT_SELECT
// BEXIA_V582P3_A35D2_PAYMENT_AMOUNT_SELECT_ONCE
(function () {
    'use strict';

    if (window.__bexiaV582P3A35D2PaymentAmountSelectOnce) {
        return;
    }

    window.__bexiaV582P3A35D2PaymentAmountSelectOnce = true;

    const modalSelector = '#v5335-payment-modal';

    const amountSelector = [
        'input[data-v5481i-amount="1"]',
        'input[data-v5448-payment-amount="1"]',
        'input[data-v5418-amount]'
    ].join(', ');

    let selectionToken = 0;

    function modal() {
        return document.querySelector(modalSelector);
    }

    function modalIsOpen() {
        const box = modal();

        return !!(
            box
            && box.classList.contains('is-open')
            && box.getAttribute('aria-hidden') !== 'true'
        );
    }

    function visibleAmountInputs() {
        const box = modal();

        if (!box) {
            return [];
        }

        return Array.from(
            box.querySelectorAll(amountSelector)
        ).filter(function (input) {
            return !input.disabled
                && !input.readOnly
                && input.offsetParent !== null;
        });
    }

    function cancelPendingSelection() {
        selectionToken += 1;
    }

    function amountInputFromEvent(event) {
        const target = event && event.target
            ? event.target
            : null;

        if (!target || typeof target.closest !== 'function') {
            return null;
        }

        const input = target.closest(amountSelector);

        if (!input || !input.closest(modalSelector)) {
            return null;
        }

        return input;
    }

    function selectWholeValueOnce(input, token) {
        if (
            token !== selectionToken
            || !input
            || !document.documentElement.contains(input)
            || !modalIsOpen()
        ) {
            return false;
        }

        try {
            input.focus({ preventScroll: true });
        } catch (error) {
            input.focus();
        }

        window.requestAnimationFrame(function () {
            if (
                token !== selectionToken
                || !document.documentElement.contains(input)
                || !modalIsOpen()
            ) {
                return;
            }

            const length = String(input.value || '').length;

            try {
                input.select();
            } catch (error) {
                // setSelectionRange será el respaldo.
            }

            try {
                if (typeof input.setSelectionRange === 'function') {
                    input.setSelectionRange(0, length);
                }
            } catch (error) {
                // Algunos navegadores lo restringen en type=number.
            }
        });

        return true;
    }

    function waitAndSelect(mode, previousCount) {
        const token = ++selectionToken;
        let attempt = 0;
        const maximumAttempts = 30;

        function run() {
            if (token !== selectionToken) {
                return;
            }

            const inputs = modalIsOpen()
                ? visibleAmountInputs()
                : [];

            let target = null;

            if (mode === 'new-last') {
                if (inputs.length > previousCount) {
                    target = inputs[inputs.length - 1] || null;
                }
            } else if (inputs.length > 0) {
                target = inputs[0] || null;
            }

            if (target) {
                selectWholeValueOnce(target, token);
                return;
            }

            attempt += 1;

            if (attempt < maximumAttempts) {
                window.setTimeout(run, 40);
            }
        }

        window.setTimeout(run, 0);
    }

    function cancelFromUserEvent(event) {
        if (amountInputFromEvent(event)) {
            cancelPendingSelection();
        }
    }

    [
        'pointerdown',
        'mousedown',
        'touchstart',
        'keydown',
        'beforeinput',
        'input',
        'paste'
    ].forEach(function (eventName) {
        document.addEventListener(
            eventName,
            cancelFromUserEvent,
            true
        );
    });

    document.addEventListener('click', function (event) {
        const target = event.target;

        if (!target || typeof target.closest !== 'function') {
            return;
        }

        if (target.closest('#v5349-charge-ticket')) {
            waitAndSelect('first', 0);
            return;
        }

        const button = target.closest(
            modalSelector + ' button'
        );

        const text = button
            ? String(button.textContent || '')
                .replace(/\s+/g, ' ')
                .trim()
                .toLowerCase()
            : '';

        if (text.includes('agregar otro método de pago')) {
            const previousCount = visibleAmountInputs().length;

            waitAndSelect('new-last', previousCount);
            return;
        }

        if (
            text === 'cerrar'
            || text === 'cancelar'
            || text.includes('cancelar cobro')
        ) {
            cancelPendingSelection();
        }
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            cancelPendingSelection();
        }
    }, true);
})();
</script>


<script id="v582p4b-pos-search-metadata-refresh">
/* BEXIA_V582P4B_POS_SEARCH_METADATA_REFRESH */
(function () {
    'use strict';

    if (window.BEXIA_POS_SEARCH_METADATA_V582P4B_READY) {
        return;
    }

    window.BEXIA_POS_SEARCH_METADATA_V582P4B_READY = true;

    function clean(value) {
        return String(value ?? '').trim();
    }

    function identifier(item, key) {
        return clean(
            item
            && Object.prototype.hasOwnProperty.call(item, key)
                ? item[key]
                : ''
        );
    }

    window.BEXIA_POS_APPLY_SEARCH_METADATA = function (products) {
        let changed = 0;

        (Array.isArray(products) ? products : []).forEach(
            function (item) {
                const id = clean(item && item.id);

                if (!id) {
                    return;
                }

                const name = identifier(item, 'name');
                const reference = identifier(
                    item,
                    'internal_reference'
                );
                const sku = identifier(item, 'sku');
                const barcode = identifier(item, 'barcode');
                const code = identifier(item, 'code');

                const search = [
                    name,
                    reference,
                    sku,
                    barcode,
                    code
                ]
                    .filter(Boolean)
                    .join(' ');

                document.querySelectorAll(
                    '.product[data-product-id="' + id + '"]'
                ).forEach(function (card) {
                    if (name) {
                        card.dataset.productName = name;
                    }

                    card.dataset.productReference = reference;
                    card.dataset.productSku = sku;
                    card.dataset.productBarcode = barcode;
                    card.dataset.productCode = code || reference;
                    card.dataset.productSearch = search;
                    changed += 1;
                });
            }
        );

        return changed;
    };
})();
</script>
