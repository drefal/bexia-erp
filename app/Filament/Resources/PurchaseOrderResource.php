<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Models\PurchaseOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseOrderResource extends Resource
{
protected static ?string $model = PurchaseOrder::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Compras';

    protected static ?string $navigationLabel = 'Órdenes de compra';

    protected static ?int $navigationSort = 210;

    protected static ?string $modelLabel = 'orden de compra';

    protected static ?string $pluralModelLabel = 'órdenes de compra';
public static function canCreate(): bool
    {
        return false;
    }

    protected static function isConfirmable(?PurchaseOrder $record): bool
    {
        return $record && in_array((string) ($record->status ?? ''), ['draft', 'review'], true);
    }

public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('purchases.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('purchases.view')
            );
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Orden de compra')
                    ->description('Editable mientras está en borrador. Los productos se editan en la tabla inferior.')
                    ->schema([
                        Forms\Components\ViewField::make('purchase_order_status_notice')
                            ->label('')
                            ->view('filament.purchases.status-notice')
                            ->viewData(fn (?PurchaseOrder $record): array => [
                                'documentType' => 'purchase_order',
                                'documentId' => $record?->getKey(),
                            ])
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('number')
                            ->label('Folio')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('supplier_name')
                            ->label('Proveedor')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('status')
                            ->label('Estado')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'draft' => 'Borrador',
                                'review' => 'Pendiente de revisión',
                                'confirmed' => 'Confirmada',
                                'partially_received' => 'Parcialmente recibida',
                                'partial_received' => 'Parcialmente recibida',
                                'received' => 'Recibida',
                                'partial_invoiced' => 'Facturada parcial',
                                'invoiced' => 'Facturada',
                                'cancelled' => 'Cancelada',
                                default => ucfirst((string) $state),
                            })
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\DateTimePicker::make('order_date')
                            ->label('Fecha de orden'),

                        Forms\Components\DateTimePicker::make('expected_date')
                            ->label('Entrega esperada'),

                        Forms\Components\TextInput::make('origin')
                            ->label('Origen')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('warehouse_label')
                            ->label('Almacén destino')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('location_label')
                            ->label('Ubicación / recepción')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas / términos')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Productos')
                    ->description('Agrega, elimina o edita productos mientras la orden esté en borrador.')
                    ->schema([
                        Forms\Components\ViewField::make('purchase_order_lines_editor')
                            ->label(false)
                            ->view('filament.purchase-orders.lines-inline-field')
                            ->viewData(fn (?PurchaseOrder $record): array => [
                                'purchaseOrderId' => $record?->getKey(),
                            ])
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Historial de orden de compra')
                    ->description('Registra creación, edición de productos, confirmaciones, envíos a aprobación y cambios de estado.')
                    ->schema([
                        Forms\Components\ViewField::make('purchase_order_history')
                            ->label(false)
                            ->view('filament.purchase-orders.history-field')
                            ->viewData(fn (?PurchaseOrder $record): array => [
                                'purchaseOrderId' => $record?->getKey(),
                            ])
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }


    public static function purchaseOrderStatusLabel(?string $state): string
    {
        return match ((string) $state) {
            'draft' => 'Borrador',
            'review' => 'Pendiente de revisión',
            'pending_review' => 'Pendiente de revisión',
            'pending_approval' => 'Pendiente de aprobación',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'confirmed' => 'Confirmada',
            'partially_received' => 'Parcialmente recibida',
            'partial_received' => 'Parcialmente recibida',
            'received' => 'Recibida',
            'partial_invoiced' => 'Facturada parcial',
            'invoiced' => 'Facturada',
            'cancelled' => 'Cancelada',
            'canceled' => 'Cancelada',
            default => $state ? ucfirst(str_replace('_', ' ', (string) $state)) : 'Sin estado',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn ($state): string => self::purchaseOrderStatusLabel((string) $state))
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'review',
                        'success' => 'confirmed',
                        'info' => ['partial_received', 'partially_received'],
                        'primary' => 'received',
                        'danger' => 'cancelled',
                    ]),

                Tables\Columns\IconColumn::make('differs_from_request')
                    ->label('Modificada')
                    ->boolean(),

                Tables\Columns\TextColumn::make('supplier_name')
                    ->label('Proveedor')
                    ->searchable(),

                Tables\Columns\TextColumn::make('origin')
                    ->label('Origen')
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_with_tax')
                    ->label('Total')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('order_date')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\Action::make('duplicate_purchase_order')
                    ->label('Duplicar')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    
                    
                    
                    
                    ->visible(fn (): bool => false),

                Tables\Actions\Action::make('confirm_order')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record): bool => in_array((string) ($record->status ?? ''), ['draft', 'borrador'], true))
                    ->url(fn ($record): string => url('/purchases/orders/' . $record->getKey() . '/confirm')),

                Tables\Actions\Action::make('view_direct')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn ($record): string => url('/admin/' . (int) ($record->company_id ?? 0) . '/purchase-orders/' . $record->getKey())),

            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'receive' => Pages\ReceivePurchaseOrder::route('/{record}/receive'),
            'view' => Pages\ViewPurchaseOrder::route('/{record}'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
