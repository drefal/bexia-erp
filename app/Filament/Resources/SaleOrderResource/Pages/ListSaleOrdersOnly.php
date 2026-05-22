<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSaleOrdersOnly extends ListRecords
{
    protected static string $resource = SaleOrderResource::class;

    public function getTitle(): string
    {
        return 'Órdenes de venta';
    }

    public function getBreadcrumb(): string
    {
        return 'Órdenes de venta';
    }

    public function getBreadcrumbs(): array
    {
        return [
            SaleOrderResource::getUrl('orders') => 'Órdenes de venta',
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'confirmed';
    }

    public function getTabs(): array
    {
        return [
            'confirmed' => Tab::make('Confirmadas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'confirmed')),

            'partially_delivered' => Tab::make('Parcialmente entregadas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'partially_delivered')),

            'delivered' => Tab::make('Entregadas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'delivered')),

            'cancelled' => Tab::make('Canceladas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'cancelled')),

            'all' => Tab::make('Todas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    'confirmed',
                    'partially_delivered',
                    'delivered',
                    'cancelled',
                ])),
        ];
    }
}
