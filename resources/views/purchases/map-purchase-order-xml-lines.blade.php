<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Mapear productos XML</title>
    <style>
        body {
            margin: 0;
            background: #f8fafc;
            color: #0f172a;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .page {
            padding: 28px;
        }

        .card {
            background: #fff;
            border: 1px solid #dbe3ef;
            border-radius: 16px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, .05);
            overflow: hidden;
        }

        .header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
        }

        h1 {
            margin: 0;
            font-size: 24px;
        }

        .muted {
            color: #64748b;
            font-size: 13px;
            margin-top: 6px;
        }

        .content {
            padding: 20px 24px;
        }

        .toolbar {
            margin-bottom: 14px;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search {
            width: 420px;
            max-width: 100%;
            min-height: 40px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 8px 10px;
            outline: none;
        }

        .search:focus,
        .product-search:focus,
        select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            background: #f8fafc;
            border-bottom: 1px solid #dbe3ef;
            padding: 10px;
            text-align: left;
            font-weight: 700;
        }

        td {
            border-bottom: 1px solid #edf2f7;
            padding: 10px;
            vertical-align: top;
        }

        select,
        .product-search {
            width: 100%;
            min-height: 40px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 8px;
            background: white;
            outline: none;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 18px;
        }

        .btn {
            min-height: 40px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            padding: 0 15px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .btn-primary {
            border-color: #2563eb;
            background: #2563eb;
            color: white;
        }

        .btn-gray {
            background: white;
            color: #334155;
        }

        .badge {
            display: inline-flex;
            border-radius: 999px;
            padding: 3px 8px;
            font-size: 12px;
            font-weight: 700;
        }

        .badge-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-green {
            background: #dcfce7;
            color: #166534;
        }

        .xml-desc {
            max-width: 430px;
        }

        .product-picker {
            position: relative;
            min-width: 360px;
        }

        .product-results {
            position: absolute;
            z-index: 50;
            left: 0;
            right: 0;
            top: 44px;
            max-height: 260px;
            overflow: auto;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 18px 30px rgba(15, 23, 42, .16);
            display: none;
        }

        .product-result {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
        }

        .product-result:hover {
            background: #eff6ff;
        }

        .product-result strong {
            display: block;
            font-size: 13px;
        }

        .product-result small {
            color: #64748b;
            font-size: 12px;
        }

        .selected-product {
            margin-top: 6px;
            font-size: 12px;
            color: #166534;
            font-weight: 700;
        }

        .clear-product {
            margin-top: 6px;
            border: 0;
            background: transparent;
            color: #dc2626;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            padding: 0;
        }

        .empty-results {
            padding: 12px;
            color: #64748b;
            font-size: 13px;
        }

        .hint {
            font-size: 12px;
            color: #64748b;
            margin-top: 6px;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <div class="header">
            <h1>Mapear productos XML</h1>
            <div class="muted">
                OC {{ $order->number ?? ('#' . $order->id) }}.
                Busca por código, SKU, nombre o descripción. Esta pantalla solo corrige el producto interno de cada concepto XML.
            </div>
        </div>

        <form method="POST" action="{{ route('purchases.orders.xml-mapping.update', ['purchaseOrder' => $order->id]) }}">
            @csrf

            <div class="content">
                <div class="toolbar">
                    <input id="search_xml_lines" class="search" type="text" placeholder="Filtrar filas por concepto XML o producto...">
                    <div class="muted">
                        Tip: escribe varias palabras, por ejemplo: <strong>cartulina negra</strong>.
                    </div>
                </div>

                <table>
                    <thead>
                    <tr>
                        <th>Concepto XML</th>
                        <th>Cantidad</th>
                        <th>Costo</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Producto interno correcto</th>
                        <th>Variante</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($lines as $line)
                        @php
                            $isPending = (bool) ($line->xml_requires_mapping ?? false) || empty($line->product_id);
                            $currentProductLabel = $line->product_label ?? '';
                        @endphp
                        <tr class="mapping-row">
                            <td class="xml-desc">
                                <strong>{{ $line->xml_description ?? $line->product_label ?? 'Sin descripción' }}</strong>
                                <div class="muted">
                                    No. identificación: {{ $line->xml_no_identificacion ?? '—' }}
                                </div>
                            </td>
                            <td>{{ number_format((float) ($line->ordered_quantity ?? 0), 4) }}</td>
                            <td>${{ number_format((float) ($line->unit_cost_without_tax ?? 0), 4) }}</td>
                            <td>${{ number_format((float) ($line->line_total_with_tax ?? 0), 2) }}</td>
                            <td>
                                @if($isPending)
                                    <span class="badge badge-red">Pendiente</span>
                                @else
                                    <span class="badge badge-green">Mapeado</span>
                                @endif
                            </td>
                            <td>
                                <div class="product-picker" data-line-id="{{ $line->id }}">
                                    <input
                                        type="hidden"
                                        name="mappings[{{ $line->id }}][product_id]"
                                        class="product-id"
                                        value="{{ (int) ($line->product_id ?? 0) ?: '' }}"
                                    >

                                    <input
                                        type="text"
                                        class="product-search"
                                        autocomplete="off"
                                        placeholder="Buscar producto..."
                                        value="{{ $currentProductLabel && ! str_starts_with($currentProductLabel, 'PENDIENTE') ? $currentProductLabel : '' }}"
                                    >

                                    <div class="product-results"></div>

                                    <div class="selected-product">
                                        @if(! empty($line->product_id))
                                            Producto actual: {{ $currentProductLabel }}
                                        @else
                                            Sin producto asignado
                                        @endif
                                    </div>

                                    <button type="button" class="clear-product">
                                        Limpiar selección
                                    </button>

                                    <div class="hint">
                                        Escribe al menos 2 caracteres para buscar.
                                    </div>
                                </div>
                            </td>
                            <td>
                                <select
                                    name="mappings[{{ $line->id }}][variant_id]"
                                    class="variant-select"
                                    data-line-id="{{ $line->id }}"
                                    data-current-value="{{ (int) ($line->product_variant_id ?? $line->variant_id ?? 0) ?: '' }}"
                                >
                                    <option value="">Sin variante</option>
                                </select>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <div class="actions">
                    <a class="btn btn-gray" href="{{ url('/admin/' . $tenantId . '/purchase-orders/' . $order->id . '/edit') }}">
                        Cancelar
                    </a>

                    <button type="submit" class="btn btn-primary">
                        Guardar mapeo
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    const PRODUCTS = @json($products);
    const VARIANTS = @json($variants);

    function normalizeText(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function productMatches(product, term) {
        const haystack = normalizeText(product.label);
        const words = normalizeText(term).split(/\s+/).filter(Boolean);

        return words.every(word => haystack.includes(word));
    }

    function renderProductResults(picker, term) {
        const results = picker.querySelector('.product-results');
        const input = picker.querySelector('.product-search');

        results.innerHTML = '';

        if (normalizeText(term).length < 2) {
            results.style.display = 'none';
            return;
        }

        const matches = PRODUCTS
            .filter(product => productMatches(product, term))
            .slice(0, 30);

        if (!matches.length) {
            results.innerHTML = '<div class="empty-results">Sin coincidencias. Intenta con otra palabra o código.</div>';
            results.style.display = 'block';
            return;
        }

        matches.forEach(product => {
            const item = document.createElement('div');
            item.className = 'product-result';
            item.innerHTML = '<strong>' + product.label + '</strong><small>ID ' + product.id + '</small>';

            item.addEventListener('click', () => {
                selectProduct(picker, product);
            });

            results.appendChild(item);
        });

        results.style.display = 'block';
    }

    function selectProduct(picker, product) {
        const lineId = picker.dataset.lineId;
        const hidden = picker.querySelector('.product-id');
        const input = picker.querySelector('.product-search');
        const results = picker.querySelector('.product-results');
        const selected = picker.querySelector('.selected-product');

        hidden.value = product.id;
        input.value = product.label;
        selected.textContent = 'Producto seleccionado: ' + product.label;
        results.style.display = 'none';


        const variantSelect = document.querySelector('.variant-select[data-line-id="' + lineId + '"]');
        if (variantSelect) {
            variantSelect.dataset.currentValue = '';
        }

        refreshVariants(lineId, product.id);
    }

    function clearProduct(picker) {
        const lineId = picker.dataset.lineId;
        const hidden = picker.querySelector('.product-id');
        const input = picker.querySelector('.product-search');
        const results = picker.querySelector('.product-results');
        const selected = picker.querySelector('.selected-product');

        hidden.value = '';
        input.value = '';
        selected.textContent = 'Sin producto asignado';
        results.style.display = 'none';

        refreshVariants(lineId, '');
    }
function refreshVariants(lineId, productId) {
        const select = document.querySelector('.variant-select[data-line-id="' + lineId + '"]');

        if (!select) {
            return;
        }

        const currentValue = select.dataset.currentValue || '';

        select.innerHTML = '';

        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = 'Sin variante';
        select.appendChild(empty);

        const filtered = VARIANTS.filter(function (variant) {
            return String(variant.product_id) === String(productId);
        });

        filtered.forEach(function (variant) {
            const option = document.createElement('option');
            option.value = variant.id;
            option.textContent = variant.label;

            select.appendChild(option);
        });

        if (filtered.length > 0) {
            select.disabled = false;
        } else {
            select.disabled = false;
        }

        if (currentValue && filtered.some(function (variant) {
            return String(variant.id) === String(currentValue);
        })) {
            select.value = currentValue;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.product-picker').forEach(function (picker) {
            const input = picker.querySelector('.product-search');
            const clear = picker.querySelector('.clear-product');
            const hidden = picker.querySelector('.product-id');

            input.addEventListener('input', function () {
                renderProductResults(picker, input.value);
            });

            input.addEventListener('focus', function () {
                renderProductResults(picker, input.value);
            });

            clear.addEventListener('click', function () {
                clearProduct(picker);
            });

            if (hidden.value) {
                refreshVariants(picker.dataset.lineId, hidden.value);
            }
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('.product-picker')) {
                document.querySelectorAll('.product-results').forEach(function (results) {
                    results.style.display = 'none';
                });
            }
        });

        const rowSearch = document.getElementById('search_xml_lines');

        if (rowSearch) {
            rowSearch.addEventListener('input', function () {
                const term = normalizeText(rowSearch.value);

                document.querySelectorAll('.mapping-row').forEach(function (row) {
                    row.style.display = normalizeText(row.textContent).includes(term) ? '' : 'none';
                });
            });
        }
    });
</script>
</body>
</html>
