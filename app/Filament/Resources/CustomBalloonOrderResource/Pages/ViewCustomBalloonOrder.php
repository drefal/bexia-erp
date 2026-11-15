<?php

namespace App\Filament\Resources\CustomBalloonOrderResource\Pages;

use App\Filament\Resources\CustomBalloonOrderResource;
use App\Filament\Pages\CustomBalloonCatalogPreview;
use App\Models\CustomBalloonOrder;
use App\Support\CustomBalloons\BalloonOrderEditService;
use App\Support\CustomBalloons\BalloonOrderRules;
use App\Support\CustomBalloonsAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ViewCustomBalloonOrder extends Page
{
    protected static string $resource =
        CustomBalloonOrderResource::class;

    protected static string $view =
        'filament.resources.custom-balloon-orders.view';

    protected static ?string $title = 'Pedido de Globos';

    public ?CustomBalloonOrder $order = null;

    public ?int $customerContactId = null;
    public string $requestedDeliveryAt = '';
    public string $orderNotes = '';
    public string $newCustomerPhone = '';

    public array $lines = [];
    public string $revision = '';

    public function mount(int|string $record): void
    {
        $this->loadOrder((int) $record);
    }

    private function loadOrder(int $id): void
    {
        $order = CustomBalloonOrderResource::getEloquentQuery()
            ->with('lines')
            ->findOrFail($id);

        abort_unless(
            CustomBalloonOrderResource::canView($order),
            403
        );

        $this->order = $order;
        $this->revision = (string) $order->updated_at;
        $this->customerContactId = (int) $order->customer_contact_id;

        $this->requestedDeliveryAt = $order->promised_at
            ? $order->promised_at->copy()
                ->timezone('America/Mexico_City')
                ->format('Y-m-d\TH:i')
            : '';

        $this->orderNotes = (string) ($order->notes ?? '');
        $this->newCustomerPhone = '';

        $this->lines = $order->lines->map(function ($line): array {
            $meta = is_array($line->metadata)
                ? $line->metadata
                : [];

            return [
                'product_id' => (int) ($meta['product_id'] ?? 0),
                'variant_id' => (int) ($meta['variant_id'] ?? 0),
                'model_id' => (int) ($line->model_id ?? 0),
                'quantity' => (int) $line->quantity,
                'personalization_text' =>
                    (string) ($line->personalization_text ?? ''),
                'name' => (string) ($meta['product_name'] ?? 'Producto'),
                'variant_name' => (string) ($line->size_name_snapshot ?? ''),
                'unit_price' => (float) $line->unit_price,
                'subtotal' => (float) $line->subtotal,
            ];
        })->all();
    }

    public function getTitle(): string
    {
        return $this->order
            ? 'Pedido ' . $this->order->number
            : 'Pedido de Globos';
    }

    public function getEditBlockReasonProperty(): ?string
    {
        if (! $this->order) {
            return 'Pedido no disponible.';
        }

        if (! CustomBalloonsAccess::can('custom_balloons.update')) {
            return 'No tienes permiso para modificar pedidos.';
        }

        try {
            BalloonOrderRules::assertEditable(
                (int) $this->order->id,
                3
            );

            if (
                ! $this->order->pos_order_id
                && (string) $this->order->status !== 'captured'
            ) {
                return 'El estado actual no admite cambios.';
            }

            return null;
        } catch (ValidationException $exception) {
            return collect($exception->errors())
                ->flatten()
                ->first() ?: 'Pedido bloqueado.';
        }
    }

    public function getCanModifyProperty(): bool
    {
        return $this->editBlockReason === null;
    }

    public function getCustomersProperty()
    {
        return DB::table('contacts')
            ->where('company_id', 3)
            ->where('is_customer', true)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'mobile']);
    }

    public function getMissingCustomerPhoneProperty(): bool
    {
        if (! $this->customerContactId) {
            return false;
        }

        $contact = DB::table('contacts')
            ->where('id', $this->customerContactId)
            ->where('company_id', 3)
            ->first(['phone', 'mobile']);

        if (! $contact) {
            return false;
        }

        foreach ([$contact->phone, $contact->mobile] as $value) {
            if (strlen(preg_replace('/\D+/', '', (string) $value)) >= 10) {
                return false;
            }
        }

        return true;
    }

    public function updatedCustomerContactId(): void
    {
        $this->newCustomerPhone = '';
    }

    public function getProductsProperty()
    {
        return DB::table('products')
            ->where('company_id', 3)
            ->where('is_variant', false)
            ->where('is_active', true)
            ->where('can_be_sold', true)
            ->where('available_in_custom_balloons', true)
            ->whereIn('product_category_id', function ($query) {
                $query->select('id')
                    ->from('product_categories')
                    ->where('company_id', 3)
                    ->where('code', 'GLB-PERS')
                    ->where('is_active', true);
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function variantsForProduct(int|string|null $id)
    {
        return DB::table('products')
            ->where('company_id', 3)
            ->where('parent_product_id', (int) $id)
            ->where('is_variant', true)
            ->where('is_active', true)
            ->where('can_be_sold', true)
            ->orderBy('variant_value')
            ->get(['id', 'variant_value', 'name']);
    }

    public function getDesignsProperty()
    {
        return DB::table('custom_balloon_models')
            ->where('company_id', 3)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function addLine(): void
    {
        abort_unless($this->canModify, 403);

        $this->lines[] = [
            'product_id' => 0,
            'variant_id' => 0,
            'model_id' => 0,
            'quantity' => 1,
            'personalization_text' => '',
            'name' => '',
            'variant_name' => '',
            'unit_price' => 0,
            'subtotal' => 0,
        ];
    }

    public function removeLine(int $index): void
    {
        abort_unless($this->canModify, 403);

        if (array_key_exists($index, $this->lines)) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
        }
    }

    public function saveChanges(): void
    {
        abort_unless(
            CustomBalloonsAccess::can('custom_balloons.update'),
            403
        );

        if (! $this->canModify) {
            throw ValidationException::withMessages([
                'order' => $this->editBlockReason,
            ]);
        }

        $this->validate([
            'customerContactId' => ['required', 'integer', 'min:1'],
            'requestedDeliveryAt' => ['required', 'string'],
            'orderNotes' => ['nullable', 'string', 'max:2000'],
            'newCustomerPhone' => ['nullable', 'string', 'max:30'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'integer', 'min:1'],
            'lines.*.variant_id' => ['nullable', 'integer'],
            'lines.*.model_id' => ['nullable', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'lines.*.personalization_text' =>
                ['nullable', 'string', 'max:1000'],
        ]);

        $items = array_map(
            fn (array $line): array => [
                'product_id' => (int) $line['product_id'],
                'variant_id' => (int) ($line['variant_id'] ?? 0),
                'model_id' => (int) ($line['model_id'] ?? 0),
                'quantity' => (int) $line['quantity'],
                'personalization_text' =>
                    (string) ($line['personalization_text'] ?? ''),
            ],
            $this->lines
        );

        app(BalloonOrderEditService::class)->update(
            (int) $this->order->id,
            $this->revision,
            (int) $this->customerContactId,
            str_replace('T', ' ', $this->requestedDeliveryAt),
            $items,
            trim($this->orderNotes) ?: null,
            trim($this->newCustomerPhone) ?: null
        );

        $this->loadOrder((int) $this->order->id);

        Notification::make()
            ->title('Pedido actualizado correctamente')
            ->body($this->order->number)
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editar_pedido')
                ->label('Editar pedido')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->visible(
                    fn (): bool =>
                        CustomBalloonsAccess::can(
                            'custom_balloons.update'
                        )
                )
                ->disabled(fn (): bool => ! $this->canModify)
                ->url(
                    fn (): string =>
                        CustomBalloonCatalogPreview::getUrl([
                            'edit' => (int) $this->order->id,
                        ])
                ),


            Action::make('enviar_a_pdv')
                ->label('Enviar a PDV')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(
                    fn (): bool => app(
                        \App\Support\CustomBalloons\BalloonOrderToPosService::class
                    )->canSend()
                )
                ->disabled(
                    fn (): bool =>
(string) $this->order->status !== 'captured'
                )
                ->modalHeading('Enviar pedido pendiente al PDV')
                ->modalDescription(
                    fn (): string =>
                        'Pedido ' . $this->order->number
                        . ' - Total $'
                        . number_format(
                            (float) $this->order->total,
                            2
                        )
                        . '. Selecciona la caja con sesión abierta.'
                )
                ->modalSubmitActionLabel('Confirmar envío')
                ->form([
                    Select::make('pos_point_id')
                        ->label('Punto de venta destino')
                        ->options(
                            fn (): array => app(
                                \App\Support\CustomBalloons\BalloonOrderToPosService::class
                            )->availablePosPoints()
                        )
                        ->placeholder('Selecciona un PDV')
                        ->required()
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    $result = app(
                        \App\Support\CustomBalloons\BalloonOrderToPosService::class
                    )->send(
                        (int) $this->order->id,
                        (int) $data['pos_point_id']
                    );

                    $this->loadOrder((int) $this->order->id);

                    Notification::make()
                        ->title('Ticket pendiente enviado al PDV')
                        ->body(
                            'Ticket '
                            . $result['pos_order_number']
                            . ' - Sesión '
                            . $result['pos_session_id']
                        )
                        ->success()
                        ->send();
                }),

            Action::make('imprimir_pdf')
                ->label('Imprimir PDF')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(
                    fn () => app(
                        \App\Support\CustomBalloons\BalloonOrderPdfService::class
                    )->download(
                        (int) $this->order->id,
                        'single'
                    )
                ),

            Action::make('imprimir_pdf_doble')
                ->label('Imprimir PDF doble')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->action(
                    fn () => app(
                        \App\Support\CustomBalloons\BalloonOrderPdfService::class
                    )->download(
                        (int) $this->order->id,
                        'double'
                    )
                ),

            Action::make('volver')
                ->label('Volver a pedidos')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(
                    fn (): string =>
                        CustomBalloonOrderResource::getUrl('index')
                ),
        ];
    }
}
