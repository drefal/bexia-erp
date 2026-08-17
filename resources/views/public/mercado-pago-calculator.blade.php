<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Calculadora de pagos - {{ $company->name }}</title>

    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system,
                BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f6f8fb;
            color: #172033;
        }

        .page {
            width: min(1080px, calc(100% - 32px));
            margin: 0 auto;
            padding: 32px 0 56px;
        }

        .header {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 28px;
        }

        .logo {
            max-width: 170px;
            max-height: 70px;
            object-fit: contain;
        }

        .company-name {
            margin: 0;
            font-size: 14px;
            color: #667085;
        }

        h1 {
            margin: 4px 0 0;
            font-size: clamp(28px, 5vw, 42px);
            letter-spacing: -0.03em;
        }

        .panel {
            background: #ffffff;
            border: 1px solid #e4e7ec;
            border-radius: 20px;
            padding: clamp(20px, 4vw, 34px);
            box-shadow: 0 12px 36px rgba(16, 24, 40, 0.06);
        }

        .label {
            display: block;
            margin-bottom: 8px;
            font-weight: 650;
        }

        .amount-wrap {
            position: relative;
            max-width: 440px;
        }

        .currency {
            position: absolute;
            top: 50%;
            left: 18px;
            transform: translateY(-50%);
            font-size: 22px;
            color: #667085;
        }

        #amount {
            width: 100%;
            padding: 15px 18px 15px 38px;
            border: 1px solid #cfd4dc;
            border-radius: 12px;
            font-size: 24px;
            font-weight: 650;
            outline: none;
        }

        #amount:focus {
            border-color: #667085;
            box-shadow: 0 0 0 3px rgba(102, 112, 133, .12);
        }

        .intro {
            color: #667085;
            margin: 10px 0 0;
            line-height: 1.5;
        }

        /* BEXIA_MP_PDF_BUTTON_V5_83_0E */
        .calculator-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .pdf-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            padding: 10px 18px;
            border-radius: 10px;
            background: #172033;
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            transition:
                opacity .15s ease,
                transform .15s ease;
        }

        .pdf-button:hover {
            opacity: .9;
        }

        .pdf-button:active {
            transform: translateY(1px);
        }

        .pdf-button.is-disabled {
            opacity: .35;
            pointer-events: none;
        }

        .results {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-top: 28px;
        }

        .plan {
            border: 1px solid #e4e7ec;
            border-radius: 16px;
            padding: 20px;
            background: #fff;
        }

        .months {
            color: #475467;
            font-size: 15px;
            font-weight: 650;
        }

        .monthly {
            margin-top: 8px;
            font-size: clamp(25px, 4vw, 34px);
            font-weight: 750;
            letter-spacing: -0.03em;
        }

        .monthly-note {
            color: #667085;
            font-size: 13px;
            margin-top: 2px;
        }

        .total {
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid #eaecf0;
            font-size: 14px;
            color: #475467;
        }

        .total strong {
            display: block;
            margin-top: 4px;
            color: #172033;
            font-size: 18px;
        }

        .empty {
            margin-top: 26px;
            padding: 18px;
            border-radius: 12px;
            background: #f9fafb;
            color: #667085;
        }

        .footnote {
            margin: 22px 0 0;
            font-size: 13px;
            line-height: 1.5;
            color: #667085;
        }

        /* BEXIA_CALCULATOR_BRANDING_V5_83_0C */
        /* BEXIA_PUBLIC_COUNTER_V5_83_1B */
        .public-counter {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            margin-top: 18px;
            color: #98a2b3;
            font-size: 12px;
            line-height: 1.4;
        }

        .public-counter strong {
            color: #667085;
            font-weight: 650;
        }

        .public-counter-dot {
            opacity: .55;
        }

        .bexia-powered {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
            margin-top: 26px;
            padding-top: 18px;
            border-top: 1px solid #eaecf0;
            color: #98a2b3;
            font-size: 11px;
        }

        .bexia-powered img {
            display: block;
            width: auto;
            height: 50px;
            max-width: 190px;
            object-fit: contain;
        }

        @media (max-width: 820px) {
            .results {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 560px) {
            .bexia-powered img {
                height: 44px;
                max-width: 170px;
            }

            .page {
                width: min(100% - 20px, 1080px);
                padding-top: 20px;
            }

            .header {
                align-items: flex-start;
                flex-direction: column;
            }

            .results {
                grid-template-columns: 1fr;
            }

            .panel {
                border-radius: 16px;
            }
        }
    </style>
</head>

<body>
<div class="page">
    <header class="header">
        @if ($company->getLogoUrl())
            <img
                class="logo"
                src="{{ $company->getLogoUrl() }}"
                alt="{{ $company->name }}"
            >
        @endif

        <div>
            <p class="company-name">{{ $company->name }}</p>
            <h1>Calculadora de pagos</h1>
        </div>
    </header>

    <main class="panel">
        <label class="label" for="amount">
            ¿Cuánto deseas financiar?
        </label>

        <div class="amount-wrap">
            <span class="currency">$</span>

            <input
                id="amount"
                type="number"
                min="0"
                max="5000000"
                step="0.01"
                inputmode="decimal"
                value="{{ $initialAmount > 0 ? number_format($initialAmount, 2, '.', '') : '' }}"
                placeholder="0.00"
                autocomplete="off"
            >
        </div>

        <p class="intro">
            Captura el monto para consultar las opciones de pago disponibles.
        </p>

        @if ($plans->isEmpty())
            <div class="empty">
                Por el momento no hay planes de pago publicados.
            </div>
        @else
            <div id="results" class="results"></div>

            <div class="calculator-actions">
                <a
                    id="download-pdf"
                    class="pdf-button is-disabled"
                    href="#"
                    data-base-url="{{ route(
                        'public.calculator.mercado-pago.pdf',
                        ['companySlug' => $company->slug]
                    ) }}"
                    aria-disabled="true"
                >
                    Descargar PDF
                </a>
            </div>
        @endif

        <p class="footnote">
            Las mensualidades mostradas son aproximadas.
            El total a pagar es el importe de referencia.
        </p>

        {{-- BEXIA_PUBLIC_COUNTER_V5_83_1B --}}
        @if (!empty($publicStats))
            <div class="public-counter">
                <span>
                    <strong>
                        {{ number_format($publicStats['all']['views'] ?? 0) }}
                    </strong>
                    {{ ($publicStats['all']['views'] ?? 0) === 1 ? 'visita' : 'visitas' }}
                </span>

                <span class="public-counter-dot">•</span>

                <span>
                    <strong>
                        {{ number_format($publicStats['all']['unique'] ?? 0) }}
                    </strong>
                    {{ ($publicStats['all']['unique'] ?? 0) === 1 ? 'visitante' : 'visitantes' }}
                </span>
            </div>
        @endif

        <div class="bexia-powered">
            <span>Plataforma</span>

            <img
                src="{{ asset('logo.png') }}"
                alt="Bexia ERP"
            >
        </div>
    </main>
</div>

@if ($plans->isNotEmpty())
<script>
    const plans = @json($plans);
    const amountInput = document.getElementById('amount');
    const results = document.getElementById('results');
    const downloadPdf = document.getElementById('download-pdf');

    const money = new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    function render() {
        const amount = Number(amountInput.value || 0);

        if (!Number.isFinite(amount) || amount <= 0) {
            results.innerHTML = '';

            if (downloadPdf) {
                downloadPdf.href = '#';
                downloadPdf.classList.add('is-disabled');
                downloadPdf.setAttribute('aria-disabled', 'true');
            }

            return;
        }

        if (downloadPdf) {
            const baseUrl = downloadPdf.dataset.baseUrl;

            downloadPdf.href =
                `${baseUrl}?monto=${encodeURIComponent(amount)}`;

            downloadPdf.classList.remove('is-disabled');
            downloadPdf.setAttribute('aria-disabled', 'false');
        }

        results.innerHTML = plans.map((plan) => {
            const total = amount * (1 + (Number(plan.rate) / 100));
            const monthly = total / Number(plan.months);

            return `
                <article class="plan">
                    <div class="months">
                        Paga a ${plan.months} meses
                    </div>

                    <div class="monthly">
                        ${money.format(monthly)}
                    </div>

                    <div class="monthly-note">
                        pago mensual aproximado
                    </div>

                    <div class="total">
                        Total a pagar
                        <strong>${money.format(total)}</strong>
                    </div>
                </article>
            `;
        }).join('');
    }

    amountInput.addEventListener('input', render);

    render();
</script>
@endif
</body>
</html>
