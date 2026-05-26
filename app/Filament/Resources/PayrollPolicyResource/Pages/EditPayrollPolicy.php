<?php

namespace App\Filament\Resources\PayrollPolicyResource\Pages;

use App\Filament\Resources\PayrollPolicyResource;
use Filament\Resources\Pages\EditRecord;

class EditPayrollPolicy extends EditRecord
{
    protected static string $resource = PayrollPolicyResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user_id'] = auth()->id();

        return $data;
    }
}
