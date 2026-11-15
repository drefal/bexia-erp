<?php

namespace App\Filament\Resources\CustomBalloonExtraResource\Pages;

use App\Filament\Resources\CustomBalloonExtraResource;
use App\Support\CustomBalloonsAccess;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomBalloonExtras extends ListRecords
{
    protected static string $resource =
        CustomBalloonExtraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(
                    fn (): bool =>
                        CustomBalloonsAccess::canManageExtras()
                ),
        ];
    }
}
