<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PosTicketResource\Pages;
use App\Models\PosOrder;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class PosTicketResource extends Resource
{
    protected static ?string $model = PosOrder::class;

    protected static ?string $navigationGroup = 'Punto de Venta';

    protected static ?string $navigationLabel = 'Tickets PDV';

    protected static ?string $modelLabel = 'ticket PDV';

    protected static ?string $pluralModelLabel = 'tickets PDV';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?int $navigationSort = 10;

    protected static bool $isScopedToTenant = false;

public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('pos_orders', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('pos.menu.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('pos.menu.view')
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->recordUrl(fn ($record): string => static::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Folio')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->state(fn ($record): string => static::v5509dRefundStatusLabel($record))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => static::statusLabel((string) $state))
                    ->color(fn ($state): string => match ((string) $state) {
                        'paid' => 'success',
                        'pending_payment' => 'warning',
                        'cancelled', 'canceled' => 'danger',
                        'returned', 'refunded' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('inventory_status')
                    ->label('Inventario')
                    ->badge()
                    ->state(fn ($record): string => static::inventoryStatus($record))
                    ->formatStateUsing(fn ($state): string => static::inventoryStatusLabel((string) $state))
                    ->color(fn ($state): string => match ((string) $state) {
                        'delivered' => 'success',
                        'no_stockable_products' => 'gray',
                        'pending_configuration', 'pending_no_quant', 'pending_insufficient_stock', 'pending_error' => 'warning',
                        default => 'gray',
                    }),


                /*
                 * BEXIA_V5527B_POS_TICKET_FISCAL_STATE_COLUMN
                 * Estado fiscal calculado: evita doble facturación individual/global.
                 */
                Tables\Columns\TextColumn::make('fiscal_state_pos_ticket')
                    ->label('Estado fiscal')
                    ->badge()
                    ->state(fn ($record): string => static::fiscalStatus($record))
                    ->formatStateUsing(fn ($state): string => static::fiscalStatusLabel((string) $state))
                    ->color(fn ($state): string => static::fiscalStatusColor((string) $state))
                    ->description(fn ($record): ?string => static::fiscalStatusDescription($record))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('billing_status')
                    ->label('Solicitud portal')
                    ->badge()
                    ->state(fn ($record): string => static::billingStatus($record))
                    ->formatStateUsing(fn ($state): string => static::billingStatusLabel((string) $state))
                    ->color(fn ($state): string => match ((string) $state) {
                        'requested' => 'warning',
                        'invoiced', 'internal_invoice_draft' => 'success',
                        'not_required' => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('customer_id')
                    ->label('Cliente')
                    ->state(fn ($record): string => static::customerLabel($record->customer_id))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        if (! Schema::hasTable('contacts')) {
                            return $query;
                        }

                        $ids = DB::table('contacts')
                            ->where('name', 'ilike', '%' . $search . '%')
                            ->orWhere('commercial_name', 'ilike', '%' . $search . '%')
                            ->orWhere('fiscal_name', 'ilike', '%' . $search . '%')
                            ->limit(50)
                            ->pluck('id')
                            ->all();

                        return $query->whereIn('customer_id', $ids ?: [-1]);
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('pos_session_id')
                    ->label('Sesión')
                    ->state(fn ($record): string => static::labelFromTable('pos_sessions', $record->pos_session_id, ['number']))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('pos_point_id')
                    ->label('PDV')
                    ->state(fn ($record): string => static::labelFromTable('pos_points', $record->pos_point_id, ['name', 'code']))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ordered_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Pagado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('payment_count')
                    ->label('Pagos')
                    ->state(fn ($record): string => (string) static::paymentCount($record->id))
                    ->alignCenter()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending_payment' => 'Pendiente de cobro',
                        'paid' => 'Pagado',
                        'cancelled' => 'Cancelado',
                        'returned' => 'Devuelto',
                    ]),

                Tables\Filters\Filter::make('billing_requested')
                    ->label('Enviado a facturación')
                    ->query(fn (Builder $query): Builder => $query->where('metadata', 'like', '%"billing_status":"requested"%')),

                Tables\Filters\Filter::make('today')
                    ->label('Hoy')
                    ->query(fn (Builder $query): Builder => $query->whereDate('ordered_at', now()->toDateString())),
            ])
            ->actions([
                Tables\Actions\Action::make('view_ticket')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->url(fn ($record): string => static::getUrl('view', ['record' => $record])),

            ])
            ->bulkActions([])
            ->emptyStateHeading('No hay tickets PDV')
            ->emptyStateDescription('Cuando se creen o cobren tickets en el PDV aparecerán aquí.');
    }



    public static function v5509dRefundForTicket(object $record): ?object
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')) {
            return null;
        }

        return \Illuminate\Support\Facades\DB::table('pos_order_refunds')
            ->where('pos_order_id', (int) $record->id)
            ->where('status', 'done')
            ->orderByDesc('id')
            ->first();
    }

    public static function v5509dHasDoneRefund(object $record): bool
    {
        return (bool) static::v5509dRefundForTicket($record);
    }

    public static function v5509dRefundStatusLabel(object $record): string
    {
        $refund = static::v5509dRefundForTicket($record);

        if (! $refund) {
            return static::statusLabel((string) ($record->status ?? ''));
        }

        return match ((string) ($refund->type ?? '')) {
            'partial' => 'Devuelto parcial',
            'total' => 'Devuelto total',
            default => 'Devuelto',
        };
    }



    public static function v5513cUserCanAnyRefundPermission(array $permissions): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
            return true;
        }

        if (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (method_exists($user, 'can') && $user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    public static function v5513cCanRefundCreate(): bool
    {
        return static::v5513cUserCanAnyRefundPermission([
            'pos.refund.create',
            'pos.refund.full',
            'pos.refund.partial',

            // Compatibilidad temporal con permiso anterior.
            'pos.refunds.create',
        ]);
    }

    public static function v5513cCanRefundFull(): bool
    {
        return static::v5513cUserCanAnyRefundPermission([
            'pos.refund.full',

            // Compatibilidad temporal con permiso anterior.
            'pos.refunds.create',
        ]);
    }

    public static function v5513cCanRefundPartial(): bool
    {
        return static::v5513cUserCanAnyRefundPermission([
            'pos.refund.partial',

            // Compatibilidad temporal con permiso anterior.
            'pos.refunds.create',
        ]);
    }


    public static function v5515aWritePosAuditLog(string $action, array $payload = []): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('pos_audit_logs')) {
                return;
            }

            $request = request();

            $companyId = $payload['company_id'] ?? null;

            if (! $companyId && isset($payload['pos_order_id']) && \Illuminate\Support\Facades\Schema::hasTable('pos_orders')) {
                $companyId = \Illuminate\Support\Facades\DB::table('pos_orders')
                    ->where('id', (int) $payload['pos_order_id'])
                    ->value('company_id');
            }

            if (! $companyId && function_exists('tenant')) {
                try {
                    $tenant = tenant();

                    if (is_object($tenant) && isset($tenant->id)) {
                        $companyId = (int) $tenant->id;
                    }
                } catch (\Throwable $e) {
                    //
                }
            }

            \Illuminate\Support\Facades\DB::table('pos_audit_logs')->insert([
                'company_id' => $companyId,
                'user_id' => auth()->id(),

                'pos_session_id' => $payload['pos_session_id'] ?? null,
                'pos_order_id' => $payload['pos_order_id'] ?? null,
                'pos_order_refund_id' => $payload['pos_order_refund_id'] ?? null,
                'stock_movement_id' => $payload['stock_movement_id'] ?? null,

                'action' => $action,
                'entity_type' => $payload['entity_type'] ?? null,
                'entity_id' => $payload['entity_id'] ?? null,

                'description' => $payload['description'] ?? null,

                'before_data' => isset($payload['before_data'])
                    ? json_encode($payload['before_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,

                'after_data' => isset($payload['after_data'])
                    ? json_encode($payload['after_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,

                'metadata' => isset($payload['metadata'])
                    ? json_encode($payload['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,

                'ip_address' => $request?->ip(),
                'user_agent' => substr((string) $request?->userAgent(), 0, 2000),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo escribir auditoría devolución PDV.', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function v5506bCanCreateRefund(): bool
    {
        return static::v5513cCanRefundCreate();
    }


    public static function v5506bMetadataArray(?object $row): array
    {
        if (! $row || empty($row->metadata)) {
            return [];
        }

        $decoded = json_decode((string) $row->metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function v5506bJson(?array $data): ?string
    {
        return $data === null
            ? null
            : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function v5506bNextRefundNumber(?object $order = null): string
    {
        $date = now()->format('Ymd');
        $base = 'DEV-' . $date . '-';

        $last = DB::table('pos_order_refunds')
            ->where('number', 'like', $base . '%')
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if ($last && preg_match('/-(\d+)$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $base . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public static function v5506bLineAmounts(object $line): array
    {
        $qty = round((float) ($line->quantity ?? 0), 6);
        $unitPrice = round((float) ($line->unit_price ?? 0), 6);
        $taxRate = round((float) ($line->tax_rate ?? 0), 4);

        $total = round((float) ($line->total ?? ($qty * $unitPrice)), 4);

        if ($total <= 0 && $qty > 0 && $unitPrice > 0) {
            $total = round($qty * $unitPrice, 4);
        }

        if (isset($line->subtotal)) {
            $subtotal = round((float) $line->subtotal, 4);
        } elseif ($taxRate > 0) {
            $subtotal = round($total / (1 + ($taxRate / 100)), 4);
        } else {
            $subtotal = $total;
        }

        if (isset($line->tax_total)) {
            $taxTotal = round((float) $line->tax_total, 4);
        } else {
            $taxTotal = round($total - $subtotal, 4);
        }

        return [
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'tax_rate' => $taxRate,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $total,
        ];
    }

    public static function v5506bCreateTotalRefund(object $record, string $reason): int
    {
        // V5.51.5A - Audit intento devolución total.
        static::v5515aWritePosAuditLog('pos.refund.total.attempt', [
            'pos_order_id' => (int) ($record->id ?? 0),
            'pos_session_id' => (int) ($record->pos_session_id ?? 0),
            'entity_type' => 'pos_order',
            'entity_id' => (int) ($record->id ?? 0),
            'description' => 'Intento de devolución total de ticket PDV.',
            'before_data' => [
                'order_number' => $record->number ?? null,
                'status' => $record->status ?? null,
                'total' => $record->total ?? null,
            ],
            'metadata' => [
                'reason' => $reason,
            ],
        ]);


        // V5.51.3C - Permiso backend devolución total.
        if (! static::v5513cCanRefundFull()) {
            throw new \RuntimeException('No tienes permiso para registrar devoluciones totales.');
        }


        $reason = trim($reason);

        if ($reason === '') {
            throw new \RuntimeException('El motivo de devolución es obligatorio.');
        }

        foreach (['pos_order_refunds', 'pos_order_refund_lines', 'pos_order_refund_payments', 'pos_orders', 'pos_order_lines'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException('No existe la tabla requerida: ' . $table);
            }
        }

        return DB::transaction(function () use ($record, $reason): int {
            $order = DB::table('pos_orders')
                ->where('id', (int) $record->id)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new \RuntimeException('No se encontró el ticket.');
            }

            if ((string) ($order->status ?? '') !== 'paid') {
                throw new \RuntimeException('Solo se pueden devolver tickets pagados.');
            }

            $existingDoneRefund = DB::table('pos_order_refunds')
                ->where('pos_order_id', $order->id)
                ->where('status', 'done')
                ->exists();

            if ($existingDoneRefund) {
                throw new \RuntimeException('Este ticket ya tiene una devolución registrada.');
            }

            $lines = DB::table('pos_order_lines')
                ->where('pos_order_id', $order->id)
                ->orderBy('id')
                ->get();

            if ($lines->isEmpty()) {
                throw new \RuntimeException('El ticket no tiene líneas para devolver.');
            }

            $payments = Schema::hasTable('pos_order_payments')
                ? DB::table('pos_order_payments')
                    ->where('pos_order_id', $order->id)
                    ->orderBy('id')
                    ->get()
                : collect();

            $refundNumber = static::v5506bNextRefundNumber($order);

            $subtotal = 0.0;
            $taxTotal = 0.0;
            $total = 0.0;

            foreach ($lines as $line) {
                $amounts = static::v5506bLineAmounts($line);
                $subtotal += $amounts['subtotal'];
                $taxTotal += $amounts['tax_total'];
                $total += $amounts['total'];
            }

            $subtotal = round($subtotal, 4);
            $taxTotal = round($taxTotal, 4);
            $total = round((float) ($order->total ?? $total), 4);

            $paymentTotal = round((float) $payments->sum(fn ($payment) => (float) ($payment->amount ?? 0)), 4);

            if ($paymentTotal <= 0) {
                $paymentTotal = $total;
            }

            $orderMetadata = static::v5506bMetadataArray($order);

            $refundId = DB::table('pos_order_refunds')->insertGetId([
                'company_id' => $order->company_id ?? null,
                'pos_order_id' => $order->id,
                'pos_session_id' => $order->pos_session_id ?? null,
                'pos_point_id' => $order->pos_point_id ?? null,
                'customer_id' => $order->customer_id ?? null,
                'number' => $refundNumber,
                'type' => 'total',
                'status' => 'done',
                'reason' => $reason,
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $total,
                'payment_total' => $paymentTotal,
                'stock_movement_id' => null,
                'created_by_user_id' => auth()->id(),
                'refunded_at' => now(),
                'metadata' => static::v5506bJson([
                    'source' => 'filament_pos_ticket_total_refund',
                    'inventory_return_status' => 'pending',
                    'original_order_number' => $order->number ?? null,
                    'original_order_status' => $order->status ?? null,
                    'original_stock_movement_id' => $orderMetadata['stock_movement_id'] ?? null,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($lines as $line) {
                $amounts = static::v5506bLineAmounts($line);

                DB::table('pos_order_refund_lines')->insert([
                    'pos_order_refund_id' => $refundId,
                    'pos_order_id' => $order->id,
                    'pos_order_line_id' => $line->id ?? null,
                    'product_id' => $line->product_id ?? null,
                    'product_variant_id' => $line->product_variant_id ?? null,
                    'product_name' => $line->product_name ?? ($line->name ?? null),
                    'product_reference' => $line->product_reference ?? ($line->reference ?? null),
                    'quantity' => $amounts['quantity'],
                    'unit_price' => $amounts['unit_price'],
                    'tax_rate' => $amounts['tax_rate'],
                    'subtotal' => $amounts['subtotal'],
                    'tax_total' => $amounts['tax_total'],
                    'total' => $amounts['total'],
                    'metadata' => static::v5506bJson([
                        'source_line_id' => $line->id ?? null,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($payments->isEmpty()) {
                DB::table('pos_order_refund_payments')->insert([
                    'pos_order_refund_id' => $refundId,
                    'pos_order_id' => $order->id,
                    'payment_form_id' => null,
                    'payment_label' => 'No especificado',
                    'amount' => $paymentTotal,
                    'status' => 'refunded',
                    'metadata' => static::v5506bJson([
                        'source' => 'no_original_payment_records',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                foreach ($payments as $payment) {
                    DB::table('pos_order_refund_payments')->insert([
                        'pos_order_refund_id' => $refundId,
                        'pos_order_id' => $order->id,
                        'payment_form_id' => $payment->payment_form_id ?? null,
                        'payment_label' => $payment->payment_label ?? 'Pago',
                        'amount' => round((float) ($payment->amount ?? 0), 4),
                        'status' => 'refunded',
                        'metadata' => static::v5506bJson([
                            'source_payment_id' => $payment->id ?? null,
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $orderMetadata['refund_status'] = 'total_refunded';
            $orderMetadata['refund_id'] = $refundId;
            $orderMetadata['refund_number'] = $refundNumber;
            $orderMetadata['refunded_at'] = now()->toDateTimeString();
            $orderMetadata['refunded_by_user_id'] = auth()->id();
            $orderMetadata['refund_reason'] = $reason;
            $orderMetadata['inventory_return_status'] = 'pending';

            DB::table('pos_orders')
                ->where('id', $order->id)
                ->update([
                    'status' => 'returned',
                    'metadata' => static::v5506bJson($orderMetadata),
                    'updated_at' => now(),
                ]);

                        static::v5515aWritePosAuditLog('pos.refund.total.success', [
                'pos_order_id' => (int) ($record->id ?? 0),
                'pos_session_id' => (int) ($record->pos_session_id ?? 0),
                'pos_order_refund_id' => (int) $refundId,
                'entity_type' => 'pos_order_refund',
                'entity_id' => (int) $refundId,
                'description' => 'Devolución total de ticket PDV registrada.',
                'after_data' => [
                    'refund_id' => (int) $refundId,
                    'refund_number' => $refundNumber ?? null,
                    'refund_total' => $paymentTotal ?? $total ?? null,
                ],
                'metadata' => [
                    'reason' => $reason,
                    'order_id' => (int) ($record->id ?? 0),
                    'order_number' => $record->number ?? null,
                ],
            ]);

return (int) $refundId;
        });
    }




    public static function v5509bCreatePartialRefund(object $record, string $reason, array $quantities): int
    {
        // V5.51.5A - Audit intento devolución parcial.
        static::v5515aWritePosAuditLog('pos.refund.partial.attempt', [
            'pos_order_id' => (int) ($record->id ?? 0),
            'pos_session_id' => (int) ($record->pos_session_id ?? 0),
            'entity_type' => 'pos_order',
            'entity_id' => (int) ($record->id ?? 0),
            'description' => 'Intento de devolución parcial de ticket PDV.',
            'before_data' => [
                'order_number' => $record->number ?? null,
                'status' => $record->status ?? null,
                'total' => $record->total ?? null,
            ],
            'metadata' => [
                'reason' => $reason,
                'quantities' => $quantities,
            ],
        ]);


        // V5.51.3C - Permiso backend devolución parcial.
        if (! static::v5513cCanRefundPartial()) {
            throw new \RuntimeException('No tienes permiso para registrar devoluciones parciales.');
        }


        $reason = trim($reason);

        if ($reason === '') {
            throw new \RuntimeException('El motivo de devolución es obligatorio.');
        }

        foreach (['pos_order_refunds', 'pos_order_refund_lines', 'pos_order_refund_payments', 'pos_orders', 'pos_order_lines'] as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                throw new \RuntimeException('No existe la tabla requerida: ' . $table);
            }
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($record, $reason, $quantities): int {
            $order = \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('id', (int) $record->id)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new \RuntimeException('No se encontró el ticket.');
            }

            if ((string) ($order->status ?? '') !== 'paid') {
                throw new \RuntimeException('Solo se pueden hacer devoluciones parciales de tickets pagados.');
            }

            $existingDoneRefund = \Illuminate\Support\Facades\DB::table('pos_order_refunds')
                ->where('pos_order_id', $order->id)
                ->where('status', 'done')
                ->exists();

            if ($existingDoneRefund) {
                throw new \RuntimeException('Este ticket ya tiene una devolución registrada.');
            }

            $lines = \Illuminate\Support\Facades\DB::table('pos_order_lines')
                ->where('pos_order_id', $order->id)
                ->orderBy('id')
                ->get();

            if ($lines->isEmpty()) {
                throw new \RuntimeException('El ticket no tiene líneas para devolver.');
            }

            $selectedLines = collect();
            $subtotal = 0.0;
            $taxTotal = 0.0;
            $total = 0.0;

            foreach ($lines as $line) {
                $lineId = (int) ($line->id ?? 0);
                $key = 'line_' . $lineId;

                $refundQty = round((float) ($quantities[$key] ?? 0), 6);
                $originalQty = round((float) ($line->quantity ?? 0), 6);

                if ($refundQty <= 0) {
                    continue;
                }

                if ($refundQty > $originalQty) {
                    throw new \RuntimeException('La cantidad a devolver de "' . ($line->product_name ?? ('línea #' . $lineId)) . '" no puede ser mayor a la cantidad vendida.');
                }

                $ratio = $originalQty > 0 ? ($refundQty / $originalQty) : 0;

                $unitPrice = round((float) ($line->unit_price ?? 0), 6);
                $lineSubtotal = round((float) ($line->subtotal ?? 0) * $ratio, 4);
                $lineTaxTotal = round((float) ($line->tax_total ?? 0) * $ratio, 4);
                $lineTotal = round((float) ($line->total ?? ($originalQty * $unitPrice)) * $ratio, 4);

                if ($lineTotal <= 0 && $refundQty > 0 && $unitPrice > 0) {
                    $lineTotal = round($refundQty * $unitPrice, 4);
                }

                if ($lineSubtotal <= 0) {
                    $taxRate = round((float) ($line->tax_rate ?? 0), 4);
                    $lineSubtotal = $taxRate > 0
                        ? round($lineTotal / (1 + ($taxRate / 100)), 4)
                        : $lineTotal;
                    $lineTaxTotal = round($lineTotal - $lineSubtotal, 4);
                }

                $subtotal += $lineSubtotal;
                $taxTotal += $lineTaxTotal;
                $total += $lineTotal;

                $selectedLines->push([
                    'line' => $line,
                    'quantity' => $refundQty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineSubtotal,
                    'tax_total' => $lineTaxTotal,
                    'total' => $lineTotal,
                ]);
            }

            if ($selectedLines->isEmpty()) {
                throw new \RuntimeException('Captura al menos una cantidad a devolver.');
            }

            $subtotal = round($subtotal, 4);
            $taxTotal = round($taxTotal, 4);
            $total = round($total, 4);
            $paymentTotal = $total;

            if ($total <= 0) {
                throw new \RuntimeException('El total de la devolución parcial debe ser mayor a cero.');
            }

            $payments = \Illuminate\Support\Facades\Schema::hasTable('pos_order_payments')
                ? \Illuminate\Support\Facades\DB::table('pos_order_payments')
                    ->where('pos_order_id', $order->id)
                    ->orderBy('id')
                    ->get()
                : collect();

            $paymentLabel = (string) ($payments->first()->payment_label ?? 'No especificado');
            $paymentFormId = $payments->first()->payment_form_id ?? null;

            $refundNumber = static::v5506bNextRefundNumber($order);
            $orderMetadata = static::v5506bMetadataArray($order);

            $refundId = \Illuminate\Support\Facades\DB::table('pos_order_refunds')->insertGetId([
                'company_id' => $order->company_id ?? null,
                'pos_order_id' => $order->id,
                'pos_session_id' => $order->pos_session_id ?? null,
                'pos_point_id' => $order->pos_point_id ?? null,
                'customer_id' => $order->customer_id ?? null,
                'number' => $refundNumber,
                'type' => 'partial',
                'status' => 'done',
                'reason' => $reason,
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $total,
                'payment_total' => $paymentTotal,
                'stock_movement_id' => null,
                'created_by_user_id' => auth()->id(),
                'refunded_at' => now(),
                'metadata' => static::v5506bJson([
                    'source' => 'filament_pos_ticket_partial_refund',
                    'inventory_return_status' => 'pending',
                    'original_order_number' => $order->number ?? null,
                    'original_order_status' => $order->status ?? null,
                    'original_stock_movement_id' => $orderMetadata['stock_movement_id'] ?? null,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($selectedLines as $item) {
                $line = $item['line'];

                \Illuminate\Support\Facades\DB::table('pos_order_refund_lines')->insert([
                    'pos_order_refund_id' => $refundId,
                    'pos_order_id' => $order->id,
                    'pos_order_line_id' => $line->id ?? null,
                    'product_id' => $line->product_id ?? null,
                    'product_variant_id' => $line->product_variant_id ?? null,
                    'product_name' => $line->product_name ?? ($line->name ?? null),
                    'product_reference' => $line->product_reference ?? ($line->reference ?? null),
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $line->tax_rate ?? 0,
                    'subtotal' => $item['subtotal'],
                    'tax_total' => $item['tax_total'],
                    'total' => $item['total'],
                    'metadata' => static::v5506bJson([
                        'source_line_id' => $line->id ?? null,
                        'partial' => true,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            \Illuminate\Support\Facades\DB::table('pos_order_refund_payments')->insert([
                'pos_order_refund_id' => $refundId,
                'pos_order_id' => $order->id,
                'payment_form_id' => $paymentFormId,
                'payment_label' => $paymentLabel !== '' ? $paymentLabel : 'No especificado',
                'amount' => $paymentTotal,
                'status' => 'refunded',
                'metadata' => static::v5506bJson([
                    'source' => 'partial_refund_single_payment',
                    'original_payment_count' => $payments->count(),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $orderMetadata['refund_status'] = 'partial_refunded';
            $orderMetadata['refund_id'] = $refundId;
            $orderMetadata['refund_number'] = $refundNumber;
            $orderMetadata['refunded_at'] = now()->toDateTimeString();
            $orderMetadata['refunded_by_user_id'] = auth()->id();
            $orderMetadata['refund_reason'] = $reason;
            $orderMetadata['refund_type'] = 'partial';
            $orderMetadata['refund_total'] = $paymentTotal;
            $orderMetadata['inventory_return_status'] = 'pending';

            \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('id', $order->id)
                ->update([
                    'metadata' => static::v5506bJson($orderMetadata),
                    'updated_at' => now(),
                ]);

                        static::v5515aWritePosAuditLog('pos.refund.partial.success', [
                'pos_order_id' => (int) ($record->id ?? 0),
                'pos_session_id' => (int) ($record->pos_session_id ?? 0),
                'pos_order_refund_id' => (int) $refundId,
                'entity_type' => 'pos_order_refund',
                'entity_id' => (int) $refundId,
                'description' => 'Devolución parcial de ticket PDV registrada.',
                'after_data' => [
                    'refund_id' => (int) $refundId,
                    'refund_number' => $refundNumber ?? null,
                    'refund_total' => $paymentTotal ?? null,
                ],
                'metadata' => [
                    'reason' => $reason,
                    'order_id' => (int) ($record->id ?? 0),
                    'order_number' => $record->number ?? null,
                    'quantities' => $quantities,
                ],
            ]);

return (int) $refundId;
        });
    }


    public static function v5506gCanPostRefundInventory(): bool
    {
        return static::v5513cCanRefundCreate();
    }


    public static function v5506gMetadataArray(?object $row): array
    {
        if (! $row || ! isset($row->metadata)) {
            return [];
        }

        if (is_array($row->metadata)) {
            return $row->metadata;
        }

        if (is_object($row->metadata)) {
            return json_decode(json_encode($row->metadata), true) ?: [];
        }

        if ($row->metadata === null || $row->metadata === '') {
            return [];
        }

        $decoded = json_decode((string) $row->metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function v5506gJson(?array $data): ?string
    {
        return $data === null
            ? null
            : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function v5506gRefundForOrder(int $orderId): ?object
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')) {
            return null;
        }

        return \Illuminate\Support\Facades\DB::table('pos_order_refunds')
            ->where('pos_order_id', $orderId)
            ->where('status', 'done')
            ->orderByDesc('id')
            ->first();
    }

    public static function v5506gShouldShowInventoryReturn(object $record): bool
    {
        if (! static::v5506gCanPostRefundInventory()) {
            return false;
        }

        if (! in_array((string) ($record->status ?? ''), ['paid', 'returned'], true)) {
            return false;
        }

        $metadata = static::v5506gMetadataArray($record);

        if (! in_array(($metadata['refund_status'] ?? null), ['total_refunded', 'partial_refunded'], true)) {
            return false;
        }

        if (($metadata['inventory_return_status'] ?? null) === 'done') {
            return false;
        }

        return (bool) static::v5506gRefundForOrder((int) $record->id);
    }




    public static function v5506kCustomerLocationId(int $companyId): ?int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('stock_locations')) {
            return null;
        }

        $query = \Illuminate\Support\Facades\DB::table('stock_locations')
            ->where('is_active', true)
            ->where(function ($q) use ($companyId): void {
                $q->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            })
            ->where(function ($q): void {
                $q->where('code', 'CLIENTES')
                    ->orWhere('name', 'like', '%Cliente%')
                    ->orWhere('name', 'like', '%Clientes%');
            });

        return $query
            ->orderByRaw('company_id IS NULL')
            ->orderBy('id')
            ->value('id');
    }

    public static function v5506gReturnOperationType(int $companyId, int $warehouseId, int $destinationLocationId, ?int $sourceLocationId = null): object
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('stock_operation_types')) {
            throw new \RuntimeException('No existe la tabla stock_operation_types.');
        }

        $operation = \Illuminate\Support\Facades\DB::table('stock_operation_types')
            ->where('code', 'DEV_PDV')
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')->orWhere('company_id', $companyId);
            })
            ->where(function ($q) use ($warehouseId) {
                $q->whereNull('warehouse_id')->orWhere('warehouse_id', $warehouseId);
            })
            ->orderByRaw('company_id IS NULL')
            ->orderByRaw('warehouse_id IS NULL')
            ->lockForUpdate()
            ->first();

        if ($operation) {
            \Illuminate\Support\Facades\DB::table('stock_operation_types')
                ->where('id', $operation->id)
                ->update([
                    'name' => 'Entrada por devolución',
                    'operation_kind' => 'receipt',
                    'source_location_id' => $sourceLocationId ?: ($operation->source_location_id ?? null),
                    'destination_location_id' => $destinationLocationId ?: ($operation->destination_location_id ?? null),
                    'reference_prefix' => 'DEV',
                    'is_active' => true,
                    'updated_at' => now(),
                ]);

            return \Illuminate\Support\Facades\DB::table('stock_operation_types')
                ->where('id', $operation->id)
                ->lockForUpdate()
                ->first();
        }

        $id = \Illuminate\Support\Facades\DB::table('stock_operation_types')->insertGetId([
            'company_id' => $companyId ?: null,
            'warehouse_id' => $warehouseId ?: null,
            'code' => 'DEV_PDV',
            'name' => 'Entrada por devolución',
            'operation_kind' => 'receipt',
            'source_location_id' => $sourceLocationId ?: null,
            'destination_location_id' => $destinationLocationId ?: null,
            'reference_prefix' => 'DEV',
            'next_number' => 1,
            'sequence' => 90,
            'description' => 'Entrada automática de inventario por devolución total de ticket PDV.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return \Illuminate\Support\Facades\DB::table('stock_operation_types')
            ->where('id', $id)
            ->lockForUpdate()
            ->first();
    }


    public static function v5506gNextStockReference(object $operation, int $warehouseId, int $destinationLocationId): string
    {
        $locationCode = \Illuminate\Support\Facades\Schema::hasTable('stock_locations')
            ? \Illuminate\Support\Facades\DB::table('stock_locations')->where('id', $destinationLocationId)->value('code')
            : null;

        $warehouseCode = \Illuminate\Support\Facades\Schema::hasTable('warehouses')
            ? \Illuminate\Support\Facades\DB::table('warehouses')->where('id', $warehouseId)->value('code')
            : null;

        $baseCode = trim((string) ($locationCode ?: $warehouseCode ?: 'PDV'));
        $prefix = $baseCode . '/DEV/';

        $lastReference = \Illuminate\Support\Facades\DB::table('stock_movements')
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('reference')
            ->value('reference');

        $next = 1;

        if ($lastReference && preg_match('/\/DEV\/(\d+)$/', (string) $lastReference, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        do {
            $reference = $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);

            $exists = \Illuminate\Support\Facades\DB::table('stock_movements')
                ->where('reference', $reference)
                ->exists();

            $next++;
        } while ($exists);

        \Illuminate\Support\Facades\DB::table('stock_operation_types')
            ->where('id', $operation->id)
            ->update([
                'next_number' => $next,
                'reference_prefix' => 'DEV',
                'updated_at' => now(),
            ]);

        return $reference;
    }

    public static function v5506gIncrementQuant(
        int $companyId,
        int $warehouseId,
        int $locationId,
        int $productId,
        ?int $productVariantId,
        float $quantity
    ): void {
        if ($quantity <= 0 || $productId <= 0) {
            return;
        }

        $query = \Illuminate\Support\Facades\DB::table('stock_quants')
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId);

        if ($productVariantId) {
            $query->where('product_variant_id', $productVariantId);
        } else {
            $query->whereNull('product_variant_id');
        }

        $query->whereNull('lot_id');

        $quant = $query->lockForUpdate()->first();

        if ($quant) {
            \Illuminate\Support\Facades\DB::table('stock_quants')
                ->where('id', $quant->id)
                ->update([
                    'quantity' => round(((float) $quant->quantity) + $quantity, 6),
                    'updated_at' => now(),
                ]);

            return;
        }

        \Illuminate\Support\Facades\DB::table('stock_quants')->insert([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'lot_id' => null,
            'quantity' => round($quantity, 6),
            'reserved_quantity' => 0,
            'average_cost' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function v5506gPostRefundInventory(object $record): int
    {
        foreach ([
            'pos_order_refunds',
            'pos_order_refund_lines',
            'pos_orders',
            'pos_points',
            'stock_movements',
            'stock_movement_lines',
            'stock_quants',
        ] as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                throw new \RuntimeException('No existe la tabla requerida: ' . $table);
            }
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($record): int {
            $order = \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('id', (int) $record->id)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new \RuntimeException('No se encontró el ticket.');
            }

            if (! in_array((string) ($order->status ?? ''), ['paid', 'returned'], true)) {
                throw new \RuntimeException('Solo se puede regresar inventario de tickets pagados o devueltos con devolución registrada.');
            }

            $orderMetadata = static::v5506gMetadataArray($order);

            if (($orderMetadata['inventory_return_status'] ?? null) === 'done') {
                if (! empty($orderMetadata['inventory_return_stock_movement_id'])) {
                    return (int) $orderMetadata['inventory_return_stock_movement_id'];
                }

                throw new \RuntimeException('La entrada de inventario ya está marcada como realizada.');
            }

            $refund = static::v5506gRefundForOrder((int) $order->id);

            if (! $refund) {
                throw new \RuntimeException('No se encontró la devolución del ticket.');
            }

            $refundMetadata = static::v5506gMetadataArray($refund);

            if (($refundMetadata['inventory_return_status'] ?? null) === 'done' && ! empty($refund->stock_movement_id)) {
                return (int) $refund->stock_movement_id;
            }

            $pos = null;

            if (! empty($order->pos_point_id)) {
                $pos = \Illuminate\Support\Facades\DB::table('pos_points')
                    ->where('id', (int) $order->pos_point_id)
                    ->first();
            }

            $companyId = (int) ($order->company_id ?? $pos->company_id ?? 0);
            $warehouseId = (int) ($pos->warehouse_id ?? 0);
            $destinationLocationId = (int) ($pos->stock_source_location_id ?? $pos->stock_location_id ?? 0);

            if ($companyId <= 0 || $warehouseId <= 0 || $destinationLocationId <= 0) {
                throw new \RuntimeException('Falta configurar empresa, almacén o ubicación de stock del PDV.');
            }

            $customerLocationId = static::v5506kCustomerLocationId($companyId);

            $refundLines = \Illuminate\Support\Facades\DB::table('pos_order_refund_lines')
                ->where('pos_order_refund_id', $refund->id)
                ->orderBy('id')
                ->get();

            if ($refundLines->isEmpty()) {
                throw new \RuntimeException('La devolución no tiene líneas para regresar a inventario.');
            }

            $stockableLines = collect();

            foreach ($refundLines as $line) {
                $productId = (int) ($line->product_id ?? 0);
                $qty = round((float) ($line->quantity ?? 0), 6);

                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }

                $product = \Illuminate\Support\Facades\DB::table('products')
                    ->where('id', $productId)
                    ->first();

                $productType = (string) ($product->product_type ?? 'stockable');

                if ($productType === 'service') {
                    continue;
                }

                $stockableLines->push([
                    'line' => $line,
                    'product' => $product,
                    'quantity' => $qty,
                    'product_id' => $productId,
                    'product_variant_id' => isset($line->product_variant_id) && $line->product_variant_id
                        ? (int) $line->product_variant_id
                        : null,
                ]);
            }

            if ($stockableLines->isEmpty()) {
                $orderMetadata['inventory_return_status'] = 'skipped_no_stockable_lines';
                $orderMetadata['inventory_return_message'] = 'No había líneas inventariables en la devolución.';

                $refundMetadata['inventory_return_status'] = 'skipped_no_stockable_lines';
                $refundMetadata['inventory_return_message'] = 'No había líneas inventariables en la devolución.';

                \Illuminate\Support\Facades\DB::table('pos_orders')
                    ->where('id', $order->id)
                    ->update([
                        'metadata' => static::v5506gJson($orderMetadata),
                        'updated_at' => now(),
                    ]);

                \Illuminate\Support\Facades\DB::table('pos_order_refunds')
                    ->where('id', $refund->id)
                    ->update([
                        'metadata' => static::v5506gJson($refundMetadata),
                        'updated_at' => now(),
                    ]);

                return 0;
            }

            $operation = static::v5506gReturnOperationType($companyId, $warehouseId, $destinationLocationId, $customerLocationId);
            $reference = static::v5506gNextStockReference($operation, $warehouseId, $destinationLocationId);

            $movementId = \Illuminate\Support\Facades\DB::table('stock_movements')->insertGetId([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'stock_operation_type_id' => $operation->id,
                'source_location_id' => $customerLocationId ?: ($operation->source_location_id ?? null),
                'destination_location_id' => $destinationLocationId,
                'reference' => $reference,
                'movement_at' => now(),
                'status' => 'done',
                'origin_document' => (string) ($refund->number ?? ('DEV-' . $refund->id)),
                'contact_id' => $order->customer_id ?? null,
                'notes' => 'Entrada por devolución PDV. Ticket: ' . ($order->number ?? $order->id) . '. Devolución: ' . ($refund->number ?? $refund->id),
                'created_by' => auth()->id(),
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($stockableLines as $item) {
                $line = $item['line'];
                $qty = (float) $item['quantity'];
                $product = $item['product'];

                \Illuminate\Support\Facades\DB::table('stock_movement_lines')->insert([
                    'stock_movement_id' => $movementId,
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'lot_id' => null,
                    'requested_quantity' => $qty,
                    'done_quantity' => $qty,
                    'unit_cost' => $product->average_cost_without_tax ?? $product->standard_cost ?? null,
                    'notes' => trim((string) ($line->product_name ?? $product->name ?? ('Producto #' . $item['product_id']))),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                static::v5506gIncrementQuant(
                    $companyId,
                    $warehouseId,
                    $destinationLocationId,
                    $item['product_id'],
                    $item['product_variant_id'],
                    $qty
                );
            }

            $orderMetadata['inventory_return_status'] = 'done';
            $orderMetadata['inventory_return_stock_movement_id'] = $movementId;
            $orderMetadata['inventory_return_reference'] = $reference;
            $orderMetadata['inventory_return_at'] = now()->toDateTimeString();
            $orderMetadata['inventory_return_by_user_id'] = auth()->id();

            $refundMetadata['inventory_return_status'] = 'done';
            $refundMetadata['stock_movement_id'] = $movementId;
            $refundMetadata['inventory_return_reference'] = $reference;
            $refundMetadata['inventory_return_at'] = now()->toDateTimeString();
            $refundMetadata['inventory_return_by_user_id'] = auth()->id();

            \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('id', $order->id)
                ->update([
                    'metadata' => static::v5506gJson($orderMetadata),
                    'updated_at' => now(),
                ]);

            \Illuminate\Support\Facades\DB::table('pos_order_refunds')
                ->where('id', $refund->id)
                ->update([
                    'stock_movement_id' => $movementId,
                    'metadata' => static::v5506gJson($refundMetadata),
                    'updated_at' => now(),
                ]);

            return (int) $movementId;
        });
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosTickets::route('/'),
            'view' => Pages\ViewPosTicket::route('/{record}'),
            'inventory' => Pages\ViewPosTicketInventory::route('/{record}/inventory-output'),
            'inventory-print' => Pages\PrintPosTicketInventory::route('/{record}/inventory-output/print'),
        ];
    }

    public static function orderLines(int $orderId)
    {
        if (! Schema::hasTable('pos_order_lines')) {
            return collect();
        }

        return DB::table('pos_order_lines')
            ->where('pos_order_id', $orderId)
            ->orderBy('id')
            ->get();
    }

    public static function orderPayments(int $orderId)
    {
        if (! Schema::hasTable('pos_order_payments')) {
            return collect();
        }

        return DB::table('pos_order_payments')
            ->where('pos_order_id', $orderId)
            ->orderBy('id')
            ->get();
    }

    public static function stockMovementForOrder(object $record): ?object
    {
        $metadata = static::metadataArray($record);
        $movementId = (int) ($metadata['stock_movement_id'] ?? 0);

        if ($movementId <= 0 || ! Schema::hasTable('stock_movements')) {
            return null;
        }

        return DB::table('stock_movements')->where('id', $movementId)->first();
    }

    public static function stockMovementPrintUrl(object $record): string
    {
        $metadata = static::metadataArray($record);
        $movementId = (int) ($metadata['stock_movement_id'] ?? 0);

        if ($movementId <= 0) {
            return '#';
        }

        return route('pos.tickets.inventory-output.pdf', ['order' => $record->id]);
    }

    public static function stockMovementUrl(object $record): string
    {
        $metadata = static::metadataArray($record);
        $movementId = (int) ($metadata['stock_movement_id'] ?? 0);

        if ($movementId <= 0) {
            return '#';
        }

        return static::getUrl('inventory', ['record' => $record]);
    }

    public static function pendingPrintUrl(object $record): string
    {
        return route('pos.orders.pending-ticket.print', ['order' => $record->id]);
    }

    public static function receiptPrintUrl(object $record): string
    {
        return route('pos.orders.receipt.print', ['order' => $record->id]);
    }

    public static function invoicePortalUrl(object $record): string
    {
        return url('/facturar') . '?' . http_build_query(['ticket' => $record->number]);
    }


    public static function individualInvoiceForTicket(object $record): ?object
    {
        /*
         * BEXIA_V5527B_POS_TICKET_FISCAL_STATE_HELPERS
         */
        if (! Schema::hasTable('invoices')) {
            return null;
        }

        return DB::table('invoices')
            ->where('source_type', 'pos_order')
            ->where('source_id', (int) ($record->id ?? 0))
            ->orderByDesc('id')
            ->first();
    }

    public static function globalInvoiceLinkForTicket(object $record): ?object
    {
        if (Schema::hasTable('global_invoice_tickets')) {
            $link = DB::table('global_invoice_tickets')
                ->where('pos_order_id', (int) ($record->id ?? 0))
                ->orderByDesc('id')
                ->first();

            if ($link) {
                return $link;
            }
        }

        if (! empty($record->global_invoice_id)) {
            return (object) [
                'invoice_id' => (int) $record->global_invoice_id,
                'pos_order_id' => (int) ($record->id ?? 0),
                'status' => null,
            ];
        }

        return null;
    }

    public static function globalInvoiceForTicket(object $record): ?object
    {
        if (! Schema::hasTable('invoices')) {
            return null;
        }

        $link = static::globalInvoiceLinkForTicket($record);

        if ($link && ! empty($link->invoice_id)) {
            return DB::table('invoices')
                ->where('id', (int) $link->invoice_id)
                ->first();
        }

        return null;
    }

    public static function fiscalStatus(object $record): string
    {
        $metadata = static::metadataArray($record);
        $status = (string) ($record->status ?? '');

        $individual = static::individualInvoiceForTicket($record);

        if ($individual) {
            $cfdiStatus = (string) ($individual->cfdi_status ?? '');
            $invoiceStatus = (string) ($individual->status ?? '');

            if (in_array($cfdiStatus, ['stamped'], true)) {
                return 'individual_stamped';
            }

            if (in_array($cfdiStatus, ['cancelled', 'cancel_requested'], true) || $invoiceStatus === 'cancelled') {
                return 'individual_cancelled';
            }

            if (in_array($cfdiStatus, ['stamp_error', 'validation_error'], true)) {
                return 'individual_error';
            }

            return 'individual_draft';
        }

        $globalLink = static::globalInvoiceLinkForTicket($record);
        $globalInvoice = static::globalInvoiceForTicket($record);

        if ($globalLink || $globalInvoice || ! empty($record->global_invoice_id)) {
            $linkStatus = (string) ($globalLink->status ?? '');
            $cfdiStatus = (string) ($globalInvoice->cfdi_status ?? '');
            $invoiceStatus = (string) ($globalInvoice->status ?? '');

            if ($linkStatus === 'cancelled' || in_array($cfdiStatus, ['cancelled', 'cancelled_internal'], true) || $invoiceStatus === 'cancelled') {
                return 'global_cancelled_released';
            }

            if ($cfdiStatus === 'stamped' || $linkStatus === 'stamped') {
                return 'global_stamped';
            }

            return 'global_draft';
        }

        if (in_array($status, ['cancelled', 'canceled', 'cancelled_test'], true)) {
            return 'not_billable_cancelled';
        }

        if ($status === 'returned'
            || in_array((string) ($metadata['refund_status'] ?? ''), ['total_refunded', 'partial_refunded'], true)) {
            return 'not_billable_refunded';
        }

        if ($status === 'pending_payment') {
            return 'pending_payment';
        }

        if ((string) ($metadata['billing_status'] ?? '') === 'requested') {
            return 'billing_requested';
        }

        if ($status === 'paid') {
            return 'not_invoiced';
        }

        return 'unknown';
    }

    public static function fiscalStatusLabel(string $status): string
    {
        return match ($status) {
            'not_invoiced' => 'Sin facturar',
            'billing_requested' => 'Solicitada',
            'individual_draft' => 'Factura individual borrador',
            'individual_stamped' => 'Factura individual timbrada',
            'individual_error' => 'Factura individual con error',
            'individual_cancelled' => 'Factura individual cancelada',
            'global_draft' => 'En factura global borrador',
            'global_stamped' => 'Factura global timbrada',
            'global_cancelled_released' => 'Liberado por global cancelada',
            'pending_payment' => 'Pendiente de cobro',
            'not_billable_cancelled' => 'No facturable: cancelado',
            'not_billable_refunded' => 'No facturable: devolución',
            default => $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Sin estado fiscal',
        };
    }

    public static function fiscalStatusColor(string $status): string
    {
        return match ($status) {
            'not_invoiced', 'billing_requested' => 'warning',
            'individual_draft', 'global_draft' => 'info',
            'individual_stamped', 'global_stamped' => 'success',
            'global_cancelled_released' => 'gray',
            'individual_error' => 'danger',
            'individual_cancelled', 'not_billable_cancelled', 'not_billable_refunded' => 'danger',
            'pending_payment' => 'gray',
            default => 'gray',
        };
    }

    public static function fiscalStatusDescription(object $record): ?string
    {
        $status = static::fiscalStatus($record);

        if (in_array($status, ['individual_draft', 'individual_stamped', 'individual_error', 'individual_cancelled'], true)) {
            $invoice = static::individualInvoiceForTicket($record);

            return $invoice
                ? 'Factura: '.((string) ($invoice->number ?? ('#'.$invoice->id)))
                : null;
        }

        if (in_array($status, ['global_draft', 'global_stamped', 'global_cancelled_released'], true)) {
            $invoice = static::globalInvoiceForTicket($record);

            return $invoice
                ? 'Global: '.((string) ($invoice->number ?? ('#'.$invoice->id)))
                : null;
        }

        return null;
    }

    public static function canCreateIndividualInvoiceFromTicket(object $record): bool
    {
        $status = static::fiscalStatus($record);

        return $status === 'not_invoiced'
            && (string) ($record->status ?? '') === 'paid';
    }



    public static function fiscalInvoiceForTicket(object $record): ?object
    {
        /*
         * BEXIA_V5527C_POS_TICKET_REFUND_AND_INVOICE_NAV_HELPERS
         * Regresa la factura ligada al ticket: primero individual, luego global.
         */
        $individual = static::individualInvoiceForTicket($record);

        if ($individual) {
            return $individual;
        }

        return static::globalInvoiceForTicket($record);
    }

    public static function fiscalInvoiceUrl(object $record): string
    {
        $invoice = static::fiscalInvoiceForTicket($record);

        if (! $invoice || empty($invoice->id)) {
            return '#';
        }

        return \App\Filament\Resources\InvoiceResource::getUrl('view', ['record' => (int) $invoice->id]);
    }

    public static function canRefundTicket(object $record): bool
    {
        if ((string) ($record->status ?? '') !== 'paid') {
            return false;
        }

        if (! static::v5506bCanCreateRefund()) {
            return false;
        }

        if (static::v5509dHasDoneRefund($record)) {
            return false;
        }

        /*
         * Bloquea devolución si el ticket está facturado o dentro de una factura global activa.
         * Se permite si no está facturado, si solo está solicitado, o si fue liberado por cancelación.
         */
        return in_array(static::fiscalStatus($record), [
            'not_invoiced',
            'billing_requested',
            'global_cancelled_released',
            'individual_cancelled',
        ], true);
    }


    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending_payment' => 'Pendiente de cobro',
            'paid' => 'Pagado',
            'cancelled', 'canceled' => 'Cancelado',
            'returned' => 'Devuelto',
            'refunded' => 'Reembolsado',
            default => $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Sin estado',
        };
    }

    public static function inventoryStatus(object $record): string
    {
        $metadata = static::metadataArray($record);

        return (string) ($metadata['inventory_status'] ?? 'pending');
    }

    public static function inventoryStatusLabel(string $status): string
    {
        return match ($status) {
            'delivered' => 'Salida generada',
            'no_stockable_products' => 'Sin salida',
            'pending_configuration' => 'Config. pendiente',
            'pending_no_quant' => 'Sin existencia',
            'pending_insufficient_stock' => 'Stock insuficiente',
            'pending_error' => 'Error pendiente',
            'pending_payment' => 'Pendiente de cobro',
            'pending' => 'Pendiente',
            default => $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Pendiente',
        };
    }

    public static function billingStatus(object $record): string
    {
        $metadata = static::metadataArray($record);

        return (string) ($metadata['billing_status'] ?? 'pending');
    }

    public static function billingStatusLabel(string $status): string
    {
        return match ($status) {
            'requested' => 'Solicitado',
            'invoiced' => 'Facturado',
            'not_required' => 'No requerido',
            'pending' => 'Pendiente',
            default => $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Pendiente',
        };
    }

    public static function metadataArray(object $record): array
    {
        $metadata = $record->metadata ?? [];

        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && trim($metadata) !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    public static function paymentCount(int $orderId): int
    {
        if (! Schema::hasTable('pos_order_payments')) {
            return 0;
        }

        return DB::table('pos_order_payments')
            ->where('pos_order_id', $orderId)
            ->count();
    }

    public static function customerLabel(mixed $id): string
    {
        if (! $id || ! Schema::hasTable('contacts')) {
            return 'Público en General';
        }

        $row = DB::table('contacts')->where('id', $id)->first();

        if (! $row) {
            return 'Público en General';
        }

        foreach (['commercial_name', 'fiscal_name', 'name'] as $column) {
            if (isset($row->{$column}) && trim((string) $row->{$column}) !== '') {
                return trim((string) $row->{$column});
            }
        }

        return '#' . $id;
    }

    public static function labelFromTable(string $table, mixed $id, array $labelColumns): string
    {
        if (! $id || ! Schema::hasTable($table)) {
            return '—';
        }

        $row = DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '—';
        }

        $parts = [];

        foreach ($labelColumns as $column) {
            if (isset($row->{$column}) && trim((string) $row->{$column}) !== '') {
                $parts[] = trim((string) $row->{$column});
            }
        }

        return $parts ? implode(' - ', $parts) : ('#' . $id);
    }

    protected static function currentCompanyId(): ?int
    {
        try {
            $tenant = Filament::getTenant();

            if (is_object($tenant) && method_exists($tenant, 'getKey')) {
                return (int) $tenant->getKey();
            }

            if (is_numeric($tenant)) {
                return (int) $tenant;
            }
        } catch (\Throwable $e) {
            //
        }

        $tenant = request()->route('tenant');

        if (is_object($tenant) && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        if (is_numeric($tenant)) {
            return (int) $tenant;
        }

        return auth()->user()?->company_id ? (int) auth()->user()->company_id : null;
    }
}
