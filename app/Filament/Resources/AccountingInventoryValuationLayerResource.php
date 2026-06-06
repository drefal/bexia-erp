<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountingInventoryValuationLayerResource\Pages;
use App\Models\AccountingInventoryValuationLayer;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AccountingInventoryValuationLayerResource extends Resource
{
    protected static ?string $model = AccountingInventoryValuationLayer::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Contabilidad';

    protected static ?string $navigationLabel = 'Movimientos de inventario';

    protected static ?string $modelLabel = 'Movimiento de inventario';

    protected static ?string $pluralModelLabel = 'Movimientos de inventario';

    protected static ?int $navigationSort = 40;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        try {
            $tenant = Filament::getTenant();

            if ($tenant && Schema::hasColumn('accounting_inventory_valuation_layers', 'company_id')) {
                $query->where('company_id', $tenant->getKey());
            }
        } catch (Throwable $e) {
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function operationLabel(?string $operation): string
    {
        return [
            'purchase_receipt' => 'Entrada por compra',
            'sale_issue' => 'Salida por venta',
            'adjustment_in' => 'Ajuste positivo',
            'adjustment_out' => 'Ajuste negativo',
            'customer_return' => 'Devolución de cliente',
            'supplier_return' => 'Devolución a proveedor',
            'reversal' => 'Reversa',
        ][$operation] ?? ($operation ?: 'Sin operación');
    }

    public static function operationColor(?string $operation): string
    {
        return [
            'purchase_receipt' => 'success',
            'sale_issue' => 'warning',
            'adjustment_in' => 'info',
            'adjustment_out' => 'danger',
            'customer_return' => 'success',
            'supplier_return' => 'warning',
            'reversal' => 'gray',
        ][$operation] ?? 'gray';
    }

    public static function directionLabel(?string $direction): string
    {
        return [
            'in' => 'Entrada',
            'out' => 'Salida',
        ][$direction] ?? ($direction ?: 'Sin dirección');
    }

    public static function directionColor(?string $direction): string
    {
        return [
            'in' => 'success',
            'out' => 'warning',
        ][$direction] ?? 'gray';
    }

    public static function sourceLabel(?string $source): string
    {
        return [
            'purchase_order_lines' => 'Línea de compra',
            'sales_order_lines' => 'Línea de venta',
            'pos_order_lines' => 'Línea POS',
            'pos_order_refund_lines' => 'Línea devolución POS',
            'manual_inventory' => 'Inventario manual',
            'accounting.reversal' => 'Reversa contable',
        ][$source] ?? ($source ?: 'Sin origen');
    }

public static function shouldRegisterNavigation(): bool
{
    return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
        'resources.accountinginventoryvaluationlayerresource',
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
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('company_id')
                    ->label('Empresa')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_id')
                    ->label('Producto')
                    ->sortable(),

                Tables\Columns\TextColumn::make('operation_type')
                    ->label('Operación')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::operationLabel($state))
                    ->color(fn ($state) => self::operationColor($state))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('direction')
                    ->label('Movimiento')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::directionLabel($state))
                    ->color(fn ($state) => self::directionColor($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('movement_date')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 6)),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Costo unit.')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 2) . ' MXN'),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Costo total')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 2) . ' MXN'),

                Tables\Columns\TextColumn::make('source_type')
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::sourceLabel($state))
                    ->color('info')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('source_id')
                    ->label('ID origen')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('accounting_entry_id')
                    ->label('Asiento')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('operation_type')
                    ->label('Operación')
                    ->options(fn () => self::operationTypeOptions()),

                Tables\Filters\SelectFilter::make('direction')
                    ->label('Movimiento')
                    ->options([
                        'in' => 'Entrada',
                        'out' => 'Salida',
                    ]),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    private static function operationTypeOptions(): array
    {
        try {
            $query = DB::table('accounting_inventory_valuation_layers')
                ->whereNotNull('operation_type');

            $tenant = Filament::getTenant();

            if ($tenant && Schema::hasColumn('accounting_inventory_valuation_layers', 'company_id')) {
                $query->where('company_id', $tenant->getKey());
            }

            return $query
                ->distinct()
                ->orderBy('operation_type')
                ->pluck('operation_type', 'operation_type')
                ->mapWithKeys(fn ($value, $key) => [$key => self::operationLabel($value)])
                ->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountingInventoryValuationLayers::route('/'),
        ];
    }
}
