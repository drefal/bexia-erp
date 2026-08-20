<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Calculadora de pagos -
        {{ $company->name }}
    </title>

    <style>
        :root {
            color-scheme: light;
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
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
            width:
                min(
                    1120px,
                    calc(100% - 32px)
                );
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
            color: #667085;
            font-size: 14px;
        }

        h1 {
            margin: 4px 0 0;
            font-size:
                clamp(
                    28px,
                    5vw,
                    42px
                );
            letter-spacing: -.03em;
        }

        .panel {
            background: #fff;
            border: 1px solid #e4e7ec;
            border-radius: 20px;
            padding:
                clamp(
                    20px,
                    4vw,
                    34px
                );
            box-shadow:
                0 12px 36px
                rgba(
                    16,
                    24,
                    40,
                    .06
                );
        }

        .mode-area {
            max-width: 620px;
            margin-bottom: 24px;
        }

        .label {
            display: block;
            margin-bottom: 8px;
            font-weight: 650;
        }

        .segmented {
            display: grid;
            grid-template-columns:
                repeat(
                    2,
                    minmax(0,1fr)
                );
            gap: 4px;
            padding: 4px;
            border:
                1px solid
                #e4e7ec;
            border-radius: 12px;
            background: #f2f4f7;
        }

        .segment {
            min-height: 44px;
            border: 0;
            border-radius: 9px;
            padding: 9px 14px;
            background: transparent;
            color: #667085;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .segment.active {
            background: #172033;
            color: #fff;
        }

        .mode-help {
            margin: 7px 0 0;
            color: #667085;
            font-size: 12px;
            line-height: 1.5;
        }

        .amount-wrap {
            position: relative;
            max-width: 440px;
        }

        .currency {
            position: absolute;
            top: 50%;
            left: 18px;
            transform:
                translateY(-50%);
            color: #667085;
            font-size: 22px;
        }

        #amount {
            width: 100%;
            padding:
                15px
                18px
                15px
                38px;
            border:
                1px solid
                #cfd4dc;
            border-radius: 12px;
            outline: none;
            font-size: 24px;
            font-weight: 650;
        }

        #amount:focus {
            border-color: #667085;
            box-shadow:
                0 0 0 3px
                rgba(
                    102,
                    112,
                    133,
                    .12
                );
        }

        .intro {
            margin: 10px 0 0;
            color: #667085;
            line-height: 1.5;
        }

        .results {
            display: grid;
            grid-template-columns:
                repeat(
                    2,
                    minmax(0,1fr)
                );
            gap: 18px;
            margin-top: 30px;
        }

        .term-card {
            border:
                1px solid
                #e4e7ec;
            border-radius: 18px;
            overflow: hidden;
            background: #fff;
        }

        .term-title {
            padding: 15px 18px;
            border-bottom:
                1px solid
                #eaecf0;
            background: #f9fafb;
            color: #344054;
            font-size: 17px;
            font-weight: 750;
        }

        .card-options {
            display: grid;
            grid-template-columns:
                repeat(
                    2,
                    minmax(0,1fr)
                );
        }

        .card-option {
            padding: 18px;
        }

        .card-option + .card-option {
            border-left:
                1px solid
                #eaecf0;
        }

        .card-type {
            color: #475467;
            font-size: 13px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .payment {
            margin-top: 8px;
            font-size:
                clamp(
                    23px,
                    3vw,
                    31px
                );
            font-weight: 780;
            letter-spacing: -.03em;
        }

        .payment-note {
            margin-top: 2px;
            color: #667085;
            font-size: 12px;
        }

        .amount-lines {
            display: grid;
            gap: 7px;
            margin-top: 15px;
            padding-top: 13px;
            border-top:
                1px solid
                #eaecf0;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            color: #667085;
            font-size: 12px;
        }

        .row strong {
            color: #172033;
        }

        .fees {
            display: grid;
            gap: 5px;
            margin-top: 13px;
            padding-top: 12px;
            border-top:
                1px dashed
                #e4e7ec;
        }

        .fee-total {
            color: #344054;
            font-weight: 750;
        }

        /*
         * BEXIA_MP_PDF_PLAN_PICKER_V5_83_2A5
         */
        .pdf-plan-picker {
            margin-top: 24px;
            padding: 15px 17px;
            border: 1px solid #e4e7ec;
            border-radius: 14px;
            background: #f9fafb;
        }

        .pdf-plan-picker-title {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            color: #344054;
        }

        .pdf-plan-picker-help {
            margin: 4px 0 10px;
            font-size: 11px;
            line-height: 1.45;
            color: #667085;
        }

        .pdf-plan-options {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .pdf-plan-option {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 34px;
            padding: 6px 10px;
            border: 1px solid #d0d5dd;
            border-radius: 9px;
            background: #fff;
            color: #344054;
            font-size: 12px;
            font-weight: 650;
            cursor: pointer;
        }

        .pdf-plan-option input {
            margin: 0;
            accent-color: #172033;
        }

        .pdf-plan-option:has(input:checked) {
            border-color: #172033;
            background: #f2f4f7;
        }

        .calculator-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 22px;
        }

        .pdf-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 10px 18px;
            border-radius: 10px;
            background: #172033;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
        }

        .pdf-button.is-disabled {
            opacity: .35;
            pointer-events: none;
        }

        .footnote {
            margin: 22px 0 0;
            color: #667085;
            font-size: 13px;
            line-height: 1.55;
        }

        .public-counter {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            margin-top: 18px;
            color: #98a2b3;
            font-size: 12px;
        }

        .public-counter strong {
            color: #667085;
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
            border-top:
                1px solid
                #eaecf0;
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

        @media (
            max-width: 820px
        ) {
            .results {
                grid-template-columns: 1fr;
            }
        }

        @media (
            max-width: 560px
        ) {
            .page {
                width:
                    min(
                        100% - 20px,
                        1120px
                    );
                padding-top: 20px;
            }

            .header {
                align-items: flex-start;
                flex-direction: column;
            }

            .panel {
                border-radius: 16px;
            }

            .card-options {
                grid-template-columns: 1fr;
            }

            .card-option + .card-option {
                border-left: 0;
                border-top:
                    1px solid
                    #eaecf0;
            }

            .bexia-powered img {
                height: 44px;
                max-width: 170px;
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
            <p class="company-name">
                {{ $company->name }}
            </p>

            <h1>
                Calculadora de pagos
            </h1>
        </div>
    </header>

    <main class="panel">
        <div class="mode-area">
            <span class="label">
                ¿Qué deseas calcular?
            </span>

            <div class="segmented">
                <button
                    type="button"
                    class="segment"
                    data-mode="receive"
                >
                    Monto recibido
                </button>

                <button
                    type="button"
                    class="segment"
                    data-mode="charge"
                >
                    Monto cobrado
                </button>
            </div>

            <p
                id="mode-help"
                class="mode-help"
            ></p>
        </div>

        <label
            id="amount-label"
            class="label"
            for="amount"
        >
            Monto
        </label>

        <div class="amount-wrap">
            <span class="currency">
                $
            </span>

            <input
                id="amount"
                type="number"
                min="0"
                max="5000000"
                step="0.01"
                inputmode="decimal"
                value="{{
                    $initialAmount > 0
                        ? number_format(
                            $initialAmount,
                            2,
                            '.',
                            ''
                        )
                        : ''
                }}"
                placeholder="0.00"
                autocomplete="off"
            >
        </div>

        <p
            id="intro"
            class="intro"
        ></p>

        <div
            id="results"
            class="results"
        ></div>

        {{-- BEXIA_MP_PDF_PLAN_PICKER_V5_83_2A5 --}}
        <div class="pdf-plan-picker">
            <p class="pdf-plan-picker-title">
                Planes a incluir en el PDF
            </p>

            <p class="pdf-plan-picker-help">
                Marca únicamente los planes que quieras
                imprimir. Si no seleccionas ninguno,
                el PDF incluirá todos.
            </p>

            <div class="pdf-plan-options">
                @foreach ($terms as $term)
                    <label class="pdf-plan-option">
                        <input
                            type="checkbox"
                            data-pdf-plan
                            value="{{ $term['months'] }}"
                        >

                        <span>
                            {{
                                $term['months'] === 1
                                    ? '1 pago'
                                    : $term['months'] .
                                        ' meses'
                            }}
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="calculator-actions">
            <a
                id="download-pdf"
                class="pdf-button is-disabled"
                href="#"
                data-base-url="{{
                    route(
                        'public.calculator.mercado-pago.pdf',
                        [
                            'companySlug' =>
                                $company->slug
                        ]
                    )
                }}"
                aria-disabled="true"
            >
                Descargar PDF
            </a>
        </div>

        <p class="footnote">
            Cada plazo muestra dos escenarios:
            tarjeta de crédito y tarjeta de débito.
            El financiamiento adicional es el mismo
            para ambas; únicamente cambia la tasa
            de deslizamiento.
        </p>

        @if (!empty($publicStats))
            <div class="public-counter">
                <span>
                    <strong>
                        {{
                            number_format(
                                $publicStats[
                                    'all'
                                ][
                                    'views'
                                ] ?? 0
                            )
                        }}
                    </strong>

                    {{
                        (
                            $publicStats[
                                'all'
                            ][
                                'views'
                            ] ?? 0
                        ) === 1
                            ? 'visita'
                            : 'visitas'
                    }}
                </span>

                <span
                    class="public-counter-dot"
                >
                    •
                </span>

                <span>
                    <strong>
                        {{
                            number_format(
                                $publicStats[
                                    'all'
                                ][
                                    'unique'
                                ] ?? 0
                            )
                        }}
                    </strong>

                    {{
                        (
                            $publicStats[
                                'all'
                            ][
                                'unique'
                            ] ?? 0
                        ) === 1
                            ? 'visitante'
                            : 'visitantes'
                    }}
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

<script>
    const terms = @json($terms);

    const creditSwipe =
        Number(@json($creditSwipe));

    const debitSwipe =
        Number(@json($debitSwipe));

    let mode =
        @json($initialMode);

    const amountInput =
        document.getElementById(
            'amount'
        );

    const amountLabel =
        document.getElementById(
            'amount-label'
        );

    const modeHelp =
        document.getElementById(
            'mode-help'
        );

    const intro =
        document.getElementById(
            'intro'
        );

    const results =
        document.getElementById(
            'results'
        );

    const downloadPdf =
        document.getElementById(
            'download-pdf'
        );

    const modeButtons =
        document.querySelectorAll(
            '[data-mode]'
        );

    const pdfPlanCheckboxes =
        document.querySelectorAll(
            '[data-pdf-plan]'
        );

    const money =
        new Intl.NumberFormat(
            'es-MX',
            {
                style: 'currency',
                currency: 'MXN',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }
        );

    const percentage =
        new Intl.NumberFormat(
            'es-MX',
            {
                minimumFractionDigits: 4,
                maximumFractionDigits: 4,
            }
        );

    function roundMoney(value) {
        return Number(
            Number(value).toFixed(2)
        );
    }

    function calculateCard(
        amount,
        months,
        financing,
        swipe
    ) {
        const rate =
            financing + swipe;

        const factor =
            1 - (rate / 100);

        let charged;
        let received;

        if (mode === 'receive') {
            received = amount;
            charged =
                amount / factor;
        } else {
            charged = amount;
            received =
                amount * factor;
        }

        const swipeAmount =
            charged * (swipe / 100);

        const financingAmount =
            charged * (financing / 100);

        const feeAmount =
            charged - received;

        return {
            swipe,
            financing,
            rate,
            charged:
                roundMoney(charged),
            received:
                roundMoney(received),
            payment:
                roundMoney(
                    charged / months
                ),
            swipeAmount:
                roundMoney(
                    swipeAmount
                ),
            financingAmount:
                roundMoney(
                    financingAmount
                ),
            feeAmount:
                roundMoney(
                    feeAmount
                ),
        };
    }

    function updateMode() {
        pdfPlanCheckboxes.forEach(
        (checkbox) => {
            checkbox.addEventListener(
                'change',
                () => {
                    updatePdf(
                        Number(
                            amountInput.value
                            || 0
                        )
                    );
                }
            );
        }
    );

    modeButtons.forEach(
            (button) => {
                button.classList.toggle(
                    'active',
                    button.dataset.mode ===
                        mode
                );
            }
        );

        if (mode === 'receive') {
            amountLabel.textContent =
                'Monto que deseas recibir';

            modeHelp.textContent =
                'Calcularemos cuánto debes ' +
                'cobrar para recibir ese ' +
                'importe neto.';

            intro.textContent =
                'El resultado compensa ' +
                'deslizamiento y financiamiento.';
        } else {
            amountLabel.textContent =
                'Monto que cobrarás';

            modeHelp.textContent =
                'Calcularemos cuánto recibirás ' +
                'después de descontar las ' +
                'comisiones.';

            intro.textContent =
                'El monto capturado es el total ' +
                'que pagará el cliente.';
        }
    }

    function renderCard(
        label,
        data,
        months
    ) {
        const paymentNote =
            months === 1
                ? 'pago único'
                : 'mensualidad aproximada';

        return `
            <div class="card-option">
                <div class="card-type">
                    ${label}
                </div>

                <div class="payment">
                    ${money.format(
                        data.payment
                    )}
                </div>

                <div class="payment-note">
                    ${paymentNote}
                </div>

                <div class="amount-lines">
                    <div class="row">
                        <span>
                            Cobrar total
                        </span>

                        <strong>
                            ${money.format(
                                data.charged
                            )}
                        </strong>
                    </div>

                    <div class="row">
                        <span>
                            Recibir neto
                        </span>

                        <strong>
                            ${money.format(
                                data.received
                            )}
                        </strong>
                    </div>
                </div>

                <div class="fees">
                    <div class="row">
                        <span>
                            Deslizamiento
                        </span>

                        <span>
                            ${percentage.format(
                                data.swipe
                            )}% · ${money.format(
                                data.swipeAmount
                            )}
                        </span>
                    </div>

                    <div class="row">
                        <span>
                            Financiamiento
                        </span>

                        <span>
                            ${percentage.format(
                                data.financing
                            )}% · ${money.format(
                                data.financingAmount
                            )}
                        </span>
                    </div>

                    <div class="row fee-total">
                        <span>
                            Comisión total
                        </span>

                        <span>
                            ${percentage.format(
                                data.rate
                            )}% · ${money.format(
                                data.feeAmount
                            )}
                        </span>
                    </div>
                </div>
            </div>
        `;
    }

    function updatePdf(
        amount
    ) {
        if (
            !Number.isFinite(amount)
            || amount <= 0
        ) {
            downloadPdf.href = '#';

            downloadPdf.classList.add(
                'is-disabled'
            );

            downloadPdf.setAttribute(
                'aria-disabled',
                'true'
            );

            return;
        }

        const params =
            new URLSearchParams({
                monto:
                    amount.toString(),

                modo:
                    mode,
            });

        const selectedPlans =
            Array.from(
                pdfPlanCheckboxes
            )
                .filter(
                    (checkbox) =>
                        checkbox.checked
                )
                .map(
                    (checkbox) =>
                        checkbox.value
                );

        /*
         * Sin seleccion = todos.
         */
        if (
            selectedPlans.length > 0
        ) {
            params.set(
                'planes',
                selectedPlans.join(',')
            );
        }

        downloadPdf.href =
            downloadPdf.dataset.baseUrl +
            '?' +
            params.toString();

        downloadPdf.classList.remove(
            'is-disabled'
        );

        downloadPdf.setAttribute(
            'aria-disabled',
            'false'
        );
    }

    function render() {
        updateMode();

        const amount =
            Number(
                amountInput.value || 0
            );

        if (
            !Number.isFinite(amount)
            || amount <= 0
        ) {
            results.innerHTML = '';
            updatePdf(0);
            return;
        }

        updatePdf(amount);

        results.innerHTML =
            terms.map(
                (term) => {
                    const months =
                        Number(
                            term.months
                        );

                    const financing =
                        Number(
                            term.financing
                        );

                    const credit =
                        calculateCard(
                            amount,
                            months,
                            financing,
                            creditSwipe
                        );

                    const debit =
                        calculateCard(
                            amount,
                            months,
                            financing,
                            debitSwipe
                        );

                    const termLabel =
                        months === 1
                            ? '1 pago'
                            : `${months} meses`;

                    return `
                        <article class="term-card">
                            <div class="term-title">
                                ${termLabel}
                            </div>

                            <div class="card-options">
                                ${renderCard(
                                    'Crédito',
                                    credit,
                                    months
                                )}

                                ${renderCard(
                                    'Débito',
                                    debit,
                                    months
                                )}
                            </div>
                        </article>
                    `;
                }
            ).join('');
    }

    modeButtons.forEach(
        (button) => {
            button.addEventListener(
                'click',
                () => {
                    mode =
                        button.dataset.mode;

                    render();
                }
            );
        }
    );

    amountInput.addEventListener(
        'input',
        render
    );

    render();
</script>
</body>
</html>
