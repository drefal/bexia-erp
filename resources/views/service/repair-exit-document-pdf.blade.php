<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">

    <title>
        {{ $document['folio'] ?? 'SAL-ATC' }}
    </title>

    <style>
        @page {
            margin: 20px 28px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.25px;
            line-height: 1.28;
            color: #111827;
        }

        .header {
            border-bottom: 1.5px solid #111827;
            padding-bottom: 8px;
            margin-bottom: 11px;
        }

        .title {
            font-size: 17px;
            font-weight: bold;
            margin: 0 0 3px 0;
        }

        .subtitle {
            font-size: 9px;
            color: #4b5563;
        }

        .folio {
            margin-top: 5px;
            font-size: 11.5px;
            font-weight: bold;
        }

        .section {
            margin-top: 9px;
        }

        .section-title {
            font-size: 10px;
            font-weight: bold;
            background: #f3f4f6;
            padding: 4px 6px;
            border: 1px solid #d1d5db;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            border: 1px solid #d1d5db;
            padding: 4.5px 6px;
            vertical-align: middle;
            font-size: 9px;
        }

        .label {
            width: 29%;
            font-weight: bold;
            background: #fafafa;
        }

        .notice {
            margin-top: 11px;
            border: 1px solid #111827;
            padding: 7px 8px;
            font-size: 8.25px;
            line-height: 1.35;
        }

        .signatures {
            margin-top: 16px;
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .signatures td {
            border: 0;
            width: 33.33%;
            padding: 0 8px;
            text-align: center;
            vertical-align: bottom;
        }

        .signature-space {
            height: 54px;
            border-bottom: 1px solid #111827;
        }

        .signature-label {
            padding-top: 4px;
            font-size: 8.5px;
            font-weight: bold;
        }

        .signature-name {
            margin-top: 2px;
            min-height: 12px;
            font-size: 7.75px;
            color: #4b5563;
        }

        .footer {
            margin-top: 14px;
            font-size: 7.5px;
            color: #4b5563;
            border-top: 1px solid #d1d5db;
            padding-top: 5px;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="title">
            SALIDA DE EQUIPO ATC
        </div>

        <div class="subtitle">
            Documento de autorización de salida física /
            devolución de equipo bajo custodia
        </div>

        <div class="folio">
            Folio:
            {{ $document['folio'] ?? '-' }}
        </div>
    </div>

    <div class="section">
        <div class="section-title">
            Datos del expediente
        </div>

        <table>
            <tr>
                <td class="label">
                    Empresa
                </td>

                <td>
                    {{
                        $company->name
                        ?? (
                            'Empresa #'
                            . (
                                $repair->company_id
                                ?? '-'
                            )
                        )
                    }}
                </td>
            </tr>

            <tr>
                <td class="label">
                    Ticket ATC
                </td>

                <td>
                    {{
                        $document[
                            'service_case_folio'
                        ]
                        ?? (
                            $case->folio
                            ?? '-'
                        )
                    }}
                </td>
            </tr>

            <tr>
                <td class="label">
                    Orden de reparación
                </td>

                <td>
                    {{
                        $document[
                            'repair_folio'
                        ]
                        ?? (
                            $repair->folio
                            ?? '-'
                        )
                    }}
                </td>
            </tr>

            <tr>
                <td class="label">
                    Cliente
                </td>

                <td>
                    {{
                        $document[
                            'customer_name'
                        ]
                        ?? '-'
                    }}
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">
            Equipo
        </div>

        <table>
            <tr>
                <td class="label">
                    Producto / modelo
                </td>

                <td>
                    {{
                        $document[
                            'product_name'
                        ]
                        ?? (
                            $repair->product_name
                            ?? '-'
                        )
                    }}
                </td>
            </tr>

            <tr>
                <td class="label">
                    Número de serie
                </td>

                <td>
                    {{
                        $document[
                            'serial_number'
                        ]
                        ?? (
                            $repair->serial_number
                            ?? '-'
                        )
                    }}
                </td>
            </tr>

            <tr>
                <td class="label">
                    Lote
                </td>

                <td>
                    {{
                        $document[
                            'lot_number'
                        ]
                        ?? (
                            $repair->lot_number
                            ?? '-'
                        )
                    }}
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">
            Movimiento físico de custodia
        </div>

        <table>
            <tr>
                <td class="label">
                    Ubicación de salida
                </td>

                <td>
                    {{
                        $document[
                            'origin_location_label'
                        ]
                        ?? '-'
                    }}
                </td>
            </tr>

            <tr>
                <td class="label">
                    Destino
                </td>

                <td>
                    {{
                        $document[
                            'destination_label'
                        ]
                        ?? 'Cliente / exterior'
                    }}
                </td>
            </tr>

            <tr>
                <td class="label">
                    Motivo
                </td>

                <td>
                    {{
                        $document[
                            'reason'
                        ]
                        ?? '-'
                    }}
                </td>
            </tr>

            <tr>
                <td class="label">
                    Observaciones
                </td>

                <td>
                    {{
                        $document[
                            'notes'
                        ]
                        ?? 'Sin observaciones adicionales.'
                    }}
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">
            Autorización
        </div>

        <table>
            <tr>
                <td class="label">
                    Autorizado por
                </td>

                <td>
                    {{
                        $document[
                            'authorized_by_name'
                        ]
                        ?? '-'
                    }}
                </td>
            </tr>

            <tr>
                <td class="label">
                    Fecha de autorización
                </td>

                <td>
                    {{
                        $document[
                            'authorized_at'
                        ]
                        ?? '-'
                    }}
                </td>
            </tr>
        </table>
    </div>

    <div class="notice">
        <strong>IMPORTANTE:</strong>

        Este documento ampara la salida física de un equipo
        propiedad del cliente que se encontraba bajo custodia
        del área de servicio.

        No representa una venta, entrega comercial, consumo,
        baja de inventario ni movimiento contable.

        La salida y la entrega deberán quedar respaldadas
        con las firmas correspondientes de este documento
        y con el registro del expediente de servicio.
    </div>

    <!--
        BEXIA_ATC_EXIT_DOCUMENT_SIGNATURES_V5_83_4C5G11
    -->
    <table class="signatures">
        <tr>
            <td>
                <div class="signature-space"></div>

                <div class="signature-label">
                    Firma de salida
                </div>

                <div class="signature-name">
                    {{
                        $document[
                            'authorized_by_name'
                        ]
                        ?? ''
                    }}
                </div>
            </td>

            <td>
                <div class="signature-space"></div>

                <div class="signature-label">
                    Firma de seguridad
                </div>

                <div class="signature-name">
                    Nombre y firma
                </div>
            </td>

            <td>
                <div class="signature-space"></div>

                <div class="signature-label">
                    Firma del cliente al recibir
                </div>

                <div class="signature-name">
                    {{
                        $document[
                            'customer_name'
                        ]
                        ?? 'Nombre del cliente'
                    }}
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Documento generado desde Atención a Clientes / Servicio.
        Folio {{ $document['folio'] ?? '-' }}.
        Inventario afectado: NO.
    </div>
</body>
</html>
