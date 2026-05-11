<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateSaleOrder extends CreateRecord
{
    protected static string $resource = SaleOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (Filament::getTenant()) {
            $data['company_id'] = (int) Filament::getTenant()->getKey();
        } else {
            $data['company_id'] = (int) (request()->route('tenant') ?? auth()->user()?->company_id ?? 0);
        }

        $data['created_by_user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->recalculateTotals();
    }
    public function getTitle(): string
    {
        return 'Nueva cotización';
    }


}
