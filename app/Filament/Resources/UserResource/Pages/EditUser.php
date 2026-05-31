<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Company;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

        $companyGroupIds = Company::query()
            ->whereIn('id', $data['companies'])
            ->whereNotNull('company_group_id')
            ->distinct()
            ->pluck('company_group_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (count($companyGroupIds) === 1) {
            $data['access_company_group_id'] = $companyGroupIds[0];
            $data['access_company_group_is_admin'] = DB::table('company_group_user')
                ->where('user_id', $this->record->getKey())
                ->where('company_group_id', $companyGroupIds[0])
                ->where('is_group_admin', true)
                ->exists();
        } else {
            $primaryGroup = $this->record->companyGroups()
                ->orderByDesc('company_group_user.is_group_admin')
                ->orderBy('company_groups.name')
                ->first();

            $data['access_company_group_id'] = $primaryGroup?->getKey();
            $data['access_company_group_is_admin'] = (bool) ($primaryGroup?->pivot?->is_group_admin ?? false);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $this->saveUserAndAccessConfiguration();

        return $record->refresh();
    }

    public function saveUserAndAccessConfiguration(): void
    {
        $rawState = $this->form->getRawState();

        if (is_object($rawState) && method_exists($rawState, 'toArray')) {
            $rawState = $rawState->toArray();
        }

        if (! is_array($rawState)) {
            $rawState = $this->data ?? [];
        }

        /*
         * Para FileUpload necesitamos el estado procesado de Filament.
         * Para accesos usamos rawState porque Select/Toggle reactivos ya nos dieron problemas antes.
         */
        $userState = $this->form->getState();

        if (is_object($userState) && method_exists($userState, 'toArray')) {
            $userState = $userState->toArray();
        }

        if (! is_array($userState)) {
            $userState = $rawState;
        }

        \Log::info('BEXIA_USER_SAVE_STATE_SPLIT_V57125C', [
            'record_id' => $this->record->getKey(),
            'raw_access_company_group_id' => $rawState['access_company_group_id'] ?? null,
            'raw_access_company_group_is_admin' => $rawState['access_company_group_is_admin'] ?? null,
            'raw_avatar_path' => $rawState['avatar_path'] ?? null,
            'user_avatar_path' => $userState['avatar_path'] ?? null,
            'raw_companies' => $rawState['companies'] ?? null,
            'user_keys' => array_keys($userState),
        ]);

        \Log::info('BEXIA_USER_SAVE_BEFORE_PERSIST_V57123F', [
            'record_id' => $this->record->getKey(),
            'access_company_group_id' => $rawState['access_company_group_id'] ?? null,
            'access_company_group_is_admin' => $rawState['access_company_group_is_admin'] ?? null,
        ]);

        $this->persistUserDataFromState($this->record, $userState);

        \Log::info('BEXIA_USER_SAVE_AFTER_USERDATA_V57123F', [
            'record_id' => $this->record->getKey(),
            'avatar_path' => $this->record->fresh()?->avatar_path,
        ]);

        $this->persistAccessFromState($this->record, $rawState);

        \Log::info('BEXIA_USER_SAVE_AFTER_ACCESS_V57123F', [
            'record_id' => $this->record->getKey(),
        ]);

        Notification::make()
            ->title('Usuario guardado')
            ->body('Se guardaron datos, grupo, empresas y roles.')
            ->success()
            ->send();
    }

    private function persistUserDataFromState(Model $record, array $state): void
    {
        $allowedKeys = [
            'name',
            'email',
            'password',
            'avatar_path',
            'locale',
            'is_system_admin',
        ];

        $data = collect($state)
            ->only($allowedKeys)
            ->toArray();

        if (array_key_exists('password', $data) && blank($data['password'])) {
            unset($data['password']);
        }

        if (array_key_exists('avatar_path', $data)) {
            $data['avatar_path'] = UserResource::normalizeAvatarPathValue($data['avatar_path']);
        }

        $data = UserResource::normalizeOperationalDefaults($data);

        \Log::info('BEXIA_USERDATA_WHITELIST_V57123G2', [
            'record_id' => $record->getKey(),
            'keys' => array_keys($data),
            'defaults_skipped' => [
                'default_warehouse_id' => $state['default_warehouse_id'] ?? null,
                'default_location_id' => $state['default_location_id'] ?? null,
            ],
        ]);

        $record->fill($data);
        $record->save();
    }

    private function persistAccessFromState(Model $record, array $state): void
    {
        $roleIds = collect($state['role_ids'] ?? [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $permissionIds = collect($state['permission_ids'] ?? [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $groupId = filled($state['access_company_group_id'] ?? null)
            ? (int) $state['access_company_group_id']
            : null;

        $isGroupAdmin = (bool) ($state['access_company_group_is_admin'] ?? false);

        $selectedCompanyIds = collect($state['companies'] ?? [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $groupId = $this->resolveGroupIdFromSelectedCompanies($selectedCompanyIds, $groupId);
        $companyIds = $this->normalizeCompanyIdsForGroup($selectedCompanyIds, $groupId);

        \Log::info('BEXIA_USER_SAVE_FINAL_V57118A', [
            'record_id' => $record->getKey(),
            'group_id' => $groupId,
            'selected_company_ids' => $selectedCompanyIds,
            'normalized_company_ids' => $companyIds,
            'is_group_admin' => $isGroupAdmin,
            'role_ids' => $roleIds,
            'permission_ids' => $permissionIds,
        ]);

        DB::transaction(function () use ($record, $companyIds, $groupId, $isGroupAdmin, $roleIds, $permissionIds): void {
            $record->companies()->sync($companyIds);

            $this->syncCompanyGroup($record, $groupId, $isGroupAdmin);

            $this->syncRolesForCompanies($record, $roleIds, $companyIds);

            $permissions = Permission::query()
                ->whereIn('id', $permissionIds)
                ->get();

            $record->syncPermissions($permissions);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
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

    private function syncCompanyGroup(Model $record, ?int $groupId, bool $isGroupAdmin): void
    {
        DB::table('company_group_user')
            ->where('user_id', $record->getKey())
            ->delete();

        if (! $groupId) {
            return;
        }

        DB::table('company_group_user')->insert([
            'company_group_id' => $groupId,
            'user_id' => $record->getKey(),
            'is_group_admin' => $isGroupAdmin,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
                    'model_type' => $record->getMorphClass(),
                    'model_id' => $record->getKey(),
                    'company_id' => $companyId,
                ];

                continue;
            }

            foreach ($companyIds as $companyId) {
                $rows[] = [
                    'role_id' => $role->id,
                    'model_type' => $record->getMorphClass(),
                    'model_id' => $record->getKey(),
                    'company_id' => $companyId,
                ];
            }
        }

        if (! empty($rows)) {
            DB::table('model_has_roles')->insertOrIgnore($rows);
        }
    }

    protected function getSaveFormAction(): Actions\Action
    {
        return Actions\Action::make('save')
            ->label('Guardar')
            ->icon('heroicon-o-check')
            ->color('primary')
            ->action('saveUserAndAccessConfiguration')
            ->keyBindings(['mod+s']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
