<?php

namespace App\Filament\Resources\CustomBalloonModelResource\Pages;

use App\Filament\Resources\CustomBalloonModelResource;
use App\Support\CustomBalloonsAccess;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomBalloonModels extends ListRecords
{
    protected static string $resource = CustomBalloonModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(
                    fn (): bool =>
                        CustomBalloonsAccess::canManageCatalog()
                ),
        ];
    }
}
