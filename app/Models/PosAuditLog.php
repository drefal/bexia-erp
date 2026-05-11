<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'user_id',
        'pos_session_id',
        'pos_order_id',
        'pos_order_refund_id',
        'stock_movement_id',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'before_data',
        'after_data',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'user_id' => 'integer',
        'pos_session_id' => 'integer',
        'pos_order_id' => 'integer',
        'pos_order_refund_id' => 'integer',
        'stock_movement_id' => 'integer',
        'entity_id' => 'integer',
        'before_data' => 'array',
        'after_data' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
