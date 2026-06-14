<?php

namespace App\Filament\Resources\AiInsightUserAccessResource\Pages;

use App\Filament\Resources\AiInsightUserAccessResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAiInsightUserAccesses extends ListRecords
{
    protected static string $resource = AiInsightUserAccessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Agregar usuario'),
        ];
    }
}
