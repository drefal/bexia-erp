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

    public static function form(Form $form): Form
    {
        $tenantId = Filament::getTenant()?->getKey();

        return $form->schema([
            Forms\Components\Section::make('Datos del usuario')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->label('Correo')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('password')
                        ->label('Contraseña')
                        ->password()
                        ->dehydrated(fn ($state) => filled($state))
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                        ->required(fn (string $operation): bool => $operation === 'create'),

                    FileUpload::make('avatar_path')
                        ->label('Avatar')
                        ->disk('public')
                        ->directory('users/avatars')
                        ->visibility('public')
                        ->image()
                        ->imageEditor()
                        ->avatar()
                        ->imagePreviewHeight('120')
                        ->openable()
                        ->downloadable(),
                ])
                ->columns(2),


            Forms\Components\Section::make('Preferencias operativas')
                ->description('Valores predeterminados para ventas, inventario y entregas.')
                ->schema([
                    Forms\Components\Select::make('default_warehouse_id')
                        ->label('Almacén predeterminado')
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => static::warehouseOptions())
                        ->reactive(),

                    Forms\Components\Select::make('default_location_id')
                        ->label('Ubicación predeterminada')
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => static::locationOptions())
                        ->helperText('No se borra al cambiar almacén. Al guardar se valida que pertenezca al almacén seleccionado.'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Accesos')
                ->schema([
                    Forms\Components\Select::make('company_group_access_helper')
                        ->label('Cargar empresas de un grupo')
                        ->helperText('Selecciona un grupo para llenar automáticamente todas sus empresas. Después puedes quitar manualmente las que no apliquen.')
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
                        ->dehydrated(false)
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
                        }),

                    Forms\Components\Select::make('companies')
                        ->label('Empresas asignadas')
                        ->helperText('Estas son las empresas finales a las que tendrá acceso el usuario. Puedes quitar empresas después de cargar el grupo.')
                        ->relationship(
                            name: 'companies',
                            titleAttribute: 'name',
                            modifyQueryUsing: function (Builder $query, Forms\Get $get) use ($tenantId) {
                                $query->select(['companies.id', 'companies.name']);

                                $groupId = $get('company_group_access_helper');

                                $selectedCompanyIds = collect($get('companies') ?? [])
                                    ->filter()
                                    ->map(fn ($id): int => (int) $id)
                                    ->unique()
                                    ->values()
                                    ->all();

                                if ($groupId) {
                                    $query->where(function (Builder $q) use ($groupId, $selectedCompanyIds) {
                                        $q->where('companies.company_group_id', $groupId);

                                        if (! empty($selectedCompanyIds)) {
                                            $q->orWhereIn('companies.id', $selectedCompanyIds);
                                        }
                                    });

                                    return;
                                }

                                if ($tenantId) {
                                    $query->where(function (Builder $q) use ($tenantId, $selectedCompanyIds) {
                                        $q->where('companies.id', $tenantId);

                                        if (! empty($selectedCompanyIds)) {
                                            $q->orWhereIn('companies.id', $selectedCompanyIds);
                                        }
                                    });
                                }
                            }
                        )
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Forms\Components\Select::make('role_ids')
                        ->label('Roles')
                        ->options(function () use ($tenantId) {
                            return Role::query()
                                ->where(function ($q) use ($tenantId) {
                                    $q->whereNull('roles.company_id');

                                    if ($tenantId) {
                                        $q->orWhere('roles.company_id', $tenantId);
                                    }
                                })
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->default([]),

                    Forms\Components\Select::make('permission_ids')
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


    public static function normalizeOperationalDefaults(array $data): array
    {
        $warehouseId = (int) ($data['default_warehouse_id'] ?? 0);
        $locationId = (int) ($data['default_location_id'] ?? 0);

        if ($warehouseId <= 0 && $locationId <= 0) {
            return $data;
        }

        if ($warehouseId <= 0 && $locationId > 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'default_warehouse_id' => 'Selecciona un almacén predeterminado antes de guardar una ubicación predeterminada.',
            ]);
        }

        if ($warehouseId > 0 && ! array_key_exists($warehouseId, static::warehouseOptions())) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'default_warehouse_id' => 'El almacén predeterminado no pertenece a la empresa actual o no está activo.',
            ]);
        }

        if ($locationId > 0 && ! array_key_exists($locationId, static::locationOptions($warehouseId))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'default_location_id' => 'La ubicación predeterminada debe pertenecer al almacén seleccionado y a la empresa actual.',
            ]);
        }

        return $data;
    }





    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_path')
                    ->label('Avatar')
                    ->disk('public')
                    ->circular(),

                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_system_admin')
                    ->label('Superadmin')
                    ->boolean(),

                Tables\Columns\TextColumn::make('grupos_acceso')
                    ->label('Grupos')
                    ->getStateUsing(function (User $record): string {
                        return $record->companyGroups
                            ->pluck('name')
                            ->filter()
                            ->join(', ') ?: 'Sin grupo';
                    })
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('empresas_acceso')
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
