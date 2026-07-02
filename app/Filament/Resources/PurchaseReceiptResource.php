<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseReceiptResource\Pages;
use App\Models\PurchaseReceipt;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseReceiptResource extends Resource
{
    protected static ?string $model = PurchaseReceipt::class;

    protected static ?string $navigationGroup = 'Compras';

    protected static ?string $navigationLabel = 'Recepciones de compra';

    protected static ?int $navigationSort = 50;
protected static ?string $modelLabel = 'recepción de compra';

    protected static ?string $pluralModelLabel = 'recepciones de compra';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static bool $isScopedToTenant = false;

public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('purchase_receipts', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.purchasereceiptresource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('purchases.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('purchases.view')
            );
    }

    /*
     * BEXIA_PURCHASE_RECEIPT_RESOURCE_RESPONSIVE_V5_79_76C
     * Visual-only responsive marker.
     */


    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->extraHeaderAttributes(['class' => 'bexia-prr-col-number bexia-prr-col-primary bexia-prr-col-reference bexia-prr-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-prr-col-number bexia-prr-col-primary bexia-prr-col-reference bexia-prr-col-context'])
                    ->label('Recepción')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('purchase_order_id')
                    ->extraHeaderAttributes(['class' => 'bexia-prr-col-purchase-order bexia-prr-col-reference bexia-prr-col-related bexia-prr-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-prr-col-purchase-order bexia-prr-col-reference bexia-prr-col-related bexia-prr-col-context'])
                    ->label('Orden de compra')
                    ->state(fn (PurchaseReceipt $record): string => static::orderNumber($record))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        if (! Schema::hasTable('purchase_orders')) {
                            return $query;
                        }

                        $orderIds = DB::table('purchase_orders')
                            ->where('number', 'ilike', '%' . $search . '%')
                            ->pluck('id')
                            ->all();

                        return $query->whereIn('purchase_order_id', $orderIds ?: [-1]);
                    }),

                Tables\Columns\TextColumn::make('supplier')
                    ->extraHeaderAttributes(['class' => 'bexia-prr-col-supplier bexia-prr-col-party bexia-prr-col-long-text bexia-prr-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-prr-col-supplier bexia-prr-col-party bexia-prr-col-long-text bexia-prr-col-context'])
                    ->label('Proveedor')
                    ->state(fn (PurchaseReceipt $record): string => static::supplierName($record))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->extraHeaderAttributes(['class' => 'bexia-prr-col-status bexia-prr-col-badge bexia-prr-col-state bexia-prr-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-prr-col-status bexia-prr-col-badge bexia-prr-col-state bexia-prr-col-context'])
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'Borrador',
                        'received' => 'Recibida',
                        'done' => 'Validada',
                        'cancelled' => 'Cancelada',
                        default => $state ?: 'Sin estado',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'draft' => 'warning',
                        'received', 'done' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('warehouse_id')
                    ->extraHeaderAttributes(['class' => 'bexia-prr-col-warehouse bexia-prr-col-location bexia-prr-col-related bexia-prr-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-prr-col-warehouse bexia-prr-col-location bexia-prr-col-related bexia-prr-col-context'])
                    ->label('Almacén')
                    ->state(fn (PurchaseReceipt $record): string => static::warehouseLabel($record))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('location_id')
                    ->extraHeaderAttributes(['class' => 'bexia-prr-col-location-detail bexia-prr-col-location bexia-prr-col-related bexia-prr-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-prr-col-location-detail bexia-prr-col-location bexia-prr-col-related bexia-prr-col-context'])
                    ->label('Ubicación')
                    ->state(fn (PurchaseReceipt $record): string => static::locationLabel($record))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('stock_movement_id')
                    ->extraHeaderAttributes(['class' => 'bexia-prr-col-stock-movement bexia-prr-col-movement bexia-prr-col-reference bexia-prr-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-prr-col-stock-movement bexia-prr-col-movement bexia-prr-col-reference bexia-prr-col-context'])
                    ->label('Movimiento')
                    ->formatStateUsing(fn ($state): string => $state ? ('#' . $state) : 'Pendiente')
                    ->badge()
                    ->color(fn ($state): string => $state ? 'success' : 'warning')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('received_at')
                    ->extraHeaderAttributes(['class' => 'bexia-prr-col-received-at bexia-prr-col-date bexia-prr-col-context bexia-prr-col-timeline'])
                    ->extraCellAttributes(['class' => 'bexia-prr-col-received-at bexia-prr-col-date bexia-prr-col-context bexia-prr-col-timeline'])
                    ->label('Recibida')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendiente')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_with_tax')
                    ->extraHeaderAttributes(['class' => 'bexia-prr-col-total bexia-prr-col-money bexia-prr-col-number-value bexia-prr-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-prr-col-total bexia-prr-col-money bexia-prr-col-number-value bexia-prr-col-context'])
                    ->label('Total')
                    ->money('MXN')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->extraHeaderAttributes(['class' => 'bexia-prr-col-created-at bexia-prr-col-date bexia-prr-col-context bexia-prr-col-timeline'])
                    ->extraCellAttributes(['class' => 'bexia-prr-col-created-at bexia-prr-col-date bexia-prr-col-context bexia-prr-col-timeline'])
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->extraAttributes(['class' => 'bexia-prr-filter-status bexia-prr-filter-field bexia-prr-filter-state'])
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'received' => 'Recibida',
                        'done' => 'Validada',
                        'cancelled' => 'Cancelada',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('review_receipt')
                    ->label('Revisar')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (PurchaseReceipt $record): string => static::receiptUrl($record, false)),

                Tables\Actions\Action::make('print_receipt')
                    ->label('Imprimir')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (PurchaseReceipt $record): string => static::receiptUrl($record, true))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('return_to_supplier')
                    ->label('Devolver a proveedor')
                    ->icon('heroicon-o-arrow-uturn-right')
                    ->color('warning')
                    ->visible(fn (PurchaseReceipt $record): bool => (string) $record->status === 'done' && ! empty($record->stock_movement_id))
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->extraAttributes(['class' => 'bexia-prr-modal-field bexia-prr-modal-reason-field bexia-prr-long-field'])
                            ->label('Motivo de la devolución')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Devolver a proveedor')
                    ->modalDescription('Se creará una salida de inventario hacia proveedor con costo original de recepción y lote/serie cuando aplique.')
                    ->modalSubmitActionLabel('Registrar devolución')
                    ->action(function (PurchaseReceipt $record, array $data): void {
                        try {
                            $returnId = app(\App\Support\PurchaseReturnInventoryPoster::class)
                                ->createFromReceipt((int) $record->id, (string) ($data['reason'] ?? ''));

                            \Filament\Notifications\Notification::make()
                                ->title('Devolución a proveedor registrada')
                                ->body('Se registró la devolución a proveedor #' . $returnId . ' y se descontó el inventario disponible.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);

                            \Filament\Notifications\Notification::make()
                                ->title('No se pudo registrar la devolución')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('open_order')
                    ->label('Abrir OC')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (PurchaseReceipt $record): ?string => static::orderUrl($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseReceipts::route('/'),
            'view_panel' => Pages\ViewPurchaseReceiptPanel::route('/{record}/panel'),
        ];
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

        if (is_object($tenant) && isset($tenant->id)) {
            return (int) $tenant->id;
        }

        if (is_numeric($tenant)) {
            return (int) $tenant;
        }

        return null;
    }

    protected static function tenantKey(PurchaseReceipt $record): string|int
    {
        $tenant = request()->route('tenant');

        if (is_object($tenant) && method_exists($tenant, 'getRouteKey')) {
            return $tenant->getRouteKey();
        }

        if ($tenant) {
            return $tenant;
        }

        $companyId = static::currentCompanyId();

        return $companyId ?: (int) ($record->company_id ?? 0);
    }

    protected static function orderNumber(PurchaseReceipt $record): string
    {
        if (! $record->purchase_order_id || ! Schema::hasTable('purchase_orders')) {
            return '—';
        }

        $number = DB::table('purchase_orders')
            ->where('id', $record->purchase_order_id)
            ->value('number');

        return $number ?: ('OC #' . $record->purchase_order_id);
    }

    protected static function supplierName(PurchaseReceipt $record): string
    {
        if (! $record->purchase_order_id || ! Schema::hasTable('purchase_orders')) {
            return '—';
        }

        $supplier = DB::table('purchase_orders')
            ->where('id', $record->purchase_order_id)
            ->value('supplier_name');

        return $supplier ?: '—';
    }

    protected static function warehouseLabel(PurchaseReceipt $record): string
    {
        return static::labelFromTable('warehouses', $record->warehouse_id, ['code'], ['name']);
    }

    protected static function locationLabel(PurchaseReceipt $record): string
    {
        return static::labelFromTable('stock_locations', $record->location_id, ['code'], ['name']);
    }

    protected static function labelFromTable(string $table, mixed $id, array $codeColumns, array $nameColumns): string
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
            return $name . ' (' . $code . ')';
        }

        return $name !== '' ? $name : ($code !== '' ? $code : ('#' . $id));
    }

    protected static function receiptUrl(PurchaseReceipt $record, bool $pdf): string
    {
        $tenant = static::tenantKey($record);

        return url('/admin/' . $tenant . '/purchase-receipts/' . $record->id . ($pdf ? '/pdf' : ''));
    }

    protected static function orderUrl(PurchaseReceipt $record): ?string
    {
        if (! $record->purchase_order_id) {
            return null;
        }

        $tenant = static::tenantKey($record);

        return url('/admin/' . $tenant . '/purchase-orders/' . $record->purchase_order_id . '/edit');
    }
}
