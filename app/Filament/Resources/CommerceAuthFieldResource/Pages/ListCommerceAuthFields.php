<?php

namespace App\Filament\Resources\CommerceAuthFieldResource\Pages;

use App\Filament\Resources\CommerceAuthFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCommerceAuthFields extends ListRecords
{
    protected static string $resource =
        CommerceAuthFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
