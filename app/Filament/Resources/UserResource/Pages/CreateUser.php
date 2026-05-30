<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Company;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset(
            $data['role_ids'],
            $data['role_group_loader'],
            $data['permission_ids'],
            $data['companies'],
            $data['access_company_group_id'],
            $data['access_company_group_is_admin'],
        );

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

        $groupId = filled($formState['access_company_group_id'] ?? $this->data['access_company_group_id'] ?? null)
            ? (int) ($formState['access_company_group_id'] ?? $this->data['access_company_group_id'])
            : null;

        $isGroupAdmin = (bool) ($formState['access_company_group_is_admin'] ?? $this->data['access_company_group_is_admin'] ?? false);

        $selectedCompanyIds = collect($formState['companies'] ?? $this->data['companies'] ?? [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $groupId = $this->resolveGroupIdFromSelectedCompanies($selectedCompanyIds, $groupId);
        $companyIds = $this->normalizeCompanyIdsForGroup($selectedCompanyIds, $groupId);

        DB::transaction(function () use ($companyIds, $groupId, $isGroupAdmin, $roleIds, $permissionIds): void {
            $this->record->companies()->sync($companyIds);

            $this->syncCompanyGroup($groupId, $isGroupAdmin);

            $this->syncRolesForCompanies($roleIds, $companyIds);

            $permissions = Permission::query()
                ->whereIn('id', $permissionIds)
                ->get();

            $this->record->syncPermissions($permissions);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        Notification::make()
            ->title('Usuario guardado')
            ->body('Se actualizaron grupo, empresas y roles del usuario.')
            ->success()
            ->send();
    }

    private function resolveGroupIdFromSelectedCompanies(array $selectedCompanyIds, ?int $groupId): ?int
    {
        if (empty($selectedCompanyIds)) {
            return $groupId;
        }

        $groupIds = Company::query()
            ->whereIn('id', $selectedCompanyIds)
            ->whereNotNull('company_group_id')
            ->distinct()
            ->pluck('company_group_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (count($groupIds) === 1) {
            return $groupIds[0];
        }

        return $groupId;
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

    private function syncCompanyGroup(?int $groupId, bool $isGroupAdmin): void
    {
        DB::table('company_group_user')
            ->where('user_id', $this->record->getKey())
            ->delete();

        if (! $groupId) {
            return;
        }

        DB::table('company_group_user')->insert([
            'company_group_id' => $groupId,
            'user_id' => $this->record->getKey(),
            'is_group_admin' => $isGroupAdmin,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

        $companyIds = collect($companyIds)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $roles = Role::query()
            ->whereIn('id', $roleIds)
            ->get(['id', 'company_id']);

        $rows = [];

        foreach ($roles as $role) {
            if (filled($role->company_id)) {
                $companyId = (int) $role->company_id;

                if (! in_array($companyId, $companyIds, true)) {
                    continue;
                }

                $rows[] = [
                    'role_id' => $role->id,
                    'model_type' => $this->record->getMorphClass(),
                    'model_id' => $this->record->getKey(),
                    'company_id' => $companyId,
                ];

                continue;
            }

            foreach ($companyIds as $companyId) {
                $rows[] = [
                    'role_id' => $role->id,
                    'model_type' => $this->record->getMorphClass(),
                    'model_id' => $this->record->getKey(),
                    'company_id' => $companyId,
                ];
            }
        }

        if (! empty($rows)) {
            DB::table('model_has_roles')->insertOrIgnore($rows);
        }
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
