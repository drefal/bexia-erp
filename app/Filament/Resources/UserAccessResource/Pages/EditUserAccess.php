<?php

namespace App\Filament\Resources\UserAccessResource\Pages;

use App\Filament\Resources\UserAccessResource;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class EditUserAccess extends EditRecord
{
    protected static string $resource = UserAccessResource::class;

    public function getTitle(): string
    {
        return 'Editar permisos';
    }

    public function getBreadcrumbs(): array
    {
        return [
            UserAccessResource::getUrl('index', ['tenant' => Filament::getTenant()?->getKey()]) => 'Usuarios',
            null => 'Editar',
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['name'], $data['email']);

        return $data;
    }

    protected function afterSave(): void
    {
        $tenantId = Filament::getTenant()?->getKey();

        if (! $tenantId) {
            return;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

        $selectedRoleIds = collect($this->data['roles'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $roles = Role::query()
            ->where('company_id', $tenantId)
            ->whereIn('id', $selectedRoleIds)
            ->get();

        $this->record->syncRoles($roles);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
