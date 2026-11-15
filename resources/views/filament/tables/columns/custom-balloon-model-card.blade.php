
@once
    <style>
        /* GLB3I4_MODEL_GRID */

        /*
         * Esta vista sólo existe en la tabla de modelos de globos.
         * Convertimos el cuerpo de la tabla en una cuadrícula real.
         */

        .fi-ta-content table:has(
            [data-custom-balloon-model-card]
        ) {
            display: block !important;
            width: 100% !important;
        }

        .fi-ta-content table:has(
            [data-custom-balloon-model-card]
        ) thead {
            display: none !important;
        }

        .fi-ta-content table:has(
            [data-custom-balloon-model-card]
        ) tbody {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 1rem !important;
            padding: 1rem !important;
            width: 100% !important;
        }

        .fi-ta-content table:has(
            [data-custom-balloon-model-card]
        ) tbody > tr {
            display: block !important;
            width: 100% !important;
            border: 0 !important;
            background: transparent !important;
        }

        .fi-ta-content table:has(
            [data-custom-balloon-model-card]
        ) tbody > tr > td {
            display: block !important;
            width: 100% !important;
            padding: 0 !important;
            border: 0 !important;
        }

        /*
         * Ocultamos celdas auxiliares de acciones porque la tarjeta
         * completa ya abre Editar mediante recordUrl().
         */
        .fi-ta-content table:has(
            [data-custom-balloon-model-card]
        ) tbody > tr > td:not(:has([data-custom-balloon-model-card])) {
            display: none !important;
        }

        @media (max-width: 1100px) {
            .fi-ta-content table:has(
                [data-custom-balloon-model-card]
            ) tbody {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 700px) {
            .fi-ta-content table:has(
                [data-custom-balloon-model-card]
            ) tbody {
                grid-template-columns:
                    minmax(0, 1fr) !important;
            }
        }
    </style>
@endonce

@php
    /** @var \App\Models\CustomBalloonModel $record */
    $record = $getRecord();

    $imageUrl = null;

    if ($record->image_path) {
        $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')
            ->url($record->image_path);
    }

    $sizes = $record->modelSizes
        ->where('is_active', true)
        ->map(fn ($item) => $item->size?->name)
        ->filter()
        ->values();

    $legacyId = data_get($record->metadata, 'legacy_model_id');
@endphp

<div
    data-custom-balloon-model-card
    class="
        h-full overflow-hidden rounded-xl
        bg-white ring-1 ring-gray-950/5
        shadow-sm transition
        hover:-translate-y-0.5 hover:shadow-md
        dark:bg-gray-900 dark:ring-white/10
    "
>
    <div class="relative h-64 overflow-hidden bg-gray-100 dark:bg-gray-800">
        @if ($imageUrl)
            <img
                src="{{ $imageUrl }}"
                alt="{{ $record->name }}"
                loading="lazy"
                class="h-full w-full object-cover"
            >
        @else
            <div class="flex h-full items-center justify-center text-gray-400">
                <div class="text-center">
                    <svg
                        class="mx-auto h-12 w-12"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5A2.25 2.25 0 0 0 22.5 17.25V6.75A2.25 2.25 0 0 0 20.25 4.5H3.75A2.25 2.25 0 0 0 1.5 6.75v10.5A2.25 2.25 0 0 0 3.75 19.5Zm10.5-11.25h.008v.008h-.008V8.25Z"
                        />
                    </svg>

                    <div class="mt-2 text-xs">
                        Sin imagen
                    </div>
                </div>
            </div>
        @endif

        <div class="absolute left-3 top-3">
            <span
                class="
                    inline-flex items-center rounded-md
                    bg-white/90 px-2 py-1
                    text-xs font-semibold text-gray-700
                    shadow-sm backdrop-blur
                    dark:bg-gray-900/90 dark:text-gray-200
                "
            >
                {{ $record->code }}
            </span>
        </div>

        <div class="absolute right-3 top-3">
            @if ($record->is_active)
                <span
                    class="
                        inline-flex items-center rounded-full
                        bg-success-50 px-2 py-1
                        text-xs font-medium text-success-700
                        ring-1 ring-inset ring-success-600/20
                        dark:bg-success-400/10 dark:text-success-400
                    "
                >
                    Activo
                </span>
            @else
                <span
                    class="
                        inline-flex items-center rounded-full
                        bg-gray-100 px-2 py-1
                        text-xs font-medium text-gray-600
                        dark:bg-gray-800 dark:text-gray-300
                    "
                >
                    Inactivo
                </span>
            @endif
        </div>
    </div>

    <div class="p-4">
        <div class="min-h-[48px]">
            <div
                class="
                    line-clamp-2 text-base font-semibold
                    text-gray-950 dark:text-white
                "
                title="{{ $record->name }}"
            >
                {{ $record->name }}
            </div>

            @if ($legacyId)
                <div class="mt-1 text-xs text-gray-400">
                    Histórico #{{ $legacyId }}
                </div>
            @endif
        </div>

        <div class="mt-4">
            <div
                class="
                    mb-1.5 text-[11px] font-semibold
                    uppercase tracking-wide text-gray-400
                "
            >
                Tamaños disponibles
            </div>

            @if ($sizes->isNotEmpty())
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($sizes as $size)
                        <span
                            class="
                                inline-flex items-center rounded-full
                                bg-primary-50 px-2.5 py-1
                                text-xs font-semibold text-primary-700
                                ring-1 ring-inset ring-primary-600/10
                                dark:bg-primary-400/10 dark:text-primary-400
                            "
                        >
                            {{ $size }}
                        </span>
                    @endforeach
                </div>
            @else
                <div class="text-sm font-medium text-warning-600 dark:text-warning-400">
                    Sin tamaño asignado
                </div>
            @endif
        </div>

        <div
            class="
                mt-4 border-t border-gray-100 pt-3
                text-xs text-gray-400
                dark:border-white/10
            "
        >
            Actualizado:
            {{ optional($record->updated_at)->format('d/m/Y H:i') }}
        </div>
    </div>
</div>
