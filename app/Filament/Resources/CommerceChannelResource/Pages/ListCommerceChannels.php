<?php

namespace App\Filament\Resources\CommerceChannelResource\Pages;

use App\Filament\Resources\CommerceChannelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCommerceChannels extends ListRecords
{
    protected static string $resource =
        CommerceChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
