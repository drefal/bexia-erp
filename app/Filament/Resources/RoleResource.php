<?php

namespace App\Filament\Resources;

use App\Support\PermissionLabels;
use App\Filament\Resources\RoleResource\Pages;
use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;
    protected static bool $isScopedToTenant = false;
    protected static ?string $tenantOwnershipRelationshipName = null;
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'Seguridad';
    protected static ?int $navigationSort = 10;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('roles.view');
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->can('roles.view');
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->can('roles.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->check() && auth()->user()->can('roles.manage');
    }

    public static function canDeleteAny(): bool
    {
        return auth()->check() && auth()->user()->can('roles.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->check() && auth()->user()->can('roles.manage');
    }

    public static function getNavigationLabel(): string
    {
        return 'Roles';
    }

    public static function getModelLabel(): string
    {
        return 'Rol';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Roles';
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()
            ->withCount('permissions')
            ->when($tenantId, fn (Builder $query) => $query->where('company_id', $tenantId));
    }

    public static function form(Form $form): Form
    {
        $tenantId = Filament::getTenant()?->getKey();

        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre del rol')
                ->required()
                ->maxLength(255),

            Forms\Components\Select::make('company_ids')
                ->label('Empresas')
                ->options(fn () => Company::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->multiple()
                ->searchable()
                ->preload()
                ->native(false)
                ->visible(fn (string $operation): bool => auth()->user()?->isSystemAdmin() && $operation === 'create')
                ->required(fn (string $operation): bool => auth()->user()?->isSystemAdmin() && $operation === 'create')
                ->helperText('Como superadmin, puedes replicar este rol en varias empresas.'),

            Forms\Components\Select::make('company_id')
                ->label('Empresa')
                ->options(function () use ($tenantId) {
                    return Company::query()
                        ->when($tenantId, fn ($q) => $q->where('id', $tenantId))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->default($tenantId)
                ->required()
                ->searchable()
                ->preload()
                ->native(false)
                ->visible(fn (string $operation): bool => ! (auth()->user()?->isSystemAdmin() && $operation === 'create'))
                ->dehydrated(fn (string $operation): bool => ! (auth()->user()?->isSystemAdmin() && $operation === 'create')),

            Forms\Components\Placeholder::make('permission_guide')
                ->label('Guía rápida de permisos')
                ->content(new \Illuminate\Support\HtmlString('
                    <div style="font-size: 13px; line-height: 1.65;">
                        <div><strong>Ver empresas</strong>: permite entrar al módulo de empresas y consultar su información.</div>
                        <div><strong>Editar empresas</strong>: permite crear o modificar datos de empresas.</div>
                        <div><strong>Ver usuarios</strong>: permite ver los usuarios de la empresa actual.</div>
                        <div><strong>Crear usuarios</strong>: permite registrar nuevos usuarios.</div>
                        <div><strong>Editar usuarios</strong>: permite modificar usuarios existentes.</div>
                        <div><strong>Eliminar usuarios</strong>: permite eliminar usuarios.</div>
                        <div><strong>Ver salidas</strong>: permite entrar al módulo de salidas.</div>
                        <div><strong>Crear salidas</strong>: permite registrar nuevas salidas.</div>
                        <div><strong>Editar salidas</strong>: permite modificar salidas existentes.</div>
                        <div><strong>Eliminar salidas</strong>: permite borrar salidas.</div>
                        <div><strong>Enviar PDF</strong>: permite enviar el PDF de una salida.</div>
                        <div><strong>Ver todas las salidas</strong>: permite ver salidas de todos los usuarios, no solo las propias.</div>
                        <div><strong>Ver roles</strong>: permite ver el módulo de roles.</div>
                        <div><strong>Administrar roles</strong>: permite crear, editar y eliminar roles.</div>
                        <div><strong>Ver accesos</strong>: permite consultar accesos o permisos directos.</div>
                        <div><strong>Editar accesos</strong>: permite modificar accesos o permisos directos.</div>
                        <div><strong>Acceso a configuración</strong>: permite entrar a opciones avanzadas de configuración.</div>
                    </div>
                '))
                ->columnSpanFull(),

            Forms\Components\CheckboxList::make('permission_ids')
                ->label('Permisos')
                ->options(function () {
                    $labels = [
                        'company.view' => 'Ver empresas',
                        'company.update' => 'Editar empresas',
                        'users.view' => 'Ver usuarios',
                        'users.create' => 'Crear usuarios',
                        'users.update' => 'Editar usuarios',
                        'users.delete' => 'Eliminar usuarios',
                        'salidas.ver' => 'Ver salidas',
                        'salidas.create' => 'Crear salidas',
                        'salidas.update' => 'Editar salidas',
                        'salidas.delete' => 'Eliminar salidas',
                        'salidas.enviar_pdf' => 'Enviar PDF',
                        'salidas.ver_todas' => 'Ver todas las salidas',
                        'roles.view' => 'Ver roles',
                        'roles.manage' => 'Administrar roles',
                        'user_access.view' => 'Ver accesos',
                        'user_access.update' => 'Editar accesos',
                        'settings.access' => 'Acceso a configuración',
                    ];

                    return \Spatie\Permission\Models\Permission::query()
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(function ($permission) use ($labels) {
                            $friendly = PermissionLabels::label($permission->name);

                            return [
                                $permission->id => $friendly . ' (' . $permission->name . ')',
                            ];
                        })
                        ->toArray();
                })
                ->columns(2)
                ->searchable()
                ->bulkToggleable(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Rol')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('company_id')
                    ->label('Empresa')
                    ->formatStateUsing(fn ($state) => Company::find($state)?->name ?? '—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('# Permisos')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => auth()->check() && auth()->user()->can('roles.manage')),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn () => auth()->check() && auth()->user()->can('roles.manage')),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
