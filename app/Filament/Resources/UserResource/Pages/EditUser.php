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

        $data['access_company_group_id'] = $this->record->companyGroups()
            ->wherePivot('is_group_admin', false)
            ->orderBy('company_groups.name')
            ->value('company_groups.id');

        if (! $data['access_company_group_id'] && ! empty($data['companies'])) {
            $groupIds = Company::query()
                ->whereIn('id', $data['companies'])
                ->whereNotNull('company_group_id')
                ->distinct()
                ->pluck('company_group_id')
                ->all();

            if (count($groupIds) === 1) {
                $data['access_company_group_id'] = $groupIds[0];
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset(
            $data['role_ids'],
            $data['permission_ids'],
            $data['companies'],
            $data['access_company_group_id'],
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
        $state = $this->form->getState();

        if (is_object($state) && method_exists($state, 'toArray')) {
            $state = $state->toArray();
        }

        if (! is_array($state)) {
            $state = [];
        }

        $this->persistUserDataFromState($this->record, $state);
        $this->persistAccessFromState($this->record, $state);

        Notification::make()
            ->title('Usuario guardado')
            ->body('Se guardaron datos, grupo, empresas y roles.')
            ->success()
            ->send();
    }

    private function persistUserDataFromState(Model $record, array $state): void
    {
        $data = $state;

        unset(
            $data['role_ids'],
            $data['permission_ids'],
            $data['companies'],
            $data['access_company_group_id'],
        );

        if (array_key_exists('password', $data) && blank($data['password'])) {
            unset($data['password']);
        }

        $data = UserResource::normalizeOperationalDefaults($data);

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

        $selectedCompanyIds = collect($state['companies'] ?? [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $companyIds = $this->normalizeCompanyIdsForGroup($selectedCompanyIds, $groupId);

        \Log::info('BEXIA_USER_SAVE_FINAL_V57117C', [
            'record_id' => $record->getKey(),
            'group_id' => $groupId,
            'selected_company_ids' => $selectedCompanyIds,
            'normalized_company_ids' => $companyIds,
            'role_ids' => $roleIds,
            'permission_ids' => $permissionIds,
        ]);

        DB::transaction(function () use ($record, $companyIds, $groupId, $roleIds, $permissionIds): void {
            $record->companies()->sync($companyIds);

            $this->syncNonAdminCompanyGroup($record, $groupId);

            $this->syncRolesForCompanies($record, $roleIds, $companyIds);

            $permissions = Permission::query()
                ->whereIn('id', $permissionIds)
                ->get();

            $record->syncPermissions($permissions);

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

    private function syncNonAdminCompanyGroup(Model $record, ?int $groupId): void
    {
        DB::table('company_group_user')
            ->where('user_id', $record->getKey())
            ->where(function ($query) {
                $query->whereNull('is_group_admin')
                    ->orWhere('is_group_admin', false);
            })
            ->delete();

        if (! $groupId) {
            return;
        }

        DB::table('company_group_user')->insert([
            'company_group_id' => $groupId,
            'user_id' => $record->getKey(),
            'is_group_admin' => false,
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

        $roleNames = Role::query()
            ->whereIn('id', $roleIds)
            ->pluck('name')
            ->unique()
            ->values()
            ->all();

        if (empty($roleNames)) {
            return;
        }

        $rows = [];

        foreach ($companyIds as $companyId) {
            $roles = Role::query()
                ->whereIn('name', $roleNames)
                ->where(function ($query) use ($companyId) {
                    $query->whereNull('company_id')
                        ->orWhere('company_id', $companyId);
                })
                ->get(['id']);

            foreach ($roles as $role) {
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
