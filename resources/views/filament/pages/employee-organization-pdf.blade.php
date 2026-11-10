<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
@page { margin: 12mm 11mm; }
body {
    font-family: DejaVu Sans, sans-serif;
    color: #172b4d;
    font-size: 9px;
}
.header {
    border-bottom: 3px solid #1d4ed8;
    padding-bottom: 9px;
    margin-bottom: 12px;
}
h1 {
    font-size: 18px;
    color: #123a78;
    margin: 0 0 4px;
}
.subtitle { color: #64748b; font-size: 9px; }
.summary {
    padding: 8px;
    background: #eff6ff;
    margin-bottom: 14px;
}
.team {
    margin-bottom: 15px;
    page-break-inside: auto;
}
.team-heading {
    background: #eaf2ff;
    padding: 8px 10px;
    border-left: 4px solid #2563eb;
    margin-bottom: 8px;
    page-break-inside: avoid;
}
.team-title { font-size: 12px; font-weight: bold; }
.team-meta {
    color: #52647c;
    font-size: 8px;
    margin-top: 3px;
}
.cards {
    width: 100%;
    table-layout: fixed;
    border-collapse: separate;
    border-spacing: 5px 6px;
}
.cards td {
    width: 33.33%;
    vertical-align: top;
}
.card {
    border: 1px solid #dbe3ef;
    border-top: 3px solid #2563eb;
    padding: 8px;
    page-break-inside: avoid;
    min-height: 56px;
}
.avatar {
    float: left;
    width: 26px;
    height: 26px;
    margin-right: 8px;
}
.initials {
    float: left;
    width: 26px;
    height: 26px;
    line-height: 26px;
    text-align: center;
    margin-right: 8px;
    background: #dbeafe;
    color: #1e40af;
    font-weight: bold;
}
.name {
    font-weight: bold;
    font-size: 9px;
}
.meta {
    font-size: 8px;
    color: #52647c;
    margin-top: 4px;
}
.subteam {
    border-top: 1px solid #dbe3ef;
    padding-top: 5px;
    margin-top: 7px;
    font-size: 7px;
    color: #344968;
}
.subperson {
    padding: 2px 0;
}
.empty { color: #64748b; font-style: italic; }
.level {
    color: #2563eb;
    font-size: 8px;
    margin-bottom: 3px;
}
</style>
</head>
<body>
<div class="header">
    <h1>Organigrama corporativo</h1>
    <div class="subtitle">
        Grupo Línea 7 · Vista jerárquica según pantalla
    </div>
</div>

<div class="summary">
    <strong>{{ $total }} empleados incluidos en el PDF</strong>
    · {{ $focusMode ? 'Vista enfocada' : 'Vista general' }}
    · Generado: {{ $generatedAt }}
</div>

@foreach ($sections as $section)
    @php
        $manager = $section['manager'];
        $direct = $section['children'];
    @endphp

    <div class="team">
        <div class="team-heading">
            <div class="level">
                Nivel {{ $section['depth'] + 1 }}
                · {{ count($direct) }} colaboradores directos visibles
            </div>
            <div class="team-title">
                {{ $manager['name'] }}
            </div>
            <div class="team-meta">
                {{ $manager['position'] }} ·
                {{ $manager['department'] }}
                @if (!empty($manager['manager_name']))
                    · Jefe: {{ $manager['manager_name'] }}
                @endif
            </div>
        </div>

        @if (count($direct))
            <table class="cards">
                <tbody>
                @foreach (array_chunk($direct, 3) as $group)
                    <tr>
                    @foreach ($group as $person)
                        @php
                            $parts = preg_split(
                                '/\s+/u',
                                trim((string) $person['name'])
                            );
                            $initials = '';
                            foreach (array_slice($parts, 0, 2) as $part) {
                                $initials .= mb_strtoupper(
                                    mb_substr($part, 0, 1)
                                );
                            }
                            $subteam = $section['subteams'][
                                (int) $person['id']
                            ] ?? [];
                        @endphp
                        <td>
                            <div class="card">
                                @if (!empty($person['avatar_data']))
                                    <img class="avatar"
                                        src="{{ $person['avatar_data'] }}">
                                @else
                                    <span class="initials">
                                        {{ $initials ?: 'NA' }}
                                    </span>
                                @endif
                                <div class="name">
                                    {{ $person['name'] }}
                                </div>
                                <div class="meta">
                                    {{ $person['position'] }}
                                </div>
                                <div class="meta">
                                    {{ $person['department'] }}
                                </div>

                                @if ($focusMode && count($subteam))
                                    <div class="subteam">
                                        <strong>
                                            Equipo:
                                            {{ count($subteam) }}
                                        </strong>
                                        @foreach ($subteam as $sub)
                                            <div class="subperson">
                                                · {{ $sub['name'] }}
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </td>
                    @endforeach

                    @for ($i = count($group); $i < 3; $i++)
                        <td></td>
                    @endfor
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <div class="empty">
                No hay colaboradores directos desplegados.
            </div>
        @endif
    </div>
@endforeach
</body>
</html>
