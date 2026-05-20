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
