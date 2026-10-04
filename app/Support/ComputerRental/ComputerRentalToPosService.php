<?php

namespace App\Support\ComputerRental;

use App\Http\Controllers\PosController;
use App\Models\ComputerRentalSession;
use App\Models\ComputerRentalSessionLine;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ComputerRentalToPosService
{
    /**
     * Finaliza la renta y crea su ticket PDV dentro de una sola
     * transacción.
     *
     * Si cualquier paso falla:
     * - no queda ticket parcial;
     * - la renta sigue activa;
     * - la estación sigue En uso.
     */
    public function finalizeAndSend(
        ComputerRentalSession $session,
        ?int $userId = null
    ): array {
        return DB::transaction(function () use ($session, $userId): array {

            /*
             * Bloqueo principal.
             *
             * Evita doble ticket por doble clic o solicitudes simultáneas.
             */
            $locked = ComputerRentalSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($locked->status !== 'active') {
                throw ValidationException::withMessages([
                    'rental' => 'La renta ya no está activa.',
                ]);
            }

            if (! empty($locked->pos_order_id)) {
                throw ValidationException::withMessages([
                    'rental' => 'La renta ya tiene un ticket PDV relacionado.',
                ]);
            }

            $this->assertUserCanCreatePendingTicket();

            if (empty($locked->pos_point_id)) {
                throw ValidationException::withMessages([
                    'pos' => 'La PC no tiene Punto de Venta asignado.',
                ]);
            }

            /*
             * Exigimos exactamente una sesión abierta para evitar
             * mandar la venta a una caja incorrecta.
             */
            $openPosSessions = DB::table('pos_sessions')
                ->where('company_id', (int) $locked->company_id)
                ->where('pos_point_id', (int) $locked->pos_point_id)
                ->where('status', 'open')
                ->orderByDesc('id')
                ->get();

            if ($openPosSessions->isEmpty()) {
                throw ValidationException::withMessages([
                    'pos' => 'No hay una sesión PDV abierta para el punto de venta de esta PC.',
                ]);
            }

            if ($openPosSessions->count() > 1) {
                throw ValidationException::withMessages([
                    'pos' => 'Hay más de una sesión PDV abierta para este punto. Cierra o selecciona la sesión correcta antes de finalizar.',
                ]);
            }

            $posSession = $openPosSessions->first();

            /*
             * Producto utilizado para cobrar el tiempo.
             */
            if (empty($locked->product_id)) {
                throw ValidationException::withMessages([
                    'product' => 'La tarifa no tiene Producto servicio para PDV.',
                ]);
            }

            $rentalProduct = Product::query()
                ->where('id', (int) $locked->product_id)
                ->where('company_id', (int) $locked->company_id)
                ->first();

            if (! $rentalProduct) {
                throw ValidationException::withMessages([
                    'product' => 'No existe el producto configurado para cobrar la renta.',
                ]);
            }

            if ((string) $rentalProduct->product_type !== 'service') {
                throw ValidationException::withMessages([
                    'product' => 'El producto de renta debe ser de tipo Servicio.',
                ]);
            }

            /*
             * Validar consumos antes de finalizar.
             *
             * El precio ya quedó congelado en cada línea.
             */
            $consumptionLines = ComputerRentalSessionLine::query()
                ->where(
                    'computer_rental_session_id',
                    $locked->id
                )
                ->where(
                    'company_id',
                    (int) $locked->company_id
                )
                ->with('product')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($consumptionLines as $line) {
                if (! $line->product_id || ! $line->product) {
                    throw ValidationException::withMessages([
                        'consumption' =>
                            'Uno de los consumos ya no tiene un producto válido.',
                    ]);
                }

                if (
                    (string) $line->product->product_type
                    !== 'service'
                ) {
                    throw ValidationException::withMessages([
                        'consumption' =>
                            "{$line->description} ya no es un producto de servicio.",
                    ]);
                }

                if (
                    (float) $line->quantity <= 0
                    || (float) $line->unit_price <= 0
                ) {
                    throw ValidationException::withMessages([
                        'consumption' =>
                            "{$line->description} tiene cantidad o precio inválido.",
                    ]);
                }
            }

            /*
             * Finaliza usando el snapshot original de tarifa.
             *
             * ComputerRentalSessionService ya:
             * - calcula importe;
             * - fija ended_at;
             * - status -> pending_pos;
             * - libera estación.
             *
             * Estamos dentro de la misma transacción externa.
             */
            $finished = app(ComputerRentalSessionService::class)
                ->finish(
                    $locked,
                    $userId
                );

            $finished->load([
                'station',
                'product',
                'consumptionLines.product',
            ]);

            if ((float) $finished->amount <= 0) {
                throw ValidationException::withMessages([
                    'rental' =>
                        'El importe final de la renta no es válido.',
                ]);
            }

            /*
             * Construir carrito PDV.
             *
             * IMPORTANTE:
             * price es PRECIO FINAL CON IVA.
             */
            $items = [];

            $stationCode =
                (string) ($finished->station?->code ?? 'PC');

            $stationName =
                (string) ($finished->station?->name ?? '');

            $items[] = [
                'product_id' => (int) $finished->product_id,

                'name' =>
                    trim(
                        ($finished->product?->name ?? 'Renta PC')
                        . ' · '
                        . $stationCode
                    ),

                'reference' => '',

                'qty' => 1,

                /*
                 * Importe final calculado por el módulo de renta.
                 */
                'price' => round(
                    (float) $finished->amount,
                    4
                ),

                'tax_rate' => (float) $finished->tax_rate,

                'source' => 'computer_rental',
                'source_type' => 'rental',
                'computer_rental_session_id' =>
                    (int) $finished->id,
                'computer_rental_station_id' =>
                    (int) $finished->station_id,
                'station_code' => $stationCode,
                'station_name' => $stationName,
                'started_at' =>
                    optional($finished->started_at)
                        ->toISOString(),
                'ended_at' =>
                    optional($finished->ended_at)
                        ->toISOString(),
                'duration_seconds' =>
                    (int) $finished->duration_seconds,
                'billable_minutes' =>
                    (int) $finished->billable_minutes,
                'billing_mode' =>
                    (string) $finished->billing_mode,
            ];

            foreach ($finished->consumptionLines as $line) {
                $items[] = [
                    'product_id' => (int) $line->product_id,

                    'name' =>
                        (string) $line->description,

                    'reference' => '',

                    'qty' => (float) $line->quantity,

                    /*
                     * unit_price ya está guardado con IVA.
                     */
                    'price' => round(
                        (float) $line->unit_price,
                        4
                    ),

                    'tax_rate' =>
                        (float) $line->tax_rate,

                    'source' => 'computer_rental',
                    'source_type' => 'consumption',
                    'computer_rental_session_id' =>
                        (int) $finished->id,
                    'computer_rental_session_line_id' =>
                        (int) $line->id,
                    'station_code' => $stationCode,
                ];
            }

            $consumptionTotal = round(
                (float) $finished
                    ->consumptionLines
                    ->sum('total'),
                4
            );

            $expectedTotal = round(
                (float) $finished->amount
                + $consumptionTotal,
                4
            );

            /*
             * Crear request interno exactamente para storeOrder().
             */
            $request = Request::create(
                '/computer-rental/internal/finalize-to-pos',
                'POST',
                [
                    'customer_id' =>
                        $finished->customer_id,

                    'payment_label' => '',

                    'order_note' =>
                        'Renta '
                        . $stationCode
                        . ' · '
                        . (int) $finished->billable_minutes
                        . ' min'
                        . (
                            $consumptionTotal > 0
                                ? ' · consumos $'
                                    . number_format(
                                        $consumptionTotal,
                                        2,
                                        '.',
                                        ''
                                    )
                                : ''
                        ),

                    'items' => $items,
                ]
            );

            /*
             * Reutilizamos la lógica real del PDV.
             */
            $response = app(PosController::class)
                ->storeOrder(
                    $request,
                    (int) $posSession->id
                );

            if (! method_exists($response, 'getData')) {
                throw ValidationException::withMessages([
                    'pos' =>
                        'El PDV devolvió una respuesta no válida.',
                ]);
            }

            $payload = $response->getData(true);

            if (! ($payload['ok'] ?? false)) {
                throw ValidationException::withMessages([
                    'pos' =>
                        (string) (
                            $payload['message']
                            ?? 'No se pudo crear el ticket PDV.'
                        ),
                ]);
            }

            $orderId =
                (int) ($payload['order_id'] ?? 0);

            if ($orderId <= 0) {
                throw ValidationException::withMessages([
                    'pos' =>
                        'PDV no devolvió el ID del ticket.',
                ]);
            }

            $order = DB::table('pos_orders')
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw ValidationException::withMessages([
                    'pos' =>
                        'El ticket fue creado pero no pudo recuperarse.',
                ]);
            }

            /*
             * Verificación económica.
             */
            $actualTotal = round(
                (float) $order->total,
                4
            );

            if (
                abs($actualTotal - $expectedTotal)
                > 0.02
            ) {
                throw ValidationException::withMessages([
                    'pos' =>
                        'El total generado en PDV no coincide con la cuenta de renta. '
                        . 'Esperado $'
                        . number_format($expectedTotal, 2)
                        . ', PDV $'
                        . number_format($actualTotal, 2)
                        . '.',
                ]);
            }

            /*
             * Metadata trazable.
             */
            $metadata = [];

            if (! empty($order->metadata)) {
                $decoded = json_decode(
                    (string) $order->metadata,
                    true
                );

                if (is_array($decoded)) {
                    $metadata = $decoded;
                }
            }

            $metadata['computer_rental'] = [
                'session_id' =>
                    (int) $finished->id,
                'station_id' =>
                    (int) $finished->station_id,
                'station_code' =>
                    $stationCode,
                'station_name' =>
                    $stationName,
                'rental_amount' =>
                    round(
                        (float) $finished->amount,
                        4
                    ),
                'consumption_total' =>
                    $consumptionTotal,
                'expected_total' =>
                    $expectedTotal,
                'billable_minutes' =>
                    (int) $finished->billable_minutes,
                'consumption_lines' =>
                    $finished->consumptionLines->count(),
                'sent_by_user_id' =>
                    $userId ?: auth()->id(),
                'sent_at' =>
                    now()->toISOString(),
            ];

            $orderUpdates = [
                'metadata' => json_encode(
                    $metadata,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),
                'updated_at' => now(),
            ];

            if (
                Schema::hasColumn(
                    'pos_orders',
                    'source_type'
                )
            ) {
                $orderUpdates['source_type'] =
                    'computer_rental';
            }

            if (
                Schema::hasColumn(
                    'pos_orders',
                    'source_id'
                )
            ) {
                $orderUpdates['source_id'] =
                    (int) $finished->id;
            }

            if (
                Schema::hasColumn(
                    'pos_orders',
                    'source_reference'
                )
            ) {
                $orderUpdates['source_reference'] =
                    $stationCode;
            }

            DB::table('pos_orders')
                ->where('id', $orderId)
                ->update($orderUpdates);

            /*
             * Enlazar renta con ticket.
             */
            $sessionMetadata =
                is_array($finished->metadata)
                    ? $finished->metadata
                    : [];

            $sessionMetadata['pos_ticket'] = [
                'order_id' => $orderId,
                'number' =>
                    (string) ($order->number ?? ''),
                'total' => $actualTotal,
                'pos_session_id' =>
                    (int) $posSession->id,
                'pos_point_id' =>
                    (int) $posSession->pos_point_id,
                'created_at' =>
                    now()->toISOString(),
            ];

            $finished->update([
                'status' => 'sent_to_pos',
                'pos_session_id' =>
                    (int) $posSession->id,
                'pos_order_id' =>
                    $orderId,
                'sent_to_pos_at' =>
                    now(),
                'metadata' =>
                    $sessionMetadata,
            ]);

            return [
                'ok' => true,
                'rental_session_id' =>
                    (int) $finished->id,
                'pos_order_id' =>
                    $orderId,
                'number' =>
                    (string) ($order->number ?? ''),
                'rental_amount' =>
                    round(
                        (float) $finished->amount,
                        4
                    ),
                'consumption_total' =>
                    $consumptionTotal,
                'total' =>
                    $actualTotal,
                'billable_minutes' =>
                    (int) $finished->billable_minutes,
                'pos_session_id' =>
                    (int) $posSession->id,
            ];
        });
    }


    /**
     * CIBER3H3B_RESEND_CANCELLED
     *
     * Genera un nuevo ticket para una renta ya finalizada cuyo
     * ticket anterior fue cancelado.
     *
     * NO:
     * - recalcula duración;
     * - recalcula renta;
     * - modifica ended_at;
     * - cambia la estación;
     * - vuelve a llamar finish().
     */
    public function resendCancelledTicket(
        ComputerRentalSession $session,
        ?int $userId = null
    ): array {
        return DB::transaction(
            function () use (
                $session,
                $userId
            ): array {

                /*
                 * lockForUpdate evita doble ticket por doble clic.
                 */
                $locked =
                    ComputerRentalSession::query()
                        ->lockForUpdate()
                        ->findOrFail($session->id);

                if (
                    $locked->status
                    !== 'ticket_cancelled'
                ) {
                    throw ValidationException::withMessages([
                        'rental' =>
                            'La renta ya no tiene un ticket cancelado pendiente de regenerar.',
                    ]);
                }

                if (empty($locked->pos_order_id)) {
                    throw ValidationException::withMessages([
                        'rental' =>
                            'La renta no tiene referencia al ticket cancelado.',
                    ]);
                }

                $oldOrder = DB::table('pos_orders')
                    ->where(
                        'id',
                        (int) $locked->pos_order_id
                    )
                    ->where(
                        'company_id',
                        (int) $locked->company_id
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $oldOrder) {
                    throw ValidationException::withMessages([
                        'rental' =>
                            'No se encontró el ticket anterior.',
                    ]);
                }

                $oldStatus = strtolower(
                    trim(
                        (string) (
                            $oldOrder->status
                            ?? ''
                        )
                    )
                );

                if (
                    ! in_array(
                        $oldStatus,
                        ['cancelled', 'canceled'],
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'rental' =>
                            'El ticket anterior ya no está cancelado. Actualiza los estados antes de continuar.',
                    ]);
                }

                $this->assertUserCanCreatePendingTicket();

                if (empty($locked->pos_point_id)) {
                    throw ValidationException::withMessages([
                        'pos' =>
                            'La PC no tiene Punto de Venta asignado.',
                    ]);
                }

                /*
                 * Igual que el flujo original: exactamente una
                 * sesión abierta para evitar usar la caja incorrecta.
                 */
                $openPosSessions =
                    DB::table('pos_sessions')
                        ->where(
                            'company_id',
                            (int) $locked->company_id
                        )
                        ->where(
                            'pos_point_id',
                            (int) $locked->pos_point_id
                        )
                        ->where('status', 'open')
                        ->orderByDesc('id')
                        ->get();

                if ($openPosSessions->isEmpty()) {
                    throw ValidationException::withMessages([
                        'pos' =>
                            'No hay una sesión PDV abierta para el punto de venta de esta PC.',
                    ]);
                }

                if ($openPosSessions->count() > 1) {
                    throw ValidationException::withMessages([
                        'pos' =>
                            'Hay más de una sesión PDV abierta para este punto. Corrige las sesiones antes de regenerar el ticket.',
                    ]);
                }

                $posSession =
                    $openPosSessions->first();

                /*
                 * Valores congelados.
                 */
                if ((float) $locked->amount <= 0) {
                    throw ValidationException::withMessages([
                        'rental' =>
                            'La renta no tiene un importe final válido.',
                    ]);
                }

                if (empty($locked->product_id)) {
                    throw ValidationException::withMessages([
                        'product' =>
                            'La renta no tiene Producto servicio para PDV.',
                    ]);
                }

                $rentalProduct =
                    Product::query()
                        ->where(
                            'id',
                            (int) $locked->product_id
                        )
                        ->where(
                            'company_id',
                            (int) $locked->company_id
                        )
                        ->first();

                if (! $rentalProduct) {
                    throw ValidationException::withMessages([
                        'product' =>
                            'No existe el producto utilizado para cobrar la renta.',
                    ]);
                }

                if (
                    (string) $rentalProduct->product_type
                    !== 'service'
                ) {
                    throw ValidationException::withMessages([
                        'product' =>
                            'El producto de renta debe ser de tipo Servicio.',
                    ]);
                }

                $consumptionLines =
                    ComputerRentalSessionLine::query()
                        ->where(
                            'computer_rental_session_id',
                            $locked->id
                        )
                        ->where(
                            'company_id',
                            (int) $locked->company_id
                        )
                        ->with('product')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                foreach ($consumptionLines as $line) {
                    if (
                        ! $line->product_id
                        || ! $line->product
                    ) {
                        throw ValidationException::withMessages([
                            'consumption' =>
                                'Uno de los consumos ya no tiene un producto válido.',
                        ]);
                    }

                    if (
                        (string)
                            $line->product->product_type
                        !== 'service'
                    ) {
                        throw ValidationException::withMessages([
                            'consumption' =>
                                "{$line->description} ya no es un producto de servicio.",
                        ]);
                    }

                    if (
                        (float) $line->quantity <= 0
                        || (float) $line->unit_price <= 0
                    ) {
                        throw ValidationException::withMessages([
                            'consumption' =>
                                "{$line->description} tiene cantidad o precio inválido.",
                        ]);
                    }
                }

                $locked->load([
                    'station',
                    'product',
                ]);

                $stationCode =
                    (string) (
                        $locked->station?->code
                        ?? 'PC'
                    );

                $stationName =
                    (string) (
                        $locked->station?->name
                        ?? ''
                    );

                /*
                 * Construcción del ticket usando únicamente
                 * datos congelados de la renta.
                 */
                $items = [];

                $items[] = [
                    'product_id' =>
                        (int) $locked->product_id,

                    'name' =>
                        trim(
                            ($locked->product?->name
                                ?? 'Renta PC')
                            . ' · '
                            . $stationCode
                        ),

                    'reference' => '',

                    'qty' => 1,

                    'price' =>
                        round(
                            (float) $locked->amount,
                            4
                        ),

                    'tax_rate' =>
                        (float) $locked->tax_rate,

                    'source' =>
                        'computer_rental',

                    'source_type' =>
                        'rental_resend',

                    'computer_rental_session_id' =>
                        (int) $locked->id,

                    'computer_rental_station_id' =>
                        (int) $locked->station_id,

                    'station_code' =>
                        $stationCode,

                    'station_name' =>
                        $stationName,

                    'started_at' =>
                        optional(
                            $locked->started_at
                        )->toISOString(),

                    'ended_at' =>
                        optional(
                            $locked->ended_at
                        )->toISOString(),

                    'duration_seconds' =>
                        (int)
                            $locked->duration_seconds,

                    'billable_minutes' =>
                        (int)
                            $locked->billable_minutes,

                    'billing_mode' =>
                        (string)
                            $locked->billing_mode,
                ];

                foreach (
                    $consumptionLines
                    as $line
                ) {
                    $items[] = [
                        'product_id' =>
                            (int) $line->product_id,

                        'name' =>
                            (string)
                                $line->description,

                        'reference' => '',

                        'qty' =>
                            (float)
                                $line->quantity,

                        'price' =>
                            round(
                                (float)
                                    $line->unit_price,
                                4
                            ),

                        'tax_rate' =>
                            (float)
                                $line->tax_rate,

                        'source' =>
                            'computer_rental',

                        'source_type' =>
                            'consumption_resend',

                        'computer_rental_session_id' =>
                            (int) $locked->id,

                        'computer_rental_session_line_id' =>
                            (int) $line->id,

                        'station_code' =>
                            $stationCode,
                    ];
                }

                $consumptionTotal =
                    round(
                        (float)
                            $consumptionLines
                                ->sum('total'),
                        4
                    );

                $expectedTotal =
                    round(
                        (float) $locked->amount
                        + $consumptionTotal,
                        4
                    );

                $request = Request::create(
                    '/computer-rental/internal/resend-to-pos',
                    'POST',
                    [
                        'customer_id' =>
                            $locked->customer_id,

                        'payment_label' => '',

                        'order_note' =>
                            'REGENERADO · Renta '
                            . $stationCode
                            . ' · '
                            . (int)
                                $locked->billable_minutes
                            . ' min'
                            . (
                                $consumptionTotal > 0
                                    ? ' · consumos $'
                                        . number_format(
                                            $consumptionTotal,
                                            2,
                                            '.',
                                            ''
                                        )
                                    : ''
                            ),

                        'items' => $items,
                    ]
                );

                /*
                 * Reutilizamos el flujo real de creación
                 * de tickets pendientes.
                 */
                $response =
                    app(PosController::class)
                        ->storeOrder(
                            $request,
                            (int) $posSession->id
                        );

                if (
                    ! method_exists(
                        $response,
                        'getData'
                    )
                ) {
                    throw ValidationException::withMessages([
                        'pos' =>
                            'El PDV devolvió una respuesta no válida.',
                    ]);
                }

                $payload =
                    $response->getData(true);

                if (! ($payload['ok'] ?? false)) {
                    throw ValidationException::withMessages([
                        'pos' =>
                            (string) (
                                $payload['message']
                                ?? 'No se pudo crear el nuevo ticket PDV.'
                            ),
                    ]);
                }

                $newOrderId =
                    (int) (
                        $payload['order_id']
                        ?? 0
                    );

                if ($newOrderId <= 0) {
                    throw ValidationException::withMessages([
                        'pos' =>
                            'PDV no devolvió el ID del nuevo ticket.',
                    ]);
                }

                $newOrder =
                    DB::table('pos_orders')
                        ->where(
                            'id',
                            $newOrderId
                        )
                        ->lockForUpdate()
                        ->first();

                if (! $newOrder) {
                    throw ValidationException::withMessages([
                        'pos' =>
                            'El nuevo ticket fue creado pero no pudo recuperarse.',
                    ]);
                }

                $actualTotal =
                    round(
                        (float) $newOrder->total,
                        4
                    );

                if (
                    abs(
                        $actualTotal
                        - $expectedTotal
                    ) > 0.02
                ) {
                    throw ValidationException::withMessages([
                        'pos' =>
                            'El nuevo ticket no coincide con la cuenta congelada. '
                            . 'Esperado $'
                            . number_format(
                                $expectedTotal,
                                2
                            )
                            . ', PDV $'
                            . number_format(
                                $actualTotal,
                                2
                            )
                            . '.',
                    ]);
                }

                /*
                 * Metadata del nuevo ticket.
                 */
                $newOrderMetadata = [];

                if (! empty($newOrder->metadata)) {
                    $decoded = json_decode(
                        (string)
                            $newOrder->metadata,
                        true
                    );

                    if (is_array($decoded)) {
                        $newOrderMetadata =
                            $decoded;
                    }
                }

                $newOrderMetadata['computer_rental'] = [
                    'session_id' =>
                        (int) $locked->id,

                    'station_id' =>
                        (int) $locked->station_id,

                    'station_code' =>
                        $stationCode,

                    'station_name' =>
                        $stationName,

                    'rental_amount' =>
                        round(
                            (float)
                                $locked->amount,
                            4
                        ),

                    'consumption_total' =>
                        $consumptionTotal,

                    'expected_total' =>
                        $expectedTotal,

                    'billable_minutes' =>
                        (int)
                            $locked->billable_minutes,

                    'consumption_lines' =>
                        $consumptionLines->count(),

                    'sent_by_user_id' =>
                        $userId
                        ?: auth()->id(),

                    'sent_at' =>
                        now()->toISOString(),

                    'regenerated' =>
                        true,

                    'previous_pos_order_id' =>
                        (int) $oldOrder->id,

                    'previous_pos_order_number' =>
                        (string)
                            ($oldOrder->number ?? ''),
                ];

                $newOrderUpdates = [
                    'metadata' =>
                        json_encode(
                            $newOrderMetadata,
                            JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                        ),

                    'updated_at' =>
                        now(),
                ];

                if (
                    Schema::hasColumn(
                        'pos_orders',
                        'source_type'
                    )
                ) {
                    $newOrderUpdates['source_type'] =
                        'computer_rental';
                }

                if (
                    Schema::hasColumn(
                        'pos_orders',
                        'source_id'
                    )
                ) {
                    $newOrderUpdates['source_id'] =
                        (int) $locked->id;
                }

                if (
                    Schema::hasColumn(
                        'pos_orders',
                        'source_reference'
                    )
                ) {
                    $newOrderUpdates['source_reference'] =
                        $stationCode;
                }

                DB::table('pos_orders')
                    ->where(
                        'id',
                        $newOrderId
                    )
                    ->update(
                        $newOrderUpdates
                    );

                /*
                 * Preservar historial antes de reemplazar
                 * el pos_order_id actual.
                 */
                $sessionMetadata =
                    is_array($locked->metadata)
                        ? $locked->metadata
                        : [];

                $history =
                    $sessionMetadata[
                        'pos_ticket_history'
                    ]
                    ?? [];

                if (! is_array($history)) {
                    $history = [];
                }

                $oldMetadata = [];

                if (! empty($oldOrder->metadata)) {
                    $decodedOld =
                        json_decode(
                            (string)
                                $oldOrder->metadata,
                            true
                        );

                    if (is_array($decodedOld)) {
                        $oldMetadata =
                            $decodedOld;
                    }
                }

                $history[] = [
                    'order_id' =>
                        (int) $oldOrder->id,

                    'number' =>
                        (string)
                            ($oldOrder->number ?? ''),

                    'total' =>
                        (float)
                            ($oldOrder->total ?? 0),

                    'status' =>
                        (string)
                            ($oldOrder->status ?? ''),

                    'cancelled_at' =>
                        $oldOrder->cancelled_at
                        ?? (
                            $oldMetadata[
                                'cancelled_at'
                            ]
                            ?? null
                        ),

                    'cancel_reason' =>
                        $oldMetadata[
                            'cancel_reason'
                        ]
                        ?? (
                            $sessionMetadata[
                                'pos_ticket_cancelled'
                            ]['cancel_reason']
                            ?? null
                        ),

                    'cancelled_by_user_id' =>
                        $oldMetadata[
                            'cancelled_by_user_id'
                        ]
                        ?? (
                            $sessionMetadata[
                                'pos_ticket_cancelled'
                            ][
                                'cancelled_by_user_id'
                            ]
                            ?? null
                        ),

                    'archived_at' =>
                        now()->toISOString(),
                ];

                $sessionMetadata[
                    'pos_ticket_history'
                ] = $history;

                $sessionMetadata['pos_ticket'] = [
                    'order_id' =>
                        $newOrderId,

                    'number' =>
                        (string)
                            ($newOrder->number ?? ''),

                    'total' =>
                        $actualTotal,

                    'pos_session_id' =>
                        (int) $posSession->id,

                    'pos_point_id' =>
                        (int) $posSession->pos_point_id,

                    'created_at' =>
                        now()->toISOString(),

                    'regenerated' =>
                        true,

                    'previous_order_id' =>
                        (int) $oldOrder->id,
                ];

                $sessionMetadata[
                    'last_ticket_regeneration'
                ] = [
                    'previous_order_id' =>
                        (int) $oldOrder->id,

                    'new_order_id' =>
                        $newOrderId,

                    'regenerated_by_user_id' =>
                        $userId
                        ?: auth()->id(),

                    'regenerated_at' =>
                        now()->toISOString(),
                ];

                /*
                 * Regresa al flujo normal de cobro.
                 *
                 * NO tocamos:
                 * ended_at
                 * duration_seconds
                 * billable_minutes
                 * amount
                 * station.status
                 */
                $locked->update([
                    'status' =>
                        'sent_to_pos',

                    'pos_session_id' =>
                        (int) $posSession->id,

                    'pos_order_id' =>
                        $newOrderId,

                    'sent_to_pos_at' =>
                        now(),

                    'metadata' =>
                        $sessionMetadata,
                ]);

                return [
                    'ok' => true,

                    'rental_session_id' =>
                        (int) $locked->id,

                    'old_pos_order_id' =>
                        (int) $oldOrder->id,

                    'old_number' =>
                        (string)
                            ($oldOrder->number ?? ''),

                    'pos_order_id' =>
                        $newOrderId,

                    'number' =>
                        (string)
                            ($newOrder->number ?? ''),

                    'rental_amount' =>
                        round(
                            (float)
                                $locked->amount,
                            4
                        ),

                    'consumption_total' =>
                        $consumptionTotal,

                    'total' =>
                        $actualTotal,

                    'billable_minutes' =>
                        (int)
                            $locked->billable_minutes,

                    'pos_session_id' =>
                        (int) $posSession->id,
                ];
            }
        );
    }


    protected function assertUserCanCreatePendingTicket(): void
    {
        $user = auth()->user();

        if (! $user) {
            throw ValidationException::withMessages([
                'permission' =>
                    'La sesión del usuario expiró.',
            ]);
        }

        if (
            method_exists($user, 'isSystemAdmin')
            && $user->isSystemAdmin()
        ) {
            return;
        }

        if (
            method_exists($user, 'isGroupAdmin')
            && $user->isGroupAdmin()
        ) {
            return;
        }

        if (
            method_exists($user, 'can')
            && $user->can(
                'pos.pending_tickets.create'
            )
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'permission' =>
                'No tienes permiso para crear tickets pendientes.',
        ]);
    }
}
