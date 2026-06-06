<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleDeliveryResource\Pages;
use App\Models\SaleDelivery;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SaleDeliveryResource extends Resource
{
    protected static ?string $model = SaleDelivery::class;

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Entregas de venta';

    protected static ?int $navigationSort = 20;
protected static ?string $modelLabel = 'entrega de venta';

    protected static ?string $pluralModelLabel = 'entregas de venta';

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static bool $isScopedToTenant = false;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.saledeliveryresource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
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

        return $user->can('sales.view') || $user->can('inventory.view');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('order');

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Entrega')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('order.number')
                    ->label('Orden de venta')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('order.customer_name')
                    ->label('Cliente')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'Borrador',
                        'done' => 'Validada',
                        'cancelled' => 'Cancelada',
                        default => $state ?: 'Sin estado',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'draft' => 'warning',
                        'done' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('delivery_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'complete' => 'Completa',
                        'partial' => 'Parcial',
                        default => $state ?: 'Sin tipo',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'complete' => 'success',
                        'partial' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('lines_sum_quantity')
                    ->label('Cantidad')
                    ->state(function (SaleDelivery $record): string {
                        $qty = DB::table('sale_delivery_lines')
                            ->where('sale_delivery_id', $record->id)
                            ->sum('quantity');

                        return number_format((float) $qty, 2);
                    }),

                Tables\Columns\TextColumn::make('stock_movement_id')
                    ->label('Movimiento')
                    ->formatStateUsing(fn ($state): string => $state ? ('#' . $state) : 'Pendiente')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('delivered_at')
                    ->label('Validada')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendiente')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'done' => 'Validada',
                        'cancelled' => 'Cancelada',
                    ]),

                Tables\Filters\SelectFilter::make('delivery_type')
                    ->label('Tipo')
                    ->options([
                        'partial' => 'Parcial',
                        'complete' => 'Completa',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Revisar'),

                Tables\Actions\Action::make('print_delivery')
                    ->label('Imprimir')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (SaleDelivery $record): string => route('sales.deliveries.print', ['saleDelivery' => $record->id]))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('return_delivery')
                    ->label('Devolver')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (SaleDelivery $record): bool => (string) $record->status === 'done' && ! empty($record->stock_movement_id))
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Motivo de la devolución')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Devolver entrega')
                    ->modalDescription('Se creará una devolución total de esta entrega y el inventario regresará al almacén con lote/serie/costo original.')
                    ->modalSubmitActionLabel('Registrar devolución')
                    ->action(function (SaleDelivery $record, array $data): void {
                        try {
                            $returnId = app(\App\Http\Controllers\SaleDeliveryController::class)
                                ->createSaleReturnFromDelivery($record, (string) ($data['reason'] ?? ''));

                            \Filament\Notifications\Notification::make()
                                ->title('Devolución registrada')
                                ->body('Se registró la devolución de venta #' . $returnId . ' y se reingresó el inventario.')
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

                Tables\Actions\Action::make('validate_delivery')
                    ->label('Validar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SaleDelivery $record): bool => (string) $record->status === 'draft' && empty($record->stock_movement_id))
                    ->requiresConfirmation()
                    ->modalHeading('Validar entrega')
                    ->modalDescription('Se validará la entrega, se generará el movimiento de salida y se descontará inventario. ¿Deseas continuar?')
                    ->modalSubmitActionLabel('Validar entrega')
                    ->action(function (SaleDelivery $record): void {
                        $beforeStatus = (string) $record->status;

                        app(\App\Http\Controllers\SaleDeliveryController::class)
                            ->validateDelivery(request(), $record);

                        $record->refresh();

                        if ($beforeStatus === 'draft' && (string) $record->status === 'done') {
                            Notification::make()
                                ->title('Entrega validada')
                                ->body('Se generó el movimiento de salida y se descontó inventario.')
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('No se pudo validar la entrega')
                            ->body('Revisa existencias o abre la orden para ver el detalle.')
                            ->danger()
                            ->send();
                    }),

                Tables\Actions\Action::make('open_order')
                    ->label('Abrir orden')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(function (SaleDelivery $record): ?string {
                        $tenant = static::currentCompanyId();

                        if (! $tenant) {
                            $tenant = $record->company_id;
                        }

                        if (! $record->sales_order_id || ! $tenant) {
                            return null;
                        }

                        return url('/admin/' . $tenant . '/sale-orders/' . $record->sales_order_id . '/edit');
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSaleDeliveries::route('/'),
            'view' => Pages\ViewSaleDelivery::route('/{record}'),
        ];
    }

    protected static function currentCompanyId(): ?int
    {
        try {
            $tenant = Filament::getTenant();

            if (is_object($tenant) && isset($tenant->id)) {
                return (int) $tenant->id;
            }

            if (is_numeric($tenant)) {
                return (int) $tenant;
            }
        } catch (\Throwable $e) {
            //
        }

        $routeTenant = request()->route('tenant');

        if (is_object($routeTenant) && isset($routeTenant->id)) {
            return (int) $routeTenant->id;
        }

        if (is_numeric($routeTenant)) {
            return (int) $routeTenant;
        }

        return null;
    }
}
