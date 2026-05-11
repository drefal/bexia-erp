<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use Filament\Resources\Pages\ListRecords;

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


}
