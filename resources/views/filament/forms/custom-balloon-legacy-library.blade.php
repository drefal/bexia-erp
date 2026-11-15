@php
    use App\Models\CustomBalloonModel;
    use Illuminate\Support\Facades\Storage;

    /*
     * Todas las imágenes históricas disponibles.
     */
    $libraryFiles = collect(
        Storage::disk('public')->files(
            'custom-balloons/library/legacy'
        )
    )
        ->filter(
            fn (string $path): bool =>
                in_array(
                    strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                    ['jpg', 'jpeg', 'png', 'webp'],
                    true
                )
        );

    /*
     * Excluimos del selector las fotografías que ya están ligadas
     * a algún modelo, comparando por nombre de archivo.
     */
    $usedBasenames = CustomBalloonModel::query()
        ->where('company_id', 3)
        ->whereNotNull('image_path')
        ->pluck('image_path')
        ->map(
            fn ($path) =>
                strtolower(basename((string) $path))
        )
        ->filter()
        ->unique()
        ->flip();

    $availableFiles = $libraryFiles
        ->reject(
            fn (string $path): bool =>
                $usedBasenames->has(
                    strtolower(basename($path))
                )
        )
        ->sortBy(
            fn (string $path): string =>
                strtolower(basename($path))
        )
        ->values();
@endphp

<div
    x-data="{
        search: '',
        selecting: false,
    }"
    class="space-y-3"
>
    <div
        class="
            rounded-xl border border-gray-200
            bg-gray-50 p-4
            dark:border-white/10 dark:bg-white/5
        "
    >
        <div
            class="
                flex flex-col gap-3
                sm:flex-row sm:items-center sm:justify-between
            "
        >
            <div>
                <div
                    class="
                        text-sm font-semibold
                        text-gray-950 dark:text-white
                    "
                >
                    Biblioteca histórica
                </div>

                <div
                    class="
                        mt-1 text-sm
                        text-gray-500 dark:text-gray-400
                    "
                >
                    {{ number_format($availableFiles->count()) }}
                    imágenes disponibles que todavía no están
                    asignadas a un modelo.
                </div>
            </div>

            <x-filament::modal
                id="custom-balloon-legacy-library"
                width="7xl"
                :close-by-clicking-away="false"
            >
                <x-slot name="trigger">
                    <x-filament::button
                        type="button"
                        color="gray"
                        icon="heroicon-o-photo"
                    >
                        Elegir de biblioteca histórica
                    </x-filament::button>
                </x-slot>

                <x-slot name="heading">
                    Biblioteca histórica de globos
                </x-slot>

                <x-slot name="description">
                    Selecciona una imagen existente.
                    El archivo no se duplicará.
                </x-slot>

                <div
                    x-data="{ search: '' }"
                    class="space-y-4"
                >
                    <div class="sticky top-0 z-10 bg-white pb-3 dark:bg-gray-900">
                        <div class="relative">
                            <input
                                x-model.debounce.200ms="search"
                                type="search"
                                placeholder="Buscar por nombre de archivo..."
                                class="
                                    block w-full rounded-lg
                                    border-gray-300
                                    bg-white px-4 py-2.5
                                    text-sm text-gray-950
                                    shadow-sm outline-none
                                    focus:border-primary-500
                                    focus:ring-primary-500
                                    dark:border-white/10
                                    dark:bg-white/5
                                    dark:text-white
                                "
                            >
                        </div>

                        <div
                            class="
                                mt-2 text-xs
                                text-gray-500 dark:text-gray-400
                            "
                        >
                            {{ number_format($availableFiles->count()) }}
                            imágenes disponibles
                        </div>
                    </div>

                    @if ($availableFiles->isEmpty())
                        <div
                            class="
                                rounded-xl border border-gray-200
                                p-8 text-center text-sm text-gray-500
                                dark:border-white/10 dark:text-gray-400
                            "
                        >
                            No hay imágenes históricas pendientes
                            de asignar.
                        </div>
                    @else
                        <div
                            class="
                                grid grid-cols-2 gap-3
                                sm:grid-cols-3
                                md:grid-cols-4
                                lg:grid-cols-5
                                xl:grid-cols-6
                            "
                        >
                            @foreach ($availableFiles as $path)
                                @php
                                    $filename = basename($path);
                                    $url = Storage::disk('public')->url($path);
                                    $searchName = mb_strtolower($filename);
                                @endphp

                                <button
                                    type="button"
                                    x-show="
                                        search === '' ||
                                        @js($searchName)
                                            .includes(
                                                search.toLowerCase()
                                            )
                                    "
                                    x-on:click="
                                        selecting = true;

                                        $wire.set(
                                            'data.image_path',
                                            [@js($path)]
                                        );

                                        $dispatch(
                                            'close-modal',
                                            {
                                                id:
                                                'custom-balloon-legacy-library'
                                            }
                                        );

                                        selecting = false;
                                    "
                                    class="
                                        group overflow-hidden
                                        rounded-xl border border-gray-200
                                        bg-white text-left
                                        shadow-sm transition
                                        hover:-translate-y-0.5
                                        hover:border-primary-400
                                        hover:shadow-md
                                        focus:outline-none
                                        focus:ring-2
                                        focus:ring-primary-500
                                        dark:border-white/10
                                        dark:bg-gray-900
                                    "
                                >
                                    <div
                                        class="
                                            aspect-square overflow-hidden
                                            bg-gray-100 dark:bg-gray-800
                                        "
                                    >
                                        <img
                                            src="{{ $url }}"
                                            alt="{{ $filename }}"
                                            loading="lazy"
                                            class="
                                                h-full w-full object-cover
                                                transition duration-200
                                                group-hover:scale-105
                                            "
                                        >
                                    </div>

                                    <div class="p-2">
                                        <div
                                            class="
                                                truncate text-xs font-medium
                                                text-gray-700
                                                dark:text-gray-200
                                            "
                                            title="{{ $filename }}"
                                        >
                                            {{ $filename }}
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-filament::modal>
        </div>
    </div>
</div>
