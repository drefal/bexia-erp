<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'company_id',
        'purchase_request_id',
        'number',
        'status',
        'supplier_contact_id',
        'supplier_name',
        'warehouse_id',
        'location_id',
        'warehouse_label',
        'location_label',
        'order_date',
        'expected_date',
        'currency',
        'origin',
        'total_without_tax',
        'total_tax',
        'total_with_tax',
        'source_snapshot_hash',
        'current_hash',
        'approval_hash',
        'differs_from_request',
        'approval_required_reason',
        'submitted_for_approval_at',
        'confirmed_at',
        'confirmed_by_user_id',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'expected_date' => 'datetime',
        'total_without_tax' => 'decimal:6',
        'total_tax' => 'decimal:6',
        'total_with_tax' => 'decimal:6',
        'differs_from_request' => 'boolean',
        'submitted_for_approval_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id');
    }

    /*
     * Compatibilidad con la configuración global de tenancy de Filament,
     * que en este proyecto intenta usar "companies".
     */
    public function companies(): BelongsTo
    {
        return $this->company();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_id');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }
}
