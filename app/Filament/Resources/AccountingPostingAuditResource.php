<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountingPostingAuditResource\Pages;
use App\Models\AccountingPostingAudit;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AccountingPostingAuditResource extends Resource
{
    protected static ?string $model = AccountingPostingAudit::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Contabilidad';

    protected static ?string $navigationLabel = 'Auditoría contable';

    protected static ?string $modelLabel = 'Auditoría contable';

    protected static ?string $pluralModelLabel = 'Auditorías contables';

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        try {
            $tenant = Filament::getTenant();

            if ($tenant && Schema::hasColumn('accounting_posting_audits', 'company_id')) {
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
            'success' => 'Correcto',
            'error' => 'Con error',
            'info' => 'Informativo',
            'warning' => 'Advertencia',
        ][$status] ?? ($status ?: 'Sin estatus');
    }

    public static function statusColor(?string $status): string
    {
        return [
            'success' => 'success',
            'error' => 'danger',
            'info' => 'info',
            'warning' => 'warning',
        ][$status] ?? 'gray';
    }

    public static function eventLabel(?string $event): string
    {
        return [
            'post_inventory_purchase_receipt' => 'Contabilizar entrada compra',
            'post_inventory_sale_issue' => 'Contabilizar costo de venta',
            'post_inventory_adjustment_in' => 'Contabilizar ajuste positivo',
            'post_inventory_adjustment_out' => 'Contabilizar ajuste negativo',
            'post_inventory_customer_return' => 'Contabilizar devolución cliente',
            'post_inventory_supplier_return' => 'Contabilizar devolución proveedor',
            'post_invoice' => 'Contabilizar factura',
            'reverse_manual_test_entry' => 'Reversa de póliza prueba',
        ][$event] ?? ($event ?: 'Sin evento');
    }

    public static function sourceLabel(?string $source): string
    {
        return [
            'purchase_order_lines' => 'Línea de compra',
            'sales_order_lines' => 'Línea de venta',
            'pos_order_lines' => 'Línea POS',
            'pos_order_refund_lines' => 'Línea devolución POS',
            'manual_inventory' => 'Inventario manual',
            'accounting.reversal' => 'Reversa contable',
            'invoice' => 'Factura',
        ][$source] ?? ($source ?: 'Sin origen');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('company_id')
                    ->label('Empresa')
                    ->sortable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::eventLabel($state))
                    ->color('info')
                    ->searchable()
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
                    ->color('gray')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('source_id')
                    ->label('ID origen')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('accounting_entry_id')
                    ->label('Asiento')
                    ->sortable(),

                Tables\Columns\TextColumn::make('message')
                    ->label('Mensaje')
                    ->limit(80)
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'success' => 'Correcto',
                        'error' => 'Con error',
                        'info' => 'Informativo',
                        'warning' => 'Advertencia',
                    ]),

                Tables\Filters\SelectFilter::make('event')
                    ->label('Evento')
                    ->options(fn () => self::eventOptions()),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    private static function eventOptions(): array
    {
        try {
            $query = DB::table('accounting_posting_audits')
                ->whereNotNull('event');

            $tenant = Filament::getTenant();

            if ($tenant && Schema::hasColumn('accounting_posting_audits', 'company_id')) {
                $query->where('company_id', $tenant->getKey());
            }

            return $query
                ->distinct()
                ->orderBy('event')
                ->pluck('event', 'event')
                ->mapWithKeys(fn ($value, $key) => [$key => self::eventLabel($value)])
                ->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountingPostingAudits::route('/'),
        ];
    }
}
