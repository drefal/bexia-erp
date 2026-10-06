<?php

namespace App\Filament\Resources\FundingSourceResource\Pages;

use App\Filament\Resources\FundingSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFundingSource extends EditRecord
{
    protected static string $resource =
        FundingSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
