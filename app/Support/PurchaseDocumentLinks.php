<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseDocumentLinks
{
    public static function orderForRequest(int $purchaseRequestId): ?object
    {
        if ($purchaseRequestId <= 0 || ! Schema::hasTable('purchase_orders')) {
            return null;
        }

        return DB::table('purchase_orders')
            ->where('purchase_request_id', $purchaseRequestId)
            ->orderByDesc('id')
            ->first();
    }

    public static function requestForOrder(object|int $order): ?object
    {
        if (! Schema::hasTable('purchase_requests')) {
            return null;
        }

        if (is_int($order)) {
            $order = DB::table('purchase_orders')->where('id', $order)->first();

            if (! $order) {
                return null;
            }
        }

        $requestId = (int) ($order->purchase_request_id ?? 0);

        if ($requestId > 0) {
            return DB::table('purchase_requests')
                ->where('id', $requestId)
                ->first();
        }

        $origin = trim((string) ($order->origin ?? ''));

        if ($origin !== '') {
            return DB::table('purchase_requests')
                ->where('number', $origin)
                ->first();
        }

        return null;
    }

    public static function orderNumberForRequest(int $purchaseRequestId): ?string
    {
        return static::orderForRequest($purchaseRequestId)?->number;
    }

    public static function orderStatusForRequest(int $purchaseRequestId): ?string
    {
        return static::orderForRequest($purchaseRequestId)?->status;
    }

    public static function requestNumberForOrder(object|int $order): ?string
    {
        return static::requestForOrder($order)?->number;
    }

    public static function orderUrlFromRequest(int $purchaseRequestId, ?object $fallbackRequest = null): string
    {
        $order = static::orderForRequest($purchaseRequestId);

        if (! $order) {
            return '#';
        }

        $tenantId = static::tenantId($order, $fallbackRequest);

        return url('/admin/' . $tenantId . '/purchase-orders/' . $order->id . '/edit');
    }

    public static function requestUrlFromOrder(object|int $order): string
    {
        if (is_int($order)) {
            $order = DB::table('purchase_orders')->where('id', $order)->first();

            if (! $order) {
                return '#';
            }
        }

        $request = static::requestForOrder($order);

        if (! $request) {
            return '#';
        }

        $tenantId = static::tenantId($request, $order);

        return url('/admin/' . $tenantId . '/purchase-requests/' . $request->id);
    }

    public static function tenantId(?object ...$records): int
    {
        foreach ($records as $record) {
            if ($record && (int) ($record->company_id ?? 0) > 0) {
                return (int) $record->company_id;
            }
        }

        $tenant = request()->route('tenant');

        if (is_numeric($tenant) && (int) $tenant > 0) {
            return (int) $tenant;
        }

        return (int) (auth()->user()?->company_id ?? 0);
    }
}
