<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected array $bexiaPermissionIds = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $permissionIds = $this->record
            ->permissions()
            ->pluck('permissions.id')
            ->all();

        return RoleResource::hydratePermissionGroupState(
            $data,
            $permissionIds
        );
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->bexiaPermissionIds =
            RoleResource::extractPermissionIds(
                $data
            );

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record
            ->permissions()
            ->sync(
                $this->bexiaPermissionIds
            );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
