<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAttachment extends Model
{
    protected $fillable = [
        'company_id',
        'service_case_id',
        'repair_order_id',
        'uploaded_by',
        'stage',
        'file_name',
        'file_path',
        'mime_type',
        'size',
        'is_customer_visible',
        'notes',
    ];

    protected $casts = [
        'is_customer_visible' => 'boolean',
    ];

    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }
}
