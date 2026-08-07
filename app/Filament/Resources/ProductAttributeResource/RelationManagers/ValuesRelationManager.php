<?php

namespace App\Filament\Resources\ProductAttributeResource\RelationManagers;

use App\Models\ProductAttributeValue;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    protected static ?string $title = 'Valores';
    protected static ?string $modelLabel = 'Valor';
    protected static ?string $pluralModelLabel = 'Valores';

    protected static function canManage(string $permission): bool
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

        return $user->can($permission);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return static::canManage('inventory.view');
    }

    /*
     * BEXIA_V5_83_P12C4D_PROTECT_USED_ATTRIBUTE_VALUE
     *
     * Un valor no se elimina si está referenciado por
     * product_attribute_assignments o por una variante
     * histórica usando variant_group / variant_value.
     */
    protected function valueIsUsed(
        ProductAttributeValue $record
    ): bool {
        if (
            \App\Models\ProductAttributeAssignment::query()
                ->where(
                    'product_attribute_value_id',
                    $record->id
                )
                ->exists()
        ) {
            return true;
        }

        $owner = $this->getOwnerRecord();

        return \App\Models\Product::query()
            ->where(
                'company_id',
                $owner->company_id
            )
            ->where('is_variant', true)
            ->whereRaw(
                'LOWER(TRIM(variant_group)) = ?',
                [
                    mb_strtolower(
                        trim((string) $owner->name),
                        'UTF-8'
                    ),
                ]
            )
            ->whereRaw(
                'LOWER(TRIM(variant_value)) = ?',
                [
                    mb_strtolower(
                        trim((string) $record->name),
                        'UTF-8'
                    ),
                ]
            )
            ->exists();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn () => $this->getOwnerRecord()->company_id)
                    ->required(),

                Forms\Components\TextInput::make('code')
                    ->label('Código')
                    ->helperText('Ejemplo: ROJO, AZUL, M, G.')
                    ->required()
                    ->maxLength(80)
                    /*
                     * BEXIA_V5_83_P12C4D_UNIQUE_ATTRIBUTE_VALUE_CODE
                     */
                    ->rules(
                        function (
                            ?ProductAttributeValue $record
                        ): array {
                            $owner = $this->getOwnerRecord();

                            return [
                                function (
                                    string $attribute,
                                    $value,
                                    \Closure $fail
                                ) use ($owner, $record): void {
                                    $normalized =
                                        mb_strtolower(
                                            trim(
                                                (string) $value
                                            ),
                                            'UTF-8'
                                        );

                                    $exists =
                                        ProductAttributeValue::query()
                                            ->where(
                                                'company_id',
                                                $owner->company_id
                                            )
                                            ->where(
                                                'product_attribute_id',
                                                $owner->id
                                            )
                                            ->whereRaw(
                                                'LOWER(TRIM(code)) = ?',
                                                [$normalized]
                                            )
                                            ->when(
                                                $record?->getKey(),
                                                fn ($query) =>
                                                    $query->whereKeyNot(
                                                        $record->getKey()
                                                    )
                                            )
                                            ->exists();

                                    if ($exists) {
                                        $fail(
                                            'Ya existe un valor con este código dentro del atributo.'
                                        );
                                    }
                                },
                            ];
                        }
                    )
                    ->dehydrateStateUsing(
                        fn (?string $state) =>
                            strtoupper(
                                trim((string) $state)
                            )
                    )
                    ->columnSpan(4),

                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255)
                    /*
                     * BEXIA_V5_83_P12C4D_UNIQUE_ATTRIBUTE_VALUE_NAME
                     */
                    ->rules(
                        function (
                            ?ProductAttributeValue $record
                        ): array {
                            $owner = $this->getOwnerRecord();

                            return [
                                function (
                                    string $attribute,
                                    $value,
                                    \Closure $fail
                                ) use ($owner, $record): void {
                                    $normalized =
                                        mb_strtolower(
                                            trim(
                                                (string) $value
                                            ),
                                            'UTF-8'
                                        );

                                    $exists =
                                        ProductAttributeValue::query()
                                            ->where(
                                                'company_id',
                                                $owner->company_id
                                            )
                                            ->where(
                                                'product_attribute_id',
                                                $owner->id
                                            )
                                            ->whereRaw(
                                                'LOWER(TRIM(name)) = ?',
                                                [$normalized]
                                            )
                                            ->when(
                                                $record?->getKey(),
                                                fn ($query) =>
                                                    $query->whereKeyNot(
                                                        $record->getKey()
                                                    )
                                            )
                                            ->exists();

                                    if ($exists) {
                                        $fail(
                                            'Ya existe este valor dentro del atributo.'
                                        );
                                    }
                                },
                            ];
                        }
                    )
                    ->dehydrateStateUsing(
                        fn (?string $state) =>
                            trim((string) $state)
                    )
                    ->columnSpan(5),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Orden')
                    ->numeric()
                    ->default(0)
                    ->columnSpan(3),

                Forms\Components\Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true)
                    ->columnSpan(3),
            ])
            ->columns(12);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable()
                    ->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nuevo valor')
                    ->visible(fn () => static::canManage('inventory.create'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->visible(fn () => static::canManage('inventory.update'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;

                        return $data;
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(
                        fn (
                            ProductAttributeValue $record
                        ): bool =>
                            static::canManage(
                                'inventory.delete'
                            )
                            && ! $this->valueIsUsed(
                                $record
                            )
                    ),
            ])
            ->bulkActions([]);
    }
}
