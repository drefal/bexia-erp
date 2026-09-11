<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width,initial-scale=1,maximum-scale=1"
    >
    <meta name="robots" content="noindex,nofollow">

    <title>
        Entrega de equipo ATC
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f1f5f9;
            color: #0f172a;
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        .page {
            width: 100%;
            max-width: 720px;
            margin: 0 auto;
            padding: 18px 14px 42px;
        }

        .brand {
            margin-bottom: 14px;
            text-align: center;
        }

        .brand-title {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .brand-subtitle {
            margin-top: 4px;
            color: #64748b;
            font-size: 13px;
        }

        .card {
            margin-bottom: 14px;
            padding: 18px;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            background: #ffffff;
            box-shadow:
                0 1px 2px rgba(15,23,42,.04);
        }

        .title {
            margin: 0 0 4px;
            font-size: 22px;
            line-height: 1.2;
        }

        .muted {
            color: #64748b;
        }

        .small {
            font-size: 12px;
        }

        .grid {
            display: grid;
            grid-template-columns:
                repeat(2,minmax(0,1fr));
            gap: 10px;
            margin-top: 16px;
        }

        .item {
            padding: 11px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
        }

        .item.wide {
            grid-column: 1 / -1;
        }

        .label {
            margin-bottom: 3px;
            color: #64748b;
            font-size: 11px;
        }

        .value {
            font-size: 14px;
            font-weight: 700;
            word-break: break-word;
        }

        .notice {
            margin-top: 14px;
            padding: 14px;
            border-radius: 14px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
        }

        .notice strong {
            display: block;
            margin-bottom: 3px;
        }

        .notice.success {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #065f46;
        }

        .notice.info {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1e40af;
        }

        .field {
            margin-top: 16px;
        }

        .field label {
            display: block;
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 700;
        }

        input[type="text"],
        textarea,
        input[type="file"] {
            display: block;
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #ffffff;
            color: #0f172a;
            font: inherit;
        }

        input[type="text"],
        textarea {
            padding: 12px 13px;
        }

        input[type="file"] {
            padding: 10px;
        }

        textarea {
            min-height: 96px;
            resize: vertical;
        }

        .error {
            margin-top: 6px;
            color: #b91c1c;
            font-size: 12px;
            font-weight: 600;
        }

        .error-box {
            margin-bottom: 14px;
            padding: 13px 14px;
            border-radius: 12px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            font-size: 13px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 10px 15px;
            border: 0;
            border-radius: 12px;
            cursor: pointer;
            background: #0f172a;
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
        }

        .button.secondary {
            background: #e2e8f0;
            color: #0f172a;
        }

        .button.success {
            width: 100%;
            min-height: 52px;
            margin-top: 20px;
            background: #15803d;
            font-size: 16px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }

        .signature-wrap {
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            overflow: hidden;
            background: #ffffff;
        }

        #signatureCanvas {
            display: block;
            width: 100%;
            height: 210px;
            background: #ffffff;
            touch-action: none;
        }

        .signature-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .geo-status {
            margin-top: 7px;
            color: #64748b;
            font-size: 12px;
        }

        .divider {
            height: 1px;
            margin: 18px 0;
            background: #e2e8f0;
        }

        @media (max-width: 540px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .item.wide {
                grid-column: auto;
            }

            .card {
                padding: 15px;
            }
        }
    </style>
</head>

<body>
@php
    $completed =
        (bool) (
            $delivery['completed']
            ?? false
        );

    $canSubmit =
        (bool) (
            $delivery['can_submit']
            ?? false
        );

    $policy =
        (string) (
            $delivery['policy']
            ?? ''
        );

    $amountToCollect =
        $delivery['amount_to_collect']
        ?? null;

    $currency =
        (string) (
            $delivery['currency']
            ?? 'MXN'
        );

    $currentUrl =
        request()->fullUrl();
@endphp

<div class="page">
    <div class="brand">
        <div class="brand-title">
            Bexia ERP
        </div>

        <div class="brand-subtitle">
            Atención a clientes · Entrega de equipo
        </div>
    </div>

    <div class="card">
        <h1 class="title">
            Entrega de equipo
        </h1>

        <div class="muted small">
            Verifica los datos antes de entregar el equipo al cliente.
        </div>

        <div class="grid">
            <div class="item">
                <div class="label">
                    Ticket
                </div>
                <div class="value">
                    {{ $delivery['service_case_folio'] ?: '—' }}
                </div>
            </div>

            <div class="item">
                <div class="label">
                    Reparación
                </div>
                <div class="value">
                    {{ $delivery['repair_folio'] ?: '—' }}
                </div>
            </div>

            <div class="item">
                <div class="label">
                    Salida autorizada
                </div>
                <div class="value">
                    {{ $delivery['sal_folio'] ?: '—' }}
                </div>
            </div>

            <div class="item">
                <div class="label">
                    Condición
                </div>
                <div class="value">
                    {{ $delivery['condition_label'] ?: '—' }}
                </div>
            </div>

            @if(!empty($delivery['customer_name']))
                <div class="item wide">
                    <div class="label">
                        Cliente
                    </div>
                    <div class="value">
                        {{ $delivery['customer_name'] }}
                    </div>
                </div>
            @endif

            <div class="item wide">
                <div class="label">
                    Equipo
                </div>
                <div class="value">
                    {{ $delivery['product_name'] ?: 'Equipo registrado en ticket' }}
                </div>

                @if(!empty($delivery['serial_number']))
                    <div
                        class="muted small"
                        style="margin-top:4px;"
                    >
                        Serie:
                        {{ $delivery['serial_number'] }}
                    </div>
                @endif
            </div>
        </div>

        @if(
            $policy === 'payment_on_delivery'
            && $amountToCollect !== null
        )
            <div class="notice">
                <strong>
                    Cobro contra entrega
                </strong>

                El importe pendiente indicado para esta entrega es:

                <div
                    style="
                        margin-top:6px;
                        font-size:24px;
                        font-weight:900;
                    "
                >
                    ${{ number_format((float) $amountToCollect, 2) }}
                    {{ $currency }}
                </div>

                <div
                    class="small"
                    style="margin-top:6px;"
                >
                    Esta página no registra el cobro en
                    Cuentas por cobrar. El registro contable
                    continúa siendo responsabilidad del área
                    autorizada.
                </div>
            </div>
        @elseif($policy === 'credit_allowed')
            <div class="notice info">
                <strong>
                    Crédito autorizado
                </strong>

                El equipo puede entregarse con saldo pendiente.
                Esta página no registra pagos.
            </div>
        @elseif($policy === 'no_charge')
            <div class="notice info">
                <strong>
                    Entrega sin cobro
                </strong>

                La reparación fue autorizada sin cargo al cliente.
            </div>
        @endif

        @if(!$completed)
            <div class="actions">
                <button
                    type="button"
                    class="button secondary"
                    id="copyLinkButton"
                >
                    Copiar liga
                </button>

                <a
                    class="button secondary"
                    target="_blank"
                    rel="noopener noreferrer"
                    href="https://wa.me/?text={{ rawurlencode(
                        'Entrega ATC '
                        . ($delivery['repair_folio'] ?: '')
                        . ' '
                        . $currentUrl
                    ) }}"
                >
                    WhatsApp
                </a>
            </div>
        @endif

        <div
            class="muted small"
            style="margin-top:12px;"
        >
            Liga válida hasta:
            {{ $delivery['expires_at'] ?: '—' }}
        </div>
    </div>

    @if(session('delivery_success') || $completed)
        <div class="card">
            <div class="notice success">
                <strong>
                    Entrega registrada
                </strong>

                La evidencia y la firma quedaron guardadas.

                @if(!empty($delivery['delivered_to']))
                    <div style="margin-top:8px;">
                        Recibió:
                        <strong>
                            {{ $delivery['delivered_to'] }}
                        </strong>
                    </div>
                @endif

                @if(!empty($delivery['delivered_at']))
                    <div style="margin-top:4px;">
                        Fecha:
                        <strong>
                            {{ $delivery['delivered_at'] }}
                        </strong>
                    </div>
                @endif
            </div>
        </div>
    @elseif(!$canSubmit)
        <div class="card">
            <div class="notice">
                <strong>
                    Entrega no disponible
                </strong>

                {{
                    $delivery['blocked_reason']
                    ?: 'La entrega ya no puede registrarse desde esta liga.'
                }}
            </div>
        </div>
    @else
        <div class="card">
            <h2
                style="
                    margin:0 0 4px;
                    font-size:18px;
                "
            >
                Confirmar entrega
            </h2>

            <div class="muted small">
                La evidencia y la firma son obligatorias.
            </div>

            @if($errors->any())
                <div
                    class="error-box"
                    style="margin-top:14px;"
                >
                    <strong>
                        Revisa la información:
                    </strong>

                    <ul
                        style="
                            margin:8px 0 0;
                            padding-left:18px;
                        "
                    >
                        @foreach($errors->all() as $error)
                            <li>
                                {{ $error }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form
                id="deliveryForm"
                method="POST"
                action="{{ route(
                    'public.service.repair-delivery.store',
                    ['token' => $token]
                ) }}"
                enctype="multipart/form-data"
            >
                @csrf

                <div class="field">
                    <label for="driver_name">
                        Nombre del chofer *
                    </label>

                    <input
                        id="driver_name"
                        name="driver_name"
                        type="text"
                        maxlength="255"
                        autocomplete="name"
                        required
                        value="{{ old('driver_name') }}"
                        placeholder="Nombre completo"
                    >

                    @error('driver_name')
                        <div class="error">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="field">
                    <label for="delivered_to">
                        Persona que recibe *
                    </label>

                    <input
                        id="delivered_to"
                        name="delivered_to"
                        type="text"
                        maxlength="255"
                        autocomplete="name"
                        required
                        value="{{ old(
                            'delivered_to',
                            $delivery['customer_name'] ?? ''
                        ) }}"
                        placeholder="Nombre de quien recibe"
                    >

                    @error('delivered_to')
                        <div class="error">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="field">
                    <label for="delivery_notes">
                        Observaciones
                    </label>

                    <textarea
                        id="delivery_notes"
                        name="delivery_notes"
                        maxlength="2000"
                        placeholder="Condición del equipo, comentarios de entrega, etc."
                    >{{ old('delivery_notes') }}</textarea>

                    @error('delivery_notes')
                        <div class="error">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="field">
                    <label>
                        Ubicación de entrega
                        <span class="muted">
                            (opcional)
                        </span>
                    </label>

                    <input
                        type="hidden"
                        name="latitude"
                        id="latitude"
                        value="{{ old('latitude') }}"
                    >

                    <input
                        type="hidden"
                        name="longitude"
                        id="longitude"
                        value="{{ old('longitude') }}"
                    >

                    <button
                        type="button"
                        class="button secondary"
                        id="locationButton"
                    >
                        Usar ubicación actual
                    </button>

                    <div
                        id="geoStatus"
                        class="geo-status"
                    >
                        No se ha capturado ubicación.
                    </div>
                </div>

                <div class="divider"></div>

                <div class="field">
                    <label for="delivery_files">
                        Evidencia de entrega *
                    </label>

                    <input
                        id="delivery_files"
                        name="delivery_files[]"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                        multiple
                        required
                    >

                    <div
                        class="muted small"
                        style="margin-top:6px;"
                    >
                        Puedes adjuntar fotografías o PDF.
                        Máximo 10 archivos de 10 MB cada uno.
                    </div>

                    @error('delivery_files')
                        <div class="error">
                            {{ $message }}
                        </div>
                    @enderror

                    @error('delivery_files.*')
                        <div class="error">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="field">
                    <label>
                        Firma de quien recibe *
                    </label>

                    <div class="signature-wrap">
                        <canvas
                            id="signatureCanvas"
                            width="900"
                            height="300"
                        ></canvas>

                        <div class="signature-footer">
                            <span class="muted small">
                                Firmar dentro del recuadro.
                            </span>

                            <button
                                type="button"
                                class="button secondary"
                                id="clearSignatureButton"
                            >
                                Limpiar
                            </button>
                        </div>
                    </div>

                    <input
                        type="hidden"
                        id="delivery_signature_data"
                        name="delivery_signature_data"
                    >

                    <div
                        id="signatureError"
                        class="error"
                        style="display:none;"
                    >
                        Solicita la firma de quien recibe.
                    </div>

                    @error('delivery_signature_data')
                        <div class="error">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="button success"
                    id="submitDeliveryButton"
                >
                    Confirmar entrega
                </button>

                <div
                    class="muted small"
                    style="
                        margin-top:10px;
                        text-align:center;
                    "
                >
                    Confirma sólo después de haber entregado
                    físicamente el equipo.
                </div>
            </form>
        </div>
    @endif
</div>

<script>
(function () {
    const copyButton =
        document.getElementById(
            'copyLinkButton'
        );

    if (copyButton) {
        copyButton.addEventListener(
            'click',
            async function () {
                try {
                    await navigator.clipboard.writeText(
                        window.location.href
                    );

                    const original =
                        copyButton.textContent;

                    copyButton.textContent =
                        'Liga copiada';

                    setTimeout(
                        function () {
                            copyButton.textContent =
                                original;
                        },
                        1800
                    );
                } catch (error) {
                    window.prompt(
                        'Copia esta liga:',
                        window.location.href
                    );
                }
            }
        );
    }

    const locationButton =
        document.getElementById(
            'locationButton'
        );

    const latitude =
        document.getElementById(
            'latitude'
        );

    const longitude =
        document.getElementById(
            'longitude'
        );

    const geoStatus =
        document.getElementById(
            'geoStatus'
        );

    if (
        locationButton &&
        latitude &&
        longitude &&
        geoStatus
    ) {
        locationButton.addEventListener(
            'click',
            function () {
                if (
                    ! navigator.geolocation
                ) {
                    geoStatus.textContent =
                        'Este dispositivo no permite obtener ubicación.';

                    return;
                }

                geoStatus.textContent =
                    'Obteniendo ubicación...';

                navigator.geolocation
                    .getCurrentPosition(
                        function (position) {
                            latitude.value =
                                position.coords.latitude;

                            longitude.value =
                                position.coords.longitude;

                            geoStatus.textContent =
                                'Ubicación capturada correctamente.';
                        },
                        function () {
                            geoStatus.textContent =
                                'No fue posible obtener la ubicación. Puedes continuar sin ella.';
                        },
                        {
                            enableHighAccuracy: true,
                            timeout: 10000,
                            maximumAge: 30000
                        }
                    );
            }
        );
    }

    const canvas =
        document.getElementById(
            'signatureCanvas'
        );

    const hidden =
        document.getElementById(
            'delivery_signature_data'
        );

    const clearButton =
        document.getElementById(
            'clearSignatureButton'
        );

    const form =
        document.getElementById(
            'deliveryForm'
        );

    const signatureError =
        document.getElementById(
            'signatureError'
        );

    if (
        canvas &&
        hidden
    ) {
        const ctx =
            canvas.getContext('2d');

        ctx.lineWidth = 5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#0f172a';

        let drawing = false;
        let hasSignature = false;

        function point(event) {
            const rect =
                canvas.getBoundingClientRect();

            return {
                x:
                    (
                        event.clientX -
                        rect.left
                    )
                    *
                    (
                        canvas.width /
                        rect.width
                    ),

                y:
                    (
                        event.clientY -
                        rect.top
                    )
                    *
                    (
                        canvas.height /
                        rect.height
                    )
            };
        }

        canvas.addEventListener(
            'pointerdown',
            function (event) {
                event.preventDefault();

                drawing = true;

                const p =
                    point(event);

                ctx.beginPath();
                ctx.moveTo(
                    p.x,
                    p.y
                );

                try {
                    canvas.setPointerCapture(
                        event.pointerId
                    );
                } catch (error) {
                }
            }
        );

        canvas.addEventListener(
            'pointermove',
            function (event) {
                if (! drawing) {
                    return;
                }

                event.preventDefault();

                const p =
                    point(event);

                ctx.lineTo(
                    p.x,
                    p.y
                );

                ctx.stroke();

                hasSignature = true;
            }
        );

        function finishSignature(event) {
            if (! drawing) {
                return;
            }

            drawing = false;

            if (hasSignature) {
                hidden.value =
                    canvas.toDataURL(
                        'image/png'
                    );

                if (signatureError) {
                    signatureError.style.display =
                        'none';
                }
            }

            try {
                canvas.releasePointerCapture(
                    event.pointerId
                );
            } catch (error) {
            }
        }

        canvas.addEventListener(
            'pointerup',
            finishSignature
        );

        canvas.addEventListener(
            'pointercancel',
            finishSignature
        );

        if (clearButton) {
            clearButton.addEventListener(
                'click',
                function () {
                    ctx.clearRect(
                        0,
                        0,
                        canvas.width,
                        canvas.height
                    );

                    hidden.value = '';
                    hasSignature = false;

                    if (signatureError) {
                        signatureError.style.display =
                            'none';
                    }
                }
            );
        }

        if (form) {
            form.addEventListener(
                'submit',
                function (event) {
                    if (
                        ! hidden.value
                        || ! hasSignature
                    ) {
                        event.preventDefault();

                        if (signatureError) {
                            signatureError.style.display =
                                'block';
                        }

                        canvas.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });

                        return;
                    }

                    const submitButton =
                        document.getElementById(
                            'submitDeliveryButton'
                        );

                    if (submitButton) {
                        submitButton.disabled =
                            true;

                        submitButton.textContent =
                            'Registrando entrega...';
                    }
                }
            );
        }
    }
})();
</script>

</body>
</html>
