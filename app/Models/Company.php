<?php

namespace App\Models;

use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Company extends Model implements HasAvatar
{
    protected $fillable = [
        'name',
        'business_name',
        'slug',
        'active',
        'default_warehouse_id',
        'default_location_id',
        'organization_id',
        'company_group_id',
        'tax_id',
        'tax_regime',
        'fiscal_postal_code',
        'billing_pac_provider',
        'billing_pac_username',
        'billing_pac_password',
        'billing_pac_test_env',
        'billing_trusted_exporter_number',
        'billing_pac_last_test_status',
        'billing_pac_last_test_message',
        'billing_pac_last_test_at',
        'street',
        'ext_number',
        'int_number',
        'neighborhood',
        'municipality',
        'city',
        'state',
        'country',
        'contact_name',
        'contact_phone',
        'contact_email',
        'max_branches',
        'max_users',
        'free_trial',
        'membership_status',
        'paid_until_at',
        'last_payment_at',
        'logo_path',
        'logo_compact_path',
        'favicon_path',
            'billing_csd_certificate_path',
        'billing_csd_key_path',
        'billing_csd_password',
        'billing_csd_serial_number',
        'billing_csd_rfc',
        'billing_csd_valid_from',
        'billing_csd_valid_to',
        'billing_csd_last_test_status',
        'billing_csd_last_test_message',
        'billing_csd_last_test_at',
];

    protected $casts = [
        'active' => 'boolean',
        'free_trial' => 'boolean',
        'max_branches' => 'integer',
        'max_users' => 'integer',
        'paid_until_at' => 'date',
        'last_payment_at' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Organization::class);
    }

    public function companyGroup(): BelongsTo
    {
        return $this->belongsTo(\App\Models\CompanyGroup::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(\App\Models\Branch::class);
    }

    public function getLogoUrl(): ?string
    {
        return filled($this->logo_path)
            ? Storage::disk('public')->url($this->logo_path)
            : null;
    }

    public function getCompactLogoUrl(): ?string
    {
        return filled($this->logo_compact_path)
            ? Storage::disk('public')->url($this->logo_compact_path)
            : null;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if (filled($this->logo_compact_path)) {
            return Storage::disk('public')->url($this->logo_compact_path);
        }

        if (filled($this->logo_path)) {
            return Storage::disk('public')->url($this->logo_path);
        }

        return null;
    }

    public function exitWarehouses(): HasMany
    {
        return $this->hasMany(ExitWarehouse::class);
    }


    public function exitProjects(): HasMany
    {
        return $this->hasMany(ExitProject::class);
    }


    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Product::class);
    }


    public function productTemplates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ProductTemplate::class);
    }


    public function productAttributes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ProductAttribute::class);
    }


    public function productAttributeValues(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ProductAttributeValue::class);
    }


    public function productAttributeAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ProductAttributeAssignment::class);
    }


    public function productImages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ProductImage::class);
    }

    public function saleOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\SaleOrder::class, 'company_id');
    }


    public function salesPriceLists(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\SalesPriceList::class, 'company_id');
    }

    public function invoices()
    {
        return $this->hasMany(\App\Models\Invoice::class);
    }

    public function banks(): HasMany
    {
        return $this->hasMany(Bank::class);
    }

    public function treasuryAccounts(): HasMany
    {
        return $this->hasMany(TreasuryAccount::class);
    }

    public function treasuryMovements(): HasMany
    {
        return $this->hasMany(TreasuryMovement::class);
    }


}
