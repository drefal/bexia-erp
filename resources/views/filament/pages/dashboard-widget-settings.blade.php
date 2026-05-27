<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Configuración de dashboards del Escritorio
            </x-slot>

            <x-slot name="description">
                Selecciona qué widgets puede ver cada usuario en el Escritorio. Esta configuración solo controla visibilidad; no otorga permisos operativos.
            </x-slot>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">
                        Usuario
                    </label>

                    <select
                        wire:model.live="selectedUserId"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    >
                        @foreach ($users as $user)
                            <option value="{{ $user['id'] }}">
                                {{ $user['name'] }} · {{ $user['email'] }}
                            </option>
                        @endforeach
                    </select>

                    <p class="mt-2 text-xs text-gray-500">
                        Empresa actual: {{ $companyId ?: '—' }} · Usuario seleccionado: {{ $this->selectedUserLabel() }}
                    </p>
                </div>

                <div class="flex items-end justify-start gap-2 md:justify-end">
                    <x-filament::button color="gray" wire:click="refreshDashboardSettings">
                        Actualizar
                    </x-filament::button>

                    <x-filament::button color="warning" wire:click="resetSelectedUser">
                        Restaurar defaults
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Widgets disponibles
            </x-slot>

            @if (empty($rows))
                <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500">
                    No hay widgets configurables para mostrar.
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Orden</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Widget</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Módulo</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Visible</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Permiso</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ $row['sort_order'] }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-950">
                                            {{ $row['label'] }}
                                        </div>

                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ $row['description'] }}
                                        </div>

                                        <div class="mt-1 font-mono text-xs text-gray-400">
                                            {{ $row['key'] }}
                                        </div>
                                    </td>

                                    <td class="px-4 py-3 text-gray-600">
                                        {{ $row['module'] }}
                                    </td>

                                    <td class="px-4 py-3">
                                        @if ($row['is_visible'])
                                            <span class="rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-green-700">
                                                Visible
                                            </span>
                                        @else
                                            <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">
                                                Oculto
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3">
                                        @if ($row['allowed_by_permission'])
                                            <span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700">
                                                Permitido
                                            </span>
                                        @else
                                            <span class="rounded-full bg-red-50 px-2 py-1 text-xs font-medium text-red-700">
                                                Sin permiso
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <x-filament::button
                                                size="xs"
                                                color="gray"
                                                wire:click="moveWidgetUp('{{ $row['key'] }}')"
                                            >
                                                Subir
                                            </x-filament::button>

                                            <x-filament::button
                                                size="xs"
                                                color="gray"
                                                wire:click="moveWidgetDown('{{ $row['key'] }}')"
                                            >
                                                Bajar
                                            </x-filament::button>

                                            <x-filament::button
                                                size="xs"
                                                color="{{ $row['is_visible'] ? 'danger' : 'success' }}"
                                                wire:click="toggleWidget('{{ $row['key'] }}')"
                                            >
                                                {{ $row['is_visible'] ? 'Ocultar' : 'Mostrar' }}
                                            </x-filament::button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
