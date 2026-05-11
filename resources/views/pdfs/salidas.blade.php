<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Salida {{ $folioCorto ?? ($submission->folio ?? 'SALIDA') }}</title>

<style>
{!! file_get_contents(resource_path('views/pdfs/styles/salidas.css')) !!}
</style>
</head>

<body>

@php
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

$schema = is_array($form->schema ?? null) ? ($form->schema ?? []) : [];
$steps = $schema['steps'] ?? [];
$data = is_array($submission->data ?? null) ? ($submission->data ?? []) : [];

$fmt = function ($n) {
    return rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
};

$human = function ($value) use ($fmt) {
    if (is_null($value)) return '';
    if (is_bool($value)) return $value ? 'Sí' : 'No';
    if (is_numeric($value)) return $fmt($value);
    if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE);
    return (string) $value;
};

$normalize = function ($text) {
    $text = (string) $text;
    $text = mb_strtolower($text, 'UTF-8');

    $map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        'ñ' => 'n',
    ];

    return strtr($text, $map);
};

$publicAbsPath = function (?string $path): ?string {
    if (! $path) return null;

    $path = trim($path);
    $path = preg_replace('#^https?://[^/]+/#i', '', $path);
    $path = preg_replace('#^/?storage/#', '', $path);
    $path = ltrim($path, '/');

    if ($path === '') return null;

    $abs = Storage::disk('public')->path($path);

    return file_exists($abs) ? $abs : null;
};

$extractPath = function ($raw): ?string {
    if (is_string($raw) && trim($raw) !== '') {
        return $raw;
    }

    if (is_array($raw) && count($raw) > 0) {
        if (array_is_list($raw)) {
            $first = $raw[0] ?? null;
            return is_string($first) ? $first : null;
        }

        $first = reset($raw);
        return is_string($first) ? $first : null;
    }

    return null;
};

$imgFileUri = function (?string $path) use ($publicAbsPath): ?string {
    $abs = $publicAbsPath($path);
    return $abs ? ('file://' . $abs) : null;
};

$wmAbs = storage_path('app/public/watermarks/marca.png');
$wmB64 = null;

if (is_file($wmAbs) && is_readable($wmAbs)) {
    $wmB64 = 'data:image/png;base64,' . base64_encode(file_get_contents($wmAbs));
}

$findStep = function ($wantedTitle) use ($steps, $normalize) {
    $wanted = $normalize($wantedTitle);

    foreach ($steps as $st) {
        $title = $normalize($st['title'] ?? '');
        if ($title === $wanted) {
            return $st;
        }
    }

    foreach ($steps as $st) {
        $title = $normalize($st['title'] ?? '');
        if ($wanted !== '' && str_contains($title, $wanted)) {
            return $st;
        }
    }

    return null;
};

$findValueInStep = function ($stepTitle, array $nameCandidates = [], array $labelNeedles = []) use ($findStep, $data, $normalize) {
    $step = $findStep($stepTitle);

    if ($step) {
        $fields = $step['fields'] ?? [];

        foreach ($nameCandidates as $candidate) {
            foreach ($fields as $f) {
                if (($f['name'] ?? null) === $candidate) {
                    $name = $f['name'] ?? null;
                    return $name ? ($data[$name] ?? null) : null;
                }
            }
        }

        foreach ($fields as $f) {
            $label = $normalize($f['label'] ?? '');

            foreach ($labelNeedles as $needle) {
                if ($needle !== '' && str_contains($label, $normalize($needle))) {
                    $name = $f['name'] ?? null;
                    return $name ? ($data[$name] ?? null) : null;
                }
            }
        }
    }

    foreach ($nameCandidates as $candidate) {
        if (array_key_exists($candidate, $data)) {
            return $data[$candidate];
        }
    }

    foreach ($data as $key => $value) {
        $keyNorm = $normalize($key);

        foreach ($labelNeedles as $needle) {
            if ($needle !== '' && str_contains($keyNorm, $normalize($needle))) {
                return $value;
            }
        }
    }

    return null;
};

$fechaSalidaValor = $findValueInStep('Datos generales', ['fecha_salida', 'fecha'], ['fecha de salida']);
$enviaNombreValor = $findValueInStep('Datos generales', ['envia_nombre', 'nombre_quien_envia', 'nombre_envia'], ['nombre de quien envia']);
$almacenEnvioValor = $findValueInStep('Datos de envío', ['almacen_envio', 'almacen_de_envio'], ['almacen de envio']);
$almacenRecepcionValor = $findValueInStep('Datos de recepción', ['almacen_recepcion', 'almacen_de_recepcion'], ['almacen de recepcion']);
$recibeNombreValor = $findValueInStep('Participantes / autorización', ['recibe_nombre', 'nombre_quien_recibe', 'nombre_recibe'], ['nombre de quien recibe']);
$proyectoValor = $findValueInStep('Participantes / autorización', ['proyecto', 'project'], ['proyecto']);
$observacionesValor = $findValueInStep('Participantes / autorización', ['observacion', 'observaciones'], ['observacion', 'observaciones']);

$elaboraNombre = $enviaNombreValor;
$elaboraCorreo = $findValueInStep('Datos generales', ['envia_correo', 'correo_envia', 'email_envia'], ['correo electronico de quien envia']);
$recibeNombre = $recibeNombreValor;
$recibeCorreo = $findValueInStep('Participantes / autorización', ['recibe_correo', 'correo_recibe', 'email_recibe'], ['correo electronico de quien recibe']);
$autorizaNombre = $findValueInStep('Participantes / autorización', ['autoriza_nombre', 'nombre_quien_autoriza', 'nombre_autoriza'], ['nombre de quien autoriza']);
$autorizaCorreo = $findValueInStep('Participantes / autorización', ['autoriza_correo', 'correo_autoriza', 'email_autoriza'], ['correo electronico de quien autoriza']);

$fechaImpresion = Carbon::now('America/Mexico_City')->format('Y-m-d H:i');

$itemsSteps = [];

foreach ($steps as $st) {
    $fields = $st['fields'] ?? [];
    $hasItems = false;

    foreach ($fields as $f) {
        if (($f['type'] ?? null) === 'items') {
            $hasItems = true;
            break;
        }
    }

    if ($hasItems) {
        $itemsSteps[] = $st;
    }
}
@endphp

@include('pdfs.partials.salidas-header')
@include('pdfs.partials.salidas-info')
@include('pdfs.partials.salidas-items')
@include('pdfs.partials.salidas-signatures')

</body>
</html>
