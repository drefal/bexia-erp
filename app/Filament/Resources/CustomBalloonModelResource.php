<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomBalloonModelResource\Pages;
use App\Models\CustomBalloonModel;
use App\Models\CustomBalloonSize;
use App\Support\CustomBalloonsAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomBalloonModelResource extends Resource
{
    protected static ?string $model = CustomBalloonModel::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Globos personalizados';

    protected static ?string $navigationLabel = 'Modelos';

    protected static ?string $modelLabel = 'modelo';

    protected static ?string $pluralModelLabel = 'modelos';

    protected static ?int $navigationSort = 40;


    public static function nextGeneratedCode(): string
    {
        $codes = CustomBalloonModel::query()
            ->where(
                'company_id',
                CustomBalloonsAccess::COMPANY_ID
            )
            ->where('code', 'like', 'GLB-%')
            ->pluck('code');

        $max = $codes
            ->map(function ($code): int {
                if (
                    preg_match(
                        '/^GLB-(\d+)$/',
                        (string) $code,
                        $matches
                    )
                ) {
                    return (int) $matches[1];
                }

                return 0;
            })
            ->max() ?? 0;

        return sprintf(
            'GLB-%04d',
            $max + 1
        );
    }

    public static function currentPriceForSize(
        mixed $sizeId
    ): ?float {
        if (! $sizeId) {
            return null;
        }

        $price = \App\Models\CustomBalloonPrice::query()
            ->where(
                'company_id',
                CustomBalloonsAccess::COMPANY_ID
            )
            ->where('size_id', $sizeId)
            ->whereNull('valid_to')
            ->orderByDesc('valid_from')
            ->value('price');

        return $price !== null
            ? (float) $price
            : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Modelo')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Clave')
                    ->default(
                        fn (): string =>
                            static::nextGeneratedCode()
                    )
                    ->readOnly()
                    ->dehydrated()
                    ->helperText(
                        'La clave se genera automáticamente.'
                    )
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
                            ->maxLength(180),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('image_path')
                            ->label('Imagen del modelo')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('custom-balloons/models')
                            ->visibility('public')
                            ->columnSpanFull(),

                        Forms\Components\ViewField::make(
                    'legacy_library_picker'
                )
                    ->label('')
                    ->view(
                        'filament.forms.custom-balloon-legacy-library'
                    )
                    ->dehydrated(false)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                        Forms\Components\Hidden::make('sort_order')
                            ->default(0),

                    ])
                    ->columns(2),

                Forms\Components\Section::make('Tamaños disponibles')
                    ->description(
                        'El modelo no guarda precios. Sólo define qué tamaños se pueden vender.'
                    )
                    ->schema([
                        Forms\Components\Repeater::make('modelSizes')
                            ->label('')
                            ->relationship('modelSizes')
                            ->schema([
                                Forms\Components\Select::make('size_id')
                            ->label('Tamaño')
                            ->options(
                                fn (): array =>
                                    \App\Models\CustomBalloonSize::query()
                                        ->where(
                                            'company_id',
                                            CustomBalloonsAccess::COMPANY_ID
                                        )
                                        ->where('is_active', true)
                                        ->orderBy('sort_order')
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all()
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    mixed $state,
                                    \Filament\Forms\Set $set
                                ): void {
                                    $set(
                                        'current_price',
                                        static::currentPriceForSize(
                                            $state
                                        )
                                    );
                                }
                            ),

                        Forms\Components\TextInput::make(
                            'current_price'
                        )
                            ->label('Precio vigente')
                            ->prefix('$')
                            ->suffix('MXN')
                            ->numeric()
                            ->readOnly()
                            ->dehydrated(false)
                            ->helperText(
                                'Se toma de Tamaños y precios.'
                            )
                            ->afterStateHydrated(
                                function (
                                    Forms\Components\TextInput $component,
                                    \Filament\Forms\Get $get
                                ): void {
                                    $component->state(
                                        static::currentPriceForSize(
                                            $get('size_id')
                                        )
                                    );
                                }
                            ),

                        Forms\Components\TextInput::make(
                            'price_adjustment'
                        )
                            ->label('Ajuste excepcional')
                            ->prefix('$')
                            ->numeric()
                            ->default(0)
                            ->helperText(
                                'Normalmente $0. Sólo modifica el precio para este modelo.'
                            ),

                        Forms\Components\Toggle::make('is_active')
                                    ->label('Disponible')
                                    ->default(true),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar tamaño')
                            ->mutateRelationshipDataBeforeCreateUsing(
                                function (array $data): array {
                                    $data['company_id'] =
                                        CustomBalloonsAccess::COMPANY_ID;

                                    return $data;
                                }
                            )
                            ->mutateRelationshipDataBeforeSaveUsing(
                                function (array $data): array {
                                    $data['company_id'] =
                                        CustomBalloonsAccess::COMPANY_ID;

                                    return $data;
                                }
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ViewColumn::make('gallery_card')
                    ->label('')
                    ->view(
                        'filament.tables.columns.custom-balloon-model-card'
                    ),
            ])
            ->contentGrid([
                'default' => 1,
                'sm' => 1,
                'md' => 2,
                'lg' => 3,
                'xl' => 3,
                '2xl' => 3,
            ])
            ->defaultSort('sort_order')
            ->searchable()
            ->recordUrl(
                fn (CustomBalloonModel $record): string =>
                    static::getUrl(
                        'edit',
                        ['record' => $record]
                    )
            )
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
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
                'modelSizes.size',
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return CustomBalloonsAccess::canViewCatalog();
    }

    public static function canCreate(): bool
    {
        return CustomBalloonsAccess::canManageCatalog();
    }

    public static function canEdit($record): bool
    {
        return CustomBalloonsAccess::canManageCatalog()
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
            'index' => Pages\ListCustomBalloonModels::route('/'),
            'create' => Pages\CreateCustomBalloonModel::route('/create'),
            'edit' => Pages\EditCustomBalloonModel::route('/{record}/edit'),
        ];
    }
}
