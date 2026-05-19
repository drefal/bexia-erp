<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReceivePurchaseOrder extends Page
{
    protected static string $resource = PurchaseOrderResource::class;

    protected static string $view = 'filament.resources.purchase-order-resource.pages.receive-purchase-order';

    public PurchaseOrder $record;

    public function mount(mixed $record): void
    {
        $recordId = $this->recordIdFromRouteValue($record);

        $this->record = PurchaseOrder::query()->whereKey($recordId)->firstOrFail();

        abort_unless(auth()->check(), 403);

        $order = $this->getOrderRow();

        abort_if(! $this->canReceive($order), 404, 'Esta orden no está lista para recepción o no tiene cantidades pendientes.');
    }

    protected function recordIdFromRouteValue(mixed $record): int
    {
        if ($record instanceof PurchaseOrder) {
            return (int) $record->getKey();
        }

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

        abort(404, 'No se pudo identificar la orden de compra.');
    }

    public function getTitle(): string
    {
        return 'Recibir orden de compra';
    }

    public function getHeading(): string
    {
        return 'Recibir orden de compra';
    }

    public function getSubheading(): ?string
    {
        $order = $this->getOrderRow();

        return 'OC ' . ($order->number ?? ('#' . $order->id)) . '. Captura la cantidad recibida por producto.';
    }

    public function getOrderRow(): object
    {
        return DB::table('purchase_orders')
            ->where('id', $this->record->getKey())
            ->firstOrFail();
    }

    public function getLinesForReceipt(): Collection
    {
        return DB::table('purchase_order_lines')
            ->where('purchase_order_id', $this->record->getKey())
            ->orderBy('id')
            ->get()
            ->map(function ($line) {
                $ordered = (float) ($line->ordered_quantity ?? 0);
                $received = (float) ($line->received_quantity ?? 0);
                $pending = max($ordered - $received, 0);
                $tracking = $this->trackingTypeForLine($line);

                $line->ordered_for_view = $ordered;
                $line->received_for_view = $received;
                $line->pending_for_view = $pending;
                $line->tracking_for_view = $tracking;
                $line->tracking_label_for_view = match ($tracking) {
                    'lot' => 'Lote',
                    'serial' => 'Número de serie',
                    default => 'Sin seguimiento',
                };

                $advancedTracking = $this->advancedTrackingConfigForLine($line);
                $line->advanced_tracking_mode_for_view = $advancedTracking['mode'] ?? 'none';
                $line->advanced_tracking_fields_for_view = $advancedTracking['fields'] ?? [];

                return $line;
            });
    }

    public function postActionUrl(): string
    {
        return route('purchases.orders.receipts.store', [
            'purchaseOrder' => $this->record->getKey(),
        ]);
    }

    public function cancelUrl(): string
    {
        $order = $this->getOrderRow();

        return url('/admin/' . $this->tenantId($order) . '/purchase-orders/' . $order->id . '/edit');
    }



    protected function advancedTrackingConfigForLine(object $line): array
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'advanced_tracking_mode')) {
            return ['mode' => 'none', 'fields' => []];
        }

        $ids = [];

        foreach (['product_id', 'product_variant_id', 'variant_id'] as $field) {
            $id = (int) ($line->{$field} ?? 0);

            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        foreach ($ids as $id) {
            $product = DB::table('products')
                ->where('id', $id)
                ->first(['advanced_tracking_mode', 'advanced_tracking_fields']);

            if (! $product) {
                continue;
            }

            $mode = (string) ($product->advanced_tracking_mode ?? 'none');
            $fields = $this->decodeAdvancedTrackingFields($product->advanced_tracking_fields ?? null);

            if (in_array($mode, ['warning', 'required'], true)) {
                return ['mode' => $mode, 'fields' => $fields];
            }
        }

        return ['mode' => 'none', 'fields' => []];
    }

    protected function decodeAdvancedTrackingFields(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return array_values(array_filter(array_map('strval', $decoded)));
            }

            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        return [];
    }


    protected function trackingTypeForLine(object $line): string
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'tracking')) {
            return 'none';
        }

        $ids = [];

        foreach (['product_variant_id', 'variant_id', 'product_id'] as $field) {
            $id = (int) ($line->{$field} ?? 0);

            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        foreach ($ids as $id) {
            $tracking = DB::table('products')->where('id', $id)->value('tracking');

            if (in_array($tracking, ['lot', 'serial'], true)) {
                return (string) $tracking;
            }
        }

        return 'none';
    }

    protected function canReceive(object $order): bool
    {
        if (! in_array((string) ($order->status ?? ''), ['confirmed', 'partially_received'], true)) {
            return false;
        }

        if (! Schema::hasTable('purchase_order_lines')) {
            return false;
        }

        $lines = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $order->id)
            ->get();

        foreach ($lines as $line) {
            $ordered = (float) ($line->ordered_quantity ?? 0);
            $received = (float) ($line->received_quantity ?? 0);

            if ($ordered - $received > 0.000001) {
                return true;
            }
        }

        return false;
    }

    protected function tenantId(object $order): int
    {
        if ((int) ($order->company_id ?? 0) > 0) {
            return (int) $order->company_id;
        }

        $tenant = request()->route('tenant');

        return is_numeric($tenant)
            ? (int) $tenant
            : (int) (auth()->user()?->company_id ?? 0);
    }
}
