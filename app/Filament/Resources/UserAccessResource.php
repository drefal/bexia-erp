<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserAccessResource\Pages;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserAccessResource extends Resource
{
    protected static ?int $navigationSort = 30;
    protected static ?string $model = User::class;
    protected static bool $isScopedToTenant = false;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $tenantOwnershipRelationshipName = null;
    protected static ?string $navigationGroup = 'Seguridad';

public static function shouldRegisterNavigation(): bool
{
    $user = auth()->user();

    return (bool) (
        $user &&
        (
            (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) ||
            $user->can('users.view') ||
            $user->can('user_access.view')
        )
    );
}

    public static function getNavigationLabel(): string
    {
        return 'Usuarios';
    }

    public static function getModelLabel(): string
    {
        return 'Permiso de usuario';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Permisos de usuario';
    }

    public static function getBreadcrumb(): string
    {
        return 'Usuarios';
    }

public static function canViewAny(): bool
{
    $user = auth()->user();

    return (bool) (
        $user &&
        (
            (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) ||
            $user->can('users.view') ||
            $user->can('user_access.view')
        )
    );
}

    public static function canEdit(Model $record): bool
    {
        return auth()->check() && auth()->user()->can('users.update');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()
            ->with(['companies', 'roles'])
            ->when($tenantId, function (Builder $query) use ($tenantId) {
                $query->whereHas('companies', fn (Builder $q) => $q->where('companies.id', $tenantId));
            });
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre')
                ->disabled(),

            Forms\Components\TextInput::make('email')
                ->label('Correo')
                ->disabled(),

            Forms\Components\CheckboxList::make('roles')
                ->label('Roles')
                ->options(function () {
                    $tenantId = Filament::getTenant()?->getKey();

                    return Role::query()
                        ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->afterStateHydrated(function ($component, ?User $record) {
                    if (! $record) {
                        return;
                    }

                    $tenantId = Filament::getTenant()?->getKey();

                    if (! $tenantId) {
                        return;
                    }

                    app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

                    $component->state(
                        $record->roles()
                            ->where('roles.company_id', $tenantId)
                            ->pluck('roles.id')
                            ->map(fn ($id) => (string) $id)
                            ->all()
                    );
                })
                ->columns(2),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles_actuales')
                    ->label('Roles')
                    ->getStateUsing(function ($record) {
                        $tenantId = Filament::getTenant()?->getKey();

                        return $record->roles
                            ->where('company_id', $tenantId)
                            ->pluck('name')
                            ->join(', ');
                    })
                    ->badge(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar permisos'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserAccess::route('/'),
            'edit' => Pages\EditUserAccess::route('/{record}/edit'),
        ];
    }
}
