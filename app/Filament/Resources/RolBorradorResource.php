<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RolBorradorResource\Pages;
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

class RolBorradorResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationGroup = 'Seguridad';

    protected static ?string $navigationLabel = 'Rol';

    protected static ?int $navigationSort = 20;

public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->can('rol.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->check() && auth()->user()->can('rol.manage');
    }

    public static function canDeleteAny(): bool
    {
        return auth()->check() && auth()->user()->can('rol.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->check() && auth()->user()->can('rol.manage');
    }

    public static function getModelLabel(): string
    {
        return 'Rol';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Rol';
    }

    public static function getSlug(): string
    {
        return 'rol-borrador';
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()
            ->withCount('permissions')
            ->where('name', 'Administrador')
            ->when($tenantId, fn (Builder $query) => $query->where('company_id', $tenantId));
    }

        public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return (bool) ($user && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin());
    }

        public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin());
    }

    public static function form(Form $form): Form
    {
        $tenantId = Filament::getTenant()?->getKey();

        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre del rol')
                ->required()
                ->maxLength(255),

            Forms\Components\Select::make('company_id')
                ->label('Empresa')
                ->options(function () use ($tenantId) {
                    return Company::query()
                        ->when($tenantId, fn ($query) => $query->where('id', $tenantId))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->default($tenantId)
                ->required()
                ->searchable()
                ->preload()
                ->native(false),

            Forms\Components\CheckboxList::make('permission_ids')
                ->label('Permisos')
                ->options(function () {
                    $labels = [
                        'rol.view' => 'Ver Rol',
                        'rol.manage' => 'Administrar Rol',

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
                        'salidas.configurar' => 'Configurar salidas',
                    ];

                    return Permission::query()
                        ->whereIn('name', array_keys($labels))
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn ($permission) => [
                            $permission->id => ($labels[$permission->name] ?? $permission->name) . ' (' . $permission->name . ')',
                        ])
                        ->toArray();
                })
                ->afterStateHydrated(function ($component, ?Role $record) {
                    if ($record) {
                        $component->state(
                            $record->permissions()->pluck('permissions.id')->toArray()
                        );
                    }
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
                    ->label('Editar')
                    ->visible(fn () => auth()->check() && auth()->user()->can('rol.manage')),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn () => auth()->check() && auth()->user()->can('rol.manage')),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRolBorradors::route('/'),
            'create' => Pages\CreateRolBorrador::route('/create'),
            'edit' => Pages\EditRolBorrador::route('/{record}/edit'),
        ];
    }
}