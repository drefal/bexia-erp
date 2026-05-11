<div class="space-y-4">
    <div class="rounded-xl border border-gray-200 p-4">
        <div class="text-sm text-gray-500">Ticket</div>
        <div class="text-lg font-bold">{{ $order->number ?? ('#' . $order->id) }}</div>
        <div class="mt-1 text-sm text-gray-500">
            Total: <strong>${{ number_format((float) ($order->total ?? 0), 2) }}</strong>
        </div>
    </div>

    @if($payments->isEmpty())
        <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
            Este ticket todavía no tiene pagos registrados.
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left">Método</th>
                        <th class="px-3 py-2 text-right">Importe</th>
                        <th class="px-3 py-2 text-left">Estado</th>
                        <th class="px-3 py-2 text-left">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $payment)
                        <tr class="border-t border-gray-100">
                            <td class="px-3 py-2">{{ $payment->payment_label ?? 'Pago' }}</td>
                            <td class="px-3 py-2 text-right font-bold">${{ number_format((float) ($payment->amount ?? 0), 2) }}</td>
                            <td class="px-3 py-2">{{ $payment->status ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $payment->created_at ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="text-right text-sm">
            Total pagado:
            <strong>${{ number_format((float) $payments->sum(fn ($payment) => (float) ($payment->amount ?? 0)), 2) }}</strong>
        </div>
    @endif
</div>
