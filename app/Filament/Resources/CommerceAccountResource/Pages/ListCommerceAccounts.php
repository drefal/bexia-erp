<?php

namespace App\Filament\Resources\CommerceAccountResource\Pages;

use App\Filament\Resources\CommerceAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCommerceAccounts extends ListRecords
{
    protected static string $resource =
        CommerceAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
