<?php

namespace App\Support\CustomBalloons;

use App\Support\CustomBalloonsAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BalloonOrderEditService
{
    public function update(
        int $orderId,
        string $revision,
        int $contactId,
        string $delivery,
        array $items,
        ?string $notes,
        ?string $newPhone
    ): void {
        if (! CustomBalloonsAccess::can('custom_balloons.update')) {
            abort(403);
        }

        DB::transaction(function () use (
            $orderId,
            $revision,
            $contactId,
            $delivery,
            $items,
            $notes,
            $newPhone
        ): void {
            $order = DB::table('custom_balloon_orders')
                ->where('id', $orderId)
                ->where('company_id', 3)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                abort(404);
            }

            if ((string) $order->updated_at !== $revision) {
                throw ValidationException::withMessages([
                    'order' => 'El pedido cambió desde que abriste la ficha. Recarga antes de guardar.',
                ]);
            }

            if (in_array(
                strtolower((string) $order->status),
                ['cancelled', 'canceled', 'delivered'],
                true
            )) {
                throw ValidationException::withMessages([
                    'order' => 'El pedido está cerrado y no admite modificaciones.',
                ]);
            }

            $posId = $order->pos_order_id
                ? (int) $order->pos_order_id
                : null;

            $posStatus = null;
            $paymentRows = 0;

            if ($posId !== null) {
                $pos = DB::table('pos_orders')
                    ->where('id', $posId)
                    ->where('company_id', 3)
                    ->lockForUpdate()
                    ->first(['id', 'status']);

                if (! $pos) {
                    throw ValidationException::withMessages([
                        'order' => 'No se encontró el ticket relacionado del PDV.',
                    ]);
                }

                $posStatus = (string) $pos->status;

                $paymentRows = DB::table('pos_order_payments')
                    ->where('pos_order_id', $posId)
                    ->count();
            } elseif ((string) $order->status !== 'captured') {
                throw ValidationException::withMessages([
                    'order' => 'El estado del pedido requiere revisión antes de editar.',
                ]);
            }

            BalloonOrderRules::assertNoHistoricalPayments(
                $orderId,
                (string) $order->number,
                3
            );

            BalloonOrderRules::assertEditableState(
                $posId,
                $posStatus,
                $paymentRows,
                3
            );

            $contact = DB::table('contacts')
                ->where('id', $contactId)
                ->where('company_id', 3)
                ->where('is_customer', true)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first(['id', 'phone', 'mobile']);

            if (! $contact) {
                throw ValidationException::withMessages([
                    'customerContactId' => 'Selecciona un cliente activo de Papelón.',
                ]);
            }

            $validPhone = static function (?string $value): bool {
                $digits = preg_replace('/\D+/', '', (string) $value);

                return strlen($digits) >= 10
                    && strlen($digits) <= 15;
            };

            if (
                ! $validPhone($contact->phone)
                && ! $validPhone($contact->mobile)
            ) {
                $phone = trim((string) $newPhone);

                if (! $validPhone($phone)) {
                    throw ValidationException::withMessages([
                        'newCustomerPhone' => 'Captura un teléfono de 10 a 15 dígitos.',
                    ]);
                }

                DB::table('contacts')
                    ->where('id', $contactId)
                    ->where('company_id', 3)
                    ->update([
                        'phone' => $phone,
                        'updated_at' => now(),
                    ]);
            }

            $prepared = app(BalloonOrderDraftService::class)
                ->prepare($contactId, $delivery, $items);

            $now = now();

            DB::table('custom_balloon_orders')
                ->where('id', $orderId)
                ->where('company_id', 3)
                ->update([
                    'customer_contact_id' => $prepared['customer']['id'],
                    'customer_name_snapshot' => $prepared['customer']['name'],
                    'customer_phone_snapshot' => $prepared['customer']['phone'],
                    'promised_at' => $prepared['promised_at'],
                    'notes' => $notes,
                    'status' => 'captured',
                    'subtotal' => $prepared['total'],
                    'total' => $prepared['total'],
                    'balance_amount' => $prepared['total'],
                    'extras_total' => 0,
                    'discount_total' => 0,
                    'updated_by_user_id' => auth()->id(),
                    'updated_at' => $now,
                ]);

            DB::table('custom_balloon_order_lines')
                ->where('order_id', $orderId)
                ->where('company_id', 3)
                ->delete();

            foreach ($prepared['lines'] as $line) {
                DB::table('custom_balloon_order_lines')->insert([
                    'company_id' => 3,
                    'order_id' => $orderId,
                    'model_id' => $line['model_id'],
                    'size_id' => null,
                    'model_name_snapshot' => $line['model_name_snapshot'],
                    'size_name_snapshot' => $line['size_name_snapshot'],
                    'quantity' => $line['quantity'],
                    'base_unit_price' => $line['unit_price'],
                    'model_adjustment' => 0,
                    'unit_price' => $line['unit_price'],
                    'subtotal' => $line['subtotal'],
                    'personalization_text' => $line['personalization_text'],
                    'metadata' => json_encode(
                        $line['metadata'],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }, 3);
    }
}
