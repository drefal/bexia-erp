<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductPriceCostAuditResource\Pages;
use App\Models\ProductPriceCostAudit;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductPriceCostAuditResource extends Resource
{
    protected static ?string $model = ProductPriceCostAudit::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Listas de Precios';

    protected static ?int $navigationSort = 320;
protected static ?string $modelLabel = 'auditoría de precio/costo';

    protected static ?string $pluralModelLabel = 'auditoría de precios y costos de productos';
    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        $query = ProductPriceCostAudit::query()->with(['product', 'user']);

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('product_price_cost_audits', 'company_id')) {
            $query->where('company_id', $companyId);
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('changed_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_display')
                    ->label('Producto')
                    ->state(fn (ProductPriceCostAudit $record): string => trim(
                        ($record->product_reference ? $record->product_reference . ' - ' : '') .
                        ($record->product_name ?: ($record->product?->name ?? 'Producto #' . $record->product_id))
                    ))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            $query
                                ->where('product_name', 'ilike', '%' . $search . '%')
                                ->orWhere('product_reference', 'ilike', '%' . $search . '%');
                        });
                    }),

                Tables\Columns\TextColumn::make('field_label')
                    ->label('Campo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('old_value')
                    ->label('Antes')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('new_value')
                    ->label('Nuevo')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('user_display')
                    ->label('Usuario')
                    ->state(fn (ProductPriceCostAudit $record): string => static::userLabel($record->user_id))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('user', function (Builder $query) use ($search): void {
                            foreach (['name', 'email'] as $column) {
                                if (Schema::hasColumn('users', $column)) {
                                    $query->orWhere($column, 'ilike', '%' . $search . '%');
                                }
                            }
                        });
                    }),

                Tables\Columns\TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'manual' => 'Manual',
                        'sistema' => 'Sistema',
                        'importacion' => 'Importación',
                        'compra' => 'Compra',
                        default => $state ?: '—',
                    }),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Motivo')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('field_name')
                    ->label('Campo')
                    ->options([
                        'sale_price' => 'Precio de venta sin IVA',
                        'sale_tax_rate' => 'IVA venta %',
                        'average_cost_without_tax' => 'Costo promedio actual sin IVA',
                        'purchase_tax_rate' => 'IVA compra %',
                        'purchase_price' => 'Precio de compra',
                        'standard_cost' => 'Costo estándar',
                        'purchase_pack_units' => 'UXES / unidades por empaque',
                        'purchase_min_quantity' => 'Compra mínima',
                        'purchase_multiple_quantity' => 'Múltiplo de compra',
                        'purchase_lead_time_days' => 'Plazo compra / entrega',
                        'purchase_delivery_days' => 'Plazo compra / entrega',
                        'purchase_delay' => 'Plazo compra / entrega',
                    ]),

                Tables\Filters\SelectFilter::make('source')
                    ->label('Origen')
                    ->options([
                        'manual' => 'Manual',
                        'sistema' => 'Sistema',
                        'importacion' => 'Importación',
                        'compra' => 'Compra',
                    ]),
            ])
            ->defaultSort('changed_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductPriceCostAudits::route('/'),
        ];
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

protected static function userCanView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (
            method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole([
                'super_admin',
                'Super Admin',
                'Super Administrador',
                'admin',
                'Administrador',
                'Admin Empresa',
                'Admin Grupo',
                'Inventarios',
                'Reportes',
            ])
        ) {
            return true;
        }

        return method_exists($user, 'can')
            ? $user->can('inventory.view_product_price_cost_audit') || $user->can('inventory.view')
            : false;
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

    protected static function userLabel($userId): string
    {
        if (! $userId || ! Schema::hasTable('users')) {
            return 'Sistema';
        }

        $row = DB::table('users')->where('id', $userId)->first();

        if (! $row) {
            return 'Usuario #' . $userId;
        }

        foreach (['name', 'email'] as $column) {
            if (Schema::hasColumn('users', $column) && trim((string) ($row->{$column} ?? '')) !== '') {
                return (string) $row->{$column};
            }
        }

        return 'Usuario #' . $userId;
    }
}
