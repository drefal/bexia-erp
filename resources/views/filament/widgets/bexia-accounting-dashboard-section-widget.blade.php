<x-filament-widgets::widget>
    <div
        x-data="{ collapsed: localStorage.getItem('bexia_section_accounting_collapsed') === '1' }"
        style="background:#ffffff; border:1px solid #e5e7eb; border-radius:24px; padding:16px; box-shadow:0 8px 24px rgba(15,23,42,.04); overflow:hidden;"
    >
        <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:22px; padding:20px;">
            <div style="background:#dbeafe; border:1px solid #93c5fd; border-radius:18px; padding:18px; margin-bottom:20px;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                    <div style="display:flex; align-items:center; gap:14px;">
                        <div style="width:8px; height:56px; border-radius:999px; background:#2563eb;"></div>

                        <div>
                            <div style="font-size:12px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#1d4ed8;">
                                Sección del Escritorio
                            </div>

                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:4px;">
                                <h2 style="margin:0; font-size:20px; font-weight:800; color:#0f172a;">Contabilidad</h2>

                                <button
                                    type="button"
                                    style="border:1px solid #60a5fa; border-radius:10px; background:#ffffff; padding:8px 12px; font-size:12px; font-weight:800; color:#0f172a; cursor:pointer;"
                                    x-on:click="collapsed = ! collapsed; localStorage.setItem('bexia_section_accounting_collapsed', collapsed ? '1' : '0')"
                                    x-text="collapsed ? 'Expandir' : 'Contraer'"
                                >Contraer</button>

                                <a
                                    href="/admin/{{ $company_id }}/dashboard-section-pdf/contabilidad"
                                    target="_blank"
                                    style="border:1px solid #60a5fa; border-radius:10px; background:#ffffff; padding:8px 12px; font-size:12px; font-weight:800; color:#0f172a; text-decoration:none;"
                                >Exportar PDF</a>
                            </div>

                            <div style="font-size:14px; color:#475569; margin-top:6px;">
                                {{ $company_name ? 'Empresa: ' . $company_name . ' · ' : '' }}Indicadores contables y procesos pendientes.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="! collapsed" x-transition>
                <div style="display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:18px;">
                    @foreach ($cards as $card)
                        <div style="background:#ffffff; border:1px solid #bfdbfe; border-radius:18px; padding:24px; box-shadow:0 6px 18px rgba(15,23,42,.04);">
                            <div style="font-size:14px; font-weight:600; color:#64748b;">{{ $card['label'] }}</div>
                            <div style="font-size:30px; font-weight:800; color:#020617; margin-top:12px;">{{ $card['value'] }}</div>
                            <div style="font-size:14px; color:#475569; margin-top:12px;">{{ $card['description'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
