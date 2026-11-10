{{-- BEXIA_V5836_POS_AREAS3I --}}
@php
    $salesAreas = $summary['sales_areas_display'] ?? [];
    $salesSections = $salesAreas['sections'] ?? [];
@endphp

@if(!empty($salesSections))
    {{-- BEXIA_V5836_POS_AREAS3N --}}
<style>
    .bexia-sales-areas {
        width: 100%;
        margin-top: 9px;
        margin-bottom: 9px;
    }

    .bexia-sales-areas .sales-area-block {
        page-break-inside: avoid;
        break-inside: avoid;
        margin: 8px 0;
    }

    .bexia-sales-areas .sales-area-title {
        font-weight: 700;
        margin: 6px 0 4px;
        page-break-after: avoid;
        break-after: avoid;
    }

    .bexia-sales-areas table {
        width: 100%;
        border-collapse: collapse;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .bexia-sales-areas tr {
        page-break-inside: avoid;
        break-inside: avoid;
    }
</style>
<div class="bexia-sales-areas">
        <div style="font-weight:700;margin:8px 0 5px">
            DESGLOSE DE VENTAS POR ÁREA
        </div>

        @foreach($salesSections as $section)
            <div class="sales-area-block">
            <div class="sales-area-title">
                {{ $section['name'] ?? 'Área' }}
            </div>

            <table style="width:100%;border-collapse:collapse">
                @foreach(($section['methods'] ?? []) as $method)
                    <tr>
                        <td style="padding:2px 0">
                            {{ $method['method'] ?? '' }}
                        </td>
                        <td style="text-align:right;padding:2px 0">
                            ${{ number_format(
                                (float) ($method['total'] ?? 0),
                                2
                            ) }}
                        </td>
                    </tr>
                @endforeach

                <tr>
                    <td style="font-weight:700;border-top:1px solid">
                        Total {{ $section['name'] ?? '' }}
                    </td>
                    <td style="text-align:right;font-weight:700;border-top:1px solid">
                        ${{ number_format(
                            (float) ($section['total'] ?? 0),
                            2
                        ) }}
                    </td>
                </tr>
            </table>
            </div>
        @endforeach
    </div>
@endif
