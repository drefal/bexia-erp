@php
    $employeeId = (int) $node['id'];
    $nodeChildren = $children[$employeeId] ?? [];
    $isRoot = empty($node['ancestors']);
@endphp

<li
    class="org-node"
    x-show="visible({{ $employeeId }})"
    x-cloak
>
    <article
        class="org-card {{ $isRoot ? 'org-card-root' : '' }}"
        x-bind:class="matchesById({{ $employeeId }}) && filtering()
            ? 'org-card-match'
            : ''"
    >
        <div class="org-card-top">
            @if (count($nodeChildren))
                <button
                    type="button"
                    class="org-toggle"
                    x-on:click="toggle({{ $employeeId }})"
                    x-bind:aria-expanded="filtering() || !collapsed.includes({{ $employeeId }})"
                    aria-label="Expandir o contraer subordinados de {{ $node['name'] }}"
                >
                    <span x-text="filtering() || !collapsed.includes({{ $employeeId }}) ? '−' : '+'"></span>
                </button>
            @else
                <span class="org-leaf">•</span>
            @endif

            <div class="org-card-info">
                <div class="org-person-name">
                    {{ $node['name'] }}
                </div>
                <div class="org-person-position">
                    {{ $node['position'] }}
                </div>

                <div class="org-card-meta">
                    <span class="org-area-pill">
                        {{ $node['department'] }}
                    </span>

                    @if (count($nodeChildren))
                        <span class="org-team-count">
                            {{ count($nodeChildren) }}
                            {{ count($nodeChildren) === 1 ? 'reporte directo' : 'reportes directos' }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </article>

    @if (count($nodeChildren))
        <ul
            class="org-children"
            x-show="filtering() || !collapsed.includes({{ $employeeId }})"
            x-cloak
        >
            @foreach ($nodeChildren as $childId)
                @include('filament.pages.partials.employee-org-node', [
                    'node' => $byId[$childId],
                    'children' => $children,
                    'byId' => $byId,
                ])
            @endforeach
        </ul>
    @endif
</li>
