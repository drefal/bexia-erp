<?php

namespace App\Filament\Resources\AccountingEntryResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Partidas';

    public static function sourceLabel(?string $source): string
    {
        return [
            'inventory.adjustment_in' => 'Ajuste de inventario',
            'inventory.purchase_receipt' => 'Entrada por compra',
            'inventory.sale_issue' => 'Costo de venta',
            'inventory.customer_return' => 'Devolución de cliente',
            'accounting.reversal' => 'Reversa contable',
            'invoice' => 'Factura',
        ][$source] ?? ($source ?: 'Sin origen');
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('line_number')
            ->columns([
                Tables\Columns\TextColumn::make('line_number')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('account.code')
                    ->label('Cuenta')
                    ->searchable(),

                Tables\Columns\TextColumn::make('account.name')
                    ->label('Nombre cuenta')
                    ->searchable(),

                Tables\Columns\TextColumn::make('label')
                    ->label('Concepto')
                    ->searchable(),

                Tables\Columns\TextColumn::make('debit')
                    ->label('Debe')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 2) . ' MXN'),

                Tables\Columns\TextColumn::make('credit')
                    ->label('Haber')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 2) . ' MXN'),

                Tables\Columns\TextColumn::make('source_type')
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::sourceLabel($state))
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('source_id')
                    ->label('ID origen')
                    ->toggleable(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
