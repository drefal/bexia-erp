@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $purchaseOrderId = (int) ($purchaseOrderId ?? 0);

    $receipts = collect();
    $linesByReceipt = collect();
    $users = collect();
    $movements = collect();

    if ($purchaseOrderId > 0 && Schema::hasTable('purchase_receipts')) {
        $receipts = DB::table('purchase_receipts')
            ->where('purchase_order_id', $purchaseOrderId)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->get();

        $receiptIds = $receipts->pluck('id')->filter()->values();

        if ($receiptIds->isNotEmpty() && Schema::hasTable('purchase_receipt_lines')) {
            $linesByReceipt = DB::table('purchase_receipt_lines')
                ->whereIn('purchase_receipt_id', $receiptIds)
                ->orderBy('id')
                ->get()
                ->groupBy('purchase_receipt_id');
        }

        $movementIds = $receipts->pluck('stock_movement_id')->filter()->unique()->values();

        if ($movementIds->isNotEmpty() && Schema::hasTable('stock_movements')) {
            $movements = DB::table('stock_movements')
                ->whereIn('id', $movementIds)
                ->get()
                ->keyBy('id');
        }

        $userIds = $receipts->pluck('received_by_user_id')->filter()->unique()->values();

        if ($userIds->isNotEmpty() && Schema::hasTable('users')) {
            $users = DB::table('users')
                ->whereIn('id', $userIds)
                ->get()
                ->mapWithKeys(function ($user) {
                    $name = trim((string) ($user->name ?? ''));

                    if ($name === '') {
                        $name = trim((string) ($user->email ?? ''));
                    }

                    return [(int) $user->id => $name ?: ('Usuario #' . $user->id)];
                });
        }
    }

    $tenantId = request()->route('tenant');

    if (! $tenantId && auth()->check()) {
        $tenantId = auth()->user()?->company_id ?? null;
    }

    $money = fn ($value) => '$' . number_format((float) $value, 2);
    $qty = fn ($value) => number_format((float) $value, 6);
@endphp

<div style="display:grid; gap:14px;">
    @if($receipts->isEmpty())
        <div style="
            border:1px dashed #cbd5e1;
            background:#f8fafc;
            color:#64748b;
            border-radius:14px;
            padding:18px;
            font-size:14px;
        ">
            Esta orden todavía no tiene recepciones registradas.
        </div>
    @else
        @foreach($receipts as $receipt)
            @php
                $lines = $linesByReceipt->get($receipt->id, collect());
                $movement = $receipt->stock_movement_id ? $movements->get((int) $receipt->stock_movement_id) : null;
                $receivedBy = $receipt->received_by_user_id ? ($users->get((int) $receipt->received_by_user_id) ?: '—') : '—';
                $movementUrl = ($movement && $tenantId)
                    ? url('/admin/' . $tenantId . '/stock-movements/' . $movement->id . '/edit')
                    : null;
            @endphp

            <div style="
                border:1px solid #dbe3ef;
                border-radius:16px;
                overflow:hidden;
                background:#ffffff;
                box-shadow:0 8px 18px rgba(15,23,42,.04);
            ">
                <div style="
                    display:flex;
                    justify-content:space-between;
                    gap:14px;
                    align-items:flex-start;
                    padding:16px 18px;
                    background:#f8fafc;
                    border-bottom:1px solid #e5e7eb;
                ">
                    <div>
                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            <span style="
                                display:inline-flex;
                                align-items:center;
                                border-radius:999px;
                                background:#dbeafe;
                                color:#1e40af;
                                padding:4px 10px;
                                font-size:12px;
                                font-weight:800;
                            ">
                                {{ $receipt->number ?? ('REC #' . $receipt->id) }}
                            </span>

                            <span style="
                                display:inline-flex;
                                align-items:center;
                                border-radius:999px;
                                background:#dcfce7;
                                color:#166534;
                                padding:4px 10px;
                                font-size:12px;
                                font-weight:800;
                            ">
                                Recibida
                            </span>
                        </div>

                        <div style="margin-top:8px; color:#475569; font-size:13px;">
                            Fecha:
                            <strong style="color:#0f172a;">
                                {{ $receipt->received_at ? \Carbon\Carbon::parse($receipt->received_at)->format('d/m/Y H:i') : '—' }}
                            </strong>
                            &nbsp;·&nbsp;
                            Recibió:
                            <strong style="color:#0f172a;">{{ $receivedBy }}</strong>
                        </div>

                        @if($movement)
                            <div style="margin-top:6px; color:#475569; font-size:13px;">
                                Movimiento de inventario:
                                @if($movementUrl)
                                    <a href="{{ $movementUrl }}" style="color:#2563eb; font-weight:800; text-decoration:none;">
                                        {{ $movement->reference ?? ('Movimiento #' . $movement->id) }}
                                    </a>
                                @else
                                    <strong style="color:#0f172a;">{{ $movement->reference ?? ('Movimiento #' . $movement->id) }}</strong>
                                @endif
                                <span style="color:#94a3b8;">#{{ $movement->id }}</span>
                            </div>
                        @endif
                    </div>

                    <div style="text-align:right; min-width:160px;">
                        <div style="font-size:12px; color:#64748b; font-weight:700;">Total recibido</div>
                        <div style="font-size:18px; font-weight:900; color:#0f172a;">
                            {{ $money($receipt->total_with_tax ?? 0) }}
                        </div>
                        <div style="font-size:12px; color:#64748b;">
                            Subtotal {{ $money($receipt->total_without_tax ?? 0) }}
                        </div>
                    </div>
                </div>

                <div style="overflow:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="background:#ffffff;">
                                <th style="padding:10px 12px; text-align:left; border-bottom:1px solid #e5e7eb;">Producto</th>
                                <th style="padding:10px 12px; text-align:left; border-bottom:1px solid #e5e7eb;">Variante</th>
                                <th style="padding:10px 12px; text-align:right; border-bottom:1px solid #e5e7eb;">Cantidad</th>
                                <th style="padding:10px 12px; text-align:right; border-bottom:1px solid #e5e7eb;">Costo unit.</th>
                                <th style="padding:10px 12px; text-align:right; border-bottom:1px solid #e5e7eb;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lines as $line)
                                <tr>
                                    <td style="padding:10px 12px; border-bottom:1px solid #f1f5f9;">
                                        <strong>{{ $line->product_label ?? 'Producto' }}</strong>
                                    </td>
                                    <td style="padding:10px 12px; border-bottom:1px solid #f1f5f9; color:#475569;">
                                        {{ $line->variant_label ?? '—' }}
                                    </td>
                                    <td style="padding:10px 12px; border-bottom:1px solid #f1f5f9; text-align:right; font-variant-numeric:tabular-nums;">
                                        {{ $qty($line->received_quantity ?? 0) }}
                                        <div style="font-size:11px; color:#64748b;">{{ $line->purchase_unit_label ?? '' }}</div>
                                    </td>
                                    <td style="padding:10px 12px; border-bottom:1px solid #f1f5f9; text-align:right; font-variant-numeric:tabular-nums;">
                                        {{ $money($line->unit_cost_without_tax ?? 0) }}
                                    </td>
                                    <td style="padding:10px 12px; border-bottom:1px solid #f1f5f9; text-align:right; font-weight:800; font-variant-numeric:tabular-nums;">
                                        {{ $money($line->line_total_with_tax ?? 0) }}
                                    </td>
                                </tr>
                            @endforeach

                            @if($lines->isEmpty())
                                <tr>
                                    <td colspan="5" style="padding:14px; color:#64748b;">
                                        Esta recepción no tiene líneas registradas.
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                @if(trim((string) ($receipt->notes ?? '')) !== '')
                    <div style="padding:12px 18px; color:#475569; font-size:13px; background:#f8fafc;">
                        <strong>Notas:</strong> {{ $receipt->notes }}
                    </div>
                @endif
            </div>
        @endforeach
    @endif
</div>
