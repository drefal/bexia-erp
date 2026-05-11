<x-filament-panels::page>
    <div class="space-y-4">
        <div class="flex justify-end">
            <x-filament::button color="gray" wire:click="markAllAsRead">
                Marcar todo como leído
            </x-filament::button>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($rows as $row)
                    <div class="p-4 {{ empty($row['read_at']) ? 'bg-blue-50/60 dark:bg-blue-950/20' : '' }}">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-semibold text-gray-950 dark:text-white">
                                    {{ $row['title'] }}
                                    @if(empty($row['read_at']))
                                        <span class="ml-2 rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">Nuevo</span>
                                    @endif
                                </div>

                                <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $row['body'] }}
                                </div>

                                <div class="mt-2 text-xs text-gray-500">
                                    {{ \Carbon\Carbon::parse($row['created_at'])->format('d/m/Y H:i') }}
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                @if(! empty($row['url']))
                                    <a href="{{ $row['url'] }}" class="text-sm font-semibold text-primary-600 hover:underline">
                                        Abrir
                                    </a>
                                @endif

                                @if(empty($row['read_at']))
                                    <button type="button" wire:click="markAsRead({{ $row['id'] }})" class="text-sm font-semibold text-gray-600 hover:underline">
                                        Leído
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-500">
                        No tienes avisos.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
