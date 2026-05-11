@php
    $pdfTitle = $form->name ?? 'Salidas Materiales Varios GL7';
    $folioPdf = $folioDisplay ?? $submission->folio ?? ('SAL-' . ($submission->id ?? ''));
    $statusPdf = strtoupper((string) ($submission->status ?? 'ENVIADA'));
    $fechaPdf = isset($submittedAt) && $submittedAt ? $submittedAt->format('Y-m-d H:i') : now()->format('Y-m-d H:i');
    $showGeneral = ($human($proyectoValor) !== '') || ($human($observacionesValor) !== '');
@endphp

@if($wmB64)
<img src="{{ $wmB64 }}" class="watermark" alt="">
@endif

<div class="header">
    <table class="header-layout">
        <tr>
            <td class="header-left">
                <p class="title">{{ $pdfTitle }}</p>

                <div class="meta-line">
                    <span class="meta-label">Folio:</span>
                    <span class="badge red">{{ $folioPdf }}</span>

                    <span class="meta-sep">|</span>

                    <span class="meta-label">Estatus:</span>
                    <span class="badge">{{ $statusPdf }}</span>

                    <span class="meta-sep">|</span>

                    <span class="meta-label">Fecha:</span>
                    <span class="badge">{{ $fechaPdf }}</span>
                </div>
            </td>

            <td class="header-right">
                @if($showGeneral)
                    <table class="header-general">
                        <thead>
                            <tr>
                                <th colspan="2">Información general</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th>Proyecto</th>
                                <td>{{ $human($proyectoValor) }}</td>
                            </tr>
                            <tr>
                                <th>Observaciones</th>
                                <td>{{ $human($observacionesValor) }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif
            </td>
        </tr>
    </table>
</div>
