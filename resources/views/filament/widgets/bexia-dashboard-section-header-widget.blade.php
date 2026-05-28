<x-filament-widgets::widget>
    <x-filament::section>
        @include('filament.widgets.partials.bexia-dashboard-section-tools')

        <div
            class="bexia-section-shell px-6 py-4"
            data-bexia-section-header="{{ $sectionKey ?? 'general' }}"
            data-bexia-section-title="{{ $title ?? 'Sección' }}"
            x-data="{
                sectionKey: @js($sectionKey ?? 'general'),
                sectionTitle: @js($title ?? 'Sección'),
                sectionBackground: @js($sectionBackground ?? '#ffffff'),
                sectionBorder: @js($sectionBorder ?? '#e2e8f0'),
                collapsed: false,

                root() {
                    return this.$el.closest('.fi-wi-widget')
                        || this.$el.closest('[wire\\:id]')
                        || this.$el.closest('section')?.parentElement
                        || this.$el.parentElement;
                },

                items() {
                    const root = this.root();

                    if (! root || ! root.parentElement) {
                        return [];
                    }

                    const siblings = Array.from(root.parentElement.children);
                    const start = siblings.indexOf(root);
                    const found = [];

                    for (let i = start + 1; i < siblings.length; i++) {
                        const node = siblings[i];

                        if (node.querySelector('[data-bexia-section-header]')) {
                            break;
                        }

                        found.push(node);
                    }

                    return found;
                },

                apply() {
                    const key = this.sectionKey;
                    const bg = this.sectionBackground;
                    const border = this.sectionBorder;

                    this.collapsed = localStorage.getItem('bexia_dashboard_section_collapsed_' + key) === '1';

                    this.items().forEach((node) => {
                        node.setAttribute('data-bexia-section-item', key);
                        node.style.background = bg;
                        node.style.borderColor = border;
                        node.style.display = this.collapsed ? 'none' : '';

                        node.querySelectorAll('.fi-section, .fi-wi-stats-overview-stat').forEach((child) => {
                            child.style.background = bg;
                            child.style.borderColor = border;
                        });
                    });
                },

                toggle() {
                    this.collapsed = ! this.collapsed;
                    localStorage.setItem('bexia_dashboard_section_collapsed_' + this.sectionKey, this.collapsed ? '1' : '0');
                    this.apply();
                },

                exportPdf() {
                    const root = this.root();
                    const nodes = [root].concat(this.items().filter((node) => node.style.display !== 'none'));
                    const win = window.open('', '_blank', 'width=1200,height=800');

                    if (! win) {
                        alert('El navegador bloqueó la ventana emergente. Permite pop-ups para exportar.');
                        return;
                    }

                    const doc = win.document;
                    doc.open();
                    doc.write('<!doctype html><html><head><meta charset=&quot;utf-8&quot;><title>' + this.sectionTitle + ' - Bexia ERP</title></head><body></body></html>');
                    doc.close();

                    const style = doc.createElement('style');
                    style.textContent = 'body{font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a;margin:24px}.print-actions{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;padding:14px 16px;background:white;border:1px solid #e2e8f0;border-radius:14px}button{border:0;border-radius:10px;padding:10px 14px;background:#2563eb;color:white;font-weight:700;cursor:pointer}.fi-wi-widget,section{margin-bottom:14px}.bexia-section-toolbar{display:none!important}table{width:100%;border-collapse:collapse;background:white}th,td{border-bottom:1px solid #e2e8f0;padding:8px;font-size:12px}@media print{body{background:white;margin:12mm}.print-actions{display:none}}';
                    doc.head.appendChild(style);

                    const bar = doc.createElement('div');
                    bar.className = 'print-actions';
                    bar.innerHTML = '<div><strong>' + this.sectionTitle + '</strong><br><small>Generado desde Bexia ERP</small></div>';

                    const button = doc.createElement('button');
                    button.textContent = 'Imprimir / Guardar PDF';
                    button.onclick = function () { win.print(); };
                    bar.appendChild(button);

                    const content = doc.createElement('div');
                    content.innerHTML = nodes.map((node) => node.outerHTML).join('');

                    doc.body.appendChild(bar);
                    doc.body.appendChild(content);

                    win.focus();
                }
            }"
            x-init="apply(); setTimeout(() => apply(), 500); setTimeout(() => apply(), 1500);"
            style="background: {{ $softBackground ?? 'white' }};"
        >
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-4">
                    <div class="h-14 w-2 rounded-full" style="background: {{ $accent ?? '#2563eb' }};"></div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ $eyebrow ?? 'Sección del Escritorio' }}
                        </p>
                        <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                            {{ $title ?? 'Sección' }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {{ $description ?? '' }}
                        </p>
                    </div>
                </div>

                <div class="bexia-section-toolbar">
                    <button type="button" class="bexia-section-button" x-on:click="toggle()" x-text="collapsed ? 'Expandir' : 'Contraer'">
                        Contraer
                    </button>

                    <button type="button" class="bexia-section-button" x-on:click="exportPdf()">
                        Exportar PDF
                    </button>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
