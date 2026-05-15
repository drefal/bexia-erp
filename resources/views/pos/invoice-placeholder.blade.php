<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Facturación Bexia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- BEXIA_V5528C14_PUBLIC_INVOICE_PORTAL_CLEAN_VIEW_PROD --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=5528c14" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v=5528c14">

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
            --ok-border: #4ade80;
            --ok-text: #065f46;

            --warn-bg: #fffbeb;
            --warn-border: #f59e0b;
            --warn-text: #92400e;

            --info-bg: #eff6ff;
            --info-border: #93c5fd;
            --info-text: #1e3a8a;
        }

        * { box-sizing: border-box; }

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
            max-width: 850px;
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
            max-width: 640px;
            margin: 0 auto 24px;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.6;
            text-align: center;
        }

        .panel {
            border: 1px solid #eef2f7;
            background: #fbfdff;
            border-radius: 22px;
            padding: 20px;
            margin-top: 18px;
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
            .grid.two { grid-template-columns: 1.35fr .85fr; }
            .grid.fiscal { grid-template-columns: 1fr 1fr; }
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

        input, select {
            width: 100%;
            border: 1px solid var(--input-border);
            border-radius: 15px;
            padding: 14px 15px;
            background: #fff;
            color: var(--text);
            font-size: 16px;
            outline: none;
        }

        input:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(31, 94, 255, .13);
        }

        .button-row {
            display: flex;
            justify-content: center;
        }

        button {
            width: 100%;
            max-width: 330px;
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

        button:hover { background: var(--primary-dark); }

        .hint {
            margin-top: 12px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.45;
            text-align: center;
        }

        .section-title {
            margin: 0 0 14px;
            font-size: 18px;
            font-weight: 900;
        }

        .result {
            margin-top: 22px;
            border-radius: 20px;
            padding: 18px;
            border: 1px solid var(--border);
        }

        .result-success,
        .result-ok {
            background: var(--ok-bg);
            border-color: var(--ok-border);
            color: var(--ok-text);
        }

        .result-error,
        .result-blocked,
        .result-warning {
            background: var(--warn-bg);
            border-color: var(--warn-border);
            color: var(--warn-text);
        }

        .result-info {
            background: var(--info-bg);
            border-color: var(--info-border);
            color: var(--info-text);
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

        .summary strong { text-align: right; }

        .portal-result-downloads {
            margin-top: 16px;
            padding: 16px;
            border: 1px solid #bfdbfe;
            border-radius: 20px;
            background: #eff6ff;
            color: #1e3a8a;
        }

        .portal-result-downloads-title {
            font-weight: 900;
            margin-bottom: 10px;
            color: #1e3a8a;
        }

        .portal-result-downloads-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }

        @media (min-width: 720px) {
            .portal-result-downloads-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .portal-result-download-link {
            display: block;
            width: 100%;
            text-align: center;
            text-decoration: none;
            border-radius: 14px;
            padding: 12px 14px;
            background: #1f5eff;
            color: #ffffff;
            font-weight: 900;
            box-shadow: 0 10px 22px rgba(31, 94, 255, .18);
        }

        .portal-result-download-link:hover {
            background: #1748c7;
        }

        .portal-actions-clean {
            margin-top: 14px;
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .portal-home-link-clean {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 260px;
            border-radius: 16px;
            padding: 13px 18px;
            background: #ffffff;
            color: #1f5eff;
            border: 1px solid #bfdbfe;
            font-weight: 900;
            text-decoration: none;
            box-shadow: 0 10px 24px rgba(31, 94, 255, .10);
        }

        .portal-home-link-clean:hover {
            background: #eff6ff;
            border-color: #1f5eff;
        }

        .footer {
            margin-top: 18px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
            text-align: center;
        }

        @media (max-width: 520px) {
            body { padding: 18px 10px; }
            .card { padding: 22px 16px; border-radius: 24px; }
            .brand-logo { width: min(300px, 86vw); min-height: 72px; }
            .brand-logo img { max-height: 110px; }
            .brand-subtitle { font-size: 15px; }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="card">
            <header class="brand">
                <div class="brand-logo">
                    <img src="{{ asset('logo-facturacion.png') }}?v=5528c14" alt="Bexia Facturación">
                </div>

                <div class="brand-subtitle">
                    Valida tu ticket para solicitar factura.
                </div>
            </header>

            <p class="intro">
                Captura el folio y el total exacto de tu ticket. Si el ticket es elegible, podrás capturar tus datos fiscales para crear tu factura.
            </p>

            <section class="panel">
                <form method="POST" action="{{ route('public.invoice.validate') }}">
                    @csrf

                    <div class="grid two">
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
                    $resultType = $result['type'] ?? 'success';

                    $class = match ($resultType) {
                        'success' => 'result-success',
                        'error', 'blocked', 'warning' => 'result-warning',
                        'info' => 'result-info',
                        default => (($result['ok'] ?? false) ? 'result-ok' : 'result-warning'),
                    };

                    $showFiscalForm = (bool) (($result['show_fiscal_form'] ?? false) && ! ($result['completed'] ?? false));

                    $orderNumber = $result['order_number'] ?? $result['ticket'] ?? null;
                    $orderTotal = $result['order_total'] ?? null;
                @endphp

                <section class="result {{ $class }}">
                    <div class="result-title">{{ $result['title'] ?? 'Resultado' }}</div>
                    <div class="result-message">{{ $result['message'] ?? '' }}</div>

                    @if($orderNumber)
                        <div class="summary">
                            <div>
                                <span>Ticket</span>
                                <strong>{{ $orderNumber }}</strong>
                            </div>

                            @if($orderTotal !== null)
                                <div>
                                    <span>Total</span>
                                    <strong>${{ number_format((float) $orderTotal, 2) }}</strong>
                                </div>
                            @endif

                            <div>
                                <span>Estado fiscal</span>
                                <strong>{{ $result['fiscal_label'] ?? '—' }}</strong>
                            </div>

                            @if(! empty($result['invoice_number']))
                                <div>
                                    <span>Factura</span>
                                    <strong>{{ $result['invoice_number'] }}</strong>
                                </div>
                            @endif

                            @if(! empty($result['email']))
                                <div>
                                    <span>Correo</span>
                                    <strong>{{ $result['email'] }}</strong>
                                </div>
                            @endif
                        </div>
                    @endif
                </section>

                {{-- BEXIA_V5528C14_PORTAL_DOWNLOADS_AFTER_RESULT_PROD --}}
                @if(! $showFiscalForm && ! empty($result['download_links']))
                    <section class="portal-result-downloads">
                        <div class="portal-result-downloads-title">Archivos de tu factura</div>

                        <div class="portal-result-downloads-grid">
                            @foreach($result['download_links'] as $link)
                                <a class="portal-result-download-link" href="{{ $link['url'] }}" target="_blank" rel="noopener">
                                    {{ $link['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- BEXIA_V5528C14_PORTAL_BACK_HOME_AFTER_FINAL_RESULT_PROD --}}
                @if(! $showFiscalForm && (! empty($result['invoice_id']) || ! empty($result['invoice_number'])))
                    <div class="portal-actions-clean">
                        <a class="portal-home-link-clean" href="{{ route('public.invoice-placeholder') }}">
                            Regresar al inicio de facturación
                        </a>
                    </div>
                @endif

                @if($showFiscalForm)
                    <section class="panel">
                        <h2 class="section-title">Datos fiscales</h2>

                        <form method="POST" action="{{ route('public.invoice.request') }}">
                            @csrf

                            <input type="hidden" name="ticket" value="{{ $ticket ?? '' }}">
                            <input type="hidden" name="total" value="{{ $total ?? '' }}">

                            <div class="grid fiscal">
                                <div>
                                    <label for="rfc">RFC</label>
                                    <input
                                        id="rfc"
                                        name="rfc"
                                        value="{{ old('rfc', $fiscalData['rfc'] ?? '') }}"
                                        placeholder="Ej. XAXX010101000"
                                        maxlength="13"
                                        required
                                    >
                                </div>

                                <div>
                                    <label for="fiscal_name">Razón social / nombre fiscal</label>
                                    <input
                                        id="fiscal_name"
                                        name="fiscal_name"
                                        value="{{ old('fiscal_name', $fiscalData['fiscal_name'] ?? '') }}"
                                        placeholder="Nombre fiscal"
                                        required
                                    >
                                </div>

                                <div>
                                    <label for="postal_code">Código postal fiscal</label>
                                    <input
                                        id="postal_code"
                                        name="postal_code"
                                        value="{{ old('postal_code', $fiscalData['postal_code'] ?? '') }}"
                                        placeholder="Ej. 06020"
                                        maxlength="5"
                                        inputmode="numeric"
                                        required
                                    >
                                </div>

                                <div>
                                    <label for="tax_regime_code">Régimen fiscal</label>
                                    <select id="tax_regime_code" name="tax_regime_code" required>
                                        <option value="">Selecciona régimen</option>
                                        @foreach($taxRegimeOptions as $code => $label)
                                            <option value="{{ $code }}" @selected(old('tax_regime_code', $fiscalData['tax_regime_code'] ?? '') === $code)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="cfdi_use_code">Uso CFDI</label>
                                    <select id="cfdi_use_code" name="cfdi_use_code" required>
                                        <option value="">Selecciona uso CFDI</option>
                                        @foreach($cfdiUseOptions as $code => $label)
                                            <option value="{{ $code }}" @selected(old('cfdi_use_code', $fiscalData['cfdi_use_code'] ?? '') === $code)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="email">Correo electrónico</label>
                                    <input
                                        id="email"
                                        name="email"
                                        value="{{ old('email', $fiscalData['email'] ?? '') }}"
                                        placeholder="correo@dominio.com"
                                        type="email"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="button-row">
                                <button type="submit">Solicitar factura</button>
                            </div>
                        </form>

                        <div class="hint">
                            Si los datos fiscales fueron rechazados, corrígelos exactamente como aparecen en tu Constancia de Situación Fiscal y vuelve a solicitar la factura.
                        </div>
                    </section>
                @endif
            @endif

            <footer class="footer">
                Bexia ERP · Portal de autofacturación. Si tu ticket ya fue facturado, cancelado, devuelto o está dentro de una factura global, el sistema no permitirá generar otra factura.
            </footer>
        </section>
    </main>
</body>
</html>
