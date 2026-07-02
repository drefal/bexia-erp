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
    /**
     * BEXIA_ACCOUNTING_INVENTORY_VALUATION_LAYER_RESOURCE_RESPONSIVE_V5_79_97C
     *
     * Visual-only responsive classes for AccountingInventoryValuationLayerResource.
     */
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
        return $form
            ->extraAttributes([
                'class' => 'bexia-aivl-form bexia-aivl-shell bexia-aivl-readonly-form',
            ])
            ->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aivl-header bexia-aivl-col-id',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aivl-cell bexia-aivl-col-id bexia-aivl-col-compact',
                    ])
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('company_id')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aivl-header bexia-aivl-col-company',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aivl-cell bexia-aivl-col-company bexia-aivl-col-compact',
                    ])
                    ->label('Empresa')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_id')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aivl-header bexia-aivl-col-product',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aivl-cell bexia-aivl-col-product bexia-aivl-col-reference',
                    ])
                    ->label('Producto')
                    ->sortable(),

                Tables\Columns\TextColumn::make('operation_type')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aivl-header bexia-aivl-col-operation',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aivl-cell bexia-aivl-col-operation bexia-aivl-col-badge',
                    ])
                    ->label('Operación')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::operationLabel($state))
                    ->color(fn ($state) => self::operationColor($state))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('direction')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aivl-header bexia-aivl-col-direction',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aivl-cell bexia-aivl-col-direction bexia-aivl-col-badge',
                    ])
                    ->label('Movimiento')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::directionLabel($state))
                    ->color(fn ($state) => self::directionColor($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('movement_date')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aivl-header bexia-aivl-col-date',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aivl-cell bexia-aivl-col-date bexia-aivl-col-compact',
                    ])
                    ->label('Fecha')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aivl-header bexia-aivl-col-quantity',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aivl-cell bexia-aivl-col-quantity bexia-aivl-col-number',
                    ])
                    ->label('Cantidad')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 6)),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aivl-header bexia-aivl-col-unit-cost',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aivl-cell bexia-aivl-col-unit-cost bexia-aivl-col-money',
                    ])
                    ->label('Costo unit.')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 2) . ' MXN'),

                Tables\Columns\TextColumn::make('total_cost')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aivl-header bexia-aivl-col-total-cost',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aivl-cell bexia-aivl-col-total-cost bexia-aivl-col-money',
                    ])
                    ->label('Costo total')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 2) . ' MXN'),

                Tables\Columns\TextColumn::make('source_type')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aivl-header bexia-aivl-col-source',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aivl-cell bexia-aivl-col-source bexia-aivl-col-badge',
                    ])
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::sourceLabel($state))
                    ->color('info')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('source_id')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aivl-header bexia-aivl-col-source-id',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aivl-cell bexia-aivl-col-source-id bexia-aivl-col-reference',
                    ])
                    ->label('ID origen')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('accounting_entry_id')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-aivl-header bexia-aivl-col-entry',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-aivl-cell bexia-aivl-col-entry bexia-aivl-col-reference',
                    ])
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
