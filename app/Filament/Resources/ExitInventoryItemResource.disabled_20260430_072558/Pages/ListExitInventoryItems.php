<?php

namespace App\Filament\Resources\ExitInventoryItemResource\Pages;

use App\Filament\Resources\ExitInventoryItemResource;
use Filament\Resources\Pages\ListRecords;

class ListExitInventoryItems extends ListRecords
{
    protected static string $resource = ExitInventoryItemResource::class;


    public function getTitle(): string
    {
        return 'Entrega salidas almacén';
    }


}
