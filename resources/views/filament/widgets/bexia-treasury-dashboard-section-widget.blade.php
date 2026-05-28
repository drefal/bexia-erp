<x-filament-widgets::widget>
    <div
        wire:poll.{{ (int) ($refresh_seconds ?? 60) }}s
        x-data="{ collapsed: localStorage.getItem('bexia_section_treasury_collapsed') === '1' }"
        style="background:#ffffff; border:1px solid #e5e7eb; border-radius:24px; padding:16px; box-shadow:0 8px 24px rgba(15,23,42,.04); overflow:hidden;"
    >
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:22px; padding:20px;">
            <div style="background:#dcfce7; border:1px solid #86efac; border-radius:18px; padding:18px; margin-bottom:20px;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                    <div style="display:flex; align-items:center; gap:14px;">
                        <div style="width:8px; height:56px; border-radius:999px; background:#22c55e;"></div>

                        <div>
                            <div style="font-size:12px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#15803d;">
                                Sección del Escritorio
                            </div>

                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:4px;">
                                <h2 style="margin:0; font-size:24px; font-weight:800; color:#0f172a;">Tesorería / Efectivo</h2>

                                <button
                                    type="button"
                                    style="border:1px solid #4ade80; border-radius:10px; background:#ffffff; padding:8px 12px; font-size:12px; font-weight:800; color:#0f172a; cursor:pointer;"
                                    x-on:click="collapsed = ! collapsed; localStorage.setItem('bexia_section_treasury_collapsed', collapsed ? '1' : '0')"
                                    x-text="collapsed ? 'Expandir' : 'Contraer'"
                                >Contraer</button>

                                <a
                                    href="/admin/{{ $company_id }}/dashboard-section-pdf/tesoreria"
                                    target="_blank"
                                    style="border:1px solid #4ade80; border-radius:10px; background:#ffffff; padding:8px 12px; font-size:12px; font-weight:800; color:#0f172a; text-decoration:none;"
                                >Exportar PDF</a>
                            </div>

                            <div style="font-size:14px; color:#475569; margin-top:6px;">
                                {{ $company_name ? 'Empresa: ' . $company_name . ' · ' : '' }}Actualización automática cada {{ $refresh_label ?? "1 minuto" }}.
                            </div>
                        </div>
                    </div>

                    <div style="background:#ffffff; border:1px solid #86efac; border-radius:14px; padding:12px 16px; text-align:right; box-shadow:0 4px 14px rgba(15,23,42,.04);">
                        <div style="font-size:12px; color:#64748b;">Última lectura</div>
                        <div style="font-size:18px; font-weight:800; color:#0f172a;">{{ $updated_at }}</div>
                    </div>
                </div>
            </div>

            <div x-show="! collapsed" x-transition style="display:block;">
                <div style="display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:18px; margin-bottom:22px;">
                    <div style="background:#ffffff; border:1px solid #bbf7d0; border-radius:18px; padding:24px; box-shadow:0 6px 18px rgba(15,23,42,.04);">
                        <div style="font-size:14px; font-weight:600; color:#64748b;">Efectivo actual</div>
                        <div style="font-size:24px; font-weight:800; color:#020617; margin-top:12px;">$ {{ number_format($total_cash, 2) }} MXN</div>
                        <div style="font-size:14px; color:#475569; margin-top:12px;">Saldo en cajas operativas</div>
                    </div>

                    <div style="background:#ffffff; border:1px solid #bbf7d0; border-radius:18px; padding:24px; box-shadow:0 6px 18px rgba(15,23,42,.04);">
                        <div style="font-size:14px; font-weight:600; color:#64748b;">En tránsito</div>
                        <div style="font-size:24px; font-weight:800; color:#020617; margin-top:12px;">$ {{ number_format($transit_total, 2) }} MXN</div>
                        <div style="font-size:14px; color:#475569; margin-top:12px;">Aprobado / pendiente sin aplicar</div>
                    </div>

                    <div style="background:#ffffff; border:1px solid #bbf7d0; border-radius:18px; padding:24px; box-shadow:0 6px 18px rgba(15,23,42,.04);">
                        <div style="font-size:14px; font-weight:600; color:#64748b;">Entradas hoy</div>
                        <div style="font-size:24px; font-weight:800; color:#020617; margin-top:12px;">$ {{ number_format($today_in, 2) }} MXN</div>
                        <div style="font-size:14px; color:#475569; margin-top:12px;">Movimientos de entrada</div>
                    </div>

                    <div style="background:#ffffff; border:1px solid #bbf7d0; border-radius:18px; padding:24px; box-shadow:0 6px 18px rgba(15,23,42,.04);">
                        <div style="font-size:14px; font-weight:600; color:#64748b;">Salidas hoy</div>
                        <div style="font-size:24px; font-weight:800; color:#020617; margin-top:12px;">$ {{ number_format($today_out, 2) }} MXN</div>
                        <div style="font-size:14px; color:#475569; margin-top:12px;">Movimientos de salida</div>
                    </div>
                </div>

                <div style="background:#ffffff; border:1px solid #bbf7d0; border-radius:18px; padding:24px; margin-bottom:22px; box-shadow:0 6px 18px rgba(15,23,42,.04);">
                    <div style="margin-bottom:18px;">
                        <h3 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;">Cajas operativas</h3>
                        <p style="margin:4px 0 0; font-size:14px; color:#64748b;">Columnas compactas por saldo relativo.</p>
                    </div>

                    <div style="display:grid; grid-template-columns:repeat(5, minmax(0, 1fr)); gap:18px;">
                        @foreach ($columns as $column)
                            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:18px; padding:18px; box-shadow:0 4px 14px rgba(15,23,42,.04);">
                                <div style="height:128px; display:flex; align-items:flex-end; border-radius:14px; background:#f8fafc; padding:12px;">
                                    <div style="width:100%; height:{{ $column['percent'] }}%; background:{{ $column['color'] }}; border-radius:12px 12px 4px 4px;"></div>
                                </div>
                                <div style="margin-top:14px; font-size:14px; font-weight:800; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $column['name'] }}</div>
                                <div style="font-size:12px; color:#64748b;">{{ $column['scope_label'] }}</div>
                                <div style="font-size:18px; font-weight:800; color:#0f172a; margin-top:10px;">{{ $column['money'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:22px; margin-bottom:22px;">
                    <div style="background:#ffffff; border:1px solid #bbf7d0; border-radius:18px; padding:24px; box-shadow:0 6px 18px rgba(15,23,42,.04);">
                        <h3 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;">Flujo de efectivo del día</h3>
                        <p style="margin:4px 0 18px; font-size:14px; color:#64748b;">Entradas y salidas agrupadas por hora.</p>

                        @php($hasFlow = collect($flow)->filter(fn ($row) => $row['in'] > 0 || $row['out'] > 0)->isNotEmpty())

                        @if (! $hasFlow)
                            <div style="border:1px dashed #cbd5e1; border-radius:14px; padding:12px 16px; font-size:14px; color:#64748b;">
                                Sin movimientos de efectivo registrados hoy.
                            </div>
                        @else
                            <div style="display:flex; flex-direction:column; gap:12px;">
                                @foreach ($flow as $row)
                                    @if ($row['in'] > 0 || $row['out'] > 0)
                                        @php($inPercent = $flow_max > 0 ? min(100, round(($row['in'] / $flow_max) * 100, 2)) : 0)
                                        @php($outPercent = $flow_max > 0 ? min(100, round(($row['out'] / $flow_max) * 100, 2)) : 0)

                                        <div style="font-size:12px;">
                                            <strong>{{ $row['hour'] }}</strong>
                                            <div style="height:10px; background:#f1f5f9; border-radius:999px; margin-top:6px;">
                                                <div style="height:10px; width:{{ $inPercent }}%; background:#22c55e; border-radius:999px;"></div>
                                            </div>
                                            <div style="color:#64748b; margin-top:3px;">Entrada $ {{ number_format($row['in'], 2) }}</div>

                                            <div style="height:10px; background:#f1f5f9; border-radius:999px; margin-top:6px;">
                                                <div style="height:10px; width:{{ $outPercent }}%; background:#ef4444; border-radius:999px;"></div>
                                            </div>
                                            <div style="color:#64748b; margin-top:3px;">Salida $ {{ number_format($row['out'], 2) }}</div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div style="background:#ffffff; border:1px solid #bbf7d0; border-radius:18px; padding:24px; box-shadow:0 6px 18px rgba(15,23,42,.04);">
                        <h3 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;">Cajas en tránsito</h3>
                        <p style="margin:4px 0 18px; font-size:14px; color:#64748b;">Solicitudes pendientes de aplicación.</p>

                        @if ($transit->isEmpty())
                            <div style="border:1px dashed #cbd5e1; border-radius:14px; padding:12px 16px; font-size:14px; color:#64748b;">
                                No hay efectivo en tránsito.
                            </div>
                        @else
                            <div style="overflow:hidden; border:1px solid #e5e7eb; border-radius:14px;">
                                <table style="width:100%; border-collapse:collapse; font-size:14px;">
                                    <thead style="background:#f8fafc; color:#64748b; text-transform:uppercase; font-size:12px;">
                                        <tr>
                                            <th style="text-align:left; padding:12px;">Folio</th>
                                            <th style="text-align:left; padding:12px;">Origen</th>
                                            <th style="text-align:left; padding:12px;">Destino</th>
                                            <th style="text-align:right; padding:12px;">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($transit as $row)
                                            <tr style="border-top:1px solid #e5e7eb;">
                                                <td style="padding:12px;">{{ $row->number ?? ('#' . $row->id) }}</td>
                                                <td style="padding:12px;">{{ $row->source_name ?? '-' }}</td>
                                                <td style="padding:12px;">{{ $row->destination_name ?? '-' }}</td>
                                                <td style="padding:12px; text-align:right;">$ {{ number_format((float) $row->amount, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <div style="background:#ffffff; border:1px solid #bbf7d0; border-radius:18px; padding:24px; box-shadow:0 6px 18px rgba(15,23,42,.04);">
                    <h3 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;">Últimos movimientos de tesorería</h3>
                    <p style="margin:4px 0 18px; font-size:14px; color:#64748b;">Entradas y salidas recientes.</p>

                    @if ($movements->isEmpty())
                        <div style="border:1px dashed #cbd5e1; border-radius:14px; padding:12px 16px; font-size:14px; color:#64748b;">
                            No hay movimientos de tesorería registrados.
                        </div>
                    @else
                        <div style="overflow:hidden; border:1px solid #e5e7eb; border-radius:14px;">
                            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                                <thead style="background:#f8fafc; color:#64748b; text-transform:uppercase; font-size:12px;">
                                    <tr>
                                        <th style="text-align:left; padding:12px;">Caja / cuenta</th>
                                        <th style="text-align:left; padding:12px;">Tipo</th>
                                        <th style="text-align:left; padding:12px;">Referencia</th>
                                        <th style="text-align:left; padding:12px;">Fecha</th>
                                        <th style="text-align:right; padding:12px;">Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($movements as $row)
                                        <tr style="border-top:1px solid #e5e7eb;">
                                            <td style="padding:12px;">{{ $row->account_name ?? '-' }}</td>
                                            <td style="padding:12px;">{{ str_replace('_', ' ', $row->type ?? '-') }}</td>
                                            <td style="padding:12px;">{{ $row->reference ?? '-' }}</td>
                                            <td style="padding:12px;">{{ $row->created_at ? \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i') : '-' }}</td>
                                            <td style="padding:12px; text-align:right;">$ {{ number_format((float) $row->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
