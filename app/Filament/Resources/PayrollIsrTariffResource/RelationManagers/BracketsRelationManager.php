<?php

namespace App\Filament\Resources\PayrollIsrTariffResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BracketsRelationManager extends RelationManager
{
    protected static string $relationship = 'brackets';

    protected static ?string $title = 'Rangos ISR';

    protected static ?string $modelLabel = 'rango ISR';

    protected static ?string $pluralModelLabel = 'rangos ISR';

    protected function ownerLocked(): bool
    {
        $owner = $this->getOwnerRecord();

        return method_exists(
            $owner,
            'isHistoricallyUsed'
        )
            && $owner->isHistoricallyUsed();
    }

    protected static function clean(
        mixed $state,
        int $decimals = 2
    ): string {
        if ($state === null || $state === '') {
            return '';
        }

        return number_format(
            (float) $state,
            $decimals,
            '.',
            ''
        );
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make(
                    'sort_order'
                )
                    ->label('Orden')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required(),

                Forms\Components\TextInput::make(
                    'lower_limit'
                )
                    ->label('Límite inferior')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->prefix('$')
                    ->afterStateHydrated(
                        fn (
                            Forms\Components\TextInput $component,
                            $state
                        ) =>
                            $component->state(
                                static::clean($state)
                            )
                    ),

                Forms\Components\TextInput::make(
                    'upper_limit'
                )
                    ->label('Límite superior')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$')
                    ->helperText(
                        'Vacío únicamente para el último rango.'
                    )
                    ->afterStateHydrated(
                        fn (
                            Forms\Components\TextInput $component,
                            $state
                        ) =>
                            $component->state(
                                static::clean($state)
                            )
                    ),

                Forms\Components\TextInput::make(
                    'fixed_fee'
                )
                    ->label('Cuota fija')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->prefix('$')
                    ->afterStateHydrated(
                        fn (
                            Forms\Components\TextInput $component,
                            $state
                        ) =>
                            $component->state(
                                static::clean($state)
                            )
                    ),

                Forms\Components\TextInput::make(
                    'marginal_rate'
                )
                    ->label('Tasa marginal')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->required()
                    ->suffix('%')
                    ->afterStateHydrated(
                        fn (
                            Forms\Components\TextInput $component,
                            $state
                        ) =>
                            $component->state(
                                static::clean($state)
                            )
                    ),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make(
                    'sort_order'
                )
                    ->label('#'),

                Tables\Columns\TextColumn::make(
                    'lower_limit'
                )
                    ->label('Límite inferior')
                    ->money('MXN'),

                Tables\Columns\TextColumn::make(
                    'upper_limit'
                )
                    ->label('Límite superior')
                    ->money('MXN')
                    ->placeholder('En adelante'),

                Tables\Columns\TextColumn::make(
                    'fixed_fee'
                )
                    ->label('Cuota fija')
                    ->money('MXN'),

                Tables\Columns\TextColumn::make(
                    'marginal_rate'
                )
                    ->label('Tasa')
                    ->formatStateUsing(
                        fn ($state): string =>
                            number_format(
                                (float) $state,
                                2
                            )
                            . '%'
                    ),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar rango')
                    ->visible(
                        fn (): bool =>
                            ! $this->ownerLocked()
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(
                        fn (): bool =>
                            ! $this->ownerLocked()
                    ),
            ])
            ->bulkActions([])
            ->defaultSort('sort_order');
    }
}
