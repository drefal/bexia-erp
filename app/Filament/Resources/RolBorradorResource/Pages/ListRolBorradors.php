<?php

namespace App\Filament\Resources\RolBorradorResource\Pages;

use App\Filament\Resources\RolBorradorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRolBorradors extends ListRecords
{
    protected static string $resource = RolBorradorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
