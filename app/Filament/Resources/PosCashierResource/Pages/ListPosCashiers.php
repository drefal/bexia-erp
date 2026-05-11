<?php

namespace App\Filament\Resources\PosCashierResource\Pages;

use App\Filament\Resources\PosCashierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPosCashiers extends ListRecords
{
    protected static string $resource = PosCashierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo cajero'),
        ];
    }
}
