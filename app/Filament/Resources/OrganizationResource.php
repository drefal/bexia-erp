<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrganizationResource\Pages;
use App\Models\Organization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class OrganizationResource extends Resource
{
    /**
     * BEXIA_ORGANIZATION_RESOURCE_RESPONSIVE_V5_79_112C
     *
     * Visual-only responsive classes for OrganizationResource.
     */
    protected static ?string $model = Organization::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Configuración Bexia';
    protected static bool $isScopedToTenant = false;
    protected static ?string $tenantOwnershipRelationshipName = null;
    protected static ?int $navigationSort = 10;

public static function getNavigationLabel(): string
    {
        return 'Clientes Bexia';
    }

        public static function shouldRegisterNavigation(): bool
        {
            return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
                'resources.organizationresource',
                fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
            );
        }

        protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return (bool) ($user && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin());
    }

        public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin());
    }

    // BEXIA_V57917C_ORG_CRUD_PERMISSIONS
    protected static function bexiaCanManageOrganizations(): bool
    {
        $user = auth()->user();

        return (bool) ($user && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin());
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanManageOrganizations();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::bexiaCanManageOrganizations();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::bexiaCanManageOrganizations();
    }

    public static function canDeleteAny(): bool
    {
        return static::bexiaCanManageOrganizations();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->extraAttributes([
                    'class' => 'bexia-orgn-field bexia-orgn-name-field bexia-orgn-compact-field',
                ])
                ->label('Nombre')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    if (blank($get('slug'))) {
                        $set('slug', Str::slug((string) $state));
                    }
                }),
            Forms\Components\TextInput::make('slug')
                ->extraAttributes([
                    'class' => 'bexia-orgn-field bexia-orgn-slug-field bexia-orgn-compact-field',
                ])
                ->label('Slug')
                ->required(),
            Forms\Components\Toggle::make('active')
                ->extraAttributes([
                    'class' => 'bexia-orgn-field bexia-orgn-active-field bexia-orgn-boolean-field',
                ])
                ->label('Activo')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')
                ->extraHeaderAttributes([
                    'class' => 'bexia-orgn-header bexia-orgn-col-name',
                ])
                ->extraCellAttributes([
                    'class' => 'bexia-orgn-cell bexia-orgn-col-name bexia-orgn-col-main',
                ])
                ->label('Nombre')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('slug')
                ->extraHeaderAttributes([
                    'class' => 'bexia-orgn-header bexia-orgn-col-slug',
                ])
                ->extraCellAttributes([
                    'class' => 'bexia-orgn-cell bexia-orgn-col-slug bexia-orgn-col-main',
                ])
                ->label('Slug')->sortable()->searchable(),
            Tables\Columns\IconColumn::make('active')
                ->extraHeaderAttributes([
                    'class' => 'bexia-orgn-header bexia-orgn-col-active',
                ])
                ->extraCellAttributes([
                    'class' => 'bexia-orgn-cell bexia-orgn-col-active bexia-orgn-col-bool',
                ])
                ->label('Activo')->boolean(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizations::route('/'),
            'create' => Pages\CreateOrganization::route('/create'),
            'edit' => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }
}
