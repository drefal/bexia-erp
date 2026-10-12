<?php

namespace App\Filament\Resources\PayrollGroupResource\Pages;

use App\Filament\Resources\PayrollGroupResource;
use Filament\Resources\Pages\EditRecord;

class EditPayrollGroup extends EditRecord
{
    protected static string $resource = PayrollGroupResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user_id'] = auth()->id();

        $data['code'] = strtoupper(
            trim((string) ($data['code'] ?? ''))
        );

        return $data;
    }
}
