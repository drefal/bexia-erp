<?php

namespace App\Filament\Resources\CommerceConnectorVersionResource\Pages;

use App\Filament\Resources\CommerceConnectorVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCommerceConnectorVersions extends ListRecords
{
    protected static string $resource =
        CommerceConnectorVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
