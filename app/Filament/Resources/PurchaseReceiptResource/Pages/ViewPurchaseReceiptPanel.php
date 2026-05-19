<?php

namespace App\Filament\Resources\PurchaseReceiptResource\Pages;

use App\Filament\Resources\PurchaseReceiptResource;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ViewPurchaseReceiptPanel extends Page
{
    protected static string $resource = PurchaseReceiptResource::class;

    protected static string $view = 'filament.resources.purchase-receipt-resource.pages.view-purchase-receipt-panel';

    public mixed $record = null;

    public int $receiptId = 0;

    public function mount(mixed $record): void
    {
        $this->receiptId = $this->recordIdFromRouteValue($record);

        $model = PurchaseReceiptResource::getModel();

        $this->record = $model::query()
            ->whereKey($this->receiptId)
            ->firstOrFail();
    }

    public function getTitle(): string
    {
        return 'Recepción de compra';
    }

    public function getHeading(): string
    {
        $receipt = $this->receipt();

        return 'Recepción de compra ' . ($receipt->number ?? ('#' . $this->receiptId));
    }

    public function receipt(): object
    {
        $receipt = DB::table('purchase_receipts')
            ->where('id', $this->receiptId)
            ->first();

        if (! $receipt) {
            abort(404, 'No se encontró la recepción.');
        }

        return $receipt;
    }

    public function getReceiptRow(): ?object
    {
        return $this->receipt();
    }

    public function order(): ?object
    {
        $receipt = $this->receipt();

        if (empty($receipt->purchase_order_id)) {
            return null;
        }

        return DB::table('purchase_orders')
            ->where('id', $receipt->purchase_order_id)
            ->first();
    }

    public function getOrderRow(): ?object
    {
        return $this->order();
    }

    public function movement(): ?object
    {
        $receipt = $this->receipt();

        if (empty($receipt->stock_movement_id)) {
            return null;
        }

        return DB::table('stock_movements')
            ->where('id', $receipt->stock_movement_id)
            ->first();
    }

    public function warehouse(): ?object
    {
        $receipt = $this->receipt();

        if (empty($receipt->warehouse_id)) {
            return null;
        }

        return DB::table('warehouses')
            ->where('id', $receipt->warehouse_id)
            ->first();
    }

    public function location(): ?object
    {
        $receipt = $this->receipt();

        if (empty($receipt->location_id)) {
            return null;
        }

        return DB::table('stock_locations')
            ->where('id', $receipt->location_id)
            ->first();
    }

    public function receivedBy(): ?object
    {
        $receipt = $this->receipt();

        if (empty($receipt->received_by_user_id)) {
            return null;
        }

        return DB::table('users')
            ->where('id', $receipt->received_by_user_id)
            ->first();
    }

    public function lines(): Collection
    {
        return DB::table('purchase_receipt_lines')
            ->where('purchase_receipt_id', $this->receiptId)
            ->orderBy('id')
            ->get();
    }

    public function getLinesForReceipt(): Collection
    {
        return $this->lines();
    }

    public function ocUrl(): string
    {
        $receipt = $this->receipt();
        $order = $this->order();

        if (! $order) {
            return '#';
        }

        return url('/admin/' . $this->tenantId($receipt) . '/purchase-orders/' . $order->id . '/edit');
    }

    public function movementUrl(): string
    {
        $receipt = $this->receipt();

        if (empty($receipt->stock_movement_id)) {
            return '#';
        }

        return url('/admin/' . $this->tenantId($receipt) . '/stock-movements');
    }

    public function pdfUrl(): string
    {
        return $this->printUrl();
    }

    public function printUrl(): string
    {
        $receipt = $this->receipt();

        return url('/admin/' . $this->tenantId($receipt) . '/purchase-receipts/' . $this->receiptId . '/pdf');
    }

    protected function tenantId(object $receipt): int
    {
        $tenant = request()->route('tenant');

        if (is_numeric($tenant)) {
            return (int) $tenant;
        }

        if (is_object($tenant) && isset($tenant->id)) {
            return (int) $tenant->id;
        }

        if (is_object($tenant) && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        if ((int) ($receipt->company_id ?? 0) > 0) {
            return (int) $receipt->company_id;
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

        abort(404, 'No se pudo identificar la recepción.');
    }
}
