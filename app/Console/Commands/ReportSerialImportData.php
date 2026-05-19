<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportSerialImportData extends Command
{
    protected $signature = 'bexia:report-serial-import-data
        {--receipt-id= : ID de recepción de compra}
        {--receipt-number= : Folio de recepción, ej. CDF/IN/000009}
        {--serial= : Número de serie específico}
        {--output=/tmp/serial_import_report.csv : Ruta CSV de salida}';

    protected $description = 'Genera reporte CSV de números de serie con datos de importación';

    public function handle(): int
    {
        if (! Schema::hasTable('stock_serial_numbers')) {
            $this->error('No existe la tabla stock_serial_numbers.');
            return self::FAILURE;
        }

        $output = (string) $this->option('output');
        $receiptId = trim((string) $this->option('receipt-id'));
        $receiptNumber = trim((string) $this->option('receipt-number'));
        $serialFilter = trim((string) $this->option('serial'));

        $query = DB::table('stock_serial_numbers')->orderBy('id');

        if ($serialFilter !== '') {
            $query->whereRaw('LOWER(serial_number) = LOWER(?)', [$serialFilter]);
        }

        if ($receiptId !== '' && Schema::hasColumn('stock_serial_numbers', 'purchase_receipt_id')) {
            $query->where('purchase_receipt_id', (int) $receiptId);
        }

        if ($receiptNumber !== '' && Schema::hasTable('purchase_receipts') && Schema::hasColumn('stock_serial_numbers', 'purchase_receipt_id')) {
            $receipt = DB::table('purchase_receipts')
                ->where('number', $receiptNumber)
                ->first(['id']);

            if (! $receipt) {
                $this->error("No se encontró la recepción: {$receiptNumber}");
                return self::FAILURE;
            }

            $query->where('purchase_receipt_id', $receipt->id);
        }

        $serials = $query->get();

        $handle = fopen($output, 'wb');

        if (! $handle) {
            $this->error("No se pudo escribir el archivo: {$output}");
            return self::FAILURE;
        }

        fputcsv($handle, [
            'stock_serial_id',
            'company_id',
            'receipt_id',
            'receipt_number',
            'product_id',
            'product',
            'variant_id',
            'serial_number',
            'status',
            'warehouse_id',
            'location_id',
            'motor_number',
            'customs_entry_number',
            'customs_entry_date',
            'customs_office',
            'imported_model',
            'imported_color',
            'import_document_reference',
            'missing_fields',
        ]);

        $rows = 0;
        $rowsWithMissing = 0;

        foreach ($serials as $serial) {
            $receiptNumberValue = '';
            $productName = '';

            if (Schema::hasTable('purchase_receipts') && ! empty($serial->purchase_receipt_id)) {
                $receipt = DB::table('purchase_receipts')
                    ->where('id', $serial->purchase_receipt_id)
                    ->first(['number']);

                $receiptNumberValue = (string) ($receipt->number ?? '');
            }

            if (Schema::hasTable('products') && ! empty($serial->product_id)) {
                $product = DB::table('products')
                    ->where('id', $serial->product_id)
                    ->first(['internal_reference', 'sku', 'name']);

                $productName = trim(implode(' - ', array_filter([
                    $product->internal_reference ?? null,
                    $product->name ?? null,
                ])));
            }

            $missing = [];

            foreach ([
                'motor_number' => 'motor',
                'customs_entry_number' => 'pedimento',
                'customs_entry_date' => 'fecha_pedimento',
                'customs_office' => 'aduana',
                'imported_model' => 'modelo',
                'imported_color' => 'color',
            ] as $field => $label) {
                if (Schema::hasColumn('stock_serial_numbers', $field) && trim((string) ($serial->{$field} ?? '')) === '') {
                    $missing[] = $label;
                }
            }

            if ($missing !== []) {
                $rowsWithMissing++;
            }

            fputcsv($handle, [
                $serial->id ?? '',
                $serial->company_id ?? '',
                $serial->purchase_receipt_id ?? '',
                $receiptNumberValue,
                $serial->product_id ?? '',
                $productName,
                $serial->product_variant_id ?? '',
                $serial->serial_number ?? '',
                $serial->status ?? '',
                $serial->current_warehouse_id ?? '',
                $serial->current_location_id ?? '',
                $serial->motor_number ?? '',
                $serial->customs_entry_number ?? '',
                $serial->customs_entry_date ?? '',
                $serial->customs_office ?? '',
                $serial->imported_model ?? '',
                $serial->imported_color ?? '',
                $serial->import_document_reference ?? '',
                implode(', ', $missing),
            ]);

            $rows++;
        }

        fclose($handle);

        $this->info('Reporte generado correctamente.');
        $this->line("Archivo: {$output}");
        $this->line("Series incluidas: {$rows}");
        $this->line("Series con campos faltantes: {$rowsWithMissing}");

        return self::SUCCESS;
    }
}
