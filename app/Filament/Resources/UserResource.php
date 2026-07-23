<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Company;
use App\Models\CompanyGroup;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?int $navigationSort = 40;
    protected static ?string $navigationGroup = 'Seguridad';
    protected static ?string $model = User::class;
    protected static bool $isScopedToTenant = false;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function currentUserIsSuperAdmin(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return method_exists($user, 'isSystemAdmin')
            ? $user->isSystemAdmin()
            : (bool) ($user->is_system_admin ?? false);
    }

    protected static function recordIsSuperAdmin(?Model $record): bool
    {
        if (! $record instanceof User) {
            return false;
        }

        return method_exists($record, 'isSystemAdmin')
            ? $record->isSystemAdmin()
            : (bool) ($record->is_system_admin ?? false);
    }

public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->can('users.create');
    }

    public static function canEdit(Model $record): bool
    {
        if (! auth()->check() || ! auth()->user()->can('users.update')) {
            return false;
        }

        if (static::recordIsSuperAdmin($record) && ! static::currentUserIsSuperAdmin()) {
            return false;
        }

        return true;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->check() && auth()->user()->can('users.delete');
    }

    public static function canDelete(Model $record): bool
    {
        if (! auth()->check() || ! auth()->user()->can('users.delete')) {
            return false;
        }

        if (static::recordIsSuperAdmin($record) && ! static::currentUserIsSuperAdmin()) {
            return false;
        }

        return true;
    }

    public static function getNavigationLabel(): string
    {
        return 'Usuarios';
    }

    public static function getModelLabel(): string
    {
        return 'Usuario';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Usuarios';
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();
        $user = auth()->user();

        $query = parent::getEloquentQuery()->with(['companies', 'companyGroups']);

        if ($user && static::currentUserIsSuperAdmin()) {
            return $query;
        }

        $manageableGroupIds = [];

        if ($user && method_exists($user, 'manageableCompanyGroupIds')) {
            $manageableGroupIds = $user->manageableCompanyGroupIds();
        }

        if (! empty($manageableGroupIds)) {
            $query->whereHas('companies', function (Builder $q) use ($manageableGroupIds) {
                $q->whereIn('companies.company_group_id', $manageableGroupIds);
            });
        } elseif ($tenantId) {
            $query->whereHas('companies', function (Builder $q) use ($tenantId) {
                $q->where('companies.id', $tenantId);
            });
        }

        $query->where(function (Builder $q) {
            $q->whereNull('is_system_admin')
              ->orWhere('is_system_admin', false);
        });

        return $query;
    }

public static function shouldRegisterNavigation(): bool
{
    return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
        'resources.userresource',
        fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
    );
}

protected static function bexiaBaseShouldRegisterNavigation(): bool
{
    $user = auth()->user();

    return (bool) (
        $user &&
        (
            (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) ||
            $user->can('users.view')
        )
    );
}

public static function canViewAny(): bool
{
    $user = auth()->user();

    return (bool) (
        $user &&
        (
            (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) ||
            $user->can('users.view')
        )
    );
}


    /*
     * BEXIA_USR_RESOURCE_RESPONSIVE_V5_79_43C
     * Visual-only responsive marker.
     */

    public static function form(Form $form): Form
    {
        $tenantId = Filament::getTenant()?->getKey();

        return $form->schema([
            Forms\Components\Section::make('Datos del usuario')
                ->extraAttributes(['class' => 'bexia-usr-section bexia-usr-section-main'])
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->extraAttributes(['class' => 'bexia-usr-field bexia-usr-field-name'])
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->extraAttributes(['class' => 'bexia-usr-field bexia-usr-field-mail'])
                        ->label('Correo')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('password')
                        ->extraAttributes(['class' => 'bexia-usr-field bexia-usr-field-key'])
                        ->label('Contraseña')
                        ->password()
                        ->dehydrated(fn ($state) => filled($state))
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                        ->required(fn (string $operation): bool => $operation === 'create'),

                    FileUpload::make('avatar_path')
                        ->extraAttributes(['class' => 'bexia-usr-field bexia-usr-field-avatar'])
                        ->label('Avatar')
                        ->disk('public')
                        ->directory('users/avatars')
                        ->visibility('public')
                        ->image()
                        ->imageEditor()
                        ->avatar()
                        ->imagePreviewHeight('120')
                        ->openable()
                        ->downloadable()
                        ->dehydrateStateUsing(fn ($state) => static::normalizeAvatarPathValue($state)),
                ])
                ->columns(2),


            Forms\Components\Section::make('Preferencias operativas')
                ->extraAttributes(['class' => 'bexia-usr-section bexia-usr-section-ops'])
                ->description('Valores predeterminados para ventas, inventario y entregas.')
                ->schema([
                    Forms\Components\Select::make('default_warehouse_id')
                        ->extraAttributes(['class' => 'bexia-usr-field bexia-usr-field-wh'])
                        ->label('Almacén predeterminado')
                        // BEXIA_V5_82_8A6D_NATIVE_DEFAULT_SELECTS
                        // Evita Choices/Alpine en este formulario.
                        ->native()
                        // BEXIA_V5_82_8A6C_GROUP_DEFAULT_PREFERENCES
                        ->options(
                            fn (): array =>
                                static::groupDefaultWarehouseOptions()
                        )
                        ->live()
                        ->afterStateUpdated(
                            function (Forms\Set $set): void {
                                $set('default_location_id', null);
                            }
                        ),

                    Forms\Components\Select::make('default_location_id')
                        ->extraAttributes(['class' => 'bexia-usr-field bexia-usr-field-loc'])
                        ->label('Ubicación predeterminada')
                        // BEXIA_V5_82_8A6D_NATIVE_DEFAULT_SELECTS
                        ->native()
                        ->options(
                            fn (Forms\Get $get): array =>
                                static::groupDefaultLocationOptions(
                                    (int) (
                                        $get('default_warehouse_id') ?? 0
                                    )
                                )
                        )
                        ->disabled(
                            fn (Forms\Get $get): bool =>
                                blank($get('default_warehouse_id'))
                        )
                        ->placeholder(
                            'Selecciona primero un almacén'
                        )
                        ->helperText(
                            'Solo se muestran ubicaciones activas del almacén seleccionado.'
                        ),
                ])
                ->columns(2),

            Forms\Components\Section::make('Accesos')
                ->extraAttributes(['class' => 'bexia-usr-section bexia-usr-section-sec'])
                ->schema([
                    Forms\Components\Select::make('access_company_group_id')
                        ->extraAttributes(['class' => 'bexia-usr-field bexia-usr-field-scopegrp'])
                        ->label('Grupo de acceso del usuario')
                        ->helperText('Selecciona el grupo principal de acceso. Se cargarán sus empresas y después puedes quitar manualmente las que no apliquen.')
                        ->options(function (): array {
                            $query = CompanyGroup::query()
                                ->where('active', true)
                                ->orderBy('name');

                            $user = auth()->user();

                            if (
                                $user
                                && ! static::currentUserIsSuperAdmin()
                                && method_exists($user, 'manageableCompanyGroupIds')
                            ) {
                                $groupIds = $user->manageableCompanyGroupIds();

                                if (! empty($groupIds)) {
                                    $query->whereIn('id', $groupIds);
                                }
                            }

                            return $query->pluck('name', 'id')->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->live()
                        ->dehydrated(true)
                        ->afterStateUpdated(function ($state, Forms\Set $set): void {
                            if (! $state) {
                                return;
                            }

                            $companyIds = Company::query()
                                ->where('company_group_id', $state)
                                ->where('active', true)
                                ->orderBy('name')
                                ->pluck('id')
                                ->map(fn ($id): string => (string) $id)
                                ->all();

                            $set('companies', $companyIds);
                            $set('access_company_group_is_admin', false);
                            $set('role_group_loader', []);
                            $set('role_ids', []);
                        }),

                    Forms\Components\Toggle::make('access_company_group_is_admin')
                        ->extraAttributes(['class' => 'bexia-usr-field bexia-usr-field-gadm'])
                        ->label('Administrar este grupo')
                        ->helperText('Activa esto para que el usuario sea administrador real del grupo seleccionado. Si queda apagado, el grupo solo se usa para cargar empresas y limitar acceso.')
                        ->default(false)
                        ->live()
                        ->dehydrated(true)
                        ->visible(fn (Forms\Get $get): bool => filled($get('access_company_group_id'))),

                    Forms\Components\Select::make('companies')
                        ->extraAttributes(['class' => 'bexia-usr-field bexia-usr-field-orgs'])
                        ->label('Empresas asignadas')
                        ->helperText('Estas son las empresas finales a las que tendrá acceso el usuario. Puedes quitar empresas después de cargar el grupo.')
                        ->options(function (Forms\Get $get) use ($tenantId): array {
                            $groupId = $get('access_company_group_id');

                            $selectedCompanyIds = collect($get('companies') ?? [])
                                ->filter()
                                ->map(fn ($id): int => (int) $id)
                                ->unique()
                                ->values()
                                ->all();

                            $query = Company::query()
                                ->select(['companies.id', 'companies.name'])
                                ->where('active', true);

                            if ($groupId) {
                                $query->where('companies.company_group_id', $groupId);
                            } elseif ($tenantId) {
                                $query->where(function (Builder $q) use ($tenantId, $selectedCompanyIds) {
                                    $q->where('companies.id', $tenantId);

                                    if (! empty($selectedCompanyIds)) {
                                        $q->orWhereIn('companies.id', $selectedCompanyIds);
                                    }
                                });
                            }

                            return $query
                                ->orderBy('companies.name')
                                ->pluck('companies.name', 'companies.id')
                                ->toArray();
                        })
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->native(false)
                        ->afterStateUpdated(fn (): null => null),

                    Forms\Components\Select::make('role_group_loader')
                        ->extraAttributes(['class' => 'bexia-usr-field bexia-usr-field-gload'])
                        ->label('Cargar roles base para el grupo')
                        ->helperText('Selecciona roles por nombre. Si faltan en empresas del grupo, se crearán copiando permisos desde el rol plantilla.')
                        ->options(function (Forms\Get $get) use ($tenantId): array {
                            $companyIds = collect($get('companies') ?? [])
                                ->filter()
                                ->map(fn ($id): int => (int) $id)
                                ->unique()
                                ->values();

                            $groupId = $get('access_company_group_id');

                            if ($groupId) {
                                $companyIds = Company::query()
                                    ->where('company_group_id', $groupId)
                                    ->where('active', true)
                                    ->pluck('id')
                                    ->map(fn ($id): int => (int) $id)
                                    ->values();
                            }

                            if ($companyIds->isEmpty() && $tenantId) {
                                $companyIds->push((int) $tenantId);
                            }

                            $companyIds = $companyIds
                                ->unique()
                                ->values()
                                ->all();

                            if (empty($companyIds)) {
                                return [];
                            }

                            return Role::query()
                                ->where(function ($q) use ($companyIds) {
                                    $q->whereNull('roles.company_id')
                                        ->orWhereIn('roles.company_id', $companyIds);
                                })
                                ->orderBy('name')
                                ->pluck('name')
                                ->filter()
                                ->unique()
                                ->values()
                                ->mapWithKeys(fn (string $name): array => [$name => $name])
                                ->toArray();
                        })
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->dehydrated(false)
                        ->native(false)
                        ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) use ($tenantId): void {
                            $roleNames = collect($state ?? [])
                                ->filter()
                                ->map(fn ($name): string => (string) $name)
                                ->unique()
                                ->values()
                                ->all();

                            $groupId = $get('access_company_group_id');

                            if (empty($roleNames)) {
                                $set('role_ids', []);

                                return;
                            }

                            if ($groupId) {
                                $companyIds = Company::query()
                                    ->where('company_group_id', $groupId)
                                    ->where('active', true)
                                    ->orderBy('name')
                                    ->pluck('id')
                                    ->map(fn ($id): int => (int) $id)
                                    ->values();

                                $set('companies', $companyIds
                                    ->map(fn ($id): string => (string) $id)
                                    ->all());
                            } else {
                                $companyIds = collect($get('companies') ?? [])
                                    ->filter()
                                    ->map(fn ($id): int => (int) $id)
                                    ->unique()
                                    ->values();
                            }

                            if ($companyIds->isEmpty() && $tenantId) {
                                $companyIds->push((int) $tenantId);
                            }

                            $companyIds = $companyIds
                                ->unique()
                                ->values()
                                ->all();

                            if (empty($companyIds)) {
                                $set('role_ids', []);

                                return;
                            }

                            $created = 0;
                            $updated = 0;
                            $skipped = [];

                            DB::transaction(function () use ($roleNames, $companyIds, $tenantId, &$created, &$updated, &$skipped): void {
                                foreach ($roleNames as $roleName) {
                                    $availableRoles = Role::query()
                                        ->where('name', $roleName)
                                        ->where(function ($q) use ($companyIds) {
                                            $q->whereNull('roles.company_id')
                                                ->orWhereIn('roles.company_id', $companyIds);
                                        })
                                        ->get(['id', 'name', 'guard_name', 'company_id']);

                                    $sourceRole = null;

                                    if ($tenantId) {
                                        $sourceRole = $availableRoles
                                            ->first(fn (Role $role): bool => filled($role->company_id) && (int) $role->company_id === (int) $tenantId);
                                    }

                                    if (! $sourceRole) {
                                        $sourceRole = $availableRoles
                                            ->first(fn (Role $role): bool => filled($role->company_id));
                                    }

                                    if (! $sourceRole) {
                                        $sourceRole = $availableRoles
                                            ->first(fn (Role $role): bool => blank($role->company_id));
                                    }

                                    if (! $sourceRole) {
                                        $skipped[] = $roleName;

                                        continue;
                                    }

                                    $permissionIds = DB::table('role_has_permissions')
                                        ->where('role_id', $sourceRole->id)
                                        ->pluck('permission_id')
                                        ->map(fn ($id): int => (int) $id)
                                        ->values()
                                        ->all();

                                    foreach ($companyIds as $companyId) {
                                        $existingRole = Role::query()
                                            ->where('name', $roleName)
                                            ->where('guard_name', $sourceRole->guard_name)
                                            ->where('company_id', $companyId)
                                            ->first();

                                        if ($existingRole) {
                                            $existingRole->syncPermissions($permissionIds);
                                            $updated++;

                                            continue;
                                        }

                                        $newRole = Role::query()->create([
                                            'name' => $roleName,
                                            'guard_name' => $sourceRole->guard_name,
                                            'company_id' => $companyId,
                                        ]);

                                        $newRole->syncPermissions($permissionIds);
                                        $created++;
                                    }
                                }

                                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
                            });

                            $loadedRoleIds = Role::query()
                                ->whereIn('name', $roleNames)
                                ->whereIn('company_id', $companyIds)
                                ->orderBy('name')
                                ->orderBy('company_id')
                                ->pluck('id')
                                ->map(fn ($id): string => (string) $id)
                                ->values()
                                ->all();

                            $set('role_ids', $loadedRoleIds);

                            \Log::info('BEXIA_ROLE_GROUP_LOADER_AUTOSYNC_V57119A', [
                                'group_id' => $groupId,
                                'company_ids' => $companyIds,
                                'role_names' => $roleNames,
                                'created' => $created,
                                'updated' => $updated,
                                'skipped' => $skipped,
                                'loaded_role_ids' => $loadedRoleIds,
                            ]);

                            if ($created > 0 || $updated > 0) {
                                Notification::make()
                                    ->title('Roles base sincronizados')
                                    ->body('Creados: ' . $created . '. Actualizados: ' . $updated . '.')
                                    ->success()
                                    ->send();
                            }

                            if (! empty($skipped)) {
                                Notification::make()
                                    ->title('Roles no sincronizados')
                                    ->body('No se encontró rol plantilla para: ' . implode(', ', $skipped))
                                    ->warning()
                                    ->send();
                            }
                        }),

                    Forms\Components\Select::make('role_ids')
                        ->extraAttributes(['class' => 'bexia-usr-field bexia-usr-field-rset'])
                        ->label('Roles')
                        ->options(function (Forms\Get $get) use ($tenantId): array {
                            $companyIds = collect($get('companies') ?? [])
                                ->filter()
                                ->map(fn ($id): int => (int) $id)
                                ->unique()
                                ->values();

                            $selectedRoleIds = collect($get('role_ids') ?? [])
                                ->filter()
                                ->map(fn ($id): int => (int) $id)
                                ->unique()
                                ->values()
                                ->all();

                            $groupId = $get('access_company_group_id');

                            if ($companyIds->isEmpty() && $groupId) {
                                $companyIds = Company::query()
                                    ->where('company_group_id', $groupId)
                                    ->where('active', true)
                                    ->pluck('id')
                                    ->map(fn ($id): int => (int) $id)
                                    ->values();
                            }

                            if ($companyIds->isEmpty() && $tenantId) {
                                $companyIds->push((int) $tenantId);
                            }

                            $companyIds = $companyIds
                                ->unique()
                                ->values()
                                ->all();

                            $roles = Role::query()
                                ->where(function ($q) use ($companyIds, $selectedRoleIds) {
                                    $q->whereNull('roles.company_id');

                                    if (! empty($companyIds)) {
                                        $q->orWhereIn('roles.company_id', $companyIds);
                                    }

                                    if (! empty($selectedRoleIds)) {
                                        $q->orWhereIn('roles.id', $selectedRoleIds);
                                    }
                                })
                                ->orderBy('name')
                                ->get(['id', 'name', 'company_id']);

                            $companyNames = ! empty($companyIds)
                                ? Company::query()->whereIn('id', $companyIds)->pluck('name', 'id')
                                : collect();

                            return $roles
                                ->mapWithKeys(function (Role $role) use ($companyNames): array {
                                    $label = $role->name;

                                    if ($role->company_id) {
                                        $companyName = $companyNames->get($role->company_id);
                                        $label .= $companyName ? ' - ' . $companyName : ' - Empresa #' . $role->company_id;
                                    } else {
                                        $label .= ' - Global';
                                    }

                                    return [$role->id => $label];
                                })
                                ->toArray();
                        })
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->native(false)
                        ->default([])
                        ->helperText('Estos son los roles finales del usuario. Puedes quitar roles por empresa después de usar la carga base por grupo.'),

                    Forms\Components\Select::make('permission_ids')
                        ->extraAttributes(['class' => 'bexia-usr-field bexia-usr-field-pset'])
                        ->label('Permisos directos')
                        ->options(function () {
                            return Permission::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->default([])
                        ->visible(fn () => auth()->check() && auth()->user()->can('user_access.update'))
                        ->dehydrated(fn () => auth()->check() && auth()->user()->can('user_access.update'))
                        ->helperText('Solo usuarios autorizados pueden ver y modificar permisos directos.'),
                ])
                ->columns(1),
        ]);
    }


    public static function normalizeAvatarPathValue(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_array($value)) {
            $value = collect($value)->filter()->first();
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = collect($decoded)->filter()->first();
            }
        }

        if (blank($value)) {
            return null;
        }

        $path = ltrim((string) $value, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return $path !== '' ? $path : null;
    }


    protected static function warehouseOptions(): array
    {
        if (! Schema::hasTable('warehouses')) {
            return [];
        }

        $tenantId = Filament::getTenant()?->getKey();

        $query = DB::table('warehouses');

        if ($tenantId && Schema::hasColumn('warehouses', 'company_id')) {
            $query->where('company_id', $tenantId);
        }

        if (Schema::hasColumn('warehouses', 'is_active')) {
            $query->where('is_active', true);
        }

        $labelColumn = Schema::hasColumn('warehouses', 'name') ? 'name' : 'id';

        return $query
            ->orderBy($labelColumn)
            ->get()
            ->mapWithKeys(function ($warehouse): array {
                $name = trim((string) ($warehouse->name ?? ''));
                $code = trim((string) ($warehouse->code ?? ''));

                if ($name === '') {
                    $name = 'Almacén #' . $warehouse->id;
                }

                $label = $code !== '' ? "{$code} - {$name}" : $name;

                return [(int) $warehouse->id => $label];
            })
            ->all();
    }


    protected static function locationOptions(int $warehouseId = 0): array
    {
        if (! Schema::hasTable('stock_locations')) {
            return [];
        }

        $tenantId = Filament::getTenant()?->getKey();

        $query = DB::table('stock_locations');

        if ($tenantId && Schema::hasColumn('stock_locations', 'company_id')) {
            $query->where('company_id', $tenantId);
        }

        if ($warehouseId > 0 && Schema::hasColumn('stock_locations', 'warehouse_id')) {
            $query->where(function ($qq) use ($warehouseId) {
                $qq->where('warehouse_id', $warehouseId)
                   ->orWhereNull('warehouse_id');
            });
        }

        if (Schema::hasColumn('stock_locations', 'is_active')) {
            $query->where('is_active', true);
        }

        $orderColumn = Schema::hasColumn('stock_locations', 'name') ? 'name' : 'id';

        return $query
            ->orderBy($orderColumn)
            ->get()
            ->mapWithKeys(function ($location): array {
                $name = trim((string) ($location->name ?? ''));
                $code = trim((string) ($location->code ?? ''));

                if ($name === '') {
                    $name = 'Ubicación #' . $location->id;
                }

                $label = $code !== '' ? "{$code} - {$name}" : $name;

                return [(int) $location->id => $label];
            })
            ->all();
    }



    /**
     * @return array<int, int>
     */
    private static function groupDefaultCompanyIds(
        ?int $tenantCompanyId = null
    ): array {
        $tenantCompanyId = $tenantCompanyId
            ?: (int) (
                \Filament\Facades\Filament::getTenant()?->getKey()
                ?? 0
            );

        if ($tenantCompanyId <= 0) {
            return [];
        }

        $tenantCompany = \App\Models\Company::query()
            ->select([
                'id',
                'company_group_id',
            ])
            ->find($tenantCompanyId);

        if (! $tenantCompany) {
            return [];
        }

        $groupId = (int) (
            $tenantCompany->company_group_id ?? 0
        );

        $query = \App\Models\Company::query()
            ->where('active', true);

        if ($groupId > 0) {
            $query->where('company_group_id', $groupId);
        } else {
            $query->whereKey($tenantCompanyId);
        }

        return $query
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function groupDefaultWarehouseOptions(
        ?int $tenantCompanyId = null
    ): array {
        $companyIds = static::groupDefaultCompanyIds(
            $tenantCompanyId
        );

        if ($companyIds === []) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table(
            'warehouses'
        )
            ->join(
                'companies',
                'companies.id',
                '=',
                'warehouses.company_id'
            )
            ->whereIn(
                'warehouses.company_id',
                $companyIds
            )
            ->where('warehouses.is_active', true)
            ->where('companies.active', true)
            ->orderBy('companies.name')
            ->orderBy('warehouses.name')
            ->orderBy('warehouses.id')
            ->get([
                'warehouses.id',
                'warehouses.code',
                'warehouses.name AS warehouse_name',
                'companies.name AS company_name',
            ])
            ->mapWithKeys(function ($warehouse): array {
                $label =
                    trim((string) $warehouse->company_name) .
                    ' — ' .
                    trim((string) $warehouse->warehouse_name);

                if (filled($warehouse->code)) {
                    $label .=
                        ' [' .
                        trim((string) $warehouse->code) .
                        ']';
                }

                return [
                    (string) $warehouse->id => $label,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function groupDefaultLocationOptions(
        int $warehouseId,
        ?int $tenantCompanyId = null
    ): array {
        if ($warehouseId <= 0) {
            return [];
        }

        $companyIds = static::groupDefaultCompanyIds(
            $tenantCompanyId
        );

        if ($companyIds === []) {
            return [];
        }

        $warehouse = \Illuminate\Support\Facades\DB::table(
            'warehouses'
        )
            ->where('id', $warehouseId)
            ->whereIn('company_id', $companyIds)
            ->where('is_active', true)
            ->first([
                'id',
                'company_id',
            ]);

        if (! $warehouse) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table(
            'stock_locations'
        )
            ->where('warehouse_id', $warehouseId)
            ->where(
                'company_id',
                (int) $warehouse->company_id
            )
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'code',
                'name',
            ])
            ->mapWithKeys(function ($location): array {
                $label = trim((string) $location->name);

                if (filled($location->code)) {
                    $label .=
                        ' [' .
                        trim((string) $location->code) .
                        ']';
                }

                return [
                    (string) $location->id => $label,
                ];
            })
            ->all();
    }

    public static function normalizeOperationalDefaults(array $data): array {
        $warehouseId = (int) (
            $data['default_warehouse_id'] ?? 0
        );

        $locationId = (int) (
            $data['default_location_id'] ?? 0
        );

        if ($locationId > 0 && $warehouseId <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'default_warehouse_id' =>
                    'Selecciona un almacén predeterminado antes de guardar una ubicación predeterminada.',
            ]);
        }

        if ($warehouseId <= 0) {
            return $data;
        }

        $companyIds = static::groupDefaultCompanyIds();

        $warehouse = \Illuminate\Support\Facades\DB::table(
            'warehouses'
        )
            ->where('id', $warehouseId)
            ->whereIn('company_id', $companyIds)
            ->where('is_active', true)
            ->first([
                'id',
                'company_id',
            ]);

        if (! $warehouse) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'default_warehouse_id' =>
                    'El almacén predeterminado no pertenece al grupo empresarial actual o no está activo.',
            ]);
        }

        if ($locationId <= 0) {
            return $data;
        }

        $locationExists = \Illuminate\Support\Facades\DB::table(
            'stock_locations'
        )
            ->where('id', $locationId)
            ->where('warehouse_id', $warehouseId)
            ->where(
                'company_id',
                (int) $warehouse->company_id
            )
            ->where('is_active', true)
            ->exists();

        if (! $locationExists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'default_location_id' =>
                    'La ubicación predeterminada debe pertenecer al almacén seleccionado y estar activa.',
            ]);
        }

        return $data;
    }





    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_url')
                    ->extraHeaderAttributes(['class' => 'bexia-usr-col-avatar'])
                    ->extraCellAttributes(['class' => 'bexia-usr-col-avatar'])
                    ->label('Avatar')
                    ->getStateUsing(fn (User $record): ?string => $record->getFilamentAvatarUrl())
                    ->circular(),

                Tables\Columns\TextColumn::make('id')
                    ->extraHeaderAttributes(['class' => 'bexia-usr-col-id bexia-usr-col-numeric'])
                    ->extraCellAttributes(['class' => 'bexia-usr-col-id bexia-usr-col-numeric'])
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes(['class' => 'bexia-usr-col-name bexia-usr-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-usr-col-name bexia-usr-col-primary'])
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->extraHeaderAttributes(['class' => 'bexia-usr-col-mail'])
                    ->extraCellAttributes(['class' => 'bexia-usr-col-mail'])
                    ->label('Correo')
                    ->searchable()
                    ->sortable(),

                // V5.71.27b columna Superadmin removida del listado para liberar espacio.

                Tables\Columns\TextColumn::make('grupos_acceso')
                    ->extraHeaderAttributes(['class' => 'bexia-usr-col-gacc'])
                    ->extraCellAttributes(['class' => 'bexia-usr-col-gacc'])
                    ->label('Grupo acceso')
                    ->getStateUsing(function (User $record): string {
                        /*
                         * V5.71.26k:
                         * El grupo visible del usuario se calcula desde sus empresas asignadas.
                         * El pivot de companyGroups se usa para administración del grupo, no para
                         * decidir si el usuario pertenece o no al grupo en el listado.
                         */
                        $groups = \Illuminate\Support\Facades\DB::table('company_user')
                            ->join('companies', 'companies.id', '=', 'company_user.company_id')
                            ->join('company_groups', 'company_groups.id', '=', 'companies.company_group_id')
                            ->where('company_user.user_id', $record->getKey())
                            ->whereNotNull('companies.company_group_id')
                            ->select('company_groups.name')
                            ->distinct()
                            ->orderBy('company_groups.name')
                            ->pluck('company_groups.name')
                            ->filter()
                            ->values();

                        return $groups->join(', ') ?: 'Sin grupo';
                    })
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('grupos_admin')
                    ->extraHeaderAttributes(['class' => 'bexia-usr-col-gadm'])
                    ->extraCellAttributes(['class' => 'bexia-usr-col-gadm'])
                    ->label('Admin grupos')
                    ->getStateUsing(function (User $record): string {
                        return $record->companyGroups
                            ->filter(fn ($group): bool => (bool) ($group->pivot->is_group_admin ?? false))
                            ->pluck('name')
                            ->filter()
                            ->join(', ') ?: 'No';
                    })
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('empresas_acceso')
                    ->extraHeaderAttributes(['class' => 'bexia-usr-col-eacc'])
                    ->extraCellAttributes(['class' => 'bexia-usr-col-eacc'])
                    ->label('Empresas')
                    ->getStateUsing(function (User $record): string {
                        $companies = $record->companies
                            ->pluck('name')
                            ->filter()
                            ->values();

                        $count = $companies->count();

                        if ($count === 0) {
                            return 'Sin empresas';
                        }

                        $preview = $companies->take(3)->join(', ');

                        if ($count > 3) {
                            return $count . ' empresas: ' . $preview . ' +' . ($count - 3) . ' más';
                        }

                        return $count . ' empresa' . ($count === 1 ? ': ' : 's: ') . $preview;
                    })
                    ->tooltip(function (User $record): ?string {
                        $companies = $record->companies
                            ->pluck('name')
                            ->filter()
                            ->values();

                        return $companies->isNotEmpty()
                            ? $companies->join("\n")
                            : null;
                    })
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->extraHeaderAttributes(['class' => 'bexia-usr-col-created'])
                    ->extraCellAttributes(['class' => 'bexia-usr-col-created'])
                    ->label('Creado')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (User $record) => static::canEdit($record)),

                DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn (User $record) => static::canDelete($record))
                    ->modalHeading('Eliminar usuario')
                    ->modalDescription('¿Estás seguro? Esta acción no se puede deshacer.')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->modalCancelActionLabel('Cancelar')
                    ->before(function ($record) {
                        if ($record->id === auth()->id()) {
                            Notification::make()
                                ->title('No permitido')
                                ->body('No puedes eliminar tu propio usuario.')
                                ->danger()
                                ->send();

                            throw new \Exception('Cancel delete');
                        }

                        if (static::recordIsSuperAdmin($record) && ! static::currentUserIsSuperAdmin()) {
                            Notification::make()
                                ->title('No permitido')
                                ->body('Solo un superadmin puede eliminar a otro superadmin.')
                                ->danger()
                                ->send();

                            throw new \Exception('Cancel delete');
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
