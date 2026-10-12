<div class="space-y-4">
    <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
        <div>
            <div class="text-xs text-gray-500">Versión</div>
            <div class="font-semibold">V{{ $version->version_number }}</div>
        </div>

        <div>
            <div class="text-xs text-gray-500">Empleados</div>
            <div class="font-semibold">{{ $version->employees_count }}</div>
        </div>

        <div>
            <div class="text-xs text-gray-500">Base</div>
            <div class="font-semibold">
                ${{ number_format((float) $version->base_total, 2) }}
            </div>
        </div>

        <div>
            <div class="text-xs text-gray-500">Bruto</div>
            <div class="font-semibold">
                ${{ number_format((float) $version->gross_total, 2) }}
            </div>
        </div>

        <div>
            <div class="text-xs text-gray-500">Neto</div>
            <div class="font-semibold">
                ${{ number_format((float) $version->net_total, 2) }}
            </div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b bg-gray-50 text-left">
                    <th class="p-2">Empleado</th>
                    <th class="p-2">Contrato</th>
                    <th class="p-2 text-right">Sueldo</th>
                    <th class="p-2 text-right">Días</th>
                    <th class="p-2 text-right">Horas</th>
                    <th class="p-2 text-right">Base</th>
                    <th class="p-2 text-right">Extra</th>
                    <th class="p-2 text-right">Deducciones</th>
                    <th class="p-2 text-right">Neto</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($version->lines as $line)
                    <tr class="border-b">
                        <td class="p-2">
                            {{ $line->employee_name_snapshot }}
                        </td>

                        <td class="p-2">
                            {{ $line->contract_number_snapshot ?: '-' }}
                        </td>

                        <td class="p-2 text-right">
                            ${{ number_format((float) $line->base_salary, 2) }}
                        </td>

                        <td class="p-2 text-right">
                            {{ number_format((float) $line->payable_days, 2) }}
                        </td>

                        <td class="p-2 text-right">
                            {{ number_format((float) $line->worked_hours, 2) }}
                        </td>

                        <td class="p-2 text-right">
                            ${{ number_format((float) $line->base_amount, 2) }}
                        </td>

                        <td class="p-2 text-right">
                            ${{ number_format((float) $line->overtime_amount, 2) }}
                        </td>

                        <td class="p-2 text-right">
                            ${{ number_format((float) $line->deductions_amount, 2) }}
                        </td>

                        <td class="p-2 text-right font-semibold">
                            ${{ number_format((float) $line->net_amount, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="p-4 text-center text-gray-500">
                            Esta versión no contiene líneas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
