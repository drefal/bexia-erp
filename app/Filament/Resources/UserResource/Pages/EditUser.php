<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role_ids'] = DB::table('model_has_roles')
            ->where('model_type', $this->record->getMorphClass())
            ->where('model_id', $this->record->getKey())
            ->pluck('role_id')
            ->all();

        $data['permission_ids'] = $this->record->permissions()
            ->pluck('permissions.id')
            ->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return UserResource::normalizeOperationalDefaults($data);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $roleIds = $data['role_ids'] ?? [];
        $permissionIds = $data['permission_ids'] ?? [];

        unset($data['role_ids'], $data['permission_ids']);

        $data = UserResource::normalizeOperationalDefaults($data);

        $record->fill($data);
        $record->save();

        $companyId = $record->company_id
            ?? filament()->getTenant()?->getKey()
            ?? DB::table('company_user')
                ->where('user_id', $record->getKey())
                ->value('company_id');

        DB::table('model_has_roles')
            ->where('model_type', $record->getMorphClass())
            ->where('model_id', $record->getKey())
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->delete();

        foreach ($roleIds as $roleId) {
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => $record->getMorphClass(),
                'model_id' => $record->getKey(),
                'company_id' => $companyId,
            ]);
        }

        $permissions = Permission::query()
            ->whereIn('id', $permissionIds)
            ->get();

        $record->syncPermissions($permissions);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}