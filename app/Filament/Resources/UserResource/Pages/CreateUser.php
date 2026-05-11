<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $roleIds = $this->data['role_ids'] ?? [];
        $permissionIds = $this->data['permission_ids'] ?? [];

        $roles = Role::query()->whereIn('id', $roleIds)->pluck('name')->all();
        $permissions = Permission::query()->whereIn('id', $permissionIds)->pluck('name')->all();

        $this->record->syncRoles($roles);
        $this->record->syncPermissions($permissions);
    }

    protected function getCreateFormAction(): Action
    {
        return Action::make('create')
            ->label('Guardar cambios')
            ->submit('create');
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return Action::make('createAnother')
            ->label('Guardar y crear otro')
            ->action('createAnother');
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancelar')
            ->url(static::getResource()::getUrl('index'));
    }
}
