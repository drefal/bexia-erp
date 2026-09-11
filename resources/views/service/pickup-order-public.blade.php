{{-- BEXIA_ATC_PICKUP_ORDER_VIEW_V5_83_4C4B1 --}}
{{-- BEXIA_ATC_PICKUP_SHARE_UI_V5_83_4C4B3 --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="robots"
        content="noindex,nofollow,noarchive"
    >

    <title>
        Orden de recolección {{ $snapshot['folio'] ?? '' }}
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f3f4f6;
            color: #111827;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
        }

        .page {
            width:
                min(
                    980px,
                    calc(100% - 24px)
                );
            margin: 24px auto;
            background: #fff;
            border-radius: 18px;
            box-shadow:
                0 12px 35px
                rgba(15, 23, 42, .12);
            overflow: hidden;
        }

        .header {
            padding: 24px 30px;
            border-bottom:
                1px solid #e5e7eb;
            display: flex;
            justify-content:
                space-between;
            align-items:
                flex-start;
            gap: 24px;
        }

        .brand {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 5px;
            font-weight: 700;
        }

        h1 {
            margin: 0;
            font-size: 27px;
        }

        .folio {
            margin-top: 7px;
            font-size: 15px;
            color: #4b5563;
        }

        .status {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 999px;
            background: #fff7ed;
            border:
                1px solid #fed7aa;
            color: #9a3412;
            font-weight: 700;
            font-size: 12px;
        }

        .content {
            padding: 24px 30px 32px;
        }

        .share-panel {
            display: grid;
            grid-template-columns:
                190px 1fr;
            gap: 22px;
            align-items: center;
            padding: 18px;
            margin-bottom: 28px;
            border-radius: 15px;
            border:
                1px solid #bfdbfe;
            background: #eff6ff;
        }

        .share-qr {
            background: #fff;
            border-radius: 12px;
            padding: 10px;
            text-align: center;
        }

        .share-qr img {
            width: 165px;
            height: 165px;
            max-width: 100%;
        }

        .share-title {
            margin: 0 0 7px;
            font-size: 18px;
            font-weight: 800;
        }

        .url {
            padding: 10px;
            margin: 10px 0;
            background: #fff;
            border:
                1px solid #dbeafe;
            border-radius: 9px;
            font-family: monospace;
            font-size: 11px;
            overflow-wrap: anywhere;
        }

        .share-text {
            white-space: pre-wrap;
            font-size: 12px;
            color: #475569;
            padding: 9px 10px;
            border-radius: 8px;
            background:
                rgba(255,255,255,.72);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .button,
        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 9px;
            padding: 10px 13px;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
        }

        .blue {
            background: #2563eb;
            color: #fff;
        }

        .green {
            background: #16a34a;
            color: #fff;
        }

        .dark {
            background: #1f2937;
            color: #fff;
        }

        .light {
            background: #fff;
            color: #1f2937;
            border:
                1px solid #d1d5db;
        }

        .section {
            margin-bottom: 26px;
        }

        .section-title {
            margin: 0 0 11px;
            font-size: 16px;
            font-weight: 800;
            border-bottom:
                1px solid #e5e7eb;
            padding-bottom: 7px;
        }

        .grid {
            display: grid;
            grid-template-columns:
                repeat(
                    2,
                    minmax(0, 1fr)
                );
            gap: 11px 18px;
        }

        .field {
            padding: 10px 11px;
            border:
                1px solid #e5e7eb;
            border-radius: 9px;
            min-height: 61px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        .label {
            color: #6b7280;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 5px;
        }

        .value {
            font-size: 14px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .evidence-grid {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fill,
                    minmax(
                        180px,
                        1fr
                    )
                );
            gap: 12px;
        }

        .evidence {
            border:
                1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            background: #f9fafb;
        }

        .evidence img {
            display: block;
            width: 100%;
            height: 165px;
            object-fit: cover;
            background: #fff;
        }

        .evidence-meta {
            padding: 8px;
            font-size: 10px;
            color: #4b5563;
            overflow-wrap: anywhere;
        }

        .placeholder {
            padding: 17px;
            border:
                2px dashed #cbd5e1;
            border-radius: 11px;
            background: #f8fafc;
            line-height: 1.5;
            font-size: 13px;
        }

        /*
         * BEXIA_ATC_COPY_URL_MODAL_V5_83_4C4B6A1
         */
        .bexia-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background:
                rgba(15, 23, 42, .48);
        }

        .bexia-modal-backdrop.is-open {
            display: flex;
        }

        .bexia-modal {
            width:
                min(
                    430px,
                    100%
                );
            background: #ffffff;
            border-radius: 18px;
            box-shadow:
                0 24px 70px
                rgba(15, 23, 42, .28);
            overflow: hidden;
            animation:
                bexiaModalEnter
                .16s ease-out;
        }

        .bexia-modal-body {
            padding:
                24px 24px 18px;
        }

        .bexia-modal-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            border-radius: 999px;
            background: #ecfdf5;
            color: #15803d;
            font-size: 23px;
            font-weight: 800;
        }

        .bexia-modal-title {
            margin:
                0 0 7px;
            font-size: 19px;
            font-weight: 800;
            color: #111827;
        }

        .bexia-modal-message {
            margin: 0;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.5;
        }

        .bexia-modal-footer {
            display: flex;
            justify-content: flex-end;
            padding:
                14px 24px 20px;
        }

        .bexia-modal-accept {
            min-width: 92px;
            border: 0;
            border-radius: 9px;
            padding:
                10px 17px;
            background: #4f46e5;
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .bexia-modal-accept:hover {
            background: #4338ca;
        }

        .bexia-modal-accept:focus {
            outline:
                3px solid
                rgba(79, 70, 229, .22);
            outline-offset: 2px;
        }

        @keyframes bexiaModalEnter {
            from {
                opacity: 0;
                transform:
                    translateY(-8px)
                    scale(.985);
            }

            to {
                opacity: 1;
                transform:
                    translateY(0)
                    scale(1);
            }
        }

        /*
         * BEXIA_ATC_PUBLIC_PICKUP_FORM_V5_83_4C4C
         */
        .pickup-form-card {
            padding: 18px;
            border: 1px solid #dbeafe;
            border-radius: 14px;
            background: #f8fbff;
        }

        .pickup-intro {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e3a8a;
            font-size: 13px;
            line-height: 1.5;
        }

        .pickup-form-grid {
            display: grid;
            grid-template-columns:
                repeat(
                    2,
                    minmax(0, 1fr)
                );
            gap: 14px 18px;
        }

        .pickup-form-field {
            min-width: 0;
        }

        .pickup-form-field.full {
            grid-column: 1 / -1;
        }

        .pickup-form-field label,
        .pickup-form-label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 800;
            color: #374151;
        }

        .pickup-form-field input[type="text"],
        .pickup-form-field textarea,
        .pickup-form-field select {
            width: 100%;
            min-height: 42px;
            padding: 9px 11px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            background: #fff;
            color: #111827;
            font: inherit;
        }

        .pickup-form-field textarea {
            min-height: 88px;
            resize: vertical;
        }

        .pickup-form-field input:focus,
        .pickup-form-field textarea:focus,
        .pickup-form-field select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, .12);
        }

        .pickup-help {
            margin-top: 5px;
            color: #6b7280;
            font-size: 11px;
            line-height: 1.4;
        }

        .pickup-error {
            margin-top: 5px;
            color: #b91c1c;
            font-size: 11px;
            font-weight: 700;
        }

        .pickup-errors {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            font-size: 12px;
        }

        .pickup-checks {
            display: grid;
            gap: 9px;
        }

        .pickup-check {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            padding: 10px 11px;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            background: #fff;
            font-size: 12px;
            line-height: 1.4;
        }

        .pickup-check input {
            margin-top: 2px;
        }

        .pickup-accessories {
            display: grid;
            grid-template-columns:
                repeat(
                    3,
                    minmax(0, 1fr)
                );
            gap: 8px;
        }

        .pickup-accessory {
            display: flex;
            gap: 7px;
            align-items: center;
            padding: 9px;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            background: #fff;
            font-size: 12px;
        }

        .pickup-signature-box {
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 11px;
            background: #fff;
        }

        #pickup-signature-canvas {
            display: block;
            width: 100%;
            height: 210px;
            border: 1px dashed #94a3b8;
            border-radius: 8px;
            background: #fff;
            touch-action: none;
            cursor: crosshair;
        }

        .pickup-signature-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 8px;
        }

        .pickup-signature-status {
            font-size: 11px;
            font-weight: 700;
            color: #92400e;
        }

        .pickup-signature-status.is-signed {
            color: #166534;
        }

        .pickup-submit {
            width: 100%;
            margin-top: 20px;
            padding: 13px 18px;
            border: 0;
            border-radius: 10px;
            background: #2563eb;
            color: #fff;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
        }

        .pickup-submit:hover {
            background: #1d4ed8;
        }

        .pickup-complete {
            padding: 18px;
            border: 1px solid #bbf7d0;
            border-radius: 14px;
            background: #f0fdf4;
        }

        .pickup-complete-title {
            margin: 0 0 7px;
            color: #166534;
            font-size: 18px;
            font-weight: 800;
        }

        .pickup-difference {
            display: inline-block;
            margin-top: 5px;
            padding: 3px 7px;
            border-radius: 999px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            font-size: 10px;
            font-weight: 800;
        }

        /*
         * BEXIA_ATC_PUBLIC_PICKUP_MOBILE_V5_83_4C4C2
         *
         * Mobile-first para captura en campo.
         */
        .pickup-accessory {
            min-height: 46px;
            cursor: pointer;
            transition:
                border-color .12s ease,
                background .12s ease,
                box-shadow .12s ease;
        }

        .pickup-accessory.is-selected {
            border-color: #60a5fa;
            background: #eff6ff;
            box-shadow:
                inset 0 0 0 1px
                #bfdbfe;
        }

        .pickup-accessory input,
        .pickup-check input {
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
            accent-color: #2563eb;
        }

        .pickup-photo-count {
            margin-top: 7px;
            font-size: 12px;
            font-weight: 700;
            color: #475569;
        }

        .pickup-photo-count.has-files {
            color: #166534;
        }

        #pickup-submit:disabled {
            cursor: wait;
            opacity: .72;
        }

        @media (max-width: 680px) {
            .pickup-form-card {
                padding: 13px;
                border-radius: 12px;
            }

            .pickup-intro {
                margin-bottom: 16px;
                padding: 12px;
                font-size: 13px;
                line-height: 1.45;
            }

            .pickup-form-grid {
                grid-template-columns: 1fr;
                gap: 17px;
            }

            .pickup-form-field.full {
                grid-column: auto;
            }

            .pickup-form-field label,
            .pickup-form-label {
                margin-bottom: 7px;
                font-size: 14px;
                line-height: 1.3;
            }

            /*
             * 16px evita el zoom automatico
             * de Safari/iPhone al enfocar.
             */
            .pickup-form-field input[type="text"],
            .pickup-form-field textarea,
            .pickup-form-field select,
            .pickup-form-field input[type="file"] {
                font-size: 16px;
            }

            .pickup-form-field input[type="text"],
            .pickup-form-field select {
                min-height: 50px;
                padding: 11px 12px;
            }

            .pickup-form-field textarea {
                min-height: 110px;
                padding: 11px 12px;
            }

            .pickup-form-field input[type="file"] {
                width: 100%;
                min-height: 50px;
            }

            .pickup-help {
                margin-top: 6px;
                font-size: 12px;
                line-height: 1.4;
            }

            .pickup-accessories {
                grid-template-columns:
                    repeat(
                        2,
                        minmax(0, 1fr)
                    );
                gap: 9px;
            }

            .pickup-accessory {
                min-height: 52px;
                padding: 10px 11px;
                font-size: 14px;
                line-height: 1.25;
            }

            .pickup-accessory input,
            .pickup-check input {
                width: 21px;
                height: 21px;
            }

            .pickup-checks {
                gap: 10px;
            }

            .pickup-check {
                min-height: 54px;
                padding: 11px;
                gap: 10px;
                font-size: 13px;
                line-height: 1.4;
                cursor: pointer;
            }

            .pickup-signature-box {
                padding: 9px;
            }

            #pickup-signature-canvas {
                height: 190px;
            }

            .pickup-signature-actions {
                align-items: stretch;
                flex-direction: column;
                gap: 8px;
            }

            .pickup-signature-actions .light {
                width: 100%;
                min-height: 46px;
            }

            .pickup-submit {
                min-height: 54px;
                margin-top: 22px;
                padding: 14px 16px;
                border-radius: 11px;
                font-size: 16px;
            }
        }

        @media (max-width: 390px) {
            .pickup-accessories {
                grid-template-columns: 1fr;
            }

            .pickup-accessory {
                min-height: 50px;
            }
        }

        .muted {
            color: #6b7280;
        }

        @media (max-width: 680px) {
            .page {
                width: 100%;
                margin: 0;
                border-radius: 0;
            }

            .header,
            .content {
                padding-left: 16px;
                padding-right: 16px;
            }

            .header {
                display: block;
            }

            .status {
                margin-top: 12px;
            }

            .share-panel {
                grid-template-columns: 1fr;
            }

            .share-qr {
                max-width: 220px;
                margin: auto;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .field.full {
                grid-column: auto;
            }

            .actions {
                display: grid;
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }

        /*
         * BEXIA_ATC_PICKUP_MOBILE_HEADER_V5_83_4C4C3
         *
         * SOLO corrige la parte superior en telefono.
         * Escritorio conserva su presentacion.
         */
        @media (max-width: 680px) {

            .pickup-mobile-order-header {
                display: block !important;
                padding-top: 14px !important;
                padding-bottom: 12px !important;
            }

            .pickup-mobile-order-title {
                margin:
                    0 0 5px !important;
                font-size: 22px !important;
                line-height: 1.12 !important;
                letter-spacing: -.02em !important;
                overflow-wrap: normal !important;
                word-break: normal !important;
            }

            .pickup-mobile-order-header
            .badge,
            .pickup-mobile-order-header
            [class*="badge"] {
                margin-top: 7px !important;
            }

            /*
             * El bloque azul deja de intentar
             * conservar la composicion desktop.
             */
            .pickup-mobile-share-card {
                display: block !important;
                grid-template-columns:
                    1fr !important;
                gap: 0 !important;
                margin-top: 10px !important;
                padding: 13px !important;
                border-radius: 12px !important;
            }

            .pickup-mobile-share-heading {
                display: block !important;
                margin:
                    0 0 4px !important;
                font-size: 15px !important;
                line-height: 1.25 !important;
                text-align: left !important;
            }

            /*
             * En el propio celular:
             * - no tiene sentido escanear el QR
             *   que esta en esa misma pantalla.
             * - tampoco hace falta mostrar el
             *   token/URL completo.
             *
             * QR y URL NO se eliminan.
             * Solo se ocultan visualmente
             * en viewport movil.
             */
            .pickup-mobile-share-verbose,
            .pickup-mobile-qr-block,
            .pickup-mobile-share-card
            #pickup-public-url,
            .pickup-mobile-share-card
            #share-text {
                display: none !important;
            }

            /*
             * Acciones compactas.
             */
            .pickup-mobile-share-actions {
                display: grid !important;
                grid-template-columns:
                    repeat(
                        2,
                        minmax(0, 1fr)
                    ) !important;
                gap: 8px !important;
                width: 100% !important;
                margin-top: 10px !important;
            }

            .pickup-mobile-share-action {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 100% !important;
                min-width: 0 !important;
                min-height: 44px !important;
                margin: 0 !important;
                padding:
                    9px 8px !important;
                border-radius: 9px !important;
                font-size: 12px !important;
                line-height: 1.2 !important;
                text-align: center !important;
                white-space: normal !important;
                box-sizing: border-box !important;
            }

            /*
             * El QR descargable sigue disponible.
             * Solo quitamos su previsualizacion.
             */
            .pickup-mobile-share-card
            img {
                max-width: 100%;
            }
        }

        @media (max-width: 360px) {
            .pickup-mobile-share-actions {
                grid-template-columns:
                    1fr !important;
            }

            .pickup-mobile-share-action {
                min-height: 46px !important;
                font-size: 13px !important;
            }

            .pickup-mobile-order-title {
                font-size: 20px !important;
            }
        }

</style>
</head>

<body>

<div class="page">

    <header class="header">

        <div>
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
        </div>

        <div class="status">
            RECOLECCIÓN PENDIENTE
        </div>

    </header>

    <main class="content">

        <section class="share-panel no-print">

            <div class="share-qr">
                <img
                    src="{{ $qrDataUri }}"
                    alt="QR Orden de recolección"
                >

                <div
                    style="
                        font-size:11px;
                        font-weight:700;
                        margin-top:5px;
                    "
                >
                    Escanear orden
                </div>
            </div>

            <div>

                <h2 class="share-title">
                    Compartir Orden de recolección
                </h2>

                <div>
                    Puedes enviar por WhatsApp
                    el PDF, el QR o solamente
                    este texto con la liga.
                </div>

                <div
                    id="pickup-public-url"
                    class="url"
                    data-copy-url="{{ $publicUrl }}"
                >
                    {{ $publicUrl }}
                </div>

                <div
                    id="share-text"
                    class="share-text"
                >{{ $whatsappText }}</div>

                <div class="actions">

                    <a
                        class="button blue"
                        href="{{ $pdfUrl }}"
                        target="_blank"
                        rel="noopener"
                    >
                        PDF
                    </a>

                    <a
                        class="button green"
                        href="{{ $whatsappUrl }}"
                        target="_blank"
                        rel="noopener"
                    >
                        Enviar por WhatsApp
                    </a>

                    <button
                        type="button"
                        class="dark"
                        onclick="copyPickupUrl()"
                    >
                        Copiar liga
                    </button>

                    <a
                        class="button light"
                        href="{{ $qrUrl }}"
                    >
                        Descargar QR
                    </a>

                </div>

            </div>

        </section>

        <section class="section">

            <h2 class="section-title">
                Cliente / contacto
            </h2>

            <div class="grid">

                <div class="field">
                    <div class="label">
                        Cliente
                    </div>
                    <div class="value">
                        {{
                            $snapshot['contact_name']
                            ?: 'No capturado'
                        }}
                    </div>
                </div>

                <div class="field">
                    <div class="label">
                        Teléfono
                    </div>
                    <div class="value">
                        {{
                            $snapshot['contact_phone']
                            ?: 'No capturado'
                        }}
                    </div>
                </div>

                <div class="field">
                    <div class="label">
                        Correo
                    </div>
                    <div class="value">
                        {{
                            $snapshot['contact_email']
                            ?: 'No capturado'
                        }}
                    </div>
                </div>

                <div class="field">
                    <div class="label">
                        Lugar previsto de recolección
                    </div>
                    <div class="value">
                        {{
                            $snapshot['pickup_place']
                            ?: 'No capturado'
                        }}
                    </div>
                </div>

            </div>

        </section>

        <section class="section">

            <h2 class="section-title">
                Solicitud de servicio
            </h2>

            <div class="grid">

                <div class="field">
                    <div class="label">
                        Fecha del ticket
                    </div>
                    <div class="value">
                        {{
                            $snapshot['created_at']
                            ?: 'No disponible'
                        }}
                    </div>
                </div>

                <div class="field">
                    <div class="label">
                        Prioridad
                    </div>
                    <div class="value">
                        {{
                            ucfirst(
                                (string) (
                                    $snapshot['priority']
                                    ?? ''
                                )
                            )
                        }}
                    </div>
                </div>

                <div class="field">
                    <div class="label">
                        Medio de contacto
                    </div>
                    <div class="value">
                        {{
                            ucfirst(
                                (string) (
                                    $snapshot['channel']
                                    ?? ''
                                )
                            )
                        }}
                    </div>
                </div>

                <div class="field full">
                    <div class="label">
                        Asunto
                    </div>
                    <div class="value">
                        {{
                            $snapshot['subject']
                            ?: 'No capturado'
                        }}
                    </div>
                </div>

                <div class="field full">
                    <div class="label">
                        Problema reportado
                    </div>
                    <div class="value">
                        {{
                            $snapshot['description']
                            ?: 'No capturado'
                        }}
                    </div>
                </div>

            </div>

        </section>

        <section class="section">

            <h2 class="section-title">
                Equipo reportado en el ticket
            </h2>

            <div class="grid">

                <div class="field">
                    <div class="label">
                        Producto / modelo
                    </div>
                    <div class="value">
                        {{
                            $snapshot['product_name']
                            ?: 'No capturado'
                        }}
                    </div>
                </div>

                <div class="field">
                    <div class="label">
                        Número de serie
                    </div>
                    <div class="value">
                        {{
                            $snapshot['serial_number']
                            ?: 'No capturado'
                        }}
                    </div>
                </div>

                <div class="field">
                    <div class="label">
                        Lote
                    </div>
                    <div class="value">
                        {{
                            $snapshot['lot_number']
                            ?: 'No capturado'
                        }}
                    </div>
                </div>

            </div>

        </section>

        <section class="section">

            <h2 class="section-title">
                Atención preparada
            </h2>

            <div class="grid">

                <div class="field">
                    <div class="label">
                        Forma prevista
                    </div>
                    <div class="value">
                        {{
                            $snapshot[
                                'arrival_method_label'
                            ]
                            ?: 'Recolección por chofer'
                        }}
                    </div>
                </div>

                <div class="field">
                    <div class="label">
                        Responsable de seguimiento
                    </div>
                    <div class="value">
                        {{
                            $snapshot['responsible']
                            ?: 'Responsable asignado'
                        }}
                    </div>
                </div>

                <div class="field">
                    <div class="label">
                        Fecha prevista
                    </div>
                    <div class="value">
                        {{
                            $snapshot[
                                'planned_reception_at'
                            ]
                            ?: 'Sin fecha definida'
                        }}
                    </div>
                </div>

                <div class="field full">
                    <div class="label">
                        Indicaciones
                    </div>
                    <div class="value">
                        {{
                            $snapshot[
                                'attention_notes'
                            ]
                            ?: 'Sin indicaciones adicionales'
                        }}
                    </div>
                </div>

            </div>

        </section>

        @if ($attachments->count())

            <section class="section">

                <h2 class="section-title">
                    Evidencias recopiladas
                </h2>

                <div class="evidence-grid">

                    @foreach ($attachments as $attachment)

                        @php
                            $fileUrl =
                                route(
                                    'public.service.pickup.evidence',
                                    [
                                        'token' =>
                                            $token,

                                        'attachment' =>
                                            $attachment->id,
                                    ]
                                );

                            $isImage =
                                str_starts_with(
                                    (string) (
                                        $attachment
                                            ->mime_type
                                        ?? ''
                                    ),
                                    'image/'
                                );
                        @endphp

                        <article class="evidence">

                            @if ($isImage)
                                <a
                                    href="{{ $fileUrl }}"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <img
                                        src="{{ $fileUrl }}"
                                        alt="Evidencia"
                                    >
                                </a>
                            @else
                                <div
                                    style="
                                        padding:30px 12px;
                                        text-align:center;
                                    "
                                >
                                    <a
                                        href="{{ $fileUrl }}"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        Abrir evidencia
                                    </a>
                                </div>
                            @endif

                            <div class="evidence-meta">
                                {{ $attachment->file_name }}
                            </div>

                        </article>

                    @endforeach

                </div>

            </section>

        @endif

        {{-- BEXIA_ATC_PUBLIC_PICKUP_FORM_V5_83_4C4C --}}
        <section class="section">

            <h2 class="section-title">
                Registro de la recolección
            </h2>

            @php
                $pickupStatus =
                    (string) (
                        $pickup['status']
                        ?? 'pending'
                    );

                $pickupData =
                    (array) (
                        $pickup['pickup']
                        ?? []
                    );

                $selectedAccessories =
                    (array) old(
                        'accessories',
                        []
                    );
            @endphp

            @if ($pickupStatus === 'completed')

                <div class="pickup-complete">

                    <h3 class="pickup-complete-title">
                        ✓ Recolección registrada
                    </h3>

                    <p>
                        La información del equipo ya fue
                        registrada. Esta Orden quedó cerrada
                        para nuevas capturas.
                    </p>

                    <div
                        class="grid"
                        style="margin-top:14px;"
                    >

                        <div class="field">
                            <div class="label">
                                Chofer
                            </div>

                            <div class="value">
                                {{
                                    $pickupData[
                                        'driver_name'
                                    ]
                                    ?? '-'
                                }}
                            </div>
                        </div>

                        <div class="field">
                            <div class="label">
                                Persona que entrega
                            </div>

                            <div class="value">
                                {{
                                    $pickupData[
                                        'delivered_by'
                                    ]
                                    ?? '-'
                                }}
                            </div>
                        </div>

                        <div class="field">
                            <div class="label">
                                Lugar real
                            </div>

                            <div class="value">
                                {{
                                    $pickupData[
                                        'pickup_location'
                                    ]
                                    ?? '-'
                                }}
                            </div>
                        </div>

                        <div class="field">
                            <div class="label">
                                Fecha / hora
                            </div>

                            <div class="value">
                                {{
                                    $pickup[
                                        'completed_at'
                                    ]
                                    ?? '-'
                                }}
                            </div>
                        </div>

                        <div class="field">
                            <div class="label">
                                Producto / modelo observado
                            </div>

                            <div class="value">
                                {{
                                    $pickupData[
                                        'observed_product_name'
                                    ]
                                    ?? '-'
                                }}

                                @if (
                                    ! empty(
                                        $pickupData[
                                            'product_differs'
                                        ]
                                    )
                                )
                                    <br>

                                    <span class="pickup-difference">
                                        DIFERENTE A LO REPORTADO
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="field">
                            <div class="label">
                                Serie observada
                            </div>

                            <div class="value">
                                {{
                                    $pickupData[
                                        'observed_serial_number'
                                    ]
                                    ?? 'Sin serie'
                                }}

                                @if (
                                    ! empty(
                                        $pickupData[
                                            'serial_differs'
                                        ]
                                    )
                                )
                                    <br>

                                    <span class="pickup-difference">
                                        DIFERENTE A LO REPORTADO
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="field">
                            <div class="label">
                                Condición
                            </div>

                            <div class="value">
                                {{
                                    $pickupData[
                                        'physical_condition_label'
                                    ]
                                    ?? '-'
                                }}
                            </div>
                        </div>

                        <div class="field">
                            <div class="label">
                                Accesorios
                            </div>

                            <div class="value">
                                {{
                                    implode(
                                        ', ',
                                        (array) (
                                            $pickupData[
                                                'accessories'
                                            ]
                                            ?? []
                                        )
                                    )
                                    ?: '-'
                                }}
                            </div>
                        </div>

                        @if (
                            filled(
                                $pickupData[
                                    'notes'
                                ]
                                ?? null
                            )
                        )
                            <div class="field full">
                                <div class="label">
                                    Observaciones
                                </div>

                                <div class="value">
                                    {{
                                        $pickupData[
                                            'notes'
                                        ]
                                    }}
                                </div>
                            </div>
                        @endif

                    </div>

                    <div
                        class="notice"
                        style="margin-top:14px;"
                    >
                        El registro de recolección conserva
                        los datos originalmente reportados.
                        Cualquier diferencia será confirmada
                        posteriormente durante la recepción
                        física del equipo.
                    </div>

                </div>

            @else

                <div class="pickup-form-card">

                    <div class="pickup-intro">
                        Completa esta sección al momento de
                        recoger físicamente el equipo.
                        No necesitas iniciar sesión en Bexia.
                        Los datos reportados arriba permanecen
                        como referencia y no serán reemplazados.
                    </div>

                    {{-- BEXIA_ATC_PUBLIC_PICKUP_ERRORS_SAFE_V5_83_4C4C1 --}}
                    @php
                        $pickupErrorBag =
                            isset($errors)
                            && $errors instanceof
                                \Illuminate\Support\ViewErrorBag
                                ? $errors
                                : new
                                    \Illuminate\Support\ViewErrorBag();
                    @endphp

                    @if ($pickupErrorBag->any())

                        <div class="pickup-errors">

                            <strong>
                                Revisa los datos:
                            </strong>

                            <ul>
                                @foreach (
                                    $pickupErrorBag->all()
                                    as $error
                                )
                                    <li>
                                        {{ $error }}
                                    </li>
                                @endforeach
                            </ul>

                        </div>

                    @endif

                    <form
                        id="pickup-public-form"
                        method="POST"
                        action="{{
                            route(
                                'public.service.pickup.store',
                                [
                                    'token' =>
                                        $token,
                                ]
                            )
                        }}"
                        enctype="multipart/form-data"
                    >

                        @csrf

                        <div class="pickup-form-grid">

                            <div class="pickup-form-field">

                                <label for="driver_name">
                                    Nombre del chofer *
                                </label>

                                <input
                                    id="driver_name"
                                    name="driver_name"
                                    type="text"
                                    maxlength="150"
                                    required
                                    value="{{
                                        old(
                                            'driver_name'
                                        )
                                    }}"
                                >

                            </div>

                            <div class="pickup-form-field">

                                <label for="delivered_by">
                                    Persona que entrega *
                                </label>

                                <input
                                    id="delivered_by"
                                    name="delivered_by"
                                    type="text"
                                    maxlength="150"
                                    required
                                    value="{{
                                        old(
                                            'delivered_by'
                                        )
                                    }}"
                                >

                            </div>

                            <div class="pickup-form-field full">

                                <label for="pickup_location">
                                    Lugar real de recolección *
                                </label>

                                <input
                                    id="pickup_location"
                                    name="pickup_location"
                                    type="text"
                                    maxlength="255"
                                    required
                                    value="{{
                                        old(
                                            'pickup_location',
                                            $snapshot[
                                                'pickup_place'
                                            ]
                                            ?? ''
                                        )
                                    }}"
                                >

                            </div>

                            <div class="pickup-form-field">

                                <label for="observed_product_name">
                                    Producto / modelo observado *
                                </label>

                                <input
                                    id="observed_product_name"
                                    name="observed_product_name"
                                    type="text"
                                    maxlength="255"
                                    required
                                    value="{{
                                        old(
                                            'observed_product_name',
                                            $snapshot[
                                                'product_name'
                                            ]
                                            ?? ''
                                        )
                                    }}"
                                >

                                <div class="pickup-help">
                                    Corrígelo si físicamente
                                    observas un modelo distinto.
                                </div>

                            </div>

                            <div class="pickup-form-field">

                                <label for="observed_serial_number">
                                    Número de serie observado
                                </label>

                                <input
                                    id="observed_serial_number"
                                    name="observed_serial_number"
                                    type="text"
                                    maxlength="255"
                                    value="{{
                                        old(
                                            'observed_serial_number',
                                            $snapshot[
                                                'serial_number'
                                            ]
                                            ?? ''
                                        )
                                    }}"
                                >

                                <div class="pickup-help">
                                    Si es diferente, captura
                                    exactamente la serie física.
                                </div>

                            </div>

                            <div class="pickup-form-field full">

                                <label for="physical_condition">
                                    Condición física del equipo *
                                </label>

                                <select
                                    id="physical_condition"
                                    name="physical_condition"
                                    required
                                >
                                    <option value="">
                                        Selecciona...
                                    </option>

                                    <option
                                        value="sin_danos_visibles"
                                        @selected(
                                            old(
                                                'physical_condition'
                                            )
                                            ===
                                            'sin_danos_visibles'
                                        )
                                    >
                                        Sin daños visibles
                                    </option>

                                    <option
                                        value="con_danos_visibles"
                                        @selected(
                                            old(
                                                'physical_condition'
                                            )
                                            ===
                                            'con_danos_visibles'
                                        )
                                    >
                                        Con daños visibles
                                    </option>

                                    <option
                                        value="desgaste_normal"
                                        @selected(
                                            old(
                                                'physical_condition'
                                            )
                                            ===
                                            'desgaste_normal'
                                        )
                                    >
                                        Desgaste normal
                                    </option>

                                    <option
                                        value="otro"
                                        @selected(
                                            old(
                                                'physical_condition'
                                            )
                                            ===
                                            'otro'
                                        )
                                    >
                                        Otro
                                    </option>
                                </select>

                            </div>

                            <div class="pickup-form-field full">

                                <span class="pickup-form-label">
                                    Accesorios entregados *
                                </span>

                                <div class="pickup-accessories">

                                    @foreach (
                                        [
                                            'ninguno' =>
                                                'Ninguno',

                                            'cargador' =>
                                                'Cargador',

                                            'cable' =>
                                                'Cable',

                                            'bateria' =>
                                                'Batería',

                                            'llaves' =>
                                                'Llaves',

                                            'control' =>
                                                'Control',

                                            'otro' =>
                                                'Otro',
                                        ]
                                        as $value => $label
                                    )

                                        <label class="pickup-accessory">

                                            <input
                                                type="checkbox"
                                                name="accessories[]"
                                                value="{{ $value }}"
                                                @checked(
                                                    in_array(
                                                        $value,
                                                        $selectedAccessories,
                                                        true
                                                    )
                                                )
                                            >

                                            <span>
                                                {{ $label }}
                                            </span>

                                        </label>

                                    @endforeach

                                </div>

                            </div>

                            <div class="pickup-form-field full">

                                <label for="accessories_other">
                                    Otro accesorio
                                </label>

                                <input
                                    id="accessories_other"
                                    name="accessories_other"
                                    type="text"
                                    maxlength="255"
                                    value="{{
                                        old(
                                            'accessories_other'
                                        )
                                    }}"
                                >

                                <div class="pickup-help">
                                    Llena este campo sólo si
                                    seleccionaste Otro.
                                </div>

                            </div>

                            <div class="pickup-form-field full">

                                <label for="notes">
                                    Observaciones de recolección
                                </label>

                                <textarea
                                    id="notes"
                                    name="notes"
                                    maxlength="2000"
                                >{{ old('notes') }}</textarea>

                            </div>

                            <div class="pickup-form-field full">

                                <label for="photos">
                                    Fotografías / evidencias
                                </label>

                                <input
                                    id="photos"
                                    name="photos[]"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    multiple
                                >

                                <div class="pickup-help">
                                    Hasta 5 fotografías.
                                    JPG, PNG o WEBP.
                                    Desde el celular puedes usar
                                    la cámara o seleccionar imágenes
                                    de la galería.
                                </div>

                                <div
                                    id="pickup-photo-count"
                                    class="pickup-photo-count"
                                    aria-live="polite"
                                >
                                    Ninguna foto seleccionada
                                </div>

                            </div>

                            <div class="pickup-form-field full">

                                <span class="pickup-form-label">
                                    Confirmaciones *
                                </span>

                                <div class="pickup-checks">

                                    <label class="pickup-check">
                                        <input
                                            type="checkbox"
                                            name="confirm_identity"
                                            value="1"
                                            required
                                            @checked(
                                                old(
                                                    'confirm_identity'
                                                )
                                            )
                                        >

                                        <span>
                                            Confirmo que el equipo
                                            mostrado corresponde al
                                            que estoy recolectando.
                                        </span>
                                    </label>

                                    <label class="pickup-check">
                                        <input
                                            type="checkbox"
                                            name="confirm_condition"
                                            value="1"
                                            required
                                            @checked(
                                                old(
                                                    'confirm_condition'
                                                )
                                            )
                                        >

                                        <span>
                                            Revisé la condición física
                                            del equipo con la persona
                                            que lo entrega.
                                        </span>
                                    </label>

                                    <label class="pickup-check">
                                        <input
                                            type="checkbox"
                                            name="confirm_accessories"
                                            value="1"
                                            required
                                            @checked(
                                                old(
                                                    'confirm_accessories'
                                                )
                                            )
                                        >

                                        <span>
                                            Revisé los accesorios que
                                            se entregan junto con el
                                            equipo.
                                        </span>
                                    </label>

                                    <label class="pickup-check">
                                        <input
                                            type="checkbox"
                                            name="confirm_authorization"
                                            value="1"
                                            required
                                            @checked(
                                                old(
                                                    'confirm_authorization'
                                                )
                                            )
                                        >

                                        <span>
                                            La persona que entrega
                                            autoriza la recolección
                                            del equipo para revisión.
                                        </span>
                                    </label>

                                </div>

                            </div>

                            <div class="pickup-form-field full">

                                <span class="pickup-form-label">
                                    Firma del cliente *
                                </span>

                                <div class="pickup-signature-box">

                                    <canvas
                                        id="pickup-signature-canvas"
                                        width="900"
                                        height="260"
                                    ></canvas>

                                    <input
                                        id="pickup-customer-signature"
                                        name="customer_signature"
                                        type="hidden"
                                        value=""
                                    >

                                    <div class="pickup-signature-actions">

                                        <span
                                            id="pickup-signature-status"
                                            class="pickup-signature-status"
                                        >
                                            Pendiente de firma
                                        </span>

                                        <button
                                            type="button"
                                            class="light"
                                            onclick="clearPickupSignature()"
                                        >
                                            Limpiar firma
                                        </button>

                                    </div>

                                </div>

                                <div
                                    id="pickup-signature-error"
                                    class="pickup-error"
                                    style="display:none;"
                                >
                                    La firma del cliente es obligatoria.
                                </div>

                                <div class="pickup-help">
                                    La firma se captura al final,
                                    después de revisar equipo,
                                    condición, accesorios y evidencias.
                                </div>

                            </div>

                        </div>

                        <button
                            id="pickup-submit"
                            class="pickup-submit"
                            type="submit"
                        >
                            Confirmar recolección
                        </button>

                    </form>

                </div>

            @endif

        </section>

        <div
            class="muted"
            style="
                font-size:10px;
                border-top:
                    1px solid #e5e7eb;
                padding-top:12px;
            "
        >
            Orden generada:
            {{
                $pickup['generated_at']
                ?? '-'
            }}
        </div>

    </main>

</div>

{{-- BEXIA_ATC_COPY_URL_MODAL_V5_83_4C4B6A1 --}}
<div
    id="bexia-copy-modal"
    class="bexia-modal-backdrop"
    aria-hidden="true"
    onclick="handleCopyModalBackdrop(event)"
>
    <div
        class="bexia-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="bexia-copy-modal-title"
        aria-describedby="bexia-copy-modal-message"
    >
        <div class="bexia-modal-body">

            <div
                class="bexia-modal-icon"
                aria-hidden="true"
            >
                ✓
            </div>

            <h2
                id="bexia-copy-modal-title"
                class="bexia-modal-title"
            >
                Liga copiada
            </h2>

            <p
                id="bexia-copy-modal-message"
                class="bexia-modal-message"
            >
                La liga de la Orden de recolección
                se copió al portapapeles.
            </p>

        </div>

        <div class="bexia-modal-footer">

            <button
                id="bexia-copy-modal-accept"
                type="button"
                class="bexia-modal-accept"
                onclick="closeCopyModal()"
            >
                Aceptar
            </button>

        </div>
    </div>
</div>

<script>
/*
 * BEXIA_ATC_COPY_URL_MODAL_V5_83_4C4B6A1
 *
 * Copiar liga usa exclusivamente data-copy-url.
 * No utiliza share-text ni whatsappText.
 */
let bexiaCopyModalLastFocus = null;

function showCopyModal() {
    const modal =
        document.getElementById(
            'bexia-copy-modal'
        );

    const button =
        document.getElementById(
            'bexia-copy-modal-accept'
        );

    if (!modal) {
        return;
    }

    bexiaCopyModalLastFocus =
        document.activeElement;

    modal.classList.add(
        'is-open'
    );

    modal.setAttribute(
        'aria-hidden',
        'false'
    );

    document.body.style.overflow =
        'hidden';

    window.setTimeout(
        function () {
            if (button) {
                button.focus();
            }
        },
        20
    );
}

function closeCopyModal() {
    const modal =
        document.getElementById(
            'bexia-copy-modal'
        );

    if (!modal) {
        return;
    }

    modal.classList.remove(
        'is-open'
    );

    modal.setAttribute(
        'aria-hidden',
        'true'
    );

    document.body.style.overflow =
        '';

    if (
        bexiaCopyModalLastFocus
        && typeof bexiaCopyModalLastFocus.focus
            === 'function'
    ) {
        bexiaCopyModalLastFocus.focus();
    }
}

function handleCopyModalBackdrop(event) {
    if (
        event.target
        && event.target.id
            === 'bexia-copy-modal'
    ) {
        closeCopyModal();
    }
}

document.addEventListener(
    'keydown',
    function (event) {
        if (
            event.key === 'Escape'
        ) {
            const modal =
                document.getElementById(
                    'bexia-copy-modal'
                );

            if (
                modal
                && modal.classList.contains(
                    'is-open'
                )
            ) {
                closeCopyModal();
            }
        }
    }
);

function copyPickupUrl() {
    const urlElement =
        document.getElementById(
            'pickup-public-url'
        );

    if (!urlElement) {
        return;
    }

    /*
     * data-copy-url contiene solamente:
     * https://.../servicio/recoleccion/TOKEN
     */
    const url =
        (
            urlElement.dataset.copyUrl
            || ''
        ).trim();

    if (!url) {
        return;
    }

    if (
        navigator.clipboard
        && navigator.clipboard.writeText
    ) {
        navigator.clipboard
            .writeText(
                url
            )
            .then(
                function () {
                    showCopyModal();
                }
            )
            .catch(
                function () {
                    fallbackCopyPickupUrl(
                        url
                    );
                }
            );

        return;
    }

    fallbackCopyPickupUrl(
        url
    );
}

function fallbackCopyPickupUrl(url) {
    const area =
        document.createElement(
            'textarea'
        );

    area.value = url;

    area.setAttribute(
        'readonly',
        ''
    );

    area.style.position =
        'fixed';

    area.style.left =
        '-9999px';

    area.style.opacity =
        '0';

    document.body.appendChild(
        area
    );

    area.select();

    let copied = false;

    try {
        copied =
            document.execCommand(
                'copy'
            );
    } finally {
        area.remove();
    }

    if (copied) {
        showCopyModal();
    }
}
</script>


<script>
/*
 * BEXIA_ATC_PUBLIC_PICKUP_SIGNATURE_V5_83_4C4C
 */
(function () {
    const canvas =
        document.getElementById(
            'pickup-signature-canvas'
        );

    const input =
        document.getElementById(
            'pickup-customer-signature'
        );

    const status =
        document.getElementById(
            'pickup-signature-status'
        );

    const form =
        document.getElementById(
            'pickup-public-form'
        );

    const error =
        document.getElementById(
            'pickup-signature-error'
        );

    if (
        !canvas
        || !input
        || !form
    ) {
        return;
    }

    const context =
        canvas.getContext(
            '2d'
        );

    context.lineWidth = 3;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    context.strokeStyle = '#111827';

    let drawing = false;
    let hasSignature = false;

    function point(event) {
        const rect =
            canvas.getBoundingClientRect();

        return {
            x:
                (
                    event.clientX
                    - rect.left
                )
                * (
                    canvas.width
                    / rect.width
                ),

            y:
                (
                    event.clientY
                    - rect.top
                )
                * (
                    canvas.height
                    / rect.height
                ),
        };
    }

    function begin(event) {
        event.preventDefault();

        drawing = true;

        const p =
            point(event);

        context.beginPath();

        context.moveTo(
            p.x,
            p.y
        );

        if (
            canvas.setPointerCapture
        ) {
            canvas.setPointerCapture(
                event.pointerId
            );
        }
    }

    function move(event) {
        if (!drawing) {
            return;
        }

        event.preventDefault();

        const p =
            point(event);

        context.lineTo(
            p.x,
            p.y
        );

        context.stroke();

        hasSignature = true;
    }

    function end(event) {
        if (!drawing) {
            return;
        }

        event.preventDefault();

        drawing = false;

        if (hasSignature) {
            input.value =
                canvas.toDataURL(
                    'image/png'
                );

            status.textContent =
                'Firma capturada';

            status.classList.add(
                'is-signed'
            );

            if (error) {
                error.style.display =
                    'none';
            }
        }
    }

    canvas.addEventListener(
        'pointerdown',
        begin
    );

    canvas.addEventListener(
        'pointermove',
        move
    );

    canvas.addEventListener(
        'pointerup',
        end
    );

    canvas.addEventListener(
        'pointercancel',
        end
    );

    canvas.addEventListener(
        'pointerleave',
        function (event) {
            if (drawing) {
                end(event);
            }
        }
    );

    form.addEventListener(
        'submit',
        function (event) {
            if (
                !input.value
                || !hasSignature
            ) {
                event.preventDefault();

                if (error) {
                    error.style.display =
                        'block';
                }

                canvas.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                });
            }
        }
    );

    window.clearPickupSignature =
        function () {
            context.clearRect(
                0,
                0,
                canvas.width,
                canvas.height
            );

            input.value = '';
            hasSignature = false;

            status.textContent =
                'Pendiente de firma';

            status.classList.remove(
                'is-signed'
            );

            if (error) {
                error.style.display =
                    'none';
            }
        };
})();
</script>


<script>
/*
 * BEXIA_ATC_PUBLIC_PICKUP_MOBILE_V5_83_4C4C2
 *
 * UX de accesorios:
 * - Ninguno es excluyente.
 * - Otro muestra su campo solamente cuando aplica.
 *
 * UX movil:
 * - contador de fotos
 * - evita doble submit
 */
(function () {
    const form =
        document.getElementById(
            'pickup-public-form'
        );

    if (!form) {
        return;
    }

    const accessoryBoxes =
        Array.from(
            form.querySelectorAll(
                'input[name="accessories[]"]'
            )
        );

    const noneBox =
        accessoryBoxes.find(
            function (box) {
                return box.value
                    === 'ninguno';
            }
        );

    const otherBox =
        accessoryBoxes.find(
            function (box) {
                return box.value
                    === 'otro';
            }
        );

    const otherInput =
        document.getElementById(
            'accessories_other'
        );

    const otherWrapper =
        otherInput
            ? otherInput.closest(
                '.pickup-form-field'
            )
            : null;

    function paintAccessories() {
        accessoryBoxes.forEach(
            function (box) {
                const card =
                    box.closest(
                        '.pickup-accessory'
                    );

                if (card) {
                    card.classList.toggle(
                        'is-selected',
                        box.checked
                    );
                }
            }
        );
    }

    function syncOtherAccessory() {
        const active =
            Boolean(
                otherBox
                && otherBox.checked
            );

        if (otherWrapper) {
            otherWrapper.hidden =
                !active;
        }

        if (otherInput) {
            otherInput.disabled =
                !active;

            otherInput.required =
                active;

            if (!active) {
                otherInput.value = '';
            }
        }
    }

    function syncAccessories(
        changedBox
    ) {
        if (
            changedBox
            && changedBox.value
                === 'ninguno'
            && changedBox.checked
        ) {
            accessoryBoxes.forEach(
                function (box) {
                    if (
                        box !== changedBox
                    ) {
                        box.checked = false;
                    }
                }
            );
        }

        if (
            changedBox
            && changedBox.value
                !== 'ninguno'
            && changedBox.checked
            && noneBox
        ) {
            noneBox.checked = false;
        }

        paintAccessories();
        syncOtherAccessory();
    }

    accessoryBoxes.forEach(
        function (box) {
            box.addEventListener(
                'change',
                function () {
                    syncAccessories(
                        box
                    );
                }
            );
        }
    );

    /*
     * Corregir estado inicial.
     *
     * Si por old() hubiera Ninguno y
     * algun accesorio al mismo tiempo,
     * prevalece el accesorio especifico.
     */
    const selectedSpecific =
        accessoryBoxes.some(
            function (box) {
                return (
                    box.value
                        !== 'ninguno'
                    && box.checked
                );
            }
        );

    if (
        selectedSpecific
        && noneBox
    ) {
        noneBox.checked = false;
    }

    paintAccessories();
    syncOtherAccessory();

    /*
     * Fotos.
     */
    const photos =
        document.getElementById(
            'photos'
        );

    const photoCount =
        document.getElementById(
            'pickup-photo-count'
        );

    if (
        photos
        && photoCount
    ) {
        photos.addEventListener(
            'change',
            function () {
                const count =
                    photos.files
                        ? photos.files.length
                        : 0;

                if (count <= 0) {
                    photoCount.textContent =
                        'Ninguna foto seleccionada';

                    photoCount.classList.remove(
                        'has-files'
                    );

                    return;
                }

                photoCount.textContent =
                    count === 1
                        ? '1 foto seleccionada'
                        : count
                            + ' fotos seleccionadas';

                photoCount.classList.add(
                    'has-files'
                );
            }
        );
    }

    /*
     * Evitar doble toque al guardar.
     *
     * El listener de firma ya pudo
     * cancelar el submit si no existe
     * firma. Respetamos defaultPrevented.
     */
    const submit =
        document.getElementById(
            'pickup-submit'
        );

    form.addEventListener(
        'submit',
        function (event) {
            window.setTimeout(
                function () {
                    if (
                        event.defaultPrevented
                        || !submit
                    ) {
                        return;
                    }

                    submit.disabled = true;
                    submit.textContent =
                        'Guardando recolección...';
                },
                0
            );
        }
    );
})();
</script>


<script>
/*
 * BEXIA_ATC_PICKUP_MOBILE_HEADER_DOM_V5_83_4C4C3
 *
 * Solo agrega clases de presentacion.
 * No modifica URLs, acciones ni datos.
 */
(function () {

    function normalizedText(element) {
        return (
            element
                && element.textContent
                ? element.textContent
                : ''
        )
            .replace(
                /\s+/g,
                ' '
            )
            .trim();
    }

    function findExactText(
        root,
        values
    ) {
        const wanted =
            Array.isArray(values)
                ? values
                : [values];

        const elements =
            Array.from(
                root.querySelectorAll(
                    'h1,h2,h3,h4,p,div,span,strong,a,button'
                )
            );

        return elements.find(
            function (element) {
                return wanted.includes(
                    normalizedText(
                        element
                    )
                );
            }
        ) || null;
    }

    /*
     * CABECERA
     */
    const orderTitle =
        findExactText(
            document,
            'ORDEN DE RECOLECCIÓN'
        );

    if (orderTitle) {
        orderTitle.classList.add(
            'pickup-mobile-order-title'
        );

        const orderHeader =
            orderTitle.closest(
                'header'
            )
            || orderTitle.parentElement;

        if (orderHeader) {
            orderHeader.classList.add(
                'pickup-mobile-order-header'
            );
        }
    }

    /*
     * BLOQUE COMPARTIR
     *
     * Partimos del elemento de URL,
     * que tiene ID estable desde C4B6A1.
     */
    const publicUrl =
        document.getElementById(
            'pickup-public-url'
        );

    if (!publicUrl) {
        return;
    }

    let shareCard =
        publicUrl.parentElement;

    while (
        shareCard
        && shareCard !== document.body
    ) {
        const text =
            normalizedText(
                shareCard
            );

        if (
            text.includes(
                'Compartir Orden de recolección'
            )
            && text.includes(
                'Enviar por WhatsApp'
            )
            && text.includes(
                'Copiar liga'
            )
        ) {
            break;
        }

        shareCard =
            shareCard.parentElement;
    }

    if (
        !shareCard
        || shareCard === document.body
    ) {
        return;
    }

    shareCard.classList.add(
        'pickup-mobile-share-card'
    );

    /*
     * Encabezado de compartir.
     */
    const shareHeading =
        findExactText(
            shareCard,
            'Compartir Orden de recolección'
        );

    if (shareHeading) {
        shareHeading.classList.add(
            'pickup-mobile-share-heading'
        );
    }

    /*
     * Texto descriptivo largo.
     */
    Array.from(
        shareCard.querySelectorAll(
            'p,div,span'
        )
    ).forEach(
        function (element) {
            const text =
                normalizedText(
                    element
                );

            if (
                text.startsWith(
                    'Puedes enviar por WhatsApp'
                )
            ) {
                element.classList.add(
                    'pickup-mobile-share-verbose'
                );
            }
        }
    );

    /*
     * QR grande.
     */
    const qrImage =
        shareCard.querySelector(
            'img[src^="data:image/svg+xml"],'
            + 'img[src^="data:image/png;base64"]'
        );

    if (qrImage) {
        const qrWrapper =
            qrImage.parentElement;

        if (qrWrapper) {
            qrWrapper.classList.add(
                'pickup-mobile-qr-block'
            );
        } else {
            qrImage.classList.add(
                'pickup-mobile-qr-block'
            );
        }
    }

    /*
     * Por si "Escanear orden" no vive
     * exactamente dentro del wrapper QR.
     */
    const scanText =
        findExactText(
            shareCard,
            [
                'Escanear orden',
                'Escanear Orden',
            ]
        );

    if (scanText) {
        scanText.classList.add(
            'pickup-mobile-qr-block'
        );
    }

    /*
     * ACCIONES
     */
    const actionLabels = [
        'PDF',
        'Enviar por WhatsApp',
        'Copiar liga',
        'Descargar QR',
    ];

    const actions =
        Array.from(
            shareCard.querySelectorAll(
                'a,button'
            )
        ).filter(
            function (element) {
                return actionLabels.includes(
                    normalizedText(
                        element
                    )
                );
            }
        );

    actions.forEach(
        function (element) {
            element.classList.add(
                'pickup-mobile-share-action'
            );
        }
    );

    /*
     * Encontrar el ancestro comun mas
     * pequeno de los botones.
     */
    if (actions.length >= 2) {
        let candidate =
            actions[0].parentElement;

        while (
            candidate
            && candidate !== shareCard
        ) {
            const containsAll =
                actions.every(
                    function (action) {
                        return candidate.contains(
                            action
                        );
                    }
                );

            if (containsAll) {
                candidate.classList.add(
                    'pickup-mobile-share-actions'
                );

                break;
            }

            candidate =
                candidate.parentElement;
        }

        if (
            candidate === shareCard
        ) {
            /*
             * Si los botones cuelgan directamente
             * de la tarjeta, el grid se deja en
             * su contenedor inmediato si coincide.
             */
            const parents =
                actions.map(
                    function (action) {
                        return action.parentElement;
                    }
                );

            const sameParent =
                parents.every(
                    function (parent) {
                        return parent
                            === parents[0];
                    }
                );

            if (
                sameParent
                && parents[0]
            ) {
                parents[0].classList.add(
                    'pickup-mobile-share-actions'
                );
            }
        }
    }
})();
</script>

</body>
</html>
