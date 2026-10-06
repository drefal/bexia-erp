<?php

namespace App\Filament\Resources\FundingSourceResource\Pages;

use App\Filament\Resources\FundingSourceResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateFundingSource extends CreateRecord
{
    protected static string $resource =
        FundingSourceResource::class;

    protected function mutateFormDataBeforeCreate(
        array $data
    ): array {
        $data['company_id'] =
            (int) Filament::getTenant()->getKey();

        return $data;
    }
}
