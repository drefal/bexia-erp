<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asistencia Bexia</title>

    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            margin: 0;
            background: #f3f4f6;
            color: #111827;
        }

        .wrap {
            max-width: 520px;
            margin: 0 auto;
            padding: 24px;
        }

        .card {
            background: white;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .08);
            margin-bottom: 16px;
        }

        .muted {
            color: #6b7280;
            font-size: 14px;
        }

        .title {
            font-size: 24px;
            font-weight: 800;
            margin: 0 0 6px;
        }

        .employee {
            font-size: 20px;
            font-weight: 700;
            margin-top: 10px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .box {
            background: #f9fafb;
            border-radius: 12px;
            padding: 12px;
        }

        .label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .value {
            font-size: 18px;
            font-weight: 700;
            margin-top: 4px;
        }

        .btn {
            width: 100%;
            border: 0;
            border-radius: 14px;
            padding: 16px;
            font-size: 17px;
            font-weight: 800;
            cursor: pointer;
            color: white;
            background: #16a34a;
            margin-top: 10px;
        }

        .btn.meal-out {
            background: #2563eb;
        }

        .btn.meal-in {
            background: #16a34a;
        }

        .btn.clock-out {
            background: #f59e0b;
        }

        .btn.done {
            background: #6b7280;
            cursor: not-allowed;
        }

        .alert {
            border-radius: 14px;
            padding: 12px 14px;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .ok {
            background: #dcfce7;
            color: #166534;
        }

        .warn {
            background: #fef3c7;
            color: #92400e;
        }

        .danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .small {
            font-size: 12px;
            color: #6b7280;
            margin-top: 12px;
            line-height: 1.45;
        }

        .break-info {
            margin-top: 14px;
            padding: 10px 12px;
            border-radius: 12px;
            background: #eff6ff;
            color: #1e40af;
            font-size: 13px;
        }
    </style>
</head>

<body>
<div class="wrap">

    <div class="card">
        <p class="title">Asistencia Bexia</p>
        <div class="muted">Registro por QR de credencial</div>

        <div class="employee">{{ $employee->name }}</div>

        <div class="muted">
            {{ $employee->employee_number
                ? 'No. empleado: ' . $employee->employee_number
                : 'Empleado #' . $employee->id }}
        </div>
    </div>

    @if (session('success'))
        <div class="alert ok">{{ session('success') }}</div>
    @endif

    @if (session('warning'))
        <div class="alert warn">{{ session('warning') }}</div>
    @endif

    @if ($errors->has('attendance'))
        <div class="alert danger">{{ $errors->first('attendance') }}</div>
    @endif

    @if (! $hasGeofence)
        <div class="alert warn">
            No hay geocerca activa configurada para este empleado/empresa/sucursal.
            El registro se guardará como pendiente de revisión.
        </div>
    @endif

    @php
        $hasIn = filled($attendance?->clock_in_at);
        $hasMealOut = filled($attendance?->meal_out_at);
        $hasMealIn = filled($attendance?->meal_in_at);
        $hasOut = filled($attendance?->clock_out_at);

        $breakMinutes = max(0, (int) ($attendance?->break_minutes ?? 0));
    @endphp

    <div class="card">

        @if ($breakMinutes > 0)
            <div class="grid">
                <div class="box">
                    <div class="label">Entrada</div>
                    <div class="value">
                        {{ $attendance?->clock_in_at?->format('H:i') ?: '-' }}
                    </div>
                </div>

                <div class="box">
                    <div class="label">Salida a comida</div>
                    <div class="value">
                        {{ $attendance?->meal_out_at?->format('H:i') ?: '-' }}
                    </div>
                </div>

                <div class="box">
                    <div class="label">Regreso de comida</div>
                    <div class="value">
                        {{ $attendance?->meal_in_at?->format('H:i') ?: '-' }}
                    </div>
                </div>

                <div class="box">
                    <div class="label">Salida</div>
                    <div class="value">
                        {{ $attendance?->clock_out_at?->format('H:i') ?: '-' }}
                    </div>
                </div>
            </div>

            <div class="break-info">
                Comida programada: {{ $breakMinutes }} minutos.
            </div>
        @else
            <div class="grid">
                <div class="box">
                    <div class="label">Entrada</div>
                    <div class="value">
                        {{ $attendance?->clock_in_at?->format('H:i') ?: '-' }}
                    </div>
                </div>

                <div class="box">
                    <div class="label">Salida</div>
                    <div class="value">
                        {{ $attendance?->clock_out_at?->format('H:i') ?: '-' }}
                    </div>
                </div>
            </div>
        @endif

        <form
            id="attendanceForm"
            method="POST"
            action="{{ route('attendance.employee.store', ['token' => $token]) }}"
            style="margin-top:16px;"
        >
            @csrf

            <input type="hidden" name="action" id="attendance_action">
            <input type="hidden" name="latitude" id="latitude">
            <input type="hidden" name="longitude" id="longitude">
            <input type="hidden" name="accuracy" id="accuracy">
            <input type="hidden" name="device_fingerprint" id="device_fingerprint">
            <input type="hidden" name="device_info" id="device_info">

            @if (! $hasIn)

                <button
                    class="btn attendance-action"
                    type="button"
                    data-action="clock_in"
                >
                    Registrar entrada
                </button>

            @elseif ($hasOut)

                <button class="btn done" type="button" disabled>
                    Registro completo
                </button>

            @elseif ($breakMinutes <= 0)

                <button
                    class="btn clock-out attendance-action"
                    type="button"
                    data-action="clock_out"
                >
                    Terminar jornada
                </button>

            @elseif (! $hasMealOut)

                <button
                    class="btn meal-out attendance-action"
                    type="button"
                    data-action="meal_out"
                >
                    Salir a comer
                </button>

                <button
                    class="btn clock-out attendance-action"
                    type="button"
                    data-action="clock_out"
                >
                    Terminar jornada
                </button>

            @elseif (! $hasMealIn)

                <button
                    class="btn meal-in attendance-action"
                    type="button"
                    data-action="meal_in"
                >
                    Regresar de comida
                </button>

                <button
                    class="btn clock-out attendance-action"
                    type="button"
                    data-action="clock_out"
                >
                    Terminar jornada
                </button>

            @else

                <button
                    class="btn clock-out attendance-action"
                    type="button"
                    data-action="clock_out"
                >
                    Terminar jornada
                </button>

            @endif
        </form>

        <div id="locationMessage" class="small">
            Al registrar, el navegador pedirá permiso para usar la ubicación.
            Esto permite validar si estás dentro de la geocerca autorizada.
        </div>
    </div>

    <div class="small">
        Si el celular no tiene datos o no puede obtener ubicación,
        usa la tablet de la empresa o solicita apoyo a RRHH.
    </div>
</div>

<script>
(function () {
    const buttons = Array.from(
        document.querySelectorAll('.attendance-action')
    );

    const form = document.getElementById('attendanceForm');
    const msg = document.getElementById('locationMessage');
    const actionInput = document.getElementById('attendance_action');

    if (!buttons.length || !form) {
        return;
    }

    async function buildDeviceFingerprint() {
        const info = {
            userAgent: navigator.userAgent || '',
            language: navigator.language || '',
            languages: navigator.languages || [],
            platform: navigator.platform || '',
            vendor: navigator.vendor || '',
            timezone: (
                Intl.DateTimeFormat().resolvedOptions().timeZone || ''
            ),
            screen: {
                width: screen.width || null,
                height: screen.height || null,
                availWidth: screen.availWidth || null,
                availHeight: screen.availHeight || null,
                colorDepth: screen.colorDepth || null,
                pixelDepth: screen.pixelDepth || null,
            },
            hardwareConcurrency: navigator.hardwareConcurrency || null,
            maxTouchPoints: navigator.maxTouchPoints || null,
            cookieEnabled: navigator.cookieEnabled || false,
        };

        document.getElementById('device_info').value =
            JSON.stringify(info);

        const raw = JSON.stringify(info);

        if (
            window.crypto
            && window.crypto.subtle
            && window.TextEncoder
        ) {
            const bytes = new TextEncoder().encode(raw);

            const hashBuffer = await crypto.subtle.digest(
                'SHA-256',
                bytes
            );

            const hashArray = Array.from(
                new Uint8Array(hashBuffer)
            );

            return hashArray
                .map(function (b) {
                    return b.toString(16).padStart(2, '0');
                })
                .join('');
        }

        let hash = 0;

        for (let i = 0; i < raw.length; i++) {
            hash = ((hash << 5) - hash) + raw.charCodeAt(i);
            hash |= 0;
        }

        return 'fallback-' + Math.abs(hash).toString(16);
    }

    function disableButtons() {
        buttons.forEach(function (button) {
            button.disabled = true;
        });
    }

    function submitWithoutLocation(message) {
        msg.textContent = message;
        form.submit();
    }

    buttons.forEach(function (button) {
        button.addEventListener('click', async function () {
            const action = button.dataset.action || '';

            if (!action) {
                return;
            }

            actionInput.value = action;

            disableButtons();

            msg.textContent = 'Identificando dispositivo...';

            try {
                document.getElementById(
                    'device_fingerprint'
                ).value = await buildDeviceFingerprint();
            } catch (e) {
                document.getElementById(
                    'device_fingerprint'
                ).value = '';
            }

            msg.textContent = 'Solicitando ubicación...';

            if (!navigator.geolocation) {
                submitWithoutLocation(
                    'Este navegador no soporta geolocalización. '
                    + 'Se registrará pendiente de revisión.'
                );

                return;
            }

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    document.getElementById('latitude').value =
                        position.coords.latitude;

                    document.getElementById('longitude').value =
                        position.coords.longitude;

                    document.getElementById('accuracy').value =
                        Math.round(position.coords.accuracy || 0);

                    msg.textContent =
                        'Ubicación obtenida. Registrando asistencia...';

                    form.submit();
                },
                function (error) {
                    let message =
                        'No se pudo obtener ubicación. '
                        + 'Se registrará pendiente de revisión.';

                    if (error.code === 1) {
                        message =
                            'Permiso de ubicación rechazado. '
                            + 'Se registrará pendiente de revisión.';
                    } else if (error.code === 2) {
                        message =
                            'Ubicación no disponible. '
                            + 'Se registrará pendiente de revisión.';
                    } else if (error.code === 3) {
                        message =
                            'Tiempo agotado al obtener ubicación. '
                            + 'Se registrará pendiente de revisión.';
                    }

                    submitWithoutLocation(message);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 12000,
                    maximumAge: 0
                }
            );
        });
    });
})();
</script>

</body>
</html>
