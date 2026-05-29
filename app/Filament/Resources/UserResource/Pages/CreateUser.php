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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return UserResource::normalizeOperationalDefaults($data);
    }

    protected function afterCreate(): void
    {
        $formState = $this->form->getRawState();

        if (is_object($formState) && method_exists($formState, 'toArray')) {
            $formState = $formState->toArray();
        }

        if (! is_array($formState)) {
            $formState = $this->data ?? [];
        }

        $roleIds = $formState['role_ids'] ?? $this->data['role_ids'] ?? [];
        $permissionIds = $formState['permission_ids'] ?? $this->data['permission_ids'] ?? [];

        $companyIds = collect($formState['companies'] ?? $this->data['companies'] ?? [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $groupId = filled($formState['company_group_access_helper'] ?? $this->data['company_group_access_helper'] ?? null)
            ? (int) ($formState['company_group_access_helper'] ?? $this->data['company_group_access_helper'])
            : null;

        $roles = Role::query()->whereIn('id', $roleIds)->pluck('name')->all();
        $permissions = Permission::query()->whereIn('id', $permissionIds)->pluck('name')->all();

        $this->record->companies()->sync($companyIds);

        $this->record->companyGroups()
            ->wherePivot('is_group_admin', false)
            ->detach();

        if ($groupId) {
            $this->record->companyGroups()->syncWithoutDetaching([
                $groupId => ['is_group_admin' => false],
            ]);
        }

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
