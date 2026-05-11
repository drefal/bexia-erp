<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variantes';

    protected static ?string $modelLabel = 'variante';

    protected static ?string $pluralModelLabel = 'variantes';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return ! (bool) ($ownerRecord->is_variant ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Sin variantes')
            ->emptyStateDescription('Este producto todavía no tiene variantes ligadas.')
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Imagen')
                    ->disk('public')
                    ->square()
                    ->height(48),

                Tables\Columns\TextColumn::make('variant_group')
                    ->label('Atributo')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('variant_value')
                    ->label('Valor')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('internal_reference')
                    ->label('Referencia')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU / Código de barras')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Precio')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('standard_cost')
                    ->label('Costo')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('source_stock_qty')
                    ->label('Stock migrado')
                    ->state(function (Product $record): mixed {
                        $extra = is_array($record->extra_attributes)
                            ? $record->extra_attributes
                            : json_decode((string) $record->extra_attributes, true);

                        return is_array($extra) ? ($extra['source_stock_qty'] ?? null) : null;
                    })
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->recordUrl(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record]))
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Abrir')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record])),
            ])
            ->defaultSort('variant_value');
    }
}
