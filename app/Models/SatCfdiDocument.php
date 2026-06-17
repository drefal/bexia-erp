<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SatCfdiDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'sat_download_package_id',
        'imported_by_id',
        'uuid',
        'direction',
        'cfdi_type',
        'status',
        'version',
        'issuer_rfc',
        'issuer_name',
        'receiver_rfc',
        'receiver_name',
        'issued_at',
        'certified_at',
        'cancelled_at',
        'currency',
        'exchange_rate',
        'subtotal',
        'discount',
        'total_transferred_taxes',
        'total_withheld_taxes',
        'total',
        'payment_form',
        'payment_method',
        'usage_cfdi',
        'export_status',
        'xml_path',
        'xml_sha256',
        'source',
        'imported_at',
        'metadata',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'certified_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'imported_at' => 'datetime',
        'exchange_rate' => 'decimal:6',
        'subtotal' => 'decimal:6',
        'discount' => 'decimal:6',
        'total_transferred_taxes' => 'decimal:6',
        'total_withheld_taxes' => 'decimal:6',
        'total' => 'decimal:6',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SatDownloadPackage::class, 'sat_download_package_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_id');
    }

    public function concepts(): HasMany
    {
        return $this->hasMany(SatCfdiConcept::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(SatCfdiTax::class);
    }
}
