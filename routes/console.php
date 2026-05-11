<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// V5_52_4B_ACCOUNTING_POST_INVOICE
Artisan::command('bexia:accounting-post-invoice {invoice_id}', function ($invoice_id) {
    if (! class_exists(\App\Models\Invoice::class)) {
        $this->error('No existe App\Models\Invoice. Revisa que el módulo de facturas internas esté instalado.');
        return self::FAILURE;
    }

    $invoice = \App\Models\Invoice::query()->find($invoice_id);

    if (! $invoice) {
        $this->error('No se encontró la factura interna ID: ' . $invoice_id);
        return self::FAILURE;
    }

    try {
        $entry = app(\App\Support\Accounting\InvoiceAccountingPoster::class)->post($invoice, null);

        $this->info('Factura contabilizada correctamente.');
        $this->line('Asiento ID: ' . $entry->id);
        $this->line('Número: ' . $entry->entry_number);
        $this->line('Debe: ' . $entry->total_debit);
        $this->line('Haber: ' . $entry->total_credit);

        return self::SUCCESS;
    } catch (Throwable $e) {
        $this->error($e->getMessage());

        return self::FAILURE;
    }
})->purpose('Genera asiento contable RC1 para una factura interna');

// V5_52_4C_ACCOUNTING_INVENTORY_PERPETUAL_COMMANDS
\Illuminate\Support\Facades\Artisan::command('bexia:accounting-inventory-map-check {company_id}', function ($company_id) {
    $companyId = (int) $company_id;

    try {
        $diagnosis = app(\App\Support\Accounting\InventoryAccountingPoster::class)->diagnoseMappings($companyId);

        foreach ($diagnosis as $operation => $sides) {
            $this->line('');
            $this->info('Operacion: ' . $operation);

            foreach ($sides as $side => $data) {
                if ($data['ok'] ?? false) {
                    $this->line(
                        '  ' . $side
                        . ' / ' . $data['mapping_key']
                        . ' => ' . ($data['account_code'] ?? '')
                        . ' ' . ($data['account_name'] ?? '')
                        . ' [ID ' . ($data['account_id'] ?? '') . ']'
                    );
                } else {
                    $this->error(
                        '  ' . $side
                        . ' / ' . $data['mapping_key']
                        . ' => FALTA: ' . ($data['error'] ?? 'sin detalle')
                    );
                }
            }
        }

        return self::SUCCESS;
    } catch (\Throwable $e) {
        $this->error($e->getMessage());

        return self::FAILURE;
    }
})->purpose('Diagnostica mapeos contables para inventario perpetuo RC1');

\Illuminate\Support\Facades\Artisan::command('bexia:accounting-post-inventory {company_id} {operation} {amount} {--source_type=manual_inventory} {--source_id=} {--source_line_id=} {--product_id=} {--quantity=} {--unit_cost=} {--currency=MXN} {--movement_date=} {--label=}', function ($company_id, $operation, $amount) {
    $payload = [
        'company_id' => (int) $company_id,
        'operation_type' => (string) $operation,
        'amount' => (float) $amount,
        'source_type' => (string) $this->option('source_type'),
        'source_id' => $this->option('source_id'),
        'source_line_id' => $this->option('source_line_id'),
        'product_id' => $this->option('product_id'),
        'quantity' => $this->option('quantity'),
        'unit_cost' => $this->option('unit_cost'),
        'currency' => (string) $this->option('currency'),
        'movement_date' => $this->option('movement_date'),
        'label' => $this->option('label') ?: null,
    ];

    try {
        $entry = app(\App\Support\Accounting\InventoryAccountingPoster::class)->post($payload, null);

        $this->info('Movimiento de inventario contabilizado correctamente.');
        $this->line('Asiento ID: ' . $entry->id);
        $this->line('Numero: ' . $entry->entry_number);
        $this->line('Debe: ' . $entry->total_debit);
        $this->line('Haber: ' . $entry->total_credit);

        return self::SUCCESS;
    } catch (\Throwable $e) {
        $this->error($e->getMessage());

        return self::FAILURE;
    }
})->purpose('Genera asiento de inventario perpetuo RC1');

// V5_52_4F_INVENTORY_PURCHASE_SALES_COMMANDS
\Illuminate\Support\Facades\Artisan::command('bexia:accounting-post-purchase-order-receipt {purchase_order_id} {--dry-run} {--force}', function ($purchase_order_id) {
    try {
        $summary = app(\App\Support\Accounting\InventoryDocumentAccountingPoster::class)
            ->postPurchaseOrderReceipt(
                (int) $purchase_order_id,
                (bool) $this->option('dry-run'),
                (bool) $this->option('force')
            );

        $this->info('Proceso compra/inventario terminado.');
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return empty($summary['errors']) ? self::SUCCESS : self::FAILURE;
    } catch (\Throwable $e) {
        $this->error($e->getMessage());

        return self::FAILURE;
    }
})->purpose('Contabiliza entrada de inventario desde líneas recibidas de una orden de compra');

\Illuminate\Support\Facades\Artisan::command('bexia:accounting-post-sales-order-cost {sales_order_id} {--dry-run} {--force}', function ($sales_order_id) {
    try {
        $summary = app(\App\Support\Accounting\InventoryDocumentAccountingPoster::class)
            ->postSalesOrderCost(
                (int) $sales_order_id,
                (bool) $this->option('dry-run'),
                (bool) $this->option('force')
            );

        $this->info('Proceso venta/costo terminado.');
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return empty($summary['errors']) ? self::SUCCESS : self::FAILURE;
    } catch (\Throwable $e) {
        $this->error($e->getMessage());

        return self::FAILURE;
    }
})->purpose('Contabiliza costo de venta desde líneas entregadas de una orden de venta');

// V5_52_4H_POS_INVENTORY_COMMANDS
\Illuminate\Support\Facades\Artisan::command('bexia:accounting-post-pos-order-cost {pos_order_id} {--dry-run} {--force}', function ($pos_order_id) {
    try {
        $summary = app(\App\Support\Accounting\InventoryPosAccountingPoster::class)
            ->postPosOrderCost(
                (int) $pos_order_id,
                (bool) $this->option('dry-run'),
                (bool) $this->option('force')
            );

        $this->info('Proceso POS/costo terminado.');
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return empty($summary['errors']) ? self::SUCCESS : self::FAILURE;
    } catch (\Throwable $e) {
        $this->error($e->getMessage());

        return self::FAILURE;
    }
})->purpose('Contabiliza costo de venta desde líneas de ticket POS pagado');

\Illuminate\Support\Facades\Artisan::command('bexia:accounting-post-pos-refund-return {pos_order_refund_id} {--dry-run} {--force}', function ($pos_order_refund_id) {
    try {
        $summary = app(\App\Support\Accounting\InventoryPosAccountingPoster::class)
            ->postPosRefundReturn(
                (int) $pos_order_refund_id,
                (bool) $this->option('dry-run'),
                (bool) $this->option('force')
            );

        $this->info('Proceso POS/devolución inventario terminado.');
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return empty($summary['errors']) ? self::SUCCESS : self::FAILURE;
    } catch (\Throwable $e) {
        $this->error($e->getMessage());

        return self::FAILURE;
    }
})->purpose('Contabiliza entrada de inventario por devolución de POS');
