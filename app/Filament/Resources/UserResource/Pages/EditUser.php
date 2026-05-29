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

        $data['companies'] = $this->record->companies()
            ->pluck('companies.id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        $data['company_group_access_helper'] = $this->record->companyGroups()
            ->orderBy('company_groups.name')
            ->value('company_groups.id');

        if (! $data['company_group_access_helper'] && ! empty($data['companies'])) {
            $groupIds = \App\Models\Company::query()
                ->whereIn('id', $data['companies'])
                ->whereNotNull('company_group_id')
                ->distinct()
                ->pluck('company_group_id')
                ->all();

            if (count($groupIds) === 1) {
                $data['company_group_access_helper'] = $groupIds[0];
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return UserResource::normalizeOperationalDefaults($data);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $formState = $this->form->getRawState();

        if (is_object($formState) && method_exists($formState, 'toArray')) {
            $formState = $formState->toArray();
        }

        if (! is_array($formState)) {
            $formState = $data;
        }

        $roleIds = $formState['role_ids'] ?? $data['role_ids'] ?? [];
        $permissionIds = $formState['permission_ids'] ?? $data['permission_ids'] ?? [];

        $companyIds = collect($formState['companies'] ?? $data['companies'] ?? [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $groupId = filled($formState['company_group_access_helper'] ?? $data['company_group_access_helper'] ?? null)
            ? (int) ($formState['company_group_access_helper'] ?? $data['company_group_access_helper'])
            : null;

        unset(
            $data['role_ids'],
            $data['permission_ids'],
            $data['companies'],
            $data['company_group_access_helper'],
        );

        $data = UserResource::normalizeOperationalDefaults($data);

        $record->fill($data);
        $record->save();

        $record->companies()->sync($companyIds);

        $record->companyGroups()
            ->wherePivot('is_group_admin', false)
            ->detach();

        if ($groupId) {
            $record->companyGroups()->syncWithoutDetaching([
                $groupId => ['is_group_admin' => false],
            ]);
        }

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