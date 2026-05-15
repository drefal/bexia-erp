<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Facturación Bexia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- BEXIA_V5528A5_PUBLIC_INVOICE_PORTAL_FINAL_FORMAT --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=5528a5" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v=5528a5">

    <style>
        :root {
            color-scheme: light;
            --bg: #f6f8fb;
            --card: #ffffff;
            --text: #111827;
            --muted: #64748b;
            --border: #e5e7eb;
            --input-border: #cbd5e1;
            --primary: #1f5eff;
            --primary-dark: #1748c7;
            --shadow: 0 22px 70px rgba(15, 23, 42, .13);

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

        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            padding: 28px 16px;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(31, 94, 255, .13), transparent 34%),
                radial-gradient(circle at top right, rgba(15, 23, 42, .08), transparent 28%),
                var(--bg);
        }

        .page {
            width: 100%;
            max-width: 820px;
            margin: 0 auto;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 14px;
            margin-bottom: 28px;
        }

        .brand-logo {
            width: min(360px, 88vw);
            min-height: 86px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-logo img {
            display: block;
            max-width: 100%;
            max-height: 132px;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        .brand-subtitle {
            max-width: 520px;
            margin: 0 auto;
            color: var(--muted);
            font-size: 17px;
            font-weight: 800;
            line-height: 1.45;
        }

        .intro {
            max-width: 620px;
            margin: 0 auto 24px;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.6;
            text-align: center;
        }

        .form-panel {
            border: 1px solid #eef2f7;
            background: #fbfdff;
            border-radius: 22px;
            padding: 20px;
        }

        form {
            display: grid;
            gap: 16px;
        }

        .grid {
            display: grid;
            gap: 14px;
        }

        @media (min-width: 720px) {
            .grid {
                grid-template-columns: 1.35fr .85fr;
            }
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #475569;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .045em;
            text-transform: uppercase;
        }

        input {
            width: 100%;
            border: 1px solid var(--input-border);
            border-radius: 15px;
            padding: 14px 15px;
            background: #fff;
            color: var(--text);
            font-size: 16px;
            outline: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        input::placeholder {
            color: #94a3b8;
        }

        input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(31, 94, 255, .13);
        }

        .button-row {
            display: flex;
            justify-content: center;
        }

        button {
            width: 100%;
            max-width: 300px;
            border: 0;
            border-radius: 16px;
            padding: 15px 18px;
            background: var(--primary);
            color: #fff;
            font-size: 15px;
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 12px 30px rgba(31, 94, 255, .23);
        }

        button:hover {
            background: var(--primary-dark);
        }

        .hint {
            margin-top: 12px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.45;
            text-align: center;
        }

        .result {
            margin-top: 22px;
            border-radius: 20px;
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
            margin-bottom: 7px;
            font-size: 18px;
            font-weight: 900;
        }

        .result-message {
            line-height: 1.55;
        }

        .summary {
            margin-top: 15px;
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

        .summary span {
            opacity: .82;
        }

        .summary strong {
            text-align: right;
        }

        .next {
            margin-top: 16px;
            padding: 14px;
            border-radius: 15px;
            background: rgba(255, 255, 255, .65);
            border: 1px solid rgba(15, 23, 42, .10);
            line-height: 1.55;
        }

        .footer {
            margin-top: 18px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
            text-align: center;
        }

        @media (max-width: 520px) {
            body {
                padding: 18px 10px;
            }

            .card {
                padding: 22px 16px;
                border-radius: 24px;
            }

            .brand-logo {
                width: min(300px, 86vw);
                min-height: 72px;
            }

            .brand-logo img {
                max-height: 110px;
            }

            .brand-subtitle {
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="card">
            <header class="brand">
                <div class="brand-logo">
                    <img src="{{ asset('logo-facturacion.png') }}?v=5528a5" alt="Bexia Facturación">
                </div>

                <div class="brand-subtitle">
                    Valida tu ticket para solicitar factura.
                </div>
            </header>

            <p class="intro">
                Captura el folio y el total exacto de tu ticket. Esta validación ayuda a proteger tu información y evita facturar tickets incorrectos.
            </p>

            <section class="form-panel">
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
                            <label for="total">Total del ticket</label>
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

                    <div class="button-row">
                        <button type="submit">Validar ticket</button>
                    </div>
                </form>

                <div class="hint">
                    El total debe capturarse exactamente como aparece en el ticket.
                </div>
            </section>

            @if($result)
                @php
                    $class = ($result['ok'] ?? false)
                        ? 'ok'
                        : (($result['type'] ?? '') === 'blocked' ? 'blocked' : 'error');
                @endphp

                <section class="result {{ $class }}">
                    <div class="result-title">{{ $result['title'] ?? 'Resultado' }}</div>
                    <div class="result-message">{{ $result['message'] ?? '' }}</div>

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
                </section>
            @endif

            <footer class="footer">
                Bexia ERP · Portal de autofacturación. Si tu ticket ya fue facturado, cancelado, devuelto o está dentro de una factura global, el sistema no permitirá generar otra factura.
            </footer>
        </section>
    </main>
</body>
</html>
