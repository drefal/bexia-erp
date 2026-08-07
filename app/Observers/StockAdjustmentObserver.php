<?php

namespace App\Observers;

use App\Models\StockAdjustment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockAdjustmentObserver
{
    public function saving(StockAdjustment $adjustment): void
    {
        $reason = trim((string) ($adjustment->reason ?? ''));
        $status = strtolower((string) ($adjustment->status ?? ''));

        if (! $adjustment->exists) {
            if ($reason === '') {
                throw new \RuntimeException('El motivo del ajuste es obligatorio.');
            }

        }

        if ($adjustment->isDirty('reason') && $reason === '') {
            throw new \RuntimeException('El motivo del ajuste no puede quedar vacío.');
        }

        if ($adjustment->isDirty('status') && in_array($status, ['done', 'confirmed'], true)) {
            if ($reason === '') {
                throw new \RuntimeException('El motivo del ajuste es obligatorio antes de confirmar.');
            }

        }

        if ($adjustment->isDirty('status') && in_array($status, ['cancelled', 'canceled'], true)) {
            $cancelReason = trim((string) ($adjustment->cancellation_reason ?? ''));

            if ($cancelReason === '') {
                throw new \RuntimeException('El motivo de cancelación del ajuste es obligatorio.');
            }
        }
    }

    public function created(StockAdjustment $adjustment): void
    {
        $this->writeAudit(
            $adjustment,
            'created',
            'Ajuste de inventario creado.',
            null,
            $this->snapshot($adjustment)
        );
    }

    public function updated(StockAdjustment $adjustment): void
    {
        $before = [];
        $after = [];

        foreach ($this->trackedFields() as $field) {
            if ($adjustment->wasChanged($field)) {
                $before[$field] = $adjustment->getOriginal($field);
                $after[$field] = $adjustment->{$field};
            }
        }

        if ($before === [] && $after === []) {
            return;
        }

        $event = 'updated';
        $description = 'Ajuste de inventario actualizado.';

        if (array_key_exists('status', $after)) {
            $status = strtolower((string) $after['status']);

            if (in_array($status, ['done', 'confirmed'], true)) {
                $event = 'confirmed';
                $description = 'Ajuste de inventario confirmado.';
            } elseif (in_array($status, ['cancelled', 'canceled'], true)) {
                $event = 'cancelled';
                $description = 'Ajuste de inventario cancelado.';
            }
        }

        $this->writeAudit($adjustment, $event, $description, $before, $after);
    }

    protected function trackedFields(): array
    {
        return [
            'reference',
            'adjustment_date',
            'adjustment_at',
            'status',
            'reason',
            'notes',
            'created_by',
            'confirmed_by',
            'confirmed_at',
            'cancellation_reason',
            'cancelled_by',
            'cancelled_at',
            'warehouse_id',
            'location_id',
        ];
    }

    protected function snapshot(StockAdjustment $adjustment): array
    {
        return collect($this->trackedFields())
            ->mapWithKeys(fn (string $field): array => [$field => $adjustment->{$field} ?? null])
            ->all();
    }

    protected function writeAudit(
        StockAdjustment $adjustment,
        string $event,
        string $description,
        ?array $before,
        ?array $after
    ): void {
        if (! Schema::hasTable('stock_adjustment_audits')) {
            return;
        }

        DB::table('stock_adjustment_audits')->insert([
            'company_id' => $adjustment->company_id ?? null,
            'stock_adjustment_id' => $adjustment->id ?? null,
            'stock_adjustment_line_id' => null,
            'user_id' => Auth::id(),
            'event' => $event,
            'description' => $description,
            'before_data' => $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'after_data' => $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'metadata' => json_encode([
                'reference' => $adjustment->reference ?? null,
                'status' => $adjustment->status ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
