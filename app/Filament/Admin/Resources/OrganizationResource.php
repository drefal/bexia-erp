<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OrganizationResource\Pages;
use App\Filament\Admin\Resources\OrganizationResource\RelationManagers;
use App\Models\Organization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    // BEXIA_V57917C_ADMIN_ORG_NAV
    protected static ?string $navigationGroup = 'Configuración Bexia';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Clientes Bexia';

    protected static ?string $modelLabel = 'Cliente Bexia';

    protected static ?string $pluralModelLabel = 'Clientes Bexia';

    // BEXIA_V57917C_ADMIN_ORG_PERMISSIONS
    protected static function bexiaCanManageGlobalOrganizations(): bool
    {
        $user = auth()->user();

        return (bool) ($user && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::bexiaCanManageGlobalOrganizations();
    }

    public static function canViewAny(): bool
    {
        return static::bexiaCanManageGlobalOrganizations();
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanManageGlobalOrganizations();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::bexiaCanManageGlobalOrganizations();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::bexiaCanManageGlobalOrganizations();
    }

    public static function canDeleteAny(): bool
    {
        return static::bexiaCanManageGlobalOrganizations();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Toggle::make('active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\IconColumn::make('active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
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
