<?php

namespace App\Filament\Resources\CustomBalloonSizeResource\Pages;

use App\Filament\Resources\CustomBalloonSizeResource;
use App\Support\CustomBalloonsAccess;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomBalloonSizes extends ListRecords
{
    protected static string $resource =
        CustomBalloonSizeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(
                    fn (): bool =>
                        CustomBalloonsAccess::canManagePrices()
                ),
        ];
    }
}
