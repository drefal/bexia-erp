<?php

namespace App\Models;

use App\Models\Company;
use App\Models\SaleOrderLine;
use App\Models\SalesPriceList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SaleOrder extends Model
{
    protected $table = 'sales_orders';

    protected $fillable = [
        'company_id',
        'number',
        'status',
        'customer_contact_id',
        'delivery_contact_id',
        'customer_name',
        'price_list_id',
        'warehouse_id',
        'location_id',
        'order_date',
        'expected_delivery_date',
        'currency',
        'source_type',
        'source_id',
        'source_reference',
        'total_without_tax',
        'total_tax',
        'total_with_tax',
        'delivered_total_quantity',
        'confirmed_at',
        'confirmed_by_user_id',
        'created_by_user_id',
        'delivery_policy',
        'delivery_address',
        'billing_address',
        'payment_terms',
        'payment_method',
        'fiscal_position',
        'invoice_status',
        'payment_status',
        'salesperson_user_id',
        'sales_team',
        'crm_opportunity_reference',
        'campaign',
        'medium',
        'customer_reference',
        'margin_approval_required',
        'margin_approval_status',
        'margin_approval_user_id',
        'margin_approval_reason',
        'margin_approval_requested_at',
        'margin_approved_by_user_id',
        'margin_approved_at',
        'margin_rejected_by_user_id',
        'margin_rejected_at',
        'margin_rejection_reason',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'expected_delivery_date' => 'date',
        'confirmed_at' => 'datetime',
        'total_without_tax' => 'decimal:6',
        'total_tax' => 'decimal:6',
        'total_with_tax' => 'decimal:6',
        'delivered_total_quantity' => 'decimal:6',
    ];

    protected static function booted(): void
    {
        static::creating(function (SaleOrder $order): void {
            if (! $order->status) {
                $order->status = 'draft';
            }

            if (! $order->order_date) {
                $order->order_date = now();
            }

            if (! $order->currency) {
                $order->currency = 'MXN';
            }

            if (! $order->source_type) {
                $order->source_type = 'manual';
            }

            if (! $order->created_by_user_id && auth()->check()) {
                $order->created_by_user_id = auth()->id();
            }

            if (! $order->number && $order->company_id) {
                $order->number = static::nextNumber((int) $order->company_id);
            }
        });
    }

    public static function nextNumber(int $companyId): string
    {
        $date = now()->format('Ymd');
        $base = 'VTA-' . $date . '-';

        $last = DB::table('sales_orders')
            ->where('company_id', $companyId)
            ->where('number', 'like', $base . '%')
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if ($last && preg_match('/(\d+)$/', (string) $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $base . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }


    public function priceList(): BelongsTo
    {
        return $this->belongsTo(SalesPriceList::class, 'price_list_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleOrderLine::class, 'sales_order_id');
    }

    public function recalculateTotals(): void
    {
        if (! $this->exists) {
            return;
        }

        $totals = $this->lines()
            ->selectRaw('
                COALESCE(SUM(line_total_without_tax), 0) as subtotal,
                COALESCE(SUM(line_tax), 0) as tax,
                COALESCE(SUM(line_total_with_tax), 0) as total,
                COALESCE(SUM(delivered_quantity), 0) as delivered_qty
            ')
            ->first();

        $this->forceFill([
            'total_without_tax' => (float) ($totals->subtotal ?? 0),
            'total_tax' => (float) ($totals->tax ?? 0),
            'total_with_tax' => (float) ($totals->total ?? 0),
            'delivered_total_quantity' => (float) ($totals->delivered_qty ?? 0),
        ])->saveQuietly();
    }

    public function canBeConfirmed(): bool
    {
        return $this->status === 'draft';
    }

    public function confirm(): void
    {
        $this->recalculateTotals();

        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'confirmed_by_user_id' => auth()->id(),
        ]);
    }
}
