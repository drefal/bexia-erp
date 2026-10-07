<!doctype html>
<html lang="es">

<body
    style="
        font-family:Arial,sans-serif;
        color:#0f172a;
        line-height:1.45;
    "
>

<h2>
    Cierre de Punto de Venta
</h2>

<p>
    Se adjuntan el
    <strong>corte de caja</strong>
    y el
    <strong>reporte de cierre</strong>.
</p>

<table
    cellpadding="5"
    cellspacing="0"
    style="border-collapse:collapse"
>
    <tr>
        <td>
            <strong>Empresa</strong>
        </td>

        <td>
            {{
                $companyName !== ''
                    ? $companyName
                    : '—'
            }}
        </td>
    </tr>

    <tr>
        <td>
            <strong>PDV</strong>
        </td>

        <td>
            {{
                $posName !== ''
                    ? $posName
                    : '—'
            }}
        </td>
    </tr>

    <tr>
        <td>
            <strong>Sesión</strong>
        </td>

        <td>
            {{ $sessionNumber }}
        </td>
    </tr>

    <tr>
        <td>
            <strong>Cierre</strong>
        </td>

        <td>
            {{ $closedAt }}
        </td>
    </tr>
</table>

@php
    $totals =
        $summary[
            'totals'
        ]
        ?? [];
@endphp

<p>
    <strong>
        Cobrado en sesión:
    </strong>

    ${{
        number_format(
            (float) (
                $totals[
                    'payments_total'
                ]
                ?? $totals[
                    'collected_total'
                ]
                ?? 0
            ),
            2
        )
    }}
</p>

<p
    style="
        font-size:12px;
        color:#64748b;
    "
>
    Mensaje generado automáticamente por Bexia ERP.
</p>

</body>
</html>
