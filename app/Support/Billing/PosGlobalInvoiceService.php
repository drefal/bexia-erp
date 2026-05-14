<?php

namespace App\Support\Billing;

use App\Models\Invoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PosGlobalInvoiceService
{
    public function eligibleTickets(int $companyId, array $filters = []): Collection
    {
        if (! Schema::hasTable('pos_orders')) {
            return collect();
        }

        $query = DB::table('pos_orders as po')
            ->where('po.company_id', $companyId)
            ->where('po.status', 'paid')
            ->whereRaw('COALESCE(po.total, 0) > 0');

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));

        if ($dateFrom !== '') {
            $query->whereDate('po.created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('po.created_at', '<=', $dateTo);
        }

        if (Schema::hasTable('invoices')) {
            $query->leftJoin('invoices as inv_ind', function ($join): void {
                $join->on('inv_ind.source_id', '=', 'po.id')
                    ->where('inv_ind.source_type', '=', 'pos_order');
            })->whereNull('inv_ind.id');
        }

        if (Schema::hasTable('global_invoice_tickets')) {
            $query->leftJoin('global_invoice_tickets as git', function ($join): void {
                $join->on('git.pos_order_id', '=', 'po.id')
                    ->whereIn('git.status', ['draft', 'stamped']);
            })->whereNull('git.id');
        }

        if (Schema::hasColumn('pos_orders', 'global_invoice_id')) {
            $query->whereNull('po.global_invoice_id');
        }

        if (Schema::hasColumn('pos_orders', 'global_invoiced_at')) {
            $query->whereNull('po.global_invoiced_at');
        }

        return $query
            ->select('po.*')
            ->orderBy('po.created_at')
            ->orderBy('po.id')
            ->limit(500)
            ->get()
            ->map(fn ($ticket): array => $this->ticketArray($ticket));
    }

    public function counters(int $companyId, array $filters = []): array
    {
        $tickets = $this->eligibleTickets($companyId, $filters);

        return [
            'count' => $tickets->count(),
            'subtotal' => round((float) $tickets->sum('subtotal'), 4),
            'tax_total' => round((float) $tickets->sum('tax_total'), 4),
            'total' => round((float) $tickets->sum('total'), 4),
        ];
    }

    public function createDraftGlobalInvoice(int $companyId, array $ticketIds, array $data = [], ?int $userId = null): Invoice
    {
        $ticketIds = collect($ticketIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! $ticketIds) {
            throw new RuntimeException('Selecciona al menos un ticket.');
        }

        return DB::transaction(function () use ($companyId, $ticketIds, $data, $userId): Invoice {
            $eligible = $this->eligibleTickets($companyId, [
                'date_from' => $data['date_from'] ?? null,
                'date_to' => $data['date_to'] ?? null,
            ])->keyBy('id');

            $tickets = collect($ticketIds)
                ->map(fn ($id) => $eligible->get($id))
                ->filter()
                ->values();

            if ($tickets->isEmpty()) {
                throw new RuntimeException('Los tickets seleccionados ya no son elegibles.');
            }

            if ($tickets->count() !== count($ticketIds)) {
                throw new RuntimeException('Algunos tickets seleccionados ya no son elegibles. Actualiza la pantalla.');
            }

            $subtotal = round((float) $tickets->sum('subtotal'), 4);
            $taxTotal = round((float) $tickets->sum('tax_total'), 4);
            $total = round((float) $tickets->sum('total'), 4);

            if ($total <= 0) {
                throw new RuntimeException('El total de la factura global debe ser mayor a cero.');
            }

            [$paymentFormCode, $paymentSummary] = $this->dominantPaymentForm($tickets->pluck('id')->all(), $companyId);

            $periodicity = (string) ($data['periodicity'] ?? '01');
            $month = (string) ($data['month'] ?? now()->format('m'));
            $year = (string) ($data['year'] ?? now()->format('Y'));

            $company = DB::table('companies')->where('id', $companyId)->first();

            $invoiceId = DB::table('invoices')->insertGetId([
                'company_id' => $companyId,
                'contact_id' => null,
                'number' => $this->nextNumber($companyId),
                'status' => 'draft',
                'source_type' => 'pos_global_invoice',
                'source_id' => null,
                'source_number' => 'GLOBAL-'.$year.'-'.$month.'-'.now()->format('His'),
                'invoice_date' => now()->toDateString(),
                'currency_code' => 'MXN',
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'tax_total' => $taxTotal,
                'total' => $total,
                'paid_total' => $total,
                'balance_total' => 0,
                'issuer_name' => (string) ($company->business_name ?? $company->name ?? ''),
                'issuer_tax_id' => (string) ($company->tax_id ?? ''),
                'issuer_tax_regime' => (string) ($company->tax_regime ?? ''),
                'issuer_postal_code' => (string) ($company->fiscal_postal_code ?? $company->postal_code ?? ''),
                'customer_name' => 'Público en general',
                'customer_fiscal_name' => 'Público en general',
                'customer_rfc' => 'XAXX010101000',
                'customer_tax_regime_code' => '616',
                'customer_cfdi_use_code' => 'S01',
                'customer_postal_code' => (string) ($company->fiscal_postal_code ?? $company->postal_code ?? ''),
                'payment_form_code' => $paymentFormCode,
                'payment_method_code' => 'PUE',
                'payment_terms' => 'Pago inmediato',
                'created_by_user_id' => $userId ?: auth()->id(),
                'metadata' => json_encode([
                    'source' => 'pos_global_invoice',
                    'is_global_invoice' => true,
                    'global_invoice' => [
                        'periodicity' => $periodicity,
                        'month' => $month,
                        'year' => $year,
                        'ticket_count' => $tickets->count(),
                        'payment_summary' => $paymentSummary,
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($tickets as $ticket) {
                $taxRate = ((float) $ticket['subtotal']) > 0
                    ? round(((float) $ticket['tax_total'] / (float) $ticket['subtotal']) * 100, 6)
                    : 0;

                DB::table('invoice_lines')->insert([
                    'invoice_id' => $invoiceId,
                    'company_id' => $companyId,
                    'source_type' => 'pos_global_ticket',
                    'source_line_id' => (int) $ticket['id'],
                    'product_id' => null,
                    'product_name' => 'Venta público general '.$ticket['number'],
                    'description' => 'Venta público general ticket '.$ticket['number'],
                    'quantity' => 1,
                    'unit_price_without_tax' => (float) $ticket['subtotal'],
                    'unit_price' => (float) $ticket['total'],
                    'tax_rate' => $taxRate,
                    'subtotal' => (float) $ticket['subtotal'],
                    'discount_total' => 0,
                    'tax_total' => (float) $ticket['tax_total'],
                    'total' => (float) $ticket['total'],
                    'sat_product_service_code' => '01010101',
                    'sat_unit_code' => 'ACT',
                    'sat_tax_object_code' => ((float) $ticket['tax_total']) > 0 ? '02' : '01',
                    'metadata' => json_encode([
                        'pos_order_id' => (int) $ticket['id'],
                        'pos_order_number' => $ticket['number'],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                /*
                 * BEXIA_V5526D_GLOBAL_TICKET_RELATION_UPSERT
                 * Permite reutilizar tickets liberados de una factura global cancelada en borrador.
                 */
                $this->storeGlobalInvoiceTicket($ticket, $companyId, $invoiceId, $paymentSummary);

                $this->markTicketGlobalDraft((int) $ticket['id'], $invoiceId);
            }

            DB::table('invoice_payments')->insert([
                'invoice_id' => $invoiceId,
                'company_id' => $companyId,
                'source_type' => 'pos_global_invoice',
                'source_payment_id' => null,
                'payment_form_id' => null,
                'payment_label' => $paymentSummary,
                'payment_form_code' => $paymentFormCode,
                'amount' => $total,
                'status' => 'paid',
                'paid_at' => now(),
                'metadata' => json_encode([
                    'source' => 'pos_global_invoice',
                    'ticket_count' => $tickets->count(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return Invoice::query()->findOrFail($invoiceId);
        });
    }


    public function canCancelDraftGlobalInvoice(Invoice $invoice): bool
    {
        /*
         * BEXIA_V5526D_CAN_CANCEL_GLOBAL_DRAFT
         */
        if ((string) ($invoice->source_type ?? '') !== 'pos_global_invoice') {
            return false;
        }

        if (! empty($invoice->cfdi_uuid)) {
            return false;
        }

        if (in_array((string) ($invoice->cfdi_status ?? ''), ['stamped', 'cancel_requested', 'cancelled'], true)) {
            return false;
        }

        return in_array((string) ($invoice->status ?? ''), ['draft', 'borrador'], true);
    }

    public function cancelDraftGlobalInvoice(Invoice $invoice, ?int $userId = null): void
    {
        /*
         * BEXIA_V5526D_CANCEL_GLOBAL_DRAFT_RELEASE_TICKETS
         */
        DB::transaction(function () use ($invoice, $userId): void {
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->canCancelDraftGlobalInvoice($lockedInvoice)) {
                throw new RuntimeException('Solo se puede cancelar internamente una factura global en borrador y sin timbrar.');
            }

            $relations = Schema::hasTable('global_invoice_tickets')
                ? DB::table('global_invoice_tickets')
                    ->where('invoice_id', (int) $lockedInvoice->id)
                    ->get()
                : collect();

            foreach ($relations as $relation) {
                $this->releaseTicketFromGlobalInvoice((int) $relation->pos_order_id, (int) $lockedInvoice->id);
            }

            if (Schema::hasTable('global_invoice_tickets')) {
                DB::table('global_invoice_tickets')
                    ->where('invoice_id', (int) $lockedInvoice->id)
                    ->update([
                        'status' => 'cancelled',
                        'updated_at' => now(),
                    ]);
            }

            if (Schema::hasTable('invoice_payments')) {
                $paymentUpdate = [
                    'status' => 'cancelled',
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('invoice_payments', 'cancelled_at')) {
                    $paymentUpdate['cancelled_at'] = now();
                }

                DB::table('invoice_payments')
                    ->where('invoice_id', (int) $lockedInvoice->id)
                    ->update($paymentUpdate);
            }

            $invoiceUpdate = [
                'status' => 'cancelled',
                'cfdi_status' => 'cancelled_internal',
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('invoices', 'cancelled_at')) {
                $invoiceUpdate['cancelled_at'] = now();
            }

            if (Schema::hasColumn('invoices', 'metadata')) {
                $metadata = $this->metadataArray($lockedInvoice->metadata ?? null);
                $metadata['global_invoice_cancelled_internal_at'] = now()->toDateTimeString();
                $metadata['global_invoice_cancelled_internal_by_user_id'] = $userId ?: auth()->id();

                $invoiceUpdate['metadata'] = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            DB::table('invoices')
                ->where('id', (int) $lockedInvoice->id)
                ->update($invoiceUpdate);

            if (Schema::hasTable('invoice_cfdi_audits')) {
                DB::table('invoice_cfdi_audits')->insert([
                    'invoice_id' => (int) $lockedInvoice->id,
                    'company_id' => (int) $lockedInvoice->company_id,
                    'user_id' => $userId ?: auth()->id(),
                    'action' => 'cancel_global_invoice_draft',
                    'status' => 'cancelled_internal',
                    'pac_provider' => null,
                    'pac_environment' => null,
                    'message' => 'Factura global en borrador cancelada internamente. Tickets liberados.',
                    'request_meta' => json_encode([
                        'released_tickets' => $relations->pluck('pos_order_id')->values()->all(),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'response_meta' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    private function storeGlobalInvoiceTicket(array $ticket, int $companyId, int $invoiceId, string $paymentSummary): void
    {
        if (! Schema::hasTable('global_invoice_tickets')) {
            return;
        }

        DB::table('global_invoice_tickets')->updateOrInsert(
            [
                'company_id' => $companyId,
                'pos_order_id' => (int) $ticket['id'],
            ],
            [
                'invoice_id' => $invoiceId,
                'ticket_number' => $ticket['number'],
                'ticket_date' => $ticket['created_at'],
                'subtotal' => (float) $ticket['subtotal'],
                'tax_total' => (float) $ticket['tax_total'],
                'total' => (float) $ticket['total'],
                'payment_summary' => $paymentSummary,
                'status' => 'draft',
                'metadata' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function releaseTicketFromGlobalInvoice(int $ticketId, int $invoiceId): void
    {
        if (! Schema::hasTable('pos_orders')) {
            return;
        }

        $ticket = DB::table('pos_orders')
            ->where('id', $ticketId)
            ->first();

        if (! $ticket) {
            return;
        }

        $updates = [
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('pos_orders', 'global_invoice_id')) {
            $updates['global_invoice_id'] = null;
        }

        if (Schema::hasColumn('pos_orders', 'global_invoiced_at')) {
            $updates['global_invoiced_at'] = null;
        }

        if (Schema::hasColumn('pos_orders', 'metadata')) {
            $metadata = $this->metadataArray($ticket->metadata ?? null);

            foreach ([
                'billing_status',
                'global_invoice_id',
                'global_invoice_created_at',
            ] as $key) {
                unset($metadata[$key]);
            }

            $metadata['last_released_global_invoice_id'] = $invoiceId;
            $metadata['last_released_global_invoice_at'] = now()->toDateTimeString();

            $updates['metadata'] = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        DB::table('pos_orders')
            ->where('id', $ticketId)
            ->update($updates);
    }

    private function metadataArray($metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && trim($metadata) !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }


    private function ticketArray(object $ticket): array
    {
        return [
            'id' => (int) $ticket->id,
            'company_id' => (int) $ticket->company_id,
            'number' => (string) ($ticket->number ?? $ticket->id),
            'status' => (string) ($ticket->status ?? ''),
            'subtotal' => round((float) ($ticket->subtotal ?? 0), 4),
            'tax_total' => round((float) ($ticket->tax_total ?? 0), 4),
            'total' => round((float) ($ticket->total ?? 0), 4),
            'currency_code' => (string) ($ticket->currency_code ?? 'MXN'),
            'created_at' => (string) ($ticket->created_at ?? ''),
            'paid_at' => (string) ($ticket->paid_at ?? ''),
        ];
    }

    private function dominantPaymentForm(array $ticketIds, int $companyId): array
    {
        if (! Schema::hasTable('pos_order_payments')) {
            return ['99', 'Por definir'];
        }

        $row = DB::table('pos_order_payments as pop')
            ->leftJoin('payment_forms as pf', 'pf.id', '=', 'pop.payment_form_id')
            ->whereIn('pop.pos_order_id', $ticketIds)
            ->where('pop.status', 'paid')
            ->selectRaw("
                COALESCE(pf.sat_payment_form_code, pf.code, '99') as sat_code,
                COALESCE(pf.name, pop.payment_label, 'Por definir') as label,
                SUM(COALESCE(pop.amount, 0)) as total_amount
            ")
            ->groupBy('sat_code', 'label')
            ->orderByDesc('total_amount')
            ->first();

        if (! $row) {
            return ['99', 'Por definir'];
        }

        return [
            trim((string) ($row->sat_code ?? '99')) ?: '99',
            trim((string) ($row->label ?? 'Por definir')) ?: 'Por definir',
        ];
    }

    private function markTicketGlobalDraft(int $ticketId, int $invoiceId): void
    {
        $ticket = DB::table('pos_orders')->where('id', $ticketId)->first();

        if (! $ticket) {
            return;
        }

        $metadata = json_decode((string) ($ticket->metadata ?? ''), true);
        $metadata = is_array($metadata) ? $metadata : [];

        $metadata['billing_status'] = 'global_invoice_draft';
        $metadata['global_invoice_id'] = $invoiceId;
        $metadata['global_invoice_created_at'] = now()->toDateTimeString();

        $updates = [
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('pos_orders', 'global_invoice_id')) {
            $updates['global_invoice_id'] = $invoiceId;
        }

        DB::table('pos_orders')
            ->where('id', $ticketId)
            ->update($updates);
    }

    private function nextNumber(int $companyId): string
    {
        $prefix = 'GLOB-' . now()->format('Ymd') . '-';

        $last = DB::table('invoices')
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if ($last && preg_match('/-(\d+)$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        do {
            $number = $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $exists = DB::table('invoices')
                ->where('company_id', $companyId)
                ->where('number', $number)
                ->exists();

            $next++;
        } while ($exists);

        return $number;
    }
}
