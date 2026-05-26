<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                Vista básica del organigrama con jefe directo, coach, departamento, puesto y usuario ligado.
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Empleado</th>
                        <th class="px-4 py-3 text-left font-semibold">Puesto</th>
                        <th class="px-4 py-3 text-left font-semibold">Departamento</th>
                        <th class="px-4 py-3 text-left font-semibold">Jefe directo</th>
                        <th class="px-4 py-3 text-left font-semibold">Usuario jefe</th>
                        <th class="px-4 py-3 text-left font-semibold">Coach</th>
                        <th class="px-4 py-3 text-left font-semibold">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($this->rows() as $row)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $row['name'] }}</div>
                                <div class="text-xs text-gray-500">{{ $row['employee_number'] ?: 'Sin número' }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $row['position'] ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $row['department'] ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $row['manager'] ?: 'Sin jefe' }}</td>
                            <td class="px-4 py-3">{{ $row['manager_user'] ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $row['coach'] ?: '-' }}</td>
                            <td class="px-4 py-3">
                                @if ($row['active'])
                                    <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">Activo</span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">Inactivo</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                                No hay empleados para mostrar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
