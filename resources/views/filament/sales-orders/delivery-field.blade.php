@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $order = $saleOrderId ? DB::table('sales_orders')->where('id', $saleOrderId)->first() : null;
    $tablesReady = Schema::hasTable('sale_deliveries') && Schema::hasTable('sale_delivery_lines');

    $lines = collect();
    $deliveries = collect();
    $deliveryLines = collect();

    if ($order && $tablesReady) {
        $reservedTotals = DB::table('sale_delivery_lines as l')
            ->join('sale_deliveries as d', 'd.id', '=', 'l.sale_delivery_id')
            ->where('d.sales_order_id', $order->id)
            ->where('d.status', '!=', 'cancelled')
            ->groupBy('l.sales_order_line_id')
            ->selectRaw('l.sales_order_line_id, SUM(l.quantity) as total_quantity')
            ->pluck('total_quantity', 'sales_order_line_id');

        $lines = DB::table('sales_order_lines')
            ->where('sales_order_id', $order->id)
            ->orderBy('id')
            ->get()
            ->map(function ($line) use ($reservedTotals) {
                $requested = (float) ($line->quantity ?? 0);
                $delivered = (float) ($line->delivered_quantity ?? 0);
                $reserved = (float) ($reservedTotals[$line->id] ?? 0);
                $covered = max($delivered, $reserved);

                $line->_requested = $requested;
                $line->_reserved = $reserved;
                $line->_covered = $covered;
                $line->_pending = max(0, $requested - $covered);

                return $line;
            });

        $deliveries = DB::table('sale_deliveries')
            ->where('sales_order_id', $order->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        if ($deliveries->isNotEmpty()) {
            $deliveryLines = DB::table('sale_delivery_lines')
                ->whereIn('sale_delivery_id', $deliveries->pluck('id')->all())
                ->orderBy('id')
                ->get()
                ->groupBy('sale_delivery_id');
        }
    }

    $canCreateDelivery = $order && in_array((string) ($order->status ?? ''), ['confirmed', 'partially_delivered'], true);
@endphp

<div class="space-y-4" id="bexia-delivery-screen">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            {{ session('warning') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    @if(! $order)
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">
            Guarda la orden para poder crear entregas.
        </div>
    @elseif(! $tablesReady)
        <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800">
            Faltan las tablas de entregas. Ejecuta las migraciones de V5.29.3.
        </div>
    @else
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="mb-3">
                <div class="text-sm font-semibold text-gray-900">Crear entrega y reservar inventario</div>
                <div class="text-xs text-gray-500">
                    Captura la cantidad a entregar. El sistema calculará si la entrega es completa o parcial.
                    Al crear la entrega se reserva inventario. Al validar, se genera la salida y se descuenta existencia.
                </div>
            </div>

            @if(! $canCreateDelivery)
                <div class="mb-3 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800">
                    Solo se pueden crear entregas cuando la orden está confirmada y tiene cantidades pendientes.
                </div>
            @endif

            <div
                id="bexia-partial-warning"
                class="mb-3 hidden rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
            >
                Se creará una entrega parcial. Quedará una entrega pendiente relacionada a esta venta.
            </div>

            <form
                method="POST"
                action="{{ route('sales-orders.deliveries.partial', ['saleOrder' => $order->id]) }}"
                id="bexia-delivery-form"
            >
                @csrf

                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-2">Producto</th>
                                <th class="px-3 py-2 text-right">Cantidad solicitada</th>
                                <th class="px-3 py-2 text-right">Cantidad reservada</th>
                                <th class="px-3 py-2 text-right">Pendiente</th>
                                <th class="px-3 py-2 text-right">Cantidad a entregar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lines as $line)
                                <tr class="border-t border-gray-100">
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-gray-900">{{ $line->product_label ?: 'Producto' }}</div>
                                        @if($line->variant_label)
                                            <div class="text-xs text-gray-500">{{ $line->variant_label }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">{{ number_format($line->_requested, 2) }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($line->_reserved, 2) }}</td>
                                    <td class="px-3 py-2 text-right font-semibold">{{ number_format($line->_pending, 2) }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <input
                                            type="number"
                                            step="0.000001"
                                            min="0"
                                            max="{{ $line->_pending }}"
                                            name="line_quantities[{{ $line->id }}]"
                                            data-pending="{{ $line->_pending }}"
                                            class="bexia-delivery-qty w-32 rounded-lg border-gray-300 text-right text-sm"
                                            @disabled(! $canCreateDelivery || $line->_pending <= 0)
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <textarea
                        name="notes"
                        rows="2"
                        class="w-full rounded-lg border-gray-300 text-sm"
                        placeholder="Notas de entrega, opcional"
                        @disabled(! $canCreateDelivery)
                    ></textarea>
                </div>

                <div class="mt-3 flex justify-end">
                    <button
                        type="submit"
                        @disabled(! $canCreateDelivery)
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm disabled:opacity-50"
                    >
                        Crear entrega
                    </button>
                </div>
            </form>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="mb-3 text-sm font-semibold text-gray-900">Entregas creadas</div>

            @forelse($deliveries as $delivery)
                <div class="mb-3 rounded-lg border border-gray-200 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="font-medium text-gray-900">{{ $delivery->number ?: ('Entrega #' . $delivery->id) }}</div>
                            @php
                                $deliveryStatusLabel = match ((string) $delivery->status) {
                                    'draft' => 'Borrador',
                                    'done' => 'Validada',
                                    'cancelled' => 'Cancelada',
                                    default => $delivery->status ?: 'Sin estado',
                                };

                                $deliveryTypeLabel = match ((string) $delivery->delivery_type) {
                                    'complete' => 'Completa',
                                    'partial' => 'Parcial',
                                    default => $delivery->delivery_type ?: 'Sin tipo',
                                };
                            @endphp

                            <div class="text-xs text-gray-500">
                                Estado: {{ $deliveryStatusLabel }} · Tipo: {{ $deliveryTypeLabel }} · {{ $delivery->created_at }}
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <a
                                href="{{ route('sales.deliveries.print', ['saleDelivery' => $delivery->id]) }}"
                                target="_blank"
                                class="rounded-lg border border-blue-200 px-3 py-1 text-xs font-semibold text-blue-700"
                            >
                                Imprimir
                            </a>

                            @if($delivery->status === 'draft')
                                <form
                                    method="POST"
                                    action="{{ route('sales-deliveries.validate', ['saleDelivery' => $delivery->id]) }}"
                                    data-bexia-validate-delivery-form="1"
                                >
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-green-200 px-3 py-1 text-xs font-semibold text-green-700">
                                        Validar entrega
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('sales-deliveries.cancel', ['saleDelivery' => $delivery->id]) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-700">
                                        Cancelar
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="mt-2 text-xs text-gray-600">
                        @foreach(($deliveryLines[$delivery->id] ?? collect()) as $dLine)
                            <div>
                                {{ $dLine->product_label }}@if($dLine->variant_label) — {{ $dLine->variant_label }}@endif:
                                <strong>{{ number_format((float) $dLine->quantity, 2) }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="text-sm text-gray-500">Todavía no hay entregas creadas.</div>
            @endforelse
        </div>
    @endif
</div>


<div
    id="bexia-partial-delivery-modal"
    style="display: none; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center; background: rgba(17, 24, 39, 0.55); padding: 24px;"
>
    <div
        style="width: 100%; max-width: 520px; border-radius: 18px; background: #ffffff; padding: 26px; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.28); border: 1px solid rgba(226, 232, 240, 0.9);"
        role="dialog"
        aria-modal="true"
        aria-labelledby="bexia-partial-delivery-title"
    >
        <div
            id="bexia-partial-delivery-title"
            style="font-size: 18px; line-height: 1.4; font-weight: 800; color: #111827; margin-bottom: 12px;"
        >
            Entrega parcial
        </div>

        <div style="font-size: 14px; line-height: 1.65; color: #475569;">
            Se creará una entrega parcial. Quedará una entrega pendiente relacionada a esta venta.
            ¿Deseas continuar?
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 26px;">
            <button
                type="button"
                id="bexia-partial-delivery-cancel"
                style="border-radius: 10px; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; padding: 10px 18px; font-size: 14px; font-weight: 700; cursor: pointer; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);"
            >
                Cancelar
            </button>

            <button
                type="button"
                id="bexia-partial-delivery-accept"
                style="border-radius: 10px; border: 1px solid #2563eb; background: #2563eb; color: #ffffff; padding: 10px 18px; font-size: 14px; font-weight: 800; cursor: pointer; box-shadow: 0 8px 18px rgba(37, 99, 235, 0.25);"
            >
                Aceptar
            </button>
        </div>
    </div>
</div>

<div
    id="bexia-validate-delivery-modal"
    style="display: none; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center; background: rgba(17, 24, 39, 0.55); padding: 24px;"
>
    <div
        style="width: 100%; max-width: 520px; border-radius: 18px; background: #ffffff; padding: 26px; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.28); border: 1px solid rgba(226, 232, 240, 0.9);"
        role="dialog"
        aria-modal="true"
        aria-labelledby="bexia-validate-delivery-title"
    >
        <div
            id="bexia-validate-delivery-title"
            style="font-size: 18px; line-height: 1.4; font-weight: 800; color: #111827; margin-bottom: 12px;"
        >
            Validar entrega
        </div>

        <div style="font-size: 14px; line-height: 1.65; color: #475569;">
            Se validará la entrega, se generará el movimiento de salida y se descontará inventario.
            ¿Deseas continuar?
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 26px;">
            <button
                type="button"
                id="bexia-validate-delivery-cancel"
                style="border-radius: 10px; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; padding: 10px 18px; font-size: 14px; font-weight: 700; cursor: pointer; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);"
            >
                Cancelar
            </button>

            <button
                type="button"
                id="bexia-validate-delivery-accept"
                style="border-radius: 10px; border: 1px solid #2563eb; background: #2563eb; color: #ffffff; padding: 10px 18px; font-size: 14px; font-weight: 800; cursor: pointer; box-shadow: 0 8px 18px rgba(37, 99, 235, 0.25);"
            >
                Aceptar
            </button>
        </div>
    </div>
</div>

<script>
(function () {

    var bexiaPendingValidateDeliveryForm = null;

    function bexiaValidateDeliveryModal() {
        return document.getElementById('bexia-validate-delivery-modal');
    }

    function bexiaShowValidateDeliveryModal(form) {
        bexiaPendingValidateDeliveryForm = form;

        var modal = bexiaValidateDeliveryModal();

        if (! modal) {
            return false;
        }

        modal.style.display = 'flex';
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        return true;
    }

    function bexiaHideValidateDeliveryModal() {
        var modal = bexiaValidateDeliveryModal();

        if (! modal) {
            return;
        }

        modal.style.display = 'none';
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    var pendingSubmit = false;

    function deliveryInputs() {
        return Array.prototype.slice.call(document.querySelectorAll('.bexia-delivery-qty'));
    }

    function numberValue(value) {
        var parsed = parseFloat(String(value || '').replace(',', '.'));
        return isNaN(parsed) ? 0 : parsed;
    }

    function pendingValue(input) {
        return numberValue(input.getAttribute('data-pending'));
    }

    function currentState() {
        var inputs = deliveryInputs();
        var any = false;
        var complete = true;

        inputs.forEach(function (input) {
            var pending = pendingValue(input);
            var qty = numberValue(input.value);

            if (qty > 0) {
                any = true;
            }

            if (pending > 0 && Math.abs(qty - pending) > 0.000001) {
                complete = false;
            }
        });

        return {
            any: any,
            complete: complete,
            partial: any && ! complete
        };
    }

    function refreshWarning() {
        var warning = document.getElementById('bexia-partial-warning');

        if (! warning) {
            return;
        }

        var state = currentState();

        if (state.partial) {
            warning.classList.remove('hidden');
        } else {
            warning.classList.add('hidden');
        }
    }

    function modalElement() {
        return document.getElementById('bexia-partial-delivery-modal');
    }

    function showPartialModal() {
        var modal = modalElement();

        if (! modal) {
            return false;
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.style.display = 'flex';

        return true;
    }

    function hidePartialModal() {
        var modal = modalElement();

        if (! modal) {
            return;
        }

        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.style.display = 'none';
    }

    window.bexiaFillDeliveryQuantities = function () {
        var inputs = deliveryInputs();

        inputs.forEach(function (input) {
            var pending = pendingValue(input);

            if (! input.disabled && pending > 0) {
                input.value = String(pending.toFixed(6)).replace(/\.?0+$/, '');
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        refreshWarning();
    };

    window.addEventListener('bexia-fill-delivery-quantities', function () {
        window.bexiaFillDeliveryQuantities();
    });

    document.addEventListener('click', function (event) {
        var target = event.target;
        var button = target ? target.closest('[data-bexia-fill-delivery-quantities="1"], a, button') : null;

        if (! button) {
            return;
        }

        var text = (button.textContent || '').trim().toLowerCase();

        if (
            button.getAttribute('data-bexia-fill-delivery-quantities') === '1'
            || text.indexOf('establecer cantidades') !== -1
        ) {
            event.preventDefault();
            event.stopPropagation();
            window.bexiaFillDeliveryQuantities();
            return false;
        }
    }, true);

    document.addEventListener('input', function (event) {
        if (event.target && event.target.classList && event.target.classList.contains('bexia-delivery-qty')) {
            refreshWarning();
        }
    });

    document.addEventListener('click', function (event) {
        if (event.target && event.target.id === 'bexia-partial-delivery-cancel') {
            event.preventDefault();
            pendingSubmit = false;
            hidePartialModal();
        }

        if (event.target && event.target.id === 'bexia-partial-delivery-accept') {
            event.preventDefault();

            var form = document.getElementById('bexia-delivery-form');

            if (! form) {
                return;
            }

            pendingSubmit = true;
            hidePartialModal();
            form.submit();
        }

        if (event.target && event.target.id === 'bexia-partial-delivery-modal') {
            pendingSubmit = false;
            hidePartialModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            pendingSubmit = false;
            hidePartialModal();
        }
    });

    var form = document.getElementById('bexia-delivery-form');

    if (form) {
        form.addEventListener('submit', function (event) {
            var state = currentState();

            if (! state.any) {
                event.preventDefault();
                alert('Captura al menos una cantidad para entregar.');
                return;
            }

            if (state.partial && ! pendingSubmit) {
                event.preventDefault();
                showPartialModal();
                return;
            }

            pendingSubmit = false;
        });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (! form || form.getAttribute('data-bexia-validate-delivery-form') !== '1') {
            return;
        }

        if (form.getAttribute('data-bexia-validation-confirmed') === '1') {
            form.removeAttribute('data-bexia-validation-confirmed');
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        bexiaShowValidateDeliveryModal(form);
    }, true);

    document.addEventListener('click', function (event) {
        if (event.target && event.target.id === 'bexia-validate-delivery-cancel') {
            event.preventDefault();
            bexiaPendingValidateDeliveryForm = null;
            bexiaHideValidateDeliveryModal();
            return;
        }

        if (event.target && event.target.id === 'bexia-validate-delivery-accept') {
            event.preventDefault();

            if (! bexiaPendingValidateDeliveryForm) {
                bexiaHideValidateDeliveryModal();
                return;
            }

            var form = bexiaPendingValidateDeliveryForm;
            bexiaPendingValidateDeliveryForm = null;

            form.setAttribute('data-bexia-validation-confirmed', '1');
            bexiaHideValidateDeliveryModal();
            form.submit();

            return;
        }

        if (event.target && event.target.id === 'bexia-validate-delivery-modal') {
            event.preventDefault();
            bexiaPendingValidateDeliveryForm = null;
            bexiaHideValidateDeliveryModal();
        }
    });


})();
</script>
