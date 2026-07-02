<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyGroupResource\Pages;
use App\Models\CompanyGroup;
use App\Models\Organization;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CompanyGroupResource extends Resource
{
    /**
     * BEXIA_COMPANY_GROUP_RESOURCE_RESPONSIVE_V5_79_86C
     *
     * Visual-only responsive classes for CompanyGroupResource.
     */
    protected static ?string $model = CompanyGroup::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $navigationGroup = 'Configuración Bexia';
    protected static bool $isScopedToTenant = false;
    protected static ?string $tenantOwnershipRelationshipName = null;
    protected static ?int $navigationSort = 20;

public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->isSystemAdmin();
    }

    public static function canEdit($record): bool
    {
        return auth()->check() && auth()->user()->isSystemAdmin();
    }

    public static function getNavigationLabel(): string
    {
        return 'Grupos de empresas';
    }

        public static function shouldRegisterNavigation(): bool
        {
            return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
                'resources.companygroupresource',
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

    // BEXIA_V57917C_COMPANY_GROUP_DELETE_PERMISSIONS
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->check() && auth()->user()->isSystemAdmin();
    }

    public static function canDeleteAny(): bool
    {
        return auth()->check() && auth()->user()->isSystemAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->extraAttributes([
                'class' => 'bexia-cgr-form bexia-cgr-form-main',
            ])
            ->schema([
            Forms\Components\Select::make('organization_id')
                ->extraAttributes([
                    'class' => 'bexia-cgr-field bexia-cgr-organization-field bexia-cgr-wide-field',
                ])
                ->label('Cliente Bexia')
                ->options(fn () => Organization::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\TextInput::make('name')
                ->extraAttributes([
                    'class' => 'bexia-cgr-field bexia-cgr-name-field bexia-cgr-wide-field',
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
                    'class' => 'bexia-cgr-field bexia-cgr-slug-field bexia-cgr-wide-field',
                ])
                ->label('Slug')
                ->required(),

            Forms\Components\Toggle::make('active')
                ->extraAttributes([
                    'class' => 'bexia-cgr-field bexia-cgr-active-field bexia-cgr-toggle-field',
                ])
                ->label('Activo')
                ->default(true),

            Forms\Components\Toggle::make('free_trial')
                ->extraAttributes([
                    'class' => 'bexia-cgr-field bexia-cgr-free-trial-field bexia-cgr-toggle-field',
                ])
                ->label('Sin restricciones')
                ->default(false),

            Forms\Components\TextInput::make('max_companies')
                ->extraAttributes([
                    'class' => 'bexia-cgr-field bexia-cgr-limit-field bexia-cgr-companies-field bexia-cgr-number-field',
                ])
                ->label('Máximo de empresas')
                ->numeric()
                ->minValue(1),

            Forms\Components\TextInput::make('max_branches')
                ->extraAttributes([
                    'class' => 'bexia-cgr-field bexia-cgr-limit-field bexia-cgr-branches-field bexia-cgr-number-field',
                ])
                ->label('Máximo de sucursales')
                ->numeric()
                ->minValue(1),

            Forms\Components\TextInput::make('max_users')
                ->extraAttributes([
                    'class' => 'bexia-cgr-field bexia-cgr-limit-field bexia-cgr-users-field bexia-cgr-number-field',
                ])
                ->label('Máximo de usuarios')
                ->numeric()
                ->minValue(1),

            Forms\Components\Select::make('admins')
                ->extraAttributes([
                    'class' => 'bexia-cgr-field bexia-cgr-admins-field bexia-cgr-wide-field',
                ])
                ->label('Admins del grupo')
                ->multiple()
                ->relationship('admins', 'name')
                ->preload()
                ->searchable(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('organization.name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-cgr-header bexia-cgr-col-organization',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-cgr-cell bexia-cgr-col-organization bexia-cgr-col-wide',
                    ])->label('Cliente')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-cgr-header bexia-cgr-col-name',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-cgr-cell bexia-cgr-col-name bexia-cgr-col-wide',
                    ])->label('Nombre')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('max_companies')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-cgr-header bexia-cgr-col-limit bexia-cgr-col-companies',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-cgr-cell bexia-cgr-col-limit bexia-cgr-col-companies bexia-cgr-col-number',
                    ])->label('Empresas'),
                Tables\Columns\TextColumn::make('max_branches')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-cgr-header bexia-cgr-col-limit bexia-cgr-col-branches',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-cgr-cell bexia-cgr-col-limit bexia-cgr-col-branches bexia-cgr-col-number',
                    ])->label('Sucursales'),
                Tables\Columns\TextColumn::make('max_users')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-cgr-header bexia-cgr-col-limit bexia-cgr-col-users',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-cgr-cell bexia-cgr-col-limit bexia-cgr-col-users bexia-cgr-col-number',
                    ])->label('Usuarios'),
                Tables\Columns\IconColumn::make('free_trial')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-cgr-header bexia-cgr-col-free-trial',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-cgr-cell bexia-cgr-col-free-trial bexia-cgr-col-bool',
                    ])->label('Sin restricciones')->boolean(),
                Tables\Columns\IconColumn::make('active')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-cgr-header bexia-cgr-col-active',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-cgr-cell bexia-cgr-col-active bexia-cgr-col-bool',
                    ])->label('Activo')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanyGroups::route('/'),
            'create' => Pages\CreateCompanyGroup::route('/create'),
            'edit' => Pages\EditCompanyGroup::route('/{record}/edit'),
        ];
    }
}
