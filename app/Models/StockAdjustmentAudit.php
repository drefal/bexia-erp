<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustmentAudit extends Model
{
    protected $fillable = [
        'company_id',
        'stock_adjustment_id',
        'stock_adjustment_line_id',
        'user_id',
        'event',
        'description',
        'before_data',
        'after_data',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
        'metadata' => 'array',
    ];

    // BEXIA_V5729Z_STOCK_ADJUSTMENT_AUDIT_COMPANY_RELATION
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

}
