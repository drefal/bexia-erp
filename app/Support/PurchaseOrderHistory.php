<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseOrderHistory
{
    public static function log(
        int $purchaseOrderId,
        string $event,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $notes = null,
        array $metadata = []
    ): void {
        if (! Schema::hasTable('purchase_order_status_logs')) {
            return;
        }

        $order = Schema::hasTable('purchase_orders')
            ? DB::table('purchase_orders')->where('id', $purchaseOrderId)->first()
            : null;

        DB::table('purchase_order_status_logs')->insert([
            'purchase_order_id' => $purchaseOrderId,
            'company_id' => $order->company_id ?? null,
            'user_id' => auth()->id(),
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'notes' => $notes,
            'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function statusLabel(?string $status): string
    {
        return match ((string) $status) {
            'draft' => 'Borrador',
            'review' => 'Pendiente de revisión',
            'confirmed' => 'Confirmada',
            'partial_received' => 'Recepción parcial',
            'received' => 'Recibida',
            'partial_invoiced' => 'Facturada parcial',
            'invoiced' => 'Facturada',
            'cancelled' => 'Cancelada',
            default => $status ? ucfirst($status) : '—',
        };
    }

    public static function eventLabel(?string $event): string
    {
        return match ((string) $event) {
            'created' => 'Creación',
            'lines_changed' => 'Productos actualizados',
            'confirm_order' => 'Confirmación',
            'sent_to_review' => 'Enviada a aprobación',
            'confirmed' => 'Confirmada',
            'approval_error' => 'Error de aprobación',
            default => $event ? ucfirst(str_replace('_', ' ', $event)) : '—',
        };
    }
}
