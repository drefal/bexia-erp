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
    protected static ?string $model = CompanyGroup::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $navigationGroup = 'Configuración';
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
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('settings.access')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('settings.access')
            );
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('organization_id')
                ->label('Cliente Bexia')
                ->options(fn () => Organization::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    if (blank($get('slug'))) {
                        $set('slug', Str::slug((string) $state));
                    }
                }),

            Forms\Components\TextInput::make('slug')
                ->label('Slug')
                ->required(),

            Forms\Components\Toggle::make('active')
                ->label('Activo')
                ->default(true),

            Forms\Components\Toggle::make('free_trial')
                ->label('Sin restricciones')
                ->default(false),

            Forms\Components\TextInput::make('max_companies')
                ->label('Máximo de empresas')
                ->numeric()
                ->minValue(1),

            Forms\Components\TextInput::make('max_branches')
                ->label('Máximo de sucursales')
                ->numeric()
                ->minValue(1),

            Forms\Components\TextInput::make('max_users')
                ->label('Máximo de usuarios')
                ->numeric()
                ->minValue(1),

            Forms\Components\Select::make('admins')
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
                Tables\Columns\TextColumn::make('organization.name')->label('Cliente')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Nombre')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('max_companies')->label('Empresas'),
                Tables\Columns\TextColumn::make('max_branches')->label('Sucursales'),
                Tables\Columns\TextColumn::make('max_users')->label('Usuarios'),
                Tables\Columns\IconColumn::make('free_trial')->label('Sin restricciones')->boolean(),
                Tables\Columns\IconColumn::make('active')->label('Activo')->boolean(),
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
