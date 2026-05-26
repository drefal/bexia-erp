<?php

namespace App\Filament\Resources\HrDocumentTypeResource\Pages;

use App\Filament\Resources\HrDocumentTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHrDocumentTypes extends ListRecords
{
    protected static string $resource = HrDocumentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
