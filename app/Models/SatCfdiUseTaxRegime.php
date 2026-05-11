<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SatCfdiUseTaxRegime extends Model
{
    protected $table = 'sat_cfdi_use_tax_regime';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function taxRegime()
    {
        return $this->belongsTo(SatTaxRegime::class, 'tax_regime_code', 'code');
    }

    public function cfdiUse()
    {
        return $this->belongsTo(SatCfdiUse::class, 'cfdi_use_code', 'code');
    }
}
