<?php

namespace App\Filament\Resources\ExitWarehouseResource\Pages;

use App\Filament\Resources\ExitWarehouseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExitWarehouses extends ListRecords
{
    protected static string $resource = ExitWarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
