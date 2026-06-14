<?php

namespace App\Filament\Resources\AiInsightUserAccessResource\Pages;

use App\Filament\Resources\AiInsightUserAccessResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAiInsightUserAccess extends EditRecord
{
    protected static string $resource = AiInsightUserAccessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
