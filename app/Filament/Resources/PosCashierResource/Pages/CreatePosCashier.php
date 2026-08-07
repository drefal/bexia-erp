<?php

namespace App\Filament\Resources\PosCashierResource\Pages;

use App\Filament\Resources\PosCashierResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePosCashier extends CreateRecord
{
    protected static string $resource = PosCashierResource::class;

    protected ?string $pendingPlainPin = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $plainPin = trim((string) ($data['plain_pin'] ?? ''));

        $this->pendingPlainPin = $plainPin !== ''
            ? $plainPin
            : null;

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->pendingPlainPin === null) {
            return;
        }

        PosCashierResource::syncPinToLinkedEmployee(
            $this->record,
            $this->pendingPlainPin
        );

        $this->pendingPlainPin = null;
    }
}
