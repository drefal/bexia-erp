<?php

namespace App\Filament\Resources\PosCashierResource\Pages;

use App\Filament\Resources\PosCashierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPosCashier extends EditRecord
{
    protected static string $resource = PosCashierResource::class;

    protected ?string $pendingPlainPin = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $plainPin = trim((string) ($data['plain_pin'] ?? ''));

        $this->pendingPlainPin = $plainPin !== ''
            ? $plainPin
            : null;

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->pendingPlainPin === null) {
            return;
        }

        PosCashierResource::syncPinToLinkedEmployee(
            $this->record,
            $this->pendingPlainPin
        );

        /*
         * El valor plano solo vive durante esta operación.
         */
        $this->pendingPlainPin = null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
