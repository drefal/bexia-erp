<?php

namespace App\Filament\Resources\ExitProjectResource\Pages;

use App\Filament\Resources\ExitProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExitProjects extends ListRecords
{
    protected static string $resource = ExitProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
