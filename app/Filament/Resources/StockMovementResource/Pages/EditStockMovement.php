<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Resources\StockMovementResource;
use App\Models\StockMovement;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStockMovement extends EditRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn (): string => route('inventory.stock-movements.pdf', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('confirm')
                ->label(fn (): string =>
                    $this->record instanceof StockMovement
                    && $this->record->isInterWarehouseTransfer()
                        ? 'Despachar traslado'
                        : 'Confirmar traslado'
                )
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(fn (): string =>
                    $this->record instanceof StockMovement
                    && $this->record->isInterWarehouseTransfer()
                        ? 'Despachar traslado'
                        : 'Confirmar traslado'
                )
                ->modalDescription(fn (): string =>
                    $this->record instanceof StockMovement
                    && $this->record->isInterWarehouseTransfer()
                        ? 'La mercancía saldrá del almacén origen y quedará en tránsito hasta que el destino confirme la recepción.'
                        : 'El traslado se aplicará inmediatamente entre ubicaciones del mismo almacén.'
                )
                ->modalSubmitActionLabel('Confirmar traslado')
                ->visible(fn (): bool => $this->record instanceof StockMovement && $this->record->status === 'draft')
                ->action(function (): void {
                    StockMovementResource::confirmMovement($this->record);

                    Notification::make()
                        ->title('Traslado confirmado')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\Action::make('receive')
                ->label('Recibir traslado')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('success')
                ->modalHeading('Recibir traslado')
                ->modalDescription(
                    'Captura lo que físicamente llegó al destino. '
                    . 'Lo que no se reciba permanecerá en tránsito.'
                )
                ->modalWidth('5xl')
                ->modalSubmitActionLabel('Confirmar recepción')
                ->visible(fn (): bool =>
                    $this->record instanceof StockMovement
                    && $this->record->status === 'in_transit'
                )
                ->form(function (): array {
                    /** @var StockMovement $movement */
                    $movement = $this->record;

                    $movement->load('lines');

                    $lineIds = $movement->lines
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->all();

                    $received = [];

                    if ($lineIds !== []) {
                        $received = \App\Models\StockMovementReceiptLine::query()
                            ->selectRaw(
                                'stock_movement_line_id, SUM(quantity) AS received_quantity'
                            )
                            ->whereIn('stock_movement_line_id', $lineIds)
                            ->groupBy('stock_movement_line_id')
                            ->pluck(
                                'received_quantity',
                                'stock_movement_line_id'
                            )
                            ->map(fn ($qty): float => (float) $qty)
                            ->all();
                    }

                    $schema = [];

                    foreach ($movement->lines as $line) {
                        $sent = round((float) $line->done_quantity, 6);
                        $receivedQty = round(
                            (float) ($received[(int) $line->id] ?? 0),
                            6
                        );
                        $pending = round(
                            max(0, $sent - $receivedQty),
                            6
                        );

                        $productLabel = \App\Models\Product::query()
                            ->whereKey($line->product_id)
                            ->value('name') ?: ('Producto #' . $line->product_id);

                        $sku = \App\Models\Product::query()
                            ->whereKey($line->product_id)
                            ->value('internal_reference');

                        $schema[] = Forms\Components\Section::make(
                            trim(
                                ($sku ? $sku . ' - ' : '')
                                . $productLabel
                            )
                        )
                            ->compact()
                            ->columns(4)
                            ->schema([
                                Forms\Components\TextInput::make(
                                    'display_sent_' . $line->id
                                )
                                    ->label('Enviado')
                                    ->default($sent)
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make(
                                    'display_received_' . $line->id
                                )
                                    ->label('Recibido anteriormente')
                                    ->default($receivedQty)
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make(
                                    'display_pending_' . $line->id
                                )
                                    ->label('Pendiente')
                                    ->default($pending)
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make(
                                    'receive_' . $line->id
                                )
                                    ->label('Recibir ahora')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue($pending)
                                    ->step(0.000001)
                                    ->default($pending)
                                    ->required(),
                            ]);
                    }

                    $schema[] = Forms\Components\Textarea::make('receipt_notes')
                        ->label('Notas de esta recepción')
                        ->placeholder(
                            'Opcional. Ejemplo: recepción parcial, caja dañada, '
                            . 'pendiente una pieza...'
                        )
                        ->rows(3)
                        ->columnSpanFull();

                    return $schema;
                })
                ->action(function (array $data): void {
                    /** @var StockMovement $movement */
                    $movement = $this->record;

                    $movement->load('lines');

                    $quantities = [];

                    foreach ($movement->lines as $line) {
                        $quantities[(int) $line->id] =
                            (float) ($data['receive_' . $line->id] ?? 0);
                    }

                    $receipt = StockMovementResource::receiveMovement(
                        $movement,
                        $quantities,
                        $data['receipt_notes'] ?? null
                    );

                    $movement->refresh();

                    Notification::make()
                        ->title(
                            $movement->status === 'done'
                                ? 'Traslado recibido completamente'
                                : 'Recepción parcial registrada'
                        )
                        ->body(
                            $movement->status === 'done'
                                ? 'Toda la mercancía del traslado fue recibida.'
                                : 'La mercancía pendiente continúa en tránsito.'
                        )
                        ->success()
                        ->send();

                    if ($movement->status === 'done') {
                        $this->redirect(
                            $this->getResource()::getUrl('index')
                        );

                        return;
                    }

                    $this->redirect(
                        $this->getResource()::getUrl(
                            'edit',
                            ['record' => $movement]
                        )
                    );
                }),

            Actions\DeleteAction::make()
                ->label('Eliminar borrador')
                ->visible(fn (): bool => $this->record instanceof StockMovement && $this->record->status === 'draft'),
        ];
    }

    protected function getFormActions(): array
    {
        if ($this->record instanceof StockMovement && in_array($this->record->status, ['in_transit', 'done', 'cancelled'], true)) {
            return [];
        }

        return parent::getFormActions();
    }

    protected function afterSave(): void
    {
        Notification::make()
            ->title('Traslado guardado')
            ->success()
            ->send();
    }
}
