<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSaleOrders extends ListRecords
{
    protected static string $resource = SaleOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nueva cotización')
                ->label('Nueva venta'),
        ];
    }
    public function getTabs(): array
    {
        return [
            'cotizaciones' => Tab::make('Cotizaciones')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'draft')),

            'ordenes' => Tab::make('Órdenes de venta')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', ['confirmed', 'partially_delivered', 'delivered'])),

            'por_aprobar' => Tab::make('Por aprobar margen')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('margin_approval_status', ['required', 'pending', 'rejected'])),

            'canceladas' => Tab::make('Canceladas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'cancelled')),

            'todas' => Tab::make('Todas'),
        ];
    }


}
