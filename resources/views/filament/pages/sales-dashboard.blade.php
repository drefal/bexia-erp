<x-filament-panels::page>
@php
    $data = $this->dashboardData;

    $money = static fn ($value) => '$ ' . number_format((float) $value, 2);

    $mainValues = array_column($data['main_series'], 'value');
    $mainMax = max($mainValues ?: [1]);
    $mainMin = min($mainValues ?: [0]);

    $hourMax = max(array_column($data['hourly_sales'], 'value') ?: [1]);
    $weekdayMax = max(array_column($data['weekday_sales'], 'value') ?: [1]);
    $productMax = max(array_column($data['top_products'], 'sales') ?: [1]);
    $branchMax = max(array_column($data['branches'], 'sales') ?: [1]);

    $chartW = 900;
    $chartH = 270;
    $padL = 50;
    $padR = 20;
    $padT = 20;
    $padB = 36;

    $plotW = $chartW - $padL - $padR;
    $plotH = $chartH - $padT - $padB;

    $points = [];

    foreach ($data['main_series'] as $i => $point) {
        $count = max(1, count($data['main_series']) - 1);

        $x = $padL + (($i / $count) * $plotW);
        $y = $padT + $plotH - (($point['value'] / max(1, $mainMax)) * $plotH);

        $points[] = [
            'x' => round($x, 2),
            'y' => round($y, 2),
            'label' => $point['label'],
            'value' => $point['value'],
        ];
    }

    $polyline = collect($points)
        ->map(fn ($p) => $p['x'] . ',' . $p['y'])
        ->implode(' ');

    $areaPoints = $padL . ',' . ($padT + $plotH)
        . ' ' . $polyline
        . ' ' . ($padL + $plotW) . ',' . ($padT + $plotH);

    $paymentGradient = [];
    $gradientStart = 0;

    foreach ($data['payment_methods'] as $payment) {
        $gradientEnd = $gradientStart + $payment['percentage'];

        $paymentGradient[] =
            $payment['css'] . ' ' . $gradientStart . '% ' . $gradientEnd . '%';

        $gradientStart = $gradientEnd;
    }

    $donutGradient = implode(', ', $paymentGradient);
@endphp

<style>
    .sd-dashboard {
        --sd-blue: #2563eb;
        --sd-blue-soft: #eff6ff;
        --sd-green: #16a34a;
        --sd-red: #dc2626;
        --sd-border: rgb(226 232 240);
        --sd-muted: rgb(100 116 139);
    }

    .dark .sd-dashboard {
        --sd-border: rgb(51 65 85);
        --sd-muted: rgb(148 163 184);
    }

    .sd-card {
        background: white;
        border: 1px solid var(--sd-border);
        border-radius: 14px;
        height: auto;
    }

    .dark .sd-card {
        background: rgb(17 24 39);
    }

    .sd-filter-label {
        display: block;
        margin-bottom: .35rem;
        font-size: .72rem;
        font-weight: 600;
        color: var(--sd-muted);
    }

    .sd-input {
        width: 100%;
        min-height: 40px;
        border: 1px solid var(--sd-border);
        border-radius: 9px;
        background: white;
        padding: 0 .65rem;
        font-size: .78rem;
    }

    .dark .sd-input {
        background: rgb(15 23 42);
        color: rgb(241 245 249);
    }

    .sd-section-title {
        font-size: .95rem;
        font-weight: 700;
        line-height: 1.25rem;
    }

    .sd-section-subtitle {
        margin-top: .12rem;
        font-size: .72rem;
        color: var(--sd-muted);
    }

    .sd-kpi {
        position: relative;
        overflow: hidden;
    }

    .sd-kpi-green { background: linear-gradient(135deg, #f0fdf4, #ffffff); }
    .sd-kpi-blue { background: linear-gradient(135deg, #eff6ff, #ffffff); }
    .sd-kpi-violet { background: linear-gradient(135deg, #f5f3ff, #ffffff); }
    .sd-kpi-orange { background: linear-gradient(135deg, #fff7ed, #ffffff); }
    .sd-kpi-cyan { background: linear-gradient(135deg, #ecfeff, #ffffff); }
    .sd-kpi-amber { background: linear-gradient(135deg, #fffbeb, #ffffff); }

    .dark .sd-kpi-green,
    .dark .sd-kpi-blue,
    .dark .sd-kpi-violet,
    .dark .sd-kpi-orange,
    .dark .sd-kpi-cyan,
    .dark .sd-kpi-amber {
        background: rgb(17 24 39);
    }

    /* =========================
       LAYOUT EXPLICITO
       ========================= */

    .sd-kpi-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: 1fr;
    }

    .sd-row {
        display: grid;
        gap: 16px;
        grid-template-columns: 1fr;
    }

    @media (min-width: 768px) {
        .sd-kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (min-width: 1280px) {
        .sd-kpi-grid {
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }

        .sd-row-1,
        .sd-row-2,
        .sd-row-3,
        .sd-row-4 {
            grid-template-columns: repeat(12, minmax(0, 1fr));
        }

        .sd-col-6 { grid-column: span 6 / span 6; }
        .sd-col-4 { grid-column: span 4 / span 4; }
        .sd-col-3 { grid-column: span 3 / span 3; }
        .sd-col-12 { grid-column: span 12 / span 12; }
    }

    .sd-bar-track {
        height: 8px;
        overflow: hidden;
        border-radius: 999px;
        background: rgb(226 232 240);
    }

    .dark .sd-bar-track {
        background: rgb(51 65 85);
    }

    .sd-bar-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #60a5fa, #2563eb);
    }

    .sd-hour-bars {
        display: flex;
        height: 220px;
        align-items: end;
        gap: 7px;
    }

    .sd-hour-col {
        display: flex;
        min-width: 0;
        flex: 1 1 0%;
        flex-direction: column;
        justify-content: end;
        height: 100%;
    }

    .sd-hour-bar {
        width: 100%;
        min-height: 3px;
        border-radius: 5px 5px 1px 1px;
        background: linear-gradient(180deg, #3b82f6, #2563eb);
    }

    .sd-heat {
        border-radius: 5px;
        min-height: 29px;
        background: rgb(37 99 235);
    }

    .sd-insight-green { background: #f0fdf4; }
    .sd-insight-blue { background: #eff6ff; }
    .sd-insight-orange { background: #fff7ed; }
    .sd-insight-violet { background: #f5f3ff; }

    .dark .sd-insight-green,
    .dark .sd-insight-blue,
    .dark .sd-insight-orange,
    .dark .sd-insight-violet {
        background: rgb(30 41 59);
    }

    .sd-donut {
        width: 160px;
        aspect-ratio: 1;
        border-radius: 50%;
        position: relative;
        background: conic-gradient({{ $donutGradient }});
    }

    .sd-donut::after {
        content: '';
        position: absolute;
        inset: 28px;
        border-radius: 50%;
        background: white;
    }

    .dark .sd-donut::after {
        background: rgb(17 24 39);
    }

    @media (max-width: 768px) {
        .sd-hour-bars {
            gap: 3px;
        }
    }
    /* =========================================================
       FIX FILAMENT: mantener todo dentro del area central
       ========================================================= */

    .sd-dashboard {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: hidden;
    }

    .sd-dashboard,
    .sd-dashboard * {
        box-sizing: border-box;
    }

    .sd-dashboard > * {
        max-width: 100%;
        min-width: 0;
    }

    .sd-kpi-grid,
    .sd-row {
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }

    .sd-kpi-grid > *,
    .sd-row > * {
        min-width: 0;
        max-width: 100%;
    }

    .sd-card {
        min-width: 0;
        max-width: 100%;
        height: auto;
    }

    /*
     * Solo las tarjetas que viven dentro de renglones analiticos
     * igualan altura con sus companeras.
     */
    .sd-row > .sd-card {
        height: 100%;
    }

    /*
     * La tarjeta de filtros JAMAS debe tomar la altura completa
     * del contenedor Filament.
     */
    .sd-dashboard > .sd-card {
        height: auto;
    }

    /*
     * Los elementos graficos pueden tener scroll interno
     * pero nunca aumentar el ancho de la pagina.
     */
    .sd-dashboard svg,
    .sd-dashboard table,
    .sd-dashboard .sd-hour-bars {
        max-width: 100%;
    }

    /*
     * KPIs: 6 columnas reales en escritorio.
     */
     (min-width: 1280px) {
        .sd-kpi-grid {
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }
    }

    /*
     * Laptop / resoluciones menores.
     */
     (min-width: 900px) and (max-width: 1279px) {
        .sd-kpi-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    /*
     * Evitar que textos largos rompan columnas.
     */
    .sd-card,
    .sd-card div,
    .sd-card span {
        min-width: 0;
    }


    /* ================================================================
       V1.4B
       ================================================================ */

    .sd-filter-section + .sd-filter-section {
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px solid rgba(148, 163, 184, .20);
    }

    .sd-filter-group-title {
        margin-bottom: 8px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: rgb(100 116 139);
    }

    .sd-filter-grid-context {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .sd-filter-grid-period {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 12px;
        align-items: end;
    }

    .sd-row-1 > .sd-col-6 {
        grid-column: span 12;
    }

    .sd-row-1 > .sd-col-3 {
        grid-column: span 6;
    }

    .sd-col-4 {
        grid-column: span 4;
    }

    .sd-top-compact-list {
        margin-top: 12px;
        display: grid;
        gap: 8px;
    }

    .sd-top-compact-row {
        display: grid;
        grid-template-columns: 26px minmax(0, 1fr) auto;
        align-items: center;
        gap: 9px;
        min-height: 38px;
        padding: 5px 0;
    }

    .sd-top-compact-rank {
        display: flex;
        width: 24px;
        height: 24px;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        background: rgb(241 245 249);
        color: rgb(71 85 105);
        font-size: 10px;
        font-weight: 700;
    }

    .dark .sd-top-compact-rank {
        background: rgb(31 41 55);
        color: rgb(203 213 225);
    }

    .sd-top-compact-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 12px;
        font-weight: 600;
    }

    .sd-top-compact-ref {
        margin-top: 1px;
        font-size: 10px;
        color: rgb(148 163 184);
    }

    .sd-top-compact-value {
        white-space: nowrap;
        text-align: right;
        font-size: 12px;
        font-weight: 700;
    }

    @media (max-width: 1279px) {
        .sd-filter-grid-context,
        .sd-filter-grid-period {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .sd-col-4 {
            grid-column: span 6;
        }
    }

    @media (max-width: 767px) {
        .sd-filter-grid-context,
        .sd-filter-grid-period {
            grid-template-columns: 1fr;
        }

        .sd-row-1 > .sd-col-3,
        .sd-col-4 {
            grid-column: span 12;
        }
    }


    /* ================================================================
       DASHBOARD V1.4C3 - LAYOUT ANALITICOS
       ================================================================ */

    .sd-row-v14c {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 1rem;
        align-items: stretch;
    }

    .sd-row-v14c > .sd-card {
        height: 100%;
        min-width: 0;
    }

    .sd-v14c-col-8 {
        grid-column: span 8;
    }

    .sd-v14c-col-4 {
        grid-column: span 4;
    }

    @media (max-width: 1279px) {
        .sd-v14c-col-8 {
            grid-column: span 12;
        }

        .sd-v14c-col-4 {
            grid-column: span 6;
        }
    }

    @media (max-width: 767px) {
        .sd-v14c-col-8,
        .sd-v14c-col-4 {
            grid-column: span 12;
        }
    }


    /* ================================================================
       DASHBOARD V1.4D - LAYOUT ANALITICOS
       ================================================================ */

    .sd-row-v14d {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 1rem;
        align-items: stretch;
    }

    .sd-row-v14d > .sd-card {
        height: auto;
        min-width: 0;
    }

    .sd-v14d-col-12 {
        grid-column: span 12;
    }

    .sd-v14d-col-6 {
        grid-column: span 6;
    }

    @media (max-width: 767px) {
        .sd-v14d-col-12,
        .sd-v14d-col-6 {
            grid-column: span 12;
        }
    }

</style>

<div class="sd-dashboard space-y-4">

    {{-- TITULO --}}
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary-50 text-primary-600
                           dark:bg-primary-500/10 dark:text-primary-400"
                >
                    <x-heroicon-o-chart-bar-square class="h-7 w-7"/>
                </div>

                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Dashboard de Ventas
                    </h1>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Información clave para una mejor toma de decisiones
                    </p>
                </div>
            </div>
        </div>

        <div>
            <span
                class="inline-flex rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700
                       dark:bg-green-500/10 dark:text-green-300"
            >
                Datos reales
            </span>
        </div>
    </div>

    {{-- FILTROS --}}
    <div class="sd-card p-4">

        <div class="sd-filter-section">
            <div class="sd-filter-group-title">
                Contexto de venta
            </div>

            <div class="sd-filter-grid-context">
                <div>
                    <label class="sd-filter-label">Alcance</label>
                    <select wire:model.live="scope" class="sd-input">
                        <option value="group">Grupo</option>
                        <option value="company">Empresa</option>
                        <option value="branch">Sucursal</option>
                        <option value="user">Usuario</option>
                    </select>
                </div>

                <div>
                    <label class="sd-filter-label">Empresa</label>
                    <select wire:model.live="companyId" class="sd-input">
                        @forelse($this->companyOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @empty
                            <option value="">Sin empresas disponibles</option>
                        @endforelse
                    </select>
                </div>

                <div>
                    <label class="sd-filter-label">Sucursal</label>
                    <select wire:model.live="branchId" class="sd-input">
                        <option value="">Todas las sucursales</option>

                        @foreach($this->branchOptions as $id => $name)
                            <option value="{{ $id }}">
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="sd-filter-label">Canal</label>
                    <select wire:model.live="channel" class="sd-input">
                        <option value="all">Todos</option>
                        <option value="pos">PDV</option>
                        <option value="sales">Ventas</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="sd-filter-section">
            <div class="sd-filter-group-title">
                Periodo y análisis
            </div>

            <div class="sd-filter-grid-period">
                <div>
                    <label class="sd-filter-label">Periodo</label>
                    <select wire:model.live="period" class="sd-input">
                        <option value="today">Hoy</option>
                        <option value="yesterday">Ayer</option>
                        <option value="week">Esta semana</option>
                        <option value="month">Este mes</option>
                        <option value="last_month">Mes anterior</option>
                        <option value="year">Este año</option>
                        <option value="custom">Personalizado</option>
                    </select>
                </div>

                <div>
                    <label class="sd-filter-label">Desde</label>
                    <input
                        type="date"
                        wire:model.live="from"
                        class="sd-input"
                    >
                </div>

                <div>
                    <label class="sd-filter-label">Hasta</label>
                    <input
                        type="date"
                        wire:model.live="until"
                        class="sd-input"
                    >
                </div>

                <div>
                    <label class="sd-filter-label">Granularidad</label>
                    <select wire:model.live="granularity" class="sd-input">
                        <option value="hour">Por hora</option>
                        <option value="day">Por día</option>
                        <option value="week">Por semana</option>
                        <option value="month">Por mes</option>
                    </select>
                </div>

                <div>
                    <label class="sd-filter-label">Comparar contra</label>

                    <div class="flex gap-2">
                        <select
                            wire:model.live="comparison"
                            class="sd-input"
                        >
                            <option value="previous_period">
                                Periodo anterior
                            </option>

                            <option value="previous_year">
                                Año anterior
                            </option>

                            <option value="none">
                                Sin comparación
                            </option>
                        </select>

                        <button
                            type="button"
                            wire:click="refreshDashboard"
                            title="Actualizar"
                            class="inline-flex h-10 w-10 shrink-0
                                   items-center justify-center rounded-lg
                                   bg-primary-600 text-white
                                   hover:bg-primary-500"
                        >
                            <x-heroicon-o-arrow-path class="h-4 w-4"/>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- KPIS --}}
    <div class="sd-kpi-grid">
        @foreach($data['kpis'] as $kpi)
            <div class="sd-card sd-kpi sd-kpi-{{ $kpi['tone'] }} p-4">
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full
                               bg-white/70 text-primary-600 shadow-sm
                               dark:bg-white/5 dark:text-primary-400"
                    >
                        <x-dynamic-component :component="$kpi['icon']" class="h-5 w-5" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ $kpi['label'] }}
                        </div>

                        <div class="mt-1 truncate text-xl font-bold tracking-tight">
                            {{ $kpi['display'] }}
                        </div>

                        @if($kpi['show_change'] ?? true)
                            <div class="mt-1 flex items-center gap-1.5 text-[11px]">
                                <span class="font-bold {{ $kpi['change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $kpi['change'] >= 0 ? '▲' : '▼' }}
                                    {{ number_format(abs($kpi['change']), 1) }}{{ $kpi['suffix'] }}
                                </span>

                                <span class="text-gray-400">
                                    vs. periodo anterior
                                </span>
                            </div>
                        @elseif(!empty($kpi['note']))
                            <div class="mt-1 text-[11px] font-medium text-amber-600 dark:text-amber-400">
                                {{ $kpi['note'] }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ROW 1 ANALITICOS V1.4D --}}
    <div class="sd-row sd-row-v14d">

        {{-- GRAFICA PRINCIPAL --}}
        <div class="sd-card p-4 sd-v14d-col-12">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="sd-section-title">
                        Ventas por
                        {{
                            match($granularity) {
                                'hour' => 'hora',
                                'week' => 'semana',
                                'month' => 'mes',
                                default => 'día',
                            }
                        }}
                    </h2>

                    <p class="sd-section-subtitle">
                        Evolución de las ventas netas
                    </p>
                </div>

                <div class="text-right">
                    <div class="text-xs text-gray-500">Ventas netas</div>
                    <div class="font-bold">
                        {{ $data['kpis'][0]['display'] }}
                    </div>
                </div>
            </div>

            <div class="mt-3 overflow-x-auto">
                <svg
                    viewBox="0 0 {{ $chartW }} {{ $chartH }}"
                    class="min-w-[650px] w-full"
                    role="img"
                    aria-label="Gráfica de ventas"
                >
                    @for($i = 0; $i <= 4; $i++)
                        @php
                            $gy = $padT + (($plotH / 4) * $i);
                            $gValue = $mainMax - (($mainMax / 4) * $i);
                        @endphp

                        <line
                            x1="{{ $padL }}"
                            y1="{{ $gy }}"
                            x2="{{ $padL + $plotW }}"
                            y2="{{ $gy }}"
                            stroke="currentColor"
                            class="text-gray-200 dark:text-gray-700"
                            stroke-width="1"
                        />

                        <text
                            x="{{ $padL - 8 }}"
                            y="{{ $gy + 4 }}"
                            text-anchor="end"
                            fill="currentColor"
                            class="text-gray-400"
                            font-size="11"
                        >
                            ${{ number_format($gValue / 1000, 0) }}k
                        </text>
                    @endfor

                    <polygon points="{{ $areaPoints }}" fill="rgba(37,99,235,.08)" />

                    <polyline
                        points="{{ $polyline }}"
                        fill="none"
                        stroke="#2563eb"
                        stroke-width="3"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />

                    @foreach($points as $i => $point)
                        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="#2563eb">
                            <title>{{ $point['label'] }} · {{ $money($point['value']) }}</title>
                        </circle>

                        @if($i === 0 || $i === count($points) - 1 || $i % max(1, (int) floor(count($points) / 7)) === 0)
                            <text
                                x="{{ $point['x'] }}"
                                y="{{ $chartH - 9 }}"
                                text-anchor="middle"
                                fill="currentColor"
                                class="text-gray-400"
                                font-size="11"
                            >
                                {{ $point['label'] }}
                            </text>
                        @endif
                    @endforeach
                </svg>
            </div>
        </div>



    </div>

    {{-- ROW 2 ANALITICOS V1.4D --}}
    <div class="sd-row sd-row-v14d">

        {{-- VENTAS POR HORA --}}
        <div class="sd-card p-4 sd-v14d-col-12">
            <h2 class="sd-section-title">Ventas por hora</h2>
            <p class="sd-section-subtitle">Distribución de ventas durante el día</p>

            <div class="sd-hour-bars mt-4">
                @foreach($data['hourly_sales'] as $point)
                    @php
                        $height = max(3, ($point['value'] / max(1, $hourMax)) * 170);
                    @endphp

                    <div class="sd-hour-col">
                        <div class="mb-1 text-center text-[9px] font-semibold text-gray-500">
                            {{ number_format($point['value'] / 1000, 1) }}k
                        </div>

                        <div
                            class="sd-hour-bar"
                            style="height: {{ $height }}px"
                            title="{{ $point['label'] }}:00 · {{ $money($point['value']) }}"
                        ></div>

                        <div class="mt-2 text-center text-[10px] text-gray-400">
                            {{ $point['label'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>



    </div>

    {{-- ROW 3 ANALITICOS V1.4D --}}
    <div class="sd-row sd-row-v14d">

        {{-- HEATMAP --}}
        <div class="sd-card p-4 sd-v14d-col-12">
            <h2 class="sd-section-title">Comportamiento por hora y día</h2>
            <p class="sd-section-subtitle">Más oscuro = mayor venta</p>

            <div class="mt-4 overflow-x-auto">
                <div
                    class="grid min-w-[520px] gap-1 text-[10px]"
                    style="grid-template-columns: 75px repeat({{ count($data['heatmap']['hours']) }}, minmax(32px, 1fr));"
                >
                    <div></div>

                    @foreach($data['heatmap']['hours'] as $hour)
                        <div class="text-center text-gray-400">{{ $hour }}</div>
                    @endforeach

                    @foreach($data['heatmap']['rows'] as $row)
                        <div class="flex items-center text-gray-500">
                            {{ $row['day'] }}
                        </div>

                        @foreach($row['values'] as $value)
                            @php
                                $opacity = 0.08 + (($value / 9) * 0.92);
                            @endphp

                            <div
                                class="sd-heat"
                                style="opacity: {{ $opacity }}"
                                title="{{ $row['day'] }} · intensidad {{ $value }}"
                            ></div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>

    </div>

    {{-- ROW 4 ANALITICOS V1.4D --}}
    <div class="sd-row sd-row-v14d">

        {{-- METODOS PAGO --}}
        <div class="sd-card p-4 sd-v14d-col-6">
            <h2 class="sd-section-title">Métodos de pago</h2>
            <p class="sd-section-subtitle">Distribución de ventas por método de pago</p>

            <div class="mt-5 flex flex-col items-center gap-5 sm:flex-row">
                <div class="relative shrink-0">
                    <div class="sd-donut"></div>

                    <div
                        class="pointer-events-none absolute inset-0 z-10 flex
                               flex-col items-center justify-center text-center"
                    >
                        <div class="text-sm font-bold">
                            {{ $data['kpis'][0]['display'] }}
                        </div>
                        <div class="text-[10px] text-gray-400">Ventas netas</div>
                    </div>
                </div>

                <div class="w-full space-y-3">
                    @foreach($data['payment_methods'] as $payment)
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-2">
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                    style="background: {{ $payment['css'] }}"
                                ></span>

                                <div class="truncate text-xs">
                                    {{ $payment['label'] }}
                                </div>
                            </div>

                            <div class="text-right">
                                <div class="text-xs font-bold">
                                    {{ number_format($payment['percentage'], 1) }}%
                                </div>

                                <div class="text-[10px] text-gray-400">
                                    {{ $money($payment['value']) }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- DIA DE SEMANA --}}
        <div class="sd-card p-4 sd-v14d-col-6">
            <h2 class="sd-section-title">Ventas por día de la semana</h2>
            <p class="sd-section-subtitle">Comparativa de ventas por día</p>

            <div class="mt-5 flex h-[218px] items-end gap-2">
                @foreach($data['weekday_sales'] as $point)
                    @php
                        $height = max(10, ($point['value'] / max(1, $weekdayMax)) * 155);
                    @endphp

                    <div class="flex min-w-0 flex-1 flex-col items-center justify-end">
                        <div class="mb-1 text-center text-[9px] font-bold">
                            ${{ number_format($point['value'] / 1000, 1) }}k
                        </div>

                        @php
                            $isBestDay =
                                (float) $point['value'] > 0
                                && (float) $point['value'] >= (float) $weekdayMax;
                        @endphp

                        <div
                            class="w-full rounded-t-md shadow-sm"
                            style="
                                height: {{ $height }}px;
                                background-color: {{ $isBestDay ? '#10b981' : '#3b82f6' }};
                            "
                            title="{{ $point['label'] }} · ${{ number_format($point['value'], 2) }}"
                        ></div>

                        <div class="mt-2 text-[10px] text-gray-500">
                            {{ $point['label'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>


    </div>

    {{-- ROW 3 --}}
    <div class="sd-row sd-row-3">

        {{-- TOP PRODUCTOS POR MONTO --}}
        <div class="sd-card p-4 sd-col-4">
            <h2 class="sd-section-title">
                Top productos por monto
            </h2>

            <p class="sd-section-subtitle">
                Ventas netas
            </p>

            <div class="sd-top-compact-list">
                @forelse($data['top_products'] as $index => $product)
                    <div class="sd-top-compact-row">
                        <span class="sd-top-compact-rank">
                            {{ $index + 1 }}
                        </span>

                        <div class="min-w-0">
                            <div class="sd-top-compact-name">
                                {{ $product['name'] }}
                            </div>

                            <div class="sd-top-compact-ref">
                                {{ $product['reference'] }}
                            </div>
                        </div>

                        <div class="sd-top-compact-value">
                            {{ $money($product['sales']) }}
                        </div>
                    </div>
                @empty
                    <div class="text-xs text-gray-400">
                        Sin ventas en el periodo.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- TOP PRODUCTOS POR CANTIDAD --}}
        <div class="sd-card p-4 sd-col-4">
            <h2 class="sd-section-title">
                Top productos por cantidad
            </h2>

            <p class="sd-section-subtitle">
                Unidades vendidas
            </p>

            <div class="sd-top-compact-list">
                @forelse($data['top_products_by_quantity'] as $index => $product)
                    <div class="sd-top-compact-row">
                        <span class="sd-top-compact-rank">
                            {{ $index + 1 }}
                        </span>

                        <div class="min-w-0">
                            <div class="sd-top-compact-name">
                                {{ $product['name'] }}
                            </div>

                            <div class="sd-top-compact-ref">
                                {{ $product['reference'] }}
                            </div>
                        </div>

                        <div class="sd-top-compact-value">
                            {{ number_format($product['quantity'], 2) }}
                        </div>
                    </div>
                @empty
                    <div class="text-xs text-gray-400">
                        Sin ventas en el periodo.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- SUCURSALES --}}
        <div class="sd-card p-4 sd-col-4">
            <h2 class="sd-section-title">Sucursales</h2>
            <p class="sd-section-subtitle">Ventas netas por sucursal</p>

            <div class="mt-4 space-y-3">
                @foreach($data['branches'] as $index => $branch)
                    @php
                        $width = ($branch['sales'] / max(1, $branchMax)) * 100;
                    @endphp

                    <div class="grid grid-cols-[22px_120px_minmax(80px,1fr)_100px_60px] items-center gap-2">
                        <span
                            class="flex h-5 w-5 items-center justify-center rounded-full
                                   bg-amber-50 text-[10px] font-bold text-amber-700
                                   dark:bg-amber-500/10 dark:text-amber-300"
                        >
                            {{ $index + 1 }}
                        </span>

                        <span class="truncate text-xs font-medium">
                            {{ $branch['name'] }}
                        </span>

                        <div class="sd-bar-track">
                            <div class="sd-bar-fill" style="width: {{ $width }}%"></div>
                        </div>

                        <span class="text-right text-xs font-bold">
                            {{ $money($branch['sales']) }}
                        </span>

                        <span class="text-right text-[10px] font-bold text-green-600">
                            ▲ {{ number_format($branch['change'], 1) }}%
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ROW 4 --}}
    <div class="sd-row sd-row-4">
        <div class="sd-card p-4 sd-col-12">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="sd-section-title">Hallazgos</h2>
                    <p class="sd-section-subtitle">Información relevante detectada automáticamente</p>
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($data['insights'] as $insight)
                    <div class="sd-insight-{{ $insight['tone'] }} rounded-xl p-3">
                        <div class="flex items-start gap-2">
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center
                                       rounded-full bg-white/70 text-primary-600
                                       dark:bg-white/5 dark:text-primary-400"
                            >
                                <x-dynamic-component :component="$insight['icon']" class="h-4 w-4" />
                            </div>

                            <div>
                                <div class="text-xs font-bold">
                                    {{ $insight['title'] }}
                                </div>

                                <p class="mt-1 text-[10px] leading-4 text-gray-600 dark:text-gray-300">
                                    {{ $insight['body'] }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

</div>
</x-filament-panels::page>
