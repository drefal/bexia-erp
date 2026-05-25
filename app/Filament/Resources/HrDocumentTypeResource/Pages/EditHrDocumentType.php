<?php

namespace App\Filament\Resources\HrDocumentTypeResource\Pages;

use App\Filament\Resources\HrDocumentTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHrDocumentType extends EditRecord
{
    protected static string $resource = HrDocumentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
