<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">

    <title>
        Constancia de solución y cierre -
        {{ $case->folio ?? ('Ticket #' . $case->id) }}
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f3f4f6;
            color: #111827;
            font-family: Arial, sans-serif;
        }

        .toolbar {
            width: 216mm;
            margin: 16px auto 0;
            text-align: right;
        }

        .button {
            border: 0;
            border-radius: 6px;
            padding: 9px 14px;
            background: #111827;
            color: #ffffff;
            cursor: pointer;
            font-size: 13px;
        }

        .page {
            width: 216mm;
            min-height: 279mm;
            margin: 16px auto;
            padding: 17mm;
            background: #ffffff;
            border: 1px solid #e5e7eb;
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding-bottom: 14px;
            margin-bottom: 18px;
            border-bottom: 2px solid #111827;
        }

        .logo {
            max-width: 150px;
            max-height: 70px;
            object-fit: contain;
        }

        h1 {
            margin: 0 0 5px;
            font-size: 21px;
        }

        .subtitle {
            font-size: 12px;
            color: #4b5563;
        }

        .meta {
            font-size: 11px;
            color: #4b5563;
            text-align: right;
            line-height: 1.5;
        }

        .section {
            margin-top: 18px;
        }

        .section h2 {
            margin: 0 0 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid #d1d5db;
            font-size: 15px;
        }

        .grid {
            display: grid;
            grid-template-columns: 48mm 1fr;
            border: 1px solid #e5e7eb;
        }

        .label,
        .value {
            min-height: 31px;
            padding: 7px 9px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 12px;
        }

        .label {
            border-right: 1px solid #e5e7eb;
            background: #f9fafb;
            font-weight: 700;
        }

        .value {
            white-space: pre-wrap;
        }

        .text-box {
            padding: 11px;
            border: 1px solid #e5e7eb;
            font-size: 12px;
            line-height: 1.55;
            white-space: pre-wrap;
        }

        .approved {
            margin-top: 9px;
            padding: 9px 11px;
            border: 1px solid #d1d5db;
            background: #f9fafb;
            font-size: 11px;
        }

        .footer {
            margin-top: 24px;
            padding-top: 9px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 10px;
            line-height: 1.45;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .toolbar {
                display: none;
            }

            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 12mm;
                border: 0;
            }
        }
    </style>
</head>

<body>

<div class="toolbar">
    <button
        class="button"
        type="button"
        onclick="window.print()"
    >
        Imprimir / Guardar PDF
    </button>
</div>

<div class="page">

    <div class="header">
        <div>
            @if ($logoUrl)
                <img
                    src="{{ $logoUrl }}"
                    alt="Logo"
                    class="logo"
                >
            @endif

            <h1>
                Constancia de solución y cierre
            </h1>

            <div class="subtitle">
                Atención y Servicio · Atención directa sin reparación
            </div>
        </div>

        <div class="meta">
            <strong>
                {{ $company->name ?? $company->business_name ?? 'Bexia ERP' }}
            </strong>
            <br>

            Ticket:
            {{ $case->folio ?? ('#' . $case->id) }}
            <br>

            Impreso:
            {{ $printedAt }}
        </div>
    </div>

    <div class="section">
        <h2>Datos del servicio</h2>

        <div class="grid">
            <div class="label">Folio</div>
            <div class="value">
                {{ $case->folio ?? ('#' . $case->id) }}
            </div>

            <div class="label">Cliente</div>
            <div class="value">
                {{
                    $customer->name
                    ?? $customer->business_name
                    ?? $customer->legal_name
                    ?? 'Sin nombre'
                }}
            </div>

            <div class="label">Asunto</div>
            <div class="value">
                {{ $case->subject ?? 'Atención de servicio' }}
            </div>

            <div class="label">Producto / equipo</div>
            <div class="value">
                {{
                    $case->product_name
                    ?? $product->name
                    ?? 'No especificado'
                }}
            </div>

            <div class="label">Técnico asignado</div>
            <div class="value">
                {{
                    $technician->name
                    ?? $technician->full_name
                    ?? 'No especificado'
                }}
            </div>

            <div class="label">Estado</div>
            <div class="value">Cerrado</div>
        </div>
    </div>

    <div class="section">
        <h2>Solución proporcionada</h2>

        <div class="text-box">
            {{ $response->notes ?? 'Sin detalle de respuesta.' }}
        </div>

        <div class="approved">
            Registrada por:
            <strong>
                {{
                    $responseUser->name
                    ?? 'Usuario #' . ($response->performed_by ?? '')
                }}
            </strong>

            · Fecha:
            {{ $response->performed_at ?? '' }}
        </div>
    </div>

    <div class="section">
        <h2>Validación de la solución</h2>

        <div class="grid">
            <div class="label">Validado por</div>
            <div class="value">
                {{
                    $validatorUser->name
                    ?? 'Usuario #' . (
                        $validationValues['validated_by']
                        ?? $validation->performed_by
                        ?? ''
                    )
                }}
            </div>

            <div class="label">Fecha validación</div>
            <div class="value">
                {{ $validation->performed_at ?? '' }}
            </div>

            <div class="label">Tipo</div>
            <div class="value">
                {{
                    ($validationValues['validation_mode'] ?? '') === 'automatic'
                        ? 'Validación automática por responsable autorizado'
                        : 'Validación manual'
                }}
            </div>

            <div class="label">Observaciones</div>
            <div class="value">
                {{ $validation->notes ?? 'Sin observaciones adicionales.' }}
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Cierre del ticket</h2>

        <div class="grid">
            <div class="label">Tipo de resolución</div>
            <div class="value">
                {{ $resolutionTypeLabel }}
            </div>

            <div class="label">Notas de cierre</div>
            <div class="value">
                {{ $case->resolution_notes ?? 'Sin notas adicionales.' }}
            </div>

            <div class="label">Cerrado por</div>
            <div class="value">
                {{
                    $closerUser->name
                    ?? 'Usuario #' . ($case->closed_by ?? '')
                }}
            </div>

            <div class="label">Fecha de cierre</div>
            <div class="value">
                {{ $case->closed_at ?? '' }}
            </div>
        </div>
    </div>

    <div class="footer">
        Documento generado desde Bexia ERP.
        Esta constancia refleja la solución registrada,
        su validación y el cierre del ticket.

        Las evidencias internas que no estén marcadas para
        cliente no se incluyen en esta copia.
    </div>

</div>

</body>
</html>
