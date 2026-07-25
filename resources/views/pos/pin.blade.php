
@php
    $staffKeyForPin = $staffKey
        ?? (! empty($cashier->employee_id)
            ? ('emp_' . $cashier->employee_id)
            : ('cashier_' . ($cashier->legacy_cashier_id ?? $staffKeyForPin ?? '')));

    $cashierNameForPin = $cashier->name ?? 'Empleado PDV';
    $cashierRoleForPin = $cashier->role ?? 'cashier';
@endphp

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Bexia PDV - Clave cajero</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { margin:0; font-family: Inter, ui-sans-serif, system-ui; background:#f4f7fb; color:#0f172a; }
        .wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:36px; }
        .panel { width:min(430px, 100%); background:#fff; border:1px solid #dbe3ef; border-radius:28px; box-shadow:0 24px 70px rgba(15,23,42,.10); padding:32px; }
        input { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:16px; padding:16px; font-size:24px; text-align:center; letter-spacing:8px; }
        button { width:100%; border:0; border-radius:16px; padding:16px; background:#2563eb; color:#fff; font-size:16px; font-weight:900; cursor:pointer; margin-top:14px; }
        a { display:block; text-align:center; margin-top:14px; color:#475569; text-decoration:none; font-weight:700; }
        .error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; border-radius:14px; padding:10px 12px; margin:14px 0; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="panel">
            <div style="font-size:26px; font-weight:950; color:#2563eb;">Bexia PDV</div>
            <h1 style="margin:18px 0 4px;">Clave de cajero</h1>
            <p style="margin:0 0 18px; color:#64748b;">
                {{ $cashierNameForPin }} · {{ $pos->name }}
            </p>

            @if(session('error'))
                <div class="error">{{ session('error') }}</div>
            @endif

            <form id="bexia-pos-pin-form" method="POST" action="{{ url('/pos/' . $pos->id . '/cashiers/' . $staffKeyForPin . '/login') }}">
                @csrf
                <input type="password" name="pin" inputmode="numeric" autofocus placeholder="••••">
                <button type="submit">Entrar al PDV</button>
            </form>

            <a href="{{ url('/pos/' . $pos->id . '/open') }}">Cambiar cajero</a>
        </div>
    </div>


@php
    $v5487bCashierRole = mb_strtolower(trim((string) ($cashier->role ?? $cashier->staff_role ?? $cashier->box_type ?? '')));

    $v5487bNeedsOpeningCash =
        str_contains($v5487bCashierRole, 'cashier')
        || str_contains($v5487bCashierRole, 'cajero')
        || str_contains($v5487bCashierRole, 'mixed')
        || str_contains($v5487bCashierRole, 'mixto')
        || (bool) ($cashier->is_pos_cashier ?? false)
        || (bool) ($cashier->can_collect_payment ?? false)
        || (bool) ($cashier->can_close_session ?? false);

    $v5487bDenominations = collect();

    if (\Illuminate\Support\Facades\Schema::hasTable('cash_denominations')) {
        $query = \Illuminate\Support\Facades\DB::table('cash_denominations');

        if (\Illuminate\Support\Facades\Schema::hasColumn('cash_denominations', 'company_id') && ! empty($pos->company_id)) {
            $query->where(function ($q) use ($pos) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', (int) $pos->company_id);
            });
        }

        foreach (['is_active', 'active'] as $column) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('cash_denominations', $column)) {
                $query->where($column, true);
                break;
            }
        }

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('cash_denominations');

        $v5487bDenominations = $query
            ->get()
            ->map(function ($row) use ($columns) {
                $value = 0.0;

                foreach (['value', 'amount', 'denomination', 'nominal_value'] as $field) {
                    if (in_array($field, $columns, true) && isset($row->{$field})) {
                        $value = round((float) $row->{$field}, 2);
                        break;
                    }
                }

                $type = '';

                foreach (['type', 'kind'] as $field) {
                    if (in_array($field, $columns, true) && isset($row->{$field})) {
                        $type = (string) $row->{$field};
                        break;
                    }
                }

                $name = '';

                foreach (['name', 'label', 'description'] as $field) {
                    if (in_array($field, $columns, true) && isset($row->{$field}) && trim((string) $row->{$field}) !== '') {
                        $name = trim((string) $row->{$field});
                        break;
                    }
                }

                if ($name === '') {
                    $name = '$' . number_format($value, 2);
                }

                return [
                    'name' => $name,
                    'type' => $type,
                    'value' => $value,
                ];
            })
            ->filter(fn ($row) => $row['value'] > 0)
            ->unique(fn ($row) => mb_strtolower((string) $row['type']) . '|' . number_format((float) $row['value'], 2, '.', ''))
            ->sortByDesc('value')
            ->values();
    }

    if ($v5487bDenominations->isEmpty()) {
        $v5487bDenominations = collect([
            ['name' => 'Billete de $1,000', 'type' => 'bill', 'value' => 1000],
            ['name' => 'Billete de $500', 'type' => 'bill', 'value' => 500],
            ['name' => 'Billete de $200', 'type' => 'bill', 'value' => 200],
            ['name' => 'Billete de $100', 'type' => 'bill', 'value' => 100],
            ['name' => 'Billete de $50', 'type' => 'bill', 'value' => 50],
            ['name' => 'Billete de $20', 'type' => 'bill', 'value' => 20],
            ['name' => 'Moneda de $20', 'type' => 'coin', 'value' => 20],
            ['name' => 'Moneda de $10', 'type' => 'coin', 'value' => 10],
            ['name' => 'Moneda de $5', 'type' => 'coin', 'value' => 5],
            ['name' => 'Moneda de $2', 'type' => 'coin', 'value' => 2],
            ['name' => 'Moneda de $1', 'type' => 'coin', 'value' => 1],
            ['name' => 'Moneda de $0.50', 'type' => 'coin', 'value' => 0.5],
        ]);
    }
@endphp

<style id="v5487-opening-cash-style">
    .v5487b-opening-backdrop { position: fixed; inset: 0; z-index: 20000; display: none; align-items: center; justify-content: center; padding: 24px; background: rgba(15,23,42,.55); }
    .v5487b-opening-backdrop.is-open { display: flex; }
    .v5487b-opening-card { width: min(640px,96vw); max-height: 92vh; overflow: auto; background:#fff; border:1px solid #e2e8f0; border-radius:22px; box-shadow:0 24px 80px rgba(15,23,42,.38); }
    .v5487b-opening-header { padding:18px 22px; border-bottom:1px solid #e2e8f0; }
    .v5487b-opening-header h2 { margin:0; color:#0f172a; font-size:22px; font-weight:950; }
    .v5487b-opening-body { padding:18px 22px; }
    .v5487b-denom-row { display:grid; grid-template-columns:1fr 90px 120px; gap:10px; align-items:center; margin-bottom:8px; }
    .v5487b-denom-row input { border:1px solid #cbd5e1; border-radius:12px; padding:9px; text-align:right; font-weight:850; }
    .v5487b-total-box { border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8; border-radius:16px; padding:12px; display:flex; justify-content:space-between; align-items:center; font-weight:950; margin-top:12px; }
    .v5487b-actions { display:flex; justify-content:flex-end; gap:10px; padding:16px 22px 20px; border-top:1px solid #e2e8f0; background:#f8fafc; }
    .v5487b-secondary, .v5487b-primary { border-radius:14px; padding:11px 16px; font-weight:950; cursor:pointer; }
    .v5487b-secondary { border:1px solid #cbd5e1; background:#fff; color:#0f172a; }
    .v5487b-primary { border:1px solid #2563eb; background:#2563eb; color:#fff; }
</style>

<style id="bexia-v582p3-a35f-opening-cash-compact">
    /* BEXIA_V582P3_A35F_COMPACT_OPENING_CASH_MODAL */

    #v5487b-opening-cash-modal {
        padding: 14px;
    }

    #v5487b-opening-cash-modal .v5487b-opening-card {
        width: min(760px, 96vw);
        max-height: 95vh;
    }

    #v5487b-opening-cash-modal .v5487b-opening-header {
        padding: 14px 20px 12px;
    }

    #v5487b-opening-cash-modal .v5487b-opening-header h2 {
        font-size: 19px;
        line-height: 1.15;
    }

    #v5487b-opening-cash-modal .v5487b-opening-header > div {
        margin-top: 4px !important;
        font-size: 11px !important;
        line-height: 1.25;
    }

    #v5487b-opening-cash-modal .v5487b-opening-body {
        padding: 12px 20px;
    }

    #v5487b-opening-cash-modal .v5487b-denom-row {
        grid-template-columns: minmax(220px, 1fr) 78px 105px;
        gap: 14px;
        min-height: 38px;
        margin-bottom: 5px;
        font-size: 13px;
    }

    #v5487b-opening-cash-modal .v5487b-denom-row strong {
        font-size: 13px;
        line-height: 1.2;
    }

    #v5487b-opening-cash-modal .v5487b-denom-row input {
        width: 100%;
        height: 38px;
        padding: 5px 8px;
        border-radius: 10px;
        font-size: 14px;
        line-height: 1;
    }

    #v5487b-opening-cash-modal [data-v5487b-opening-total] {
        font-size: 13px;
    }

    #v5487b-opening-cash-modal .v5487b-total-box {
        margin-top: 8px;
        padding: 9px 12px;
        border-radius: 12px;
        font-size: 13px;
    }

    #v5487b-opening-cash-modal .v5487b-actions {
        gap: 10px;
        padding: 12px 20px 14px;
    }

    #v5487b-opening-cash-modal .v5487b-secondary,
    #v5487b-opening-cash-modal .v5487b-primary {
        padding: 9px 14px;
        border-radius: 12px;
        font-size: 13px;
    }

    @media (max-width: 700px) {
        #v5487b-opening-cash-modal {
            padding: 10px;
        }

        #v5487b-opening-cash-modal .v5487b-opening-card {
            width: min(620px, 98vw);
            max-height: 97vh;
        }

        #v5487b-opening-cash-modal .v5487b-opening-body {
            padding: 11px 14px;
        }

        #v5487b-opening-cash-modal .v5487b-denom-row {
            grid-template-columns: minmax(130px, 1fr) 70px 88px;
            gap: 8px;
            font-size: 12px;
        }

        #v5487b-opening-cash-modal .v5487b-denom-row strong,
        #v5487b-opening-cash-modal [data-v5487b-opening-total] {
            font-size: 12px;
        }
    }
</style>

<div id="v5487b-opening-cash-modal" class="v5487b-opening-backdrop" aria-hidden="true">
    <div class="v5487b-opening-card" role="dialog" aria-modal="true">
        <div class="v5487b-opening-header">
            <h2>Fondo inicial de caja</h2>
            <div style="margin-top:6px;color:#64748b;font-size:13px;font-weight:800;">
                Captura con cuánto efectivo inicia el cajero y sus denominaciones.
            </div>
        </div>

        <div class="v5487b-opening-body">
            <div id="v5487b-opening-denominations">
                @foreach($v5487bDenominations as $index => $denom)
                    <div class="v5487b-denom-row" data-value="{{ (float) $denom['value'] }}" data-name="{{ e($denom['name']) }}" data-type="{{ e($denom['type'] ?? '') }}">
                        <div><strong>{{ $denom['name'] }}</strong></div>
                        <input type="number" min="0" step="1" value="0" data-v5487b-opening-qty="{{ $index }}">
                        <div style="text-align:right;font-weight:950;" data-v5487b-opening-total="{{ $index }}">$0.00</div>
                    </div>
                @endforeach
            </div>

            <div class="v5487b-total-box">
                <span>Total fondo inicial</span>
                <strong id="v5487b-opening-total">$0.00</strong>
            </div>
        </div>

        <div class="v5487b-actions">
            <button type="button" class="v5487b-secondary" id="v5487b-opening-cancel">Regresar</button>
            <button type="button" class="v5487b-primary" id="v5487b-opening-confirm">Abrir sesión con este fondo</button>
        </div>
    </div>
</div>

<script id="v5487-opening-cash-script">
document.addEventListener('DOMContentLoaded', function () {
    const needsOpeningCash = @json($v5487bNeedsOpeningCash);

    if (!needsOpeningCash || window.BEXIA_POS_OPENING_CASH_V5487B_READY) {
        return;
    }

    window.BEXIA_POS_OPENING_CASH_V5487B_READY = true;

    const modal = document.getElementById('v5487b-opening-cash-modal');
    const confirmButton = document.getElementById('v5487b-opening-confirm');
    const cancelButton = document.getElementById('v5487b-opening-cancel');

    let pendingForm = null;
    let confirmed = false;

    function money(value) {
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(Number(value || 0));
    }

    function recalcOpeningCash() {
        let total = 0;
        const cashCount = [];

        document.querySelectorAll('#v5487b-opening-denominations .v5487b-denom-row').forEach(function (row) {
            const value = Number(row.dataset.value || 0);
            const name = row.dataset.name || money(value);
            const type = row.dataset.type || '';
            const input = row.querySelector('[data-v5487b-opening-qty]');
            const qty = Math.max(0, Number(input ? input.value : 0));
            const lineTotal = value * qty;
            const totalBox = row.querySelector('[data-v5487b-opening-total]');

            total += lineTotal;

            if (totalBox) totalBox.textContent = money(lineTotal);

            cashCount.push({
                name: name,
                type: type,
                value: value,
                quantity: qty,
                total: Number(lineTotal.toFixed(2)),
            });
        });

        const totalBox = document.getElementById('v5487b-opening-total');

        if (totalBox) totalBox.textContent = money(total);

        return {
            total: Number(total.toFixed(2)),
            cashCount: cashCount,
        };
    }

    function setHidden(form, name, value) {
        let input = form.querySelector('input[name="' + name + '"]');

        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }

        input.value = value;
    }

    function openModal(form) {
        pendingForm = form;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        recalcOpeningCash();

        setTimeout(function () {
            modal.querySelector('[data-v5487b-opening-qty]')?.focus();
        }, 80);
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('input', function (event) {
        if (event.target && event.target.matches('[data-v5487b-opening-qty]')) {
            recalcOpeningCash();
        }
    }, true);

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!form || confirmed) return;

        const action = String(form.getAttribute('action') || '');

        if (!action.includes('/login')) return;

        event.preventDefault();
        event.stopPropagation();

        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }

        openModal(form);
    }, true);

    cancelButton?.addEventListener('click', function () {
        confirmed = false;
        closeModal();
    });

    modal?.addEventListener('click', function (event) {
        if (event.target === modal) {
            confirmed = false;
            closeModal();
        }
    });

    confirmButton?.addEventListener('click', function () {
        if (!pendingForm) return;

        const result = recalcOpeningCash();

        setHidden(pendingForm, 'opening_amount', result.total.toFixed(2));
        setHidden(pendingForm, 'opening_cash_count', JSON.stringify(result.cashCount || []));

        confirmed = true;
        closeModal();

        const v5828b5eFormToSubmit = pendingForm;

        if (
            typeof window.BEXIA_POS_SHOW_PIN_LOADING
            === 'function'
        ) {
            window.BEXIA_POS_SHOW_PIN_LOADING(
                'Abriendo sesion'
            );
        }

        /*
         * BEXIA_V5828B5E_DELAY_NATIVE_SUBMIT
         *
         * pendingForm.submit() no dispara el evento submit.
         * Esperamos un frame y unos milisegundos para que el
         * navegador alcance a dibujar el overlay.
         */
        window.requestAnimationFrame(function () {
            window.setTimeout(function () {
                v5828b5eFormToSubmit.submit();
            }, 90);
        });
    });
});
</script>

{{-- BEXIA_V5828B5C_PIN_LOADING --}}
<style id="bexia-v5828b5a-pin-loading-style">
    #bexia-v5828b5a-pin-loading {
        position: fixed;
        inset: 0;
        z-index: 30000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(15, 23, 42, .72);
        backdrop-filter: blur(3px);
    }

    #bexia-v5828b5a-pin-loading.is-visible {
        display: flex;
    }

    .bexia-v5828b5a-loading-card {
        width: min(390px, 92vw);
        padding: 30px 24px;
        border-radius: 24px;
        background: #ffffff;
        box-shadow: 0 28px 90px rgba(15, 23, 42, .35);
        text-align: center;
    }

    .bexia-v5828b5a-spinner {
        width: 46px;
        height: 46px;
        margin: 0 auto 18px;
        border: 5px solid #dbeafe;
        border-top-color: #2563eb;
        border-radius: 999px;
        animation: bexia-v5828b5a-spin .8s linear infinite;
    }

    @keyframes bexia-v5828b5a-spin {
        to { transform: rotate(360deg); }
    }
</style>

<div
    id="bexia-v5828b5a-pin-loading"
    aria-hidden="true"
    aria-live="polite"
>
    <div class="bexia-v5828b5a-loading-card">
        <div class="bexia-v5828b5a-spinner"></div>
        <div
            id="bexia-v5828b5a-pin-loading-title"
            style="font-size:20px;font-weight:950;color:#0f172a;"
        >
            Validando cajero
        </div>
        <div style="margin-top:8px;color:#64748b;font-weight:700;">
            Preparando productos, existencias y configuracion del PDV...
        </div>
    </div>
</div>

<script id="bexia-v5828b5a-pin-loading-script">
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('bexia-pos-pin-form');
    const overlay = document.getElementById(
        'bexia-v5828b5a-pin-loading'
    );
    const title = document.getElementById(
        'bexia-v5828b5a-pin-loading-title'
    );
    const submitButton = form
        ? form.querySelector('button[type="submit"]')
        : null;

    function showLoading(message) {
        if (!overlay) return;

        if (title && message) {
            title.textContent = message;
        }

        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Cargando PDV...';
        }
    }

    function hideLoading() {
        if (!overlay) return;

        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');

        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = 'Entrar al PDV';
        }
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            window.setTimeout(function () {
                // El flujo de fondo inicial cancela temporalmente el submit.
                // Solo mostramos el overlay cuando el formulario realmente
                // continuara hacia el servidor.
                if (!event.defaultPrevented) {
                    showLoading('Validando cajero');
                }
            }, 0);
        });
    }

    const openingConfirm = document.getElementById(
        'v5487b-opening-confirm'
    );

    if (openingConfirm) {
        openingConfirm.addEventListener('click', function () {
            window.setTimeout(function () {
                showLoading('Abriendo sesion');
            }, 40);
        });
    }

    window.addEventListener('pageshow', hideLoading);
});
</script>

{{-- BEXIA_V5828B5E_PIN_LOADING_BRIDGE --}}
{{-- BEXIA_V5828B5F_OVERLAY_IDS_ALIGNED --}}
<script id="bexia-v5828b5e-pin-loading-bridge">
(function () {
    'use strict';

    window.BEXIA_POS_SHOW_PIN_LOADING = function (message) {
        const overlay = document.getElementById(
            'bexia-v5828b5a-pin-loading'
        );

        const title = document.getElementById(
            'bexia-v5828b5a-pin-loading-title'
        );

        const form = document.getElementById(
            'bexia-pos-pin-form'
        );

        const button = form
            ? form.querySelector('button[type="submit"]')
            : null;

        if (title && message) {
            title.textContent = message;
        }

        if (overlay) {
            overlay.classList.add('is-visible');
            overlay.setAttribute('aria-hidden', 'false');
        }

        if (button) {
            button.disabled = true;
            button.textContent = 'Cargando PDV...';
        }
    };
})();
</script>

</body>
</html>
