<?php

namespace App\Support\Service;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServiceReceivableCreator
{
    public static function createForRepairOrder(int|object $repairOrder, array $options = []): array
    {
        $repairId = is_object($repairOrder)
            ? (int) ($repairOrder->id ?? $repairOrder->getKey())
            : (int) $repairOrder;

        if (! Schema::hasTable('account_receivables')) {
            throw new \RuntimeException('No existe la tabla account_receivables.');
        }

        if (! Schema::hasTable('repair_orders')) {
            throw new \RuntimeException('No existe la tabla repair_orders.');
        }

        return DB::transaction(function () use ($repairId, $options): array {
            $repair = DB::table('repair_orders')
                ->where('id', $repairId)
                ->lockForUpdate()
                ->first();

            if (! $repair) {
                throw new \RuntimeException("No existe la reparación {$repairId}.");
            }

            $companyId = (int) ($repair->company_id ?? 0);

            if ($companyId <= 0) {
                throw new \RuntimeException('La reparación no tiene company_id válido.');
            }

            $total = self::number($repair->total_amount ?? $repair->economic_total ?? 0);

            if ($total <= 0) {
                throw new \RuntimeException('La reparación no tiene total para cobrar. Primero calcula el cierre económico.');
            }

            if ((bool) ($repair->economic_requires_approval ?? false)) {
                throw new \RuntimeException('La reparación requiere aprobación económica antes de crear CxC.');
            }

            $economicStatus = (string) ($repair->economic_status ?? '');

            if (! in_array($economicStatus, ['ready_to_charge', 'receivable_created'], true)) {
                throw new \RuntimeException('La reparación no está lista para cobrar.');
            }

            $existing = self::existingReceivable($repair);

            if ($existing) {
                self::linkRepairToReceivable($repair, (int) $existing->id, (int) ($options['created_by'] ?? 0), false);

                return [
                    'created' => false,
                    'account_receivable_id' => (int) $existing->id,
                    'number' => $existing->number ?? null,
                    'status' => $existing->status ?? null,
                    'total' => self::number($existing->total ?? 0),
                    'message' => 'Ya existía una CxC para esta reparación.',
                ];
            }

            $serviceCase = self::serviceCase($repair);
            $contact = self::customerContact($repair, $serviceCase);

            $issueDate = Carbon::now()->toDateString();
            $dueDate = (string) ($options['due_date'] ?? $issueDate);

            $subtotal = self::number($repair->economic_subtotal ?? 0);
            $tax = self::number($repair->economic_tax ?? 0);

            if ($subtotal <= 0) {
                $taxRate = self::number($repair->economic_tax_rate ?? 16);
                $subtotal = round($total / (1 + ($taxRate / 100)), 4);
                $tax = round($total - $subtotal, 4);
            }

            $number = self::nextReceivableNumber($companyId, $issueDate);

            $customerName = self::customerName($repair, $serviceCase, $contact);

            $metadata = [
                'created_by' => 'ServiceReceivableCreator',
                'version' => 'v5.74.0c1',
                'source_type' => 'service_repair_order',
                'repair_order_id' => (int) $repair->id,
                'repair_order_folio' => $repair->folio ?? null,
                'service_case_id' => $repair->service_case_id ?? null,
                'economic_status' => $repair->economic_status ?? null,
                'economic_subtotal' => self::number($repair->economic_subtotal ?? 0),
                'economic_tax' => self::number($repair->economic_tax ?? 0),
                'economic_total' => self::number($repair->economic_total ?? 0),
                'parts_profit_amount' => self::number($repair->parts_profit_amount ?? 0),
                'parts_profit_percent' => $repair->parts_profit_percent ?? null,
                'total_profit_amount' => self::number($repair->total_profit_amount ?? 0),
                'total_profit_percent' => $repair->total_profit_percent ?? null,
                'dedupe_rule' => 'source_type_service_repair_order_and_source_id',
            ];

            $row = [
                'company_id' => $companyId,
                'number' => $number,
                'status' => 'open',
                'source_type' => 'service_repair_order',
                'source_id' => (int) $repair->id,
                'sale_order_id' => null,
                'invoice_id' => null,
                'customer_contact_id' => $contact?->id ?? null,
                'customer_name' => $customerName,
                'customer_reference' => $repair->folio ?? ('REP-' . $repair->id),
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'currency' => 'MXN',
                'subtotal' => round($subtotal, 4),
                'tax_total' => round($tax, 4),
                'total' => round($total, 4),
                'collected_total' => 0,
                'balance_total' => round($total, 4),
                'accounting_status' => 'not_posted',
                'accounting_entry_id' => null,
                'accounting_posted_at' => null,
                'accounting_error_message' => null,
                'notes' => 'CxC generada automáticamente desde reparación ' . ($repair->folio ?? ('#' . $repair->id)),
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'created_by_user_id' => $options['created_by'] ?? null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];

            $safeRow = self::safePayload('account_receivables', $row);

            $receivableId = DB::table('account_receivables')->insertGetId($safeRow);

            self::linkRepairToReceivable($repair, (int) $receivableId, (int) ($options['created_by'] ?? 0), true);

            self::logEvent($repair, [
                'event_type' => 'account_receivable_created',
                'description' => 'CxC creada desde reparación: ' . $number . ' por $' . number_format($total, 2),
                'data' => [
                    'account_receivable_id' => $receivableId,
                    'number' => $number,
                    'total' => $total,
                    'customer_name' => $customerName,
                ],
            ]);

            return [
                'created' => true,
                'account_receivable_id' => (int) $receivableId,
                'number' => $number,
                'status' => 'open',
                'total' => round($total, 4),
                'message' => 'CxC creada correctamente.',
            ];
        });
    }

    protected static function existingReceivable(object $repair): ?object
    {
        if (Schema::hasColumn('repair_orders', 'account_receivable_id') && ! empty($repair->account_receivable_id)) {
            $found = DB::table('account_receivables')
                ->where('id', (int) $repair->account_receivable_id)
                ->first();

            if ($found) {
                return $found;
            }
        }

        return DB::table('account_receivables')
            ->where('source_type', 'service_repair_order')
            ->where('source_id', (int) $repair->id)
            ->first();
    }

    protected static function linkRepairToReceivable(object $repair, int $receivableId, int $createdBy, bool $createdNow): void
    {
        $payload = [
            'account_receivable_id' => $receivableId,
            'economic_status' => 'receivable_created',
            'receivable_created_at' => Carbon::now(),
            'receivable_created_by' => $createdBy > 0 ? $createdBy : null,
            'updated_at' => Carbon::now(),
        ];

        if (! $createdNow && ! empty($repair->receivable_created_at)) {
            unset($payload['receivable_created_at']);
        }

        DB::table('repair_orders')
            ->where('id', (int) $repair->id)
            ->update(self::safePayload('repair_orders', $payload));
    }

    protected static function nextReceivableNumber(int $companyId, string $issueDate): string
    {
        $prefix = 'CXC-SRV-' . Carbon::parse($issueDate)->format('Ymd') . '-';

        $last = DB::table('account_receivables')
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if ($last && preg_match('/-(\d+)$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    protected static function serviceCase(object $repair): ?object
    {
        if (! Schema::hasTable('service_cases') || empty($repair->service_case_id)) {
            return null;
        }

        return DB::table('service_cases')
            ->where('id', (int) $repair->service_case_id)
            ->first();
    }

    protected static function customerContact(object $repair, ?object $serviceCase): ?object
    {
        if (! Schema::hasTable('contacts')) {
            return null;
        }

        $candidateIds = [];

        foreach ([$serviceCase, $repair] as $source) {
            if (! $source) {
                continue;
            }

            foreach ([
                'customer_contact_id',
                'contact_id',
                'client_contact_id',
                'customer_id',
                'client_id',
            ] as $column) {
                if (isset($source->{$column}) && (int) $source->{$column} > 0) {
                    $candidateIds[] = (int) $source->{$column};
                }
            }
        }

        foreach (array_unique($candidateIds) as $contactId) {
            $contact = DB::table('contacts')
                ->where('id', $contactId)
                ->first();

            if ($contact) {
                return $contact;
            }
        }

        return DB::table('contacts')
            ->where('company_id', (int) ($repair->company_id ?? 0))
            ->where('is_customer', true)
            ->where(function ($query): void {
                $query->whereRaw('upper(coalesce(rfc, \'\')) = ?', ['XAXX010101000'])
                    ->orWhereRaw('upper(coalesce(name, \'\')) like ?', ['%PUBLICO%GENERAL%']);
            })
            ->orderBy('id')
            ->first();
    }

    protected static function customerName(object $repair, ?object $serviceCase, ?object $contact): string
    {
        if ($contact) {
            $name = trim((string) (($contact->commercial_name ?? '') ?: ($contact->name ?? '') ?: ($contact->fiscal_name ?? '')));

            if ($name !== '') {
                $rfc = trim((string) ($contact->rfc ?? ''));

                return $rfc !== '' ? "{$name} ({$rfc})" : $name;
            }
        }

        foreach ([$serviceCase, $repair] as $source) {
            if (! $source) {
                continue;
            }

            foreach ([
                'customer_name',
                'client_name',
                'contact_name',
                'delivered_to',
            ] as $column) {
                if (isset($source->{$column}) && trim((string) $source->{$column}) !== '') {
                    return trim((string) $source->{$column});
                }
            }
        }

        return 'Cliente servicio';
    }

    protected static function logEvent(object $repair, array $payload): void
    {
        if (! Schema::hasTable('service_case_events')) {
            return;
        }

        $row = [
            'company_id' => $repair->company_id ?? null,
            'service_case_id' => $repair->service_case_id ?? null,
            'repair_order_id' => $repair->id ?? null,
            'event_type' => $payload['event_type'] ?? 'account_receivable_created',
            'description' => $payload['description'] ?? null,
            'data' => isset($payload['data']) ? json_encode($payload['data'], JSON_UNESCAPED_UNICODE) : null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        $safeRow = self::safePayload('service_case_events', $row);

        if ($safeRow !== []) {
            DB::table('service_case_events')->insert($safeRow);
        }
    }

    protected static function safePayload(string $table, array $payload): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $columns = Schema::getColumnListing($table);

        return array_filter(
            $payload,
            fn ($value, string $column): bool => in_array($column, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    protected static function number(mixed $value): float
    {
        return is_numeric($value)
            ? (float) $value
            : (float) str_replace([',', '$', ' '], '', (string) $value);
    }
}
