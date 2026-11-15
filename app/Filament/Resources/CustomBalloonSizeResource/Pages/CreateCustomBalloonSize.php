<?php

namespace App\Filament\Resources\CustomBalloonSizeResource\Pages;

use App\Filament\Resources\CustomBalloonSizeResource;
use App\Models\CustomBalloonPrice;
use App\Support\CustomBalloonsAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomBalloonSize extends CreateRecord
{
    protected static string $resource =
        CustomBalloonSizeResource::class;

    protected ?float $pendingPrice = null;

    protected function mutateFormDataBeforeCreate(
        array $data
    ): array {
        $this->pendingPrice =
            isset($data['current_price'])
                ? (float) $data['current_price']
                : null;

        unset($data['current_price']);

        $data['company_id'] =
            CustomBalloonsAccess::COMPANY_ID;

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->pendingPrice === null) {
            return;
        }

        CustomBalloonPrice::create([
            'company_id' =>
                CustomBalloonsAccess::COMPANY_ID,
            'size_id' => $this->record->id,
            'price' => $this->pendingPrice,
            'valid_from' => now(),
            'valid_to' => null,
            'created_by_user_id' => auth()->id(),
            'notes' => 'Precio creado desde configuración de globos',
        ]);
    }
}
