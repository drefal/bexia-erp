<?php

namespace App\Support\CustomBalloons;

use App\Support\CustomBalloonsAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BalloonOrderDraftService
{
    private const COMPANY_ID = 3;

    public function prepare(
        int $contactId,
        string $requestedAt,
        array $items
    ): array {
        if ($items === []) {
            throw ValidationException::withMessages([
                'cart' => 'Agrega al menos un producto.',
            ]);
        }

        if (count($items) > 100) {
            throw ValidationException::withMessages([
                'cart' => 'El pedido supera el límite de partidas.',
            ]);
        }

        $customer = BalloonOrderRules::assertContact(
            $contactId,
            self::COMPANY_ID
        );

        $delivery = BalloonOrderRules::requestedDeliveryAt(
            $requestedAt
        );

        $prepared = [];
        $totalCents = 0;

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw ValidationException::withMessages([
                    'cart' => 'Partida inválida.',
                ]);
            }

            $productId = (int) ($item['product_id'] ?? 0);
            $variantId = (int) ($item['variant_id'] ?? 0);
            $modelId = (int) ($item['model_id'] ?? 0);
            $quantity = filter_var(
                $item['quantity'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (
                $productId <= 0
                || $quantity === false
                || $quantity < 1
                || $quantity > 99
            ) {
                throw ValidationException::withMessages([
                    'cart' => 'Producto o cantidad inválidos.',
                ]);
            }

            $parent = DB::table('products')
                ->where('id', $productId)
                ->where('company_id', self::COMPANY_ID)
                ->where('is_variant', false)
                ->where('is_active', true)
                ->where('can_be_sold', true)
                ->where('available_in_custom_balloons', true)
                ->whereIn('product_category_id', function ($q) {
                    $q->select('id')
                        ->from('product_categories')
                        ->where('company_id', self::COMPANY_ID)
                        ->where('code', 'GLB-PERS')
                        ->where('is_active', true);
                })
                ->first();

            if (!$parent) {
                throw ValidationException::withMessages([
                    'cart' => 'Producto fuera del catálogo de Globos.',
                ]);
            }

            $variant = null;

            if ($variantId > 0) {
                $variant = DB::table('products')
                    ->where('id', $variantId)
                    ->where('company_id', self::COMPANY_ID)
                    ->where('parent_product_id', $productId)
                    ->where('is_variant', true)
                    ->where('is_active', true)
                    ->where('can_be_sold', true)
                    ->first();

                if (!$variant) {
                    throw ValidationException::withMessages([
                        'cart' => 'Variante no disponible.',
                    ]);
                }
            } elseif ((bool) $parent->has_variants) {
                throw ValidationException::withMessages([
                    'cart' => 'Selecciona una variante.',
                ]);
            }

            $size = $variant
                ? (string) ($variant->variant_value ?: 'Variante')
                : 'Única';

            $inflation = null;

            $matrix = BalloonVariantMatrix::forProduct(
                $productId,
                self::COMPANY_ID
            );

            if ($matrix !== []) {
                $matching = collect($matrix)->first(
                    fn (array $row): bool =>
                        (int) $row['variant_id'] === $variantId
                );

                if (!$matching) {
                    throw ValidationException::withMessages([
                        'cart' => 'Combinación Tamaño/Inflado inválida.',
                    ]);
                }

                $size = (string) $matching['size'];
                $inflation = (string) $matching['inflation'];
            }

            $requiresDesign = (bool) $parent->requires_balloon_design;
            $model = null;

            if ($requiresDesign) {
                if ($modelId <= 0) {
                    throw ValidationException::withMessages([
                        'cart' => 'Selecciona un diseño para el globo.',
                    ]);
                }

                $model = DB::table('custom_balloon_models')
                    ->where('id', $modelId)
                    ->where('company_id', self::COMPANY_ID)
                    ->where('is_active', true)
                    ->first();

                if (!$model) {
                    throw ValidationException::withMessages([
                        'cart' => 'Diseño no disponible.',
                    ]);
                }
            } elseif ($modelId > 0) {
                throw ValidationException::withMessages([
                    'cart' => 'Este producto no requiere diseño.',
                ]);
            }

            // La cantidad y los precios del navegador nunca
            // se consideran una fuente de verdad.
            $resolved = CatalogProductResolver::resolve(
                $productId,
                $variantId > 0 ? $variantId : null,
                self::COMPANY_ID
            );

            $unitCents = (int) round(
                (float) $resolved['sale_price_with_tax'] * 100
            );

            if ($unitCents <= 0) {
                throw ValidationException::withMessages([
                    'cart' => 'Precio inválido en el catálogo.',
                ]);
            }

            $lineCents = $unitCents * $quantity;
            $totalCents += $lineCents;

            $prepared[] = [
                'company_id' => self::COMPANY_ID,
                'product_id' => $productId,
                'variant_id' => $variantId ?: null,
                'model_id' => $model?->id,
                'model_name_snapshot' => $model
                    ? (string) $model->name
                    : 'Sin diseño',
                'size_name_snapshot' => $size,
                'quantity' => $quantity,
                'unit_price' => $unitCents / 100,
                'subtotal' => $lineCents / 100,
                'personalization_text' => $requiresDesign
                    ? trim((string) ($item['personalization_text'] ?? ''))
                    : null,
                'metadata' => [
                    'source' => 'bexia_globos',
                    'product_id' => $productId,
                    'product_name' => (string) $parent->name,
                    'variant_id' => $variantId ?: null,
                    'variant_reference' => $variant
                        ? (string) $variant->internal_reference
                        : null,
                    'size' => $size,
                    'inflation' => $inflation,
                    'requires_design' => $requiresDesign,
                    'model_image_path' => $model?->image_path,
                    'price_includes_tax' => true,
                    'source_line_index' => $index,
                ],
            ];
        }

        return [
            'company_id' => self::COMPANY_ID,
            'customer' => $customer,
            'promised_at' => $delivery
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s'),
            'lines' => $prepared,
            'total' => $totalCents / 100,
        ];
    }

    public function createDraft(
        int $contactId,
        string $requestedAt,
        array $items,
        ?string $notes = null,
        ?string $newCustomerPhone = null
    ): int {
        if (
            !CustomBalloonsAccess::can(
                'custom_balloons.create'
            )
        ) {
            abort(403);
        }

        return DB::transaction(function () use (
            $contactId,
            $requestedAt,
            $items,
            $notes,
            $newCustomerPhone
        ): int {
            // El contacto se bloquea dentro de la misma
            // transaccion que guarda el pedido y sus lineas.
            $contact = DB::table('contacts')
                ->where('id', $contactId)
                ->where('company_id', self::COMPANY_ID)
                ->where('is_customer', true)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first(['id', 'phone', 'mobile']);

            if (! $contact) {
                throw ValidationException::withMessages([
                    'customerContactId' =>
                        'El cliente ya no está disponible en Contactos.',
                ]);
            }

            $existingPhone = trim(
                (string) ($contact->mobile ?: $contact->phone)
            );

            if ($existingPhone === '') {
                $phone = trim((string) $newCustomerPhone);
                $digits = preg_replace('/[^0-9]/', '', $phone);

                if (
                    ! is_string($digits)
                    || strlen($digits) < 10
                    || strlen($digits) > 15
                ) {
                    throw ValidationException::withMessages([
                        'customer_phone' =>
                            'Captura un teléfono válido de 10 a 15 dígitos.',
                    ]);
                }

                DB::table('contacts')
                    ->where('id', $contactId)
                    ->where('company_id', self::COMPANY_ID)
                    ->update([
                        'phone' => $phone,
                        'updated_at' => now(),
                    ]);
            }

            $data = $this->prepare(
                $contactId,
                $requestedAt,
                $items
            );

            $now = now();
            $userId = auth()->id();

            if (!$userId) {
                abort(403);
            }

            $number = 'GLB-'
                . $now->format('Ymd')
                . '-'
                . Str::upper(Str::random(8));

            $orderId = DB::table('custom_balloon_orders')
                ->insertGetId([
                    'company_id' => self::COMPANY_ID,
                    'number' => $number,
                    'customer_contact_id' => $data['customer']['id'],
                    'customer_name_snapshot' => $data['customer']['name'],
                    'customer_phone_snapshot' => $data['customer']['phone'],
                    'status' => 'captured',
                    'ordered_at' => $now,
                    'promised_at' => $data['promised_at'],
                    'subtotal' => $data['total'],
                    'extras_total' => 0,
                    'discount_total' => 0,
                    'total' => $data['total'],
                    'deposit_amount' => 0,
                    'balance_amount' => $data['total'],
                    'payment_status' => 'pending',
                    'notes' => $notes,
                    'created_by_user_id' => $userId,
                    'updated_by_user_id' => $userId,
                    'pos_order_id' => null,
                    'sent_to_pos_at' => null,
                    'metadata' => json_encode([
                        'source' => 'bexia_globos',
                        'version' => 'GLB4-F3',
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            foreach ($data['lines'] as $line) {
                DB::table('custom_balloon_order_lines')
                    ->insert([
                        'company_id' => self::COMPANY_ID,
                        'order_id' => $orderId,
                        'model_id' => $line['model_id'],
                        'size_id' => null,
                        'model_name_snapshot' =>
                            $line['model_name_snapshot'],
                        'size_name_snapshot' =>
                            $line['size_name_snapshot'],
                        'quantity' => $line['quantity'],
                        'base_unit_price' => $line['unit_price'],
                        'model_adjustment' => 0,
                        'unit_price' => $line['unit_price'],
                        'subtotal' => $line['subtotal'],
                        'personalization_text' =>
                            $line['personalization_text'],
                        'metadata' => json_encode(
                            $line['metadata'],
                            JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                        ),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
            }

            return (int) $orderId;
        });
    }
}
