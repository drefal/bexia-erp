<?php

namespace App\Support\Inventory;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class OutboundSerialNumberService
{
    /**
     * Lista números de serie disponibles para un producto.
     */
    public function availableForProduct(
        int $companyId,
        int $productId,
        ?int $productVariantId = null,
        ?int $warehouseId = null,
        ?int $locationId = null,
        int $limit = 50
    ): Collection {
        if (! Schema::hasTable('stock_serial_numbers')) {
            return collect();
        }

        $query = DB::table('stock_serial_numbers')
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('status', 'available');

        if ($productVariantId) {
            $query->where('product_variant_id', $productVariantId);
        } else {
            $query->where(function ($inner): void {
                $inner->whereNull('product_variant_id')
                    ->orWhere('product_variant_id', 0);
            });
        }

        if ($warehouseId && Schema::hasColumn('stock_serial_numbers', 'warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($locationId && Schema::hasColumn('stock_serial_numbers', 'location_id')) {
            $query->where('location_id', $locationId);
        }

        return $query
            ->orderBy('serial_number')
            ->limit($limit)
            ->get();
    }

    /**
     * Marca una serie como vendida/usada en una salida.
     *
     * Contexto esperado:
     * - company_id
     * - product_id
     * - product_variant_id opcional
     * - stock_movement_line_id opcional
     * - source_type: pos_order | sale_delivery | manual_exit
     * - source_id
     * - source_line_type: pos_order_line | sale_delivery_line
     * - source_line_id
     * - user_id opcional
     */
    public function markSold(int $serialNumberId, array $context): array
    {
        return DB::transaction(function () use ($serialNumberId, $context): array {
            $this->assertReady();

            $serial = DB::table('stock_serial_numbers')
                ->where('id', $serialNumberId)
                ->lockForUpdate()
                ->first();

            if (! $serial) {
                throw new RuntimeException('No se encontró el número de serie seleccionado.');
            }

            $this->validateSerialCanBeUsed($serial, $context);

            $now = now();

            $serialUpdates = $this->filterColumns('stock_serial_numbers', [
                'status' => 'sold',
                'out_stock_movement_line_id' => $this->nullableInt($context['stock_movement_line_id'] ?? null),
                'out_source_type' => $this->nullableString($context['source_type'] ?? null),
                'out_source_id' => $this->nullableInt($context['source_id'] ?? null),
                'out_source_line_type' => $this->nullableString($context['source_line_type'] ?? null),
                'out_source_line_id' => $this->nullableInt($context['source_line_id'] ?? null),
                'sold_at' => $now,
                'sold_by' => $this->nullableInt($context['user_id'] ?? null),
                'updated_at' => $now,
            ]);

            DB::table('stock_serial_numbers')
                ->where('id', $serialNumberId)
                ->update($serialUpdates);

            $movementLineId = $this->nullableInt($context['stock_movement_line_id'] ?? null);

            if ($movementLineId && Schema::hasTable('stock_movement_lines')) {
                $movementUpdates = $this->filterColumns('stock_movement_lines', [
                    'stock_serial_number_id' => $serialNumberId,
                    'source_type' => $this->nullableString($context['source_type'] ?? null),
                    'source_id' => $this->nullableInt($context['source_id'] ?? null),
                    'source_line_type' => $this->nullableString($context['source_line_type'] ?? null),
                    'source_line_id' => $this->nullableInt($context['source_line_id'] ?? null),
                    'updated_at' => $now,
                ]);

                if ($movementUpdates) {
                    DB::table('stock_movement_lines')
                        ->where('id', $movementLineId)
                        ->update($movementUpdates);
                }
            }

            $this->updateSourceLine($serialNumberId, $context, $now);

            return [
                'ok' => true,
                'serial_number_id' => $serialNumberId,
                'serial_number' => (string) ($serial->serial_number ?? ''),
                'status' => 'sold',
                'message' => 'Número de serie marcado como vendido/no disponible.',
            ];
        });
    }

    /**
     * Reactiva una serie por devolución.
     */
    public function markReturned(int $serialNumberId, array $context = []): array
    {
        return DB::transaction(function () use ($serialNumberId, $context): array {
            $this->assertReady();

            $serial = DB::table('stock_serial_numbers')
                ->where('id', $serialNumberId)
                ->lockForUpdate()
                ->first();

            if (! $serial) {
                throw new RuntimeException('No se encontró el número de serie seleccionado.');
            }

            if (! in_array((string) ($serial->status ?? ''), ['sold', 'used', 'unavailable'], true)) {
                throw new RuntimeException('La serie no está marcada como vendida/no disponible.');
            }

            $now = now();

            $updates = $this->filterColumns('stock_serial_numbers', [
                'status' => 'available',
                'returned_at' => $now,
                'returned_by' => $this->nullableInt($context['user_id'] ?? null),
                'updated_at' => $now,
            ]);

            DB::table('stock_serial_numbers')
                ->where('id', $serialNumberId)
                ->update($updates);

            return [
                'ok' => true,
                'serial_number_id' => $serialNumberId,
                'serial_number' => (string) ($serial->serial_number ?? ''),
                'status' => 'available',
                'message' => 'Número de serie reactivado por devolución.',
            ];
        });
    }

    public function assertSerialAvailable(int $serialNumberId, array $context = []): bool
    {
        $this->assertReady();

        $serial = DB::table('stock_serial_numbers')
            ->where('id', $serialNumberId)
            ->first();

        if (! $serial) {
            throw new RuntimeException('No se encontró el número de serie seleccionado.');
        }

        $this->validateSerialCanBeUsed($serial, $context);

        return true;
    }

    private function validateSerialCanBeUsed(object $serial, array $context): void
    {
        if ((string) ($serial->status ?? '') !== 'available') {
            throw new RuntimeException('La serie seleccionada no está disponible.');
        }

        $companyId = $this->nullableInt($context['company_id'] ?? null);
        if ($companyId && (int) ($serial->company_id ?? 0) !== $companyId) {
            throw new RuntimeException('La serie seleccionada pertenece a otra empresa.');
        }

        $productId = $this->nullableInt($context['product_id'] ?? null);
        if ($productId && (int) ($serial->product_id ?? 0) !== $productId) {
            throw new RuntimeException('La serie seleccionada no corresponde al producto.');
        }

        $variantId = $this->nullableInt($context['product_variant_id'] ?? null);
        $serialVariantId = $this->nullableInt($serial->product_variant_id ?? null);

        if ($variantId && $serialVariantId && $serialVariantId !== $variantId) {
            throw new RuntimeException('La serie seleccionada no corresponde a la variante.');
        }
    }

    private function updateSourceLine(int $serialNumberId, array $context, mixed $now): void
    {
        $sourceLineType = (string) ($context['source_line_type'] ?? '');
        $sourceLineId = $this->nullableInt($context['source_line_id'] ?? null);

        if (! $sourceLineType || ! $sourceLineId) {
            return;
        }

        $table = match ($sourceLineType) {
            'pos_order_line', 'pos_order_lines' => 'pos_order_lines',
            'sale_delivery_line', 'sale_delivery_lines' => 'sale_delivery_lines',
            'pos_order_refund_line', 'pos_order_refund_lines' => 'pos_order_refund_lines',
            default => null,
        };

        if (! $table || ! Schema::hasTable($table)) {
            return;
        }

        $metadata = [
            'stock_serial_number_id' => $serialNumberId,
            'serial_number' => DB::table('stock_serial_numbers')->where('id', $serialNumberId)->value('serial_number'),
            'source_type' => $context['source_type'] ?? null,
            'source_id' => $context['source_id'] ?? null,
            'source_line_type' => $sourceLineType,
            'source_line_id' => $sourceLineId,
            'updated_at' => (string) $now,
        ];

        $updates = $this->filterColumns($table, [
            'stock_serial_number_id' => $serialNumberId,
            'serial_tracking_metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => $now,
        ]);

        if ($updates) {
            DB::table($table)->where('id', $sourceLineId)->update($updates);
        }
    }

    private function assertReady(): void
    {
        if (! Schema::hasTable('stock_serial_numbers')) {
            throw new RuntimeException('No existe la tabla stock_serial_numbers.');
        }

        if (! Schema::hasColumn('stock_serial_numbers', 'status')) {
            throw new RuntimeException('La tabla stock_serial_numbers no tiene columna status.');
        }
    }

    private function filterColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $columns = Schema::getColumnListing($table);

        return array_intersect_key($data, array_flip($columns));
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }
}
