<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SatBillingCatalogItemResource\Pages;
use App\Models\SatBillingCatalog;
use App\Models\SatBillingCatalogItem;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SatBillingCatalogItemResource extends Resource
{
    protected static ?string $model = SatBillingCatalogItem::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Catálogos de facturación';
    protected static ?string $navigationLabel = 'Catálogos CFDI';
    protected static ?string $modelLabel = 'Elemento CFDI';
    protected static ?string $pluralModelLabel = 'Catálogos CFDI';
    protected static ?int $navigationSort = 50;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
            return true;
        }

        if (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
            return true;
        }

        return $user->can('accounting.update') || $user->can('inventory.update');
    }

    protected static function catalogOptions(): array
    {
        return SatBillingCatalog::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'catalog_key')
            ->all();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Catálogo CFDI')
                    ->schema([
                        Forms\Components\Select::make('catalog_key')
                            ->label('Catálogo')
                            ->options(fn (): array => static::catalogOptions())
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('source_sheet')
                            ->label('Hoja origen')
                            ->maxLength(120)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(120)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->maxLength(500)
                            ->columnSpan(6),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpan(6),

                        Forms\Components\DatePicker::make('valid_from')
                            ->label('Inicio vigencia')
                            ->columnSpan(3),

                        Forms\Components\DatePicker::make('valid_to')
                            ->label('Fin vigencia')
                            ->columnSpan(3),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->columnSpan(3),
                    ])
                    ->columns(12),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('catalog.name')
                    ->label('Catálogo')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->limit(70)
                    ->wrap()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->limit(100)
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('source_sheet')
                    ->label('Hoja')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('valid_from')
                    ->label('Inicio')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('valid_to')
                    ->label('Fin')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('catalog_key')
            ->filters([
                Tables\Filters\SelectFilter::make('catalog_key')
                    ->label('Catálogo')
                    ->options(fn (): array => static::catalogOptions())
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSatBillingCatalogItems::route('/'),
            'create' => Pages\CreateSatBillingCatalogItem::route('/create'),
            'edit' => Pages\EditSatBillingCatalogItem::route('/{record}/edit'),
        ];
    }
}
