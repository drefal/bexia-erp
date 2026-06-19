<x-filament-panels::page>
    @php
        $columns = $this->columns();
        $board = $this->getBoard();
    @endphp

    <div data-service-kanban-page>
        <div class="mb-5 rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 shadow-sm">
            <strong>Kanban operativo:</strong>
            las tarjetas solo pueden avanzar a la siguiente etapa permitida. No se permite regresar, brincar etapas ni aprobar arrastrando.
        </div>

        <div class="flex gap-6 overflow-x-auto pb-6 pr-4">
            @foreach ($columns as $stage => $label)
                <div class="min-w-[380px] w-[380px] shrink-0 rounded-2xl border p-4 shadow-sm {{ $this->stageColor($stage) }}">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h3 class="text-base font-extrabold text-gray-950">
                            {{ $label }}
                        </h3>

                        <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-gray-800 shadow-sm">
                            {{ count($board[$stage] ?? []) }}
                        </span>
                    </div>

                    <div
                        class="service-kanban-list min-h-[640px] space-y-4 rounded-xl"
                        data-service-kanban-list
                        data-stage="{{ $stage }}"
                    >
                        @forelse (($board[$stage] ?? []) as $repair)
                            @php
                                $nextOptions = $this->nextStageOptions($repair);
                                $allowedTargets = implode(',', array_keys($nextOptions));
                            @endphp

                            <div
                                class="service-kanban-card cursor-grab rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:shadow-lg active:cursor-grabbing"
                                data-service-kanban-card
                                data-repair-id="{{ (int) $repair->id }}"
                                data-stage="{{ $stage }}"
                                data-allowed-targets="{{ $allowedTargets }}"
                                wire:key="repair-kanban-card-{{ $repair->id }}"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="text-base font-extrabold tracking-tight text-gray-950">
                                            {{ $repair->folio ?? ('REP-' . $repair->id) }}
                                        </div>

                                        <div class="mt-1 text-xs text-gray-500">
                                            ID #{{ $repair->id }}
                                        </div>
                                    </div>

                                    <div class="rounded-full bg-gray-100 px-2.5 py-1 text-right text-xs font-bold text-gray-800">
                                        {{ $this->cardAmount($repair) }}
                                    </div>
                                </div>

                                <p class="mt-4 min-h-[44px] text-sm leading-6 text-gray-700">
                                    {{ $this->cardDescription($repair) }}
                                </p>

                                <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-4">
                                    <a
                                        href="{{ $this->editUrl((int) $repair->id) }}"
                                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 shadow-sm hover:bg-primary-50"
                                    >
                                        Abrir
                                    </a>

                                    @if (count($nextOptions) > 0)
                                        <div class="flex flex-wrap justify-end gap-1">
                                            @foreach ($nextOptions as $targetStage => $targetLabel)
                                                <button
                                                    type="button"
                                                    wire:click="requestStageChange({{ (int) $repair->id }}, '{{ $targetStage }}')"
                                                    class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-700"
                                                >
                                                    Avanzar a {{ $targetLabel }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-gray-300 bg-white/50 p-6 text-center text-sm text-gray-500">
                                Sin reparaciones
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <x-filament::modal id="service-kanban-transition-modal" width="lg">
        <x-slot name="heading">
            {{ $this->transitionHeading() }}
        </x-slot>

        <div class="space-y-4">
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">
                {{ $this->transitionHint() }}
            </div>

            @if ($transitionTargetStage === 'repaired')
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Solución / comentario de cierre
                    </label>

                    <textarea
                        wire:model.defer="transitionNotes"
                        rows="5"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        placeholder="Ej. Se reemplazó refacción, se probó funcionamiento y quedó operando correctamente."
                    ></textarea>

                    <p class="mt-1 text-xs text-gray-500">
                        Las fotos y documentos de solución se pueden seguir adjuntando desde la ficha de la reparación.
                    </p>
                </div>
            @endif

            @if ($transitionTargetStage === 'delivered')
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Nombre de quien recibe
                    </label>

                    <input
                        type="text"
                        wire:model.defer="deliveredTo"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        placeholder="Nombre completo"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Observaciones de entrega
                    </label>

                    <textarea
                        wire:model.defer="deliveryNotes"
                        rows="4"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        placeholder="Ej. Recibe y valida funcionamiento."
                    ></textarea>
                </div>
            @endif

            <div class="flex justify-end gap-2 pt-2">
                <x-filament::button
                    color="gray"
                    x-on:click="$dispatch('close-modal', { id: 'service-kanban-transition-modal' })"
                >
                    Cancelar
                </x-filament::button>

                <x-filament::button wire:click="confirmStageChange">
                    Confirmar cambio
                </x-filament::button>
            </div>
        </div>
    </x-filament::modal>

    <script>
        (() => {
            function loadSortable(callback) {
                if (window.Sortable) {
                    callback();
                    return;
                }

                const existing = document.querySelector('script[data-bexia-sortablejs]');

                if (existing) {
                    existing.addEventListener('load', callback, { once: true });
                    return;
                }

                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';
                script.dataset.bexiaSortablejs = '1';
                script.onload = callback;
                document.head.appendChild(script);
            }

            function livewireComponentFor(element) {
                const root = element.closest('[wire\\:id]');

                if (! root || ! window.Livewire) {
                    return null;
                }

                return window.Livewire.find(root.getAttribute('wire:id'));
            }

            function allowedTargetsFor(item) {
                return String(item?.dataset?.allowedTargets || '')
                    .split(',')
                    .map((value) => value.trim())
                    .filter(Boolean);
            }

            function isAllowedTarget(item, targetStage) {
                return allowedTargetsFor(item).includes(targetStage);
            }

            function restoreOriginalPosition(event) {
                const from = event.from;
                const item = event.item;
                const oldIndex = event.oldIndex;

                if (! from || ! item || oldIndex === undefined || oldIndex === null) {
                    return;
                }

                const reference = from.children[oldIndex] || null;
                from.insertBefore(item, reference);
            }

            function notifyInvalidMove() {
                if (window.$wireui) {
                    return;
                }

                console.info('Movimiento no permitido. Solo se puede avanzar a la siguiente etapa.');
            }

            function initServiceKanbanSortable() {
                loadSortable(() => {
                    document.querySelectorAll('[data-service-kanban-list]').forEach((list) => {
                        if (list.dataset.sortableReady === '1') {
                            return;
                        }

                        list.dataset.sortableReady = '1';

                        window.Sortable.create(list, {
                            group: 'service-repair-kanban',
                            animation: 160,
                            draggable: '[data-service-kanban-card]',
                            ghostClass: 'opacity-50',
                            chosenClass: 'ring-2',
                            dragClass: 'shadow-xl',

                            onMove: function (event) {
                                const item = event.dragged;
                                const targetStage = event.to?.dataset?.stage || '';
                                const sourceStage = item?.dataset?.stage || '';

                                if (! targetStage || targetStage === sourceStage) {
                                    return true;
                                }

                                return isAllowedTarget(item, targetStage);
                            },

                            onEnd: function (event) {
                                const item = event.item;
                                const targetList = event.to;
                                const sourceList = event.from;

                                const repairId = parseInt(item?.dataset?.repairId || '0', 10);
                                const targetStage = targetList?.dataset?.stage || '';
                                const sourceStage = sourceList?.dataset?.stage || item?.dataset?.stage || '';

                                restoreOriginalPosition(event);

                                if (! repairId || ! targetStage || targetStage === sourceStage) {
                                    return;
                                }

                                if (! isAllowedTarget(item, targetStage)) {
                                    notifyInvalidMove();
                                    return;
                                }

                                const component = livewireComponentFor(document.querySelector('[data-service-kanban-page]') || item);

                                if (! component) {
                                    alert('No se pudo conectar con Livewire. Abre la reparación desde el enlace Abrir.');
                                    return;
                                }

                                component.call('requestStageChange', repairId, targetStage);
                            },
                        });
                    });
                });
            }

            document.addEventListener('DOMContentLoaded', initServiceKanbanSortable);
            document.addEventListener('livewire:navigated', initServiceKanbanSortable);
            document.addEventListener('livewire:init', initServiceKanbanSortable);

            setTimeout(initServiceKanbanSortable, 300);
            setTimeout(initServiceKanbanSortable, 1200);
        })();
    </script>
</x-filament-panels::page>
