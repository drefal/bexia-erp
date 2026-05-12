<?php

namespace App\Support\Billing;

use App\Models\BillingSeries;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class BillingSeriesResolver
{
    public function assignFiscalFolio(Invoice $invoice, ?User $user = null, bool $force = false): array
    {
        $invoice->refresh();

        if (! $force && trim((string) ($invoice->cfdi_series ?? '')) !== '' && trim((string) ($invoice->cfdi_folio ?? '')) !== '') {
            return [
                'success' => true,
                'changed' => false,
                'message' => 'La factura ya tiene folio CFDI asignado.',
                'series' => (string) $invoice->cfdi_series,
                'folio' => (string) $invoice->cfdi_folio,
                'display' => $this->display((string) $invoice->cfdi_series, (string) $invoice->cfdi_folio, (int) ($invoice->cfdi_folio_padding ?? 5)),
            ];
        }

        return DB::transaction(function () use ($invoice, $user, $force): array {
            $context = $this->contextFromInvoice($invoice);
            $series = $this->resolveSeries($invoice, $context);

            if (! $series) {
                throw new RuntimeException('No hay serie de facturación activa para esta empresa/sucursal/PDV.');
            }

            $series = BillingSeries::query()
                ->whereKey($series->id)
                ->lockForUpdate()
                ->first();

            if (! $series) {
                throw new RuntimeException('La serie de facturación ya no existe.');
            }

            $number = max(1, (int) ($series->next_number ?: 1));
            $folio = (string) $number;
            $display = $this->display((string) $series->series, $folio, (int) ($series->padding ?: 1));

            $updates = [
                'cfdi_series' => (string) $series->series,
                'cfdi_folio' => $folio,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('invoices', 'cfdi_number_display')) {
                $updates['cfdi_number_display'] = $display;
            }

            if (Schema::hasColumn('invoices', 'billing_series_id')) {
                $updates['billing_series_id'] = (int) $series->id;
            }

            DB::table('invoices')
                ->where('id', (int) $invoice->id)
                ->update($updates);

            $series->forceFill([
                'last_number' => $number,
                'next_number' => $number + 1,
                'last_assigned_at' => now(),
            ])->save();

            $invoice->refresh();

            if (class_exists(InvoiceCfdiValidator::class) && Schema::hasTable('invoice_cfdi_audits')) {
                app(InvoiceCfdiValidator::class)->audit($invoice, $user, [
                    'action' => 'assign_folio',
                    'status' => 'success',
                    'pac_provider' => (string) ($invoice->pac_provider ?? 'sw'),
                    'pac_environment' => (string) ($invoice->pac_environment ?? ''),
                    'message' => 'Folio CFDI asignado desde serie de facturación.',
                    'request_meta' => [
                        'invoice_id' => (int) $invoice->id,
                        'invoice_number' => (string) ($invoice->number ?? ''),
                        'company_id' => (int) ($invoice->company_id ?? 0),
                        'context' => $context,
                        'force' => $force,
                    ],
                    'response_meta' => [
                        'billing_series_id' => (int) $series->id,
                        'series' => (string) $series->series,
                        'folio' => $folio,
                        'display' => $display,
                        'next_number_after' => (int) $series->next_number,
                    ],
                ]);
            }

            return [
                'success' => true,
                'changed' => true,
                'message' => 'Folio CFDI asignado correctamente: ' . $display,
                'series' => (string) $series->series,
                'folio' => $folio,
                'display' => $display,
                'billing_series_id' => (int) $series->id,
            ];
        });
    }

    public function resolveSeries(Invoice $invoice, array $context = []): ?BillingSeries
    {
        $companyId = (int) ($invoice->company_id ?? 0);

        if ($companyId <= 0) {
            return null;
        }

        $branchId = (int) ($context['branch_id'] ?? 0);
        $posPointId = (int) ($context['pos_point_id'] ?? 0);

        $series = BillingSeries::query()
            ->where('company_id', $companyId)
            ->where('document_type', 'invoice')
            ->where('active', true)
            ->orderByDesc('pos_point_id')
            ->orderByDesc('branch_id')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        if ($series->isEmpty()) {
            return null;
        }

        if ($posPointId > 0) {
            $match = $series->first(fn (BillingSeries $row): bool => (int) ($row->pos_point_id ?? 0) === $posPointId);
            if ($match) {
                return $match;
            }
        }

        if ($branchId > 0) {
            $match = $series->first(fn (BillingSeries $row): bool => (int) ($row->branch_id ?? 0) === $branchId && blank($row->pos_point_id));
            if ($match) {
                return $match;
            }
        }

        $default = $series->first(fn (BillingSeries $row): bool => (bool) $row->is_default && blank($row->branch_id) && blank($row->pos_point_id));

        if ($default) {
            return $default;
        }

        return $series->first();
    }

    public function contextFromInvoice(Invoice $invoice): array
    {
        $branchId = $this->firstExistingInt($invoice, [
            'branch_id',
            'store_id',
            'location_id',
            'warehouse_id',
        ]);

        $posPointId = $this->firstExistingInt($invoice, [
            'pos_point_id',
            'point_of_sale_id',
            'pos_id',
        ]);

        if (! $posPointId && (string) ($invoice->source_type ?? '') === 'pos_order' && ! empty($invoice->source_id) && Schema::hasTable('pos_orders')) {
            $order = DB::table('pos_orders')->where('id', (int) $invoice->source_id)->first();

            if ($order) {
                $posPointId = $this->firstExistingInt($order, [
                    'pos_point_id',
                    'point_of_sale_id',
                    'pos_id',
                    'pos_register_id',
                    'register_id',
                ]);

                $branchId = $branchId ?: $this->firstExistingInt($order, [
                    'branch_id',
                    'store_id',
                    'location_id',
                    'warehouse_id',
                ]);
            }
        }

        return [
            'branch_id' => $branchId ?: null,
            'pos_point_id' => $posPointId ?: null,
            'source_type' => (string) ($invoice->source_type ?? ''),
            'source_id' => (int) ($invoice->source_id ?? 0) ?: null,
        ];
    }

    private function firstExistingInt(object $row, array $fields): int
    {
        foreach ($fields as $field) {
            if (isset($row->{$field}) && (int) $row->{$field} > 0) {
                return (int) $row->{$field};
            }
        }

        return 0;
    }

    private function display(string $series, string $folio, int $padding): string
    {
        $folio = preg_replace('/\D+/', '', $folio) ?: $folio;
        $folioPadded = ctype_digit((string) $folio)
            ? str_pad((string) ((int) $folio), max(1, $padding), '0', STR_PAD_LEFT)
            : (string) $folio;

        return trim($series) . '/' . $folioPadded;
    }
}
