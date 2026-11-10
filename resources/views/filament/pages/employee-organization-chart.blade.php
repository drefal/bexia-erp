<x-filament-panels::page>
    @php
        $chart = $this->chartPayload();
        $byId = [];
        $children = [];
        $roots = [];

        foreach ($chart['nodes'] as $row) {
            $byId[(int) $row['id']] = $row;
        }

        // Reconstruir el arbol usando el recorrido ya
        // validado por chartPayload(), sin alterar jefes.
        foreach ($chart['nodes'] as $row) {
            $id = (int) $row['id'];
            $ancestors = $row['ancestors'] ?? [];

            if (empty($ancestors)) {
                $roots[] = $id;
                continue;
            }

            $parentId = (int) end($ancestors);
            $children[$parentId][] = $id;
        }

        $managerIds = array_keys($children);
        $initialCollapsed = [];

        // Mantener visible la primera linea de mando.
        // Los siguientes niveles empiezan contraidos.
        foreach ($managerIds as $id) {
            if (($byId[$id]['depth'] ?? 0) >= 1) {
                $initialCollapsed[] = (int) $id;
            }
        }
    @endphp

    <style>
        [x-cloak] { display: none !important; }

        .org-shell {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .org-panel {
            border: 1px solid #e2e8f0;
            background: #fff;
            border-radius: 14px;
            padding: 18px;
        }

        .org-heading {
            font-size: 1.25rem;
            line-height: 1.5;
            font-weight: 750;
            color: #0f172a;
        }

        .org-muted {
            font-size: .85rem;
            color: #64748b;
        }

        .org-area-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(175px, 1fr));
            gap: 10px;
        }

        .org-area-card {
            text-align: left;
            padding: 14px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: white;
            transition: border-color .15s, box-shadow .15s;
        }

        .org-area-card:hover {
            border-color: #94a3b8;
            box-shadow: 0 2px 10px rgba(15, 23, 42, .07);
        }

        .org-area-card-selected {
            border-color: #2563eb !important;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, .14);
        }

        .org-area-title {
            font-size: .85rem;
            font-weight: 650;
            color: #334155;
        }

        .org-area-number {
            font-size: 1.6rem;
            line-height: 1.5;
            font-weight: 750;
            color: #0f172a;
        }

        .org-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .org-search {
            flex: 1 1 270px;
            min-width: 0;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            padding: 9px 12px;
            font-size: .875rem;
            background: #fff;
            color: #0f172a;
        }

        .org-action {
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            padding: 9px 12px;
            font-size: .8rem;
            font-weight: 600;
            color: #334155;
            background: #fff;
        }

        .org-action:hover {
            background: #f1f5f9;
        }

        .org-chart-canvas {
            padding: 24px;
            overflow-x: auto;
            background:
                radial-gradient(#e2e8f0 0.7px, transparent 0.7px);
            background-size: 18px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
        }

        .org-roots,
        .org-children {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .org-roots {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .org-node {
            position: relative;
            min-width: 0;
        }

        .org-children {
            position: relative;
            margin-left: 20px;
            padding-left: 23px;
            padding-top: 10px;
            border-left: 2px solid #cbd5e1;
        }

        .org-children > .org-node {
            margin-bottom: 9px;
        }

        .org-children > .org-node::before {
            content: '';
            position: absolute;
            top: 27px;
            left: -23px;
            width: 23px;
            border-top: 2px solid #cbd5e1;
        }

        .org-card {
            display: block;
            width: min(100%, 660px);
            border: 1px solid #dbe3ed;
            border-radius: 12px;
            padding: 12px 14px;
            background: #fff;
            box-shadow: 0 2px 5px rgba(15, 23, 42, .035);
        }

        .org-card-root {
            border-color: #93b4ea;
            border-top: 3px solid #2563eb;
            background: #eff6ff;
        }

        .org-card-match {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, .12);
        }

        .org-card-top {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .org-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 30px;
            height: 30px;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 750;
            cursor: pointer;
        }

        .org-leaf {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 30px;
            color: #94a3b8;
        }

        .org-card-info {
            min-width: 0;
        }

        .org-person-name {
            font-size: .94rem;
            font-weight: 750;
            line-height: 1.4;
            overflow-wrap: anywhere;
            color: #0f172a;
        }

        .org-person-position {
            margin-top: 3px;
            font-size: .82rem;
            color: #475569;
        }

        .org-card-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }

        .org-area-pill {
            border-radius: 999px;
            background: #f1f5f9;
            color: #475569;
            padding: 3px 9px;
            font-size: .7rem;
        }

        .org-team-count {
            font-size: .7rem;
            color: #64748b;
        }

        .dark .org-panel,
        .dark .org-area-card,
        .dark .org-card,
        .dark .org-search,
        .dark .org-action {
            background: #111827;
            border-color: #374151;
        }

        .dark .org-heading,
        .dark .org-area-title,
        .dark .org-area-number,
        .dark .org-person-name {
            color: #f9fafb;
        }

        .dark .org-person-position,
        .dark .org-muted {
            color: #cbd5e1;
        }

        .dark .org-chart-canvas {
            background-color: #0b1220;
            border-color: #374151;
        }

        .dark .org-card-root {
            background: #172554;
            border-top-color: #60a5fa;
        }

        .dark .org-area-pill {
            background: #374151;
            color: #e5e7eb;
        }

        .dark .org-search,
        .dark .org-action {
            color: #f9fafb;
        }

        @media (max-width: 640px) {
            .org-chart-canvas { padding: 12px; }
            .org-children {
                margin-left: 9px;
                padding-left: 13px;
            }
            .org-children > .org-node::before {
                left: -13px;
                width: 13px;
            }
        }
    </style>

    <div
        x-data="{
            nodes: @js($chart['nodes']),
            area: '',
            query: '',
            collapsed: @js($initialCollapsed),

            normalize(value) {
                return String(value || '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase();
            },

            filtering() {
                return Boolean(this.area || this.query.trim());
            },

            matches(row) {
                if (this.area && row.department !== this.area) {
                    return false;
                }

                const q = this.normalize(this.query.trim());

                if (!q) return true;

                const text = this.normalize(
                    row.name + ' ' + row.position + ' ' +
                    row.department + ' ' + (row.manager_name || '')
                );

                return text.includes(q);
            },

            matchesById(id) {
                const row = this.nodes.find(n => n.id === id);
                return row ? this.matches(row) : false;
            },

            visible(id) {
                if (!this.filtering()) return true;

                const row = this.nodes.find(n => n.id === id);

                if (!row) return false;
                if (this.matches(row)) return true;

                return this.nodes.some(other =>
                    this.matches(other) &&
                    other.ancestors.includes(id)
                );
            },

            toggle(id) {
                if (this.collapsed.includes(id)) {
                    this.collapsed = this.collapsed.filter(x => x !== id);
                } else {
                    this.collapsed = [...this.collapsed, id];
                }
            },

            expandAll() {
                this.collapsed = [];
            },

            collapseAll() {
                this.collapsed = this.nodes
                    .filter(row => row.has_children)
                    .map(row => row.id);
            },

            resetFilters() {
                this.area = '';
                this.query = '';
                this.collapsed = @js($initialCollapsed);
            },

            matchedCount() {
                return this.nodes.filter(row => this.matches(row)).length;
            }
        }"
        class="org-shell"
    >
        <section class="org-panel">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="org-heading">Organigrama corporativo</h2>
                    <p class="org-muted mt-1">
                        Estructura basada en los jefes directos registrados en RRHH.
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-gray-950 dark:text-white">
                        {{ $chart['total'] }}
                    </div>
                    <div class="org-muted">Empleados activos</div>
                </div>
            </div>
        </section>

        <section class="org-area-grid">
            @foreach ($chart['areas'] as $areaItem)
                <button
                    type="button"
                    class="org-area-card"
                    x-on:click="area = area === @js($areaItem['name']) ? '' : @js($areaItem['name'])"
                    x-bind:class="area === @js($areaItem['name'])
                        ? 'org-area-card-selected'
                        : ''"
                    x-bind:aria-pressed="area === @js($areaItem['name'])"
                >
                    <div class="org-area-title">
                        {{ $areaItem['name'] }}
                    </div>
                    <div class="org-area-number">
                        {{ $areaItem['count'] }}
                    </div>
                    <div class="org-muted">colaboradores</div>
                </button>
            @endforeach
        </section>

        <section class="org-panel">
            <div class="org-toolbar">
                <input
                    type="search"
                    x-model.debounce.200ms="query"
                    placeholder="Buscar empleado, puesto, departamento o jefe..."
                    aria-label="Buscar empleado en organigrama"
                    class="org-search"
                />

                <button type="button" class="org-action"
                    x-on:click="expandAll()">
                    Expandir todo
                </button>

                <button type="button" class="org-action"
                    x-on:click="collapseAll()">
                    Contraer todo
                </button>

                <button type="button" class="org-action"
                    x-on:click="resetFilters()">
                    Restablecer
                </button>
            </div>

            <p class="org-muted mt-3">
                <span x-text="matchedCount()"></span>
                empleados coinciden con el filtro.
                La búsqueda conserva visibles sus superiores.
            </p>
        </section>

        <section class="org-chart-canvas">
            @if (count($roots))
                <ul class="org-roots">
                    @foreach ($roots as $rootId)
                        @include('filament.pages.partials.employee-org-node', [
                            'node' => $byId[$rootId],
                            'children' => $children,
                            'byId' => $byId,
                        ])
                    @endforeach
                </ul>
            @else
                <div class="org-muted text-center">
                    No hay empleados activos para mostrar.
                </div>
            @endif
        </section>

        <p class="org-muted">
            Vista de consulta. Los filtros y controles no modifican
            empleados, departamentos ni jefes directos.
        </p>
    </div>
</x-filament-panels::page>
