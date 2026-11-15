<?php

namespace App\Support\CustomBalloons;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BalloonOrderRules
{
    public const COMPANY_ID = 3;

    public static function assertContact(
        int $contactId,
        int $companyId = 3
    ): array {
        if ($companyId !== self::COMPANY_ID || $contactId <= 0) {
            throw ValidationException::withMessages([
                'customer_contact_id' => 'Cliente no autorizado.',
            ]);
        }

        $contact = DB::table('contacts')
            ->where('id', $contactId)
            ->where('company_id', $companyId)
            ->where('is_customer', true)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first(['id', 'name', 'phone', 'mobile']);

        if (! $contact) {
            throw ValidationException::withMessages([
                'customer_contact_id' =>
                    'Selecciona un cliente activo de Contactos de Papelón.',
            ]);
        }

        $phone = trim((string) ($contact->mobile ?: $contact->phone));

        if (
            $phone === ''
            || strlen(preg_replace('/\D+/', '', $phone)) < 10
        ) {
            throw ValidationException::withMessages([
                'customer_contact_id' =>
                    'El cliente debe tener un teléfono o celular válido en Contactos.',
            ]);
        }

        return [
            'id' => (int) $contact->id,
            'name' => (string) $contact->name,
            'phone' => $phone,
        ];
    }

    public static function requestedDeliveryAt(
        string $localDateTime
    ): DateTimeImmutable {
        $timezone = new DateTimeZone('America/Mexico_City');

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i',
            trim($localDateTime),
            $timezone
        );

        if (
            ! $date
            || $date->format('Y-m-d H:i') !== trim($localDateTime)
        ) {
            throw ValidationException::withMessages([
                'promised_at' =>
                    'Indica una fecha y hora de entrega válidas.',
            ]);
        }

        if ($date <= new DateTimeImmutable('now', $timezone)) {
            throw ValidationException::withMessages([
                'promised_at' =>
                    'La fecha y hora solicitadas deben ser futuras.',
            ]);
        }

        return $date;
    }

    public static function assertEditableState(
        ?int $posOrderId,
        ?string $posStatus,
        int $paymentRows,
        int $companyId = 3
    ): void {
        if ($companyId !== self::COMPANY_ID) {
            throw ValidationException::withMessages([
                'order' => 'Empresa no autorizada.',
            ]);
        }

        if ($posOrderId === null) {
            if ($paymentRows !== 0) {
                throw ValidationException::withMessages([
                    'order' => 'Existen pagos que requieren conciliación.',
                ]);
            }

            return;
        }

        if (
            ! in_array(
                strtolower(trim((string) $posStatus)),
                ['cancelled', 'canceled'],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'order' =>
                    'Cancela primero el ticket pendiente en el PDV.',
            ]);
        }

        if ($paymentRows !== 0) {
            throw ValidationException::withMessages([
                'order' =>
                    'El ticket tiene registros de pago. Requiere revisión financiera.',
            ]);
        }
    }


    /**
     * Bloquea cambios si cualquier ticket historico
     * del pedido registra pagos o solicitudes completadas.
     */
    public static function assertNoHistoricalPayments(
        int $orderId,
        string $orderNumber,
        int $companyId = 3
    ): void {
        if (
            $companyId !== self::COMPANY_ID
            || $orderId <= 0
            || trim($orderNumber) === ''
        ) {
            throw ValidationException::withMessages([
                'order' => 'Pedido no autorizado.',
            ]);
        }

        $ticketIds = DB::table('pos_orders')
            ->where('company_id', $companyId)
            ->where(function ($query) use ($orderId, $orderNumber) {
                $query->where(function ($inner) use ($orderId) {
                    $inner->where(
                        'source_type',
                        'custom_balloon_order'
                    )->where('source_id', $orderId);
                })->orWhere(
                    'source_reference',
                    $orderNumber
                );
            })
            ->pluck('id');

        if ($ticketIds->isEmpty()) {
            return;
        }

        if (
            DB::table('pos_order_payments')
                ->whereIn('pos_order_id', $ticketIds->all())
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'order' =>
                    'El pedido tiene historial de pagos en PDV. '
                    . 'Requiere revision financiera.',
            ]);
        }

        if (\Illuminate\Support\Facades\Schema::hasTable(
            'pos_payment_requests'
        )) {
            $completed = DB::table('pos_payment_requests')
                ->whereIn('pos_order_id', $ticketIds->all())
                ->whereIn('status', [
                    'completed',
                    'paid',
                    'success',
                    'succeeded',
                ])
                ->exists();

            if ($completed) {
                throw ValidationException::withMessages([
                    'order' =>
                        'Existen solicitudes de pago completadas '
                        . 'en el historial del pedido.',
                ]);
            }
        }
    }

    public static function assertEditable(
        int $orderId,
        int $companyId = 3
    ): void {
        if ($companyId !== self::COMPANY_ID || $orderId <= 0) {
            throw ValidationException::withMessages([
                'order' => 'Pedido no autorizado.',
            ]);
        }

        $order = DB::table('custom_balloon_orders')
            ->where('id', $orderId)
            ->where('company_id', $companyId)
            ->first(['id', 'number', 'status', 'pos_order_id']);

        if (! $order || in_array(
            strtolower((string) $order->status),
            ['cancelled', 'delivered'],
            true
        )) {
            throw ValidationException::withMessages([
                'order' => 'El pedido no se puede editar.',
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
                ->where('company_id', $companyId)
                ->first(['id', 'status']);

            if (! $pos) {
                throw ValidationException::withMessages([
                    'order' => 'Ticket del PDV no encontrado.',
                ]);
            }

            $posStatus = (string) $pos->status;

            $paymentRows = DB::table('pos_order_payments')
                ->where('pos_order_id', $posId)
                ->count();
        }

        self::assertNoHistoricalPayments(
            $orderId,
            (string) $order->number,
            $companyId
        );

        self::assertEditableState(
            $posId,
            $posStatus,
            $paymentRows,
            $companyId
        );
    }
}
