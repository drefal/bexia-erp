<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['permission_ids'] = $this->record->permissions()->pluck('permissions.id')->all();

        return $data;
    }

    protected function afterSave(): void
    {
        $permissionIds = $this->data['permission_ids'] ?? [];

        $this->record->permissions()->sync($permissionIds);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
