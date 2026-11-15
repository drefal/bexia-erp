<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomBalloonExtraResource\Pages;
use App\Models\CustomBalloonExtra;
use App\Support\CustomBalloonsAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomBalloonExtraResource extends Resource
{
    protected static ?string $model =
        CustomBalloonExtra::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon =
        'heroicon-o-plus-circle';

    protected static ?string $navigationGroup =
        'Globos personalizados';

    protected static ?string $navigationLabel = 'Extras';

    protected static ?string $modelLabel = 'extra';

    protected static ?string $pluralModelLabel = 'extras';

    protected static ?int $navigationSort = 60;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Extra')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Clave')
                            ->required()
                            ->maxLength(60)
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
                            ->maxLength(160),

                        Forms\Components\Select::make('pricing_type')
                            ->label('Tipo de precio')
                            ->options([
                                'fixed' => 'Precio fijo',
                                'manual' => 'Importe manual',
                            ])
                            ->default('fixed')
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('price')
                            ->label('Precio')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->minValue(0)
                            ->disabled(
                                fn (Forms\Get $get): bool =>
                                    $get('pricing_type') === 'manual'
                            )
                            ->dehydrated()
                            ->helperText(
                                'Si es importe manual, el precio se captura al hacer el pedido.'
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
                    ->label('Extra')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pricing_type')
                    ->label('Tipo')
                    ->formatStateUsing(
                        fn (string $state): string =>
                            $state === 'manual'
                                ? 'Importe manual'
                                : 'Precio fijo'
                    )
                    ->badge(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->money('MXN')
                    ->placeholder('Manual'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(
                        fn (): bool =>
                            CustomBalloonsAccess::canManageExtras()
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
            );
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
        return CustomBalloonsAccess::canManageExtras();
    }

    public static function canEdit($record): bool
    {
        return CustomBalloonsAccess::canManageExtras()
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
            'index' => Pages\ListCustomBalloonExtras::route('/'),
            'create' => Pages\CreateCustomBalloonExtra::route('/create'),
            'edit' => Pages\EditCustomBalloonExtra::route('/{record}/edit'),
        ];
    }
}
