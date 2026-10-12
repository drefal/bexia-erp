<?php

namespace App\Filament\Resources\PayrollGroupResource\Pages;

use App\Filament\Resources\PayrollGroupResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollGroup extends CreateRecord
{
    protected static string $resource = PayrollGroupResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] =
            Filament::getTenant()?->getKey()
            ?? $data['company_id']
            ?? null;

        $data['created_by_user_id'] = auth()->id();
        $data['updated_by_user_id'] = auth()->id();

        $data['code'] = strtoupper(
            trim((string) ($data['code'] ?? ''))
        );

        return $data;
    }
}
