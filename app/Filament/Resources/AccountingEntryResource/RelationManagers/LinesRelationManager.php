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
            'account_receivable_invoice' => 'CxC por factura',
            'account_receivable_sales_order' => 'CxC por venta entregada',
            'account_payable_purchase_receipt' => 'CxP por recepción de compra',
            'account_payable_payment' => 'Pago de cuenta por pagar',
            'account_payable_payment_cancellation' => 'Cancelación de pago CxP',

            'inventory.adjustment_in' => 'Ajuste de inventario',
            'inventory.adjustment_out' => 'Ajuste negativo de inventario',
            'inventory.adjustment_in:manual_inventory' => 'Ajuste manual de inventario',
            'inventory.adjustment_out:manual_inventory' => 'Ajuste negativo de inventario',
            'inventory.purchase_receipt' => 'Entrada por compra',
            'inventory.purchase_receipt:purchase_order_lines' => 'Entrada compra',
            'inventory.sale_issue' => 'Costo de venta',
            'inventory.sale_issue:sales_order_lines' => 'Costo de venta',
            'inventory.sale_issue:pos_order_lines' => 'Costo POS',
            'inventory.customer_return' => 'Devolución de cliente',
            'inventory.customer_return:pos_order_refund_lines' => 'Devolución POS',
            'inventory.supplier_return' => 'Devolución a proveedor',

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
