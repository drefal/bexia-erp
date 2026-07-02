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
    /**
     * BEXIA_SAT_UNIT_RESOURCE_RESPONSIVE_V5_79_101C
     *
     * Visual-only responsive classes for SatUnitResource.
     */

    protected static bool $isScopedToTenant = false;
    protected static ?string $model = SatUnit::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Catálogos';

    protected static ?string $navigationLabel = 'Unidades SAT';

    protected static ?string $modelLabel = 'unidad SAT';

    protected static ?string $pluralModelLabel = 'unidades SAT';

    protected static ?int $navigationSort = 20;

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
    return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
        'resources.satunitresource',
        fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
    );
}

protected static function bexiaBaseShouldRegisterNavigation(): bool
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
                    ->extraAttributes([
                        'class' => 'bexia-satu-section bexia-satu-unit-section',
                    ])
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->extraAttributes([
                                'class' => 'bexia-satu-field bexia-satu-code-field bexia-satu-compact-field',
                            ])
                            ->label('Clave SAT')
                            ->placeholder('H87, XBX, XPK...')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('key', strtoupper(trim((string) $state)))),

                        Forms\Components\TextInput::make('name')
                            ->extraAttributes([
                                'class' => 'bexia-satu-field bexia-satu-name-field bexia-satu-main-field',
                            ])
                            ->label('Nombre')
                            ->required()
                            ->maxLength(150),

                        Forms\Components\TextInput::make('symbol')
                            ->extraAttributes([
                                'class' => 'bexia-satu-field bexia-satu-symbol-field bexia-satu-compact-field',
                            ])
                            ->label('Símbolo')
                            ->maxLength(30),

                        Forms\Components\Toggle::make('is_active')
                            ->extraAttributes([
                                'class' => 'bexia-satu-field bexia-satu-active-field bexia-satu-bool-field',
                            ])
                            ->label('Activa')
                            ->default(true),

                        Forms\Components\Textarea::make('description')
                            ->extraAttributes([
                                'class' => 'bexia-satu-field bexia-satu-description-field bexia-satu-wide-field bexia-satu-textarea-field',
                            ])
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
                Tables\Columns\TextColumn::make('code')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-satu-header bexia-satu-col-code',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-satu-cell bexia-satu-col-code bexia-satu-col-compact',
                    ])
                    ->label('Clave')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-satu-header bexia-satu-col-name',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-satu-cell bexia-satu-col-name bexia-satu-col-main',
                    ])
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('symbol')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-satu-header bexia-satu-col-symbol',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-satu-cell bexia-satu-col-symbol bexia-satu-col-compact',
                    ])
                    ->label('Símbolo')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-satu-header bexia-satu-col-active',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-satu-cell bexia-satu-col-active bexia-satu-col-bool',
                    ])
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
