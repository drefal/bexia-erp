<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-base font-semibold text-gray-950 dark:text-white">
                    Aprobaciones pendientes
                </div>

                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Tienes {{ $this->pendingCount }} documento(s) esperando tu aprobación.
                </div>
            </div>

            @if ($this->pendingCount > 0)
                <a href="{{ \App\Filament\Pages\MyPendingApprovals::getUrl() }}" class="text-sm font-semibold text-primary-600 hover:text-primary-500">
                    Ver todas
                </a>
            @endif
        </div>

        @php
            $rows = $this->getPendingRows();
        @endphp

        @if ($rows->isEmpty())
            <div class="mt-4 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500">
                No tienes aprobaciones pendientes.
            </div>
        @else
            <div class="mt-4 divide-y divide-gray-100">
                @foreach ($rows as $row)
                    <div class="flex items-start justify-between gap-4 py-3">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-900">
                                {{ $row->document_label }} {{ $row->document_number ? '· ' . $row->document_number : '' }}
                            </div>

                            <div class="mt-1 text-xs text-gray-500">
                                {{ $row->step_name }} · $ {{ number_format((float) ($row->amount_total ?? 0), 2) }}
                            </div>

                            @include('filament.partials.approval-priority-inline', ['row' => $row])
                        </div>

                        <a href="{{ $row->action_url }}" class="shrink-0 text-sm font-semibold text-primary-600 hover:text-primary-500">
                            Abrir
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
