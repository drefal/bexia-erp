<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Role;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $permissionIds = $data['permission_ids'] ?? [];
        $companyIds = $data['company_ids'] ?? [];
        $currentTenantId = Filament::getTenant()?->getKey();

        unset($data['permission_ids'], $data['company_ids']);

        if (! auth()->user()?->isSystemAdmin()) {
            $companyIds = [$data['company_id'] ?? $currentTenantId];
        }

        $companyIds = array_values(array_unique(array_filter($companyIds)));

        if (empty($companyIds)) {
            $companyIds = [$currentTenantId];
        }

        $firstRole = null;

        foreach ($companyIds as $companyId) {
            $role = Role::firstOrCreate([
                'name' => $data['name'],
                'guard_name' => 'web',
                'company_id' => $companyId,
            ]);

            $role->permissions()->sync($permissionIds);

            if ($firstRole === null) {
                $firstRole = $role;
            }
        }

        Notification::make()
            ->title('Rol creado')
            ->body(count($companyIds) > 1
                ? 'Se replicó el rol en ' . count($companyIds) . ' empresas.'
                : 'El rol se creó correctamente.')
            ->success()
            ->send();

        return $firstRole;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index', [
            'tenant' => Filament::getTenant()?->getKey(),
        ]);
    }
}
