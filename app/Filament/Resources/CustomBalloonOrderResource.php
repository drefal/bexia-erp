<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomBalloonOrderResource\Pages;
use App\Models\CustomBalloonOrder;
use App\Support\CustomBalloonsAccess;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomBalloonOrderResource extends Resource
{
    protected static ?string $model = CustomBalloonOrder::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Globos personalizados';

    protected static ?string $navigationLabel = 'Pedidos';

    protected static ?string $modelLabel = 'pedido de globos';

    protected static ?string $pluralModelLabel = 'pedidos de globos';

    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', 3);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return CustomBalloonsAccess::can(
            'custom_balloons.view'
        );
    }

    public static function canView(Model $record): bool
    {
        return (int) $record->company_id === 3
            && static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return CustomBalloonsAccess::can(
            'custom_balloons.create'
        );
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->recordUrl(
                fn (CustomBalloonOrder $record): string =>
                    static::getUrl('view', [
                        'record' => $record->getKey(),
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'customer_name_snapshot'
                )
                    ->label('Cliente')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make(
                    'customer_phone_snapshot'
                )
                    ->label('Teléfono'),

                Tables\Columns\TextColumn::make('promised_at')
                    ->label('Entrega solicitada')
                    ->dateTime(
                        'd/m/Y H:i',
                        'America/Mexico_City'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'balance_amount'
                )
                    ->label('Saldo')
                    ->money('MXN'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'captured' => 'Capturado',
                            'sent_to_pos' => 'Enviado a PDV',
                            'pending_pos' => 'Pendiente en PDV',
                            'paid' => 'Pagado',
                            'cancelled' => 'Cancelado',
                            default => (string) ($state ?? ''),
                        }
                    )
                    ->badge(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Capturado el')
                    ->dateTime(
                        'd/m/Y H:i',
                        'America/Mexico_City'
                    )
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('abrir')
                    ->label('Abrir pedido')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(
                        fn (CustomBalloonOrder $record): string =>
                            static::getUrl('view', [
                                'record' => $record->getKey(),
                            ])
                    )
                    ->visible(
                        fn (CustomBalloonOrder $record): bool =>
                            static::canView($record)
                    ),
            ])
            ->emptyStateHeading('No hay pedidos de Globos')
            ->emptyStateDescription(
                'Utiliza Nuevo pedido para registrar el primero.'
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomBalloonOrders::route('/'),
            'view' => Pages\ViewCustomBalloonOrder::route('/{record}'),
        ];
    }
}
