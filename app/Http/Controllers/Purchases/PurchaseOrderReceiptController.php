<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Support\PurchaseReceiptInventoryPoster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PurchaseOrderReceiptController extends Controller
{
    public function edit(PurchaseOrder $purchaseOrder)
    {
        abort_unless(auth()->check(), 403);

        $order = DB::table('purchase_orders')
            ->where('id', $purchaseOrder->getKey())
            ->first();

        abort_if(! $order, 404);

        $this->authorizeTenant($order);

        if (! $this->canReceive($order)) {
            return redirect('/admin/' . $this->tenantId($order) . '/purchase-orders/' . $order->id . '/edit')
                ->with('error', 'Esta orden no está lista para recepción o no tiene cantidades pendientes.');
        }

        return redirect('/admin/' . $this->tenantId($order) . '/purchase-orders/' . $order->id . '/receive');
    }

    public function store(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless(auth()->check(), 403);

        $order = DB::table('purchase_orders')
            ->where('id', $purchaseOrder->getKey())
            ->first();

        abort_if(! $order, 404);

        $this->authorizeTenant($order);

        if (! $this->canReceive($order)) {
            return redirect()
                ->back()
                ->with('error', 'Esta orden no está lista para recepción.');
        }

        $quantities = $request->input('quantities', []);
        $lotNumbers = $request->input('lot_numbers', []);
        $lotExpirationDates = $request->input('lot_expiration_dates', []);
        $serialNumbers = $request->input('serial_numbers', []);
        $commonImportData = $request->input('common_import_data', []);
        $lineImportData = $request->input('line_import_data', []);
        $applyCommonImportToAll = $request->boolean('apply_common_import_to_all');
        $notes = trim((string) $request->input('notes', ''));

        try {
            $receiptId = $this->createReceipt(
                (int) $order->id,
                $quantities,
                $lotNumbers,
                $lotExpirationDates,
                $serialNumbers,
                $commonImportData,
                $lineImportData,
                $applyCommonImportToAll,
                $notes
            );

            return redirect('/admin/' . $this->tenantId($order) . '/purchase-orders/' . $order->id . '/edit')
                ->with('success', 'Recepción guardada correctamente. Folio recepción #' . $receiptId . '.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    protected function createReceipt(
        int $purchaseOrderId,
        array $quantities,
        array $lotNumbers,
        array $lotExpirationDates,
        array $serialNumbers,
        array $commonImportData,
        array $lineImportData,
        bool $applyCommonImportToAll,
        string $notes
    ): int {
        return DB::transaction(function () use (
            $purchaseOrderId,
            $quantities,
            $lotNumbers,
            $lotExpirationDates,
            $serialNumbers,
            $commonImportData,
            $lineImportData,
            $applyCommonImportToAll,
            $notes
        ): int {
            $order = DB::table('purchase_orders')
                ->where('id', $purchaseOrderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new RuntimeException('No se encontró la orden de compra.');
            }

            $lines = DB::table('purchase_order_lines')
                ->where('purchase_order_id', $purchaseOrderId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $receiptLines = [];
            $totalWithoutTax = 0.0;
            $totalTax = 0.0;
            $totalWithTax = 0.0;

            foreach ($lines as $line) {
                $lineId = (int) $line->id;
                $receiveQty = round((float) ($quantities[$lineId] ?? 0), 6);

                if ($receiveQty <= 0) {
                    continue;
                }

                $orderedQty = (float) ($line->ordered_quantity ?? 0);
                $alreadyReceived = (float) ($line->received_quantity ?? 0);
                $pendingQty = max($orderedQty - $alreadyReceived, 0);

                if ($receiveQty > $pendingQty + 0.000001) {
                    throw new RuntimeException('La cantidad a recibir supera el pendiente para: ' . ($line->product_label ?? 'producto'));
                }

                $baseFactor = $this->baseFactor($line);
                $receivedBaseQty = round($receiveQty * $baseFactor, 6);

                $trackingType = $this->trackingTypeForLine($line);
                $lotNumber = null;
                $lotExpirationDate = null;
                $serials = [];

                if ($trackingType === 'lot') {
                    $lotNumber = trim((string) ($lotNumbers[$lineId] ?? ''));

                    if ($lotNumber === '') {
                        throw new RuntimeException('Captura el lote para: ' . ($line->product_label ?? 'producto'));
                    }

                    $lotExpirationDate = $this->normalizeDate($lotExpirationDates[$lineId] ?? null);
                }

                if ($trackingType === 'serial') {
                    if (abs($receivedBaseQty - round($receivedBaseQty)) > 0.000001) {
                        throw new RuntimeException('La cantidad base recibida debe ser entera para productos con número de serie: ' . ($line->product_label ?? 'producto'));
                    }

                    $expectedSerials = (int) round($receivedBaseQty);
                    $serials = $this->parseSerialNumbers($serialNumbers[$lineId] ?? '');

                    if ($expectedSerials <= 0) {
                        throw new RuntimeException('La cantidad recibida para números de serie debe ser mayor a cero: ' . ($line->product_label ?? 'producto'));
                    }

                    if (count($serials) !== $expectedSerials) {
                        throw new RuntimeException(
                            'El producto "' . ($line->product_label ?? 'producto') . '" requiere ' .
                            $expectedSerials . ' número(s) de serie y capturaste ' . count($serials) . '.'
                        );
                    }

                    $this->assertSerialNumbersAvailable($order, $line, $serials);
                }

                $advancedTrackingConfig = $this->advancedTrackingConfigForLine($line);
                $importData = $this->importDataForLine(
                    $commonImportData,
                    $lineImportData,
                    $applyCommonImportToAll,
                    $lineId,
                    $trackingType,
                    $serials
                );

                $this->validateAdvancedTrackingForLine(
                    $line,
                    $trackingType,
                    $serials,
                    $importData,
                    $advancedTrackingConfig
                );

                $unitCost = (float) ($line->unit_cost_without_tax ?? 0);
                $taxRate = (float) ($line->tax_rate ?? 0);

                $lineWithoutTax = round($receiveQty * $unitCost, 6);
                $lineTax = round($lineWithoutTax * ($taxRate / 100), 6);
                $lineWithTax = round($lineWithoutTax + $lineTax, 6);

                $receiptLines[] = [
                    'source_line' => $line,
                    'received_quantity' => $receiveQty,
                    'received_base_quantity' => $receivedBaseQty,
                    'line_total_without_tax' => $lineWithoutTax,
                    'line_tax' => $lineTax,
                    'line_total_with_tax' => $lineWithTax,
                    'tracking_type' => $trackingType,
                    'lot_number' => $lotNumber,
                    'lot_expiration_date' => $lotExpirationDate,
                    'serial_numbers' => $serials,
                    'import_data' => $importData,
                    'lot_id' => null,
                ];

                $totalWithoutTax += $lineWithoutTax;
                $totalTax += $lineTax;
                $totalWithTax += $lineWithTax;
            }

            if (count($receiptLines) === 0) {
                throw new RuntimeException('Captura al menos una cantidad a recibir.');
            }

            $receiptId = $this->insertReceipt($order, $notes, $totalWithoutTax, $totalTax, $totalWithTax);

            foreach ($receiptLines as $receiptLine) {
                if (($receiptLine['tracking_type'] ?? 'none') === 'lot') {
                    $receiptLine['lot_id'] = $this->firstOrCreateStockLot(
                        $order,
                        $receiptLine['source_line'],
                        $receiptId,
                        (string) $receiptLine['lot_number'],
                        $receiptLine['lot_expiration_date'],
                        $receiptLine['import_data'] ?? []
                    );
                }

                $this->insertReceiptLine($receiptId, $order, $receiptLine);
                $this->updateOrderLineReceipt($receiptLine['source_line'], $receiptLine['received_quantity'], $receiptLine['received_base_quantity']);
            }

            $this->refreshOrderReceiptStatus($purchaseOrderId);

            app(PurchaseReceiptInventoryPoster::class)->post($receiptId);

            return $receiptId;
        });
    }

    protected function insertReceipt(object $order, string $notes, float $totalWithoutTax, float $totalTax, float $totalWithTax): int
    {
        $columns = Schema::getColumnListing('purchase_receipts');

        $data = [];

        $this->set($data, $columns, 'company_id', $order->company_id ?? null);
        $this->set($data, $columns, 'purchase_order_id', $order->id);
        $this->set($data, $columns, 'number', $this->nextReceiptNumber($order));
        $this->set($data, $columns, 'status', 'received');
        $this->set($data, $columns, 'received_at', now());
        $this->set($data, $columns, 'warehouse_id', $order->warehouse_id ?? null);
        $this->set($data, $columns, 'location_id', $order->location_id ?? null);
        $this->set($data, $columns, 'received_by_user_id', auth()->id());
        $this->set($data, $columns, 'total_without_tax', round($totalWithoutTax, 6));
        $this->set($data, $columns, 'total_tax', round($totalTax, 6));
        $this->set($data, $columns, 'total_with_tax', round($totalWithTax, 6));
        $this->set($data, $columns, 'notes', $notes);
        $this->set($data, $columns, 'created_at', now());
        $this->set($data, $columns, 'updated_at', now());

        return DB::table('purchase_receipts')->insertGetId($data);
    }

    protected function insertReceiptLine(int $receiptId, object $order, array $receiptLine): void
    {
        $line = $receiptLine['source_line'];
        $columns = Schema::getColumnListing('purchase_receipt_lines');

        $data = [];

        $this->set($data, $columns, 'purchase_receipt_id', $receiptId);
        $this->set($data, $columns, 'purchase_order_id', $order->id);
        $this->set($data, $columns, 'purchase_order_line_id', $line->id);
        $this->set($data, $columns, 'product_id', $line->product_id ?? null);
        $this->set($data, $columns, 'product_variant_id', $line->product_variant_id ?? null);
        $this->set($data, $columns, 'variant_id', $line->variant_id ?? null);
        $this->set($data, $columns, 'lot_id', $receiptLine['lot_id'] ?? null);
        $this->set($data, $columns, 'lot_number', $receiptLine['lot_number'] ?? null);
        $this->set($data, $columns, 'lot_expiration_date', $receiptLine['lot_expiration_date'] ?? null);
        $this->set($data, $columns, 'serial_numbers', json_encode($receiptLine['serial_numbers'] ?? []));
        $this->set($data, $columns, 'tracking_type', $receiptLine['tracking_type'] ?? 'none');
        $this->setImportColumns($data, $columns, $receiptLine['import_data'] ?? []);
        $this->set($data, $columns, 'product_label', $line->product_label ?? null);
        $this->set($data, $columns, 'variant_label', $line->variant_label ?? null);
        $this->set($data, $columns, 'purchase_unit_label', $line->purchase_unit_label ?? null);
        $this->set($data, $columns, 'received_quantity', $receiptLine['received_quantity']);
        $this->set($data, $columns, 'received_base_quantity', $receiptLine['received_base_quantity']);
        $this->set($data, $columns, 'unit_cost_without_tax', $line->unit_cost_without_tax ?? 0);
        $this->set($data, $columns, 'tax_rate', $line->tax_rate ?? 0);
        $this->set($data, $columns, 'line_total_without_tax', $receiptLine['line_total_without_tax']);
        $this->set($data, $columns, 'line_tax', $receiptLine['line_tax']);
        $this->set($data, $columns, 'line_total_with_tax', $receiptLine['line_total_with_tax']);
        $this->set($data, $columns, 'created_at', now());
        $this->set($data, $columns, 'updated_at', now());

        DB::table('purchase_receipt_lines')->insert($data);
    }

    protected function firstOrCreateStockLot(object $order, object $line, int $receiptId, string $lotNumber, ?string $expirationDate, array $importData = []): int
    {
        if (! Schema::hasTable('stock_lots')) {
            throw new RuntimeException('No existe la tabla de lotes.');
        }

        $companyId = (int) ($order->company_id ?? 0);
        $productId = (int) ($line->product_id ?? 0);
        $variantId = (int) ($line->product_variant_id ?? 0);
        $columns = Schema::getColumnListing('stock_lots');

        $query = DB::table('stock_lots')
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->whereRaw('LOWER(lot_number) = LOWER(?)', [$lotNumber]);

        $variantId > 0
            ? $query->where('product_variant_id', $variantId)
            : $query->whereNull('product_variant_id');

        $existing = $query->lockForUpdate()->first();

        if ($existing) {
            $updates = [];

            if ($expirationDate && empty($existing->expiration_date)) {
                $this->set($updates, $columns, 'expiration_date', $expirationDate);
            }

            if (empty($existing->purchase_receipt_id)) {
                $this->set($updates, $columns, 'purchase_receipt_id', $receiptId);
            }

            if (empty($existing->purchase_order_id)) {
                $this->set($updates, $columns, 'purchase_order_id', $order->id);
            }

            foreach ($this->importFieldNames() as $fieldName) {
                if (! empty($importData[$fieldName]) && empty($existing->{$fieldName})) {
                    $this->set($updates, $columns, $fieldName, $importData[$fieldName]);
                }
            }

            $this->set($updates, $columns, 'updated_at', now());

            if ($updates) {
                DB::table('stock_lots')->where('id', $existing->id)->update($updates);
            }

            return (int) $existing->id;
        }

        $data = [];

        $this->set($data, $columns, 'company_id', $companyId);
        $this->set($data, $columns, 'product_id', $productId);
        $this->set($data, $columns, 'product_variant_id', $variantId ?: null);
        $this->set($data, $columns, 'lot_number', $lotNumber);
        $this->set($data, $columns, 'expiration_date', $expirationDate);
        $this->set($data, $columns, 'supplier_contact_id', $order->supplier_contact_id ?? null);
        $this->set($data, $columns, 'purchase_order_id', $order->id);
        $this->set($data, $columns, 'purchase_receipt_id', $receiptId);
        $this->setImportColumns($data, $columns, $importData);
        $this->set($data, $columns, 'status', 'available');
        $this->set($data, $columns, 'metadata', json_encode([
            'source' => 'purchase_receipt',
            'created_from' => 'v5.53.3a',
        ]));
        $this->set($data, $columns, 'created_at', now());
        $this->set($data, $columns, 'updated_at', now());

        return DB::table('stock_lots')->insertGetId($data);
    }

    protected function assertSerialNumbersAvailable(object $order, object $line, array $serials): void
    {
        if (! Schema::hasTable('stock_serial_numbers')) {
            throw new RuntimeException('No existe la tabla de números de serie.');
        }

        $companyId = (int) ($order->company_id ?? 0);
        $productId = (int) ($line->product_id ?? 0);
        $variantId = (int) ($line->product_variant_id ?? 0);

        foreach ($serials as $serial) {
            $query = DB::table('stock_serial_numbers')
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->whereRaw('LOWER(serial_number) = LOWER(?)', [$serial]);

            $variantId > 0
                ? $query->where('product_variant_id', $variantId)
                : $query->whereNull('product_variant_id');

            if ($query->exists()) {
                throw new RuntimeException('El número de serie ya existe para este producto: ' . $serial);
            }
        }
    }

    protected function parseSerialNumbers(mixed $value): array
    {
        if (is_array($value)) {
            $raw = implode("\n", $value);
        } else {
            $raw = (string) $value;
        }

        $raw = str_replace([',', ';', "\t"], "\n", $raw);
        $lines = preg_split('/\R+/', $raw) ?: [];

        $serials = [];
        $seen = [];

        foreach ($lines as $line) {
            $serial = trim((string) $line);

            if ($serial === '') {
                continue;
            }

            $key = mb_strtolower($serial);

            if (isset($seen[$key])) {
                throw new RuntimeException('El número de serie está repetido en la captura: ' . $serial);
            }

            $seen[$key] = true;
            $serials[] = $serial;
        }

        return $serials;
    }

    protected function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new RuntimeException('La fecha de caducidad debe tener formato AAAA-MM-DD.');
        }

        return $value;
    }


    protected function importFieldNames(): array
    {
        return [
            'motor_number',
            'customs_entry_number',
            'customs_entry_date',
            'customs_office',
            'imported_model',
            'imported_color',
            'import_document_reference',
        ];
    }

    protected function setImportColumns(array &$data, array $columns, array $importData): void
    {
        foreach ($this->importFieldNames() as $fieldName) {
            $value = $importData[$fieldName] ?? null;

            if ($value === '') {
                $value = null;
            }

            $this->set($data, $columns, $fieldName, $value);
        }
    }

    protected function importDataForLine(
        array $commonImportData,
        array $lineImportData,
        bool $applyCommonImportToAll,
        int $lineId,
        string $trackingType,
        array $serials
    ): array {
        $lineData = $lineImportData[$lineId] ?? [];

        if (! is_array($lineData)) {
            $lineData = [];
        }

        $data = [];

        foreach ($this->importFieldNames() as $fieldName) {
            $lineValue = $this->cleanTextValue($lineData[$fieldName] ?? null);
            $commonValue = $this->cleanTextValue($commonImportData[$fieldName] ?? null);

            $value = $lineValue !== null
                ? $lineValue
                : ($applyCommonImportToAll ? $commonValue : null);

            if ($fieldName === 'customs_entry_date') {
                $value = $this->normalizeOptionalDate($value, 'fecha de pedimento');
            }

            if ($fieldName === 'motor_number' && $trackingType === 'serial' && $value !== null) {
                $motors = $this->parseSimpleList($value);

                if (count($motors) > 1) {
                    if (count($serials) > 0 && count($motors) !== count($serials)) {
                        throw new RuntimeException('Si capturas varios números de motor, deben coincidir con la cantidad de números de serie.');
                    }

                    $value = json_encode($motors);
                }
            }

            $data[$fieldName] = $value;
        }

        return $data;
    }

    protected function validateAdvancedTrackingForLine(
        object $line,
        string $trackingType,
        array $serials,
        array $importData,
        array $config
    ): void {
        $mode = (string) ($config['mode'] ?? 'none');
        $fields = $config['fields'] ?? [];

        if ($mode !== 'required' || ! is_array($fields) || count($fields) === 0) {
            return;
        }

        $missing = [];

        foreach ($fields as $fieldName) {
            $fieldName = (string) $fieldName;

            if ($fieldName === 'serial_number') {
                if ($trackingType !== 'serial' || count($serials) === 0) {
                    $missing[] = 'VIN / número de serie';
                }

                continue;
            }

            $value = $importData[$fieldName] ?? null;

            if ($value === null || trim((string) $value) === '') {
                $missing[] = $this->advancedTrackingFieldLabel($fieldName);
            }
        }

        if ($missing) {
            throw new RuntimeException(
                'Faltan datos obligatorios de trazabilidad para "' .
                ($line->product_label ?? 'producto') .
                '": ' .
                implode(', ', $missing) .
                '.'
            );
        }
    }

    protected function advancedTrackingConfigForLine(object $line): array
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'advanced_tracking_mode')) {
            return ['mode' => 'none', 'fields' => []];
        }

        foreach (['product_variant_id', 'variant_id', 'product_id'] as $field) {
            $id = (int) ($line->{$field} ?? 0);

            if ($id <= 0) {
                continue;
            }

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

    protected function advancedTrackingFieldLabel(string $fieldName): string
    {
        return match ($fieldName) {
            'serial_number' => 'VIN / número de serie',
            'motor_number' => 'Número de motor',
            'customs_entry_number' => 'Número de pedimento',
            'customs_entry_date' => 'Fecha de pedimento',
            'customs_office' => 'Aduana',
            'imported_model' => 'Modelo importado',
            'imported_color' => 'Color importado',
            'import_document_reference' => 'Referencia documento',
            default => $fieldName,
        };
    }

    protected function cleanTextValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function parseSimpleList(mixed $value): array
    {
        if (is_array($value)) {
            $raw = implode("\n", $value);
        } else {
            $raw = (string) $value;
        }

        $raw = str_replace([';', "\t"], "\n", $raw);
        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];

        return array_values(array_filter(array_map(fn ($part) => trim((string) $part), $parts)));
    }

    protected function normalizeOptionalDate(mixed $value, string $label): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new RuntimeException('La ' . $label . ' debe tener formato AAAA-MM-DD.');
        }

        return $value;
    }


    protected function updateOrderLineReceipt(object $line, float $receiveQty, float $receiveBaseQty): void
    {
        $columns = Schema::getColumnListing('purchase_order_lines');

        $orderedQty = (float) ($line->ordered_quantity ?? 0);
        $newReceived = round((float) ($line->received_quantity ?? 0) + $receiveQty, 6);
        $newBaseReceived = round((float) ($line->received_base_quantity ?? 0) + $receiveBaseQty, 6);

        if ($newReceived <= 0) {
            $lineStatus = 'pending';
        } elseif ($newReceived + 0.000001 >= $orderedQty) {
            $lineStatus = 'received';
        } else {
            $lineStatus = 'partial';
        }

        $updates = [];

        $this->set($updates, $columns, 'received_quantity', $newReceived);
        $this->set($updates, $columns, 'received_base_quantity', $newBaseReceived);
        $this->set($updates, $columns, 'receipt_status', $lineStatus);
        $this->set($updates, $columns, 'last_received_at', now());
        $this->set($updates, $columns, 'updated_at', now());

        DB::table('purchase_order_lines')
            ->where('id', $line->id)
            ->update($updates);
    }

    protected function refreshOrderReceiptStatus(int $purchaseOrderId): void
    {
        $lines = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $purchaseOrderId)
            ->get();

        $totalOrdered = 0.0;
        $totalReceived = 0.0;

        foreach ($lines as $line) {
            $totalOrdered += (float) ($line->ordered_quantity ?? 0);
            $totalReceived += (float) ($line->received_quantity ?? 0);
        }

        if ($totalOrdered <= 0) {
            $status = 'confirmed';
        } elseif ($totalReceived <= 0) {
            $status = 'confirmed';
        } elseif ($totalReceived + 0.000001 >= $totalOrdered) {
            $status = 'received';
        } else {
            $status = 'partially_received';
        }

        $columns = Schema::getColumnListing('purchase_orders');

        $updates = [];

        $this->set($updates, $columns, 'status', $status);
        $this->set($updates, $columns, 'updated_at', now());

        DB::table('purchase_orders')
            ->where('id', $purchaseOrderId)
            ->update($updates);
    }

    protected function linesForReceipt(int $purchaseOrderId)
    {
        return DB::table('purchase_order_lines')
            ->where('purchase_order_id', $purchaseOrderId)
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

                return $line;
            });
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

    protected function baseFactor(object $line): float
    {
        $factor = (float) ($line->purchase_unit_factor ?? 0);

        if ($factor > 0) {
            return $factor;
        }

        $ordered = (float) ($line->ordered_quantity ?? 0);
        $base = (float) ($line->base_quantity ?? 0);

        if ($ordered > 0 && $base > 0) {
            return $base / $ordered;
        }

        return 1.0;
    }

    protected function nextReceiptNumber(object $order): string
    {
        $companyId = (int) ($order->company_id ?? 0);
        $warehouseId = (int) ($order->warehouse_id ?? 0);
        $locationId = (int) ($order->location_id ?? 0);
        $prefix = $this->receiptReferencePrefix($order);

        $max = 0;

        foreach ([
            ['purchase_receipts', 'number'],
            ['stock_movements', 'reference'],
        ] as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $query = DB::table($table)
                ->where($column, 'like', $prefix . '/IN/%');

            if ($table === 'purchase_receipts' && $companyId > 0 && Schema::hasColumn($table, 'company_id')) {
                $query->where('company_id', $companyId);
            }

            if ($table === 'stock_movements') {
                if ($companyId > 0 && Schema::hasColumn($table, 'company_id')) {
                    $query->where('company_id', $companyId);
                }

                if ($warehouseId > 0 && Schema::hasColumn($table, 'warehouse_id')) {
                    $query->where('warehouse_id', $warehouseId);
                }
            }

            $values = $query->pluck($column);

            foreach ($values as $value) {
                if (preg_match('#/IN/(\d+)$#', (string) $value, $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            }
        }

        return $prefix . '/IN/' . str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }

    protected function receiptReferencePrefix(object $order): string
    {
        $locationId = (int) ($order->location_id ?? 0);

        if ($locationId > 0 && Schema::hasTable('stock_locations')) {
            $location = DB::table('stock_locations')->where('id', $locationId)->first();

            foreach (['code', 'barcode', 'reference'] as $field) {
                $value = trim((string) ($location->{$field} ?? ''));

                if ($value !== '') {
                    return $this->normalizeReferencePrefix($value);
                }
            }

            $name = trim((string) ($location->name ?? ''));

            if ($name !== '') {
                return $this->normalizeReferencePrefix(str_contains($name, ' - ') ? explode(' - ', $name)[0] : $name);
            }
        }

        $locationLabel = trim((string) ($order->location_label ?? ''));

        if ($locationLabel !== '') {
            return $this->normalizeReferencePrefix(str_contains($locationLabel, ' - ') ? explode(' - ', $locationLabel)[0] : $locationLabel);
        }

        $warehouseId = (int) ($order->warehouse_id ?? 0);

        if ($warehouseId > 0 && Schema::hasTable('warehouses')) {
            $warehouse = DB::table('warehouses')->where('id', $warehouseId)->first();

            foreach (['code', 'short_code', 'reference'] as $field) {
                $value = trim((string) ($warehouse->{$field} ?? ''));

                if ($value !== '') {
                    return $this->normalizeReferencePrefix($value);
                }
            }
        }

        return 'IN';
    }

    protected function normalizeReferencePrefix(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9]+/', '', $value) ?: 'IN';

        return substr($value, 0, 12);
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

    protected function authorizeTenant(object $order): void
    {
        $tenant = request()->route('tenant');

        if (is_numeric($tenant) && (int) $tenant > 0 && (int) ($order->company_id ?? 0) > 0) {
            abort_if((int) $tenant !== (int) $order->company_id, 403);
        }
    }

    protected function set(array &$array, array $columns, string $column, mixed $value): void
    {
        if (in_array($column, $columns, true)) {
            $array[$column] = $value;
        }
    }
}
