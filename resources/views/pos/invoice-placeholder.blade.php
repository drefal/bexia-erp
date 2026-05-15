<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Facturación Bexia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- BEXIA_V5528A2_PUBLIC_PORTAL_BRANDING / BEXIA_V5528A3_PUBLIC_PORTAL_BILLING_LOGO --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=5528a2" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v=5528a2">
    <style>
        :root {
            color-scheme: light;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --ok-bg: #ecfdf5;
            --ok-border: #86efac;
            --ok-text: #14532d;
            --warn-bg: #fffbeb;
            --warn-border: #fcd34d;
            --warn-text: #78350f;
            --error-bg: #fef2f2;
            --error-border: #fca5a5;
            --error-text: #7f1d1d;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: radial-gradient(circle at top, #eff6ff 0, var(--bg) 42%);
            color: var(--text);
            margin: 0;
            padding: 28px 16px;
        }

        .card {
            max-width: 760px;
            margin: 0 auto;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 20px 70px rgba(15, 23, 42, .12);
        }

                .brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 12px;
            margin-bottom: 22px;
        }

                .logo {
            width: min(320px, 86vw);
            min-height: 84px;
            background: transparent;
            border: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: visible;
            box-shadow: none;
            flex: 0 0 auto;
        }

                .logo img {
            max-width: 100%;
            max-height: 120px;
            width: auto;
            height: auto;
            object-fit: contain;
            padding: 0;
            display: block;
        }

        .brand-subtitle {
            color: var(--muted);
            font-size: 16px;
            font-weight: 800;
            line-height: 1.45;
            margin-top: 2px;
        }

        h1 {
            margin: 0;
            font-size: 28px;
            line-height: 1.1;
        }

        .muted {
            color: var(--muted);
            line-height: 1.55;
        }

        form {
            margin-top: 24px;
            display: grid;
            gap: 16px;
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #475569;
            margin-bottom: 7px;
        }

        input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            padding: 13px 14px;
            font-size: 16px;
            outline: none;
            background: white;
        }

        input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
        }

        .grid {
            display: grid;
            gap: 14px;
        }

        @media (min-width: 720px) {
            .grid {
                grid-template-columns: 1.4fr .8fr;
            }
        }

        button {
            border: 0;
            border-radius: 14px;
            padding: 14px 16px;
            background: var(--primary);
            color: white;
            font-weight: 900;
            font-size: 15px;
            cursor: pointer;
        }

        button:hover {
            background: var(--primary-dark);
        }

        .result {
            margin-top: 22px;
            border-radius: 18px;
            padding: 18px;
            border: 1px solid var(--border);
        }

        .result.ok {
            background: var(--ok-bg);
            border-color: var(--ok-border);
            color: var(--ok-text);
        }

        .result.blocked {
            background: var(--warn-bg);
            border-color: var(--warn-border);
            color: var(--warn-text);
        }

        .result.error {
            background: var(--error-bg);
            border-color: var(--error-border);
            color: var(--error-text);
        }

        .result-title {
            font-weight: 900;
            font-size: 18px;
            margin-bottom: 6px;
        }

        .summary {
            margin-top: 14px;
            display: grid;
            gap: 8px;
            font-size: 14px;
        }

        .summary div {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            border-top: 1px solid rgba(15, 23, 42, .10);
            padding-top: 8px;
        }

        .next {
            margin-top: 16px;
            padding: 14px;
            border-radius: 14px;
            background: rgba(255, 255, 255, .65);
            border: 1px solid rgba(15, 23, 42, .10);
        }

        .footer {
            margin-top: 22px;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.45;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">
            {{-- BEXIA_V5528A4_PUBLIC_PORTAL_LOGO_TEXT_BELOW --}}
            <div class="logo">
                <img src="{{ asset('logo-facturacion.png') }}?v=5528a5" alt="Bexia Facturación">
            </div>
            <div class="brand-subtitle">Valida tu ticket para solicitar factura.</div>
        </div>

        <p class="muted">
            Captura el folio y el total exacto del ticket. Esta validación evita consultar información de tickets que no te corresponden.
        </p>

        <form method="POST" action="{{ route('public.invoice.validate') }}">
            @csrf

            <div class="grid">
                <div>
                    <label for="ticket">Folio del ticket</label>
                    <input
                        id="ticket"
                        name="ticket"
                        value="{{ old('ticket', $ticket ?? '') }}"
                        placeholder="Ej. PDVFL-20260515-00001"
                        autocomplete="off"
                        required
                    >
                </div>

                <div>
                    <label for="total">Total</label>
                    <input
                        id="total"
                        name="total"
                        value="{{ old('total', $total ?? '') }}"
                        placeholder="Ej. 123.45"
                        inputmode="decimal"
                        autocomplete="off"
                        required
                    >
                </div>
            </div>

            <button type="submit">Validar ticket</button>
        </form>

        @if($result)
            @php
                $class = ($result['ok'] ?? false)
                    ? 'ok'
                    : (($result['type'] ?? '') === 'blocked' ? 'blocked' : 'error');
            @endphp

            <div class="result {{ $class }}">
                <div class="result-title">{{ $result['title'] ?? 'Resultado' }}</div>
                <div>{{ $result['message'] ?? '' }}</div>

                @if(! empty($result['order_number']))
                    <div class="summary">
                        <div>
                            <span>Ticket</span>
                            <strong>{{ $result['order_number'] }}</strong>
                        </div>
                        <div>
                            <span>Total</span>
                            <strong>${{ number_format((float) ($result['order_total'] ?? 0), 2) }}</strong>
                        </div>
                        <div>
                            <span>Estado fiscal</span>
                            <strong>{{ $result['fiscal_label'] ?? '—' }}</strong>
                        </div>
                    </div>
                @endif

                @if(($result['ok'] ?? false) === true)
                    <div class="next">
                        <strong>Siguiente paso:</strong>
                        captura de datos fiscales del receptor. Esta parte se activará en la siguiente versión del portal.
                    </div>
                @endif
            </div>
        @endif

        <div class="footer">
            Bexia ERP · Portal de autofacturación. Si tu ticket ya fue facturado, cancelado, devuelto o está dentro de una factura global, el sistema no permitirá generar otra factura.
        </div>
    </div>
</body>
</html>
