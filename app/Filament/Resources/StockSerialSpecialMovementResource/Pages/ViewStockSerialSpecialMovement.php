<?php

namespace App\Filament\Resources\StockSerialSpecialMovementResource\Pages;

use App\Filament\Resources\StockSerialSpecialMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewStockSerialSpecialMovement extends ViewRecord
{
    protected static string $resource = StockSerialSpecialMovementResource::class;

    public function getTitle(): string
    {
        return 'Detalle de auditoría de número de serie';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('backToIndex')
                ->label('Volver')
                ->url(static::getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }
}
