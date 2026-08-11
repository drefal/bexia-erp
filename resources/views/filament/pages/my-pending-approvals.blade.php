<x-filament-panels::page>
    <div class="space-y-4">
        <div>
            <div class="text-base font-semibold text-gray-950 dark:text-white">
                Aprobaciones pendientes
            </div>

            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Documentos donde tu usuario puede aprobar o rechazar la etapa actual.
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Documento</th>
                            <th class="px-4 py-3 text-left font-semibold">Folio</th>
                            <th class="px-4 py-3 text-left font-semibold">Etapa</th>
                            <th class="px-4 py-3 text-left font-semibold">Solicitante</th>
                            <th class="px-4 py-3 text-right font-semibold">Cantidad / Monto / Minutos</th>
                            <th class="px-4 py-3 text-left font-semibold">Enviado</th>
                            <th class="px-4 py-3 text-right font-semibold">Acción</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($rows as $row)
                            <tr>
                                <td class="px-4 py-3 align-top">
                                    {{ $row['document_label'] }}
                                </td>

                                <td class="px-4 py-3 align-top font-medium text-gray-950 dark:text-white">
                                    {{ $row['document_number'] ?: '—' }}
                                    @include('filament.partials.approval-priority-inline', ['row' => $row])
                                </td>

                                <td class="px-4 py-3 align-top">{{ $row['step_name'] ?: '—' }}</td>

                                <td class="px-4 py-3 align-top">{{ $row['requester_name'] ?: '—' }}</td>

                                <td class="px-4 py-3 align-top text-right">
                                    {{ number_format((float) $row['amount_total'], 2) }}
                                </td>

                                <td class="px-4 py-3 align-top">
                                    @if(! empty($row['sent_at']))
                                        {{ \Carbon\Carbon::parse($row['sent_at'])->format('d/m/Y H:i') }}
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="px-4 py-3 align-top">
                                    <div class="flex items-center justify-end gap-2">
                                        <a
                                            href="{{ $row['url'] }}"
                                            class="inline-flex items-center rounded-lg px-2 py-1 text-sm font-semibold text-primary-600 hover:bg-primary-50"
                                        >
                                            Abrir
                                        </a>

                                        <x-filament::button
                                            type="button"
                                            size="sm"
                                            color="success"
                                            icon="heroicon-o-check"
                                            wire:click="approveStep({{ (int) $row['step_id'] }})"
                                            wire:confirm="¿Aprobar {{ $row['document_label'] }} {{ $row['document_number'] }}?"
                                            wire:loading.attr="disabled"
                                        >
                                            Aprobar
                                        </x-filament::button>

                                        <x-filament::button
                                            type="button"
                                            size="sm"
                                            color="danger"
                                            icon="heroicon-o-x-mark"
                                            wire:click="openRejectModal({{ (int) $row['step_id'] }})"
                                            wire:loading.attr="disabled"
                                        >
                                            Rechazar
                                        </x-filament::button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    No tienes aprobaciones pendientes.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($rejectStepId)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4">
                <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900">
                    <div class="text-lg font-semibold text-gray-950 dark:text-white">
                        Rechazar documento
                    </div>

                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $rejectDocumentLabel }}
                    </div>

                    <div class="mt-5">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                            Motivo de rechazo
                        </label>

                        <textarea
                            wire:model.defer="rejectReason"
                            rows="4"
                            class="mt-2 block w-full rounded-xl border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950"
                            placeholder="Ej. Falta justificar la compra o el monto no corresponde."
                        ></textarea>

                        @error('rejectReason')
                            <div class="mt-2 text-sm text-danger-600">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <x-filament::button color="gray" wire:click="cancelReject">
                            Cancelar
                        </x-filament::button>

                        <x-filament::button color="danger" wire:click="confirmRejectStep">
                            Rechazar
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
