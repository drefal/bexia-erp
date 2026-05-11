<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosStaffAssignment extends Model
{
    protected $table = 'pos_point_employee';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'can_create_ticket' => 'boolean',
        'can_charge' => 'boolean',
        'can_discount' => 'boolean',
        'can_cancel' => 'boolean',
        'can_open_cash_drawer' => 'boolean',
        'max_discount_percent' => 'decimal:2',
    ];
}
