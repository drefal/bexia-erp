<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAdjustmentResource\Pages;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use App\Models\StockQuant;
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

class StockAdjustmentResource extends Resource
{
    protected static ?string $model = StockAdjustment::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Ajustes de inventario';

    protected static ?string $modelLabel = 'ajuste de inventario';

    protected static ?string $pluralModelLabel = 'ajustes de inventario';

    protected static ?int $navigationSort = 90;

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        $query = StockAdjustment::query()
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

                Forms\Components\Section::make('Ajuste')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label('Referencia')
                            ->placeholder('Se genera automáticamente al guardar')
                            ->helperText('Formato: UBICACION/AJU/000001. Ej. STOCK/AJU/000001.')
                            ->maxLength(80)
                            ->readOnly()
                            ->dehydrated(true)
                            ->columnSpan(3),

                        Forms\Components\DateTimePicker::make('adjustment_at')
                            ->label('Fecha y hora')
                            ->default(now())
                            ->required()
                            ->seconds(false)
                            ->displayFormat('d/m/Y H:i')
                            ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                            ->columnSpan(3),

                        Forms\Components\Select::make('warehouse_id')
                            ->label('Almacén')
                            ->options(fn (): array => static::warehouseOptions())
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set): void {
                                $set('location_id', null);
                            })
                            ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                            ->columnSpan(3),

                        Forms\Components\Select::make('location_id')
                            ->label('Ubicación')
                            ->options(fn (Forms\Get $get): array => static::locationOptions($get('warehouse_id')))
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
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

                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo')
                            ->placeholder('Ej. Inventario inicial, conteo físico, corrección por diferencia.')
                            ->rows(2)
                            ->required()
                            ->helperText('El motivo es obligatorio para ajustes nuevos.')
                            ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                            ->columnSpan(9),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(2)
                            ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                            ->columnSpanFull(),
                    ])
                    ->columns(12),

                Forms\Components\Section::make('Productos contados')
                    ->description('Captura la cantidad física contada. Al confirmar, Bexia actualizará la existencia en la ubicación seleccionada.')
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
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get): void {
                                        $set('product_variant_id', null);
                                        $set('lot_id', null);
                                        static::refreshAdjustmentLineComputedFields($set, $get);
                                    })
                                    ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                                    ->columnSpan(4),

                                Forms\Components\Select::make('product_variant_id')
                                    ->label('Variante')
                                    ->options(fn (Forms\Get $get): array => static::variantOptions($get('product_id')))
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->placeholder('Sin variante')
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get): void {
                                        // V5623D_RESET_LOT_ON_VARIANT
                                        $set('lot_id', null);
                                        static::refreshAdjustmentLineComputedFields($set, $get);
                                    })
                                    ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                                    ->columnSpan(3),

                                Forms\Components\Select::make('lot_id')
                                    ->label('Lote')
                                    ->placeholder('Selecciona lote')
                                    ->searchable()
                                    ->preload()
                                    ->options(fn (Forms\Get $get): array => static::lotOptions(
                                        $get('product_id'),
                                        $get('product_variant_id'),
                                        $get('../../warehouse_id') ?: $get('warehouse_id'),
                                        $get('../../location_id') ?: $get('location_id')
                                    ))
                                    ->visible(fn (Forms\Get $get): bool => static::productRequiresLot($get('product_id'), $get('product_variant_id')))
                                    ->required(fn (Forms\Get $get): bool => static::productRequiresLot($get('product_id'), $get('product_variant_id')))
                                    ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get): null => static::refreshAdjustmentLineComputedFields($set, $get))
                                    ->columnSpan(3),

                                Forms\Components\Placeholder::make('serial_adjustment_notice')
                                    ->label('Número de serie')
                                    ->content('Este producto maneja números de serie. El ajuste por serie se hará en una pantalla especial para mantener la trazabilidad individual.')
                                    ->visible(fn (Forms\Get $get): bool => static::productRequiresSerial($get('product_id'), $get('product_variant_id')))
                                    ->columnSpan(6),

                                Forms\Components\TextInput::make('counted_quantity')
                                    ->label('Cantidad contada')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get): void {
                                        static::refreshAdjustmentLineComputedFields($set, $get);
                                    })
                                    ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                                    ->columnSpan(2),

                                Forms\Components\Hidden::make('current_quantity')
                                    ->default(0)
                                    ->dehydrated(true),

                                Forms\Components\Hidden::make('difference_quantity')
                                    ->default(0)
                                    ->dehydrated(true),

                                Forms\Components\Hidden::make('unit_cost')
                                    ->default(0)
                                    ->dehydrated(true),

                                Forms\Components\Placeholder::make('current_quantity_info')
                                    ->label('Cantidad actual')
                                    ->content(fn (Forms\Get $get): string => number_format((float) ($get('current_quantity') ?: 0), 2))
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('difference_quantity_info')
                                    ->label('Diferencia')
                                    ->content(fn (Forms\Get $get): string => number_format((float) ($get('difference_quantity') ?: 0), 2))
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('unit_cost_info')
                                    ->label('Costo prom.')
                                    ->content(fn (Forms\Get $get): string => '$ ' . number_format((float) ($get('unit_cost') ?: 0), 2))
                                    ->columnSpan(1),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Notas')
                                    ->rows(1)
                                    ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                                    ->columnSpanFull(),
                            ])
                            ->columns(12)
                            ->defaultItems(1)
                            ->addActionLabel('Agregar producto')
                            ->reorderable(false)
                            ->disabled(fn (Forms\Get $get): bool => static::adjustmentIsDoneFromForm($get))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Referencia')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('adjustment_at')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse_label')
                    ->label('Almacén')
                    ->state(fn (StockAdjustment $record): string => static::warehouseLabel($record->warehouse_id))
                    ->searchable(false),

                Tables\Columns\TextColumn::make('location_label')
                    ->label('Ubicación')
                    ->state(fn (StockAdjustment $record): string => static::locationLabel($record->location_id))
                    ->searchable(false),

                Tables\Columns\TextColumn::make('lines_count')
                    ->label('Líneas')
                    ->sortable(),

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

                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->limit(45)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'done' => 'Hecho',
                        'cancelled' => 'Cancelado',
                    ]),

                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Almacén')
                    ->options(fn (): array => static::warehouseOptions()),

                Tables\Filters\SelectFilter::make('location_id')
                    ->label('Ubicación')
                    ->options(fn (): array => static::allLocationOptions()),
            ])
            ->actions([

                Tables\Actions\Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar ajuste de inventario')
                    ->modalDescription('Al confirmar, se actualizarán las existencias. Esta acción no debe hacerse si aún estás revisando las cantidades.')
                    ->modalSubmitActionLabel('Confirmar ajuste')
                    ->visible(fn (StockAdjustment $record): bool => $record->status === 'draft')
                    ->action(function (StockAdjustment $record): void {
                        static::confirmAdjustment($record);

                        Notification::make()
                            ->title('Ajuste confirmado')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->label('Editar'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockAdjustments::route('/'),
            'create' => Pages\CreateStockAdjustment::route('/create'),
            'edit' => Pages\EditStockAdjustment::route('/{record}/edit'),
        ];
    }

    public static function confirmAdjustment(StockAdjustment $adjustment): void
    {
        if ($adjustment->status !== 'draft') {
            return;
        }

        if (trim((string) $adjustment->reason) === '') {
            \Filament\Notifications\Notification::make()
                ->title('Motivo requerido')
                ->body('El ajuste necesita un motivo antes de confirmar.')
                ->danger()
                ->send();

            throw new \Filament\Support\Exceptions\Halt();
        }

        $adjustment->load('lines');

        if ($adjustment->lines->isEmpty()) {
            \Filament\Notifications\Notification::make()
                ->title('No se puede confirmar')
                ->body('El ajuste no tiene productos capturados.')
                ->danger()
                ->send();

            throw new \Filament\Support\Exceptions\Halt();
        }

        DB::transaction(function () use ($adjustment): void {
            $previousStatus = (string) $adjustment->status;
            $adjustmentMovementId = null;

            foreach ($adjustment->lines as $line) {
                if (! $line->product_id) {
                    continue;
                }

                $lineLotId = ! empty($line->lot_id) ? (int) $line->lot_id : null;

                if (static::productRequiresSerial((int) $line->product_id, $line->product_variant_id ? (int) $line->product_variant_id : null)) {
                    \Filament\Notifications\Notification::make()
                        ->title('Ajuste por serie pendiente')
                        ->body('Los productos con numero de serie se ajustaran en una pantalla especial para no romper la trazabilidad por serie.')
                        ->danger()
                        ->send();

                    throw new \Filament\Support\Exceptions\Halt();
                }

                if (static::productRequiresLot((int) $line->product_id, $line->product_variant_id ? (int) $line->product_variant_id : null) && ! $lineLotId) {
                    \Filament\Notifications\Notification::make()
                        ->title('Lote requerido')
                        ->body('Selecciona el lote para cada producto que maneja lote.')
                        ->danger()
                        ->send();

                    throw new \Filament\Support\Exceptions\Halt();
                }

                $current = static::currentQuantity(
                    (int) $adjustment->company_id,
                    (int) $adjustment->warehouse_id,
                    (int) $adjustment->location_id,
                    (int) $line->product_id,
                    $line->product_variant_id ? (int) $line->product_variant_id : null,
                    $lineLotId
                );

                $counted = (float) $line->counted_quantity;

                if ($counted < 0 && ! static::locationAllowsNegativeStock((int) $adjustment->location_id)) {
                    \Filament\Notifications\Notification::make()
                        ->title('No se permite existencia negativa')
                        ->body('La ubicación seleccionada no permite cantidades negativas. Activa "Permitir existencias negativas" en la ubicación o captura una cantidad mayor o igual a cero.')
                        ->danger()
                        ->send();

                    throw new \Filament\Support\Exceptions\Halt();
                }

                $difference = $counted - $current;

                $averageCost = static::averageCost(
                    (int) $adjustment->company_id,
                    (int) $adjustment->warehouse_id,
                    (int) $adjustment->location_id,
                    (int) $line->product_id,
                    $line->product_variant_id ? (int) $line->product_variant_id : null,
                    $lineLotId
                );

                $unitCost = $averageCost;

                if ($unitCost === null && $line->unit_cost !== null) {
                    $unitCost = (float) $line->unit_cost;
                }

                $line->update([
                    'current_quantity' => $current,
                    'difference_quantity' => $difference,
                    'unit_cost' => $unitCost,
                ]);

                static::setQuantQuantity(
                    (int) $adjustment->company_id,
                    (int) $adjustment->warehouse_id,
                    (int) $adjustment->location_id,
                    (int) $line->product_id,
                    $line->product_variant_id ? (int) $line->product_variant_id : null,
                    $counted,
                    $unitCost,
                    $lineLotId
                );

                if (abs((float) $difference) > 0.000001) {
                    if ($adjustmentMovementId === null) {
                        $adjustmentMovementId = static::v5632gCreateAdjustmentStockMovement($adjustment);
                    }

                    static::v5632gCreateAdjustmentStockMovementLine(
                        (int) $adjustmentMovementId,
                        $adjustment,
                        $line,
                        (float) $difference,
                        $unitCost,
                        $lineLotId
                    );
                }
            }

            $adjustment->update([
                'status' => 'done',
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
            ]);

            static::recordAdjustmentStatusLog(
                $adjustment,
                $previousStatus,
                'done',
                'confirm',
                (string) $adjustment->reason,
                ['confirmed_by' => auth()->id()]
            );
        });
    }








    public static function assertAdjustmentLinesCanBeSaved(?int $locationId, array $lines): void
    {
        if (! $locationId || static::locationAllowsNegativeStock($locationId)) {
            return;
        }

        foreach ($lines as $line) {
            $counted = (float) ($line['counted_quantity'] ?? 0);

            if ($counted < 0) {
                \Filament\Notifications\Notification::make()
                    ->title('No se puede guardar el borrador')
                    ->body('La ubicación seleccionada no permite cantidades negativas. Activa "Permitir existencias negativas" en la ubicación o captura una cantidad mayor o igual a cero.')
                    ->danger()
                    ->send();

                throw new \Filament\Support\Exceptions\Halt();
            }
        }
    }

    protected static function adjustmentIsDoneFromForm(Forms\Get $get): bool
    {
        $status = $get('status') ?: $get('../../status');

        return $status === 'done';
    }

    protected static function refreshAdjustmentLineComputedFields(Forms\Set $set, Forms\Get $get): void
    {
        $productId = $get('product_id');
        $variantId = $get('product_variant_id');
        $lotId = $get('lot_id');

        $warehouseId = $get('../../warehouse_id') ?: $get('warehouse_id');
        $locationId = $get('../../location_id') ?: $get('location_id');

        $companyId = static::currentCompanyId();

        $current = 0.0;
        $averageCost = 0.0;

        if ($companyId && $warehouseId && $locationId && $productId) {
            $current = (float) static::currentQuantity(
                (int) $companyId,
                (int) $warehouseId,
                (int) $locationId,
                (int) $productId,
                $variantId ? (int) $variantId : null,
                $lotId ? (int) $lotId : null
            );

            $average = static::averageCost(
                (int) $companyId,
                (int) $warehouseId,
                (int) $locationId,
                (int) $productId,
                $variantId ? (int) $variantId : null,
                $lotId ? (int) $lotId : null
            );

            $averageCost = $average !== null ? (float) $average : 0.0;
        }

        $counted = (float) ($get('counted_quantity') ?: 0);
        $difference = $counted - $current;

        $set('current_quantity', round($current, 2));
        $set('difference_quantity', round($difference, 2));
        $set('unit_cost', round($averageCost, 2));
    }



        protected static function lotOptions(mixed $productId, mixed $variantId = null, mixed $warehouseId = null, mixed $locationId = null): array
    {
        $productId = (int) ($productId ?: 0);
        $variantId = $variantId ? (int) $variantId : null;
        $warehouseId = $warehouseId ? (int) $warehouseId : null;
        $locationId = $locationId ? (int) $locationId : null;

        if (! $productId || ! Schema::hasTable('stock_lots')) {
            return [];
        }

        $query = DB::table('stock_lots as l')
            ->where('l.product_id', $productId);

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('stock_lots', 'company_id')) {
            $query->where('l.company_id', $companyId);
        }

        if ($variantId && Schema::hasColumn('stock_lots', 'product_variant_id')) {
            $query->where(function ($inner) use ($variantId): void {
                $inner->where('l.product_variant_id', $variantId)
                    ->orWhereNull('l.product_variant_id');
            });
        }

        if (Schema::hasTable('stock_quants') && Schema::hasColumn('stock_quants', 'lot_id')) {
            $query->leftJoin('stock_quants as q', function ($join) use ($companyId, $warehouseId, $locationId, $productId, $variantId): void {
                $join->on('q.lot_id', '=', 'l.id')
                    ->where('q.product_id', '=', $productId);

                if ($companyId) {
                    $join->where('q.company_id', '=', $companyId);
                }

                if ($warehouseId) {
                    $join->where('q.warehouse_id', '=', $warehouseId);
                }

                if ($locationId) {
                    $join->where('q.location_id', '=', $locationId);
                }

                if ($variantId) {
                    $join->where(function ($inner) use ($variantId): void {
                        $inner->where('q.product_variant_id', '=', $variantId)
                            ->orWhereNull('q.product_variant_id');
                    });
                } else {
                    $join->whereNull('q.product_variant_id');
                }
            });

            $query->selectRaw('l.id, l.lot_number, l.expiration_date, COALESCE(SUM(q.quantity - COALESCE(q.reserved_quantity, 0)), 0) as available_quantity')
                ->groupBy('l.id', 'l.lot_number', 'l.expiration_date');
        } else {
            $query->select('l.id', 'l.lot_number', 'l.expiration_date');
        }

        return $query
            ->orderBy('l.lot_number')
            ->limit(100)
            ->get()
            ->mapWithKeys(function ($lot): array {
                $label = trim((string) ($lot->lot_number ?? ''));

                if ($label === '') {
                    $label = 'Lote #' . $lot->id;
                }

                if (! empty($lot->expiration_date)) {
                    $label .= ' · vence ' . $lot->expiration_date;
                }

                if (property_exists($lot, 'available_quantity')) {
                    $label .= ' · disp. ' . number_format((float) $lot->available_quantity, 2);
                }

                return [(int) $lot->id => $label];
            })
            ->all();
    }

    protected static function productRequiresLot(mixed $productId, mixed $variantId = null): bool
    {
        return static::productTrackingMatches($productId, $variantId, ['lot', 'lote']);
    }

    protected static function productRequiresSerial(mixed $productId, mixed $variantId = null): bool
    {
        return static::productTrackingMatches($productId, $variantId, ['serial', 'serie']);
    }

    protected static function productTrackingMatches(mixed $productId, mixed $variantId, array $needles): bool
    {
        if (! Schema::hasTable('products')) {
            return false;
        }

        $ids = array_values(array_filter(array_unique([
            (int) ($variantId ?: 0),
            (int) ($productId ?: 0),
        ])));

        if (empty($ids)) {
            return false;
        }

        $rows = DB::table('products')
            ->whereIn('id', $ids)
            ->get();

        foreach ($rows as $row) {
            foreach (['tracking', 'advanced_tracking_mode', 'tracking_type', 'inventory_tracking', 'lot_serial_tracking'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $value = strtolower(trim((string) ($row->{$column} ?? '')));

                    foreach ($needles as $needle) {
                        if ($value !== '' && str_contains($value, $needle)) {
                            return true;
                        }
                    }
                }
            }

            if (Schema::hasColumn('products', 'advanced_tracking_fields')) {
                $fields = $row->advanced_tracking_fields ?? null;

                if ($fields !== null && $fields !== '') {
                    $flat = strtolower(is_string($fields) ? $fields : json_encode($fields));

                    foreach ($needles as $needle) {
                        if (str_contains($flat, $needle)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    protected static function averageCost(int $companyId, int $warehouseId, int $locationId, int $productId, ?int $variantId = null, ?int $lotId = null): ?float
    {
        if (Schema::hasTable('stock_quants') && Schema::hasColumn('stock_quants', 'average_cost')) {
            $query = DB::table('stock_quants')
                ->where('company_id', $companyId)
                ->where('warehouse_id', $warehouseId)
                ->where('location_id', $locationId)
                ->where('product_id', $productId);

            if ($variantId) {
                $query->where('product_variant_id', $variantId);
            } else {
                $query->whereNull('product_variant_id');
            }

            if (Schema::hasColumn('stock_quants', 'lot_id')) {
                if ($lotId) {
                    $query->where('lot_id', $lotId);
                } else {
                    $query->whereNull('lot_id');
                }
            }

            $value = $query->value('average_cost');

            if ($value !== null) {
                return (float) $value;
            }
        }

        if ($variantId && Schema::hasTable('products')) {
            $variant = DB::table('products')->where('id', $variantId)->first();

            if ($variant) {
                foreach (['standard_cost', 'purchase_price', 'last_purchase_cost'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $value = $variant->{$column} ?? null;

                        if ($value !== null && (float) $value > 0) {
                            return (float) $value;
                        }
                    }
                }
            }
        }

        if (Schema::hasTable('products')) {
            $product = DB::table('products')->where('id', $productId)->first();

            if ($product) {
                foreach (['standard_cost', 'purchase_price', 'last_purchase_cost'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $value = $product->{$column} ?? null;

                        if ($value !== null && (float) $value > 0) {
                            return (float) $value;
                        }
                    }
                }
            }
        }

        return null;
    }



    protected static function locationAllowsNegativeStock(int $locationId): bool
    {
        if (! Schema::hasTable('stock_locations')) {
            return false;
        }

        if (! Schema::hasColumn('stock_locations', 'allow_negative_stock')) {
            return false;
        }

        return (bool) DB::table('stock_locations')
            ->where('id', $locationId)
            ->value('allow_negative_stock');
    }

        protected static function currentQuantity(int $companyId, int $warehouseId, int $locationId, int $productId, ?int $variantId = null, ?int $lotId = null): float
    {
        if (! Schema::hasTable('stock_quants')) {
            return 0;
        }

        $query = DB::table('stock_quants')
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId);

        if ($variantId) {
            $query->where('product_variant_id', $variantId);
        } else {
            $query->whereNull('product_variant_id');
        }

        if (Schema::hasColumn('stock_quants', 'lot_id')) {
            if ($lotId) {
                $query->where('lot_id', $lotId);
            } else {
                $query->whereNull('lot_id');
            }
        }

        return (float) $query->sum('quantity');
    }

        protected static function setQuantQuantity(int $companyId, int $warehouseId, int $locationId, int $productId, ?int $variantId, float $quantity, ?float $unitCost = null, ?int $lotId = null): void
    {
        $query = StockQuant::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId);

        if ($variantId) {
            $query->where('product_variant_id', $variantId);
        } else {
            $query->whereNull('product_variant_id');
        }

        if (Schema::hasColumn('stock_quants', 'lot_id')) {
            if ($lotId) {
                $query->where('lot_id', $lotId);
            } else {
                $query->whereNull('lot_id');
            }
        }

        $quant = $query->first();

        if (! $quant) {
            $data = [
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'reserved_quantity' => 0,
            ];

            if (Schema::hasColumn('stock_quants', 'lot_id')) {
                $data['lot_id'] = $lotId;
            }

            $quant = new StockQuant($data);
        }

        $quant->quantity = $quantity;

        if ($unitCost !== null) {
            $quant->average_cost = $unitCost;
        }

        $quant->save();
    }

    protected static function warehouseOptions(): array
    {
        if (! Schema::hasTable('warehouses')) {
            return [];
        }

        $query = DB::table('warehouses')
            ->where('is_active', true);

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where('company_id', $companyId);
        } else {
            $query->whereNull('company_id');
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }

    protected static function locationOptions($warehouseId): array
    {
        if (! $warehouseId || ! Schema::hasTable('stock_locations')) {
            return [];
        }

        $query = DB::table('stock_locations')
            ->where('stock_locations.warehouse_id', $warehouseId)
            ->where('stock_locations.is_active', true);

        if (Schema::hasTable('stock_location_types')) {
            $query
                ->leftJoin('stock_location_types', 'stock_location_types.id', '=', 'stock_locations.stock_location_type_id')
                ->where(function ($query): void {
                    $query
                        ->where('stock_location_types.is_internal', true)
                        ->orWhereNull('stock_location_types.id');
                });
        }

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where('stock_locations.company_id', $companyId);
        } else {
            $query->whereNull('stock_locations.company_id');
        }

        return $query
            ->orderBy('stock_locations.name')
            ->pluck('stock_locations.name', 'stock_locations.id')
            ->all();
    }

    protected static function allLocationOptions(): array
    {
        if (! Schema::hasTable('stock_locations')) {
            return [];
        }

        $query = DB::table('stock_locations')
            ->where('is_active', true);

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where('company_id', $companyId);
        } else {
            $query->whereNull('company_id');
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
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

        // En ajustes se selecciona el producto padre.
        // Las variantes se eligen en el campo Variante.
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
        if (! $productId || ! Schema::hasTable('products')) {
            return [];
        }

        $productId = (int) $productId;

        if (! Schema::hasColumn('products', 'parent_product_id')) {
            return [];
        }

        $query = DB::table('products')
            ->where('parent_product_id', $productId);

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
        return static::labelFromTable('products', $productId, ['sku', 'internal_reference', 'reference', 'code'], ['name', 'description']);
    }

    protected static function variantLabel($variantId): string
    {
        if (! $variantId || ! Schema::hasTable('products')) {
            return '—';
        }

        $row = DB::table('products')
            ->where('id', $variantId)
            ->first();

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

        $variantText = '';

        $group = Schema::hasColumn('products', 'variant_group')
            ? trim((string) ($row->variant_group ?? ''))
            : '';

        $value = Schema::hasColumn('products', 'variant_value')
            ? trim((string) ($row->variant_value ?? ''))
            : '';

        if ($group !== '' && $value !== '') {
            $variantText = $group . ': ' . $value;
        } elseif ($value !== '') {
            $variantText = $value;
        } elseif (Schema::hasColumn('products', 'variant_name')) {
            $variantText = trim((string) ($row->variant_name ?? ''));
        }

        if ($variantText === '' && Schema::hasColumn('products', 'name')) {
            $variantText = trim((string) ($row->name ?? ''));
        }

        if ($reference !== '' && $variantText !== '') {
            return $reference . ' - ' . $variantText;
        }

        return $variantText ?: ($reference ?: ('Variante #' . $variantId));
    }









    protected static function warehouseLabel($id): string
    {
        return static::labelFromTable('warehouses', $id, ['code'], ['name']);
    }

    protected static function locationLabel($id): string
    {
        return static::labelFromTable('stock_locations', $id, ['code'], ['name']);
    }

    protected static function labelFromTable(string $table, $id, array $codeColumns = [], array $nameColumns = []): string
    {
        if (! $id || ! Schema::hasTable($table)) {
            return '—';
        }

        $row = DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '—';
        }

        $code = '';

        foreach ($codeColumns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $value = trim((string) ($row->{$column} ?? ''));

                if ($value !== '') {
                    $code = $value;
                    break;
                }
            }
        }

        $name = '';

        foreach ($nameColumns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $value = trim((string) ($row->{$column} ?? ''));

                if ($value !== '') {
                    $name = $value;
                    break;
                }
            }
        }

        if ($code !== '' && $name !== '') {
            return $code . ' - ' . $name;
        }

        return $name ?: ($code ?: ('#' . $id));
    }

    public static function auditLogRows(StockAdjustment $adjustment): array
    {
        $userIds = collect([
            $adjustment->created_by ?? null,
            $adjustment->confirmed_by ?? null,
            $adjustment->cancelled_by ?? null,
        ])->filter()->map(fn ($id): int => (int) $id);

        $logs = collect();

        if (\Illuminate\Support\Facades\Schema::hasTable('stock_adjustment_status_logs')) {
            $logs = \Illuminate\Support\Facades\DB::table('stock_adjustment_status_logs')
                ->where('stock_adjustment_id', $adjustment->getKey())
                ->orderBy('id')
                ->get();

            $userIds = $userIds
                ->merge($logs->pluck('user_id')->filter()->map(fn ($id): int => (int) $id));
        }

        $userLabels = static::auditUserLabels($userIds->unique()->values()->all());

        return [
            'summary' => [
                [
                    'label' => 'Estado actual',
                    'value' => static::auditStatusLabel((string) $adjustment->status),
                ],
                [
                    'label' => 'Motivo del ajuste',
                    'value' => trim((string) ($adjustment->reason ?? '')) !== '' ? (string) $adjustment->reason : '—',
                ],
                [
                    'label' => 'Creado por',
                    'value' => static::auditUserLabel($adjustment->created_by ?? null, $userLabels),
                ],
                [
                    'label' => 'Confirmado',
                    'value' => trim(implode("\n", array_filter([
                        static::auditUserLabel($adjustment->confirmed_by ?? null, $userLabels),
                        static::auditDate($adjustment->confirmed_at ?? null),
                    ]))) ?: '—',
                ],
                [
                    'label' => 'Cancelado',
                    'value' => trim(implode("\n", array_filter([
                        static::auditUserLabel($adjustment->cancelled_by ?? null, $userLabels),
                        static::auditDate($adjustment->cancelled_at ?? null),
                    ]))) ?: '—',
                ],
                [
                    'label' => 'Motivo de cancelación',
                    'value' => trim((string) ($adjustment->cancellation_reason ?? '')) !== '' ? (string) $adjustment->cancellation_reason : '—',
                ],
            ],
            'logs' => $logs->map(function ($log) use ($userLabels): array {
                $from = static::auditStatusLabel((string) ($log->from_status ?? ''));
                $to = static::auditStatusLabel((string) ($log->to_status ?? ''));

                return [
                    'created_at' => static::auditDate($log->created_at ?? null),
                    'action' => (string) ($log->action ?? ''),
                    'action_label' => static::auditActionLabel((string) ($log->action ?? '')),
                    'from_status' => $from,
                    'to_status' => $to,
                    'status_label' => trim($from . ' → ' . $to, ' →'),
                    'user_label' => static::auditUserLabel($log->user_id ?? null, $userLabels),
                    'reason' => trim((string) ($log->reason ?? '')) !== '' ? (string) $log->reason : '—',
                ];
            })->all(),
        ];
    }

    protected static function auditUserLabels(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));

        if (empty($userIds) || ! \Illuminate\Support\Facades\Schema::hasTable('users')) {
            return [];
        }

        $select = ['id'];

        foreach (['name', 'email'] as $column) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', $column)) {
                $select[] = $column;
            }
        }

        return \Illuminate\Support\Facades\DB::table('users')
            ->select($select)
            ->whereIn('id', $userIds)
            ->get()
            ->mapWithKeys(function ($user): array {
                $label = trim((string) ($user->name ?? ''));

                if ($label === '') {
                    $label = trim((string) ($user->email ?? ''));
                }

                if ($label === '') {
                    $label = 'Usuario #' . $user->id;
                }

                return [(int) $user->id => $label];
            })
            ->all();
    }

    protected static function auditUserLabel($userId, array $userLabels): string
    {
        if (! $userId) {
            return '—';
        }

        return $userLabels[(int) $userId] ?? ('Usuario #' . (int) $userId);
    }

    protected static function auditDate($value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)
                ->timezone(config('app.timezone', 'UTC'))
                ->format('d/m/Y H:i');
        } catch (\Throwable $exception) {
            return (string) $value;
        }
    }

    protected static function auditStatusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Borrador',
            'done' => 'Hecho',
            'cancelled' => 'Cancelado',
            default => $status !== '' ? $status : '—',
        };
    }

    protected static function auditActionLabel(string $action): string
    {
        return match ($action) {
            'confirm' => 'Confirmación',
            'cancel' => 'Cancelación',
            default => $action !== '' ? $action : '—',
        };
    }

    public static function recordAdjustmentStatusLog(StockAdjustment $adjustment, ?string $fromStatus, string $toStatus, string $action, ?string $reason = null, ?array $metadata = null): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('stock_adjustment_status_logs')) {
                return;
            }

            \Illuminate\Support\Facades\DB::table('stock_adjustment_status_logs')->insert([
                'stock_adjustment_id' => $adjustment->getKey(),
                'company_id' => $adjustment->company_id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'action' => $action,
                'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
                'notes' => null,
                'user_id' => auth()->id(),
                'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
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
        return static::userCanPermission('inventory.adjust_stock');
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCanPermission('inventory.adjust_stock');
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof StockAdjustment
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
            && $user->hasAnyRole(['admin', 'Administrador', 'Admin Empresa', 'Admin Grupo'])
        ) {
            return true;
        }

        if (method_exists($user, 'can')) {
            return $user->can($permission);
        }

        return false;
    }
    protected static function v5632gCreateAdjustmentStockMovement(StockAdjustment $adjustment): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('stock_movements')) {
            throw new \RuntimeException('No existe la tabla stock_movements.');
        }

        $companyId = (int) $adjustment->company_id;
        $warehouseId = (int) $adjustment->warehouse_id;
        $locationId = (int) $adjustment->location_id;

        $operationTypeId = static::v5632gAdjustmentOperationTypeId($companyId, $warehouseId, $locationId);

        $referenceBase = trim((string) ($adjustment->reference ?: ('AJU-' . $adjustment->id)));
        $movementReference = 'MOV-' . $referenceBase;

        $data = static::v5632gFilterColumns('stock_movements', [
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'stock_operation_type_id' => $operationTypeId,
            'source_location_id' => $locationId,
            'destination_location_id' => $locationId,
            'reference' => $movementReference,
            'movement_at' => $adjustment->adjustment_at ?? now(),
            'status' => 'done',
            'origin_document' => 'stock_adjustment:' . $referenceBase,
            'contact_id' => null,
            'notes' => 'Movimiento generado por ajuste de inventario. Ajuste: ' . $referenceBase . '. Motivo: ' . trim((string) ($adjustment->reason ?? '')),
            'created_by' => auth()->id(),
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) \Illuminate\Support\Facades\DB::table('stock_movements')->insertGetId($data);
    }

    protected static function v5632gAdjustmentOperationTypeId(int $companyId, int $warehouseId, int $locationId): ?int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('stock_operation_types')) {
            return null;
        }

        $query = \Illuminate\Support\Facades\DB::table('stock_operation_types');

        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_operation_types', 'company_id')) {
            $query->where(function ($q) use ($companyId): void {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_operation_types', 'warehouse_id')) {
            $query->where(function ($q) use ($warehouseId): void {
                $q->where('warehouse_id', $warehouseId)->orWhereNull('warehouse_id');
            });
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('stock_operation_types', 'is_active')) {
            $query->where('is_active', true);
        }

        $query->where(function ($q): void {
            if (\Illuminate\Support\Facades\Schema::hasColumn('stock_operation_types', 'operation_kind')) {
                $q->orWhere('operation_kind', 'inventory_adjustment');
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('stock_operation_types', 'code')) {
                $q->orWhere('code', 'AJU_INV')
                    ->orWhere('code', 'AJUSTE_INV')
                    ->orWhere('code', 'AJUSTE');
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('stock_operation_types', 'name')) {
                $q->orWhere('name', 'ilike', '%ajuste%');
            }
        });

        $existing = $query
            ->orderByRaw("case when code = 'AJU_INV' then 0 else 1 end")
            ->orderBy('id')
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        $data = static::v5632gFilterColumns('stock_operation_types', [
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'name' => 'Ajuste de inventario',
            'code' => 'AJU_INV',
            'operation_kind' => 'inventory_adjustment',
            'type' => 'internal',
            'direction' => 'internal',
            'source_location_id' => $locationId,
            'destination_location_id' => $locationId,
            'is_active' => true,
            'sequence' => 90,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            return (int) \Illuminate\Support\Facades\DB::table('stock_operation_types')->insertGetId($data);
        } catch (\Throwable $e) {
            $fallback = \Illuminate\Support\Facades\DB::table('stock_operation_types')
                ->orderBy('id')
                ->value('id');

            if ($fallback) {
                return (int) $fallback;
            }

            throw $e;
        }
    }

    protected static function v5632gCreateAdjustmentStockMovementLine(
        int $movementId,
        StockAdjustment $adjustment,
        StockAdjustmentLine $line,
        float $difference,
        ?float $unitCost,
        ?int $lotId
    ): void {
        if (! \Illuminate\Support\Facades\Schema::hasTable('stock_movement_lines')) {
            throw new \RuntimeException('No existe la tabla stock_movement_lines.');
        }

        $quantity = round($difference, 6);
        $cost = $unitCost !== null ? round((float) $unitCost, 6) : 0.0;

        $data = static::v5632gFilterColumns('stock_movement_lines', [
            'stock_movement_id' => $movementId,
            'product_id' => (int) $line->product_id,
            'product_variant_id' => $line->product_variant_id ? (int) $line->product_variant_id : null,
            'lot_id' => $lotId,
            'source_type' => 'stock_adjustment',
            'source_id' => (int) $adjustment->id,
            'source_line_type' => 'stock_adjustment_line',
            'source_line_id' => (int) $line->id,
            'requested_quantity' => $quantity,
            'done_quantity' => $quantity,
            'unit_cost' => $cost,
            'total_cost' => round(abs($quantity) * $cost, 6),
            'notes' => 'Ajuste de inventario. Diferencia: ' . number_format($quantity, 6, '.', ''),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $movementLineId = (int) \Illuminate\Support\Facades\DB::table('stock_movement_lines')->insertGetId($data);

        try {
            app(\App\Support\Inventory\StockMovementLineCostBackfiller::class)
                ->applyToLineId($movementLineId, 'stock_adjustment.unit_cost');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected static function v5632gFilterColumns(string $table, array $data): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            return $data;
        }

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);

        return array_intersect_key($data, array_flip($columns));
    }

}
