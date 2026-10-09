@php
    $papelon = $summary['papelon_close'] ?? [];
    $sections = $papelon['sections'] ?? [];
    $methodTotals = $papelon['method_totals'] ?? [];
    $productsBySection = $papelon['products_by_section'] ?? [];
    $refunds = $papelon['refunds'] ?? [];
    $totals = $papelon['totals'] ?? [];

    $money = fn ($value) => '$' . number_format((float) $value, 2);
    $sessionNumber = (string) ($session->number ?? ('#' . ($session->id ?? '')));
    $openedAt = $session->opened_at ?? $session->created_at ?? null;
    $closedAt = $session->closed_at ?? null;

    /*
     * BEXIA_V5836G5H4C_SEPARATE_SALES_ADVANCES
     */
    $isClosed =
        (string) ($session->status ?? '') === 'closed'
        && ! empty($closedAt);

    $logoSrc = trim((string) ($companyLogoUrl ?? ''));
    $hasLogo = $logoSrc !== '';
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Corte de Caja Papelón</title>
<style>
*{box-sizing:border-box}
body{font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;color:#111827;margin:24px}
h1,h2,h3{margin:0}
h1{font-size:24px;text-transform:uppercase;letter-spacing:.5px}
h2{font-size:15px;margin-top:18px;padding:7px 9px;background:#111827;color:white}
h3{font-size:13px;margin:12px 0 5px}
.muted{color:#6b7280}
.header-table{width:100%;border-collapse:collapse;border-bottom:2px solid #111827;margin-bottom:16px}
.header-table td{border:none;padding:0 0 12px 0;vertical-align:middle}
.logo{max-width:180px;max-height:80px}
.header-right{text-align:right}
table{width:100%;border-collapse:collapse;margin-top:7px}
th{background:#f3f4f6;border:1px solid #d1d5db;padding:6px;text-align:left}
td{border:1px solid #e5e7eb;padding:5px 6px;vertical-align:top}
.right{text-align:right}.center{text-align:center}.bold{font-weight:800}
.total-row td{font-weight:900;background:#f9fafb}
.net-row td{font-weight:900;background:#111827;color:white;font-size:13px}
.note{margin-top:12px;font-size:10px;color:#6b7280}
</style>
</head>
<body>
<table class="header-table">
    <tr>
        <td style="width:42%;">
            @if($hasLogo)
                <img class="logo" src="{{ $logoSrc }}" alt="Logo">
            @else
                <h1>PAPELÓN</h1>
            @endif
        </td>
        <td class="header-right">
            <h1>Corte de Caja</h1>
            <div class="muted">Reporte especial por sección de cierre</div>
            <div><strong>Sesión:</strong> {{ $sessionNumber }}</div>
            <div><strong>Apertura:</strong> {{ $openedAt ?: 'N/D' }}</div>
            @if($isClosed)
                <div><strong>Cierre:</strong> {{ $closedAt }}</div>
            @else
                <div><strong>Generado:</strong> {{ now()->format('Y-m-d H:i:s') }}</div>
            @endif
        </td>
    </tr>
</table>

<h2>Resumen de cobros por sección y método de pago</h2>

@foreach($sections as $section)
    <h3>{{ $section['name'] ?? 'SECCIÓN' }}</h3>
    <table>
        <thead>
            <tr>
                <th>Método</th>
                <th class="right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($section['methods'] ?? []) as $method)
                <tr>
                    <td>{{ $method['method'] ?? 'Método' }}</td>
                    <td class="right">{{ $money($method['total'] ?? 0) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>
                    @if(($section['name'] ?? '') === 'IMPRESIÓN Y COPIAS')
                        TOTAL COBRADO IMPRESIONES
                    @else
                        Total cobrado {{ $section['name'] ?? '' }}
                    @endif
                </td>
                <td class="right">{{ $money($section['total'] ?? 0) }}</td>
            </tr>
        </tbody>
    </table>
@endforeach

<h2>Devoluciones</h2>
<table>
    <tbody>
        <tr>
            <td>Devoluciones totales</td>
            <td class="right">{{ $money($refunds['total_refunds_total'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Devoluciones parciales</td>
            <td class="right">{{ $money($refunds['partial_refunds_total'] ?? 0) }}</td>
        </tr>
        {{-- BEXIA_V5836G5H7C4B_ADVANCE_REFUND_VISUAL --}}
        @if((float)($refunds['advance_refunds_total'] ?? 0) > 0)
            <tr>
                <td>Anticipos devueltos</td>
                <td class="right">{{ $money($refunds['advance_refunds_total'] ?? 0) }}</td>
            </tr>
        @endif
        @if((float)($refunds['other_refunds_total'] ?? 0) > 0)
            <tr>
                <td>Otras devoluciones</td>
                <td class="right">{{ $money($refunds['other_refunds_total'] ?? 0) }}</td>
            </tr>
        @endif
        <tr class="total-row">
            <td>Total devuelto</td>
            <td class="right">-{{ $money($refunds['refunded_total'] ?? 0) }}</td>
        </tr>
    </tbody>
</table>

<h2>Resumen final</h2>

{{-- BEXIA_V5836G5H7C3_VISUAL_ADVANCE_REFUNDS --}}
<table>
    <tbody>
        <tr>
            <td>Ventas liquidadas</td>
            <td class="right">{{ $money($totals['gross_total'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Anticipos cobrados</td>
            <td class="right">{{ $money($totals['advance_payments_total'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Anticipos devueltos</td>
            <td class="right">-{{ $money($totals['advance_refunds_total'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Anticipos pendientes</td>
            <td class="right">{{ $money($totals['outstanding_advance_total'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Cobrado en sesión</td>
            <td class="right">{{ $money($totals['collected_total'] ?? $totals['payments_total'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Flujo neto cobrado</td>
            <td class="right">{{ $money($totals['net_cashflow_total'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Devoluciones de venta</td>
            <td class="right">-{{ $money($totals['sale_refunds_total'] ?? 0) }}</td>
        </tr>
        <tr class="net-row">
            <td>Venta neta liquidada</td>
            <td class="right">{{ $money($totals['net_settled_sales_total'] ?? $totals['net_total'] ?? 0) }}</td>
        </tr>
    </tbody>
</table>

<h2>Totales cobrados por método</h2>
<table>
    <tbody>
        @foreach($methodTotals as $method)
            <tr>
                <td>{{ $method['method'] ?? 'Método' }}</td>
                <td class="right">{{ $money($method['total'] ?? 0) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<h2>Detalle de productos vendidos por sección</h2>

@if(empty($productsBySection))
    <div class="note">
        Sin productos vendidos/liquidados en esta sesión.
    </div>
@endif

@foreach($productsBySection as $sectionName => $products)
    @continue($sectionName === 'IMPRESIÓN Y COPIAS')
    <h3>{{ $sectionName }}</h3>
    <table>
        <thead>
            <tr>
                {{-- BEXIA_V5836_PDV1C_REFERENCE_FIRST --}}
                <th>Referencia</th>

                <th>Producto</th>

                <th>Ruta categoría</th>
                <th class="right">Cantidad</th>
                <th class="right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $product)
                <tr>
                    <td>
                        {{
                            trim(
                                (string) (
                                    $product['reference']
                                    ?? ''
                                )
                            ) !== ''
                                ? $product['reference']
                                : '—'
                        }}
                    </td>

                    <td>
                        {{ $product['name'] ?? 'Producto' }}
                    </td>

                    <td>
                        {{ $product['path'] ?? '' }}
                    </td>

                    <td class="right">
                        {{
                            number_format(
                                (float) (
                                    $product['qty']
                                    ?? 0
                                ),
                                2
                            )
                        }}
                    </td>

                    <td class="right">
                        {{
                            $money(
                                $product['total']
                                ?? 0
                            )
                        }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endforeach

{{-- BEXIA_V5836_PAPC16_COUNT_AND_RECONCILIATION --}}
@php
    $cashRows = $summary['session']['closing_cash_count'] ?? [];
    if (is_string($cashRows)) {
        $cashRows = json_decode($cashRows, true);
    }
    $hasCashCount = is_array($cashRows) && count($cashRows) > 0;

    // The modal sorts denominations by value and then bill/coin.
    // Stored counts are positional, so preserve exactly that mapping.
    $denomCatalog = $summary['denominations'] ?? [];
    usort($denomCatalog, function ($a, $b) {
        $cmp = ((float) ($b['value'] ?? 0))
            <=> ((float) ($a['value'] ?? 0));
        if ($cmp !== 0) return $cmp;

        $rank = function ($d) {
            $s = mb_strtolower(
                (string) ($d['name'] ?? '')
                . ' ' . (string) ($d['type'] ?? '')
            );
            if (str_contains($s, 'billete')
                || str_contains($s, 'bill')) return 0;
            if (str_contains($s, 'moneda')
                || str_contains($s, 'coin')) return 1;
            return 2;
        };
        return $rank($a) <=> $rank($b);
    });

    $countLines = [];
    foreach ($hasCashCount ? $cashRows : [] as $index => $row) {
        if (!is_array($row)) continue;

        $value = round((float) ($row['value'] ?? 0), 2);
        $qty = max(0, (int) ($row['quantity'] ?? 0));
        if ($value <= 0) continue;

        $name = trim((string) ($row['name'] ?? ''));
        $catalogRow = $denomCatalog[$index] ?? [];

        if ($name === ''
            && abs((float) ($catalogRow['value'] ?? 0) - $value)
                < 0.001) {
            $name = (string) ($catalogRow['name'] ?? '');
        }

        if ($name === '') {
            $name = '$' . number_format(
                $value, $value < 1 ? 2 : 0
            );
        }

        $typeString = mb_strtolower($name);
        $sortType = str_contains($typeString, 'billete')
            ? 0 : (str_contains($typeString, 'moneda') ? 1 : 2);

        $countLines[] = [
            'name' => $name,
            'quantity' => $qty,
            'value' => $value,
            'sort_type' => $sortType,
            'total' => round($qty * $value, 2),
        ];
    }

    usort($countLines, fn ($a, $b) =>
        (($b['value'] <=> $a['value']) !== 0)
            ? ($b['value'] <=> $a['value'])
            : ($a['sort_type'] <=> $b['sort_type'])
    );

    $cashCounted = round(
        array_sum(array_column($countLines, 'total')), 2
    );
    $cardsCredit = 0.0;
    $cardsDebit = 0.0;
    $otherCards = 0.0;
    $transfers = 0.0;
    $otherMethods = 0.0;
    $registered = 0.0;

    foreach ($methodTotals as $method) {
        $name = mb_strtolower(
            \Illuminate\Support\Str::ascii(
                (string) ($method['method'] ?? '')
            )
        );
        $amount = (float) ($method['total'] ?? 0);
        $registered += $amount;

        if (str_contains($name, 'efectivo')
            || str_contains($name, 'cash')) {
            continue;
        }
        if (str_contains($name, 'transfer')
            || str_contains($name, 'spei')) {
            $transfers += $amount;
        } elseif (str_contains($name, 'tarjeta')
            || str_contains($name, 'card')) {
            if (str_contains($name, 'credito')) {
                $cardsCredit += $amount;
            } elseif (str_contains($name, 'debito')) {
                $cardsDebit += $amount;
            } else {
                $otherCards += $amount;
            }
        } else {
            $otherMethods += $amount;
        }
    }

    $countedCombined = round(
        $cashCounted + $cardsCredit + $cardsDebit
        + $otherCards + $transfers + $otherMethods, 2
    );
    $difference = round($countedCombined - $registered, 2);
    $closingNote = trim((string) (
        $summary['session']['closing_note'] ?? ''
    ));
@endphp

<h2>Cuenta de efectivo</h2>
<table>
    <thead>
        <tr>
            <th>Denominación</th>
            <th class="center">Cantidad</th>
            <th class="right">Importe</th>
        </tr>
    </thead>
    <tbody>
        @forelse($countLines as $line)
            <tr>
                <td>{{ $line['name'] }}</td>
                <td class="center">{{ $line['quantity'] }}</td>
                <td class="right">{{ $money($line['total']) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3">
                    Sin conteo registrado
                </td>
            </tr>
        @endforelse
        <tr class="total-row">
            <td colspan="2">TOTAL EFECTIVO CONTADO</td>
            <td class="right">
                {{ $hasCashCount ? $money($cashCounted) : 'Sin conteo' }}
            </td>
        </tr>
    </tbody>
</table>

<h2>Conciliación del cierre</h2>
<table>
    <tbody>
        <tr>
            <td>Total cobrado registrado</td>
            <td class="right">{{ $money($registered) }}</td>
        </tr>
        <tr>
            <td>Efectivo esperado</td>
            <td class="right">
                {{ $money($summary['totals']['expected_cash'] ?? 0) }}
            </td>
        </tr>
        <tr>
            <td>Efectivo contado</td>
            <td class="right">
                {{ $hasCashCount ? $money($cashCounted) : 'Sin conteo' }}
            </td>
        </tr>
        <tr><td>Tarjeta de crédito</td>
            <td class="right">{{ $money($cardsCredit) }}</td></tr>
        <tr><td>Tarjeta de débito</td>
            <td class="right">{{ $money($cardsDebit) }}</td></tr>
        @if(abs($otherCards) > 0.009)
            <tr><td>Otras tarjetas</td>
                <td class="right">{{ $money($otherCards) }}</td></tr>
        @endif
        <tr><td>Transferencias</td>
            <td class="right">{{ $money($transfers) }}</td></tr>
        @if(abs($otherMethods) > 0.009)
            <tr><td>Otros métodos</td>
                <td class="right">{{ $money($otherMethods) }}</td></tr>
        @endif
        <tr class="net-row">
            <td>TOTAL CONTADO Y REGISTRADO</td>
            <td class="right">
                {{ $hasCashCount ? $money($countedCombined) : 'Sin conteo' }}
            </td>
        </tr>
        <tr class="total-row">
            <td>DIFERENCIA VS COBRADO</td>
            <td class="right">
                {{ $hasCashCount ? $money($difference) : 'Sin conteo' }}
            </td>
        </tr>
    </tbody>
</table>

@if($closingNote !== '')
    <p><strong>Nota de cierre:</strong> {{ $closingNote }}</p>
@endif


<div class="note">
    Este formato separa visualmente Impresión y Copias, aunque la categoría sigue perteneciendo a PAPELÓN.
</div>
</body>
</html>
