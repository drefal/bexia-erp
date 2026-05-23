<?php

namespace App\Filament\Resources\StockSerialNumberResource\Pages;

use App\Models\StockSerialNumber;
use Filament\Actions;
use Filament\Forms;

use App\Filament\Resources\StockSerialNumberResource;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ViewStockSerialNumber extends Page
{
    protected static string $resource = StockSerialNumberResource::class;

    protected static string $view = 'filament.resources.stock-serial-number-resource.pages.view-stock-serial-number';

    public int $serialId = 0;

    public function mount(mixed $record): void
    {
        $this->serialId = $this->recordIdFromRouteValue($record);

        if (! $this->serial()) {
            abort(404, 'No se encontró el número de serie.');
        }
    }

    public function getTitle(): string
    {
        return 'Detalle de número de serie';
    }

    public function getHeading(): string
    {
        $serial = $this->serial();

        return 'Número de serie ' . ($serial->serial_number ?? ('#' . $this->serialId));
    }

    public function serial(): ?object
    {
        if (! Schema::hasTable('stock_serial_numbers')) {
            return null;
        }

        return DB::table('stock_serial_numbers')->where('id', $this->serialId)->first();
    }

    public function product(): ?object
    {
        $serial = $this->serial();

        if (! $serial || empty($serial->product_id) || ! Schema::hasTable('products')) {
            return null;
        }

        return DB::table('products')->where('id', $serial->product_id)->first();
    }

    public function variant(): ?object
    {
        $serial = $this->serial();

        if (! $serial || empty($serial->product_variant_id)) {
            return null;
        }

        if (Schema::hasTable('product_variants')) {
            $variant = DB::table('product_variants')->where('id', $serial->product_variant_id)->first();

            if ($variant) {
                return $variant;
            }
        }

        if (Schema::hasTable('products')) {
            return DB::table('products')->where('id', $serial->product_variant_id)->first();
        }

        return null;
    }

    public function lot(): ?object
    {
        $serial = $this->serial();

        if (! $serial || ! Schema::hasTable('stock_lots')) {
            return null;
        }

        if (property_exists($serial, 'lot_id') && ! empty($serial->lot_id)) {
            return DB::table('stock_lots')->where('id', $serial->lot_id)->first();
        }

        if (property_exists($serial, 'lot_number') && ! empty($serial->lot_number) && Schema::hasColumn('stock_lots', 'lot_number')) {
            return DB::table('stock_lots')->where('lot_number', $serial->lot_number)->first();
        }

        return null;
    }

    public function receipt(): ?object
    {
        $serial = $this->serial();

        if (! $serial || empty($serial->purchase_receipt_id) || ! Schema::hasTable('purchase_receipts')) {
            return null;
        }

        return DB::table('purchase_receipts')->where('id', $serial->purchase_receipt_id)->first();
    }

    public function receiptLine(): ?object
    {
        $serial = $this->serial();

        if (! $serial || ! Schema::hasTable('purchase_receipt_lines')) {
            return null;
        }

        if (property_exists($serial, 'purchase_receipt_line_id') && ! empty($serial->purchase_receipt_line_id)) {
            return DB::table('purchase_receipt_lines')->where('id', $serial->purchase_receipt_line_id)->first();
        }

        if (! empty($serial->purchase_receipt_id) && ! empty($serial->serial_number)) {
            return DB::table('purchase_receipt_lines')
                ->where('purchase_receipt_id', $serial->purchase_receipt_id)
                ->where(function ($query) use ($serial): void {
                    $query
                        ->where('serial_numbers', 'like', '%' . $serial->serial_number . '%')
                        ->orWhere('serial_import_rows', 'like', '%' . $serial->serial_number . '%');
                })
                ->first();
        }

        return null;
    }

    public function movement(): ?object
    {
        $receipt = $this->receipt();

        if (! $receipt || empty($receipt->stock_movement_id) || ! Schema::hasTable('stock_movements')) {
            return null;
        }

        return DB::table('stock_movements')->where('id', $receipt->stock_movement_id)->first();
    }

    public function warehouse(): ?object
    {
        $serial = $this->serial();

        if (! $serial || ! Schema::hasTable('warehouses')) {
            return null;
        }

        $warehouseId = $serial->current_warehouse_id ?? $serial->warehouse_id ?? null;

        if (! $warehouseId) {
            return null;
        }

        return DB::table('warehouses')->where('id', $warehouseId)->first();
    }

    public function location(): ?object
    {
        $serial = $this->serial();

        if (! $serial || ! Schema::hasTable('stock_locations')) {
            return null;
        }

        $locationId = $serial->current_location_id ?? $serial->location_id ?? null;

        if (! $locationId) {
            return null;
        }

        return DB::table('stock_locations')->where('id', $locationId)->first();
    }

    public function posOrderLine(): ?object
    {
        $serial = $this->serial();

        if (! $serial || ! Schema::hasTable('pos_order_lines')) {
            return null;
        }

        if (($serial->out_source_line_type ?? null) === 'pos_order_line' && ! empty($serial->out_source_line_id)) {
            $line = DB::table('pos_order_lines')->where('id', $serial->out_source_line_id)->first();

            if ($line) {
                return $line;
            }
        }

        return DB::table('pos_order_lines')
            ->where('stock_serial_number_id', $serial->id)
            ->orderByDesc('id')
            ->first();
    }

    public function posOrder(): ?object
    {
        $serial = $this->serial();

        if (! $serial || ! Schema::hasTable('pos_orders')) {
            return null;
        }

        if (($serial->out_source_type ?? null) === 'pos_order' && ! empty($serial->out_source_id)) {
            $order = DB::table('pos_orders')->where('id', $serial->out_source_id)->first();

            if ($order) {
                return $order;
            }
        }

        $line = $this->posOrderLine();

        if ($line && ! empty($line->pos_order_id)) {
            return DB::table('pos_orders')->where('id', $line->pos_order_id)->first();
        }

        return null;
    }

    public function outboundMovementLine(): ?object
    {
        $serial = $this->serial();

        if (! $serial || ! Schema::hasTable('stock_movement_lines')) {
            return null;
        }

        if (! empty($serial->out_stock_movement_line_id)) {
            $line = DB::table('stock_movement_lines')->where('id', $serial->out_stock_movement_line_id)->first();

            if ($line) {
                return $line;
            }
        }

        return DB::table('stock_movement_lines')
            ->where('stock_serial_number_id', $serial->id)
            ->orderByDesc('id')
            ->first();
    }

    public function outboundMovement(): ?object
    {
        $line = $this->outboundMovementLine();

        if (! $line || empty($line->stock_movement_id) || ! Schema::hasTable('stock_movements')) {
            return null;
        }

        return DB::table('stock_movements')->where('id', $line->stock_movement_id)->first();
    }

    public function movementHistory()
    {
        $serial = $this->serial();

        if (! $serial || ! Schema::hasTable('stock_movement_lines') || ! Schema::hasTable('stock_movements')) {
            return collect();
        }

        return DB::table('stock_movement_lines as l')
            ->leftJoin('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
            ->select([
                'l.*',
                'm.reference as movement_reference',
                'm.status as movement_status',
                'm.movement_at as movement_at',
                'm.origin_document as origin_document',
            ])
            ->where('l.stock_serial_number_id', $serial->id)
            ->orderByDesc('l.id')
            ->limit(30)
            ->get();
    }

    public function receiptUrl(): ?string
    {
        $receipt = $this->receipt();

        if (! $receipt) {
            return null;
        }

        return url('/admin/' . $this->tenantId($receipt) . '/purchase-receipts/' . $receipt->id . '/panel');
    }

    public function lotUrl(): ?string
    {
        $lot = $this->lot();
        $serial = $this->serial();

        if (! $lot) {
            return null;
        }

        return url('/admin/' . $this->tenantId($serial) . '/stock-lots/' . $lot->id . '/view');
    }

    public function printUrl(): string
    {
        $serial = $this->serial();

        return url('/admin/' . $this->tenantId($serial) . '/stock-serial-numbers/' . $this->serialId . '/print');
    }

    public function contactLabel(mixed $contactId): string
    {
        if (empty($contactId) || ! Schema::hasTable('contacts')) {
            return '—';
        }

        $contact = DB::table('contacts')->where('id', $contactId)->first();

        if (! $contact) {
            return '#' . $contactId;
        }

        return trim(collect([
            $contact->name ?? null,
            $contact->business_name ?? null,
            $contact->legal_name ?? null,
            $contact->commercial_name ?? null,
            $contact->rfc ?? null,
        ])->filter()->unique()->implode(' - ')) ?: ('#' . $contactId);
    }

    public function posPointLabel(mixed $posPointId): string
    {
        if (empty($posPointId) || ! Schema::hasTable('pos_points')) {
            return '—';
        }

        $posPoint = DB::table('pos_points')->where('id', $posPointId)->first();

        if (! $posPoint) {
            return '#' . $posPointId;
        }

        return ($posPoint->name ?? $posPoint->code ?? $posPoint->number ?? null) ?: ('#' . $posPointId);
    }

    public function userLabel(mixed $userId): string
    {
        if (empty($userId) || ! Schema::hasTable('users')) {
            return '—';
        }

        $user = DB::table('users')->where('id', $userId)->first();

        if (! $user) {
            return '#' . $userId;
        }

        return ($user->name ?? $user->email ?? null) ?: ('#' . $userId);
    }

    public function sourceLabel(mixed $type, mixed $id): string
    {
        if (empty($type) && empty($id)) {
            return '—';
        }

        $labels = [
            'pos_order' => 'Venta PDV',
            'pos_order_line' => 'Línea PDV',
            'sale_delivery' => 'Entrega de venta',
            'sale_delivery_line' => 'Línea entrega',
            'purchase_receipt' => 'Recepción de compra',
            'purchase_receipt_line' => 'Línea recepción',
            'stock_movement' => 'Movimiento inventario',
            'stock_movement_line' => 'Línea movimiento',
        ];

        return ($labels[(string) $type] ?? (string) $type) . (! empty($id) ? ' #' . $id : '');
    }

    public function formatDateTime(mixed $value): string
    {
        if (empty($value)) {
            return '—';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    public function formatNumber(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return is_numeric($value)
            ? rtrim(rtrim(number_format((float) $value, 6, '.', ','), '0'), '.')
            : (string) $value;
    }

    protected function tenantId(?object $row = null): int
    {
        $tenant = request()->route('tenant');

        if (is_numeric($tenant)) {
            return (int) $tenant;
        }

        if (is_object($tenant) && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        if (is_object($tenant) && isset($tenant->id)) {
            return (int) $tenant->id;
        }

        if ($row && property_exists($row, 'company_id') && (int) $row->company_id > 0) {
            return (int) $row->company_id;
        }

        return (int) (auth()->user()?->company_id ?? 0);
    }

    protected function recordIdFromRouteValue(mixed $record): int
    {
        if (is_object($record) && method_exists($record, 'getKey')) {
            return (int) $record->getKey();
        }

        if (is_object($record) && isset($record->id)) {
            return (int) $record->id;
        }

        if (is_array($record) && isset($record['id'])) {
            return (int) $record['id'];
        }

        if (is_numeric($record)) {
            return (int) $record;
        }

        $value = trim((string) $record);

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (str_starts_with($value, '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded) && isset($decoded['id']) && is_numeric($decoded['id'])) {
                return (int) $decoded['id'];
            }
        }

        return 0;
    }
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('correctSerialNumber')
                ->label('Corregir serie')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->modalHeading(fn (): string => 'Corregir número de serie: ' . $this->currentSerialRecord()->serial_number)
                ->modalDescription('Solo se corregirá el texto del número de serie. No cambia estado, ubicación, existencias, ventas, PDV ni entregas.')
                ->modalSubmitActionLabel('Confirmar corrección')
                ->form([
                    Forms\Components\TextInput::make('serial_number_before')
                        ->label('Serie actual')
                        ->default(fn (): ?string => $this->currentSerialRecord()->serial_number)
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('serial_number_after')
                        ->label('Nuevo número de serie')
                        ->required()
                        ->maxLength(160)
                        ->helperText('No debe existir otro registro con este número de serie en la misma empresa.'),

                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo de corrección')
                        ->required()
                        ->rows(3)
                        ->helperText('El motivo quedará guardado en el historial especial.'),

                    Forms\Components\TextInput::make('reference')
                        ->label('Referencia / documento')
                        ->maxLength(160)
                        ->placeholder('Opcional'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Notas')
                        ->rows(2)
                        ->placeholder('Opcional'),
                ])
                ->action(function (array $data): void {
                    \App\Filament\Resources\StockSerialNumberResource::correctSerialNumberRecord($this->currentSerialRecord(), $data);
                    $this->redirect(static::getResource()::getUrl('view', [
                        'record' => $this->currentSerialRecord(),
                    ]));
                }),
        ];
    }


    protected function currentSerialRecord(): StockSerialNumber
    {
        $record = request()->route('record');

        if ($record instanceof StockSerialNumber) {
            return $record;
        }

        if (is_numeric($record)) {
            return StockSerialNumber::query()->findOrFail((int) $record);
        }

        if (property_exists($this, 'record')) {
            $value = $this->record ?? null;

            if ($value instanceof StockSerialNumber) {
                return $value;
            }

            if (is_numeric($value)) {
                return StockSerialNumber::query()->findOrFail((int) $value);
            }
        }

        foreach (['recordId', 'recordKey', 'id'] as $property) {
            if (property_exists($this, $property)) {
                $value = $this->{$property} ?? null;

                if (is_numeric($value)) {
                    return StockSerialNumber::query()->findOrFail((int) $value);
                }
            }
        }

        $referer = (string) request()->headers->get('referer', '');

        if ($referer !== '' && preg_match('~/stock-serial-numbers/([0-9]+)/view~', $referer, $matches)) {
            return StockSerialNumber::query()->findOrFail((int) $matches[1]);
        }

        throw new \RuntimeException('No se pudo resolver el número de serie actual para esta acción.');
    }

}
