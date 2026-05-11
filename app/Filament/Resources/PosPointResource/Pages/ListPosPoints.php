<?php

namespace App\Filament\Resources\PosPointResource\Pages;

use App\Filament\Resources\PosPointResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPosPoints extends ListRecords
{
    protected static string $resource = PosPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(''),
        ];
    }
}
