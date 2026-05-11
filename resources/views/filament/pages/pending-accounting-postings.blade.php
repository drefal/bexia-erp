<x-filament-panels::page>
    <style>
        .bexia-accounting-page {
            width: 100%;
            max-width: none;
        }

        .bexia-accounting-card-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            width: 100%;
        }

        @media (max-width: 1280px) {
            .bexia-accounting-card-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 900px) {
            .bexia-accounting-card-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .bexia-accounting-card-grid {
                grid-template-columns: 1fr;
            }
        }

        .bexia-accounting-card {
            border-radius: 0.75rem;
            background: white;
            padding: 1rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            border: 1px solid rgba(15, 23, 42, 0.08);
            min-height: 86px;
        }

        .dark .bexia-accounting-card {
            background: rgb(17 24 39);
            border-color: rgba(255, 255, 255, 0.10);
        }

        .bexia-accounting-section {
            width: 100%;
            border-radius: 0.75rem;
            background: white;
            padding: 1rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            border: 1px solid rgba(15, 23, 42, 0.08);
        }

        .dark .bexia-accounting-section {
            background: rgb(17 24 39);
            border-color: rgba(255, 255, 255, 0.10);
        }

        .bexia-accounting-table {
            width: 100%;
            min-width: 980px;
            border-collapse: collapse;
        }

        .bexia-confirm-backdrop {
            position: fixed;
            inset: 0;
            z-index: 60;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.55);
        }

        .bexia-confirm-modal {
            width: 100%;
            max-width: 34rem;
            border-radius: 1rem;
            background: white;
            padding: 1.25rem;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.35);
        }

        .dark .bexia-confirm-modal {
            background: rgb(17 24 39);
        }
    </style>

    @php
        $docs = $this->documents();
        $counters = $this->counters();
        $pending = $this->pendingConfirmation;

        $money = fn ($value) => '$ ' . number_format((float) $value, 2) . ' MXN';

        $cards = [
            ['label' => 'Total pendiente', 'value' => $counters['total']],
            ['label' => 'Compras', 'value' => $counters['purchase_orders']],
            ['label' => 'Ventas', 'value' => $counters['sales_orders']],
            ['label' => 'POS', 'value' => $counters['pos_orders']],
            ['label' => 'Devoluciones', 'value' => $counters['pos_order_refunds']],
            ['label' => 'Facturas', 'value' => $counters['invoices']],
        ];

        $sections = [
            'purchase_orders' => 'Compras recibidas',
            'sales_orders' => 'Ventas entregadas',
            'pos_orders' => 'Tickets POS pagados o devueltos',
            'pos_order_refunds' => 'Devoluciones POS',
            'invoices' => 'Facturas internas',
        ];
    @endphp

    <div class="bexia-accounting-page space-y-6">
        <div class="bexia-accounting-card-grid">
            @foreach ($cards as $card)
                <div class="bexia-accounting-card">
                    <div class="text-sm text-gray-500">{{ $card['label'] }}</div>
                    <div class="mt-2 text-2xl font-bold">{{ $card['value'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="bexia-accounting-section text-sm text-gray-600 dark:text-gray-300">
            Esta pantalla ejecuta la contabilización de documentos operativos ya terminados. No procesa documentos en borrador ni pendientes de pago.
        </div>

        @foreach ($sections as $key => $title)
            <div class="bexia-accounting-section">
                <h2 class="text-lg font-semibold">{{ $title }}</h2>

                <div class="mt-4 w-full overflow-x-auto">
                    <table class="bexia-accounting-table text-sm">
                        <thead>
                            <tr class="border-b text-left dark:border-gray-700">
                                <th class="py-2 pr-4">Documento</th>
                                <th class="py-2 pr-4">Fecha</th>
                                <th class="py-2 pr-4">Estado operación</th>
                                <th class="py-2 pr-4">Estado contable</th>
                                <th class="py-2 pr-4 text-right">Importe</th>
                                <th class="py-2 pr-4 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($docs[$key] ?? [] as $row)
                                <tr class="border-b dark:border-gray-800">
                                    <td class="py-2 pr-4">
                                        <div class="font-medium">{{ $row['number'] }}</div>
                                        <div class="text-xs text-gray-500">ID {{ $row['id'] }}</div>
                                    </td>
                                    <td class="py-2 pr-4">
                                        {{ $row['date'] ? \Illuminate\Support\Carbon::parse($row['date'])->format('Y-m-d H:i') : 'Sin fecha' }}
                                    </td>
                                    <td class="py-2 pr-4">
                                        <div>{{ $row['status_label'] }}</div>
                                        @if (! empty($row['fiscal_status_label']))
                                            <div class="text-xs text-gray-500">Fiscal: {{ $row['fiscal_status_label'] }}</div>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-4">{{ $row['accounting_status_label'] }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $money($row['total']) }}</td>
                                    <td class="py-2 pr-4 text-right">
                                        @if ($row['can_post'] ?? true)
                                            <x-filament::button
                                                size="sm"
                                                color="primary"
                                                wire:click="askPostDocument('{{ $row['type'] }}', {{ $row['id'] }})"
                                            >
                                                Contabilizar
                                            </x-filament::button>
                                        @else
                                            <div class="text-right">
                                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                    {{ $row['block_label'] ?? 'No lista' }}
                                                </span>
                                                <div class="mt-1 max-w-md text-xs text-gray-500">
                                                    {{ $row['block_reason'] ?? 'Documento no disponible para contabilizar.' }}
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                </tr>

                                @if (! empty($row['error']))
                                    <tr class="border-b dark:border-gray-800">
                                        <td colspan="6" class="pb-3 text-sm text-danger-600">
                                            {{ $row['error'] }}
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="6" class="py-4 text-gray-500">
                                        Sin documentos pendientes en esta sección.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        @if ($this->lastResult)
            <div class="bexia-accounting-section">
                <h2 class="text-lg font-semibold">Último resultado</h2>
                <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-100 p-4 text-xs dark:bg-gray-950">{{ $this->lastResult }}</pre>
            </div>
        @endif
    </div>

    @if ($pending)
        <div class="bexia-confirm-backdrop" wire:key="accounting-confirmation-modal">
            <div class="bexia-confirm-modal">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-400">
                        <x-heroicon-o-calculator class="h-5 w-5" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                            Confirmar contabilización
                        </h2>

                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            Vas a contabilizar
                            <strong>{{ $pending['type_label'] }}</strong>
                            <strong>{{ $pending['number'] }}</strong>
                            por <strong>{{ $money($pending['total']) }}</strong>.
                        </p>

                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Esta acción generará el asiento contable correspondiente.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-filament::button
                        color="gray"
                        wire:click="cancelPosting"
                    >
                        Cancelar
                    </x-filament::button>

                    <x-filament::button
                        color="primary"
                        wire:click="confirmPostDocument"
                    >
                        Sí, contabilizar
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
