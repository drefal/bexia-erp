<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Company;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
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
            ->unique()
            ->values()
            ->all();

        $data['permission_ids'] = $this->record->permissions()
            ->pluck('permissions.id')
            ->all();

        $data['companies'] = $this->record->companies()
            ->pluck('companies.id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        $data['company_group_access_helper'] = $this->record->companyGroups()
            ->wherePivot('is_group_admin', false)
            ->orderBy('company_groups.name')
            ->value('company_groups.id');

        if (! $data['company_group_access_helper'] && ! empty($data['companies'])) {
            $groupIds = Company::query()
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

        $roleIds = collect($formState['role_ids'] ?? $data['role_ids'] ?? [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $permissionIds = collect($formState['permission_ids'] ?? $data['permission_ids'] ?? [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $groupId = filled($formState['company_group_access_helper'] ?? $data['company_group_access_helper'] ?? null)
            ? (int) ($formState['company_group_access_helper'] ?? $data['company_group_access_helper'])
            : null;

        $selectedCompanyIds = collect($formState['companies'] ?? $data['companies'] ?? [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $companyIds = $this->normalizeCompanyIdsForGroup($selectedCompanyIds, $groupId);

        unset(
            $data['role_ids'],
            $data['permission_ids'],
            $data['companies'],
            $data['company_group_access_helper'],
        );

        $data = UserResource::normalizeOperationalDefaults($data);

        DB::transaction(function () use ($record, $data, $companyIds, $groupId, $roleIds, $permissionIds): void {
            $record->fill($data);
            $record->save();

            $record->companies()->sync($companyIds);

            $this->syncNonAdminCompanyGroup($record, $groupId);

            $this->syncRolesForCompanies($record, $roleIds, $companyIds);

            $permissions = Permission::query()
                ->whereIn('id', $permissionIds)
                ->get();

            $record->syncPermissions($permissions);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        return $record;
    }

    private function normalizeCompanyIdsForGroup(array $selectedCompanyIds, ?int $groupId): array
    {
        if (! $groupId) {
            return $selectedCompanyIds;
        }

        $groupCompanyIds = Company::query()
            ->where('company_group_id', $groupId)
            ->where('active', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (empty($selectedCompanyIds)) {
            return $groupCompanyIds;
        }

        return collect($selectedCompanyIds)
            ->intersect($groupCompanyIds)
            ->values()
            ->all();
    }

    private function syncNonAdminCompanyGroup(Model $record, ?int $groupId): void
    {
        DB::table('company_group_user')
            ->where('user_id', $record->getKey())
            ->where(function ($query) {
                $query->whereNull('is_group_admin')
                    ->orWhere('is_group_admin', false);
            })
            ->delete();

        if ($groupId) {
            $record->companyGroups()->syncWithoutDetaching([
                $groupId => ['is_group_admin' => false],
            ]);
        }
    }

    private function syncRolesForCompanies(Model $record, array $roleIds, array $companyIds): void
    {
        DB::table('model_has_roles')
            ->where('model_type', $record->getMorphClass())
            ->where('model_id', $record->getKey())
            ->delete();

        if (empty($roleIds) || empty($companyIds)) {
            return;
        }

        $rows = [];

        foreach ($companyIds as $companyId) {
            foreach ($roleIds as $roleId) {
                $rows[] = [
                    'role_id' => $roleId,
                    'model_type' => $record->getMorphClass(),
                    'model_id' => $record->getKey(),
                    'company_id' => $companyId,
                ];
            }
        }

        DB::table('model_has_roles')->insertOrIgnore($rows);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
