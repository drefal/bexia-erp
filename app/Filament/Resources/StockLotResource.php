<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockLotResource\Pages;
use App\Models\StockLot;
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

class StockLotResource extends Resource
{
    protected static ?string $model = StockLot::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Lotes';

    protected static ?string $modelLabel = 'lote';

    protected static ?string $pluralModelLabel = 'lotes';

    protected static ?int $navigationSort = 52;

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
        $query = StockLot::query()->withCount('serialNumbers');

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

                Forms\Components\Section::make('Datos del lote')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Producto')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => static::productSearchOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => static::productLabel($value))
                            ->required()
                            ->helperText('Solo aparecen productos configurados con seguimiento por lote o número de serie.')
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set): void {
                                $set('product_variant_id', null);
                            })
                            ->columnSpan(5),

                        Forms\Components\Select::make('product_variant_id')
                            ->label('Variante')
                            ->options(fn (Forms\Get $get): array => static::variantOptions($get('product_id')))
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Sin variante')
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('lot_number')
                            ->label('Número de lote')
                            ->required()
                            ->maxLength(120)
                            ->columnSpan(3),

                        Forms\Components\DatePicker::make('expiration_date')
                            ->label('Fecha de caducidad')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->columnSpan(3),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options(static::statusOptions())
                            ->default('available')
                            ->required()
                            ->native(false)
                            ->columnSpan(3),

                        Forms\Components\Select::make('supplier_contact_id')
                            ->label('Proveedor')
                            ->options(fn (): array => static::contactOptions())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Sin proveedor')
                            ->visible(fn (): bool => Schema::hasTable('contacts'))
                            ->columnSpan(6),
                    ])
                    ->columns(12),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('lot_number')
                    ->label('Lote')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_label')
                    ->label('Producto')
                    ->state(fn (StockLot $record): string => static::productLabel($record->product_id) ?: '—')
                    ->searchable(false)
                    ->sortable(false)
                    ->wrap(),

                Tables\Columns\TextColumn::make('variant_label')
                    ->label('Variante')
                    ->state(fn (StockLot $record): string => static::productLabel($record->product_variant_id) ?: '—')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('expiration_date')
                    ->label('Caducidad')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('serial_numbers_count')
                    ->label('Series')
                    ->alignRight()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                    ->color(fn (?string $state): string => static::statusColor($state))
                    ->sortable(),

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

                Tables\Filters\Filter::make('expired')
                    ->label('Caducados')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('expiration_date')->whereDate('expiration_date', '<', today())),

                Tables\Filters\Filter::make('with_expiration')
                    ->label('Con caducidad')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('expiration_date')),
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
            
            
            'print' => Pages\PrintStockLot::route('/{record}/print'),'view' => Pages\ViewStockLot::route('/{record}/view'),'index' => Pages\ListStockLots::route('/'),
            'create' => Pages\CreateStockLot::route('/create'),
            'edit' => Pages\EditStockLot::route('/{record}/edit'),
        ];
    }

    protected static function statusOptions(): array
    {
        return [
            'available' => 'Disponible',
            'reserved' => 'Reservado',
            'depleted' => 'Agotado',
            'blocked' => 'Bloqueado',
            'expired' => 'Caducado',
            'scrapped' => 'Merma / desecho',
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
            'reserved' => 'warning',
            'depleted', 'expired', 'scrapped' => 'danger',
            'blocked' => 'gray',
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
            $query->whereIn('tracking', ['lot', 'serial']);
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

    protected static function contactOptions(): array
    {
        if (! Schema::hasTable('contacts')) {
            return [];
        }

        $query = DB::table('contacts');

        if (Schema::hasColumn('contacts', 'company_id')) {
            $companyId = static::currentCompanyId();

            if ($companyId) {
                $query->where('company_id', $companyId);
            }
        }

        if (Schema::hasColumn('contacts', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query
            ->orderBy(Schema::hasColumn('contacts', 'name') ? 'name' : 'id')
            ->limit(100)
            ->get()
            ->mapWithKeys(function ($row): array {
                $name = trim((string) ($row->name ?? $row->display_name ?? $row->business_name ?? ''));
                $email = trim((string) ($row->email ?? ''));

                $label = $name !== '' ? $name : ('Contacto #' . $row->id);

                if ($email !== '') {
                    $label .= ' - ' . $email;
                }

                return [$row->id => $label];
            })
            ->all();
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
