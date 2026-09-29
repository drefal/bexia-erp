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
                    'Clasifica lo que físicamente llegó. '
                    . 'Recibido entra a existencias; dañado va a '
                    . 'cuarentena; faltante queda en investigación. '
                    . 'Lo no clasificado continúa en tránsito.'
                )
                ->modalWidth('7xl')
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

                    $resolved = [];

                    if ($lineIds !== []) {
                        $resolved =
                            \App\Models\StockMovementReceiptLine::query()
                                ->selectRaw(
                                    'stock_movement_line_id, '
                                    . 'SUM(quantity) AS resolved_quantity'
                                )
                                ->whereIn(
                                    'stock_movement_line_id',
                                    $lineIds
                                )
                                ->groupBy('stock_movement_line_id')
                                ->pluck(
                                    'resolved_quantity',
                                    'stock_movement_line_id'
                                )
                                ->map(
                                    fn ($qty): float => (float) $qty
                                )
                                ->all();
                    }

                    $schema = [];

                    foreach ($movement->lines as $line) {
                        $sent = round(
                            (float) $line->done_quantity,
                            6
                        );

                        $resolvedQty = round(
                            (float) (
                                $resolved[(int) $line->id] ?? 0
                            ),
                            6
                        );

                        $pending = round(
                            max(0, $sent - $resolvedQty),
                            6
                        );

                        $product = \App\Models\Product::query()
                            ->whereKey($line->product_id)
                            ->first();

                        $productLabel = $product?->name
                            ?: ('Producto #' . $line->product_id);

                        $sku = $product?->internal_reference;

                        $sectionTitle = trim(
                            ($sku ? $sku . ' - ' : '')
                            . $productLabel
                        );

                        if ($line->stock_serial_number_id) {
                            $serial = \App\Models\StockSerialNumber::query()
                                ->whereKey(
                                    $line->stock_serial_number_id
                                )
                                ->value('serial_number');

                            $fields = [
                                Forms\Components\TextInput::make(
                                    'display_sent_' . $line->id
                                )
                                    ->label('Enviado')
                                    ->default($sent)
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make(
                                    'display_resolved_' . $line->id
                                )
                                    ->label('Resuelto anteriormente')
                                    ->default($resolvedQty)
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
                                    'display_serial_' . $line->id
                                )
                                    ->label('Número de serie')
                                    ->default(
                                        $serial
                                            ?: (
                                                'Serie #'
                                                . $line
                                                    ->stock_serial_number_id
                                            )
                                    )
                                    ->disabled()
                                    ->dehydrated(false),
                            ];

                            if ($pending > 0.000001) {
                                $fields[] =
                                    Forms\Components\Select::make(
                                        'serial_disposition_'
                                        . $line->id
                                    )
                                        ->label('Resultado de la serie')
                                        ->options([
                                            'pending' =>
                                                'Pendiente / no llegó aún',
                                            'received' =>
                                                'Recibida correctamente',
                                            'damaged' =>
                                                'Llegó dañada',
                                            'missing' =>
                                                'Faltante',
                                        ])
                                        ->placeholder(
                                            'Selecciona el resultado'
                                        )
                                        ->required();

                                $fields[] =
                                    Forms\Components\Textarea::make(
                                        'incident_reason_'
                                        . $line->id
                                    )
                                        ->label(
                                            'Motivo si hay incidencia'
                                        )
                                        ->placeholder(
                                            'Obligatorio si seleccionas '
                                            . 'dañada o faltante.'
                                        )
                                        ->rows(2)
                                        ->columnSpanFull();
                            }

                            $schema[] =
                                Forms\Components\Section::make(
                                    $sectionTitle
                                )
                                    ->description(
                                        'Producto seriado: cada número '
                                        . 'de serie se clasifica '
                                        . 'individualmente.'
                                    )
                                    ->compact()
                                    ->columns(5)
                                    ->schema($fields);

                            continue;
                        }

                        $schema[] =
                            Forms\Components\Section::make(
                                $sectionTitle
                            )
                                ->compact()
                                ->columns(6)
                                ->schema([
                                    Forms\Components\TextInput::make(
                                        'display_sent_' . $line->id
                                    )
                                        ->label('Enviado')
                                        ->default($sent)
                                        ->disabled()
                                        ->dehydrated(false),

                                    Forms\Components\TextInput::make(
                                        'display_resolved_' . $line->id
                                    )
                                        ->label('Resuelto anteriormente')
                                        ->default($resolvedQty)
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
                                        'receive_good_' . $line->id
                                    )
                                        ->label('Recibido bien')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue($pending)
                                        ->step(0.000001)
                                        ->default($pending)
                                        ->required(),

                                    Forms\Components\TextInput::make(
                                        'receive_damaged_' . $line->id
                                    )
                                        ->label('Dañado')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue($pending)
                                        ->step(0.000001)
                                        ->default(0)
                                        ->required(),

                                    Forms\Components\TextInput::make(
                                        'receive_missing_' . $line->id
                                    )
                                        ->label('Faltante')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue($pending)
                                        ->step(0.000001)
                                        ->default(0)
                                        ->required(),

                                    Forms\Components\Textarea::make(
                                        'incident_reason_'
                                        . $line->id
                                    )
                                        ->label(
                                            'Motivo de daño / faltante'
                                        )
                                        ->placeholder(
                                            'Obligatorio cuando exista '
                                            . 'cantidad dañada o faltante.'
                                        )
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ]);
                    }

                    $schema[] =
                        Forms\Components\Textarea::make(
                            'receipt_notes'
                        )
                            ->label('Notas generales de esta recepción')
                            ->placeholder(
                                'Observaciones generales de la recepción.'
                            )
                            ->rows(3)
                            ->columnSpanFull();

                    return $schema;
                })
                ->action(function (array $data): void {
                    /** @var StockMovement $movement */
                    $movement = $this->record;

                    $movement->load('lines');

                    $items = [];

                    foreach ($movement->lines as $line) {
                        $reason = trim(
                            (string) (
                                $data[
                                    'incident_reason_' . $line->id
                                ] ?? ''
                            )
                        );

                        if ($line->stock_serial_number_id) {
                            $disposition = (string) (
                                $data[
                                    'serial_disposition_' . $line->id
                                ] ?? 'pending'
                            );

                            $items[(int) $line->id] = [
                                'received' =>
                                    $disposition === 'received'
                                        ? 1
                                        : 0,
                                'damaged' =>
                                    $disposition === 'damaged'
                                        ? 1
                                        : 0,
                                'missing' =>
                                    $disposition === 'missing'
                                        ? 1
                                        : 0,
                                'reason' => $reason,
                            ];

                            continue;
                        }

                        $items[(int) $line->id] = [
                            'received' => (float) (
                                $data[
                                    'receive_good_' . $line->id
                                ] ?? 0
                            ),
                            'damaged' => (float) (
                                $data[
                                    'receive_damaged_' . $line->id
                                ] ?? 0
                            ),
                            'missing' => (float) (
                                $data[
                                    'receive_missing_' . $line->id
                                ] ?? 0
                            ),
                            'reason' => $reason,
                        ];
                    }

                    $receipt =
                        StockMovementResource::
                            receiveMovementWithIncidents(
                                $movement,
                                $items,
                                $data['receipt_notes'] ?? null
                            );

                    $movement->refresh();

                    $incidentCount =
                        \App\Models\StockMovementReceiptLine::query()
                            ->where(
                                'stock_movement_receipt_id',
                                $receipt->id
                            )
                            ->whereIn(
                                'disposition',
                                ['damaged', 'missing', 'rejected']
                            )
                            ->count();

                    if ($incidentCount > 0) {
                        Notification::make()
                            ->title(
                                $movement->status === 'done'
                                    ? 'Traslado recibido con incidencias'
                                    : 'Recepción parcial con incidencias'
                            )
                            ->body(
                                $incidentCount
                                . ' incidencia(s) quedaron abiertas '
                                . 'para seguimiento.'
                            )
                            ->warning()
                            ->send();
                    } else {
                        Notification::make()
                            ->title(
                                $movement->status === 'done'
                                    ? 'Traslado recibido completamente'
                                    : 'Recepción parcial registrada'
                            )
                            ->body(
                                $movement->status === 'done'
                                    ? 'Toda la mercancía fue recibida '
                                        . 'correctamente.'
                                    : 'Lo pendiente continúa en tránsito.'
                            )
                            ->success()
                            ->send();
                    }

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

            Actions\Action::make('resolve_incident')
                ->label('Resolver incidencia')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->modalHeading('Resolver incidencia de recepción')
                ->modalDescription(
                    'Selecciona una incidencia pendiente y define cómo se resolvió. '
                    . 'La resolución moverá físicamente la existencia o confirmará '
                    . 'la pérdida según corresponda.'
                )
                ->modalSubmitActionLabel('Resolver incidencia')
                ->modalWidth('2xl')
                ->visible(function (): bool {
                    if (! $this->record instanceof StockMovement) {
                        return false;
                    }

                    return \Illuminate\Support\Facades\DB::table(
                        'stock_movement_incidents'
                    )
                        ->where(
                            'company_id',
                            (int) $this->record->company_id
                        )
                        ->where(
                            'stock_movement_id',
                            (int) $this->record->id
                        )
                        ->where('status', 'open')
                        ->exists();
                })
                ->form(function (): array {
                    /** @var StockMovement $movement */
                    $movement = $this->record;

                    $incidents = \Illuminate\Support\Facades\DB::table(
                        'stock_movement_incidents as i'
                    )
                        ->leftJoin(
                            'stock_movement_lines as sml',
                            'sml.id',
                            '=',
                            'i.stock_movement_line_id'
                        )
                        ->leftJoin(
                            'products as p',
                            'p.id',
                            '=',
                            'sml.product_id'
                        )
                        ->where(
                            'i.company_id',
                            (int) $movement->company_id
                        )
                        ->where(
                            'i.stock_movement_id',
                            (int) $movement->id
                        )
                        ->where('i.status', 'open')
                        ->orderBy('i.id')
                        ->get([
                            'i.id',
                            'i.incident_type',
                            'i.quantity',
                            'i.reason',
                            'sml.product_id',
                            'p.name as product_name',
                            'p.internal_reference',
                        ]);

                    $incidentOptions = [];

                    foreach ($incidents as $incident) {
                        $type = match (
                            (string) $incident->incident_type
                        ) {
                            'damaged' => 'Dañado',
                            'missing' => 'Faltante',
                            default => (string) $incident->incident_type,
                        };

                        $product = trim(
                            (string) (
                                $incident->product_name
                                ?: (
                                    'Producto #'
                                    . (string) $incident->product_id
                                )
                            )
                        );

                        $sku = trim(
                            (string) (
                                $incident->internal_reference ?? ''
                            )
                        );

                        $label = '#'
                            . (int) $incident->id
                            . ' · '
                            . $type
                            . ' · '
                            . $product;

                        if ($sku !== '') {
                            $label .= ' [' . $sku . ']';
                        }

                        $label .= ' · Cant. '
                            . number_format(
                                (float) $incident->quantity,
                                2
                            );

                        if (
                            trim(
                                (string) ($incident->reason ?? '')
                            ) !== ''
                        ) {
                            $label .= ' · '
                                . trim(
                                    (string) $incident->reason
                                );
                        }

                        $incidentOptions[
                            (string) $incident->id
                        ] = $label;
                    }

                    return [
                        Forms\Components\Select::make('incident_id')
                            ->label('Incidencia pendiente')
                            ->options($incidentOptions)
                            ->required()
                            ->live()
                            ->searchable()
                            ->native(false),

                        Forms\Components\Select::make(
                            'resolution_type'
                        )
                            ->label('Resolución')
                            ->options(function (
                                Forms\Get $get
                            ) use ($incidents): array {
                                $incidentId = (int) (
                                    $get('incident_id') ?? 0
                                );

                                if ($incidentId <= 0) {
                                    return [];
                                }

                                $incident = $incidents->first(
                                    fn ($row): bool =>
                                        (int) $row->id
                                        === $incidentId
                                );

                                if (! $incident) {
                                    return [];
                                }

                                return match (
                                    (string) $incident->incident_type
                                ) {
                                    'damaged' => [
                                        'recovered' =>
                                            'Recuperado y liberado a existencias',
                                        'loss' =>
                                            'Confirmar pérdida / baja',
                                    ],
                                    'missing' => [
                                        'found_received' =>
                                            'Encontrado y recibido en destino',
                                        'returned_origin' =>
                                            'Devuelto al origen',
                                        'confirmed_loss' =>
                                            'Confirmar pérdida / baja',
                                    ],
                                    default => [],
                                };
                            })
                            ->required()
                            ->native(false),

                        Forms\Components\Textarea::make(
                            'resolution_notes'
                        )
                            ->label('Notas de resolución')
                            ->rows(4)
                            ->maxLength(1000)
                            ->helperText(
                                'Describe cómo se comprobó o resolvió '
                                . 'la incidencia.'
                            ),
                    ];
                })
                ->action(function (array $data): void {
                    /** @var StockMovement $movement */
                    $movement = $this->record;

                    $incidentId = (int) (
                        $data['incident_id'] ?? 0
                    );

                    $resolutionType = trim(
                        (string) (
                            $data['resolution_type'] ?? ''
                        )
                    );

                    $notes = trim(
                        (string) (
                            $data['resolution_notes'] ?? ''
                        )
                    );

                    if (
                        $incidentId <= 0
                        || $resolutionType === ''
                    ) {
                        Notification::make()
                            ->title(
                                'Selecciona una incidencia y su resolución'
                            )
                            ->danger()
                            ->send();

                        return;
                    }

                    /*
                     * Defensa UI adicional.
                     *
                     * El motor resolveTransferIncident() vuelve a validar
                     * company_id, estado abierto, movimiento y resolución
                     * dentro de transacción. Aquí evitamos incluso enviar
                     * a ese motor un ID que no pertenezca al traslado
                     * actualmente abierto.
                     */
                    $belongsToMovement =
                        \Illuminate\Support\Facades\DB::table(
                            'stock_movement_incidents'
                        )
                            ->where('id', $incidentId)
                            ->where(
                                'company_id',
                                (int) $movement->company_id
                            )
                            ->where(
                                'stock_movement_id',
                                (int) $movement->id
                            )
                            ->where('status', 'open')
                            ->exists();

                    if (! $belongsToMovement) {
                        Notification::make()
                            ->title(
                                'La incidencia ya no está disponible'
                            )
                            ->body(
                                'Actualiza el traslado e inténtalo '
                                . 'nuevamente.'
                            )
                            ->danger()
                            ->send();

                        return;
                    }

                    $resolutionMovementId =
                        StockMovementResource::
                            resolveTransferIncident(
                                $incidentId,
                                $resolutionType,
                                $notes !== '' ? $notes : null
                            );

                    Notification::make()
                        ->title('Incidencia resuelta')
                        ->body(
                            'Se generó el movimiento de resolución #'
                            . $resolutionMovementId
                            . '.'
                        )
                        ->success()
                        ->send();

                    $this->redirect(
                        $this->getResource()::getUrl(
                            'edit',
                            ['record' => $movement]
                        )
                    );
                }),

            Actions\Action::make('cancel_transfer')
                ->label('Cancelar traslado')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancelar traslado')
                ->modalDescription(
                    'Si el traslado ya fue despachado y no tiene recepciones, '
                    . 'las existencias en tránsito regresarán al origen.'
                )
                ->form([
                    \Filament\Forms\Components\Textarea::make(
                        'reason'
                    )
                        ->label('Motivo')
                        ->required()
                        ->maxLength(500),
                ])
                ->visible(fn (): bool =>
                    $this->record instanceof StockMovement
                    && in_array(
                        $this->record->status,
                        ['draft', 'in_transit'],
                        true
                    )
                )
                ->action(function (array $data): void {
                    StockMovementResource::cancelTransfer(
                        $this->record,
                        (string) ($data['reason'] ?? '')
                    );

                    Notification::make()
                        ->title('Traslado cancelado')
                        ->success()
                        ->send();

                    $this->redirect(
                        $this->getResource()::getUrl('index')
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
