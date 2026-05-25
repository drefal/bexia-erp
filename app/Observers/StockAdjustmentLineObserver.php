<?php

namespace App\Observers;

use App\Models\StockAdjustmentLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockAdjustmentLineObserver
{
    public function created(StockAdjustmentLine $line): void
    {
        $this->writeAudit(
            $line,
            'line_created',
            'Línea de ajuste creada.',
            null,
            $this->snapshot($line)
        );
    }

    public function updated(StockAdjustmentLine $line): void
    {
        $before = [];
        $after = [];

        foreach ($this->trackedFields() as $field) {
            if ($line->wasChanged($field)) {
                $before[$field] = $line->getOriginal($field);
                $after[$field] = $line->{$field};
            }
        }

        if ($before === [] && $after === []) {
            return;
        }

        $this->writeAudit(
            $line,
            'line_updated',
            'Línea de ajuste actualizada.',
            $before,
            $after
        );
    }

    public function deleted(StockAdjustmentLine $line): void
    {
        $this->writeAudit(
            $line,
            'line_deleted',
            'Línea de ajuste eliminada.',
            $this->snapshot($line),
            null
        );
    }

    protected function trackedFields(): array
    {
        return [
            'product_id',
            'product_variant_id',
            'lot_id',
            'current_quantity',
            'counted_quantity',
            'difference_quantity',
            'unit_cost',
            'notes',
        ];
    }

    protected function snapshot(StockAdjustmentLine $line): array
    {
        return collect($this->trackedFields())
            ->mapWithKeys(fn (string $field): array => [$field => $line->{$field} ?? null])
            ->all();
    }

    protected function writeAudit(
        StockAdjustmentLine $line,
        string $event,
        string $description,
        ?array $before,
        ?array $after
    ): void {
        if (! Schema::hasTable('stock_adjustment_audits')) {
            return;
        }

        $adjustment = DB::table('stock_adjustments')
            ->where('id', $line->stock_adjustment_id)
            ->first();

        DB::table('stock_adjustment_audits')->insert([
            'company_id' => $adjustment->company_id ?? null,
            'stock_adjustment_id' => $line->stock_adjustment_id ?? null,
            'stock_adjustment_line_id' => $line->id ?? null,
            'user_id' => Auth::id(),
            'event' => $event,
            'description' => $description,
            'before_data' => $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'after_data' => $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'metadata' => json_encode([
                'reference' => $adjustment->reference ?? null,
                'product_id' => $line->product_id ?? null,
                'product_variant_id' => $line->product_variant_id ?? null,
                'lot_id' => $line->lot_id ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
