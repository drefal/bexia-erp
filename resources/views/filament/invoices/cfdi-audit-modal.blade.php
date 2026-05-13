@php
    // BEXIA_V5523V2_CFDI_AUDIT_LABELS
    $actionLabels = [
        'validate' => 'Validación CFDI',
        'assign_folio' => 'Asignación de folio',
        'generate_signed_xml' => 'Generación de XML firmado',
        'stamp' => 'Timbrado CFDI',
        'generate_pdf' => 'Generación de PDF',
        'send_cfdi_email' => 'Envío por correo',
        'send_cfdi_email_resend' => 'Reenvío por correo',
        'download_cfdi_pdf' => 'Descarga PDF',
        'download_cfdi_xml' => 'Descarga XML',
        'download_cfdi_zip' => 'Descarga ZIP',
        'prepare_cfdi_cancel' => 'Preparación de cancelación',
        'send_cfdi_cancel' => 'Envío de cancelación PAC/SAT',
    ];

    $statusLabels = [
        'success' => 'Correcto',
        'error' => 'Error',
        'ready_to_cancel' => 'Listo para cancelar',
        'cancelled' => 'Cancelado',
        'pending' => 'Pendiente',
        'accepted' => 'Aceptado',
        'rejected' => 'Rechazado',
    ];
@endphp

<div class="space-y-4">
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm dark:border-gray-700 dark:bg-gray-900">
        <div><strong>Factura:</strong> {{ $invoice->number ?? $invoice->id }}</div>
        <div><strong>UUID:</strong> {{ $invoice->cfdi_uuid ?: 'Sin UUID' }}</div>
        <div><strong>Estado CFDI:</strong> {{ $invoice->cfdi_status ?: 'Sin estado' }}</div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold">Fecha</th>
                    <th class="px-3 py-2 text-left font-semibold">Acción</th>
                    <th class="px-3 py-2 text-left font-semibold">Estado</th>
                    <th class="px-3 py-2 text-left font-semibold">Mensaje</th>
                    <th class="px-3 py-2 text-left font-semibold">Usuario</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($audits as $audit)
                    <tr>
                        <td class="whitespace-nowrap px-3 py-2">{{ $audit->created_at ?? '' }}</td>
                        <td class="whitespace-nowrap px-3 py-2 font-medium">{{ $actionLabels[$audit->action ?? ''] ?? ($audit->action ?? '') }}</td>
                        <td class="whitespace-nowrap px-3 py-2">{{ $statusLabels[$audit->status ?? ''] ?? ($audit->status ?? '') }}</td>
                        <td class="px-3 py-2">{{ $audit->message ?? '' }}</td>
                        <td class="whitespace-nowrap px-3 py-2">
                            {{ $audit->user_name ?? $audit->user_email ?? $audit->user_id ?? 'Sistema' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-gray-500">
                            Esta factura todavía no tiene auditoría CFDI.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500">
        La auditoría es solo lectura. Registra eventos CFDI como timbrado, PDF, correo, descarga y cancelación.
    </p>
</div>
