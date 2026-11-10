@php
    $employeeId = (int) $node['id'];
    $nodeChildren = $children[$employeeId] ?? [];
    $isRoot = empty($node['ancestors'] ?? []);
    $parts = preg_split('/\s+/u', trim((string) $node['name']));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    $initials = $initials ?: 'NA';
@endphp

<li class="org-node {{ $isRoot ? 'org-node-root' : '' }}"
    x-show="visible({{ $employeeId }})" x-cloak>

    <article class="org-card {{ $isRoot ? 'org-card-root' : '' }}"
        x-bind:class="matchesById({{ $employeeId }}) && filtering()
            ? 'org-card-match' : ''">

        <div class="org-card-main">
            <div class="org-avatar">
                @if (!empty($node['avatar_url']))
                    <img
                        src="{{ $node['avatar_url'] }}"
                        alt="Foto de {{ $node['name'] }}"
                        loading="lazy"
                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"
                        style="width:100%;height:100%;object-fit:cover;border-radius:50%"
                    />
                    <span style="display:none;width:100%;height:100%;align-items:center;justify-content:center">
                        {{ $initials }}
                    </span>
                @else
                    <span>{{ $initials }}</span>
                @endif
            </div>

            <div class="org-card-body">
                <div class="org-card-row">
                    <div>
                        <div class="org-person-name">{{ $node['name'] }}</div>
                        <div class="org-person-position">{{ $node['position'] }}</div>
                    </div>

                    @if (count($nodeChildren))
                        <button type="button" class="org-toggle"
                            x-on:click="toggle({{ $employeeId }})"
                            x-bind:aria-expanded="filtering() || !collapsed.includes({{ $employeeId }})"
                            aria-label="Expandir o contraer equipo de {{ $node['name'] }}">
                            <span x-text="filtering() || !collapsed.includes({{ $employeeId }}) ? '−' : '+'"></span>
                        </button>
                    @endif
                </div>

                <div class="org-meta">
                    <span class="org-badge-area">{{ $node['department'] }}</span>
                    @if (count($nodeChildren))
                        <span class="org-badge-count">
                            {{ count($nodeChildren) }} reportes directos
                        </span>
                    @endif
                </div>
            </div>
        </div>
        @if (count($nodeChildren))
        <button type="button"
            class="orgx-focus-open"
            style="margin:5px 0 12px"
            x-on:click.stop="focusTeam({{ $employeeId }})">
            Ver equipo en 3 columnas
        </button>
    @endif

</article>

    @if (count($nodeChildren))
        <ul class="org-children"
            x-show="filtering() || !collapsed.includes({{ $employeeId }})"
            x-cloak>
            <li class="org-team-label">
                <span class="org-team-label-name">
                    Equipo de {{ $node['name'] }}
                </span>
                <span class="org-team-label-count">
                    {{ count($nodeChildren) }}
                    {{ count($nodeChildren) === 1 ? 'colaborador directo' : 'colaboradores directos' }}
                </span>
            </li>
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
