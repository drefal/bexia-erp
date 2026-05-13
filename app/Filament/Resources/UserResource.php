<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
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
        $query = parent::getEloquentQuery()->with(['companies']);

        if ($tenantId) {
            $query->whereHas('companies', function (Builder $q) use ($tenantId) {
                $q->where('companies.id', $tenantId);
            });
        }

        if (! static::currentUserIsSuperAdmin()) {
            $query->where(function (Builder $q) {
                $q->whereNull('is_system_admin')
                  ->orWhere('is_system_admin', false);
            });
        }

        return $query;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('users.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('users.view')
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
                        ->options(fn (Forms\Get $get): array => static::locationOptions((int) ($get('default_warehouse_id') ?? 0))),
                ])
                ->columns(2),

            Forms\Components\Section::make('Accesos')
                ->schema([
                    Forms\Components\Select::make('companies')
                        ->label('Empresas')
                        ->relationship(
                            name: 'companies',
                            titleAttribute: 'name',
                            modifyQueryUsing: function (Builder $query) use ($tenantId) {
                                if ($tenantId) {
                                    $query->where('companies.id', $tenantId);
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

                Tables\Columns\TextColumn::make('empresa_actual')
                    ->label('Empresa')
                    ->getStateUsing(function ($record) {
                        $tenantId = Filament::getTenant()?->getKey();

                        return $record->companies
                            ->where('id', $tenantId)
                            ->pluck('name')
                            ->join(', ');
                    })
                    ->badge(),

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
