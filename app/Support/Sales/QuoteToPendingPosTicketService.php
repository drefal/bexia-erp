<?php

namespace App\Support\Sales;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class QuoteToPendingPosTicketService
{
    public function validateQuoteForPosPoint(int $salesOrderId, int $posPointId): array
    {
        $errors = [];
        $warnings = [];
        $stockLines = [];

        $order = $this->salesOrder($salesOrderId);
        if (! $order) {
            return [
                'ok' => false,
                'errors' => ['No se encontró la cotización.'],
                'warnings' => [],
                'stock_lines' => [],
            ];
        }

        $posPoint = $this->posPoint($posPointId);
        if (! $posPoint) {
            return [
                'ok' => false,
                'errors' => ['No se encontró el PDV destino.'],
                'warnings' => [],
                'stock_lines' => [],
            ];
        }

        if ((int) ($order->company_id ?? 0) !== (int) ($posPoint->company_id ?? 0)) {
            $errors[] = 'La cotización y el PDV pertenecen a empresas diferentes.';
        }

        if ((string) ($posPoint->status ?? '') !== 'active') {
            $errors[] = 'El PDV destino no está activo.';
        }

        $warehouseId = (int) ($posPoint->warehouse_id ?? 0);
        $locationId = (int) ($posPoint->stock_location_id ?? 0);

        if ($warehouseId <= 0 || $locationId <= 0) {
            $errors[] = 'El PDV destino no tiene almacén o ubicación configurada.';
        }

        $status = (string) ($order->status ?? '');
        if (in_array($status, ['cancelled', 'delivered', 'partially_delivered'], true)) {
            $errors[] = 'La cotización/orden ya está cancelada o entregada y no se puede enviar a PDV.';
        }

        $lines = $this->salesOrderLines($salesOrderId);

        if ($lines->isEmpty()) {
            $errors[] = 'La cotización no tiene líneas.';
        }

        foreach ($lines as $line) {
            $qty = (float) ($line->quantity ?? 0);
            $productName = (string) ($line->product_label ?: $line->product_name ?: 'Producto sin nombre');
            $productType = (string) ($line->product_type ?? '');
            $tracking = (string) ($line->tracking ?? 'none');

            if ($qty <= 0) {
                $warnings[] = "La línea {$productName} tiene cantidad cero o negativa.";
                continue;
            }

            if ($productType === 'service') {
                $stockLines[] = [
                    'product_id' => $line->product_id,
                    'product_name' => $productName,
                    'product_type' => $productType,
                    'tracking' => $tracking,
                    'required_quantity' => $qty,
                    'available_quantity' => null,
                    'status' => 'service_no_stock_validation',
                    'message' => 'Servicio: no valida inventario.',
                ];

                continue;
            }

            if ($productType !== 'stockable') {
                $stockLines[] = [
                    'product_id' => $line->product_id,
                    'product_name' => $productName,
                    'product_type' => $productType,
                    'tracking' => $tracking,
                    'required_quantity' => $qty,
                    'available_quantity' => null,
                    'status' => 'non_stockable_no_stock_validation',
                    'message' => 'Producto sin control inventariable.',
                ];

                continue;
            }

            if (! $line->product_id) {
                $errors[] = "La línea {$productName} no tiene producto ligado.";
                continue;
            }

            if (isset($line->is_active) && ! (bool) $line->is_active) {
                $errors[] = "El producto {$productName} está inactivo.";
            }

            if (isset($line->can_be_sold) && ! (bool) $line->can_be_sold) {
                $errors[] = "El producto {$productName} no está marcado como vendible.";
            }

            $available = $this->availableQuantity(
                companyId: (int) $order->company_id,
                warehouseId: $warehouseId,
                locationId: $locationId,
                productId: (int) $line->product_id,
                productVariantId: $line->product_variant_id ? (int) $line->product_variant_id : null,
            );

            $lineStatus = $available >= $qty ? 'ok' : 'insufficient_stock';

            $stockLines[] = [
                'product_id' => $line->product_id,
                'product_variant_id' => $line->product_variant_id,
                'product_name' => $productName,
                'product_type' => $productType,
                'tracking' => $tracking,
                'required_quantity' => $qty,
                'available_quantity' => $available,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'status' => $lineStatus,
                'message' => $lineStatus === 'ok'
                    ? 'Existencia suficiente en el PDV destino.'
                    : 'No hay existencia suficiente en el PDV destino.',
            ];

            if ($available < $qty) {
                $errors[] = "No hay existencia suficiente de {$productName} en el PDV destino. Requerido: {$qty}, disponible: {$available}.";
            }

            if (in_array($tracking, ['serial', 'lot'], true)) {
                $warnings[] = "El producto {$productName} usa tracking {$tracking}; al cobrar deberá seleccionarse serie/lote en el PDV.";
            }
        }

        return [
            'ok' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'stock_lines' => $stockLines,
            'warehouse_id' => $warehouseId,
            'stock_location_id' => $locationId,
        ];
    }

    public function createPendingTicket(
        int $salesOrderId,
        int $posPointId,
        ?int $posSessionId = null,
        ?int $userId = null,
        ?string $note = null,
    ): array {
        $validation = $this->validateQuoteForPosPoint($salesOrderId, $posPointId);

        if (! ($validation['ok'] ?? false)) {
            throw new RuntimeException('No se puede enviar la cotización a PDV: ' . implode(' ', $validation['errors'] ?? []));
        }

        $order = $this->salesOrder($salesOrderId);
        $posPoint = $this->posPoint($posPointId);

        if (! $order || ! $posPoint) {
            throw new RuntimeException('No se encontró la cotización o el PDV destino.');
        }

        // V5.61.2g: seguridad backend.
        // No se permite crear ticket PDV si la cotización no fue validada.
        if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_validation_status')) {
            $quoteValidationStatus = (string) ($order->quote_validation_status ?? 'not_validated');
            $marginApprovalStatus = (string) ($order->margin_approval_status ?? 'not_required');

            $isValidated = $quoteValidationStatus === 'validated'
                || ($quoteValidationStatus === 'pending_approval' && $marginApprovalStatus === 'approved');

            if (! $isValidated) {
                throw new RuntimeException('La cotización debe validarse antes de enviarse a PDV.');
            }
        }

        $session = $this->openSession($posPointId, $posSessionId);

        if (! $session) {
            throw new RuntimeException('El PDV destino no tiene una sesión abierta.');
        }

        if ((int) ($session->pos_point_id ?? 0) !== (int) $posPointId) {
            throw new RuntimeException('La sesión abierta no pertenece al PDV seleccionado.');
        }

        $pending = $this->existingPendingTicket($salesOrderId);
        if ($pending) {
            throw new RuntimeException('Esta cotización ya tiene un ticket pendiente en PDV. Cancela o cobra el ticket pendiente antes de generar otro.');
        }

        $lines = $this->salesOrderLines($salesOrderId);
        $publicToken = $this->generatePublicToken();
        $number = $this->generatePosOrderNumber((string) ($posPoint->code ?: 'PDV'));

        return DB::transaction(function () use ($order, $posPoint, $session, $lines, $publicToken, $number, $userId, $note, $validation) {
            $now = now();

            $metadata = [
                'source' => 'sales_quote',
                'source_sales_order_id' => (int) $order->id,
                'source_sales_order_number' => (string) ($order->number ?? ''),
                'source_customer_name' => (string) ($order->customer_name ?? ''),
                'source_public_token' => $publicToken,
                'price_policy' => 'quote_locked',
                'locked_prices' => true,
                'quote_price_list_id' => $order->price_list_id ?? null,
                'quote_price_list_applied_id' => $order->price_list_applied_id ?? null,
                'sent_note' => $note,
                'stock_validation' => $validation,
            ];

            $posOrderId = DB::table('pos_orders')->insertGetId($this->filterColumns('pos_orders', [
                'company_id' => (int) $order->company_id,
                'pos_point_id' => (int) $posPoint->id,
                'pos_session_id' => (int) $session->id,
                'employee_id' => $session->employee_id ?? null,
                'customer_id' => $order->customer_contact_id ?? null,
                'number' => $number,
                'status' => 'pending_payment',
                'subtotal' => round((float) ($order->total_without_tax ?? 0), 4),
                'tax_total' => round((float) ($order->total_tax ?? 0), 4),
                'total' => round((float) ($order->total_with_tax ?? 0), 4),
                'currency_code' => (string) ($order->currency ?? 'MXN'),
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ordered_at' => $now,
                'price_list_id' => null,
                'price_list_name' => 'Precio de cotización',
                'source_type' => 'sales_quote',
                'source_id' => (int) $order->id,
                'source_reference' => (string) ($order->number ?? ''),
                'source_public_token' => $publicToken,
                'source_price_policy' => 'quote_locked',
                'source_locked_prices' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            foreach ($lines as $line) {
                $lineMetadata = [
                    'source' => 'sales_quote',
                    'source_sales_order_id' => (int) $order->id,
                    'source_sales_order_line_id' => (int) $line->id,
                    'price_policy' => 'quote_locked',
                    'locked_prices' => true,
                    'unit_price_without_tax' => (float) ($line->unit_price_without_tax ?? 0),
                    'unit_price_with_tax' => (float) ($line->unit_price_with_tax ?? 0),
                    'line_total_without_tax' => (float) ($line->line_total_without_tax ?? 0),
                    'line_tax' => (float) ($line->line_tax ?? 0),
                    'line_total_with_tax' => (float) ($line->line_total_with_tax ?? 0),
                    'sales_tax_rate_percent' => (float) ($line->tax_rate ?? 0),
                ];

                DB::table('pos_order_lines')->insert($this->filterColumns('pos_order_lines', [
                    'pos_order_id' => $posOrderId,
                    'product_id' => $line->product_id,
                    'product_variant_id' => $line->product_variant_id ?? null,
                    'product_name' => (string) ($line->product_label ?: $line->product_name ?: 'Producto'),
                    'product_reference' => $line->variant_label ?? null,
                    'quantity' => round((float) ($line->quantity ?? 0), 4),
                    'unit_price' => round((float) ($line->unit_price_with_tax ?? 0), 4),
                    'tax_rate' => round(((float) ($line->tax_rate ?? 0)) / 100, 4),
                    'subtotal' => round((float) ($line->line_total_without_tax ?? 0), 4),
                    'tax_total' => round((float) ($line->line_tax ?? 0), 4),
                    'total' => round((float) ($line->line_total_with_tax ?? 0), 4),
                    'metadata' => json_encode($lineMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }

            $bridgeId = DB::table('sales_quote_pos_tickets')->insertGetId([
                'company_id' => (int) $order->company_id,
                'sales_order_id' => (int) $order->id,
                'pos_order_id' => $posOrderId,
                'pos_point_id' => (int) $posPoint->id,
                'pos_session_id' => (int) $session->id,
                'warehouse_id' => $posPoint->warehouse_id ?? null,
                'stock_location_id' => $posPoint->stock_location_id ?? null,
                'status' => 'pending',
                'public_token' => $publicToken,
                'sent_by_user_id' => $userId,
                'sent_at' => $now,
                'notes' => $note,
                'metadata' => json_encode([
                    'pos_order_number' => $number,
                    'sales_order_number' => (string) ($order->number ?? ''),
                    'price_policy' => 'quote_locked',
                    'locked_prices' => true,
                    'stock_validation' => $validation,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'ok' => true,
                'sales_quote_pos_ticket_id' => $bridgeId,
                'pos_order_id' => $posOrderId,
                'pos_order_number' => $number,
                'public_token' => $publicToken,
                'print_url' => route('pos.orders.pending-ticket.print', ['order' => $posOrderId]),
            ];
        });
    }

    protected function salesOrder(int $salesOrderId): ?object
    {
        if (! Schema::hasTable('sales_orders')) {
            return null;
        }

        return DB::table('sales_orders')->where('id', $salesOrderId)->first();
    }

    protected function posPoint(int $posPointId): ?object
    {
        if (! Schema::hasTable('pos_points')) {
            return null;
        }

        return DB::table('pos_points')->where('id', $posPointId)->first();
    }

    protected function openSession(int $posPointId, ?int $posSessionId = null): ?object
    {
        if (! Schema::hasTable('pos_sessions')) {
            return null;
        }

        $query = DB::table('pos_sessions')
            ->where('status', 'open')
            ->where('pos_point_id', $posPointId);

        if ($posSessionId) {
            $query->where('id', $posSessionId);
        }

        return $query->orderByDesc('id')->first();
    }

    protected function existingPendingTicket(int $salesOrderId): ?object
    {
        if (! Schema::hasTable('sales_quote_pos_tickets')) {
            return null;
        }

        return DB::table('sales_quote_pos_tickets')
            ->where('sales_order_id', $salesOrderId)
            ->whereIn('status', ['pending', 'sent'])
            ->orderByDesc('id')
            ->first();
    }

    protected function salesOrderLines(int $salesOrderId)
    {
        if (! Schema::hasTable('sales_order_lines')) {
            return collect();
        }

        return DB::table('sales_order_lines as sol')
            ->leftJoin('products as p', 'p.id', '=', 'sol.product_id')
            ->where('sol.sales_order_id', $salesOrderId)
            ->select([
                'sol.*',
                'p.name as product_name',
                'p.product_type',
                'p.tracking',
                'p.can_be_sold',
                'p.is_active',
                'p.available_in_pos',
                'p.allow_out_of_stock_sales',
            ])
            ->orderBy('sol.id')
            ->get();
    }

    protected function availableQuantity(int $companyId, int $warehouseId, int $locationId, int $productId, ?int $productVariantId = null): float
    {
        if (! Schema::hasTable('stock_quants')) {
            return 0.0;
        }

        $query = DB::table('stock_quants')
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId);

        if ($productVariantId && Schema::hasColumn('stock_quants', 'product_variant_id')) {
            $query->where(function ($q) use ($productVariantId) {
                $q->where('product_variant_id', $productVariantId)
                    ->orWhereNull('product_variant_id');
            });
        }

        $row = $query->selectRaw('coalesce(sum(quantity), 0) as qty, coalesce(sum(reserved_quantity), 0) as reserved')->first();

        return round((float) ($row->qty ?? 0) - (float) ($row->reserved ?? 0), 6);
    }

    protected function generatePublicToken(): string
    {
        do {
            $token = 'QPDV-' . strtoupper(Str::random(10));
        } while (Schema::hasTable('sales_quote_pos_tickets') && DB::table('sales_quote_pos_tickets')->where('public_token', $token)->exists());

        return $token;
    }

    protected function generatePosOrderNumber(string $posCode): string
    {
        $cleanCode = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $posCode) ?: 'PDV');
        $prefix = 'COT-' . $cleanCode . '-' . now()->format('Ymd') . '-';

        for ($i = 1; $i <= 99999; $i++) {
            $number = $prefix . str_pad((string) $i, 5, '0', STR_PAD_LEFT);

            if (! DB::table('pos_orders')->where('number', $number)->exists()) {
                return $number;
            }
        }

        throw new RuntimeException('No se pudo generar número de ticket pendiente para PDV.');
    }

    protected function filterColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return $data;
        }

        $columns = array_flip(Schema::getColumnListing($table));

        return array_filter(
            $data,
            fn ($value, string $column): bool => array_key_exists($column, $columns),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
