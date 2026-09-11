{{-- BEXIA_ATC_PICKUP_ORDER_PDF_ONE_PAGE_V5_83_4C4B3 --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">

    <style>
        @page {
            size: letter portrait;
            margin:
                7mm 8mm 7mm 8mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            color: #111827;
            font-family:
                DejaVu Sans,
                sans-serif;
            font-size: 8.3pt;
            line-height: 1.23;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .header {
            margin-bottom: 5px;
        }

        .header td {
            vertical-align: top;
        }

        .brand {
            font-size: 7.8pt;
            color: #6b7280;
            font-weight: bold;
        }

        h1 {
            font-size: 17pt;
            margin:
                2px 0 3px 0;
        }

        .folio {
            font-size: 9.3pt;
        }

        .status {
            display: inline-block;
            padding: 3px 6px;
            border:
                1px solid #f59e0b;
            border-radius: 8px;
            color: #92400e;
            background: #fffbeb;
            font-weight: bold;
            font-size: 7pt;
        }

        .qr {
            width: 104px;
            height: 104px;
        }

        .qr-label {
            font-size: 6.5pt;
            font-weight: bold;
            text-align: center;
            margin-top: 2px;
        }

        .url {
            font-size: 5.7pt;
            color: #374151;
            word-break: break-all;
            margin-top: 3px;
        }

        .section {
            margin-top: 5px;
        }

        .section-title {
            font-size: 8.2pt;
            font-weight: bold;
            padding:
                3px 5px;
            background: #f3f4f6;
            border:
                1px solid #d1d5db;
            border-bottom: 0;
        }

        .data td {
            border:
                1px solid #d1d5db;
            padding:
                3px 5px;
            vertical-align: top;
        }

        .label {
            display: block;
            font-size: 5.8pt;
            color: #6b7280;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 1px;
        }

        .value {
            font-size: 7.5pt;
        }

        .two {
            width: 50%;
        }

        .evidence-table td {
            width: 50%;
            padding: 3px;
            vertical-align: top;
        }

        .evidence-box {
            border:
                1px solid #d1d5db;
            padding: 3px;
        }

        .evidence-img {
            max-width: 100%;
            height: 58px;
            object-fit: contain;
        }

        .evidence-caption {
            margin-top: 1px;
            font-size: 5.8pt;
            color: #6b7280;
        }

        .footer-box {
            margin-top: 5px;
            padding: 4px 5px;
            border:
                1px solid #bfdbfe;
            background: #eff6ff;
            font-size: 6.7pt;
        }

        .footer {
            margin-top: 4px;
            padding-top: 3px;
            border-top:
                1px solid #d1d5db;
            font-size: 5.8pt;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>

<body>

<table class="header">
    <tr>

        <td style="width:72%;">

            <div class="brand">
                BEXIA · ATENCIÓN DE SERVICIO
            </div>

            <h1>
                ORDEN DE RECOLECCIÓN
            </h1>

            <div class="folio">
                Ticket:
                <strong>
                    {{
                        $snapshot['folio']
                        ?? $case->folio
                    }}
                </strong>
            </div>

            <div style="margin-top:4px;">
                <span class="status">
                    RECOLECCIÓN PENDIENTE
                </span>
            </div>

            <div
                style="
                    margin-top:5px;
                    font-size:7pt;
                    color:#4b5563;
                "
            >
                Escanee el QR para consultar
                el expediente y registrar la recolección.
            </div>

        </td>

        <td
            style="
                width:28%;
                text-align:right;
            "
        >
            <img
                class="qr"
                src="{{ $qrDataUri }}"
                alt="QR"
            >

            <div class="qr-label">
                ABRIR ORDEN
            </div>
        </td>

    </tr>
</table>

<div class="url">
    {{ $publicUrl }}
</div>

<div class="section">

    <div class="section-title">
        CLIENTE / RECOLECCIÓN
    </div>

    <table class="data">
        <tr>

            <td class="two">
                <span class="label">
                    Cliente
                </span>
                <span class="value">
                    {{
                        $snapshot['contact_name']
                        ?: 'No capturado'
                    }}
                </span>
            </td>

            <td class="two">
                <span class="label">
                    Teléfono
                </span>
                <span class="value">
                    {{
                        $snapshot['contact_phone']
                        ?: 'No capturado'
                    }}
                </span>
            </td>

        </tr>

        <tr>

            <td>
                <span class="label">
                    Lugar previsto
                </span>
                <span class="value">
                    {{
                        $snapshot['pickup_place']
                        ?: 'No capturado'
                    }}
                </span>
            </td>

            <td>
                <span class="label">
                    Responsable
                </span>
                <span class="value">
                    {{
                        $snapshot['responsible']
                        ?: 'Responsable asignado'
                    }}
                </span>
            </td>

        </tr>
    </table>

</div>

<div class="section">

    <div class="section-title">
        SOLICITUD DE SERVICIO
    </div>

    <table class="data">

        <tr>

            <td style="width:35%;">
                <span class="label">
                    Asunto
                </span>
                <span class="value">
                    {{
                        \Illuminate\Support\Str::limit(
                            (string) (
                                $snapshot['subject']
                                ?? ''
                            ),
                            110
                        )
                    }}
                </span>
            </td>

            <td style="width:20%;">
                <span class="label">
                    Prioridad
                </span>
                <span class="value">
                    {{
                        ucfirst(
                            (string) (
                                $snapshot['priority']
                                ?? ''
                            )
                        )
                    }}
                </span>
            </td>

            <td style="width:45%;">
                <span class="label">
                    Fecha ticket
                </span>
                <span class="value">
                    {{
                        $snapshot['created_at']
                        ?: '-'
                    }}
                </span>
            </td>

        </tr>

        <tr>
            <td colspan="3">

                <span class="label">
                    Problema reportado
                </span>

                <span class="value">
                    {{
                        \Illuminate\Support\Str::limit(
                            (string) (
                                $snapshot['description']
                                ?? ''
                            ),
                            360
                        )
                    }}
                </span>

            </td>
        </tr>

    </table>

</div>

<div class="section">

    <div class="section-title">
        EQUIPO REPORTADO
    </div>

    <table class="data">

        <tr>

            <td style="width:40%;">
                <span class="label">
                    Producto / modelo
                </span>
                <span class="value">
                    {{
                        $snapshot['product_name']
                        ?: 'No capturado'
                    }}
                </span>
            </td>

            <td style="width:35%;">
                <span class="label">
                    Número de serie
                </span>
                <span class="value">
                    {{
                        $snapshot['serial_number']
                        ?: 'No capturado'
                    }}
                </span>
            </td>

            <td style="width:25%;">
                <span class="label">
                    Lote
                </span>
                <span class="value">
                    {{
                        $snapshot['lot_number']
                        ?: 'No capturado'
                    }}
                </span>
            </td>

        </tr>

    </table>

</div>

<div class="section">

    <div class="section-title">
        ATENCIÓN PREPARADA
    </div>

    <table class="data">

        <tr>

            <td style="width:35%;">
                <span class="label">
                    Modalidad
                </span>
                <span class="value">
                    {{
                        $snapshot[
                            'arrival_method_label'
                        ]
                        ?: 'Recolección por chofer'
                    }}
                </span>
            </td>

            <td style="width:30%;">
                <span class="label">
                    Fecha prevista
                </span>
                <span class="value">
                    {{
                        $snapshot[
                            'planned_reception_at'
                        ]
                        ?: 'Sin fecha definida'
                    }}
                </span>
            </td>

            <td style="width:35%;">
                <span class="label">
                    Medio de contacto
                </span>
                <span class="value">
                    {{
                        ucfirst(
                            (string) (
                                $snapshot['channel']
                                ?? ''
                            )
                        )
                    }}
                </span>
            </td>

        </tr>

        <tr>
            <td colspan="3">

                <span class="label">
                    Indicaciones
                </span>

                <span class="value">
                    {{
                        \Illuminate\Support\Str::limit(
                            (string) (
                                $snapshot[
                                    'attention_notes'
                                ]
                                ?? ''
                            ),
                            260
                        )
                    }}
                </span>

            </td>
        </tr>

    </table>

</div>

@if (! empty($pdfEvidence))

    <div class="section">

        <div class="section-title">
            EVIDENCIAS
        </div>

        <table class="evidence-table">
            <tr>

                @foreach ($pdfEvidence as $evidence)

                    <td>

                        <div class="evidence-box">

                            <div
                                style="
                                    text-align:center;
                                "
                            >
                                <img
                                    class="evidence-img"
                                    src="{{ $evidence['data_uri'] }}"
                                    alt="Evidencia"
                                >
                            </div>

                            <div class="evidence-caption">
                                {{
                                    \Illuminate\Support\Str::limit(
                                        $evidence['name'],
                                        55
                                    )
                                }}
                            </div>

                        </div>

                    </td>

                @endforeach

                @if (count($pdfEvidence) === 1)
                    <td></td>
                @endif

            </tr>
        </table>

        @if (
            $evidenceCount
            > count($pdfEvidence)
        )
            <div
                style="
                    font-size:5.8pt;
                    color:#6b7280;
                    margin-top:2px;
                "
            >
                Existen
                {{
                    $evidenceCount
                    - count($pdfEvidence)
                }}
                evidencia(s) adicional(es).
                Consulte el expediente mediante el QR.
            </div>
        @endif

    </div>

@endif

<div class="footer-box">

    <strong>
        INSTRUCCIÓN PARA EL CHOFER:
    </strong>

    Abra el QR o la liga para registrar
    producto/serie observados, persona que entrega,
    condición, accesorios, fotografías y observaciones.

    <strong>
        La firma del cliente se captura al final
        del formulario de recolección.
    </strong>

</div>

<div class="footer">
    Orden generada:
    {{
        $pickup['generated_at']
        ?? '-'
    }}
    · Documento resumido a una sola hoja.
    El expediente digital completo está disponible mediante el QR.
</div>

</body>
</html>
