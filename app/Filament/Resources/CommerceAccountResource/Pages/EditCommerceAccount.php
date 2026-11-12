<?php

namespace App\Filament\Resources\CommerceAccountResource\Pages;

use App\Filament\Resources\CommerceAccountResource;
use Filament\Resources\Pages\EditRecord;

class EditCommerceAccount extends EditRecord
{
    protected static string $resource =
        CommerceAccountResource::class;

    protected function mutateFormDataBeforeSave(
        array $data
    ): array {
        if (
            ! CommerceAccountResource::canManageCompany()
            || ! CommerceAccountResource::ownsRecord(
                $this->record
            )
            || (bool) $this->record->is_active
        ) {
            abort(403);
        }

        unset(
            $data['company_id'],
            $data['commerce_channel_id'],
            $data['is_active'],
            $data['status'],
            $data['last_successful_sync_at']
        );

        return $data;
    }
}
