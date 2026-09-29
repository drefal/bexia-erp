<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use App\Models\StockMovementReceipt;
use App\Models\StockMovementReceiptLine;
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

// BEXIA_STOCK_MOVEMENT_RESOURCE_RESPONSIVE_V5_79_35C
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
            ->with([
                'operationType',
                'warehouse',
                'destinationWarehouse',
                'sourceLocation',
                'destinationLocation',
                'transitLocation',
            ])
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
                $user?->can('inventory.movements.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('inventory.movements.view')
            );
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => static::currentCompanyId()),

                Forms\Components\Section::make('Traslado')
                    ->extraAttributes(['class' => 'bexia-stock-movement-section bexia-stock-movement-transfer-section'])
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label('Referencia')
                            ->extraAttributes(['class' => 'bexia-stock-movement-field bexia-stock-movement-reference-field'])
                            ->placeholder('Se genera automáticamente al guardar')
                            ->helperText('Formato: UBICACION/PREFIJO/000001.')
                            ->readOnly()
                            ->dehydrated(true)
                            ->columnSpan(3),

                        Forms\Components\DateTimePicker::make('movement_at')
                            ->label('Fecha y hora')
                            ->extraAttributes(['class' => 'bexia-stock-movement-field bexia-stock-movement-movement-at-field'])
                            ->default(now())
                            ->required()
                            ->seconds(false)
                            ->displayFormat('d/m/Y H:i')
                            ->disabled(fn (Forms\Get $get): bool => static::movementIsDoneFromForm($get))
                            ->columnSpan(3),

                        Forms\Components\Select::make('stock_operation_type_id')
                            ->label('Tipo de operación')
                            ->extraAttributes(['class' => 'bexia-stock-movement-field bexia-stock-movement-operation-type-field'])
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
                                $set('destination_warehouse_id', $operation->warehouse_id);
                                $set('source_location_id', $operation->source_location_id);
                                $set('destination_location_id', null);
                                $set('transit_location_id', null);
                                $set('reference', null);
                            })
                            ->disabled(
                                fn (?\App\Models\StockMovement $record, Forms\Get $get): bool =>
                                    filled($record?->id)
                                    || static::movementIsDoneFromForm($get)
                            )
                            ->columnSpan(3),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->extraAttributes(['class' => 'bexia-stock-movement-field bexia-stock-movement-status-field'])
                            ->options([
                                'draft' => 'Borrador',
                                'in_transit' => 'En tránsito',
                                'done' => 'Hecho',
                                'cancelled' => 'Cancelado',
                            ])
                            ->default('draft')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(3),

                        Forms\Components\Fieldset::make('Origen')
                            ->schema([
                                Forms\Components\Select::make('warehouse_id')
                                    ->label('Almacén origen')
                                    ->extraAttributes(['class' => 'bexia-stock-movement-field bexia-stock-movement-warehouse-field'])
                                    ->options(fn (): array => static::warehouseOptions())
                                    ->searchable()
                                    ->native(false)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, $state): void {
                                        $set('destination_warehouse_id', $state ? (int) $state : null);
                                        $set('source_location_id', null);
                                        $set('destination_location_id', null);
                                        $set('transit_location_id', null);
                                        $set('reference', null);
                                    })
                                    ->disabled(
                                fn (?\App\Models\StockMovement $record, Forms\Get $get): bool =>
                                    filled($record?->id)
                                    || static::movementIsDoneFromForm($get)
                            ),

                                Forms\Components\Select::make('source_location_id')
                                    ->label('Ubicación origen')
                                    ->extraAttributes(['class' => 'bexia-stock-movement-field bexia-stock-movement-source-location-field'])
                                    ->options(fn (Forms\Get $get): array => static::movementLocationOptions($get('warehouse_id')))
                                    ->searchable()
                                    ->native(false)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Set $set): null => $set('reference', null))
                                    ->disabled(
                                fn (?\App\Models\StockMovement $record, Forms\Get $get): bool =>
                                    filled($record?->id)
                                    || static::movementIsDoneFromForm($get)
                            ),
                            ])
                            ->columns(1)
                            ->columnSpan(6),

                        Forms\Components\Fieldset::make('Destino')
                            ->schema([
                                Forms\Components\Select::make('destination_warehouse_id')
                                    ->label('Almacén destino')
                                    ->extraAttributes(['class' => 'bexia-stock-movement-field bexia-stock-movement-destination-warehouse-field'])
                                    ->options(fn (): array => static::warehouseOptions())
                                    ->searchable()
                                    ->native(false)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set): void {
                                        $set('destination_location_id', null);
                                        $set('transit_location_id', null);
                                    })
                                    ->disabled(
                                fn (?\App\Models\StockMovement $record, Forms\Get $get): bool =>
                                    filled($record?->id)
                                    || static::movementIsDoneFromForm($get)
                            ),

                                Forms\Components\Select::make('destination_location_id')
                                    ->label('Ubicación destino')
                                    ->extraAttributes(['class' => 'bexia-stock-movement-field bexia-stock-movement-destination-location-field'])
                                    ->options(fn (Forms\Get $get): array => static::movementLocationOptions(
                                        $get('destination_warehouse_id') ?: $get('warehouse_id')
                                    ))
                                    ->searchable()
                                    ->native(false)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Set $set): null => $set('reference', null))
                                    ->disabled(
                                fn (?\App\Models\StockMovement $record, Forms\Get $get): bool =>
                                    filled($record?->id)
                                    || static::movementIsDoneFromForm($get)
                            ),
                            ])
                            ->columns(1)
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('origin_document')
                            ->label('Documento de origen')
                            ->extraAttributes(['class' => 'bexia-stock-movement-field bexia-stock-movement-origin-document-field'])
                            ->placeholder('Ej. OC-0001, Pedido, Ticket, referencia externa')
                            ->maxLength(180)
                            ->disabled(fn (Forms\Get $get): bool => static::movementIsDoneFromForm($get))
                            ->columnSpan(6),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->extraAttributes(['class' => 'bexia-stock-movement-field bexia-stock-movement-notes-field'])
                            ->rows(2)
                            ->disabled(fn (Forms\Get $get): bool => static::movementIsDoneFromForm($get))
                            ->columnSpanFull(),
                    ])
                    ->columns(12),

                Forms\Components\Section::make('Productos')
                    ->extraAttributes(['class' => 'bexia-stock-movement-section bexia-stock-movement-products-section'])
                    ->description('Selecciona productos con existencia disponible en el almacén y ubicación de origen.')
                    ->schema([
                        Forms\Components\View::make('filament.components.stock-transfer-lines-inline-field')
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
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-stock-movement-col-reference'])
                    ->extraCellAttributes(['class' => 'bexia-stock-movement-col-reference'])
                    ->formatStateUsing(fn (?string $state): string => static::stockMovementReferenceLabel($state))
                    ->copyable()
                    ->searchable()
                    ->sortable(),


                Tables\Columns\TextColumn::make('movement_at')
                    ->label('Fecha y hora')
                    ->extraHeaderAttributes(['class' => 'bexia-stock-movement-col-movement-at'])
                    ->extraCellAttributes(['class' => 'bexia-stock-movement-col-movement-at'])
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('operationType.name')
                    ->label('Tipo')
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-stock-movement-col-operation-type'])
                    ->extraCellAttributes(['class' => 'bexia-stock-movement-col-operation-type'])
                    ->state(fn ($record): string => static::v5511iStockMovementTypeLabel($record))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Almacén')
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-stock-movement-col-warehouse'])
                    ->extraCellAttributes(['class' => 'bexia-stock-movement-col-warehouse'])
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sourceLocation.name')
                    ->label('Desde')
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-stock-movement-col-source-location'])
                    ->extraCellAttributes(['class' => 'bexia-stock-movement-col-source-location'])
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('destinationLocation.name')
                    ->label('A')
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-stock-movement-col-destination-location'])
                    ->extraCellAttributes(['class' => 'bexia-stock-movement-col-destination-location'])
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('lines_count')
                    ->label('Líneas')
                    ->extraHeaderAttributes(['class' => 'bexia-stock-movement-col-lines-count'])
                    ->extraCellAttributes(['class' => 'bexia-stock-movement-col-lines-count'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('origin_document')
                    ->label('Origen')
                    ->wrap()
                    ->extraHeaderAttributes(['class' => 'bexia-stock-movement-col-origin-document'])
                    ->extraCellAttributes(['class' => 'bexia-stock-movement-col-origin-document'])
                    ->getStateUsing(fn ($record): string => static::v5511cOriginDocumentLabel($record))
                    ->formatStateUsing(fn (?string $state): string => static::stockMovementOriginLabel($state))
                    ->placeholder('—')
                    ->searchable(),







                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->extraHeaderAttributes(['class' => 'bexia-stock-movement-col-status'])
                    ->extraCellAttributes(['class' => 'bexia-stock-movement-col-status'])
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'Borrador',
                        'in_transit' => 'En tránsito',
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
                        'in_transit' => 'En tránsito',
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

        static::validateTransfer($movement);

        $sourceAffectsStock = static::locationAffectsStock(
            (int) $movement->source_location_id
        );
        $destinationAffectsStock = static::locationAffectsStock(
            (int) $movement->destination_location_id
        );

        if (! $sourceAffectsStock && ! $destinationAffectsStock) {
            Notification::make()
                ->title('Traslado sin efecto de inventario')
                ->body(
                    'El origen y el destino son ubicaciones virtuales. '
                    . 'Selecciona al menos una ubicación física o de tránsito.'
                )
                ->danger()
                ->send();

            throw new Halt();
        }

        /*
         * Sólo usamos el flujo de tránsito cuando ambos extremos
         * afectan stock y pertenecen a almacenes distintos.
         *
         * Una ubicación virtual final (cliente, proveedor, pérdida,
         * producción, ajuste, etc.) no debe convertirse en tránsito
         * únicamente porque destination_warehouse_id sea distinto.
         */
        if (
            $sourceAffectsStock
            && $destinationAffectsStock
            && $movement->isInterWarehouseTransfer()
        ) {
            static::dispatchInterWarehouseTransfer($movement);

            return;
        }

        static::completeDirectTransfer(
            $movement,
            $sourceAffectsStock,
            $destinationAffectsStock
        );
    }

    protected static function validateTransfer(StockMovement $movement): void
    {
        if ($movement->lines->isEmpty()) {
            Notification::make()
                ->title('No se puede procesar')
                ->body('El traslado no tiene productos.')
                ->danger()
                ->send();

            throw new Halt();
        }

        if (! $movement->warehouse_id || ! $movement->destination_warehouse_id) {
            Notification::make()
                ->title('Almacenes incompletos')
                ->body('Selecciona almacén origen y almacén destino.')
                ->danger()
                ->send();

            throw new Halt();
        }

        if (! $movement->source_location_id || ! $movement->destination_location_id) {
            Notification::make()
                ->title('Ubicaciones incompletas')
                ->body('Selecciona ubicación origen y ubicación destino.')
                ->danger()
                ->send();

            throw new Halt();
        }

        if ((int) $movement->source_location_id === (int) $movement->destination_location_id) {
            Notification::make()
                ->title('Origen y destino iguales')
                ->body('La ubicación origen y destino no pueden ser la misma.')
                ->danger()
                ->send();

            throw new Halt();
        }

        static::assertWarehouseCompany(
            (int) $movement->company_id,
            (int) $movement->warehouse_id
        );

        static::assertWarehouseCompany(
            (int) $movement->company_id,
            (int) $movement->destination_warehouse_id
        );

        static::assertMovementLocation(
            (int) $movement->company_id,
            (int) $movement->warehouse_id,
            (int) $movement->source_location_id,
            'origen'
        );

        static::assertMovementLocation(
            (int) $movement->company_id,
            (int) $movement->destination_warehouse_id,
            (int) $movement->destination_location_id,
            'destino'
        );
    }

    protected static function completeDirectTransfer(
        StockMovement $movement,
        bool $sourceAffectsStock,
        bool $destinationAffectsStock
    ): void {
        DB::transaction(function () use (
            $movement,
            $sourceAffectsStock,
            $destinationAffectsStock
        ): void {
            foreach ($movement->lines as $line) {
                $qty = (float) $line->done_quantity;

                static::validateLineQuantity($line, $qty);

                $productId = (int) $line->product_id;
                $variantId = $line->product_variant_id
                    ? (int) $line->product_variant_id
                    : null;
                $lotId = $line->lot_id
                    ? (int) $line->lot_id
                    : null;

                $unitCost = $line->unit_cost !== null
                    ? (float) $line->unit_cost
                    : null;

                if ($sourceAffectsStock && $unitCost === null) {
                    $unitCost = static::averageCost(
                        (int) $movement->company_id,
                        (int) $movement->warehouse_id,
                        (int) $movement->source_location_id,
                        $productId,
                        $variantId
                    );
                }

                $unitCost ??= static::productCost(
                    $productId,
                    $variantId
                );

                if ($sourceAffectsStock) {
                    static::decreaseQuant(
                        companyId: (int) $movement->company_id,
                        warehouseId: (int) $movement->warehouse_id,
                        locationId: (int) $movement->source_location_id,
                        productId: $productId,
                        variantId: $variantId,
                        lotId: $lotId,
                        quantity: $qty,
                        unitCost: $unitCost
                    );
                }

                if ($destinationAffectsStock) {
                    $destinationWarehouseId =
                        $movement->destination_warehouse_id
                            ? (int) $movement->destination_warehouse_id
                            : (int) $movement->warehouse_id;

                    static::increaseQuant(
                        companyId: (int) $movement->company_id,
                        warehouseId: $destinationWarehouseId,
                        locationId: (int) $movement->destination_location_id,
                        productId: $productId,
                        variantId: $variantId,
                        lotId: $lotId,
                        quantity: $qty,
                        unitCost: $unitCost
                    );

                    static::moveSerial(
                        $line->stock_serial_number_id
                            ? (int) $line->stock_serial_number_id
                            : null,
                        (int) $movement->company_id,
                        $destinationWarehouseId,
                        (int) $movement->destination_location_id
                    );
                }

                $line->update([
                    'requested_quantity' =>
                        $line->requested_quantity ?: $qty,
                    'done_quantity' => $qty,
                    'unit_cost' => $unitCost,
                ]);
            }

            $movement->update([
                'status' => 'done',
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
                'received_by' => auth()->id(),
                'received_at' => now(),
            ]);
        });
    }

    protected static function dispatchInterWarehouseTransfer(StockMovement $movement): void
    {
        $transitLocationId = static::transitLocationId((int) $movement->company_id);

        if (! $transitLocationId) {
            Notification::make()
                ->title('Falta ubicación de tránsito')
                ->body('La empresa no tiene una ubicación TRANSITO activa.')
                ->danger()
                ->send();

            throw new Halt();
        }

        DB::transaction(function () use ($movement, $transitLocationId): void {
            foreach ($movement->lines as $line) {
                $qty = (float) $line->done_quantity;

                static::validateLineQuantity($line, $qty);

                $productId = (int) $line->product_id;
                $variantId = $line->product_variant_id ? (int) $line->product_variant_id : null;
                $lotId = $line->lot_id ? (int) $line->lot_id : null;

                $unitCost = $line->unit_cost !== null
                    ? (float) $line->unit_cost
                    : static::averageCost(
                        (int) $movement->company_id,
                        (int) $movement->warehouse_id,
                        (int) $movement->source_location_id,
                        $productId,
                        $variantId
                    );

                $unitCost ??= static::productCost($productId, $variantId);

                static::decreaseQuant(
                    companyId: (int) $movement->company_id,
                    warehouseId: (int) $movement->warehouse_id,
                    locationId: (int) $movement->source_location_id,
                    productId: $productId,
                    variantId: $variantId,
                    lotId: $lotId,
                    quantity: $qty,
                    unitCost: $unitCost
                );

                /*
                 * Mientras viaja, conservamos warehouse_id=origen.
                 * Esto es compatible con los quants de tránsito históricos
                 * existentes en Bexia.
                 */
                static::increaseQuant(
                    companyId: (int) $movement->company_id,
                    warehouseId: (int) $movement->warehouse_id,
                    locationId: $transitLocationId,
                    productId: $productId,
                    variantId: $variantId,
                    lotId: $lotId,
                    quantity: $qty,
                    unitCost: $unitCost
                );

                static::moveSerial(
                    $line->stock_serial_number_id ? (int) $line->stock_serial_number_id : null,
                    (int) $movement->company_id,
                    (int) $movement->warehouse_id,
                    $transitLocationId
                );

                $line->update([
                    'requested_quantity' => $line->requested_quantity ?: $qty,
                    'done_quantity' => $qty,
                    'unit_cost' => $unitCost,
                ]);
            }

            $movement->update([
                'transit_location_id' => $transitLocationId,
                'status' => 'in_transit',
                'dispatched_by' => auth()->id(),
                'dispatched_at' => now(),
            ]);
        });
    }

    public static function cancelTransfer(
        StockMovement $movement,
        string $reason
    ): void {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \RuntimeException(
                'El motivo de cancelación es obligatorio.'
            );
        }

        $companyId = static::currentCompanyId();

        if ($companyId <= 0) {
            throw new \RuntimeException(
                'No se pudo determinar la empresa activa.'
            );
        }

        \Illuminate\Support\Facades\DB::transaction(
            function () use ($movement, $reason, $companyId): void {
                $movement = StockMovement::query()
                    ->whereKey($movement->getKey())
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! static::stockMovementIsInternalTransfer($movement)) {
                    throw new \RuntimeException(
                        'Solo se pueden cancelar traslados internos.'
                    );
                }

                if (! in_array(
                    $movement->status,
                    ['draft', 'in_transit'],
                    true
                )) {
                    throw new \RuntimeException(
                        'El traslado ya no puede cancelarse directamente.'
                    );
                }

                $hasReceipts = \Illuminate\Support\Facades\DB::table(
                    'stock_movement_receipts'
                )
                    ->where('stock_movement_id', $movement->id)
                    ->exists();

                if ($hasReceipts) {
                    throw new \RuntimeException(
                        'El traslado ya tiene recepciones. Debe utilizar una reversa controlada.'
                    );
                }

                $openIncidents = \Illuminate\Support\Facades\DB::table(
                    'stock_movement_incidents'
                )
                    ->where('stock_movement_id', $movement->id)
                    ->where('status', 'open')
                    ->exists();

                if ($openIncidents) {
                    throw new \RuntimeException(
                        'El traslado tiene incidencias abiertas y no puede cancelarse directamente.'
                    );
                }

                if ($movement->status === 'in_transit') {
                    $movement->loadMissing('lines');

                    $transitLocationId = (int) (
                        $movement->transit_location_id ?? 0
                    );

                    $sourceLocationId = (int) (
                        $movement->source_location_id ?? 0
                    );

                    if (
                        $transitLocationId <= 0
                        || $sourceLocationId <= 0
                    ) {
                        throw new \RuntimeException(
                            'El traslado no tiene ubicaciones válidas para regresar el tránsito.'
                        );
                    }

                    $transitLocation =
                        \Illuminate\Support\Facades\DB::table(
                            'stock_locations'
                        )
                            ->where('id', $transitLocationId)
                            ->where('company_id', $companyId)
                            ->where('tracks_stock', true)
                            ->first();

                    $sourceLocation =
                        \Illuminate\Support\Facades\DB::table(
                            'stock_locations'
                        )
                            ->where('id', $sourceLocationId)
                            ->where('company_id', $companyId)
                            ->where('tracks_stock', true)
                            ->first();

                    if (! $transitLocation || ! $sourceLocation) {
                        throw new \RuntimeException(
                            'Las ubicaciones del traslado no pertenecen a la empresa activa o no controlan existencias.'
                        );
                    }

                    foreach ($movement->lines as $line) {
                        $qty = round(
                            (float) $line->done_quantity,
                            6
                        );

                        if ($qty <= 0) {
                            continue;
                        }

                        $productId = (int) $line->product_id;
                        $variantId = $line->product_variant_id
                            ? (int) $line->product_variant_id
                            : null;
                        $lotId = $line->lot_id
                            ? (int) $line->lot_id
                            : null;
                        $unitCost = $line->unit_cost !== null
                            ? (float) $line->unit_cost
                            : null;

                        static::decreaseQuant(
                            companyId: $companyId,
                            warehouseId: (int) $movement->warehouse_id,
                            locationId: $transitLocationId,
                            productId: $productId,
                            variantId: $variantId,
                            lotId: $lotId,
                            quantity: $qty,
                            unitCost: $unitCost
                        );

                        static::increaseQuant(
                            companyId: $companyId,
                            warehouseId: (int) $movement->warehouse_id,
                            locationId: $sourceLocationId,
                            productId: $productId,
                            variantId: $variantId,
                            lotId: $lotId,
                            quantity: $qty,
                            unitCost: $unitCost
                        );

                        static::returnTransferSerialsToOrigin(
                            $movement,
                            $line,
                            $companyId,
                            $transitLocationId,
                            $sourceLocationId
                        );
                    }
                }

                $movement->forceFill([
                    'status' => 'cancelled',
                    'cancelled_by' => auth()->id(),
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ])->save();
            }
        );
    }


    protected static function returnTransferSerialsToOrigin(
        StockMovement $movement,
        $line,
        int $companyId,
        int $transitLocationId,
        int $sourceLocationId
    ): void {
        if (! $line->stock_serial_number_id) {
            return;
        }

        $serialId = (int) $line->stock_serial_number_id;

        $serial = \Illuminate\Support\Facades\DB::table(
            'stock_serial_numbers'
        )
            ->where('id', $serialId)
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->first();

        if (! $serial) {
            throw new \RuntimeException(
                'No se encontró la serie #' . $serialId
                . ' para cancelar el traslado.'
            );
        }

        if (
            isset($serial->current_location_id)
            && (int) $serial->current_location_id !== $transitLocationId
        ) {
            throw new \RuntimeException(
                'La serie #' . $serialId
                . ' ya no se encuentra en la ubicación de tránsito.'
            );
        }

        if (
            isset($serial->current_warehouse_id)
            && (int) $serial->current_warehouse_id
                !== (int) $movement->warehouse_id
        ) {
            throw new \RuntimeException(
                'La serie #' . $serialId
                . ' no pertenece al almacén origen durante el tránsito.'
            );
        }

        static::moveSerial(
            $serialId,
            $companyId,
            (int) $movement->warehouse_id,
            $sourceLocationId
        );

        \Illuminate\Support\Facades\DB::table(
            'stock_serial_numbers'
        )
            ->where('id', $serialId)
            ->where('company_id', $companyId)
            ->update([
                'status' => 'available',
                'updated_at' => now(),
            ]);
    }

    public static function receiveMovementWithIncidents(
        StockMovement $movement,
        array $items,
        ?string $notes = null
    ): StockMovementReceipt {
        $companyId = static::currentCompanyId();

        if (! $companyId || (int) $movement->company_id !== $companyId) {
            Notification::make()
                ->title('Traslado no disponible')
                ->body('El traslado no pertenece a la empresa seleccionada.')
                ->danger()
                ->send();

            throw new Halt();
        }

        if (
            $movement->status !== 'in_transit'
            || ! $movement->isInterWarehouseTransfer()
        ) {
            Notification::make()
                ->title('Traslado no disponible para recepción')
                ->danger()
                ->send();

            throw new Halt();
        }

        if (
            ! Schema::hasTable('stock_movement_incidents')
            || ! Schema::hasColumn(
                'stock_movement_receipt_lines',
                'disposition'
            )
        ) {
            Notification::make()
                ->title('Esquema de incidencias no disponible')
                ->body('Falta aplicar la estructura H9M4D1.')
                ->danger()
                ->send();

            throw new Halt();
        }

        $movement->load('lines');

        static::validateTransfer($movement);

        if (! $movement->transit_location_id) {
            Notification::make()
                ->title('Traslado sin tránsito')
                ->body(
                    'El traslado no tiene ubicación de tránsito registrada.'
                )
                ->danger()
                ->send();

            throw new Halt();
        }

        $requested = [];

        foreach ($items as $lineId => $item) {
            if (! is_array($item)) {
                continue;
            }

            $received = round(
                max(0, (float) ($item['received'] ?? 0)),
                6
            );

            $damaged = round(
                max(0, (float) ($item['damaged'] ?? 0)),
                6
            );

            $missing = round(
                max(0, (float) ($item['missing'] ?? 0)),
                6
            );

            $reason = trim((string) ($item['reason'] ?? ''));

            if (($received + $damaged + $missing) <= 0) {
                continue;
            }

            $requested[(int) $lineId] = [
                'received' => $received,
                'damaged' => $damaged,
                'missing' => $missing,
                'reason' => $reason,
            ];
        }

        if ($requested === []) {
            Notification::make()
                ->title('Sin mercancía para procesar')
                ->body(
                    'Captura al menos una unidad como recibida, '
                    . 'dañada o faltante.'
                )
                ->danger()
                ->send();

            throw new Halt();
        }

        return DB::transaction(
            function () use (
                $movement,
                $requested,
                $notes,
                $companyId
            ): StockMovementReceipt {
                $movement = StockMovement::query()
                    ->whereKey($movement->getKey())
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($movement->status !== 'in_transit') {
                    Notification::make()
                        ->title('El traslado ya cambió de estado')
                        ->danger()
                        ->send();

                    throw new Halt();
                }

                $movement->load('lines');

                $lineIds = $movement->lines
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                foreach (array_keys($requested) as $lineId) {
                    if (! in_array((int) $lineId, $lineIds, true)) {
                        Notification::make()
                            ->title('Línea inválida')
                            ->body(
                                'Una de las líneas no pertenece '
                                . 'a este traslado.'
                            )
                            ->danger()
                            ->send();

                        throw new Halt();
                    }
                }

                $resolvedTotals = StockMovementReceiptLine::query()
                    ->selectRaw(
                        'stock_movement_line_id, '
                        . 'SUM(quantity) AS resolved_quantity'
                    )
                    ->whereIn(
                        'stock_movement_line_id',
                        $lineIds
                    )
                    ->groupBy('stock_movement_line_id')
                    ->pluck(
                        'resolved_quantity',
                        'stock_movement_line_id'
                    );

                foreach ($movement->lines as $line) {
                    $lineId = (int) $line->id;

                    if (! isset($requested[$lineId])) {
                        continue;
                    }

                    $request = $requested[$lineId];

                    $receivedNow = (float) $request['received'];
                    $damagedNow = (float) $request['damaged'];
                    $missingNow = (float) $request['missing'];

                    $resolveNow = round(
                        $receivedNow + $damagedNow + $missingNow,
                        6
                    );

                    $sent = round(
                        (float) $line->done_quantity,
                        6
                    );

                    $resolved = round(
                        (float) (
                            $resolvedTotals[$lineId] ?? 0
                        ),
                        6
                    );

                    $pending = round(
                        max(0, $sent - $resolved),
                        6
                    );

                    if ($resolveNow > $pending + 0.000001) {
                        Notification::make()
                            ->title('Cantidad mayor al pendiente')
                            ->body(
                                static::stockItemLabel(
                                    (int) $line->product_id,
                                    $line->product_variant_id
                                        ? (int) $line->product_variant_id
                                        : null
                                )
                                . ' tiene pendiente '
                                . number_format($pending, 6)
                                . ' y se intentó resolver '
                                . number_format($resolveNow, 6)
                                . '.'
                            )
                            ->danger()
                            ->send();

                        throw new Halt();
                    }

                    if (
                        ($damagedNow > 0 || $missingNow > 0)
                        && $request['reason'] === ''
                    ) {
                        Notification::make()
                            ->title('Motivo obligatorio')
                            ->body(
                                'Indica el motivo del daño o faltante en '
                                . static::stockItemLabel(
                                    (int) $line->product_id,
                                    $line->product_variant_id
                                        ? (int) $line->product_variant_id
                                        : null
                                )
                                . '.'
                            )
                            ->danger()
                            ->send();

                        throw new Halt();
                    }

                    if ($line->stock_serial_number_id) {
                        $validSerialLine =
                            abs($sent - 1.0) < 0.000001
                            && abs($pending - 1.0) < 0.000001
                            && abs($resolveNow - 1.0) < 0.000001;

                        $validDisposition =
                            (
                                abs($receivedNow - 1.0) < 0.000001
                                && $damagedNow == 0.0
                                && $missingNow == 0.0
                            )
                            || (
                                abs($damagedNow - 1.0) < 0.000001
                                && $receivedNow == 0.0
                                && $missingNow == 0.0
                            )
                            || (
                                abs($missingNow - 1.0) < 0.000001
                                && $receivedNow == 0.0
                                && $damagedNow == 0.0
                            );

                        if (! $validSerialLine || ! $validDisposition) {
                            Notification::make()
                                ->title(
                                    'Recepción inválida para número de serie'
                                )
                                ->body(
                                    'Cada serie debe clasificarse '
                                    . 'completa como recibida, dañada '
                                    . 'o faltante.'
                                )
                                ->danger()
                                ->send();

                            throw new Halt();
                        }
                    }
                }

                $receipt = StockMovementReceipt::create([
                    'stock_movement_id' => $movement->id,
                    'company_id' => $movement->company_id,
                    'received_by' => auth()->id(),
                    'received_at' => now(),
                    'notes' => filled($notes)
                        ? trim((string) $notes)
                        : null,
                ]);

                foreach ($movement->lines as $line) {
                    $lineId = (int) $line->id;

                    if (! isset($requested[$lineId])) {
                        continue;
                    }

                    $request = $requested[$lineId];

                    $productId = (int) $line->product_id;
                    $variantId = $line->product_variant_id
                        ? (int) $line->product_variant_id
                        : null;
                    $lotId = $line->lot_id
                        ? (int) $line->lot_id
                        : null;
                    $unitCost = $line->unit_cost !== null
                        ? (float) $line->unit_cost
                        : null;

                    $dispositions = [
                        'received' => (float) $request['received'],
                        'damaged' => (float) $request['damaged'],
                        'missing' => (float) $request['missing'],
                    ];

                    foreach ($dispositions as $disposition => $qty) {
                        $qty = round($qty, 6);

                        if ($qty <= 0) {
                            continue;
                        }

                        static::validateLineQuantity(
                            $line,
                            $qty
                        );

                        $targetWarehouseId = match ($disposition) {
                            'received',
                            'damaged' => (int) $movement
                                ->destination_warehouse_id,
                            'missing' => (int) $movement->warehouse_id,
                            default => throw new \RuntimeException(
                                'Disposición de recepción inválida.'
                            ),
                        };

                        $targetLocationId = match ($disposition) {
                            'received' => (int) $movement
                                ->destination_location_id,
                            'damaged' => static::
                                transferExceptionLocationId(
                                    (int) $movement->company_id,
                                    (int) $movement
                                        ->destination_warehouse_id,
                                    'CUARENTENA'
                                ),
                            'missing' => static::
                                transferExceptionLocationId(
                                    (int) $movement->company_id,
                                    null,
                                    'FALTANTE_TRANSITO'
                                ),
                            default => throw new \RuntimeException(
                                'Disposición de recepción inválida.'
                            ),
                        };

                        static::decreaseQuant(
                            companyId: (int) $movement->company_id,
                            warehouseId: (int) $movement->warehouse_id,
                            locationId: (int) $movement
                                ->transit_location_id,
                            productId: $productId,
                            variantId: $variantId,
                            lotId: $lotId,
                            quantity: $qty,
                            unitCost: $unitCost
                        );

                        static::increaseQuant(
                            companyId: (int) $movement->company_id,
                            warehouseId: $targetWarehouseId,
                            locationId: $targetLocationId,
                            productId: $productId,
                            variantId: $variantId,
                            lotId: $lotId,
                            quantity: $qty,
                            unitCost: $unitCost
                        );

                        $receiptLine = StockMovementReceiptLine::create([
                            'stock_movement_receipt_id' => $receipt->id,
                            'stock_movement_line_id' => $line->id,
                            'quantity' => $qty,
                            'disposition' => $disposition,
                            'stock_serial_number_id' =>
                                $line->stock_serial_number_id
                                    ? (int) $line
                                        ->stock_serial_number_id
                                    : null,
                            'reason' => in_array(
                                $disposition,
                                ['damaged', 'missing'],
                                true
                            )
                                ? $request['reason']
                                : null,
                        ]);

                        if (
                            in_array(
                                $disposition,
                                ['damaged', 'missing'],
                                true
                            )
                        ) {
                            DB::table(
                                'stock_movement_incidents'
                            )->insert([
                                'company_id' => $movement->company_id,
                                'stock_movement_id' => $movement->id,
                                'stock_movement_line_id' => $line->id,
                                'stock_movement_receipt_id' =>
                                    $receipt->id,
                                'stock_movement_receipt_line_id' =>
                                    $receiptLine->id,
                                'stock_serial_number_id' =>
                                    $line->stock_serial_number_id
                                        ? (int) $line
                                            ->stock_serial_number_id
                                        : null,
                                'incident_type' => $disposition,
                                'quantity' => $qty,
                                'status' => 'open',
                                'reason' => $request['reason'],
                                'created_by' => auth()->id(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        if ($line->stock_serial_number_id) {
                            static::moveSerial(
                                (int) $line->stock_serial_number_id,
                                (int) $movement->company_id,
                                $targetWarehouseId,
                                $targetLocationId
                            );

                            if (
                                in_array(
                                    $disposition,
                                    ['damaged', 'missing'],
                                    true
                                )
                            ) {
                                DB::table('stock_serial_numbers')
                                    ->where(
                                        'id',
                                        (int) $line
                                            ->stock_serial_number_id
                                    )
                                    ->where(
                                        'company_id',
                                        (int) $movement->company_id
                                    )
                                    ->update([
                                        'status' => 'blocked',
                                        'updated_at' => now(),
                                    ]);
                            }
                        }
                    }
                }

                $resolvedTotalsAfter =
                    StockMovementReceiptLine::query()
                        ->selectRaw(
                            'stock_movement_line_id, '
                            . 'SUM(quantity) AS resolved_quantity'
                        )
                        ->whereIn(
                            'stock_movement_line_id',
                            $lineIds
                        )
                        ->groupBy('stock_movement_line_id')
                        ->pluck(
                            'resolved_quantity',
                            'stock_movement_line_id'
                        );

                $allResolved = true;

                foreach ($movement->lines as $line) {
                    $sent = round(
                        (float) $line->done_quantity,
                        6
                    );

                    $resolved = round(
                        (float) (
                            $resolvedTotalsAfter[
                                (int) $line->id
                            ] ?? 0
                        ),
                        6
                    );

                    if ($resolved + 0.000001 < $sent) {
                        $allResolved = false;
                        break;
                    }
                }

                if ($allResolved) {
                    $movement->update([
                        'status' => 'done',
                        'received_by' => auth()->id(),
                        'received_at' => now(),
                        'confirmed_by' => auth()->id(),
                        'confirmed_at' => now(),
                    ]);
                } else {
                    $movement->update([
                        'status' => 'in_transit',
                        'received_by' => null,
                        'received_at' => null,
                    ]);
                }

                return $receipt->fresh('lines');
            }
        );
    }

    protected static function transferExceptionLocationId(
        int $companyId,
        ?int $warehouseId,
        string $code
    ): int {
        $query = DB::table('stock_locations')
            ->where('company_id', $companyId)
            ->whereRaw('upper(code) = ?', [strtoupper($code)])
            ->where('is_active', true)
            ->where('tracks_stock', true);

        if ($warehouseId === null) {
            $query->whereNull('warehouse_id');
        } else {
            $query->where('warehouse_id', $warehouseId);
        }

        $id = $query->orderBy('id')->value('id');

        if (! $id) {
            Notification::make()
                ->title('Ubicación de incidencia no disponible')
                ->body(
                    'No se encontró la ubicación '
                    . $code
                    . ' para esta empresa/almacén.'
                )
                ->danger()
                ->send();

            throw new Halt();
        }

        return (int) $id;
    }

    public static function resolveTransferIncident(
        int $incidentId,
        string $resolutionType,
        ?string $notes = null
    ): int {
        $companyId = static::currentCompanyId();

        if (! $companyId) {
            throw new \RuntimeException(
                'No hay empresa seleccionada.'
            );
        }

        $resolutionType = trim($resolutionType);
        $notes = trim((string) $notes);

        $allowed = [
            'recovered',
            'loss',
            'found_received',
            'returned_origin',
            'confirmed_loss',
        ];

        if (! in_array($resolutionType, $allowed, true)) {
            throw new \RuntimeException(
                'Tipo de resolución inválido.'
            );
        }

        return DB::transaction(function () use (
            $incidentId,
            $resolutionType,
            $notes,
            $companyId
        ): int {
            $incident = DB::table(
                'stock_movement_incidents'
            )
                ->where('id', $incidentId)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (! $incident) {
                throw new \RuntimeException(
                    'La incidencia no existe o no pertenece a la empresa seleccionada.'
                );
            }

            if ((string) $incident->status !== 'open') {
                throw new \RuntimeException(
                    'La incidencia ya fue resuelta o cancelada.'
                );
            }

            $incidentType = (string) $incident->incident_type;

            $allowedForIncident = match ($incidentType) {
                'damaged' => [
                    'recovered',
                    'loss',
                ],
                'missing' => [
                    'found_received',
                    'returned_origin',
                    'confirmed_loss',
                ],
                default => [],
            };

            if (
                ! in_array(
                    $resolutionType,
                    $allowedForIncident,
                    true
                )
            ) {
                throw new \RuntimeException(
                    'La resolución seleccionada no corresponde al tipo de incidencia.'
                );
            }

            $movement = StockMovement::query()
                ->whereKey($incident->stock_movement_id)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (! $movement) {
                throw new \RuntimeException(
                    'No se encontró el traslado de la incidencia.'
                );
            }

            $line = DB::table('stock_movement_lines')
                ->where('id', $incident->stock_movement_line_id)
                ->where(
                    'stock_movement_id',
                    $movement->getKey()
                )
                ->lockForUpdate()
                ->first();

            if (! $line || ! $line->product_id) {
                throw new \RuntimeException(
                    'No se encontró la línea de inventario de la incidencia.'
                );
            }

            $quantity = round(
                (float) $incident->quantity,
                6
            );

            if ($quantity <= 0) {
                throw new \RuntimeException(
                    'La incidencia no tiene cantidad válida.'
                );
            }

            $productId = (int) $line->product_id;

            $variantId = $line->product_variant_id
                ? (int) $line->product_variant_id
                : null;

            $lotId = $line->lot_id
                ? (int) $line->lot_id
                : null;

            $serialId = $incident->stock_serial_number_id
                ? (int) $incident->stock_serial_number_id
                : null;

            if ($serialId && abs($quantity - 1.0) > 0.000001) {
                throw new \RuntimeException(
                    'Una incidencia con serie debe resolverse por una sola unidad.'
                );
            }

            $unitCost = $line->unit_cost !== null
                ? (float) $line->unit_cost
                : null;

            $originWarehouseId =
                (int) $movement->warehouse_id;

            $destinationWarehouseId =
                (int) $movement->destination_warehouse_id;

            $originLocationId =
                (int) $movement->source_location_id;

            $destinationLocationId =
                (int) $movement->destination_location_id;

            $sourceWarehouseId = match ($incidentType) {
                'damaged' => $destinationWarehouseId,
                'missing' => $originWarehouseId,
                default => throw new \RuntimeException(
                    'Tipo de incidencia inválido.'
                ),
            };

            $sourceLocationCode = match ($incidentType) {
                'damaged' => 'CUARENTENA',
                'missing' => 'FALTANTE_TRANSITO',
                default => throw new \RuntimeException(
                    'Tipo de incidencia inválido.'
                ),
            };

            $sourceLocationId =
                static::transferExceptionLocationId(
                    $companyId,
                    $incidentType === 'damaged'
                        ? $destinationWarehouseId
                        : null,
                    $sourceLocationCode
                );

            if (! $sourceLocationId) {
                throw new \RuntimeException(
                    'No se encontró la ubicación origen de la incidencia.'
                );
            }

            $isLoss = in_array(
                $resolutionType,
                ['loss', 'confirmed_loss'],
                true
            );

            if ($isLoss) {
                $destination = DB::table(
                    'stock_locations as l'
                )
                    ->leftJoin(
                        'stock_location_types as t',
                        't.id',
                        '=',
                        'l.stock_location_type_id'
                    )
                    ->select([
                        'l.id',
                        'l.company_id',
                        'l.warehouse_id',
                        'l.code',
                        'l.name',
                        'l.is_active',
                        'l.tracks_stock',
                        't.code as type_code',
                    ])
                    ->where('l.company_id', $companyId)
                    ->where('l.is_active', true)
                    ->where(function ($query): void {
                        $query
                            ->where('t.code', 'LOSS')
                            ->orWhere('l.code', 'PERDIDA');
                    })
                    ->orderByRaw(
                        "CASE WHEN l.code='PERDIDA' THEN 0 ELSE 1 END"
                    )
                    ->orderBy('l.id')
                    ->first();

                if (! $destination) {
                    throw new \RuntimeException(
                        'La empresa no tiene ubicación de pérdida activa.'
                    );
                }

                $targetLocationId =
                    (int) $destination->id;

                $targetWarehouseId =
                    $destination->warehouse_id
                        ? (int) $destination->warehouse_id
                        : 0;
            } else {
                [$targetWarehouseId, $targetLocationId] =
                    match ($resolutionType) {
                        'recovered',
                        'found_received' => [
                            $destinationWarehouseId,
                            $destinationLocationId,
                        ],
                        'returned_origin' => [
                            $originWarehouseId,
                            $originLocationId,
                        ],
                        default => throw new \RuntimeException(
                            'Resolución física inválida.'
                        ),
                    };
            }

            static::decreaseQuant(
                companyId: $companyId,
                warehouseId: $sourceWarehouseId,
                locationId: $sourceLocationId,
                productId: $productId,
                variantId: $variantId,
                lotId: $lotId,
                quantity: $quantity,
                unitCost: $unitCost
            );

            /*
             * PERDIDA es virtual y tracks_stock=false.
             * Una baja disminuye inventario real pero no genera
             * un quant destino.
             */
            if (! $isLoss) {
                static::increaseQuant(
                    companyId: $companyId,
                    warehouseId: $targetWarehouseId,
                    locationId: $targetLocationId,
                    productId: $productId,
                    variantId: $variantId,
                    lotId: $lotId,
                    quantity: $quantity,
                    unitCost: $unitCost
                );
            }

            $operationTypeId = DB::table(
                'stock_operation_types'
            )
                ->where('company_id', $companyId)
                ->where(
                    'warehouse_id',
                    $sourceWarehouseId
                )
                ->where(
                    'operation_kind',
                    'inventory_adjustment'
                )
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');

            $resolutionMovement =
                StockMovement::create([
                    'company_id' => $companyId,
                    'warehouse_id' => $sourceWarehouseId,
                    'destination_warehouse_id' =>
                        $isLoss
                            ? null
                            : $targetWarehouseId,
                    'stock_operation_type_id' =>
                        $operationTypeId
                            ? (int) $operationTypeId
                            : null,
                    'source_location_id' =>
                        $sourceLocationId,
                    'destination_location_id' =>
                        $targetLocationId,
                    'movement_at' => now(),
                    'status' => 'done',
                    'origin_document' =>
                        'transfer_incident:'
                        . $incident->id,
                    'notes' =>
                        'Resolución de incidencia #'
                        . $incident->id
                        . ' · '
                        . $resolutionType
                        . (
                            $notes !== ''
                                ? ' · ' . $notes
                                : ''
                        ),
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                    'received_by' => auth()->id(),
                    'received_at' => now(),
                ]);

            DB::table('stock_movement_lines')->insert([
                'stock_movement_id' =>
                    $resolutionMovement->getKey(),
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'lot_id' => $lotId,
                'stock_serial_number_id' => $serialId,
                'requested_quantity' => $quantity,
                'done_quantity' => $quantity,
                'unit_cost' => $unitCost,
                'notes' =>
                    'Resolución incidencia #'
                    . $incident->id
                    . ' · '
                    . $resolutionType,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($serialId) {
                $serial = DB::table(
                    'stock_serial_numbers'
                )
                    ->where('id', $serialId)
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->first();

                if (! $serial) {
                    throw new \RuntimeException(
                        'No se encontró la serie de la incidencia.'
                    );
                }

                if (
                    (int) $serial->current_location_id
                    !== $sourceLocationId
                ) {
                    throw new \RuntimeException(
                        'La serie ya no se encuentra en la ubicación de la incidencia.'
                    );
                }

                if ($isLoss) {
                    DB::table(
                        'stock_serial_numbers'
                    )
                        ->where('id', $serialId)
                        ->where(
                            'company_id',
                            $companyId
                        )
                        ->update([
                            'status' => 'scrapped',
                            'current_warehouse_id' =>
                                $targetWarehouseId > 0
                                    ? $targetWarehouseId
                                    : null,
                            'current_location_id' =>
                                $targetLocationId,
                            'updated_at' => now(),
                        ]);

                    if (
                        Schema::hasTable(
                            'stock_serial_special_movements'
                        )
                    ) {
                        $special = [
                            'company_id' => $companyId,
                            'stock_serial_number_id' =>
                                $serialId,
                            'product_id' => $productId,
                            'product_variant_id' =>
                                $variantId,
                            'lot_id' => $lotId,
                            'movement_type' =>
                                \App\Models\StockSerialSpecialMovement::TYPE_SCRAP_LOSS,
                            'status' => 'confirmed',
                            'serial_number_before' =>
                                $serial->serial_number,
                            'serial_number_after' => null,
                            'source_warehouse_id' =>
                                $serial
                                    ->current_warehouse_id,
                            'source_location_id' =>
                                $sourceLocationId,
                            'destination_warehouse_id' =>
                                $targetWarehouseId > 0
                                    ? $targetWarehouseId
                                    : null,
                            'destination_location_id' =>
                                $targetLocationId,
                            'reason' =>
                                $notes !== ''
                                    ? $notes
                                    : 'Resolución de incidencia de traslado.',
                            'reference' =>
                                $resolutionMovement
                                    ->reference,
                            'notes' =>
                                'Incidencia #'
                                . $incident->id,
                            'created_by' =>
                                auth()->id(),
                            'confirmed_by' =>
                                auth()->id(),
                            'confirmed_at' => now(),
                            'metadata' => json_encode(
                                [
                                    'incident_id' =>
                                        (int) $incident->id,
                                    'resolution_type' =>
                                        $resolutionType,
                                    'source' =>
                                        'StockMovementResource.resolveTransferIncident',
                                ],
                                JSON_UNESCAPED_UNICODE
                                | JSON_UNESCAPED_SLASHES
                            ),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        $special = array_intersect_key(
                            $special,
                            array_flip(
                                Schema::getColumnListing(
                                    'stock_serial_special_movements'
                                )
                            )
                        );

                        DB::table(
                            'stock_serial_special_movements'
                        )->insert($special);
                    }
                } else {
                    static::moveSerial(
                        $serialId,
                        $companyId,
                        $targetWarehouseId,
                        $targetLocationId
                    );

                    DB::table(
                        'stock_serial_numbers'
                    )
                        ->where('id', $serialId)
                        ->where(
                            'company_id',
                            $companyId
                        )
                        ->update([
                            'status' => 'available',
                            'updated_at' => now(),
                        ]);
                }
            }

            DB::table(
                'stock_movement_incidents'
            )
                ->where('id', $incident->id)
                ->where('company_id', $companyId)
                ->update([
                    'status' => 'resolved',
                    'resolution_type' =>
                        $resolutionType,
                    'resolution_location_id' =>
                        $targetLocationId,
                    'resolution_stock_movement_id' =>
                        $resolutionMovement->getKey(),
                    'resolution_notes' =>
                        $notes !== ''
                            ? $notes
                            : null,
                    'resolved_by' => auth()->id(),
                    'resolved_at' => now(),
                    'updated_at' => now(),
                ]);

            return (int) $resolutionMovement->getKey();
        });
    }


    public static function receiveMovement(
        StockMovement $movement,
        array $quantities,
        ?string $notes = null
    ): StockMovementReceipt {
        $companyId = static::currentCompanyId();

        if (! $companyId || (int) $movement->company_id !== $companyId) {
            Notification::make()
                ->title('Traslado no disponible')
                ->body('El traslado no pertenece a la empresa seleccionada.')
                ->danger()
                ->send();

            throw new Halt();
        }

        if ($movement->status !== 'in_transit' || ! $movement->isInterWarehouseTransfer()) {
            Notification::make()
                ->title('Traslado no disponible para recepción')
                ->danger()
                ->send();

            throw new Halt();
        }

        $movement->load('lines');

        static::validateTransfer($movement);

        if (! $movement->transit_location_id) {
            Notification::make()
                ->title('Traslado sin tránsito')
                ->body('El traslado no tiene ubicación de tránsito registrada.')
                ->danger()
                ->send();

            throw new Halt();
        }

        $requested = [];

        foreach ($quantities as $lineId => $quantity) {
            $qty = round((float) $quantity, 6);

            if ($qty > 0) {
                $requested[(int) $lineId] = $qty;
            }
        }

        if ($requested === []) {
            Notification::make()
                ->title('Sin cantidades para recibir')
                ->body('Captura al menos una cantidad mayor a cero.')
                ->danger()
                ->send();

            throw new Halt();
        }

        return DB::transaction(function () use ($movement, $requested, $notes, $companyId): StockMovementReceipt {
            $movement = StockMovement::query()
                ->whereKey($movement->getKey())
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($movement->status !== 'in_transit') {
                Notification::make()
                    ->title('El traslado ya cambió de estado')
                    ->danger()
                    ->send();

                throw new Halt();
            }

            $movement->load('lines');

            $lineIds = $movement->lines
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            foreach (array_keys($requested) as $lineId) {
                if (! in_array((int) $lineId, $lineIds, true)) {
                    Notification::make()
                        ->title('Línea inválida')
                        ->body('Una de las líneas no pertenece a este traslado.')
                        ->danger()
                        ->send();

                    throw new Halt();
                }
            }

            $receivedTotals = StockMovementReceiptLine::query()
                ->selectRaw('stock_movement_line_id, SUM(quantity) AS received_quantity')
                ->whereIn('stock_movement_line_id', $lineIds)
                ->groupBy('stock_movement_line_id')
                ->pluck('received_quantity', 'stock_movement_line_id');

            foreach ($movement->lines as $line) {
                $lineId = (int) $line->id;
                $receiveNow = (float) ($requested[$lineId] ?? 0);

                if ($receiveNow <= 0) {
                    continue;
                }

                $sent = round((float) $line->done_quantity, 6);
                $received = round((float) ($receivedTotals[$lineId] ?? 0), 6);
                $pending = round(max(0, $sent - $received), 6);

                if ($receiveNow > $pending + 0.000001) {
                    Notification::make()
                        ->title('Cantidad mayor al pendiente')
                        ->body(
                            static::stockItemLabel(
                                (int) $line->product_id,
                                $line->product_variant_id ? (int) $line->product_variant_id : null
                            )
                            . ' tiene pendiente '
                            . number_format($pending, 6)
                            . ' y se intentó recibir '
                            . number_format($receiveNow, 6)
                            . '.'
                        )
                        ->danger()
                        ->send();

                    throw new Halt();
                }
            }

            $receipt = StockMovementReceipt::create([
                'stock_movement_id' => $movement->id,
                'company_id' => $movement->company_id,
                'received_by' => auth()->id(),
                'received_at' => now(),
                'notes' => filled($notes) ? trim((string) $notes) : null,
            ]);

            foreach ($movement->lines as $line) {
                $lineId = (int) $line->id;
                $qty = (float) ($requested[$lineId] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                static::validateLineQuantity($line, $qty);

                $productId = (int) $line->product_id;
                $variantId = $line->product_variant_id ? (int) $line->product_variant_id : null;
                $lotId = $line->lot_id ? (int) $line->lot_id : null;
                $unitCost = $line->unit_cost !== null ? (float) $line->unit_cost : null;

                static::decreaseQuant(
                    companyId: (int) $movement->company_id,
                    warehouseId: (int) $movement->warehouse_id,
                    locationId: (int) $movement->transit_location_id,
                    productId: $productId,
                    variantId: $variantId,
                    lotId: $lotId,
                    quantity: $qty,
                    unitCost: $unitCost
                );

                static::increaseQuant(
                    companyId: (int) $movement->company_id,
                    warehouseId: (int) $movement->destination_warehouse_id,
                    locationId: (int) $movement->destination_location_id,
                    productId: $productId,
                    variantId: $variantId,
                    lotId: $lotId,
                    quantity: $qty,
                    unitCost: $unitCost
                );

                StockMovementReceiptLine::create([
                    'stock_movement_receipt_id' => $receipt->id,
                    'stock_movement_line_id' => $line->id,
                    'quantity' => $qty,
                ]);

                /*
                 * Un número de serie representa una unidad indivisible.
                 * Sólo puede moverse cuando la línea queda totalmente recibida.
                 */
                if ($line->stock_serial_number_id) {
                    $receivedAfter = StockMovementReceiptLine::query()
                        ->where('stock_movement_line_id', $line->id)
                        ->sum('quantity');

                    if ((float) $receivedAfter + 0.000001 >= (float) $line->done_quantity) {
                        static::moveSerial(
                            (int) $line->stock_serial_number_id,
                            (int) $movement->company_id,
                            (int) $movement->destination_warehouse_id,
                            (int) $movement->destination_location_id
                        );
                    }
                }
            }

            $allReceived = true;

            $receivedTotalsAfter = StockMovementReceiptLine::query()
                ->selectRaw('stock_movement_line_id, SUM(quantity) AS received_quantity')
                ->whereIn('stock_movement_line_id', $lineIds)
                ->groupBy('stock_movement_line_id')
                ->pluck('received_quantity', 'stock_movement_line_id');

            foreach ($movement->lines as $line) {
                $sent = round((float) $line->done_quantity, 6);
                $received = round(
                    (float) ($receivedTotalsAfter[(int) $line->id] ?? 0),
                    6
                );

                if ($received + 0.000001 < $sent) {
                    $allReceived = false;
                    break;
                }
            }

            if ($allReceived) {
                $movement->update([
                    'status' => 'done',
                    'received_by' => auth()->id(),
                    'received_at' => now(),
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                ]);
            } else {
                /*
                 * received_by / received_at del encabezado representan
                 * la recepción FINAL del traslado.
                 * Cada parcialidad queda auditada en stock_movement_receipts.
                 */
                $movement->update([
                    'status' => 'in_transit',
                    'received_by' => null,
                    'received_at' => null,
                ]);
            }

            return $receipt->fresh('lines');
        });
    }

    protected static function validateLineQuantity($line, float $qty): void
    {
        if (! $line->product_id || $qty <= 0) {
            Notification::make()
                ->title('Cantidad inválida')
                ->body('Todas las líneas deben tener producto y cantidad mayor a cero.')
                ->danger()
                ->send();

            throw new Halt();
        }
    }

    protected static function decreaseQuant(
        int $companyId,
        int $warehouseId,
        int $locationId,
        int $productId,
        ?int $variantId,
        ?int $lotId,
        float $quantity,
        ?float $unitCost = null
    ): void {
        $quant = static::findOrNewQuant(
            $companyId,
            $warehouseId,
            $locationId,
            $productId,
            $variantId,
            $lotId
        );

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
        ?int $lotId,
        float $quantity,
        ?float $unitCost = null
    ): void {
        $quant = static::findOrNewQuant(
            $companyId,
            $warehouseId,
            $locationId,
            $productId,
            $variantId,
            $lotId
        );

        $currentQuantity = (float) $quant->quantity;
        $newQuantity = $currentQuantity + $quantity;

        if ($unitCost !== null) {
            $currentCost = $quant->average_cost !== null
                ? (float) $quant->average_cost
                : null;

            if ($currentCost !== null && $currentQuantity > 0 && $newQuantity > 0) {
                $quant->average_cost =
                    (($currentQuantity * $currentCost) + ($quantity * $unitCost))
                    / $newQuantity;
            } else {
                $quant->average_cost = $unitCost;
            }
        }

        $quant->quantity = $newQuantity;
        $quant->save();
    }

    protected static function findOrNewQuant(
        int $companyId,
        int $warehouseId,
        int $locationId,
        int $productId,
        ?int $variantId,
        ?int $lotId
    ): StockQuant {
        $query = StockQuant::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId);

        $variantId
            ? $query->where('product_variant_id', $variantId)
            : $query->whereNull('product_variant_id');

        if (Schema::hasColumn('stock_quants', 'lot_id')) {
            $lotId
                ? $query->where('lot_id', $lotId)
                : $query->whereNull('lot_id');
        }

        $quant = $query
            ->lockForUpdate()
            ->first();

        if ($quant) {
            return $quant;
        }

        $quant = new StockQuant([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'reserved_quantity' => 0,
            'quantity' => 0,
        ]);

        if (Schema::hasColumn('stock_quants', 'lot_id')) {
            $quant->lot_id = $lotId;
        }

        return $quant;
    }

    protected static function assertWarehouseCompany(
        int $companyId,
        int $warehouseId
    ): void {
        $valid = DB::table('warehouses')
            ->where('id', $warehouseId)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->exists();

        if (! $valid) {
            Notification::make()
                ->title('Almacén inválido')
                ->body('El almacén no pertenece a la empresa actual.')
                ->danger()
                ->send();

            throw new Halt();
        }
    }

    protected static function assertMovementLocation(
        int $companyId,
        int $warehouseId,
        int $locationId,
        string $label
    ): void {
        $location = DB::table('stock_locations')
            ->where('id', $locationId)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->first([
                'id',
                'warehouse_id',
                'code',
                'name',
            ]);

        $valid = $location
            && (
                $location->warehouse_id === null
                || (int) $location->warehouse_id === $warehouseId
            );

        if (! $valid) {
            Notification::make()
                ->title('Ubicación inválida')
                ->body(
                    'La ubicación '
                    . $label
                    . ' no pertenece al almacén o a las ubicaciones virtuales de la empresa actual.'
                )
                ->danger()
                ->send();

            throw new Halt();
        }
    }

    protected static function assertInternalLocation(
        int $companyId,
        int $warehouseId,
        int $locationId,
        string $label
    ): void {
        $valid = DB::table('stock_locations as sl')
            ->leftJoin(
                'stock_location_types as t',
                't.id',
                '=',
                'sl.stock_location_type_id'
            )
            ->where('sl.id', $locationId)
            ->where('sl.company_id', $companyId)
            ->where('sl.warehouse_id', $warehouseId)
            ->where('sl.is_active', true)
            ->where(function ($query): void {
                $query
                    ->where('t.is_internal', true)
                    ->orWhereNull('t.id');
            })
            ->exists();

        if (! $valid) {
            Notification::make()
                ->title('Ubicación inválida')
                ->body('La ubicación ' . $label . ' no pertenece al almacén seleccionado.')
                ->danger()
                ->send();

            throw new Halt();
        }
    }

    protected static function transitLocationId(int $companyId): ?int
    {
        $query = DB::table('stock_locations as sl')
            ->leftJoin(
                'stock_location_types as t',
                't.id',
                '=',
                'sl.stock_location_type_id'
            )
            ->where('sl.company_id', $companyId)
            ->whereNull('sl.warehouse_id')
            ->where('sl.is_active', true)
            ->where(function ($query): void {
                $query
                    ->where('t.code', 'TRANSIT')
                    ->orWhereIn(DB::raw('upper(sl.code)'), ['TRANSIT', 'TRANSITO']);
            });

        if (Schema::hasColumn('stock_locations', 'tracks_stock')) {
            $query->where('sl.tracks_stock', true);
        }

        $preferred = (clone $query)
            ->whereRaw("upper(sl.code) = 'TRANSITO'")
            ->value('sl.id');

        if ($preferred) {
            return (int) $preferred;
        }

        $id = $query->orderBy('sl.id')->value('sl.id');

        return $id ? (int) $id : null;
    }

    protected static function moveSerial(
        ?int $serialId,
        int $companyId,
        int $warehouseId,
        int $locationId
    ): void {
        if (
            ! $serialId
            || ! Schema::hasTable('stock_serial_numbers')
        ) {
            return;
        }

        $values = [];

        if (Schema::hasColumn('stock_serial_numbers', 'current_warehouse_id')) {
            $values['current_warehouse_id'] = $warehouseId;
        }

        if (Schema::hasColumn('stock_serial_numbers', 'current_location_id')) {
            $values['current_location_id'] = $locationId;
        }

        if ($values === []) {
            return;
        }

        $query = DB::table('stock_serial_numbers')
            ->where('id', $serialId);

        if (Schema::hasColumn('stock_serial_numbers', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        $updated = $query->update($values);

        if ($updated < 1) {
            Notification::make()
                ->title('Serie inválida')
                ->body('No se pudo actualizar la serie #' . $serialId . '.')
                ->danger()
                ->send();

            throw new Halt();
        }
    }

    protected static function locationAffectsStock(int $locationId): bool
    {
        if (
            ! Schema::hasTable('stock_locations')
            || ! Schema::hasColumn('stock_locations', 'tracks_stock')
        ) {
            return false;
        }

        return (bool) DB::table('stock_locations')
            ->where('id', $locationId)
            ->value('tracks_stock');
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

        if (! $companyId) {
            return [];
        }

        $query->where('stock_operation_types.company_id', $companyId);

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

        $companyId = static::currentCompanyId();

        if (! $companyId) {
            return null;
        }

        return DB::table('stock_operation_types')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('operation_kind', 'internal_transfer')
            ->first();
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

    protected static function movementLocationOptions($warehouseId): array
    {
        if (! Schema::hasTable('stock_locations')) {
            return [];
        }

        $companyId = static::currentCompanyId();

        $query = DB::table('stock_locations as sl')
            ->leftJoin(
                'stock_location_types as t',
                't.id',
                '=',
                'sl.stock_location_type_id'
            )
            ->where('sl.is_active', true)
            ->where(function ($query) use ($warehouseId): void {
                if ($warehouseId) {
                    $query
                        ->where('sl.warehouse_id', (int) $warehouseId)
                        ->orWhereNull('sl.warehouse_id');
                } else {
                    $query->whereNull('sl.warehouse_id');
                }
            });

        if ($companyId) {
            $query->where('sl.company_id', $companyId);
        } else {
            $query->whereNull('sl.company_id');
        }

        return $query
            ->orderByRaw('sl.warehouse_id nulls last')
            ->orderBy('sl.name')
            ->get([
                'sl.id',
                'sl.warehouse_id',
                'sl.code',
                'sl.name',
                't.code as type_code',
            ])
            ->mapWithKeys(function ($location): array {
                $isVirtual = $location->warehouse_id === null;

                $prefix = $isVirtual ? 'Virtual / ' : '';

                $label = trim(
                    $prefix
                    . ($location->code ? $location->code . ' - ' : '')
                    . $location->name
                );

                return [
                    $location->id => $label,
                ];
            })
            ->all();
    }

    protected static function internalLocationOptions($warehouseId): array
    {
        if (! $warehouseId || ! Schema::hasTable('stock_locations')) {
            return [];
        }

        $companyId = static::currentCompanyId();

        $query = DB::table('stock_locations as sl')
            ->leftJoin(
                'stock_location_types as t',
                't.id',
                '=',
                'sl.stock_location_type_id'
            )
            ->where('sl.warehouse_id', (int) $warehouseId)
            ->where('sl.is_active', true)
            ->where(function ($query): void {
                $query
                    ->where('t.is_internal', true)
                    ->orWhereNull('t.id');
            });

        if ($companyId) {
            $query->where('sl.company_id', $companyId);
        } else {
            $query->whereNull('sl.company_id');
        }

        return $query
            ->orderBy('sl.name')
            ->get(['sl.id', 'sl.code', 'sl.name'])
            ->mapWithKeys(fn ($location): array => [
                $location->id => trim(
                    ($location->code ? $location->code . ' - ' : '')
                    . $location->name
                ),
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

        return in_array($status, ['in_transit', 'done', 'cancelled'], true);
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

    protected static function recordBelongsToCurrentCompany(Model $record): bool
    {
        if (! $record instanceof StockMovement) {
            return false;
        }

        $companyId = static::currentCompanyId();

        return $companyId !== null
            && (int) $record->company_id === $companyId;
    }

    public static function canCreate(): bool
    {
        return static::userCanPermission('inventory.movements.create');
    }

    public static function canEdit(Model $record): bool
    {
        /*
         * La ruta Edit funciona también como vista operativa/consulta.
         *
         * in_transit:
         *   permite entrar para recibir.
         *
         * done / cancelled:
         *   permite consultar y reimprimir PDF, pero el formulario
         *   permanece bloqueado y no muestra acciones de guardado.
         */
        return static::recordBelongsToCurrentCompany($record)
            && static::userCanPermission('inventory.movements.update');
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof StockMovement
            && static::recordBelongsToCurrentCompany($record)
            && $record->status === 'draft'
            && static::userCanPermission('inventory.movements.delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::userCanPermission('inventory.movements.delete');
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
