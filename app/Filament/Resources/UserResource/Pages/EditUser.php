<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role_ids'] = $this->record->roles()->pluck('roles.id')->all();
        $data['permission_ids'] = $this->record->permissions()->pluck('permissions.id')->all();

        return $data;
    }

    protected function afterSave(): void
    {
        $roleIds = $this->data['role_ids'] ?? [];
        $permissionIds = $this->data['permission_ids'] ?? [];

        $roles = Role::query()->whereIn('id', $roleIds)->pluck('name')->all();
        $permissions = Permission::query()->whereIn('id', $permissionIds)->pluck('name')->all();

        $this->record->syncRoles($roles);
        $this->record->syncPermissions($permissions);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
