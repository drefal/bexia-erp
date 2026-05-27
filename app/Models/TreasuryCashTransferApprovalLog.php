<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasuryCashTransferApprovalLog extends Model
{
    protected $fillable = [
        'company_id',
        'treasury_cash_transfer_request_id',
        'action',
        'from_status',
        'to_status',
        'user_id',
        'signer_name',
        'signature_hash',
        'ip_address',
        'user_agent',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function transferRequest(): BelongsTo
    {
        return $this->belongsTo(TreasuryCashTransferRequest::class, 'treasury_cash_transfer_request_id');
    }
}
