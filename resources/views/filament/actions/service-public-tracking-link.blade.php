<div class="space-y-4">
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
        Este enlace puede compartirse con el cliente para consultar el estado actualizado de la reparación.
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">
            Enlace público de seguimiento
        </label>

        <div class="flex items-center gap-2">
            <input
                id="service-public-tracking-url"
                type="text"
                readonly
                value="{{ $url }}"
                class="w-full rounded-lg border-gray-300 text-sm"
                onclick="this.select()"
            >

            <button
                type="button"
                title="Copiar enlace"
                aria-label="Copiar enlace"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 shadow-sm hover:bg-gray-50"
                onclick="
                    const input = document.getElementById('service-public-tracking-url');
                    input.select();
                    input.setSelectionRange(0, 99999);
                    navigator.clipboard?.writeText(input.value);
                    const original = this.innerHTML;
                    this.innerHTML = '✓';
                    setTimeout(() => this.innerHTML = original, 1500);
                "
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 8.5A2.5 2.5 0 0 1 10.5 6H18A2 2 0 0 1 20 8v10a2 2 0 0 1-2 2h-7.5A2.5 2.5 0 0 1 8 17.5v-9Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 16H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1" />
                </svg>
            </button>
        </div>
    </div>

    <div class="text-xs text-gray-500">
        Si el navegador no permite copiar automáticamente, selecciona el texto y cópialo manualmente.
    </div>
</div>
