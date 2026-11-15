<?php

namespace App\Support\CustomBalloons;

use App\Support\CustomBalloonsAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

final class BalloonOrderToPosService
{
    private const COMPANY_ID = 3;
    private const SOURCE_TYPE = 'custom_balloon_order';

    public function canSend(): bool
    {
        if (! CustomBalloonsAccess::can(
            'custom_balloons.update'
        )) {
            return false;
        }

        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (
            method_exists($user, 'isSystemAdmin')
            && $user->isSystemAdmin()
        ) {
            return true;
        }

        if (
            method_exists($user, 'isGroupAdmin')
            && $user->isGroupAdmin()
        ) {
            return true;
        }

        return method_exists($user, 'can')
            && $user->can('pos.pending_tickets.create');
    }

    public function availablePosPoints(): array
    {
        if (! CustomBalloonsAccess::isPapelon()) {
            return [];
        }

        $points = DB::table('pos_points')
            ->where('company_id', self::COMPANY_ID)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $options = [];

        foreach ($points as $point) {
            $sessions = DB::table('pos_sessions')
                ->where('company_id', self::COMPANY_ID)
                ->where('pos_point_id', $point->id)
                ->where('status', 'open')
                ->get(['id']);

            // No ofrecer una caja con ninguna o multiples sesiones.
            if ($sessions->count() !== 1) {
                continue;
            }

            $options[(int) $point->id] =
                (string) $point->name
                . ' (' . $point->code . ')'
                . ' - Sesion ' . $sessions->first()->id;
        }

        return $options;
    }


    /**
     * Todos los tickets anteriores deben estar cancelados
     * y sin historial de pagos antes de permitir reenvio.
     *
     * Conserva todos los tickets historicos.
     */
    private function assertPreviousTicketsCancelled(
        object $order,
        bool $lockRows = false
    ): void {
        $query = DB::table('pos_orders')
            ->where('company_id', self::COMPANY_ID)
            ->where(function ($q) use ($order) {
                $q->where(function ($inner) use ($order) {
                    $inner
                        ->where('source_type', self::SOURCE_TYPE)
                        ->where('source_id', (int) $order->id);
                })->orWhere(
                    'source_reference',
                    (string) $order->number
                );
            })
            ->orderBy('id');

        if ($lockRows) {
            $query->lockForUpdate();
        }

        $tickets = $query->get([
            'id', 'status', 'number'
        ]);

        $previousId = (int) ($order->pos_order_id ?? 0);

        if ($tickets->isEmpty()) {
            if ($previousId !== 0) {
                throw ValidationException::withMessages([
                    'order' =>
                        'Falta el ticket historico relacionado.',
                ]);
            }

            return;
        }

        if (
            $previousId <= 0
            || ! $tickets->contains(
                fn ($ticket): bool =>
                    (int) $ticket->id === $previousId
            )
        ) {
            throw ValidationException::withMessages([
                'order' =>
                    'El historial de tickets no coincide '
                    . 'con el pedido.',
            ]);
        }

        foreach ($tickets as $ticket) {
            if (! in_array(
                strtolower((string) $ticket->status),
                ['cancelled', 'canceled'],
                true
            )) {
                throw ValidationException::withMessages([
                    'order' =>
                        'Hay un ticket anterior sin cancelar: '
                        . $ticket->number,
                ]);
            }
        }

        $ticketIds = $tickets->pluck('id')->all();

        if (
            DB::table('pos_order_payments')
                ->whereIn('pos_order_id', $ticketIds)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'order' =>
                    'El historial tiene pagos. '
                    . 'Se requiere revision financiera.',
            ]);
        }

        if (
            DB::table('pos_payment_requests')
                ->whereIn('pos_order_id', $ticketIds)
                ->whereIn('status', [
                    'completed',
                    'paid',
                    'success',
                    'succeeded',
                ])
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'order' =>
                    'Hay solicitudes de pago completadas '
                    . 'en tickets anteriores.',
            ]);
        }
    }

    public function preview(int $orderId): array
    {
        $order = DB::table('custom_balloon_orders')
            ->where('company_id', self::COMPANY_ID)
            ->where('id', $orderId)
            ->first();

        if (! $order) {
            throw ValidationException::withMessages([
                'order' => 'Pedido no encontrado.',
            ]);
        }

        if (
(string) $order->status !== 'captured'
        ) {
            throw ValidationException::withMessages([
                'order' => 'El pedido no esta disponible para un primer envio.',
            ]);
        }

        $this->assertPreviousTicketsCancelled($order);

        $lines = DB::table('custom_balloon_order_lines')
            ->where('company_id', self::COMPANY_ID)
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'order' => 'El pedido no tiene partidas.',
            ]);
        }

        $prepared = [];
        $subtotal = 0.0;
        $tax = 0.0;
        $total = 0.0;

        foreach ($lines as $line) {
            $meta = json_decode(
                (string) $line->metadata,
                true
            );

            $meta = is_array($meta) ? $meta : [];

            $parentId = (int) ($meta['product_id'] ?? 0);
            $variantId = (int) ($meta['variant_id'] ?? 0);

            if ($parentId <= 0) {
                throw ValidationException::withMessages([
                    'order' => 'Una partida no tiene producto vinculado.',
                ]);
            }

            $parent = DB::table('products')
                ->where('company_id', self::COMPANY_ID)
                ->where('id', $parentId)
                ->where('is_variant', false)
                ->where('is_active', true)
                ->first();

            if (! $parent) {
                throw ValidationException::withMessages([
                    'order' => 'Producto del pedido no disponible.',
                ]);
            }

            $source = $parent;

            if ($variantId > 0) {
                $source = DB::table('products')
                    ->where('company_id', self::COMPANY_ID)
                    ->where('id', $variantId)
                    ->where('parent_product_id', $parentId)
                    ->where('is_variant', true)
                    ->where('is_active', true)
                    ->first();

                if (! $source) {
                    throw ValidationException::withMessages([
                        'order' => 'Variante del pedido no disponible.',
                    ]);
                }
            }

            $ratePercent = $source->sale_tax_rate
                ?? $parent->sale_tax_rate;

            if (
                $ratePercent === null
                || ! is_numeric($ratePercent)
                || (float) $ratePercent < 0
                || (float) $ratePercent > 100
            ) {
                throw ValidationException::withMessages([
                    'order' => 'Impuesto del producto no valido.',
                ]);
            }

            $quantity = (float) $line->quantity;
            $unit = (float) $line->unit_price;
            $lineTotal = (float) $line->subtotal;

            if (
                $quantity <= 0
                || $unit <= 0
                || abs(
                    round($unit * $quantity, 2)
                    - $lineTotal
                ) > 0.01
            ) {
                throw ValidationException::withMessages([
                    'order' => 'Importes inconsistentes en las partidas.',
                ]);
            }

            $rate = round((float) $ratePercent / 100, 4);
            $lineSubtotal = round(
                $lineTotal / (1 + $rate),
                4
            );
            $lineTax = round(
                $lineTotal - $lineSubtotal,
                4
            );

            $prepared[] = [
                'source_line_id' => (int) $line->id,
                'product_id' => $parentId,
                'variant_id' => $variantId ?: null,
                'name' => (string) (
                    $meta['product_name'] ?? $parent->name
                ),
                'reference' => (string) (
                    $meta['variant_reference']
                    ?? $source->internal_reference
                    ?? ''
                ),
                'quantity' => $quantity,
                'unit_price' => $unit,
                'tax_rate' => $rate,
                'subtotal' => $lineSubtotal,
                'tax_total' => $lineTax,
                'total' => $lineTotal,
                'design' => (string) (
                    $line->model_name_snapshot ?? ''
                ),
                'personalization' => (string) (
                    $line->personalization_text ?? ''
                ),
                'size' => (string) (
                    $line->size_name_snapshot ?? ''
                ),
                'model_id' => $line->model_id,
            ];

            $subtotal += $lineSubtotal;
            $tax += $lineTax;
            $total += $lineTotal;
        }

        if (
            abs($total - (float) $order->total) > 0.01
            || (float) $order->total <= 0
        ) {
            throw ValidationException::withMessages([
                'order' => 'El total del pedido no coincide con sus partidas.',
            ]);
        }

        return [
            'order' => $order,
            'lines' => $prepared,
            'subtotal' => round($subtotal, 4),
            'tax_total' => round($tax, 4),
            'total' => round($total, 4),
        ];
    }

    public function send(
        int $orderId,
        int $posPointId
    ): array {
        if (! $this->canSend()) {
            throw ValidationException::withMessages([
                'permission' =>
                    'No tienes permiso para crear tickets pendientes.',
            ]);
        }

        return DB::transaction(function () use (
            $orderId,
            $posPointId
        ): array {
            $locked = DB::table('custom_balloon_orders')
                ->where('id', $orderId)
                ->where('company_id', self::COMPANY_ID)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                abort(404);
            }

            if (
(string) $locked->status !== 'captured'
            ) {
                throw ValidationException::withMessages([
                    'order' =>
                        'El pedido ya fue enviado o no admite envio.',
                ]);
            }

            $point = DB::table('pos_points')
                ->where('id', $posPointId)
                ->where('company_id', self::COMPANY_ID)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $point) {
                throw ValidationException::withMessages([
                    'pos_point_id' =>
                        'Selecciona un PDV activo de Papelón.',
                ]);
            }

            $sessions = DB::table('pos_sessions')
                ->where('company_id', self::COMPANY_ID)
                ->where('pos_point_id', $posPointId)
                ->where('status', 'open')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($sessions->count() !== 1) {
                throw ValidationException::withMessages([
                    'pos_point_id' =>
                        'El PDV debe tener exactamente una sesion abierta.',
                ]);
            }

            $session = $sessions->first();

            $this->assertPreviousTicketsCancelled(
                $locked,
                true
            );

            // Recuperar y validar partidas, montos y tasa IVA
            // dentro de la transaccion del primer envio.
            $plan = $this->preview($orderId);

            $number = null;

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $candidate = 'GLB-'
                    . strtoupper(
                        preg_replace(
                            '/[^A-Z0-9]/i',
                            '',
                            (string) $point->code
                        )
                    )
                    . '-'
                    . now()->format('Ymd')
                    . '-'
                    . Str::upper(Str::random(8));

                if (! DB::table('pos_orders')
                    ->where('number', $candidate)
                    ->exists()
                ) {
                    $number = $candidate;
                    break;
                }
            }

            if (! $number) {
                throw new \RuntimeException(
                    'No fue posible generar el folio PDV.'
                );
            }

            $now = now();
            $token = 'GLBPDV-' . Str::upper(Str::random(16));

            $metadata = [
                'source' => self::SOURCE_TYPE,
                'balloon_order_id' => $orderId,
                'balloon_order_number' => $locked->number,
                'price_policy' => 'balloon_order_locked',
                'locked_prices' => true,
                'pos_point_id' => $posPointId,
                'pos_session_id' => (int) $session->id,
            ];

            $posOrderId = DB::table('pos_orders')
                ->insertGetId([
                    'company_id' => self::COMPANY_ID,
                    'pos_point_id' => $posPointId,
                    'pos_session_id' => (int) $session->id,
                    'employee_id' => $session->employee_id,
                    'customer_id' => $locked->customer_contact_id,
                    'number' => $number,
                    'status' => 'pending_payment',
                    'subtotal' => $plan['subtotal'],
                    'tax_total' => $plan['tax_total'],
                    'total' => $plan['total'],
                    'currency_code' => 'MXN',
                    'metadata' => json_encode(
                        $metadata,
                        JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    ),
                    'ordered_at' => $now,
                    'source_type' => self::SOURCE_TYPE,
                    'source_id' => $orderId,
                    'source_reference' => $locked->number,
                    'source_public_token' => $token,
                    'source_price_policy' =>
                        'balloon_order_locked',
                    'source_locked_prices' => true,
                    'creation_request_uuid' =>
                        (string) Str::uuid(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            foreach ($plan['lines'] as $line) {
                DB::table('pos_order_lines')->insert([
                    'pos_order_id' => $posOrderId,
                    'product_id' => $line['product_id'],
                    'product_variant_id' => $line['variant_id'],
                    'product_name' => $line['name'],
                    'product_reference' => $line['reference'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_rate' => $line['tax_rate'],
                    'subtotal' => $line['subtotal'],
                    'tax_total' => $line['tax_total'],
                    'total' => $line['total'],
                    'source_line_id' => $line['source_line_id'],
                    'source_system' => 'bexia_globos',
                    'source_model' =>
                        'custom_balloon_order_line',
                    'source_reference' => $locked->number,
                    'metadata' => json_encode([
                        'source' => self::SOURCE_TYPE,
                        'balloon_order_id' => $orderId,
                        'balloon_line_id' =>
                            $line['source_line_id'],
                        'model_id' => $line['model_id'],
                        'design' => $line['design'],
                        'personalization' =>
                            $line['personalization'],
                        'size' => $line['size'],
                        'price_policy' =>
                            'balloon_order_locked',
                        'locked_prices' => true,
                    ], JSON_UNESCAPED_UNICODE
                       | JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('custom_balloon_orders')
                ->where('id', $orderId)
                ->where('company_id', self::COMPANY_ID)
                ->update([
                    'status' => 'sent_to_pos',
                    'pos_order_id' => $posOrderId,
                    'sent_to_pos_at' => $now,
                    'updated_by_user_id' => auth()->id(),
                    'updated_at' => $now,
                ]);

            return [
                'order_id' => $orderId,
                'pos_order_id' => $posOrderId,
                'pos_order_number' => $number,
                'pos_point_id' => $posPointId,
                'pos_session_id' => (int) $session->id,
                'total' => $plan['total'],
            ];
        }, 3);
    }
}
