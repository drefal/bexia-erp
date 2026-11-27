<x-filament-panels::page>

    <x-filament::section>
        <x-slot name="heading">
            Nueva autorización de acceso
        </x-slot>

        <x-slot name="description">
            Empresa: {{ $companyName }}.
            La autorización de acceso no modifica
            automáticamente las incidencias de nómina.
        </x-slot>

        <form wire:submit="createAuthorization"
              class="space-y-4">

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                <div>
                    <label class="text-sm font-medium">
                        Sucursal
                    </label>

                    <x-filament::input.wrapper>
                        <x-filament::input.select
                            wire:model.live="branchId"
                            required>
                            <option value="">
                                Seleccionar sucursal
                            </option>

                            @foreach ($branches as $id => $name)
                                <option value="{{ $id }}">
                                    {{ $name }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>

                    @error('branchId')
                        <p class="text-sm text-danger-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium">
                        Empleado
                    </label>

                    <x-filament::input.wrapper>
                        <x-filament::input.select
                            wire:model="employeeId"
                            required
                            :disabled="!$policyEnabled">

                            <option value="">
                                Seleccionar empleado
                            </option>

                            @foreach ($employeeOptions as $id => $name)
                                <option value="{{ $id }}">
                                    {{ $name }}
                                </option>
                            @endforeach

                        </x-filament::input.select>
                    </x-filament::input.wrapper>

                    @error('employeeId')
                        <p class="text-sm text-danger-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium">
                        Vigencia desde
                    </label>

                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="datetime-local"
                            wire:model="validFrom"
                            required />
                    </x-filament::input.wrapper>

                    @error('validFrom')
                        <p class="text-sm text-danger-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium">
                        Vigencia hasta
                    </label>

                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="datetime-local"
                            wire:model="validUntil"
                            required />
                    </x-filament::input.wrapper>

                    @error('validUntil')
                        <p class="text-sm text-danger-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

            </div>

            <div>
                <label class="text-sm font-medium">
                    Motivo de la autorización
                </label>

                <textarea
                    wire:model="reason"
                    rows="3"
                    maxlength="1000"
                    required
                    class="w-full rounded-lg border-gray-300"
                    placeholder="Indica el motivo de acceso extraordinario"
                ></textarea>

                @error('reason')
                    <p class="text-sm text-danger-600">
                        {{ $message }}
                    </p>
                @enderror

            </div>

            @if ($branchId !== '' && !$policyEnabled)
                <div class="rounded-lg border border-amber-300
                            bg-amber-50 p-3 text-sm text-amber-800">
                    El control de acceso todavía no está
                    habilitado para esta sucursal.
                </div>
            @endif

            <div class="flex justify-end">
                <x-filament::button
                    type="submit"
                    icon="heroicon-o-shield-check"
                    wire:loading.attr="disabled"
                    :disabled="!$policyEnabled
                        || empty($employeeOptions)">

                    Registrar autorización

                </x-filament::button>
            </div>

        </form>

    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            Historial de autorizaciones
        </x-slot>

        <x-slot name="description">
            Últimos 50 registros dentro de tu alcance.
        </x-slot>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

            <x-filament::input.wrapper>
                <x-filament::input
                    wire:model.live.debounce.400ms="search"
                    placeholder="Buscar empleado" />
            </x-filament::input.wrapper>

            <x-filament::input.wrapper>
                <x-filament::input.select
                    wire:model.live="statusFilter">

                    <option value="all">Todas</option>
                    <option value="current">Vigentes</option>
                    <option value="future">Programadas</option>
                    <option value="expired">Vencidas</option>
                    <option value="revoked">Revocadas</option>

                </x-filament::input.select>
            </x-filament::input.wrapper>

        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">

                <thead class="border-b bg-gray-50">
                    <tr>
                        <th class="p-3">Folio</th>
                        <th class="p-3">Empleado</th>
                        <th class="p-3">Sucursal</th>
                        <th class="p-3">Desde</th>
                        <th class="p-3">Hasta</th>
                        <th class="p-3">Estado</th>
                    </tr>
                </thead>

                <tbody class="divide-y">

                    @forelse ($accessRows as $row)

                        <tr wire:key="access-{{ $row['id'] }}">

                            <td class="p-3">
                                #{{ $row['id'] }}
                            </td>

                            <td class="p-3 font-medium">
                                {{ $row['employee'] }}
                            </td>

                            <td class="p-3">
                                {{ $row['branch'] }}
                            </td>

                            <td class="p-3">
                                {{ $row['from'] }}
                            </td>

                            <td class="p-3">
                                {{ $row['until'] }}
                            </td>

                            <td class="p-3">
                                <x-filament::badge
                                    :color="match ($row['status']) {
                                        'Vigente' => 'success',
                                        'Programada' => 'info',
                                        'Revocada',
                                        'Rechazada' => 'danger',
                                        'Pendiente' => 'warning',
                                        default => 'gray',
                                    }">

                                    {{ $row['status'] }}

                                </x-filament::badge>
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="6"
                                class="p-8 text-center text-gray-500">
                                No hay autorizaciones para mostrar.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>
        </div>

    </x-filament::section>

</x-filament-panels::page>
