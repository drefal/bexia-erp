<?php

namespace App\Filament\Resources\PayrollConceptResource\Pages;

use App\Filament\Resources\PayrollConceptResource;
use Filament\Resources\Pages\EditRecord;

class EditPayrollConcept extends EditRecord
{
    protected static string $resource = PayrollConceptResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }
}
