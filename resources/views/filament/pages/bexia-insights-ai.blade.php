<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">
                        Bexia Insights AI
                    </h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Asistente directivo para consultar información ejecutiva de las empresas permitidas.
                    </p>
                </div>

                @if ($openAiConfigured)
                    <div class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700">
                        Agent Core + OpenAI activo
                    </div>
                @else
                    <div class="rounded-full bg-yellow-100 px-3 py-1 text-xs font-medium text-yellow-800">
                        Agent Core local
                    </div>
                @endif
            </div>

            <div class="mt-4 rounded-lg bg-blue-50 p-4 text-sm text-blue-900">
                Puedes preguntar en lenguaje natural. Bexia ejecuta solo herramientas seguras y filtradas por empresa.
                @if (! $openAiConfigured)
                    OpenAI todavía no está activado en el .env, por lo que ventas responde en modo local seguro.
                @endif
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">
                        Consulta
                    </h3>

                    <div class="mt-3 flex flex-wrap gap-2 text-xs text-gray-600">
                        <span class="rounded-full bg-gray-100 px-3 py-1">¿Cómo vamos en ventas este mes?</span>
                        <span class="rounded-full bg-gray-100 px-3 py-1">¿Cuánto vendimos hoy en PDV?</span>
                        <span class="rounded-full bg-gray-100 px-3 py-1">Dame un resumen comercial del año</span>
                        <span class="rounded-full bg-gray-100 px-3 py-1">¿Qué puedes consultar?</span>
                    </div>

                    <div class="mt-4">
                        <textarea
                            wire:model.defer="question"
                            rows="5"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="Pregunta libremente, por ejemplo: ¿cómo vamos en ventas este mes contra lo esperado?"
                        ></textarea>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <x-filament::button wire:click="sendQuestion" icon="heroicon-o-paper-airplane">
                            Enviar pregunta
                        </x-filament::button>

                        <x-filament::button color="gray" icon="heroicon-o-microphone" disabled>
                            Micrófono próximamente
                        </x-filament::button>
                    </div>

                    @if ($answer)
                        <div class="mt-6 whitespace-pre-line rounded-xl border border-primary-100 bg-primary-50 p-4 text-sm text-primary-950">
                            {{ $answer }}
                        </div>

                        @if (! empty($lastResult['mode']))
                            <div class="mt-2 text-xs text-gray-500">
                                Modo: {{ $lastResult['mode'] }}
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">
                        Alcance de seguridad
                    </h3>

                    <div class="mt-3 text-sm text-gray-600">
                        Empresas permitidas detectadas:
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse ($allowedCompanies as $company)
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                                {{ $company['name'] }}
                            </span>
                        @empty
                            <span class="text-sm text-red-600">
                                No se detectaron empresas permitidas.
                            </span>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">
                        Herramientas seguras
                    </h3>

                    <div class="mt-4 space-y-3">
                        @foreach ($tools as $tool)
                            <div class="rounded-lg border border-gray-100 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $tool['label'] }}
                                    </div>

                                    @if ($tool['enabled'])
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-medium text-green-700">
                                            Activa
                                        </span>
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">
                                            Pendiente
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-1 text-xs text-gray-500">
                                    {{ $tool['status'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
