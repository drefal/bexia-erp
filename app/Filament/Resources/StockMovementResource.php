<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use App\Models\StockMovementLine;
use App\Models\StockQuant;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Transacciones de almacén';

    protected static ?string $modelLabel = 'transacción de almacén';

    protected static ?string $pluralModelLabel = 'transacciones de almacén';

    protected static ?int $navigationSort = 80;

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        $query = StockMovement::query()
            ->with(['operationType', 'warehouse', 'sourceLocation', 'destinationLocation'])
            ->withCount('lines');

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where('company_id', $companyId);
        } else {
            $query->whereNull('company_id');
        }

        return $query;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.stockmovementresource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('inventory.menu.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('inventory.menu.view')
            );
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => static::currentCompanyId()),

                Forms\Components\Section::make('Traslado')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label('Referencia')
                            ->placeholder('Se genera automáticamente al guardar')
                            ->helperText('Formato: UBICACION/PREFIJO/000001.')
                            ->readOnly()
                            ->dehydrated(true)
                            ->columnSpan(3),

                        Forms\Components\DateTimePicker::make('movement_at')
                            ->label('Fecha y hora')
                            ->default(now())
                            ->required()
                            ->seconds(false)
                            ->displayFormat('d/m/Y H:i')
                            ->disabled(fn (Forms\Get $get): bool => static::movementIsDoneFromForm($get))
                            ->columnSpan(3),

                        Forms\Components\Select::make('stock_operation_type_id')
                            ->label('Tipo de operación')
                            ->options(fn (): array => static::operationTypeOptions())
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state): void {
                                $operation = static::operationTypeRow($state);

                                if (! $operation) {
                                    return;
                                }

                                $set('warehouse_id', $operation->warehouse_id);
                                $set('source_location_id', $operation->source_location_id);
                                $set('destination_location_id', $operation->destination_location_id);
                                $set('reference', null);
                            })
                            ->disabled(fn (Forms\Get $get): bool => static::movementIsDoneFromForm($get))
                            ->columnSpan(3),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'draft' => 'Borrador',
                                'done' => 'Hecho',
                                'cancelled' => 'Cancelado',
                            ])
                            ->default('draft')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(3),

                        Forms\Components\Select::make('warehouse_id')
                            ->label('Almacén')
                            ->options(fn (): array => static::warehouseOptions())
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set): void {
                                $set('source_location_id', null);
                                $set('destination_location_id', null);
                                $set('reference', null);
                            })
                            ->disabled(fn (Forms\Get $get): bool => static::movementIsDoneFromForm($get))
                            ->columnSpan(4),

                        Forms\Components\Select::make('source_location_id')
                            ->label('Ubicación origen')
                            ->options(fn (Forms\Get $get): array => static::locationOptions($get('warehouse_id')))
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set): null => $set('reference', null))
                            ->disabled(fn (Forms\Get $get): bool => static::movementIsDoneFromForm($get))
                            ->columnSpan(4),

                        Forms\Components\Select::make('destination_location_id')
                            ->label('Ubicación destino')
                            ->options(fn (Forms\Get $get): array => static::locationOptions($get('warehouse_id')))
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set): null => $set('reference', null))
                            ->disabled(fn (Forms\Get $get): bool => static::movementIsDoneFromForm($get))
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('origin_document')
                            ->label('Documento de origen')
                            ->placeholder('Ej. OC-0001, Pedido, Ticket, referencia externa')
                            ->maxLength(180)
                            ->disabled(fn (Forms\Get $get): bool => static::movementIsDoneFromForm($get))
                            ->columnSpan(6),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(2)
                            ->disabled(fn (Forms\Get $get): bool => static::movementIsDoneFromForm($get))
                            ->columnSpanFull(),
                    ])
                    ->columns(12),

                Forms\Components\Section::make('Productos')
                    ->description('Captura los productos que se trasladarán. Al confirmar, Bexia actualizará las existencias.')
                    ->schema([
                        Forms\Components\Repeater::make('lines')
                            ->label('Líneas')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Producto')
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search): array => static::productSearchOptions($search))
                                    ->getOptionLabelUsing(fn ($value): ?string => static::productLabel($value))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set): void {
                                        $set('product_variant_id', null);
                                    })
                                    ->disabled(fn (Forms\Get $get): bool => static::movementIsDoneFromForm($get))
                                    ->columnSpan(4),

                                Forms\Components\Select::make('product_variant_id')
                                    ->label('Variante')
                                    ->options(fn (Forms\Get $get): array => static::variantOptions($get('product_id')))
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->placeholder('Sin variante')
                                    ->disabled(fn (Forms\Get $get): bool => static::movementIsDoneFromForm($get))
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('done_quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(0.000001)
                                    ->default(1)
                                    ->required()
                                    ->disabled(fn (Forms\Get $get): bool => static::movementIsDoneFromForm($get))
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('unit_cost')
                                    ->label('Costo unit.')
                                    ->numeric()
                                    ->prefix('$')
                                    ->helperText('Si se deja vacío, se tomará el costo promedio o costo del producto.')
                                    ->disabled(fn (Forms\Get $get): bool => static::movementIsDoneFromForm($get))
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('source_stock')
                                    ->label('Stock origen')
                                    ->content(fn (Forms\Get $get): string => static::sourceQuantityLabelFromForm($get))
                                    ->columnSpan(2),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Notas')
                                    ->rows(1)
                                    ->disabled(fn (Forms\Get $get): bool => static::movementIsDoneFromForm($get))
                                    ->columnSpanFull(),
                            ])
                            ->columns(12)
                            ->defaultItems(1)
                            ->addActionLabel('Agregar producto')
                            ->addable(fn (Forms\Get $get): bool => ! static::movementIsDoneFromForm($get))
                            ->deletable(fn (Forms\Get $get): bool => ! static::movementIsDoneFromForm($get))
                            ->reorderable(false)
                            ->disabled(fn (Forms\Get $get): bool => static::movementIsDoneFromForm($get))
                            ->columnSpanFull(),
                    ]),
            ]);
    }


    protected static function stockMovementReferenceLabel(?string $reference): string
    {
        $reference = trim((string) $reference);

        return $reference !== '' ? $reference : '—';
    }

    protected static function stockMovementOriginLabel(?string $originDocument): string
    {
        $originDocument = trim((string) $originDocument);

        if ($originDocument === '') {
            return '—';
        }

        if (str_starts_with($originDocument, 'exit:')) {
            $folio = trim(substr($originDocument, strlen('exit:')));

            return $folio !== '' ? $folio : 'Salida';
        }


        if (str_starts_with($originDocument, 'sale_delivery:')) {
            $deliveryKey = trim(substr($originDocument, strlen('sale_delivery:')));

            if ($deliveryKey !== '' && \Illuminate\Support\Facades\Schema::hasTable('sale_deliveries')) {
                $deliveryQuery = \Illuminate\Support\Facades\DB::table('sale_deliveries');

                if (ctype_digit($deliveryKey)) {
                    $deliveryQuery->where('id', (int) $deliveryKey);
                } else {
                    $deliveryQuery->where('number', $deliveryKey);
                }

                $delivery = $deliveryQuery->first();

                if ($delivery) {
                    if (! empty($delivery->sales_order_id) && \Illuminate\Support\Facades\Schema::hasTable('sales_orders')) {
                        $orderNumber = \Illuminate\Support\Facades\DB::table('sales_orders')
                            ->where('id', $delivery->sales_order_id)
                            ->value('number');

                        if ($orderNumber) {
                            return $orderNumber;
                        }
                    }

                    if (! empty($delivery->number)) {
                        return $delivery->number;
                    }
                }
            }

            return 'Entrega de venta';
        }

        if (str_starts_with($originDocument, 'purchase_receipt:')) {
            $receiptNumber = trim(substr($originDocument, strlen('purchase_receipt:')));

            if (
                $receiptNumber !== ''
                && \Illuminate\Support\Facades\Schema::hasTable('purchase_receipts')
                && \Illuminate\Support\Facades\Schema::hasTable('purchase_orders')
            ) {
                $receipt = \Illuminate\Support\Facades\DB::table('purchase_receipts')
                    ->where('number', $receiptNumber)
                    ->first(['purchase_order_id']);

                if ($receipt && $receipt->purchase_order_id) {
                    $orderNumber = \Illuminate\Support\Facades\DB::table('purchase_orders')
                        ->where('id', $receipt->purchase_order_id)
                        ->value('number');

                    if ($orderNumber) {
                        return $orderNumber;
                    }
                }
            }

            return $receiptNumber ?: 'Recepción de compra';
        }

        if (str_starts_with($originDocument, 'purchase_order:')) {
            $order = trim(substr($originDocument, strlen('purchase_order:')));

            return $order !== '' ? $order : 'Orden de compra';
        }

        if (str_starts_with($originDocument, 'stock_adjustment:')) {
            $adjustment = trim(substr($originDocument, strlen('stock_adjustment:')));

            return $adjustment !== '' ? $adjustment : 'Ajuste de inventario';
        }

        if (str_starts_with($originDocument, 'transfer:')) {
            $transfer = trim(substr($originDocument, strlen('transfer:')));

            return $transfer !== '' ? $transfer : 'Transferencia';
        }

        return $originDocument;
    }


    protected static function stockMovementPurchaseOrderLabel($record): string
    {
        $reference = trim((string) ($record->reference ?? ''));
        $originDocument = trim((string) ($record->origin_document ?? ''));

        $receiptNumber = null;

        if (str_starts_with($originDocument, 'exit:')) {
            $folio = trim(substr($originDocument, strlen('exit:')));

            return $folio !== '' ? $folio : 'Salida';
        }


        if (str_starts_with($originDocument, 'sale_delivery:')) {
            $deliveryKey = trim(substr($originDocument, strlen('sale_delivery:')));

            if ($deliveryKey !== '' && \Illuminate\Support\Facades\Schema::hasTable('sale_deliveries')) {
                $deliveryQuery = \Illuminate\Support\Facades\DB::table('sale_deliveries');

                if (ctype_digit($deliveryKey)) {
                    $deliveryQuery->where('id', (int) $deliveryKey);
                } else {
                    $deliveryQuery->where('number', $deliveryKey);
                }

                $delivery = $deliveryQuery->first();

                if ($delivery) {
                    if (! empty($delivery->sales_order_id) && \Illuminate\Support\Facades\Schema::hasTable('sales_orders')) {
                        $orderNumber = \Illuminate\Support\Facades\DB::table('sales_orders')
                            ->where('id', $delivery->sales_order_id)
                            ->value('number');

                        if ($orderNumber) {
                            return $orderNumber;
                        }
                    }

                    if (! empty($delivery->number)) {
                        return $delivery->number;
                    }
                }
            }

            return 'Entrega de venta';
        }

        if (str_starts_with($originDocument, 'purchase_receipt:')) {
            $receiptNumber = trim(substr($originDocument, strlen('purchase_receipt:')));
        }

        if (! $receiptNumber && str_starts_with($reference, 'REC-')) {
            $receiptNumber = $reference;
        }

        if (! $receiptNumber || ! \Illuminate\Support\Facades\Schema::hasTable('purchase_receipts')) {
            return '—';
        }

        $receipt = \Illuminate\Support\Facades\DB::table('purchase_receipts')
            ->where('number', $receiptNumber)
            ->first(['purchase_order_id']);

        if (! $receipt || ! $receipt->purchase_order_id || ! \Illuminate\Support\Facades\Schema::hasTable('purchase_orders')) {
            return '—';
        }

        $orderNumber = \Illuminate\Support\Facades\DB::table('purchase_orders')
            ->where('id', $receipt->purchase_order_id)
            ->value('number');

        return $orderNumber ?: ('OC #' . $receipt->purchase_order_id);
    }



    protected static function stockMovementReceiptId($record): ?int
    {
        $reference = trim((string) ($record->reference ?? ''));
        $originDocument = trim((string) ($record->origin_document ?? ''));

        $receiptNumber = null;

        if (str_starts_with($originDocument, 'purchase_receipt:')) {
            $receiptNumber = trim(substr($originDocument, strlen('purchase_receipt:')));
        }

        if (! $receiptNumber && str_starts_with($reference, 'REC-')) {
            $receiptNumber = $reference;
        }

        if (! $receiptNumber || ! \Illuminate\Support\Facades\Schema::hasTable('purchase_receipts')) {
            return null;
        }

        $query = \Illuminate\Support\Facades\DB::table('purchase_receipts');

        if (ctype_digit($receiptNumber)) {
            $query->where('id', (int) $receiptNumber);
        } else {
            $query->where('number', $receiptNumber);
        }

        $id = $query->value('id');

        return $id ? (int) $id : null;
    }

    protected static function stockMovementReceiptUrl($record, bool $pdf = false): string
    {
        $tenantId = (int) ($record->company_id ?? 0);

        if ($tenantId <= 0 && \Filament\Facades\Filament::getTenant()) {
            $tenantId = (int) \Filament\Facades\Filament::getTenant()->getKey();
        }

        if ($tenantId <= 0) {
            $tenantId = (int) (auth()->user()?->company_id ?? request()->route('tenant') ?? 0);
        }

        $receiptId = static::stockMovementReceiptId($record);

        if (! $receiptId) {
            return '#';
        }

        return url('/admin/' . $tenantId . '/purchase-receipts/' . $receiptId . ($pdf ? '/pdf' : '/panel'));
    }


    protected static function stockMovementTenantIdForRelatedDocument($record): int
    {
        $tenantId = (int) ($record->company_id ?? 0);

        try {
            $tenant = \Filament\Facades\Filament::getTenant();

            if ($tenantId <= 0 && is_object($tenant) && method_exists($tenant, 'getKey')) {
                $tenantId = (int) $tenant->getKey();
            } elseif ($tenantId <= 0 && is_numeric($tenant)) {
                $tenantId = (int) $tenant;
            }
        } catch (\Throwable $e) {
            //
        }

        if ($tenantId <= 0) {
            $routeTenant = request()->route('tenant');

            if (is_object($routeTenant) && method_exists($routeTenant, 'getKey')) {
                $tenantId = (int) $routeTenant->getKey();
            } elseif (is_numeric($routeTenant)) {
                $tenantId = (int) $routeTenant;
            }
        }

        if ($tenantId <= 0) {
            $tenantId = (int) (auth()->user()?->company_id ?? 0);
        }

        return $tenantId;
    }

    protected static function stockMovementSaleDeliveryId($record): ?int
    {
        $originDocument = trim((string) ($record->origin_document ?? ''));
        $reference = trim((string) ($record->reference ?? ''));

        $deliveryKey = null;

        if (str_starts_with($originDocument, 'sale_delivery:')) {
            $deliveryKey = trim(substr($originDocument, strlen('sale_delivery:')));
        }

        if (! $deliveryKey && str_starts_with($reference, 'ENT-')) {
            $deliveryKey = $reference;
        }

        if (! $deliveryKey || ! \Illuminate\Support\Facades\Schema::hasTable('sale_deliveries')) {
            return null;
        }

        $query = \Illuminate\Support\Facades\DB::table('sale_deliveries');

        if (ctype_digit($deliveryKey)) {
            $query->where('id', (int) $deliveryKey);
        } else {
            $query->where('number', $deliveryKey);
        }

        $id = $query->value('id');

        return $id ? (int) $id : null;
    }

    protected static function stockMovementSaleDeliveryUrl($record): string
    {
        $deliveryId = static::stockMovementSaleDeliveryId($record);

        if (! $deliveryId) {
            return '#';
        }

        return url('/admin/' . static::stockMovementTenantIdForRelatedDocument($record) . '/sale-deliveries/' . $deliveryId);
    }

    protected static function stockMovementIsInternalTransfer($record): bool
    {
        $reference = trim((string) ($record->reference ?? ''));

        if (str_contains($reference, '/INT/')) {
            return true;
        }

        if (! empty($record->stock_operation_type_id) && \Illuminate\Support\Facades\Schema::hasTable('stock_operation_types')) {
            $kind = \Illuminate\Support\Facades\DB::table('stock_operation_types')
                ->where('id', $record->stock_operation_type_id)
                ->value('operation_kind');

            return (string) $kind === 'internal_transfer';
        }

        return false;
    }

    protected static function stockMovementTransferUrl($record): string
    {
        return url('/admin/' . static::stockMovementTenantIdForRelatedDocument($record) . '/stock-movements/' . $record->getKey() . '/edit');
    }


    protected static function stockMovementGeneralPdfUrl($record): string
    {
        $tenantId = (int) ($record->company_id ?? 0);

        if ($tenantId <= 0 && \Filament\Facades\Filament::getTenant()) {
            $tenantId = (int) \Filament\Facades\Filament::getTenant()->getKey();
        }

        if ($tenantId <= 0) {
            $tenantId = (int) (auth()->user()?->company_id ?? request()->route('tenant') ?? 0);
        }

        return url('/admin/' . $tenantId . '/stock-movements/' . $record->getKey() . '/pdf');
    }



    public static function v5509dMovementTypeLabel(object $record): string
    {
        $base = (string) ($record->operation_type_name ?? $record->type_name ?? $record->operation_name ?? '');

        if ((string) ($record->operation_type_code ?? $record->type_code ?? '') === 'DEV_PDV' || str_contains((string) ($record->origin_document ?? ''), 'DEV-')) {
            if (\Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')) {
                $refund = \Illuminate\Support\Facades\DB::table('pos_order_refunds')
                    ->where('number', (string) ($record->origin_document ?? ''))
                    ->first();

                if ($refund) {
                    return ((string) ($refund->type ?? '') === 'partial')
                        ? 'Entrada por devolución parcial'
                        : 'Entrada por devolución total';
                }
            }

            return 'Entrada por devolución';
        }

        return $base !== '' ? $base : 'Movimiento';
    }

    public static function v5509dRefundMovementUrl(object $record): ?string
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')) {
            return null;
        }

        $refund = \Illuminate\Support\Facades\DB::table('pos_order_refunds')
            ->where('number', (string) ($record->origin_document ?? ''))
            ->first();

        if (! $refund || empty($refund->stock_movement_id)) {
            return null;
        }

        return static::getUrl('view', ['record' => $refund->stock_movement_id]);
    }



    public static function v5509fRefundMovementUrl(object $record): string
    {
        return static::getUrl('view', ['record' => $record]);
    }


    public static function v5509kStockMovementUrl(object $record): string
    {
        $tenant = request()->route('tenant');

        return url('/admin/' . $tenant . '/stock-movements/' . $record->id . '/edit');
    }


    public static function v5511bOperationTypeLabel(?object $record): string
    {
        try {
            if (isset($record->stock_operation_type_id) && \Illuminate\Support\Facades\Schema::hasTable('stock_operation_types')) {
                $operationType = \Illuminate\Support\Facades\DB::table('stock_operation_types')
                    ->where('id', (int) $record->stock_operation_type_id)
                    ->first();

                $code = strtoupper(trim((string) ($operationType->code ?? '')));
                $name = trim((string) ($operationType->name ?? ''));
                $nameLower = mb_strtolower($name);

                /*
                 * No tocar devoluciones. Mostrar exactamente el nombre que trae BD.
                 */
                if (
                    str_contains($code, 'DEV')
                    || str_contains($code, 'REFUND')
                    || str_contains($code, 'DEVOL')
                    || str_contains($nameLower, 'devolución')
                    || str_contains($nameLower, 'devolucion')
                ) {
                    return $name !== '' ? $name : 'Entrada por devolución';
                }

                if ($code === 'PDV' || $code === 'SALE_PDV' || $code === 'VENTA_PDV' || str_contains($code, 'PDV')) {
                    return 'Venta PDV';
                }

                if ($code === 'VTA' || $code === 'SALE' || $code === 'OUT' || str_contains($code, 'VTA') || str_contains($code, 'SALE') || str_contains($code, 'VENTA')) {
                    return 'Salida por venta';
                }

                if ($code === 'COMPRA' || $code === 'PURCHASE' || $code === 'IN' || str_contains($code, 'COMPRA') || str_contains($code, 'PURCHASE') || str_contains($code, 'BUY')) {
                    return 'Entrada por compra';
                }

                if ($code === 'INT' || $code === 'INTERNAL' || $code === 'TRANSFER' || str_contains($code, 'INT') || str_contains($code, 'TRANSFER') || str_contains($code, 'TRAS')) {
                    return 'Traslado';
                }

                if ($name !== '') {
                    return $name;
                }
            }
        } catch (\Throwable $e) {
            //
        }

        return 'Movimiento';
    }


    public static function v5511cOperationTypeLabel(?object $record): string
    {
        try {
            $reference = strtoupper((string) ($record->reference ?? ''));
            $origin = strtoupper((string) ($record->origin_document ?? ''));

            $code = '';
            $name = '';

            if (isset($record->stock_operation_type_id) && \Illuminate\Support\Facades\Schema::hasTable('stock_operation_types')) {
                $operationType = \Illuminate\Support\Facades\DB::table('stock_operation_types')
                    ->where('id', (int) $record->stock_operation_type_id)
                    ->first();

                $code = strtoupper(trim((string) ($operationType->code ?? '')));
                $name = trim((string) ($operationType->name ?? ''));
            }

            $nameLower = mb_strtolower($name);

            /*
             * NO TOCAR DEV. Mostrar exactamente el nombre guardado.
             */
            if (
                str_contains($code, 'DEV')
                || str_contains($code, 'REFUND')
                || str_contains($code, 'DEVOL')
                || str_contains($reference, '/DEV/')
                || str_contains($nameLower, 'devolución')
                || str_contains($nameLower, 'devolucion')
            ) {
                return $name !== '' ? $name : 'Entrada por devolución';
            }

            if (
                $code === 'PDV'
                || $code === 'SALE_PDV'
                || $code === 'VENTA_PDV'
                || str_contains($code, 'PDV')
                || str_contains($reference, '/PDV/')
            ) {
                return 'Venta PDV';
            }

            if (
                $code === 'VTA'
                || $code === 'SALE'
                || $code === 'OUT'
                || str_contains($code, 'VTA')
                || str_contains($code, 'SALE')
                || str_contains($code, 'VENTA')
                || str_contains($reference, '/VTA/')
            ) {
                return 'Salida por venta';
            }

            if (
                $code === 'COMPRA'
                || $code === 'PURCHASE'
                || $code === 'IN'
                || str_contains($code, 'COMPRA')
                || str_contains($code, 'PURCHASE')
                || str_contains($code, 'BUY')
                || str_contains($nameLower, 'compra')
                || str_contains($reference, '/IN/')
            ) {
                return 'Entrada por compra';
            }

            if (
                $code === 'INT'
                || $code === 'INTERNAL'
                || $code === 'TRANSFER'
                || str_contains($code, 'INT')
                || str_contains($code, 'TRANSFER')
                || str_contains($code, 'TRAS')
                || str_contains($reference, '/INT/')
            ) {
                return 'Traslado';
            }

            return $name !== '' ? $name : 'Movimiento';
        } catch (\Throwable $e) {
            return 'Movimiento';
        }
    }

    public static function v5511cOriginDocumentLabel(?object $record): string
    {
        try {
            $origin = trim((string) ($record->origin_document ?? ''));

            if (preg_match('/(?:pos_order|POS_ORDER|pos-order|pos_order_id)\s*:\s*(\d+)/', $origin, $matches)) {
                $orderId = (int) $matches[1];

                if (\Illuminate\Support\Facades\Schema::hasTable('pos_orders')) {
                    $number = \Illuminate\Support\Facades\DB::table('pos_orders')
                        ->where('id', $orderId)
                        ->value('number');

                    if (! empty($number)) {
                        return (string) $number;
                    }
                }
            }

            return $origin !== '' ? $origin : '—';
        } catch (\Throwable $e) {
            return (string) ($record->origin_document ?? '—');
        }
    }


    public static function v5511iStockMovementTypeLabel(?object $record): string
    {
        try {
            $reference = strtoupper((string) ($record->reference ?? ''));
            $origin = strtoupper((string) ($record->origin_document ?? ''));

            $code = '';
            $name = '';

            if (! empty($record->stock_operation_type_id) && \Illuminate\Support\Facades\Schema::hasTable('stock_operation_types')) {
                $operationType = \Illuminate\Support\Facades\DB::table('stock_operation_types')
                    ->where('id', (int) $record->stock_operation_type_id)
                    ->first();

                $code = strtoupper(trim((string) ($operationType->code ?? '')));
                $name = trim((string) ($operationType->name ?? ''));
            }

            $nameLower = mb_strtolower($name);

            /*
             * DEV: diferenciar total/parcial por pos_order_refunds.type.
             * No cambia catálogo; solo cambia etiqueta visual del movimiento.
             */
            if (
                str_contains($reference, '/DEV/')
                || str_contains($code, 'DEV')
                || str_contains($code, 'REFUND')
                || str_contains($code, 'DEVOL')
                || str_contains($nameLower, 'devolución')
                || str_contains($nameLower, 'devolucion')
            ) {
                $refundType = null;

                if (\Illuminate\Support\Facades\Schema::hasTable('pos_order_refunds')) {
                    $query = \Illuminate\Support\Facades\DB::table('pos_order_refunds');

                    $query->where(function ($q) use ($record) {
                        $movementId = (int) ($record->id ?? 0);
                        $movementReference = (string) ($record->reference ?? '');
                        $originDocument = (string) ($record->origin_document ?? '');

                        if ($movementId > 0 && \Illuminate\Support\Facades\Schema::hasColumn('pos_order_refunds', 'stock_movement_id')) {
                            $q->orWhere('stock_movement_id', $movementId);
                        }

                        if ($originDocument !== '' && \Illuminate\Support\Facades\Schema::hasColumn('pos_order_refunds', 'number')) {
                            $q->orWhere('number', $originDocument);
                        }

                        if ($movementReference !== '' && \Illuminate\Support\Facades\Schema::hasColumn('pos_order_refunds', 'inventory_return_reference')) {
                            $q->orWhere('inventory_return_reference', $movementReference);
                        }

                        if ($movementReference !== '' && \Illuminate\Support\Facades\Schema::hasColumn('pos_order_refunds', 'metadata')) {
                            $q->orWhere('metadata', 'like', '%' . $movementReference . '%');
                        }
                    });

                    $refund = $query
                        ->orderByDesc('id')
                        ->first();

                    if ($refund) {
                        $refundType = strtolower((string) ($refund->type ?? ''));

                        if ($refundType === '' && isset($refund->metadata)) {
                            $metadata = json_decode((string) $refund->metadata, true);

                            if (is_array($metadata)) {
                                $refundType = strtolower((string) ($metadata['type'] ?? $metadata['refund_type'] ?? ''));
                            }
                        }
                    }
                }

                if (in_array($refundType, ['partial', 'parcial', 'partial_refund', 'partially_refunded'], true)) {
                    return 'Entrada por devolución parcial';
                }

                if (in_array($refundType, ['full', 'total', 'full_refund', 'refunded'], true)) {
                    return 'Entrada por devolución total';
                }

                /*
                 * Fallback:
                 * Si el nombre ya trae parcial/total, respetarlo.
                 */
                if (str_contains($nameLower, 'parcial')) {
                    return 'Entrada por devolución parcial';
                }

                if (str_contains($nameLower, 'total')) {
                    return 'Entrada por devolución total';
                }

                return $name !== '' ? $name : 'Entrada por devolución';
            }

            // Reglas por referencia.
            if (str_contains($reference, '/PDV/')) {
                return 'Venta PDV';
            }

            if (str_contains($reference, '/OUT/') || str_contains($reference, '/VTA/')) {
                return 'Salida por venta';
            }

            if (str_contains($reference, '/IN/')) {
                return 'Entrada por compra';
            }

            if (str_contains($reference, '/INT/')) {
                return 'Traslado';
            }

            if (str_contains($reference, '/AJU/')) {
                return 'Ajuste de inventario';
            }

            // Reglas por catálogo.
            if ($code === 'VENTA_PDV' || $code === 'SALE_PDV' || $code === 'PDV' || str_contains($code, 'PDV')) {
                return 'Venta PDV';
            }

            if ($code === 'ENTREGA' || $code === 'VTA' || $code === 'SALE' || $code === 'OUT' || str_contains($code, 'VENTA')) {
                return 'Salida por venta';
            }

            if ($code === 'RECEPCION' || $code === 'COMPRA' || $code === 'PURCHASE' || $code === 'IN') {
                return 'Entrada por compra';
            }

            if ($code === 'TRASLADO_INTERNO' || $code === 'INT' || $code === 'TRANSFER') {
                return 'Traslado';
            }

            if ($code === 'AJUSTE_INVENTARIO') {
                return 'Ajuste de inventario';
            }

            return $name !== '' ? $name : 'Movimiento';
        } catch (\Throwable $e) {
            return 'Movimiento';
        }
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Referencia')
                    ->formatStateUsing(fn (?string $state): string => static::stockMovementReferenceLabel($state))
                    ->copyable()
                    ->searchable()
                    ->sortable(),


                Tables\Columns\TextColumn::make('movement_at')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('operationType.name')
                    ->label('Tipo')
                    ->state(fn ($record): string => static::v5511iStockMovementTypeLabel($record))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Almacén')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sourceLocation.name')
                    ->label('Desde')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('destinationLocation.name')
                    ->label('A')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('lines_count')
                    ->label('Líneas')
                    ->sortable(),

                Tables\Columns\TextColumn::make('origin_document')
                    ->label('Origen')
                    ->getStateUsing(fn ($record): string => static::v5511cOriginDocumentLabel($record))
                    ->formatStateUsing(fn (?string $state): string => static::stockMovementOriginLabel($state))
                    ->placeholder('—')
                    ->searchable(),







                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'Borrador',
                        'done' => 'Hecho',
                        'cancelled' => 'Cancelado',
                        default => (string) $state,
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'done' => 'Hecho',
                        'cancelled' => 'Cancelado',
                    ]),

                Tables\Filters\SelectFilter::make('stock_operation_type_id')
                    ->label('Tipo de operación')
                    ->options(fn (): array => static::operationTypeOptions()),

                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Almacén')
                    ->options(fn (): array => static::warehouseOptions()),
            ])
            ->actions([

                Tables\Actions\Action::make('view_pos_refund')
                    ->label('Ver devolución')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(function ($record): bool {
                        $origin = (string) ($record->origin_document ?? '');

                        if (str_starts_with($origin, 'DEV-')) {
                            return true;
                        }

                        if (\Illuminate\Support\Facades\Schema::hasTable('stock_operation_types')) {
                            $code = \Illuminate\Support\Facades\DB::table('stock_operation_types')
                                ->where('id', (int) ($record->stock_operation_type_id ?? 0))
                                ->value('code');

                            return (string) $code === 'DEV_PDV';
                        }

                        return false;
                    })
                    ->url(fn ($record): string => static::getUrl('view_refund', ['record' => $record]))
                    ->openUrlInNewTab(false),

                Tables\Actions\Action::make('view_sale_delivery')
                    ->label('Ver entrega')
                    ->icon('heroicon-o-truck')
                    ->color('gray')
                    ->visible(fn ($record): bool => static::stockMovementSaleDeliveryId($record) !== null)
                    ->url(fn ($record): string => static::stockMovementSaleDeliveryUrl($record)),

                Tables\Actions\Action::make('view_internal_transfer')
                    ->label('Ver traslado')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('gray')
                    ->visible(fn ($record): bool => static::stockMovementIsInternalTransfer($record))
                    ->url(fn ($record): string => static::stockMovementTransferUrl($record)),


                Tables\Actions\Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar movimiento')
                    ->modalDescription('Al confirmar, se actualizarán las existencias y el movimiento quedará bloqueado.')
                    ->modalSubmitActionLabel('Confirmar movimiento')
                    ->visible(fn (StockMovement $record): bool => $record->status === 'draft')
                    ->action(function (StockMovement $record): void {
                        static::confirmMovement($record);

                        Notification::make()
                            ->title('Movimiento confirmado')
                            ->success()
                            ->send();
                    }),












                Tables\Actions\Action::make('view_purchase_receipt')
                    ->label('Ver recepción')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->visible(fn ($record): bool => static::stockMovementReceiptId($record) !== null)
                    ->url(fn ($record): string => static::stockMovementReceiptUrl($record, false))
                    ->openUrlInNewTab(false),

                Tables\Actions\Action::make('view_pos_output')
                    ->label('Ver salida PDV')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->visible(fn (StockMovement $record): bool => static::stockMovementPosOrderId($record) !== null)
                    ->url(fn (StockMovement $record): string => static::stockMovementPosOutputUrl($record)),

                Tables\Actions\Action::make('stock_movement_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->openUrlInNewTab()
                    ->extraAttributes(['target' => '_blank'])
                    ->url(fn ($record): string => static::stockMovementGeneralPdfUrl($record)),

                Tables\Actions\EditAction::make()
                    ->label('Editar'),
            ])
            ->defaultSort('created_at', 'desc');
    }


    protected static function stockMovementPosOrderId($record): ?int
    {
        if (! $record || ! \Illuminate\Support\Facades\Schema::hasTable('pos_orders')) {
            return null;
        }

        $movementId = (int) ($record->id ?? 0);

        if ($movementId <= 0) {
            return null;
        }

        $originDocument = trim((string) ($record->origin_document ?? ''));

        // Caso normal: el ticket guarda stock_movement_id en metadata.
        $orderId = \Illuminate\Support\Facades\DB::table('pos_orders')
            ->where(function ($query) use ($movementId): void {
                $query
                    ->where('metadata', 'like', '%"stock_movement_id":' . $movementId . '%')
                    ->orWhere('metadata', 'like', '%"stock_movement_id":"' . $movementId . '"%');
            })
            ->orderByDesc('id')
            ->value('id');

        if ($orderId) {
            return (int) $orderId;
        }

        // Respaldo: si origin_document ya fue normalizado al folio del ticket.
        if ($originDocument !== '') {
            $orderId = \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('number', $originDocument)
                ->value('id');

            if ($orderId) {
                return (int) $orderId;
            }
        }

        return null;
    }

    protected static function stockMovementPosOutputUrl($record): string
    {
        $orderId = static::stockMovementPosOrderId($record);

        if (! $orderId) {
            return '#';
        }

        $tenantId = null;

        try {
            $tenant = \Filament\Facades\Filament::getTenant();

            if (is_object($tenant) && method_exists($tenant, 'getKey')) {
                $tenantId = (int) $tenant->getKey();
            } elseif (is_numeric($tenant)) {
                $tenantId = (int) $tenant;
            }
        } catch (\Throwable $e) {
            //
        }

        if (! $tenantId) {
            $tenantId = (int) ($record->company_id ?? auth()->user()?->company_id ?? 0);
        }

        return url('/admin/' . $tenantId . '/pos-tickets/' . $orderId . '/inventory-output');
    }

    public static function getPages(): array
    {
        return [
                'view_refund' => Pages\ViewRefundStockMovement::route('/{record}/refund-view'),
            'index' => Pages\ListStockMovements::route('/'),
            'create' => Pages\CreateStockMovement::route('/create'),
            'edit' => Pages\EditStockMovement::route('/{record}/edit'),
        ];
    }

    public static function confirmMovement(StockMovement $movement): void
    {
        if ($movement->status !== 'draft') {
            return;
        }

        $movement->load('lines');

        if ($movement->lines->isEmpty()) {
            Notification::make()
                ->title('No se puede confirmar')
                ->body('El traslado no tiene productos.')
                ->danger()
                ->send();

            throw new Halt();
        }

        if (! $movement->source_location_id && ! $movement->destination_location_id) {
            Notification::make()
                ->title('No se puede confirmar')
                ->body('El traslado necesita una ubicación origen o destino.')
                ->danger()
                ->send();

            throw new Halt();
        }

        if (
            $movement->source_location_id
            && $movement->destination_location_id
            && (int) $movement->source_location_id === (int) $movement->destination_location_id
        ) {
            Notification::make()
                ->title('Origen y destino iguales')
                ->body('La ubicación origen y destino no pueden ser la misma.')
                ->danger()
                ->send();

            throw new Halt();
        }

        $sourceAffectsStock = $movement->source_location_id
            ? static::locationAffectsStock((int) $movement->source_location_id)
            : false;

        $destinationAffectsStock = $movement->destination_location_id
            ? static::locationAffectsStock((int) $movement->destination_location_id)
            : false;

        if (! $sourceAffectsStock && ! $destinationAffectsStock) {
            Notification::make()
                ->title('Movimiento sin impacto')
                ->body('Ni el origen ni el destino afectan existencias. Revisa el tipo de operación.')
                ->danger()
                ->send();

            throw new Halt();
        }

        DB::transaction(function () use ($movement, $sourceAffectsStock, $destinationAffectsStock): void {
            foreach ($movement->lines as $line) {
                $qty = (float) $line->done_quantity;

                if (! $line->product_id || $qty <= 0) {
                    Notification::make()
                        ->title('Cantidad inválida')
                        ->body('Todas las líneas deben tener producto y cantidad mayor a cero.')
                        ->danger()
                        ->send();

                    throw new Halt();
                }

                $productId = (int) $line->product_id;
                $variantId = $line->product_variant_id ? (int) $line->product_variant_id : null;

                $unitCost = $line->unit_cost !== null
                    ? (float) $line->unit_cost
                    : null;

                if ($unitCost === null && $sourceAffectsStock && $movement->source_location_id) {
                    $unitCost = static::averageCost(
                        (int) $movement->company_id,
                        (int) $movement->warehouse_id,
                        (int) $movement->source_location_id,
                        $productId,
                        $variantId
                    );
                }

                if ($unitCost === null) {
                    $unitCost = static::productCost($productId, $variantId);
                }

                if ($sourceAffectsStock && $movement->source_location_id) {
                    static::decreaseQuant(
                        companyId: (int) $movement->company_id,
                        warehouseId: (int) $movement->warehouse_id,
                        locationId: (int) $movement->source_location_id,
                        productId: $productId,
                        variantId: $variantId,
                        quantity: $qty,
                        unitCost: $unitCost
                    );
                }

                if ($destinationAffectsStock && $movement->destination_location_id) {
                    static::increaseQuant(
                        companyId: (int) $movement->company_id,
                        warehouseId: (int) $movement->warehouse_id,
                        locationId: (int) $movement->destination_location_id,
                        productId: $productId,
                        variantId: $variantId,
                        quantity: $qty,
                        unitCost: $unitCost
                    );
                }

                $line->update([
                    'requested_quantity' => $line->requested_quantity ?: $qty,
                    'done_quantity' => $qty,
                    'unit_cost' => $unitCost,
                ]);
            }

            $movement->update([
                'status' => 'done',
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
            ]);
        });
    }

    protected static function decreaseQuant(
        int $companyId,
        int $warehouseId,
        int $locationId,
        int $productId,
        ?int $variantId,
        float $quantity,
        ?float $unitCost = null
    ): void {
        $quant = static::findOrNewQuant($companyId, $warehouseId, $locationId, $productId, $variantId);
        $current = (float) $quant->quantity;
        $newQuantity = $current - $quantity;

        if ($newQuantity < 0 && ! static::locationAllowsNegativeStock($locationId)) {
            Notification::make()
                ->title('Existencia insuficiente')
                ->body(
                    static::stockItemLabel($productId, $variantId)
                    . ' no tiene existencia suficiente en '
                    . static::locationLabel($locationId)
                    . '. Disponible: ' . number_format($current, 2)
                    . ', solicitado: ' . number_format($quantity, 2) . '.'
                )
                ->danger()
                ->send();

            throw new Halt();
        }

        $quant->quantity = $newQuantity;

        if ($unitCost !== null && $quant->average_cost === null) {
            $quant->average_cost = $unitCost;
        }

        $quant->save();
    }



    protected static function increaseQuant(
        int $companyId,
        int $warehouseId,
        int $locationId,
        int $productId,
        ?int $variantId,
        float $quantity,
        ?float $unitCost = null
    ): void {
        $quant = static::findOrNewQuant($companyId, $warehouseId, $locationId, $productId, $variantId);

        $currentQuantity = (float) $quant->quantity;
        $newQuantity = $currentQuantity + $quantity;

        if ($unitCost !== null) {
            $currentCost = $quant->average_cost !== null ? (float) $quant->average_cost : null;

            if ($currentCost !== null && $currentQuantity > 0 && $newQuantity > 0) {
                $quant->average_cost = (($currentQuantity * $currentCost) + ($quantity * $unitCost)) / $newQuantity;
            } else {
                $quant->average_cost = $unitCost;
            }
        }

        $quant->quantity = $newQuantity;
        $quant->save();
    }

    protected static function findOrNewQuant(int $companyId, int $warehouseId, int $locationId, int $productId, ?int $variantId): StockQuant
    {
        $query = StockQuant::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId);

        $variantId
            ? $query->where('product_variant_id', $variantId)
            : $query->whereNull('product_variant_id');

        $quant = $query->first();

        if ($quant) {
            return $quant;
        }

        return new StockQuant([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'reserved_quantity' => 0,
            'quantity' => 0,
        ]);
    }

    protected static function locationAffectsStock(int $locationId): bool
    {
        if (! Schema::hasTable('stock_locations')) {
            return false;
        }

        $location = DB::table('stock_locations')
            ->leftJoin('stock_location_types', 'stock_location_types.id', '=', 'stock_locations.stock_location_type_id')
            ->where('stock_locations.id', $locationId)
            ->select(
                'stock_locations.warehouse_id',
                'stock_location_types.code as type_code',
                'stock_location_types.is_internal'
            )
            ->first();

        if (! $location) {
            return false;
        }

        if ((bool) $location->is_internal) {
            return true;
        }

        return (string) $location->type_code === 'TRANSIT';
    }

    protected static function locationAllowsNegativeStock(int $locationId): bool
    {
        if (! Schema::hasTable('stock_locations') || ! Schema::hasColumn('stock_locations', 'allow_negative_stock')) {
            return false;
        }

        return (bool) DB::table('stock_locations')
            ->where('id', $locationId)
            ->value('allow_negative_stock');
    }

    protected static function averageCost(int $companyId, int $warehouseId, int $locationId, int $productId, ?int $variantId = null): ?float
    {
        if (! Schema::hasTable('stock_quants')) {
            return null;
        }

        $query = DB::table('stock_quants')
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId);

        $variantId
            ? $query->where('product_variant_id', $variantId)
            : $query->whereNull('product_variant_id');

        $value = $query->value('average_cost');

        return $value !== null ? (float) $value : null;
    }

    protected static function productCost(int $productId, ?int $variantId = null): ?float
    {
        if (! Schema::hasTable('products')) {
            return null;
        }

        foreach (array_filter([$variantId, $productId]) as $id) {
            $product = DB::table('products')->where('id', $id)->first();

            if (! $product) {
                continue;
            }

            foreach (['standard_cost', 'purchase_price', 'last_purchase_cost', 'cost'] as $column) {
                if (! Schema::hasColumn('products', $column)) {
                    continue;
                }

                $value = $product->{$column} ?? null;

                if ($value !== null && (float) $value > 0) {
                    return (float) $value;
                }
            }
        }

        return null;
    }

    protected static function sourceQuantityFromForm(Forms\Get $get): float
    {
        $companyId = static::currentCompanyId();

        $warehouseId = $get('../../warehouse_id')
            ?: $get('../../../warehouse_id')
            ?: $get('warehouse_id');

        $sourceLocationId = $get('../../source_location_id')
            ?: $get('../../../source_location_id')
            ?: $get('source_location_id');

        $productId = $get('product_id');
        $variantId = $get('product_variant_id');

        if (! $companyId || ! $warehouseId || ! $sourceLocationId || ! $productId) {
            return 0;
        }

        if (! static::locationAffectsStock((int) $sourceLocationId)) {
            return 0;
        }

        return static::currentQuantity(
            (int) $companyId,
            (int) $warehouseId,
            (int) $sourceLocationId,
            (int) $productId,
            $variantId ? (int) $variantId : null
        );
    }




    protected static function sourceQuantityLabelFromForm(Forms\Get $get): string
    {
        $sourceLocationId = $get('../../source_location_id')
            ?: $get('../../../source_location_id')
            ?: $get('source_location_id');

        if (! $sourceLocationId) {
            return 'Seleccione origen';
        }

        if (! static::locationAffectsStock((int) $sourceLocationId)) {
            return 'No aplica';
        }

        $quantity = static::sourceQuantityFromForm($get);

        return number_format($quantity, 2) . ' en ' . static::locationLabel((int) $sourceLocationId);
    }

    protected static function stockItemLabel(int $productId, ?int $variantId = null): string
    {
        $product = static::productLabel($productId);

        if (! $variantId) {
            return $product;
        }

        return $product . ' / ' . static::variantLabel($variantId);
    }

    protected static function locationLabel(int $locationId): string
    {
        if (! Schema::hasTable('stock_locations')) {
            return 'ubicación #' . $locationId;
        }

        $location = DB::table('stock_locations')
            ->where('id', $locationId)
            ->first();

        if (! $location) {
            return 'ubicación #' . $locationId;
        }

        $code = Schema::hasColumn('stock_locations', 'code')
            ? trim((string) ($location->code ?? ''))
            : '';

        $name = Schema::hasColumn('stock_locations', 'name')
            ? trim((string) ($location->name ?? ''))
            : '';

        if ($code !== '' && $name !== '') {
            return $code . ' - ' . $name;
        }

        return $name ?: ($code ?: ('ubicación #' . $locationId));
    }

    protected static function currentQuantity(int $companyId, int $warehouseId, int $locationId, int $productId, ?int $variantId = null): float
    {
        if (! Schema::hasTable('stock_quants')) {
            return 0;
        }

        $query = DB::table('stock_quants')
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId);

        $variantId
            ? $query->where('product_variant_id', $variantId)
            : $query->whereNull('product_variant_id');

        return (float) $query->sum('quantity');
    }

    protected static function operationTypeOptions(): array
    {
        if (! Schema::hasTable('stock_operation_types')) {
            return [];
        }

        $query = DB::table('stock_operation_types')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stock_operation_types.warehouse_id')
            ->where('stock_operation_types.is_active', true)
            // En Inventario > Movimientos solo se crean traslados manuales.
            // Recepciones se crearán desde Compras y Entregas desde Ventas/Punto de venta.
            ->whereIn('stock_operation_types.operation_kind', [
                'internal_transfer',
            ]);

        $companyId = static::currentCompanyId();

        $query->where(function ($query) use ($companyId): void {
            $query->whereNull('stock_operation_types.company_id');

            if ($companyId) {
                $query->orWhere('stock_operation_types.company_id', $companyId);
            }
        });

        return $query
            ->orderBy('warehouses.name')
            ->orderBy('stock_operation_types.sequence')
            ->get([
                'stock_operation_types.id',
                'stock_operation_types.name',
                'stock_operation_types.reference_prefix',
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
            ])
            ->mapWithKeys(fn ($row): array => [
                $row->id => trim(($row->warehouse_code ? $row->warehouse_code . ' - ' : '') . $row->name . ($row->reference_prefix ? ' / ' . $row->reference_prefix : '')),
            ])
            ->all();
    }



    protected static function operationTypeRow($id): ?object
    {
        if (! $id || ! Schema::hasTable('stock_operation_types')) {
            return null;
        }

        return DB::table('stock_operation_types')->where('id', $id)->first();
    }

    protected static function warehouseOptions(): array
    {
        if (! Schema::hasTable('warehouses')) {
            return [];
        }

        $query = DB::table('warehouses')->where('is_active', true);

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('warehouses', 'company_id')) {
            $query->where('company_id', $companyId);
        } elseif (Schema::hasColumn('warehouses', 'company_id')) {
            $query->whereNull('company_id');
        }

        return $query
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn ($warehouse): array => [
                $warehouse->id => trim(($warehouse->code ? $warehouse->code . ' - ' : '') . $warehouse->name),
            ])
            ->all();
    }

    protected static function locationOptions($warehouseId): array
    {
        if (! Schema::hasTable('stock_locations')) {
            return [];
        }

        $query = DB::table('stock_locations')
            ->where('is_active', true);

        if ($warehouseId) {
            $query->where(function ($query) use ($warehouseId): void {
                $query
                    ->where('warehouse_id', $warehouseId)
                    ->orWhereNull('warehouse_id');
            });
        }

        $companyId = static::currentCompanyId();

        $query->where(function ($query) use ($companyId): void {
            $query->whereNull('company_id');

            if ($companyId) {
                $query->orWhere('company_id', $companyId);
            }
        });

        return $query
            ->orderByRaw('warehouse_id nulls first')
            ->orderBy('name')
            ->get(['id', 'warehouse_id', 'code', 'name'])
            ->mapWithKeys(fn ($location): array => [
                $location->id => trim(($location->warehouse_id ? '' : 'Virtual / ') . ($location->code ? $location->code . ' - ' : '') . $location->name),
            ])
            ->all();
    }

    protected static function productSearchOptions(string $search): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        $query = DB::table('products');

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('products', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('products', 'is_variant')) {
            $query->where(function ($query): void {
                $query
                    ->where('is_variant', false)
                    ->orWhereNull('is_variant');
            });
        }

        $search = trim($search);

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

            $query->where(function ($query) use ($like): void {
                foreach (['name', 'description', 'sku', 'internal_reference', 'reference', 'code', 'barcode'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $query->orWhere($column, 'ilike', $like);
                    }
                }
            });
        }

        return $query
            ->orderBy(Schema::hasColumn('products', 'name') ? 'name' : 'id')
            ->limit(50)
            ->get(['id'])
            ->mapWithKeys(fn ($row): array => [
                $row->id => static::productLabel($row->id),
            ])
            ->all();
    }

    protected static function variantOptions($productId): array
    {
        if (! $productId || ! Schema::hasTable('products') || ! Schema::hasColumn('products', 'parent_product_id')) {
            return [];
        }

        $query = DB::table('products')
            ->where('parent_product_id', (int) $productId);

        if (Schema::hasColumn('products', 'is_variant')) {
            $query->where('is_variant', true);
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('is_active', true);
        }

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('products', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->orderBy(Schema::hasColumn('products', 'variant_value') ? 'variant_value' : 'name')
            ->limit(300)
            ->get(['id'])
            ->mapWithKeys(fn ($row): array => [
                $row->id => static::variantLabel($row->id),
            ])
            ->all();
    }

    protected static function productLabel($productId): string
    {
        return static::labelFromProducts($productId, false);
    }

    protected static function variantLabel($variantId): string
    {
        return static::labelFromProducts($variantId, true);
    }

    protected static function labelFromProducts($id, bool $variant = false): string
    {
        if (! $id || ! Schema::hasTable('products')) {
            return '—';
        }

        $row = DB::table('products')->where('id', $id)->first();

        if (! $row) {
            return '—';
        }

        $reference = '';

        foreach (['internal_reference', 'sku', 'barcode', 'code'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                $value = trim((string) ($row->{$column} ?? ''));

                if ($value !== '') {
                    $reference = $value;
                    break;
                }
            }
        }

        if ($variant) {
            $group = Schema::hasColumn('products', 'variant_group') ? trim((string) ($row->variant_group ?? '')) : '';
            $value = Schema::hasColumn('products', 'variant_value') ? trim((string) ($row->variant_value ?? '')) : '';

            $variantText = '';

            if ($group !== '' && $value !== '') {
                $variantText = $group . ': ' . $value;
            } elseif ($value !== '') {
                $variantText = $value;
            } elseif (Schema::hasColumn('products', 'name')) {
                $variantText = trim((string) ($row->name ?? ''));
            }

            if ($reference !== '' && $variantText !== '') {
                return $reference . ' - ' . $variantText;
            }

            return $variantText ?: ($reference ?: ('Variante #' . $id));
        }

        $name = Schema::hasColumn('products', 'name') ? trim((string) ($row->name ?? '')) : '';

        if ($reference !== '' && $name !== '') {
            return $reference . ' - ' . $name;
        }

        return $name ?: ($reference ?: ('Producto #' . $id));
    }

    protected static function movementIsDoneFromForm(Forms\Get $get): bool
    {
        $status = $get('status') ?: $get('../../status') ?: $get('../../../status');

        return in_array($status, ['done', 'cancelled'], true);
    }

    protected static function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        if ($tenant && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        $user = auth()->user();

        if ($user && isset($user->company_id)) {
            return (int) $user->company_id;
        }

        return null;
    }

public static function canCreate(): bool
    {
        return static::userCanPermission('inventory.transfer_stock');
    }

    public static function canEdit(Model $record): bool
    {
        if ($record instanceof StockMovement && in_array($record->status, ['done', 'cancelled'], true)) {
            return false;
        }

        return static::userCanPermission('inventory.transfer_stock');
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof StockMovement
            && $record->status === 'draft'
            && static::userCanPermission('inventory.delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::userCanPermission('inventory.delete');
    }

    protected static function userCanPermission(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

if (
    method_exists($user, 'hasAnyRole')
    && $user->hasAnyRole(['super_admin', 'Super Admin', 'Super Administrador'])
) {
    return true;
}

        return method_exists($user, 'can')
            ? $user->can($permission)
            : false;
    }
}
