<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockSerialNumberResource\Pages;
use App\Models\StockSerialNumber;
use App\Models\StockSerialSpecialMovement;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockSerialNumberResource extends Resource
{
    protected static ?string $model = StockSerialNumber::class;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Números de serie';

    protected static ?string $modelLabel = 'número de serie';

    protected static ?string $pluralModelLabel = 'números de serie';

    protected static ?int $navigationSort = 70;

    protected static bool $isScopedToTenant = false;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && static::canManage('inventory.menu.view');
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && static::canManage('inventory.menu.view');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = StockSerialNumber::query()
            ->with(['lot', 'warehouse', 'location']);

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where('company_id', $companyId);
        } else {
            $query->whereNull('company_id');
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => static::currentCompanyId())
                    ->dehydrated(true),

                Forms\Components\Section::make('Datos del número de serie')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Producto')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => static::productSearchOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => static::productLabel($value))
                            ->required()
                            ->helperText('Solo aparecen productos configurados con seguimiento por número de serie.')
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set): void {
                                $set('product_variant_id', null);
                                $set('lot_id', null);
                            })
                            ->columnSpan(5),

                        Forms\Components\Select::make('product_variant_id')
                            ->label('Variante')
                            ->options(fn (Forms\Get $get): array => static::variantOptions($get('product_id')))
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Sin variante')
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set): void {
                                $set('lot_id', null);
                            })
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('serial_number')
                            ->label('Número de serie')
                            ->required()
                            ->maxLength(160)
                            ->columnSpan(3),

                        Forms\Components\Select::make('lot_id')
                            ->label('Lote')
                            ->options(fn (Forms\Get $get): array => static::lotOptions($get('product_id'), $get('product_variant_id')))
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Sin lote')
                            ->columnSpan(4),

                        Forms\Components\Select::make('current_warehouse_id')
                            ->label('Almacén actual')
                            ->options(fn (): array => static::warehouseOptions())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Sin almacén')
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set): void {
                                $set('current_location_id', null);
                            })
                            ->columnSpan(4),

                        Forms\Components\Select::make('current_location_id')
                            ->label('Ubicación actual')
                            ->options(fn (Forms\Get $get): array => static::locationOptions($get('current_warehouse_id')))
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Sin ubicación')
                            ->columnSpan(4),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options(static::statusOptions())
                            ->default('available')
                            ->required()
                            ->native(false)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('source_type')
                            ->label('Origen')
                            ->placeholder('Ej. manual, purchase_receipt, pos_order')
                            ->maxLength(80)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('source_id')
                            ->label('ID origen')
                            ->numeric()
                            ->columnSpan(4),
                    ])
                    ->columns(12),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serie')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_label')
                    ->label('Producto')
                    ->state(fn (StockSerialNumber $record): string => static::productLabel($record->product_id) ?: '—')
                    ->searchable(false)
                    ->sortable(false)
                    ->wrap(),

                Tables\Columns\TextColumn::make('lot.lot_number')
                    ->label('Lote')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Almacén')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('location.name')
                    ->label('Ubicación')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                    ->color(fn (?string $state): string => static::statusColor($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('source_type')
                    ->label('Origen')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(static::statusOptions()),

                Tables\Filters\SelectFilter::make('current_warehouse_id')
                    ->label('Almacén')
                    ->options(fn (): array => static::warehouseOptions()),
            ])
            ->actions([

                \Filament\Tables\Actions\ViewAction::make()
                    ->label('Ver detalle'),

                Tables\Actions\EditAction::make()
                    ->label('Editar'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            
            
            'print' => Pages\PrintStockSerialNumber::route('/{record}/print'),'view' => Pages\ViewStockSerialNumber::route('/{record}/view'),'index' => Pages\ListStockSerialNumbers::route('/'),
            'create' => Pages\CreateStockSerialNumber::route('/create'),
            'edit' => Pages\EditStockSerialNumber::route('/{record}/edit'),
        ];
    }

    protected static function statusOptions(): array
    {
        return [
            'available' => 'Disponible',
            'reserved' => 'Reservado',
            'sold' => 'Vendido',
            'delivered' => 'Entregado',
            'consumed' => 'Consumido',
            'returned' => 'Devuelto',
            'blocked' => 'Bloqueado',
            'scrapped' => 'Merma / desecho',
            'lost' => 'Perdido',
        ];
    }

    protected static function statusLabel(?string $state): string
    {
        return static::statusOptions()[$state ?: ''] ?? ($state ?: 'Sin estado');
    }

    protected static function statusColor(?string $state): string
    {
        return match ($state) {
            'available' => 'success',
            'reserved', 'returned' => 'warning',
            'sold', 'delivered', 'consumed' => 'info',
            'blocked' => 'gray',
            'scrapped', 'lost' => 'danger',
            default => 'gray',
        };
    }

    protected static function productSearchOptions(string $search): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        $query = DB::table('products')
            ->where('is_active', true);

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('products', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if (Schema::hasColumn('products', 'tracking')) {
            $query->where('tracking', 'serial');
        }

        $query->where(function ($q) use ($search): void {
            $q->where('name', 'ilike', '%' . $search . '%')
                ->orWhere('sku', 'ilike', '%' . $search . '%')
                ->orWhere('internal_reference', 'ilike', '%' . $search . '%')
                ->orWhere('barcode', 'ilike', '%' . $search . '%');
        });

        return $query
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => static::productLabel($row->id) ?: ('Producto #' . $row->id)])
            ->all();
    }

    protected static function variantOptions($productId): array
    {
        if (! $productId || ! Schema::hasTable('products') || ! Schema::hasColumn('products', 'parent_product_id')) {
            return [];
        }

        return DB::table('products')
            ->where('parent_product_id', (int) $productId)
            ->where('is_active', true)
            ->orderBy('variant_name')
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => static::productLabel($row->id) ?: ('Variante #' . $row->id)])
            ->all();
    }

    protected static function lotOptions($productId, $variantId = null): array
    {
        if (! Schema::hasTable('stock_lots')) {
            return [];
        }

        $query = DB::table('stock_lots');

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('stock_lots', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if ($productId) {
            $query->where('product_id', (int) $productId);
        }

        if ($variantId) {
            $query->where('product_variant_id', (int) $variantId);
        }

        return $query
            ->orderBy('lot_number')
            ->limit(100)
            ->get()
            ->mapWithKeys(function ($row): array {
                $label = trim((string) ($row->lot_number ?? ''));

                if (! empty($row->expiration_date)) {
                    $label .= ' - Cad. ' . date('d/m/Y', strtotime((string) $row->expiration_date));
                }

                return [$row->id => $label !== '' ? $label : ('Lote #' . $row->id)];
            })
            ->all();
    }

    protected static function warehouseOptions(): array
    {
        if (! Schema::hasTable('warehouses')) {
            return [];
        }

        $query = DB::table('warehouses');

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('warehouses', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if (Schema::hasColumn('warehouses', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => trim((string) ($row->name ?? ('Almacén #' . $row->id)))])
            ->all();
    }

    protected static function locationOptions($warehouseId): array
    {
        if (! Schema::hasTable('stock_locations')) {
            return [];
        }

        $query = DB::table('stock_locations');

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('stock_locations', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if ($warehouseId && Schema::hasColumn('stock_locations', 'warehouse_id')) {
            $query->where('warehouse_id', (int) $warehouseId);
        }

        if (Schema::hasColumn('stock_locations', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function ($row): array {
                $code = trim((string) ($row->code ?? ''));
                $name = trim((string) ($row->name ?? ''));

                if ($code !== '' && $name !== '') {
                    return [$row->id => $name . ' (' . $code . ')'];
                }

                return [$row->id => $name !== '' ? $name : ($code !== '' ? $code : ('Ubicación #' . $row->id))];
            })
            ->all();
    }

    protected static function productLabel($productId): ?string
    {
        if (! $productId || ! Schema::hasTable('products')) {
            return null;
        }

        $row = DB::table('products')->where('id', $productId)->first();

        if (! $row) {
            return null;
        }

        $code = trim((string) ($row->internal_reference ?? $row->sku ?? $row->barcode ?? ''));
        $name = trim((string) ($row->name ?? ''));

        if ($code !== '' && $name !== '') {
            return $code . ' - ' . $name;
        }

        return $name !== '' ? $name : ($code !== '' ? $code : ('Producto #' . $productId));
    }

    public static function relocateSerialRecord(StockSerialNumber $record, array $data): null
    {
        $destinationLocationId = (int) ($data['destination_location_id'] ?? 0);
        $reason = trim((string) ($data['reason'] ?? ''));
        $reference = trim((string) ($data['reference'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));

        if ($destinationLocationId <= 0) {
            Notification::make()
                ->title('Ubicación destino requerida')
                ->body('Selecciona la ubicación interna destino.')
                ->danger()
                ->send();

            throw new \Filament\Support\Exceptions\Halt();
        }

        if ($reason === '') {
            Notification::make()
                ->title('Motivo requerido')
                ->body('Captura el motivo de la reubicación.')
                ->danger()
                ->send();

            throw new \Filament\Support\Exceptions\Halt();
        }

        DB::transaction(function () use ($record, $destinationLocationId, $reason, $reference, $notes): void {
            /** @var StockSerialNumber $locked */
            $locked = StockSerialNumber::query()
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $allowedStatuses = ['available', 'blocked'];

            if (! in_array((string) $locked->status, $allowedStatuses, true)) {
                Notification::make()
                    ->title('La serie no se puede reubicar')
                    ->body('Solo se permiten series disponibles o bloqueadas. Estado actual: ' . static::statusLabel($locked->status))
                    ->danger()
                    ->send();

                throw new \Filament\Support\Exceptions\Halt();
            }

            $sourceWarehouseId = $locked->current_warehouse_id;
            $sourceLocationId = $locked->current_location_id;

            if (! $sourceWarehouseId || ! $sourceLocationId) {
                Notification::make()
                    ->title('Origen incompleto')
                    ->body('La serie no tiene almacén o ubicación origen válida.')
                    ->danger()
                    ->send();

                throw new \Filament\Support\Exceptions\Halt();
            }

            if ((int) $sourceLocationId === $destinationLocationId) {
                Notification::make()
                    ->title('Destino igual al origen')
                    ->body('Selecciona una ubicación destino diferente.')
                    ->warning()
                    ->send();

                throw new \Filament\Support\Exceptions\Halt();
            }

            $destination = DB::table('stock_locations as l')
                ->leftJoin('stock_location_types as t', 't.id', '=', 'l.stock_location_type_id')
                ->leftJoin('warehouses as w', 'w.id', '=', 'l.warehouse_id')
                ->select([
                    'l.id',
                    'l.company_id',
                    'l.warehouse_id',
                    'l.name',
                    'l.code',
                    'l.is_active',
                    't.code as type_code',
                    't.name as type_name',
                    'w.name as warehouse_name',
                ])
                ->where('l.id', $destinationLocationId)
                ->where('l.company_id', $locked->company_id)
                ->where('l.is_active', true)
                ->where('t.code', 'INTERNAL')
                ->whereNotNull('l.warehouse_id')
                ->first();

            if (! $destination) {
                Notification::make()
                    ->title('Ubicación destino inválida')
                    ->body('La ubicación destino debe ser interna, activa y de la misma empresa.')
                    ->danger()
                    ->send();

                throw new \Filament\Support\Exceptions\Halt();
            }

            $beforeSnapshot = [
                'id' => $locked->getKey(),
                'serial_number' => $locked->serial_number,
                'status' => $locked->status,
                'company_id' => $locked->company_id,
                'product_id' => $locked->product_id,
                'product_variant_id' => $locked->product_variant_id,
                'lot_id' => $locked->lot_id,
                'current_warehouse_id' => $locked->current_warehouse_id,
                'current_location_id' => $locked->current_location_id,
            ];

            $quantAdjustment = [
                'source' => null,
                'destination' => null,
            ];

            if (Schema::hasTable('stock_quants') && $locked->product_id) {
                $originQuery = DB::table('stock_quants')
                    ->where('company_id', $locked->company_id)
                    ->where('warehouse_id', $sourceWarehouseId)
                    ->where('location_id', $sourceLocationId)
                    ->where('product_id', $locked->product_id);

                if (! empty($locked->product_variant_id)) {
                    $originQuery->where('product_variant_id', $locked->product_variant_id);
                } else {
                    $originQuery->whereNull('product_variant_id');
                }

                if (Schema::hasColumn('stock_quants', 'lot_id')) {
                    if (! empty($locked->lot_id)) {
                        $originQuery->where('lot_id', $locked->lot_id);
                    } else {
                        $originQuery->whereNull('lot_id');
                    }
                }

                $originQuant = $originQuery->lockForUpdate()->first();

                if ($originQuant) {
                    $originBefore = (float) ($originQuant->quantity ?? 0);
                    $originAfter = max(0, $originBefore - 1);

                    DB::table('stock_quants')
                        ->where('id', $originQuant->id)
                        ->update(array_intersect_key([
                            'quantity' => $originAfter,
                            'updated_at' => now(),
                        ], array_flip(Schema::getColumnListing('stock_quants'))));

                    $quantAdjustment['source'] = [
                        'action' => 'decrement',
                        'quant_id' => (int) $originQuant->id,
                        'before_quantity' => $originBefore,
                        'after_quantity' => $originAfter,
                    ];
                } else {
                    $quantAdjustment['source'] = [
                        'action' => 'not_found',
                        'message' => 'No se encontró stock_quant origen para descontar.',
                    ];
                }

                $destinationQuery = DB::table('stock_quants')
                    ->where('company_id', $locked->company_id)
                    ->where('warehouse_id', $destination->warehouse_id)
                    ->where('location_id', $destination->id)
                    ->where('product_id', $locked->product_id);

                if (! empty($locked->product_variant_id)) {
                    $destinationQuery->where('product_variant_id', $locked->product_variant_id);
                } else {
                    $destinationQuery->whereNull('product_variant_id');
                }

                if (Schema::hasColumn('stock_quants', 'lot_id')) {
                    if (! empty($locked->lot_id)) {
                        $destinationQuery->where('lot_id', $locked->lot_id);
                    } else {
                        $destinationQuery->whereNull('lot_id');
                    }
                }

                $destinationQuant = $destinationQuery->lockForUpdate()->first();

                if ($destinationQuant) {
                    $destinationBefore = (float) ($destinationQuant->quantity ?? 0);
                    $destinationAfter = $destinationBefore + 1;

                    DB::table('stock_quants')
                        ->where('id', $destinationQuant->id)
                        ->update(array_intersect_key([
                            'quantity' => $destinationAfter,
                            'updated_at' => now(),
                        ], array_flip(Schema::getColumnListing('stock_quants'))));

                    $quantAdjustment['destination'] = [
                        'action' => 'increment',
                        'quant_id' => (int) $destinationQuant->id,
                        'before_quantity' => $destinationBefore,
                        'after_quantity' => $destinationAfter,
                    ];
                } else {
                    $insert = [
                        'company_id' => $locked->company_id,
                        'warehouse_id' => $destination->warehouse_id,
                        'location_id' => $destination->id,
                        'product_id' => $locked->product_id,
                        'product_variant_id' => $locked->product_variant_id,
                        'lot_id' => $locked->lot_id,
                        'quantity' => 1,
                        'reserved_quantity' => 0,
                        'average_cost' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $insert = array_intersect_key($insert, array_flip(Schema::getColumnListing('stock_quants')));

                    $newQuantId = DB::table('stock_quants')->insertGetId($insert);

                    $quantAdjustment['destination'] = [
                        'action' => 'create',
                        'quant_id' => (int) $newQuantId,
                        'before_quantity' => 0,
                        'after_quantity' => 1,
                    ];
                }
            }

            $locked->current_warehouse_id = $destination->warehouse_id;
            $locked->current_location_id = $destination->id;
            $locked->save();

            if (Schema::hasTable('stock_serial_special_movements')) {
                $insert = [
                    'company_id' => $locked->company_id,
                    'stock_serial_number_id' => $locked->getKey(),
                    'product_id' => $locked->product_id,
                    'product_variant_id' => $locked->product_variant_id,
                    'lot_id' => $locked->lot_id,
                    'movement_type' => StockSerialSpecialMovement::TYPE_INTERNAL_RELOCATION,
                    'status' => 'confirmed',
                    'serial_number_before' => $locked->serial_number,
                    'serial_number_after' => $locked->serial_number,
                    'source_warehouse_id' => $sourceWarehouseId,
                    'source_location_id' => $sourceLocationId,
                    'destination_warehouse_id' => $destination->warehouse_id,
                    'destination_location_id' => $destination->id,
                    'reason' => $reason,
                    'reference' => $reference !== '' ? $reference : null,
                    'notes' => $notes !== '' ? $notes : null,
                    'created_by' => auth()->id(),
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                    'metadata' => json_encode([
                        'before' => $beforeSnapshot,
                        'after' => [
                            'serial_number' => $locked->serial_number,
                            'status' => $locked->status,
                            'current_warehouse_id' => $locked->current_warehouse_id,
                            'current_location_id' => $locked->current_location_id,
                        ],
                        'destination_location' => [
                            'id' => $destination->id,
                            'warehouse_id' => $destination->warehouse_id,
                            'warehouse_name' => $destination->warehouse_name,
                            'name' => $destination->name,
                            'code' => $destination->code,
                            'type_code' => $destination->type_code,
                            'type_name' => $destination->type_name,
                        ],
                        'quant_adjustment' => $quantAdjustment,
                        'source' => 'StockSerialNumberResource.relocateSerialRecord',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $insert = array_intersect_key($insert, array_flip(Schema::getColumnListing('stock_serial_special_movements')));

                DB::table('stock_serial_special_movements')->insert($insert);
            }
        });

        Notification::make()
            ->title('Serie reubicada')
            ->body('La serie cambió de ubicación y se registró en auditoría.')
            ->success()
            ->send();

        return null;
    }

    public static function scrapSerialRecord(StockSerialNumber $record, array $data): null
    {
        $destinationLocationId = (int) ($data['destination_location_id'] ?? 0);
        $reason = trim((string) ($data['reason'] ?? ''));
        $reference = trim((string) ($data['reference'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));

        if ($destinationLocationId <= 0) {
            Notification::make()
                ->title('Ubicación de merma requerida')
                ->body('Selecciona la ubicación de merma o pérdida.')
                ->danger()
                ->send();

            throw new \Filament\Support\Exceptions\Halt();
        }

        if ($reason === '') {
            Notification::make()
                ->title('Motivo requerido')
                ->body('Captura el motivo de la baja por merma.')
                ->danger()
                ->send();

            throw new \Filament\Support\Exceptions\Halt();
        }

        DB::transaction(function () use ($record, $destinationLocationId, $reason, $reference, $notes): void {
            /** @var StockSerialNumber $locked */
            $locked = StockSerialNumber::query()
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $allowedStatuses = ['available', 'blocked'];

            if (! in_array((string) $locked->status, $allowedStatuses, true)) {
                Notification::make()
                    ->title('La serie no se puede dar de baja por merma')
                    ->body('Solo se permiten series disponibles o bloqueadas. Estado actual: ' . static::statusLabel($locked->status))
                    ->danger()
                    ->send();

                throw new \Filament\Support\Exceptions\Halt();
            }

            $destination = DB::table('stock_locations as l')
                ->leftJoin('stock_location_types as t', 't.id', '=', 'l.stock_location_type_id')
                ->select([
                    'l.id',
                    'l.company_id',
                    'l.warehouse_id',
                    'l.name',
                    'l.code',
                    'l.is_active',
                    't.code as type_code',
                    't.name as type_name',
                ])
                ->where('l.id', $destinationLocationId)
                ->where('l.company_id', $locked->company_id)
                ->where('l.is_active', true)
                ->where(function ($query): void {
                    $query->where('t.code', 'LOSS')
                        ->orWhere('t.name', 'ilike', '%merma%')
                        ->orWhere('t.name', 'ilike', '%pérdida%')
                        ->orWhere('t.name', 'ilike', '%perdida%')
                        ->orWhere('t.name', 'ilike', '%scrap%')
                        ->orWhere('l.name', 'ilike', '%merma%')
                        ->orWhere('l.name', 'ilike', '%pérdida%')
                        ->orWhere('l.name', 'ilike', '%perdida%')
                        ->orWhere('l.name', 'ilike', '%scrap%');
                })
                ->first();

            if (! $destination) {
                Notification::make()
                    ->title('Ubicación inválida')
                    ->body('La ubicación seleccionada no es una ubicación activa de merma o pérdida de esta empresa.')
                    ->danger()
                    ->send();

                throw new \Filament\Support\Exceptions\Halt();
            }

            $beforeSnapshot = [
                'id' => $locked->getKey(),
                'serial_number' => $locked->serial_number,
                'status' => $locked->status,
                'company_id' => $locked->company_id,
                'product_id' => $locked->product_id,
                'product_variant_id' => $locked->product_variant_id,
                'lot_id' => $locked->lot_id,
                'current_warehouse_id' => $locked->current_warehouse_id,
                'current_location_id' => $locked->current_location_id,
            ];

            $sourceWarehouseId = $locked->current_warehouse_id;
            $sourceLocationId = $locked->current_location_id;
            $statusBefore = (string) ($locked->status ?? '');

            $quantAdjustment = [
                'attempted' => false,
                'action' => null,
                'quant_id' => null,
                'before_quantity' => null,
                'after_quantity' => null,
                'message' => null,
            ];

            if (
                Schema::hasTable('stock_quants')
                && $locked->company_id
                && $sourceWarehouseId
                && $sourceLocationId
                && $locked->product_id
            ) {
                $quantQuery = DB::table('stock_quants')
                    ->where('company_id', $locked->company_id)
                    ->where('warehouse_id', $sourceWarehouseId)
                    ->where('location_id', $sourceLocationId)
                    ->where('product_id', $locked->product_id);

                if (! empty($locked->product_variant_id)) {
                    $quantQuery->where('product_variant_id', $locked->product_variant_id);
                } else {
                    $quantQuery->whereNull('product_variant_id');
                }

                if (Schema::hasColumn('stock_quants', 'lot_id')) {
                    if (! empty($locked->lot_id)) {
                        $quantQuery->where('lot_id', $locked->lot_id);
                    } else {
                        $quantQuery->whereNull('lot_id');
                    }
                }

                $quant = $quantQuery->lockForUpdate()->first();
                $quantAdjustment['attempted'] = true;

                if ($quant) {
                    $beforeQuantity = (float) ($quant->quantity ?? 0);
                    $afterQuantity = max(0, $beforeQuantity - 1);

                    DB::table('stock_quants')
                        ->where('id', $quant->id)
                        ->update(array_intersect_key([
                            'quantity' => $afterQuantity,
                            'updated_at' => now(),
                        ], array_flip(Schema::getColumnListing('stock_quants'))));

                    $quantAdjustment = [
                        'attempted' => true,
                        'action' => 'decrement_source_quant',
                        'quant_id' => (int) $quant->id,
                        'before_quantity' => $beforeQuantity,
                        'after_quantity' => $afterQuantity,
                        'message' => 'Se restó 1 de la existencia origen.',
                    ];
                } else {
                    $quantAdjustment['message'] = 'No se encontró stock_quant origen para descontar.';
                }
            } else {
                $quantAdjustment['message'] = 'No se intentó ajuste de stock_quant por datos incompletos.';
            }

            $locked->status = 'scrapped';
            $locked->current_warehouse_id = $destination->warehouse_id;
            $locked->current_location_id = $destination->id;
            $locked->save();

            if (Schema::hasTable('stock_serial_special_movements')) {
                $insert = [
                    'company_id' => $locked->company_id,
                    'stock_serial_number_id' => $locked->getKey(),
                    'product_id' => $locked->product_id,
                    'product_variant_id' => $locked->product_variant_id,
                    'lot_id' => $locked->lot_id,
                    'movement_type' => StockSerialSpecialMovement::TYPE_SCRAP_LOSS,
                    'status' => 'confirmed',
                    'serial_number_before' => $locked->serial_number,
                    'serial_number_after' => null,
                    'source_warehouse_id' => $sourceWarehouseId,
                    'source_location_id' => $sourceLocationId,
                    'destination_warehouse_id' => $destination->warehouse_id,
                    'destination_location_id' => $destination->id,
                    'reason' => $reason,
                    'reference' => $reference !== '' ? $reference : null,
                    'notes' => $notes !== '' ? $notes : null,
                    'created_by' => auth()->id(),
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                    'metadata' => json_encode([
                        'before' => $beforeSnapshot,
                        'after' => [
                            'serial_number' => $locked->serial_number,
                            'status' => $locked->status,
                            'current_warehouse_id' => $locked->current_warehouse_id,
                            'current_location_id' => $locked->current_location_id,
                        ],
                        'status_before' => $statusBefore,
                        'status_after' => 'scrapped',
                        'destination_location' => [
                            'id' => $destination->id,
                            'warehouse_id' => $destination->warehouse_id,
                            'name' => $destination->name,
                            'code' => $destination->code,
                            'type_code' => $destination->type_code,
                            'type_name' => $destination->type_name,
                        ],
                        'quant_adjustment' => $quantAdjustment,
                        'source' => 'StockSerialNumberResource.scrapSerialRecord',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $insert = array_intersect_key($insert, array_flip(Schema::getColumnListing('stock_serial_special_movements')));

                DB::table('stock_serial_special_movements')->insert($insert);
            }
        });

        Notification::make()
            ->title('Serie dada de baja por merma')
            ->body('La serie quedó en estado Merma / desecho y se registró en auditoría.')
            ->success()
            ->send();

        return null;
    }

    public static function markSerialDuplicateConflictRecord(StockSerialNumber $record, array $data): null
    {
        $relatedSerialId = (int) ($data['related_stock_serial_number_id'] ?? 0);
        $conflictSerial = trim((string) ($data['conflict_serial_number'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));
        $reference = trim((string) ($data['reference'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $blockSerial = (bool) ($data['block_serial'] ?? true);

        if ($reason === '') {
            Notification::make()
                ->title('Motivo requerido')
                ->body('Captura el motivo del conflicto.')
                ->danger()
                ->send();

            throw new \Filament\Support\Exceptions\Halt();
        }

        DB::transaction(function () use ($record, $conflictSerial, $reason, $reference, $notes, $blockSerial, $relatedSerialId): void {
            /** @var StockSerialNumber $locked */
            $locked = StockSerialNumber::query()
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $beforeSnapshot = [
                'id' => $locked->getKey(),
                'serial_number' => $locked->serial_number,
                'status' => $locked->status,
                'company_id' => $locked->company_id,
                'product_id' => $locked->product_id,
                'product_variant_id' => $locked->product_variant_id,
                'lot_id' => $locked->lot_id,
                'current_warehouse_id' => $locked->current_warehouse_id,
                'current_location_id' => $locked->current_location_id,
            ];

            $statusBefore = (string) ($locked->status ?? '');

            $relatedSerial = null;

            if ($relatedSerialId > 0) {
                $relatedSerial = StockSerialNumber::query()
                    ->where('company_id', $locked->company_id)
                    ->where('id', '!=', $locked->getKey())
                    ->whereKey($relatedSerialId)
                    ->first();

                if (! $relatedSerial) {
                    Notification::make()
                        ->title('Serie relacionada inválida')
                        ->body('La serie relacionada seleccionada no existe o no pertenece a la misma empresa.')
                        ->danger()
                        ->send();

                    throw new \Filament\Support\Exceptions\Halt();
                }

                $conflictSerial = (string) $relatedSerial->serial_number;
            }

            if ($blockSerial && $locked->status !== 'blocked') {
                $locked->status = 'blocked';
                $locked->save();
            }

            $relatedMatches = [];

            if ($conflictSerial !== '' && Schema::hasTable('stock_serial_numbers')) {
                $relatedMatches = DB::table('stock_serial_numbers')
                    ->select([
                        'id',
                        'company_id',
                        'product_id',
                        'product_variant_id',
                        'serial_number',
                        'status',
                        'current_warehouse_id',
                        'current_location_id',
                    ])
                    ->where('company_id', $locked->company_id)
                    ->whereRaw('lower(serial_number) = ?', [mb_strtolower($conflictSerial)])
                    ->where('id', '!=', $locked->getKey())
                    ->limit(20)
                    ->get()
                    ->map(fn ($row): array => (array) $row)
                    ->all();
            }

            if (Schema::hasTable('stock_serial_special_movements')) {
                $insert = [
                    'company_id' => $locked->company_id,
                    'stock_serial_number_id' => $locked->getKey(),
                    'product_id' => $locked->product_id,
                    'product_variant_id' => $locked->product_variant_id,
                    'lot_id' => $locked->lot_id,
                    'movement_type' => StockSerialSpecialMovement::TYPE_DUPLICATE_CONFLICT,
                    'status' => 'confirmed',
                    'serial_number_before' => $locked->serial_number,
                    'serial_number_after' => $conflictSerial !== '' ? $conflictSerial : null,
                    'source_warehouse_id' => $locked->current_warehouse_id,
                    'source_location_id' => $locked->current_location_id,
                    'destination_warehouse_id' => $locked->current_warehouse_id,
                    'destination_location_id' => $locked->current_location_id,
                    'reason' => $reason,
                    'reference' => $reference !== '' ? $reference : null,
                    'notes' => $notes !== '' ? $notes : null,
                    'created_by' => auth()->id(),
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                    'metadata' => json_encode([
                        'before' => $beforeSnapshot,
                        'after' => [
                            'status' => $locked->status,
                            'blocked' => $blockSerial,
                        ],
                        'conflict_serial_number' => $conflictSerial,
                        'related_stock_serial_number_id' => $relatedSerialId ?: null,
                        'related_selected' => $relatedSerial ? [
                            'id' => $relatedSerial->getKey(),
                            'serial_number' => $relatedSerial->serial_number,
                            'status' => $relatedSerial->status,
                            'product_id' => $relatedSerial->product_id,
                            'product_variant_id' => $relatedSerial->product_variant_id,
                            'current_warehouse_id' => $relatedSerial->current_warehouse_id,
                            'current_location_id' => $relatedSerial->current_location_id,
                        ] : null,
                        'related_matches' => $relatedMatches,
                        'status_before' => $statusBefore,
                        'status_after' => (string) ($locked->status ?? ''),
                        'source' => 'StockSerialNumberResource.markSerialDuplicateConflict',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $insert = array_intersect_key($insert, array_flip(Schema::getColumnListing('stock_serial_special_movements')));

                DB::table('stock_serial_special_movements')->insert($insert);
            }
        });

        Notification::make()
            ->title('Conflicto de serie registrado')
            ->body($blockSerial ? 'La serie quedó marcada como Bloqueada y se registró en auditoría.' : 'El conflicto quedó registrado en auditoría.')
            ->success()
            ->send();

        return null;
    }

    public static function correctSerialNumberRecord(StockSerialNumber $record, array $data): null
    {
        $newSerial = trim((string) ($data['serial_number_after'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));
        $reference = trim((string) ($data['reference'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));

        if ($newSerial === '') {
            Notification::make()
                ->title('Nuevo número requerido')
                ->body('Captura el nuevo número de serie.')
                ->danger()
                ->send();

            throw new \Filament\Support\Exceptions\Halt();
        }

        if ($reason === '') {
            Notification::make()
                ->title('Motivo requerido')
                ->body('Captura el motivo de corrección.')
                ->danger()
                ->send();

            throw new \Filament\Support\Exceptions\Halt();
        }

        $oldSerial = trim((string) $record->serial_number);

        if (mb_strtolower($oldSerial) === mb_strtolower($newSerial)) {
            Notification::make()
                ->title('Sin cambios')
                ->body('El nuevo número de serie es igual al actual.')
                ->warning()
                ->send();

            throw new \Filament\Support\Exceptions\Halt();
        }

        $companyId = (int) ($record->company_id ?: static::currentCompanyId());

        $duplicate = DB::table('stock_serial_numbers')
            ->where('company_id', $companyId)
            ->where('id', '!=', $record->getKey())
            ->whereRaw('lower(serial_number) = ?', [mb_strtolower($newSerial)])
            ->first();

        if ($duplicate) {
            Notification::make()
                ->title('Número de serie duplicado')
                ->body('Ya existe otro registro con ese número de serie en la misma empresa. Usa el flujo de duplicado/conflicto.')
                ->danger()
                ->send();

            throw new \Filament\Support\Exceptions\Halt();
        }

        DB::transaction(function () use ($record, $data, $oldSerial, $newSerial, $reason, $reference, $notes, $companyId): void {
            /** @var StockSerialNumber $locked */
            $locked = StockSerialNumber::query()
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $duplicate = DB::table('stock_serial_numbers')
                ->where('company_id', $companyId)
                ->where('id', '!=', $locked->getKey())
                ->whereRaw('lower(serial_number) = ?', [mb_strtolower($newSerial)])
                ->first();

            if ($duplicate) {
                Notification::make()
                    ->title('Número de serie duplicado')
                    ->body('Ya existe otro registro con ese número de serie en la misma empresa. Usa el flujo de duplicado/conflicto.')
                    ->danger()
                    ->send();

                throw new \Filament\Support\Exceptions\Halt();
            }

            $beforeSnapshot = [
                'id' => $locked->getKey(),
                'serial_number' => $locked->serial_number,
                'status' => $locked->status,
                'company_id' => $locked->company_id,
                'product_id' => $locked->product_id,
                'product_variant_id' => $locked->product_variant_id,
                'lot_id' => $locked->lot_id,
                'current_warehouse_id' => $locked->current_warehouse_id,
                'current_location_id' => $locked->current_location_id,
            ];

            $locked->serial_number = $newSerial;
            $locked->save();

            if (Schema::hasTable('stock_serial_special_movements')) {
                $insert = [
                    'company_id' => $locked->company_id,
                    'stock_serial_number_id' => $locked->getKey(),
                    'product_id' => $locked->product_id,
                    'product_variant_id' => $locked->product_variant_id,
                    'lot_id' => $locked->lot_id,
                    'movement_type' => StockSerialSpecialMovement::TYPE_SERIAL_CORRECTION,
                    'status' => 'confirmed',
                    'serial_number_before' => $oldSerial,
                    'serial_number_after' => $newSerial,
                    'source_warehouse_id' => $locked->current_warehouse_id,
                    'source_location_id' => $locked->current_location_id,
                    'destination_warehouse_id' => $locked->current_warehouse_id,
                    'destination_location_id' => $locked->current_location_id,
                    'reason' => $reason,
                    'reference' => $reference !== '' ? $reference : null,
                    'notes' => $notes !== '' ? $notes : null,
                    'created_by' => auth()->id(),
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                    'metadata' => json_encode([
                        'before' => $beforeSnapshot,
                        'after' => [
                            'serial_number' => $newSerial,
                        ],
                        'source' => 'StockSerialNumberResource.correctSerialNumber',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $insert = array_intersect_key($insert, array_flip(Schema::getColumnListing('stock_serial_special_movements')));

                DB::table('stock_serial_special_movements')->insert($insert);
            }
        });

        Notification::make()
            ->title('Número de serie corregido')
            ->body('Se corrigió de "' . $oldSerial . '" a "' . $newSerial . '" y quedó registrado en el historial especial.')
            ->success()
            ->send();

        return null;
    }

    protected static function currentCompanyId(): ?int
    {
        try {
            $tenant = Filament::getTenant();

            if (is_object($tenant) && method_exists($tenant, 'getKey')) {
                return (int) $tenant->getKey();
            }

            if (is_object($tenant) && isset($tenant->id)) {
                return (int) $tenant->id;
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

        return null;
    }

    protected static function canManage(string $permission): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
            return true;
        }

        if (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
            return true;
        }

        return $user->can($permission);
    }
    public static function canCreate(): bool
    {
        return static::canManageTrackingMasterData();
    }

    public static function canEdit($record): bool
    {
        return static::canManageTrackingMasterData();
    }

    public static function canDelete($record): bool
    {
        return static::canManageTrackingMasterData();
    }

    public static function canDeleteAny(): bool
    {
        return static::canManageTrackingMasterData();
    }

    protected static function canManageTrackingMasterData(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $allowedRoleKeys = [
            'superadministrador',
            'superadmin',
            'admininventario',
            'administradordeinventario',
            'inventoryadmin',
            'inventoryadministrator',
        ];

        $allowedPermissionNames = [
            'inventory.tracking.manage',
            'inventory.lots.manage',
            'inventory.serials.manage',
            'stock.lots.manage',
            'stock.serials.manage',
            'inventario.lotes_series.administrar',
            'inventario.lotes.administrar',
            'inventario.series.administrar',
        ];

        try {
            foreach ($allowedPermissionNames as $permission) {
                if (method_exists($user, 'can') && $user->can($permission)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            //
        }

        try {
            if (method_exists($user, 'hasRole')) {
                foreach ([
                    'Super Administrador',
                    'Super Admin',
                    'Admin Inventario',
                    'Administrador de Inventario',
                    'Inventory Admin',
                    'Inventory Administrator',
                ] as $roleName) {
                    if ($user->hasRole($roleName)) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            //
        }

        $roleNames = [];

        try {
            if (method_exists($user, 'roles')) {
                $roleNames = $user->roles()->pluck('name')->all();
            }
        } catch (\Throwable $e) {
            $roleNames = [];
        }

        foreach ($roleNames as $roleName) {
            $key = static::trackingPermissionKey((string) $roleName);

            if (in_array($key, $allowedRoleKeys, true)) {
                return true;
            }
        }

        foreach (['role', 'role_name', 'type'] as $field) {
            $value = (string) ($user->{$field} ?? '');

            if ($value !== '' && in_array(static::trackingPermissionKey($value), $allowedRoleKeys, true)) {
                return true;
            }
        }

        return false;
    }

    protected static function trackingPermissionKey(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9áéíóúñ]+/u', '', $value) ?: '';

        return strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);
    }


    public static function serialStatusLabel(?string $state): string
    {
        return match ((string) $state) {
            'available' => 'Disponible',
            'sold' => 'Vendido',
            'blocked' => 'Bloqueado',
            'scrapped' => 'Merma / desecho',
            'reserved' => 'Reservado',
            'returned' => 'Devuelto',
            default => $state ? ucfirst(str_replace('_', ' ', (string) $state)) : 'Sin estado',
        };
    }

    public static function serialStatusColor(?string $state): string
    {
        return match ((string) $state) {
            'available' => 'success',
            'sold' => 'gray',
            'blocked' => 'warning',
            'scrapped' => 'danger',
            'reserved' => 'info',
            'returned' => 'info',
            default => 'gray',
        };
    }

}
