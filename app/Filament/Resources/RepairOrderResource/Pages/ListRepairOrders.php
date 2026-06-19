<?php

namespace App\Filament\Resources\RepairOrderResource\Pages;

use App\Filament\Resources\RepairOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRepairOrders extends ListRecords
{
    protected static string $resource = RepairOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear reparación')
                ->visible(fn (): bool => RepairOrderResource::canCreate()),
        ];
    }
}
