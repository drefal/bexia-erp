<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<style>
@page { margin: 17mm 13mm; }
body { font-family: DejaVu Sans, sans-serif; color:#172b4d; font-size:9px; }
.header { border-bottom:3px solid #1d4ed8; padding-bottom:10px; margin-bottom:12px; }
h1 { font-size:20px; margin:0 0 4px; color:#123a78; }
.subtitle { color:#64748b; font-size:9px; }
.summary { margin:10px 0 16px; padding:9px; background:#eff6ff; }
.employee {
    border:1px solid #dbe3ef;
    border-left:3px solid #2563eb;
    border-radius:5px;
    padding:7px 9px;
    margin-bottom:6px;
    page-break-inside:avoid;
    clear:both;
}
.avatar {
    float:left; width:27px; height:27px;
    margin-right:10px; border-radius:15px;
}
.avatar-fallback {
    float:left; width:27px; height:27px;
    margin-right:10px; border-radius:15px;
    background:#dbeafe; color:#1e40af;
    text-align:center; line-height:27px;
    font-weight:bold;
}
.name { font-weight:bold; font-size:10px; }
.meta { color:#52647c; font-size:8px; margin-top:3px; }
.level { color:#1d4ed8; font-size:8px; }
</style>
</head>
<body>
<div class="header">
    <h1>Organigrama corporativo</h1>
    <div class="subtitle">Grupo Línea 7 · Estructura jerárquica de empleados</div>
</div>
<div class="summary">
    <strong>{{ $total }} empleados activos</strong>
    · Generado: {{ $generatedAt }}
    · Jerarquía según jefe directo en Bexia
</div>

@foreach ($rows as $row)
    @php
        $depth = min((int) ($row['depth'] ?? 0), 6);
        $parts = preg_split('/\s+/u', trim((string) $row['name']));
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
    @endphp

    <div class="employee" style="margin-left:{{ $depth * 10 }}px">
        @if (!empty($row['avatar_data']))
            <img class="avatar" src="{{ $row['avatar_data'] }}"/>
        @else
            <span class="avatar-fallback">{{ $initials ?: 'NA' }}</span>
        @endif
        <div class="name">{{ $row['name'] }}</div>
        <div class="meta">
            {{ $row['position'] }} · {{ $row['department'] }}
        </div>
        <div class="level">
            Nivel jerárquico {{ $depth + 1 }}
            @if (!empty($row['manager_name']))
                · Jefe: {{ $row['manager_name'] }}
            @endif
        </div>
    </div>
@endforeach
</body>
</html>
