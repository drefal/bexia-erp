<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountingEntryResource\Pages;
use App\Filament\Resources\AccountingEntryResource\RelationManagers;
use App\Models\AccountingEntry;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AccountingEntryResource extends Resource
{
    protected static ?string $model = AccountingEntry::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Contabilidad';

    protected static ?string $navigationLabel = 'Asientos';

    protected static ?string $modelLabel = 'Asiento contable';

    protected static ?string $pluralModelLabel = 'Asientos contables';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        try {
            $tenant = Filament::getTenant();

            if ($tenant && Schema::hasColumn('accounting_entries', 'company_id')) {
                $query->where('company_id', $tenant->getKey());
            }
        } catch (Throwable $e) {
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function statusLabel(?string $status): string
    {
        return [
            'draft' => 'Borrador',
            'posted' => 'Contabilizado',
            'cancelled' => 'Cancelado',
            'canceled' => 'Cancelado',
            'error' => 'Con error',
            'not_posted' => 'Sin contabilizar',
            'partial' => 'Parcial',
        ][$status] ?? ($status ?: 'Sin estatus');
    }

    public static function statusColor(?string $status): string
    {
        return [
            'draft' => 'gray',
            'posted' => 'success',
            'cancelled' => 'danger',
            'canceled' => 'danger',
            'error' => 'danger',
            'not_posted' => 'gray',
            'partial' => 'warning',
        ][$status] ?? 'gray';
    }

    public static function sourceLabel(?string $source): string
    {
        return [
            'account_payable_payment' => 'Pago de cuenta por pagar',
            'account_payable_payment_cancellation' => 'Cancelación de pago CxP',

            'inventory.adjustment_in:manual_inventory' => 'Ajuste manual de inventario',
            'inventory.sale_issue:sales_order_lines' => 'Costo de venta',
            'inventory.purchase_receipt:purchase_order_lines' => 'Entrada compra',
            'inventory.sale_issue:pos_order_lines' => 'Costo POS',
            'inventory.customer_return:pos_order_refund_lines' => 'Devolución POS',
            'accounting.reversal' => 'Reversa contable',
            'invoice' => 'Factura',
        ][$source] ?? ($source ?: 'Sin origen');
    }

public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('accounting.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('accounting.view')
            );
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Asiento')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('entry_number')->label('Número'),
                            TextEntry::make('entry_date')->label('Fecha')->date(),
                            TextEntry::make('status')
                                ->label('Estatus')
                                ->badge()
                                ->formatStateUsing(fn ($state) => self::statusLabel($state))
                                ->color(fn ($state) => self::statusColor($state)),
                            TextEntry::make('source_type')
                                ->label('Origen')
                                ->formatStateUsing(fn ($state) => self::sourceLabel($state)),
                            TextEntry::make('source_id')->label('ID origen'),
                            TextEntry::make('currency')->label('Moneda'),
                            TextEntry::make('total_debit')->label('Debe')->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 2) . ' MXN'),
                            TextEntry::make('total_credit')->label('Haber')->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 2) . ' MXN'),
                            TextEntry::make('posted_at')->label('Contabilizado')->dateTime(),
                        ]),
                    TextEntry::make('notes')->label('Notas')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('entry_number')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('entry_date')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('company_id')
                    ->label('Empresa')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::statusLabel($state))
                    ->color(fn ($state) => self::statusColor($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('source_type')
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::sourceLabel($state))
                    ->color('info')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('source_id')
                    ->label('ID origen')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_debit')
                    ->label('Debe')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 2) . ' MXN'),

                Tables\Columns\TextColumn::make('total_credit')
                    ->label('Haber')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => '$ ' . number_format((float) $state, 2) . ' MXN'),

                Tables\Columns\TextColumn::make('posted_at')
                    ->label('Fecha contabilización')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'draft' => 'Borrador',
                        'posted' => 'Contabilizado',
                        'cancelled' => 'Cancelado',
                        'error' => 'Con error',
                    ]),

                Tables\Filters\SelectFilter::make('source_type')
                    ->label('Origen')
                    ->options(fn () => self::sourceTypeOptions()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),
            ])
            ->bulkActions([]);
    }

    private static function sourceTypeOptions(): array
    {
        try {
            $query = DB::table('accounting_entries')
                ->whereNotNull('source_type');

            $tenant = Filament::getTenant();

            if ($tenant && Schema::hasColumn('accounting_entries', 'company_id')) {
                $query->where('company_id', $tenant->getKey());
            }

            return $query
                ->distinct()
                ->orderBy('source_type')
                ->pluck('source_type', 'source_type')
                ->mapWithKeys(fn ($value, $key) => [$key => self::sourceLabel($value)])
                ->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountingEntries::route('/'),
            'view' => Pages\ViewAccountingEntry::route('/{record}'),
        ];
    }
}
