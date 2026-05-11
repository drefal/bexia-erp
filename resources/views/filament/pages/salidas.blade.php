<x-filament-panels::page>
    @if($this->showForm)

        <div class="space-y-6">
            {{ $this->form }}

            @if(count($this->getFormActions()))
                <x-filament::section>
                    <div class="flex flex-wrap justify-end gap-3">
                        @foreach($this->getFormActions() as $action)
                            {{ $action }}
                        @endforeach
                    </div>
                </x-filament::section>
            @endif
        </div>

    @else

        @php
            $normalize = function ($text) {
                $text = (string) $text;
                $text = mb_strtolower($text, 'UTF-8');

                $map = [
                    'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
                    'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
                    'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
                    'ñ' => 'n',
                ];

                return strtr($text, $map);
            };

            $findStep = function (array $steps, string $wantedTitle) use ($normalize) {
                $wanted = $normalize($wantedTitle);

                foreach ($steps as $st) {
                    $title = $normalize($st['title'] ?? '');
                    if ($title === $wanted) {
                        return $st;
                    }
                }

                foreach ($steps as $st) {
                    $title = $normalize($st['title'] ?? '');
                    if ($wanted !== '' && str_contains($title, $wanted)) {
                        return $st;
                    }
                }

                return null;
            };

            $findValueInSchema = function (array $schema, array $data, string $stepTitle, array $nameCandidates = [], array $labelNeedles = []) use ($findStep, $normalize) {
                $steps = $schema['steps'] ?? [];
                $step = $findStep($steps, $stepTitle);

                if ($step) {
                    $fields = $step['fields'] ?? [];

                    foreach ($nameCandidates as $candidate) {
                        foreach ($fields as $f) {
                            if (($f['name'] ?? null) === $candidate) {
                                $name = $f['name'] ?? null;
                                return $name ? ($data[$name] ?? null) : null;
                            }
                        }
                    }

                    foreach ($fields as $f) {
                        $label = $normalize($f['label'] ?? '');

                        foreach ($labelNeedles as $needle) {
                            if ($needle !== '' && str_contains($label, $normalize($needle))) {
                                $name = $f['name'] ?? null;
                                return $name ? ($data[$name] ?? null) : null;
                            }
                        }
                    }
                }

                foreach ($nameCandidates as $candidate) {
                    if (array_key_exists($candidate, $data)) {
                        return $data[$candidate];
                    }
                }

                foreach ($data as $key => $value) {
                    $keyNorm = $normalize($key);

                    foreach ($labelNeedles as $needle) {
                        if ($needle !== '' && str_contains($keyNorm, $normalize($needle))) {
                            return $value;
                        }
                    }
                }

                return null;
            };

            $folioCortoTabla = function ($folio, $id) {
                return 'SAL-' . (preg_match('/(\d+)$/', (string) $folio, $m) ? $m[1] : $id);
            };

            $twoLines = function ($value, $first = 12, $second = 12) {
                $value = trim((string) ($value ?? ''));

                if ($value === '') {
                    return '-';
                }

                if (mb_strlen($value) <= $first) {
                    return e($value);
                }

                $line1 = mb_substr($value, 0, $first);
                $rest = mb_substr($value, $first);

                if (mb_strlen($rest) > $second) {
                    $line2 = mb_substr($rest, 0, $second) . '...';
                } else {
                    $line2 = $rest;
                }

                return e($line1) . '<br>' . e($line2);
            };

            $paginatedSubmissions = $this->paginatedSubmissions;
        @endphp

        <x-filament::section>
            <x-slot name="heading">
                PDFs enviados
            </x-slot>

            <div class="mb-4">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar por folio, fecha, envía, recibe, proyecto u observaciones..."
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                >
            </div>

            <div class="mb-4 flex items-center justify-between gap-4">
                <div class="text-sm text-gray-500">
                    Mostrando {{ $paginatedSubmissions->firstItem() ?? 0 }} - {{ $paginatedSubmissions->lastItem() ?? 0 }}
                    de {{ $paginatedSubmissions->total() }} registros
                </div>

                <div class="flex items-center gap-2 whitespace-nowrap">
                    <span class="text-sm text-gray-600">Mostrar</span>

                    <div class="relative">
                        <select
                            wire:model.live="perPage"
                            class="appearance-none rounded-lg border border-gray-300 pl-2 pr-8 py-1 text-sm w-[72px] bg-white"
                        >
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>
            </div>

            @if($paginatedSubmissions->count())
                <div class="overflow-x-auto">
                    <table class="w-full border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-800">
                                <th class="px-2 py-3 text-left rounded-tl-xl" style="width: 80px;">
                                    <button type="button" wire:click="sortBy('folio')" class="flex items-center gap-1 font-semibold hover:underline">
                                        Folio
                                        @if($sortField === 'folio')
                                            @if($sortDirection === 'asc')
                                                ↑
                                            @else
                                                ↓
                                            @endif
                                        @endif
                                    </button>
                                </th>

                                <th class="px-2 py-3 text-left" style="width: 135px;">
                                    <button type="button" wire:click="sortBy('fecha')" class="flex items-center gap-1 font-semibold hover:underline">
                                        Fecha
                                        @if($sortField === 'fecha')
                                            @if($sortDirection === 'asc')
                                                ↑
                                            @else
                                                ↓
                                            @endif
                                        @endif
                                    </button>
                                </th>

                                <th class="px-2 py-3 text-left" style="width: 115px;">
                                    <button type="button" wire:click="sortBy('envia')" class="flex items-center gap-1 font-semibold hover:underline">
                                        Envía
                                        @if($sortField === 'envia')
                                            @if($sortDirection === 'asc')
                                                ↑
                                            @else
                                                ↓
                                            @endif
                                        @endif
                                    </button>
                                </th>

                                <th class="px-2 py-3 text-left" style="width: 115px;">
                                    <button type="button" wire:click="sortBy('recibe')" class="flex items-center gap-1 font-semibold hover:underline">
                                        Recibe
                                        @if($sortField === 'recibe')
                                            @if($sortDirection === 'asc')
                                                ↑
                                            @else
                                                ↓
                                            @endif
                                        @endif
                                    </button>
                                </th>

                                <th class="px-2 py-3 text-left" style="width: 110px;">
                                    <button type="button" wire:click="sortBy('proyecto')" class="flex items-center gap-1 font-semibold hover:underline">
                                        Proyecto
                                        @if($sortField === 'proyecto')
                                            @if($sortDirection === 'asc')
                                                ↑
                                            @else
                                                ↓
                                            @endif
                                        @endif
                                    </button>
                                </th>

                                <th class="px-2 py-3 text-left" style="width: 180px;">
                                    <button type="button" wire:click="sortBy('observaciones')" class="flex items-center gap-1 font-semibold hover:underline">
                                        Observaciones
                                        @if($sortField === 'observaciones')
                                            @if($sortDirection === 'asc')
                                                ↑
                                            @else
                                                ↓
                                            @endif
                                        @endif
                                    </button>
                                </th>

                                <th class="px-2 py-3 text-center rounded-tr-xl" style="width: 270px;">
                                    Acciones
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($paginatedSubmissions as $row)
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <td class="px-2 py-3 align-top font-semibold">
                                        {{ $this->displayFolioFor($row['submission']) }}
                                    </td>

                                    <td class="px-2 py-3 align-top">
                                        {{ $row['fecha'] }}
                                    </td>

                                    <td class="px-2 py-3 align-top">
                                        {!! $twoLines($row['envia'], 12, 12) !!}
                                    </td>

                                    <td class="px-2 py-3 align-top">
                                        {!! $twoLines($row['recibe'], 12, 12) !!}
                                    </td>

                                    <td class="px-2 py-3 align-top">
                                        {!! $twoLines($row['proyecto'], 12, 12) !!}
                                    </td>

                                    <td class="px-2 py-3 align-top text-sm text-gray-700 dark:text-gray-300">
                                        {!! $twoLines($row['observaciones'], 18, 18) !!}
                                    </td>

                                    <td class="px-2 py-3 align-top" style="min-width: 260px;">
                                        <div style="display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;min-width:240px;">
                                            <button
                                                type="button"
                                                wire:click="viewSubmission({{ $row['submission']->id }})"
                                                style="display:inline-flex;align-items:center;border-radius:10px;background:#f3f4f6;color:#374151;padding:6px 12px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid #e5e7eb;"
                                            >
                                                Ver salida
                                            </button>

                                            <a
                                                href="{{ $this->pdfUrlFor($row['submission']) }}"
                                                target="_blank"
                                                style="display:inline-flex;align-items:center;border-radius:10px;background:#2563eb;color:#ffffff;padding:6px 12px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid #1d4ed8;"
                                            >
                                                Ver PDF
                                            </a>

                                            @if($this->canDeleteSalidas())
                                                <button
                                                    type="button"
                                                    wire:click="deleteSubmission({{ $row['submission']->id }})"
                                                    wire:confirm="¿Seguro que deseas eliminar esta salida?"
                                                    style="display:inline-flex;align-items:center;border-radius:10px;background:#dc2626;color:#ffffff;padding:6px 12px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid #b91c1c;"
                                                >
                                                    Eliminar
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $paginatedSubmissions->links() }}
                </div>
            @else
                <div class="rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">
                    No hay salidas enviadas todavía.
                </div>
            @endif
        </x-filament::section>

    @endif
</x-filament-panels::page>
