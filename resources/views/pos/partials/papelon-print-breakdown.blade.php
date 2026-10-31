{{-- BEXIA_V5836_PAPELON_PRINT_SPLIT_FIX1 --}}
@php
    $printBnQty = 0.0;
    $printColorQty = 0.0;
    $printBnAmount = 0.0;
    $printColorAmount = 0.0;

    $printingProducts = $productsBySection['IMPRESIÓN Y COPIAS'] ?? [];

    foreach ($printingProducts as $printProduct) {
        $path = mb_strtolower(
            (string) ($printProduct['path'] ?? ''),
            'UTF-8'
        );

        $reference = trim(
            (string) ($printProduct['reference'] ?? '')
        );

        $isBn = str_contains($path, 'impresión b&n')
            || str_contains($path, 'copia b&n');

        $isColor = str_contains($path, 'impresión color')
            || str_contains($path, 'copia color');

        // Legacy: Copia Oficio B&N in parent category.
        if (!$isBn && !$isColor && $reference === '0416') {
            $isBn = true;
        }

        $qty = (float) ($printProduct['qty'] ?? 0);
        $amount = (float) ($printProduct['total'] ?? 0);

        if ($isBn) {
            $printBnQty += $qty;
            $printBnAmount += $amount;
        } elseif ($isColor) {
            $printColorQty += $qty;
            $printColorAmount += $amount;
        }
    }

    $printQty = $printBnQty + $printColorQty;
    $printAmount = $printBnAmount + $printColorAmount;

    $formatPrintQty = static function ($value) {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    };
@endphp
<tr>
    <td colspan="2" style="font-weight:800;padding-top:6px;">
        DESGLOSE IMPRESIONES (VENTAS NETAS LIQUIDADAS)
    </td>
</tr>
<tr>
    <td>
        Impresiones B/N
        ({{ $formatPrintQty($printBnQty) }})
    </td>
    <td class="right">{{ $money($printBnAmount) }}</td>
</tr>
<tr>
    <td>
        Impresiones a color
        ({{ $formatPrintQty($printColorQty) }})
    </td>
    <td class="right">{{ $money($printColorAmount) }}</td>
</tr>
<tr>
    <td style="font-weight:800;">
        Total impresiones y copias
        ({{ $formatPrintQty($printQty) }})
    </td>
    <td class="right" style="font-weight:800;">
        {{ $money($printAmount) }}
    </td>
</tr>
