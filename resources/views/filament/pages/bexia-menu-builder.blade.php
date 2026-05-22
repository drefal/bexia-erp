<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                        Configurador visual del menú lateral
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Estructura de 2 niveles: grupos y opciones.
                    </p>
                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                        Los botones subir/bajar, visible/oculto y mover de grupo guardan al instante.
                        Para cambiar nombres, edita el campo y presiona el botón azul Guardar nombre.
                        Esta configuración todavía no reemplaza el menú real.
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    wire:click="resetDefaultOrder"
                    wire:confirm="¿Restaurar nombres, visibilidad y orden base del menú?"
                >
                    Restaurar base
                </button>
            </div>
        </div>

        <div class="grid gap-4">
            @foreach($this->groups() as $group)
                <div
                    class="rounded-xl border bg-white shadow-sm dark:bg-gray-900 {{ $group->is_visible ? 'border-gray-200 dark:border-gray-700' : 'border-dashed border-gray-300 opacity-70 dark:border-gray-700' }}"
                    wire:key="menu-group-{{ $group->id }}"
                >
                    <div class="border-b border-gray-100 p-4 dark:border-gray-800">
                        <div class="grid gap-3 lg:grid-cols-[auto_1fr_auto] lg:items-center">
                            <div class="flex gap-1">
                                <button
                                    type="button"
                                    class="rounded-md border px-3 py-2 text-sm font-bold hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                                    wire:click="moveGroup({{ $group->id }}, 'up')"
                                    title="Subir grupo"
                                >
                                    ↑
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border px-3 py-2 text-sm font-bold hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                                    wire:click="moveGroup({{ $group->id }}, 'down')"
                                    title="Bajar grupo"
                                >
                                    ↓
                                </button>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                    Nombre del grupo
                                </label>
                                <input
                                    type="text"
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    wire:model.defer="groupLabels.{{ $group->id }}"
                                />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Key: {{ $group->key }} · Orden: {{ $group->sort }} · Opciones: {{ $group->items->count() }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold shadow-sm" style="background-color:#2563eb;color:#ffffff;border:1px solid #1d4ed8;"
                                    wire:click="saveGroupLabel({{ $group->id }})"
                                >
                                    Guardar nombre
                                </button>

                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold shadow-sm {{ $group->is_visible ? 'bg-green-600 text-white hover:bg-green-500' : 'bg-gray-500 text-white hover:bg-gray-400' }}"
                                    wire:click="toggleGroupVisibility({{ $group->id }})"
                                >
                                    {{ $group->is_visible ? 'Visible' : 'Oculto' }}
                                </button>

                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                    wire:click="resetGroupLabel({{ $group->id }})"
                                >
                                    Restaurar nombre
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($group->items as $item)
                            <div
                                class="p-3 {{ $item->is_visible ? '' : 'opacity-60' }}"
                                wire:key="menu-item-{{ $item->id }}"
                            >
                                <div class="grid gap-3 lg:grid-cols-[auto_1fr_auto] lg:items-center">
                                    <div class="flex gap-1">
                                        <button
                                            type="button"
                                            class="rounded-md border px-3 py-2 text-sm font-bold hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                                            wire:click="moveItem({{ $item->id }}, 'up')"
                                            title="Subir opción"
                                        >
                                            ↑
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md border px-3 py-2 text-sm font-bold hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                                            wire:click="moveItem({{ $item->id }}, 'down')"
                                            title="Bajar opción"
                                        >
                                            ↓
                                        </button>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                            Nombre de la opción
                                        </label>
                                        <input
                                            type="text"
                                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                            wire:model.defer="itemLabels.{{ $item->id }}"
                                        />
                                        <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                                            Key: {{ $item->key }} · Orden: {{ $item->sort }} · Fuente: {{ $item->source ?: 'n/d' }}
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold shadow-sm" style="background-color:#2563eb;color:#ffffff;border:1px solid #1d4ed8;"
                                            wire:click="saveItemLabel({{ $item->id }})"
                                        >
                                            Guardar nombre
                                        </button>

                                        <select
                                            class="rounded-lg border-gray-300 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                            wire:change="moveItemToGroup({{ $item->id }}, $event.target.value)"
                                        >
                                            @foreach($this->groups() as $targetGroup)
                                                <option value="{{ $targetGroup->id }}" @selected($targetGroup->id === $group->id)>
                                                    {{ $targetGroup->label }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold shadow-sm {{ $item->is_visible ? 'bg-green-600 text-white hover:bg-green-500' : 'bg-gray-500 text-white hover:bg-gray-400' }}"
                                            wire:click="toggleItemVisibility({{ $item->id }})"
                                        >
                                            {{ $item->is_visible ? 'Visible' : 'Oculto' }}
                                        </button>

                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                            wire:click="resetItemLabel({{ $item->id }})"
                                        >
                                            Restaurar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-4 text-sm text-gray-500 dark:text-gray-400">
                                Este grupo no tiene opciones.
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
