<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestStatusLog extends Model
{
    protected $fillable = [
        'purchase_request_id',
        'company_id',
        'user_id',
        'user_name',
        'from_status',
        'to_status',
        'event',
        'notes',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }
}
