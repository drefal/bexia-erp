<?php

namespace App\Filament\Resources\RolBorradorResource\Pages;

use App\Filament\Resources\RolBorradorResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateRolBorrador extends CreateRecord
{
    protected static string $resource = RolBorradorResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        if (auth()->user()?->isSystemAdmin() && ! empty($data['company_ids'])) {
            foreach ($data['company_ids'] as $companyId) {
                $role = Role::create([
                    'name' => $data['name'],
                    'company_id' => $companyId,
                ]);

                $permissions = Permission::whereIn('id', $data['permission_ids'] ?? [])->get();

                $role->syncPermissions($permissions);
            }

            return new Role(); // dummy
        }

        $role = Role::create([
            'name' => $data['name'],
            'company_id' => $data['company_id'],
        ]);

        $permissions = Permission::whereIn('id', $data['permission_ids'] ?? [])->get();

        $role->syncPermissions($permissions);

        return $role;
    }
}