<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Company;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
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

        $roleIds = collect($formState['role_ids'] ?? $this->data['role_ids'] ?? [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $permissionIds = collect($formState['permission_ids'] ?? $this->data['permission_ids'] ?? [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $groupId = filled($formState['company_group_access_helper'] ?? $this->data['company_group_access_helper'] ?? null)
            ? (int) ($formState['company_group_access_helper'] ?? $this->data['company_group_access_helper'])
            : null;

        $selectedCompanyIds = collect($formState['companies'] ?? $this->data['companies'] ?? [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $companyIds = $this->normalizeCompanyIdsForGroup($selectedCompanyIds, $groupId);

        DB::transaction(function () use ($companyIds, $groupId, $roleIds, $permissionIds): void {
            $this->record->companies()->sync($companyIds);

            $this->syncNonAdminCompanyGroup($groupId);

            $this->syncRolesForCompanies($roleIds, $companyIds);

            $permissions = Permission::query()
                ->whereIn('id', $permissionIds)
                ->get();

            $this->record->syncPermissions($permissions);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
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

    private function syncNonAdminCompanyGroup(?int $groupId): void
    {
        DB::table('company_group_user')
            ->where('user_id', $this->record->getKey())
            ->where(function ($query) {
                $query->whereNull('is_group_admin')
                    ->orWhere('is_group_admin', false);
            })
            ->delete();

        if ($groupId) {
            $this->record->companyGroups()->syncWithoutDetaching([
                $groupId => ['is_group_admin' => false],
            ]);
        }
    }

    private function syncRolesForCompanies(array $roleIds, array $companyIds): void
    {
        DB::table('model_has_roles')
            ->where('model_type', $this->record->getMorphClass())
            ->where('model_id', $this->record->getKey())
            ->delete();

        if (empty($roleIds) || empty($companyIds)) {
            return;
        }

        $nowRows = [];

        foreach ($companyIds as $companyId) {
            foreach ($roleIds as $roleId) {
                $nowRows[] = [
                    'role_id' => $roleId,
                    'model_type' => $this->record->getMorphClass(),
                    'model_id' => $this->record->getKey(),
                    'company_id' => $companyId,
                ];
            }
        }

        DB::table('model_has_roles')->insertOrIgnore($nowRows);
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
