<?php

namespace App\Filament\Resources\PosTicketResource\Pages;

use App\Filament\Resources\PosTicketResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewPosTicket extends ViewRecord
{
    protected static string $resource = PosTicketResource::class;

    protected static string $view = 'filament.pos-tickets.view-ticket';

    public function getTitle(): string
    {
        return 'Ticket ' . ($this->record->number ?: ('#' . $this->record->id));
    }

    /*
     * BEXIA_V5525H2_PDV_REMOVE_REQUEST_BILLING
     * Se elimina Solicitar factura / Enviar a facturación.
     * El ticket PDV solo ofrece portal, crear factura desde ticket o ver factura existente.
     */
    // BEXIA_V5836_REFUND_FIX2A4_R1
    // Preview: asignar el total al unico metodo existente.
    // BEXIA_V5836_POS_REFUND_NET_P10I17
    // Consulta neta. No registra devoluciones ni modifica pagos.
    protected function v5836RefundNetPreview(
        array $quantities,
        bool $refundAll
    ): array {
        $orderId = (int) $this->record->id;

        $order = \Illuminate\Support\Facades\DB::table(
            'pos_orders'
        )->where('id', $orderId)->first();

        if (! $order || (string) $order->status !== 'paid') {
            throw new \RuntimeException(
                'El ticket no esta pagado.'
            );
        }

        $lines = \Illuminate\Support\Facades\DB::table(
            'pos_order_lines'
        )->where('pos_order_id', $orderId)
            ->orderBy('id')
            ->get()
            ->all();

        $payments = \Illuminate\Support\Facades\DB::table(
            'pos_order_payments'
        )->where('pos_order_id', $orderId)
            ->where('status', 'paid')
            ->get(['amount']);

        if ($payments->isEmpty()) {
            throw new \RuntimeException(
                'El ticket no tiene pagos confirmados.'
            );
        }

        $paidCents = (int) round(
            $payments->sum(
                fn ($payment) => (float) $payment->amount
            ) * 100
        );

        return \App\Support\PosRefundNetCalculator::calculate(
            $order,
            $lines,
            $quantities,
            $refundAll,
            $paidCents
        );
    }

    // BEXIA_V5836_POS_REFUND_NET_P10I17
    protected function v5836SyncSingleRefundAmount(
        \Filament\Forms\Get $get,
        \Filament\Forms\Set $set
    ): void {
        $payments = $get('refund_payments');

        if (! is_array($payments) || count($payments) !== 1) {
            return;
        }

        $quantities = [];

        foreach (
            \App\Filament\Resources\PosTicketResource::orderLines(
                (int) $this->record->id
            ) as $line
        ) {
            $quantities['line_' . $line->id] =
                $get('line_' . $line->id) ?? '0';
        }

        try {
            $preview = $this->v5836RefundNetPreview(
                $quantities,
                (bool) $get('refund_all')
            );
        } catch (\Throwable $e) {
            // Evitar conservar un importe automatico incorrecto.
            $key = array_key_first($payments);
            $payments[$key]['amount'] = '0.00';
            $set('refund_payments', $payments);
            return;
        }

        $key = array_key_first($payments);
        $payments[$key]['amount'] = number_format(
            $preview['refund_cents'] / 100,
            2,
            '.',
            ''
        );

        $set('refund_payments', $payments);
    }

    // BEXIA_V5836_POS_REFUND_FIX2B8P4B
    // Solo habilitacion visual; backend conserva guard P3.
    protected function v5836RefundFormIsReady(array $data): bool
    {
        try {
            if (
                ! \App\Filament\Resources\PosTicketResource::
                    v5836CanAccessRefundPos($this->record)
                || ! \App\Filament\Resources\PosTicketResource::
                    canRefundTicket($this->record)
                || ! (
                    (bool) ($data['refund_all'] ?? false)
                        ? \App\Filament\Resources\PosTicketResource::
                            v5513cCanRefundFull()
                        : \App\Filament\Resources\PosTicketResource::
                            v5513cCanRefundPartial()
                )
            ) {
                return false;
            }

            if (trim((string) ($data['reason'] ?? '')) === '') {
                return false;
            }

            $lines = \App\Filament\Resources\PosTicketResource::
                orderLines((int) $this->record->id);

            $totalCents = 0;
            $refundAll = (bool) ($data['refund_all'] ?? false);

            foreach ($lines as $line) {
                $sold = (float) ($line->quantity ?? 0);
                $raw = (string) ($data['line_' . $line->id] ?? '0');
                $raw = trim($raw);

                if (
                    ! $refundAll
                    && ! preg_match(
                        '/\A\d+(?:\.\d{1,2})?\z/D',
                        $raw
                    )
                ) {
                    return false;
                }

                $qty = $refundAll ? $sold : (float) $raw;

                if ($qty < 0 || $qty > $sold + 0.000001) {
                    return false;
                }

                if ($qty > 0) {
                    $totalCents += (int) round(
                        $qty * (float) ($line->unit_price ?? 0) * 100
                    );
                }
            }

            // BEXIA_V5836_POS_REFUND_NET_P10I18
            // Los pagos deben sumar el importe neto autorizado.
            $quantities = [];

            foreach ($lines as $line) {
                $quantities['line_' . $line->id] =
                    $data['line_' . $line->id] ?? '0';
            }

            try {
                $netPreview = $this->v5836RefundNetPreview(
                    $quantities,
                    $refundAll
                );
                $totalCents = (int) $netPreview['refund_cents'];
            } catch (\Throwable $e) {
                return false;
            }

            if ($totalCents <= 0) {
                return false;
            }

            $payments = $data['refund_payments'] ?? null;

            if (
                ! is_array($payments)
                || count($payments) < 1
                || count($payments) > 8
            ) {
                return false;
            }

            $pos = \Illuminate\Support\Facades\DB::table('pos_points')
                ->where(
                    'id',
                    (int) $this->record->pos_point_id
                )
                ->where(
                    'company_id',
                    (int) $this->record->company_id
                )
                ->first(['payment_method_ids']);

            if (! $pos) {
                return false;
            }

            $configured = json_decode(
                (string) ($pos->payment_method_ids ?? '[]'),
                true
            );

            if (! is_array($configured)) {
                return false;
            }

            $allowed = array_filter(
                array_map('intval', $configured),
                fn (int $id): bool => $id > 0
            );

            $forms = \Illuminate\Support\Facades\DB::table(
                'payment_forms'
            )
                ->where(
                    'company_id',
                    (int) $this->record->company_id
                )
                ->where('is_active', true)
                ->whereIn('id', $allowed)
                ->whereIn('code', ['01', '03', '04', '28'])
                ->get([
                    'id',
                    'requires_reference',
                ])
                ->keyBy('id');

            $paidCents = 0;

            foreach ($payments as $payment) {
                if (! is_array($payment)) {
                    return false;
                }

                $formId = (int) ($payment['payment_form_id'] ?? 0);
                $form = $forms->get($formId);

                if (! $form) {
                    return false;
                }

                $amount = trim(
                    (string) ($payment['amount'] ?? '')
                );

                if (
                    strlen($amount) > 12
                    || ! preg_match(
                        '/\A\d+(?:\.\d{1,2})?\z/D',
                        $amount
                    )
                ) {
                    return false;
                }

                $parts = explode('.', $amount, 2);
                $cents = ((int) $parts[0] * 100)
                    + (int) str_pad($parts[1] ?? '', 2, '0');

                if ($cents <= 0 || $cents > $totalCents) {
                    return false;
                }

                $reference = trim(
                    (string) ($payment['reference'] ?? '')
                );

                if (
                    mb_strlen($reference) > 120
                    || (
                        (bool) $form->requires_reference
                        && $reference === ''
                    )
                ) {
                    return false;
                }

                $paidCents += $cents;

                if ($paidCents > $totalCents) {
                    return false;
                }
            }

            return $paidCents === $totalCents;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function getHeaderActions(): array
    {
        return [

            \Filament\Actions\Action::make('post_refund_inventory_return')
                ->label('Registrar entrada inventario')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn (): bool => \App\Filament\Resources\PosTicketResource::v5506gShouldShowInventoryReturn($this->record))
                ->requiresConfirmation()
                ->modalHeading('Registrar entrada de inventario')
                ->modalDescription('Se generará el movimiento de entrada para regresar al inventario los productos de esta devolución.')
                ->modalSubmitActionLabel('Registrar entrada')
                ->action(function (): void {
                    $movementId = \App\Filament\Resources\PosTicketResource::v5506gPostRefundInventory($this->record);

                    \Filament\Notifications\Notification::make()
                        ->title('Inventario actualizado')
                        ->body($movementId > 0 ? ('Entrada de inventario #' . $movementId . ' registrada.') : 'No había líneas inventariables para regresar.')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                }),



            // BEXIA_V5836_POS_REFUND_FIX2B8O2
            // Modal de consulta. No confirma ni modifica pagos.
            \Filament\Actions\Action::make('external_refunds_pending')
                ->label('Confirmar reembolso')
                ->icon('heroicon-o-credit-card')
                ->color('warning')
                ->visible(function (): bool {
                    if (
                        ! \App\Filament\Resources\PosTicketResource::
                            v5513cCanRefundCreate()
                        || ! \App\Filament\Resources\PosTicketResource::
                            v5836CanAccessRefundPos($this->record)
                    ) {
                        return false;
                    }

                    $rows = \Illuminate\Support\Facades\DB::table(
                        'pos_order_refund_payments as p'
                    )
                        ->join(
                            'pos_order_refunds as r',
                            'r.id',
                            '=',
                            'p.pos_order_refund_id'
                        )
                        ->where(
                            'r.pos_order_id',
                            (int) $this->record->id
                        )
                        ->where(
                            'r.company_id',
                            (int) $this->record->company_id
                        )
                        ->where('r.status', 'done')
                        ->where('p.status', 'pending')
                        ->get([
                            'p.metadata',
                        ]);

                    foreach ($rows as $row) {
                        $metadata = json_decode(
                            (string) ($row->metadata ?? '{}'),
                            true
                        );

                        if (
                            is_array($metadata)
                            && ($metadata['source'] ?? null)
                                === 'pos_ticket_multimethod_refund'
                            && ($metadata['settlement_status'] ?? null)
                                === 'pending_external_confirmation'
                        ) {
                            return true;
                        }
                    }

                    return false;
                })
                ->modalHeading('Confirmar reembolso bancario')
                ->modalDescription(
                    'Confirma únicamente reembolsos ya realizados '
                    . 'por el banco o terminal. Captura el folio '
                    . 'real de la operación.'
                )
                ->form([
                    \Filament\Forms\Components\Select::make(
                        'refund_payment_id'
                    )
                        ->label('Reembolso pendiente')
                        ->options(function (): array {
                            $rows = \Illuminate\Support\Facades\DB::table(
                                'pos_order_refund_payments as p'
                            )
                                ->join(
                                    'pos_order_refunds as r',
                                    'r.id',
                                    '=',
                                    'p.pos_order_refund_id'
                                )
                                ->where(
                                    'r.pos_order_id',
                                    (int) $this->record->id
                                )
                                ->where(
                                    'r.company_id',
                                    (int) $this->record->company_id
                                )
                                ->where('r.status', 'done')
                                ->where('p.status', 'pending')
                                ->orderBy('p.id')
                                ->get([
                                    'p.id',
                                    'p.payment_label',
                                    'p.amount',
                                    'p.metadata',
                                ]);

                            $options = [];

                            foreach ($rows as $row) {
                                $metadata = json_decode(
                                    (string) ($row->metadata ?? '{}'),
                                    true
                                );

                                if (
                                    ! is_array($metadata)
                                    || ($metadata['source'] ?? null)
                                        !== 'pos_ticket_multimethod_refund'
                                    || ($metadata['settlement_status'] ?? null)
                                        !== 'pending_external_confirmation'
                                ) {
                                    continue;
                                }

                                $options[(int) $row->id] =
                                    '#' . (int) $row->id
                                    . ' - '
                                    . (string) $row->payment_label
                                    . ' - $'
                                    . number_format(
                                        (float) $row->amount,
                                        2
                                    );
                            }

                            return $options;
                        })
                        ->helperText(
                            'Solo se muestran reembolsos externos '
                            . 'pendientes de confirmación.'
                        ),
                    \Filament\Forms\Components\TextInput::make(
                        'bank_reference'
                    )
                        ->label('Folio bancario o de terminal')
                        ->required()
                        ->maxLength(120),
                    \Filament\Forms\Components\DatePicker::make(
                        'confirmation_date'
                    )
                        ->label('Fecha de confirmación')
                        ->default(now()->toDateString())
                        ->maxDate(now())
                        ->required(),
                    \Filament\Forms\Components\Textarea::make(
                        'confirmation_notes'
                    )
                        ->label('Observaciones')
                        ->maxLength(1000)
                        ->rows(3),
                ])
                // BEXIA_V5836_POS_REFUND_FIX2B8O4
                ->modalSubmitActionLabel('Confirmar reembolso')
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    // BEXIA_V5836_POS_REFUND_FIX2B8P10D4
                    if (
                        ! \App\Filament\Resources\PosTicketResource::
                            v5513cCanRefundCreate()
                        || ! \App\Filament\Resources\PosTicketResource::
                            v5836CanAccessRefundPos($this->record)
                    ) {
                        throw new \RuntimeException(
                            'No autorizado para confirmar reembolsos.'
                        );
                    }

                    $paymentId = (int) (
                        $data['refund_payment_id'] ?? 0
                    );

                    if ($paymentId <= 0) {
                        throw new \RuntimeException(
                            'Selecciona el reembolso pendiente.'
                        );
                    }

                    // Validar pertenencia al ticket y empresa actual.
                    $payment = \Illuminate\Support\Facades\DB::table(
                        'pos_order_refund_payments as p'
                    )
                        ->join(
                            'pos_order_refunds as r',
                            'r.id',
                            '=',
                            'p.pos_order_refund_id'
                        )
                        ->where('p.id', $paymentId)
                        ->where(
                            'r.pos_order_id',
                            (int) $this->record->id
                        )
                        ->where(
                            'r.company_id',
                            (int) $this->record->company_id
                        )
                        ->where('r.status', 'done')
                        ->where('p.status', 'pending')
                        ->first(['p.id', 'p.metadata']);

                    if (! $payment) {
                        throw new \RuntimeException(
                            'El reembolso ya no está pendiente '
                            . 'o no pertenece a este ticket.'
                        );
                    }

                    $metadata = json_decode(
                        (string) ($payment->metadata ?? '{}'),
                        true
                    );

                    if (
                        ! is_array($metadata)
                        || ($metadata['source'] ?? null)
                            !== 'pos_ticket_multimethod_refund'
                        || ($metadata['settlement_status'] ?? null)
                            !== 'pending_external_confirmation'
                    ) {
                        throw new \RuntimeException(
                            'El reembolso no puede confirmarse '
                            . 'desde esta acción.'
                        );
                    }

                    $result = \App\Filament\Resources\PosTicketResource::
                        v5836ConfirmExternalRefund(
                            $paymentId,
                            (string) ($data['bank_reference'] ?? ''),
                            (string) ($data['confirmation_date'] ?? ''),
                            (string) ($data['confirmation_notes'] ?? '')
                        );

                    \Filament\Notifications\Notification::make()
                        ->title('Reembolso confirmado')
                        ->body(
                            'Pago #'
                            . (int) $result['refund_payment_id']
                            . ' confirmado con folio '
                            . (string) $result['bank_reference']
                            . '.'
                        )
                        ->success()
                        ->send();

                    $this->redirect(
                        static::getResource()::getUrl(
                            'view',
                            ['record' => $this->record]
                        )
                    );
                }),

            \Filament\Actions\Action::make('refund_inside_ticket')
                ->label('Devolución')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(function (): bool {
                    // BEXIA_V5836_POS_REFUND_FIX2B8P2B
                    // BEXIA_V5836_POS_REFUND_FIX2B8P10D4
                    return \App\Filament\Resources\PosTicketResource::
                        v5836CanAccessRefundPos($this->record)
                        && \App\Filament\Resources\PosTicketResource::
                            canRefundTicket($this->record);
                })
                ->form(function (): array {
                    $lines = \App\Filament\Resources\PosTicketResource::orderLines((int) $this->record->id);

                    $fields = [
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Motivo de devolución')
                            ->placeholder('Ej. Cliente solicita devolución, producto incorrecto, error de cobro...')
                            ->required()
                            ->live(onBlur: true)
                            ->rows(3),

                        \Filament\Forms\Components\Toggle::make('refund_all')
                            ->label('Devolver todo el ticket')
                            ->helperText('Activa esta opción para devolver el ticket completo. Si la dejas apagada, captura cantidades por producto.')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(
                                fn (
                                    \Filament\Forms\Get $get,
                                    \Filament\Forms\Set $set
                                ) => $this->v5836SyncSingleRefundAmount($get, $set)
                            ),

                        \Filament\Forms\Components\Placeholder::make('partial_refund_help')
                            ->label('Cantidades por producto')
                            ->content('Para devolución parcial, captura la cantidad a devolver. Para devolución total, activa “Devolver todo el ticket”. Los servicios se reembolsan financieramente, pero no generan entrada de inventario.'),
                    ];


                    // BEXIA_V5836_REFUND_FIX2A3_R2
                    // Vista compacta; conserva las claves line_ID.
                    $fields[] = \Filament\Forms\Components\Grid::make(12)
                        ->schema([
                            \Filament\Forms\Components\Placeholder::make('refund_col_product')
                                ->hiddenLabel()
                                ->content('PRODUCTO')
                                ->columnSpan(5),
                            \Filament\Forms\Components\Placeholder::make('refund_col_sold')
                                ->hiddenLabel()
                                ->content('VENDIDO')
                                ->columnSpan(1),
                            \Filament\Forms\Components\Placeholder::make('refund_col_price')
                                ->hiddenLabel()
                                ->content('PRECIO')
                                ->columnSpan(2),
                            \Filament\Forms\Components\Placeholder::make('refund_col_qty')
                                ->hiddenLabel()
                                ->content('DEVOLVER')
                                ->columnSpan(2),
                            \Filament\Forms\Components\Placeholder::make('refund_col_amount')
                                ->hiddenLabel()
                                ->content('IMPORTE')
                                ->columnSpan(2),
                        ])
                        // BEXIA_V5836_REFUND_FIX2A5
                        ->extraAttributes([
                            'class' => 'text-xs font-semibold bg-gray-50 dark:bg-gray-800 rounded-lg px-3 py-2',
                        ]);

                    foreach ($lines as $line) {
                        $qty = (float) ($line->quantity ?? 0);
                        $price = (float) ($line->unit_price ?? 0);
                        $lineId = (int) $line->id;

                        $label = trim((string) (
                            $line->product_name
                            ?? ('Producto #' . ($line->product_id ?? $lineId))
                        ));

                        $fields[] = \Filament\Forms\Components\Grid::make(12)
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make(
                                    'refund_name_' . $lineId
                                )
                                    ->hiddenLabel()
                                    ->content($label)
                                    ->columnSpan(5),

                                \Filament\Forms\Components\Placeholder::make(
                                    'refund_sold_' . $lineId
                                )
                                    ->hiddenLabel()
                                    ->content(number_format($qty, 2))
                                    ->columnSpan(1),

                                \Filament\Forms\Components\Placeholder::make(
                                    'refund_price_' . $lineId
                                )
                                    ->hiddenLabel()
                                    ->content('$' . number_format($price, 2))
                                    ->columnSpan(2),

                                \Filament\Forms\Components\TextInput::make(
                                    'line_' . $lineId
                                )
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->maxValue($qty)
                                    // BEXIA_V5836_REFUND_FIX2A3_R3
                                    ->step('0.01')
                                    ->extraInputAttributes([
                                        'class' => 'text-xs py-1 text-center',
                                        'inputmode' => 'decimal',
                                    ])
                                    ->rule('decimal:0,2')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn (
                                            \Filament\Forms\Get $get,
                                            \Filament\Forms\Set $set
                                        ) => $this->v5836SyncSingleRefundAmount($get, $set)
                                    )
                                    ->disabled(
                                        fn (\Filament\Forms\Get $get): bool =>
                                            (bool) $get('refund_all')
                                    )
                                    ->columnSpan(2),

                                \Filament\Forms\Components\Placeholder::make(
                                    'refund_amount_' . $lineId
                                )
                                    ->hiddenLabel()
                                    ->content(function (
                                        \Filament\Forms\Get $get
                                    ) use ($lineId, $lines): string {
                                        // BEXIA_V5836_POS_REFUND_NET_P10I20
                                        $quantities = [];
                                        foreach ($lines as $item) {
                                            $quantities['line_' . $item->id] =
                                                $get('line_' . $item->id) ?? '0';
                                        }

                                        try {
                                            $preview = $this->v5836RefundNetPreview(
                                                $quantities,
                                                (bool) $get('refund_all')
                                            );

                                            foreach ($preview['lines'] as $row) {
                                                if ((int) $row['id'] === $lineId) {
                                                    return '$' . number_format(
                                                        $row['refund_cents'] / 100,
                                                        2
                                                    );
                                                }
                                            }
                                        } catch (\Throwable $e) {
                                            return 'No disponible';
                                        }

                                        return 'No disponible';
                                    })
                                    ->columnSpan(2),
                            ])
                            ->extraAttributes([
                                'class' => 'text-xs border-b border-gray-200 dark:border-gray-700 px-3 py-1 items-center',
                            ]);
                    }

                    $fields[] = \Filament\Forms\Components\Placeholder::make(
                        'refund_product_summary'
                    )
                        ->label('Resumen de devolución')
                        ->content(function (
                            \Filament\Forms\Get $get
                        ) use ($lines): string {
                            // BEXIA_V5836_POS_REFUND_NET_P10I20
                            $quantities = [];
                            $refundAll = (bool) $get('refund_all');

                            foreach ($lines as $item) {
                                $quantities['line_' . $item->id] =
                                    $get('line_' . $item->id) ?? '0';
                            }

                            try {
                                $preview = $this->v5836RefundNetPreview(
                                    $quantities,
                                    $refundAll
                                );
                            } catch (\Throwable $e) {
                                return 'Importe no disponible. '
                                    . 'Revisa las cantidades y '
                                    . 'la conciliación del ticket.';
                            }

                            $products = 0;
                            $units = 0.0;
                            $grossCents = 0;

                            foreach ($preview['lines'] as $row) {
                                $returned = (float) $row['quantity'];
                                if ($returned <= 0) {
                                    continue;
                                }

                                $products++;
                                $units += $returned;
                                $grossCents += (int) round(
                                    $row['gross_cents']
                                    * ($returned / $row['sold'])
                                );
                            }

                            $netCents = (int) $preview['refund_cents'];
                            $discountCents = $grossCents - $netCents;

                            return 'Productos: ' . $products
                                . ' | Unidades: ' . number_format($units, 2)
                                . ' | Bruto: $'
                                . number_format($grossCents / 100, 2)
                                . ' | Descuento: $'
                                . number_format($discountCents / 100, 2)
                                . ' | Neto a devolver: $'
                                . number_format($netCents / 100, 2);
                        })
                        ->extraAttributes([
                            'class' => 'text-sm font-bold bg-gray-50 dark:bg-gray-800 rounded-lg px-4 py-3',
                        ]);

                    // BEXIA_V5836_REFUND_FIX2A2_R2
                    // Campos de preparacion: SIN procesamiento financiero.
                    $fields[] = \Filament\Forms\Components\Section::make(
                        'Métodos de devolución'
                    )
                        ->description(
                            'Selecciona uno o varios métodos e indica el importe '
                            . 'a devolver por cada uno.'
                        )
                        ->schema([
                            \Filament\Forms\Components\Repeater::make('refund_payments')
                                ->label('Distribución del reembolso')
                                ->schema([
                                    \Filament\Forms\Components\Select::make('payment_form_id')
                                        ->label('Método')
                                        ->options(function (): array {
                                            $pos = DB::table('pos_points')
                                                ->where('id', (int) $this->record->pos_point_id)
                                                ->where('company_id', (int) $this->record->company_id)
                                                ->first(['payment_method_ids']);

                                            $ids = json_decode(
                                                (string) ($pos->payment_method_ids ?? '[]'),
                                                true
                                            );

                                            if (! is_array($ids)) {
                                                return [];
                                            }

                                            $ids = array_filter(
                                                array_map('intval', $ids),
                                                fn (int $id): bool => $id > 0
                                            );

                                            return DB::table('payment_forms')
                                                ->where('company_id', (int) $this->record->company_id)
                                                ->where('is_active', true)
                                                ->whereIn('id', $ids)
                                                ->where(function ($q) {
                                                    $q->where('is_cash', true)
                                                        ->orWhereIn('name', [
                                                            'Tarjeta de crédito',
                                                            'Tarjeta de débito',
                                                            'Transferencia electrónica de fondos',
                                                        ]);
                                                })
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->all();
                                        })
                                        ->live()
                                        ->required()
                                        ->native(false),

                                    \Filament\Forms\Components\TextInput::make('amount')
                                        ->label('Importe')
                                        // BEXIA_V5836_REFUND_FIX2A5_R2
                                        // Al crear una fila, completar lo pendiente.
                                        ->default(function (
                                            \Filament\Forms\Get $get
                                        ) use ($lines): string {
                                            // BEXIA_V5836_POS_REFUND_NET_P10I22
                                            // El saldo pendiente se calcula
                                            // sobre el total neto autorizado.
                                            $quantities = [];

                                            foreach ($lines as $line) {
                                                $quantities['line_' . $line->id] =
                                                    $get('../../line_' . $line->id)
                                                    ?? '0';
                                            }

                                            try {
                                                $netPreview = $this->v5836RefundNetPreview(
                                                    $quantities,
                                                    (bool) $get('../../refund_all')
                                                );
                                                $totalCents = (int)
                                                    $netPreview['refund_cents'];
                                            } catch (\Throwable $e) {
                                                // Nunca sugerir importe bruto
                                                // cuando no hay conciliacion.
                                                $totalCents = 0;
                                            }

                                            $usedCents = 0;
                                            $payments = $get('../../refund_payments');

                                            if (is_array($payments)) {
                                                foreach ($payments as $payment) {
                                                    if (! is_array($payment)) {
                                                        continue;
                                                    }

                                                    $raw = trim((string) (
                                                        $payment['amount'] ?? ''
                                                    ));

                                                    if (! preg_match(
                                                        '/^\d+(\.\d{1,2})?$/',
                                                        $raw
                                                    )) {
                                                        continue;
                                                    }

                                                    $usedCents += (int) round(
                                                        ((float) $raw) * 100
                                                    );
                                                }
                                            }

                                            return number_format(
                                                max(0, $totalCents - $usedCents) / 100,
                                                2,
                                                '.',
                                                ''
                                            );
                                        })
                                        ->numeric()
                                        ->minValue(0.01)
                                        ->step(0.01)
                                        ->prefix('$')
                                        ->live(onBlur: true)
                                        ->required(),

                                    \Filament\Forms\Components\TextInput::make('reference')
                                        ->label('Referencia o comprobante')
                                        ->maxLength(120)
                                        ->live(onBlur: true),
                                ])
                                ->columns(3)
                                ->defaultItems(1)
                                ->live()
                                ->minItems(1)
                                ->maxItems(8)
                                ->addActionLabel('Agregar otro método')
                                ->required(),
                        ]);

                    // BEXIA_V5836_REFUND_FIX2A4
                    // Resumen visual. No autoriza movimientos financieros.
                    $fields[] = \Filament\Forms\Components\Placeholder::make(
                        'refund_payment_balance'
                    )
                        ->label('Conciliación de métodos de devolución')
                        ->content(function (
                            \Filament\Forms\Get $get
                        ) use ($lines): \Illuminate\Support\HtmlString {
                            // BEXIA_V5836_POS_REFUND_NET_P10I22
                            // Mismo importe neto que valida el backend.
                            $quantities = [];

                            foreach ($lines as $item) {
                                $quantities['line_' . $item->id] =
                                    $get('line_' . $item->id) ?? '0';
                            }

                            try {
                                $netPreview = $this->v5836RefundNetPreview(
                                    $quantities,
                                    (bool) $get('refund_all')
                                );
                                $expectedCents = (int)
                                    $netPreview['refund_cents'];
                            } catch (\Throwable $e) {
                                return new \Illuminate\Support\HtmlString(
                                    '<div class="text-danger-600">'
                                    . 'No se puede conciliar la devolución. '
                                    . 'Revisa las cantidades y el ticket.'
                                    . '</div>'
                                );
                            }

                            $paymentCents = 0;
                            $invalid = false;
                            $payments = $get('refund_payments') ?? [];

                            if (! is_array($payments)) {
                                $payments = [];
                            }

                            foreach ($payments as $payment) {
                                if (! is_array($payment)) {
                                    $invalid = true;
                                    continue;
                                }

                                $raw = trim((string) ($payment['amount'] ?? ''));

                                if ($raw === '') {
                                    continue;
                                }

                                if (! preg_match(
                                    '/^\d+(\.\d{1,2})?$/',
                                    $raw
                                )) {
                                    $invalid = true;
                                    continue;
                                }

                                $cents = (int) round((float) $raw * 100);

                                if ($cents <= 0) {
                                    $invalid = true;
                                    continue;
                                }

                                $paymentCents += $cents;
                            }

                            $difference = $expectedCents - $paymentCents;

                            if ($invalid) {
                                $message = 'Importe inválido: máximo 2 decimales.';
                                $color = '#b91c1c';
                            } elseif ($expectedCents <= 0) {
                                $message = 'Selecciona productos para devolver.';
                                $color = '#92400e';
                            } elseif ($difference < 0) {
                                $message = 'EXCEDE EL TOTAL EN $'
                                    . number_format(abs($difference) / 100, 2);
                                $color = '#b91c1c';
                            } elseif ($difference > 0) {
                                $message = 'FALTA DISTRIBUIR $'
                                    . number_format($difference / 100, 2);
                                $color = '#92400e';
                            } else {
                                $message = 'IMPORTES COINCIDEN';
                                $color = '#15803d';
                            }

                            // BEXIA_V5836_POS_REFUND_FIX2B8P1A
                            // Igualar el preview a requires_reference
                            // del catalogo validado por backend.
                            $missingReferences = [];
                            $missingMethods = false;

                            $selectedIds = [];
                            foreach ($payments as $payment) {
                                if (! is_array($payment)) {
                                    continue;
                                }
                                $id = (int) (
                                    $payment['payment_form_id'] ?? 0
                                );
                                if ($id > 0) {
                                    $selectedIds[] = $id;
                                }
                            }

                            $forms = collect();
                            if ($selectedIds !== []) {
                                $forms = \Illuminate\Support\Facades\DB::table(
                                    'payment_forms'
                                )
                                    ->where(
                                        'company_id',
                                        (int) $this->record->company_id
                                    )
                                    ->whereIn(
                                        'id',
                                        array_unique($selectedIds)
                                    )
                                    ->get([
                                        'id',
                                        'name',
                                        'requires_reference',
                                    ])
                                    ->keyBy('id');
                            }

                            foreach ($payments as $payment) {
                                if (! is_array($payment)) {
                                    continue;
                                }

                                $id = (int) (
                                    $payment['payment_form_id'] ?? 0
                                );
                                $form = $forms->get($id);

                                if (! $form) {
                                    $missingMethods = true;
                                    continue;
                                }

                                if (
                                    (bool) $form->requires_reference
                                    && trim(
                                        (string) (
                                            $payment['reference'] ?? ''
                                        )
                                    ) === ''
                                ) {
                                    $missingReferences[] =
                                        (string) $form->name;
                                }
                            }

                            if (
                                ! $invalid
                                && $expectedCents > 0
                                && $difference === 0
                            ) {
                                if ($missingMethods) {
                                    $message =
                                        'SELECCIONA UN MÉTODO VÁLIDO.';
                                    $color = '#b91c1c';
                                } elseif ($missingReferences !== []) {
                                    $message =
                                        'FALTA REFERENCIA O COMPROBANTE: '
                                        . implode(
                                            ', ',
                                            array_unique(
                                                $missingReferences
                                            )
                                        );
                                    $color = '#b91c1c';
                                }
                            }

                            $html = '<div style="font-size:14px;line-height:1.8">'
                                . '<div>Productos a devolver: <strong>$'
                                . number_format($expectedCents / 100, 2)
                                . '</strong></div>'
                                . '<div>Distribuido entre métodos: <strong>$'
                                . number_format($paymentCents / 100, 2)
                                . '</strong></div>'
                                . '<div style="font-weight:700;color:'
                                . $color . '">'
                                . $message
                                . '</div></div>';

                            return new \Illuminate\Support\HtmlString($html);
                        });


                    return $fields;
                })
                ->requiresConfirmation()
                ->modalWidth(\Filament\Support\Enums\MaxWidth::SevenExtraLarge)
                ->modalHeading(fn (): string => 'Devolución - ' . ($this->record->number ?: ('#' . $this->record->id)))
                ->modalDescription('Puedes devolver todo el ticket o capturar cantidades específicas para una devolución parcial. Los productos inventariables regresarán automáticamente al inventario; los servicios solo generan devolución financiera.')
                // BEXIA_V5836_REFUND_FIX2A4_R1_SUBMIT_GUARD
                // Habilitar confirmacion solo con formulario valido.
                ->modalSubmitAction(function (
                    \Filament\Actions\StaticAction $action,
                    $livewire
                ): void {
                    $action->label('Registrar devolución')
                        ->disabled(function () use ($livewire): bool {
                            $states = $livewire->mountedActionsData ?? [];
                            if (! is_array($states) || $states === []) {
                                return true;
                            }

                            $data = end($states);

                            return ! is_array($data)
                                || ! $this->v5836RefundFormIsReady($data);
                        });
                })
                ->action(function (array $data): void {
                    // BEXIA_V5836_REFUND_FIX2A2_R2_SAFE_GUARD
                    // Revalidar en backend: no basta ocultar el boton.
                    if (
                        ! \App\Filament\Resources\PosTicketResource::
                            v5836CanAccessRefundPos($this->record)
                        || ! \App\Filament\Resources\PosTicketResource::
                            canRefundTicket($this->record)
                    ) {
                        throw new \RuntimeException(
                            'No tienes autorización para devolver este ticket.'
                        );
                    }

                    // BEXIA_V5836_POS_REFUND_FIX2B8P5R
                    // Validar nuevamente al enviar, sin confiar
                    // exclusivamente en el boton reactivo.
                    if (! $this->v5836RefundFormIsReady($data)) {
                        \Filament\Notifications\Notification::make()
                            ->title('Datos de devolución incompletos')
                            ->body(
                                'Revisa el motivo, las cantidades, '
                                . 'los métodos, los importes y las '
                                . 'referencias obligatorias.'
                            )
                            ->danger()
                            ->send();

                        throw new \Filament\Support\Exceptions\Halt();
                    }

                    // BEXIA_V5836_POS_REFUND_FIX2B8N
                    // Ruta habilitada con controles de empresa, PDV y permisos.
                    $reason = trim((string) ($data['reason'] ?? ''));
                    $refundAll = (bool) ($data['refund_all'] ?? false);
                    $allocations = $data['refund_payments'] ?? [];

                    if (! is_array($allocations)) {
                        throw new \RuntimeException(
                            'La distribución de pagos no es válida.'
                        );
                    }

                    $quantities = [];

                    foreach ($data as $key => $value) {
                        if (preg_match('/\Aline_\d+\z/D', (string) $key)) {
                            $quantities[$key] = $value;
                        }
                    }

                    try {
                        $result = \App\Filament\Resources\PosTicketResource::
                            v5836CreateMultimethodRefund(
                                $this->record,
                                $reason,
                                $refundAll,
                                $quantities,
                                $allocations
                            );
                    } catch (\RuntimeException $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('No se registró la devolución')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        throw new \Filament\Support\Exceptions\Halt();
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Devolución registrada')
                        ->body(
                            'Devolución #'
                            . (int) $result['refund_id']
                            . ' por $'
                            . (string) $result['amount']
                            . '. Inventario y movimientos financieros '
                            . 'procesados.'
                        )
                        ->success()
                        ->send();

                    $this->redirect(
                        static::getResource()::getUrl(
                            'view',
                            ['record' => $this->record]
                        )
                    );
                }),

            Actions\Action::make('back_to_tickets')
                ->label('Volver a tickets')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(PosTicketResource::getUrl('index')),

            Actions\Action::make('print_pending')
                ->label('Imprimir ticket pendiente')
                ->icon('heroicon-o-printer')
                ->color('warning')
                ->visible(fn (): bool => (string) ($this->record->status ?? '') === 'pending_payment')
                ->url(fn (): string => PosTicketResource::pendingPrintUrl($this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('print_receipt')
                ->label('Imprimir ticket pagado')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->visible(fn (): bool => (string) ($this->record->status ?? '') === 'paid')
                ->url(fn (): string => PosTicketResource::receiptPrintUrl($this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('stock_movement')
                ->label('Ver salida')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('success')
                ->visible(fn (): bool => PosTicketResource::stockMovementUrl($this->record) !== '#')
                ->url(fn (): string => PosTicketResource::stockMovementUrl($this->record)),

            /*
             * BEXIA_V5525H_PDV_BILLING_BUTTONS_ORDER
             * Flujo claro:
             * 1) Solicitar factura: solo marca el ticket como solicitado.
             * 2) Abrir portal de facturación: liga para captura/consulta del cliente.
             * 3) Crear factura desde ticket: crea la factura interna desde el ticket.
             * 4) Ver factura: aparece cuando ya existe factura.
             */

            Actions\Action::make('invoice_portal')
                ->label('Abrir portal de facturación')
                ->icon('heroicon-o-globe-alt')
                ->color('info')
                ->visible(function (): bool {
                    /*
                     * BEXIA_V5527B_BLOCK_PORTAL_WHEN_TICKET_NOT_FISCALLY_ELIGIBLE
                     */
                    return PosTicketResource::canCreateIndividualInvoiceFromTicket($this->record);
                })
                ->url(fn (): string => PosTicketResource::invoicePortalUrl($this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('generate_internal_invoice')
                ->label('Crear factura desde ticket')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Crear factura desde ticket')
                ->modalDescription('Esto creará una factura interna desde este ticket PDV. No timbra CFDI todavía.')
                ->modalSubmitActionLabel('Sí, crear factura')
                ->modalCancelActionLabel('Salir')
                ->visible(function (): bool {
                    /*
                     * BEXIA_V5527B_BLOCK_INDIVIDUAL_INVOICE_DUPLICATES
                     */
                    return PosTicketResource::canCreateIndividualInvoiceFromTicket($this->record);
                })
                ->action(function (): void {
                    try {
                        if (! PosTicketResource::canCreateIndividualInvoiceFromTicket($this->record)) {
                            throw new \RuntimeException('Este ticket no es elegible para factura individual. Estado fiscal: '.PosTicketResource::fiscalStatusLabel(PosTicketResource::fiscalStatus($this->record)));
                        }

                        $invoiceId = app(\App\Support\InternalInvoiceBuilder::class)
                            ->createFromPosOrder((int) $this->record->id, auth()->id());

                        $invoice = \Illuminate\Support\Facades\DB::table('invoices')
                            ->where('id', $invoiceId)
                            ->first();

                        /*
                         * Marcar el ticket como facturado internamente para que el usuario
                         * ya no vea acciones que no aplican.
                         */
                        $metadata = PosTicketResource::metadataArray($this->record);
                        $metadata['billing_status'] = 'internal_invoice_draft';
                        $metadata['internal_invoice_id'] = $invoiceId;
                        $metadata['internal_invoice_created_at'] = now()->toDateTimeString();
                        $metadata['internal_invoice_created_by_user_id'] = auth()->id();

                        \Illuminate\Support\Facades\DB::table('pos_orders')
                            ->where('id', (int) $this->record->id)
                            ->update([
                                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                                'updated_at' => now(),
                            ]);

                        $this->record->refresh();

                        \Filament\Notifications\Notification::make()
                            ->title('Factura creada desde ticket')
                            ->body('Folio: ' . ($invoice->number ?? ('#' . $invoiceId)))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('No se pudo crear la factura')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            /*
             * BEXIA_V5527C_GLOBAL_INVOICE_LINK_FROM_POS_TICKET
             * Ver factura debe abrir factura individual o factura global ligada al ticket.
             */
            Actions\Action::make('view_internal_invoice')
                ->label('Ver factura')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->visible(fn (): bool => \App\Filament\Resources\PosTicketResource::fiscalInvoiceUrl($this->record) !== '#')
                ->url(fn (): string => \App\Filament\Resources\PosTicketResource::fiscalInvoiceUrl($this->record)),

        ];
    }

    protected function getViewData(): array
    {
        return [
            'order' => $this->record,
            'metadata' => PosTicketResource::metadataArray($this->record),
            'lines' => PosTicketResource::orderLines((int) $this->record->id),
            'payments' => PosTicketResource::orderPayments((int) $this->record->id),
            'movement' => PosTicketResource::stockMovementForOrder($this->record),
            'pendingPrintUrl' => PosTicketResource::pendingPrintUrl($this->record),
            'receiptPrintUrl' => PosTicketResource::receiptPrintUrl($this->record),
            'invoicePortalUrl' => PosTicketResource::invoicePortalUrl($this->record),
            'stockMovementUrl' => PosTicketResource::stockMovementUrl($this->record),
        ];
    }
}
