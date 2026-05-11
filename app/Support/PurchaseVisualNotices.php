<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseVisualNotices
{
    public static function notice(?string $documentType, ?int $documentId): ?array
    {
        $documentId = (int) ($documentId ?? 0);

        if ($documentId <= 0) {
            return null;
        }

        return match ((string) $documentType) {
            'purchase_request' => static::purchaseRequestNotice($documentId),
            'purchase_order' => static::purchaseOrderNotice($documentId),
            default => null,
        };
    }

    public static function purchaseRequestNotice(int $id): ?array
    {
        if (! Schema::hasTable('purchase_requests')) {
            return null;
        }

        $record = DB::table('purchase_requests')->where('id', $id)->first();

        if (! $record) {
            return null;
        }

        $status = (string) ($record->status ?? '');
        $approval = static::latestApproval('purchase_request', $id);

        if ($approval && (string) ($approval->status ?? '') === 'pending') {
            return [
                'type' => 'warning',
                'title' => 'Solicitud pendiente de aprobación',
                'body' => 'Esta solicitud fue enviada al flujo de aprobación. Espera la decisión antes de convertirla en orden de compra.',
                'meta' => static::approvalMeta($approval),
            ];
        }

        if ($approval && (string) ($approval->status ?? '') === 'rejected') {
            $reason = static::approvalReason($approval);

            return [
                'type' => 'danger',
                'title' => 'Solicitud rechazada',
                'body' => $reason !== ''
                    ? 'Motivo: ' . $reason
                    : 'La solicitud fue rechazada. Revisa el historial o los comentarios antes de reenviarla.',
                'meta' => static::approvalMeta($approval),
            ];
        }

        return match ($status) {
            'draft' => [
                'type' => 'info',
                'title' => 'Solicitud en borrador',
                'body' => 'Puedes editar productos, cantidades, proveedor, almacén y ubicación antes de enviarla a aprobación.',
            ],
            'review', 'pending_approval' => [
                'type' => 'warning',
                'title' => 'Solicitud en revisión',
                'body' => 'La solicitud está en proceso de aprobación. Evita modificarla hasta que se apruebe o rechace.',
            ],
            'approved' => [
                'type' => 'success',
                'title' => 'Solicitud aprobada',
                'body' => 'La solicitud fue aprobada. Ya puede utilizarse como base para generar una orden de compra.',
                'meta' => $approval ? static::approvalMeta($approval) : null,
            ],
            'rejected' => [
                'type' => 'danger',
                'title' => 'Solicitud rechazada',
                'body' => static::lastRejectedReason('purchase_request', $id) ?: 'La solicitud fue rechazada. Ajusta la información antes de reenviarla.',
            ],
            'cancelled', 'canceled' => [
                'type' => 'gray',
                'title' => 'Solicitud cancelada',
                'body' => 'Este documento quedó cancelado y se conserva solo para trazabilidad.',
            ],
            'converted' => [
                'type' => 'success',
                'title' => 'Solicitud convertida',
                'body' => 'Esta solicitud ya fue convertida en orden de compra.',
            ],
            default => null,
        };
    }

    public static function purchaseOrderNotice(int $id): ?array
    {
        if (! Schema::hasTable('purchase_orders')) {
            return null;
        }

        $record = DB::table('purchase_orders')->where('id', $id)->first();

        if (! $record) {
            return null;
        }

        $status = (string) ($record->status ?? '');
        $approval = static::latestApproval('purchase_order', $id);

        if ($approval && (string) ($approval->status ?? '') === 'pending') {
            return [
                'type' => 'warning',
                'title' => 'Orden pendiente de aprobación',
                'body' => 'Esta orden fue enviada al flujo de aprobación. No debe confirmarse hasta que sea aprobada.',
                'meta' => static::approvalMeta($approval),
            ];
        }

        if ($approval && (string) ($approval->status ?? '') === 'rejected') {
            $reason = static::approvalReason($approval);

            return [
                'type' => 'danger',
                'title' => 'Orden de compra rechazada',
                'body' => $reason !== ''
                    ? 'Motivo: ' . $reason
                    : 'La orden fue rechazada. Revisa el historial o los comentarios antes de reenviarla.',
                'meta' => static::approvalMeta($approval),
            ];
        }

        if ((bool) ($record->created_from_xml ?? false) && (int) ($record->xml_mapping_pending_count ?? 0) > 0) {
            return [
                'type' => 'warning',
                'title' => 'XML pendiente de mapeo',
                'body' => 'Esta OC fue creada desde XML y todavía tiene ' . (int) $record->xml_mapping_pending_count . ' concepto(s) pendientes de asignar a producto.',
            ];
        }

        if ((bool) ($record->differs_from_request ?? false) && ! in_array($status, ['confirmed', 'received', 'partially_received', 'partial_received', 'invoiced', 'cancelled', 'canceled'], true)) {
            $reason = trim((string) ($record->approval_required_reason ?? ''));

            return [
                'type' => 'warning',
                'title' => 'Orden modificada contra la solicitud',
                'body' => $reason !== ''
                    ? $reason
                    : 'La orden cambió contra la solicitud aprobada. Debe revisarse antes de confirmarla.',
            ];
        }

        return match ($status) {
            'draft' => [
                'type' => 'info',
                'title' => 'Orden en borrador',
                'body' => 'Puedes revisar proveedor, productos, cantidades y costos antes de confirmar o enviar a aprobación.',
            ],
            'review', 'pending_review', 'pending_approval' => [
                'type' => 'warning',
                'title' => 'Orden en revisión',
                'body' => 'La orden está en proceso de aprobación/revisión. Espera la decisión antes de confirmarla.',
            ],
            'approved' => [
                'type' => 'success',
                'title' => 'Orden aprobada',
                'body' => 'La orden fue aprobada. Puede continuar a confirmación si corresponde.',
                'meta' => $approval ? static::approvalMeta($approval) : null,
            ],
            'rejected' => [
                'type' => 'danger',
                'title' => 'Orden rechazada',
                'body' => static::lastRejectedReason('purchase_order', $id) ?: 'La orden fue rechazada. Ajusta la información antes de reenviarla.',
            ],
            'confirmed' => [
                'type' => 'success',
                'title' => 'Orden confirmada',
                'body' => 'La orden está confirmada y lista para recepción de mercancía.',
            ],
            'partially_received', 'partial_received' => [
                'type' => 'info',
                'title' => 'Orden parcialmente recibida',
                'body' => 'Esta orden ya tiene recepción parcial. Los cambios deben controlarse para no afectar inventario.',
            ],
            'received' => [
                'type' => 'success',
                'title' => 'Orden recibida',
                'body' => 'La orden ya fue recibida. Se conserva para trazabilidad, historial y facturación.',
            ],
            'partial_invoiced' => [
                'type' => 'info',
                'title' => 'Orden facturada parcialmente',
                'body' => 'La orden tiene facturación parcial asociada.',
            ],
            'invoiced' => [
                'type' => 'success',
                'title' => 'Orden facturada',
                'body' => 'La orden ya fue facturada.',
            ],
            'cancelled', 'canceled' => [
                'type' => 'gray',
                'title' => 'Orden cancelada',
                'body' => 'Este documento quedó cancelado y se conserva solo para trazabilidad.',
            ],
            default => null,
        };
    }

    protected static function latestApproval(string $documentType, int $approvableId): ?object
    {
        if (! Schema::hasTable('approval_requests')) {
            return null;
        }

        return DB::table('approval_requests')
            ->where('document_type', $documentType)
            ->where('approvable_id', $approvableId)
            ->orderByDesc('id')
            ->first();
    }

    protected static function approvalReason(object $approval): string
    {
        $reason = trim((string) ($approval->last_decision_reason ?? ''));

        if ($reason !== '') {
            return $reason;
        }

        if (! Schema::hasTable('approval_request_steps')) {
            return '';
        }

        $step = DB::table('approval_request_steps')
            ->where('approval_request_id', $approval->id)
            ->whereIn('status', ['rejected', 'approved'])
            ->orderByDesc('acted_at')
            ->orderByDesc('id')
            ->first();

        return trim((string) (
            $step->decision_reason
            ?? $step->comments
            ?? ''
        ));
    }

    protected static function lastRejectedReason(string $documentType, int $approvableId): string
    {
        $approval = static::latestApproval($documentType, $approvableId);

        if (! $approval) {
            return '';
        }

        return static::approvalReason($approval);
    }

    protected static function approvalMeta(object $approval): ?string
    {
        $parts = [];

        if (! empty($approval->sent_at)) {
            $parts[] = 'Enviada: ' . date('d/m/Y H:i', strtotime((string) $approval->sent_at));
        }

        if (! empty($approval->completed_at)) {
            $parts[] = 'Cerrada: ' . date('d/m/Y H:i', strtotime((string) $approval->completed_at));
        }

        if (! empty($approval->amount_total)) {
            $parts[] = 'Monto: $' . number_format((float) $approval->amount_total, 2);
        }

        return empty($parts) ? null : implode(' · ', $parts);
    }
}
