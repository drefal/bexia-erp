<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayrollPurchaseLine extends Model
{
    protected $fillable = [
        'company_id',
        'employee_payroll_purchase_id',
        'product_id',
        'product_sku',
        'product_reference',
        'product_name',
        'variant_name',
        'quantity',
        'unit_price_without_tax',
        'tax_rate',
        'unit_price_with_tax',
        'line_subtotal',
        'line_tax',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price_without_tax' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'unit_price_with_tax' => 'decimal:4',
        'line_subtotal' => 'decimal:2',
        'line_tax' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(EmployeePayrollPurchase::class, 'employee_payroll_purchase_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
