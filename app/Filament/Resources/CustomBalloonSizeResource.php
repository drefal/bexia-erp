<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomBalloonSizeResource\Pages;
use App\Models\CustomBalloonSize;
use App\Support\CustomBalloonsAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomBalloonSizeResource extends Resource
{
    protected static ?string $model = CustomBalloonSize::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon =
        'heroicon-o-arrows-pointing-out';

    protected static ?string $navigationGroup =
        'Globos personalizados';

    protected static ?string $navigationLabel =
        'Tamaños y precios';

    protected static ?string $modelLabel = 'tamaño';

    protected static ?string $pluralModelLabel =
        'tamaños y precios';

    protected static ?int $navigationSort = 50;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Tamaño')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Clave')
                            ->required()
                            ->maxLength(50)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule) =>
                                    $rule->where(
                                        'company_id',
                                        CustomBalloonsAccess::COMPANY_ID
                                    )
                            ),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(120),

                        Forms\Components\TextInput::make('dimensions')
                            ->label('Medidas')
                            ->maxLength(120)
                            ->placeholder('Ej. 18 pulgadas'),

                        Forms\Components\TextInput::make('current_price')
                            ->label('Precio vigente')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->minValue(0)
                            ->helperText(
                                'Al cambiarlo se conserva automáticamente el precio anterior en el historial.'
                            ),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ])
                    ->columns(2),
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
                    ->label('Tamaño')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('dimensions')
                    ->label('Medidas')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('current_price_display')
                    ->label('Precio vigente')
                    ->state(
                        fn (CustomBalloonSize $record) =>
                            $record->prices->first()?->price
                    )
                    ->money('MXN'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(
                        fn (): bool =>
                            CustomBalloonsAccess::canManagePrices()
                    ),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(
                'company_id',
                CustomBalloonsAccess::COMPANY_ID
            )
            ->with([
                'prices' => fn ($query) =>
                    $query
                        ->whereNull('valid_to')
                        ->orderByDesc('valid_from'),
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return CustomBalloonsAccess::canViewCatalog();
    }

    public static function canCreate(): bool
    {
        return CustomBalloonsAccess::canManagePrices();
    }

    public static function canEdit($record): bool
    {
        return CustomBalloonsAccess::canManagePrices()
            && (int) $record->company_id ===
                CustomBalloonsAccess::COMPANY_ID;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomBalloonSizes::route('/'),
            'create' => Pages\CreateCustomBalloonSize::route('/create'),
            'edit' => Pages\EditCustomBalloonSize::route('/{record}/edit'),
        ];
    }
}
