<?php

namespace App\Filament\Resources\CustomBalloonOrderResource\Pages;

use App\Filament\Pages\CustomBalloonCatalogPreview;
use App\Filament\Resources\CustomBalloonOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomBalloonOrders extends ListRecords
{
    protected static string $resource =
        CustomBalloonOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('nuevo_pedido')
                ->label('Nuevo pedido')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->visible(
                    fn (): bool =>
                        CustomBalloonOrderResource::canCreate()
                )
                ->url(
                    fn (): string =>
                        CustomBalloonCatalogPreview::getUrl()
                ),
        ];
    }
}
