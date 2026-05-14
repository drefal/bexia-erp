<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class InternalInvoiceBuilder
{
    public function createFromPosOrder(int $posOrderId, ?int $userId = null): int
    {
        $this->assertReady();

        $existing = DB::table('invoices')
            ->where('source_type', 'pos_order')
            ->where('source_id', $posOrderId)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $order = DB::table('pos_orders')->where('id', $posOrderId)->first();

        if (! $order) {
            throw new RuntimeException("No existe pos_order {$posOrderId}.");
        }

        if ((string) ($order->status ?? '') !== 'paid') {
            throw new RuntimeException('Solo se pueden facturar tickets PDV pagados.');
        }

        $companyId = (int) ($order->company_id ?? 0);
        $company = DB::table('companies')->where('id', $companyId)->first();
        $contact = ! empty($order->customer_id) ? DB::table('contacts')->where('id', (int) $order->customer_id)->first() : null;

        $lines = DB::table('pos_order_lines')
            ->where('pos_order_id', $posOrderId)
            ->orderBy('id')
            ->get();

        if ($lines->isEmpty()) {
            throw new RuntimeException('El ticket no tiene lineas para facturar.');
        }

        $payments = Schema::hasTable('pos_order_payments')
            ? DB::table('pos_order_payments')->where('pos_order_id', $posOrderId)->orderBy('id')->get()
            : collect();

        $subtotal = round((float) ($order->subtotal ?? $lines->sum('subtotal')), 4);
        $taxTotal = round((float) ($order->tax_total ?? $lines->sum('tax_total')), 4);
        $total = round((float) ($order->total ?? $lines->sum('total')), 4);
        $paidTotal = round((float) $payments->sum('amount'), 4);

        if ($paidTotal <= 0) {
            $paidTotal = $total;
        }

        /*
         * BEXIA_V5525J2_POS_CFDI_PAYMENT_DATA
         * Todo ticket PDV pagado se considera PUE + Pago inmediato.
         * La forma de pago SAT se toma del pago de mayor importe.
         */
        [$cfdiPaymentFormCode, $cfdiPaymentMethodCode, $cfdiPaymentTerms] = $this->resolvePosCfdiPaymentData($payments, $companyId);

        $invoiceId = (int) DB::transaction(function () use ($order, $companyId, $company, $contact, $lines, $payments, $subtotal, $taxTotal, $total, $paidTotal, $userId, $cfdiPaymentFormCode, $cfdiPaymentMethodCode, $cfdiPaymentTerms) {
            $invoiceId = DB::table('invoices')->insertGetId([
                'company_id' => $companyId,
                'contact_id' => $contact ? (int) $contact->id : null,
                'number' => $this->nextNumber($companyId),
                'status' => 'draft',
                'source_type' => 'pos_order',
                'source_id' => (int) $order->id,
                'source_number' => (string) ($order->number ?? ''),
                'invoice_date' => now()->toDateString(),
                'currency_code' => (string) ($order->currency_code ?? 'MXN'),
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'tax_total' => $taxTotal,
                'total' => $total,
                'paid_total' => $paidTotal,
                'balance_total' => round($total - $paidTotal, 4),
                'issuer_name' => (string) ($company->business_name ?? $company->name ?? ''),
                'issuer_tax_id' => (string) ($company->tax_id ?? ''),
                'issuer_tax_regime' => (string) ($company->tax_regime ?? ''),
                'issuer_postal_code' => (string) ($company->fiscal_postal_code ?? $company->postal_code ?? ''),
                'customer_name' => (string) ($contact->name ?? 'Publico general'),
                'customer_fiscal_name' => (string) ($contact->fiscal_name ?? $contact->name ?? ''),
                'customer_rfc' => (string) ($contact->rfc ?? ''),
                'customer_tax_regime_code' => (string) ($contact->sat_tax_regime_code ?? ''),
                'customer_cfdi_use_code' => (string) ($contact->customer_cfdi_use_code ?? $contact->sat_cfdi_use_code ?? ''),
                'customer_postal_code' => (string) ($contact->fiscal_zip ?? $contact->postal_code ?? ''),
                'payment_form_code' => $cfdiPaymentFormCode,
                'payment_method_code' => $cfdiPaymentMethodCode,
                'payment_terms' => $cfdiPaymentTerms,
                'created_by_user_id' => $userId ?: auth()->id(),
                'metadata' => json_encode([
                    'source' => 'internal_invoice_builder',
                    'source_type' => 'pos_order',
                    'pos_order_id' => (int) $order->id,
                    'pos_order_number' => (string) ($order->number ?? ''),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $productIds = $lines->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
            $products = $productIds->isNotEmpty()
                ? DB::table('products')->whereIn('id', $productIds->all())->get()->keyBy('id')
                : collect();

            foreach ($lines as $line) {
                $product = ! empty($line->product_id) ? $products->get((int) $line->product_id) : null;
                $qty = (float) ($line->quantity ?? 0);
                $subtotalLine = (float) ($line->subtotal ?? 0);

                DB::table('invoice_lines')->insert([
                    'invoice_id' => $invoiceId,
                    'company_id' => $companyId,
                    'source_type' => 'pos_order_line',
                    'source_line_id' => (int) $line->id,
                    'product_id' => ! empty($line->product_id) ? (int) $line->product_id : null,
                    'product_name' => (string) ($line->product_name ?? $product->name ?? 'Producto'),
                    'description' => (string) ($line->product_name ?? $product->name ?? 'Producto'),
                    'quantity' => $qty,
                    'unit_price_without_tax' => $qty > 0 ? round($subtotalLine / $qty, 4) : 0,
                    'unit_price' => (float) ($line->unit_price ?? 0),
                    'tax_rate' => 0,
                    'subtotal' => $subtotalLine,
                    'discount_total' => 0,
                    'tax_total' => (float) ($line->tax_total ?? 0),
                    'total' => (float) ($line->total ?? 0),
                    'sat_product_service_code' => (string) ($product->sat_product_service_code ?? ''),
                    'sat_unit_code' => (string) ($product->sat_unit_code ?? ''),
                    'sat_tax_object_code' => (string) ($product->sat_tax_object_code ?? ''),
                    'metadata' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($payments as $payment) {
                DB::table('invoice_payments')->insert([
                    'invoice_id' => $invoiceId,
                    'company_id' => $companyId,
                    'source_type' => 'pos_order_payment',
                    'source_payment_id' => (int) $payment->id,
                    'payment_form_id' => ! empty($payment->payment_form_id) ? (int) $payment->payment_form_id : null,
                    'payment_label' => (string) ($payment->payment_label ?? ''),
                    'payment_form_code' => $this->resolvePosPaymentFormCode($payment, $companyId),
                    'amount' => (float) ($payment->amount ?? 0),
                    'status' => (string) ($payment->status ?? 'paid'),
                    'paid_at' => $payment->created_at ?? now(),
                    'metadata' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (Schema::hasColumn('pos_orders', 'metadata')) {
                $metadata = json_decode((string) ($order->metadata ?? ''), true);
                $metadata = is_array($metadata) ? $metadata : [];
                $invoice = DB::table('invoices')->where('id', $invoiceId)->first();

                $metadata['billing_status'] = 'internal_invoice_draft';
                $metadata['internal_invoice_id'] = $invoiceId;
                $metadata['internal_invoice_number'] = (string) ($invoice->number ?? '');
                $metadata['internal_invoice_created_at'] = now()->toDateTimeString();

                DB::table('pos_orders')
                    ->where('id', (int) $order->id)
                    ->update([
                        'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'updated_at' => now(),
                    ]);
            }

            return $invoiceId;
        });
        /*
         * BEXIA_V5525J4B_RETURN_INVOICE_ID_AFTER_POS_CREATE
         * createFromPosOrder debe devolver siempre el ID de factura creada/existente.
         */
        return (int) $invoiceId;

    }


    public function createFromSalesOrder(int $salesOrderId, ?int $userId = null): int
    {
        $this->assertReady();

        if (! Schema::hasTable('sales_orders') || ! Schema::hasTable('sales_order_lines')) {
            throw new RuntimeException('Faltan tablas de ventas.');
        }

        $existing = DB::table('invoices')
            ->where('source_type', 'sales_order')
            ->where('source_id', $salesOrderId)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $order = DB::table('sales_orders')->where('id', $salesOrderId)->first();

        if (! $order) {
            throw new RuntimeException("No existe sales_order {$salesOrderId}.");
        }

        if ((string) ($order->status ?? '') === 'draft') {
            throw new RuntimeException('No se puede facturar una venta en borrador.');
        }

        $companyId = (int) ($order->company_id ?? 0);

        if ($companyId <= 0) {
            throw new RuntimeException('La venta no tiene company_id.');
        }

        $company = DB::table('companies')->where('id', $companyId)->first();

        if (! $company) {
            throw new RuntimeException("No existe company {$companyId}.");
        }

        $contactId = (int) ($order->customer_contact_id ?? 0);
        $contact = $contactId > 0 && Schema::hasTable('contacts')
            ? DB::table('contacts')->where('id', $contactId)->first()
            : null;

        $lines = DB::table('sales_order_lines')
            ->where('sales_order_id', $salesOrderId)
            ->orderBy('id')
            ->get();

        if ($lines->isEmpty()) {
            throw new RuntimeException('La venta no tiene líneas para facturar.');
        }

        $subtotal = round((float) ($order->total_without_tax ?? $lines->sum('line_total_without_tax')), 4);
        $taxTotal = round((float) ($order->total_tax ?? $lines->sum('line_tax')), 4);
        $total = round((float) ($order->total_with_tax ?? $lines->sum('line_total_with_tax')), 4);

        return (int) DB::transaction(function () use (
            $order,
            $companyId,
            $company,
            $contact,
            $lines,
            $subtotal,
            $taxTotal,
            $total,
            $userId
        ) {
            $invoiceId = DB::table('invoices')->insertGetId([
                'company_id' => $companyId,
                'contact_id' => $contact ? (int) $contact->id : null,
                'number' => $this->nextNumber($companyId),
                'status' => 'draft',
                'source_type' => 'sales_order',
                'source_id' => (int) $order->id,
                'source_number' => (string) ($order->number ?? ''),
                'invoice_date' => substr((string) ($order->order_date ?? now()->toDateString()), 0, 10),
                'currency_code' => (string) ($order->currency ?? 'MXN'),
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'tax_total' => $taxTotal,
                'total' => $total,
                'paid_total' => 0,
                'balance_total' => $total,
                'issuer_name' => (string) ($company->business_name ?? $company->name ?? ''),
                'issuer_tax_id' => (string) ($company->tax_id ?? ''),
                'issuer_tax_regime' => (string) ($company->tax_regime ?? ''),
                'issuer_postal_code' => (string) ($company->fiscal_postal_code ?? $company->postal_code ?? ''),
                'customer_name' => (string) ($order->customer_name ?? $contact->name ?? 'Cliente'),
                'customer_fiscal_name' => (string) ($contact->fiscal_name ?? $order->customer_name ?? $contact->name ?? ''),
                'customer_rfc' => (string) ($contact->rfc ?? ''),
                'customer_tax_regime_code' => (string) ($contact->sat_tax_regime_code ?? ''),
                'customer_cfdi_use_code' => (string) ($order->fiscal_position ?? $contact->customer_cfdi_use_code ?? $contact->sat_cfdi_use_code ?? ''),
                'customer_postal_code' => (string) ($contact->fiscal_zip ?? $contact->postal_code ?? ''),
                'payment_form_code' => (string) ($contact->customer_payment_form_code ?? $contact->payment_form_code ?? ''),
                'payment_method_code' => (string) ($order->payment_method ?? $contact->customer_payment_method_code ?? $contact->payment_method_code ?? ''),
                'payment_terms' => (string) ($order->payment_terms ?? $contact->customer_payment_terms_text ?? ''),
                'created_by_user_id' => $userId ?: auth()->id(),
                'metadata' => json_encode([
                    'source' => 'internal_invoice_builder',
                    'source_type' => 'sales_order',
                    'sales_order_id' => (int) $order->id,
                    'sales_order_number' => (string) ($order->number ?? ''),
                    'note' => 'Factura interna sin cobranza asociada todavía; sales_order_payments no existe.',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $productIds = $lines->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
            $products = $productIds->isNotEmpty()
                ? DB::table('products')->whereIn('id', $productIds->all())->get()->keyBy('id')
                : collect();

            foreach ($lines as $line) {
                $product = ! empty($line->product_id) ? $products->get((int) $line->product_id) : null;

                $name = trim((string) (($line->product_label ?? '') . ' ' . ($line->variant_label ?? '')));

                if ($name === '') {
                    $name = (string) ($product->name ?? 'Producto');
                }

                DB::table('invoice_lines')->insert([
                    'invoice_id' => $invoiceId,
                    'company_id' => $companyId,
                    'source_type' => 'sales_order_line',
                    'source_line_id' => (int) $line->id,
                    'product_id' => ! empty($line->product_id) ? (int) $line->product_id : null,
                    'product_name' => $name,
                    'description' => $name,
                    'quantity' => (float) ($line->quantity ?? 0),
                    'unit_price_without_tax' => (float) ($line->unit_price_without_tax ?? 0),
                    'unit_price' => (float) ($line->unit_price_with_tax ?? 0),
                    'tax_rate' => (float) ($line->tax_rate ?? 0),
                    'subtotal' => (float) ($line->line_total_without_tax ?? 0),
                    'discount_total' => 0,
                    'tax_total' => (float) ($line->line_tax ?? 0),
                    'total' => (float) ($line->line_total_with_tax ?? 0),
                    'sat_product_service_code' => (string) ($product->sat_product_service_code ?? ''),
                    'sat_unit_code' => (string) ($product->sat_unit_code ?? ''),
                    'sat_tax_object_code' => (string) ($product->sat_tax_object_code ?? ''),
                    'metadata' => json_encode([
                        'delivery_status' => (string) ($line->delivery_status ?? ''),
                        'delivered_quantity' => (float) ($line->delivered_quantity ?? 0),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (Schema::hasColumn('sales_orders', 'invoice_status')) {
                DB::table('sales_orders')
                    ->where('id', (int) $order->id)
                    ->update([
                        'invoice_status' => 'invoiced',
                        'updated_at' => now(),
                    ]);
            }

            return $invoiceId;
        });
    }



    protected function resolvePosCfdiPaymentData($payments, int $companyId): array
    {
        $paymentFormCode = '99';

        if ($payments && method_exists($payments, 'filter')) {
            $payment = $payments
                ->filter(fn ($payment) => (float) ($payment->amount ?? 0) > 0)
                ->sortByDesc(fn ($payment) => (float) ($payment->amount ?? 0))
                ->first();

            if ($payment) {
                $paymentFormCode = $this->resolvePosPaymentFormCode($payment, $companyId);
            }
        }

        return [
            $paymentFormCode ?: '99',
            'PUE',
            $this->immediatePaymentTermName($companyId),
        ];
    }

    protected function resolvePosPaymentFormCode(object $payment, int $companyId): string
    {
        $paymentFormId = ! empty($payment->payment_form_id) ? (int) $payment->payment_form_id : 0;

        if ($paymentFormId > 0 && Schema::hasTable('payment_forms')) {
            $form = DB::table('payment_forms')
                ->where('id', $paymentFormId)
                ->first();

            if ($form) {
                foreach (['sat_payment_form_code', 'code'] as $field) {
                    $value = trim((string) ($form->{$field} ?? ''));

                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        $metadata = json_decode((string) ($payment->metadata ?? ''), true);
        $metadata = is_array($metadata) ? $metadata : [];

        $metadataCode = trim((string) ($metadata['payment_form_code'] ?? ''));

        return $metadataCode !== '' ? $metadataCode : '99';
    }

    protected function immediatePaymentTermName(int $companyId): string
    {
        if (! Schema::hasTable('payment_terms')) {
            return 'Pago inmediato';
        }

        $query = DB::table('payment_terms')
            ->where('code', 'PAGO_INMEDIATO');

        if (Schema::hasColumn('payment_terms', 'company_id')) {
            $query->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        $name = $query
            ->orderByRaw('company_id nulls last')
            ->value('name');

        return trim((string) $name) !== '' ? (string) $name : 'Pago inmediato';
    }
    protected function assertReady(): void
    {
        foreach (['invoices', 'invoice_lines', 'invoice_payments'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Falta tabla {$table}.");
            }
        }
    }

    protected function nextNumber(int $companyId): string
    {
        $prefix = 'FAC-' . now()->format('Ymd') . '-';

        $last = DB::table('invoices')
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if ($last && preg_match('/-(\d+)$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        do {
            $number = $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $exists = DB::table('invoices')
                ->where('company_id', $companyId)
                ->where('number', $number)
                ->exists();

            $next++;
        } while ($exists);

        return $number;
    }
}
