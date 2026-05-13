<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SatUnitResource\Pages;
use App\Models\SatUnit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SatUnitResource extends Resource
{
    protected static ?string $model = SatUnit::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Facturación';

    protected static ?string $navigationLabel = 'Unidades SAT';

    protected static ?string $modelLabel = 'unidad SAT';

    protected static ?string $pluralModelLabel = 'unidades SAT';

    protected static ?int $navigationSort = 30;

public static function canCreate(): bool
{
    return auth()->user()?->can('sat_units.create') ?? false;
}

public static function canEdit($record): bool
{
    return auth()->user()?->can('sat_units.update') ?? false;
}

public static function canDelete($record): bool
{
    return auth()->user()?->can('sat_units.delete') ?? false;
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
                Forms\Components\Section::make('Unidad SAT')
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->label('Clave SAT')
                            ->placeholder('H87, XBX, XPK...')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('key', strtoupper(trim((string) $state)))),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(150),

                        Forms\Components\TextInput::make('symbol')
                            ->label('Símbolo')
                            ->maxLength(30),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('Clave')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('symbol')
                    ->label('Símbolo')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nueva unidad SAT'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSatUnits::route('/'),
            'create' => Pages\CreateSatUnit::route('/create'),
            'edit' => Pages\EditSatUnit::route('/{record}/edit'),
        ];
    }
}
