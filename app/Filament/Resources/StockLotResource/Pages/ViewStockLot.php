<?php

namespace App\Filament\Resources\StockLotResource\Pages;

use App\Filament\Resources\StockLotResource;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ViewStockLot extends Page
{
    protected static string $resource = StockLotResource::class;

    protected static string $view = 'filament.resources.stock-lot-resource.pages.view-stock-lot';

    public int|string|null $record = null;

    public ?int $lotId = null;

    public function mount(int|string $record): void
    {
        $this->record = $record;
        $this->lotId = (int) $record;
    }

    public function getTitle(): string
    {
        return 'Detalle de lote';
    }

    public function getHeading(): string
    {
        $lot = $this->lot();

        return 'Lote ' . (($lot->lot_number ?? null) ?: ('#' . $this->lotId));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function lot(): ?object
    {
        if (! $this->lotId || ! Schema::hasTable('stock_lots')) {
            return null;
        }

        return DB::table('stock_lots')->where('id', $this->lotId)->first();
    }

    public function saleDeliveryLines()
    {
        $lot = $this->lot();

        if (
            ! $lot
            || ! Schema::hasTable('sale_delivery_lines')
            || ! Schema::hasTable('sale_deliveries')
        ) {
            return collect();
        }

        $query = DB::table('sale_delivery_lines as dl')
            ->join('sale_deliveries as d', 'd.id', '=', 'dl.sale_delivery_id');

        if (Schema::hasTable('sales_orders')) {
            $query->leftJoin('sales_orders as so', 'so.id', '=', 'dl.sales_order_id');
        }

        if (Schema::hasTable('stock_movement_lines')) {
            $query->leftJoin('stock_movement_lines as ml', 'ml.id', '=', 'dl.stock_movement_line_id');
        }

        if (Schema::hasTable('stock_movements')) {
            $query->leftJoin('stock_movements as sm', 'sm.id', '=', 'd.stock_movement_id');
        }

        $query->where(function ($where) use ($lot): void {
            $added = false;

            if (Schema::hasColumn('sale_delivery_lines', 'stock_lot_id')) {
                $where->where('dl.stock_lot_id', $lot->id);
                $added = true;
            }

            if (Schema::hasTable('stock_movement_lines') && Schema::hasColumn('stock_movement_lines', 'lot_id')) {
                $added
                    ? $where->orWhere('ml.lot_id', $lot->id)
                    : $where->where('ml.lot_id', $lot->id);

                $added = true;
            }

            if (Schema::hasColumn('sale_delivery_lines', 'lot_tracking_metadata')) {
                $patterns = [
                    '%"stock_lot_id":' . (int) $lot->id . '%',
                    '%"stock_lot_id": "' . (int) $lot->id . '"%',
                ];

                if (! empty($lot->lot_number)) {
                    $patterns[] = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $lot->lot_number) . '%';
                }

                foreach ($patterns as $pattern) {
                    $added
                        ? $where->orWhereRaw('CAST(dl.lot_tracking_metadata AS TEXT) ILIKE ?', [$pattern])
                        : $where->whereRaw('CAST(dl.lot_tracking_metadata AS TEXT) ILIKE ?', [$pattern]);

                    $added = true;
                }
            }

            if (! $added) {
                $where->whereRaw('1 = 0');
            }
        });

        return $query
            ->select([
                'dl.*',
                'd.number as delivery_number',
                'd.status as delivery_status',
                'd.delivered_at as delivery_delivered_at',
                'd.created_at as delivery_created_at',
                'd.stock_movement_id as delivery_stock_movement_id',
                DB::raw('COALESCE(so.number, NULL) as sale_order_number'),
                DB::raw('COALESCE(so.customer_name, NULL) as customer_name'),
                DB::raw('COALESCE(ml.id, NULL) as movement_line_id'),
                DB::raw('COALESCE(ml.lot_id, NULL) as movement_lot_id'),
                DB::raw('COALESCE(sm.reference, NULL) as movement_reference'),
            ])
            ->orderByDesc('d.id')
            ->orderByDesc('dl.id')
            ->limit(100)
            ->get();
    }

    public function movements()
    {
        $lot = $this->lot();

        if (! $lot || ! Schema::hasTable('stock_movement_lines') || ! Schema::hasTable('stock_movements')) {
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
            ->where('l.lot_id', $lot->id)
            ->orderByDesc('l.id')
            ->limit(50)
            ->get();
    }

    public function serials()
    {
        $lot = $this->lot();

        if (! $lot || ! Schema::hasTable('stock_serial_numbers')) {
            return collect();
        }

        return DB::table('stock_serial_numbers')
            ->where('lot_id', $lot->id)
            ->orderBy('serial_number')
            ->limit(100)
            ->get();
    }

    public function quants()
    {
        $lot = $this->lot();

        if (! $lot || ! Schema::hasTable('stock_quants') || ! Schema::hasColumn('stock_quants', 'lot_id')) {
            return collect();
        }

        return DB::table('stock_quants')
            ->where('lot_id', $lot->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    public function productLabel(mixed $id): string
    {
        if (empty($id) || ! Schema::hasTable('products')) {
            return '—';
        }

        $row = DB::table('products')->where('id', $id)->first();

        if (! $row) {
            return '#' . $id;
        }

        return trim(collect([
            $row->internal_reference ?? null,
            $row->sku ?? null,
            $row->barcode ?? null,
            $row->name ?? null,
        ])->filter()->unique()->implode(' - ')) ?: ('#' . $id);
    }

    public function labelFromTable(string $table, mixed $id, array $fields): string
    {
        if (empty($id) || ! Schema::hasTable($table)) {
            return '—';
        }

        $row = DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '#' . $id;
        }

        foreach ($fields as $field) {
            if (isset($row->{$field}) && $row->{$field} !== '') {
                return (string) $row->{$field};
            }
        }

        return '#' . $id;
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

    public function dt(mixed $value): string
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

    public function d(mixed $value): string
    {
        if (empty($value)) {
            return '—';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    public function n(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return is_numeric($value)
            ? rtrim(rtrim(number_format((float) $value, 6, '.', ','), '0'), '.')
            : (string) $value;
    }
}
