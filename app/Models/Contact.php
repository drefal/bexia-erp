<?php

namespace App\Models;

use App\Models\Concerns\ArchivesContactChildren;
use App\Models\Concerns\ValidatesUniqueContactRfc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use ArchivesContactChildren;

    use SoftDeletes;
    use ValidatesUniqueContactRfc;

    protected $fillable = [
        'company_id',
        'parent_contact_id',
        'contact_type',
        'address_type',
        'name',
        'commercial_name',
        'fiscal_name',
        'is_customer',
        'is_supplier',
        'supplier_payment_terms_text',
        'supplier_currency_code',
        'supplier_payment_form_code',
        'customer_credit_limit',
        'customer_payment_terms_text',
        'supplier_payment_term_id',
        'customer_payment_term_id',
        'customer_currency_code',
        'customer_payment_form_code',
        'customer_payment_method_code',
        'customer_cfdi_use_code',
        'salesperson_user_id',
        'is_active',
        'rfc',
        'curp',
        'email',
        'phone',
        'mobile',
        'website',
        'street',
        'street2',
        'exterior_number',
        'interior_number',
        'neighborhood',
        'locality',
        'municipality',
        'city',
        'state',
        'country',
        'postal_code',
        'sat_country_code',
        'sat_tax_regime_code',
        'sat_cfdi_use_code',
        'payment_form_code',
        'payment_method_code',
        'fiscal_zip',
        'branch_name',
        'blacklisted_sat',
        'price_list_name',
        'salesperson_name',
        'sales_payment_terms',
        'delivery_method',
        'purchase_payment_terms',
        'supplier_currency',
        'supplier_reference',
        'internal_notes',
        'extra_attributes',
        'customer_price_list_id',
    ];

    protected $casts = [
        'is_customer' => 'boolean',
        'is_supplier' => 'boolean',
        'is_active' => 'boolean',
        'blacklisted_sat' => 'boolean',
        'extra_attributes' => 'array',
        'customer_credit_limit' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parentContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'parent_contact_id');
    }

    public function childContacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'parent_contact_id');
    }

    public function getDisplayNameAttribute(): string
    {
        $name = trim((string) $this->name);

        if ($this->commercial_name) {
            return $name . ' / ' . $this->commercial_name;
        }

        return $name;
    }
}
