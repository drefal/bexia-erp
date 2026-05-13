<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SatProductServiceCodeResource\Pages;
use App\Models\Product;
use App\Models\SatProductServiceCode;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SatProductServiceCodeResource extends Resource
{
    // sat_prodserv_catalog_fields_v1
    protected static bool $isScopedToTenant = false;

    protected static ?string $model = SatProductServiceCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Catálogos de facturación';
    protected static ?string $navigationLabel = 'Claves producto/servicio SAT';
    protected static ?string $modelLabel = 'Clave producto/servicio SAT';
    protected static ?string $pluralModelLabel = 'Claves producto/servicio SAT';
    protected static ?int $navigationSort = 40;

    protected static function canManage(): bool
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

    public static function canAccess(): bool
    {
        return static::canManage();
    }

public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('invoicing.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('invoicing.view')
            );
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Clave SAT')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Clave')
                            ->required()
                            ->maxLength(20)
                            ->columnSpan(3),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->required()
                            ->rows(2)
                            ->columnSpan(8),

                        Forms\Components\Toggle::make('include_iva')
                            ->label('Incluye IVA')
                            ->default(false)
                            ->columnSpan(3),

                        Forms\Components\Toggle::make('include_ieps')
                            ->label('Incluye IEPS')
                            ->default(false)
                            ->columnSpan(3),

                        Forms\Components\Toggle::make('border_stimulus')
                            ->label('Estímulo frontera')
                            ->default(false)
                            ->columnSpan(3),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true)
                            ->columnSpan(3),

                        Forms\Components\Textarea::make('required_complement')
                            ->label('Complemento requerido')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('similar_words')
                            ->label('Palabras similares')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\DatePicker::make('valid_from')
                            ->label('Inicio vigencia')
                            ->columnSpan(3),

                        Forms\Components\DatePicker::make('valid_to')
                            ->label('Fin vigencia')
                            ->columnSpan(3),
                    ])
                    ->columns(12),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Clave')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->wrap()
                    ->limit(100)
                    ->sortable(),

                Tables\Columns\IconColumn::make('include_iva')
                    ->label('IVA')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('include_ieps')
                    ->label('IEPS')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('border_stimulus')
                    ->label('Frontera')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

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
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('code')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activa'),
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
            'index' => Pages\ListSatProductServiceCodes::route('/'),
            'create' => Pages\CreateSatProductServiceCode::route('/create'),
            'edit' => Pages\EditSatProductServiceCode::route('/{record}/edit'),
        ];
    }
}
