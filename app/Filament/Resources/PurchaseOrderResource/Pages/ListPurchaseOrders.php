<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('create_purchase_order_from_xml')
                ->label('Crear desde XML')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->url(fn (): string => url('/admin/' . (int) request()->route('tenant') . '/purchase-orders/from-xml')),

        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'active';
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Activas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    'draft',
                    'pending',
                    'confirmed',
                    'approved',
                    'ordered',
                    'sent',
                    'partially_received',
                    'partial_received',
                ])),

            'draft' => Tab::make('Borrador')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    'draft',
                    'pending',
                ])),

            'ordered' => Tab::make('Confirmadas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    'confirmed',
                    'approved',
                    'ordered',
                    'sent',
                ])),

            'partial' => Tab::make('Parcial recibidas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    'partially_received',
                    'partial_received',
                ])),

            'received' => Tab::make('Recibidas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    'received',
                    'fully_received',
                    'completed',
                    'closed',
                ])),

            'cancelled' => Tab::make('Canceladas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    'cancelled',
                    'canceled',
                ])),

            'all' => Tab::make('Todas'),
        ];
    }


}
