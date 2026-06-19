<?php

namespace App\Support\Service;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServiceRepairReceivableSyncer
{
    public static function syncFromPayment(int $paymentId): array
    {
        if (! Schema::hasTable('account_receivable_payments')) {
            return [
                'synced' => false,
                'reason' => 'account_receivable_payments_missing',
            ];
        }

        $receivableId = DB::table('account_receivable_payments')
            ->where('id', $paymentId)
            ->value('account_receivable_id');

        if (! $receivableId) {
            return [
                'synced' => false,
                'reason' => 'payment_without_receivable',
                'payment_id' => $paymentId,
            ];
        }

        return self::syncFromReceivable((int) $receivableId);
    }

    public static function syncFromReceivable(int $receivableId): array
    {
        if (! Schema::hasTable('account_receivables') || ! Schema::hasTable('repair_orders')) {
            return [
                'synced' => false,
                'reason' => 'required_tables_missing',
                'account_receivable_id' => $receivableId,
            ];
        }

        return DB::transaction(function () use ($receivableId): array {
            $receivable = DB::table('account_receivables')
                ->where('id', $receivableId)
                ->lockForUpdate()
                ->first();

            if (! $receivable) {
                return [
                    'synced' => false,
                    'reason' => 'receivable_not_found',
                    'account_receivable_id' => $receivableId,
                ];
            }

            if ((string) ($receivable->source_type ?? '') !== 'service_repair_order') {
                return [
                    'synced' => false,
                    'reason' => 'not_service_repair_receivable',
                    'account_receivable_id' => $receivableId,
                    'source_type' => $receivable->source_type ?? null,
                ];
            }

            $repairId = (int) ($receivable->source_id ?? 0);

            if ($repairId <= 0) {
                return [
                    'synced' => false,
                    'reason' => 'receivable_without_repair_source',
                    'account_receivable_id' => $receivableId,
                ];
            }

            $repair = DB::table('repair_orders')
                ->where('id', $repairId)
                ->lockForUpdate()
                ->first();

            if (! $repair) {
                return [
                    'synced' => false,
                    'reason' => 'repair_not_found',
                    'account_receivable_id' => $receivableId,
                    'repair_order_id' => $repairId,
                ];
            }

            $status = (string) ($receivable->status ?? '');
            $total = round((float) ($receivable->total ?? 0), 4);
            $balance = round((float) ($receivable->balance_total ?? 0), 4);
            $collected = round((float) ($receivable->collected_total ?? 0), 4);

            $newEconomicStatus = 'receivable_created';
            $paymentStatus = 'pending';
            $paidAt = null;

            if ($status === 'paid' || ($total > 0 && $balance <= 0.0001)) {
                $newEconomicStatus = 'charged';
                $paymentStatus = 'paid';
                $paidAt = $repair->economic_paid_at ?? Carbon::now();
            } elseif ($status === 'partial' || $collected > 0) {
                $newEconomicStatus = 'partially_charged';
                $paymentStatus = 'partial';
            }

            $payload = [
                'economic_status' => $newEconomicStatus,
                'economic_payment_status' => $paymentStatus,
                'economic_payment_synced_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];

            if ($paidAt) {
                $payload['economic_paid_at'] = $paidAt;
            } elseif (Schema::hasColumn('repair_orders', 'economic_paid_at')) {
                $payload['economic_paid_at'] = null;
            }

            if (Schema::hasColumn('repair_orders', 'account_receivable_id')) {
                $payload['account_receivable_id'] = (int) $receivable->id;
            }

            DB::table('repair_orders')
                ->where('id', (int) $repair->id)
                ->update(self::safePayload('repair_orders', $payload));

            self::logEvent($repair, $receivable, $newEconomicStatus, $paymentStatus);

            return [
                'synced' => true,
                'account_receivable_id' => (int) $receivable->id,
                'receivable_number' => $receivable->number ?? null,
                'receivable_status' => $status,
                'receivable_total' => $total,
                'receivable_balance' => $balance,
                'receivable_collected' => $collected,
                'repair_order_id' => (int) $repair->id,
                'repair_folio' => $repair->folio ?? null,
                'economic_status' => $newEconomicStatus,
                'economic_payment_status' => $paymentStatus,
            ];
        });
    }

    protected static function logEvent(object $repair, object $receivable, string $economicStatus, string $paymentStatus): void
    {
        if (! Schema::hasTable('service_case_events')) {
            return;
        }

        $label = match ($paymentStatus) {
            'paid' => 'Reparación marcada como cobrada desde CxC.',
            'partial' => 'Reparación marcada con cobro parcial desde CxC.',
            default => 'Reparación sincronizada con CxC pendiente.',
        };

        $row = [
            'company_id' => $repair->company_id ?? null,
            'service_case_id' => $repair->service_case_id ?? null,
            'repair_order_id' => $repair->id ?? null,
            'event_type' => 'service_repair_receivable_synced',
            'description' => $label . ' ' . ($receivable->number ?? ''),
            'data' => json_encode([
                'account_receivable_id' => $receivable->id ?? null,
                'account_receivable_number' => $receivable->number ?? null,
                'account_receivable_status' => $receivable->status ?? null,
                'balance_total' => $receivable->balance_total ?? null,
                'economic_status' => $economicStatus,
                'payment_status' => $paymentStatus,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        $safe = self::safePayload('service_case_events', $row);

        if ($safe !== []) {
            DB::table('service_case_events')->insert($safe);
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
}
