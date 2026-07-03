<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockQuantResource\Pages;
use App\Models\StockQuant;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockQuantResource extends Resource
{
    /**
     * BEXIA_STOCK_QUANT_RESOURCE_RESPONSIVE_V5_79_109C
     *
     * Visual-only responsive classes for StockQuantResource.
     */
    protected static ?string $model = StockQuant::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Existencias';

    protected static ?string $modelLabel = 'existencia';

    protected static ?string $pluralModelLabel = 'existencias';

    protected static ?int $navigationSort = 50;

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        $query = StockQuant::query()
            ->with(['warehouse', 'location']);

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
            'resources.stockquantresource',
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-stqu-header bexia-stqu-col-warehouse',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-stqu-cell bexia-stqu-col-warehouse bexia-stqu-col-context',
                    ])
                    ->label('Almacén')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('location.name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-stqu-header bexia-stqu-col-location',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-stqu-cell bexia-stqu-col-location bexia-stqu-col-context',
                    ])
                    ->label('Ubicación')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_label')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-stqu-header bexia-stqu-col-product',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-stqu-cell bexia-stqu-col-product bexia-stqu-col-main bexia-stqu-col-wide',
                    ])
                    ->label('Producto')
                    ->state(fn (StockQuant $record): string => static::productLabel($record->product_id))
                    ->searchable(false)
                    ->sortable(false)
                    ->wrap(),

                Tables\Columns\TextColumn::make('variant_label')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-stqu-header bexia-stqu-col-variant',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-stqu-cell bexia-stqu-col-variant bexia-stqu-col-wide',
                    ])
                    ->label('Variante')
                    ->state(fn (StockQuant $record): string => static::variantLabel($record->product_variant_id))
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-stqu-header bexia-stqu-col-quantity',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-stqu-cell bexia-stqu-col-quantity bexia-stqu-col-number bexia-stqu-col-compact',
                    ])
                    ->label('Cantidad física')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('reserved_quantity')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-stqu-header bexia-stqu-col-reserved',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-stqu-cell bexia-stqu-col-reserved bexia-stqu-col-number bexia-stqu-col-compact',
                    ])
                    ->label('Reservado')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('available_quantity')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-stqu-header bexia-stqu-col-available',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-stqu-cell bexia-stqu-col-available bexia-stqu-col-number bexia-stqu-col-compact',
                    ])
                    ->label('Disponible')
                    ->alignRight()
                    ->state(fn (StockQuant $record): float => $record->available_quantity)
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2))
                    ->sortable(false),

                Tables\Columns\TextColumn::make('average_cost')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-stqu-header bexia-stqu-col-average-cost',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-stqu-cell bexia-stqu-col-average-cost bexia-stqu-col-number bexia-stqu-col-compact',
                    ])
                    ->label('Costo prom.')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => $state === null ? '—' : '$ ' . number_format((float) $state, 2))
                    ->sortable(),
])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Almacén')
                    ->options(fn (): array => static::warehouseOptions()),

                Tables\Filters\SelectFilter::make('location_id')
                    ->label('Ubicación')
                    ->options(fn (): array => static::locationOptions()),
            ])
            ->headerActions([
                \Filament\Tables\Actions\Action::make('refresh_inventory_stock_table')
                    ->label('Actualizar existencias')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->button()
                    ->action(function ($livewire): void {
                        if (method_exists($livewire, 'resetTable')) {
                            $livewire->resetTable();
                        }

                        $livewire->dispatch('$refresh');

                        \Filament\Notifications\Notification::make()
                            ->title('Existencias actualizadas')
                            ->success()
                            ->send();
                    }),
            ])

            ->actions([
                // Intencionalmente sin editar.
                // El stock debe cambiar por movimientos o ajustes.
            ])
            ->bulkActions([
                // Sin acciones masivas en existencias.
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockQuants::route('/'),
        ];
    }

    protected static function productLabel($productId): string
    {
        return static::labelFromTable('products', $productId, ['sku', 'internal_reference', 'reference', 'code'], ['name', 'description']);
    }

    protected static function variantLabel($variantId): string
    {
        if (! $variantId) {
            return '';
        }

        // En Bexia las variantes actuales viven en products.
        if (Schema::hasTable('products')) {
            $row = DB::table('products')
                ->where('id', $variantId)
                ->first();

            if ($row) {
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

                $group = Schema::hasColumn('products', 'variant_group')
                    ? trim((string) ($row->variant_group ?? ''))
                    : '';

                $value = Schema::hasColumn('products', 'variant_value')
                    ? trim((string) ($row->variant_value ?? ''))
                    : '';

                $variantText = '';

                if ($group !== '' && $value !== '') {
                    $variantText = $group . ': ' . $value;
                } elseif ($value !== '') {
                    $variantText = $value;
                } elseif (Schema::hasColumn('products', 'variant_name')) {
                    $variantText = trim((string) ($row->variant_name ?? ''));
                } elseif (Schema::hasColumn('products', 'name')) {
                    $variantText = trim((string) ($row->name ?? ''));
                }

                if ($reference !== '' && $variantText !== '') {
                    return $reference . ' - ' . $variantText;
                }

                return $variantText ?: ($reference ?: ('Variante #' . $variantId));
            }
        }

        return 'Variante #' . $variantId;
    }



    protected static function labelFromTable(string $table, $id, array $codeColumns = [], array $nameColumns = []): string
    {
        if (! $id || ! Schema::hasTable($table)) {
            return '—';
        }

        $row = DB::table($table)
            ->where('id', $id)
            ->first();

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

        if ($name !== '') {
            return $name;
        }

        if ($code !== '') {
            return $code;
        }

        return '#' . $id;
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

        return $query
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function locationOptions(): array
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

        return $query
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
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
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
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

        if (method_exists($user, 'can')) {
            return $user->can($permission);
        }

        return false;
    }
}
