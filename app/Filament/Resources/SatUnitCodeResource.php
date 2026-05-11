<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SatUnitCodeResource\Pages;
use App\Models\Product;
use App\Models\SatUnitCode;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SatUnitCodeResource extends Resource
{
    // sat_unit_catalog_fields_v1
    protected static bool $isScopedToTenant = false;

    protected static ?string $model = SatUnitCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Catálogos de facturación';
    protected static ?string $navigationLabel = 'Unidades SAT';
    protected static ?string $modelLabel = 'Unidad SAT';
    protected static ?string $pluralModelLabel = 'Unidades SAT';
    protected static ?int $navigationSort = 41;

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Unidad SAT')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Clave')
                            ->required()
                            ->maxLength(20)
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(5),

                        Forms\Components\TextInput::make('symbol')
                            ->label('Símbolo')
                            ->maxLength(30)
                            ->columnSpan(3),

                        Forms\Components\DatePicker::make('valid_from')
                            ->label('Inicio vigencia')
                            ->columnSpan(3),

                        Forms\Components\DatePicker::make('valid_to')
                            ->label('Fin vigencia')
                            ->columnSpan(3),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true)
                            ->columnSpan(3),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('note')
                            ->label('Nota')
                            ->rows(3)
                            ->columnSpanFull(),
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

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('symbol')
                    ->label('Símbolo')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->wrap()
                    ->limit(80)
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
            'index' => Pages\ListSatUnitCodes::route('/'),
            'create' => Pages\CreateSatUnitCode::route('/create'),
            'edit' => Pages\EditSatUnitCode::route('/{record}/edit'),
        ];
    }
}
