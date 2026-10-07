<x-filament-panels::page>
    <div
        x-data="{
            rows: @js(
                collect($denominations)
                    ->map(
                        fn ($row) => array_merge(
                            $row,
                            ['quantity' => 0]
                        )
                    )
                    ->values()
            ),

            money(value) {
                return new Intl.NumberFormat(
                    'es-MX',
                    {
                        style: 'currency',
                        currency: 'MXN',
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    }
                ).format(Number(value || 0));
            },

            qty(row) {
                return Math.max(
                    0,
                    parseInt(
                        row.quantity || 0,
                        10
                    ) || 0
                );
            },

            lineTotal(row) {
                return Number(
                    (
                        Number(row.value || 0)
                        * this.qty(row)
                    ).toFixed(2)
                );
            },

            total() {
                return Number(
                    this.rows.reduce(
                        (sum, row) =>
                            sum
                            + this.lineTotal(row),
                        0
                    ).toFixed(2)
                );
            },

            pieces() {
                return this.rows.reduce(
                    (sum, row) =>
                        sum + this.qty(row),
                    0
                );
            },

            billPieces() {
                return this.rows
                    .filter(
                        row =>
                            row.type === 'bill'
                    )
                    .reduce(
                        (sum, row) =>
                            sum
                            + this.qty(row),
                        0
                    );
            },

            coinPieces() {
                return this.rows
                    .filter(
                        row =>
                            row.type === 'coin'
                    )
                    .reduce(
                        (sum, row) =>
                            sum
                            + this.qty(row),
                        0
                    );
            },

            billTotal() {
                return this.rows
                    .filter(
                        row =>
                            row.type === 'bill'
                    )
                    .reduce(
                        (sum, row) =>
                            sum
                            + this.lineTotal(row),
                        0
                    );
            },

            coinTotal() {
                return this.rows
                    .filter(
                        row =>
                            row.type === 'coin'
                    )
                    .reduce(
                        (sum, row) =>
                            sum
                            + this.lineTotal(row),
                        0
                    );
            },

            usedRows() {
                return this.rows.filter(
                    row =>
                        this.qty(row) > 0
                );
            },

            clearAll() {
                this.rows.forEach(
                    row =>
                        row.quantity = 0
                );
            },

            copyTotal() {
                const amount =
                    this.total().toFixed(2);

                if (
                    navigator.clipboard
                    && navigator.clipboard.writeText
                ) {
                    navigator.clipboard
                        .writeText(amount);
                }
            },
        }"
        class="bexia-cash-counter"
    >
        <style>
            .bexia-cash-counter {
                max-width: 1180px;
                margin: 0 auto;
            }

            .bcc-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 14px;
            }

            .bcc-head h2 {
                margin: 0;
                font-size: 18px;
                font-weight: 800;
            }

            .bcc-head p {
                margin: 2px 0 0;
                font-size: 12px;
                color: #64748b;
            }

            .bcc-layout {
                display: grid;
                grid-template-columns:
                    minmax(0, 1fr)
                    310px;
                gap: 16px;
                align-items: start;
            }

            .bcc-card {
                overflow: hidden;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                background: #ffffff;
            }

            .dark .bcc-card {
                border-color: #374151;
                background: #111827;
            }

            .bcc-section-title {
                padding: 9px 12px;
                border-bottom: 1px solid #e2e8f0;
                background: #f8fafc;
                font-size: 12px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #475569;
            }

            .dark .bcc-section-title {
                border-color: #374151;
                background: #1f2937;
                color: #cbd5e1;
            }

            .bcc-table {
                width: 100%;
                border-collapse: collapse;
            }

            .bcc-table th {
                padding: 7px 10px;
                border-bottom: 1px solid #e2e8f0;
                font-size: 11px;
                font-weight: 700;
                color: #64748b;
                text-align: left;
            }

            .bcc-table th:nth-child(2),
            .bcc-table th:nth-child(3),
            .bcc-table td:nth-child(2),
            .bcc-table td:nth-child(3) {
                text-align: right;
            }

            .bcc-table td {
                padding: 6px 10px;
                border-bottom: 1px solid #f1f5f9;
                vertical-align: middle;
            }

            .dark .bcc-table th,
            .dark .bcc-table td {
                border-color: #374151;
            }

            .bcc-table tr:last-child td {
                border-bottom: 0;
            }

            .bcc-denom {
                display: flex;
                align-items: center;
                gap: 7px;
                font-size: 13px;
                font-weight: 700;
            }

            .bcc-kind {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 22px;
                height: 22px;
                border-radius: 6px;
                background: #f1f5f9;
                font-size: 12px;
            }

            .dark .bcc-kind {
                background: #374151;
            }

            .bcc-qty {
                width: 74px;
                height: 32px;
                border: 1px solid #cbd5e1;
                border-radius: 7px;
                padding: 4px 7px;
                text-align: right;
                font-weight: 700;
            }

            .dark .bcc-qty {
                border-color: #4b5563;
                background: #1f2937;
                color: #ffffff;
            }

            .bcc-line-total {
                min-width: 95px;
                font-size: 13px;
                font-weight: 800;
                white-space: nowrap;
            }

            .bcc-summary {
                position: sticky;
                top: 16px;
                border: 1px solid #dbeafe;
                border-radius: 12px;
                background: #ffffff;
                box-shadow:
                    0 8px 24px
                    rgba(15, 23, 42, .08);
                overflow: hidden;
            }

            .dark .bcc-summary {
                border-color: #1e3a8a;
                background: #111827;
            }

            .bcc-summary-head {
                padding: 12px 14px;
                border-bottom: 1px solid #e2e8f0;
            }

            .dark .bcc-summary-head {
                border-color: #374151;
            }

            .bcc-summary-head span {
                display: block;
                font-size: 11px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .06em;
                color: #64748b;
            }

            .bcc-total {
                margin-top: 3px;
                font-size: 30px;
                line-height: 1.1;
                font-weight: 900;
                color: #2563eb;
            }

            .bcc-kpis {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1px;
                background: #e2e8f0;
                border-bottom: 1px solid #e2e8f0;
            }

            .dark .bcc-kpis {
                background: #374151;
                border-color: #374151;
            }

            .bcc-kpi {
                padding: 10px 12px;
                background: #ffffff;
            }

            .dark .bcc-kpi {
                background: #111827;
            }

            .bcc-kpi small {
                display: block;
                color: #64748b;
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
            }

            .bcc-kpi strong {
                display: block;
                margin-top: 2px;
                font-size: 15px;
            }

            .bcc-breakdown {
                padding: 10px 14px;
            }

            .bcc-breakdown-title {
                margin-bottom: 7px;
                font-size: 11px;
                font-weight: 800;
                color: #64748b;
                text-transform: uppercase;
            }

            .bcc-summary-row {
                display: grid;
                grid-template-columns:
                    minmax(0, 1fr)
                    auto;
                gap: 8px;
                padding: 5px 0;
                border-bottom: 1px dashed #e2e8f0;
                font-size: 12px;
            }

            .dark .bcc-summary-row {
                border-color: #374151;
            }

            .bcc-summary-row:last-child {
                border-bottom: 0;
            }

            .bcc-summary-row strong {
                white-space: nowrap;
            }

            .bcc-empty {
                padding: 10px 0;
                color: #94a3b8;
                font-size: 12px;
                text-align: center;
            }

            .bcc-actions {
                display: flex;
                gap: 8px;
                padding: 10px 14px 14px;
            }

            .bcc-btn {
                flex: 1;
                border-radius: 8px;
                padding: 8px 10px;
                font-size: 12px;
                font-weight: 800;
                cursor: pointer;
            }

            .bcc-btn-secondary {
                border: 1px solid #cbd5e1;
                background: #ffffff;
                color: #334155;
            }

            .bcc-btn-primary {
                border: 1px solid #2563eb;
                background: #2563eb;
                color: #ffffff;
            }

            @media (max-width: 900px) {
                .bcc-layout {
                    grid-template-columns: 1fr;
                }

                .bcc-summary {
                    position: static;
                }
            }

            @media (max-width: 520px) {
                .bcc-head {
                    align-items: flex-start;
                    flex-direction: column;
                }

                .bcc-table td,
                .bcc-table th {
                    padding-left: 6px;
                    padding-right: 6px;
                }

                .bcc-qty {
                    width: 64px;
                }
            }
        </style>

        <div class="bcc-head">
            <div>
                <h2>
                    Contador de efectivo
                </h2>

                <p>
                    Captura únicamente la cantidad de piezas.
                </p>
            </div>

            <button
                type="button"
                x-on:click="clearAll()"
                class="bcc-btn bcc-btn-secondary"
                style="flex:none;"
            >
                Limpiar
            </button>
        </div>

        <div class="bcc-layout">
            <div class="space-y-3">
                <template
                    x-for="type in ['bill', 'coin']"
                    :key="type"
                >
                    <div class="bcc-card">
                        <div
                            class="bcc-section-title"
                            x-text="
                                type === 'bill'
                                    ? 'Billetes'
                                    : 'Monedas'
                            "
                        ></div>

                        <table class="bcc-table">
                            <thead>
                                <tr>
                                    <th>
                                        Denominación
                                    </th>
                                    <th>
                                        Cantidad
                                    </th>
                                    <th>
                                        Importe
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <template
                                    x-for="
                                        row in rows.filter(
                                            r =>
                                                r.type
                                                === type
                                        )
                                    "
                                    :key="row.id"
                                >
                                    <tr>
                                        <td>
                                            <div
                                                class="bcc-denom"
                                            >
                                                <span
                                                    class="bcc-kind"
                                                    x-text="
                                                        type
                                                        === 'bill'
                                                            ? '▰'
                                                            : '●'
                                                    "
                                                ></span>

                                                <span
                                                    x-text="
                                                        money(
                                                            row.value
                                                        )
                                                    "
                                                ></span>
                                            </div>
                                        </td>

                                        <td>
                                            <input
                                                type="number"
                                                min="0"
                                                step="1"
                                                inputmode="numeric"
                                                x-model.number="
                                                    row.quantity
                                                "
                                                class="bcc-qty"
                                            >
                                        </td>

                                        <td
                                            class="bcc-line-total"
                                            x-text="
                                                money(
                                                    lineTotal(
                                                        row
                                                    )
                                                )
                                            "
                                        ></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>

            <aside class="bcc-summary">
                <div class="bcc-summary-head">
                    <span>
                        Total contado
                    </span>

                    <div
                        class="bcc-total"
                        x-text="money(total())"
                    ></div>
                </div>

                <div class="bcc-kpis">
                    <div class="bcc-kpi">
                        <small>
                            Piezas
                        </small>

                        <strong
                            x-text="pieces()"
                        ></strong>
                    </div>

                    <div class="bcc-kpi">
                        <small>
                            Denominaciones
                        </small>

                        <strong
                            x-text="
                                usedRows().length
                            "
                        ></strong>
                    </div>

                    <div class="bcc-kpi">
                        <small>
                            Billetes
                        </small>

                        <strong
                            x-text="
                                billPieces()
                                + ' · '
                                + money(
                                    billTotal()
                                )
                            "
                        ></strong>
                    </div>

                    <div class="bcc-kpi">
                        <small>
                            Monedas
                        </small>

                        <strong
                            x-text="
                                coinPieces()
                                + ' · '
                                + money(
                                    coinTotal()
                                )
                            "
                        ></strong>
                    </div>
                </div>

                <div class="bcc-breakdown">
                    <div
                        class="bcc-breakdown-title"
                    >
                        Resumen por denominación
                    </div>

                    <template
                        x-if="
                            usedRows().length === 0
                        "
                    >
                        <div class="bcc-empty">
                            Captura cantidades para ver
                            el resumen.
                        </div>
                    </template>

                    <template
                        x-for="
                            row in usedRows()
                        "
                        :key="'summary-' + row.id"
                    >
                        <div class="bcc-summary-row">
                            <span>
                                <strong
                                    x-text="
                                        qty(row)
                                    "
                                ></strong>
                                ×
                                <span
                                    x-text="
                                        money(
                                            row.value
                                        )
                                    "
                                ></span>
                            </span>

                            <strong
                                x-text="
                                    money(
                                        lineTotal(
                                            row
                                        )
                                    )
                                "
                            ></strong>
                        </div>
                    </template>
                </div>

                <div class="bcc-actions">
                    <button
                        type="button"
                        x-on:click="
                            clearAll()
                        "
                        class="
                            bcc-btn
                            bcc-btn-secondary
                        "
                    >
                        Limpiar
                    </button>

                    <button
                        type="button"
                        x-on:click="
                            copyTotal()
                        "
                        class="
                            bcc-btn
                            bcc-btn-primary
                        "
                    >
                        Copiar total
                    </button>
                </div>
            </aside>
        </div>
    </div>
</x-filament-panels::page>
