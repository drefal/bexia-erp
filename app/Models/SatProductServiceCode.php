<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SatProductServiceCode extends Model
{
    protected $fillable = [
        'code',
        'description',
        'is_active',
        'include_iva',
        'include_ieps',
        'required_complement',
        'border_stimulus',
        'similar_words',
        'valid_from',
        'valid_to',
];

    protected $casts = [
        'is_active' => 'boolean',
        'include_iva' => 'boolean',
        'include_ieps' => 'boolean',
        'border_stimulus' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',];
}
