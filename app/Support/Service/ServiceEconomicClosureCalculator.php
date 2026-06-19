<?php

namespace App\Support\Service;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServiceEconomicClosureCalculator
{
    public static function recalculate(int|object $repairOrder, array $options = []): array
    {
        $repairId = is_object($repairOrder)
            ? (int) ($repairOrder->id ?? $repairOrder->getKey())
            : (int) $repairOrder;

        $repair = DB::table('repair_orders')->where('id', $repairId)->first();

        if (! $repair) {
            throw new \RuntimeException("No existe la reparación {$repairId}.");
        }

        $taxRate = self::number($options['tax_rate'] ?? ($repair->economic_tax_rate ?? 16));
        $closedBy = $options['closed_by'] ?? null;
        $close = (bool) ($options['close'] ?? true);
        $reason = trim((string) ($options['reason'] ?? ''));

        $parts = self::calculateParts($repairId);
        $labor = self::calculateLabor($repair);

        $economicSubtotal = round($parts['sale_total'] + $labor['sale_total'], 2);
        $economicTax = round($economicSubtotal * ($taxRate / 100), 2);
        $economicTotal = round($economicSubtotal + $economicTax, 2);

        $totalCost = round($parts['cost_total'] + $labor['cost_total'], 2);
        $totalProfit = round($economicSubtotal - $totalCost, 2);
        $totalProfitPercent = $totalCost > 0
            ? round(($totalProfit / $totalCost) * 100, 2)
            : null;

        $approvedTotal = self::firstPositive([
            $repair->approved_total ?? null,
            $repair->budget_total ?? null,
            $repair->total_amount ?? null,
        ]);

        $differenceAmount = $approvedTotal > 0
            ? round($economicTotal - $approvedTotal, 2)
            : 0.0;

        $differencePercent = $approvedTotal > 0
            ? round(($differenceAmount / $approvedTotal) * 100, 2)
            : 0.0;

        $requiresApproval = $approvedTotal > 0 && $differenceAmount > 0.01;

        $now = Carbon::now();

        $hasReceivable = isset($repair->account_receivable_id) && (int) $repair->account_receivable_id > 0;

        $status = $hasReceivable
            ? 'receivable_created'
            : ($requiresApproval ? 'needs_approval' : 'ready_to_charge');

        $payload = [
            'economic_status' => $status,
            'parts_subtotal' => $parts['sale_total'],
            'parts_cost_total' => $parts['cost_total'],
            'parts_sale_total' => $parts['sale_total'],
            'parts_profit_amount' => $parts['profit_amount'],
            'parts_profit_percent' => $parts['profit_percent'],
            'labor_subtotal' => $labor['sale_total'],
            'labor_cost_total' => $labor['cost_total'],
            'labor_sale_total' => $labor['sale_total'],
            'labor_profit_amount' => $labor['profit_amount'],
            'labor_profit_percent' => $labor['profit_percent'],
            'economic_subtotal' => $economicSubtotal,
            'economic_tax_rate' => $taxRate,
            'economic_tax' => $economicTax,
            'economic_total' => $economicTotal,
            'total_amount' => $economicTotal,
            'total_profit_amount' => $totalProfit,
            'total_profit_percent' => $totalProfitPercent,
            'approved_total_snapshot' => $approvedTotal > 0 ? $approvedTotal : null,
            'economic_difference_amount' => $differenceAmount,
            'economic_difference_percent' => $differencePercent,
            'economic_requires_approval' => $requiresApproval,
            'economic_difference_reason' => $reason !== '' ? $reason : null,
            'ready_to_charge_at' => $requiresApproval ? null : $now,
            'updated_at' => $now,
        ];

        if ($close) {
            $payload['economic_closed_at'] = $now;
            $payload['economic_closed_by'] = $closedBy;
        }

        self::safeUpdate('repair_orders', $repairId, $payload);

        self::logEvent($repair, [
            'event_type' => 'economic_closure_calculated',
            'description' => 'Cierre económico calculado. Total final: $' . number_format($economicTotal, 2),
            'data' => [
                'parts' => $parts,
                'labor' => $labor,
                'economic_subtotal' => $economicSubtotal,
                'economic_tax' => $economicTax,
                'economic_total' => $economicTotal,
                'total_profit_amount' => $totalProfit,
                'total_profit_percent' => $totalProfitPercent,
                'requires_approval' => $requiresApproval,
            ],
        ]);

        return [
            'repair_order_id' => $repairId,
            'parts' => $parts,
            'labor' => $labor,
            'economic_subtotal' => $economicSubtotal,
            'economic_tax_rate' => $taxRate,
            'economic_tax' => $economicTax,
            'economic_total' => $economicTotal,
            'total_cost' => $totalCost,
            'total_profit_amount' => $totalProfit,
            'total_profit_percent' => $totalProfitPercent,
            'approved_total_snapshot' => $approvedTotal > 0 ? $approvedTotal : null,
            'difference_amount' => $differenceAmount,
            'difference_percent' => $differencePercent,
            'requires_approval' => $requiresApproval,
            'economic_status' => $status,
        ];
    }

    protected static function calculateParts(int $repairId): array
    {
        $parts = DB::table('repair_order_parts')
            ->where('repair_order_id', $repairId)
            ->orderBy('id')
            ->get();

        $costTotal = 0.0;
        $saleTotal = 0.0;
        $lines = [];

        foreach ($parts as $part) {
            $quantity = self::number($part->used_quantity ?? 0);

            if ($quantity <= 0) {
                $quantity = self::number($part->quantity ?? 0);
            }

            $unitCost = self::number($part->unit_cost ?? 0);
            $unitPrice = self::number($part->unit_price ?? 0);

            $lineCost = round($quantity * $unitCost, 2);
            $lineSale = round($quantity * $unitPrice, 2);
            $lineProfit = round($lineSale - $lineCost, 2);
            $lineProfitPercent = $lineCost > 0
                ? round(($lineProfit / $lineCost) * 100, 2)
                : null;

            $costTotal += $lineCost;
            $saleTotal += $lineSale;

            self::safeUpdate('repair_order_parts', (int) $part->id, [
                'economic_quantity' => $quantity,
                'line_cost_total' => $lineCost,
                'line_sale_total' => $lineSale,
                'line_profit_amount' => $lineProfit,
                'line_profit_percent' => $lineProfitPercent,
                'subtotal' => $lineSale,
                'total' => $lineSale,
                'updated_at' => Carbon::now(),
            ]);

            $lines[] = [
                'id' => (int) $part->id,
                'description' => $part->description ?? null,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'unit_price' => $unitPrice,
                'cost_total' => $lineCost,
                'sale_total' => $lineSale,
                'profit_amount' => $lineProfit,
                'profit_percent' => $lineProfitPercent,
            ];
        }

        $costTotal = round($costTotal, 2);
        $saleTotal = round($saleTotal, 2);
        $profit = round($saleTotal - $costTotal, 2);
        $profitPercent = $costTotal > 0
            ? round(($profit / $costTotal) * 100, 2)
            : null;

        return [
            'cost_total' => $costTotal,
            'sale_total' => $saleTotal,
            'profit_amount' => $profit,
            'profit_percent' => $profitPercent,
            'lines' => $lines,
        ];
    }

    protected static function calculateLabor(object $repair): array
    {
        $hours = self::firstPositive([
            $repair->actual_labor_hours ?? null,
            $repair->estimated_labor_hours ?? null,
        ]);

        $saleRate = self::number($repair->labor_hour_rate ?? 0);

        $internalRate = property_exists($repair, 'labor_internal_hour_cost') && $repair->labor_internal_hour_cost !== null
            ? self::number($repair->labor_internal_hour_cost)
            : $saleRate;

        $saleTotal = round($hours * $saleRate, 2);
        $costTotal = round($hours * $internalRate, 2);
        $profit = round($saleTotal - $costTotal, 2);
        $profitPercent = $costTotal > 0
            ? round(($profit / $costTotal) * 100, 2)
            : null;

        return [
            'hours' => $hours,
            'sale_hour_rate' => $saleRate,
            'internal_hour_cost' => $internalRate,
            'cost_total' => $costTotal,
            'sale_total' => $saleTotal,
            'profit_amount' => $profit,
            'profit_percent' => $profitPercent,
        ];
    }

    protected static function safeUpdate(string $table, int $id, array $payload): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $columns = Schema::getColumnListing($table);

        $safePayload = array_filter(
            $payload,
            fn ($value, string $column): bool => in_array($column, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );

        if ($safePayload !== []) {
            DB::table($table)->where('id', $id)->update($safePayload);
        }
    }

    protected static function logEvent(object $repair, array $payload): void
    {
        if (! Schema::hasTable('service_case_events')) {
            return;
        }

        $columns = Schema::getColumnListing('service_case_events');
        $now = Carbon::now();

        $row = [
            'company_id' => $repair->company_id ?? null,
            'service_case_id' => $repair->service_case_id ?? null,
            'repair_order_id' => $repair->id ?? null,
            'event_type' => $payload['event_type'] ?? 'economic_closure_calculated',
            'description' => $payload['description'] ?? null,
            'data' => isset($payload['data']) ? json_encode($payload['data'], JSON_UNESCAPED_UNICODE) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $safeRow = array_filter(
            $row,
            fn ($value, string $column): bool => in_array($column, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );

        if ($safeRow !== []) {
            DB::table('service_case_events')->insert($safeRow);
        }
    }

    protected static function firstPositive(array $values): float
    {
        foreach ($values as $value) {
            $number = self::number($value);

            if ($number > 0) {
                return $number;
            }
        }

        return 0.0;
    }

    protected static function number(mixed $value): float
    {
        return is_numeric($value)
            ? (float) $value
            : (float) str_replace([',', '$', ' '], '', (string) $value);
    }
}
