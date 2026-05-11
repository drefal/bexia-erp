@foreach($itemsSteps as $step)

@php
$fields = $step['fields'] ?? [];
$itemsField = null;

foreach($fields as $f){
    if(($f['type'] ?? null) === 'items'){
        $itemsField = $f;
        break;
    }
}
@endphp

@if($itemsField)

@php
$itemsName = $itemsField['name'] ?? 'items';
$itemsLabel = $itemsField['label'] ?? 'Artículos';
$items = is_array($data[$itemsName] ?? null) ? $data[$itemsName] : [];
$total = 0.0;
@endphp

<div class="items-wrap">
    <div class="section-title">{{ $itemsLabel }}</div>

    <table class="items">
        <thead>
            <tr>
                <th class="head" style="width:60px;background:#b00020 !important;color:#fff !important;">Cantidad</th>
                <th class="head" style="background:#b00020 !important;color:#fff !important;">Descripción</th>
                <th class="head" style="width:162px;background:#b00020 !important;color:#fff !important;">Foto</th>
            </tr>
        </thead>

        <tbody>
            @forelse($items as $i)
                @php
                    $c = is_numeric($i['cantidad'] ?? null) ? (float) $i['cantidad'] : null;
                    $total += (float) ($c ?? 0);
                    $fotoPath = $extractPath($i['foto'] ?? null);
                    $fotoUri = $imgFileUri($fotoPath);
                @endphp

                <tr>
                    <td class="center">{{ $c !== null ? $fmt($c) : '' }}</td>
                    <td class="center">{{ $i['descripcion'] ?? '' }}</td>
                    <td class="img-cell">
                        @if($fotoUri)
                            <img class="img" src="{{ $fotoUri }}" alt="">
                        @else
                            <span class="img-empty">Sin foto</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="center">Sin artículos</td>
                </tr>
            @endforelse
        </tbody>

        @if(count($items))
            <tfoot>
                <tr class="total-row">
                    <th colspan="2" style="text-align:right;background:#b00020 !important;color:#fff !important;">Total de Artículos</th>
                    <td class="center" style="background:#b00020 !important;color:#fff !important;font-weight:800;">{{ $fmt($total) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>

@endif

@endforeach
