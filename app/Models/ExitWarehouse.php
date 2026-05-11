<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitWarehouse extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'usage_type',
        'is_active',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->code) && filled($model->company_id)) {
                $model->code = static::nextCodeForCompany((int) $model->company_id);
            }
        });
    }

    public static function nextCodeForCompany(int $companyId): string
    {
        $codes = static::query()
            ->where('company_id', $companyId)
            ->whereNotNull('code')
            ->pluck('code')
            ->all();

        $max = 0;

        foreach ($codes as $code) {
            $code = trim((string) $code);

            if ($code !== '' && ctype_digit($code)) {
                $num = (int) $code;
                if ($num > $max) {
                    $max = $num;
                }
            }
        }

        $next = $max + 1;

        if ($next > 99999) {
            $next = 99999;
        }

        return str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
