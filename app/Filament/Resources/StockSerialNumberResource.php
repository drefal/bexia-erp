<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockSerialNumberResource\Pages;
use App\Models\StockSerialNumber;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
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

    protected static ?int $navigationSort = 53;

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
                    ->label('Ver detalle'),                Tables\Actions\EditAction::make()
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


}
