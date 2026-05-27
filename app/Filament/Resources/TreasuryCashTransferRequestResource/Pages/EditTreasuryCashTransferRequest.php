<?php

namespace App\Filament\Resources\TreasuryCashTransferRequestResource\Pages;

use App\Filament\Resources\TreasuryCashTransferRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTreasuryCashTransferRequest extends EditRecord
{
    protected static string $resource = TreasuryCashTransferRequestResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset(
            $data['company_id'],
            $data['status'],
            $data['posted_at'],
            $data['approved_by_user_id'],
            $data['approved_at'],
            $data['rejected_by_user_id'],
            $data['rejected_at'],
            $data['received_by_user_id'],
            $data['received_at']
        );

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
